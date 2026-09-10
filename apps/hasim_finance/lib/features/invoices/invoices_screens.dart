import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim_finance/core/auth/auth_controller.dart';
import 'package:hasim_finance/core/models/models.dart';
import 'package:hasim_finance/core/network/api_exception.dart';
import 'package:hasim_finance/core/permissions/finance_permissions.dart';
import 'package:hasim_finance/core/layout/finance_layout.dart';
import 'package:hasim_finance/core/providers/catalog_provider.dart';
import 'package:hasim_finance/core/theme/finance_tokens.dart';
import 'package:hasim_finance/core/utils/files.dart';
import 'package:hasim_finance/core/widgets/widgets.dart';
import 'package:hasim_finance/features/invoices/invoice_detail_widgets.dart';
import 'package:hasim_finance/features/shared/customer_select.dart';
import 'package:hasim_finance/features/shared/document_lines_editor.dart';
import 'package:hasim_finance/features/shared/paged.dart';
import 'package:hasim_finance/l10n/app_localizations.dart';

class InvoicesScreen extends ConsumerStatefulWidget {
  const InvoicesScreen({super.key});

  @override
  ConsumerState<InvoicesScreen> createState() => _InvoicesScreenState();
}

class _InvoicesScreenState extends ConsumerState<InvoicesScreen> {
  String? _lifecycle;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<InvoiceRecord>(
      key: ValueKey(_lifecycle),
      title: l.invoices,
      allowed: auth.permissions.invoicesView,
      onCreate: auth.permissions.invoicesCreate ? () => context.push('/invoices/new') : null,
      filterBar: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Wrap(
          spacing: 8,
          children: [
            for (final option in <(String?, String)>[
              (null, l.filterAll),
              ('draft', l.lifecycleDraft),
              ('sent', l.lifecycleSent),
              ('partial', l.partial),
              ('paid', l.paid),
              ('overdue', l.overdue),
              ('cancelled', l.cancelled),
            ])
              ChoiceChip(
                label: Text(option.$2),
                selected: _lifecycle == option.$1,
                onSelected: (_) => setState(() => _lifecycle = option.$1),
              ),
          ],
        ),
      ),
      loader: (api, search, page) => api.invoices(search: search, page: page, lifecycle: _lifecycle),
      itemBuilder: (context, invoice) => Card(
        child: ListTile(
          title: Text(invoice.invoiceNumber ?? '#${invoice.id}', style: const TextStyle(fontWeight: FontWeight.w800)),
          subtitle: Text('${invoice.customerName ?? ''} · ${invoice.documentStatus} · ${invoice.paymentStatus} · ${l.due} ${invoice.amountDue}'),
          trailing: Text(invoice.total, style: const TextStyle(fontWeight: FontWeight.w800)),
          onTap: () => context.push('/invoices/${invoice.id}'),
        ),
      ),
    );
  }
}

class InvoiceDetailScreen extends ConsumerStatefulWidget {
  const InvoiceDetailScreen({super.key, required this.id});
  final int id;

  @override
  ConsumerState<InvoiceDetailScreen> createState() => _InvoiceDetailScreenState();
}

class _InvoiceDetailScreenState extends ConsumerState<InvoiceDetailScreen> {
  InvoiceRecord? _data;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref.read(financeApiProvider).invoice(widget.id);
      if (!mounted) return;
      setState(() {
        _data = data;
        _loading = false;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _act(String action, {Map<String, dynamic>? body}) async {
    try {
      await ref.read(financeApiProvider).invoiceAction(widget.id, action, body: body);
      await _load();
      if (mounted) showSnack(context, AppLocalizations.of(context).success);
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  Future<void> _checkout() async {
    final l = AppLocalizations.of(context);
    try {
      final info = await ref.read(financeApiProvider).checkout(widget.id);
      await _load();
      if (!mounted) return;
      if (!info.hasUrl) {
        showSnack(context, info.message?.isNotEmpty == true ? info.message! : l.checkoutUnavailable);
        return;
      }
      await showDialog<void>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: Text(l.checkout),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(l.paymentLinkHint),
              const SizedBox(height: 8),
              SelectableText(info.checkoutUrl!),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () async {
                await copyText(info.checkoutUrl!);
                if (ctx.mounted) Navigator.pop(ctx);
                if (mounted) showSnack(context, l.copied);
              },
              child: Text(l.copy),
            ),
            FilledButton(
              onPressed: () async {
                await openExternalUrl(info.checkoutUrl!);
                if (ctx.mounted) Navigator.pop(ctx);
                await _load();
              },
              child: Text(l.open),
            ),
          ],
        ),
      );
      await _load();
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  Future<void> _manualPayment() async {
    final l = AppLocalizations.of(context);
    final amount = TextEditingController(text: _data?.amountDue ?? '');
    final reference = TextEditingController();
    String method = 'cash';
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(l.recordPayment),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(controller: amount, decoration: InputDecoration(labelText: l.amount)),
            TextField(controller: reference, decoration: InputDecoration(labelText: l.reference)),
            DropdownButtonFormField(
              // ignore: deprecated_member_use
              value: method,
              items: [
                DropdownMenuItem(value: 'cash', child: Text(l.methodCash)),
                DropdownMenuItem(value: 'bank_transfer', child: Text(l.methodBank)),
                DropdownMenuItem(value: 'card', child: Text(l.methodCard)),
                DropdownMenuItem(value: 'other', child: Text(l.methodOther)),
              ],
              onChanged: (v) => method = v ?? 'cash',
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: Text(l.cancel)),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: Text(l.save)),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await ref.read(financeApiProvider).recordPayment(widget.id, {
        'amount': amount.text.trim(),
        'method': method,
        'reference': reference.text.trim(),
        'payment_date': DateTime.now().toIso8601String().substring(0, 10),
      });
      await _load();
      if (mounted) showSnack(context, l.success);
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  Future<void> _openPdf() async {
    final invoice = _data;
    if (invoice == null) return;
    try {
      final bytes = await ref.read(financeApiProvider).pdf('sales-invoices/${invoice.id}/pdf');
      await saveAndOpenBytes(bytes, 'invoice-${invoice.invoiceNumber}.pdf');
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  Future<void> _pickAttachments() async {
    final invoice = _data;
    if (invoice == null) return;
    final result = await FilePicker.platform.pickFiles(
      allowMultiple: true,
      type: FileType.custom,
      allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    );
    if (result == null || result.files.isEmpty) return;
    try {
      final form = FormData();
      for (final file in result.files) {
        final part = await multipartFromPicked(file);
        if (part == null) continue;
        form.files.add(MapEntry('attachments[]', part));
      }
      if (form.files.isEmpty) return;
      await ref.read(financeApiProvider).uploadInvoiceAttachments(invoice.id, form);
      await _load();
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = ref.watch(authControllerProvider).permissions;
    final invoice = _data;
    return Scaffold(
      backgroundColor: FinanceTokens.canvas,
      body: AsyncBody(
        loading: _loading,
        error: _error,
        onRetry: _load,
        child: invoice == null
            ? const SizedBox.shrink()
            : FinancePage(
                padding: const EdgeInsets.fromLTRB(20, 10, 20, 24),
                child: ListView(
                  children: [
                    InvoiceHeader(
                      invoice: invoice,
                      actions: _toolbar(context, l, p, invoice),
                    ),
                    const SizedBox(height: 14),
                    InvoiceTwoColumn(
                      leading: CustomerInfoCard(
                        invoice: invoice,
                        onOpenCustomer: invoice.customerId == null ? null : () => context.push('/customers/${invoice.customerId}'),
                      ),
                      trailing: InvoiceSummaryCard(invoice: invoice),
                    ),
                    const SizedBox(height: 12),
                    InvoiceItemsTable(invoice: invoice),
                    if (invoice.deliveries.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      DeliveryTimeline(deliveries: invoice.deliveries),
                    ],
                    const SizedBox(height: 12),
                    _lowerGrid(context, l, p, invoice),
                    if (invoice.creditNotes.isNotEmpty || invoice.receipts.isNotEmpty) ...[
                      const SizedBox(height: 12),
                      _relatedDocuments(context, l, invoice),
                    ],
                    const SizedBox(height: 12),
                    AuditLogCard(invoice: invoice),
                  ],
                ),
              ),
      ),
    );
  }

  Widget _lowerGrid(BuildContext context, AppLocalizations _, FinancePermissions p, InvoiceRecord invoice) {
    final attachments = AttachmentsCard(
      invoice: invoice,
      onUpload: invoice.documentStatus == 'draft' && p.can('invoices.edit') ? _pickAttachments : null,
      onDownload: (row) async {
        try {
          final id = int.parse('${row['id']}');
          final bytes = await ref.read(financeApiProvider).downloadInvoiceAttachment(invoice.id, id);
          await saveAndOpenBytes(bytes, '${row['file_name'] ?? 'attachment-$id'}');
        } catch (e) {
          if (context.mounted) showApiError(context, e);
        }
      },
      onDelete: invoice.documentStatus == 'draft' && p.can('invoices.edit')
          ? (row) async {
              try {
                await ref.read(financeApiProvider).deleteInvoiceAttachment(invoice.id, int.parse('${row['id']}'));
                await _load();
              } catch (e) {
                if (context.mounted) showApiError(context, e);
              }
            }
          : null,
    );
    final notes = NotesTermsCard(invoice: invoice);
    final payment = PaymentStatusCard(
      invoice: invoice,
      onRecordPayment: invoice.documentStatus == 'issued' && invoice.paymentStatus != 'paid' && p.paymentsManage ? _manualPayment : null,
      onOpenPayment: p.paymentsView ? (payment) => context.push('/payments/${payment.id}') : null,
      onReversePayment: p.can('invoices.reverse_payment')
          ? (payment) => confirmAndRun(context, () async {
                await ref.read(financeApiProvider).reverseInvoicePayment(invoice.id, payment.id);
                await _load();
              })
          : null,
    );
    final wide = MediaQuery.sizeOf(context).width >= 1100;
    if (!wide) {
      return Column(
        children: [
          InvoiceTotalsCard(invoice: invoice),
          const SizedBox(height: 12),
          payment,
          const SizedBox(height: 12),
          notes,
          const SizedBox(height: 12),
          attachments,
        ],
      );
    }
    return Column(
      children: [
        InvoiceTwoColumn(
          leading: InvoiceTotalsCard(invoice: invoice),
          trailing: payment,
        ),
        const SizedBox(height: 12),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: attachments),
            const SizedBox(width: 12),
            Expanded(child: notes),
          ],
        ),
      ],
    );
  }

  Widget _relatedDocuments(BuildContext context, AppLocalizations l, InvoiceRecord invoice) {
    return InvoiceSectionCard(
      title: l.relatedDocuments,
      icon: Icons.account_tree_outlined,
      child: Column(
        children: [
          for (final note in invoice.creditNotes)
            ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              title: Text(note.noteNumber ?? '#${note.id}'),
              subtitle: Text('${note.type} · ${note.status}'),
              trailing: Text(note.total, style: const TextStyle(fontWeight: FontWeight.w800)),
              onTap: () => context.push('/notes/${note.id}'),
            ),
          for (final receipt in invoice.receipts)
            ListTile(
              dense: true,
              contentPadding: EdgeInsets.zero,
              title: Text(receipt.receiptNumber ?? '#${receipt.id}'),
              trailing: Text(receipt.amount, style: const TextStyle(fontWeight: FontWeight.w800)),
              onTap: () => context.push('/receipts/${receipt.id}'),
            ),
        ],
      ),
    );
  }

  Widget _toolbar(BuildContext context, AppLocalizations l, FinancePermissions p, InvoiceRecord invoice) {
    final moreItems = <PopupMenuEntry<String>>[
      if (invoice.documentStatus == 'draft' && p.can('invoices.edit')) PopupMenuItem(value: 'edit', child: Text(l.edit)),
      if (invoice.documentStatus == 'issued' && p.can('invoices.remind')) PopupMenuItem(value: 'remind', child: Text(l.remind)),
      if (invoice.documentStatus == 'issued' && invoice.paymentStatus != 'paid' && p.paymentsManage)
        PopupMenuItem(value: 'checkout', child: Text(l.createCheckout)),
      if (invoice.checkout?.hasUrl == true) PopupMenuItem(value: 'copy', child: Text(l.copy)),
      if (invoice.checkout?.hasUrl == true) PopupMenuItem(value: 'open', child: Text(l.open)),
      if (invoice.documentStatus == 'issued' && p.notesCreate) PopupMenuItem(value: 'credit', child: Text(l.creditNoteFromInvoice)),
      if (invoice.documentStatus != 'cancelled' && p.can('invoices.cancel')) PopupMenuItem(value: 'cancel', child: Text(l.cancel)),
      if (invoice.documentStatus == 'draft' && p.invoicesDelete) PopupMenuItem(value: 'delete', child: Text(l.deleteDraft)),
    ];
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      alignment: WrapAlignment.end,
      children: [
        InvoiceActionButton(
          label: l.goBack,
          icon: Icons.arrow_forward_rounded,
          onPressed: () {
            if (Navigator.of(context).canPop()) {
              Navigator.of(context).maybePop();
            } else {
              context.go('/invoices');
            }
          },
        ),
        if (invoice.documentStatus == 'draft' && p.can('invoices.issue'))
          InvoiceActionButton(label: l.issue, icon: Icons.check_circle_outline, filled: true, onPressed: () => confirmAndRun(context, () => _act('issue'))),
        if (invoice.documentStatus == 'issued' && p.can('invoices.send'))
          InvoiceActionButton(label: l.send, icon: Icons.send_outlined, filled: true, onPressed: () => promptEmailAndSend(context, (email) => _act('send', body: {'email': email}))),
        InvoiceActionButton(label: l.downloadPdf, icon: Icons.download_outlined, onPressed: _openPdf),
        InvoiceActionButton(label: l.printDocument, icon: Icons.print_outlined, onPressed: _openPdf),
        if (invoice.documentStatus == 'issued' && invoice.paymentStatus != 'paid' && p.paymentsManage)
          InvoiceActionButton(label: l.recordPayment, icon: Icons.payments_outlined, onPressed: _manualPayment),
        if (moreItems.isNotEmpty)
          PopupMenuButton<String>(
            tooltip: l.more,
            onSelected: (value) async {
              switch (value) {
                case 'edit':
                  context.push('/invoices/${invoice.id}/edit');
                case 'remind':
                  await promptEmailAndSend(context, (email) => _act('remind', body: {'email': email}));
                case 'checkout':
                  await _checkout();
                case 'copy':
                  await copyText(invoice.checkout!.checkoutUrl!);
                  if (context.mounted) showSnack(context, l.copied);
                case 'open':
                  await openExternalUrl(invoice.checkout!.checkoutUrl!);
                  await _load();
                case 'credit':
                  context.push('/notes/new?invoice_id=${invoice.id}');
                case 'cancel':
                  await confirmAndRun(context, () => _act('cancel'));
                case 'delete':
                  await confirmAndRun(context, () async {
                    await ref.read(financeApiProvider).deleteInvoice(invoice.id);
                    if (context.mounted) context.go('/invoices');
                  });
              }
            },
            itemBuilder: (_) => moreItems,
            child: IgnorePointer(
              child: InvoiceActionButton(label: l.more, icon: Icons.more_horiz, onPressed: () {}),
            ),
          ),
      ],
    );
  }
}

class InvoiceFormScreen extends ConsumerStatefulWidget {
  const InvoiceFormScreen({super.key, this.id});
  final int? id;

  @override
  ConsumerState<InvoiceFormScreen> createState() => _InvoiceFormScreenState();
}

class _InvoiceFormScreenState extends ConsumerState<InvoiceFormScreen> {
  int? _customerId;
  int? _contractId;
  int? _projectId;
  bool _walkIn = false;
  String _subtype = 'standard';
  String _zatca = 'not_required';
  String _taxProfile = 'standard';
  String _taxMode = 'exclusive';
  final _walkInName = TextEditingController();
  final _issueDate = TextEditingController(text: isoDate());
  final _dueDate = TextEditingController(text: isoDate(DateTime.now().add(const Duration(days: 14))));
  final _currency = TextEditingController(text: 'SAR');
  final _paymentTerms = TextEditingController();
  final _taxRate = TextEditingController(text: '15');
  final _notes = TextEditingController();
  final _invoiceNumber = TextEditingController();
  final List<LineDraft> _lines = [LineDraft(description: 'خدمة فوترة', unitPrice: '100')];
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    if (widget.id != null) {
      ref.read(financeApiProvider).invoice(widget.id!).then((invoice) {
        if (!mounted) return;
        _customerId = invoice.customerId;
        _walkIn = invoice.customerId == null;
        _walkInName.text = invoice.customerName ?? '';
        _notes.text = invoice.notes ?? '';
        _paymentTerms.text = invoice.paymentTerms ?? '';
        _currency.text = invoice.currency;
        _taxRate.text = invoice.taxRate ?? '15';
        _taxProfile = invoice.taxProfileType ?? 'standard';
        _taxMode = invoice.taxPriceMode ?? 'exclusive';
        _subtype = invoice.zatcaSubtype ?? 'standard';
        _zatca = invoice.zatcaRequirement ?? 'not_required';
        _contractId = invoice.contractId;
        _projectId = invoice.projectId;
        _invoiceNumber.text = invoice.invoiceNumber ?? '';
        if (invoice.issueDate != null) _issueDate.text = invoice.issueDate!.substring(0, 10);
        if (invoice.dueDate != null) _dueDate.text = invoice.dueDate!.substring(0, 10);
        if (invoice.lines.isNotEmpty) {
          for (final line in _lines) {
            line.dispose();
          }
          _lines
            ..clear()
            ..addAll(invoice.lines.map(LineDraft.fromItem));
        }
        setState(() {});
      });
    }
  }

  @override
  void dispose() {
    _walkInName.dispose();
    _issueDate.dispose();
    _dueDate.dispose();
    _currency.dispose();
    _paymentTerms.dispose();
    _taxRate.dispose();
    _notes.dispose();
    _invoiceNumber.dispose();
    for (final line in _lines) {
      line.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (!_walkIn && _customerId == null) return;
    if (_walkIn && _walkInName.text.trim().isEmpty) return;
    setState(() => _busy = true);
    try {
      final saved = await ref.read(financeApiProvider).saveInvoice({
        if (!_walkIn) 'customer_id': _customerId,
        if (_walkIn) 'customer_name': _walkInName.text.trim(),
        'issue_date': _issueDate.text.trim(),
        'due_date': _dueDate.text.trim(),
        'currency': _currency.text.trim(),
        'payment_terms': _paymentTerms.text.trim(),
        'notes': _notes.text.trim(),
        'invoice_status': 'draft',
        'tax_profile_type': _taxProfile,
        'tax_rate': _taxRate.text.trim(),
        'tax_price_mode': _taxMode,
        'tax_document_subtype': _subtype,
        'zatca_requirement': _zatca,
        if (_invoiceNumber.text.trim().isNotEmpty) 'invoice_number': _invoiceNumber.text.trim(),
        if (_contractId != null) 'contract_id': _contractId,
        if (_projectId != null) 'project_id': _projectId,
        'items': _lines.map((line) => line.toPayload()).toList(),
      }, id: widget.id);
      if (!mounted) return;
      context.go('/invoices/${saved.id}');
    } catch (e) {
      if (mounted) showFormError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final catalog = ref.watch(financeCatalogProvider).valueOrNull ?? const FinanceCatalog();
    return FinanceScaffold(
      title: l.invoices,
      showBack: true,
      body: FinancePage(
        child: ListView(
        padding: EdgeInsets.zero,
        children: [
          FormSection(
            title: l.headerSection,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(l.walkInCustomer),
                  value: _walkIn,
                  onChanged: (v) => setState(() => _walkIn = v),
                ),
                if (_walkIn)
                  TextField(controller: _walkInName, decoration: InputDecoration(labelText: l.walkInName))
                else
                  CustomerSelectField(selectedId: _customerId, onSelected: (id) => setState(() => _customerId = id)),
                FormGrid(children: [
                  if (catalog.allowManualInvoiceNumbers || _invoiceNumber.text.isNotEmpty)
                    TextField(controller: _invoiceNumber, decoration: InputDecoration(labelText: l.invoiceNumber)),
                  DropdownButtonFormField(
                    // ignore: deprecated_member_use
                    value: _subtype,
                    decoration: InputDecoration(labelText: l.taxDocumentSubtype),
                    items: [
                      DropdownMenuItem(value: 'standard', child: Text(l.standardTax)),
                      DropdownMenuItem(value: 'simplified', child: Text(l.simplifiedTax)),
                    ],
                    onChanged: (v) => setState(() => _subtype = v ?? 'standard'),
                  ),
                  DropdownButtonFormField(
                    // ignore: deprecated_member_use
                    value: _zatca,
                    decoration: InputDecoration(labelText: l.zatcaRequirement),
                    items: [
                      DropdownMenuItem(value: 'not_required', child: Text(l.notRequired)),
                      DropdownMenuItem(value: 'required', child: Text(l.requiredLater)),
                    ],
                    onChanged: (v) => setState(() => _zatca = v ?? 'not_required'),
                  ),
                  OptionPicker(
                    label: l.contract,
                    options: [for (final row in catalog.contracts) NamedOption(id: row.id, name: row.name)],
                    value: _contractId,
                    onChanged: (id) => setState(() => _contractId = id),
                  ),
                  OptionPicker(
                    label: l.project,
                    options: [for (final row in catalog.projects) NamedOption(id: row.id, name: row.name)],
                    value: _projectId,
                    onChanged: (id) => setState(() => _projectId = id),
                  ),
                ]),
              ],
            ),
          ),
          FormSection(
            title: l.datesSection,
            child: FormGrid(children: [
              TextField(controller: _issueDate, decoration: InputDecoration(labelText: l.issueDate)),
              TextField(controller: _dueDate, decoration: InputDecoration(labelText: l.dueDate)),
              TextField(controller: _currency, decoration: InputDecoration(labelText: l.currency)),
              TextField(controller: _paymentTerms, decoration: InputDecoration(labelText: l.paymentTerms)),
            ]),
          ),
          FormSection(
            title: l.taxSection,
            child: FormGrid(children: [
              DropdownButtonFormField(
                // ignore: deprecated_member_use
                value: _taxProfile,
                decoration: InputDecoration(labelText: l.taxProfile),
                items: [
                  DropdownMenuItem(value: 'standard', child: Text(l.standardTax)),
                  DropdownMenuItem(value: 'zero_rated', child: Text(l.zeroRated)),
                  DropdownMenuItem(value: 'exempt', child: Text(l.exempt)),
                  DropdownMenuItem(value: 'out_of_scope', child: Text(l.outOfScope)),
                ],
                onChanged: (v) => setState(() => _taxProfile = v ?? 'standard'),
              ),
              DropdownButtonFormField(
                // ignore: deprecated_member_use
                value: _taxMode,
                decoration: InputDecoration(labelText: l.taxPriceMode),
                items: [
                  DropdownMenuItem(value: 'exclusive', child: Text(l.exclusive)),
                  DropdownMenuItem(value: 'inclusive', child: Text(l.inclusive)),
                ],
                onChanged: (v) => setState(() => _taxMode = v ?? 'exclusive'),
              ),
              TextField(controller: _taxRate, decoration: InputDecoration(labelText: l.taxRate)),
            ]),
          ),
          FormSection(
            title: l.itemsSection,
            child: DocumentLinesEditor(lines: _lines, products: catalog.products, onChanged: () => setState(() {})),
          ),
          FormSection(
            title: l.notesSection,
            child: TextField(controller: _notes, decoration: InputDecoration(labelText: l.notesField), maxLines: 3),
          ),
          FilledButton(onPressed: _busy ? null : _save, child: Text(l.save)),
        ],
      ),
      ),
    );
  }
}
