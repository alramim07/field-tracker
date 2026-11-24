# Database Schema

## Tables

### users
- id, name, email (unique), password, role (default employee), status (default active), designation (nullable), avatar_path (nullable), email_verified_at, remember_token, timestamps
- Indexes: email unique, role, status

### regions
- id, name, code (unique), status, timestamps

### areas
- id, region_id FK -> regions, name, code (unique), status, timestamps
- Indexes: (region_id, status)

### employees
- id, user_id FK unique -> users, employee_code (unique), phone, region_id FK nullable, area_id FK nullable, designation, status, duty_start_time (nullable, time), duty_end_time (nullable, time), timestamps
- Indexes: (region_id, area_id), status

### devices
- id, employee_id FK -> employees, device_uuid (unique), manufacturer, model, android_version, app_version, status, last_seen_at, registered_at, timestamps
- Indexes: (employee_id, status), last_seen_at

### tracking_policies
- id, name, start_time, end_time, duration_minutes, moving_interval_seconds (default 5), stationary_interval_seconds (default 30), status, created_by FK nullable -> users, timestamps

### tracking_sessions
- id, session_uuid (unique), employee_id FK, device_id FK, tracking_policy_id FK nullable, policy_snapshot (json), started_at, expires_at, ended_at, status, stop_reason, session_type (default `normal_duty`), counted (default `true`), timestamps
- `session_type`: `normal_duty` | `extra_duty`
- `stop_reason`: `manual`, `expired`, `revoked`, `system`, `extended_manual`, `midnight_auto`, `logout`
- `counted`: `false` for forgotten extra-duty midnight auto-stop audit sessions; counted sessions contribute to employee total tracking seconds.
- Indexes: expires_at, (employee_id,status), (device_id,status), (employee_id, session_type, started_at)

### location_logs
- id, event_uuid (unique), employee_id FK, device_id FK, session_id FK -> tracking_sessions, latitude(10,7), longitude(10,7), accuracy/speed/bearing/altitude decimal nullable, battery_level tinyint nullable, recorded_at, received_at, timestamps
- Indexes: (employee_id, recorded_at), (session_id, recorded_at), recorded_at, received_at

### latest_locations
- id, employee_id FK unique, session_id FK nullable -> tracking_sessions, latitude, longitude, accuracy/speed/bearing nullable, battery_level, recorded_at, received_at, timestamps
- Rule: update only when incoming recorded_at is newer.

### audit_logs
- id, user_id FK nullable, action, target_type, target_id, metadata (json), ip_address, timestamps
- Indexes: action, (target_type,target_id), created_at

### personal_access_tokens
- Sanctum default token storage.

## Notes
- FK strategy: restrict/cascade/nullOnDelete chosen per table to avoid accidental data loss; review before production.
- Partitioning/archival for `location_logs` deferred until benchmarking (per spec).
