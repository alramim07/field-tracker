import 'package:flutter/foundation.dart';

import '../device/device_registration_service.dart';
import '../tracking/tracker_bridge.dart';
import 'auth_repository.dart';
import 'models/auth_session.dart';
import 'models/auth_user.dart';

enum AuthStatus { unknown, unauthenticated, authenticated }

class AuthController extends ChangeNotifier {
  AuthController({
    required AuthRepository authRepository,
    DeviceRegistrationService? deviceRegistrationService,
    TrackingBridge? trackingBridge,
  })  : _authRepository = authRepository,
        _deviceRegistrationService = deviceRegistrationService,
        _trackingBridge = trackingBridge;

  final AuthRepository _authRepository;
  final DeviceRegistrationService? _deviceRegistrationService;
  final TrackingBridge? _trackingBridge;

  AuthStatus status = AuthStatus.unknown;
  AuthUser? user;
  String? error;

  bool get isAuthenticated => status == AuthStatus.authenticated;

  Future<void> restoreSession() async {
    final current = await _authRepository.currentUser();
    if (current == null) {
      user = null;
      status = AuthStatus.unauthenticated;
      notifyListeners();
      return;
    }
    if (!current.isEmployee) {
      await _authRepository.logout();
      user = null;
      status = AuthStatus.unauthenticated;
      notifyListeners();
      return;
    }
    user = current;
    status = AuthStatus.authenticated;
    notifyListeners();
  }

  Future<void> handleAuthenticated(AuthSession session) async {
    user = session.user;
    status = AuthStatus.authenticated;
    error = null;
    notifyListeners();
    try {
      await _deviceRegistrationService?.ensureRegistered();
    } catch (_) {
      // Device registration can be retried on next authenticated session.
    }
  }

  Future<void> logout() async {
    try {
      await _trackingBridge?.stopTracking();
    } catch (_) {
      // Best-effort: backend logout still ends active session.
    }
    await _authRepository.logout();
    user = null;
    status = AuthStatus.unauthenticated;
    notifyListeners();
  }
}
