# Security

## Implemented
- Password hashing via Laravel cast.
- Sanctum token auth for mobile (`/api/v1/login`, Bearer tokens).
- Sanctum SPA cookie auth for the admin dashboard (`/api/v1/admin/auth/*`), with CSRF cookie handling.
- Role/status fields on users, plus DB-backed granular permissions (`section.action`) and super-admin bypass.
- Policy/Gate authorization on admin APIs; live map region scoping is deferred but channel auth is enforced.
- Audit log model and `AuditLogService`.
- Device registration/revocation with employee ownership validation.
- Live-location endpoints and broadcast payloads whitelist only authorized fields.
- Private `admin.live-locations` channel authorization.
- Server-authoritative tracking session expiry and location `event_uuid` dedupe.
- Input validation on API endpoints.

## Planned / pending
- Full `laravel/reverb` runtime install and process supervision.
- HTTPS in production.
- Rate-limit tuning and load testing.
- Redis-backed queue/cache hardening.
- Backup security, monitoring, and production audit coverage. See master spec section 10.
