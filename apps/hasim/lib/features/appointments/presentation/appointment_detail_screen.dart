
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_exception.dart';
import 'package:intl/intl.dart' hide TextDirection;

class AppointmentDetailScreen extends ConsumerStatefulWidget {
  const AppointmentDetailScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<AppointmentDetailScreen> createState() => _AppointmentDetailScreenState();
}

class _AppointmentDetailScreenState extends ConsumerState<AppointmentDetailScreen> {
  AppointmentModel? _item;
  bool _loading = true;
  String? _error;
  final _reschedule = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _reschedule.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final item = await ref.read(appointmentRepositoryProvider).show(widget.id);
      setState(() { _item = item; _loading = false; });
    } on ApiException catch (e) {
      setState(() { _error = e.message; _loading = false; });
    } catch (_) {
      setState(() { _error = 'تعذر فتح الحجز.'; _loading = false; });
    }
  }

  Future<void> _run(Future<AppointmentModel> Function() action) async {
    try {
      final updated = await action();
      setState(() => _item = updated);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('تم التحديث')));
    } on ApiException catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final a = _item;
    return Scaffold(
      appBar: AppBar(title: const Text('تفاصيل الحجز')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Text(a!.customerName ?? 'عميل', style: Theme.of(context).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    Text('الحالة: ${a.statusLabel}'),
                    Text('الدفع: ${a.paymentStatus ?? '—'}'),
                    Text('الخدمة: ${a.serviceName ?? '—'}'),
                    Text('الموظف: ${a.staffName ?? '—'}'),
                    Text('الوقت: ${a.startsAt == null ? '—' : DateFormat('yyyy/MM/dd HH:mm').format(a.startsAt!.toLocal())}'),
                    if (a.notes != null && a.notes!.isNotEmpty) Text('ملاحظات: ${a.notes}'),
                    const SizedBox(height: 20),
                    FilledButton(onPressed: () => _run(() => ref.read(appointmentRepositoryProvider).confirm(a.id)), child: const Text('تأكيد')),
                    const SizedBox(height: 8),
                    OutlinedButton(onPressed: () => _run(() => ref.read(appointmentRepositoryProvider).cancel(a.id, reason: 'إلغاء من تطبيق حاسم')), child: const Text('إلغاء')),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _reschedule,
                      decoration: const InputDecoration(
                        labelText: 'إعادة الجدولة (starts_at)',
                        helperText: 'مثال: 2026-09-02 15:30',
                        hintText: 'YYYY-MM-DD HH:mm',
                      ),
                      textDirection: TextDirection.ltr,
                    ),
                    const SizedBox(height: 8),
                    FilledButton.tonal(
                      onPressed: () => _run(() => ref.read(appointmentRepositoryProvider).reschedule(a.id, startsAt: _reschedule.text.trim())),
                      child: const Text('إعادة جدولة'),
                    ),
                  ],
                ),
    );
  }
}
