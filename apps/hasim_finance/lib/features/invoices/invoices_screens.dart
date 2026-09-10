import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim_finance/core/auth/auth_controller.dart';
import 'package:hasim_finance/core/models/models.dart';
import 'package:hasim_finance/core/network/api_exception.dart';
import 'package:hasim_finance/core/utils/files.dart';
import 'package:hasim_finance/features/shared/customer_select.dart';
import 'package:hasim_finance/features/shared/document_lines_editor.dart';
import 'package:hasim_finance/features/shared/paged.dart';
import 'package:hasim_finance/l10n/app_localizations.dart';

class InvoicesScreen extends ConsumerWidget {
  const InvoicesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<InvoiceRecord>(
      title: l.invoices,
      allowed: auth.permissions.invoicesView,
      onCreate: auth.permissions.invoicesCreate ? () => context.push('/invoices/new') : null,
      loader: (api, search, page) => api.invoices(search: search, page: page),
      itemBuilder: (context, invoice) => Card(
        child: ListTile(
          title: Text(invoice.invoiceNumber ?? '#${invoice.id}'),
          subtitle: Text('${invoice.customerName ?? ''} · ${invoice.documentStatus} · ${invoice.paymentStatus} · ${invoice.deliveryStatus ?? '-'}'),
          trailing: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(invoice.total, style: const TextStyle(fontWeight: FontWeight.w800)),
              Text(invoice.amountDue, style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
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
              items: const [
                DropdownMenuItem(value: 'cash', child: Text('cash')),
                DropdownMenuItem(value: 'bank_transfer', child: Text('bank_transfer')),
                DropdownMenuItem(value: 'card', child: Text('card')),
                DropdownMenuItem(value: 'other', child: Text('other')),
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

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = ref.watch(authControllerProvider).permissions;
    final invoice = _data;
    return DetailScaffold(
      title: invoice?.invoiceNumber ?? l.invoices,
      loading: _loading,
      error: _error,
      onRetry: _load,
      child: invoice == null
          ? const SizedBox.shrink()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                DocumentHeader(
                  number: invoice.invoiceNumber ?? '#${invoice.id}',
                  documentStatus: invoice.documentStatus,
                  paymentStatus: invoice.paymentStatus,
                  deliveryStatus: invoice.deliveryStatus,
                  customer: invoice.customerName,
                ),
                const SizedBox(height: 12),
                TotalsCard(
                  subtotal: invoice.subtotal,
                  tax: invoice.taxAmount,
                  total: invoice.total,
                  paid: invoice.amountPaid,
                  due: invoice.amountDue,
                ),
                Text(l.neverMarkPaidLocally, style: Theme.of(context).textTheme.bodySmall),
                const SizedBox(height: 12),
                LineTable(lines: invoice.lines),
                DeliveryTimeline(deliveries: invoice.deliveries),
                if (invoice.payments.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(l.payments, style: Theme.of(context).textTheme.titleMedium),
                  for (final payment in invoice.payments)
                    ListTile(
                      title: Text('${payment.amount} · ${payment.method} · ${payment.status}'),
                      subtitle: Text(payment.receiptNumber ?? ''),
                      trailing: payment.status == 'posted' && p.can('invoices.reverse_payment')
                          ? TextButton(
                              onPressed: () => confirmAndRun(context, () async {
                                await ref.read(financeApiProvider).reverseInvoicePayment(invoice.id, payment.id);
                                await _load();
                              }),
                              child: Text(l.reversePayment),
                            )
                          : null,
                      onTap: () => context.push('/payments/${payment.id}'),
                    ),
                ],
                if (invoice.audit.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(l.auditTrail, style: Theme.of(context).textTheme.titleMedium),
                  for (final row in invoice.audit.take(20))
                    ListTile(
                      dense: true,
                      title: Text('${row['action'] ?? ''}'),
                      subtitle: Text('${row['actor_name'] ?? ''} · ${row['created_at'] ?? ''}'),
                    ),
                ],
                const SizedBox(height: 12),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  if (invoice.documentStatus == 'draft' && p.can('invoices.edit'))
                    FilledButton(onPressed: () => context.push('/invoices/${invoice.id}/edit'), child: Text(l.edit)),
                  if (invoice.documentStatus == 'draft' && p.can('invoices.issue'))
                    FilledButton(onPressed: () => confirmAndRun(context, () => _act('issue')), child: Text(l.issue)),
                  if (invoice.documentStatus == 'issued' && p.can('invoices.send'))
                    FilledButton(onPressed: () => promptEmailAndSend(context, (email) => _act('send', body: {'email': email})), child: Text(l.send)),
                  if (invoice.documentStatus == 'issued' && p.can('invoices.remind'))
                    FilledButton.tonal(onPressed: () => promptEmailAndSend(context, (email) => _act('remind', body: {'email': email})), child: Text(l.remind)),
                  if (invoice.documentStatus == 'issued' && invoice.paymentStatus != 'paid' && p.paymentsManage)
                    FilledButton(onPressed: _manualPayment, child: Text(l.recordPayment)),
                  if (invoice.documentStatus == 'issued' && invoice.paymentStatus != 'paid' && p.paymentsManage)
                    FilledButton.tonal(onPressed: _checkout, child: Text(l.createCheckout)),
                  if (invoice.checkout?.hasUrl == true) ...[
                    OutlinedButton(
                      onPressed: () async {
                        await copyText(invoice.checkout!.checkoutUrl!);
                        if (context.mounted) showSnack(context, l.copied);
                      },
                      child: Text(l.copy),
                    ),
                    OutlinedButton(
                      onPressed: () async {
                        await openExternalUrl(invoice.checkout!.checkoutUrl!);
                        await _load();
                      },
                      child: Text(l.open),
                    ),
                  ],
                  if (invoice.documentStatus != 'cancelled' && p.can('invoices.cancel'))
                    OutlinedButton(onPressed: () => confirmAndRun(context, () => _act('cancel')), child: Text(l.cancel)),
                  FilledButton.tonal(
                    onPressed: () async {
                      try {
                        final bytes = await ref.read(financeApiProvider).pdf('sales-invoices/${invoice.id}/pdf');
                        await saveAndOpenBytes(bytes, 'invoice-${invoice.invoiceNumber}.pdf');
                      } catch (e) {
                        if (context.mounted) showApiError(context, e);
                      }
                    },
                    child: Text(l.pdf),
                  ),
                  if (invoice.customerId != null)
                    TextButton(onPressed: () => context.push('/customers/${invoice.customerId}'), child: Text(l.customer)),
                ]),
              ],
            ),
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
  final _issueDate = TextEditingController(text: isoDate());
  final _dueDate = TextEditingController(text: isoDate(DateTime.now().add(const Duration(days: 14))));
  final _notes = TextEditingController();
  final List<LineDraft> _lines = [LineDraft(description: 'خدمة فوترة', unitPrice: '100')];
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    if (widget.id != null) {
      ref.read(financeApiProvider).invoice(widget.id!).then((invoice) {
        if (!mounted) return;
        _customerId = invoice.customerId;
        _notes.text = invoice.notes ?? '';
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
    _issueDate.dispose();
    _dueDate.dispose();
    _notes.dispose();
    for (final line in _lines) {
      line.dispose();
    }
    super.dispose();
  }

  Future<void> _save() async {
    if (_customerId == null) return;
    setState(() => _busy = true);
    try {
      final saved = await ref.read(financeApiProvider).saveInvoice({
        'customer_id': _customerId,
        'issue_date': _issueDate.text.trim(),
        'due_date': _dueDate.text.trim(),
        'notes': _notes.text.trim(),
        'invoice_status': 'draft',
        'items': _lines.map((line) => line.toPayload()).toList(),
      }, id: widget.id);
      if (!mounted) return;
      context.go('/invoices/${saved.id}');
    } catch (e) {
      if (mounted) showApiError(context, e);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l.invoices)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          CustomerSelectField(selectedId: _customerId, onSelected: (id) => setState(() => _customerId = id)),
          TextField(controller: _issueDate, decoration: InputDecoration(labelText: l.issueDate)),
          TextField(controller: _dueDate, decoration: InputDecoration(labelText: l.dueDate)),
          const SizedBox(height: 12),
          DocumentLinesEditor(lines: _lines, onChanged: () => setState(() {})),
          TextField(controller: _notes, decoration: InputDecoration(labelText: l.notesField), maxLines: 3),
          const SizedBox(height: 16),
          FilledButton(onPressed: _busy ? null : _save, child: Text(l.save)),
        ],
      ),
    );
  }
}
