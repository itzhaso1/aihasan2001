import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:hasim/core/config/app_config.dart';
import 'package:hasim/core/widgets/hasim_logo.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final _idController = TextEditingController();
  final _passwordController = TextEditingController();
  final _formKey = GlobalKey<FormState>();
  bool _obscure = true;
  bool _googleBusy = false;

  @override
  void dispose() {
    _idController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final ok = await ref.read(authControllerProvider.notifier).login(
          _idController.text.trim(),
          _passwordController.text,
        );
    if (!mounted) return;
    if (ok) {
      final auth = ref.read(authControllerProvider);
      if (auth.workspace == null) {
        context.go('/workspaces');
      } else {
        context.go('/conversations');
      }
    }
  }

  Future<void> _google() async {
    setState(() => _googleBusy = true);
    try {
      final google = GoogleSignIn(scopes: const ['email', 'profile']);
      final account = await google.signIn();
      if (account == null) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('تم إلغاء تسجيل الدخول عبر Google.')),
          );
        }
        return;
      }
      final auth = await account.authentication;
      final token = auth.accessToken ?? auth.idToken;
      if (token == null || token.isEmpty) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('يحتاج إعداد Google')),
          );
        }
        return;
      }
      final ok = await ref.read(authControllerProvider.notifier).socialLogin(
            provider: 'google',
            accessToken: token,
          );
      if (!mounted) return;
      if (ok) {
        final state = ref.read(authControllerProvider);
        context.go(state.workspace == null ? '/workspaces' : '/conversations');
      }
    } catch (e) {
      if (!mounted) return;
      final msg = e.toString().toLowerCase();
      final needsSetup = msg.contains('client') ||
          msg.contains('platform') ||
          msg.contains('missing') ||
          msg.contains('not been configured') ||
          msg.contains('sign_in_failed') ||
          msg.contains('10:') ||
          msg.contains('12500');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(needsSetup ? 'يحتاج إعداد Google' : 'تعذر تسجيل الدخول عبر Google.'),
        ),
      );
    } finally {
      if (mounted) setState(() => _googleBusy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider);
    final primary = Theme.of(context).colorScheme.primary;

    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [
              primary.withValues(alpha: 0.10),
              Theme.of(context).scaffoldBackgroundColor,
            ],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(24),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const SizedBox(height: 12),
                      const HasimLogo(size: 88, showWordmark: true),
                      const SizedBox(height: 8),
                      Text(
                        'إدارة المحادثات والحجوزات',
                        textAlign: TextAlign.center,
                        style: TextStyle(color: Colors.grey.shade700),
                      ),
                      const SizedBox(height: 28),
                      TextFormField(
                        controller: _idController,
                        decoration: const InputDecoration(
                          labelText: 'البريد أو الجوال',
                          prefixIcon: Icon(Icons.person_outline),
                        ),
                        textDirection: TextDirection.ltr,
                        textAlign: TextAlign.left,
                        validator: (v) => (v == null || v.trim().isEmpty) ? 'مطلوب' : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _passwordController,
                        obscureText: _obscure,
                        decoration: InputDecoration(
                          labelText: 'كلمة المرور',
                          prefixIcon: const Icon(Icons.lock_outline),
                          suffixIcon: IconButton(
                            onPressed: () => setState(() => _obscure = !_obscure),
                            icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                          ),
                        ),
                        validator: (v) => (v == null || v.isEmpty) ? 'مطلوب' : null,
                      ),
                      Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: TextButton(
                          onPressed: () => context.push('/forgot-password'),
                          child: const Text('نسيت كلمة المرور؟'),
                        ),
                      ),
                      if (auth.error != null) ...[
                        Text(
                          auth.error!,
                          style: TextStyle(color: Theme.of(context).colorScheme.error),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 8),
                      ],
                      FilledButton(
                        onPressed: auth.loading ? null : _submit,
                        child: auth.loading
                            ? const SizedBox(
                                height: 22,
                                width: 22,
                                child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                              )
                            : const Text('دخول'),
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: (auth.loading || _googleBusy) ? null : _google,
                        icon: _googleBusy
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              )
                            : const Icon(Icons.g_mobiledata_rounded, size: 28),
                        label: const Text('الدخول عبر Google'),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        AppConfig.appName,
                        textAlign: TextAlign.center,
                        style: TextStyle(fontSize: 12, color: Colors.grey.shade500),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
