#!/usr/bin/env python3
"""
SmartQuran — Full Quran Seed Generator

Downloads the complete Quran (114 surahs, 6236 ayahs) from api.alquran.cloud
and generates data/seed_quran_full.sql containing:
  - all 114 surahs with correct metadata (pages, juz, revelation type)
  - all 6236 ayahs (Uthmani script + simple/clean text for FTS search)
  - Indonesian (Kemenag) and English (Saheeh International) translations

Usage:
    python data/build_seed.py

Requires internet access only at generation time; the generated SQL file
makes the app fully offline afterwards. Re-run any time to refresh data.

Indonesian surah names come from data/surah_names_id.json (falls back to
the English name if missing).
"""

import io
import json
import os
import re
import sys
import urllib.request

BASE = os.path.dirname(os.path.abspath(__file__))
OUT_FILE = os.path.join(BASE, 'seed_quran_full.sql')
NAMES_FILE = os.path.join(BASE, 'surah_names_id.json')

API = 'https://api.alquran.cloud/v1/quran/{}'
EDITIONS = {
    'ar': ('quran-uthmani', None, None),
    'id': ('id.indonesian', 'Kemenag', 'Al-Quran Kemenag RI'),
    'en': ('en.sahih', 'Saheeh International', 'Quran.com'),
}

BATCH = 100  # rows per INSERT statement (keeps statements well under SQLite limits)

# Arabic diacritics / quranic annotation marks to strip for the "simple" text
DIACRITICS_RE = re.compile(
    '[ؐ-ًؚ-ٰٟۖ-ۜ'
    '۟-۪ۨ-ۭـ࣓-ࣿ'
    '﻿‎‏]'
)
ALEF_RE = re.compile('[أإآٱ]')  # hamza/madda alefs -> plain alef

# Basmala in simple (diacritic-free) form, word by word
BASMALA_WORDS = ['بسم', 'الله',
                 'الرحمن',
                 'الرحيم']


def fetch(edition):
    url = API.format(edition)
    print(f'Downloading {url} ...')
    req = urllib.request.Request(url, headers={'User-Agent': 'SmartQuran-Seeder/1.0'})
    with urllib.request.urlopen(req, timeout=120) as r:
        payload = json.load(io.TextIOWrapper(r, encoding='utf-8'))
    if payload.get('code') != 200:
        raise RuntimeError(f'API error for {edition}: {payload.get("status")}')
    surahs = payload['data']['surahs']
    total = sum(len(s['ayahs']) for s in surahs)
    print(f'  -> {len(surahs)} surahs, {total} ayahs')
    return surahs


def simple_text(text):
    # strip diacritics; alef wasla renders/types as plain alef, normalize it
    # so FTS queries typed with a regular alef match
    return DIACRITICS_RE.sub('', text).replace('ٱ', 'ا').strip()


def clean_text(text):
    return ALEF_RE.sub('ا', simple_text(text))


def sql_str(value):
    return "'" + value.replace("'", "''") + "'"


def strip_basmala(surah_number, ayah_in_surah, text):
    """Ayah 1 of every surah except Al-Fatihah (1) and At-Tawbah (9) has the
    basmala prepended in the source edition; store it without the prefix."""
    text = text.lstrip('﻿').strip()
    if ayah_in_surah == 1 and surah_number not in (1, 9):
        words = text.split()
        if len(words) > 4 and [clean_text(w) for w in words[:4]] == BASMALA_WORDS:
            return ' '.join(words[4:])
    return text


def sajda_value(sajda):
    if not sajda:
        return 0
    if isinstance(sajda, dict):
        if sajda.get('obligatory'):
            return 1
        return 2
    return 2


def batched_insert(out, header, rows):
    for i in range(0, len(rows), BATCH):
        out.append(header)
        out.append(',\n'.join(rows[i:i + BATCH]) + ';')
        out.append('')


def main():
    try:
        names_id = {int(k): v for k, v in json.load(
            io.open(NAMES_FILE, encoding='utf-8')).items()}
    except (OSError, ValueError):
        print('WARNING: surah_names_id.json missing/invalid; using English names')
        names_id = {}

    ar = fetch(EDITIONS['ar'][0])
    tr_id = fetch(EDITIONS['id'][0])
    tr_en = fetch(EDITIONS['en'][0])

    out = [
        '-- ============================================================',
        '-- SmartQuran — Full Quran Seed (GENERATED — do not edit by hand)',
        '-- 114 surahs, 6236 ayahs, Indonesian (Kemenag) + English (Saheeh',
        '-- International) translations. Regenerate: python data/build_seed.py',
        '-- Source: api.alquran.cloud (quran-uthmani, id.indonesian, en.sahih)',
        '-- ============================================================',
        '',
        'BEGIN TRANSACTION;',
        '',
    ]

    # ---- Surahs -------------------------------------------------------
    surah_rows = []
    for s in ar:
        num = s['number']
        name_ar = s['name'].replace('سُورَةُ ', '').strip()
        pages = [a['page'] for a in s['ayahs']]
        info = names_id.get(num, {})
        surah_rows.append('({n}, {n}, {ar}, {simple}, {en}, {nid}, {tr}, {rev}, {vc}, {p1}, {p2}, {juz})'.format(
            n=num,
            ar=sql_str(name_ar),
            simple=sql_str(simple_text(name_ar)),
            en=sql_str(s['englishNameTranslation']),
            nid=sql_str(info.get('name_id', s['englishNameTranslation'])),
            tr=sql_str(info.get('translit', s['englishName'])),
            rev=sql_str(s['revelationType']),
            vc=len(s['ayahs']),
            p1=min(pages), p2=max(pages),
            juz=s['ayahs'][0]['juz'],
        ))
    batched_insert(out,
        'INSERT OR REPLACE INTO surahs (id, number, name_ar, name_simple, name_en, '
        'name_id, name_transliteration, revelation_type, verse_count, page_start, '
        'page_end, juz_number) VALUES',
        surah_rows)

    # ---- Ayahs (id = global ayah number 1..6236) ----------------------
    ayah_rows = []
    for s in ar:
        for a in s['ayahs']:
            text = strip_basmala(s['number'], a['numberInSurah'], a['text'])
            hq = a['hizbQuarter']
            ayah_rows.append('({id}, {sid}, {num}, {id}, {t}, {ts}, {tc}, {page}, {juz}, {hizb}, {rub}, {sajda})'.format(
                id=a['number'],
                sid=s['number'],
                num=a['numberInSurah'],
                t=sql_str(text),
                ts=sql_str(simple_text(text)),
                tc=sql_str(clean_text(text)),
                page=a['page'],
                juz=a['juz'],
                hizb=(hq + 3) // 4,
                rub=((hq - 1) % 4) + 1,
                sajda=sajda_value(a.get('sajda')),
            ))
    batched_insert(out,
        'INSERT OR REPLACE INTO ayahs (id, surah_id, ayah_number, global_number, '
        'text_ar, text_ar_simple, text_ar_clean, page_number, juz_number, '
        'hizb_number, rub_number, sajda) VALUES',
        ayah_rows)

    # ---- Translations -------------------------------------------------
    for lang, surahs in (('id', tr_id), ('en', tr_en)):
        _, translator, source = EDITIONS[lang]
        rows = []
        for s in surahs:
            for a in s['ayahs']:
                rows.append('({aid}, {lang}, {text}, {tr}, {src})'.format(
                    aid=a['number'],
                    lang=sql_str(lang),
                    text=sql_str(a['text'].strip()),
                    tr=sql_str(translator),
                    src=sql_str(source),
                ))
        batched_insert(out,
            'INSERT OR REPLACE INTO translations (ayah_id, language_code, text, '
            'translator, source) VALUES',
            rows)

    out.append('COMMIT;')
    out.append('')

    io.open(OUT_FILE, 'w', encoding='utf-8', newline='\n').write('\n'.join(out))
    size_mb = os.path.getsize(OUT_FILE) / 1024 / 1024
    print(f'Written {OUT_FILE} ({size_mb:.1f} MB)')
    print(f'  surahs: {len(surah_rows)}, ayahs: {len(ayah_rows)}, '
          f'translations: {len(ayah_rows) * 2}')


if __name__ == '__main__':
    sys.exit(main())
