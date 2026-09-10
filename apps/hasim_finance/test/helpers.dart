import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:hasim_finance/core/api/finance_api.dart';
import 'package:hasim_finance/core/auth/auth_controller.dart';
import 'package:hasim_finance/core/auth/google_auth.dart';
import 'package:hasim_finance/core/models/models.dart';
import 'package:hasim_finance/core/network/api_client.dart';
import 'package:hasim_finance/core/network/api_exception.dart';
import 'package:hasim_finance/core/permissions/finance_permissions.dart';
import 'package:hasim_finance/core/storage/prefs_store.dart';
import 'package:hasim_finance/core/storage/secure_store.dart';
import 'package:hasim_finance/l10n/app_localizations.dart';
import 'package:shared_preferences/shared_preferences.dart';

class FakeGoogleSource implements GoogleAccessTokenSource {
  FakeGoogleSource({this.token = 'ya29.test', this.error, this.delay});

  String token;
  Object? error;
  Duration? delay;
  var called = false;

  @override
  Future<String> obtainAccessToken() async {
    called = true;
    final wait = delay;
    if (wait != null) {
      await Future<void>.delayed(wait);
    }
    final thrown = error;
    if (thrown != null) throw thrown;
    return token;
  }
}

class MemorySecureStore extends SecureStore {
  MemorySecureStore() : super();

  String? _token;

  @override
  Future<void> saveToken(String token) async => _token = token;

  @override
  Future<String?> readToken() async => _token;

  @override
  Future<void> clearToken() async => _token = null;
}

class SeededAuthController extends AuthController {
  SeededAuthController({this.allowAll = true, this.workspaces});

  final bool allowAll;
  final List<WorkspaceInfo>? workspaces;

  static const ownerPerms = {
    'finance.view': true,
    'customers.view': true,
    'customers.create': true,
    'customers.edit': true,
    'quotes.view': true,
    'quotes.create': true,
    'quotes.edit': true,
    'quotes.issue': true,
    'quotes.send': true,
    'quotes.accept': true,
    'quotes.reject': true,
    'quotes.convert': true,
    'quotes.cancel': true,
    'invoices.view': true,
    'invoices.create': true,
    'invoices.edit': true,
    'invoices.issue': true,
    'invoices.send': true,
    'invoices.remind': true,
    'invoices.cancel': true,
    'invoices.reverse_payment': true,
    'payments.view': true,
    'payments.manage': true,
    'receipts.view': true,
    'receipts.send': true,
    'statements.view': true,
    'notes.view': true,
    'contracts.view': true,
    'expenses.view': true,
    'purchases.view': true,
    'reports.view': true,
  };

  @override
  AuthState build() {
    final list = workspaces ??
        const [WorkspaceInfo(id: 1, name: 'شركة الاختبار', financeEnabled: true)];
    return AuthState(
      bootstrapping: false,
      user: const AuthUser(id: 1, name: 'Owner', email: 'owner@example.com'),
      workspace: list.first,
      workspaces: list,
      permissions: FinancePermissions(allowAll ? ownerPerms : const {'finance.view': false}),
      financeEnabled: true,
    );
  }
}

class FakeFinanceApi extends FinanceApi {
  FakeFinanceApi(super.client);

  DashboardData dashboardData = DashboardData(
    cards: const {
      'outstanding_customer_balance': '115.00',
      'invoices_due': '1',
      'overdue_invoices': '0',
      'paid_this_period': '0.00',
      'sales': '115.00',
      'expenses': '0.00',
      'receivables': '115.00',
      'payables': '0.00',
    },
    recentInvoices: [
      InvoiceRecord(
        id: 11,
        invoiceNumber: 'INV-11',
        customerName: 'عميل الاختبار',
        total: '115.00',
        amountDue: '115.00',
        documentStatus: 'issued',
        paymentStatus: 'unpaid',
      ),
    ],
    recentPayments: const [],
    overdueInvoices: const [],
  );

  CustomerRecord customerRecord = CustomerRecord(
    id: 7,
    name: 'عميل الاختبار',
    outstandingBalance: '115.00',
    partyType: 'company',
    email: 'buyer@example.com',
    phone: '0500000000',
    vatNumber: '300111111111113',
    invoices: [
      InvoiceRecord(id: 11, invoiceNumber: 'INV-11', total: '115.00', documentStatus: 'issued', paymentStatus: 'unpaid'),
    ],
  );

  InvoiceRecord invoiceRecord = InvoiceRecord(
    id: 11,
    invoiceNumber: 'INV-11',
    customerId: 7,
    customerName: 'عميل الاختبار',
    subtotal: '100.00',
    taxAmount: '15.00',
    total: '115.00',
    amountPaid: '0.00',
    amountDue: '115.00',
    documentStatus: 'issued',
    paymentStatus: 'unpaid',
    deliveryStatus: 'pending',
    lines: const [
      LineItem(productName: 'خدمة فوترة', quantity: '1', unitPrice: '100.00', taxAmount: '15.00', total: '115.00'),
    ],
  );

  QuoteRecord quoteRecord = QuoteRecord(
    id: 5,
    quoteNumber: 'Q-5',
    customerName: 'عميل الاختبار',
    subtotal: '100.00',
    taxAmount: '15.00',
    total: '115.00',
    documentStatus: 'issued',
    outcome: 'pending',
    deliveryStatus: 'sent',
    lines: const [
      LineItem(productName: 'خدمة عرض', quantity: '1', unitPrice: '100.00', total: '115.00'),
    ],
  );

  ReceiptRecord receiptRecord = ReceiptRecord(
    id: 3,
    receiptNumber: 'R-3',
    invoiceNumber: 'INV-11',
    customerName: 'عميل الاختبار',
    amount: '115.00',
    status: 'posted',
  );

  PaymentRecord paymentRecord = const PaymentRecord(
    id: 9,
    invoiceId: 11,
    invoiceNumber: 'INV-11',
    customerName: 'عميل الاختبار',
    amount: '115.00',
    method: 'cash',
    status: 'posted',
    receiptNumber: 'R-3',
  );

  bool loggedIn = false;
  bool loggedOut = false;
  String? lastSocialToken;
  String? lastForgotEmail;
  String? lastResetEmail;
  ApiException? socialError;
  bool financeEnabledFlag = true;
  List<WorkspaceInfo> sessionWorkspaces = const [
    WorkspaceInfo(id: 1, name: 'شركة الاختبار', financeEnabled: true),
  ];

  SessionPayload sessionPayload() {
    return SessionPayload(
      token: 'sanctum-token',
      user: const AuthUser(id: 1, name: 'Owner', email: 'owner@example.com'),
      workspace: sessionWorkspaces.first,
      workspaces: sessionWorkspaces,
      permissions: SeededAuthController.ownerPerms,
      financeEnabled: financeEnabledFlag,
    );
  }

  @override
  Future<SessionPayload> login({required String emailOrPhone, required String password}) async {
    if (password != 'password') {
      throw ApiException('بيانات الدخول غير صحيحة.', statusCode: 401);
    }
    loggedIn = true;
    return sessionPayload();
  }

  @override
  Future<SessionPayload> socialLogin({required String accessToken, int? workspaceId}) async {
    lastSocialToken = accessToken;
    final error = socialError;
    if (error != null) throw error;
    loggedIn = true;
    return sessionPayload();
  }

  bool meThrowsUnauthorized = false;

  @override
  Future<SessionPayload> me() async {
    if (meThrowsUnauthorized) {
      throw ApiException('انتهت جلسة تسجيل الدخول.', statusCode: 401);
    }
    return sessionPayload();
  }

  @override
  Future<void> switchWorkspace(int workspaceId) async {}

  @override
  Future<void> logout() async {
    loggedOut = true;
  }

  @override
  Future<Map<String, dynamic>> bootstrap() async => {};

  @override
  Future<String> forgotPassword(String email) async {
    lastForgotEmail = email;
    return 'تم إرسال رابط إعادة تعيين كلمة المرور.';
  }

  @override
  Future<String> resetPassword({
    required String email,
    required String token,
    required String password,
    required String passwordConfirmation,
  }) async {
    lastResetEmail = email;
    return 'تم إعادة تعيين كلمة المرور بنجاح.';
  }

  @override
  Future<DashboardData> dashboard() async => dashboardData;

  List<CustomerRecord> catalogCustomers = [];

  @override
  Future<PagedResult<CustomerRecord>> customers({String? search, int page = 1}) async {
    var items = catalogCustomers.isEmpty ? [customerRecord] : List<CustomerRecord>.from(catalogCustomers);
    if (search != null && search.isNotEmpty) {
      items = items.where((row) => row.name.contains(search)).toList();
    }
    const perPage = 25;
    final lastPage = items.isEmpty ? 1 : ((items.length + perPage - 1) / perPage).floor();
    final start = (page - 1) * perPage;
    final slice = items.skip(start).take(perPage).toList();
    return PagedResult(items: slice, page: page, lastPage: lastPage, total: items.length);
  }

  @override
  Future<CustomerRecord> customer(int id) async => customerRecord;

  @override
  Future<PagedResult<InvoiceRecord>> invoices({String? search, String? paymentStatus, int page = 1}) async {
    return PagedResult(items: [invoiceRecord], page: 1, lastPage: 1, total: 1);
  }

  @override
  Future<InvoiceRecord> invoice(int id) async => invoiceRecord;

  @override
  Future<Map<String, dynamic>> recordPayment(int invoiceId, Map<String, dynamic> body) async {
    invoiceRecord = InvoiceRecord(
      id: invoiceRecord.id,
      invoiceNumber: invoiceRecord.invoiceNumber,
      customerId: invoiceRecord.customerId,
      customerName: invoiceRecord.customerName,
      subtotal: invoiceRecord.subtotal,
      taxAmount: invoiceRecord.taxAmount,
      total: invoiceRecord.total,
      amountPaid: invoiceRecord.total,
      amountDue: '0.00',
      documentStatus: 'issued',
      paymentStatus: 'paid',
      lines: invoiceRecord.lines,
      payments: [paymentRecord],
      receipts: [receiptRecord],
    );
    return {
      'invoice': {'payment_status': 'paid'},
      'payment': {'status': 'posted', 'id': 9},
    };
  }

  @override
  Future<PagedResult<QuoteRecord>> quotes({String? search, String? status, int page = 1}) async {
    return PagedResult(items: [quoteRecord], page: 1, lastPage: 1, total: 1);
  }

  @override
  Future<QuoteRecord> quote(int id) async => quoteRecord;

  Map<String, dynamic>? lastQuotePayload;

  @override
  Future<QuoteRecord> saveQuote(Map<String, dynamic> body, {int? id}) async {
    lastQuotePayload = body;
    return QuoteRecord(
      id: id ?? 99,
      quoteNumber: 'Q-99',
      customerId: body['customer_id'] as int?,
      documentStatus: 'draft',
      outcome: 'pending',
    );
  }

  @override
  Future<QuoteRecord> quoteAction(int id, String action, {Map<String, dynamic>? body}) async {
    if (action == 'convert') {
      quoteRecord = QuoteRecord(
        id: quoteRecord.id,
        quoteNumber: quoteRecord.quoteNumber,
        customerName: quoteRecord.customerName,
        documentStatus: 'issued',
        outcome: 'converted',
        convertedInvoiceId: 11,
        convertedInvoiceNumber: 'INV-11',
        lines: quoteRecord.lines,
      );
    }
    return quoteRecord;
  }

  @override
  Future<PagedResult<ReceiptRecord>> receipts({String? search, int page = 1}) async {
    return PagedResult(items: [receiptRecord], page: 1, lastPage: 1, total: 1);
  }

  @override
  Future<ReceiptRecord> receipt(int id) async => receiptRecord;

  @override
  Future<PagedResult<PaymentRecord>> payments({String? search, int page = 1}) async {
    return PagedResult(items: [paymentRecord], page: 1, lastPage: 1, total: 1);
  }

  @override
  Future<PaymentRecord> payment(int id) async => paymentRecord;

  StatementRecord statementRecord = const StatementRecord(
    openingBalance: '0.00',
    closingBalance: '65.00',
    invoicesTotal: '115.00',
    paymentsTotal: '50.00',
    creditsTotal: '0.00',
    debitsTotal: '0.00',
    customerName: 'عميل الاختبار',
    customerId: 7,
    from: '2026-09-01',
    to: '2026-09-30',
    lines: [
      {
        'date': '2026-09-10',
        'kind': 'invoice',
        'reference': 'PARITY INV 001',
        'description': 'فاتورة مبيعات',
        'debit': '115.00',
        'credit': '0.00',
        'balance': '115.00',
        'invoice_id': 11,
      },
      {
        'date': '2026-09-12',
        'kind': 'payment',
        'reference': 'PAY-9',
        'description': 'دفعة جزئية',
        'debit': '0.00',
        'credit': '50.00',
        'balance': '65.00',
        'invoice_id': 11,
      },
    ],
  );

  @override
  Future<StatementRecord> statement({required int customerId, required String from, required String to}) async {
    return statementRecord;
  }

  Map<String, dynamic> reportPayload = {
    'report': 'profit-loss',
    'from': '2026-09-01',
    'to': '2026-09-30',
    'profit_and_loss': {
      'revenue': '1000.00',
      'cogs': '200.00',
      'gross_profit': '800.00',
      'net_profit': '500.00',
      'rows': [
        {'code': '4000', 'name': 'إيرادات', 'type': 'revenue', 'balance': '1000.00'},
      ],
    },
  };

  @override
  Future<Map<String, dynamic>> report(String key, {String? from, String? to, int? accountId}) async {
    return reportPayload;
  }

  @override
  Future<PagedResult<SupplierRecord>> suppliers({String? search, int page = 1}) async {
    return const PagedResult(items: [SupplierRecord(id: 3, name: 'مورد الاختبار')], page: 1, lastPage: 1, total: 1);
  }
}

Future<SharedPreferences> mockPrefs() async {
  SharedPreferences.setMockInitialValues({});
  return SharedPreferences.getInstance();
}

ApiClient testClient(SharedPreferences prefs) {
  return ApiClient(
    secureStore: MemorySecureStore(),
    prefsStore: PrefsStore(prefs),
  );
}

List<Override> financeOverrides({
  required SharedPreferences prefs,
  required FakeFinanceApi api,
  bool allowAll = true,
  GoogleAccessTokenSource? google,
  SecureStore? secureStore,
  List<WorkspaceInfo>? workspaces,
}) {
  return [
    sharedPrefsProvider.overrideWithValue(prefs),
    financeApiProvider.overrideWithValue(api),
    authControllerProvider.overrideWith(() => SeededAuthController(allowAll: allowAll, workspaces: workspaces)),
    if (google != null) googleAccessTokenSourceProvider.overrideWithValue(google),
    if (secureStore != null) secureStoreProvider.overrideWithValue(secureStore),
  ];
}

Widget financeHarness({
  required List<Override> overrides,
  required Widget child,
}) {
  return ProviderScope(
    overrides: overrides,
    child: MaterialApp(
      locale: const Locale('ar'),
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        GlobalMaterialLocalizations.delegate,
        GlobalWidgetsLocalizations.delegate,
        GlobalCupertinoLocalizations.delegate,
      ],
      home: child,
    ),
  );
}
