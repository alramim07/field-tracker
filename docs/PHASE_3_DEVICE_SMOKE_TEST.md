# Phase 3 - Device Smoke Test Checklist

**Status:** Not run in current environment (no connected Android device/emulator; NDK `28.2.13676358` missing at `C:\Android\Sdk`).

## Prerequisites

- Android device or emulator connected and recognized by `adb devices`.
- Android SDK/NDK configured; NDK `28.2.13676358` installed if debug APK build is required.
- Laravel backend running and reachable from device:
  - Emulator: `php artisan serve` + `--dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1`
  - Physical device: `php artisan serve --host=0.0.0.0 --port=8000` + `--dart-define=API_BASE_URL=http://<PC_LAN_IP>:8000/api/v1`
- Scheduler running for expiry tests: `php artisan schedule:work` (local) or minute cron.
- Test tracker employee with duty start/end configured.

## Build / Install

- [ ] `flutter pub get` succeeds in mobile repo.
- [ ] `flutter analyze` passes.
- [ ] `flutter test` passes.
- [ ] `flutter build apk --debug` succeeds (after NDK installed).
- [ ] App installs and launches on target device.

## Auth / Profile

- [ ] Login with tracker employee works (Bearer token stored).
- [ ] Device UUID generated/persisted and registered on first login.
- [ ] `/me` profile data loads (name, staff id, number, designation, duty/tracking times visible where expected).
- [ ] Profile photo upload works; photo displays in drawer/header as designed.
- [ ] Change password works; old tokens revoked as expected.
- [ ] Update profile works for allowed fields (phone/email/image); name/staff id/duty time/tracking time/designation not editable.

## Tracking - Normal Duty

- [ ] Employee can start normal duty tracking only when allowed.
- [ ] No employee stop control for normal duty session.
- [ ] Foreground GPS service starts and notification is visible.
- [ ] Current location displays on OSM map (one-shot/display + tracking updates).
- [ ] Location points sync to backend/admin while app open.
- [ ] Location points sync while app backgrounded/closed (FGS active).
- [ ] Travel history shows movement/stopped periods only when location changes, for current day after tracking starts.
- [ ] Normal duty auto-stops at duty end.
- [ ] After duty end, `Extend duty tracking time` is shown.

## Tracking - Extra Duty

- [ ] Extra duty can start only after normal duty ended.
- [ ] Only one extra duty session per phone-local day.
- [ ] Extra duty session is manually stoppable.
- [ ] Extra duty auto-stops at phone-local midnight.
- [ ] Forgotten midnight-stopped time is uncounted in employee reports/details but visible to admin with start/end/locations.
- [ ] Next phone-local day resets extend eligibility and auto-syncs as designed.

## Notifications / Sync / Logout

- [ ] Unexpected early stop during normal duty triggers local-only notification.
- [ ] Manual sync icon refreshes backend data.
- [ ] Logout stops FGS and ends active session on backend.
- [ ] After logout/login, app state is clean (no stale active tracking UI).

## Admin Verification

- [ ] Active/latest location appears on admin Live Map during tracking.
- [ ] Session type/counted/stop_reason recorded correctly.
- [ ] Extra-duty audit visible for forgotten midnight auto-stop.
- [ ] Expired sessions closed by `tracking:close-expired` scheduler/command.

## Environment Notes

- Record device model, Android version, OEM, network conditions, and any OEM background restrictions.
- Attach logs/screenshots for any failure.
