# Changelog

## [Unreleased]

### Added - 2026-11-24 (Phase 3 employee mobile tracker)
- Flutter employee app in `apps/field_tracker_mobile/` with auth, device registration, OSM map, travel history movement/stops, notifications, and drawer/profile/photo/password/logout flows.
- Native Kotlin Android foreground tracking service and Flutter bridge for tracking runtime/state/sync.
- Tracking session model/API support for `session_type` (`normal_duty`, `extra_duty`), `counted`, and explicit `stop_reason` values including `extended_manual`, `midnight_auto`, and `logout`.
- Normal duty sessions expire at employee duty end (Asia/Dhaka) and cannot be manually stopped by employee API; extra duty can start once per phone-local day after normal duty ends, requires `client_midnight_at`, and can be manually stopped.
- Forgotten extra duty auto-closes as `expired/midnight_auto`, `counted=false`; status endpoint reports additive `session_type`, `counted`, `can_extend`, `has_used_extra_today`.
- Scheduler command `tracking:close-expired` registered every minute in `bootstrap/app.php`; deployment cron note documented.
- Additive profile endpoints: `PATCH /api/v1/me/profile`, `POST /api/v1/me/photo`, `POST /api/v1/me/password`; `/api/v1/me` includes additive profile/tracking fields.
- Admin tracker-user detail includes `extra_duty_audit` for forgotten uncounted extra-duty sessions with embedded locations.
- Docs updated for Phase 3 status, additive APIs, scheduler requirement, Flutter commands/path, and verification limits.

### Verification - 2026-11-24
- `php artisan test`: **PASS** (68 passed, 329 assertions)
- `npm --prefix resources/admin-ui run build`: **PASS**
- `C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat analyze` (in `apps/field_tracker_mobile`): **PASS** (No issues found)
- `C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test` (in `apps/field_tracker_mobile`): **PASS** (22 passed)
- `C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat build apk --debug` (in `apps/field_tracker_mobile`): **BLOCKED** (missing Android NDK `28.2.13676358` at `C:\Android\Sdk`)
- Real Android device/emulator FGS smoke: **NOT RUN** (no connected device/emulator)

### Added - 2026-11-24 (Live Map refinements)
- Bangladesh-safe factories for latest locations and location logs; local demo test user moved to Dhaka.
- Collapsible admin sidebar with a header burger toggle and `localStorage` persistence.
- Live Map details table with rounded avatar, current place name, previous-ping place name, live-ticking tracker time (`HH:MM:SS`), and offline time during duty.
- Dynamic reverse geocoding via OpenStreetMap/Nominatim with 24-hour cache and coordinate fallback.
- Backend `is_tracking` flag so tracker time only ticks while a session is active.
- App timezone configurable via `APP_TIMEZONE`, defaulted to `Asia/Dhaka` in `.env.example`.
- Updated project status docs and test documentation.

### Added - 2026-11-23 (Phase 6 admin dashboard)
- RBAC schema/models: admin roles, permissions, pivots, permission seeder, PermissionService/Gate.
- Admin SPA auth endpoints (session/cookie) under `/api/v1/admin/auth/*`; mobile token auth preserved.
- Admin and tracker user management APIs with duty time, designation, and super-admin-only permission assignment.
- Dashboard metrics service/endpoint (`/api/v1/admin/dashboard/summary`).
- Live location endpoint/resource and broadcast-capable `LatestLocationUpdated` event on private `admin.live-locations`.
- React + Tailwind admin SPA in `resources/admin-ui/` (Vite build to `public/build-admin`) served at `/admin`.
- Admin UI: auth/layout/guards, tracker/admin management, dashboard cards, live map with smooth marker animation.
- Feature tests for RBAC, admin SPA auth, admin/tracker CRUD, dashboard metrics, broadcast channel auth, live locations, admin shell.

### Added - 2026-11-23 (Phase 2 auth/tracking APIs)
- Sanctum token auth endpoints: login, logout, me (rate-limited login).
- Device registration/revocation APIs with ownership checks, admin revoke, audit logging.
- Tracking session APIs: start/stop/status with server-authoritative fixed expiry, policy snapshot, duplicate active session prevention.
- Location batch ingestion API: validation, ownership checks, expired/inactive session rejection, event_uuid dedupe, recorded_at preserved, server received_at, latest_locations updated only for newer recorded_at.
- `LatestLocationUpdated` event dispatch hook (private channel auth/payload wired in Phase 6; full Reverb runtime install remains pending).
- Employee self endpoints: location-history, sessions, reports.
- Admin skeleton endpoints: employees, devices, tracking-policies, audit-logs, live-locations with admin middleware.
- Feature tests for auth, device, tracking, location batch, employee self, admin access.

### Added - 2026-11-23 (Phase 1 foundation)
- Laravel 12 project scaffolded in repository.
- Environment configured for local MariaDB (`field_tracker`, root, no password).
- Sanctum installed and published.
- API v1 route group scaffolded (`routes/api.php`).
- Core migrations: users (role/status), regions, areas, employees, devices, tracking_policies, tracking_sessions, location_logs, latest_locations, audit_logs, personal_access_tokens.
- Domain enums: UserRole, UserStatus, DeviceStatus, TrackingSessionStatus, StopReason, TrackingPolicyStatus.
- Eloquent models with relationships/casts for core entities.
- Factories and DatabaseSeeder for baseline dev data.
- AuditLogService skeleton.
- Baseline feature tests for model relationships, unique constraints, enum casts, audit logging.
- Local `plan/` execution plan (git-excluded).

### Fixed
- Removed `aria_log.00000001` to recover local MariaDB Aria startup failure (environment issue, not project code).

### Known issues / blockers
- Redis not configured locally; required before queue/cache/Reverb phases.
- Full `laravel/reverb` runtime package/process not installed; channel auth, event payload, and frontend subscription hook are implemented and tested.
- Android debug build blocked in this environment: missing NDK `28.2.13676358` at `C:\Android\Sdk`; real Android device/emulator FGS smoke not run (no connected device).
- Scheduler/cron must run `php artisan schedule:run` every minute for `tracking:close-expired` in deployed environments.
- Production MySQL vs MariaDB decision to confirm before deployment.
