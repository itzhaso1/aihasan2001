import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim_finance/core/theme/theme_controllers.dart';
import 'package:hasim_finance/core/layout/finance_layout.dart';
import 'package:hasim_finance/core/providers/catalog_provider.dart';
import 'package:hasim_finance/core/auth/auth_controller.dart';
import 'package:hasim_finance/core/models/models.dart';
import 'package:hasim_finance/core/network/api_exception.dart';
import 'package:hasim_finance/core/utils/files.dart';
import 'package:hasim_finance/core/widgets/widgets.dart';
import 'package:hasim_finance/features/shared/customer_select.dart';
import 'package:hasim_finance/features/shared/document_lines_editor.dart';
import 'package:hasim_finance/features/shared/paged.dart';
import 'package:hasim_finance/features/shared/supplier_select.dart';
import 'package:hasim_finance/features/reports/report_view.dart';
import 'package:hasim_finance/l10n/app_localizations.dart';

class PaymentsScreen extends ConsumerWidget {
  const PaymentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<PaymentRecord>(
      title: l.payments,
      allowed: auth.permissions.paymentsView,
      loader: (api, search, page) => api.payments(search: search, page: page),
      itemBuilder: (context, payment) => Card(
        child: ListTile(
          title: Text('${payment.amount} · ${payment.method ?? ''}'),
          subtitle: Text('${payment.invoiceNumber ?? ''} · ${payment.customerName ?? ''} · ${payment.status}'),
          onTap: () => context.push('/payments/${payment.id}'),
        ),
      ),
    );
  }
}

class PaymentDetailScreen extends ConsumerStatefulWidget {
  const PaymentDetailScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<PaymentDetailScreen> createState() => _PaymentDetailScreenState();
}

class _PaymentDetailScreenState extends ConsumerState<PaymentDetailScreen> {
  PaymentRecord? _data;
  bool _loading = true;
  String? _error;

  Future<void> _load() async {
    try {
      final data = await ref.read(financeApiProvider).payment(widget.id);
      setState(() {
        _data = data;
        _loading = false;
        _error = null;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = _data;
    return DetailScaffold(
      title: l.payments,
      loading: _loading,
      error: _error,
      onRetry: _load,
      child: p == null
          ? const SizedBox.shrink()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                StatusChip(label: p.status ?? '', tone: toneFor(p.status)),
                ListTile(title: Text(l.amount), trailing: Text(p.amount)),
                ListTile(title: Text(l.method), trailing: Text(p.method ?? '')),
                ListTile(title: Text(l.reference), trailing: Text(p.reference ?? '')),
                InfoRow(label: l.paymentDate, value: p.paymentDate),
                InfoRow(label: l.notesField, value: p.notes),
                InfoRow(label: l.treasuryAccount, value: p.treasuryAccountName),
                InfoRow(label: l.reversed, value: p.reversedAt),
                InfoRow(label: l.reason, value: p.reversalReason),
                if (p.invoiceId != null) TextButton(onPressed: () => context.push('/invoices/${p.invoiceId}'), child: Text(p.invoiceNumber ?? l.invoices)),
                if (p.receiptId != null) TextButton(onPressed: () => context.push('/receipts/${p.receiptId}'), child: Text(p.receiptNumber ?? l.receipts)),
                if (p.status == 'posted' && ref.watch(authControllerProvider).permissions.can('invoices.reverse_payment'))
                  FilledButton(
                    onPressed: () => confirmAndRun(context, () async {
                      await ref.read(financeApiProvider).reversePayment(p.id, reason: 'reversed from finance app');
                      await _load();
                    }),
                    child: Text(l.reversePayment),
                  ),
              ],
            ),
    );
  }
}

class ReceiptsScreen extends ConsumerWidget {
  const ReceiptsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<ReceiptRecord>(
      title: l.receipts,
      allowed: auth.permissions.receiptsView,
      loader: (api, search, page) => api.receipts(search: search, page: page),
      itemBuilder: (context, receipt) => Card(
        child: ListTile(
          title: Text(receipt.receiptNumber ?? '#${receipt.id}'),
          subtitle: Text('${receipt.customerName ?? ''} · ${receipt.status}'),
          trailing: Text(receipt.amount),
          onTap: () => context.push('/receipts/${receipt.id}'),
        ),
      ),
    );
  }
}

class ReceiptDetailScreen extends ConsumerStatefulWidget {
  const ReceiptDetailScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<ReceiptDetailScreen> createState() => _ReceiptDetailScreenState();
}

class _ReceiptDetailScreenState extends ConsumerState<ReceiptDetailScreen> {
  ReceiptRecord? _data;
  bool _loading = true;
  String? _error;

  Future<void> _load() async {
    try {
      final data = await ref.read(financeApiProvider).receipt(widget.id);
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

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final r = _data;
    return DetailScaffold(
      title: r?.receiptNumber ?? l.receipts,
      loading: _loading,
      error: _error,
      onRetry: _load,
      child: r == null
          ? const SizedBox.shrink()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                StatusChip(label: r.status ?? '', tone: toneFor(r.status)),
                ListTile(title: Text(l.amount), trailing: Text(r.amount)),
                InfoRow(label: l.method, value: r.method),
                InfoRow(label: l.reference, value: r.reference),
                InfoRow(label: l.paymentDate, value: r.paymentDate),
                if (r.invoiceId != null)
                  TextButton(onPressed: () => context.push('/invoices/${r.invoiceId}'), child: Text(r.invoiceNumber ?? l.invoices)),
                DeliveryTimeline(deliveries: r.deliveries),
                Wrap(spacing: 8, children: [
                  FilledButton.tonal(
                    onPressed: () async {
                      final bytes = await ref.read(financeApiProvider).pdf('receipts/${r.id}/pdf');
                      await saveAndOpenBytes(bytes, 'receipt-${r.receiptNumber}.pdf');
                    },
                    child: Text(l.pdf),
                  ),
                  FilledButton(
                    onPressed: () => promptEmailAndSend(context, (email) async {
                      await ref.read(financeApiProvider).sendReceipt(r.id, {'email': email});
                      await _load();
                    }),
                    child: Text(l.send),
                  ),
                ]),
              ],
            ),
    );
  }
}

typedef StatementsScreen = StatementScreen;

class StatementScreen extends ConsumerStatefulWidget {
  const StatementScreen({super.key, this.customerId});
  final int? customerId;
  @override
  ConsumerState<StatementScreen> createState() => _StatementScreenState();
}

class _StatementScreenState extends ConsumerState<StatementScreen> {
  int? _customerId;
  late final TextEditingController _from;
  late final TextEditingController _to;
  StatementRecord? _data;
  String? _error;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _customerId = widget.customerId;
    final now = DateTime.now();
    _from = TextEditingController(text: DateTime(now.year, now.month, 1).toIso8601String().substring(0, 10));
    _to = TextEditingController(text: now.toIso8601String().substring(0, 10));
  }

  Future<void> _load() async {
    final customerId = _customerId;
    if (customerId == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref.read(financeApiProvider).statement(
            customerId: customerId,
            from: _from.text.trim(),
            to: _to.text.trim(),
          );
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

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PermissionGate(
      allowed: auth.permissions.statementsView,
      child: Scaffold(
        appBar: AppBar(title: Text(l.statements)),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            CustomerSelectField(selectedId: _customerId, onSelected: (id) => setState(() => _customerId = id)),
            TextField(controller: _from, decoration: InputDecoration(labelText: l.from)),
            TextField(controller: _to, decoration: InputDecoration(labelText: l.to)),
            const SizedBox(height: 8),
            FilledButton(onPressed: _loading || _customerId == null ? null : _load, child: Text(l.refresh)),
            if (_error != null) Text(_error!),
            if (_data != null) ...[
              const SizedBox(height: 16),
              Text('${l.customer}: ${_data!.customerName ?? ''}'),
              Text('${l.openingBalance}: ${_data!.openingBalance}'),
              Text('${l.closingBalance}: ${_data!.closingBalance}'),
              Text('${l.invoicesTotal}: ${_data!.invoicesTotal}'),
              Text('${l.paymentsTotal}: ${_data!.paymentsTotal}'),
              Text('${l.creditsTotal}: ${_data!.creditsTotal}'),
              Text('${l.debitsTotal}: ${_data!.debitsTotal}'),
              Card(
                child: SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: DataTable(
                    columns: [
                      DataColumn(label: Text(l.date)),
                      DataColumn(label: Text(l.description)),
                      DataColumn(label: Text(l.reference)),
                      DataColumn(label: Text(l.statementDebit)),
                      DataColumn(label: Text(l.statementCredit)),
                      DataColumn(label: Text(l.runningBalance)),
                    ],
                    rows: [
                      for (final line in _data!.lines)
                        DataRow(
                          cells: [
                            DataCell(Text('${line['date'] ?? ''}')),
                            DataCell(Text('${line['kind'] ?? ''} ${line['description'] ?? ''}')),
                            DataCell(Text('${line['reference'] ?? ''}')),
                            DataCell(Text('${line['debit'] ?? ''}')),
                            DataCell(Text('${line['credit'] ?? ''}')),
                            DataCell(Text('${line['balance'] ?? ''}')),
                          ],
                          onSelectChanged: line['invoice_id'] == null
                              ? null
                              : (_) => context.push('/invoices/${line['invoice_id']}'),
                        ),
                    ],
                  ),
                ),
              ),
              Wrap(spacing: 8, children: [
                FilledButton.tonal(
                  onPressed: () async {
                    final bytes = await ref.read(financeApiProvider).pdf('statements', query: {
                      'customer_id': _customerId,
                      'from': _from.text.trim(),
                      'to': _to.text.trim(),
                      'format': 'pdf',
                    });
                    await saveAndOpenBytes(bytes, 'statement.pdf');
                  },
                  child: Text(l.pdf),
                ),
                FilledButton.tonal(
                  onPressed: () async {
                    final bytes = await ref.read(financeApiProvider).pdf('statements', query: {
                      'customer_id': _customerId,
                      'from': _from.text.trim(),
                      'to': _to.text.trim(),
                      'format': 'csv',
                    });
                    await saveAndOpenBytes(bytes, 'statement.csv');
                  },
                  child: Text(l.csv),
                ),
              ]),
            ],
          ],
        ),
      ),
    );
  }
}

class NotesScreen extends ConsumerWidget {
  const NotesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<NoteRecord>(
      title: l.notes,
      allowed: auth.permissions.notesView,
      onCreate: auth.permissions.can('notes.create') ? () => context.push('/notes/new') : null,
      loader: (api, search, page) => api.notes(search: search, page: page),
      itemBuilder: (context, note) => Card(
        child: ListTile(
          title: Text(note.noteNumber ?? '#${note.id}'),
          subtitle: Text('${note.type} · ${note.status} · ${note.customerName ?? ''}'),
          trailing: Text(note.total),
          onTap: () => context.push('/notes/${note.id}'),
        ),
      ),
    );
  }
}

class NoteDetailScreen extends ConsumerStatefulWidget {
  const NoteDetailScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<NoteDetailScreen> createState() => _NoteDetailScreenState();
}

class _NoteDetailScreenState extends ConsumerState<NoteDetailScreen> {
  NoteRecord? _data;
  bool _loading = true;
  String? _error;

  Future<void> _load() async {
    try {
      final data = await ref.read(financeApiProvider).note(widget.id);
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

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final n = _data;
    return DetailScaffold(
      title: n?.noteNumber ?? l.notes,
      loading: _loading,
      error: _error,
      onRetry: _load,
      child: n == null
          ? const SizedBox.shrink()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                StatusChip(label: '${n.type} · ${n.status}', tone: toneFor(n.status)),
                InfoRow(label: l.reason, value: n.reason),
                InfoRow(label: l.notesField, value: n.notes),
                InfoRow(label: l.issueDate, value: n.issueDate),
                if (n.invoiceId != null)
                  TextButton(onPressed: () => context.push('/invoices/${n.invoiceId}'), child: Text(n.invoiceNumber ?? l.invoices)),
                TotalsCard(subtotal: n.subtotal, tax: n.taxAmount, total: n.total),
                LineTable(lines: n.lines),
                Wrap(spacing: 8, children: [
                  if (n.status == 'draft')
                    FilledButton(onPressed: () async {
                      await ref.read(financeApiProvider).noteAction(n.id, 'issue');
                      await _load();
                    }, child: Text(l.issue)),
                  if (n.status != 'cancelled')
                    OutlinedButton(onPressed: () async {
                      await ref.read(financeApiProvider).noteAction(n.id, 'cancel');
                      await _load();
                    }, child: Text(l.cancel)),
                  FilledButton.tonal(
                    onPressed: () async {
                      final bytes = await ref.read(financeApiProvider).pdf('credit-notes/${n.id}/pdf');
                      await saveAndOpenBytes(bytes, 'note-${n.noteNumber}.pdf');
                    },
                    child: Text(l.pdf),
                  ),
                ]),
              ],
            ),
    );
  }
}

class NoteFormScreen extends ConsumerStatefulWidget {
  const NoteFormScreen({super.key, this.invoiceId});
  final int? invoiceId;
  @override
  ConsumerState<NoteFormScreen> createState() => _NoteFormScreenState();
}

class _NoteFormScreenState extends ConsumerState<NoteFormScreen> {
  late final TextEditingController _invoiceId;
  final _reason = TextEditingController();
  final _issueDate = TextEditingController(text: isoDate());
  final List<LineDraft> _lines = [LineDraft(description: 'بند', unitPrice: '10')];
  String _type = 'credit';

  @override
  void initState() {
    super.initState();
    _invoiceId = TextEditingController(text: widget.invoiceId?.toString() ?? '');
  }

  @override
  void dispose() {
    _invoiceId.dispose();
    _reason.dispose();
    _issueDate.dispose();
    for (final line in _lines) {
      line.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final catalog = ref.watch(financeCatalogProvider).valueOrNull ?? const FinanceCatalog();
    return Scaffold(
      appBar: AppBar(title: Text(l.notes)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          FormSection(
            title: l.headerSection,
            child: FormGrid(children: [
              DropdownButtonFormField(
                // ignore: deprecated_member_use
                value: _type,
                items: [
                  DropdownMenuItem(value: 'credit', child: Text(l.credit)),
                  DropdownMenuItem(value: 'debit', child: Text(l.debit)),
                ],
                onChanged: (v) => setState(() => _type = v ?? 'credit'),
              ),
              TextField(controller: _invoiceId, decoration: InputDecoration(labelText: l.invoiceId)),
              TextField(controller: _issueDate, decoration: InputDecoration(labelText: l.issueDate)),
            ]),
          ),
          TextField(controller: _reason, decoration: InputDecoration(labelText: l.reason), maxLines: 2),
          FormSection(
            title: l.itemsSection,
            child: DocumentLinesEditor(lines: _lines, products: catalog.products, onChanged: () => setState(() {})),
          ),
          FilledButton(
            onPressed: () async {
              try {
                final saved = await ref.read(financeApiProvider).saveNote({
                  'invoice_id': int.parse(_invoiceId.text.trim()),
                  'type': _type,
                  'reason': _reason.text.trim(),
                  'issue_date': _issueDate.text.trim(),
                  'items': _lines.map((line) => line.toPayload()).toList(),
                });
                if (context.mounted) context.go('/notes/${saved.id}');
              } catch (e) {
                if (context.mounted) showFormError(context, e);
              }
            },
            child: Text(l.save),
          ),
        ],
      ),
    );
  }
}

class ContractsScreen extends ConsumerStatefulWidget {
  const ContractsScreen({super.key});

  @override
  ConsumerState<ContractsScreen> createState() => _ContractsScreenState();
}

class _ContractsScreenState extends ConsumerState<ContractsScreen> {
  String? _status;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<ContractRecord>(
      key: ValueKey(_status),
      title: l.contracts,
      allowed: auth.permissions.contractsView,
      onCreate: auth.permissions.can('contracts.create') ? () => context.push('/contracts/new') : null,
      filterBar: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Wrap(
          spacing: 8,
          children: [
            for (final option in <String?>[null, 'draft', 'open', 'closed', 'cancelled'])
              ChoiceChip(
                label: Text(option ?? l.filterAll),
                selected: _status == option,
                onSelected: (_) => setState(() => _status = option),
              ),
          ],
        ),
      ),
      loader: (api, search, page) => api.contracts(search: search, page: page, status: _status),
      itemBuilder: (context, contract) => Card(
        child: ListTile(
          title: Text(contract.title ?? contract.contractNumber ?? '#${contract.id}'),
          subtitle: Text('${contract.customerName ?? ''} · ${contract.status}'),
          trailing: Text(contract.value),
          onTap: () => context.push('/contracts/${contract.id}'),
        ),
      ),
    );
  }
}

class ContractDetailScreen extends ConsumerStatefulWidget {
  const ContractDetailScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<ContractDetailScreen> createState() => _ContractDetailScreenState();
}

class _ContractDetailScreenState extends ConsumerState<ContractDetailScreen> {
  ContractRecord? _data;
  bool _loading = true;
  String? _error;

  Future<void> _load() async {
    try {
      final data = await ref.read(financeApiProvider).contract(widget.id);
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

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final c = _data;
    return DetailScaffold(
      title: c?.title ?? l.contracts,
      loading: _loading,
      error: _error,
      onRetry: _load,
      child: c == null
          ? const SizedBox.shrink()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                StatusChip(label: c.status ?? '', tone: toneFor(c.status)),
                Text('${c.startDate} → ${c.endDate}'),
                Text(c.value),
                InfoRow(label: l.notesField, value: c.notes),
                InfoRow(label: l.terms, value: c.terms),
                if (c.billingSummary.isNotEmpty)
                  TotalsCard(
                    subtotal: '${c.billingSummary['invoiced_total'] ?? c.value}',
                    tax: '0.00',
                    total: '${c.billingSummary['invoiced_total'] ?? c.value}',
                    paid: '${c.billingSummary['paid_total'] ?? ''}',
                    due: '${c.billingSummary['outstanding'] ?? ''}',
                  ),
                if (c.items.isNotEmpty) ...[
                  Text(l.lines, style: Theme.of(context).textTheme.titleMedium),
                  for (final item in c.items)
                    ListTile(
                      title: Text(item.title ?? item.description ?? ''),
                      subtitle: Text('${l.quantity}: ${item.quantity} · ${l.price}: ${item.unitPrice}'),
                      trailing: Text(item.total ?? ''),
                    ),
                ],
                for (final s in c.scheduleRecords)
                  ListTile(
                    title: Text(s.title ?? l.billingSchedule),
                    subtitle: Text('${s.frequency} · ${s.status} · ${l.nextRun}: ${s.nextRunOn ?? ''} · ${l.autoIssue}: ${s.autoIssue}'),
                    trailing: Text(s.amount),
                    isThreeLine: true,
                    onTap: () {},
                  ),
                for (final s in c.scheduleRecords)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Wrap(
                      spacing: 8,
                      children: [
                        TextButton(
                          onPressed: () async {
                            try {
                              final invoice = await ref.read(financeApiProvider).generateScheduleInvoice(c.id, s.id);
                              await _load();
                              if (context.mounted) {
                                showSnack(context, invoice?.invoiceNumber ?? AppLocalizations.of(context).success);
                              }
                            } catch (e) {
                              if (context.mounted) showApiError(context, e);
                            }
                          },
                          child: Text(l.generateInvoice),
                        ),
                        if (s.status != 'active')
                          TextButton(
                            onPressed: () async {
                              try {
                                await ref.read(financeApiProvider).scheduleAction(c.id, s.id, 'activate');
                                await _load();
                              } catch (e) {
                                if (context.mounted) showApiError(context, e);
                              }
                            },
                            child: Text(l.activateSchedule),
                          ),
                        if (s.status == 'active')
                          TextButton(
                            onPressed: () async {
                              try {
                                await ref.read(financeApiProvider).scheduleAction(c.id, s.id, 'pause');
                                await _load();
                              } catch (e) {
                                if (context.mounted) showApiError(context, e);
                              }
                            },
                            child: Text(l.pauseSchedule),
                          ),
                        TextButton(
                          onPressed: () => confirmAndRun(context, () async {
                            await ref.read(financeApiProvider).scheduleAction(c.id, s.id, 'cancel');
                            await _load();
                          }),
                          child: Text(l.cancelSchedule),
                        ),
                      ],
                    ),
                  ),
                if (c.generatedInvoices.isNotEmpty) ...[
                  Text(l.generatedInvoices, style: Theme.of(context).textTheme.titleMedium),
                  for (final invoice in c.generatedInvoices)
                    ListTile(
                      title: Text(invoice.invoiceNumber ?? '#${invoice.id}'),
                      trailing: Text(invoice.total),
                      onTap: () => context.push('/invoices/${invoice.id}'),
                    ),
                ],
                Wrap(spacing: 8, children: [
                  if (c.status == 'draft') FilledButton(onPressed: () => context.push('/contracts/${c.id}/edit'), child: Text(l.edit)),
                  if (c.status == 'draft') FilledButton(onPressed: () async { await ref.read(financeApiProvider).contractAction(c.id, 'activate'); await _load(); }, child: Text(l.signContract)),
                  if (c.status == 'open') OutlinedButton(onPressed: () async { await ref.read(financeApiProvider).contractAction(c.id, 'close'); await _load(); }, child: Text(l.closeContract)),
                  OutlinedButton(onPressed: () async { await ref.read(financeApiProvider).contractAction(c.id, 'cancel'); await _load(); }, child: Text(l.cancel)),
                  FilledButton.tonal(
                    onPressed: () async {
                      try {
                        final bytes = await ref.read(financeApiProvider).pdf('contracts/${c.id}/pdf');
                        await saveAndOpenBytes(bytes, 'contract-${c.contractNumber}.pdf');
                      } catch (e) {
                        if (context.mounted) showApiError(context, e);
                      }
                    },
                    child: Text(l.pdf),
                  ),
                  FilledButton.tonal(
                    onPressed: () async {
                      final title = TextEditingController(text: l.billingSchedule);
                      final start = TextEditingController(text: isoDate());
                      String frequency = 'monthly';
                      final occurrences = TextEditingController(text: '12');
                      final ok = await showDialog<bool>(
                        context: context,
                        builder: (ctx) => AlertDialog(
                          title: Text(l.addSchedule),
                          content: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              TextField(controller: title, decoration: InputDecoration(labelText: l.fieldName)),
                              TextField(controller: start, decoration: InputDecoration(labelText: l.from)),
                              TextField(controller: occurrences, decoration: InputDecoration(labelText: l.generatedCount)),
                              DropdownButtonFormField(
                                // ignore: deprecated_member_use
                                value: frequency,
                                items: const [
                                  DropdownMenuItem(value: 'weekly', child: Text('weekly')),
                                  DropdownMenuItem(value: 'monthly', child: Text('monthly')),
                                  DropdownMenuItem(value: 'quarterly', child: Text('quarterly')),
                                  DropdownMenuItem(value: 'yearly', child: Text('yearly')),
                                ],
                                onChanged: (v) => frequency = v ?? 'monthly',
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
                        await ref.read(financeApiProvider).saveSchedule(c.id, {
                          'title': title.text.trim(),
                          'frequency': frequency,
                          'start_date': start.text.trim(),
                          'total_occurrences': int.tryParse(occurrences.text.trim()) ?? 12,
                        });
                        await _load();
                      } catch (e) {
                        if (context.mounted) showFormError(context, e);
                      }
                    },
                    child: Text(l.addSchedule),
                  ),
                ]),
              ],
            ),
    );
  }
}

class ContractFormScreen extends ConsumerStatefulWidget {
  const ContractFormScreen({super.key, this.id});
  final int? id;
  @override
  ConsumerState<ContractFormScreen> createState() => _ContractFormScreenState();
}

class _ContractFormScreenState extends ConsumerState<ContractFormScreen> {
  final _title = TextEditingController();
  int? _customerId;
  final _value = TextEditingController(text: '0');
  final _currency = TextEditingController(text: 'SAR');
  final _start = TextEditingController(text: isoDate());
  final _end = TextEditingController();
  final _notes = TextEditingController();
  final _terms = TextEditingController();

  @override
  void initState() {
    super.initState();
    if (widget.id != null) {
      ref.read(financeApiProvider).contract(widget.id!).then((c) {
        _title.text = c.title ?? '';
        _customerId = c.customerId;
        _value.text = c.value;
        _currency.text = c.currency;
        _start.text = c.startDate ?? isoDate();
        _end.text = c.endDate ?? '';
        _notes.text = c.notes ?? '';
        _terms.text = c.terms ?? '';
        if (mounted) setState(() {});
      });
    }
  }

  @override
  void dispose() {
    _title.dispose();
    _value.dispose();
    _currency.dispose();
    _start.dispose();
    _end.dispose();
    _notes.dispose();
    _terms.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l.contracts)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          FormSection(
            title: l.headerSection,
            child: Column(
              children: [
                TextField(controller: _title, decoration: InputDecoration(labelText: l.fieldName)),
                CustomerSelectField(selectedId: _customerId, onSelected: (id) => setState(() => _customerId = id)),
                FormGrid(children: [
                  TextField(controller: _value, decoration: InputDecoration(labelText: l.amount)),
                  TextField(controller: _currency, decoration: InputDecoration(labelText: l.currency)),
                  TextField(controller: _start, decoration: InputDecoration(labelText: l.from)),
                  TextField(controller: _end, decoration: InputDecoration(labelText: l.to)),
                ]),
              ],
            ),
          ),
          TextField(controller: _notes, decoration: InputDecoration(labelText: l.notesField), maxLines: 2),
          TextField(controller: _terms, decoration: InputDecoration(labelText: l.terms), maxLines: 2),
          FilledButton(
            onPressed: _customerId == null
                ? null
                : () async {
                    try {
                      final saved = await ref.read(financeApiProvider).saveContract({
                        'title': _title.text.trim(),
                        'customer_id': _customerId,
                        'value': _value.text.trim(),
                        'currency': _currency.text.trim(),
                        'start_date': _start.text.trim(),
                        'end_date': _end.text.trim(),
                        'notes': _notes.text.trim(),
                        'terms': _terms.text.trim(),
                      }, id: widget.id);
                      if (context.mounted) context.go('/contracts/${saved.id}');
                    } catch (e) {
                      if (context.mounted) showFormError(context, e);
                    }
                  },
            child: Text(l.save),
          ),
        ],
      ),
    );
  }
}

class ExpensesScreen extends ConsumerStatefulWidget {
  const ExpensesScreen({super.key});

  @override
  ConsumerState<ExpensesScreen> createState() => _ExpensesScreenState();
}

class _ExpensesScreenState extends ConsumerState<ExpensesScreen> {
  String? _status;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<ExpenseRecord>(
      key: ValueKey(_status),
      title: l.expenses,
      allowed: auth.permissions.expensesView,
      onCreate: auth.permissions.expensesCreate ? () => context.push('/expenses/new') : null,
      filterBar: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Wrap(
          spacing: 8,
          children: [
            for (final option in <String?>[null, 'draft', 'approved', 'paid', 'cancelled'])
              ChoiceChip(
                label: Text(option ?? l.filterAll),
                selected: _status == option,
                onSelected: (_) => setState(() => _status = option),
              ),
          ],
        ),
      ),
      loader: (api, search, page) => api.expenses(search: search, page: page, status: _status),
      itemBuilder: (context, expense) => Card(
        child: ListTile(
          title: Text(expense.description ?? expense.expenseNumber ?? '#${expense.id}'),
          subtitle: Text('${expense.categoryName ?? ''} · ${expense.status}'),
          trailing: Text(expense.total),
          onTap: () => context.push('/expenses/${expense.id}'),
        ),
      ),
    );
  }
}

class ExpenseFormScreen extends ConsumerStatefulWidget {
  const ExpenseFormScreen({super.key, this.id});
  final int? id;
  @override
  ConsumerState<ExpenseFormScreen> createState() => _ExpenseFormScreenState();
}

class _ExpenseFormScreenState extends ConsumerState<ExpenseFormScreen> {
  final _desc = TextEditingController();
  final _amount = TextEditingController();
  final _taxRate = TextEditingController(text: '15');
  final _date = TextEditingController(text: isoDate());
  String _method = 'cash';
  String _status = 'draft';
  String _taxProfile = 'standard';
  int? _supplierId;
  int? _categoryId;
  int? _treasuryId;
  bool _recurring = false;
  bool _busy = false;
  PlatformFile? _file;

  @override
  void initState() {
    super.initState();
    final id = widget.id;
    if (id != null) {
      ref.read(financeApiProvider).expense(id).then((expense) {
        if (!mounted) return;
        _desc.text = expense.description ?? '';
        _amount.text = expense.amount;
        _taxRate.text = expense.taxRate ?? '15';
        if (expense.expenseDate != null && expense.expenseDate!.length >= 10) {
          _date.text = expense.expenseDate!.substring(0, 10);
        }
        _method = expense.paymentMethod ?? 'cash';
        _status = expense.status ?? 'draft';
        _taxProfile = expense.taxProfileType ?? 'standard';
        _supplierId = expense.supplierId;
        _categoryId = expense.categoryId;
        _treasuryId = expense.treasuryAccountId;
        _recurring = expense.isRecurring;
        setState(() {});
      });
    }
  }

  @override
  void dispose() {
    _desc.dispose();
    _amount.dispose();
    _taxRate.dispose();
    _date.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final catalog = ref.watch(financeCatalogProvider).valueOrNull ?? const FinanceCatalog();
    return Scaffold(
      appBar: AppBar(title: Text(l.expenses)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          FormSection(
            title: l.headerSection,
            child: FormGrid(children: [
              TextField(controller: _desc, decoration: InputDecoration(labelText: l.description)),
              TextField(controller: _amount, decoration: InputDecoration(labelText: l.amount)),
              TextField(controller: _date, decoration: InputDecoration(labelText: l.date)),
              DropdownButtonFormField(
                // ignore: deprecated_member_use
                value: _method,
                decoration: InputDecoration(labelText: l.method),
                items: [
                  DropdownMenuItem(value: 'cash', child: Text(l.methodCash)),
                  DropdownMenuItem(value: 'bank_transfer', child: Text(l.methodBank)),
                  DropdownMenuItem(value: 'card', child: Text(l.methodCard)),
                  DropdownMenuItem(value: 'other', child: Text(l.methodOther)),
                  DropdownMenuItem(value: 'credit', child: Text(l.methodCredit)),
                ],
                onChanged: (v) => setState(() => _method = v ?? 'cash'),
              ),
              DropdownButtonFormField(
                // ignore: deprecated_member_use
                value: _status,
                decoration: InputDecoration(labelText: l.status),
                items: [
                  DropdownMenuItem(value: 'draft', child: Text(l.draft)),
                  DropdownMenuItem(value: 'approved', child: Text(l.accepted)),
                  DropdownMenuItem(value: 'paid', child: Text(l.paid)),
                ],
                onChanged: (v) => setState(() => _status = v ?? 'draft'),
              ),
              OptionPicker(
                label: l.category,
                options: [for (final row in catalog.expenseCategories) NamedOption(id: row.id, name: row.name)],
                value: _categoryId,
                onChanged: (id) => setState(() => _categoryId = id),
              ),
              OptionPicker(
                label: l.treasuryAccount,
                options: [for (final row in catalog.treasuryAccounts) NamedOption(id: row.id, name: row.name)],
                value: _treasuryId,
                onChanged: (id) => setState(() => _treasuryId = id),
              ),
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
              TextField(controller: _taxRate, decoration: InputDecoration(labelText: l.taxRate)),
            ]),
          ),
          SupplierSelectField(selectedId: _supplierId, onSelected: (id) => setState(() => _supplierId = id)),
          SwitchListTile(
            title: Text(l.recurring),
            value: _recurring,
            onChanged: (v) => setState(() => _recurring = v),
          ),
          OutlinedButton(
            onPressed: () async {
              final result = await FilePicker.platform.pickFiles(
                type: FileType.custom,
                allowedExtensions: const ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
              );
              if (result != null && result.files.isNotEmpty) {
                setState(() => _file = result.files.first);
              }
            },
            child: Text(_file?.name ?? l.attachment),
          ),
          FilledButton(
            onPressed: _busy
                ? null
                : () async {
                    setState(() => _busy = true);
                    try {
                      late final ExpenseRecord saved;
                      final payload = <String, dynamic>{
                        'description': _desc.text.trim(),
                        'amount': _amount.text.trim(),
                        'expense_date': _date.text.trim(),
                        'status': _status,
                        'tax_rate': _taxRate.text.trim(),
                        'tax_profile_type': _taxProfile,
                        'is_recurring': _recurring,
                        'payment_method': _method,
                        if (_supplierId != null) 'supplier_id': _supplierId,
                        if (_categoryId != null) 'category_id': _categoryId,
                        if (_treasuryId != null) 'treasury_account_id': _treasuryId,
                      };
                      final picked = _file;
                      if (picked != null && widget.id == null) {
                        final part = await multipartFromPicked(picked);
                        if (part != null) {
                          saved = await ref.read(financeApiProvider).saveExpense(
                            {},
                            form: FormData.fromMap({
                              ...payload,
                              'attachment_file': part,
                            }),
                          );
                        } else {
                          saved = await ref.read(financeApiProvider).saveExpense(payload, id: widget.id);
                        }
                      } else {
                        saved = await ref.read(financeApiProvider).saveExpense(payload, id: widget.id);
                      }
                      if (context.mounted) context.go('/expenses/${saved.id}');
                    } catch (e) {
                      if (context.mounted) showFormError(context, e);
                    } finally {
                      if (mounted) setState(() => _busy = false);
                    }
                  },
            child: Text(l.save),
          ),
        ],
      ),
    );
  }
}

class ExpenseDetailScreen extends ConsumerStatefulWidget {
  const ExpenseDetailScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<ExpenseDetailScreen> createState() => _ExpenseDetailScreenState();
}

class _ExpenseDetailScreenState extends ConsumerState<ExpenseDetailScreen> {
  ExpenseRecord? _data;
  bool _loading = true;
  String? _error;

  Future<void> _load() async {
    try {
      final data = await ref.read(financeApiProvider).expense(widget.id);
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

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final e = _data;
    return DetailScaffold(
      title: e?.expenseNumber ?? l.expenses,
      loading: _loading,
      error: _error,
      onRetry: _load,
      child: e == null
          ? const SizedBox.shrink()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                StatusChip(label: e.status ?? '', tone: toneFor(e.status)),
                Text(e.description ?? ''),
                TotalsCard(subtotal: e.amount, tax: e.taxAmount, total: e.total),
                InfoRow(label: l.supplier, value: e.supplierName),
                InfoRow(label: l.category, value: e.categoryName),
                InfoRow(label: l.method, value: e.paymentMethod),
                InfoRow(label: l.treasuryAccount, value: e.treasuryAccountName),
                InfoRow(label: l.taxRate, value: e.taxRate),
                InfoRow(label: l.recurring, value: e.isRecurring ? l.recurring : null),
                if (e.hasAttachment)
                  FilledButton.tonal(
                    onPressed: () async {
                      try {
                        final bytes = await ref.read(financeApiProvider).expenseAttachment(e.id);
                        await saveAndOpenBytes(bytes, 'expense-${e.id}');
                      } catch (err) {
                        if (context.mounted) showApiError(context, err);
                      }
                    },
                    child: Text(l.downloadAttachment),
                  ),
                if (e.status == 'draft' && ref.watch(authControllerProvider).permissions.expensesCreate)
                  FilledButton(onPressed: () => context.push('/expenses/${e.id}/edit'), child: Text(l.edit)),
                if (e.status == 'draft' && ref.watch(authControllerProvider).permissions.can('expenses.edit'))
                  OutlinedButton(
                    onPressed: () => confirmAndRun(context, () async {
                      await ref.read(financeApiProvider).deleteExpense(e.id);
                      if (context.mounted) context.go('/expenses');
                    }),
                    child: Text(l.delete),
                  ),
                if (e.status != 'draft') Text('لا يمكن تعديل مصروف مرحّل من التطبيق.'),
              ],
            ),
    );
  }
}

class PurchasesScreen extends ConsumerStatefulWidget {
  const PurchasesScreen({super.key});

  @override
  ConsumerState<PurchasesScreen> createState() => _PurchasesScreenState();
}

class _PurchasesScreenState extends ConsumerState<PurchasesScreen> {
  String? _lifecycle;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<InvoiceRecord>(
      key: ValueKey(_lifecycle),
      title: l.purchases,
      allowed: auth.permissions.purchasesView,
      onCreate: auth.permissions.purchasesCreate ? () => context.push('/purchases/new') : null,
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
      loader: (api, search, page) => api.purchases(search: search, page: page, lifecycle: _lifecycle),
      itemBuilder: (context, invoice) => Card(
        child: ListTile(
          title: Text(invoice.invoiceNumber ?? '#${invoice.id}'),
          subtitle: Text('${invoice.supplierName ?? ''} · ${invoice.documentStatus} · ${invoice.paymentStatus}'),
          trailing: Text(invoice.amountDue),
          onTap: () => context.push('/purchases/${invoice.id}'),
        ),
      ),
    );
  }
}

class PurchaseDetailScreen extends ConsumerStatefulWidget {
  const PurchaseDetailScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<PurchaseDetailScreen> createState() => _PurchaseDetailScreenState();
}

class _PurchaseDetailScreenState extends ConsumerState<PurchaseDetailScreen> {
  InvoiceRecord? _data;
  bool _loading = true;
  String? _error;

  Future<void> _load() async {
    try {
      final data = await ref.read(financeApiProvider).purchase(widget.id);
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

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final invoice = _data;
    return DetailScaffold(
      title: invoice?.invoiceNumber ?? l.purchases,
      loading: _loading,
      error: _error,
      onRetry: _load,
      child: invoice == null
          ? const SizedBox.shrink()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                DocumentHeader(number: invoice.invoiceNumber ?? '', documentStatus: invoice.documentStatus, paymentStatus: invoice.paymentStatus, customer: invoice.supplierName),
                TotalsCard(
                  subtotal: invoice.subtotal,
                  discount: invoice.discount,
                  taxable: invoice.taxableAmount,
                  tax: invoice.taxAmount,
                  total: invoice.total,
                  paid: invoice.amountPaid,
                  due: invoice.amountDue,
                ),
                LineTable(lines: invoice.lines),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  if (invoice.documentStatus == 'draft' && ref.watch(authControllerProvider).permissions.purchasesManage)
                    FilledButton(onPressed: () => context.push('/purchases/${invoice.id}/edit'), child: Text(l.edit)),
                  if (invoice.documentStatus == 'draft' && ref.watch(authControllerProvider).permissions.purchasesManage)
                    FilledButton(
                      onPressed: () async {
                        try {
                          await ref.read(financeApiProvider).purchaseAction(invoice.id, 'issue');
                          await _load();
                          if (context.mounted) showSnack(context, l.success);
                        } catch (e) {
                          if (context.mounted) showApiError(context, e);
                        }
                      },
                      child: Text(l.issue),
                    ),
                  if (invoice.documentStatus != 'cancelled' && ref.watch(authControllerProvider).permissions.purchasesManage)
                    OutlinedButton(
                      onPressed: () => confirmAndRun(context, () async {
                        await ref.read(financeApiProvider).purchaseAction(invoice.id, 'cancel');
                        await _load();
                      }),
                      child: Text(l.cancel),
                    ),
                  FilledButton.tonal(
                    onPressed: () async {
                      try {
                        final bytes = await ref.read(financeApiProvider).pdf('purchases/${invoice.id}/pdf');
                        await saveAndOpenBytes(bytes, 'purchase-${invoice.invoiceNumber}.pdf');
                      } catch (e) {
                        if (context.mounted) showApiError(context, e);
                      }
                    },
                    child: Text(l.pdf),
                  ),
                ]),
              ],
            ),
    );
  }
}

class PurchaseFormScreen extends ConsumerStatefulWidget {
  const PurchaseFormScreen({super.key, this.id});
  final int? id;
  @override
  ConsumerState<PurchaseFormScreen> createState() => _PurchaseFormScreenState();
}

class _PurchaseFormScreenState extends ConsumerState<PurchaseFormScreen> {
  int? _supplierId;
  int? _projectId;
  int? _contractId;
  String _taxProfile = 'standard';
  String _taxMode = 'exclusive';
  final _issueDate = TextEditingController(text: isoDate());
  final _dueDate = TextEditingController(text: isoDate(DateTime.now().add(const Duration(days: 14))));
  final _currency = TextEditingController(text: 'SAR');
  final _taxRate = TextEditingController(text: '15');
  final _notes = TextEditingController();
  final List<LineDraft> _lines = [LineDraft(description: 'بند مشتريات', unitPrice: '50')];
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    if (widget.id != null) {
      ref.read(financeApiProvider).purchase(widget.id!).then((invoice) {
        _supplierId = invoice.supplierId;
        _projectId = invoice.projectId;
        _contractId = invoice.contractId;
        _notes.text = invoice.notes ?? '';
        _currency.text = invoice.currency;
        _taxRate.text = invoice.taxRate ?? '15';
        _taxProfile = invoice.taxProfileType ?? 'standard';
        _taxMode = invoice.taxPriceMode ?? 'exclusive';
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
        if (mounted) setState(() {});
      });
    }
  }

  @override
  void dispose() {
    _issueDate.dispose();
    _dueDate.dispose();
    _currency.dispose();
    _taxRate.dispose();
    _notes.dispose();
    for (final line in _lines) {
      line.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final catalog = ref.watch(financeCatalogProvider).valueOrNull ?? const FinanceCatalog();
    return Scaffold(
      appBar: AppBar(title: Text(l.purchases)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          FormSection(
            title: l.headerSection,
            child: Column(
              children: [
                SupplierSelectField(selectedId: _supplierId, onSelected: (id) => setState(() => _supplierId = id)),
                FormGrid(children: [
                  OptionPicker(
                    label: l.project,
                    options: [for (final row in catalog.projects) NamedOption(id: row.id, name: row.name)],
                    value: _projectId,
                    onChanged: (id) => setState(() => _projectId = id),
                  ),
                  OptionPicker(
                    label: l.contract,
                    options: [for (final row in catalog.contracts) NamedOption(id: row.id, name: row.name)],
                    value: _contractId,
                    onChanged: (id) => setState(() => _contractId = id),
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
          TextField(controller: _notes, decoration: InputDecoration(labelText: l.notesField), maxLines: 3),
          FilledButton(
            onPressed: _busy || _supplierId == null
                ? null
                : () async {
                    setState(() => _busy = true);
                    try {
                      final saved = await ref.read(financeApiProvider).savePurchase({
                        'supplier_id': _supplierId,
                        'issue_date': _issueDate.text.trim(),
                        'due_date': _dueDate.text.trim(),
                        'currency': _currency.text.trim(),
                        'notes': _notes.text.trim(),
                        'invoice_status': 'draft',
                        'tax_profile_type': _taxProfile,
                        'tax_rate': _taxRate.text.trim(),
                        'tax_price_mode': _taxMode,
                        if (_projectId != null) 'project_id': _projectId,
                        if (_contractId != null) 'contract_id': _contractId,
                        'items': _lines.map((line) => line.toPayload()).toList(),
                      }, id: widget.id);
                      if (context.mounted) context.go('/purchases/${saved.id}');
                    } catch (e) {
                      if (context.mounted) showFormError(context, e);
                    } finally {
                      if (mounted) setState(() => _busy = false);
                    }
                  },
            child: Text(l.save),
          ),
        ],
      ),
    );
  }
}

class ReportsScreen extends ConsumerStatefulWidget {
  const ReportsScreen({super.key});
  @override
  ConsumerState<ReportsScreen> createState() => _ReportsScreenState();
}

class _ReportsScreenState extends ConsumerState<ReportsScreen> {
  String _key = 'profit-loss';
  Map<String, dynamic>? _data;
  bool _loading = false;
  String? _error;
  late final TextEditingController _from;
  late final TextEditingController _to;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _from = TextEditingController(text: DateTime(now.year, now.month, 1).toIso8601String().substring(0, 10));
    _to = TextEditingController(text: now.toIso8601String().substring(0, 10));
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) _load();
    });
  }

  @override
  void dispose() {
    _from.dispose();
    _to.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final data = await ref.read(financeApiProvider).report(_key, from: _from.text.trim(), to: _to.text.trim());
      setState(() {
        _data = data;
        _loading = false;
        _error = null;
      });
    } on ApiException catch (e) {
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    final reports = {
      'profit-loss': l.profitLoss,
      'trial-balance': l.trialBalance,
      'cash-flow': l.cashFlow,
      'balance-sheet': l.balanceSheet,
      'general-ledger': l.generalLedger,
      'ar-aging': l.arAging,
      'ap-aging': l.apAging,
      'inventory-valuation': l.inventoryValuation,
    };
    return PermissionGate(
      allowed: auth.permissions.reportsView,
      child: Scaffold(
        appBar: AppBar(title: Text(l.reports)),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Wrap(
              spacing: 8,
              children: [
                for (final e in reports.entries)
                  ChoiceChip(label: Text(e.value), selected: _key == e.key, onSelected: (_) => setState(() => _key = e.key)),
              ],
            ),
            const SizedBox(height: 12),
            TextField(controller: _from, decoration: InputDecoration(labelText: l.from)),
            TextField(controller: _to, decoration: InputDecoration(labelText: l.to)),
            FilledButton(onPressed: _loading ? null : _load, child: Text(l.refresh)),
            if (_error != null) Text(_error!),
            if (_data != null) ...[
              const SizedBox(height: 12),
              FinanceReportView(_data!),
              FilledButton.tonal(
                onPressed: () async {
                  final bytes = await ref.read(financeApiProvider).pdf('reports/$_key', query: {'format': 'csv'});
                  await saveAndOpenBytes(bytes, '$_key.csv');
                },
                child: Text(l.csv),
              ),
            ],
            const Divider(),
            Text(l.exports, style: Theme.of(context).textTheme.titleMedium),
            Wrap(
              spacing: 8,
              children: [
                for (final dataset in ['invoices', 'payments', 'customers', 'expenses', 'quotes'])
                  FilledButton.tonal(
                    onPressed: () async {
                      try {
                        final bytes = await ref.read(financeApiProvider).exportDataset(dataset);
                        await saveAndOpenBytes(bytes, '$dataset.csv');
                        if (context.mounted) showSnack(context, l.success);
                      } catch (e) {
                        if (context.mounted) showApiError(context, e);
                      }
                    },
                    child: Text(dataset),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});
  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  final _host = TextEditingController();
  final _company = TextEditingController();
  final _companyAr = TextEditingController();
  final _vat = TextEditingController();
  final _cr = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _website = TextEditingController();
  final _address = TextEditingController();
  final _building = TextEditingController();
  final _street = TextEditingController();
  final _district = TextEditingController();
  final _city = TextEditingController();
  final _postal = TextEditingController();
  final _currency = TextEditingController();
  final _vatRate = TextEditingController();
  final _terms = TextEditingController();
  final _prefix = TextEditingController();
  final _color = TextEditingController(text: '#06C2A4');
  final _footer = TextEditingController();
  final _country = TextEditingController(text: 'SA');
  final _taxName = TextEditingController();
  final _taxCode = TextEditingController();
  final _taxPct = TextEditingController(text: '15');
  String _taxType = 'standard';
  bool _allowManual = false;
  final _treasuryName = TextEditingController();
  final _iban = TextEditingController();
  final _bankName = TextEditingController();
  final _accountNumber = TextEditingController();
  Map<String, dynamic>? _settings;

  @override
  void initState() {
    super.initState();
    _host.text = ref.read(prefsStoreProvider).apiBaseOverride ?? '';
    ref.read(financeApiProvider).settings().then(_applySettings).catchError((_) {});
  }

  void _applySettings(Map<String, dynamic> s) {
    _company.text = '${s['company_name'] ?? ''}';
    _companyAr.text = '${s['company_name_ar'] ?? ''}';
    _vat.text = '${s['vat_number'] ?? ''}';
    _cr.text = '${s['commercial_registration'] ?? ''}';
    _phone.text = '${s['phone'] ?? ''}';
    _email.text = '${s['email'] ?? ''}';
    _website.text = '${s['website'] ?? ''}';
    _address.text = '${s['address_line'] ?? ''}';
    _building.text = '${s['building_number'] ?? ''}';
    _street.text = '${s['street'] ?? ''}';
    _district.text = '${s['district'] ?? ''}';
    _city.text = '${s['city'] ?? ''}';
    _postal.text = '${s['postal_code'] ?? ''}';
    _currency.text = '${s['currency'] ?? 'SAR'}';
    _vatRate.text = '${s['default_vat_rate'] ?? ''}';
    _terms.text = '${s['default_payment_terms'] ?? ''}';
    _prefix.text = '${s['invoice_prefix'] ?? ''}';
    _color.text = '${s['invoice_primary_color'] ?? '#06C2A4'}';
    _footer.text = '${s['invoice_footer_text'] ?? ''}';
    _country.text = '${s['country_code'] ?? 'SA'}';
    _allowManual = s['allow_manual_invoice_numbers'] == true;
    if (mounted) setState(() => _settings = s);
  }

  @override
  void dispose() {
    _host.dispose();
    _company.dispose();
    _companyAr.dispose();
    _vat.dispose();
    _cr.dispose();
    _phone.dispose();
    _email.dispose();
    _website.dispose();
    _address.dispose();
    _building.dispose();
    _street.dispose();
    _district.dispose();
    _city.dispose();
    _postal.dispose();
    _currency.dispose();
    _vatRate.dispose();
    _terms.dispose();
    _prefix.dispose();
    _color.dispose();
    _footer.dispose();
    _country.dispose();
    _taxName.dispose();
    _taxCode.dispose();
    _taxPct.dispose();
    _treasuryName.dispose();
    _iban.dispose();
    _bankName.dispose();
    _accountNumber.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return Scaffold(
      appBar: AppBar(title: Text(l.settings)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ListTile(title: Text(l.workspace), subtitle: Text(auth.workspace?.name ?? '-')),
          if (auth.workspaces.length > 1)
            DropdownButtonFormField<int>(
              // ignore: deprecated_member_use
              value: auth.workspace?.id,
              items: [
                for (final w in auth.workspaces) DropdownMenuItem(value: w.id, child: Text(w.name)),
              ],
              onChanged: (id) {
                if (id != null) ref.read(authControllerProvider.notifier).switchWorkspace(id);
              },
            ),
          TextField(controller: _host, decoration: InputDecoration(labelText: l.apiHost)),
          FilledButton.tonal(
            onPressed: () async {
              await ref.read(prefsStoreProvider).setApiBaseOverride(_host.text.trim().isEmpty ? null : _host.text.trim());
              ref.read(apiClientProvider).updateBaseUrl(_host.text.trim().isEmpty ? 'http://127.0.0.1:8000' : _host.text.trim());
              if (context.mounted) showSnack(context, l.success);
            },
            child: Text(l.save),
          ),
          const Divider(),
          Text(l.language, style: Theme.of(context).textTheme.titleMedium),
          Wrap(spacing: 8, children: [
            ChoiceChip(label: Text(l.arabic), selected: Localizations.localeOf(context).languageCode == 'ar', onSelected: (_) => ref.read(localeProvider.notifier).setLocale('ar')),
            ChoiceChip(label: Text(l.english), selected: Localizations.localeOf(context).languageCode == 'en', onSelected: (_) => ref.read(localeProvider.notifier).setLocale('en')),
          ]),
          const SizedBox(height: 8),
          Text(l.theme, style: Theme.of(context).textTheme.titleMedium),
          Wrap(spacing: 8, children: [
            ChoiceChip(label: const Text('System'), selected: ref.watch(themeModeProvider) == ThemeMode.system, onSelected: (_) => ref.read(themeModeProvider.notifier).setMode(ThemeMode.system)),
            ChoiceChip(label: const Text('Light'), selected: ref.watch(themeModeProvider) == ThemeMode.light, onSelected: (_) => ref.read(themeModeProvider.notifier).setMode(ThemeMode.light)),
            ChoiceChip(label: const Text('Dark'), selected: ref.watch(themeModeProvider) == ThemeMode.dark, onSelected: (_) => ref.read(themeModeProvider.notifier).setMode(ThemeMode.dark)),
          ]),
          if (_settings != null) ...[
            FormSection(
              title: l.headerSection,
              child: FormGrid(children: [
                TextField(controller: _company, decoration: InputDecoration(labelText: l.company)),
                TextField(controller: _companyAr, decoration: InputDecoration(labelText: l.companyNameAr)),
                TextField(controller: _vat, decoration: InputDecoration(labelText: l.vatNumber)),
                TextField(controller: _cr, decoration: InputDecoration(labelText: l.crNumber)),
                TextField(controller: _phone, decoration: InputDecoration(labelText: l.phone)),
                TextField(controller: _email, decoration: InputDecoration(labelText: l.email)),
                TextField(controller: _website, decoration: InputDecoration(labelText: l.website)),
              ]),
            ),
            FormSection(
              title: l.addressLine,
              child: FormGrid(children: [
                TextField(controller: _address, decoration: InputDecoration(labelText: l.addressLine)),
                TextField(controller: _building, decoration: InputDecoration(labelText: l.buildingNumber)),
                TextField(controller: _street, decoration: InputDecoration(labelText: l.street)),
                TextField(controller: _district, decoration: InputDecoration(labelText: l.district)),
                TextField(controller: _city, decoration: InputDecoration(labelText: l.city)),
                TextField(controller: _postal, decoration: InputDecoration(labelText: l.postalCode)),
                TextField(controller: _country, decoration: InputDecoration(labelText: l.countryCode)),
              ]),
            ),
            FormSection(
              title: l.datesSection,
              child: FormGrid(children: [
                TextField(controller: _currency, decoration: InputDecoration(labelText: l.currency)),
                TextField(controller: _vatRate, decoration: InputDecoration(labelText: l.defaultVatRate)),
                TextField(controller: _terms, decoration: InputDecoration(labelText: l.paymentTerms)),
                TextField(controller: _prefix, decoration: InputDecoration(labelText: l.invoicePrefix)),
                TextField(controller: _color, decoration: InputDecoration(labelText: l.invoiceColor)),
              ]),
            ),
            TextField(controller: _footer, decoration: InputDecoration(labelText: l.invoiceFooter), maxLines: 2),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(l.allowManualNumbers),
              value: _allowManual,
              onChanged: (v) => setState(() => _allowManual = v),
            ),
            InfoRow(label: l.zatcaMode, value: '${_settings!['zatca_integration_mode'] ?? ''}'),
            if (auth.permissions.settings)
              FilledButton.tonal(
                onPressed: () async {
                  try {
                    final next = await ref.read(financeApiProvider).updateSettings({
                      'company_name': _company.text.trim(),
                      'company_name_ar': _companyAr.text.trim(),
                      'vat_number': _vat.text.trim(),
                      'commercial_registration': _cr.text.trim(),
                      'phone': _phone.text.trim(),
                      'email': _email.text.trim(),
                      'website': _website.text.trim(),
                      'address_line': _address.text.trim(),
                      'building_number': _building.text.trim(),
                      'street': _street.text.trim(),
                      'district': _district.text.trim(),
                      'city': _city.text.trim(),
                      'postal_code': _postal.text.trim(),
                      'country_code': _country.text.trim(),
                      'currency': _currency.text.trim(),
                      'default_vat_rate': _vatRate.text.trim(),
                      'default_payment_terms': _terms.text.trim(),
                      'invoice_prefix': _prefix.text.trim(),
                      'invoice_primary_color': _color.text.trim(),
                      'invoice_footer_text': _footer.text.trim(),
                      'allow_manual_invoice_numbers': _allowManual,
                    });
                    _applySettings(next);
                    if (context.mounted) showSnack(context, l.success);
                  } catch (e) {
                    if (context.mounted) showApiError(context, e);
                  }
                },
                child: Text(l.save),
              ),
            if (auth.permissions.settings) ...[
              const SizedBox(height: 12),
              FormSection(
                title: l.createTaxRate,
                child: FormGrid(children: [
                  TextField(controller: _taxName, decoration: InputDecoration(labelText: l.fieldName)),
                  TextField(controller: _taxCode, decoration: InputDecoration(labelText: l.sku)),
                  TextField(controller: _taxPct, decoration: InputDecoration(labelText: l.taxRate)),
                  DropdownButtonFormField(
                    // ignore: deprecated_member_use
                    value: _taxType,
                    decoration: InputDecoration(labelText: l.taxProfile),
                    items: [
                      DropdownMenuItem(value: 'standard', child: Text(l.standardTax)),
                      DropdownMenuItem(value: 'zero_rated', child: Text(l.zeroRated)),
                      DropdownMenuItem(value: 'exempt', child: Text(l.exempt)),
                      DropdownMenuItem(value: 'out_of_scope', child: Text(l.outOfScope)),
                    ],
                    onChanged: (v) => setState(() => _taxType = v ?? 'standard'),
                  ),
                ]),
              ),
              FilledButton.tonal(
                onPressed: () async {
                  try {
                    await ref.read(financeApiProvider).storeTaxRate({
                      'name': _taxName.text.trim(),
                      'code': _taxCode.text.trim(),
                      'type': _taxType,
                      'rate': _taxPct.text.trim(),
                      'is_active': true,
                    });
                    if (context.mounted) showSnack(context, l.success);
                  } catch (e) {
                    if (context.mounted) showFormError(context, e);
                  }
                },
                child: Text(l.createTaxRate),
              ),
              FormSection(
                title: l.createTreasuryAccount,
                child: FormGrid(children: [
                  TextField(controller: _treasuryName, decoration: InputDecoration(labelText: l.fieldName)),
                  TextField(controller: _bankName, decoration: InputDecoration(labelText: l.bankName)),
                  TextField(controller: _iban, decoration: InputDecoration(labelText: l.iban)),
                  TextField(controller: _accountNumber, decoration: InputDecoration(labelText: l.accountNumber)),
                ]),
              ),
              FilledButton.tonal(
                onPressed: () async {
                  try {
                    await ref.read(financeApiProvider).storeTreasuryAccount({
                      'name': _treasuryName.text.trim(),
                      'type': 'bank',
                      'currency': _currency.text.trim().isEmpty ? 'SAR' : _currency.text.trim(),
                      'iban': _iban.text.trim(),
                      'bank_name': _bankName.text.trim(),
                      'account_number': _accountNumber.text.trim(),
                    });
                    if (context.mounted) showSnack(context, l.success);
                  } catch (e) {
                    if (context.mounted) showFormError(context, e);
                  }
                },
                child: Text(l.createTreasuryAccount),
              ),
            ],
          ],
          const Divider(),
          FilledButton(onPressed: () => ref.read(authControllerProvider.notifier).logout(), child: Text(l.logout)),
        ],
      ),
    );
  }
}
