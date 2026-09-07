import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/widgets/async_body.dart';
import 'package:hasim/core/widgets/hasim_shell_header.dart';
import 'package:hasim/features/appointments/providers/appointments_controller.dart';
import 'package:intl/intl.dart' hide TextDirection;

class AppointmentsScreen extends ConsumerWidget {
  const AppointmentsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(appointmentsControllerProvider);
    final notifier = ref.read(appointmentsControllerProvider.notifier);
    return Scaffold(
      appBar: const HasimShellHeader(),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Align(
              alignment: AlignmentDirectional.centerStart,
              child: Text('الحجوزات', style: Theme.of(context).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(8),
            child: SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'today', label: Text('اليوم')),
                ButtonSegment(value: 'upcoming', label: Text('القادمة')),
              ],
              selected: {state.tab},
              onSelectionChanged: (s) => notifier.setTab(s.first),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: notifier.refresh,
              child: AsyncBody(
                loading: state.loading && state.items.isEmpty,
                error: state.error,
                isEmpty: !state.loading && state.items.isEmpty,
                emptyTitle: 'لا حجوزات',
                onRetry: notifier.refresh,
                child: ListView.separated(
                  itemCount: state.items.length,
                  separatorBuilder: (_, _) => const Divider(height: 1),
                  itemBuilder: (context, i) {
                    final a = state.items[i];
                    final when = a.startsAt == null ? '—' : DateFormat('yyyy/MM/dd HH:mm').format(a.startsAt!.toLocal());
                    return ListTile(
                      onTap: () => context.push('/appointments/${a.id}'),
                      title: Text(a.customerName ?? a.bookingNumber ?? 'حجز #${a.id}'),
                      subtitle: Text('$when · ${a.serviceName ?? 'خدمة'} · ${a.statusLabel}'),
                      trailing: const Icon(Icons.chevron_left),
                    );
                  },
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
