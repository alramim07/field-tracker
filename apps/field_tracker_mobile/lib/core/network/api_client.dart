import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../storage/token_storage.dart';

class ApiClient {
  ApiClient({
    Dio? dio,
    TokenStorage? tokenStorage,
    String? baseUrl,
  })  : _tokenStorage = tokenStorage ?? TokenStorage(),
        dio = dio ??
            Dio(
              BaseOptions(
                baseUrl: baseUrl ?? AppConfig.apiBaseUrl,
                headers: {
                  'Accept': 'application/json',
                  'Content-Type': 'application/json',
                },
              ),
            ) {
    this.dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStorage.readToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          handler.next(options);
        },
      ),
    );
  }

  final Dio dio;
  final TokenStorage _tokenStorage;
}
