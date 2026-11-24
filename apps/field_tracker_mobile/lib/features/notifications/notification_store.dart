import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import 'notification_item.dart';

class NotificationStore {
  NotificationStore({required SharedPreferences prefs}) : _prefs = prefs;

  static const storageKey = 'app_notifications_v1';
  static const _activeNormalSnapshotKey = 'active_normal_duty_snapshot_v1';
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

  /// Persists last-seen active normal-duty session and detects unexpected early
  /// stops durably across refresh/resume/bridge-state observations.
  Future<void> recordTrackingObservation({
    required String? sessionId,
    required String? sessionType,
    required bool isActive,
    required String? stopReason,
    required DateTime? startedAt,
    required DateTime? endedAt,
    required DateTime? expiresAt,
    required String? dutyEndTime,
    DateTime? observedAt,
  }) async {
    final now = (observedAt ?? DateTime.now()).toUtc();
    final isNormal = sessionType == 'normal_duty';
    final snapshot = _readActiveNormalSnapshot();

    if (isNormal && isActive && sessionId != null && sessionId.isNotEmpty) {
      await _writeActiveNormalSnapshot({
        'sessionId': sessionId,
        'startedAt': startedAt?.toUtc().toIso8601String(),
        'expiresAt': expiresAt?.toUtc().toIso8601String(),
        'dutyEndTime': dutyEndTime,
      });
      return;
    }

    if (isNormal && !isActive && sessionId != null && sessionId.isNotEmpty) {
      final excluded = _isExcludedStopReason(stopReason);
      final ended = (endedAt ?? now).toUtc();
      final exp = expiresAt?.toUtc();
      final endedBeforeDutyEnd = exp == null ? true : ended.isBefore(exp);
      if (!excluded && endedBeforeDutyEnd) {
        await addUnexpectedEarlyStop(
          sessionId: sessionId,
          stoppedAt: ended,
          dutyEndTime: dutyEndTime,
          stopReason: (stopReason == null || stopReason.isEmpty)
              ? 'unexpected_stop'
              : stopReason,
        );
      }
      if (snapshot != null && snapshot['sessionId'] == sessionId) {
        await _clearActiveNormalSnapshot();
      }
      return;
    }

    // No active normal session observed. If we previously saw an active normal
    // duty session, treat disappearance before duty end as unexpected unless
    // the observation explicitly carries an excluded reason. If another session
    // is active (e.g. extra duty), do not infer an unexpected normal stop.
    if (snapshot != null) {
      if (isActive) {
        await _clearActiveNormalSnapshot();
        return;
      }

      final snapSessionId = snapshot['sessionId'] as String?;
      final snapExpiresAt = DateTime.tryParse(snapshot['expiresAt'] as String? ?? '');
      final snapDutyEnd = snapshot['dutyEndTime'] as String?;
      final sameSessionObserved = sessionId != null && sessionId == snapSessionId;
      final excluded = _isExcludedStopReason(stopReason);

      if (sameSessionObserved && excluded) {
        await _clearActiveNormalSnapshot();
        return;
      }

      final beforeDutyEnd = snapExpiresAt == null ? true : now.isBefore(snapExpiresAt.toUtc());
      if (beforeDutyEnd && !excluded) {
        await addUnexpectedEarlyStop(
          sessionId: snapSessionId ?? (sessionId ?? 'unknown-session'),
          stoppedAt: (endedAt ?? now).toUtc(),
          dutyEndTime: dutyEndTime ?? snapDutyEnd,
          stopReason: (stopReason == null || stopReason.isEmpty)
              ? 'unexpected_stop'
              : stopReason,
        );
      }
      await _clearActiveNormalSnapshot();
    }
  }

  bool _isExcludedStopReason(String? stopReason) {
    return stopReason == 'expired' ||
        stopReason == 'duty_end_expired' ||
        stopReason == 'logout' ||
        stopReason == 'manual_extra_stop' ||
        stopReason == 'midnight_auto_stopped';
  }

  Map<String, dynamic>? _readActiveNormalSnapshot() {
    final raw = _prefs.getString(_activeNormalSnapshotKey);
    if (raw == null || raw.isEmpty) return null;
    final decoded = jsonDecode(raw);
    if (decoded is! Map) return null;
    return Map<String, dynamic>.from(decoded);
  }

  Future<void> _writeActiveNormalSnapshot(Map<String, dynamic> snapshot) async {
    await _prefs.setString(_activeNormalSnapshotKey, jsonEncode(snapshot));
  }

  Future<void> _clearActiveNormalSnapshot() async {
    await _prefs.remove(_activeNormalSnapshotKey);
  }

  String _fmtTime(DateTime dt) {
    final local = dt.toLocal();
    final h = local.hour.toString().padLeft(2, '0');
    final m = local.minute.toString().padLeft(2, '0');
    return '$h:$m';
  }
}
