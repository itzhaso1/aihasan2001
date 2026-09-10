import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim_finance/core/auth/auth_controller.dart';
import 'package:hasim_finance/core/permissions/finance_permissions.dart';
import 'package:hasim_finance/l10n/app_localizations.dart';

class _Dest {
  const _Dest(this.path, this.icon, this.label, this.visible);
  final String path;
  final IconData icon;
  final String Function(AppLocalizations l) label;
  final bool Function(FinancePermissions p) visible;
}

class _Group {
  const _Group(this.title, this.items);
  final String Function(AppLocalizations l) title;
  final List<_Dest> items;
}

List<_Group> _navGroups(FinancePermissions p) => [
      _Group((l) => l.navControl, [
        _Dest('/dashboard', Icons.dashboard_outlined, (l) => l.dashboard, (p) => p.financeView),
        _Dest('/billing', Icons.receipt_outlined, (l) => l.billingHub, (p) => p.invoicesView),
      ]),
      _Group((l) => l.navSales, [
        _Dest('/sales', Icons.trending_up_outlined, (l) => l.salesHub, (p) => p.financeView),
        _Dest('/quotes', Icons.request_quote_outlined, (l) => l.quotes, (p) => p.quotesView),
        _Dest('/invoices', Icons.receipt_long_outlined, (l) => l.invoices, (p) => p.invoicesView),
        _Dest('/payments', Icons.payments_outlined, (l) => l.payments, (p) => p.paymentsView),
        _Dest('/receipts', Icons.assignment_turned_in_outlined, (l) => l.receipts, (p) => p.receiptsView),
        _Dest('/statements', Icons.account_balance_outlined, (l) => l.statements, (p) => p.statementsView),
        _Dest('/leads', Icons.person_search_outlined, (l) => l.leads, (p) => p.financeView),
        _Dest('/contracts', Icons.handshake_outlined, (l) => l.contracts, (p) => p.contractsView),
        _Dest('/customers', Icons.groups_outlined, (l) => l.customers, (p) => p.customersView),
        _Dest('/price-lists', Icons.sell_outlined, (l) => l.priceLists, (p) => p.priceListsView || p.financeView),
        _Dest('/notes', Icons.note_alt_outlined, (l) => l.notes, (p) => p.notesView),
      ]),
      _Group((l) => l.navPurchases, [
        _Dest('/purchases', Icons.shopping_bag_outlined, (l) => l.purchases, (p) => p.purchasesView),
        _Dest('/purchase-orders', Icons.local_shipping_outlined, (l) => l.purchaseOrders, (p) => p.purchasesView || p.financeView),
        _Dest('/suppliers', Icons.storefront_outlined, (l) => l.suppliers, (p) => p.purchasesView),
      ]),
      _Group((l) => l.navOps, [
        _Dest('/expenses', Icons.money_off_outlined, (l) => l.expenses, (p) => p.expensesView),
        _Dest('/products', Icons.inventory_2_outlined, (l) => l.products, (p) => p.financeView),
        _Dest('/inventory', Icons.warehouse_outlined, (l) => l.inventory, (p) => p.financeView),
        _Dest('/projects', Icons.folder_outlined, (l) => l.projects, (p) => p.financeView),
      ]),
      _Group((l) => l.navAccounting, [
        _Dest('/accounting', Icons.account_tree_outlined, (l) => l.accountingHub, (p) => p.accountingView || p.financeView),
        _Dest('/fiscal-years', Icons.calendar_month_outlined, (l) => l.fiscalYears, (p) => p.fiscalYearsView || p.financeView),
        _Dest('/vat', Icons.percent_outlined, (l) => l.vatPage, (p) => p.accountingView || p.financeView),
        _Dest('/reports', Icons.bar_chart_outlined, (l) => l.reports, (p) => p.reportsView),
        _Dest('/exports', Icons.file_download_outlined, (l) => l.exports, (p) => p.financeView),
        _Dest('/alerts', Icons.notifications_outlined, (l) => l.alerts, (p) => p.financeView),
        _Dest('/copilot', Icons.psychology_outlined, (l) => l.copilot, (p) => p.financeView),
      ]),
      _Group((l) => l.navBanks, [
        _Dest('/banks', Icons.account_balance_outlined, (l) => l.banks, (p) => p.financeView),
        _Dest('/treasury', Icons.swap_horiz_outlined, (l) => l.treasury, (p) => p.financeView),
      ]),
      _Group((l) => l.settings, [
        _Dest('/settings', Icons.settings_outlined, (l) => l.settings, (_) => true),
      ]),
    ];

class FinanceShell extends ConsumerWidget {
  const FinanceShell({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    final groups = _navGroups(auth.permissions)
        .map(
          (group) => _Group(
            group.title,
            group.items.where((item) => item.visible(auth.permissions)).toList(),
          ),
        )
        .where((group) => group.items.isNotEmpty)
        .toList();
    final dests = [for (final group in groups) ...group.items];
    final path = GoRouterState.of(context).uri.path;
    final wide = MediaQuery.sizeOf(context).width >= 980;
    final selectedIndex = dests.indexWhere((d) => path == d.path || path.startsWith('${d.path}/'));

    final sidebar = ColoredBox(
      color: Theme.of(context).colorScheme.surface,
      child: SizedBox(
        width: MediaQuery.sizeOf(context).width >= 1280 ? 280 : 248,
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 18, 16, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.account_balance_wallet, color: Color(0xFF06C2A4), size: 28),
                  const SizedBox(height: 8),
                  Text(l.appName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
                  if (auth.workspace != null)
                    Text(auth.workspace!.name, style: Theme.of(context).textTheme.bodySmall),
                  const SizedBox(height: 10),
                  OutlinedButton.icon(
                    onPressed: () => context.go('/search'),
                    icon: const Icon(Icons.search, size: 18),
                    label: Text(l.globalSearch),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.fromLTRB(8, 8, 8, 24),
                children: [
                  for (final group in groups) ...[
                    Padding(
                      padding: const EdgeInsets.fromLTRB(10, 10, 10, 4),
                      child: Text(
                        group.title(l),
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.4,
                              color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.55),
                            ),
                      ),
                    ),
                    for (final item in group.items)
                      ListTile(
                        dense: true,
                        visualDensity: VisualDensity.compact,
                        selected: path == item.path || path.startsWith('${item.path}/'),
                        leading: Icon(item.icon, size: 20),
                        title: Text(item.label(l)),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                        onTap: () => context.go(item.path),
                      ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );

    final bottom = NavigationBar(
      selectedIndex: selectedIndex < 0 ? 0 : (selectedIndex > 3 ? 3 : selectedIndex),
      onDestinationSelected: (i) {
        if (i < 3 && dests.length > i) {
          context.go(dests[i].path);
        } else {
          _showMore(context, groups, dests.take(3).map((d) => d.path).toSet(), l);
        }
      },
      destinations: [
        for (final d in dests.take(3)) NavigationDestination(icon: Icon(d.icon), label: d.label(l)),
        NavigationDestination(icon: const Icon(Icons.more_horiz), label: l.more),
      ],
    );

    return Scaffold(
      body: wide
          ? Row(
              children: [
                sidebar,
                const VerticalDivider(width: 1),
                Expanded(child: child),
              ],
            )
          : child,
      bottomNavigationBar: wide ? null : bottom,
    );
  }

  void _showMore(BuildContext context, List<_Group> groups, Set<String> pinned, AppLocalizations l) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (ctx) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.7,
        builder: (_, controller) => ListView(
          controller: controller,
          children: [
            for (final group in groups) ...[
              if (group.items.any((item) => !pinned.contains(item.path)))
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                  child: Text(group.title(l), style: const TextStyle(fontWeight: FontWeight.w800)),
                ),
              for (final d in group.items.where((item) => !pinned.contains(item.path)))
                ListTile(
                  leading: Icon(d.icon),
                  title: Text(d.label(l)),
                  onTap: () {
                    Navigator.pop(ctx);
                    context.go(d.path);
                  },
                ),
            ],
          ],
        ),
      ),
    );
  }
}
