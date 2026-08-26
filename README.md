# FACTA

**F**inding **A**ssociation in **C**ollection of **T**ext **A**l-Quran — a Quran reading & study app whose core feature is tracing relationships between Arabic root words across all 6,236 verses of the Quran.

🔗 **Live demo:** https://aiquran.diasoft.web.id/
📘 **Full user guide** (in-app, 3 languages, works even without seed data): open the app and click the ❓ button, or read [`includes/guide_content.php`](includes/guide_content.php) directly.

## Where this comes from

FACTA's core idea isn't new — it traces back to a final-year thesis project (*Tugas Akhir*) at the Informatics Engineering department (IF), Institut Teknologi Bandung (ITB), completed around 2004 by **Hendi Wibowo** (student ID 13599039). That original version was written in Java (J2SE), with a JBuilder-built UI, a simple language model stored via binary serialization of a matrix of `BitSet`, and its text corpus kept as plain text files.

This repository is a full re-engineering — modern architecture and data structures, built with AI assistance — that deliberately keeps the same underlying logic, search algorithm, and product vision as that 2004 original.

## Features

- **Root-word co-occurrence explorer** ("Muncul Bersama") — for any Arabic root, browse every other root that co-occurs with it across the Quran, drill down through multiple roots at once (breadcrumb or tree view), and jump straight to the matching verses.
- **AND-search builder** — assemble a search for verses containing several root-word concepts at once, with automatic disambiguation when a typed word matches more than one root.
- **Root-word search** — find every derived form of a 2-4 letter Arabic root via a light, runtime morphology matcher (no precomputed word→root table).
- **Full-text search** — SQLite FTS5 over Arabic text and translations (English/Indonesian).
- **Word Info popup** — derived forms, synonyms, antonyms, related roots, and the co-occurrence explorer above, all from clicking any word in a verse.
- **Reading**: surah/juz browsing, word-by-word view, adjustable reading modes (full/paged/book mode), audio recitation (2 reciters), tafsir (sample dataset), bookmarks, sharing.
- **5 UI languages**: Arabic, English, Indonesian, Sundanese, Javanese — independent from the (English/Indonesian) verse translation language.
- **Comprehensive in-app Guide** (`page=guide`) covering every feature and the data/algorithm methodology, in 3 fully-written languages.

## Tech stack

- **Backend**: PHP 8.x, no framework
- **Database**: SQLite 3 with FTS5
- **Frontend**: Vanilla JS, custom CSS, no build step
- **Deploy target**: anything that runs PHP 8 + SQLite — shared hosting (cPanel), Windows IIS, or a plain `php -S` server

## ⚠️ About the data — this repo ships code only

This repository intentionally does **not** include the seed data (Quran text, translations, or the root-word dictionary). Some of that data pipeline is built from third-party sources with their own licensing terms — most notably, part of the root-word import is derived from [`mustafa0x/quran-morphology`](https://github.com/mustafa0x/quran-morphology) (a fork of the *Quranic Arabic Corpus v0.4*), which is **GPLv3-licensed**. Redistributing that derived data under this repo's MIT license would conflict with GPLv3's copyleft terms, so it's kept out entirely rather than published in a legally ambiguous state. The Kemenag (Indonesian) and Saheeh International (English) translation texts are excluded for the same reason — their redistribution terms haven't been independently verified for this project.

**What this means in practice:**
- The application **code** (everything in this repo) is 100% original and MIT-licensed.
- To run a fully working local instance, you need to supply your own copy of the seed data — see [`data/README.md`](data/README.md) for exactly what's needed and how the existing build scripts (`build_seed.py`, `build_seed_words.py`, `build_word_roots_import.py`) turn it into the SQLite database.
- Pages that don't need the database — like the in-app Guide (`page=guide`) — work immediately, even with an empty/missing database.

## Quick start

Requirements: PHP 8+ with the `sqlite3` extension, and (only if you want to (re)generate seed data) Python 3.

```bash
git clone https://github.com/StechSuite/FACTA.git
cd FACTA
cp deploy.secrets.json.example deploy.secrets.json   # only needed if you use deploy-cpanel.bat
php -S localhost:8885
```

Then obtain/build the seed data per [`data/README.md`](data/README.md), and visit `http://localhost:8885/install.php` to build `data/smartquran.db`. Once that's done, `index.php` is the app.

## Project structure

```
FACTA/
├── index.php               # Router
├── install.php              # Database installer
├── includes/                # config, db, i18n, functions, morphology (search algorithms), guide content
├── pages/                   # home, surah, juz, search, settings, guide, ...
├── api/                     # word_info, root_lookup, bookmark, tafsir, auth, ...
├── assets/                  # css, js (vanilla, no build step), favicon
├── data/                    # schema.sql + build scripts (seed data itself is NOT included, see above)
└── backlog.md, backlog-*.md # historical development log (kept for project history/transparency)
```

## Contributing

Issues and pull requests are welcome. If you're interested in collaborating on this further, feel free to reach out directly: **hendi135@gmail.com**.

## License

[MIT](LICENSE) — Copyright (c) 2026 StechSuite. The application code only; see the data note above for the seed data's separate licensing situation.
