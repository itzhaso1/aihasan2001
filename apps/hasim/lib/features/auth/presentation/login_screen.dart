import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:hasim/core/theme/app_theme.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';

const hasimLoginArtAsset = 'assets/branding/hasim_login.png';

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

  static const _ink = Color(0xFF1A2B28);
  static const _muted = Color(0xFF8A9A96);
  static const _fieldBorder = Color(0xFFE7EFEC);

  @override
  void dispose() {
    _idController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final ok = await ref
        .read(authControllerProvider.notifier)
        .login(_idController.text.trim(), _passwordController.text);
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
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(const SnackBar(content: Text('يحتاج إعداد Google')));
        }
        return;
      }
      final ok = await ref
          .read(authControllerProvider.notifier)
          .socialLogin(provider: 'google', accessToken: token);
      if (!mounted) return;
      if (ok) {
        final state = ref.read(authControllerProvider);
        context.go(state.workspace == null ? '/workspaces' : '/conversations');
      }
    } catch (e) {
      if (!mounted) return;
      final msg = e.toString().toLowerCase();
      final needsSetup =
          msg.contains('client') ||
          msg.contains('platform') ||
          msg.contains('missing') ||
          msg.contains('not been configured') ||
          msg.contains('sign_in_failed') ||
          msg.contains('10:') ||
          msg.contains('12500');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            needsSetup ? 'يحتاج إعداد Google' : 'تعذر تسجيل الدخول عبر Google.',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _googleBusy = false);
    }
  }

  InputDecoration _fieldDecoration({
    required String hint,
    required IconData leading,
    Widget? trailing,
  }) {
    final radius = BorderRadius.circular(18);
    return InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(
        color: _muted,
        fontSize: 14,
        fontWeight: FontWeight.w500,
      ),
      prefixIcon: Icon(leading, color: _muted, size: 22),
      suffixIcon: trailing,
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
      border: OutlineInputBorder(
        borderRadius: radius,
        borderSide: BorderSide.none,
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: radius,
        borderSide: const BorderSide(color: _fieldBorder),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: radius,
        borderSide: BorderSide(color: AppTheme.brand.withValues(alpha: 0.55)),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: radius,
        borderSide: const BorderSide(color: Color(0xFFE57373)),
      ),
      focusedErrorBorder: OutlineInputBorder(
        borderRadius: radius,
        borderSide: const BorderSide(color: Color(0xFFE57373)),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authControllerProvider);
    final theme = Theme.of(context);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        statusBarBrightness: Brightness.light,
      ),
      child: Scaffold(
        backgroundColor: const Color(0xFFF3FCFA),
        body: Stack(
          fit: StackFit.expand,
          children: [
            const Positioned.fill(
              child: IgnorePointer(
                child: CustomPaint(painter: _LoginShapesPainter()),
              ),
            ),
            SafeArea(
              child: Center(
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 420),
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(24, 12, 24, 28),
                    child: Form(
                      key: _formKey,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          const SizedBox(height: 8),
                          Image.asset(
                            hasimLoginArtAsset,
                            key: const Key('hasim-login-art'),
                            height: 168,
                            fit: BoxFit.contain,
                            filterQuality: FilterQuality.high,
                          ),
                          const SizedBox(height: 28),
                          Text(
                            'مرحباً بك مجدداً',
                            textAlign: TextAlign.center,
                            style: theme.textTheme.headlineSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                              fontSize: 26,
                              color: _ink,
                              height: 1.2,
                            ),
                          ),
                          const SizedBox(height: 8),
                          const Text(
                            'سجّل دخولك إلى حسابك لمتابعة أعمالك',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: _muted,
                              fontSize: 14,
                              fontWeight: FontWeight.w500,
                              height: 1.45,
                            ),
                          ),
                          const SizedBox(height: 28),
                          TextFormField(
                            controller: _idController,
                            decoration: _fieldDecoration(
                              hint: 'البريد الإلكتروني أو الجوال',
                              leading: Icons.person_outline,
                            ),
                            keyboardType: TextInputType.emailAddress,
                            validator: (v) => (v == null || v.trim().isEmpty)
                                ? 'مطلوب'
                                : null,
                          ),
                          const SizedBox(height: 12),
                          TextFormField(
                            controller: _passwordController,
                            obscureText: _obscure,
                            decoration: _fieldDecoration(
                              hint: 'كلمة المرور',
                              leading: Icons.lock_outline,
                              trailing: IconButton(
                                onPressed: () =>
                                    setState(() => _obscure = !_obscure),
                                icon: Icon(
                                  _obscure
                                      ? Icons.visibility_outlined
                                      : Icons.visibility_off_outlined,
                                  color: _muted,
                                ),
                              ),
                            ),
                            validator: (v) =>
                                (v == null || v.isEmpty) ? 'مطلوب' : null,
                          ),
                          Align(
                            alignment: AlignmentDirectional.centerEnd,
                            child: TextButton(
                              onPressed: () => context.push('/forgot-password'),
                              style: TextButton.styleFrom(
                                foregroundColor: AppTheme.brand,
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 4,
                                  vertical: 8,
                                ),
                              ),
                              child: const Text(
                                'نسيت كلمة المرور؟',
                                style: TextStyle(
                                  fontWeight: FontWeight.w700,
                                  fontSize: 13.5,
                                ),
                              ),
                            ),
                          ),
                          if (auth.error != null) ...[
                            Text(
                              auth.error!,
                              style: TextStyle(color: theme.colorScheme.error),
                              textAlign: TextAlign.center,
                            ),
                            const SizedBox(height: 8),
                          ],
                          FilledButton(
                            onPressed: auth.loading ? null : _submit,
                            style: FilledButton.styleFrom(
                              backgroundColor: AppTheme.brand,
                              foregroundColor: Colors.white,
                              minimumSize: const Size.fromHeight(52),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(16),
                              ),
                              textStyle: const TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            child: auth.loading
                                ? const SizedBox(
                                    height: 22,
                                    width: 22,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: Colors.white,
                                    ),
                                  )
                                : const Text('دخول'),
                          ),
                          const SizedBox(height: 22),
                          const Row(
                            children: [
                              Expanded(
                                child: Divider(color: Color(0xFFD8E4E0)),
                              ),
                              Padding(
                                padding: EdgeInsets.symmetric(horizontal: 12),
                                child: Text(
                                  'أو',
                                  style: TextStyle(
                                    color: _muted,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                              Expanded(
                                child: Divider(color: Color(0xFFD8E4E0)),
                              ),
                            ],
                          ),
                          const SizedBox(height: 18),
                          OutlinedButton(
                            onPressed: (auth.loading || _googleBusy)
                                ? null
                                : _google,
                            style: OutlinedButton.styleFrom(
                              backgroundColor: Colors.white,
                              foregroundColor: _ink,
                              minimumSize: const Size.fromHeight(52),
                              side: const BorderSide(color: _fieldBorder),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(16),
                              ),
                            ),
                            child: _googleBusy
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Row(
                                    mainAxisAlignment: MainAxisAlignment.center,
                                    children: [
                                      _GoogleMark(),
                                      SizedBox(width: 10),
                                      Text(
                                        'الدخول عبر Google',
                                        style: TextStyle(
                                          fontWeight: FontWeight.w700,
                                          fontSize: 15,
                                        ),
                                      ),
                                    ],
                                  ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LoginShapesPainter extends CustomPainter {
  const _LoginShapesPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final mint = Paint()..color = const Color(0xFFD7F0EA);
    final mintSoft = Paint()..color = const Color(0xFFE8F6F2);

    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(-36, 72, 120, 120),
        const Radius.circular(28),
      ),
      mint,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(28, 36, 86, 86),
        const Radius.circular(22),
      ),
      mintSoft,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(size.width - 70, 90, 110, 110),
        const Radius.circular(26),
      ),
      mintSoft,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(-28, size.height - 160, 130, 130),
        const Radius.circular(30),
      ),
      mintSoft,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(size.width - 90, size.height - 140, 140, 140),
        const Radius.circular(32),
      ),
      mint,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _GoogleMark extends StatelessWidget {
  const _GoogleMark();

  @override
  Widget build(BuildContext context) {
    return const SizedBox(
      width: 22,
      height: 22,
      child: CustomPaint(painter: _GoogleLogoPainter()),
    );
  }
}

class _GoogleLogoPainter extends CustomPainter {
  const _GoogleLogoPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final s = size.shortestSide;
    final c = Offset(s / 2, s / 2);
    final stroke = s * 0.18;
    final rect = Rect.fromCircle(center: c, radius: s / 2 - stroke / 2);
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.butt;

    paint.color = const Color(0xFFEA4335);
    canvas.drawArc(rect, -1.05, 1.35, false, paint);
    paint.color = const Color(0xFFFBBC05);
    canvas.drawArc(rect, 0.28, 1.15, false, paint);
    paint.color = const Color(0xFF34A853);
    canvas.drawArc(rect, 1.45, 1.2, false, paint);
    paint.color = const Color(0xFF4285F4);
    canvas.drawArc(rect, 2.7, 1.55, false, paint);

    final bar = Paint()..color = const Color(0xFF4285F4);
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        Rect.fromLTWH(c.dx, c.dy - stroke / 2, s / 2 - stroke / 3, stroke),
        Radius.circular(stroke / 4),
      ),
      bar,
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
