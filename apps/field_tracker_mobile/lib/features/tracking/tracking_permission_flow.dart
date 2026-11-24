import 'dart:io';

import 'package:permission_handler/permission_handler.dart';

class TrackingPermissionFlow {
  const TrackingPermissionFlow();

  /// Requests permissions required before starting foreground location tracking.
  /// Throws [StateError] when required location permission is denied.
  Future<void> ensureGranted() async {
    if (!Platform.isAndroid) return;

    final location = await Permission.location.request();
    if (!location.isGranted) {
      throw StateError('Location permission is required to start tracking.');
    }

    // Background location may require separate grant/settings flow on Android 10+.
    final background = await Permission.locationAlways.status;
    if (!background.isGranted) {
      final requested = await Permission.locationAlways.request();
      if (!requested.isGranted) {
        throw StateError(
          'Background location permission is required for duty tracking.',
        );
      }
    }

    // Android 13+ notification permission for FGS notification visibility.
    final notification = await Permission.notification.status;
    if (!notification.isGranted) {
      await Permission.notification.request();
      // Do not hard-fail here; FGS may still run, but notification visibility can be limited.
    }
  }
}
