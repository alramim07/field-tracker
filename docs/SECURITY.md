# Security

## Implemented (Phase 1)
- Password hashing via Laravel cast.
- Sanctum installed for token auth (endpoints pending Phase 2).
- Role/status fields on users; enums defined.
- AuditLog model + AuditLogService skeleton.

## Planned
- HTTPS in production, RBAC policies, private Reverb channels, rate limiting, input validation, device revocation, audit coverage, backup security. See master spec section 10.
