# Phase 6 — Admin Dashboard & Live Map Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a secure PRAN RFL-branded admin dashboard in Laravel + React/Tailwind with granular RBAC, tracker/admin management, duty-time-aware metrics, and real-time Google Maps live tracking via Reverb.

**Architecture:** Keep Laravel 12 as the backend/API authority. Add additive admin SPA auth (Sanctum cookie/session) alongside existing mobile token auth. Implement RBAC as DB-backed permissions layered over existing `users.role`. Build a separate Vite React/Tailwind SPA under `resources/admin-ui/` served by Laravel, consuming `/api/v1/admin/*` APIs and private Reverb channels.

**Tech Stack:** PHP 8.2, Laravel 12, Sanctum, MySQL/MariaDB, PHPUnit, Laravel Reverb/broadcasting, React, Vite, Tailwind CSS, Google Maps JS API, Axios.

**Spec:** `docs/superpowers/specs/2026-11-23-phase-6-admin-dashboard-design.md`

## Global Constraints

- Preserve existing mobile token auth (`POST /api/v1/login`, Bearer tokens) and existing `/api/v1/admin/*` skeleton endpoint response shapes; Phase 6 changes are additive/backward compatible.
- Keep `users.role` enum (`super_admin`, `admin`, `employee`) as coarse identity; granular dashboard permissions layer on top. Super admin bypasses granular checks.
- Duty time fields: `employees.duty_start_time`, `employees.duty_end_time` (time, nullable). Editable by admin/super admin only; read-only in mobile-facing APIs.
- Live Map scope: show everyone with a `latest_locations` record; `live_map.view` sees all regions/areas in Phase 6.
- Realtime: Laravel Reverb private channels; no Socket.io/Firebase. Smooth marker movement (Lerp/animation), no jumping.
- Frontend: `resources/admin-ui/` separate Vite/React/Tailwind app inside this Laravel repo.
- Settings and Notifications are UI placeholders only in Phase 6.
- PRAN branding: restrained use of `#ED1D24`, white/neutral surfaces, sparse `#FED000`.

## Review Focus

- **Permission bypass/leak:** an admin without a section action must get 403 from API and no UI control; super-admin-only actions (`admins.create`, permission assignment) must never be reachable by normal admin.
- **Auth boundary regression:** admin SPA cookie auth must not break existing mobile Bearer token login/me/logout/device/tracking/location flows.
- **Duty-window/timezone correctness:** metric counts must respect app timezone and configured duty window; employees without duty times must not be miscounted as late/not-using.
- **Live location privacy:** Reverb payloads and live-location endpoints must expose only authorized fields; channel auth must reject unauthorized users.
- **Realtime map stability:** marker updates must animate smoothly, handle reconnect/disconnect, and not duplicate markers on repeated events.

---

## 6A — Domain, RBAC, and OOP Foundation

### Task 1: Add duty time and admin profile fields

**Files:**
- Create: `database/migrations/<timestamp>_add_duty_time_and_profile_fields.php`
- Modify: `app/Models/Employee.php`
- Modify: `app/Models/User.php`
- Modify: `database/factories/EmployeeFactory.php` (if exists) or create as needed
- Test: `tests/Feature/Admin/DutyTimeTest.php`

**Interfaces:**
- Produces: `Employee::$fillable` includes `duty_start_time`, `duty_end_time`; `User::$fillable` includes `designation`, `avatar_path`.

- [ ] **Step 1: Write failing test**

```php
public function test_employee_duty_time_fields_are_persisted(): void
{
    $employee = \App\Models\Employee::factory()->create([
        'duty_start_time' => '09:00:00',
        'duty_end_time' => '21:00:00',
    ]);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'duty_start_time' => '09:00:00',
    ]);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_employee_duty_time_fields_are_persisted`  
Expected: FAIL (column/fillable missing).

- [ ] **Step 3: Add migration and model fillable/casts**

Add nullable `duty_start_time`, `duty_end_time` time columns to `employees`; nullable `designation`, `avatar_path` strings to `users`. Update `$fillable`/casts in `Employee` and `User`. Keep backward compatibility.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_employee_duty_time_fields_are_persisted`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models/Employee.php app/Models/User.php database/factories tests/Feature/Admin/DutyTimeTest.php
git commit -m "feat(admin): add duty time and profile fields"
```

### Task 2: Create RBAC schema and models

**Files:**
- Create: `database/migrations/<timestamp>_create_rbac_tables.php`
- Create: `app/Models/AdminRole.php`
- Create: `app/Models/Permission.php`
- Create: `app/Enums/PermissionAction.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Admin/RbacSchemaTest.php`

**Interfaces:**
- Produces: `Permission::query()`, `AdminRole::query()`, `User::adminRoles()`, `User::hasPermission(string $key): bool` (or equivalent service contract used by policies).

- [ ] **Step 1: Write failing test**

```php
public function test_admin_role_can_be_assigned_permissions(): void
{
    $role = \App\Models\AdminRole::factory()->create(['slug' => 'ops-admin']);
    $perm = \App\Models\Permission::factory()->create(['key' => 'dashboard.view']);
    $role->permissions()->attach($perm);

    $this->assertTrue($role->permissions->contains('key', 'dashboard.view'));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_admin_role_can_be_assigned_permissions`  
Expected: FAIL (tables/models missing).

- [ ] **Step 3: Implement RBAC tables/models/enums**

Tables: `admin_roles`, `permissions` (unique `key`, `section`, `action`, `label`), `admin_role_permission`, `admin_role_user`. Add factories and Eloquent relations. `User::adminRoles()` belongsToMany. Keep implementation simple; no over-abstraction.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_admin_role_can_be_assigned_permissions`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations app/Models app/Enums database/factories tests/Feature/Admin/RbacSchemaTest.php
git commit -m "feat(admin): add RBAC schema and models"
```

### Task 3: Permission resolution service and gate/policy integration

**Files:**
- Create: `app/Services/Rbac/PermissionService.php`
- Create: `app/Support/Permissions/PermissionKeys.php` (or enum-backed registry)
- Create: `database/seeders/PermissionSeeder.php`
- Modify: `app/Providers/AppServiceProvider.php` or create `AuthServiceProvider`-style gate registration in existing provider pattern
- Modify: `app/Http/Middleware/EnsureUserIsAdmin.php`
- Test: `tests/Feature/Admin/PermissionGateTest.php`

**Interfaces:**
- Produces: `PermissionService::userHasPermission(User $user, string $key): bool`; Gate ability `permission:{key}` or `can:permission,key` convention (pick one and document in code).

- [ ] **Step 1: Write failing tests**

```php
public function test_super_admin_bypasses_granular_permission(): void { /* super admin without explicit assignment can view */ }
public function test_admin_without_permission_gets_forbidden(): void { /* normal admin denied */ }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=PermissionGateTest`  
Expected: FAIL.

- [ ] **Step 3: Implement permission registry, seeder, service, gate**

Seed fixed keys for dashboard/live_map/tracker_users/admins/settings x view/create/edit/delete. `PermissionService` returns true for super admin; otherwise checks assigned role permissions. Register Gates. Update `EnsureUserIsAdmin` to remain coarse admin gate (do not break existing admin routes) while adding permission middleware/ability checks for new granular endpoints.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=PermissionGateTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Rbac app/Support database/seeders app/Providers app/Http/Middleware tests/Feature/Admin/PermissionGateTest.php
git commit -m "feat(admin): add permission service and gates"
```

### Task 4: Admin SPA auth endpoints (Sanctum cookie)

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/AdminAuthController.php`
- Create: `app/Http/Requests/Admin/AdminLoginRequest.php`
- Modify: `routes/api.php`
- Modify: `config/sanctum.php` / `.env.example` as needed for stateful domains
- Modify: `bootstrap/app.php` (Sanctum SPA middleware if required)
- Test: `tests/Feature/Admin/AdminSpaAuthTest.php`

**Interfaces:**
- Produces: `POST /api/v1/admin/auth/login`, `POST /api/v1/admin/auth/logout`, `GET /api/v1/admin/auth/me`.
- Consumes: existing `User` role/status checks.

- [ ] **Step 1: Write failing tests**

```php
public function test_admin_can_login_via_spa_session(): void { /* session cookie auth, no plain token required */ }
public function test_employee_cannot_login_to_admin_spa(): void { /* 403/422 */ }
public function test_existing_mobile_token_login_still_works(): void { /* regression guard */ }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AdminSpaAuthTest`  
Expected: FAIL.

- [ ] **Step 3: Implement admin SPA auth**

Use session guard/cookie auth for admin SPA endpoints; return user + permissions/bootstrap payload on me/login. Do not change existing `/api/v1/login` token behavior. Add CSRF/sanctum stateful config comments.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=AdminSpaAuthTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/Admin/AdminAuthController.php app/Http/Requests/Admin routes/api.php config/sanctum.php bootstrap/app.php tests/Feature/Admin/AdminSpaAuthTest.php
git commit -m "feat(admin): add SPA auth endpoints"
```

## 6B — Admin UI Shell

### Task 5: Scaffold `resources/admin-ui` Vite React Tailwind app

**Files:**
- Create: `resources/admin-ui/package.json`
- Create: `resources/admin-ui/vite.config.js`
- Create: `resources/admin-ui/index.html`
- Create: `resources/admin-ui/src/main.jsx`
- Create: `resources/admin-ui/src/App.jsx`
- Create: `resources/admin-ui/src/styles.css`
- Modify: `package.json` (root scripts to build admin UI, or document separate build)
- Modify: `routes/web.php` (admin SPA catch-all route)
- Create: `resources/views/admin.blade.php`
- Test: `tests/Feature/Admin/AdminSpaShellTest.php`

**Interfaces:**
- Produces: `GET /admin` returns SPA shell; Vite build outputs to deterministic Laravel public path (e.g. `public/build-admin`).

- [ ] **Step 1: Write failing test**

```php
public function test_admin_shell_route_returns_ok(): void
{
    $this->get('/admin')->assertOk();
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=test_admin_shell_route_returns_ok`  
Expected: FAIL.

- [ ] **Step 3: Implement SPA scaffold and Laravel shell route**

Create separate frontend directory with its own package.json/Vite/Tailwind config. Add Blade shell that loads built assets in prod and Vite dev server in local (document env). Add `/admin/{any?}` catch-all returning shell view.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=test_admin_shell_route_returns_ok`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/admin-ui resources/views/admin.blade.php routes/web.php package.json tests/Feature/Admin/AdminSpaShellTest.php
git commit -m "feat(admin-ui): scaffold React Tailwind admin app"
```

### Task 6: Admin UI auth, layout, theme, and permission guards

**Files:**
- Create: `resources/admin-ui/src/api/client.js`
- Create: `resources/admin-ui/src/features/auth/*`
- Create: `resources/admin-ui/src/layouts/AdminLayout.jsx`
- Create: `resources/admin-ui/src/components/Can.jsx`
- Create: `resources/admin-ui/src/store/auth.js` (or context)
- Modify: `resources/admin-ui/src/App.jsx`

**Interfaces:**
- Produces: login page, protected routes, sidebar/header/layout, dark/light toggle, permission-based menu rendering.

- [ ] **Step 1: Write failing frontend smoke/build check**

Add a minimal test or build assertion script (if no frontend test runner, use `npm run build` in admin-ui as verification step).

- [ ] **Step 2: Run to verify it fails/red state**

Run: `npm --prefix resources/admin-ui run build`  
Expected: FAIL before files exist/configured.

- [ ] **Step 3: Implement layout/auth/guards**

Sidebar items: Dashboard, Live Map, Tracker Users, Admins, Settings; bottom user avatar/name/logout. Header: notification bell placeholder, date/time, dark/light. `Can` hides controls without permission; super-admin-only Add Admin button guarded.

- [ ] **Step 4: Run build to verify it passes**

Run: `npm --prefix resources/admin-ui install && npm --prefix resources/admin-ui run build`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/admin-ui
git commit -m "feat(admin-ui): add shell layout auth and guards"
```

## 6C — Admin & Tracker User Management

### Task 7: Tracker user CRUD API with duty time

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/TrackerUserController.php`
- Create: `app/Services/Admin/TrackerUserService.php`
- Create: `app/Http/Requests/Admin/StoreTrackerUserRequest.php`
- Create: `app/Http/Requests/Admin/UpdateTrackerUserRequest.php`
- Create: `app/Http/Resources/Admin/TrackerUserResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Admin/TrackerUserManagementTest.php`

**Interfaces:**
- Produces: CRUD endpoints under `/api/v1/admin/tracker-users` with `duty_start_time`, `duty_end_time`.

- [ ] **Step 1: Write failing tests**

```php
public function test_admin_can_create_tracker_user_with_duty_time(): void { /* assert 201 + db has duty times */ }
public function test_admin_without_tracker_users_create_permission_gets_403(): void { /* granular RBAC */ }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=TrackerUserManagementTest`  
Expected: FAIL.

- [ ] **Step 3: Implement service/controller/requests/resource**

Thin controller; service handles user+employee creation/update transaction. Validate time format and `duty_end_time > duty_start_time`. Audit log create/update/delete.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=TrackerUserManagementTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/Admin/TrackerUserController.php app/Services/Admin/TrackerUserService.php app/Http/Requests/Admin app/Http/Resources/Admin routes/api.php tests/Feature/Admin/TrackerUserManagementTest.php
git commit -m "feat(admin): add tracker user management API"
```

### Task 8: Admin user CRUD + permission assignment (super-admin-only)

**Files:**
- Create: `app/Http/Controllers/Api/V1/Admin/AdminUserController.php`
- Create: `app/Services/Admin/AdminUserService.php`
- Create: `app/Http/Requests/Admin/StoreAdminUserRequest.php`
- Create: `app/Http/Requests/Admin/UpdateAdminPermissionsRequest.php`
- Create: `app/Http/Resources/Admin/AdminUserResource.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Admin/AdminUserManagementTest.php`

**Interfaces:**
- Produces: `/api/v1/admin/admin-users` CRUD + permission assignment endpoint; super-admin-only create/assign.

- [ ] **Step 1: Write failing tests**

```php
public function test_super_admin_can_create_admin_with_permissions_and_designation(): void {}
public function test_normal_admin_cannot_create_admin_user(): void {}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=AdminUserManagementTest`  
Expected: FAIL.

- [ ] **Step 3: Implement super-admin-guarded service/controller**

Store admin user with `role=admin`, designation, assigned permissions/role mapping. Enforce super-admin-only on create and permission assignment via policy/gate. Audit log.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=AdminUserManagementTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/Admin/AdminUserController.php app/Services/Admin/AdminUserService.php app/Http/Requests/Admin app/Http/Resources/Admin routes/api.php tests/Feature/Admin/AdminUserManagementTest.php
git commit -m "feat(admin): add admin user and permission management"
```

### Task 9: Admin UI management screens

**Files:**
- Create: `resources/admin-ui/src/features/tracker-users/*`
- Create: `resources/admin-ui/src/features/admins/*`
- Modify: `resources/admin-ui/src/App.jsx`
- Modify: `resources/admin-ui/src/api/*`

**Interfaces:**
- Produces: Tracker Users and Admins pages with list/create/edit forms; Add Admin button only for super admin.

- [ ] **Step 1: Write failing build/smoke check**

Run: `npm --prefix resources/admin-ui run build` after adding route stubs/imports (expect fail until implemented).

- [ ] **Step 2: Implement screens**

Use API modules and reusable form/table components. Duty time inputs for tracker users. Permission checkboxes/actions for admin users. Show validation/server errors.

- [ ] **Step 3: Run build**

Run: `npm --prefix resources/admin-ui run build`  
Expected: PASS.

- [ ] **Step 4: Manual smoke via browser/dev server (document command)**

Run: `npm --prefix resources/admin-ui run dev` and/or Laravel serve.  
Expected: pages render, guarded by permissions.

- [ ] **Step 5: Commit**

```bash
git add resources/admin-ui
git commit -m "feat(admin-ui): add tracker and admin management screens"
```

## 6D — Dashboard Metrics

### Task 10: Dashboard metrics service and endpoint

**Files:**
- Create: `app/Services/Dashboard/DashboardMetricsService.php`
- Create: `app/Http/Controllers/Api/V1/Admin/DashboardController.php`
- Create: `app/Http/Resources/Admin/DashboardSummaryResource.php` (or plain JSON response DTO)
- Modify: `routes/api.php`
- Test: `tests/Feature/Admin/DashboardMetricsTest.php`

**Interfaces:**
- Produces: `GET /api/v1/admin/dashboard/summary` with four metric counts and inactive-after-duty-start list.

- [ ] **Step 1: Write failing tests with frozen time**

```php
Carbon::setTestNow('2026-11-23 10:00:00'); // app timezone
// create employees with duty windows/sessions and assert metric counts
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=DashboardMetricsTest`  
Expected: FAIL.

- [ ] **Step 3: Implement metric service**

Centralize definitions in `DashboardMetricsService`. Use efficient queries/scopes; exclude no-duty-window users from late/not-using. Return stable keys: `active_users`, `late_to_start`, `not_using_tracker`, `started_duty_inactive_now`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=DashboardMetricsTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Dashboard app/Http/Controllers/Api/V1/Admin/DashboardController.php app/Http/Resources/Admin routes/api.php tests/Feature/Admin/DashboardMetricsTest.php
git commit -m "feat(admin): add dashboard metrics service and endpoint"
```

### Task 11: Dashboard UI cards and inactive list

**Files:**
- Create: `resources/admin-ui/src/features/dashboard/*`
- Modify: `resources/admin-ui/src/api/*`

**Interfaces:**
- Produces: Dashboard page with four stat cards and list/table for started-at-duty-but-inactive-now.

- [ ] **Step 1: Build check red/green cycle**

Run: `npm --prefix resources/admin-ui run build`.

- [ ] **Step 2: Implement dashboard UI**

Use neutral/minimal cards, PRAN red accent for key states, responsive layout. Handle loading/empty/error states.

- [ ] **Step 3: Run build**

Run: `npm --prefix resources/admin-ui run build`  
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/admin-ui
git commit -m "feat(admin-ui): add dashboard metrics view"
```

## 6E — Live Map & Realtime

### Task 12: Install/configure Reverb and broadcasting auth

**Files:**
- Modify: `composer.json` / `composer.lock` (via composer require)
- Create: `config/broadcasting.php` (if missing)
- Create/modify: `config/reverb.php` (package install)
- Modify: `.env.example`
- Modify: `routes/channels.php` or `bootstrap/app.php` channel registration
- Test: `tests/Feature/Admin/LiveLocationBroadcastAuthTest.php`

**Interfaces:**
- Produces: private channel `admin.live-locations` authorized for `live_map.view`.

- [ ] **Step 1: Write failing channel auth test**

```php
Broadcast::channel('admin.live-locations', ...); // assert authorized/forbidden
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=LiveLocationBroadcastAuthTest`  
Expected: FAIL.

- [ ] **Step 3: Install/configure Reverb**

Run: `composer require laravel/reverb` and package install commands as needed. Configure private broadcast auth route and channel authorization. Keep default broadcast driver safe for non-realtime envs.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=LiveLocationBroadcastAuthTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add composer.* config .env.example routes bootstrap tests/Feature/Admin/LiveLocationBroadcastAuthTest.php
git commit -m "feat(admin): configure reverb and live location channel auth"
```

### Task 13: Live location endpoint and broadcast payload

**Files:**
- Create: `app/Events/Admin/LiveLocationUpdated.php` (or adapt existing `LatestLocationUpdated`)
- Create: `app/Http/Resources/Admin/LiveLocationResource.php`
- Modify: `app/Services/Location/LocationBatchIngestionService.php` (dispatch broadcast-safe event)
- Modify: `app/Http/Controllers/Api/V1/Admin/AdminController.php` or create `LiveLocationController.php`
- Test: `tests/Feature/Admin/LiveLocationApiTest.php`

**Interfaces:**
- Produces: initial `GET /api/v1/admin/live-locations` (everyone with `latest_locations` record) and broadcast event payload with only authorized fields.

- [ ] **Step 1: Write failing tests**

```php
public function test_live_locations_endpoint_returns_latest_location_users(): void {}
public function test_live_location_payload_contains_only_safe_fields(): void {}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=LiveLocationApiTest`  
Expected: FAIL.

- [ ] **Step 3: Implement resource, endpoint, broadcast event**

Payload fields: employee id, display name, avatar/url placeholder, lat, lng, accuracy, recorded_at, status/session info as approved. No secrets/sensitive unnecessary data. Ensure event fires when latest location is updated.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=LiveLocationApiTest`  
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Events app/Http/Resources/Admin app/Http/Controllers/Api/V1/Admin app/Services/Location routes/api.php tests/Feature/Admin/LiveLocationApiTest.php
git commit -m "feat(admin): add live location endpoint and broadcast event"
```

### Task 14: Live Map UI with smooth realtime updates

**Files:**
- Create: `resources/admin-ui/src/features/live-map/*`
- Create: `resources/admin-ui/src/services/googleMaps.js`
- Create: `resources/admin-ui/src/services/realtime.js`
- Modify: `resources/admin-ui/src/api/*`

**Interfaces:**
- Produces: Live Map page with default Google Maps, avatar pins, accuracy radius, pulsing halo, Lerp-smoothed movement, reconnect handling.

- [ ] **Step 1: Build check / smoke setup**

Run: `npm --prefix resources/admin-ui run build` (expect fail before implementation).

- [ ] **Step 2: Implement map and realtime client**

Initial load from `/api/v1/admin/live-locations`; subscribe to private Reverb channel. On update, animate marker from old to new lat/lng over duration (e.g. 500-1000ms) using requestAnimationFrame/Lerp; update accuracy circle/halo. Deduplicate by employee id.

- [ ] **Step 3: Run build**

Run: `npm --prefix resources/admin-ui run build`  
Expected: PASS.

- [ ] **Step 4: Manual realtime smoke**

Run backend + Reverb + admin UI; post location batch; confirm marker moves smoothly without refresh.  
Expected: live smooth update.

- [ ] **Step 5: Commit**

```bash
git add resources/admin-ui
git commit -m "feat(admin-ui): add realtime live map with smooth marker animation"
```

## Final Verification

### Task 15: Full verification, docs, and handoff

**Files:**
- Modify: `docs/API_DOCUMENTATION.md`
- Modify: `docs/TESTING.md`
- Modify: `docs/CHANGELOG.md`
- Modify: `README.md`
- Create: `docs/superpowers/plans/2026-11-23-phase-6-admin-dashboard-verification.md` (optional report)

**Interfaces:**
- Produces: verified Phase 6 with updated docs and manual test instructions.

- [ ] **Step 1: Run backend tests**

Run: `php artisan test`  
Expected: PASS (all existing + new Phase 6 tests).

- [ ] **Step 2: Run frontend build/lint**

Run: `npm --prefix resources/admin-ui install && npm --prefix resources/admin-ui run build`  
Expected: PASS.

- [ ] **Step 3: Run manual smoke checklist**

Verify: super admin creates admin with limited permissions; admin sees only allowed sections; tracker user duty time CRUD; dashboard metric cards; live map initial load + realtime smooth movement; Settings/Notifications placeholders visible.

- [ ] **Step 4: Update docs**

Document new endpoints, RBAC keys, Reverb/env vars (`BROADCAST_CONNECTION`, Reverb keys, `VITE_GOOGLE_MAPS_API_KEY`), admin UI build/dev commands, and manual test steps.

- [ ] **Step 5: Commit**

```bash
git add docs README.md resources/admin-ui package.json composer.* app config database routes tests
git commit -m "feat: implement phase 6 admin dashboard and live map"
```

## Notes for Implementers

- Prefer additive migrations; do not rewrite old migrations.
- Keep controllers thin; put business rules in services/query classes.
- Do not expose employee/user secrets in resources/broadcast payloads.
- If Reverb install requires env/config changes, document them in `.env.example` and README; keep tests runnable in CI without a live Reverb server where possible (use `BROADCAST_CONNECTION=log/null` in tests).
- Use `Carbon::setTestNow()` for metric tests; reset in teardown if needed.