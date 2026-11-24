import '../../core/config/app_config.dart';
import '../../core/network/api_client.dart';
import '../../core/storage/token_storage.dart';
import '../device/device_registration_service.dart';
import 'tracker_bridge.dart';

class ApiTrackingSessionApi implements TrackingSessionApi {
  ApiTrackingSessionApi({
    required ApiClient apiClient,
    required DeviceIdentityStorage deviceIdentityStorage,
    required TokenStorage tokenStorage,
    String? baseUrl,
  })  : _apiClient = apiClient,
        _deviceIdentityStorage = deviceIdentityStorage,
        _tokenStorage = tokenStorage,
        _baseUrl = baseUrl ?? AppConfig.apiBaseUrl;

  final ApiClient _apiClient;
  final DeviceIdentityStorage _deviceIdentityStorage;
  final TokenStorage _tokenStorage;
  final String _baseUrl;

  @override
  Future<Map<String, dynamic>> startNormalDuty() async {
    return _start(mode: 'normal_duty');
  }

  @override
  Future<Map<String, dynamic>> startExtraDuty() async {
    final now = DateTime.now();
    final midnightLocal = DateTime(now.year, now.month, now.day + 1);
    return _start(
      mode: 'extra_duty',
      clientMidnightAt: midnightLocal.toUtc().toIso8601String(),
    );
  }

  @override
  Future<Map<String, dynamic>> stopExtraDuty() async {
    final response = await _apiClient.dio.post<Map<String, dynamic>>('/tracking/stop');
    final data = response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
    return _enrich(data);
  }

  Future<Map<String, dynamic>> _start({
    required String mode,
    String? clientMidnightAt,
  }) async {
    final deviceUuid = await _deviceIdentityStorage.readDeviceUuid();
    if (deviceUuid == null || deviceUuid.isEmpty) {
      throw StateError('Device is not registered');
    }
    final now = DateTime.now();
    final midnightLocal = DateTime(now.year, now.month, now.day + 1);
    final response = await _apiClient.dio.post<Map<String, dynamic>>(
      '/tracking/start',
      data: {
        'device_uuid': deviceUuid,
        'mode': mode,
        'client_midnight_at': (clientMidnightAt ?? midnightLocal.toUtc().toIso8601String()),
        'client_timezone': now.timeZoneName,
        'client_now': now.toUtc().toIso8601String(),
      },
    );
    final data = response.data?['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
    return _enrich(data);
  }

  Future<Map<String, dynamic>> _enrich(Map<String, dynamic> data) async {
    final token = await _tokenStorage.readToken();
    final deviceUuid = await _deviceIdentityStorage.readDeviceUuid();
    return {
      ...data,
      'token': token,
      'base_url': _baseUrl,
      'device_uuid': deviceUuid ?? data['device_uuid'],
    };
  }
}
