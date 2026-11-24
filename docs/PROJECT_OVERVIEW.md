# Field Tracker — Project Overview

## Purpose
Field Tracker is an enterprise Android live location tracking and management system for ~300–400 Area Managers. It provides live location monitoring, movement/history visualization, session management, device status, and reporting for authorized Head Office users.

## This is NOT
- An attendance management system.
- Covert tracking. Tracking is explicit, session-based, and visible to employees.

## Core capabilities
- Employee login and authorized device registration
- Explicit Start Duty; normal duty auto-stops at duty end (server-authoritative expiry)
- Extra duty extension once per phone-local day after normal duty ends; manual stop for extra duty only
- Forgotten extra duty auto-stops at phone-local midnight as uncounted; admin audit retains start/end/locations
- Native Android foreground service location collection (Kotlin) bridged to Flutter
- Flutter employee UI with OSM map, travel history, notifications, profile/photo/password, logout
- Location batch ingestion with dedupe and newer-recorded_at latest-location rule
- Live admin dashboard with OpenStreetMap by default and Google Maps retained for later activation
- Private WebSocket updates for live locations via Laravel Reverb (payload/channel auth implemented; runtime pending)
- RBAC, audit logging, secure API

## Current status
Completed / implemented on `phase-3-employee-app`:
- **Phase 1 — Backend foundation:** Laravel 12, MariaDB, schema/migrations/models/enums/factories/seeders, baseline tests.
- **Phase 2 — Auth/tracking APIs:** Sanctum token auth, device/session APIs, location batch ingestion, latest location, employee self endpoints.
- **Phase 3 — Employee Flutter app + native tracking:** `C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile` Flutter app, OSM map, auth/device registration, Kotlin foreground tracking service + bridge, normal/extra duty rules, phone-local day reset/auto-sync, travel history, notifications, drawer/profile/photo/password/logout, additive profile endpoints, scheduler command `tracking:close-expired`, and admin extra-duty audit.
- **Phase 6 — Admin dashboard + Live Map:** RBAC, SPA cookie auth, admin/tracker user management, duty-time metrics, live location API, broadcast payload/channel auth, React/Tailwind admin UI, OSM-by-default map, collapsible sidebar, dynamic place names, and duty timers.

Remaining / partial:
- **Phase 4/5** — Native tracking hardening and Room/offline sync robustness beyond current Phase 3 implementation.
- **Phase 7** — History/reporting expansion.
- **Phase 8** — Security, performance, device/load testing.
- **Phase 9** — Production deployment.

Latest verification (2026-11-24): `php artisan test` → **68 passed, 329 assertions**; `npm --prefix resources/admin-ui run build` → **PASS**; `flutter analyze` → **PASS** (no issues); `flutter test` → **PASS** (22 passed). `flutter build apk --debug` **BLOCKED** (missing Android NDK 28.2.13676358 at `C:\Android\Sdk`). Real Android device/emulator FGS smoke **NOT RUN**.

## Tech stack
- Backend: Laravel 12, Sanctum (mobile Bearer + admin SPA cookie), MySQL/MariaDB-compatible, app timezone `Asia/Dhaka`; Redis planned for queue/cache.
- Realtime: `LatestLocationUpdated` broadcast event and private `admin.live-locations` channel auth implemented; full `laravel/reverb` runtime package/process still pending in this environment.
- Mobile: Flutter UI + Native Kotlin foreground tracking engine (Phases 3–5 pending).
- Admin: Laravel-served React + Tailwind Vite SPA in `resources/admin-ui/`, Leaflet/OpenStreetMap default, Google Maps implementation retained for later Settings-driven activation.
- DB: MariaDB (local dev) / MySQL-compatible target per spec.
