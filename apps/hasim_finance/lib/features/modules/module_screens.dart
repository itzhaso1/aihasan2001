import 'package:dio/dio.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim_finance/core/theme/theme_controllers.dart';
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
  const NoteFormScreen({super.key});
  @override
  ConsumerState<NoteFormScreen> createState() => _NoteFormScreenState();
}

class _NoteFormScreenState extends ConsumerState<NoteFormScreen> {
  final _invoiceId = TextEditingController();
  final _reason = TextEditingController();
  final _issueDate = TextEditingController(text: isoDate());
  final List<LineDraft> _lines = [LineDraft(description: 'بند', unitPrice: '10')];
  String _type = 'credit';

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
    return Scaffold(
      appBar: AppBar(title: Text(l.notes)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
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
          TextField(controller: _reason, decoration: InputDecoration(labelText: l.description), maxLines: 2),
          const SizedBox(height: 12),
          DocumentLinesEditor(lines: _lines, onChanged: () => setState(() {})),
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
                if (context.mounted) showApiError(context, e);
              }
            },
            child: Text(l.save),
          ),
        ],
      ),
    );
  }
}

class ContractsScreen extends ConsumerWidget {
  const ContractsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<ContractRecord>(
      title: l.contracts,
      allowed: auth.permissions.contractsView,
      onCreate: auth.permissions.can('contracts.create') ? () => context.push('/contracts/new') : null,
      loader: (api, search, page) => api.contracts(search: search, page: page),
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
                    trailing: Column(
                      mainAxisSize: MainAxisSize.min,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(s.amount),
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
                ]),
              ],
            ),
    );
  }
}

class ContractFormScreen extends ConsumerStatefulWidget {
  const ContractFormScreen({super.key});
  @override
  ConsumerState<ContractFormScreen> createState() => _ContractFormScreenState();
}

class _ContractFormScreenState extends ConsumerState<ContractFormScreen> {
  final _title = TextEditingController();
  int? _customerId;
  final _value = TextEditingController(text: '0');
  final _notes = TextEditingController();
  final _terms = TextEditingController();

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l.contracts)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(controller: _title, decoration: InputDecoration(labelText: l.description)),
          CustomerSelectField(selectedId: _customerId, onSelected: (id) => setState(() => _customerId = id)),
          TextField(controller: _value, decoration: InputDecoration(labelText: l.amount)),
          TextField(controller: _notes, decoration: InputDecoration(labelText: l.notesField), maxLines: 2),
          TextField(controller: _terms, decoration: InputDecoration(labelText: l.terms), maxLines: 2),
          FilledButton(
            onPressed: _customerId == null
                ? null
                : () async {
                    final saved = await ref.read(financeApiProvider).saveContract({
                      'title': _title.text.trim(),
                      'customer_id': _customerId,
                      'value': _value.text.trim(),
                      'notes': _notes.text.trim(),
                      'terms': _terms.text.trim(),
                    });
                    if (context.mounted) context.go('/contracts/${saved.id}');
                  },
            child: Text(l.save),
          ),
        ],
      ),
    );
  }
}

class ExpensesScreen extends ConsumerWidget {
  const ExpensesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<ExpenseRecord>(
      title: l.expenses,
      allowed: auth.permissions.expensesView,
      onCreate: auth.permissions.can('expenses.create') ? () => context.push('/expenses/new') : null,
      loader: (api, search, page) => api.expenses(search: search, page: page),
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
  const ExpenseFormScreen({super.key});
  @override
  ConsumerState<ExpenseFormScreen> createState() => _ExpenseFormScreenState();
}

class _ExpenseFormScreenState extends ConsumerState<ExpenseFormScreen> {
  final _desc = TextEditingController();
  final _amount = TextEditingController();
  final _taxRate = TextEditingController(text: '15');
  final _method = TextEditingController();
  int? _supplierId;
  bool _recurring = false;
  PlatformFile? _file;

  @override
  void dispose() {
    _desc.dispose();
    _amount.dispose();
    _taxRate.dispose();
    _method.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l.expenses)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(controller: _desc, decoration: InputDecoration(labelText: l.description)),
          TextField(controller: _amount, decoration: InputDecoration(labelText: l.amount)),
          TextField(controller: _taxRate, decoration: InputDecoration(labelText: l.taxRate)),
          TextField(controller: _method, decoration: InputDecoration(labelText: l.method)),
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
            onPressed: () async {
              try {
                late final ExpenseRecord saved;
                final payload = <String, dynamic>{
                  'description': _desc.text.trim(),
                  'amount': _amount.text.trim(),
                  'expense_date': DateTime.now().toIso8601String().substring(0, 10),
                  'status': 'draft',
                  'tax_rate': _taxRate.text.trim(),
                  'is_recurring': _recurring,
                  if (_method.text.trim().isNotEmpty) 'payment_method': _method.text.trim(),
                  if (_supplierId != null) 'supplier_id': _supplierId,
                };
                final path = _file?.path;
                if (path != null && path.isNotEmpty) {
                  saved = await ref.read(financeApiProvider).saveExpense(
                    {},
                    form: FormData.fromMap({
                      ...payload,
                      'attachment_file': await MultipartFile.fromFile(path, filename: _file!.name),
                    }),
                  );
                } else {
                  saved = await ref.read(financeApiProvider).saveExpense(payload);
                }
                if (context.mounted) context.go('/expenses/${saved.id}');
              } catch (e) {
                if (context.mounted) showApiError(context, e);
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
                if (e.status != 'draft') Text('لا يمكن تعديل مصروف مرحّل من التطبيق.'),
              ],
            ),
    );
  }
}

class PurchasesScreen extends ConsumerWidget {
  const PurchasesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<InvoiceRecord>(
      title: l.purchases,
      allowed: auth.permissions.purchasesView,
      onCreate: auth.permissions.can('purchases.create') ? () => context.push('/purchases/new') : null,
      loader: (api, search, page) => api.purchases(search: search, page: page),
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
              ],
            ),
    );
  }
}

class PurchaseFormScreen extends ConsumerStatefulWidget {
  const PurchaseFormScreen({super.key});
  @override
  ConsumerState<PurchaseFormScreen> createState() => _PurchaseFormScreenState();
}

class _PurchaseFormScreenState extends ConsumerState<PurchaseFormScreen> {
  int? _supplierId;
  final _issueDate = TextEditingController(text: isoDate());
  final _dueDate = TextEditingController(text: isoDate(DateTime.now().add(const Duration(days: 14))));
  final _notes = TextEditingController();
  final List<LineDraft> _lines = [LineDraft(description: 'بند مشتريات', unitPrice: '50')];

  @override
  void dispose() {
    _issueDate.dispose();
    _dueDate.dispose();
    _notes.dispose();
    for (final line in _lines) {
      line.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l.purchases)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          SupplierSelectField(selectedId: _supplierId, onSelected: (id) => setState(() => _supplierId = id)),
          TextField(controller: _issueDate, decoration: InputDecoration(labelText: l.issueDate)),
          TextField(controller: _dueDate, decoration: InputDecoration(labelText: l.dueDate)),
          const SizedBox(height: 12),
          DocumentLinesEditor(lines: _lines, onChanged: () => setState(() {})),
          TextField(controller: _notes, decoration: InputDecoration(labelText: l.notesField), maxLines: 3),
          FilledButton(
            onPressed: _supplierId == null
                ? null
                : () async {
                    try {
                      final saved = await ref.read(financeApiProvider).savePurchase({
                        'supplier_id': _supplierId,
                        'issue_date': _issueDate.text.trim(),
                        'due_date': _dueDate.text.trim(),
                        'notes': _notes.text.trim(),
                        'invoice_status': 'draft',
                        'items': _lines.map((line) => line.toPayload()).toList(),
                      });
                      if (context.mounted) context.go('/purchases/${saved.id}');
                    } catch (e) {
                      if (context.mounted) showApiError(context, e);
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
            const Divider(),
            TextField(controller: _company, decoration: InputDecoration(labelText: l.company)),
            TextField(controller: _companyAr, decoration: InputDecoration(labelText: l.companyNameAr)),
            TextField(controller: _vat, decoration: InputDecoration(labelText: l.vatNumber)),
            TextField(controller: _cr, decoration: InputDecoration(labelText: l.crNumber)),
            TextField(controller: _phone, decoration: InputDecoration(labelText: l.phone)),
            TextField(controller: _email, decoration: InputDecoration(labelText: l.email)),
            TextField(controller: _website, decoration: InputDecoration(labelText: l.website)),
            TextField(controller: _address, decoration: InputDecoration(labelText: l.addressLine)),
            TextField(controller: _building, decoration: InputDecoration(labelText: l.buildingNumber)),
            TextField(controller: _street, decoration: InputDecoration(labelText: l.street)),
            TextField(controller: _district, decoration: InputDecoration(labelText: l.district)),
            TextField(controller: _city, decoration: InputDecoration(labelText: l.city)),
            TextField(controller: _postal, decoration: InputDecoration(labelText: l.postalCode)),
            TextField(controller: _currency, decoration: InputDecoration(labelText: l.currency)),
            TextField(controller: _vatRate, decoration: InputDecoration(labelText: l.defaultVatRate)),
            TextField(controller: _terms, decoration: InputDecoration(labelText: l.paymentTerms)),
            InfoRow(label: l.invoicePrefix, value: '${_settings!['invoice_prefix'] ?? ''}'),
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
                      'currency': _currency.text.trim(),
                      'default_vat_rate': _vatRate.text.trim(),
                      'default_payment_terms': _terms.text.trim(),
                    });
                    _applySettings(next);
                    if (context.mounted) showSnack(context, l.success);
                  } catch (e) {
                    if (context.mounted) showApiError(context, e);
                  }
                },
                child: Text(l.save),
              ),
          ],
          const Divider(),
          FilledButton(onPressed: () => ref.read(authControllerProvider.notifier).logout(), child: Text(l.logout)),
        ],
      ),
    );
  }
}
