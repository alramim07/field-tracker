import 'package:material_ui/material_ui.dart';

import 'auth_repository.dart';
import 'models/auth_session.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({
    super.key,
    required this.authRepository,
    required this.onAuthenticated,
  });

  final AuthRepository authRepository;
  final Future<void> Function(AuthSession session) onAuthenticated;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final form = _formKey.currentState;
    if (form != null && !form.validate()) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final session = await widget.authRepository.login(
        email: _emailController.text.trim(),
        password: _passwordController.text,
      );

      if (!session.user.isEmployee) {
        await widget.authRepository.logout();
        setState(() {
          _error = 'Employee account required';
          _loading = false;
        });
        return;
      }

      await widget.onAuthenticated(session);
      if (mounted) {
        setState(() {
          _loading = false;
        });
      }
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = 'Login failed. Check your credentials and try again.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      'Field Tracker',
                      style: theme.textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Sign in to start your duty tracking',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 32),
                    TextFormField(
                      key: const Key('login_email'),
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(
                        labelText: 'Email',
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Email is required';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),
                    TextFormField(
                      key: const Key('login_password'),
                      controller: _passwordController,
                      obscureText: true,
                      decoration: const InputDecoration(
                        labelText: 'Password',
                        border: OutlineInputBorder(),
                      ),
                      validator: (value) {
                        if (value == null || value.isEmpty) {
                          return 'Password is required';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 20),
                    if (_error != null) ...[
                      Text(
                        _error!,
                        key: const Key('login_error'),
                        style: TextStyle(color: theme.colorScheme.error),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 12),
                    ],
                    FilledButton(
                      key: const Key('login_submit'),
                      onPressed: _loading ? null : _submit,
                      child: _loading
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Sign in'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
