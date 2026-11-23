# Deployment

## Status
Not deployed. Phase 9.

## Target
Ubuntu 24.04 LTS, Nginx, PHP 8.3+, MySQL, Redis, Supervisor, Laravel Reverb, HTTPS, backups, monitoring.

## Local dev (current)
- PHP 8.3.30 (Laragon), MariaDB 12.2.2, Composer 2.9.4, Node 24.14.1
- DB: `field_tracker`, user `root`, empty password, `127.0.0.1:3306`
- `php artisan migrate:fresh --seed` for local reset
