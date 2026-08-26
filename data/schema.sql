-- ============================================================
-- SmartQuran Database Schema (SQLite)
-- Modern Offline Quran Application
-- ============================================================

-- Enable foreign keys
PRAGMA foreign_keys = ON;

-- ============================================================
-- AUTH, USERS, ROLES
-- ============================================================

CREATE TABLE IF NOT EXISTS roles (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL UNIQUE,      -- 'admin', 'curator', 'user'
    label       TEXT,                      -- display label
    description TEXT,
    created_at  TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    google_id       TEXT UNIQUE,            -- Google sub (immutable)
    email           TEXT NOT NULL UNIQUE,
    name            TEXT,
    avatar_url      TEXT,
    whatsapp        TEXT,                   -- +62 format
    city            TEXT,                   -- kota
    province        TEXT,                   -- provinsi
    country         TEXT,                   -- negara (ISO 3166-1 alpha-2)
    auth_token      TEXT,                   -- random bearer for cookie
    token_expires   TEXT,                   -- ISO datetime
    last_login_at   TEXT,
    created_at      TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS user_roles (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    role_id     INTEGER NOT NULL,
    granted_at  TEXT DEFAULT (datetime('now')),
    granted_by  INTEGER,                    -- admin user id who granted
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE(user_id, role_id)
);

-- Activity / audit log
CREATE TABLE IF NOT EXISTS user_logs (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER,
    action      TEXT NOT NULL,              -- 'login','logout','view','verify','curate'
    entity      TEXT,                       -- e.g. 'word_form_patterns', 'root_words'
    entity_id   INTEGER,
    details     TEXT,                       -- JSON blob
    ip_address  TEXT,
    user_agent  TEXT,
    created_at  TEXT DEFAULT (datetime('now'))
);

-- Per-user browsing history (synced)
CREATE TABLE IF NOT EXISTS user_history (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    surah_id    INTEGER,
    ayah_number INTEGER,
    url         TEXT,
    title       TEXT,
    viewed_at   TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Per-user bookmarks (server-synced, replaces/replicates localStorage)
CREATE TABLE IF NOT EXISTS user_bookmarks (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL,
    surah_id    INTEGER NOT NULL,
    ayah_number INTEGER NOT NULL,
    note        TEXT,
    tags        TEXT,                       -- comma-separated
    color       TEXT DEFAULT '#1769e0',
    created_at  TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE(user_id, surah_id, ayah_number)
);

-- ============================================================
-- SEED ROLES (idempotent)
-- ============================================================
INSERT OR IGNORE INTO roles (name, label, description) VALUES
('admin', 'Administrator', 'Full access to kurator, user management, and system settings.'),
('curator', 'Curator', 'Can view kurator readonly and verify word form patterns.'),
('user', 'User', 'Standard user: AI chat, feedback, bookmarks, history.');

-- ============================================================
-- SEED ADMIN USER (hendi135@gmail.com) — will link on first login
-- ============================================================
-- We pre-insert a placeholder row so the email is reserved.
-- On first Google login with this email, the row gets google_id, name, avatar, etc.
INSERT OR IGNORE INTO users (email, name, created_at) VALUES
('hendi135@gmail.com', 'Hendi Wibowo', datetime('now'));

-- Link admin role to hendi135@gmail.com if user exists
INSERT OR IGNORE INTO user_roles (user_id, role_id, granted_at)
SELECT u.id, r.id, datetime('now')
FROM users u, roles r
WHERE u.email = 'hendi135@gmail.com' AND r.name = 'admin';

-- Languages supported in the application
CREATE TABLE IF NOT EXISTS languages (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    code        TEXT NOT NULL UNIQUE,      -- 'ar', 'en', 'id', 'su', 'jv'
    name        TEXT NOT NULL,              -- display name in native language
    name_en     TEXT NOT NULL,              -- English name for reference
    direction   TEXT DEFAULT 'ltr',         -- 'ltr' or 'rtl'
    is_active   INTEGER DEFAULT 1,
    sort_order  INTEGER DEFAULT 0,
    created_at  TEXT DEFAULT (datetime('now'))
);

-- Surah information
CREATE TABLE IF NOT EXISTS surahs (
    id              INTEGER PRIMARY KEY,
    number          INTEGER NOT NULL UNIQUE,   -- 1-114
    name_ar         TEXT NOT NULL,              -- Arabic name with tashkeel
    name_simple     TEXT NOT NULL,              -- Arabic without tashkeel
    name_en         TEXT NOT NULL,              -- English name
    name_id         TEXT,                       -- Indonesian name
    name_transliteration TEXT NOT NULL,         -- e.g. "Al-Fatihah"
    revelation_type TEXT NOT NULL,              -- 'Meccan' or 'Medinan'
    verse_count     INTEGER NOT NULL,
    page_start      INTEGER,                   -- Starting page in mushaf
    page_end        INTEGER,
    juz_number      INTEGER,
    created_at      TEXT DEFAULT (datetime('now'))
);

-- Ayahs (Verses)
CREATE TABLE IF NOT EXISTS ayahs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    surah_id        INTEGER NOT NULL,
    ayah_number     INTEGER NOT NULL,          -- within surah
    global_number   INTEGER UNIQUE,            -- 1-6236 (optional)
    text_ar         TEXT NOT NULL,              -- Uthmani script
    text_ar_simple  TEXT,                       -- Simple script without tashkeel
    text_ar_clean   TEXT,                       -- Clean for search
    page_number     INTEGER,
    juz_number      INTEGER,
    hizb_number     INTEGER,
    rub_number      INTEGER,
    sajda           INTEGER DEFAULT 0,          -- 0=no, 1=obligatory, 2=recommended
    created_at      TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (surah_id) REFERENCES surahs(id) ON DELETE CASCADE,
    UNIQUE(surah_id, ayah_number)
);

-- ============================================================
-- TRANSLATIONS
-- ============================================================

CREATE TABLE IF NOT EXISTS translations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    ayah_id         INTEGER NOT NULL,
    language_code   TEXT NOT NULL,
    text            TEXT NOT NULL,
    translator      TEXT,                       -- e.g. 'Saheeh International', 'Kemenag'
    source          TEXT,                       -- source attribution
    created_at      TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (ayah_id) REFERENCES ayahs(id) ON DELETE CASCADE,
    FOREIGN KEY (language_code) REFERENCES languages(code) ON DELETE CASCADE,
    UNIQUE(ayah_id, language_code)
);

-- ============================================================
-- TOPIC SYSTEM (Hierarchical + Graph)
-- ============================================================

CREATE TABLE IF NOT EXISTS topics (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    parent_id       INTEGER DEFAULT NULL,       -- NULL = root topic
    name_ar         TEXT,                       -- Arabic topic name
    name_en         TEXT NOT NULL,
    name_id         TEXT,
    name_su         TEXT,
    name_jv         TEXT,
    description     TEXT,
    color           TEXT DEFAULT '#1769e0',    -- for graph visualization
    icon            TEXT,                       -- emoji or icon name
    sort_order      INTEGER DEFAULT 0,
    is_active       INTEGER DEFAULT 1,
    created_at      TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (parent_id) REFERENCES topics(id) ON DELETE CASCADE
);

-- Many-to-many: Topic <-> Ayah
CREATE TABLE IF NOT EXISTS topic_ayahs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    topic_id        INTEGER NOT NULL,
    ayah_id         INTEGER NOT NULL,
    relevance_score REAL DEFAULT 1.0,          -- 0.0 to 1.0
    note            TEXT,
    FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    FOREIGN KEY (ayah_id) REFERENCES ayahs(id) ON DELETE CASCADE,
    UNIQUE(topic_id, ayah_id)
);

-- Semantic relations between topics
CREATE TABLE IF NOT EXISTS topic_relations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    source_topic_id INTEGER NOT NULL,
    target_topic_id INTEGER NOT NULL,
    relation_type   TEXT NOT NULL,              -- 'synonym','antonym','cause','effect','part_of','related','prerequisite','contrast','hierarchy'
    strength        REAL DEFAULT 0.5,            -- 0.0 to 1.0
    description     TEXT,
    FOREIGN KEY (source_topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    FOREIGN KEY (target_topic_id) REFERENCES topics(id) ON DELETE CASCADE,
    UNIQUE(source_topic_id, target_topic_id, relation_type)
);

-- ============================================================
-- ROOT WORDS & MORPHOLOGY
-- ============================================================

CREATE TABLE IF NOT EXISTS root_words (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    root_ar         TEXT NOT NULL UNIQUE,       -- e.g. "كتب"
    root_en         TEXT,                       -- transliteration e.g. "k-t-b"
    meaning_en      TEXT,
    meaning_id      TEXT,
    meaning_ar      TEXT,
    frequency       INTEGER DEFAULT 0,
    source          TEXT NOT NULL DEFAULT 'curated', -- 'curated' (meaning_* filled by hand) | 'imported' (from seed_word_roots.sql — root+occurrences only until AI-curated, see data/words-kurator-by-ai/)
    created_at      TEXT DEFAULT (datetime('now'))
);

-- Semantic relations between root words (synonym / antonym / related)
CREATE TABLE IF NOT EXISTS root_relations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    source_root_id  INTEGER NOT NULL,
    target_root_id  INTEGER NOT NULL,
    relation_type   TEXT NOT NULL,              -- 'synonym','antonym','related'
    note            TEXT,
    FOREIGN KEY (source_root_id) REFERENCES root_words(id) ON DELETE CASCADE,
    FOREIGN KEY (target_root_id) REFERENCES root_words(id) ON DELETE CASCADE,
    UNIQUE(source_root_id, target_root_id, relation_type)
);

-- ============================================================
-- SARF (MORPHOLOGY) SYSTEM — Full Ilmu Sarf Level 3
-- ============================================================

-- Reference table of all Sarf patterns (BAB I–XII + participles + masdars + etc.)
CREATE TABLE IF NOT EXISTS sarf_patterns (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    pattern_ar          TEXT NOT NULL UNIQUE,       -- e.g. "فَاعِلٌ"
    pattern_type        TEXT NOT NULL,              -- 'verb_form','active_participle','passive_participle','verbal_noun','noun_place_time','noun_instrument','noun_manner','comparative','diminutive','adjective_intensity','imperative','prohibition','absolute_object','adverb_manner','plural_masculine','plural_feminine','plural_broken','nisba','verbal_noun_extended','hyperbolic_participle'
    form_number         INTEGER DEFAULT 1,          -- 1–12 (BAB number), 0 for non-verbal
    bab                 TEXT,                       -- e.g. "BAB I"
    description_ar      TEXT,
    description_en      TEXT,
    description_id      TEXT,
    example_root        TEXT,                       -- e.g. "كتب"
    example_word        TEXT,                       -- e.g. "كَاتِبٌ"
    example_meaning_id  TEXT,                       -- e.g. "penulis"
    sort_order          INTEGER DEFAULT 0,
    is_active           INTEGER DEFAULT 1,
    created_at          TEXT DEFAULT (datetime('now'))
);

-- Per-root derivation rules: which patterns a specific root produces
CREATE TABLE IF NOT EXISTS sarf_derivations (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    root_word_id    INTEGER NOT NULL,
    pattern_id      INTEGER NOT NULL,
    is_attested     INTEGER DEFAULT 0,              -- 0=theoretical/predicted, 1=actually found in Quran
    attestation_count INTEGER DEFAULT 0,            -- how many times in Quran
    notes           TEXT,
    created_at      TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (root_word_id) REFERENCES root_words(id) ON DELETE CASCADE,
    FOREIGN KEY (pattern_id) REFERENCES sarf_patterns(id) ON DELETE CASCADE,
    UNIQUE(root_word_id, pattern_id)
);

-- AI-classified morphology links: each word_form instance → sarf_pattern
CREATE TABLE IF NOT EXISTS word_form_patterns (
    id                  INTEGER PRIMARY KEY AUTOINCREMENT,
    ayah_root_word_id   INTEGER NOT NULL,         -- FK ke ayah_root_words
    pattern_id          INTEGER NOT NULL,          -- FK ke sarf_patterns
    confidence          REAL DEFAULT 0.8,          -- AI confidence score 0.0–1.0
    verified            INTEGER DEFAULT 0,         -- 0=AI-predicted, 1=human-verified, -1=human-corrected
    verified_by         TEXT,                      -- user/admin who verified
    verified_at         TEXT,                      -- ISO timestamp
    ai_reasoning        TEXT,                      -- AI explanation string (optional)
    created_at          TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (ayah_root_word_id) REFERENCES ayah_root_words(id) ON DELETE CASCADE,
    FOREIGN KEY (pattern_id) REFERENCES sarf_patterns(id) ON DELETE CASCADE,
    UNIQUE(ayah_root_word_id, pattern_id)
);

-- Many-to-many: Root Word <-> Ayah
CREATE TABLE IF NOT EXISTS ayah_root_words (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    ayah_id         INTEGER NOT NULL,
    root_word_id    INTEGER NOT NULL,
    word_form       TEXT,                       -- actual form in ayah
    position        INTEGER DEFAULT 0,          -- word position in ayah
    FOREIGN KEY (ayah_id) REFERENCES ayahs(id) ON DELETE CASCADE,
    FOREIGN KEY (root_word_id) REFERENCES root_words(id) ON DELETE CASCADE,
    UNIQUE(ayah_id, root_word_id, position)
);
-- Co-occurrence tree (backlog-1.01.Alpha.016.md) queries filter by
-- root_word_id sets and by ayah_id sets repeatedly — both need an index,
-- not just the UNIQUE constraint's composite (ayah_id, root_word_id, ...).
CREATE INDEX IF NOT EXISTS idx_arw_root ON ayah_root_words(root_word_id);
CREATE INDEX IF NOT EXISTS idx_arw_ayah ON ayah_root_words(ayah_id);

-- ============================================================
-- WORD-BY-WORD QURAN (Arabic + English + Indonesian per word)
-- ============================================================

CREATE TABLE IF NOT EXISTS ayah_words (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    ayah_id         INTEGER NOT NULL,
    position        INTEGER NOT NULL,          -- 1-based, word-only (verse-end ornament excluded)
    text_ar         TEXT NOT NULL,              -- Uthmani word as displayed
    text_ar_clean   TEXT NOT NULL,              -- diacritics stripped + alef/ta-marbuta normalized, for matching
    transliteration TEXT,
    translation_en  TEXT,
    translation_id  TEXT,
    root_word_id    INTEGER,                    -- reserved for future manual curation; NOT seeded
    FOREIGN KEY (ayah_id) REFERENCES ayahs(id) ON DELETE CASCADE,
    FOREIGN KEY (root_word_id) REFERENCES root_words(id) ON DELETE SET NULL,
    UNIQUE(ayah_id, position)
);

-- ============================================================
-- TAFSIR
-- ============================================================

CREATE TABLE IF NOT EXISTS tafsirs (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    ayah_id         INTEGER NOT NULL,
    language_code   TEXT NOT NULL,
    text            TEXT NOT NULL,
    author          TEXT,                       -- e.g. 'Ibn Kathir', 'Al-Jalalayn'
    source          TEXT,
    created_at      TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (ayah_id) REFERENCES ayahs(id) ON DELETE CASCADE,
    FOREIGN KEY (language_code) REFERENCES languages(code) ON DELETE CASCADE,
    UNIQUE(ayah_id, language_code, author)
);

-- ============================================================
-- BOOKMARKS & USER DATA
-- ============================================================

CREATE TABLE IF NOT EXISTS bookmarks (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    surah_id        INTEGER NOT NULL,
    ayah_number     INTEGER NOT NULL,
    ayah_id         INTEGER,
    note            TEXT,
    color           TEXT DEFAULT '#1769e0',
    tags            TEXT,                       -- JSON array of tags
    created_at      TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (surah_id) REFERENCES surahs(id) ON DELETE CASCADE,
    FOREIGN KEY (ayah_id) REFERENCES ayahs(id) ON DELETE SET NULL,
    UNIQUE(surah_id, ayah_number)
);

-- Reading progress / last read position
CREATE TABLE IF NOT EXISTS reading_progress (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    surah_id        INTEGER NOT NULL,
    ayah_number     INTEGER NOT NULL DEFAULT 1,
    updated_at      TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (surah_id) REFERENCES surahs(id) ON DELETE CASCADE,
    UNIQUE(surah_id)
);

-- ============================================================
-- SEARCH & FTS5
-- ============================================================

-- FTS5 virtual table for Arabic text search
CREATE VIRTUAL TABLE IF NOT EXISTS ayahs_fts USING fts5(
    text_ar,
    text_ar_simple,
    surah_id UNINDEXED,
    ayah_number UNINDEXED,
    content='ayahs',
    content_rowid='id'
);

-- FTS5 virtual table for translation search
CREATE VIRTUAL TABLE IF NOT EXISTS translations_fts USING fts5(
    text,
    language_code UNINDEXED,
    ayah_id UNINDEXED,
    content='translations',
    content_rowid='id'
);

-- FTS5 virtual table for topic search
CREATE VIRTUAL TABLE IF NOT EXISTS topics_fts USING fts5(
    name_en,
    name_id,
    name_ar,
    description,
    topic_id UNINDEXED,
    content='topics',
    content_rowid='id'
);

-- Search history
CREATE TABLE IF NOT EXISTS search_history (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    query           TEXT NOT NULL,
    language        TEXT DEFAULT 'ar',
    result_count    INTEGER DEFAULT 0,
    created_at      TEXT DEFAULT (datetime('now'))
);

-- ============================================================
-- SETTINGS & APPLICATION STATE
-- ============================================================

CREATE TABLE IF NOT EXISTS settings (
    key             TEXT PRIMARY KEY,
    value           TEXT NOT NULL,
    type            TEXT DEFAULT 'string',      -- string, int, bool, json
    description     TEXT,
    updated_at      TEXT DEFAULT (datetime('now'))
);

-- Audio sources metadata (files loaded from CDN)
CREATE TABLE IF NOT EXISTS audio_sources (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    reciter_name    TEXT NOT NULL,
    reciter_name_ar TEXT,
    language        TEXT DEFAULT 'ar',
    base_url        TEXT NOT NULL,              -- CDN base URL pattern
    format          TEXT DEFAULT 'mp3',         -- mp3, ogg
    is_active       INTEGER DEFAULT 1,
    sort_order      INTEGER DEFAULT 0
);

-- ============================================================
-- AI / CHAT PLACEHOLDER
-- ============================================================

CREATE TABLE IF NOT EXISTS chat_sessions (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    session_name    TEXT,
    model_provider  TEXT DEFAULT 'ollama',     -- 'ollama','openrouter','openai'
    model_name      TEXT DEFAULT 'llama3',
    system_prompt   TEXT,
    created_at      TEXT DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS chat_messages (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id      INTEGER NOT NULL,
    role            TEXT NOT NULL,              -- 'system','user','assistant'
    content         TEXT NOT NULL,
    referenced_ayah_ids TEXT,                   -- JSON array of ayah IDs referenced
    tokens_used     INTEGER,
    latency_ms      INTEGER,
    created_at      TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (session_id) REFERENCES chat_sessions(id) ON DELETE CASCADE
);

-- ============================================================
-- INDEXES
-- ============================================================

CREATE INDEX IF NOT EXISTS idx_ayahs_surah ON ayahs(surah_id);
CREATE INDEX IF NOT EXISTS idx_ayahs_juz ON ayahs(juz_number);
CREATE INDEX IF NOT EXISTS idx_ayahs_page ON ayahs(page_number);
CREATE INDEX IF NOT EXISTS idx_translations_ayah ON translations(ayah_id);
CREATE INDEX IF NOT EXISTS idx_translations_lang ON translations(language_code);
CREATE INDEX IF NOT EXISTS idx_topic_ayahs_topic ON topic_ayahs(topic_id);
CREATE INDEX IF NOT EXISTS idx_topic_ayahs_ayah ON topic_ayahs(ayah_id);
CREATE INDEX IF NOT EXISTS idx_topics_parent ON topics(parent_id);
CREATE INDEX IF NOT EXISTS idx_tafsirs_ayah ON tafsirs(ayah_id);
CREATE INDEX IF NOT EXISTS idx_bookmarks_surah ON bookmarks(surah_id);
CREATE INDEX IF NOT EXISTS idx_ayah_words_ayah ON ayah_words(ayah_id);

-- ============================================================
-- FTS5 TRIGGERS (keep virtual tables in sync)
-- ============================================================

CREATE TRIGGER IF NOT EXISTS ayahs_ai AFTER INSERT ON ayahs BEGIN
    INSERT INTO ayahs_fts(rowid, text_ar, text_ar_simple, surah_id, ayah_number)
    VALUES (new.id, new.text_ar, new.text_ar_simple, new.surah_id, new.ayah_number);
END;

CREATE TRIGGER IF NOT EXISTS ayahs_ad AFTER DELETE ON ayahs BEGIN
    INSERT INTO ayahs_fts(ayahs_fts, rowid, text_ar, text_ar_simple, surah_id, ayah_number)
    VALUES ('delete', old.id, old.text_ar, old.text_ar_simple, old.surah_id, old.ayah_number);
END;

CREATE TRIGGER IF NOT EXISTS ayahs_au AFTER UPDATE ON ayahs BEGIN
    INSERT INTO ayahs_fts(ayahs_fts, rowid, text_ar, text_ar_simple, surah_id, ayah_number)
    VALUES ('delete', old.id, old.text_ar, old.text_ar_simple, old.surah_id, old.ayah_number);
    INSERT INTO ayahs_fts(rowid, text_ar, text_ar_simple, surah_id, ayah_number)
    VALUES (new.id, new.text_ar, new.text_ar_simple, new.surah_id, new.ayah_number);
END;

CREATE TRIGGER IF NOT EXISTS translations_ai AFTER INSERT ON translations BEGIN
    INSERT INTO translations_fts(rowid, text, language_code, ayah_id)
    VALUES (new.id, new.text, new.language_code, new.ayah_id);
END;

CREATE TRIGGER IF NOT EXISTS translations_ad AFTER DELETE ON translations BEGIN
    INSERT INTO translations_fts(translations_fts, rowid, text, language_code, ayah_id)
    VALUES ('delete', old.id, old.text, old.language_code, old.ayah_id);
END;

CREATE TRIGGER IF NOT EXISTS topics_ai AFTER INSERT ON topics BEGIN
    INSERT INTO topics_fts(rowid, name_en, name_id, name_ar, description, topic_id)
    VALUES (new.id, new.name_en, new.name_id, new.name_ar, new.description, new.id);
END;

CREATE TRIGGER IF NOT EXISTS topics_ad AFTER DELETE ON topics BEGIN
    INSERT INTO topics_fts(topics_fts, rowid, name_en, name_id, name_ar, description, topic_id)
    VALUES ('delete', old.id, old.name_en, old.name_id, old.name_ar, old.description, old.id);
END;
