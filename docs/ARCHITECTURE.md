# Architecture

## Target architecture (frozen unless approved deviation)
- **Mobile:** Flutter UI + Native Kotlin Android foreground tracking engine. Kotlin engine must not depend on Flutter UI or a running Dart isolate.
- **Backend:** Laravel REST API + Sanctum + MySQL/MariaDB + Redis + Reverb WebSockets.
- **Admin:** Laravel Blade + JavaScript + Google Maps JS API.
- **Server:** Ubuntu 24.04, Nginx, PHP 8.3+, MySQL, Redis, Supervisor, HTTPS.

## Phase 1 implemented foundation
- Laravel 12 app scaffolded.
- API v1 route group: `/api/v1/*` (`routes/api.php`).
- Sanctum installed for token auth.
- Domain models/enums for users, org, devices, tracking policies/sessions, locations, audit logs.
- AuditLogService skeleton (`app/Services/Audit/AuditLogService.php`).
- Service directories created for Auth, Tracking, Location, Device, Audit, Reporting.

## Key design rules
- Server-authoritative session expiry and ownership validation.
- Controllers thin; Form Requests validate; Policies authorize; Services contain business logic.
- Latest location updated only by newer `recorded_at`.
- Duplicate location events rejected by `event_uuid`.
- Private authenticated WebSocket channels for live locations.

## Pending
- Phase 2: auth/device/session/location APIs.
- Phase 3: Flutter app.
- Phase 4: Kotlin FGS tracking engine.
- Phase 5: Room offline sync.
- Phase 6: Admin dashboard + Reverb.
- Phase 7: History/reporting.
- Phase 8: Security/perf/device/load testing.
- Phase 9: Production deployment.
