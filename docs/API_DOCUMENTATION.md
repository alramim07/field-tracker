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
Response `200`: `{ "message": "Logged out successfully." }`

### GET `/me` (auth)
Response `200`: `{ "data": { ...user } }`

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
Request: `{ "device_uuid": "uuid" }`
Behavior: server calculates fixed `expires_at` from active policy `duration_minutes`; stores `policy_snapshot`; prevents duplicate active session; validates device ownership/status.
Response `201`: `{ "data": { "session_uuid": "...", "status": "active", "started_at": "...", "expires_at": "...", "policy_snapshot": {...} } }`

### POST `/tracking/stop` (auth, employee)
Manually stops active session. Response `200` with session payload.

### GET `/tracking/status` (auth, employee)
Response `200`:
```json
{ "data": { "server_time": "...", "active_session": { ...session|null } } }
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
Rules: device/session ownership verified; expired/inactive session rejected; duplicate `event_uuid` deduped; `recorded_at` preserved; `received_at` set server-side; `latest_locations` updated only when incoming `recorded_at` is newer; `LatestLocationUpdated` event dispatched (Reverb wiring Phase 6).
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
Returns summary counts: `total_sessions`, `active_sessions`, `total_locations`.

## Admin (auth + admin/super_admin)

- `GET /admin/employees` (filters: `region_id`, `area_id`, pagination)
- `GET /admin/devices`
- `GET /admin/tracking-policies`
- `GET /admin/audit-logs`
- `GET /admin/live-locations`

All return paginated JSON. Non-admin receives `403`.
