import 'package:field_tracker_mobile/features/notifications/notification_store.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  test('notification store marks unexpected early stop as unread', () async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    final store = NotificationStore(prefs: prefs);

    await store.addUnexpectedEarlyStop(
      sessionId: 'session-1',
      stoppedAt: DateTime.utc(2026, 11, 24, 15, 30),
      dutyEndTime: '17:00:00',
      stopReason: 'manual_stop',
    );

    final items = await store.list();
    expect(items, hasLength(1));
    expect(items.first.isRead, isFalse);
    expect(await store.unreadCount(), 1);
  });

  test('notification store ignores scheduled expiry and excluded stop reasons', () async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    final store = NotificationStore(prefs: prefs);

    for (final reason in const [
      'expired',
      'duty_end_expired',
      'logout',
      'manual_extra_stop',
      'midnight_auto_stopped',
    ]) {
      await store.addUnexpectedEarlyStop(
        sessionId: 'session-$reason',
        stoppedAt: DateTime.utc(2026, 11, 24, 17, 0),
        dutyEndTime: '17:00:00',
        stopReason: reason,
      );
    }

    expect(await store.list(), isEmpty);
    expect(await store.unreadCount(), 0);
  });

  test('durable detection records unexpected stop when active normal session disappears before duty end', () async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    final store = NotificationStore(prefs: prefs);

    await store.recordTrackingObservation(
      sessionId: 'normal-1',
      sessionType: 'normal_duty',
      isActive: true,
      stopReason: null,
      startedAt: DateTime.utc(2026, 11, 24, 9, 0),
      endedAt: null,
      expiresAt: DateTime.utc(2026, 11, 24, 17, 0),
      dutyEndTime: '17:00:00',
      observedAt: DateTime.utc(2026, 11, 24, 15, 0),
    );

    await store.recordTrackingObservation(
      sessionId: null,
      sessionType: null,
      isActive: false,
      stopReason: null,
      startedAt: null,
      endedAt: null,
      expiresAt: null,
      dutyEndTime: '17:00:00',
      observedAt: DateTime.utc(2026, 11, 24, 15, 30),
    );

    final items = await store.list();
    expect(items, hasLength(1));
    expect(items.first.isRead, isFalse);
    expect(items.first.sessionId, 'normal-1');
  });

  test('durable detection does not alert when normal session ends at scheduled expiry', () async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    final store = NotificationStore(prefs: prefs);

    await store.recordTrackingObservation(
      sessionId: 'normal-2',
      sessionType: 'normal_duty',
      isActive: true,
      stopReason: null,
      startedAt: DateTime.utc(2026, 11, 24, 9, 0),
      endedAt: null,
      expiresAt: DateTime.utc(2026, 11, 24, 17, 0),
      dutyEndTime: '17:00:00',
      observedAt: DateTime.utc(2026, 11, 24, 16, 59),
    );

    await store.recordTrackingObservation(
      sessionId: 'normal-2',
      sessionType: 'normal_duty',
      isActive: false,
      stopReason: 'duty_end_expired',
      startedAt: DateTime.utc(2026, 11, 24, 9, 0),
      endedAt: DateTime.utc(2026, 11, 24, 17, 0),
      expiresAt: DateTime.utc(2026, 11, 24, 17, 0),
      dutyEndTime: '17:00:00',
      observedAt: DateTime.utc(2026, 11, 24, 17, 0),
    );

    expect(await store.list(), isEmpty);
  });
}
