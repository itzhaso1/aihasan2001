import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/storage/secure_store.dart';
import 'package:hasim/features/auth/presentation/login_screen.dart';
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

  testWidgets('login shows HASIM Chat branding art', (tester) async {
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
        child: const MaterialApp(home: LoginScreen()),
      ),
    );
    await tester.pump();

    expect(find.byKey(const Key('hasim-login-art')), findsOneWidget);
    final image = tester.widget<Image>(
      find.byKey(const Key('hasim-login-art')),
    );
    expect(image.fit, BoxFit.contain);
    expect((image.image as AssetImage).assetName, hasimLoginArtAsset);
    expect(find.text('دخول'), findsOneWidget);
    expect(find.text('إدارة المحادثات والحجوزات'), findsNothing);
  });
}
