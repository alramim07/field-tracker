# Architecture

## Target architecture (frozen unless approved deviation)
- **Mobile:** Flutter UI + Native Kotlin Android foreground tracking engine. Kotlin engine must not depend on Flutter UI or a running Dart isolate.
- **Backend:** Laravel REST API + Sanctum + MySQL/MariaDB + Redis + Reverb WebSockets.
- **Admin:** Laravel-served React + Tailwind Vite SPA with Leaflet/OpenStreetMap by default; Google Maps implementation retained for later Settings-driven activation.
- **Server:** Ubuntu 24.04, Nginx, PHP 8.3+, MySQL, Redis, Supervisor, HTTPS.

## Implemented foundation
### Phase 1 — Backend foundation
- Laravel 12 app scaffolded.
- API v1 route group: `/api/v1/*` (`routes/api.php`).
- Sanctum installed for token auth.
- Domain models/enums for users, org, devices, tracking policies/sessions, locations, audit logs.
- AuditLogService skeleton (`app/Services/Audit/AuditLogService.php`).
- Service directories created for Auth, Tracking, Location, Device, Audit, Reporting.

### Phase 2 — Auth/tracking APIs
- Sanctum token endpoints for mobile (`/api/v1/login`, `/me`, `/logout`).
- Device registration/revocation with ownership checks.
- Tracking session start/stop/status with server-authoritative fixed expiry.
- Location batch ingestion with `event_uuid` dedupe, newer-`recorded_at` rule, and latest location update.
- Employee self endpoints for history/sessions/reports.

### Phase 3 — Employee Flutter app + native tracking
- Flutter app in `C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile` with auth, device registration, OSM map, travel history, notifications, drawer/profile/photo/password/logout.
- Native Kotlin Android foreground tracking service and Flutter method/event bridge; native service handles tracking runtime/FGS behavior.
- Tracking sessions support `session_type` (`normal_duty`, `extra_duty`), `counted`, and `stop_reason`.
- Normal duty expires at employee duty end (Asia/Dhaka) and cannot be manually stopped by employee API.
- Extra duty can start only after normal duty ended, only once per phone-local day, requires `client_midnight_at`, and can be manually stopped (`EXTENDED_MANUAL`).
- Forgotten extra duty is auto-closed at midnight boundary by `tracking:close-expired` / status expiry handling as `MIDNIGHT_AUTO`, `counted=false`; admin tracker-user detail includes `extra_duty_audit`.
- Additive profile endpoints: `PATCH /me/profile`, `POST /me/photo`, `POST /me/password`; `/me` includes profile/tracking summary fields.
- Scheduler registration in `bootstrap/app.php`: `tracking:close-expired` every minute.

### Phase 6 — Admin dashboard + Live Map
- DB-backed RBAC permissions layered over `users.role`; super admin bypasses granular checks.
- Admin SPA cookie auth under `/api/v1/admin/auth/*`; mobile Bearer flow preserved.
- Admin/tracker user management, duty times, dashboard metrics, and live location API.
- `LatestLocationUpdated` broadcast payload and private `admin.live-locations` channel authorization.
- React/Tailwind SPA served at `/admin`; OSM/Leaflet default map with Google Maps implementation retained.
- Live Map details table with dynamic place names (Nominatim, cached 24h), previous-ping place name, tracker/offline durations, and live-ticking tracker time.
- App timezone `Asia/Dhaka` for duty-window and duration calculations.

## Key design rules
- Server-authoritative session expiry and ownership validation.
- Normal duty is duty-window driven and not manually stoppable by employee API; extra duty is explicit, once/day, manually stoppable, and midnight-auto-stopped as uncounted when forgotten.
- Controllers thin; Form Requests validate; Policies authorize; Services contain business logic.
- Latest location updated only by newer `recorded_at`.
- Duplicate location events rejected by `event_uuid`.
- Private authenticated WebSocket channels for live locations.
- Live-location payloads expose only authorized fields.
- Scheduler/cron must run `php artisan schedule:run` every minute for `tracking:close-expired`.

## Pending / known gaps
- Real Android device/emulator foreground-service smoke verification (no connected device in this environment).
- Android debug APK build currently blocked in this environment by missing NDK `28.2.13676358` at `C:\Android\Sdk`.
- Room/offline sync hardening beyond current Phase 3 behavior.
- Full `laravel/reverb` runtime install/process (channel auth and payload are already implemented and tested).
- Phase 7 history/reporting expansion.
- Phase 8 security/perf/device/load testing.
- Phase 9 production deployment.
