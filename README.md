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
npm --prefix resources/admin-ui run build
```
Latest verified backend result: **50 passed, 230 assertions**. Admin UI build: **PASS**.

## Phase 6 Admin Dashboard
- Backend: RBAC permissions (`section.action`), admin SPA auth (`/api/v1/admin/auth/*`), admin/tracker user management APIs, dashboard metrics, live location endpoint, and broadcasting channel auth for `admin.live-locations`.
- Frontend: React + Tailwind SPA in `resources/admin-ui/`, served at `/admin`, with permission-guarded sidebar (burger-controlled collapse/expand, remembered in `localStorage`), dashboard metrics, tracker/admin management, settings placeholder, and live map.
- Live Map: uses free OpenStreetMap tiles by default (no API key). The Google Maps implementation is retained and can be enabled later through a Settings-provided API key/provider switch.
- Live Map details table: shows rounded avatar, user, dynamically reverse-geocoded current place name, previous-ping place name, live-ticking tracker time (`HH:MM:SS`), and offline time during duty. Place names come from OpenStreetMap/Nominatim and are cached for 24 hours; coordinates are used as a fallback.
- App timezone is `Asia/Dhaka` so duty windows and durations match Bangladesh operations.
- Dashboard `Currently using app` counts employees with an active tracking session **and** a location ping within the last 5 minutes, so stale test sessions do not appear as active app usage.
- Realtime: `LatestLocationUpdated` broadcasts on private `admin.live-locations`; frontend subscribes via Laravel Echo/Reverb when configured. Composer install of `laravel/reverb` may be required in environments where it is not yet present.

### Admin UI setup
```powershell
npm --prefix resources/admin-ui install
npm --prefix resources/admin-ui run build
```
For local dev:
```powershell
npm --prefix resources/admin-ui run dev
php artisan serve
```

## Manual smoke test
1. Login employee, get token.
2. Register device: `POST /api/v1/device/register`
3. Start tracking: `POST /api/v1/tracking/start` with `device_uuid`
4. Send batch: `POST /api/v1/locations/batch`
5. Check status/history: `GET /api/v1/tracking/status`, `GET /api/v1/me/location-history`
6. Login super admin to admin SPA: `POST /api/v1/admin/auth/login`, then open `/admin`.
7. Verify Dashboard metrics, Tracker Users CRUD with duty time, Admins permission assignment (super admin only), and Live Map initial load.
8. On `/admin/live-map`, confirm the details table shows a Bangladesh place name, the previous-ping place name, a tracker time that ticks each second while tracking is active, and offline time for location gaps longer than 5 minutes.
9. Click the header burger button to collapse/expand the sidebar; reload to confirm the choice is remembered.

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