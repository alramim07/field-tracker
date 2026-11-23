# Field Tracker

Enterprise Android live location tracking and management system (backend foundation + Phase 2 APIs).

## Current status
- **Phase 1:** Laravel foundation, DB schema, models/enums/factories/seeders, baseline tests — complete.
- **Phase 2:** Auth, device registration/revocation, tracking start/stop/status (fixed server-authoritative expiry), location batch ingestion (dedupe, expiry rejection, latest-location newer-recorded_at rule), employee self endpoints, admin skeleton endpoints — complete.
- **Phase 3+:** Flutter app, Kotlin tracking engine, offline sync, admin dashboard/map/Reverb, history/reporting, hardening, deployment — pending.

## Stack
- Laravel 12, PHP 8.3+, Sanctum
- MariaDB/MySQL-compatible (local dev: MariaDB via Laragon)
- Redis/Reverb planned
- Planned mobile: Flutter + Native Kotlin foreground service

## Local setup

1. Install dependencies:
   ```powershell
   composer install
   ```
2. Configure env:
   ```powershell
   copy .env.example .env
   php artisan key:generate
   ```
3. Set DB in `.env` (local default):
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=field_tracker
   DB_USERNAME=root
   DB_PASSWORD=
   ```
4. Create DB and migrate:
   ```powershell
   php artisan migrate:fresh --seed
   ```
5. Run server:
   ```powershell
   php artisan serve
   ```

## Seeded accounts
- Super Admin: `superadmin@fieldtracker.local` / `password`
- Employee: `employee@fieldtracker.local` / `password`

## Tests
```powershell
php artisan test
```
Latest verified result: **29 passed, 97 assertions**.

## Manual API smoke test
1. Login employee, get token.
2. Register device: `POST /api/v1/device/register`
3. Start tracking: `POST /api/v1/tracking/start` with `device_uuid`
4. Send batch: `POST /api/v1/locations/batch`
5. Check status/history: `GET /api/v1/tracking/status`, `GET /api/v1/me/location-history`
6. Login admin and check `GET /api/v1/admin/live-locations`

See `docs/API_DOCUMENTATION.md` for details.

## Documentation
- `docs/PROJECT_OVERVIEW.md`
- `docs/ARCHITECTURE.md`
- `docs/DATABASE_SCHEMA.md`
- `docs/API_DOCUMENTATION.md`
- `docs/SECURITY.md`
- `docs/TESTING.md`
- `docs/DECISIONS.md`
- `docs/CHANGELOG.md`

## Repo hygiene
- `plan/` is local-only and excluded from git.
- `.env`, `vendor/`, logs/cache artifacts are ignored. Never commit secrets.