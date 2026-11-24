/// Abstraction over the Task 9 native tracking bridge.
/// Task 8 consumes this interface; Task 9 provides the MethodChannel/EventChannel implementation.
abstract class TrackingBridge {
  Stream<Map<String, dynamic>> get events;
  Future<void> startTracking(Map<String, dynamic> payload);
  Future<void> stopTracking();
  Future<Map<String, dynamic>?> getCurrentState();
}
