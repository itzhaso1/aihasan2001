import 'package:flutter/material.dart';
import 'package:hasim_finance/core/theme/finance_tokens.dart';

class FinanceScaffold extends StatelessWidget {
  const FinanceScaffold({
    super.key,
    required this.title,
    required this.body,
    this.subtitle,
    this.actions = const [],
    this.floatingActionButton,
    this.primaryAction,
    this.showBack = false,
  });

  final String title;
  final String? subtitle;
  final List<Widget> actions;
  final Widget body;
  final Widget? floatingActionButton;
  final Widget? primaryAction;
  final bool showBack;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      floatingActionButton: floatingActionButton,
      body: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          FinancePageHeader(
            title: title,
            subtitle: subtitle,
            actions: [
              ...actions,
              ?primaryAction,
            ],
            showBack: showBack,
          ),
          Expanded(child: body),
        ],
      ),
    );
  }
}

class FinancePageHeader extends StatelessWidget {
  const FinancePageHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.actions = const [],
    this.showBack = false,
  });

  final String title;
  final String? subtitle;
  final List<Widget> actions;
  final bool showBack;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final canPop = showBack && Navigator.of(context).canPop();
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 4),
      child: Row(
        children: [
          if (canPop) ...[
            IconButton(
              onPressed: () => Navigator.of(context).maybePop(),
              icon: const Icon(Icons.arrow_back, size: 20),
            ),
            const SizedBox(width: 4),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800, fontSize: 24)),
                if (subtitle != null && subtitle!.isNotEmpty)
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Text(subtitle!, style: theme.textTheme.bodySmall),
                  ),
              ],
            ),
          ),
          Wrap(spacing: 8, runSpacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: actions),
        ],
      ),
    );
  }
}

class FinanceFilterBar extends StatelessWidget {
  const FinanceFilterBar({
    super.key,
    required this.children,
    this.trailing,
    this.margin = const EdgeInsets.fromLTRB(20, 8, 20, 12),
  });

  final List<Widget> children;
  final Widget? trailing;
  final EdgeInsets margin;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: margin,
      padding: const EdgeInsets.all(12),
      decoration: FinanceTokens.card(radius: FinanceTokens.radiusLg),
      child: Wrap(
        spacing: 10,
        runSpacing: 10,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          ...children,
          ?trailing,
        ],
      ),
    );
  }
}

class FinanceSurface extends StatelessWidget {
  const FinanceSurface({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.margin,
    this.title,
    this.trailing,
  });

  final Widget child;
  final EdgeInsets padding;
  final EdgeInsets? margin;
  final String? title;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: FinanceTokens.surface,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(FinanceTokens.radiusLg),
        side: const BorderSide(color: FinanceTokens.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (title != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
              child: Row(
                children: [
                  Expanded(
                    child: Text(title!, style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                  ),
                  ?trailing,
                ],
              ),
            ),
          Padding(padding: padding, child: child),
        ],
      ),
    );
  }
}

class FinanceIconBadge extends StatelessWidget {
  const FinanceIconBadge({super.key, required this.icon, required this.tone, this.size = 40});

  final IconData icon;
  final FinanceIconTone tone;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: tone.background, borderRadius: BorderRadius.circular(12)),
      child: Icon(icon, color: tone.foreground, size: size * 0.48),
    );
  }
}
