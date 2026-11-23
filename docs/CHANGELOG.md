# Changelog

## [Unreleased]

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
- `LatestLocationUpdated` event dispatch hook (Reverb wiring pending Phase 6).
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
- Flutter/Android toolchain not installed; required before Phase 3-5.
- Production MySQL vs MariaDB decision to confirm before deployment.
