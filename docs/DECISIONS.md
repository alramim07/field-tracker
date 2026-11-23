# Decisions Log

## D-001 — MariaDB for local development (Accepted)
- **Date:** 2026-11-23
- **Decision:** Use MariaDB 12.2.2 locally via Laragon; configure Laravel with `DB_CONNECTION=mysql` (MySQL driver) against MariaDB.
- **Reason:** User confirmed MariaDB is acceptable; MariaDB is MySQL-compatible and available in the environment. Spec names MySQL; compatibility risk is low for current schema.
- **Impact:** Verify MySQL-specific behavior before production deployment; production should use spec-approved MySQL/MariaDB as finalized.
- **Status:** Accepted for dev.

## D-002 — SQLite in-memory for automated tests (Accepted)
- **Date:** 2026-11-23
- **Decision:** Keep PHPUnit configured with `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` for fast baseline tests.
- **Reason:** Fast feedback; migrations are compatible.
- **Impact:** Run integration tests against MariaDB/MySQL before release for DB-specific behavior.

## D-003 — Plan folder local-only (Accepted)
- **Date:** 2026-11-23
- **Decision:** Keep `plan/` local-only, ignored via `.git/info/exclude`.
- **Reason:** User requested plan must not go to git account repo.
- **Impact:** Plan is not versioned in remote; keep backups if needed.
