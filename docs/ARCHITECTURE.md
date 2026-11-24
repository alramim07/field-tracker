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
- Controllers thin; Form Requests validate; Policies authorize; Services contain business logic.
- Latest location updated only by newer `recorded_at`.
- Duplicate location events rejected by `event_uuid`.
- Private authenticated WebSocket channels for live locations.
- Live-location payloads expose only authorized fields.

## Pending
- Phase 3: Flutter app.
- Phase 4: Kotlin FGS tracking engine.
- Phase 5: Room offline sync.
- Full `laravel/reverb` runtime install/process (channel auth and payload are already implemented and tested).
- Phase 7: History/reporting.
- Phase 8: Security/perf/device/load testing.
- Phase 9: Production deployment.
