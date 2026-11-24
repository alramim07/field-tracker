import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/storage/token_storage.dart';
import 'auth_repository.dart';
import 'models/auth_session.dart';
import 'models/auth_user.dart';

class ApiAuthRepository implements AuthRepository {
  ApiAuthRepository({
    ApiClient? apiClient,
    TokenStorage? tokenStorage,
  })  : _apiClient = apiClient ?? ApiClient(tokenStorage: tokenStorage),
        _tokenStorage = tokenStorage ?? TokenStorage();

  final ApiClient _apiClient;
  final TokenStorage _tokenStorage;

  Dio get _dio => _apiClient.dio;

  @override
  Future<AuthSession> login({
    required String email,
    required String password,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/login',
      data: {
        'email': email,
        'password': password,
        'device_name': 'field-tracker-mobile',
      },
    );

    final data = response.data?['data'] as Map<String, dynamic>? ?? {};
    final token = data['token'] as String? ?? '';
    final userJson = data['user'] as Map<String, dynamic>? ?? {};
    final session = AuthSession(
      token: token,
      user: AuthUser.fromJson(userJson),
    );
    await _tokenStorage.saveToken(session.token);
    return session;
  }

  @override
  Future<void> logout() async {
    try {
      await _dio.post<void>('/logout');
    } finally {
      await _tokenStorage.clearToken();
    }
  }

  @override
  Future<AuthUser?> currentUser() async {
    final token = await _tokenStorage.readToken();
    if (token == null || token.isEmpty) return null;
    try {
      final response = await _dio.get<Map<String, dynamic>>('/me');
      final data = response.data?['data'] as Map<String, dynamic>? ?? {};
      return AuthUser.fromJson(data);
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) {
        await _tokenStorage.clearToken();
        return null;
      }
      rethrow;
    }
  }
}
