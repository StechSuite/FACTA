#!/usr/bin/env python3
"""
SmartQuran — Word-Root Import Seed Generator (v1.01.Alpha.016)

Downloads the Quranic Arabic Corpus morphology data (fork:
mustafa0x/quran-morphology on GitHub — a refined re-export of
Quranic Arabic Corpus v0.4, corpus.quran.com, licensed GPLv3) and
generates data/seed_word_roots.sql: root words for EVERY triliteral
root tagged anywhere in the Quran (~1,700+, vs. the 160 hand-curated
ones in seed_roots.sql) plus the exact word<->root occurrence map,
for the ayah_root_words table (multi-level co-occurrence tree,
backlog-1.01.Alpha.016.md).

Source data license: GPLv3 (see corpus.quran.com/license.jsp), traced
back to Quranic Arabic Corpus by Kais Dukes. Attribution is written
into the generated SQL file's header comment. This repo is private,
so committing the derived seed file here does not constitute public
"conveying" under GPLv3; if this ever changes (e.g. repo goes public),
re-review that license before keeping this file as-is.

New roots are inserted with source='imported' and NULL meaning_ar/
meaning_en/meaning_id — see data/words-kurator-by-ai/ for the AI-
assisted curation tool that fills those in afterwards.

Usage:
    python data/build_word_roots_import.py

Requires internet access only at generation time (fetches the chapter
list from api.quran.com/v4 for ayah-id offsets, and the morphology
data from raw.githubusercontent.com). Re-run any time to refresh.
"""

import io
import json
import os
import re
import time
import urllib.error
import urllib.request

BASE = os.path.dirname(os.path.abspath(__file__))
OUT_FILE = os.path.join(BASE, 'seed_word_roots.sql')
CACHE_FILE = os.path.join(BASE, '.quran-morphology-source.txt')

API_CHAPTERS = 'https://api.quran.com/api/v4/chapters?language=en'
MORPHOLOGY_URL = ('https://raw.githubusercontent.com/mustafa0x/'
                   'quran-morphology/master/quran-morphology.txt')

RETRIES = 5
RETRY_DELAY = 1.0
TIMEOUT = 60

ROOT_TAG_RE = re.compile(r'ROOT:([^|]+)')
LOCATION_RE = re.compile(r'^(\d+):(\d+):(\d+):(\d+)$')


def http_get_text(url):
    last_err = None
    delay = RETRY_DELAY
    for attempt in range(1, RETRIES + 1):
        try:
            req = urllib.request.Request(url, headers={'User-Agent': 'SmartQuran-RootImport/1.0'})
            with urllib.request.urlopen(req, timeout=TIMEOUT) as r:
                return r.read().decode('utf-8')
        except (urllib.error.URLError, TimeoutError, ConnectionError, OSError) as e:
            last_err = e
            print(f'    retry {attempt}/{RETRIES} after error: {e}')
            if attempt < RETRIES:
                time.sleep(delay)
                delay *= 2
    raise RuntimeError(f'Failed to fetch {url}: {last_err}')


def get_chapter_offsets():
    print('Fetching chapter list (for global ayah-id offsets) ...')
    data = json.loads(http_get_text(API_CHAPTERS))
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
    print(f'  -> {len(chapters)} chapters, {total} verses confirmed.')
    return offsets


def fetch_morphology_source():
    if os.path.exists(CACHE_FILE):
        print(f'Using cached morphology source ({CACHE_FILE}).')
        return io.open(CACHE_FILE, encoding='utf-8').read()
    print(f'Fetching {MORPHOLOGY_URL} ...')
    text = http_get_text(MORPHOLOGY_URL)
    io.open(CACHE_FILE, 'w', encoding='utf-8').write(text)
    return text


def sql_str(value):
    return "'" + value.replace("'", "''") + "'"


def parse_morphology(text):
    """Group segments by (surah, ayah, word) -> (word_form, root|None)."""
    words = {}  # (s,a,w) -> {'segments': [(seg_idx, text)], 'root': str|None}
    for line in text.split('\n'):
        line = line.rstrip('\n')
        if not line or line.startswith('#'):
            continue
        parts = line.split('\t')
        if len(parts) < 4:
            continue
        loc, seg_text, pos, tags = parts[0], parts[1], parts[2], parts[3]
        m = LOCATION_RE.match(loc)
        if not m:
            continue
        s, a, w, seg = (int(x) for x in m.groups())
        key = (s, a, w)
        entry = words.setdefault(key, {'segments': [], 'root': None})
        entry['segments'].append((seg, seg_text))
        rm = ROOT_TAG_RE.search(tags)
        if rm and not entry['root']:
            entry['root'] = rm.group(1).strip()
    return words


def batched(out, header, rows, size=500):
    for i in range(0, len(rows), size):
        out.append(header)
        out.append(',\n'.join(rows[i:i + size]) + ';')
        out.append('')


def main():
    offsets = get_chapter_offsets()
    source = fetch_morphology_source()
    print('Parsing morphology data ...')
    words = parse_morphology(source)
    print(f'  -> {len(words)} words parsed.')

    roots_seen = {}       # root_ar -> occurrence count (words)
    word_rows = []         # (ayah_id, root_ar, word_form, position)
    words_with_root = 0

    for (s, a, w), entry in words.items():
        if not entry['root']:
            continue
        words_with_root += 1
        root_ar = entry['root']
        roots_seen[root_ar] = roots_seen.get(root_ar, 0) + 1
        ayah_id = offsets[s] + a
        word_form = ''.join(t for _, t in sorted(entry['segments']))
        word_rows.append((ayah_id, root_ar, word_form, w))

    print(f'  -> {words_with_root} words carry a root tag, '
          f'{len(roots_seen)} distinct roots.')

    out = [
        '-- ============================================================',
        '-- SmartQuran — Word-Root Import Seed (GENERATED — do not edit by hand)',
        '-- Regenerate: python data/build_word_roots_import.py',
        '--',
        '-- Source: Quranic Arabic Corpus morphology (corpus.quran.com,',
        '-- Kais Dukes), via the mustafa0x/quran-morphology re-export on',
        '-- GitHub. Licensed GNU GPLv3 — see corpus.quran.com/license.jsp.',
        '-- Root tags for ~1,700+ triliteral roots across all 77,430',
        '-- tagged Quran words, vs. the 160 hand-curated roots in',
        '-- seed_roots.sql (which keep their curated meanings; this file',
        '-- only INSERT OR IGNOREs, never overwrites).',
        '--',
        '-- New roots inserted with source=\'imported\', meaning_* left',
        '-- NULL — see data/words-kurator-by-ai/ to curate them via AI.',
        '-- ============================================================',
        '',
        'BEGIN TRANSACTION;',
        '',
    ]

    root_rows = [
        '({ar}, {freq}, {src})'.format(
            ar=sql_str(root_ar), freq=roots_seen[root_ar], src=sql_str('imported')
        )
        for root_ar in sorted(roots_seen.keys())
    ]
    batched(out, 'INSERT OR IGNORE INTO root_words (root_ar, frequency, source) VALUES', root_rows)

    map_rows = [
        '({aid}, (SELECT id FROM root_words WHERE root_ar={rar}), {wf}, {pos})'.format(
            aid=ayah_id, rar=sql_str(root_ar), wf=sql_str(word_form), pos=pos
        )
        for (ayah_id, root_ar, word_form, pos) in word_rows
    ]
    batched(out,
            'INSERT OR IGNORE INTO ayah_root_words (ayah_id, root_word_id, word_form, position) VALUES',
            map_rows)

    out.append('COMMIT;')
    out.append('')

    io.open(OUT_FILE, 'w', encoding='utf-8', newline='\n').write('\n'.join(out))
    size_mb = round(os.path.getsize(OUT_FILE) / 1048576, 1)
    print(f'Wrote {OUT_FILE} ({size_mb} MB): {len(root_rows)} roots, {len(map_rows)} word-root links.')


if __name__ == '__main__':
    main()
