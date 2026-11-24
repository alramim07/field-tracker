import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import 'device_registration_service.dart';

class ApiDeviceRegistrationApi implements DeviceRegistrationApi {
  ApiDeviceRegistrationApi({ApiClient? apiClient})
      : _dio = (apiClient ?? ApiClient()).dio;

  final Dio _dio;

  @override
  Future<void> registerDevice({required String deviceUuid}) async {
    await _dio.post<void>('/device/register', data: {
      'device_uuid': deviceUuid,
    });
  }
}
