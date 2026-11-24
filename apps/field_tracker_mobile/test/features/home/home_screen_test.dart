import 'package:flutter_test/flutter_test.dart';
import 'package:material_ui/material_ui.dart';
import 'package:field_tracker_mobile/features/home/day_state_store.dart';
import 'package:field_tracker_mobile/features/home/home_screen.dart';
import 'package:field_tracker_mobile/features/home/tracking_status.dart';
import 'package:field_tracker_mobile/features/home/tracking_status_repository.dart';
import 'package:field_tracker_mobile/features/sync/sync_service.dart';

class FakeTrackingStatusRepository implements TrackingStatusRepository {
  FakeTrackingStatusRepository(this.status);
  TrackingStatus status;
  int calls = 0;

  @override
  Future<TrackingStatus> fetchStatus({DateTime? clientMidnightAt}) async {
    calls++;
    return status;
  }
}

class FakeSyncService implements SyncService {
  int syncCalls = 0;
  SyncResult result = const SyncResult(ok: true);

  @override
  Future<SyncResult> syncNow() async {
    syncCalls++;
    return result;
  }
}

class FakeDayStateStore implements DayStateStore {
  FakeDayStateStore({this.lastActiveDay});
  DateTime? lastActiveDay;

  @override
  Future<DateTime?> readLastActiveDay() async => lastActiveDay;

  @override
  Future<void> writeLastActiveDay(DateTime day) async {
    lastActiveDay = DateTime(day.year, day.month, day.day);
  }

  @override
  Future<void> clear() async {
    lastActiveDay = null;
  }
}

TrackingStatus _endedNormalCanExtend() {
  final start = DateTime.utc(2026, 11, 24, 9, 0);
  final end = DateTime.utc(2026, 11, 24, 17, 0);
  return TrackingStatus(
    serverTime: DateTime.utc(2026, 11, 24, 17, 1),
    canExtend: true,
    hasUsedExtraToday: false,
    activeSession: TrackingSessionStatus(
      sessionUuid: 's1',
      status: 'expired',
      sessionType: 'normal_duty',
      counted: true,
      startedAt: start,
      expiresAt: end,
      endedAt: end,
      stopReason: 'duty_end_expired',
      canExtend: true,
      hasUsedExtraToday: false,
    ),
  );
}

TrackingStatus _freshDayStatus(DateTime now) {
  return TrackingStatus(
    serverTime: now.toUtc(),
    canExtend: false,
    hasUsedExtraToday: false,
    activeSession: null,
  );
}

void main() {
  testWidgets('shows Extend duty tracking time after duty end', (tester) async {
    final repo = FakeTrackingStatusRepository(_endedNormalCanExtend());
    final sync = FakeSyncService();

    await tester.pumpWidget(
      MaterialApp(
        home: HomeScreen(
          trackingStatusRepository: repo,
          syncService: sync,
          now: () => DateTime(2026, 11, 24, 17, 1),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Extend duty tracking time'), findsOneWidget);
  });

  testWidgets('resets to fresh day state after phone date changes', (tester) async {
    final now = DateTime(2026, 11, 25, 0, 1);
    final repo = FakeTrackingStatusRepository(_freshDayStatus(now));
    final sync = FakeSyncService();
    final dayStore = FakeDayStateStore(
      lastActiveDay: DateTime(2026, 11, 24),
    );

    // Fake stored day yesterday + midnight auto-stop; on resume expect sync called and Start duty tracking shown.
    repo.status = TrackingStatus(
      serverTime: now.toUtc(),
      canExtend: false,
      hasUsedExtraToday: true,
      activeSession: TrackingSessionStatus(
        sessionUuid: 's-extra',
        status: 'expired',
        sessionType: 'extra_duty',
        counted: false,
        startedAt: DateTime(2026, 11, 24, 18, 0),
        expiresAt: DateTime(2026, 11, 25, 0, 0),
        endedAt: DateTime(2026, 11, 25, 0, 0),
        stopReason: 'midnight_auto_stopped',
        canExtend: false,
        hasUsedExtraToday: true,
      ),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: HomeScreen(
          trackingStatusRepository: repo,
          syncService: sync,
          dayStateStore: dayStore,
          now: () => now,
        ),
      ),
    );
    await tester.pumpAndSettle();

    // After status load we have yesterday/midnight auto-stop state; simulate resume/date change.
    repo.status = _freshDayStatus(now);
    tester.binding.handleAppLifecycleStateChanged(AppLifecycleState.resumed);
    await tester.pump();
    await tester.pumpAndSettle();

    expect(sync.syncCalls, greaterThanOrEqualTo(1));
    expect(find.text('Start duty tracking'), findsOneWidget);
  });
}
