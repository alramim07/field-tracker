# Deployment

## Status
Not deployed. Phase 9 remains pending. Phases 1, 2, and 6 are implemented and verified locally, including the admin SPA at `/admin`.

Before deployment: install/configure Redis and the full `laravel/reverb` runtime, choose MySQL vs MariaDB, build `public/build-admin`, and run the production smoke checklist.

## Target
Ubuntu 24.04 LTS, Nginx, PHP 8.3+, MySQL, Redis, Supervisor, Laravel Reverb, HTTPS, backups, monitoring.

## Scheduler
Add a cron entry to run Laravel's scheduler every minute:

```bash
* * * * * cd /path/to/field-tracker && php artisan schedule:run >> /dev/null 2>&1
```

This is required for `tracking:close-expired`, which closes expired normal sessions and midnight-auto-stopped extra-duty sessions.

## Local dev (current)
- PHP 8.3.30 (Laragon), MariaDB 12.2.2, Composer 2.9.4, Node 24.14.1
- DB: `field_tracker`, user `root`, empty password, `127.0.0.1:3306`
- `php artisan migrate:fresh --seed` for local reset
