import 'models/auth_session.dart';
import 'models/auth_user.dart';

abstract class AuthRepository {
  Future<AuthSession> login({
    required String email,
    required String password,
  });

  Future<void> logout();

  Future<AuthUser?> currentUser();
}
