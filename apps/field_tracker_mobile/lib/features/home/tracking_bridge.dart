import 'dart:async';

/// Abstraction over the Task 9 native tracking bridge.
/// Task 8 consumes this interface; Task 9 provides the MethodChannel/EventChannel implementation.
abstract class TrackingBridge {
  Stream<Map<String, dynamic>> get events;
  Future<void> startTracking(Map<String, dynamic> payload);
  Future<void> stopTracking();
  Future<Map<String, dynamic>?> getCurrentState();

  /// Starts normal duty tracking for the day.
  Future<void> startNormalDuty();

  /// Extends duty by starting extra duty tracking (separate session, one/day).
  Future<void> startExtraDuty();

  /// Manually stops the active extra duty session.
  Future<void> stopExtraDuty();
}

class NoopTrackingBridge implements TrackingBridge {
  final StreamController<Map<String, dynamic>> _controller =
      StreamController<Map<String, dynamic>>.broadcast();

  @override
  Stream<Map<String, dynamic>> get events => _controller.stream;

  @override
  Future<Map<String, dynamic>?> getCurrentState() async => null;

  @override
  Future<void> startTracking(Map<String, dynamic> payload) async {}

  @override
  Future<void> stopTracking() async {}

  @override
  Future<void> startNormalDuty() async {}

  @override
  Future<void> startExtraDuty() async {}

  @override
  Future<void> stopExtraDuty() async {}

  void dispose() {
    _controller.close();
  }
}
