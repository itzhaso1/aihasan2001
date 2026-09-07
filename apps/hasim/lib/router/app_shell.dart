import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/features/home/providers/home_controller.dart';
import 'package:hasim/features/stories/providers/stories_controller.dart';
import 'package:hasim/router/hasim_nav.dart';

class AppShell extends ConsumerWidget {
  const AppShell({super.key, required this.navigationShell});
  final StatefulNavigationShell navigationShell;

  void _go(int index) {
    // الحفاظ على حالة كل تبويب (scroll / محتوى) عبر indexedStack.
    navigationShell.goBranch(
      index,
      initialLocation: index == navigationShell.currentIndex,
    );
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final snap = ref.watch(homeControllerProvider).snapshot;
    final stories = ref.watch(storiesControllerProvider);
    final chatBadge = snap?.unreadConversations ?? 0;
    final emailBadge = snap?.unreadEmail ?? 0;
    final appointmentsBadge = snap?.todaysBookingsCount ?? 0;
    final updatesBadge = stories.unviewedCount;

    return Scaffold(
      body: navigationShell,
      bottomNavigationBar: NavigationBar(
        selectedIndex: navigationShell.currentIndex,
        onDestinationSelected: _go,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        height: 68,
        destinations: [
          NavigationDestination(
            icon: Badge(
              isLabelVisible: chatBadge > 0,
              label: Text('$chatBadge'),
              child: const Icon(Icons.chat_bubble_outline),
            ),
            selectedIcon: Badge(
              isLabelVisible: chatBadge > 0,
              label: Text('$chatBadge'),
              child: const Icon(Icons.chat_bubble),
            ),
            label: HasimNav.bottomLabels[HasimNav.conversationsIndex],
          ),
          NavigationDestination(
            icon: Badge(
              isLabelVisible: emailBadge > 0,
              label: Text('$emailBadge'),
              child: const Icon(Icons.mail_outline),
            ),
            selectedIcon: Badge(
              isLabelVisible: emailBadge > 0,
              label: Text('$emailBadge'),
              child: const Icon(Icons.mail),
            ),
            label: HasimNav.bottomLabels[HasimNav.emailIndex],
          ),
          NavigationDestination(
            icon: Badge(
              isLabelVisible: appointmentsBadge > 0,
              label: Text('$appointmentsBadge'),
              child: const Icon(Icons.event_outlined),
            ),
            selectedIcon: Badge(
              isLabelVisible: appointmentsBadge > 0,
              label: Text('$appointmentsBadge'),
              child: const Icon(Icons.event),
            ),
            label: HasimNav.bottomLabels[HasimNav.appointmentsIndex],
          ),
          NavigationDestination(
            icon: Badge(
              isLabelVisible: updatesBadge > 0,
              label: Text('$updatesBadge'),
              child: const Icon(Icons.auto_stories_outlined),
            ),
            selectedIcon: Badge(
              isLabelVisible: updatesBadge > 0,
              label: Text('$updatesBadge'),
              child: const Icon(Icons.auto_stories),
            ),
            label: HasimNav.bottomLabels[HasimNav.updatesIndex],
          ),
          NavigationDestination(
            icon: const Icon(Icons.more_horiz),
            selectedIcon: const Icon(Icons.more_horiz),
            label: HasimNav.bottomLabels[HasimNav.moreIndex],
          ),
        ],
      ),
    );
  }
}
