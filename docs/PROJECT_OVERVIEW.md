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
- Live admin dashboard with Google Maps + private WebSockets (Laravel Reverb)
- Historical route visualization and reporting
- RBAC, audit logging, secure API

## Current status
- **Phase 1 (Backend foundation):** in progress / foundation implemented.
- Laravel 12 scaffolded, MariaDB configured, core schema/migrations/models/enums/factories/seeders created, baseline tests passing.

## Tech stack
- Backend: Laravel 12, Sanctum, MySQL/MariaDB-compatible, Redis (planned), Reverb (planned)
- Mobile: Flutter UI + Native Kotlin tracking engine
- Admin: Laravel Blade + JS + Google Maps JS API
- DB: MariaDB (local dev) / MySQL-compatible target per spec
