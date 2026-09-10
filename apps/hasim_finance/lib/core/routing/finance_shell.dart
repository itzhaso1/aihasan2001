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

List<_Dest> _destinations(FinancePermissions p) => [
      _Dest('/dashboard', Icons.dashboard_outlined, (l) => l.dashboard, (p) => p.financeView),
      _Dest('/customers', Icons.groups_outlined, (l) => l.customers, (p) => p.customersView),
      _Dest('/quotes', Icons.request_quote_outlined, (l) => l.quotes, (p) => p.quotesView),
      _Dest('/invoices', Icons.receipt_long_outlined, (l) => l.invoices, (p) => p.invoicesView),
      _Dest('/payments', Icons.payments_outlined, (l) => l.payments, (p) => p.paymentsView),
      _Dest('/receipts', Icons.assignment_turned_in_outlined, (l) => l.receipts, (p) => p.receiptsView),
      _Dest('/statements', Icons.account_balance_outlined, (l) => l.statements, (p) => p.statementsView),
      _Dest('/notes', Icons.note_alt_outlined, (l) => l.notes, (p) => p.notesView),
      _Dest('/contracts', Icons.handshake_outlined, (l) => l.contracts, (p) => p.contractsView),
      _Dest('/expenses', Icons.money_off_outlined, (l) => l.expenses, (p) => p.expensesView),
      _Dest('/purchases', Icons.shopping_bag_outlined, (l) => l.purchases, (p) => p.purchasesView),
      _Dest('/reports', Icons.bar_chart_outlined, (l) => l.reports, (p) => p.reportsView),
      _Dest('/settings', Icons.settings_outlined, (l) => l.settings, (_) => true),
    ];

class FinanceShell extends ConsumerWidget {
  const FinanceShell({super.key, required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l = AppLocalizations.of(context);
    final auth = ref.watch(authControllerProvider);
    final dests = _destinations(auth.permissions).where((d) => d.visible(auth.permissions)).toList();
    final path = GoRouterState.of(context).uri.path;
    final selected = dests.indexWhere((d) => path == d.path || path.startsWith('${d.path}/'));
    final wide = MediaQuery.sizeOf(context).width >= 980;

    final rail = NavigationRail(
      extended: MediaQuery.sizeOf(context).width >= 1280,
      selectedIndex: selected < 0 ? 0 : selected,
      onDestinationSelected: (i) => context.go(dests[i].path),
      leading: Padding(
        padding: const EdgeInsets.symmetric(vertical: 16),
        child: Column(
          children: [
            const Icon(Icons.account_balance_wallet, color: Color(0xFF06C2A4), size: 32),
            const SizedBox(height: 8),
            Text(l.appName, style: const TextStyle(fontWeight: FontWeight.w800)),
            if (auth.workspace != null)
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: Text(auth.workspace!.name, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodySmall),
              ),
          ],
        ),
      ),
      destinations: [
        for (final d in dests) NavigationRailDestination(icon: Icon(d.icon), label: Text(d.label(l))),
      ],
    );

    final bottom = NavigationBar(
      selectedIndex: selected < 0 ? 0 : (selected > 4 ? 4 : selected),
      onDestinationSelected: (i) {
        if (i < 4) {
          context.go(dests[i].path);
        } else {
          _showMore(context, dests.skip(4).toList(), l);
        }
      },
      destinations: [
        for (final d in dests.take(4)) NavigationDestination(icon: Icon(d.icon), label: d.label(l)),
        NavigationDestination(icon: const Icon(Icons.more_horiz), label: l.more),
      ],
    );

    return Scaffold(
      body: wide
          ? Row(
              children: [
                rail,
                const VerticalDivider(width: 1),
                Expanded(child: child),
              ],
            )
          : child,
      bottomNavigationBar: wide ? null : bottom,
    );
  }

  void _showMore(BuildContext context, List<_Dest> extra, AppLocalizations l) {
    showModalBottomSheet<void>(
      context: context,
      builder: (ctx) => ListView(
        children: [
          for (final d in extra)
            ListTile(
              leading: Icon(d.icon),
              title: Text(d.label(l)),
              onTap: () {
                Navigator.pop(ctx);
                context.go(d.path);
              },
            ),
        ],
      ),
    );
  }
}
