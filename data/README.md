# Data

This folder ships the database **schema** (`schema.sql`) and the **build scripts** used to turn source data into `smartquran.db`. It does not ship the seed data itself — see the root [`README.md`](../README.md#-about-the-data--this-repo-ships-code-only) for why.

## What's here

| File | Purpose |
|---|---|
| `schema.sql` | Full SQLite schema — tables, indexes, FTS5 virtual tables. Safe to run on its own to inspect the data model without any seed data. |
| `build_seed.py` | Builds the Quran text + translation seed SQL from a source you provide. |
| `build_seed_words.py` | Builds the word-by-word (per-ayah) seed SQL. |
| `build_word_roots_import.py` | Parses an Arabic morphology corpus into root-word + `ayah_root_words` seed SQL — this is the step that, in the original project, used [`mustafa0x/quran-morphology`](https://github.com/mustafa0x/quran-morphology) (GPLv3) as its source. If you use the same source, note that its GPLv3 license governs whatever you generate from it — that's exactly why the generated output isn't redistributed from this repo. |
| `cacert.pem` | Standard public CA certificate bundle (used for outbound HTTPS calls during data builds) — not project-specific data, safe as-is. |

## What you need to supply yourself

1. **Quran text + translations** — the base Arabic text (Uthmani script, standard 6,236-verse mushaf division) plus whichever translation(s) you want to ship (the live demo uses Kemenag for Indonesian and Saheeh International for English). Any source that gives you verse-by-verse text keyed by surah/ayah number will work with `build_seed.py` with light adaptation.
2. **Arabic root-word morphology data** — a source mapping words to their 2-4 letter Arabic root, per occurrence, per verse. `build_word_roots_import.py` expects the same shape as the Quranic Arabic Corpus-derived data the original project used; check whatever source you pick for its own license before redistributing anything built from it.
3. **(Optional) Curated root meanings** — the live demo has manually-curated meanings for ~160 common roots, and AI-assisted meanings (generated with the app's own "Kata Kurator" tool, not included in this repo) for the rest. Without this, roots will exist in the co-occurrence graph but without a displayed meaning.

## Building the database

Once you have source data in a shape the scripts expect:

```bash
python build_seed.py              # → seed_quran_full.sql
python build_seed_words.py        # → seed_words_full.sql
python build_word_roots_import.py # → seed_word_roots.sql
```

Then run the installer (`install.php` in the repo root, or `php run_install.php`) to apply `schema.sql` followed by every `seed_*.sql` file in order, producing `smartquran.db`.

## Don't want to deal with any of this?

The [live demo](https://aiquran.diasoft.web.id/) already runs the full dataset — and the in-app Guide (`page=guide`) works with zero database at all, since it's static content. That's the fastest way to see what FACTA actually does before investing in your own data pipeline.
