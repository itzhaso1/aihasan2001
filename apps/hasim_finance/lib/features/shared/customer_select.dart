import 'dart:async';

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
  final _search = TextEditingController();
  Timer? _debounce;
  List<CustomerRecord> _customers = [];
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
      final page = await ref.read(financeApiProvider).customers(
            search: _search.text.trim(),
            page: _page,
          );
      if (!mounted) return;
      var items = reset ? page.items : [..._customers, ...page.items];
      final selectedId = widget.selectedId;
      if (selectedId != null && !items.any((row) => row.id == selectedId)) {
        try {
          final selected = await ref.read(financeApiProvider).customer(selectedId);
          items = [selected, ...items.where((row) => row.id != selected.id)];
        } catch (_) {}
      }
      setState(() {
        _customers = items;
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
    final ids = {for (final c in _customers) c.id};
    final value = widget.selectedId != null && ids.contains(widget.selectedId) ? widget.selectedId : null;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: _search,
          decoration: InputDecoration(prefixIcon: const Icon(Icons.search), hintText: l.selectCustomer),
          onChanged: (_) {
            _debounce?.cancel();
            _debounce = Timer(const Duration(milliseconds: 300), () => _load(reset: true));
          },
        ),
        DropdownButtonFormField<int>(
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
