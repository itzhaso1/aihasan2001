class WorkspaceInfo {
  const WorkspaceInfo({
    required this.id,
    required this.name,
    this.type,
    this.slug,
    this.financeEnabled = false,
  });

  final int id;
  final String name;
  final String? type;
  final String? slug;
  final bool financeEnabled;

  factory WorkspaceInfo.fromJson(Map<String, dynamic> json) {
    return WorkspaceInfo(
      id: int.parse('${json['id']}'),
      name: json['name']?.toString() ?? '',
      type: json['type']?.toString(),
      slug: json['slug']?.toString(),
      financeEnabled: json['finance_enabled'] == true,
    );
  }
}

class AuthUser {
  const AuthUser({required this.id, required this.name, this.email, this.phone});

  final int id;
  final String name;
  final String? email;
  final String? phone;

  factory AuthUser.fromJson(Map<String, dynamic> json) {
    return AuthUser(
      id: int.parse('${json['id']}'),
      name: json['name']?.toString() ?? '',
      email: json['email']?.toString(),
      phone: json['phone']?.toString(),
    );
  }
}

class SessionPayload {
  const SessionPayload({
    required this.user,
    required this.workspaces,
    required this.permissions,
    this.token,
    this.workspace,
    this.financeEnabled = false,
    this.message,
  });

  final String? token;
  final AuthUser user;
  final WorkspaceInfo? workspace;
  final List<WorkspaceInfo> workspaces;
  final Map<String, bool> permissions;
  final bool financeEnabled;
  final String? message;

  factory SessionPayload.fromJson(Map<String, dynamic> json) {
    return SessionPayload(
      token: json['token']?.toString(),
      user: AuthUser.fromJson(Map<String, dynamic>.from(json['user'] as Map)),
      workspace: json['workspace'] is Map
          ? WorkspaceInfo.fromJson(Map<String, dynamic>.from(json['workspace'] as Map))
          : null,
      workspaces: (json['workspaces'] as List? ?? [])
          .whereType<Map>()
          .map((row) => WorkspaceInfo.fromJson(Map<String, dynamic>.from(row)))
          .toList(),
      permissions: (json['permissions'] as Map? ?? {}).map(
        (key, value) => MapEntry(key.toString(), value == true),
      ),
      financeEnabled: json['finance_enabled'] == true,
      message: json['message']?.toString(),
    );
  }
}

class GoogleStartResult {
  const GoogleStartResult({required this.ticket, required this.authUrl, this.expiresIn});

  final String ticket;
  final String authUrl;
  final int? expiresIn;

  factory GoogleStartResult.fromJson(Map<String, dynamic> json) {
    return GoogleStartResult(
      ticket: json['ticket']?.toString() ?? '',
      authUrl: json['auth_url']?.toString() ?? '',
      expiresIn: int.tryParse('${json['expires_in'] ?? ''}'),
    );
  }
}

class GoogleStatusResult {
  const GoogleStatusResult({required this.status, this.accessToken, this.error});

  final String status;
  final String? accessToken;
  final String? error;

  factory GoogleStatusResult.fromJson(Map<String, dynamic> json) {
    return GoogleStatusResult(
      status: json['status']?.toString() ?? '',
      accessToken: json['access_token']?.toString(),
      error: json['error']?.toString(),
    );
  }
}

class MoneyFields {
  static String asMoney(dynamic value) {
    if (value == null) return '0.00';
    return value.toString();
  }
}

class CustomerRecord {
  CustomerRecord({
    required this.id,
    required this.name,
    required this.outstandingBalance,
    this.partyType,
    this.email,
    this.phone,
    this.vatNumber,
    this.commercialRegistration,
    this.address,
    this.street,
    this.city,
    this.countryCode,
    this.notes,
    this.invoices = const [],
    this.quotes = const [],
    this.payments = const [],
    this.receipts = const [],
    this.contracts = const [],
  });

  final int id;
  final String name;
  final String? partyType;
  final String? email;
  final String? phone;
  final String? vatNumber;
  final String? commercialRegistration;
  final String? address;
  final String? street;
  final String? city;
  final String? countryCode;
  final String? notes;
  final String outstandingBalance;
  final List<InvoiceRecord> invoices;
  final List<QuoteRecord> quotes;
  final List<PaymentRecord> payments;
  final List<ReceiptRecord> receipts;
  final List<ContractRecord> contracts;

  factory CustomerRecord.fromJson(Map<String, dynamic> json) {
    return CustomerRecord(
      id: int.parse('${json['id']}'),
      name: json['name']?.toString() ?? '',
      partyType: json['party_type']?.toString(),
      email: json['email']?.toString(),
      phone: json['phone']?.toString(),
      vatNumber: json['vat_number']?.toString(),
      commercialRegistration: json['commercial_registration']?.toString(),
      address: json['address']?.toString(),
      street: json['street']?.toString(),
      city: json['city']?.toString(),
      countryCode: json['country_code']?.toString(),
      notes: json['notes']?.toString(),
      outstandingBalance: MoneyFields.asMoney(json['outstanding_balance']),
      invoices: _mapList(json['invoices'], InvoiceRecord.fromJson),
      quotes: _mapList(json['quotes'], QuoteRecord.fromJson),
      payments: _mapList(json['payments'], PaymentRecord.fromJson),
      receipts: _mapList(json['receipts'], ReceiptRecord.fromJson),
      contracts: _mapList(json['contracts'], ContractRecord.fromJson),
    );
  }
}

class LineItem {
  const LineItem({
    this.id,
    this.productId,
    this.productName,
    this.description,
    this.unit,
    this.quantity,
    this.unitPrice,
    this.discount,
    this.taxRate,
    this.taxAmount,
    this.total,
  });

  final int? id;
  final int? productId;
  final String? productName;
  final String? description;
  final String? unit;
  final String? quantity;
  final String? unitPrice;
  final String? discount;
  final String? taxRate;
  final String? taxAmount;
  final String? total;

  factory LineItem.fromJson(Map<String, dynamic> json) {
    return LineItem(
      id: json['id'] == null ? null : int.tryParse('${json['id']}'),
      productId: json['product_id'] == null ? null : int.tryParse('${json['product_id']}'),
      productName: json['product_name']?.toString(),
      description: json['description']?.toString(),
      unit: json['unit']?.toString(),
      quantity: json['quantity']?.toString(),
      unitPrice: MoneyFields.asMoney(json['unit_price']),
      discount: MoneyFields.asMoney(json['discount']),
      taxRate: MoneyFields.asMoney(json['tax_rate']),
      taxAmount: MoneyFields.asMoney(json['tax_amount']),
      total: MoneyFields.asMoney(json['total']),
    );
  }

  Map<String, dynamic> toPayload() => {
        'product_id': productId,
        'product_name': productName,
        'description': description,
        'unit': unit,
        'quantity': quantity,
        'unit_price': unitPrice,
        'discount': discount,
        'tax_rate': taxRate,
      };
}

class DeliveryRecord {
  const DeliveryRecord({
    required this.id,
    this.channel,
    this.recipient,
    this.status,
    this.subject,
    this.error,
    this.sentAt,
  });

  final int id;
  final String? channel;
  final String? recipient;
  final String? status;
  final String? subject;
  final String? error;
  final String? sentAt;

  factory DeliveryRecord.fromJson(Map<String, dynamic> json) {
    return DeliveryRecord(
      id: int.parse('${json['id']}'),
      channel: json['channel']?.toString(),
      recipient: json['recipient']?.toString(),
      status: json['status']?.toString(),
      subject: json['subject']?.toString(),
      error: json['error']?.toString(),
      sentAt: json['sent_at']?.toString(),
    );
  }
}

class CheckoutInfo {
  const CheckoutInfo({
    required this.supported,
    this.checkoutUrl,
    this.code,
    this.message,
  });

  final bool supported;
  final String? checkoutUrl;
  final String? code;
  final String? message;

  bool get hasUrl => checkoutUrl != null && checkoutUrl!.isNotEmpty;

  factory CheckoutInfo.fromJson(Map<String, dynamic> json) {
    return CheckoutInfo(
      supported: json['supported'] == true,
      checkoutUrl: json['checkout_url']?.toString(),
      code: json['code']?.toString(),
      message: json['message']?.toString(),
    );
  }
}

class InvoiceRecord {
  InvoiceRecord({
    required this.id,
    this.invoiceNumber,
    this.customerId,
    this.customerName,
    this.supplierName,
    this.issueDate,
    this.dueDate,
    this.currency = 'SAR',
    this.subtotal = '0.00',
    this.taxAmount = '0.00',
    this.total = '0.00',
    this.amountPaid = '0.00',
    this.amountDue = '0.00',
    this.documentStatus,
    this.paymentStatus,
    this.deliveryStatus,
    this.notes,
    this.lines = const [],
    this.payments = const [],
    this.receipts = const [],
    this.deliveries = const [],
    this.checkout,
    this.audit = const [],
  });

  final int id;
  final String? invoiceNumber;
  final int? customerId;
  final String? customerName;
  final String? supplierName;
  final String? issueDate;
  final String? dueDate;
  final String currency;
  final String subtotal;
  final String taxAmount;
  final String total;
  final String amountPaid;
  final String amountDue;
  final String? documentStatus;
  final String? paymentStatus;
  final String? deliveryStatus;
  final String? notes;
  final List<LineItem> lines;
  final List<PaymentRecord> payments;
  final List<ReceiptRecord> receipts;
  final List<DeliveryRecord> deliveries;
  final CheckoutInfo? checkout;
  final List<Map<String, dynamic>> audit;

  factory InvoiceRecord.fromJson(Map<String, dynamic> json) {
    return InvoiceRecord(
      id: int.parse('${json['id']}'),
      invoiceNumber: json['invoice_number']?.toString(),
      customerId: json['customer_id'] == null ? null : int.tryParse('${json['customer_id']}'),
      customerName: json['customer_name']?.toString(),
      supplierName: json['supplier_name']?.toString(),
      issueDate: json['issue_date']?.toString(),
      dueDate: json['due_date']?.toString(),
      currency: json['currency']?.toString() ?? 'SAR',
      subtotal: MoneyFields.asMoney(json['subtotal']),
      taxAmount: MoneyFields.asMoney(json['tax_amount']),
      total: MoneyFields.asMoney(json['total']),
      amountPaid: MoneyFields.asMoney(json['amount_paid']),
      amountDue: MoneyFields.asMoney(json['amount_due']),
      documentStatus: json['document_status']?.toString(),
      paymentStatus: json['payment_status']?.toString(),
      deliveryStatus: json['delivery_status']?.toString(),
      notes: json['notes']?.toString(),
      lines: _mapList(json['lines'], LineItem.fromJson),
      payments: _mapList(json['payments'], PaymentRecord.fromJson),
      receipts: _mapList(json['receipts'], ReceiptRecord.fromJson),
      deliveries: _mapList(json['deliveries'], DeliveryRecord.fromJson),
      checkout: json['checkout'] is Map
          ? CheckoutInfo.fromJson(Map<String, dynamic>.from(json['checkout'] as Map))
          : null,
      audit: (json['audit'] as List? ?? [])
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .toList(),
    );
  }
}

class QuoteRecord {
  QuoteRecord({
    required this.id,
    this.quoteNumber,
    this.customerId,
    this.customerName,
    this.issueDate,
    this.expiryDate,
    this.total = '0.00',
    this.subtotal = '0.00',
    this.taxAmount = '0.00',
    this.documentStatus,
    this.outcome,
    this.deliveryStatus,
    this.convertedInvoiceId,
    this.convertedInvoiceNumber,
    this.notes,
    this.lines = const [],
    this.deliveries = const [],
    this.currency = 'SAR',
  });

  final int id;
  final String? quoteNumber;
  final int? customerId;
  final String? customerName;
  final String? issueDate;
  final String? expiryDate;
  final String total;
  final String subtotal;
  final String taxAmount;
  final String? documentStatus;
  final String? outcome;
  final String? deliveryStatus;
  final int? convertedInvoiceId;
  final String? convertedInvoiceNumber;
  final String? notes;
  final List<LineItem> lines;
  final List<DeliveryRecord> deliveries;
  final String currency;

  factory QuoteRecord.fromJson(Map<String, dynamic> json) {
    return QuoteRecord(
      id: int.parse('${json['id']}'),
      quoteNumber: json['quote_number']?.toString(),
      customerId: json['customer_id'] == null ? null : int.tryParse('${json['customer_id']}'),
      customerName: json['customer_name']?.toString(),
      issueDate: json['issue_date']?.toString(),
      expiryDate: json['expiry_date']?.toString(),
      total: MoneyFields.asMoney(json['total']),
      subtotal: MoneyFields.asMoney(json['subtotal']),
      taxAmount: MoneyFields.asMoney(json['tax_amount']),
      documentStatus: json['document_status']?.toString(),
      outcome: json['outcome']?.toString(),
      deliveryStatus: json['delivery_status']?.toString(),
      convertedInvoiceId:
          json['converted_invoice_id'] == null ? null : int.tryParse('${json['converted_invoice_id']}'),
      convertedInvoiceNumber: json['converted_invoice_number']?.toString(),
      notes: json['notes']?.toString(),
      lines: _mapList(json['lines'], LineItem.fromJson),
      deliveries: _mapList(json['deliveries'], DeliveryRecord.fromJson),
      currency: json['currency']?.toString() ?? 'SAR',
    );
  }
}

class PaymentRecord {
  const PaymentRecord({
    required this.id,
    this.invoiceId,
    this.invoiceNumber,
    this.customerName,
    this.paymentDate,
    this.amount = '0.00',
    this.method,
    this.reference,
    this.status,
    this.receiptId,
    this.receiptNumber,
    this.receiptStatus,
    this.reversalReason,
  });

  final int id;
  final int? invoiceId;
  final String? invoiceNumber;
  final String? customerName;
  final String? paymentDate;
  final String amount;
  final String? method;
  final String? reference;
  final String? status;
  final int? receiptId;
  final String? receiptNumber;
  final String? receiptStatus;
  final String? reversalReason;

  factory PaymentRecord.fromJson(Map<String, dynamic> json) {
    return PaymentRecord(
      id: int.parse('${json['id']}'),
      invoiceId: json['invoice_id'] == null ? null : int.tryParse('${json['invoice_id']}'),
      invoiceNumber: json['invoice_number']?.toString(),
      customerName: json['customer_name']?.toString(),
      paymentDate: json['payment_date']?.toString(),
      amount: MoneyFields.asMoney(json['amount']),
      method: json['method']?.toString(),
      reference: json['reference']?.toString(),
      status: json['status']?.toString(),
      receiptId: json['receipt_id'] == null ? null : int.tryParse('${json['receipt_id']}'),
      receiptNumber: json['receipt_number']?.toString(),
      receiptStatus: json['receipt_status']?.toString(),
      reversalReason: json['reversal_reason']?.toString(),
    );
  }
}

class ReceiptRecord {
  ReceiptRecord({
    required this.id,
    this.receiptNumber,
    this.invoiceNumber,
    this.customerName,
    this.amount = '0.00',
    this.paymentDate,
    this.method,
    this.reference,
    this.status,
    this.deliveries = const [],
  });

  final int id;
  final String? receiptNumber;
  final String? invoiceNumber;
  final String? customerName;
  final String amount;
  final String? paymentDate;
  final String? method;
  final String? reference;
  final String? status;
  final List<DeliveryRecord> deliveries;

  factory ReceiptRecord.fromJson(Map<String, dynamic> json) {
    return ReceiptRecord(
      id: int.parse('${json['id']}'),
      receiptNumber: json['receipt_number']?.toString(),
      invoiceNumber: json['invoice_number']?.toString(),
      customerName: json['customer_name']?.toString(),
      amount: MoneyFields.asMoney(json['amount']),
      paymentDate: json['payment_date']?.toString(),
      method: json['method']?.toString(),
      reference: json['reference']?.toString(),
      status: json['status']?.toString(),
      deliveries: _mapList(json['deliveries'], DeliveryRecord.fromJson),
    );
  }
}

class NoteRecord {
  const NoteRecord({
    required this.id,
    this.noteNumber,
    this.type,
    this.status,
    this.invoiceNumber,
    this.customerName,
    this.issueDate,
    this.total = '0.00',
    this.taxAmount = '0.00',
    this.reason,
  });

  final int id;
  final String? noteNumber;
  final String? type;
  final String? status;
  final String? invoiceNumber;
  final String? customerName;
  final String? issueDate;
  final String total;
  final String taxAmount;
  final String? reason;

  factory NoteRecord.fromJson(Map<String, dynamic> json) {
    return NoteRecord(
      id: int.parse('${json['id']}'),
      noteNumber: json['note_number']?.toString(),
      type: json['type']?.toString(),
      status: json['status']?.toString(),
      invoiceNumber: json['invoice_number']?.toString(),
      customerName: json['customer_name']?.toString(),
      issueDate: json['issue_date']?.toString(),
      total: MoneyFields.asMoney(json['total']),
      taxAmount: MoneyFields.asMoney(json['tax_amount']),
      reason: json['reason']?.toString(),
    );
  }
}

class ExpenseRecord {
  const ExpenseRecord({
    required this.id,
    this.expenseNumber,
    this.expenseDate,
    this.description,
    this.categoryName,
    this.amount = '0.00',
    this.taxAmount = '0.00',
    this.total = '0.00',
    this.status,
    this.hasAttachment = false,
  });

  final int id;
  final String? expenseNumber;
  final String? expenseDate;
  final String? description;
  final String? categoryName;
  final String amount;
  final String taxAmount;
  final String total;
  final String? status;
  final bool hasAttachment;

  factory ExpenseRecord.fromJson(Map<String, dynamic> json) {
    return ExpenseRecord(
      id: int.parse('${json['id']}'),
      expenseNumber: json['expense_number']?.toString(),
      expenseDate: json['expense_date']?.toString(),
      description: json['description']?.toString(),
      categoryName: json['category_name']?.toString(),
      amount: MoneyFields.asMoney(json['amount']),
      taxAmount: MoneyFields.asMoney(json['tax_amount']),
      total: MoneyFields.asMoney(json['total']),
      status: json['status']?.toString(),
      hasAttachment: json['has_attachment'] == true,
    );
  }
}

class ContractRecord {
  const ContractRecord({
    required this.id,
    this.contractNumber,
    this.title,
    this.status,
    this.customerName,
    this.startDate,
    this.endDate,
    this.value = '0.00',
    this.schedules = const [],
  });

  final int id;
  final String? contractNumber;
  final String? title;
  final String? status;
  final String? customerName;
  final String? startDate;
  final String? endDate;
  final String value;
  final List<Map<String, dynamic>> schedules;

  factory ContractRecord.fromJson(Map<String, dynamic> json) {
    return ContractRecord(
      id: int.parse('${json['id']}'),
      contractNumber: json['contract_number']?.toString(),
      title: json['title']?.toString(),
      status: json['status']?.toString(),
      customerName: json['customer_name']?.toString(),
      startDate: json['start_date']?.toString(),
      endDate: json['end_date']?.toString(),
      value: MoneyFields.asMoney(json['value']),
      schedules: (json['billing_schedules'] as List? ?? [])
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .toList(),
    );
  }
}

class StatementRecord {
  const StatementRecord({
    required this.openingBalance,
    required this.closingBalance,
    required this.lines,
    this.customerName,
    this.from,
    this.to,
  });

  final String? customerName;
  final String? from;
  final String? to;
  final String openingBalance;
  final String closingBalance;
  final List<Map<String, dynamic>> lines;

  factory StatementRecord.fromJson(Map<String, dynamic> json) {
    return StatementRecord(
      customerName: json['customer'] is Map ? (json['customer'] as Map)['name']?.toString() : null,
      from: json['from']?.toString(),
      to: json['to']?.toString(),
      openingBalance: MoneyFields.asMoney(json['opening_balance']),
      closingBalance: MoneyFields.asMoney(json['closing_balance']),
      lines: (json['lines'] as List? ?? [])
          .whereType<Map>()
          .map((row) => Map<String, dynamic>.from(row))
          .toList(),
    );
  }
}

class DashboardData {
  const DashboardData({
    required this.cards,
    required this.recentInvoices,
    required this.recentPayments,
    required this.overdueInvoices,
  });

  final Map<String, String> cards;
  final List<InvoiceRecord> recentInvoices;
  final List<PaymentRecord> recentPayments;
  final List<InvoiceRecord> overdueInvoices;

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    final cardsRaw = json['cards'] as Map? ?? {};
    return DashboardData(
      cards: cardsRaw.map((key, value) => MapEntry(key.toString(), value.toString())),
      recentInvoices: _mapList(json['recent_invoices'], InvoiceRecord.fromJson),
      recentPayments: _mapList(json['recent_payments'], PaymentRecord.fromJson),
      overdueInvoices: _mapList(json['overdue_invoices'], InvoiceRecord.fromJson),
    );
  }
}

class SupplierRecord {
  const SupplierRecord({
    required this.id,
    required this.name,
    this.vatNumber,
    this.email,
    this.phone,
    this.status,
  });

  final int id;
  final String name;
  final String? vatNumber;
  final String? email;
  final String? phone;
  final String? status;

  factory SupplierRecord.fromJson(Map<String, dynamic> json) {
    return SupplierRecord(
      id: int.parse('${json['id']}'),
      name: json['name']?.toString() ?? '',
      vatNumber: json['vat_number']?.toString(),
      email: json['email']?.toString(),
      phone: json['phone']?.toString(),
      status: json['status']?.toString(),
    );
  }
}

class PagedResult<T> {
  const PagedResult({required this.items, required this.page, required this.lastPage, required this.total});

  final List<T> items;
  final int page;
  final int lastPage;
  final int total;
}

List<T> _mapList<T>(dynamic raw, T Function(Map<String, dynamic> json) map) {
  if (raw is! List) return const [];
  return raw.whereType<Map>().map((row) => map(Map<String, dynamic>.from(row))).toList();
}
