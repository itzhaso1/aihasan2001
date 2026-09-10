import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim_finance/core/auth/auth_controller.dart';
import 'package:hasim_finance/core/models/models.dart';
import 'package:hasim_finance/l10n/app_localizations.dart';

class CustomerSelectField extends ConsumerStatefulWidget {
  const CustomerSelectField({
    super.key,
    required this.selectedId,
    required this.onSelected,
  });

  final int? selectedId;
  final ValueChanged<int> onSelected;

  @override
  ConsumerState<CustomerSelectField> createState() => _CustomerSelectFieldState();
}

class _CustomerSelectFieldState extends ConsumerState<CustomerSelectField> {
  List<CustomerRecord> _customers = [];

  @override
  void initState() {
    super.initState();
    ref.read(financeApiProvider).customers(page: 1).then((page) {
      if (!mounted) return;
      setState(() => _customers = page.items);
    }).catchError((_) {});
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final ids = {for (final c in _customers) c.id};
    final value = widget.selectedId != null && ids.contains(widget.selectedId) ? widget.selectedId : null;
    return DropdownButtonFormField<int>(
      // ignore: deprecated_member_use
      value: value,
      decoration: InputDecoration(labelText: l.selectCustomer),
      items: [
        for (final customer in _customers)
          DropdownMenuItem(value: customer.id, child: Text(customer.name)),
      ],
      onChanged: (id) {
        if (id != null) widget.onSelected(id);
      },
    );
  }
}
