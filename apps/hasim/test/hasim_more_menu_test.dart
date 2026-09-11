import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/network/api_client.dart';
import 'package:hasim/core/storage/prefs_store.dart';
import 'package:hasim/core/storage/secure_store.dart';
import 'package:hasim/core/theme/app_theme.dart';
import 'package:hasim/core/widgets/hasim_more_menu.dart';
import 'package:hasim/core/widgets/hasim_shell_header.dart';
import 'package:hasim/realtime/realtime_service.dart';
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
  GoogleFonts.config.allowRuntimeFetching = false;

  late SharedPreferences prefs;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    prefs = await SharedPreferences.getInstance();
  });

  Widget app({GoRouter? router}) {
    final go = router ??
        GoRouter(
          routes: [
            GoRoute(
              path: '/',
              builder: (context, state) => const Scaffold(
                appBar: HasimShellHeader(),
                body: Text('محتوى المحادثات'),
              ),
            ),
            GoRoute(path: '/contact-groups', builder: (_, _) => const Scaffold(body: Text('شاشة المجموعات'))),
            GoRoute(path: '/channels', builder: (_, _) => const Scaffold(body: Text('شاشة القنوات'))),
            GoRoute(path: '/contacts', builder: (_, _) => const Scaffold(body: Text('شاشة جهات الاتصال'))),
            GoRoute(path: '/more/security', builder: (_, _) => const Scaffold(body: Text('شاشة الأجهزة'))),
            GoRoute(path: '/settings', builder: (_, _) => const Scaffold(body: Text('شاشة الإعدادات'))),
            GoRoute(path: '/profile', builder: (_, _) => const Scaffold(body: Text('الملف'))),
          ],
        );

    return ProviderScope(
      overrides: [
        sharedPrefsProvider.overrideWithValue(prefs),
        secureStoreProvider.overrideWithValue(_MemorySecureStore()),
        realtimeServiceProvider.overrideWithValue(NoopRealtimeService()),
        apiClientProvider.overrideWithValue(
          ApiClient(secureStore: _MemorySecureStore(), prefsStore: PrefsStore(prefs)),
        ),
      ],
      child: MaterialApp.router(
        theme: AppTheme.light(),
        locale: const Locale('ar'),
        builder: (context, child) => Directionality(
          textDirection: TextDirection.rtl,
          child: child ?? const SizedBox.shrink(),
        ),
        routerConfig: go,
      ),
    );
  }

  testWidgets('more button opens a floating popup with HASIM destinations', (tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(app());
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.byIcon(Icons.more_vert), findsOneWidget);
    await tester.tap(find.byKey(const Key('hasim-more-btn')));
    await tester.pumpAndSettle();

    expect(find.text('مجموعة جديدة'), findsOneWidget);
    expect(find.text('مجتمعات'), findsOneWidget);
    expect(find.text('الرسائل المميزة بنجمة'), findsOneWidget);
    expect(find.text('الأجهزة المرتبطة'), findsOneWidget);
    expect(find.text('الإعدادات'), findsOneWidget);
    expect(find.text('تسجيل الخروج'), findsNothing);
    expect(find.byType(BottomSheet), findsNothing);

    final button = tester.getRect(find.byKey(const Key('hasim-more-btn')));
    final menu = tester.getRect(find.text('مجموعة جديدة'));
    expect(button.center.dx, lessThan(tester.view.physicalSize.width / 2));
    expect(menu.top, greaterThan(button.top));
    expect(menu.left, lessThan(tester.view.physicalSize.width / 2));
  });

  testWidgets('tapping outside closes the popup', (tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(app());
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    await tester.tap(find.byKey(const Key('hasim-more-btn')));
    await tester.pumpAndSettle();
    expect(find.text('الإعدادات'), findsOneWidget);

    await tester.tapAt(const Offset(300, 500));
    await tester.pumpAndSettle();
    expect(find.text('الإعدادات'), findsNothing);
    expect(find.text('محتوى المحادثات'), findsOneWidget);
  });

  testWidgets('settings item navigates to existing settings route', (tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(app());
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    await tester.tap(find.byKey(const Key('hasim-more-btn')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('الإعدادات'));
    await tester.pumpAndSettle();
    expect(find.text('شاشة الإعدادات'), findsOneWidget);
  });

  testWidgets('new group item navigates to contact groups', (tester) async {
    tester.view.physicalSize = const Size(390, 844);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(app());
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    await tester.tap(find.byKey(const Key('hasim-more-btn')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('مجموعة جديدة'));
    await tester.pumpAndSettle();
    expect(find.text('شاشة المجموعات'), findsOneWidget);
  });

  test('menu items stay mapped to existing HASIM destinations', () {
    expect(hasimMoreMenuItems.map((e) => e.id).toList(), [
      'new_group',
      'communities',
      'starred',
      'devices',
      'settings',
    ]);
  });
}
