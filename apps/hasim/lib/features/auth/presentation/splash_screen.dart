import 'package:flutter/material.dart';
import 'package:flutter_animate/flutter_animate.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/widgets/hasim_logo.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';

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
    WidgetsBinding.instance.addPostFrameCallback((_) => _maybeNavigate());
  }

  Future<void> _maybeNavigate() async {
    if (_navigated || !mounted) return;
    final auth = ref.read(authControllerProvider);
    if (auth.bootstrapping) return;

    final elapsed = DateTime.now().difference(_started);
    const min = Duration(milliseconds: 900);
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

    return Scaffold(
      body: Stack(
        fit: StackFit.expand,
        children: [
          Image.asset(
            'assets/branding/splash_bg.png',
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Color(0xFF067E6B), Color(0xFF06C2A4), Color(0xFFF5FAF8)],
                ),
              ),
            ),
          ),
          Container(color: Colors.black.withValues(alpha: 0.18)),
          Center(
            child: const HasimLogo(size: 128)
                .animate()
                .fadeIn(duration: 800.ms, curve: Curves.easeOut)
                .scale(
                  begin: const Offset(0.86, 0.86),
                  end: const Offset(1, 1),
                  duration: 1000.ms,
                  curve: Curves.easeOutCubic,
                ),
          ),
        ],
      ),
    );
  }
}
