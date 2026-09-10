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
    this.whatsapp,
    this.buildingNumber,
    this.district,
    this.postalCode,
    this.additionalNumber,
    this.paymentTerms,
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
  final String? whatsapp;
  final String? buildingNumber;
  final String? district;
  final String? postalCode;
  final String? additionalNumber;
  final String? paymentTerms;
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
      whatsapp: json['whatsapp']?.toString(),
      buildingNumber: json['building_number']?.toString(),
      district: json['district']?.toString(),
      postalCode: json['postal_code']?.toString(),
      additionalNumber: json['additional_number']?.toString(),
      paymentTerms: json['payment_terms']?.toString(),
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
    this.taxableAmount,
    this.taxProfileType,
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
  final String? taxableAmount;
  final String? taxProfileType;
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
      taxableAmount: MoneyFields.asMoney(json['taxable_amount']),
      taxProfileType: json['tax_profile_type']?.toString(),
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
    this.discount = '0.00',
    this.taxableAmount = '0.00',
    this.taxAmount = '0.00',
    this.taxRate,
    this.taxProfileType,
    this.taxPriceMode,
    this.total = '0.00',
    this.amountPaid = '0.00',
    this.amountDue = '0.00',
    this.amountCredited = '0.00',
    this.amountDebited = '0.00',
    this.documentStatus,
    this.paymentStatus,
    this.deliveryStatus,
    this.notes,
    this.paymentTerms,
    this.contractId,
    this.projectId,
    this.type,
    this.supplierId,
    this.zatcaRequirement,
    this.zatcaSubtype,
    this.hasZatcaQr = false,
    this.companySnapshot,
    this.recipientSnapshot,
    this.lines = const [],
    this.payments = const [],
    this.receipts = const [],
    this.creditNotes = const [],
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
  final String discount;
  final String taxableAmount;
  final String taxAmount;
  final String? taxRate;
  final String? taxProfileType;
  final String? taxPriceMode;
  final String total;
  final String amountPaid;
  final String amountDue;
  final String amountCredited;
  final String amountDebited;
  final String? documentStatus;
  final String? paymentStatus;
  final String? deliveryStatus;
  final String? notes;
  final String? paymentTerms;
  final int? contractId;
  final int? projectId;
  final String? type;
  final int? supplierId;
  final String? zatcaRequirement;
  final String? zatcaSubtype;
  final bool hasZatcaQr;
  final Map<String, dynamic>? companySnapshot;
  final Map<String, dynamic>? recipientSnapshot;
  final List<LineItem> lines;
  final List<PaymentRecord> payments;
  final List<ReceiptRecord> receipts;
  final List<NoteRecord> creditNotes;
  final List<DeliveryRecord> deliveries;
  final CheckoutInfo? checkout;
  final List<Map<String, dynamic>> audit;

  factory InvoiceRecord.fromJson(Map<String, dynamic> json) {
    final zatca = json['zatca'] is Map ? Map<String, dynamic>.from(json['zatca'] as Map) : null;
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
      discount: MoneyFields.asMoney(json['discount']),
      taxableAmount: MoneyFields.asMoney(json['taxable_amount']),
      taxAmount: MoneyFields.asMoney(json['tax_amount']),
      taxRate: json['tax_rate']?.toString(),
      taxProfileType: json['tax_profile_type']?.toString(),
      taxPriceMode: json['tax_price_mode']?.toString(),
      total: MoneyFields.asMoney(json['total']),
      amountPaid: MoneyFields.asMoney(json['amount_paid']),
      amountDue: MoneyFields.asMoney(json['amount_due']),
      amountCredited: MoneyFields.asMoney(json['amount_credited']),
      amountDebited: MoneyFields.asMoney(json['amount_debited']),
      documentStatus: json['document_status']?.toString(),
      paymentStatus: json['payment_status']?.toString(),
      deliveryStatus: json['delivery_status']?.toString(),
      notes: json['notes']?.toString(),
      paymentTerms: json['payment_terms']?.toString(),
      contractId: json['contract_id'] == null ? null : int.tryParse('${json['contract_id']}'),
      projectId: json['project_id'] == null ? null : int.tryParse('${json['project_id']}'),
      type: json['type']?.toString(),
      supplierId: json['supplier_id'] == null ? null : int.tryParse('${json['supplier_id']}'),
      zatcaRequirement: zatca?['requirement']?.toString(),
      zatcaSubtype: zatca?['tax_document_subtype']?.toString(),
      hasZatcaQr: zatca?['has_qr'] == true,
      companySnapshot: json['company_snapshot'] is Map
          ? Map<String, dynamic>.from(json['company_snapshot'] as Map)
          : null,
      recipientSnapshot: json['recipient_snapshot'] is Map
          ? Map<String, dynamic>.from(json['recipient_snapshot'] as Map)
          : null,
      lines: _mapList(json['lines'], LineItem.fromJson),
      payments: _mapList(json['payments'], PaymentRecord.fromJson),
      receipts: _mapList(json['receipts'], ReceiptRecord.fromJson),
      creditNotes: _mapList(json['credit_notes'], NoteRecord.fromJson),
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
    this.discount = '0.00',
    this.taxableAmount = '0.00',
    this.taxAmount = '0.00',
    this.taxRate,
    this.taxProfileType,
    this.documentStatus,
    this.outcome,
    this.deliveryStatus,
    this.convertedInvoiceId,
    this.convertedInvoiceNumber,
    this.notes,
    this.terms,
    this.rejectionReason,
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
  final String discount;
  final String taxableAmount;
  final String taxAmount;
  final String? taxRate;
  final String? taxProfileType;
  final String? documentStatus;
  final String? outcome;
  final String? deliveryStatus;
  final int? convertedInvoiceId;
  final String? convertedInvoiceNumber;
  final String? notes;
  final String? terms;
  final String? rejectionReason;
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
      discount: MoneyFields.asMoney(json['discount']),
      taxableAmount: MoneyFields.asMoney(json['taxable_amount']),
      taxAmount: MoneyFields.asMoney(json['tax_amount']),
      taxRate: json['tax_rate']?.toString(),
      taxProfileType: json['tax_profile_type']?.toString(),
      documentStatus: json['document_status']?.toString(),
      outcome: json['outcome']?.toString(),
      deliveryStatus: json['delivery_status']?.toString(),
      convertedInvoiceId:
          json['converted_invoice_id'] == null ? null : int.tryParse('${json['converted_invoice_id']}'),
      convertedInvoiceNumber: json['converted_invoice_number']?.toString(),
      notes: json['notes']?.toString(),
      terms: json['terms']?.toString(),
      rejectionReason: json['rejection_reason']?.toString(),
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
    this.customerId,
    this.customerName,
    this.paymentDate,
    this.amount = '0.00',
    this.method,
    this.reference,
    this.status,
    this.notes,
    this.reversedAt,
    this.receiptId,
    this.receiptNumber,
    this.receiptStatus,
    this.reversalReason,
    this.treasuryAccountId,
    this.treasuryAccountName,
  });

  final int id;
  final int? invoiceId;
  final String? invoiceNumber;
  final int? customerId;
  final String? customerName;
  final String? paymentDate;
  final String amount;
  final String? method;
  final String? reference;
  final String? status;
  final String? notes;
  final String? reversedAt;
  final int? receiptId;
  final String? receiptNumber;
  final String? receiptStatus;
  final String? reversalReason;
  final int? treasuryAccountId;
  final String? treasuryAccountName;

  factory PaymentRecord.fromJson(Map<String, dynamic> json) {
    return PaymentRecord(
      id: int.parse('${json['id']}'),
      invoiceId: json['invoice_id'] == null ? null : int.tryParse('${json['invoice_id']}'),
      invoiceNumber: json['invoice_number']?.toString(),
      customerId: json['customer_id'] == null ? null : int.tryParse('${json['customer_id']}'),
      customerName: json['customer_name']?.toString(),
      paymentDate: json['payment_date']?.toString(),
      amount: MoneyFields.asMoney(json['amount']),
      method: json['method']?.toString(),
      reference: json['reference']?.toString(),
      status: json['status']?.toString(),
      notes: json['notes']?.toString(),
      reversedAt: json['reversed_at']?.toString(),
      receiptId: json['receipt_id'] == null ? null : int.tryParse('${json['receipt_id']}'),
      receiptNumber: json['receipt_number']?.toString(),
      receiptStatus: json['receipt_status']?.toString(),
      reversalReason: json['reversal_reason']?.toString(),
      treasuryAccountId: json['treasury_account_id'] == null ? null : int.tryParse('${json['treasury_account_id']}'),
      treasuryAccountName: json['treasury_account_name']?.toString(),
    );
  }
}

class ReceiptRecord {
  ReceiptRecord({
    required this.id,
    this.receiptNumber,
    this.invoiceId,
    this.invoiceNumber,
    this.customerId,
    this.customerName,
    this.paymentId,
    this.amount = '0.00',
    this.paymentDate,
    this.method,
    this.reference,
    this.currency = 'SAR',
    this.status,
    this.voidedAt,
    this.deliveries = const [],
  });

  final int id;
  final String? receiptNumber;
  final int? invoiceId;
  final String? invoiceNumber;
  final int? customerId;
  final String? customerName;
  final int? paymentId;
  final String amount;
  final String? paymentDate;
  final String? method;
  final String? reference;
  final String currency;
  final String? status;
  final String? voidedAt;
  final List<DeliveryRecord> deliveries;

  factory ReceiptRecord.fromJson(Map<String, dynamic> json) {
    return ReceiptRecord(
      id: int.parse('${json['id']}'),
      receiptNumber: json['receipt_number']?.toString(),
      invoiceId: json['invoice_id'] == null ? null : int.tryParse('${json['invoice_id']}'),
      invoiceNumber: json['invoice_number']?.toString(),
      customerId: json['customer_id'] == null ? null : int.tryParse('${json['customer_id']}'),
      customerName: json['customer_name']?.toString(),
      paymentId: json['payment_id'] == null ? null : int.tryParse('${json['payment_id']}'),
      amount: MoneyFields.asMoney(json['amount']),
      paymentDate: json['payment_date']?.toString(),
      method: json['method']?.toString(),
      reference: json['reference']?.toString(),
      currency: json['currency']?.toString() ?? 'SAR',
      status: json['status']?.toString(),
      voidedAt: json['voided_at']?.toString(),
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
    this.invoiceId,
    this.invoiceNumber,
    this.customerId,
    this.customerName,
    this.issueDate,
    this.total = '0.00',
    this.subtotal = '0.00',
    this.taxAmount = '0.00',
    this.currency = 'SAR',
    this.reason,
    this.notes,
    this.lines = const [],
  });

  final int id;
  final String? noteNumber;
  final String? type;
  final String? status;
  final int? invoiceId;
  final String? invoiceNumber;
  final int? customerId;
  final String? customerName;
  final String? issueDate;
  final String total;
  final String subtotal;
  final String taxAmount;
  final String currency;
  final String? reason;
  final String? notes;
  final List<LineItem> lines;

  factory NoteRecord.fromJson(Map<String, dynamic> json) {
    return NoteRecord(
      id: int.parse('${json['id']}'),
      noteNumber: json['note_number']?.toString(),
      type: json['type']?.toString(),
      status: json['status']?.toString(),
      invoiceId: json['invoice_id'] == null ? null : int.tryParse('${json['invoice_id']}'),
      invoiceNumber: json['invoice_number']?.toString(),
      customerId: json['customer_id'] == null ? null : int.tryParse('${json['customer_id']}'),
      customerName: json['customer_name']?.toString(),
      issueDate: json['issue_date']?.toString(),
      total: MoneyFields.asMoney(json['total']),
      subtotal: MoneyFields.asMoney(json['subtotal']),
      taxAmount: MoneyFields.asMoney(json['tax_amount']),
      currency: json['currency']?.toString() ?? 'SAR',
      reason: json['reason']?.toString(),
      notes: json['notes']?.toString(),
      lines: _mapList(json['lines'], LineItem.fromJson),
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
    this.supplierId,
    this.supplierName,
    this.amount = '0.00',
    this.taxRate,
    this.taxAmount = '0.00',
    this.total = '0.00',
    this.currency = 'SAR',
    this.paymentMethod,
    this.status,
    this.hasAttachment = false,
    this.treasuryAccountId,
    this.treasuryAccountName,
    this.isRecurring = false,
    this.taxProfileType,
  });

  final int id;
  final String? expenseNumber;
  final String? expenseDate;
  final String? description;
  final String? categoryName;
  final int? supplierId;
  final String? supplierName;
  final String amount;
  final String? taxRate;
  final String taxAmount;
  final String total;
  final String currency;
  final String? paymentMethod;
  final String? status;
  final bool hasAttachment;
  final int? treasuryAccountId;
  final String? treasuryAccountName;
  final bool isRecurring;
  final String? taxProfileType;

  factory ExpenseRecord.fromJson(Map<String, dynamic> json) {
    return ExpenseRecord(
      id: int.parse('${json['id']}'),
      expenseNumber: json['expense_number']?.toString(),
      expenseDate: json['expense_date']?.toString(),
      description: json['description']?.toString(),
      categoryName: json['category_name']?.toString(),
      supplierId: json['supplier_id'] == null ? null : int.tryParse('${json['supplier_id']}'),
      supplierName: json['supplier_name']?.toString(),
      amount: MoneyFields.asMoney(json['amount']),
      taxRate: json['tax_rate']?.toString(),
      taxAmount: MoneyFields.asMoney(json['tax_amount']),
      total: MoneyFields.asMoney(json['total']),
      currency: json['currency']?.toString() ?? 'SAR',
      paymentMethod: json['payment_method']?.toString(),
      status: json['status']?.toString(),
      hasAttachment: json['has_attachment'] == true,
      treasuryAccountId: json['treasury_account_id'] == null ? null : int.tryParse('${json['treasury_account_id']}'),
      treasuryAccountName: json['treasury_account_name']?.toString(),
      isRecurring: json['is_recurring'] == true,
      taxProfileType: json['tax_profile_type']?.toString(),
    );
  }
}

class ContractItemRecord {
  const ContractItemRecord({
    this.id,
    this.title,
    this.description,
    this.quantity,
    this.unitPrice,
    this.total,
  });

  final int? id;
  final String? title;
  final String? description;
  final String? quantity;
  final String? unitPrice;
  final String? total;

  factory ContractItemRecord.fromJson(Map<String, dynamic> json) {
    return ContractItemRecord(
      id: json['id'] == null ? null : int.tryParse('${json['id']}'),
      title: json['title']?.toString(),
      description: json['description']?.toString(),
      quantity: json['quantity']?.toString(),
      unitPrice: MoneyFields.asMoney(json['unit_price']),
      total: MoneyFields.asMoney(json['total']),
    );
  }
}

class BillingScheduleRecord {
  const BillingScheduleRecord({
    required this.id,
    this.title,
    this.frequency,
    this.status,
    this.startDate,
    this.endDate,
    this.nextRunOn,
    this.intervalCount,
    this.totalOccurrences,
    this.generatedCount,
    this.amount = '0.00',
    this.currency = 'SAR',
    this.autoIssue = false,
    this.notes,
  });

  final int id;
  final String? title;
  final String? frequency;
  final String? status;
  final String? startDate;
  final String? endDate;
  final String? nextRunOn;
  final int? intervalCount;
  final int? totalOccurrences;
  final int? generatedCount;
  final String amount;
  final String currency;
  final bool autoIssue;
  final String? notes;

  factory BillingScheduleRecord.fromJson(Map<String, dynamic> json) {
    return BillingScheduleRecord(
      id: int.parse('${json['id']}'),
      title: json['title']?.toString(),
      frequency: json['frequency']?.toString(),
      status: json['status']?.toString(),
      startDate: json['start_date']?.toString(),
      endDate: json['end_date']?.toString(),
      nextRunOn: json['next_run_on']?.toString(),
      intervalCount: json['interval_count'] == null ? null : int.tryParse('${json['interval_count']}'),
      totalOccurrences: json['total_occurrences'] == null ? null : int.tryParse('${json['total_occurrences']}'),
      generatedCount: json['generated_count'] == null ? null : int.tryParse('${json['generated_count']}'),
      amount: MoneyFields.asMoney(json['amount']),
      currency: json['currency']?.toString() ?? 'SAR',
      autoIssue: json['auto_issue'] == true,
      notes: json['notes']?.toString(),
    );
  }

  Map<String, dynamic> toLegacyMap() => {
        'id': id,
        'title': title,
        'frequency': frequency,
        'status': status,
        'start_date': startDate,
        'end_date': endDate,
        'next_run_on': nextRunOn,
        'interval_count': intervalCount,
        'total_occurrences': totalOccurrences,
        'generated_count': generatedCount,
        'amount': amount,
        'currency': currency,
        'auto_issue': autoIssue,
        'notes': notes,
      };
}

class ContractRecord {
  const ContractRecord({
    required this.id,
    this.contractNumber,
    this.title,
    this.status,
    this.customerId,
    this.customerName,
    this.startDate,
    this.endDate,
    this.value = '0.00',
    this.currency = 'SAR',
    this.notes,
    this.terms,
    this.items = const [],
    this.scheduleRecords = const [],
    this.generatedInvoices = const [],
    this.billingSummary = const {},
  });

  final int id;
  final String? contractNumber;
  final String? title;
  final String? status;
  final int? customerId;
  final String? customerName;
  final String? startDate;
  final String? endDate;
  final String value;
  final String currency;
  final String? notes;
  final String? terms;
  final List<ContractItemRecord> items;
  final List<BillingScheduleRecord> scheduleRecords;
  final List<InvoiceRecord> generatedInvoices;
  final Map<String, dynamic> billingSummary;

  List<Map<String, dynamic>> get schedules => scheduleRecords.map((row) => row.toLegacyMap()).toList();

  factory ContractRecord.fromJson(Map<String, dynamic> json) {
    return ContractRecord(
      id: int.parse('${json['id']}'),
      contractNumber: json['contract_number']?.toString(),
      title: json['title']?.toString(),
      status: json['status']?.toString(),
      customerId: json['customer_id'] == null ? null : int.tryParse('${json['customer_id']}'),
      customerName: json['customer_name']?.toString(),
      startDate: json['start_date']?.toString(),
      endDate: json['end_date']?.toString(),
      value: MoneyFields.asMoney(json['value']),
      currency: json['currency']?.toString() ?? 'SAR',
      notes: json['notes']?.toString(),
      terms: json['terms']?.toString(),
      items: _mapList(json['items'], ContractItemRecord.fromJson),
      scheduleRecords: _mapList(json['billing_schedules'], BillingScheduleRecord.fromJson),
      generatedInvoices: _mapList(json['generated_invoices'], InvoiceRecord.fromJson),
      billingSummary: json['billing_summary'] is Map
          ? Map<String, dynamic>.from(json['billing_summary'] as Map)
          : const {},
    );
  }
}

class StatementRecord {
  const StatementRecord({
    required this.openingBalance,
    required this.closingBalance,
    required this.lines,
    this.customerName,
    this.customerId,
    this.from,
    this.to,
    this.invoicesTotal = '0.00',
    this.paymentsTotal = '0.00',
    this.creditsTotal = '0.00',
    this.debitsTotal = '0.00',
  });

  final String? customerName;
  final int? customerId;
  final String? from;
  final String? to;
  final String openingBalance;
  final String closingBalance;
  final String invoicesTotal;
  final String paymentsTotal;
  final String creditsTotal;
  final String debitsTotal;
  final List<Map<String, dynamic>> lines;

  factory StatementRecord.fromJson(Map<String, dynamic> json) {
    final customer = json['customer'] is Map ? Map<String, dynamic>.from(json['customer'] as Map) : null;
    return StatementRecord(
      customerName: customer?['name']?.toString(),
      customerId: customer?['id'] == null ? null : int.tryParse('${customer?['id']}'),
      from: json['from']?.toString(),
      to: json['to']?.toString(),
      openingBalance: MoneyFields.asMoney(json['opening_balance']),
      closingBalance: MoneyFields.asMoney(json['closing_balance']),
      invoicesTotal: MoneyFields.asMoney(json['invoices_total']),
      paymentsTotal: MoneyFields.asMoney(json['payments_total']),
      creditsTotal: MoneyFields.asMoney(json['credits_total']),
      debitsTotal: MoneyFields.asMoney(json['debits_total']),
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
    this.recentExpenses = const [],
  });

  final Map<String, String> cards;
  final List<InvoiceRecord> recentInvoices;
  final List<PaymentRecord> recentPayments;
  final List<InvoiceRecord> overdueInvoices;
  final List<ExpenseRecord> recentExpenses;

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    final cardsRaw = json['cards'] as Map? ?? {};
    return DashboardData(
      cards: cardsRaw.map((key, value) => MapEntry(key.toString(), value.toString())),
      recentInvoices: _mapList(json['recent_invoices'], InvoiceRecord.fromJson),
      recentPayments: _mapList(json['recent_payments'], PaymentRecord.fromJson),
      overdueInvoices: _mapList(json['overdue_invoices'], InvoiceRecord.fromJson),
      recentExpenses: _mapList(json['recent_expenses'], ExpenseRecord.fromJson),
    );
  }
}

class SupplierRecord {
  const SupplierRecord({
    required this.id,
    required this.name,
    this.arabicName,
    this.vatNumber,
    this.commercialRegistration,
    this.address,
    this.email,
    this.phone,
    this.paymentTerms,
    this.status,
    this.openingBalance = '0.00',
  });

  final int id;
  final String name;
  final String? arabicName;
  final String? vatNumber;
  final String? commercialRegistration;
  final String? address;
  final String? email;
  final String? phone;
  final String? paymentTerms;
  final String? status;
  final String openingBalance;

  factory SupplierRecord.fromJson(Map<String, dynamic> json) {
    return SupplierRecord(
      id: int.parse('${json['id']}'),
      name: json['name']?.toString() ?? '',
      arabicName: json['arabic_name']?.toString(),
      vatNumber: json['vat_number']?.toString(),
      commercialRegistration: json['commercial_registration']?.toString(),
      address: json['address']?.toString(),
      email: json['email']?.toString(),
      phone: json['phone']?.toString(),
      paymentTerms: json['payment_terms']?.toString(),
      status: json['status']?.toString(),
      openingBalance: MoneyFields.asMoney(json['opening_balance']),
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
