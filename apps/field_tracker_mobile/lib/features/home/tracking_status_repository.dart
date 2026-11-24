import 'tracking_status.dart';

abstract class TrackingStatusRepository {
  Future<TrackingStatus> fetchStatus({DateTime? clientMidnightAt});
}
