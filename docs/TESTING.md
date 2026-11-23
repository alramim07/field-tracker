# Testing

## Latest automated run (Phase 6, 2026-11-23)

Commands:
```powershell
php artisan test
npm --prefix resources/admin-ui run build
```

Result: **PASS**
- Backend: **47 passed, 139 assertions**
- Admin UI build: **PASS** (Vite production build)

## Suites covered
- Unit example
- Feature example
- Phase 1 foundation: model relationships, unique constraints, enum casts, audit log service
- Auth API: login valid/invalid/inactive, me, logout, auth required
- Device API: register, duplicate same employee update, cross-employee rejection, admin revoke, non-admin forbidden
- Tracking API: start fixed expiry, duplicate active session blocked, stop/status
- Location batch API: accept+latest update, duplicate event dedupe, expired session reject, older event does not overwrite latest, event dispatch, foreign session reject
- Employee self API: own history/sessions/reports, cannot see other employee data
- Admin API: admin access OK, employee forbidden
- Phase 6 admin: duty/profile fields, RBAC schema/gates, SPA auth, tracker/admin management, dashboard metrics, broadcast channel auth, live locations endpoint/payload, admin shell route

## Manual smoke test checklist
1. `php artisan serve`
2. Login employee (`employee@fieldtracker.local` / `password`) -> get token
3. Register device (`POST /api/v1/device/register`)
4. Start tracking (`POST /api/v1/tracking/start`) -> verify `expires_at` is server-calculated
5. Post location batch (`POST /api/v1/locations/batch`) -> accepted=1
6. Repost same `event_uuid` -> duplicates=1
7. Post to expired session -> rejected
8. Check `/api/v1/me/location-history`, `/me/sessions`, `/me/reports`
9. Login admin (`superadmin@fieldtracker.local` / `password`) -> `/api/v1/admin/live-locations` returns data
10. Open `/admin/live-map` -> OpenStreetMap tiles load without any API key; marker/accuracy/halo render from latest locations
11. Open `/admin` -> `Currently using app` is 0 for stale active sessions without a recent location ping

## Not yet tested
- Real Android device/FGS behavior
- End-to-end Reverb runtime with `laravel/reverb` package installed and running (channel auth/payload covered by tests; package install timed out in this environment)
- Admin UI browser E2E against live backend/Reverb
- Load test (400 devices / 80 events/sec)
- Production MySQL/Redis deployment behavior
