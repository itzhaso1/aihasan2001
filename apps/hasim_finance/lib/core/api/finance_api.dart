import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:hasim_finance/core/models/models.dart';
import 'package:hasim_finance/core/network/api_client.dart';
import 'package:hasim_finance/core/network/api_response.dart';

class FinanceApi {
  FinanceApi(this._client);

  final ApiClient _client;

  Future<SessionPayload> login({
    required String emailOrPhone,
    required String password,
  }) async {
    final res = await _client.post(
      'auth/login',
      body: {
        'email_or_phone': emailOrPhone,
        'password': password,
        'device_name': 'Hasim Finance',
        'device_type': 'finance',
      },
      mapData: (raw) => SessionPayload.fromJson(Map<String, dynamic>.from(raw as Map)),
    );
    return res.data!;
  }

  Future<SessionPayload> me() async {
    final res = await _client.get(
      'auth/me',
      mapData: (raw) => SessionPayload.fromJson(Map<String, dynamic>.from(raw as Map)),
    );
    return res.data!;
  }

  Future<void> logout() => _client.post('auth/logout');

  Future<SessionPayload> socialLogin({required String accessToken, int? workspaceId}) async {
    final res = await _client.post(
      'auth/google',
      body: {
        'access_token': accessToken,
        'device_name': 'Hasim Finance',
        'device_type': 'finance',
        'workspace_id': ?workspaceId,
      },
      mapData: (raw) => SessionPayload.fromJson(Map<String, dynamic>.from(raw as Map)),
    );
    return res.data!;
  }

  Future<GoogleStartResult> googleStart() async {
    final res = await _client.post(
      'auth/google/start',
      mapData: (raw) => GoogleStartResult.fromJson(Map<String, dynamic>.from(raw as Map)),
    );
    return res.data!;
  }

  Future<GoogleStatusResult> googleStatus(String ticket) async {
    final res = await _client.get(
      'auth/google/status',
      query: {'ticket': ticket},
      mapData: (raw) => GoogleStatusResult.fromJson(Map<String, dynamic>.from(raw as Map)),
    );
    return res.data!;
  }

  Future<String> forgotPassword(String email) async {
    final res = await _client.post('auth/forgot-password', body: {'email': email});
    return res.message ?? '';
  }

  Future<String> resetPassword({
    required String email,
    required String token,
    required String password,
    required String passwordConfirmation,
  }) async {
    final res = await _client.post(
      'auth/reset-password',
      body: {
        'email': email,
        'token': token,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );
    return res.message ?? '';
  }

  Future<void> switchWorkspace(int workspaceId) async {
    await _client.post(
      'workspaces/switch',
      body: {'workspace_id': workspaceId, 'device_type': 'finance'},
    );
  }

  Future<DashboardData> dashboard() async {
    final res = await _client.get(
      'dashboard',
      mapData: (raw) => DashboardData.fromJson(Map<String, dynamic>.from(raw as Map)),
    );
    return res.data!;
  }

  Future<Map<String, dynamic>> bootstrap() async {
    final res = await _client.get('bootstrap');
    return Map<String, dynamic>.from(res.data as Map? ?? {});
  }

  Future<PagedResult<CustomerRecord>> customers({String? search, int page = 1}) {
    return _paged('customers', (raw) => CustomerRecord.fromJson(raw), search: search, page: page);
  }

  Future<CustomerRecord> customer(int id) async {
    final res = await _client.get('customers/$id', mapData: (raw) => CustomerRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<CustomerRecord> saveCustomer(Map<String, dynamic> body, {int? id}) async {
    final res = id == null
        ? await _client.post('customers', body: body, mapData: (raw) => CustomerRecord.fromJson(Map<String, dynamic>.from(raw as Map)))
        : await _client.put('customers/$id', body: body, mapData: (raw) => CustomerRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<PagedResult<QuoteRecord>> quotes({String? search, String? status, int page = 1}) {
    return _paged('quotes', (raw) => QuoteRecord.fromJson(raw), search: search, page: page, extra: {
      if (status != null && status.isNotEmpty) 'status': status,
    });
  }

  Future<QuoteRecord> quote(int id) async {
    final res = await _client.get('quotes/$id', mapData: (raw) => QuoteRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<QuoteRecord> saveQuote(Map<String, dynamic> body, {int? id}) async {
    final res = id == null
        ? await _client.post('quotes', body: body, mapData: (raw) => QuoteRecord.fromJson(Map<String, dynamic>.from(raw as Map)))
        : await _client.put('quotes/$id', body: body, mapData: (raw) => QuoteRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<QuoteRecord> quoteAction(int id, String action, {Map<String, dynamic>? body}) async {
    final res = await _client.post(
      'quotes/$id/$action',
      body: body,
      mapData: (raw) {
        final map = Map<String, dynamic>.from(raw as Map);
        if (map.containsKey('quote')) {
          return QuoteRecord.fromJson(Map<String, dynamic>.from(map['quote'] as Map));
        }
        return QuoteRecord.fromJson(map);
      },
    );
    return res.data!;
  }

  Future<PagedResult<InvoiceRecord>> invoices({String? search, String? paymentStatus, int page = 1}) {
    return _paged('sales-invoices', (raw) => InvoiceRecord.fromJson(raw), search: search, page: page, extra: {
      if (paymentStatus != null && paymentStatus.isNotEmpty) 'payment_status': paymentStatus,
    });
  }

  Future<InvoiceRecord> invoice(int id) async {
    final res = await _client.get('sales-invoices/$id', mapData: (raw) => InvoiceRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<InvoiceRecord> saveInvoice(Map<String, dynamic> body, {int? id}) async {
    final res = id == null
        ? await _client.post('sales-invoices', body: body, mapData: (raw) => InvoiceRecord.fromJson(Map<String, dynamic>.from(raw as Map)))
        : await _client.put('sales-invoices/$id', body: body, mapData: (raw) => InvoiceRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<InvoiceRecord> invoiceAction(int id, String action, {Map<String, dynamic>? body}) async {
    final res = await _client.post(
      'sales-invoices/$id/$action',
      body: body,
      mapData: (raw) {
        final map = Map<String, dynamic>.from(raw as Map);
        if (map.containsKey('invoice')) {
          return InvoiceRecord.fromJson(Map<String, dynamic>.from(map['invoice'] as Map));
        }
        return InvoiceRecord.fromJson(map);
      },
    );
    return res.data!;
  }

  Future<Map<String, dynamic>> recordPayment(int invoiceId, Map<String, dynamic> body) async {
    final res = await _client.post('sales-invoices/$invoiceId/payments', body: body);
    return Map<String, dynamic>.from(res.data as Map);
  }

  Future<Map<String, dynamic>> reverseInvoicePayment(int invoiceId, int paymentId, {String? reason}) async {
    final res = await _client.post(
      'sales-invoices/$invoiceId/payments/$paymentId/reverse',
      body: {'reversal_reason': reason},
    );
    return Map<String, dynamic>.from(res.data as Map);
  }

  Future<CheckoutInfo> checkoutAvailability(int invoiceId) async {
    final res = await _client.get(
      'sales-invoices/$invoiceId/checkout',
      mapData: (raw) => CheckoutInfo.fromJson(Map<String, dynamic>.from(raw as Map)),
    );
    return res.data!;
  }

  Future<CheckoutInfo> checkout(int invoiceId) async {
    final res = await _client.post(
      'sales-invoices/$invoiceId/checkout',
      idempotent: true,
      mapData: (raw) {
        final map = Map<String, dynamic>.from(raw as Map);
        return CheckoutInfo.fromJson(Map<String, dynamic>.from(map['checkout'] as Map));
      },
    );
    return res.data!;
  }

  Future<PagedResult<PaymentRecord>> payments({String? search, int page = 1}) {
    return _paged('payments', (raw) => PaymentRecord.fromJson(raw), search: search, page: page);
  }

  Future<PaymentRecord> payment(int id) async {
    final res = await _client.get('payments/$id', mapData: (raw) => PaymentRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<PagedResult<ReceiptRecord>> receipts({String? search, int page = 1}) {
    return _paged('receipts', (raw) => ReceiptRecord.fromJson(raw), search: search, page: page);
  }

  Future<ReceiptRecord> receipt(int id) async {
    final res = await _client.get('receipts/$id', mapData: (raw) => ReceiptRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<ReceiptRecord> sendReceipt(int id, Map<String, dynamic> body) async {
    final res = await _client.post(
      'receipts/$id/send',
      body: body,
      mapData: (raw) => ReceiptRecord.fromJson(Map<String, dynamic>.from((raw as Map)['receipt'] as Map)),
    );
    return res.data!;
  }

  Future<StatementRecord> statement({required int customerId, required String from, required String to}) async {
    final res = await _client.get(
      'statements',
      query: {'customer_id': customerId, 'from': from, 'to': to},
      mapData: (raw) => StatementRecord.fromJson(Map<String, dynamic>.from(raw as Map)),
    );
    return res.data!;
  }

  Future<PagedResult<NoteRecord>> notes({String? search, int page = 1}) {
    return _paged('credit-notes', (raw) => NoteRecord.fromJson(raw), search: search, page: page);
  }

  Future<NoteRecord> note(int id) async {
    final res = await _client.get('credit-notes/$id', mapData: (raw) => NoteRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<NoteRecord> saveNote(Map<String, dynamic> body) async {
    final res = await _client.post('credit-notes', body: body, mapData: (raw) => NoteRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<NoteRecord> noteAction(int id, String action) async {
    final res = await _client.post('credit-notes/$id/$action', mapData: (raw) => NoteRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<PagedResult<ContractRecord>> contracts({String? search, int page = 1}) {
    return _paged('contracts', (raw) => ContractRecord.fromJson(raw), search: search, page: page);
  }

  Future<ContractRecord> contract(int id) async {
    final res = await _client.get('contracts/$id', mapData: (raw) => ContractRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<ContractRecord> saveContract(Map<String, dynamic> body, {int? id}) async {
    final res = id == null
        ? await _client.post('contracts', body: body, mapData: (raw) => ContractRecord.fromJson(Map<String, dynamic>.from(raw as Map)))
        : await _client.put('contracts/$id', body: body, mapData: (raw) => ContractRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<ContractRecord> contractAction(int id, String action) async {
    final res = await _client.post('contracts/$id/$action', mapData: (raw) => ContractRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<InvoiceRecord?> generateScheduleInvoice(int contractId, int scheduleId) async {
    final res = await _client.post('contracts/$contractId/billing-schedules/$scheduleId/generate');
    final map = Map<String, dynamic>.from(res.data as Map? ?? {});
    final invoice = map['invoice'];
    if (invoice is Map) {
      return InvoiceRecord.fromJson(Map<String, dynamic>.from(invoice));
    }
    return null;
  }

  Future<Map<String, dynamic>> reversePayment(int paymentId, {String? reason}) async {
    final res = await _client.post('payments/$paymentId/reverse', body: {'reversal_reason': reason});
    return Map<String, dynamic>.from(res.data as Map? ?? {});
  }

  Future<Map<String, dynamic>> purchaseAging() async {
    final res = await _client.get('purchases/aging');
    return Map<String, dynamic>.from(res.data as Map? ?? {});
  }

  Future<InvoiceRecord> savePurchase(Map<String, dynamic> body, {int? id}) async {
    final res = id == null
        ? await _client.post('purchases', body: body, mapData: (raw) => InvoiceRecord.fromJson(Map<String, dynamic>.from(raw as Map)))
        : await _client.put('purchases/$id', body: body, mapData: (raw) => InvoiceRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<Uint8List> expenseAttachment(int id) => _client.downloadBytes('expenses/$id/attachment');

  Future<Uint8List> exportDataset(String dataset, {Map<String, dynamic>? query}) =>
      _client.downloadBytes('exports/$dataset', query: query);

  Future<PagedResult<ExpenseRecord>> expenses({String? search, int page = 1}) {
    return _paged('expenses', (raw) => ExpenseRecord.fromJson(raw), search: search, page: page);
  }

  Future<ExpenseRecord> expense(int id) async {
    final res = await _client.get('expenses/$id', mapData: (raw) => ExpenseRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<ExpenseRecord> saveExpense(Map<String, dynamic> body, {int? id, FormData? form}) async {
    if (form != null) {
      final res = await _client.upload('expenses', formData: form, mapData: (raw) => ExpenseRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
      return res.data!;
    }
    final res = id == null
        ? await _client.post('expenses', body: body, mapData: (raw) => ExpenseRecord.fromJson(Map<String, dynamic>.from(raw as Map)))
        : await _client.put('expenses/$id', body: body, mapData: (raw) => ExpenseRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<PagedResult<SupplierRecord>> suppliers({String? search, int page = 1}) {
    return _paged('suppliers', (raw) => SupplierRecord.fromJson(raw), search: search, page: page);
  }

  Future<PagedResult<InvoiceRecord>> purchases({String? search, int page = 1}) {
    return _paged('purchases', (raw) => InvoiceRecord.fromJson(raw), search: search, page: page);
  }

  Future<InvoiceRecord> purchase(int id) async {
    final res = await _client.get('purchases/$id', mapData: (raw) => InvoiceRecord.fromJson(Map<String, dynamic>.from(raw as Map)));
    return res.data!;
  }

  Future<Map<String, dynamic>> report(String key, {String? from, String? to}) async {
    final res = await _client.get('reports/$key', query: {
      'from': ?from,
      'to': ?to,
    });
    return Map<String, dynamic>.from(res.data as Map);
  }

  Future<Map<String, dynamic>> settings() async {
    final res = await _client.get('settings');
    return Map<String, dynamic>.from(res.data as Map? ?? {});
  }

  Future<Map<String, dynamic>> updateSettings(Map<String, dynamic> body) async {
    final res = await _client.put('settings', body: body);
    return Map<String, dynamic>.from(res.data as Map? ?? {});
  }

  Future<Map<String, dynamic>> search(String q) async {
    final res = await _client.get('search', query: {'q': q});
    return Map<String, dynamic>.from(res.data as Map? ?? {});
  }

  Future<Uint8List> pdf(String path, {Map<String, dynamic>? query}) => _client.downloadBytes(path, query: query);

  Future<PagedResult<T>> _paged<T>(
    String path,
    T Function(Map<String, dynamic> json) map, {
    String? search,
    int page = 1,
    Map<String, dynamic>? extra,
  }) async {
    final res = await _client.get(
      path,
      query: {
        'page': page,
        'per_page': 25,
        if (search != null && search.isNotEmpty) 'search': search,
        ...?extra,
      },
    );
    final items = (res.data as List? ?? [])
        .whereType<Map>()
        .map((row) => map(Map<String, dynamic>.from(row)))
        .toList();
    return PagedResult(items: items, page: res.currentPage, lastPage: res.lastPage, total: res.total);
  }
}

extension FinanceApiResponseX on ApiResponse<dynamic> {}
