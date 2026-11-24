import 'dart:async';

import 'package:flutter/services.dart';

/// Contract for starting/stopping backend tracking sessions.
/// Implementations call the Laravel API; the native bridge receives the
/// resulting session payload.
abstract class TrackingSessionApi {
  Future<Map<String, dynamic>> startNormalDuty();
  Future<Map<String, dynamic>> startExtraDuty();
  Future<Map<String, dynamic>> stopExtraDuty();
}

/// Abstraction over native foreground tracking service.
/// Task 8+ UI consumes this interface; Task 9 provides the MethodChannel impl.
abstract class TrackingBridge {
  Stream<Map<String, dynamic>> get events;

  /// Low-level: send session payload to native FGS.
  Future<void> startTracking(Map<String, dynamic> payload);

  /// Low-level: stop native tracking (any active session).
  Future<void> stopTracking();

  /// Low-level: ask native side to sync/reconcile state.
  Future<void> syncState();

  /// Low-level: get native state.
  Future<Map<String, dynamic>?> getState();

  /// Convenience used by UI.
  Future<Map<String, dynamic>?> getCurrentState();

  Future<bool> isRunning();

  /// High-level: start normal duty (backend session + native FGS).
  Future<void> startNormalDuty();

  /// High-level: extend with extra duty (backend session + native FGS).
  Future<void> startExtraDuty();

  /// High-level: manually stop extra duty (backend + native).
  Future<void> stopExtraDuty();
}

class NoopTrackingBridge implements TrackingBridge {
  final StreamController<Map<String, dynamic>> _controller =
      StreamController<Map<String, dynamic>>.broadcast();

  @override
  Stream<Map<String, dynamic>> get events => _controller.stream;

  @override
  Future<void> startTracking(Map<String, dynamic> payload) async {}

  @override
  Future<void> stopTracking() async {}

  @override
  Future<void> syncState() async {}

  @override
  Future<Map<String, dynamic>?> getState() async => null;

  @override
  Future<Map<String, dynamic>?> getCurrentState() async => null;

  @override
  Future<bool> isRunning() async => false;

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

class MethodChannelTrackingBridge implements TrackingBridge {
  MethodChannelTrackingBridge({
    MethodChannel channel = const MethodChannel('field_tracker/tracking'),
    EventChannel eventChannel =
        const EventChannel('field_tracker/tracking/events'),
    TrackingSessionApi? sessionApi,
    Future<String?> Function()? tokenProvider,
    Future<String?> Function()? deviceUuidProvider,
    String? baseUrl,
  })  : _channel = channel,
        _eventChannel = eventChannel,
        _sessionApi = sessionApi,
        _tokenProvider = tokenProvider,
        _deviceUuidProvider = deviceUuidProvider,
        _baseUrl = baseUrl;

  final MethodChannel _channel;
  final EventChannel _eventChannel;
  final TrackingSessionApi? _sessionApi;
  final Future<String?> Function()? _tokenProvider;
  final Future<String?> Function()? _deviceUuidProvider;
  final String? _baseUrl;

  Stream<Map<String, dynamic>>? _events;

  @override
  Stream<Map<String, dynamic>> get events {
    _events ??= _eventChannel.receiveBroadcastStream().map((event) {
      if (event is Map) {
        return Map<String, dynamic>.from(event);
      }
      return <String, dynamic>{'raw': event};
    });
    return _events!;
  }

  @override
  Future<void> startTracking(Map<String, dynamic> payload) async {
    final enriched = Map<String, dynamic>.from(payload);
    enriched['base_url'] ??= _baseUrl;
    if (enriched['token'] == null && _tokenProvider != null) {
      enriched['token'] = await _tokenProvider();
    }
    if (enriched['device_uuid'] == null && _deviceUuidProvider != null) {
      enriched['device_uuid'] = await _deviceUuidProvider();
    }
    await _channel.invokeMethod<void>('startTracking', enriched);
  }

  @override
  Future<void> stopTracking() async {
    await _channel.invokeMethod<void>('stopTracking');
  }

  @override
  Future<void> syncState() async {
    await _channel.invokeMethod<void>('syncState');
  }

  @override
  Future<Map<String, dynamic>?> getState() async {
    final result = await _channel.invokeMapMethod<String, dynamic>('getState');
    return result == null ? null : Map<String, dynamic>.from(result);
  }

  @override
  Future<Map<String, dynamic>?> getCurrentState() => getState();

  @override
  Future<bool> isRunning() async {
    final result = await _channel.invokeMethod<bool>('isRunning');
    if (result != null) return result;
    final state = await getState();
    return state?['is_running'] == true;
  }

  @override
  Future<void> startNormalDuty() async {
    final api = _sessionApi;
    if (api == null) {
      throw StateError('TrackingSessionApi is required for startNormalDuty');
    }
    final payload = await api.startNormalDuty();
    await startTracking(payload);
  }

  @override
  Future<void> startExtraDuty() async {
    final api = _sessionApi;
    if (api == null) {
      throw StateError('TrackingSessionApi is required for startExtraDuty');
    }
    final payload = await api.startExtraDuty();
    await startTracking(payload);
  }

  @override
  Future<void> stopExtraDuty() async {
    final api = _sessionApi;
    if (api != null) {
      await api.stopExtraDuty();
    }
    await _channel.invokeMethod<void>('stopExtraDuty');
  }
}
