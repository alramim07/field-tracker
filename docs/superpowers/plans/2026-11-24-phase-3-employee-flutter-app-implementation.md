# Phase 3 — Employee Flutter App + Native Tracker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the employee Flutter app with a native Kotlin foreground tracker, additive Laravel mobile/self-service APIs, scheduled session closure, and admin extra-duty audit so a tracker can run closed-app normal duty, extend once per day, and reset on phone-local date change.

**Architecture:** Extend the existing Laravel 12 Sanctum API with additive session/profile fields, a scheduler, and admin audit payloads; keep admin SPA on Vite/React. Build a new Flutter app in `apps/field_tracker_mobile/` using Riverpod/GoRouter/Dio/flutter_map/geolocator and a Kotlin MethodChannel/EventChannel FGS that owns background location posting. Phase 5 Room sync is out of scope; Phase 3 uses direct batch posting plus status/history refresh.

**Tech Stack:** PHP 8.3, Laravel 12, Sanctum, PHPUnit/RefreshDatabase, MySQL-compatible MariaDB, React 19/Vite/Tailwind v4 admin SPA, Flutter 3.50/Dart 3.14, flutter_riverpod, go_router, dio, flutter_secure_storage, shared_preferences, flutter_map, latlong2, geolocator, intl, Kotlin Android FGS.

**Spec:** `docs/superpowers/specs/2026-11-24-phase-3-employee-flutter-app-design.md`

## Global Constraints

- `APP_TIMEZONE=Asia/Dhaka`; backend times and duty validation use Asia/Dhaka. Phone-local date rollover/extra-duty midnight use the device local date.
- DB: `field_tracker`, `DB_CONNECTION=mysql`, `127.0.0.1:3306`, `root`, empty password.
- OpenStreetMap only in mobile app and admin dashboard default; no Google Maps SDK/API key in Phase 3 mobile.
- No Flutter-only background tracker. Closed-app location sending uses native Kotlin FGS.
- Normal duty: start-only from employee; auto-stops at duty end; employee cannot manually stop it.
- Extra duty: separate session, only one per phone-local day, manually stoppable, auto-stops at phone-local midnight; forgotten midnight auto-stop is `counted=false` and excluded from employee report totals.
- Logout stops FGS and ends any active session.
- Duty `duty_start_time` and `duty_end_time` are mandatory at tracker-user registration/admin update.
- All API changes are additive; preserve existing mobile Bearer flow and admin SPA cookie flow.
- No company name/logo; neutral Field Tracker branding only.
- Test/location data uses Bangladesh coordinates.

## File Structure

### Backend / Laravel
- Create: `database/migrations/*_add_phase3_tracking_session_fields.php` — `session_type`, `counted`, any needed indexes on `tracking_sessions`.
- Create: `database/migrations/*_add_phone_to_employees_if_missing.php` only if needed; confirm existing `employees.phone` and `users.avatar_path` are used.
- Modify: `app/Enums/StopReason.php` — add extension/midnight/logout reasons.
- Create: `app/Enums/TrackingSessionType.php` — `NORMAL_DUTY`, `EXTRA_DUTY`.
- Modify: `app/Models/TrackingSession.php`, `app/Models/User.php`, `app/Models/Employee.php` — casts/fillable/helpers.
- Modify: `app/Services/Tracking/TrackingSessionService.php` — start modes, duty-end expiry, extra-duty rules, midnight/close helpers.
- Create: `app/Services/Tracking/TrackingSessionCloseService.php` or command service method — scheduler closure.
- Create: `app/Console/Commands/CloseExpiredTrackingSessions.php` — `tracking:close-expired`.
- Modify: `bootstrap/app.php` — `withSchedule`.
- Modify: `app/Http/Controllers/Api/V1/TrackingController.php` — mode/start/stop/status payload.
- Modify: `app/Http/Requests/Tracking/StartTrackingRequest.php` — `mode`.
- Create: `app/Http/Requests/Profile/UpdateProfileRequest.php`, `ChangePasswordRequest.php`, `UploadPhotoRequest.php`.
- Modify/Create: `app/Http/Controllers/Api/V1/EmployeeSelfController.php` or `ProfileController.php` — profile/photo/password; extend `/me`.
- Modify: `app/Http/Resources/UserResource.php` — additive fields.
- Modify: `app/Http/Requests/Admin/StoreTrackerUserRequest.php`, `UpdateTrackerUserRequest.php` — mandatory duty times.
- Modify: `app/Http/Controllers/Api/V1/Admin/TrackerUserController.php` and/or resource — include `extra_duty_audit`.
- Modify: `routes/api.php` — additive endpoints.
- Tests: `tests/Feature/TrackingApiTest.php`, `tests/Feature/AuthApiTest.php` or new profile tests, `tests/Feature/Admin/TrackerUserManagementTest.php`, new scheduler/audit tests.

### Admin SPA
- Modify: `resources/admin-ui/src/api/admin.js` — add `trackerUsersApi.show`.
- Modify: `resources/admin-ui/src/App.jsx` — details route.
- Create: `resources/admin-ui/src/features/tracker-users/TrackerUserDetailsPage.jsx` — audit section.
- Modify: `resources/admin-ui/src/features/tracker-users/TrackerUsersPage.jsx` — link to details.

### Flutter / Kotlin
- Create: `apps/field_tracker_mobile/` Flutter app.
- Create: Dart core `lib/main.dart`, `lib/app.dart`, routing, theme, API client, auth/session storage, models, repositories.
- Create: feature screens `login`, `home`, `travel_history`, `notifications`, `profile`, `change_password`.
- Create: services `device_registration_service.dart`, `sync_service.dart`, `history_grouping_service.dart`, `notification_store.dart`, `tracker_bridge.dart`.
- Create: Android Kotlin `android/app/src/main/kotlin/.../tracking/TrackingForegroundService.kt`, `TrackingBridge.kt`, permission/boot/session helpers.
- Tests: `apps/field_tracker_mobile/test/...` unit/widget tests.

## Review Focus

- A closed/killed app during normal duty must still post batches through FGS until duty end; on next app open/sync, status refresh must not incorrectly create a “tracker stopped during duty” alert for scheduled expiry.
- `POST /tracking/start` with `mode=extra_duty` before normal duty has ended, or after an extra session already exists that phone-local day, must return `422` with a clear validation error.
- Midnight boundary: phone-local midnight auto-stops extra duty and marks `counted=false`; server scheduler in Asia/Dhaka must close the same session idempotently without counting it in `/me/reports` or `total_tracking_seconds`.
- Logout with active normal or extra duty must stop FGS, end the session with logout/system reason, revoke other tokens as designed, and leave no stale active session in admin dashboard.
- A forgotten-stop extra session appears in admin tracker-user `extra_duty_audit` with start/end/locations but is excluded from employee-counted totals and normal report summaries.

## Task 1: Session Data Model and Stop Reasons

**Files:**
- Create: `database/migrations/2026_11_24_000001_add_phase3_fields_to_tracking_sessions.php`
- Modify: `app/Enums/StopReason.php`
- Create: `app/Enums/TrackingSessionType.php`
- Modify: `app/Models/TrackingSession.php`
- Modify: `database/factories/TrackingSessionFactory.php`
- Test: `tests/Feature/Phase1FoundationTest.php` or create `tests/Feature/TrackingSessionModelTest.php`

**Interfaces:**
- Consumes: existing `tracking_sessions` table and `TrackingSession` model.
- Produces: `TrackingSessionType::NORMAL_DUTY|EXTRA_DUTY`; `StopReason::MANUAL|EXPIRED|REVOKED|SYSTEM|EXTENDED_MANUAL|MIDNIGHT_AUTO|LOGOUT`; `TrackingSession::$fillable` includes `session_type,counted`; casts `session_type => TrackingSessionType::class`, `counted => 'boolean'`.

- [ ] **Step 1: Write the failing test**

```php
public function test_tracking_session_supports_phase3_type_counted_and_stop_reasons(): void
{
    $session = TrackingSession::factory()->create([
        'session_type' => TrackingSessionType::EXTRA_DUTY->value,
        'counted' => false,
        'stop_reason' => StopReason::MIDNIGHT_AUTO->value,
        'status' => TrackingSessionStatus::STOPPED,
    ]);

    $session->refresh();
    $this->assertSame(TrackingSessionType::EXTRA_DUTY, $session->session_type);
    $this->assertFalse($session->counted);
    $this->assertSame(StopReason::MIDNIGHT_AUTO, $session->stop_reason);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=tracking_session_supports_phase3_type_counted_and_stop_reasons`
Expected: FAIL because enum cases/columns/casts do not exist.

- [ ] **Step 3: Implement the minimal schema/model changes**

Create migration adding `session_type string default 'normal_duty'` and `counted boolean default true` with useful indexes (`employee_id, session_type, started_at`). Add enum cases and model casts/fillable.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=tracking_session_supports_phase3_type_counted_and_stop_reasons`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/*add_phase3_fields_to_tracking_sessions.php app/Enums/StopReason.php app/Enums/TrackingSessionType.php app/Models/TrackingSession.php database/factories/TrackingSessionFactory.php tests/Feature/TrackingSessionModelTest.php
git commit -m "feat: add phase 3 tracking session fields"
```

## Task 2: Mandatory Duty Time at Tracker Registration

**Files:**
- Modify: `app/Http/Requests/Admin/StoreTrackerUserRequest.php`
- Modify: `app/Http/Requests/Admin/UpdateTrackerUserRequest.php`
- Modify: `resources/admin-ui/src/features/tracker-users/TrackerUsersPage.jsx`
- Test: `tests/Feature/Admin/TrackerUserManagementTest.php`

**Interfaces:**
- Consumes: existing tracker-user create/update endpoints.
- Produces: `duty_start_time` required `date_format:H:i,H:i:s`; `duty_end_time` required `date_format:H:i,H:i:s|after:duty_start_time` for create and update.

- [ ] **Step 1: Write the failing tests**

```php
public function test_tracker_user_create_requires_duty_times(): void
{
    $admin = User::factory()->superAdmin()->create();
    $token = $admin->createToken('test')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/admin/tracker-users', [
        'name' => 'Field User',
        'email' => 'field@example.com',
        'password' => 'password',
        'employee_code' => 'EMP-1001',
    ])->assertUnprocessable()->assertJsonValidationErrors(['duty_start_time', 'duty_end_time']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=tracker_user_create_requires_duty_times`
Expected: FAIL because duty times are currently nullable.

- [ ] **Step 3: Implement validation and admin form requirement**

Update both FormRequests to require duty times. Update TrackerUsersPage form labels/placeholders and submit payload to always include them.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=TrackerUserManagementTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Admin/StoreTrackerUserRequest.php app/Http/Requests/Admin/UpdateTrackerUserRequest.php resources/admin-ui/src/features/tracker-users/TrackerUsersPage.jsx tests/Feature/Admin/TrackerUserManagementTest.php
git commit -m "feat: require duty times for tracker users"
```

## Task 3: Extend /me and Add Self-Service Profile Endpoints

**Files:**
- Modify: `app/Http/Resources/UserResource.php`
- Modify: `app/Http/Controllers/Api/V1/AuthController.php` or profile controller
- Create: `app/Http/Controllers/Api/V1/ProfileController.php`
- Create: `app/Http/Requests/Profile/UpdateProfileRequest.php`
- Create: `app/Http/Requests/Profile/UploadPhotoRequest.php`
- Create: `app/Http/Requests/Profile/ChangePasswordRequest.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ProfileApiTest.php`

**Interfaces:**
- Consumes: Sanctum Bearer auth; `users.avatar_path`; `employees.phone,duty_start_time,duty_end_time`.
- Produces: `GET /api/v1/me` includes additive `phone, photo_url, duty_start_time, duty_end_time, total_tracking_seconds`; `PATCH /api/v1/me/profile`; `POST /api/v1/me/photo` multipart; `POST /api/v1/me/password` revokes other tokens.

- [ ] **Step 1: Write failing tests**

```php
public function test_me_returns_phase3_profile_fields(): void
{
    $employee = Employee::factory()->create(['phone' => '01700000000', 'duty_start_time' => '09:00:00', 'duty_end_time' => '21:00:00']);
    $token = $employee->user->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('data.phone', '01700000000')
        ->assertJsonPath('data.duty_start_time', '09:00:00')
        ->assertJsonPath('data.duty_end_time', '21:00:00')
        ->assertJsonStructure(['data' => ['photo_url', 'total_tracking_seconds']]);
}

public function test_employee_can_update_profile_but_not_admin_managed_fields(): void
{
    $employee = Employee::factory()->create();
    $token = $employee->user->createToken('test')->plainTextToken;

    $this->withToken($token)->patchJson('/api/v1/me/profile', [
        'phone' => '01811111111',
        'email' => 'new@example.com',
        'name' => 'Hack',
        'employee_code' => 'X',
    ])->assertOk();

    $employee->refresh()->load('user');
    $this->assertSame('01811111111', $employee->phone);
    $this->assertSame('new@example.com', $employee->user->email);
    $this->assertNotSame('Hack', $employee->user->name);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ProfileApiTest`
Expected: FAIL with 404/missing fields.

- [ ] **Step 3: Implement endpoints and resource fields**

Add routes under auth:sanctum. Add `ProfileController` with `update`, `uploadPhoto`, `changePassword`. Store photo on `public` disk and save `users.avatar_path`. `changePassword` validates current password and calls `$user->tokens()->where('id', '!=', $currentTokenId)->delete()`. Extend `UserResource`/`/me` payload with additive fields; calculate `total_tracking_seconds` from counted sessions only.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=ProfileApiTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/ProfileController.php app/Http/Requests/Profile routes/api.php app/Http/Resources/UserResource.php app/Http/Controllers/Api/V1/AuthController.php tests/Feature/ProfileApiTest.php
git commit -m "feat: add mobile self-service profile endpoints"
```

## Task 4: Tracking Start/Stop/Status Semantics for Normal and Extra Duty

**Files:**
- Modify: `app/Http/Requests/Tracking/StartTrackingRequest.php`
- Modify: `app/Http/Controllers/Api/V1/TrackingController.php`
- Modify: `app/Services/Tracking/TrackingSessionService.php`
- Modify: `tests/Feature/TrackingApiTest.php`
- Create: `tests/Feature/ExtraDutyTrackingTest.php`

**Interfaces:**
- Consumes: `TrackingSessionType`, `StopReason`, employee duty times, current device UUID.
- Produces: `POST /tracking/start` accepts `mode` (`normal_duty|extra_duty`, default normal_duty); normal expiry is duty end in Asia/Dhaka; extra expiry is phone-local midnight as provided by client `client_midnight_at` ISO timestamp or derived server fallback? **Pin:** client sends `client_timezone` and `client_now`; server computes local midnight boundary and stores expires_at in UTC/DB timezone consistently; also accept `client_midnight_at` optional for FGS. Status payload includes `session_type,counted,can_extend,has_used_extra_today`.

- [ ] **Step 1: Write failing tests**

```php
public function test_normal_session_expires_at_duty_end(): void
{
    Carbon::setTestNow('2026-11-24 09:00:00');
    $employee = Employee::factory()->create(['duty_start_time' => '09:00:00', 'duty_end_time' => '21:00:00']);
    // device, token, active policy
    $this->withToken($token)->postJson('/api/v1/tracking/start', [
        'device_uuid' => $device->device_uuid,
        'mode' => 'normal_duty',
    ])->assertCreated()->assertJsonPath('data.session_type', 'normal_duty')
      ->assertJsonPath('data.expires_at', Carbon::parse('2026-11-24 21:00:00')->toISOString());
}

public function test_extra_duty_requires_normal_session_ended_and_only_once_per_day(): void
{
    // seed ended normal session today, then start extra -> 201; second extra same day -> 422
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test --filter=ExtraDutyTrackingTest`
Expected: FAIL (mode unsupported/rules missing).

- [ ] **Step 3: Implement tracking rules**

In service `start`, branch on mode. Normal: require no active session; compute `expires_at` from employee `duty_end_time` same Asia/Dhaka day. Extra: require duty ended, a normal session ended that day, and no extra session already exists that phone-local day; create `session_type=extra_duty`, `counted=true`, `expires_at` from request `client_midnight_at` (required ISO8601 for extra mode), store as UTC/DB timestamp. In `stop`, allow employee stop only for extra duty sessions and set `stop_reason=EXTENDED_MANUAL`; normal employee stop returns 422. `status` includes `session_type,counted,can_extend,has_used_extra_today,server_time`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test --filter=TrackingApiTest --filter=ExtraDutyTrackingTest`
Expected: PASS, with updated expectations for old normal-stop behavior.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Tracking/StartTrackingRequest.php app/Http/Controllers/Api/V1/TrackingController.php app/Services/Tracking/TrackingSessionService.php tests/Feature/TrackingApiTest.php tests/Feature/ExtraDutyTrackingTest.php
git commit -m "feat: add normal and extra duty tracking rules"
```

## Task 5: Scheduler Command for Expired Sessions

**Files:**
- Create: `app/Console/Commands/CloseExpiredTrackingSessions.php`
- Modify: `bootstrap/app.php`
- Create: `tests/Feature/CloseExpiredTrackingSessionsCommandTest.php`
- Modify: `docs/DEPLOYMENT.md`

**Interfaces:**
- Consumes: `tracking_sessions` active rows past `expires_at`.
- Produces: Artisan command `tracking:close-expired`; normal expired -> `status=expired, stop_reason=expired`; extra expired at midnight boundary -> `status=stopped/expired per enum choice?` **Pin:** `status=EXPIRED`, `stop_reason=MIDNIGHT_AUTO`, `counted=false`, `ended_at=expires_at`; idempotent. Scheduler runs every minute.

- [ ] **Step 1: Write failing test**

```php
public function test_command_closes_forgotten_extra_duty_as_uncounted(): void
{
    Carbon::setTestNow('2026-11-24 23:59:30');
    $session = TrackingSession::factory()->create([
        'session_type' => TrackingSessionType::EXTRA_DUTY,
        'status' => TrackingSessionStatus::ACTIVE,
        'counted' => true,
        'started_at' => '2026-11-24 21:10:00',
        'expires_at' => '2026-11-24 23:59:59',
    ]);
    Carbon::setTestNow('2026-11-25 00:00:05');
    $this->artisan('tracking:close-expired')->assertExitCode(0);
    $session->refresh();
    $this->assertSame(TrackingSessionStatus::EXPIRED, $session->status);
    $this->assertSame(StopReason::MIDNIGHT_AUTO, $session->stop_reason);
    $this->assertFalse($session->counted);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CloseExpiredTrackingSessionsCommandTest`
Expected: FAIL command missing.

- [ ] **Step 3: Implement command and schedule**

Create command that queries active sessions where `expires_at <= now()` and closes them by type. Register in `bootstrap/app.php` with `->withSchedule(function (\Illuminate\Console\Scheduling\Schedule $schedule): void { $schedule->command('tracking:close-expired')->everyMinute(); })`. Add deployment note for cron `php artisan schedule:run`.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CloseExpiredTrackingSessionsCommandTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/CloseExpiredTrackingSessions.php bootstrap/app.php tests/Feature/CloseExpiredTrackingSessionsCommandTest.php docs/DEPLOYMENT.md
git commit -m "feat: schedule automatic tracking session closure"
```

## Task 6: Admin Extra-Duty Audit in Tracker User Details

**Files:**
- Modify: `app/Http/Controllers/Api/V1/Admin/TrackerUserController.php`
- Modify: `app/Http/Resources/Admin/TrackerUserResource.php` or details response
- Modify: `resources/admin-ui/src/api/admin.js`
- Modify: `resources/admin-ui/src/App.jsx`
- Create: `resources/admin-ui/src/features/tracker-users/TrackerUserDetailsPage.jsx`
- Modify: `resources/admin-ui/src/features/tracker-users/TrackerUsersPage.jsx`
- Test: `tests/Feature/Admin/TrackerUserManagementTest.php` or new `TrackerUserAuditTest.php`

**Interfaces:**
- Consumes: tracking sessions/location logs; existing `GET /admin/tracker-users/{id}`.
- Produces: `GET /admin/tracker-users/{id}` includes additive `extra_duty_audit` array of forgotten sessions (`session_type=extra_duty`, `counted=false`) with `session_uuid,started_at,ended_at,stop_reason,duration_seconds,locations[]`.

- [ ] **Step 1: Write failing backend test**

```php
public function test_tracker_user_details_include_forgotten_extra_duty_audit(): void
{
    $admin = User::factory()->superAdmin()->create();
    // create employee, extra session counted=false with location logs
    $response = $this->actingAs($admin)->getJson('/api/v1/admin/tracker-users/'.$employee->user_id);
    $response->assertOk()
        ->assertJsonPath('data.extra_duty_audit.0.counted', false)
        ->assertJsonStructure(['data' => ['extra_duty_audit' => [['session_uuid','started_at','ended_at','stop_reason','duration_seconds','locations']]]]);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=tracker_user_details_include_forgotten_extra_duty_audit`
Expected: FAIL missing key.

- [ ] **Step 3: Implement backend payload and admin UI page**

Add audit query in controller show/details response. Add SPA route `/tracker-users/:id`, `trackerUsersApi.show`, details page with summary card and extra-duty audit table/map links. Keep existing list page behavior.

- [ ] **Step 4: Run tests/build to verify**

Run: `php artisan test --filter=TrackerUserAuditTest`
Run: `npm --prefix resources/admin-ui run build`
Expected: PASS/build succeeds.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/V1/Admin/TrackerUserController.php resources/admin-ui/src/api/admin.js resources/admin-ui/src/App.jsx resources/admin-ui/src/features/tracker-users tests/Feature/Admin/TrackerUserAuditTest.php
git commit -m "feat: show extra duty audit in tracker user details"
```

## Task 7: Flutter App Scaffold and Auth/Device Registration

**Files:**
- Create: `apps/field_tracker_mobile/pubspec.yaml`
- Create: `apps/field_tracker_mobile/lib/main.dart`
- Create: `apps/field_tracker_mobile/lib/app.dart`
- Create: `apps/field_tracker_mobile/lib/core/config/app_config.dart`
- Create: `apps/field_tracker_mobile/lib/core/network/api_client.dart`
- Create: `apps/field_tracker_mobile/lib/core/storage/token_storage.dart`
- Create: `apps/field_tracker_mobile/lib/features/auth/...`
- Create: `apps/field_tracker_mobile/lib/features/device/device_registration_service.dart`
- Test: `apps/field_tracker_mobile/test/features/auth/login_screen_test.dart`

**Interfaces:**
- Consumes: `POST /api/v1/login`, `POST /api/v1/device/register`, `GET /api/v1/me`.
- Produces: `AppConfig.apiBaseUrl` from `--dart-define=API_BASE_URL`; secure token persistence; stable device UUID in secure storage; login rejects non-employee roles.

- [ ] **Step 1: Write failing widget/unit tests**

```dart
testWidgets('login rejects non-employee role', (tester) async {
  // pump LoginScreen with fake auth repo returning admin role
  // expect error text 'Employee account required'
});

test('device registration reuses persisted uuid', () async {
  // fake secure storage returns uuid; service calls register once with same uuid
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/field_tracker_mobile && C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test`
Expected: FAIL because project/tests do not exist.

- [ ] **Step 3: Implement scaffold and auth flow**

Create Flutter project structure, dependencies, go_router routes, Dio client with Bearer interceptor, login screen, token/device UUID storage, first-login device registration. Keep UI neutral and modern.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd apps/field_tracker_mobile && C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test test/features/auth`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/field_tracker_mobile
git commit -m "feat: scaffold flutter app with auth and device registration"
```

## Task 8: Home, Map, Timer, Sync, and Travel History UI

**Files:**
- Create: `apps/field_tracker_mobile/lib/features/home/...`
- Create: `apps/field_tracker_mobile/lib/features/history/...`
- Create: `apps/field_tracker_mobile/lib/features/sync/sync_service.dart`
- Create: `apps/field_tracker_mobile/lib/features/history/history_grouping_service.dart`
- Test: `apps/field_tracker_mobile/test/features/home/home_screen_test.dart`
- Test: `apps/field_tracker_mobile/test/features/history/history_grouping_test.dart`

**Interfaces:**
- Consumes: `/tracking/status`, `/me/location-history`, `/me`, geolocator one-shot fix.
- Produces: home states before start, normal tracking, duty ended, extra duty, extra ended, midnight auto-stopped; history groups into movement/stopped segments.

- [ ] **Step 1: Write failing tests**

```dart
test('groups stationary pings into stopped period', () {
  final segments = HistoryGroupingService().group(points);
  expect(segments.first.type, SegmentType.stop);
});

testWidgets('shows Extend duty tracking time after duty end', (tester) async {
  // fake status ended normal session, can_extend true
  // expect button text 'Extend duty tracking time'
});

testWidgets('resets to fresh day state after phone date changes', (tester) async {
  // fake stored day yesterday + midnight auto-stop; on resume expect sync called and Start duty tracking shown
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/field_tracker_mobile && C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test test/features/home test/features/history`
Expected: FAIL.

- [ ] **Step 3: Implement UI and grouping**

Build home screen header/map/timer card, manual sync, current-day timeline. Use `flutter_map` OSM tiles with attribution. Use geolocator for one-shot GPS display when idle. Implement Haversine-based grouping service; collapse pings within ~50m.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd apps/field_tracker_mobile && C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test test/features/home test/features/history`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/field_tracker_mobile/lib/features/home apps/field_tracker_mobile/lib/features/history apps/field_tracker_mobile/lib/features/sync apps/field_tracker_mobile/test/features/home apps/field_tracker_mobile/test/features/history
git commit -m "feat: add mobile home map timer and travel history"
```

## Task 9: Native Kotlin FGS Bridge and Tracking Lifecycle

**Files:**
- Create: `apps/field_tracker_mobile/android/app/src/main/kotlin/com/fieldtracker/mobile/tracking/TrackingForegroundService.kt`
- Create: `apps/field_tracker_mobile/android/app/src/main/kotlin/com/fieldtracker/mobile/tracking/TrackingBridge.kt`
- Modify: `apps/field_tracker_mobile/android/app/src/main/AndroidManifest.xml`
- Create: `apps/field_tracker_mobile/lib/features/tracking/tracker_bridge.dart`
- Modify: `apps/field_tracker_mobile/lib/features/home/...`
- Test: `apps/field_tracker_mobile/test/features/tracking/tracker_bridge_test.dart`

**Interfaces:**
- Consumes: backend session payload (`session_uuid, session_type, expires_at, device_uuid, token/baseUrl` as appropriate).
- Produces: MethodChannel `field_tracker/tracking` methods `startTracking`, `stopExtraDuty`, `syncState`, `getState`; EventChannel for state updates. FGS posts batches to `/api/v1/locations/batch`.

- [ ] **Step 1: Write failing Dart bridge tests**

```dart
test('startTracking sends normal duty payload to method channel', () async {
  // fake method channel; expect startTracking called with session_type normal_duty
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/field_tracker_mobile && C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test test/features/tracking`
Expected: FAIL.

- [ ] **Step 3: Implement Kotlin FGS and Flutter bridge**

Add foreground service, notification, location callback, batch POST, duty-end/midnight stop, logout stop, boot/resume reconciliation hooks as feasible. Keep secrets/tokens in secure storage; pass minimum needed config. Request POST_NOTIFICATIONS/location/background location permissions.

- [ ] **Step 4: Run tests and Android build check**

Run: `cd apps/field_tracker_mobile && C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test test/features/tracking`
Run: `cd apps/field_tracker_mobile && C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat build apk --debug`
Expected: Dart tests PASS; debug APK build PASS on machine with Android SDK.

- [ ] **Step 5: Commit**

```bash
git add apps/field_tracker_mobile/android apps/field_tracker_mobile/lib/features/tracking apps/field_tracker_mobile/test/features/tracking
git commit -m "feat: add native foreground tracking service"
```

## Task 10: Notifications, Drawer, Profile, Password, Logout

**Files:**
- Create: `apps/field_tracker_mobile/lib/features/notifications/...`
- Create: `apps/field_tracker_mobile/lib/features/profile/...`
- Create: `apps/field_tracker_mobile/lib/features/drawer/app_drawer.dart`
- Modify: `apps/field_tracker_mobile/lib/features/home/...`
- Test: `apps/field_tracker_mobile/test/features/profile/profile_screen_test.dart`
- Test: `apps/field_tracker_mobile/test/features/notifications/notification_store_test.dart`

**Interfaces:**
- Consumes: `/me`, `/me/profile`, `/me/photo`, `/me/password`, `/logout`, local notification store.
- Produces: drawer with photo/name/staff id/phone/designation and required menu options; profile editable only photo/phone/email/password; logout ends active tracking and clears session.

- [ ] **Step 1: Write failing tests**

```dart
testWidgets('profile shows locked admin-managed fields', (tester) async {
  // expect Name/Staff ID/Duty time/Tracking time/Designation disabled/locked labels
});

test('notification store marks unexpected early stop as unread', () async {
  // create alert for stop_reason != expired before duty end; expect unread count 1
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/field_tracker_mobile && C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test test/features/profile test/features/notifications`
Expected: FAIL.

- [ ] **Step 3: Implement screens and local store**

Implement drawer, notifications screen, profile screen, change password screen. Local notification store in SharedPreferences. On logout, call tracker bridge stop/logout, call `/logout`, clear token.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd apps/field_tracker_mobile && C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test test/features/profile test/features/notifications`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add apps/field_tracker_mobile/lib/features/drawer apps/field_tracker_mobile/lib/features/profile apps/field_tracker_mobile/lib/features/notifications
git commit -m "feat: add mobile drawer profile and notifications"
```

## Task 11: Docs, Verification, and Final Status Update

**Files:**
- Modify: `README.md`
- Modify: `docs/PROJECT_OVERVIEW.md`
- Modify: `docs/ARCHITECTURE.md`
- Modify: `docs/API_DOCUMENTATION.md`
- Modify: `docs/TESTING.md`
- Modify: `docs/CHANGELOG.md`
- Modify: `docs/DEPLOYMENT.md`
- Modify: `docs/DATABASE_SCHEMA.md`

**Interfaces:**
- Consumes: implemented behavior and test results.
- Produces: accurate Phase 3 status, API docs for additive endpoints, scheduler deployment note, Flutter run instructions with `API_BASE_URL`.

- [ ] **Step 1: Write/update doc checks as a checklist**

Create a short verification checklist in the plan execution notes: all new endpoints documented, Phase 3 marked implemented/in-progress accurately, scheduler cron documented.

- [ ] **Step 2: Run full verification**

Run: `php artisan test`
Run: `npm --prefix resources/admin-ui run build`
Run: `cd apps/field_tracker_mobile && C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat analyze`
Run: `cd apps/field_tracker_mobile && C:\Users\Lyns Romero\develop\flutter\bin\flutter.bat test`
Expected: all pass; record any environment-limited Android device smoke as not run.

- [ ] **Step 3: Update docs with actual results**

Document exact command outcomes, known gaps (no connected Android device for real FGS smoke unless available), and scheduler requirement.

- [ ] **Step 4: Run quick sanity checks**

Run: `git status --short`
Expected: only intended files modified.

- [ ] **Step 5: Commit**

```bash
git add README.md docs apps/field_tracker_mobile
git commit -m "docs: record phase 3 mobile tracker implementation"
```

## Self-Review

- Spec coverage: normal duty, extra duty once/day, midnight uncounted auto-stop, phone-local reset/auto-sync, FGS closed-app sending, self-service profile, `/me` extension, admin audit, scheduler, logout stop, mandatory duty times, OSM, no calendar, sync, notifications, travel history movement/stops. All mapped to Tasks 1-11.
- Type consistency: `TrackingSessionType`, `StopReason` cases, `session_type/counted`, `/me` additive fields, `extra_duty_audit` payload, and tracker bridge method names are defined before use.
- Gaps fixed inline: pinned extra-duty `client_midnight_at`; made normal-stop behavior change explicit in Task 4; added scheduler deployment note. Remaining environment gap recorded in Task 11: real Android FGS smoke requires a connected device/emulator.
