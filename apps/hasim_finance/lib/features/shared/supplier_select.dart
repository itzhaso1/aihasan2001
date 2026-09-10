import 'dart:async';

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
  final _search = TextEditingController();
  Timer? _debounce;
  List<SupplierRecord> _suppliers = [];
  int _page = 1;
  int _lastPage = 1;
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _load(reset: true);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<void> _load({bool reset = false}) async {
    if (reset) {
      _page = 1;
    }
    setState(() => _loading = true);
    try {
      final page = await ref.read(financeApiProvider).suppliers(
            search: _search.text.trim(),
            page: _page,
          );
      if (!mounted) return;
      setState(() {
        _suppliers = reset ? page.items : [..._suppliers, ...page.items];
        _lastPage = page.lastPage;
        _loading = false;
      });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    final ids = {for (final supplier in _suppliers) supplier.id};
    final value = widget.selectedId != null && ids.contains(widget.selectedId) ? widget.selectedId : null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: _search,
          decoration: InputDecoration(prefixIcon: const Icon(Icons.search), hintText: l.selectSupplier),
          onChanged: (_) {
            _debounce?.cancel();
            _debounce = Timer(const Duration(milliseconds: 300), () => _load(reset: true));
          },
        ),
        DropdownButtonFormField<int>(
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
        ),
        if (_page < _lastPage)
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton(
              onPressed: _loading
                  ? null
                  : () {
                      _page += 1;
                      _load();
                    },
              child: Text(l.loadMore),
            ),
          ),
      ],
    );
  }
}
