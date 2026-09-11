import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/theme/app_theme.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';

/// ملصق بداية حاسم شات — أبيض + تيل الهوية بدون تعتيم فوق الألوان.
const hasimSplashAsset = 'assets/branding/hasim_splash.png';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});

  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen> {
  bool _navigated = false;
  late final DateTime _started = DateTime.now();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      precacheImage(const AssetImage(hasimSplashAsset), context);
      _maybeNavigate();
    });
  }

  Future<void> _maybeNavigate() async {
    if (_navigated || !mounted) return;
    final auth = ref.read(authControllerProvider);
    if (auth.bootstrapping) return;

    final elapsed = DateTime.now().difference(_started);
    const min = Duration(milliseconds: 1800);
    if (elapsed < min) {
      await Future<void>.delayed(min - elapsed);
    }
    if (!mounted || _navigated) return;
    _navigated = true;

    final next = ref.read(authControllerProvider);
    if (!next.isAuthenticated) {
      context.go('/login');
    } else if (next.workspace == null) {
      context.go('/workspaces');
    } else {
      context.go('/conversations');
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(authControllerProvider, (prev, next) {
      if (prev?.bootstrapping == true && !next.bootstrapping) {
        _maybeNavigate();
      }
    });

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        statusBarBrightness: Brightness.light,
        systemNavigationBarColor: Colors.white,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: Colors.white,
        body: Stack(
          fit: StackFit.expand,
          children: [
            const DecoratedBox(
              decoration: BoxDecoration(
                gradient: RadialGradient(
                  center: Alignment.center,
                  radius: 0.92,
                  colors: [Color(0x4D06C2A4), Color(0x1A06C2A4), Colors.white],
                  stops: [0.0, 0.42, 1.0],
                ),
              ),
            ),
            Positioned.fill(
              child: SafeArea(
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 8,
                  ),
                  child: Image.asset(
                    hasimSplashAsset,
                    key: const Key('hasim-splash-art'),
                    fit: BoxFit.contain,
                    width: double.infinity,
                    height: double.infinity,
                    alignment: Alignment.center,
                    filterQuality: FilterQuality.high,
                    gaplessPlayback: true,
                    errorBuilder: (_, _, _) => const _SplashColorFallback(),
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

class _SplashColorFallback extends StatelessWidget {
  const _SplashColorFallback();

  @override
  Widget build(BuildContext context) {
    return Container(
      alignment: Alignment.center,
      decoration: const BoxDecoration(
        gradient: RadialGradient(
          colors: [Color(0xFF06C2A4), Color(0xFF067E6B), Colors.white],
          radius: 1.05,
        ),
      ),
      child: Text(
        'حاسم شات',
        style: Theme.of(context).textTheme.headlineMedium?.copyWith(
          fontWeight: FontWeight.w800,
          color: AppTheme.brandDark,
        ),
      ),
    );
  }
}
