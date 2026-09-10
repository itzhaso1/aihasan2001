import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim_finance/features/catalog/catalog_screens.dart';
import 'package:hasim_finance/features/dashboard/dashboard_screen.dart';
import 'package:hasim_finance/features/hubs/hub_screens.dart';
import 'package:hasim_finance/features/invoices/invoices_screens.dart';
import 'package:hasim_finance/l10n/app_localizations.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'helpers.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late SharedPreferences prefs;
  late FakeFinanceApi api;

  setUp(() async {
    prefs = await mockPrefs();
    api = FakeFinanceApi(testClient(prefs));
  });

  Future<void> pumpScreen(WidgetTester tester, Widget child) async {
    tester.view.physicalSize = const Size(1400, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    await tester.pumpWidget(financeHarness(
      overrides: financeOverrides(prefs: prefs, api: api),
      child: child,
    ));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
  }

  testWidgets('sales hub shows server summary totals', (tester) async {
    await pumpScreen(tester, const SalesHubScreen());
    expect(find.text('المبيعات'), findsWidgets);
    expect(find.text('115.00'), findsWidgets);
    expect(find.text('INV-11'), findsOneWidget);
  });

  testWidgets('product list and detail show catalog fields', (tester) async {
    await pumpScreen(tester, const ProductsScreen());
    expect(find.text('خدمة فوترة'), findsOneWidget);
    await pumpScreen(tester, const ProductDetailScreen(id: 1));
    expect(find.text('SKU-1'), findsOneWidget);
  });

  testWidgets('supplier detail shows VAT from the API', (tester) async {
    await pumpScreen(tester, const SupplierDetailScreen(id: 3));
    expect(find.text('مورد الاختبار'), findsWidgets);
    expect(find.text('300000000000003'), findsOneWidget);
  });

  testWidgets('invoice compose sends tax, walk-in optional customer, and no line totals', (tester) async {
    tester.view.physicalSize = const Size(1400, 2400);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ProviderScope(
        overrides: financeOverrides(prefs: prefs, api: api),
        child: MaterialApp.router(
          locale: const Locale('ar'),
          supportedLocales: AppLocalizations.supportedLocales,
          localizationsDelegates: const [
            AppLocalizations.delegate,
            GlobalMaterialLocalizations.delegate,
            GlobalWidgetsLocalizations.delegate,
            GlobalCupertinoLocalizations.delegate,
          ],
          routerConfig: GoRouter(
            initialLocation: '/invoices/new',
            routes: [
              GoRoute(path: '/invoices/new', builder: (_, _) => const InvoiceFormScreen()),
              GoRoute(path: '/invoices/:id', builder: (_, state) => Text('saved-${state.pathParameters['id']}')),
            ],
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 80));

    await tester.tap(find.text('عميل الاختبار').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('حفظ'));
    await tester.pumpAndSettle();

    expect(api.lastInvoicePayload, isNotNull);
    expect(api.lastInvoicePayload!['customer_id'], 7);
    expect(api.lastInvoicePayload!['tax_document_subtype'], 'standard');
    expect(api.lastInvoicePayload!['items'], isNotEmpty);
    expect((api.lastInvoicePayload!['items'] as List).first.containsKey('total'), isFalse);
    expect(find.text('saved-99'), findsOneWidget);
  });

  testWidgets('alerts and copilot screens render server content', (tester) async {
    await pumpScreen(tester, const AlertsScreen());
    expect(find.text('فواتير متأخرة'), findsOneWidget);
    await pumpScreen(tester, const CopilotScreen());
    expect(find.textContaining('المساعد'), findsWidgets);
  });

  testWidgets('dashboard analytics hero and attention are shown', (tester) async {
    await pumpScreen(tester, const DashboardScreen());
    expect(find.text('كم بعنا؟'), findsOneWidget);
    expect(find.text('تحصيل متأخر'), findsOneWidget);
    expect(find.text('تطبيق التصفية'), findsOneWidget);
  });
}
