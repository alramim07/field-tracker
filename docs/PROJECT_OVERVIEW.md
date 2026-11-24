# Field Tracker — Project Overview

## Purpose
Field Tracker is an enterprise Android live location tracking and management system for ~300–400 Area Managers. It provides live location monitoring, movement/history visualization, session management, device status, and reporting for authorized Head Office users.

## This is NOT
- An attendance management system.
- Covert tracking. Tracking is explicit, session-based, and visible to employees.

## Core capabilities (target)
- Employee login and authorized device registration
- Explicit Start Duty / Stop Duty
- Fixed server-authoritative tracking session expiry
- Native Android foreground service location collection (Kotlin)
- Flutter employee UI
- Offline location queue + batch sync
- Live admin dashboard with OpenStreetMap by default and Google Maps retained for later activation
- Private WebSocket updates for live locations via Laravel Reverb
- Historical route visualization and reporting
- RBAC, audit logging, secure API

## Current status
Completed:
- **Phase 1 — Backend foundation:** Laravel 12, MariaDB, schema/migrations/models/enums/factories/seeders, baseline tests.
- **Phase 2 — Auth/tracking APIs:** Sanctum token auth, device/session APIs, location batch ingestion, latest location, employee self endpoints.
- **Phase 6 — Admin dashboard + Live Map:** RBAC, SPA cookie auth, admin/tracker user management, duty-time metrics, live location API, broadcast payload/channel auth, React/Tailwind admin UI, OSM-by-default map, collapsible sidebar, dynamic place names, and duty timers.

Remaining (6 phases):
- **Phase 3** — Flutter employee app.
- **Phase 4** — Native Kotlin FGS tracking engine.
- **Phase 5** — Room offline queue + batch sync.
- **Phase 7** — History/reporting.
- **Phase 8** — Security, performance, device/load testing.
- **Phase 9** — Production deployment.

Latest verification: `php artisan test` → **50 passed, 230 assertions**; admin UI production build → **PASS**.

## Tech stack
- Backend: Laravel 12, Sanctum (mobile Bearer + admin SPA cookie), MySQL/MariaDB-compatible, app timezone `Asia/Dhaka`; Redis planned for queue/cache.
- Realtime: `LatestLocationUpdated` broadcast event and private `admin.live-locations` channel auth implemented; full `laravel/reverb` runtime package/process still pending in this environment.
- Mobile: Flutter UI + Native Kotlin foreground tracking engine (Phases 3–5 pending).
- Admin: Laravel-served React + Tailwind Vite SPA in `resources/admin-ui/`, Leaflet/OpenStreetMap default, Google Maps implementation retained for later Settings-driven activation.
- DB: MariaDB (local dev) / MySQL-compatible target per spec.
