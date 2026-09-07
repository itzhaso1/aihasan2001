import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';

/// قائمة «المزيد» المشتركة من زر ⋯ في الهيدر.
Future<void> showHasimMoreMenu(BuildContext context, WidgetRef ref) async {
  final value = await showModalBottomSheet<String>(
    context: context,
    showDragHandle: true,
    builder: (ctx) => SafeArea(
      child: ListView(
        shrinkWrap: true,
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: Text('المزيد', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
          ),
          ListTile(
            leading: const Icon(Icons.contacts_outlined),
            title: const Text('جهات الاتصال'),
            onTap: () => Navigator.pop(ctx, 'contacts'),
          ),
          ListTile(
            leading: const Icon(Icons.hub_outlined),
            title: const Text('القنوات'),
            onTap: () => Navigator.pop(ctx, 'channels'),
          ),
          ListTile(
            leading: const Icon(Icons.workspace_premium_outlined),
            title: const Text('الباقة والاستخدام'),
            onTap: () => Navigator.pop(ctx, 'plans'),
          ),
          ListTile(
            leading: const Icon(Icons.settings_outlined),
            title: const Text('الإعدادات'),
            onTap: () => Navigator.pop(ctx, 'settings'),
          ),
          ListTile(
            leading: const Icon(Icons.security_outlined),
            title: const Text('الأمان والجلسات'),
            onTap: () => Navigator.pop(ctx, 'security'),
          ),
          ListTile(
            leading: const Icon(Icons.notifications_outlined),
            title: const Text('الإشعارات'),
            onTap: () => Navigator.pop(ctx, 'notifications'),
          ),
          ListTile(
            leading: const Icon(Icons.swap_horiz),
            title: const Text('تبديل مساحة العمل'),
            onTap: () => Navigator.pop(ctx, 'workspace'),
          ),
          const Divider(),
          ListTile(
            leading: Icon(Icons.logout, color: Theme.of(ctx).colorScheme.error),
            title: Text('تسجيل الخروج', style: TextStyle(color: Theme.of(ctx).colorScheme.error)),
            onTap: () => Navigator.pop(ctx, 'logout'),
          ),
        ],
      ),
    ),
  );

  if (value == null || !context.mounted) return;
  switch (value) {
    case 'contacts':
      context.push('/contacts');
    case 'channels':
      context.push('/channels');
    case 'plans':
      context.push('/plans');
    case 'settings':
      context.push('/settings');
    case 'security':
      context.push('/more/security');
    case 'notifications':
      context.push('/notifications');
    case 'workspace':
      context.push('/workspaces');
    case 'logout':
      await ref.read(authControllerProvider.notifier).logout();
      if (context.mounted) context.go('/login');
  }
}
