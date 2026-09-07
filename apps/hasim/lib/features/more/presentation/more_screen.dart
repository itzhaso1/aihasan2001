import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/theme/theme_mode_controller.dart';
import 'package:hasim/core/widgets/hasim_shell_header.dart';
import 'package:hasim/features/auth/providers/auth_controller.dart';
import 'package:hasim/features/home/providers/home_controller.dart';

/// شاشة «المزيد» — مركز الوصول للحساب والإدارة والإعدادات (ليست Dashboard).
class MoreScreen extends ConsumerWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);
    final snap = ref.watch(homeControllerProvider).snapshot;
    final user = auth.user;
    final theme = Theme.of(context);
    final notifCount = snap?.unreadNotifications ?? 0;

    return Scaffold(
      appBar: const HasimShellHeader(),
      body: ListView(
        padding: const EdgeInsets.only(bottom: 32),
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: Text('المزيد', style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: InkWell(
              borderRadius: BorderRadius.circular(16),
              onTap: () => context.push('/profile'),
              child: Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  border: Border.all(color: theme.dividerColor.withValues(alpha: 0.6)),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 28,
                      backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.15),
                      backgroundImage:
                          user?.avatarUrl != null ? CachedNetworkImageProvider(user!.avatarUrl!) : null,
                      child: user?.avatarUrl == null
                          ? Text(
                              (user?.name.isNotEmpty == true ? user!.name[0] : 'ح'),
                              style: TextStyle(
                                fontSize: 22,
                                fontWeight: FontWeight.w800,
                                color: theme.colorScheme.primary,
                              ),
                            )
                          : null,
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            user?.name ?? 'مستخدم',
                            style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            auth.workspace?.name ?? 'مساحة العمل',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: theme.colorScheme.onSurfaceVariant,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Icon(Icons.chevron_left, color: theme.colorScheme.onSurfaceVariant),
                  ],
                ),
              ),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.swap_horiz),
            title: const Text('تبديل مساحة العمل'),
            subtitle: Text(auth.workspace?.name ?? 'اختر مساحة'),
            trailing: const Icon(Icons.chevron_left),
            onTap: () => context.push('/workspaces'),
          ),
          const _SectionLabel('الحساب'),
          _NavTile(
            icon: Icons.person_outline,
            title: 'حسابي',
            onTap: () => context.push('/profile'),
          ),
          _NavTile(
            icon: Icons.security_outlined,
            title: 'الأمان والجلسات',
            onTap: () => context.push('/more/security'),
          ),
          const _SectionLabel('إدارة النشاط'),
          _NavTile(
            icon: Icons.contacts_outlined,
            title: 'جهات الاتصال',
            onTap: () => context.push('/contacts'),
          ),
          _NavTile(
            icon: Icons.groups_outlined,
            title: 'مجموعات جهات الاتصال',
            onTap: () => context.push('/contact-groups'),
          ),
          _NavTile(
            icon: Icons.hub_outlined,
            title: 'القنوات',
            onTap: () => context.push('/channels'),
          ),
          _NavTile(
            icon: Icons.workspace_premium_outlined,
            title: 'الباقة والاستخدام',
            onTap: () => context.push('/plans'),
          ),
          _NavTile(
            icon: Icons.auto_awesome_mosaic_outlined,
            title: 'نشاط اليوم',
            subtitle: 'إحصاءات مختصرة',
            onTap: () => context.push('/activity'),
          ),
          const _SectionLabel('الإعدادات'),
          _NavTile(
            icon: Icons.notifications_outlined,
            title: 'الإشعارات',
            trailing: notifCount > 0
                ? Badge(label: Text('$notifCount'))
                : const Icon(Icons.chevron_left),
            onTap: () => context.push('/notifications'),
          ),
          _NavTile(
            icon: Icons.notifications_active_outlined,
            title: 'تفضيلات الإشعارات',
            onTap: () => context.push('/notification-preferences'),
          ),
          _NavTile(
            icon: Icons.brightness_6_outlined,
            title: 'المظهر',
            subtitle: _themeLabel(ref.watch(themeModeControllerProvider)),
            onTap: () => _showThemeSheet(context, ref),
          ),
          _NavTile(
            icon: Icons.settings_outlined,
            title: 'الإعدادات',
            onTap: () => context.push('/settings'),
          ),
          const SizedBox(height: 12),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: OutlinedButton.icon(
              style: OutlinedButton.styleFrom(
                foregroundColor: theme.colorScheme.error,
                side: BorderSide(color: theme.colorScheme.error.withValues(alpha: 0.4)),
                minimumSize: const Size.fromHeight(48),
              ),
              onPressed: () async {
                await ref.read(authControllerProvider.notifier).logout();
                if (context.mounted) context.go('/login');
              },
              icon: const Icon(Icons.logout),
              label: const Text('تسجيل الخروج'),
            ),
          ),
        ],
      ),
    );
  }

  static String _themeLabel(ThemeMode mode) => switch (mode) {
        ThemeMode.light => 'فاتح',
        ThemeMode.dark => 'داكن',
        ThemeMode.system => 'حسب النظام',
      };

  Future<void> _showThemeSheet(BuildContext context, WidgetRef ref) async {
    final current = ref.read(themeModeControllerProvider);
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final mode in ThemeMode.values)
              ListTile(
                title: Text(_themeLabel(mode)),
                trailing: current == mode ? Icon(Icons.check, color: Theme.of(ctx).colorScheme.primary) : null,
                onTap: () {
                  ref.read(themeModeControllerProvider.notifier).setMode(mode);
                  Navigator.pop(ctx);
                },
              ),
          ],
        ),
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 20, 16, 6),
      child: Text(
        text,
        style: Theme.of(context).textTheme.titleSmall?.copyWith(
              fontWeight: FontWeight.w800,
              color: Theme.of(context).colorScheme.primary,
            ),
      ),
    );
  }
}

class _NavTile extends StatelessWidget {
  const _NavTile({
    required this.icon,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.trailing,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon),
      title: Text(title),
      subtitle: subtitle == null ? null : Text(subtitle!),
      trailing: trailing ?? const Icon(Icons.chevron_left),
      onTap: onTap,
    );
  }
}
