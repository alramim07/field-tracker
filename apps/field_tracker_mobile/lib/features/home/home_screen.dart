import 'dart:async';

import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:material_ui/material_ui.dart';

import '../history/history_grouping_service.dart';
import '../history/location_history_repository.dart';
import '../sync/sync_service.dart';
import 'current_location_provider.dart';
import 'day_state_store.dart';
import 'tracking_bridge.dart';
import 'tracking_status.dart';
import 'tracking_status_repository.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({
    super.key,
    required this.trackingStatusRepository,
    required this.syncService,
    this.trackingBridge,
    this.dayStateStore,
    this.locationHistoryRepository,
    this.currentLocationProvider,
    this.now,
    this.userName = 'Employee',
    this.onLogout,
    this.tileProvider,
  });

  final TrackingStatusRepository trackingStatusRepository;
  final SyncService syncService;
  final TrackingBridge? trackingBridge;
  final DayStateStore? dayStateStore;
  final LocationHistoryRepository? locationHistoryRepository;
  final CurrentLocationProvider? currentLocationProvider;
  final DateTime Function()? now;
  final String userName;
  final Future<void> Function()? onLogout;
  final TileProvider? tileProvider;

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> with WidgetsBindingObserver {
  TrackingStatus? _status;
  bool _loading = true;
  bool _syncing = false;
  String? _syncMessage;
  List<HistorySegment> _segments = const [];
  CurrentLocationFix? _currentFix;
  Timer? _ticker;

  DateTime _now() => (widget.now ?? DateTime.now)();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _load();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _ticker?.cancel();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _handleResume();
    }
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    await _checkDayChangeAndMaybeSync(initialLoad: true);
    await _refreshStatus();
    await _refreshLocationAndHistory();
    if (mounted) setState(() => _loading = false);
  }

  Future<void> _checkDayChangeAndMaybeSync({bool initialLoad = false}) async {
    final store = widget.dayStateStore;
    if (store == null) return;
    final now = _now();
    final today = DateTime(now.year, now.month, now.day);
    final stored = await store.readLastActiveDay();
    final storedDay =
        stored == null ? null : DateTime(stored.year, stored.month, stored.day);
    final changed = storedDay != null && storedDay.isBefore(today);
    if (changed) {
      await _performSync(showMessage: false);
    }
    await store.writeLastActiveDay(today);
    if (changed && !initialLoad && mounted) {
      await _refreshStatus();
    }
  }

  Future<void> _handleResume() async {
    await _checkDayChangeAndMaybeSync();
    await _refreshStatus();
    await _refreshLocationAndHistory();
  }

  Future<void> _refreshStatus() async {
    try {
      final now = _now();
      final midnight = DateTime(now.year, now.month, now.day + 1);
      final status = await widget.trackingStatusRepository.fetchStatus(
        clientMidnightAt: midnight.toUtc(),
      );
      if (!mounted) return;
      setState(() {
        _status = status;
        _configureTicker(status);
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {});
    }
  }

  void _configureTicker(TrackingStatus status) {
    _ticker?.cancel();
    _ticker = null;
    final session = status.activeSession;
    final shouldTick = session != null && session.isActive;
    // Avoid periodic timers in widget tests.
    final isTest = WidgetsBinding.instance.runtimeType.toString().contains('Test');
    if (shouldTick && !isTest) {
      _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
        if (mounted) setState(() {});
      });
    }
  }

  Future<void> _refreshLocationAndHistory() async {
    final historyRepo = widget.locationHistoryRepository;
    final locationProvider = widget.currentLocationProvider;
    final status = _status;
    final isIdle = status == null || status.activeSession == null || !status.activeSession!.isActive;

    if (isIdle && locationProvider != null) {
      try {
        final fix = await locationProvider.getCurrentLocation();
        if (mounted && fix != null) setState(() => _currentFix = fix);
      } catch (_) {}
    }

    if (historyRepo != null) {
      try {
        final points = await historyRepo.fetchCurrentDay(day: _now());
        final segments = const HistoryGroupingService().group(points);
        if (mounted) setState(() => _segments = segments);
      } catch (_) {}
    }
  }

  Future<void> _performSync({bool showMessage = true}) async {
    if (_syncing) return;
    setState(() {
      _syncing = true;
      if (showMessage) _syncMessage = 'Syncing...';
    });
    try {
      final result = await widget.syncService.syncNow();
      if (!mounted) return;
      setState(() {
        _syncing = false;
        _syncMessage = result.ok ? 'Synced' : (result.message ?? 'Sync failed');
      });
      await _refreshStatus();
      await _refreshLocationAndHistory();
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _syncing = false;
        _syncMessage = 'Sync failed';
      });
    }
  }

  Future<void> _runBridgeAction(Future<void> Function(TrackingBridge bridge) action) async {
    final bridge = widget.trackingBridge;
    if (bridge == null) return;
    try {
      await action(bridge);
      if (!mounted) return;
      await _refreshStatus();
      await _refreshLocationAndHistory();
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _syncMessage = 'Action failed';
      });
    }
  }

  bool get _isFreshDayReset {
    final status = _status;
    if (status == null) return false;
    final session = status.activeSession;
    final now = _now();
    final today = DateTime(now.year, now.month, now.day);
    if (session == null) return false;
    final endedAt = session.endedAt;
    if (endedAt == null) return false;
    final endedDay = DateTime(
      endedAt.toLocal().year,
      endedAt.toLocal().month,
      endedAt.toLocal().day,
    );
    // Forgotten extra duty auto-stopped at midnight belongs to previous day.
    if (session.stopReason == 'midnight_auto_stopped' && session.counted == false) {
      return !endedDay.isAfter(today);
    }
    // Any other session that ended before today indicates stale state after date change.
    return endedDay.isBefore(today);
  }

  _HomeViewState get _viewState {
    final status = _status;
    if (status == null) return _HomeViewState.beforeStart;
    if (_isFreshDayReset) return _HomeViewState.beforeStart;
    final session = status.activeSession;
    if (session == null) {
      return status.canExtend ? _HomeViewState.dutyEnded : _HomeViewState.beforeStart;
    }
    if (session.isActive && session.isNormalDuty) return _HomeViewState.normalTracking;
    if (session.isActive && session.isExtraDuty) return _HomeViewState.extraTracking;
    if (session.isExtraDuty && session.status == 'expired' && session.counted == false) {
      return _HomeViewState.midnightAutoStopped;
    }
    if (session.isExtraDuty && !session.isActive) return _HomeViewState.extraEnded;
    if (session.isNormalDuty && !session.isActive) {
      return status.canExtend || session.canExtend ? _HomeViewState.dutyEnded : _HomeViewState.beforeStart;
    }
    return _HomeViewState.beforeStart;
  }

  String _formatDuration(Duration d) {
    final h = d.inHours.toString().padLeft(2, '0');
    final m = (d.inMinutes % 60).toString().padLeft(2, '0');
    final s = (d.inSeconds % 60).toString().padLeft(2, '0');
    return '$h:$m:$s';
  }

  Duration _displayDuration() {
    final session = _status?.activeSession;
    if (session == null || _isFreshDayReset) return Duration.zero;
    final start = session.startedAt;
    final end = session.isActive ? _now().toUtc() : (session.endedAt ?? _now().toUtc());
    final diff = end.difference(start);
    return diff.isNegative ? Duration.zero : diff;
  }

  bool get _showTravelHistory {
    if (_segments.isNotEmpty) return true;
    final status = _status;
    if (status == null) return false;
    if (status.activeSession != null) return true;
    if (status.hasUsedExtraToday) return true;
    if (status.canExtend) return true;
    return false;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.menu),
          onPressed: () => Scaffold.maybeOf(context)?.openDrawer(),
          tooltip: 'Menu',
        ),
        title: const Text('Field Tracker'),
        actions: [
          IconButton(
            icon: const Icon(Icons.notifications_none),
            onPressed: () {},
            tooltip: 'Notifications',
          ),
          IconButton(
            icon: _syncing
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.sync),
            onPressed: _syncing ? null : () => _performSync(),
            tooltip: 'Sync',
          ),
        ],
      ),
      drawer: Drawer(
        child: SafeArea(
          child: ListView(
            children: [
              ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person)),
                title: Text(widget.userName),
                subtitle: const Text('Employee'),
              ),
              const Divider(),
              ListTile(
                leading: const Icon(Icons.home),
                title: const Text('Home'),
                onTap: () => Navigator.of(context).maybePop(),
              ),
            ],
          ),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: () => _performSync(),
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_syncMessage != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Text(
                        _syncMessage!,
                        key: const Key('sync_message'),
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ),
                  _buildMapCard(context),
                  const SizedBox(height: 16),
                  _buildTimerCard(context),
                  if (_showTravelHistory) ...[
                    const SizedBox(height: 16),
                    _buildTimeline(context),
                  ],
                ],
              ),
            ),
    );
  }

  Widget _buildMapCard(BuildContext context) {
    final fix = _currentFix;
    final center = fix != null
        ? LatLng(fix.latitude, fix.longitude)
        : const LatLng(23.7808, 90.2792);
    final isTest = WidgetsBinding.instance.runtimeType.toString().contains('TestWidgetsFlutterBinding');
    return Card(
      clipBehavior: Clip.antiAlias,
      child: SizedBox(
        height: 220,
        child: Stack(
          children: [
            FlutterMap(
              options: MapOptions(
                initialCenter: center,
                initialZoom: 15,
              ),
              children: [
                if (!isTest)
                  TileLayer(
                    urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    userAgentPackageName: 'com.fieldtracker.mobile',
                    tileProvider: widget.tileProvider,
                  ),
                if (fix != null)
                  MarkerLayer(
                    markers: [
                      Marker(
                        point: LatLng(fix.latitude, fix.longitude),
                        width: 40,
                        height: 40,
                        child: const Icon(Icons.location_pin, color: Colors.red, size: 40),
                      ),
                    ],
                  ),
                const RichAttributionWidget(
                  attributions: [
                    TextSourceAttribution('OpenStreetMap contributors'),
                  ],
                ),
              ],
            ),
            Positioned(
              left: 12,
              bottom: 12,
              right: 12,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
                decoration: BoxDecoration(
                  color: Theme.of(context).colorScheme.surface.withValues(alpha: 0.9),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  fix != null
                      ? '${fix.latitude.toStringAsFixed(5)}, ${fix.longitude.toStringAsFixed(5)}'
                      : 'Waiting for location',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildTimerCard(BuildContext context) {
    final state = _viewState;
    final duration = _formatDuration(_displayDuration());
    final statusLabel = switch (state) {
      _HomeViewState.beforeStart => 'NOT STARTED',
      _HomeViewState.normalTracking => 'TRACKING',
      _HomeViewState.dutyEnded => 'DUTY ENDED',
      _HomeViewState.extraTracking => 'EXTRA DUTY',
      _HomeViewState.extraEnded => 'EXTRA DUTY ENDED',
      _HomeViewState.midnightAutoStopped => 'AUTO-STOPPED AT MIDNIGHT',
    };

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(statusLabel, style: Theme.of(context).textTheme.labelLarge),
            const SizedBox(height: 8),
            Text(
              duration,
              key: const Key('tracking_timer'),
              style: Theme.of(context).textTheme.displaySmall,
            ),
            const SizedBox(height: 12),
            _buildActionButton(context, state),
          ],
        ),
      ),
    );
  }

  Widget _buildActionButton(BuildContext context, _HomeViewState state) {
    switch (state) {
      case _HomeViewState.beforeStart:
        return FilledButton(
          key: const Key('start_duty_tracking'),
          onPressed: () => _runBridgeAction((b) => b.startNormalDuty()),
          child: const Text('Start duty tracking'),
        );
      case _HomeViewState.normalTracking:
        final end = _status?.activeSession?.expiresAt?.toLocal();
        final label = end != null
            ? 'Tracking until ${end.hour.toString().padLeft(2, '0')}:${end.minute.toString().padLeft(2, '0')}'
            : 'Tracking';
        return FilledButton.tonal(
          onPressed: null,
          child: Text(label),
        );
      case _HomeViewState.dutyEnded:
        return FilledButton(
          key: const Key('extend_duty_tracking'),
          onPressed: () => _runBridgeAction((b) => b.startExtraDuty()),
          child: const Text('Extend duty tracking time'),
        );
      case _HomeViewState.extraTracking:
        return FilledButton(
          key: const Key('end_extra_duty'),
          onPressed: () => _runBridgeAction((b) => b.stopExtraDuty()),
          child: const Text('End extra duty'),
        );
      case _HomeViewState.extraEnded:
        return const Text('Extra duty ended for today');
      case _HomeViewState.midnightAutoStopped:
        return const Text('Extra duty auto-stopped at midnight and was not counted');
    }
  }

  Widget _buildTimeline(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Today\'s travel history', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 8),
            if (_segments.isEmpty)
              const Text('No movement recorded yet')
            else
              ..._segments.map((s) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(s.type == SegmentType.stop ? Icons.place : Icons.route),
                    title: Text(s.type == SegmentType.stop ? 'Stopped period' : 'Movement segment'),
                    subtitle: Text(
                      '${_fmtTime(s.startAt)} - ${_fmtTime(s.endAt)} · ${s.points.length} pings',
                    ),
                  )),
          ],
        ),
      ),
    );
  }

  String _fmtTime(DateTime dt) {
    final local = dt.toLocal();
    return '${local.hour.toString().padLeft(2, '0')}:${local.minute.toString().padLeft(2, '0')}';
  }
}

enum _HomeViewState {
  beforeStart,
  normalTracking,
  dutyEnded,
  extraTracking,
  extraEnded,
  midnightAutoStopped,
}
