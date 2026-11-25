# Field Tracker

Enterprise Android live location tracking and management system.

## Current status
- **Phase 1:** Laravel foundation, DB schema, models/enums/factories/seeders, baseline tests — complete.
- **Phase 2:** Auth, device registration/revocation, tracking start/stop/status (server-authoritative expiry), location batch ingestion (dedupe, expiry rejection, latest-location newer-recorded_at rule), employee self endpoints, admin skeleton endpoints — complete.
- **Phase 3:** Flutter employee app (`C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile`) with OpenStreetMap, auth/device registration, tracking bridge to native Kotlin foreground GPS service, normal/extra duty session handling, phone-local day reset/auto-sync behavior, travel history movement/stops, notifications, drawer/profile/photo/password/logout — implemented on this branch. Real Android device/emulator foreground-service smoke is **not verified** in this environment (no connected device; debug APK build blocked by missing Android NDK 28.2.13676358).
- **Phase 6:** Admin dashboard/live map, RBAC, SPA auth, tracker/admin management, dashboard metrics, live location broadcast payload/channel auth — implemented.
- **Phase 4/5/7/8/9:** Room offline sync hardening, history/reporting expansion, security/perf/load/device hardening, production deployment — pending/partial.

## Stack
- Backend: Laravel 12, PHP 8.3+, Sanctum, MariaDB/MySQL-compatible (local dev: MariaDB via Laragon)
- Realtime: Redis/Reverb planned/runtime pending in this environment
- Mobile: Flutter employee app + native Kotlin Android foreground service (`C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile`)
- Admin: React + Tailwind Vite SPA in `resources/admin-ui/`

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
cd "C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile"
C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat analyze
C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test
```

Latest verified results (2026-11-24, this branch/worktree):
- Backend: **PASS** — `71 passed, 342 assertions`
- Admin UI build: **PASS** — Vite production build
- Flutter analyze: **PASS** — `No issues found!`
- Flutter test: **PASS** — `22 passed`
- Android debug APK build: **BLOCKED** — `flutter build apk --debug` failed because Android NDK `28.2.13676358` is missing at `C:\Android\Sdk`; install/configure the NDK before building.
- Real Android device/emulator FGS smoke: **NOT RUN** — no connected Android device/emulator in this environment.

## Phase 3 Mobile App (Flutter)
- Repo: `https://github.com/alramim07/field-tracker-mobile.git`
- Local path (this environment): `C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile`
- Flutter binary (this environment): `C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat`
- Run/analyze/test:
  ```powershell
  cd "C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile"
  C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat pub get
  C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat analyze
  C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test
  C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
  ```
- `API_BASE_URL` should point at the Laravel API v1 base (Android emulator default: `http://10.0.2.2:8000/api/v1`).
- Capabilities: employee auth, device registration, OSM map, native Kotlin foreground tracking service bridge, start normal duty, auto-stop at duty end, extend extra duty once/day, extra duty manual stop, phone-local midnight uncounted auto-stop handling, phone-local day reset/auto-sync, travel history movement/stops, notifications, drawer profile/photo/password/logout.
- Known limits: no connected Android device/emulator for real FGS smoke in this environment; `flutter build apk --debug` currently blocked by missing Android NDK `28.2.13676358` at `C:\Android\Sdk`.

## Scheduler
The tracking session auto-expiry command must run every minute in environments where sessions should close automatically:

```powershell
php artisan schedule:work
```

Production cron:
```bash
* * * * * cd /path/to/field-tracker && php artisan schedule:run >> /dev/null 2>&1
```

This is required for `tracking:close-expired` (normal duty expiry and extra duty midnight auto-stop/uncounted handling).

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
2. Register device: `POST /api/v1/device/register`.
3. Start normal duty: `POST /api/v1/tracking/start` with `device_uuid` (optional `mode=normal_duty`) and verify `session_type`, `counted`, `expires_at`.
4. Send batch: `POST /api/v1/locations/batch` and confirm dedupe/expiry rules.
5. Check status/history/reports: `GET /api/v1/tracking/status`, `GET /api/v1/me/location-history`, `GET /api/v1/me/sessions`, `GET /api/v1/me/reports`.
6. Verify profile endpoints: `GET /api/v1/me`, `PATCH /api/v1/me/profile`, `POST /api/v1/me/photo`, `POST /api/v1/me/password`.
7. After normal duty ends, start extra duty once/day: `POST /api/v1/tracking/start` with `mode=extra_duty` and `client_midnight_at`; verify manual stop works only for extra duty and forgotten extra duty becomes uncounted on midnight auto-stop.
8. Run scheduler/command: `php artisan tracking:close-expired` (or `php artisan schedule:work`) and confirm expired sessions close with expected `stop_reason/counted`.
9. Login super admin to admin SPA: `POST /api/v1/admin/auth/login`, then open `/admin`.
10. Verify Dashboard metrics, Tracker Users CRUD with duty time, Admins permission assignment (super admin only), Live Map, and tracker-user details include `extra_duty_audit` for forgotten uncounted extra-duty sessions.
11. Mobile (device/emulator required): run Flutter app with `--dart-define=API_BASE_URL=...`, verify login, map, start duty, background FGS behavior, travel history, notifications, profile, and logout stops tracking. **Not verified in this environment.**

See `docs/API_DOCUMENTATION.md` for details.

## Documentation
- `docs/PROJECT_OVERVIEW.md`
- `docs/ARCHITECTURE.md`
- `docs/DATABASE_SCHEMA.md`
- `docs/API_DOCUMENTATION.md`
- `docs/SECURITY.md`
- `docs/TESTING.md`
- `docs/DEPLOYMENT.md`
- `docs/ANDROID_TRACKING.md`
- `docs/DECISIONS.md`
- `docs/CHANGELOG.md`

## Repo hygiene
- `plan/` is local-only and excluded from git.
- `.env`, `vendor/`, logs/cache artifacts are ignored. Never commit secrets.