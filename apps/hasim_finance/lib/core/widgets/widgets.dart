import 'package:flutter/material.dart';

class EmptyState extends StatelessWidget {
  const EmptyState({
    super.key,
    required this.title,
    this.subtitle,
    this.icon = Icons.inbox_outlined,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String? subtitle;
  final IconData icon;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    final muted = Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.55);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 52, color: muted),
            const SizedBox(height: 14),
            Text(title, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            if (subtitle != null) ...[
              const SizedBox(height: 6),
              Text(subtitle!, textAlign: TextAlign.center, style: TextStyle(color: muted, height: 1.4)),
            ],
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: 16),
              FilledButton(onPressed: onAction, child: Text(actionLabel!)),
            ],
          ],
        ),
      ),
    );
  }
}

class AsyncBody extends StatelessWidget {
  const AsyncBody({
    super.key,
    required this.loading,
    required this.onRetry,
    required this.child,
    this.error,
    this.isEmpty = false,
    this.emptyTitle = 'لا توجد بيانات',
    this.emptySubtitle,
  });

  final bool loading;
  final String? error;
  final bool isEmpty;
  final VoidCallback onRetry;
  final Widget child;
  final String emptyTitle;
  final String? emptySubtitle;

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(error!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: onRetry, child: const Text('إعادة المحاولة')),
            ],
          ),
        ),
      );
    }
    if (isEmpty) {
      return EmptyState(title: emptyTitle, subtitle: emptySubtitle);
    }
    return child;
  }
}

class StatusChip extends StatelessWidget {
  const StatusChip({super.key, required this.label, this.tone = StatusTone.neutral});

  final String label;
  final StatusTone tone;

  @override
  Widget build(BuildContext context) {
    final color = switch (tone) {
      StatusTone.success => const Color(0xFF067E6B),
      StatusTone.warning => const Color(0xFFB45309),
      StatusTone.danger => const Color(0xFFB91C1C),
      StatusTone.info => const Color(0xFF1D4ED8),
      StatusTone.neutral => Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.7),
    };
    final bg = switch (tone) {
      StatusTone.success => const Color(0xFFE6F7F1),
      StatusTone.warning => const Color(0xFFFEF3C7),
      StatusTone.danger => const Color(0xFFFEE2E2),
      StatusTone.info => const Color(0xFFDBEAFE),
      StatusTone.neutral => const Color(0xFFEEF3F6),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 11)),
    );
  }
}

enum StatusTone { success, warning, danger, info, neutral }

StatusTone toneFor(String? value) {
  switch (value) {
    case 'issued':
    case 'paid':
    case 'posted':
    case 'accepted':
    case 'sent':
    case 'open':
    case 'active':
      return StatusTone.success;
    case 'draft':
    case 'pending':
    case 'partial':
    case 'ready':
      return StatusTone.info;
    case 'overdue':
    case 'failed':
    case 'rejected':
    case 'voided':
    case 'reversed':
    case 'cancelled':
      return StatusTone.danger;
    case 'expired':
    case 'unpaid':
      return StatusTone.warning;
    default:
      return StatusTone.neutral;
  }
}

class MoneyText extends StatelessWidget {
  const MoneyText(this.value, {super.key, this.currency = 'ر.س', this.style});

  final String value;
  final String currency;
  final TextStyle? style;

  @override
  Widget build(BuildContext context) {
    return Text(
      '$value $currency',
      style: style ?? Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800, letterSpacing: -0.2),
    );
  }
}

class PermissionGate extends StatelessWidget {
  const PermissionGate({
    super.key,
    required this.allowed,
    required this.child,
    this.message = 'هذه الشاشة غير متاحة لصلاحياتك الحالية.',
  });

  final bool allowed;
  final Widget child;
  final String message;

  @override
  Widget build(BuildContext context) {
    if (allowed) return child;
    return EmptyState(title: message, icon: Icons.lock_outline);
  }
}
