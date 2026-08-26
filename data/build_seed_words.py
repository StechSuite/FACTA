#!/usr/bin/env python3
"""
SmartQuran — Word-by-Word Seed Generator

Downloads per-word Quran data (Arabic Uthmani text, transliteration,
English + Indonesian gloss) from api.quran.com/v4 and generates
data/seed_words_full.sql containing one row per word (~77,000 rows)
for the `ayah_words` table.

This powers the word-by-word reading view and the dynamic root/derived
-word search (includes/morphology.php) — no root is precomputed here;
matching a word to a root happens at query time, not at seed time.

Usage:
    python data/build_seed_words.py

Requires internet access only at generation time; the generated SQL
file makes the app fully offline afterwards. Re-run any time to
refresh data. Takes a few minutes (114 chapters x 2 languages, with
politeness delays and retry/backoff on transient failures).

Resumable: each chapter/language response is cached to disk under
data/.words_cache/ as it's fetched, so a network failure partway
through does not lose earlier progress — just re-run the script and
already-cached chapters are skipped. The cache is deleted automatically
after a fully successful run; delete it by hand to force a clean re-fetch.
"""

import io
import json
import os
import re
import shutil
import sys
import time
import urllib.error
import urllib.request

BASE = os.path.dirname(os.path.abspath(__file__))
OUT_FILE = os.path.join(BASE, 'seed_words_full.sql')
CACHE_DIR = os.path.join(BASE, '.words_cache')

API_CHAPTERS = 'https://api.quran.com/api/v4/chapters?language=en'
API_WORDS = ('https://api.quran.com/api/v4/verses/by_chapter/{chapter}'
             '?words=true&word_fields=text_uthmani,transliteration'
             '&language={lang}&per_page={per_page}')

BATCH = 100
RETRIES = 5
RETRY_DELAY = 1.0        # seconds, doubles each retry (1s, 2s, 4s, 8s, 16s)
REQUEST_DELAY = 0.5      # politeness delay between requests
COOLDOWN_EVERY = 15      # extra pause every N chapters, to ease sustained load
COOLDOWN_SECONDS = 3.0
TIMEOUT = 60

# Same diacritic / tatweel / invisible-mark ranges as data/build_seed.py,
# plus ta-marbuta -> ha normalization (needed for root/stem matching).
DIACRITICS_RE_CHARS = (
    'ؐ-ًؚ-ٰٟۖ-ۜ'
    '۟-۪ۨ-ۭـ࣓-ࣿ'
    '﻿‎‏'
)
DIACRITICS_RE = re.compile('[' + DIACRITICS_RE_CHARS + ']')
ALEF_RE = re.compile('[أإآٱ]')  # hamza/madda alefs -> plain alef
TA_MARBUTA_RE = re.compile('ة')                 # ة -> ه


def ar_clean(text):
    """Diacritics stripped + alef/ta-marbuta normalized, for matching."""
    t = DIACRITICS_RE.sub('', text)
    t = ALEF_RE.sub('ا', t)          # -> ا
    t = TA_MARBUTA_RE.sub('ه', t)    # -> ه
    return t.strip()


def sql_str(value):
    return "'" + value.replace("'", "''") + "'"


def http_get_json(url):
    last_err = None
    delay = RETRY_DELAY
    for attempt in range(1, RETRIES + 1):
        try:
            req = urllib.request.Request(url, headers={'User-Agent': 'SmartQuran-WordSeeder/1.0'})
            with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
                return json.load(io.TextIOWrapper(r, encoding='utf-8'))
        except (urllib.error.URLError, TimeoutError, ConnectionError, OSError) as e:
            last_err = e
            print(f'    retry {attempt}/{RETRIES} after error: {e}')
            if attempt < RETRIES:
                time.sleep(delay)
                delay *= 2
    raise RuntimeError(f'Failed to fetch {url}: {last_err}')


def get_chapter_offsets():
    print('Fetching chapter list (for global ayah-id offsets) ...')
    data = http_get_json(API_CHAPTERS)
    chapters = data['chapters']
    if len(chapters) != 114:
        raise RuntimeError(f'Expected 114 chapters, got {len(chapters)}')
    total = sum(c['verses_count'] for c in chapters)
    if total != 6236:
        raise RuntimeError(f'Expected 6236 total verses, got {total}')
    offsets = {}
    running = 0
    for c in sorted(chapters, key=lambda c: c['id']):
        offsets[c['id']] = running
        running += c['verses_count']
    print(f'  -> {len(chapters)} chapters, {total} verses confirmed. Offsets ready.')
    return offsets, {c['id']: c['verses_count'] for c in chapters}


def fetch_chapter_raw(chapter, lang, per_page):
    """Fetch one chapter/language response, using an on-disk cache so a
    later network failure doesn't force re-fetching earlier chapters."""
    os.makedirs(CACHE_DIR, exist_ok=True)
    cache_file = os.path.join(CACHE_DIR, f'ch{chapter:03d}_{lang}.json')
    if os.path.exists(cache_file):
        return json.load(io.open(cache_file, encoding='utf-8'))

    url = API_WORDS.format(chapter=chapter, lang=lang, per_page=per_page)
    data = http_get_json(url)
    time.sleep(REQUEST_DELAY)
    io.open(cache_file, 'w', encoding='utf-8').write(json.dumps(data))
    return data


def fetch_chapter_words(chapter, lang, per_page):
    data = fetch_chapter_raw(chapter, lang, per_page)
    out = {}
    for v in data['verses']:
        vnum = v['verse_number']
        for w in v['words']:
            if w.get('char_type_name') != 'word':
                continue
            text = (w.get('translation') or {}).get('text') or ''
            # Defensive: strip any stray markup, keep plain gloss text.
            text = re.sub(r'<[^>]+>', '', text).strip()
            out[(vnum, w['position'])] = {
                'text_uthmani': w.get('text_uthmani') or w.get('text') or '',
                'transliteration': (w.get('transliteration') or {}).get('text') or '',
                'translation': text,
            }
    return out


def batched_insert(out, header, rows):
    for i in range(0, len(rows), BATCH):
        out.append(header)
        out.append(',\n'.join(rows[i:i + BATCH]) + ';')
        out.append('')


def main():
    offsets, verse_counts = get_chapter_offsets()

    out = [
        '-- ============================================================',
        '-- SmartQuran — Word-by-Word Seed (GENERATED — do not edit by hand)',
        '-- Arabic (Uthmani) + transliteration + English + Indonesian gloss',
        '-- per word, full Quran. Regenerate: python data/build_seed_words.py',
        '-- Source: api.quran.com/v4 (verses/by_chapter, words=true)',
        '-- NOTE: root_word_id is intentionally left NULL. Root/derived-word',
        '-- matching happens dynamically at query time (includes/morphology.php),',
        '-- not via a precomputed link — see README.md.',
        '-- ============================================================',
        '',
        'BEGIN TRANSACTION;',
        '',
    ]

    rows = []
    mismatch_warnings = 0
    for chapter in range(1, 115):
        per_page = verse_counts[chapter]
        print(f'[{chapter:3d}/114] fetching words (en) ...')
        en_words = fetch_chapter_words(chapter, 'en', per_page)
        print(f'[{chapter:3d}/114] fetching words (id) ...')
        id_words = fetch_chapter_words(chapter, 'id', per_page)

        offset = offsets[chapter]
        for key in sorted(en_words.keys()):
            vnum, pos = key
            en = en_words[key]
            idw = id_words.get(key)
            if idw is None:
                print(f'    WARNING: missing id-pass word at {chapter}:{vnum} pos {pos}')
                continue
            if en['text_uthmani'] != idw['text_uthmani']:
                mismatch_warnings += 1
                if mismatch_warnings <= 10:
                    print(f'    WARNING: text mismatch between en/id pass at {chapter}:{vnum} pos {pos}')

            ayah_id = offset + vnum
            text_ar = en['text_uthmani']
            rows.append('({aid}, {pos}, {t}, {tc}, {tr}, {en}, {idt})'.format(
                aid=ayah_id,
                pos=pos,
                t=sql_str(text_ar),
                tc=sql_str(ar_clean(text_ar)),
                tr=sql_str(en['transliteration']),
                en=sql_str(en['translation']),
                idt=sql_str(idw['translation']),
            ))

        if chapter % COOLDOWN_EVERY == 0:
            time.sleep(COOLDOWN_SECONDS)

    batched_insert(out,
        'INSERT OR REPLACE INTO ayah_words (ayah_id, position, text_ar, '
        'text_ar_clean, transliteration, translation_en, translation_id) VALUES',
        rows)

    out.append('COMMIT;')
    out.append('')

    io.open(OUT_FILE, 'w', encoding='utf-8', newline='\n').write('\n'.join(out))
    size_mb = os.path.getsize(OUT_FILE) / 1024 / 1024
    print(f'Written {OUT_FILE} ({size_mb:.1f} MB)')
    print(f'  words: {len(rows)}, en/id text mismatches: {mismatch_warnings}')

    shutil.rmtree(CACHE_DIR, ignore_errors=True)
    print('Cache cleaned up.')


if __name__ == '__main__':
    sys.exit(main())
