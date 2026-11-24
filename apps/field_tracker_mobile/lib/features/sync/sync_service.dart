class SyncResult {
  const SyncResult({required this.ok, this.message});
  final bool ok;
  final String? message;
}

abstract class SyncService {
  Future<SyncResult> syncNow();
}
