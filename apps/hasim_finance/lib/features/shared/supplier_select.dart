import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim_finance/core/auth/auth_controller.dart';
import 'package:hasim_finance/core/models/models.dart';
import 'package:hasim_finance/l10n/app_localizations.dart';

class SupplierSelectField extends ConsumerStatefulWidget {
  const SupplierSelectField({
    super.key,
    required this.selectedId,
    required this.onSelected,
  });

  final int? selectedId;
  final ValueChanged<int> onSelected;

  @override
  ConsumerState<SupplierSelectField> createState() => _SupplierSelectFieldState();
}

class _SupplierSelectFieldState extends ConsumerState<SupplierSelectField> {
  List<SupplierRecord> _suppliers = [];

  @override
  void initState() {
    super.initState();
    ref.read(financeApiProvider).suppliers(page: 1).then((page) {
      if (!mounted) return;
      setState(() => _suppliers = page.items);
    }).catchError((_) {});
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final ids = {for (final supplier in _suppliers) supplier.id};
    final value = widget.selectedId != null && ids.contains(widget.selectedId) ? widget.selectedId : null;
    return DropdownButtonFormField<int>(
      // ignore: deprecated_member_use
      value: value,
      decoration: InputDecoration(labelText: l.selectSupplier),
      items: [
        for (final supplier in _suppliers)
          DropdownMenuItem(value: supplier.id, child: Text(supplier.name)),
      ],
      onChanged: (id) {
        if (id != null) widget.onSelected(id);
      },
    );
  }
}
