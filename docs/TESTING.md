# Testing

## Latest automated run (Phase 3 docs/verification pass, 2026-11-24)

Commands run:
```powershell
php artisan test
npm --prefix resources/admin-ui run build
cd "C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile"
C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat analyze
C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test
C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat build apk --debug
```

Results: **PASS (backend/admin/flutter analyze/flutter test), BLOCKED (Android debug build)**
- Backend: **68 passed, 329 assertions** (`php artisan test`)
- Admin UI build: **PASS** (`npm --prefix resources/admin-ui run build`, Vite production build)
- Flutter analyze: **PASS** (`No issues found!`)
- Flutter test: **PASS** (`22 passed`)
- Android debug APK build: **BLOCKED** — missing Android NDK `28.2.13676358` at `C:\Android\Sdk` (`flutter build apk --debug` failed during Gradle configure)
- Real Android device/emulator foreground-service smoke: **NOT RUN** — no connected Android device/emulator in this environment

## Suites covered

### Backend (Laravel/PHPUnit)
- Unit example
- Feature example
- Phase 1 foundation: model relationships, unique constraints, enum casts, audit log service
- Auth API: login valid/invalid/inactive, me, logout, auth required, logout ends active tracking session, logout preserves expiry semantics
- Device API: register, duplicate same employee update, cross-employee rejection, admin revoke, non-admin forbidden
- Tracking API: start fixed expiry, duplicate active session blocked, normal duty cannot be manually stopped, extra duty can be manually stopped
- Extra duty tracking: normal session expires at duty end, extra duty requires normal session ended and once/day, forgotten extra duty auto-stops at midnight as uncounted, status uses `client_midnight_at` for phone-local day metadata
- Close expired command: closes forgotten extra duty as uncounted, closes expired normal session, idempotent
- Location batch API: accept+latest update, duplicate event dedupe, expired session reject, older event does not overwrite latest, event dispatch, foreign session reject
- Employee self API: own history/sessions/reports, cannot see other employee data
- Profile API: `/me` Phase 3 profile fields, self-service profile update restrictions, `total_tracking_seconds` counts only counted sessions, photo upload, password change revokes other tokens
- Admin API: admin access OK, employee forbidden
- Phase 6 admin: duty/profile fields, RBAC schema/gates, SPA auth, tracker/admin management, dashboard metrics, broadcast channel auth, live locations endpoint/payload, admin shell route
- Admin tracker audit: tracker user details include forgotten extra-duty audit
- Live Map data: Bangladesh factory coordinates, dynamic place-name resolution, previous-ping place name, tracker/offline durations, active-tracking flag, broadcast payload parity

### Mobile (Flutter)
- Auth: restore session clears non-employee session, login rejects non-employee role
- History: groups stationary pings into stopped period
- Home: extend duty button after duty end, fresh day reset after phone date changes, hide travel history before tracking starts, start duty tracking calls bridge and refreshes, does not fresh-reset normal same-day ended session
- Notifications: unexpected early stop unread, ignores scheduled expiry/excluded stop reasons, durable detection for unexpected stop/disappeared normal session, no alert at scheduled expiry, alerts when normal duty disappears before expiry and extra duty active
- Tracking bridge: start/stop/sync/getState/isRunning method-channel behavior, stopExtraDuty still stops native when backend stop fails, startTracking requests permissions and aborts when denied
- Profile: shows locked admin-managed fields

## Manual smoke test checklist
1. `php artisan serve`
2. Login employee (`employee@fieldtracker.local` / `password`) -> get token
3. Register device (`POST /api/v1/device/register`)
4. Start normal duty tracking (`POST /api/v1/tracking/start`) -> verify `session_type=normal_duty`, `counted=true`, server-calculated `expires_at`
5. Post location batch (`POST /api/v1/locations/batch`) -> accepted=1; repost same `event_uuid` -> duplicates=1
6. Post to expired session -> rejected
7. Check `/api/v1/me`, `/api/v1/me/location-history`, `/api/v1/me/sessions`, `/api/v1/me/reports`
8. Verify profile flows: `PATCH /api/v1/me/profile`, `POST /api/v1/me/photo`, `POST /api/v1/me/password`
9. After normal duty end, start extra duty once/day (`mode=extra_duty`, `client_midnight_at`) -> verify manual stop allowed only for extra duty
10. Run `php artisan tracking:close-expired` / scheduler and verify forgotten extra duty closes as `midnight_auto`, `counted=false`
11. Login admin (`superadmin@fieldtracker.local` / `password`) -> `/api/v1/admin/live-locations` returns data; tracker user detail includes `extra_duty_audit` for forgotten uncounted extra duty
12. Open `/admin/live-map` -> OpenStreetMap tiles load without API key; marker/accuracy/halo render; place names and tracker/offline durations display
13. Mobile device/emulator smoke (NOT RUN here): install/debug app, set `API_BASE_URL`, verify login, map, start duty, background/closed-app FGS sending, extra duty extend/stop, notifications, travel history movement/stops, profile, logout stops tracking

## Not yet tested / environment-limited
- Real Android device/emulator FGS behavior and closed-app smoke (no connected device/emulator in this environment)
- Android debug APK build in this environment (blocked by missing NDK `28.2.13676358` at `C:\Android\Sdk`)
- End-to-end Reverb runtime with `laravel/reverb` package installed and running (channel auth/payload covered by tests; package install/runtime pending)
- Automated admin UI browser E2E suite
- Load test (400 devices / 80 events/sec)
- Production MySQL/Redis deployment behavior
