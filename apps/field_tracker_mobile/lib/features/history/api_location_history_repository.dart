import '../../core/network/api_client.dart';
import 'history_grouping_service.dart';
import 'location_history_repository.dart';

class ApiLocationHistoryRepository implements LocationHistoryRepository {
  ApiLocationHistoryRepository({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;

  @override
  Future<List<HistoryPoint>> fetchCurrentDay({DateTime? day}) async {
    final now = day ?? DateTime.now();
    final from = DateTime(now.year, now.month, now.day).toUtc().toIso8601String();
    final to = DateTime(now.year, now.month, now.day, 23, 59, 59).toUtc().toIso8601String();
    final response = await _apiClient.dio.get(
      '/me/location-history',
      queryParameters: {
        'from': from,
        'to': to,
        'per_page': 200,
      },
    );
    final body = response.data;
    final List<dynamic> data;
    if (body is Map<String, dynamic>) {
      data = (body['data'] as List<dynamic>? ?? const []);
    } else if (body is List<dynamic>) {
      data = body;
    } else {
      data = const [];
    }
    return data
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .map((e) => HistoryPoint(
              latitude: _toDouble(e['latitude']),
              longitude: _toDouble(e['longitude']),
              recordedAt: DateTime.parse(e['recorded_at'] as String),
              accuracy: e['accuracy'] == null ? null : _toDouble(e['accuracy']),
            ))
        .toList();
  }

  double _toDouble(dynamic value) {
    if (value is num) return value.toDouble();
    return double.parse(value.toString());
  }
}
