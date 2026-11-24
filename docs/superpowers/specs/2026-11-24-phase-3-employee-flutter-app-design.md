# Phase 3 — Employee Flutter App + Native Tracker + Mobile APIs

Status: **awaiting human partner approval**

## Intent

Phase 3 now delivers the working employee-side product, not just a UI shell. The employee signs in, sees their current location on an OpenStreetMap map, starts normal duty tracking, keeps sending location even when the app is closed, and can extend tracking after duty time ends.

The normal duty tracker is **auto-stopped at duty end**. After that, the employee sees **Extend duty tracking time** if they are still working. Extended duty is a separate, user-stoppable session, and only one extra session is allowed per day. If the employee forgets to stop it, it auto-stops at phone-local midnight; that forgotten time is **not counted** in the employee’s normal report, but admins can still inspect it as an audit record with start/end time and locations.

Room-based offline sync remains Phase 5, but Phase 3 includes the native Kotlin foreground service needed for real closed-app location sending.

## Goals

- Employee login with the existing mobile Bearer-token API.
- Home screen with burger menu, app name, notification icon, and manual sync icon. No calendar icon.
- Current employee location on an OpenStreetMap-based map, matching the admin dashboard’s free provider choice.
- Human-readable place/address name, coordinates, and last-updated time.
- One-shot device GPS fix for the map when not actively tracking.
- **Start duty tracking** button before duty end.
- Live tracker timer in `HH:MM:SS`.
- Normal duty tracker auto-stops at duty end; no employee-facing stop button for normal duty.
- Native Kotlin foreground service continues sending location to the backend while the app is in the background or closed.
- After duty end, the button changes to **Extend duty tracking time**.
- Extra duty tracking is manually stoppable by the employee and is recorded as a separate extra-duty session.
- Only one extra-duty session is allowed per phone-local day.
- If extra duty is not manually stopped, it auto-stops at phone-local midnight, is marked uncounted for employee reports, and stays visible in an admin audit view.
- At phone-local date change, the app resets to a fresh day state and automatically syncs with the dashboard.
- Travel history for the current day, shown Google-Maps-Timeline-like: both movement segments and stopped periods, grouped by day, with place names, times, and distance where available.
- Burger drawer with round user photo, name, staff ID, phone number, and designation.
- Drawer options: Home, Current location, Travel history, Notifications, Update profile, Change password, Logout.
- Update profile really saves photo, phone, email, and password through new/additive Laravel endpoints.
- Name, staff ID, duty time, tracking time, and designation are displayed as locked/admin-managed.
- Manual sync refreshes profile, duty window, latest location, travel history, and notification state from the backend.
- Admin user-details page gains an extra-duty audit section for forgotten-stop sessions.
- Laravel scheduler/command actively closes expired sessions every minute.
- Logout stops FGS and ends any active session.
- Duty start/end time is mandatory when a tracker user is registered.

## Non-goals

- Room offline queue and durable offline batch sync (Phase 5).
- Google Maps SDK/API key. The admin dashboard uses OpenStreetMap by default, so the app uses OSM too.
- Admin-side permission changes beyond the small extra-duty audit view described here.
- Historical reporting beyond today in the employee app.
- Reverb runtime installation or push notification infrastructure.
- Any company name or logo.
- Attendance/payroll policy decisions beyond marking forgotten-stop extra duty as uncounted.

## Anti-goals

- A Flutter-only background tracker. Background tracking must remain the native Kotlin FGS.
- Letting the employee manually stop normal duty tracking.
- Letting the employee edit admin-managed identity, duty, or policy fields.
- Showing raw coordinates as the primary location label.
- Creating duplicate travel-history rows for repeated pings at the same place.
- Counting forgotten-stop extra duty in the employee’s normal report.
- Allowing registration without duty times.

## Constraints

- Existing Laravel API base: `/api/v1`.
- Mobile auth: `POST /login`, `GET /me`, `POST /logout`, Bearer token stored securely.
- Existing relevant endpoints:
  - `POST /device/register`
  - `POST /tracking/start`
  - `POST /tracking/stop`
  - `GET /tracking/status`
  - `POST /locations/batch`
  - `GET /me/location-history`
  - `GET /me/sessions`
  - `GET /me/reports`
- Existing `/tracking/stop` endpoint remains, but employee use is limited to extra-duty sessions and logout.
- Laravel app timezone is `Asia/Dhaka`; backend validation and reports use it. The app and FGS watch the user’s phone date for day rollover.
- Test/user location data is in Bangladesh.
- OSM tile usage must include attribution and avoid API keys.
- No Flutter project exists in the repo yet; Phase 3 creates one.
- Android device/emulator is not currently connected; real FGS verification requires one later.

## Decisions made with the human partner

- Header contains burger, app name, notification icon, and sync icon. Calendar icon removed.
- Notification is for the case where normal tracking stops during duty time before the duty end.
- Sync manually refreshes data from the backend/admin dashboard.
- Normal tracker is start-only from the employee’s perspective; it auto-stops at duty end.
- After duty end, employee sees **Extend duty tracking time**, which starts a separate extra-duty session.
- Only one extra-duty session is allowed per day.
- Extra duty is manually stoppable by the employee.
- Extra duty auto-stops at phone-local midnight if forgotten.
- Forgotten-stop extra duty is excluded from employee-counted time/reports but visible to admins in user details with start/end time and locations.
- At phone-local date change, app resets to fresh-day state and auto-syncs with the dashboard.
- Travel history shows both movement segments and stopped periods.
- Map provider follows the admin dashboard: OpenStreetMap.
- Home current location uses a one-shot GPS fix; background sending is handled by native Kotlin FGS.
- App sends location whether open or closed once tracking is on, until the relevant session end.
- Device UUID is generated/persisted by the app and registered via `/device/register` on first login.
- Self-service profile/password/photo changes get real backend endpoints in Phase 3.
- `/me` is extended additively with phone, photo, duty times, and tracking time.
- Notification read/unread state is local to the device; alert trigger is unexpected early stop of normal duty, not scheduled expiry or extra-duty stop.
- Extended duty is a separate backend session.
- Laravel adds a scheduler/command to actively close expired normal and midnight-auto-stopped extra sessions.
- Duty start/end time is mandatory at registration; no policy-duration fallback for missing duty time.
- Logout stops FGS and ends any active session.

## Builder calls (sensible defaults, subject to review)

- Flutter app location: `C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile`.
- Android application id: `com.fieldtracker.mobile`. App name: `Field Tracker`.
- State management: `flutter_riverpod`.
- Navigation: `go_router`.
- HTTP: `dio` with auth interceptor.
- Secure token storage: `flutter_secure_storage`.
- Local preferences/cache: `shared_preferences`.
- Map: `flutter_map` + `latlong2` with standard OSM tile layer and attribution.
- One-shot GPS: `geolocator` with runtime permission handling.
- Native bridge: MethodChannel/EventChannel or Pigeon between Flutter and Kotlin FGS; exact choice is builder discretion.
- Date/time formatting: `intl`.
- API base URL via `--dart-define=API_BASE_URL=...`, defaulting to `http://127.0.0.1:8000/api/v1`.
- Reverse geocoding via OSM/Nominatim with local cache and coordinate fallback.
- Movement threshold: consecutive points farther than roughly 50 metres; stationary pings collapse into a stopped period.
- Location posting interval: roughly 30–60 seconds while a session is active; batch endpoint used.
- Backend session expiry:
  - normal duty session expires at employee duty end time,
  - extra duty session expires at phone-local midnight unless manually stopped earlier,
  - scheduler closes both kinds every minute.
- Add `session_type` to tracking sessions: `normal_duty`, `extra_duty`.
- Add `counted` flag to tracking sessions; forgotten-stop extra duty sets it false.
- Add stop reasons for extension completion, midnight auto-stop, and logout.
- Extra mode is available only after a normal duty session has ended for that day.
- Password change revokes other Sanctum tokens but keeps the current token/session valid.
- Admin audit data is added to the existing `GET /admin/tracker-users/{id}` response under `extra_duty_audit`; location logs are embedded for the listed sessions.
- Unexpected early stop is derived from `/tracking/status` fields (`status`, `stop_reason`, `ended_at`) and stored locally as a notification.
- `total_tracking_seconds` and `/me/reports` include counted normal sessions plus manually stopped extra-duty sessions; forgotten midnight auto-stop sessions are excluded.

## Screens and behavior

### 1. Login

- Email and password fields.
- Calls `POST /api/v1/login`.
- Rejects non-employee roles with a clear message.
- Generates/persists a device UUID and calls `/device/register` on first successful login.
- Stores token securely and navigates to Home.
- Shows validation and network errors inline.

### 2. Home — header

- Burger button.
- Small neutral app mark and `Field Tracker` title.
- Notification icon with unread dot when relevant.
- Sync icon with visible progress/completion feedback.
- No calendar icon.

### 3. Home — map

- OSM map with pin, accuracy/halo, place name, coordinates, and last-updated time.
- When not tracking, uses a one-shot GPS fix.
- When tracking, map follows the latest backend/FGS-provided location.
- Location is always labeled primarily by reverse-geocoded place name.

### 4. Home — tracker card

States:

1. **Before duty end / no active session**
   - `00:00:00`, status `NOT STARTED`.
   - Button: `Start duty tracking`.
2. **Normal duty tracking**
   - Live `HH:MM:SS`, status `TRACKING`.
   - Button replaced by `Tracking until <duty end>`.
   - No stop button.
3. **After normal duty auto-stop**
   - Final frozen duration, status `DUTY ENDED`.
   - Button: `Extend duty tracking time`, only if no extra session has been used that day.
4. **Extra duty tracking**
   - Live timer, status `EXTRA DUTY`.
   - Button: `End extra duty`.
5. **Extra duty manually stopped**
   - Final duration, status `EXTRA DUTY ENDED`.
   - No tracking button until the phone date changes.
6. **Midnight auto-stop**
   - Status `AUTO-STOPPED AT MIDNIGHT`.
   - Extra time is marked uncounted for employee reports.
   - App triggers automatic sync and resets to fresh-day state.
7. **Logout while active**
   - FGS stops and the active session ends with a logout/system stop reason.

Timer calculation:
- Use backend `server_time` from `/tracking/status` for clock offset.
- Count from `started_at`.
- Normal duty stops at `expires_at`/duty end.
- Extra duty stops on manual stop or phone-local midnight, whichever comes first.
- Refresh on app resume, manual sync, periodic status polling, and FGS events.

### 5. Travel history

- Header with Today date label; no calendar icon.
- Summary: distance moved today and number of places.
- Google-like timeline:
  - Movement segment: from place A to place B, time range, distance, duration.
  - Stopped period: place name, time range, duration.
  - Current segment marked as live/current.
- Repeat pings at the same place must not create extra movement rows.
- If no movement occurred, show “No movement recorded yet” plus current location.
- History is derived from `/me/location-history` for the current day and enriched with cached reverse-geocoded place names.
- History appears after tracking starts when new pings arrive; existing pings render immediately.

### 6. Notifications

- Local in-app list only.
- Key alert: “Tracker stopped during duty” with stop time and duty end time.
- Trigger: normal duty session ended before duty end for a reason other than scheduled expiry.
- Do not trigger for scheduled duty-end expiry, manual extra-duty stop, or logout.
- Optionally show “Extra duty auto-stopped at midnight and was not counted.”
- Read/opened state is stored locally and feeds the header dot.

### 7. Burger drawer

- Round user photo.
- Name.
- Staff ID.
- Phone number.
- Designation.
- Options: Home, Current location, Travel history, Notifications, Update profile, Change password, Logout.

### 8. Update profile / Change password

Editable and saved through real backend endpoints:
- Photo/image.
- Phone number.
- Email address.
- Password.

Locked/admin-managed, shown grey with lock label:
- Full name.
- Staff ID.
- Duty time.
- Tracking time.
- Designation.

### 9. Sync

Manual sync refreshes:
- `/me` profile and employee record.
- `/tracking/status`.
- Current-day `/me/location-history`.
- `/me/sessions`.
- `/me/reports`.
- Notification state derived from refreshed session data.

Automatic sync:
- On phone-local date change at midnight.
- After app relaunch following a session that ended while closed.

Shows clear states: idle, syncing, success, failure.

### 10. Native Kotlin FGS

- Started by Flutter after `/tracking/start` succeeds.
- Receives session id/uuid, session type, device UUID, and expiry time.
- Shows a persistent foreground notification.
- Collects device location and posts batches to `/locations/batch`.
- Continues while app is backgrounded or killed by the user.
- Stops normal sessions at duty end.
- Stops extra sessions on employee action, logout, or phone-local midnight.
- Emits session-stop events back to Flutter so UI/notifications update.
- Uses backend-issued session state as authoritative when syncing.

### 11. Admin extra-duty audit

On the existing tracker-user details page:
- New section: **Extra duty audit**.
- Included in `GET /admin/tracker-users/{id}` as `extra_duty_audit`.
- Lists sessions where `session_type = extra_duty` and `counted = false`.
- Shows start time, auto-stop time, stop reason, duration, and location logs/map for that period.
- These records do not add to the employee’s counted time or normal reports.
- Admin can inspect but does not need to approve/reject them in Phase 3.

## API changes

Additive mobile/self-service endpoints:
- `PATCH /me/profile` — phone, email.
- `POST /me/photo` — image upload with validation/storage.
- `POST /me/password` — current password + new password.

Extend `/me` response additively:
- `phone`
- `photo_url`
- `duty_start_time`
- `duty_end_time`
- `total_tracking_seconds`

Tracking changes:
- `POST /tracking/start` accepts `mode: normal_duty|extra_duty`.
- Backend validates mode against duty window and phone-local day.
- Extra mode requires a completed normal duty session that day and no existing extra session that day.
- `POST /tracking/stop` allowed for employees only on extra-duty sessions or logout.
- Normal sessions stop via expiry/system/admin, not employee action.
- Midnight auto-stop marks forgotten extra-duty sessions `counted = false`.

Data model additions:
- `tracking_sessions.session_type`
- `tracking_sessions.counted`
- New stop reasons where needed.

Backend operations:
- Scheduled Artisan command closes expired normal sessions and midnight-auto-stopped extra sessions every minute.
- Deployment docs must mention the scheduler requirement.
- Tracker-user registration/admin creation validates that duty start and duty end are present.

## Testing

Flutter unit/widget tests:
- login validation and unauthorized-role handling,
- first-login device registration,
- home before/after start states,
- normal duty timer and duty-end frozen state,
- extend button after duty end,
- one-extra-session-per-day rule,
- extra-duty timer and manual stop,
- midnight auto-stop UI state,
- logout stops tracking,
- drawer identity fields and menu options,
- profile locked vs editable fields,
- travel-history grouping into movement and stop segments,
- no-movement empty state,
- sync success/failure UI states.

Backend feature tests:
- `/me` extended fields,
- profile/photo/password update validation,
- device registration/start happy path,
- mandatory duty time at registration,
- normal session cannot be manually stopped by employee,
- extra-duty session can be manually stopped,
- only one extra-duty session per day,
- forgotten extra duty auto-stopped at midnight uncounted,
- counted reports include manually stopped extra duty but exclude forgotten extra duty,
- admin tracker-user details includes forgotten sessions with locations,
- scheduler command closes expired sessions,
- location batch ingestion still tied to correct session/device.

Manual verification:
- `flutter test`
- `php artisan test`
- `npm --prefix resources/admin-ui run build`
- Android device/emulator smoke for FGS, closed-app location sending, duty-end stop, extend, manual extra stop, midnight auto-stop, auto-sync, and logout stop.

## Deliverables

- Flutter project under `C:\Users\Lyns Romero\Desktop\Projects\field-tracker-mobile`.
- Login, Home, Travel history, Notifications, Update profile, Change password screens.
- OSM map integration with attribution and one-shot GPS.
- Native Kotlin FGS integration.
- API client, secure token storage, device registration, sync service, history grouping service.
- Backend additive self-service/profile endpoints and `/me` fields.
- Extra-duty session type, counted flag, stop reasons, scheduler command, and admin audit view.
- Widget/unit/feature tests.
- README section for running the Flutter app with `API_BASE_URL`.
- Updated docs/CHANGELOG.md, docs/PROJECT_OVERVIEW.md, docs/ARCHITECTURE.md, and deployment scheduler notes.
