import 'sync_service.dart';

class AppSyncService implements SyncService {
  AppSyncService({required List<Future<void> Function()> tasks}) : _tasks = tasks;

  final List<Future<void> Function()> _tasks;

  @override
  Future<SyncResult> syncNow() async {
    try {
      for (final task in _tasks) {
        await task();
      }
      return const SyncResult(ok: true, message: 'Synced');
    } catch (e) {
      return SyncResult(ok: false, message: e.toString());
    }
  }
}
