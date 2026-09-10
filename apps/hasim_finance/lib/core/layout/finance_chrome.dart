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
    final stacked = MediaQuery.sizeOf(context).width < 720;
    final titleBlock = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800, fontSize: stacked ? 20 : 24),
        ),
        if (subtitle != null && subtitle!.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Text(subtitle!, maxLines: 2, overflow: TextOverflow.ellipsis, style: theme.textTheme.bodySmall),
          ),
      ],
    );
    final actionRow = Wrap(spacing: 8, runSpacing: 8, crossAxisAlignment: WrapCrossAlignment.center, children: actions);
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 4),
      child: stacked
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    if (canPop) ...[
                      IconButton(
                        onPressed: () => Navigator.of(context).maybePop(),
                        icon: const Icon(Icons.arrow_back, size: 20),
                      ),
                      const SizedBox(width: 4),
                    ],
                    Expanded(child: titleBlock),
                  ],
                ),
                if (actions.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  actionRow,
                ],
              ],
            )
          : Row(
              children: [
                if (canPop) ...[
                  IconButton(
                    onPressed: () => Navigator.of(context).maybePop(),
                    icon: const Icon(Icons.arrow_back, size: 20),
                  ),
                  const SizedBox(width: 4),
                ],
                Expanded(child: titleBlock),
                actionRow,
              ],
            ),
    );
  }
}

/// Visible label stacked above a compact outline field (not a floating label).
class FinanceFilterField extends StatelessWidget {
  const FinanceFilterField({
    super.key,
    required this.label,
    required this.child,
    this.width = 180,
  });

  final String label;
  final Widget child;
  final double width;

  static const double labelGap = 6;
  static const double labelSlotHeight = 24;
  static const double stackHeight = labelSlotHeight + labelGap + FinanceTokens.controlHeight;

  static InputDecoration decoration({
    String? hintText,
    Widget? prefixIcon,
    Widget? suffixIcon,
  }) {
    return InputDecoration(
      hintText: hintText,
      prefixIcon: prefixIcon,
      suffixIcon: suffixIcon,
      isDense: true,
      floatingLabelBehavior: FloatingLabelBehavior.never,
      alignLabelWithHint: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      prefixIconConstraints: prefixIcon == null ? null : const BoxConstraints(minWidth: 36, minHeight: 36),
      suffixIconConstraints: suffixIcon == null ? null : const BoxConstraints(minWidth: 36, minHeight: 36),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SizedBox(
      width: width,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            height: labelSlotHeight,
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.visible,
                style: theme.textTheme.labelLarge?.copyWith(
                  color: FinanceTokens.textMuted,
                  fontWeight: FontWeight.w600,
                  height: 1.3,
                ),
              ),
            ),
          ),
          const SizedBox(height: labelGap),
          SizedBox(
            height: FinanceTokens.controlHeight,
            child: child,
          ),
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
    final stacked = MediaQuery.sizeOf(context).width < 720;
    final filters = SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      clipBehavior: Clip.none,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          for (var i = 0; i < children.length; i++) ...[
            if (i > 0) const SizedBox(width: 12),
            children[i],
          ],
        ],
      ),
    );
    return Container(
      margin: margin,
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      decoration: FinanceTokens.card(radius: FinanceTokens.radiusLg),
      clipBehavior: Clip.none,
      child: stacked
          ? Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(height: FinanceFilterField.stackHeight, child: filters),
                if (trailing != null) ...[
                  const SizedBox(height: 12),
                  Align(
                    alignment: AlignmentDirectional.centerEnd,
                    child: trailing!,
                  ),
                ],
              ],
            )
          : Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                const SizedBox(height: FinanceFilterField.stackHeight),
                Expanded(child: filters),
                if (trailing != null) ...[
                  const SizedBox(width: 12),
                  trailing!,
                ],
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
