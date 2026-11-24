import 'package:material_ui/material_ui.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:field_tracker_mobile/features/auth/auth_repository.dart';
import 'package:field_tracker_mobile/features/auth/login_screen.dart';
import 'package:field_tracker_mobile/features/auth/models/auth_session.dart';
import 'package:field_tracker_mobile/features/auth/models/auth_user.dart';

class FakeAuthRepository implements AuthRepository {
  FakeAuthRepository({required this.session});

  final AuthSession session;
  int loginCalls = 0;
  int logoutCalls = 0;

  @override
  Future<AuthSession> login({
    required String email,
    required String password,
  }) async {
    loginCalls++;
    return session;
  }

  @override
  Future<void> logout() async {
    logoutCalls++;
  }

  @override
  Future<AuthUser?> currentUser() async => session.user;
}

void main() {
  testWidgets('login rejects non-employee role', (tester) async {
    final repo = FakeAuthRepository(
      session: AuthSession(
        token: 'token',
        user: const AuthUser(
          id: 1,
          name: 'Admin',
          email: 'admin@example.com',
          role: 'admin',
        ),
      ),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: LoginScreen(
          authRepository: repo,
          onAuthenticated: (_) async {},
        ),
      ),
    );

    await tester.enterText(find.byKey(const Key('login_email')), 'admin@example.com');
    await tester.enterText(find.byKey(const Key('login_password')), 'password');
    await tester.tap(find.byKey(const Key('login_submit')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('Employee account required'), findsOneWidget);
    expect(repo.loginCalls, 1);
    expect(repo.logoutCalls, 1);
  });
}
