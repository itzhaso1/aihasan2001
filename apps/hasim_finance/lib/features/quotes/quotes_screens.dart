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

class QuotesScreen extends ConsumerStatefulWidget {
  const QuotesScreen({super.key});

  @override
  ConsumerState<QuotesScreen> createState() => _QuotesScreenState();
}

class _QuotesScreenState extends ConsumerState<QuotesScreen> {
  String? _status;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<QuoteRecord>(
      key: ValueKey(_status),
      title: l.quotes,
      allowed: auth.permissions.quotesView,
      onCreate: auth.permissions.quotesCreate ? () => context.push('/quotes/new') : null,
      filterBar: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Wrap(
          spacing: 8,
          children: [
            for (final option in <String?>[null, 'draft', 'issued', 'cancelled'])
              ChoiceChip(
                label: Text(option ?? l.filterAll),
                selected: _status == option,
                onSelected: (_) => setState(() => _status = option),
              ),
          ],
        ),
      ),
      loader: (api, search, page) => api.quotes(search: search, page: page, status: _status),
      itemBuilder: (context, quote) => Card(
        child: ListTile(
          title: Text(quote.quoteNumber ?? '#${quote.id}'),
          subtitle: Text('${quote.customerName ?? ''} · ${quote.documentStatus} · ${quote.outcome} · ${quote.deliveryStatus ?? '-'}'),
          trailing: Text(quote.total),
          onTap: () => context.push('/quotes/${quote.id}'),
        ),
      ),
    );
  }
}

class QuoteDetailScreen extends ConsumerStatefulWidget {
  const QuoteDetailScreen({super.key, required this.id});
  final int id;

  @override
  ConsumerState<QuoteDetailScreen> createState() => _QuoteDetailScreenState();
}

class _QuoteDetailScreenState extends ConsumerState<QuoteDetailScreen> {
  QuoteRecord? _data;
  bool _loading = true;
  String? _error;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref.read(financeApiProvider).quote(widget.id);
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

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _act(String action, {Map<String, dynamic>? body}) async {
    try {
      final updated = await ref.read(financeApiProvider).quoteAction(widget.id, action, body: body);
      await _load();
      if (!mounted) return;
      showSnack(context, AppLocalizations.of(context).success);
      if (action == 'convert' && updated.convertedInvoiceId != null) {
        context.go('/invoices/${updated.convertedInvoiceId}');
      }
    } catch (e) {
      if (mounted) showApiError(context, e);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final p = ref.watch(authControllerProvider).permissions;
    final q = _data;
    return DetailScaffold(
      title: q?.quoteNumber ?? l.quotes,
      loading: _loading,
      error: _error,
      onRetry: _load,
      child: q == null
          ? const SizedBox.shrink()
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                DocumentHeader(
                  number: q.quoteNumber ?? '#${q.id}',
                  documentStatus: q.documentStatus,
                  outcome: q.outcome,
                  deliveryStatus: q.deliveryStatus,
                  customer: q.customerName,
                ),
                const SizedBox(height: 12),
                TotalsCard(
                  subtotal: q.subtotal,
                  discount: q.discount,
                  taxable: q.taxableAmount,
                  tax: q.taxAmount,
                  total: q.total,
                ),
                InfoRow(label: l.issueDate, value: q.issueDate),
                InfoRow(label: l.expiryDate, value: q.expiryDate),
                InfoRow(label: l.notesField, value: q.notes),
                InfoRow(label: l.terms, value: q.terms),
                InfoRow(label: l.rejectionReason, value: q.rejectionReason),
                const SizedBox(height: 12),
                LineTable(lines: q.lines),
                DeliveryTimeline(deliveries: q.deliveries),
                const SizedBox(height: 16),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  if (q.documentStatus == 'draft' && p.can('quotes.edit'))
                    FilledButton(onPressed: () => context.push('/quotes/${q.id}/edit'), child: Text(l.edit)),
                  if (q.documentStatus == 'draft' && p.can('quotes.issue'))
                    FilledButton(onPressed: () => confirmAndRun(context, () => _act('issue')), child: Text(l.issue)),
                  if (q.documentStatus == 'issued' && p.can('quotes.send'))
                    FilledButton(onPressed: () => promptEmailAndSend(context, (email) => _act('send', body: {'email': email})), child: Text(l.send)),
                  if (q.documentStatus == 'issued' && q.outcome == 'pending' && p.can('quotes.accept'))
                    FilledButton(onPressed: () => _act('accept'), child: Text(l.accept)),
                  if (q.documentStatus == 'issued' && q.outcome == 'pending' && p.can('quotes.reject'))
                    FilledButton(onPressed: () => _act('reject'), child: Text(l.reject)),
                  if (q.outcome == 'accepted' && p.can('quotes.convert'))
                    FilledButton(onPressed: () => _act('convert'), child: Text(l.convert)),
                  if (q.documentStatus != 'cancelled' && p.can('quotes.cancel'))
                    OutlinedButton(onPressed: () => confirmAndRun(context, () => _act('cancel')), child: Text(l.cancel)),
                  FilledButton.tonal(
                    onPressed: () async {
                      try {
                        final bytes = await ref.read(financeApiProvider).pdf('quotes/${q.id}/pdf');
                        await saveAndOpenBytes(bytes, 'quote-${q.quoteNumber}.pdf');
                      } catch (e) {
                        if (context.mounted) showApiError(context, e);
                      }
                    },
                    child: Text(l.pdf),
                  ),
                  if (q.convertedInvoiceId != null)
                    TextButton(onPressed: () => context.push('/invoices/${q.convertedInvoiceId}'), child: Text(q.convertedInvoiceNumber ?? l.invoices)),
                ]),
              ],
            ),
    );
  }
}

class QuoteFormScreen extends ConsumerStatefulWidget {
  const QuoteFormScreen({super.key, this.id});
  final int? id;

  @override
  ConsumerState<QuoteFormScreen> createState() => _QuoteFormScreenState();
}

class _QuoteFormScreenState extends ConsumerState<QuoteFormScreen> {
  int? _customerId;
  final _issueDate = TextEditingController(text: isoDate());
  final _expiryDate = TextEditingController(text: isoDate(DateTime.now().add(const Duration(days: 30))));
  final _notes = TextEditingController();
  final List<LineDraft> _lines = [LineDraft(description: 'خدمة', unitPrice: '100')];
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    if (widget.id != null) {
      ref.read(financeApiProvider).quote(widget.id!).then((q) {
        if (!mounted) return;
        _customerId = q.customerId;
        _notes.text = q.notes ?? '';
        if (q.issueDate != null) _issueDate.text = q.issueDate!.substring(0, 10);
        if (q.expiryDate != null) _expiryDate.text = q.expiryDate!.substring(0, 10);
        if (q.lines.isNotEmpty) {
          for (final line in _lines) {
            line.dispose();
          }
          _lines
            ..clear()
            ..addAll(q.lines.map(LineDraft.fromItem));
        }
        setState(() {});
      });
    }
  }

  @override
  void dispose() {
    _issueDate.dispose();
    _expiryDate.dispose();
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
      final saved = await ref.read(financeApiProvider).saveQuote({
        'customer_id': _customerId,
        'issue_date': _issueDate.text.trim(),
        'expiry_date': _expiryDate.text.trim(),
        'notes': _notes.text.trim(),
        'items': _lines.map((line) => line.toPayload()).toList(),
      }, id: widget.id);
      if (!mounted) return;
      context.go('/quotes/${saved.id}');
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
      appBar: AppBar(title: Text(l.quotes)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          CustomerSelectField(selectedId: _customerId, onSelected: (id) => setState(() => _customerId = id)),
          TextField(controller: _issueDate, decoration: InputDecoration(labelText: l.issueDate)),
          TextField(controller: _expiryDate, decoration: InputDecoration(labelText: l.expiryDate)),
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
