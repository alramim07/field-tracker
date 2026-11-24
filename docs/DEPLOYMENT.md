# Deployment

## Status
Not deployed. Phase 9 remains pending. Phases 1, 2, 3, and 6 are implemented on `phase-3-employee-app` (local verification recorded in `docs/TESTING.md`), including the admin SPA at `/admin` and the Flutter employee app in `C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile`.

Before deployment: install/configure Redis and the full `laravel/reverb` runtime, choose MySQL vs MariaDB, build `public/build-admin`, ensure scheduler cron runs every minute, and run the production smoke checklist. Real Android device/emulator FGS smoke and APK build verification are still required in an environment with Android SDK/NDK and devices.

## Target
Ubuntu 24.04 LTS, Nginx, PHP 8.3+, MySQL, Redis, Supervisor, Laravel Reverb, HTTPS, backups, monitoring.

## Scheduler
Add a cron entry to run Laravel's scheduler every minute:

```bash
* * * * * cd /path/to/field-tracker && php artisan schedule:run >> /dev/null 2>&1
```

This is required for `tracking:close-expired`, which closes expired normal sessions and midnight-auto-stopped extra-duty sessions.

## Mobile app (Phase 3)
- Repo: `https://github.com/alramim07/field-tracker-mobile.git`
- Local path: `C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile`
- Flutter binary (this environment): `C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat`
- Build/test commands:
  ```powershell
  cd "C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile"
  C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat pub get
  C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat analyze
  C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test
  C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
  C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat build apk --debug
  ```
- `API_BASE_URL` must point to Laravel API v1 (Android emulator: `http://10.0.2.2:8000/api/v1`; physical device: LAN/HTTPS URL).
- Known environment limits (2026-11-24): debug APK build blocked by missing Android NDK `28.2.13676358` at `C:\Android\Sdk`; real Android device/emulator FGS smoke not run.

## Local dev (current)
- PHP 8.3.30 (Laragon), MariaDB 12.2.2, Composer 2.9.4, Node 24.14.1
- DB: `field_tracker`, user `root`, empty password, `127.0.0.1:3306`
- `php artisan migrate:fresh --seed` for local reset
