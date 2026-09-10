import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim_finance/core/auth/auth_controller.dart';
import 'package:hasim_finance/core/permissions/finance_permissions.dart';
import 'package:hasim_finance/core/theme/finance_tokens.dart';
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
        _Dest('/dashboard', Icons.grid_view_rounded, (l) => l.dashboard, (p) => p.financeView),
        _Dest('/billing', Icons.receipt_long_outlined, (l) => l.billingHub, (p) => p.invoicesView),
      ]),
      _Group((l) => l.navSales, [
        _Dest('/sales', Icons.trending_up_rounded, (l) => l.salesHub, (p) => p.financeView),
        _Dest('/quotes', Icons.request_quote_outlined, (l) => l.quotes, (p) => p.quotesView),
        _Dest('/invoices', Icons.description_outlined, (l) => l.invoices, (p) => p.invoicesView),
        _Dest('/payments', Icons.payments_outlined, (l) => l.payments, (p) => p.paymentsView),
        _Dest('/receipts', Icons.assignment_turned_in_outlined, (l) => l.receipts, (p) => p.receiptsView),
        _Dest('/statements', Icons.account_balance_wallet_outlined, (l) => l.statements, (p) => p.statementsView),
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
      _Group((l) => l.navPeople, [
        _Dest('/people', Icons.badge_outlined, (l) => l.peopleObligations, (p) => p.payrollView),
        _Dest('/payroll', Icons.account_balance_wallet_outlined, (l) => l.payroll, (p) => p.payrollView),
        _Dest('/advances', Icons.payments_outlined, (l) => l.salaryAdvances, (p) => p.salaryAdvancesView),
        _Dest('/allowances', Icons.add_card_outlined, (l) => l.allowances, (p) => p.adjustmentsView),
        _Dest('/bonuses', Icons.emoji_events_outlined, (l) => l.bonuses, (p) => p.adjustmentsView),
        _Dest('/deductions', Icons.remove_circle_outline, (l) => l.deductions, (p) => p.adjustmentsView),
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

    final sidebar = Container(
      width: MediaQuery.sizeOf(context).width >= 1440 ? FinanceTokens.sidebarWidthWide : FinanceTokens.sidebarWidth,
      decoration: const BoxDecoration(
        color: FinanceTokens.sidebar,
        border: Border(left: BorderSide(color: FinanceTokens.border), right: BorderSide(color: FinanceTokens.border)),
      ),
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 12),
            child: Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: FinanceTokens.brand,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(Icons.account_balance_wallet_rounded, color: Colors.white, size: 20),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(l.appName, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, height: 1.2)),
                      Text(
                        auth.workspace?.name ?? l.workspace,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(fontSize: 11, color: FinanceTokens.textMuted, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const Divider(height: 1),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(10, 10, 10, 24),
              children: [
                for (final group in groups) ...[
                  Padding(
                    padding: const EdgeInsets.fromLTRB(10, 12, 10, 6),
                    child: Text(
                      group.title(l),
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: FinanceTokens.textFaint,
                      ),
                    ),
                  ),
                  for (final item in group.items)
                    _NavTile(
                      icon: item.icon,
                      label: item.label(l),
                      selected: path == item.path || path.startsWith('${item.path}/'),
                      onTap: () => context.go(item.path),
                    ),
                ],
              ],
            ),
          ),
        ],
      ),
    );

    final header = Container(
      height: FinanceTokens.headerHeight,
      decoration: const BoxDecoration(
        color: FinanceTokens.header,
        border: Border(bottom: BorderSide(color: FinanceTokens.border)),
      ),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        children: [
          _HeaderIconButton(icon: Icons.search_rounded, onPressed: () => context.go('/search'), tooltip: l.globalSearch),
          const SizedBox(width: 10),
          Expanded(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: InkWell(
                onTap: () => context.go('/search'),
                borderRadius: BorderRadius.circular(999),
                child: Container(
                  height: 40,
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  decoration: BoxDecoration(
                    color: FinanceTokens.canvasAlt,
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(color: FinanceTokens.border),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.search_rounded, size: 18, color: FinanceTokens.textFaint),
                      const SizedBox(width: 8),
                      Text(l.searchPlaceholder, style: const TextStyle(color: FinanceTokens.textFaint, fontSize: 13)),
                    ],
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 10),
          _HeaderIconButton(icon: Icons.notifications_none_rounded, onPressed: () => context.go('/alerts'), tooltip: l.alerts),
          const SizedBox(width: 4),
          _HeaderIconButton(icon: Icons.settings_outlined, onPressed: () => context.go('/settings'), tooltip: l.settings),
          const SizedBox(width: 8),
          _UserChip(
            name: auth.user?.name ?? '',
            workspace: auth.workspace?.name ?? '',
            onPressed: () => context.go('/settings'),
          ),
        ],
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
                Expanded(
                  child: Column(
                    children: [
                      header,
                      Expanded(child: child),
                    ],
                  ),
                ),
              ],
            )
          : Column(
              children: [
                header,
                Expanded(child: child),
              ],
            ),
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

class _NavTile extends StatelessWidget {
  const _NavTile({required this.icon, required this.label, required this.selected, required this.onTap});

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 1),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(10),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
          decoration: FinanceTokens.sidebarItem(selected: selected),
          child: Row(
            children: [
              Icon(icon, size: 18, color: selected ? FinanceTokens.brandDark : FinanceTokens.textMuted),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  label,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                    color: selected ? FinanceTokens.brandDark : FinanceTokens.text,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _HeaderIconButton extends StatelessWidget {
  const _HeaderIconButton({required this.icon, required this.onPressed, required this.tooltip});

  final IconData icon;
  final VoidCallback onPressed;
  final String tooltip;

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: InkWell(
        onTap: onPressed,
        borderRadius: BorderRadius.circular(12),
        child: Container(
          width: 40,
          height: 40,
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: FinanceTokens.border),
            color: FinanceTokens.surface,
          ),
          child: Icon(icon, size: 18, color: FinanceTokens.textMuted),
        ),
      ),
    );
  }
}

class _UserChip extends StatelessWidget {
  const _UserChip({required this.name, required this.workspace, required this.onPressed});

  final String name;
  final String workspace;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onPressed,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: FinanceTokens.border),
        ),
        child: Row(
          children: [
            CircleAvatar(
              radius: 14,
              backgroundColor: FinanceTokens.brandSoft,
              child: Text(
                name.isEmpty ? 'ح' : name.substring(0, 1),
                style: const TextStyle(fontWeight: FontWeight.w800, color: FinanceTokens.brandDark, fontSize: 12),
              ),
            ),
            const SizedBox(width: 8),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(name, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12, height: 1.1)),
                Text(workspace, style: const TextStyle(fontSize: 10, color: FinanceTokens.textMuted, height: 1.1)),
              ],
            ),
            const SizedBox(width: 4),
          ],
        ),
      ),
    );
  }
}
