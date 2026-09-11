import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/theme/app_theme.dart';

/// عناصر قائمة ⋮ — مرتبطة بمسارات HASIM الحالية فقط.
const hasimMoreMenuItems = <({String id, String label, IconData icon})>[
  (id: 'new_group', label: 'مجموعة جديدة', icon: Icons.group_outlined),
  (id: 'communities', label: 'مجتمعات', icon: Icons.groups_outlined),
  (id: 'starred', label: 'الرسائل المميزة بنجمة', icon: Icons.star_border_rounded),
  (id: 'devices', label: 'الأجهزة المرتبطة', icon: Icons.laptop_outlined),
  (id: 'settings', label: 'الإعدادات', icon: Icons.settings_outlined),
];

/// قائمة «المزيد» العائمة من زر ⋮ في الهيدر (ليست Bottom Sheet).
Future<void> showHasimMoreMenu(BuildContext context) async {
  final value = await _showHasimMorePopup(context);
  if (value == null || !context.mounted) return;
  switch (value) {
    case 'new_group':
      context.push('/contact-groups');
    case 'communities':
      context.push('/channels');
    case 'starred':
      context.push('/contacts', extra: const {'favorites': true});
    case 'devices':
      context.push('/more/security');
    case 'settings':
      context.push('/settings');
  }
}

Future<String?> _showHasimMorePopup(BuildContext anchorContext) {
  final overlay = Overlay.of(anchorContext, rootOverlay: true).context.findRenderObject();
  final box = anchorContext.findRenderObject();
  if (overlay is! RenderBox || box is! RenderBox) {
    return Future.value(null);
  }

  final origin = box.localToGlobal(Offset.zero, ancestor: overlay);
  final buttonSize = box.size;
  final screen = overlay.size;
  const menuWidth = 248.0;
  const estimatedHeight = 252.0;
  const margin = 10.0;

  var left = margin;
  var top = origin.dy + buttonSize.height - 2;
  if (left + menuWidth > screen.width - margin) {
    left = screen.width - menuWidth - margin;
  }
  if (top + estimatedHeight > screen.height - margin) {
    top = (origin.dy - estimatedHeight + 8).clamp(margin, screen.height - estimatedHeight - margin);
  }

  return showGeneralDialog<String>(
    context: anchorContext,
    useRootNavigator: true,
    barrierDismissible: true,
    barrierLabel: 'إغلاق القائمة',
    barrierColor: const Color(0x14000000),
    transitionDuration: const Duration(milliseconds: 160),
    pageBuilder: (ctx, animation, secondary) {
      return Stack(
        children: [
          Positioned(
            top: top,
            left: left,
            child: _HasimMorePopupCard(
              onSelect: (id) => Navigator.of(ctx).pop(id),
            ),
          ),
        ],
      );
    },
    transitionBuilder: (context, animation, secondary, child) {
      final curved = CurvedAnimation(parent: animation, curve: Curves.easeOutCubic);
      return FadeTransition(
        opacity: curved,
        child: ScaleTransition(
          alignment: Alignment.topLeft,
          scale: Tween<double>(begin: 0.96, end: 1).animate(curved),
          child: child,
        ),
      );
    },
  );
}

class _HasimMorePopupCard extends StatelessWidget {
  const _HasimMorePopupCard({required this.onSelect});

  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final fg = isDark ? Colors.white : const Color(0xFF1A2B28);

    return Material(
      color: Colors.transparent,
      child: SizedBox(
        width: 248,
        child: DecoratedBox(
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF15201D) : Colors.white,
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: isDark ? const Color(0xFF1F2E2A) : const Color(0xFFE7F0ED),
            ),
            boxShadow: const [
              BoxShadow(
                color: Color(0x14000000),
                blurRadius: 18,
                offset: Offset(0, 8),
              ),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(20),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 4),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  for (final item in hasimMoreMenuItems)
                    _HasimMoreMenuRow(
                      icon: item.icon,
                      label: item.label,
                      color: fg,
                      onTap: () => onSelect(item.id),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _HasimMoreMenuRow extends StatelessWidget {
  const _HasimMoreMenuRow({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      splashColor: AppTheme.brand.withValues(alpha: 0.10),
      highlightColor: AppTheme.brand.withValues(alpha: 0.06),
      child: Padding(
        padding: const EdgeInsetsDirectional.fromSTEB(14, 11, 16, 11),
        child: Row(
          children: [
            Icon(icon, size: 22, color: color.withValues(alpha: 0.82)),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.start,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                      fontSize: 14,
                      color: color,
                      height: 1.25,
                    ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
