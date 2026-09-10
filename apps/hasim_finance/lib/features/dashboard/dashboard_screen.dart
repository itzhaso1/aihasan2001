import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim_finance/core/auth/auth_controller.dart';
import 'package:hasim_finance/core/models/models.dart';
import 'package:hasim_finance/core/network/api_exception.dart';
import 'package:hasim_finance/core/layout/finance_layout.dart';
import 'package:hasim_finance/core/widgets/widgets.dart';
import 'package:hasim_finance/features/shared/customer_select.dart';
import 'package:hasim_finance/l10n/app_localizations.dart';

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});

  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  DashboardData? _data;
  bool _loading = true;
  String? _error;
  final _from = TextEditingController();
  final _to = TextEditingController();
  int? _customerId;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _from.text = isoDate(DateTime(now.year, now.month, 1));
    _to.text = isoDate(now);
    _load();
  }

  @override
  void dispose() {
    _from.dispose();
    _to.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref.read(financeApiProvider).dashboard(
            from: _from.text.trim(),
            to: _to.text.trim(),
            customerId: _customerId,
          );
      if (!mounted) return;
      setState(() {
        _data = data;
        _loading = false;
        final analyticsFrom = data.analytics['from']?.toString();
        final analyticsTo = data.analytics['to']?.toString();
        if (analyticsFrom != null && analyticsFrom.isNotEmpty) _from.text = analyticsFrom;
        if (analyticsTo != null && analyticsTo.isNotEmpty) _to.text = analyticsTo;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    ref.listen(authControllerProvider.select((s) => s.workspace?.id), (prev, next) {
      if (prev != next) _load();
    });
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PermissionGate(
      allowed: auth.permissions.financeView,
      child: Scaffold(
        appBar: AppBar(
          title: Text(l.dashboard),
          actions: [
            IconButton(onPressed: () => context.push('/search'), icon: const Icon(Icons.search)),
            IconButton(onPressed: _load, icon: const Icon(Icons.refresh)),
          ],
        ),
        body: AsyncBody(
          loading: _loading,
          error: _error,
          onRetry: _load,
          child: _data == null
              ? const SizedBox.shrink()
              : FinancePage(
                  child: ListView(
                  padding: EdgeInsets.zero,
                  children: [
                    FormSection(
                      title: l.decisionPeriod,
                      subtitle: l.comparePrevious,
                      child: FormGrid(children: [
                        TextField(controller: _from, decoration: InputDecoration(labelText: l.from)),
                        TextField(controller: _to, decoration: InputDecoration(labelText: l.to)),
                        CustomerSelectField(
                          selectedId: _customerId,
                          onSelected: (id) => setState(() => _customerId = id),
                        ),
                        Align(
                          alignment: AlignmentDirectional.centerStart,
                          child: FilledButton(onPressed: _load, child: Text(l.applyFilters)),
                        ),
                      ]),
                    ),
                    if ((_data!.analytics['hero'] as List?)?.isNotEmpty == true) ...[
                      MetricGrid(
                        metrics: [
                          for (final row in (_data!.analytics['hero'] as List).whereType<Map>())
                            ('${row['label'] ?? ''}', '${row['value'] ?? '0'}'),
                        ],
                      ),
                      const SizedBox(height: 12),
                    ],
                    MetricGrid(metrics: [
                      (l.outstanding, _data!.cards['outstanding_customer_balance'] ?? '0'),
                      (l.invoicesDue, _data!.cards['invoices_due'] ?? '0'),
                      (l.overdueInvoices, _data!.cards['overdue_invoices'] ?? '0'),
                      (l.paidThisPeriod, _data!.cards['paid_this_period'] ?? '0'),
                      (l.sales, _data!.cards['sales'] ?? '0'),
                      (l.purchases, _data!.cards['purchases'] ?? '0'),
                      (l.expenses, _data!.cards['expenses'] ?? '0'),
                      (l.receivables, _data!.cards['receivables'] ?? '0'),
                      (l.payables, _data!.cards['payables'] ?? '0'),
                      (l.netProfit, _data!.cards['net_profit'] ?? '0'),
                      (l.outputVat, _data!.cards['output_vat'] ?? '0'),
                      (l.inputVat, _data!.cards['input_vat'] ?? '0'),
                      (l.netVat, _data!.cards['net_vat'] ?? '0'),
                      (l.cashBalance, _data!.cards['cash_balance'] ?? '0'),
                      (l.bankBalance, _data!.cards['bank_balance'] ?? '0'),
                      (l.activeContracts, _data!.cards['active_contracts_count'] ?? '0'),
                    ]),
                    if ((_data!.analytics['attention'] as List?)?.isNotEmpty == true) ...[
                      const SizedBox(height: 20),
                      Text(l.attentionItems, style: Theme.of(context).textTheme.titleLarge),
                      for (final row in (_data!.analytics['attention'] as List).whereType<Map>())
                        ListTile(
                          title: Text('${row['title'] ?? ''}'),
                          subtitle: Text('${row['reason'] ?? ''}'),
                        ),
                    ],
                    if ((_data!.analytics['top_customers'] as List?)?.isNotEmpty == true) ...[
                      const SizedBox(height: 16),
                      Text(l.topCustomers, style: Theme.of(context).textTheme.titleLarge),
                      for (final row in (_data!.analytics['top_customers'] as List).whereType<Map>())
                        ListTile(
                          title: Text('${row['name'] ?? ''}'),
                          trailing: Text('${row['total'] ?? ''}'),
                        ),
                    ],
                    const SizedBox(height: 20),
                    Text(l.recentInvoices, style: Theme.of(context).textTheme.titleLarge),
                    const SizedBox(height: 8),
                    for (final invoice in _data!.recentInvoices)
                      ListTile(
                        title: Text(invoice.invoiceNumber ?? '#${invoice.id}'),
                        subtitle: Text('${invoice.customerName ?? ''} · ${invoice.documentStatus} · ${invoice.paymentStatus}'),
                        trailing: Text(invoice.total),
                        onTap: () => context.push('/invoices/${invoice.id}'),
                      ),
                    const SizedBox(height: 16),
                    Text(l.recentPayments, style: Theme.of(context).textTheme.titleLarge),
                    for (final payment in _data!.recentPayments)
                      ListTile(
                        title: Text(payment.invoiceNumber ?? '#${payment.id}'),
                        subtitle: Text('${payment.method ?? ''} · ${payment.status}'),
                        trailing: Text(payment.amount),
                        onTap: () => context.push('/payments/${payment.id}'),
                      ),
                    if (_data!.overdueInvoices.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      Text(l.overdueInvoices, style: Theme.of(context).textTheme.titleLarge),
                      for (final invoice in _data!.overdueInvoices)
                        ListTile(
                          title: Text(invoice.invoiceNumber ?? '#${invoice.id}'),
                          subtitle: Text('${invoice.customerName ?? ''} · ${invoice.dueDate ?? ''}'),
                          trailing: Text(invoice.amountDue),
                          onTap: () => context.push('/invoices/${invoice.id}'),
                        ),
                    ],
                    if (_data!.recentExpenses.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      Text(l.recentExpenses, style: Theme.of(context).textTheme.titleLarge),
                      for (final expense in _data!.recentExpenses)
                        ListTile(
                          title: Text(expense.description ?? expense.expenseNumber ?? '#${expense.id}'),
                          subtitle: Text('${expense.categoryName ?? ''} · ${expense.status ?? ''}'),
                          trailing: Text(expense.total),
                          onTap: () => context.push('/expenses/${expense.id}'),
                        ),
                    ],
                  ],
                ),
              ),
        ),
      ),
    );
  }
}

class FinanceSearchScreen extends ConsumerStatefulWidget {
  const FinanceSearchScreen({super.key});

  @override
  ConsumerState<FinanceSearchScreen> createState() => _FinanceSearchScreenState();
}

class _FinanceSearchScreenState extends ConsumerState<FinanceSearchScreen> {
  final _q = TextEditingController();
  Timer? _debounce;
  Map<String, dynamic>? _data;
  String? _error;
  bool _loading = false;

  @override
  void dispose() {
    _debounce?.cancel();
    _q.dispose();
    super.dispose();
  }

  Future<void> _search(String value) async {
    if (value.trim().length < 2) {
      setState(() {
        _data = null;
        _error = null;
      });
      return;
    }
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await ref.read(financeApiProvider).search(value.trim());
      if (!mounted) return;
      setState(() {
        _data = data;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(l.globalSearch)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _q,
              autofocus: true,
              decoration: InputDecoration(prefixIcon: const Icon(Icons.search), hintText: l.search),
              onChanged: (value) {
                _debounce?.cancel();
                _debounce = Timer(const Duration(milliseconds: 350), () => _search(value));
              },
            ),
          ),
          if (_loading) const LinearProgressIndicator(),
          if (_error != null) Padding(padding: const EdgeInsets.all(16), child: Text(_error!)),
          Expanded(
            child: _data == null
                ? EmptyState(title: l.globalSearch, subtitle: l.search)
                : ListView(
                    children: [
                      for (final entry in _data!.entries)
                        if (entry.value is List && (entry.value as List).isNotEmpty) ...[
                          ListTile(title: Text(entry.key, style: const TextStyle(fontWeight: FontWeight.w800))),
                          for (final row in (entry.value as List).whereType<Map>())
                            ListTile(
                              title: Text('${row['title'] ?? ''}'),
                              subtitle: Text('${row['subtitle'] ?? ''}'),
                              onTap: () {
                                final id = row['id'];
                                final type = row['type']?.toString();
                                if (id == null || type == null) return;
                                final path = switch (type) {
                                  'customer' => '/customers/$id',
                                  'invoice' => '/invoices/$id',
                                  'quote' => '/quotes/$id',
                                  'receipt' => '/receipts/$id',
                                  'payment' => '/payments/$id',
                                  'expense' => '/expenses/$id',
                                  'contract' => '/contracts/$id',
                                  'purchase' => '/purchases/$id',
                                  'supplier' => '/suppliers/$id',
                                  'product' => '/products/$id',
                                  'project' => '/projects/$id',
                                  'purchase_order' => '/purchase-orders/$id',
                                  'lead' => '/leads/$id',
                                  _ => null,
                                };
                                if (path != null) context.push(path);
                              },
                            ),
                        ],
                    ],
                  ),
          ),
        ],
      ),
    );
  }
}
