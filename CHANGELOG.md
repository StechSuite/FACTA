# Changelog

All notable changes to this repository (`StechSuite/FACTA`) are documented here, starting from its extraction into a standalone public repo. For the full pre-extraction development log (from the original private monorepo, under the `1.01.Alpha.xxx` internal version scheme), see [`backlog.md`](backlog.md) and its dated companions.

## [Unreleased]

- Added a username/password admin login (`admin` / `bismillah` by default, override via `config.admin.json`) as this repo's alternative to Google OAuth, which stays disabled here since it needs deployment-specific credentials. Reuses the existing `admin_secret` cookie gate — no change needed to `is_admin()` or anything it already protects.
- Fixed a router bug (present since the original extraction) where `?page=auth` with no `action` always bypassed straight to `api/auth.php` instead of ever rendering `pages/auth.php` — the login form above would have been unreachable without this fix.
- Header's Login button now routes through `?page=auth` (so it reaches whichever login method is actually configured) instead of straight to the Google-only endpoint, and reflects admin-password logins the same way it already did for Google logins.

## [1.0.0] — 2026-08-26

Initial public release — code extracted from a private monorepo (`CoreAI-CPanel/src/aiquran`) into this standalone, MIT-licensed repository.

- Application code only; seed data (Quran text, translations, root-word dictionary) intentionally excluded — see the data note in [`README.md`](README.md) and [`data/README.md`](data/README.md).
- Removed a hardcoded Google OAuth client secret and an `admin` cookie-secret default that existed in the private repo's `config.php` — both now require explicit configuration via environment variables, with OAuth disabled by default until configured.
- Header comments across the codebase updated from the project's former name ("SmartQuran") to FACTA.
- Version display decoupled from `backlog.md` (which is now frozen historical content) — see `includes/functions.php`.
