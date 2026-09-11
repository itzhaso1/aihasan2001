import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hasim/app.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/storage/secure_store.dart';
import 'package:hasim/core/theme/app_theme.dart';
import 'package:hasim/features/auth/presentation/splash_screen.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _MemorySecureStore extends SecureStore {
  String? _token;
  @override
  Future<void> saveToken(String token) async => _token = token;
  @override
  Future<String?> readToken() async => _token;
  @override
  Future<void> clearToken() async => _token = null;
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  testWidgets('splash shows the HASIM poster without a dark overlay', (
    tester,
  ) async {
    SharedPreferences.setMockInitialValues({});
    final prefs = await SharedPreferences.getInstance();
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          secureStoreProvider.overrideWithValue(_MemorySecureStore()),
          sharedPrefsProvider.overrideWithValue(prefs),
        ],
        child: const HasimApp(),
      ),
    );
    await tester.pump();

    expect(find.byType(SplashScreen), findsOneWidget);
    expect(find.byKey(const Key('hasim-splash-art')), findsOneWidget);

    final image = tester.widget<Image>(
      find.byKey(const Key('hasim-splash-art')),
    );
    expect(image.fit, BoxFit.contain);
    expect(image.image, isA<AssetImage>());
    expect((image.image as AssetImage).assetName, hasimSplashAsset);

    final scaffold = tester.widget<Scaffold>(find.byType(Scaffold));
    expect(scaffold.backgroundColor, Colors.white);
    expect(AppTheme.brand, const Color(0xFF06C2A4));

    await tester.pump(const Duration(milliseconds: 2000));
    await tester.pumpAndSettle();
  });
}
