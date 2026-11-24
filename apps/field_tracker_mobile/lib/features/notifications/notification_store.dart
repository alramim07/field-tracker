import 'package:shared_preferences/shared_preferences.dart';

import 'notification_item.dart';

class NotificationStore {
  NotificationStore({required SharedPreferences prefs}) : _prefs = prefs;

  static const storageKey = 'app_notifications_v1';
  final SharedPreferences _prefs;

  Future<List<NotificationItem>> list() async {
    final items = NotificationItem.decodeList(_prefs.getString(storageKey));
    items.sort((a, b) => b.createdAt.compareTo(a.createdAt));
    return items;
  }

  Future<int> unreadCount() async {
    final items = await list();
    return items.where((e) => !e.isRead).length;
  }

  Future<void> addUnexpectedEarlyStop({
    required String sessionId,
    required DateTime stoppedAt,
    required String? dutyEndTime,
    required String stopReason,
  }) async {
    // Alert only for unexpected early stop of normal duty.
    // Not for scheduled expiry/extra-duty stop/logout.
    if (stopReason == 'expired' ||
        stopReason == 'duty_end_expired' ||
        stopReason == 'logout' ||
        stopReason == 'manual_extra_stop' ||
        stopReason == 'midnight_auto_stopped') {
      return;
    }

    final existing = await list();
    if (existing.any((e) => e.sessionId == sessionId && e.stopReason == stopReason)) {
      return;
    }

    final item = NotificationItem(
      id: '${sessionId}_${stoppedAt.millisecondsSinceEpoch}_$stopReason',
      title: 'Tracker stopped during duty',
      body: dutyEndTime == null || dutyEndTime.isEmpty
          ? 'Tracking stopped unexpectedly at ${_fmtTime(stoppedAt)}.'
          : 'Tracking stopped unexpectedly at ${_fmtTime(stoppedAt)} before duty end $dutyEndTime.',
      createdAt: stoppedAt.toUtc(),
      isRead: false,
      sessionId: sessionId,
      stopReason: stopReason,
      dutyEndTime: dutyEndTime,
    );
    final updated = [item, ...existing];
    await _prefs.setString(storageKey, NotificationItem.encodeList(updated));
  }

  Future<void> markRead(String id) async {
    final items = await list();
    final updated = items
        .map((e) => e.id == id ? e.copyWith(isRead: true) : e)
        .toList();
    await _prefs.setString(storageKey, NotificationItem.encodeList(updated));
  }

  Future<void> markAllRead() async {
    final items = await list();
    final updated = items.map((e) => e.copyWith(isRead: true)).toList();
    await _prefs.setString(storageKey, NotificationItem.encodeList(updated));
  }

  Future<void> clear() async {
    await _prefs.remove(storageKey);
  }

  String _fmtTime(DateTime dt) {
    final local = dt.toLocal();
    final h = local.hour.toString().padLeft(2, '0');
    final m = local.minute.toString().padLeft(2, '0');
    return '$h:$m';
  }
}
