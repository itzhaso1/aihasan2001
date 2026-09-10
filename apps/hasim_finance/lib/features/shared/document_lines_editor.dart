import 'package:flutter/material.dart';
import 'package:hasim_finance/core/models/models.dart';
import 'package:hasim_finance/l10n/app_localizations.dart';

class LineDraft {
  LineDraft({
    String description = '',
    String quantity = '1',
    String unitPrice = '0',
    String unit = '',
    String taxRate = '15',
    String discount = '0',
  })  : description = TextEditingController(text: description),
        quantity = TextEditingController(text: quantity),
        unitPrice = TextEditingController(text: unitPrice),
        unit = TextEditingController(text: unit),
        taxRate = TextEditingController(text: taxRate),
        discount = TextEditingController(text: discount);

  factory LineDraft.fromItem(LineItem item) {
    return LineDraft(
      description: item.productName ?? item.description ?? '',
      quantity: item.quantity ?? '1',
      unitPrice: item.unitPrice ?? '0',
      unit: item.unit ?? '',
      taxRate: item.taxRate ?? '15',
      discount: item.discount ?? '0',
    );
  }

  final TextEditingController description;
  final TextEditingController quantity;
  final TextEditingController unitPrice;
  final TextEditingController unit;
  final TextEditingController taxRate;
  final TextEditingController discount;

  Map<String, dynamic> toPayload() => {
        'product_name': description.text.trim(),
        'description': description.text.trim(),
        'quantity': quantity.text.trim(),
        'unit_price': unitPrice.text.trim(),
        'unit': unit.text.trim(),
        'tax_rate': taxRate.text.trim().isEmpty ? '15' : taxRate.text.trim(),
        'discount': discount.text.trim().isEmpty ? '0' : discount.text.trim(),
      };

  void dispose() {
    description.dispose();
    quantity.dispose();
    unitPrice.dispose();
    unit.dispose();
    taxRate.dispose();
    discount.dispose();
  }
}

class DocumentLinesEditor extends StatelessWidget {
  const DocumentLinesEditor({
    super.key,
    required this.lines,
    required this.onChanged,
  });

  final List<LineDraft> lines;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    final l = AppLocalizations.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(l.lines, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        for (var i = 0; i < lines.length; i++)
          Card(
            margin: const EdgeInsets.only(bottom: 12),
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                children: [
                  Row(
                    children: [
                      Text('${l.lines} ${i + 1}', style: Theme.of(context).textTheme.labelLarge),
                      const Spacer(),
                      if (lines.length > 1)
                        IconButton(
                          tooltip: l.removeLine,
                          onPressed: () {
                            lines[i].dispose();
                            lines.removeAt(i);
                            onChanged();
                          },
                          icon: const Icon(Icons.delete_outline),
                        ),
                    ],
                  ),
                  TextField(
                    controller: lines[i].description,
                    decoration: InputDecoration(labelText: l.description),
                  ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      SizedBox(
                        width: 120,
                        child: TextField(
                          controller: lines[i].quantity,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: InputDecoration(labelText: l.quantity),
                        ),
                      ),
                      SizedBox(
                        width: 140,
                        child: TextField(
                          controller: lines[i].unitPrice,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: InputDecoration(labelText: l.price),
                        ),
                      ),
                      SizedBox(
                        width: 100,
                        child: TextField(
                          controller: lines[i].unit,
                          decoration: InputDecoration(labelText: l.unit),
                        ),
                      ),
                      SizedBox(
                        width: 100,
                        child: TextField(
                          controller: lines[i].taxRate,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: InputDecoration(labelText: l.taxRate),
                        ),
                      ),
                      SizedBox(
                        width: 100,
                        child: TextField(
                          controller: lines[i].discount,
                          keyboardType: const TextInputType.numberWithOptions(decimal: true),
                          decoration: InputDecoration(labelText: l.discount),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        OutlinedButton.icon(
          onPressed: () {
            lines.add(LineDraft());
            onChanged();
          },
          icon: const Icon(Icons.add),
          label: Text(l.addLine),
        ),
      ],
    );
  }
}

String isoDate([DateTime? value]) {
  final date = value ?? DateTime.now();
  return '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
}
