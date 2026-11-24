import 'package:field_tracker_mobile/features/tracking/tracker_bridge.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const channel = MethodChannel('field_tracker/tracking');
  final calls = <MethodCall>[];

  setUp(() {
    calls.clear();
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, (call) async {
      calls.add(call);
      switch (call.method) {
        case 'getState':
          return {
            'is_running': true,
            'session_type': 'normal_duty',
            'session_uuid': 's1',
          };
        case 'isRunning':
          return true;
        default:
          return null;
      }
    });
  });

  tearDown(() {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, null);
  });

  test('startTracking sends normal duty payload to method channel', () async {
    final bridge = MethodChannelTrackingBridge(channel: channel);

    await bridge.startTracking({
      'session_uuid': 'session-1',
      'session_type': 'normal_duty',
      'expires_at': '2026-11-24T17:00:00Z',
      'device_uuid': 'device-1',
    });

    expect(calls, hasLength(1));
    expect(calls.single.method, 'startTracking');
    expect(calls.single.arguments, isA<Map>());
    final args = Map<String, dynamic>.from(calls.single.arguments as Map);
    expect(args['session_type'], 'normal_duty');
    expect(args['session_uuid'], 'session-1');
  });

  test('stopExtraDuty invokes method channel', () async {
    final bridge = MethodChannelTrackingBridge(channel: channel);

    await bridge.stopExtraDuty();

    expect(calls.map((c) => c.method), contains('stopExtraDuty'));
  });

  test('syncState invokes method channel', () async {
    final bridge = MethodChannelTrackingBridge(channel: channel);

    await bridge.syncState();

    expect(calls.map((c) => c.method), contains('syncState'));
  });

  test('getState returns current native state', () async {
    final bridge = MethodChannelTrackingBridge(channel: channel);

    final state = await bridge.getState();

    expect(calls.map((c) => c.method), contains('getState'));
    expect(state, isNotNull);
    expect(state!['is_running'], true);
    expect(state['session_type'], 'normal_duty');
  });

  test('isRunning returns native running state', () async {
    final bridge = MethodChannelTrackingBridge(channel: channel);

    final running = await bridge.isRunning();

    expect(running, isTrue);
  });
}
