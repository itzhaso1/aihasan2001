import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim_finance/core/auth/auth_controller.dart';
import 'package:hasim_finance/core/models/models.dart';
import 'package:hasim_finance/core/network/api_exception.dart';
import 'package:hasim_finance/core/widgets/widgets.dart';
import 'package:hasim_finance/features/shared/paged.dart';
import 'package:hasim_finance/l10n/app_localizations.dart';

class CustomersScreen extends ConsumerWidget {
  const CustomersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    return PagedListScreen<CustomerRecord>(
      title: l.customers,
      allowed: auth.permissions.customersView,
      onCreate: auth.permissions.customersCreate ? () => context.push('/customers/new') : null,
      loader: (api, search, page) => api.customers(search: search, page: page),
      itemBuilder: (context, customer) => Card(
        child: ListTile(
          title: Text(customer.name),
          subtitle: Text('${customer.partyType ?? ''} · ${customer.email ?? customer.phone ?? ''}'),
          trailing: MoneyText(customer.outstandingBalance, style: const TextStyle(fontWeight: FontWeight.w700)),
          onTap: () => context.push('/customers/${customer.id}'),
        ),
      ),
    );
  }
}

class CustomerDetailScreen extends ConsumerStatefulWidget {
  const CustomerDetailScreen({super.key, required this.id});
  final int id;

  @override
  ConsumerState<CustomerDetailScreen> createState() => _CustomerDetailScreenState();
}

class _CustomerDetailScreenState extends ConsumerState<CustomerDetailScreen> {
  CustomerRecord? _data;
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
      final data = await ref.read(financeApiProvider).customer(widget.id);
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
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final customer = _data;
    return DetailScaffold(
      title: customer?.name ?? l.customers,
      loading: _loading,
      error: _error,
      onRetry: _load,
      actions: [
        IconButton(onPressed: () => context.push('/customers/${widget.id}/edit'), icon: const Icon(Icons.edit)),
      ],
      child: customer == null
          ? const SizedBox.shrink()
          : DefaultTabController(
              length: 6,
              child: Column(
                children: [
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('${l.vatNumber}: ${customer.vatNumber ?? '-'}'),
                        Text('${l.crNumber}: ${customer.commercialRegistration ?? '-'}'),
                        Text('${l.email}: ${customer.email ?? '-'}'),
                        Text('${l.phone}: ${customer.phone ?? '-'}'),
                        const SizedBox(height: 8),
                        MoneyText(customer.outstandingBalance),
                      ],
                    ),
                  ),
                  TabBar(isScrollable: true, tabs: [
                    Tab(text: l.overview),
                    Tab(text: l.invoices),
                    Tab(text: l.quotes),
                    Tab(text: l.payments),
                    Tab(text: l.receipts),
                    Tab(text: l.contracts),
                  ]),
                  Expanded(
                    child: TabBarView(children: [
                      ListView(padding: const EdgeInsets.all(16), children: [
                        Text(customer.address ?? customer.street ?? ''),
                        TextButton(onPressed: () => context.push('/statements?customer_id=${customer.id}'), child: Text(l.statements)),
                      ]),
                      _miniList(customer.invoices.map((i) => ListTile(title: Text(i.invoiceNumber ?? ''), trailing: Text(i.total), onTap: () => context.push('/invoices/${i.id}'))).toList()),
                      _miniList(customer.quotes.map((q) => ListTile(title: Text(q.quoteNumber ?? ''), trailing: Text(q.total), onTap: () => context.push('/quotes/${q.id}'))).toList()),
                      _miniList(customer.payments.map((p) => ListTile(title: Text(p.invoiceNumber ?? ''), trailing: Text(p.amount), onTap: () => context.push('/payments/${p.id}'))).toList()),
                      _miniList(customer.receipts.map((r) => ListTile(title: Text(r.receiptNumber ?? ''), trailing: Text(r.amount), onTap: () => context.push('/receipts/${r.id}'))).toList()),
                      _miniList(customer.contracts.map((c) => ListTile(title: Text(c.title ?? c.contractNumber ?? ''), onTap: () => context.push('/contracts/${c.id}'))).toList()),
                    ]),
                  ),
                ],
              ),
            ),
    );
  }

  Widget _miniList(List<Widget> children) {
    if (children.isEmpty) return const EmptyState(title: 'لا توجد بيانات');
    return ListView(children: children);
  }
}

class CustomerFormScreen extends ConsumerStatefulWidget {
  const CustomerFormScreen({super.key, this.id});
  final int? id;

  @override
  ConsumerState<CustomerFormScreen> createState() => _CustomerFormScreenState();
}

class _CustomerFormScreenState extends ConsumerState<CustomerFormScreen> {
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _email = TextEditingController();
  final _vat = TextEditingController();
  final _cr = TextEditingController();
  final _address = TextEditingController();
  String _type = 'individual';
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    if (widget.id != null) {
      ref.read(financeApiProvider).customer(widget.id!).then((c) {
        _name.text = c.name;
        _phone.text = c.phone ?? '';
        _email.text = c.email ?? '';
        _vat.text = c.vatNumber ?? '';
        _cr.text = c.commercialRegistration ?? '';
        _address.text = c.address ?? '';
        _type = c.partyType ?? 'individual';
        if (mounted) setState(() {});
      });
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _email.dispose();
    _vat.dispose();
    _cr.dispose();
    _address.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      final saved = await ref.read(financeApiProvider).saveCustomer({
        'name': _name.text.trim(),
        'phone': _phone.text.trim(),
        'email': _email.text.trim(),
        'vat_number': _vat.text.trim(),
        'commercial_registration': _cr.text.trim(),
        'address': _address.text.trim(),
        'party_type': _type,
      }, id: widget.id);
      if (!mounted) return;
      context.go('/customers/${saved.id}');
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
      appBar: AppBar(title: Text(widget.id == null ? l.create : l.edit)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          DropdownButtonFormField(
            // ignore: deprecated_member_use
            value: _type,
            items: [
              DropdownMenuItem(value: 'individual', child: Text(l.individual)),
              DropdownMenuItem(value: 'company', child: Text(l.company)),
            ],
            onChanged: (v) => setState(() => _type = v ?? 'individual'),
          ),
          TextField(controller: _name, decoration: InputDecoration(labelText: l.customer)),
          TextField(controller: _phone, decoration: InputDecoration(labelText: l.phone)),
          TextField(controller: _email, decoration: InputDecoration(labelText: l.email)),
          TextField(controller: _vat, decoration: InputDecoration(labelText: l.vatNumber)),
          TextField(controller: _cr, decoration: InputDecoration(labelText: l.crNumber)),
          TextField(controller: _address, decoration: InputDecoration(labelText: l.notesField)),
          const SizedBox(height: 16),
          FilledButton(onPressed: _busy ? null : _save, child: Text(l.save)),
        ],
      ),
    );
  }
}
