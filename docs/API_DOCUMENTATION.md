# API Documentation

Base URL: `/api/v1`  
Auth: Bearer token via Laravel Sanctum (`Authorization: Bearer <token>`)

## Authentication

### POST `/login`
Rate limited (`throttle:10,1`).
Request:
```json
{ "email": "user@example.com", "password": "secret", "device_name": "optional-device-name" }
```
Response `200`:
```json
{ "data": { "token": "<plain-text-token>", "user": { "id": 1, "name": "...", "email": "...", "role": "employee", "status": "active", "employee": { ... } } } }
```
Errors: `422` invalid credentials/inactive account.

### POST `/logout` (auth)
Revokes the current Sanctum token. If the employee has an active tracking session, backend ends it before token deletion (expired sessions preserve expiry semantics; active non-expired sessions are stopped with `stop_reason=logout`).
Response `200`: `{ "message": "Logged out successfully." }`

### GET `/me` (auth)
Returns the authenticated user. Additive Phase 3 fields:
```json
{
  "data": {
    "id": 1,
    "name": "...",
    "email": "...",
    "role": "employee",
    "status": "active",
    "phone": "...",
    "photo_url": "https://.../storage/avatars/...",
    "duty_start_time": "09:00:00",
    "duty_end_time": "17:00:00",
    "total_tracking_seconds": 1234,
    "employee": { "id": 1, "employee_code": "...", "region_id": 1, "area_id": 1, "designation": "..." }
  }
}
```
`total_tracking_seconds` includes only sessions where `counted=true`.

## Profile (auth)

### PATCH `/me/profile`
Updates employee self-service profile fields.
Request (any subset):
```json
{ "phone": "01...", "email": "user@example.com" }
```
Rules: `phone` nullable string max 50; `email` required when present, valid, unique ignoring current user. Admin-managed fields are not editable here.
Response `200`: `{ "data": { ...UserResource } }`

### POST `/me/photo`
Multipart form upload. Field: `photo` (image).
Response `200`:
```json
{ "data": { "photo_url": "https://.../storage/avatars/..." } }
```
Replaces previous avatar file when present.

### POST `/me/password`
Request:
```json
{ "current_password": "old", "password": "new-password", "password_confirmation": "new-password" }
```
Behavior: updates password and revokes other Sanctum tokens, keeping the current token.
Response `200`: `{ "message": "Password changed successfully." }`

## Devices

### POST `/device/register` (auth, employee)
Request:
```json
{ "device_uuid": "uuid", "manufacturer": "Samsung", "model": "A54", "android_version": "14", "app_version": "1.0.0" }
```
Behavior: same-employee duplicate updates device; cross-employee device UUID rejected (`422`). Audit logged.
Response `201`: `{ "data": { "id": 1, "device_uuid": "...", "status": "active", ... } }`

### POST `/device/revoke` (auth, admin/super_admin)
Request: `{ "device_uuid": "uuid" }`
Response `200`: `{ "data": { "device_uuid": "...", "status": "revoked" } }`
Errors: `403` for non-admin.

## Tracking

### POST `/tracking/start` (auth, employee)
Request:
```json
{
  "device_uuid": "uuid",
  "mode": "normal_duty|extra_duty",
  "client_midnight_at": "2026-11-25T18:00:00+06:00",
  "client_timezone": "Asia/Dhaka",
  "client_now": "2026-11-24T17:05:00+06:00"
}
```
Rules:
- `device_uuid` required, must exist and belong to authenticated active employee device.
- `mode` optional, defaults to `normal_duty`; allowed `normal_duty|extra_duty`.
- `client_midnight_at` required when `mode=extra_duty` (ISO8601 date/time).
- `client_timezone` and `client_now` are accepted optional inputs.

Behavior:
- Normal duty: server-authoritative `expires_at` from employee `duty_end_time` in `Asia/Dhaka`; cannot start after duty end for that day; duplicate active session rejected.
- Extra duty: allowed only after normal duty ended, only once per phone-local day, expires at `client_midnight_at` boundary; creates `session_type=extra_duty`, `counted=true`.
- Session carries `policy_snapshot`.

Response `201`:
```json
{
  "data": {
    "session_uuid": "...",
    "status": "active",
    "session_type": "normal_duty",
    "counted": true,
    "can_extend": false,
    "has_used_extra_today": false,
    "started_at": "...",
    "expires_at": "...",
    "ended_at": null,
    "stop_reason": null,
    "device_id": 1,
    "tracking_policy_id": 1,
    "policy_snapshot": {}
  }
}
```

### POST `/tracking/stop` (auth, employee)
Manual stop is allowed **only for extra duty** active sessions. Normal duty manual stop returns `422`.
Extra duty manual stop sets `status=stopped`, `stop_reason=extended_manual`.
Response `200` with session payload.

### GET `/tracking/status` (auth, employee)
Query: optional `client_midnight_at` (ISO8601) to evaluate phone-local day metadata.
Behavior: if latest session is active and expired, backend marks it expired before responding (`normal_duty` -> `EXPIRED/expired`, `extra_duty` forgotten -> `EXPIRED/midnight_auto`, `counted=false`).

Response `200`:
```json
{
  "data": {
    "server_time": "...",
    "active_session": { "...session payload or null..." },
    "session_type": "normal_duty|extra_duty|null",
    "counted": true,
    "can_extend": false,
    "has_used_extra_today": false
  }
}
```

## Location Ingestion

### POST `/locations/batch` (auth, employee)
Request:
```json
{
  "device_uuid": "uuid",
  "locations": [{
    "event_uuid": "uuid",
    "session_uuid": "uuid",
    "sequence_number": 1,
    "latitude": 24.8607,
    "longitude": 67.0011,
    "accuracy": 10.5,
    "speed": 1.2,
    "bearing": 90,
    "altitude": 12,
    "battery_level": 80,
    "recorded_at": "2026-11-23T09:00:00Z"
  }]
}
```
Rules: device/session ownership verified; expired/inactive session rejected; duplicate `event_uuid` deduped; `recorded_at` preserved; `received_at` set server-side; `latest_locations` updated only when incoming `recorded_at` is newer; `LatestLocationUpdated` event dispatched with private `admin.live-locations` channel auth and payload tests. Full Reverb runtime install remains an environment/deployment task.
Response `200`:
```json
{ "data": { "accepted": 1, "duplicates": 0, "rejected": 0, "errors": [] } }
```

## Employee Self

### GET `/me/location-history` (auth)
Query: `session_uuid`, `from`, `to`, `per_page`. Returns paginated own `location_logs`.

### GET `/me/sessions` (auth)
Paginated own tracking sessions.

### GET `/me/reports` (auth)
Returns summary counts: `total_sessions`, `active_sessions`, `counted_sessions`, `uncounted_sessions`, `total_locations`, `total_tracking_seconds` (counted completed sessions only).

## Admin (auth + admin/super_admin)

- `GET /admin/employees` (filters: `region_id`, `area_id`, pagination; permission `dashboard.view`)
- `GET /admin/devices` (permission `dashboard.view`)
- `GET /admin/tracking-policies` (permission `dashboard.view`)
- `GET /admin/audit-logs` (permission `dashboard.view`)
- `GET /admin/live-locations` (permission `live_map.view`)
- `GET /admin/dashboard/summary` (permission `dashboard.view`)
- `GET/POST /admin/tracker-users`, `GET/PUT/DELETE /admin/tracker-users/{tracker_user}` (permission-gated; create/edit/delete per tracker_users permissions)
- `GET /admin/tracker-users/{tracker_user}/sessions` (permission `tracker_users.view`; filters: `status`, `from`, `to`, `per_page`)
- `GET /admin/tracker-users/{tracker_user}/location-history` (permission `tracker_users.view`; filters: `session_uuid`, `from`, `to`, `per_page`)
- `GET /admin/tracker-users/{tracker_user}/report` (permission `tracker_users.view`; returns `total_sessions`, `active_sessions`, `counted_sessions`, `uncounted_sessions`, `total_locations`, `total_tracking_seconds`)
- `GET/POST /admin/admin-users`, `GET /admin/admin-users/{admin_user}`, `PUT /admin/admin-users/{admin_user}/permissions` (super-admin guarded where applicable)

### Tracker user detail additive field: `extra_duty_audit`
`GET /admin/tracker-users/{id}` includes `extra_duty_audit`: forgotten extra-duty sessions where `session_type=extra_duty` and `counted=false`.

```json
{
  "data": {
    "id": 1,
    "...": "...",
    "extra_duty_audit": [
      {
        "session_uuid": "...",
        "started_at": "...",
        "ended_at": "...",
        "stop_reason": "midnight_auto",
        "duration_seconds": 3600,
        "counted": false,
        "locations": [
          { "id": 1, "latitude": 23.7, "longitude": 90.4, "accuracy": 10.0, "recorded_at": "..." }
        ]
      }
    ]
  }
}
```

All admin endpoints return JSON; non-admin receives `403`, missing permission receives `403`.
