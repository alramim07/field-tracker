# Phase 6 — Admin Dashboard & Live Map Design

## Context

Field Tracker backend (Laravel 12, Sanctum, MariaDB, Phase 1-2 complete) already provides auth, device registration, tracking sessions, and location batch ingestion APIs. Phase 6 adds the administrative web product: a super-admin-managed RBAC system, an admin dashboard, tracker-user/admin management, and a real-time Google Maps live view. The mobile app is not built yet; backend API contracts must remain stable and mobile-compatible.

## Intent / Why

PRAN RFL BD needs a professional operations dashboard where administrators can see who is tracking, who is late/missing, manage field users/admins, and monitor live locations in real time. The super admin must control who can access what, with granular action permissions and admin designations.

## Goals

- Super admin can create/manage admins, assign granular section-action permissions, and set admin role/designation labels.
- Admins/super admins can create/manage tracker users, including duty time (`duty_start`, `duty_end`).
- Dashboard shows operational metrics: currently using app, late to start tracker, not using tracker during duty window, started at duty time but inactive now.
- Live Map shows all authorized users with avatar pin, accuracy radius, pulsing halo, default Google Maps style, updating in real time via Reverb.
- Modern, minimalist, professional PRAN RFL branding with dark/light mode.
- OOP-first Laravel structure: services, policies, form requests, API resources/DTOs, query/services, events, enums.

## Non-goals (Phase 6)

- Mobile Flutter/Kotlin app (Phases 3-5).
- Full notification delivery system (bell is a UI placeholder; backend events/notifications later).
- Full settings system (Settings page is a placeholder with extension points).
- Advanced reporting/export (Phase 7).
- Production hardening/rate-limit tuning beyond baseline (Phase 8).
- Full multi-tenant/org hierarchy beyond current region/area fields.

## Approved Product Decisions

- Backend remains Laravel; admin frontend is React + Tailwind inside same Laravel repo, separate frontend directory: `resources/admin-ui/` with its own Vite/React/Tailwind setup. *(User-approved)*
- Dashboard is another client to Laravel APIs; mobile app unaffected if APIs remain stable.
- Live map updates automatically in real time (Reverb/WebSockets), WhatsApp-live-location-like movement.
- Duty time is set by admin/super admin on user creation/edit; viewable on mobile app, editable only by admin/super admin.
- Permission granularity: section + action (`view`, `create`, `edit`, `delete`); “Add Admin User” and permission assignment are super-admin-only.
- Settings and Notifications are UI placeholders for now.

## My Calls (builder-resolvable unless overridden)

- **Auth:** Laravel Sanctum SPA cookie auth for same-origin admin SPA; keep existing token auth for mobile/API clients. CSRF protected SPA routes. *(Builder-check call: use dedicated admin web auth endpoints, e.g. `POST /api/v1/admin/auth/login`, `POST /api/v1/admin/auth/logout`, `GET /api/v1/admin/auth/me`, using session/cookie auth for SPA; keep existing `POST /api/v1/login` token flow unchanged for mobile.)*
- **Frontend integration:** `resources/admin-ui/` is a separate Vite app (own `package.json`, `vite.config`, Tailwind config) built to Laravel public/build-admin assets; Laravel serves SPA shell route for `/admin/*`.
- **Designation:** store admin designation as `users.designation` (or admin profile field) separate from RBAC permissions; permissions control access, designation is display/label.
- **Duty time storage:** add `duty_start_time` and `duty_end_time` (time columns) to `employees` table; expose read-only in employee/mobile-facing APIs.
- **Permission model (approved):** keep `users.role` enum (`super_admin/admin/employee`) as coarse identity for backward compatibility and mobile/API responses; layer granular DB permissions on top for admin dashboard access. Super admin bypasses granular checks. Existing admin middleware/policies will be adapted to honor both without breaking mobile token flows.
- **Realtime:** Laravel Reverb private channels (Laravel-native WebSocket; preferred over adding Socket.io/Firebase for this stack) broadcasting latest-location DTO/resource only to authorized admins. *(Builder-check call: existing `/api/v1/admin/*` skeleton endpoints remain backward compatible/additive; Phase 6 may add new endpoints/resources for dashboard/live-map/admin management, but should not break existing response shapes without explicit versioning.)*
- **Branding:** modern minimalist neutral UI; PRAN red `#ED1D24` primary accent, `#FED000` sparse highlight/warning accent, white/dark surfaces. No loud full-red UI.

## Phase Breakdown

### 6A — Domain, RBAC, and OOP Foundation

**Purpose:** establish stable backend/domain structure before UI.

**Work:**
- Migrations: `employees.duty_start_time`, `employees.duty_end_time`, `users.designation` (nullable), `users.avatar_path` (nullable), RBAC tables (`permissions`, `roles` or `admin_roles`, `role_permission`, `user_role`/`admin_role_user`), `notifications` placeholder table only if needed later (prefer defer).
- Enums/value objects: `PermissionAction` (`view,create,edit,delete`), `DashboardSection`/permission keys, `DutyWindowStatus`.
- Models: `Permission`, `AdminRole`/`Role`, pivots; update `User`, `Employee`.
- Policies/Gates: `AdminAccessPolicy`-style per domain or centralized `PermissionGate` using permission keys like `dashboard.view`, `tracker_users.create`.
- Services (OOP): `Rbac/PermissionService`, `Rbac/RoleAssignmentService`, `Admin/TrackerUserService`, `Admin/AdminUserService`, `Dashboard/DashboardMetricsService`, `Tracking/LiveLocationBroadcastService`.
- FormRequests for all writes; API Resources/DTOs for reads.
- Audit logging via existing `AuditLogService` for admin/user/permission changes.

**OOP rules:**
- Controllers thin: validate via FormRequest, authorize via Policy/Gate, delegate to Service, return Resource.
- No query-heavy dashboard logic in controllers/components; use dedicated query/service classes.
- Permission keys defined in one enum/config class; avoid string scattering.

**Acceptance:**
- Migrations run; models/factories/seeders updated.
- Super admin can be seeded; permission map seeded.
- Unit/feature tests for permission resolution and duty time validation.

### 6B — Admin Auth, SPA Shell, and App Infrastructure

**Purpose:** deliver secure admin entry and reusable UI shell.

**Work:**
- Laravel routes: `/admin` SPA shell, `/admin/*` catch-all for SPA; `/api/v1/admin/auth/*` or session-based SPA endpoints.
- Sanctum SPA config for same-origin (`SANCTUM_STATEFUL_DOMAINS`, session/cookie/CORS as needed).
- API endpoints: login, logout, me, permissions/bootstrap.
- React app in `resources/admin-ui/`: Vite, React, TypeScript optional? *(call: use TypeScript if low friction, else JS; default JS for speed unless team prefers TS)*, Tailwind, router, auth store, API client.
- Layout: sidebar, header, content area, responsive behavior, dark/light mode toggle persisted locally.
- Route guards/components: `<Can permission="...">`, protected routes, super-admin-only controls.

**UI shell details:**
- Sidebar: Dashboard, Live Map, Tracker Users, Admins, Settings; bottom user avatar/name + logout.
- Header: notification bell (placeholder dropdown/panel), date/time, dark/light mode.
- Branding: PRAN RFL logo/text lockup area; neutral/minimal surfaces, red accent for active/primary actions.

**Acceptance:**
- Admin can log in/out; unauthorized redirected.
- Sidebar/header renders; dark/light works; permission-based menu visibility works.

### 6C — Admin & Tracker User Management

**Purpose:** implement core management workflows.

**Work:**
- Tracker Users CRUD: create user+employee, set duty start/end, region/area/designation/phone/status, assign device? (view existing), reset/disable as needed.
- Admins CRUD: super admin creates admins, assigns designation/role name label, assigns granular permissions.
- "Add an Admin User" button visible only to super admin; backend also enforces.
- List pages: search/filter/pagination; detail/edit forms.
- Validation: duty_end after duty_start or allow overnight? *(call: support same-day window initially; validate format and end>start unless product later requests overnight)*.

**APIs:**
- `GET/POST /api/v1/admin/admin-users`
- `GET/PUT/PATCH/DELETE /api/v1/admin/admin-users/{id}`
- `GET/POST /api/v1/admin/tracker-users`
- `GET/PUT/PATCH/DELETE /api/v1/admin/tracker-users/{id}`
- `GET/PUT /api/v1/admin/admin-users/{id}/permissions`

**Acceptance:**
- Super admin can create admin with selected actions; admin cannot access unpermitted sections/actions.
- Duty time saved and visible in API responses.

### 6D — Dashboard Metrics & Operational Lists

**Purpose:** give admins the at-a-glance operational view.

**Work:**
- `DashboardMetricsService`/query class computes:
  - `active_users`: employees with active tracking session now (currently using app), regardless of duty window.
  - `late_to_start`: employee has duty window configured for today and no active session at/after duty start grace point; *(builder-check call: no grace period in v1, evaluated after `duty_start_time`; employees with no duty window excluded)*.
  - `not_using_tracker`: employee has configured duty window, is within duty window now, and has no active tracking session now.
  - `started_duty_inactive_now`: employee started a session during today’s duty window but currently has no active session; if they start again, removed from this list.
- Endpoint: `GET /api/v1/admin/dashboard/summary`.
- UI: stat cards, list/table for inactive-after-duty-start users, timestamps/status badges.
- Timezone: use app/company timezone consistently *(call: use configured app timezone; document assumption)*.

**OOP/Performance:**
- Use targeted SQL/aggregates; avoid N+1; cache short-lived summary if needed (optional).
- Keep metric definitions in one service/class to prevent duplication.

**Acceptance:**
- Feature tests cover all four metric definitions with fixed time.
- Dashboard renders cards and list with authorized data.

### 6E — Live Map & Real-time Broadcasting

**Purpose:** real-time operational map.

**Work:**
- Reverb installation/config (`laravel/reverb`), broadcasting config, private channel auth.
- Event: `LatestLocationUpdated` (existing) or dedicated broadcast event carrying DTO: employee id/code/name/avatar, lat/lng, accuracy, recorded_at, battery/speed optional, status.
- Private channel: e.g. `private-admin.live-locations` authorized by `live_map.view`; future-proof for region scoping.
- Endpoint: `GET /api/v1/admin/live-locations` for initial map load.
- **Scope (approved):** show everyone with a `latest_locations` record on the Live Map; admins with `live_map.view` see all regions/areas in Phase 6 (region scoping deferred).
- Frontend map: Google Maps JS loader/service abstraction, default map style, avatar marker, accuracy circle, pulsing halo, **smooth marker movement** on updates (linear interpolation/Lerp or equivalent animation between old/new lat-lng over a short duration; no jumping), reconnect handling.
- Google Maps API key via env (`VITE_GOOGLE_MAPS_API_KEY` / backend-exposed config endpoint as appropriate).

**Security:**
- Never broadcast sensitive fields unnecessarily.
- Channel auth required; no public location channel.
- Throttle/reuse latest-position events; no raw firehose.

**Acceptance:**
- Initial locations render; websocket update moves marker without full page reload.
- Unauthorized users cannot subscribe.

## Data Model Changes (proposed)

| Table | Change | Notes |
|---|---|---|
| `users` | add `designation` nullable string, `avatar_path` nullable string | display/profile |
| `employees` | add `duty_start_time` time nullable, `duty_end_time` time nullable | duty window |
| `admin_roles` | id, name, slug, description, is_system | super_admin/admin/custom |
| `permissions` | id, key, section, action, label | seeded fixed keys |
| `admin_role_permission` | role_id, permission_id | pivot |
| `admin_role_user` | user_id, admin_role_id | pivot; super admin may bypass |

*Alternative:* simpler `user_permissions` table; choose one during 6A based on final RBAC complexity. *(Builder may simplify if it preserves granular action assignment and auditability.)*

## Permission Keys (initial)

Sections: `dashboard`, `live_map`, `tracker_users`, `admins`, `settings`.
Actions: `view`, `create`, `edit`, `delete`.
Examples: `dashboard.view`, `live_map.view`, `tracker_users.create`, `admins.edit`.
Super-admin-only hard rules: `admins.create`, permission assignment endpoints, role/permission system management.

## Frontend Structure (`resources/admin-ui/`)

```text
resources/admin-ui/
  package.json
  vite.config.js
  tailwind.config.js / css
  index.html (if SPA) or entry
  src/
    app/            # router, providers, bootstrapping
    api/            # axios/fetch clients, endpoint modules
    components/     # reusable UI
    features/
      auth/
      dashboard/
      live-map/
      tracker-users/
      admins/
      settings/
    layouts/
    hooks/
    store/          # auth/permissions/theme
    styles/
    types/          # if TS later
```

**OOP-ish frontend discipline:** API modules, service-like map/broadcast clients, reusable permission guards, feature folders; avoid large page components with mixed data+render+mutation logic.

## Branding / UI Direction

- **Primary accent:** PRAN red `#ED1D24` (buttons/active states/small highlights only).
- **Secondary accent:** `#FED000` very sparingly (warnings/highlights).
- **Base:** white/light gray surfaces in light mode; deep neutral/slate surfaces in dark mode.
- **Style:** modern, minimalist, professional, enterprise; clear spacing, readable typography, restrained shadows.
- **Assets:** use PRAN RFL logo asset if provided later; placeholder text lockup initially.

## Testing Strategy

- **Backend feature tests:** RBAC/policy tests for every protected endpoint; duty time validation; dashboard metric calculations with frozen time; Reverb channel auth; live location payload shape.
- **Backend unit tests:** permission resolution, metric service edge cases.
- **Frontend:** component/smoke tests if test stack added; minimum lint/build check.
- **Manual smoke:** login as super admin/admin, permission gating, create tracker user with duty time, dashboard metrics, live map updates.

## Risks / Mitigations

- **RBAC complexity creep:** keep fixed permission key map; super admin bypass; test matrix.
- **Realtime infra:** Reverb requires worker/process config; provide fallback polling flag in UI/service layer if needed *(not default)*.
- **Duty time/timezone ambiguity:** centralize in metrics service; document app timezone assumption.
- **Separate frontend build:** integrate with Laravel scripts/CI; keep build output path deterministic.
- **Google Maps key/security:** env-only key, HTTP referrer restrictions in deployment docs.

## Rollout / Migration Notes

- Additive migrations; backfill null duty times.
- Seed default roles/permissions and super admin assignment.
- Keep existing mobile APIs backward compatible; add duty fields read-only.
- Document Reverb/queue/node build requirements in README/docs.

## Open Builder Choices

- JS vs TS for `resources/admin-ui/` (default JS for speed).
- Exact RBAC schema shape (role-permission pivots vs user-permission) as long as granular actions + auditability preserved.
- Overnight duty windows deferred unless requested.
- Notification storage/real delivery deferred; bell placeholder only.