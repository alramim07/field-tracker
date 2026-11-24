import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import 'profile_repository.dart';
import 'user_profile.dart';

class ApiProfileRepository implements ProfileRepository {
  ApiProfileRepository({required ApiClient apiClient}) : _apiClient = apiClient;

  final ApiClient _apiClient;
  Dio get _dio => _apiClient.dio;

  @override
  Future<UserProfile> fetchProfile() async {
    final response = await _dio.get<Map<String, dynamic>>('/me');
    final data = response.data?['data'] as Map<String, dynamic>? ?? const {};
    return UserProfile.fromMeJson(data);
  }

  @override
  Future<UserProfile> updateProfile({String? phone, String? email}) async {
    final body = <String, dynamic>{};
    if (phone != null) body['phone'] = phone;
    if (email != null) body['email'] = email;
    final response = await _dio.patch<Map<String, dynamic>>(
      '/me/profile',
      data: body,
    );
    final data = response.data?['data'] as Map<String, dynamic>? ?? const {};
    return UserProfile.fromMeJson(data);
  }

  @override
  Future<String> uploadPhoto({
    required String filePath,
    required String filename,
  }) async {
    final formData = FormData.fromMap({
      'photo': await MultipartFile.fromFile(filePath, filename: filename),
    });
    final response = await _dio.post<Map<String, dynamic>>(
      '/me/photo',
      data: formData,
      options: Options(contentType: 'multipart/form-data'),
    );
    final data = response.data?['data'] as Map<String, dynamic>? ?? const {};
    return data['photo_url'] as String? ?? '';
  }

  @override
  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    await _dio.post<void>(
      '/me/password',
      data: {
        'current_password': currentPassword,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );
  }
}
