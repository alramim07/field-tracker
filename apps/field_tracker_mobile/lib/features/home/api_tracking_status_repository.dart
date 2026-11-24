import '../../core/network/api_client.dart';
import 'tracking_status.dart';
import 'tracking_status_repository.dart';

class ApiTrackingStatusRepository implements TrackingStatusRepository {
  ApiTrackingStatusRepository({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  @override
  Future<TrackingStatus> fetchStatus({DateTime? clientMidnightAt}) async {
    final response = await _apiClient.dio.get(
      '/tracking/status',
      queryParameters: {
        if (clientMidnightAt != null) 'client_midnight_at': clientMidnightAt.toIso8601String(),
      },
    );
    return TrackingStatus.fromJson(
      Map<String, dynamic>.from(response.data as Map),
    );
  }
}
