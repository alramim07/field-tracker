import 'package:flutter_test/flutter_test.dart';
import 'package:field_tracker_mobile/features/auth/auth_controller.dart';
import 'package:field_tracker_mobile/features/auth/auth_repository.dart';
import 'package:field_tracker_mobile/features/auth/models/auth_session.dart';
import 'package:field_tracker_mobile/features/auth/models/auth_user.dart';

class FakeAuthRepository implements AuthRepository {
  FakeAuthRepository({this.currentUserValue});

  AuthUser? currentUserValue;
  int logoutCalls = 0;

  @override
  Future<AuthSession> login({
    required String email,
    required String password,
  }) {
    throw UnimplementedError();
  }

  @override
  Future<void> logout() async {
    logoutCalls++;
  }

  @override
  Future<AuthUser?> currentUser() async => currentUserValue;
}

void main() {
  test('restoreSession clears non-employee session', () async {
    final repo = FakeAuthRepository(
      currentUserValue: const AuthUser(
        id: 1,
        name: 'Admin',
        email: 'admin@example.com',
        role: 'admin',
      ),
    );
    final controller = AuthController(authRepository: repo);

    await controller.restoreSession();

    expect(controller.status, AuthStatus.unauthenticated);
    expect(controller.user, isNull);
    expect(repo.logoutCalls, 1);
  });
}
