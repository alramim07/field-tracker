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
}
