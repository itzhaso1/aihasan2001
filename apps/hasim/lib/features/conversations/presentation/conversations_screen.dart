import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:hasim/core/utils/relative_time.dart';
import 'package:hasim/core/widgets/async_body.dart';
import 'package:hasim/core/widgets/hasim_shell_header.dart';
import 'package:hasim/core/widgets/skeleton_list.dart';
import 'package:hasim/features/conversations/providers/conversations_controller.dart';
import 'package:hasim/features/home/providers/home_controller.dart';

/// الشاشة الأولى بعد الدخول — محادثات فقط (بحث + فلاتر + قائمة).
class ConversationsScreen extends ConsumerStatefulWidget {
  const ConversationsScreen({super.key});
  @override
  ConsumerState<ConversationsScreen> createState() => _ConversationsScreenState();
}

class _ConversationsScreenState extends ConsumerState<ConversationsScreen> {
  final _search = TextEditingController();

  /// فلاتر مدعومة في Mobile API: all | unread | archived
  /// «المخصصة لي» غير متاحة في الـ API حاليًا — لا نخترع فلترًا وهميًا.
  static const _filters = [
    ('all', 'الكل'),
    ('unread', 'غير مقروءة'),
    ('archived', 'مؤرشفة'),
  ];

  static const _channels = [
    ('whatsapp', 'واتساب'),
    ('email', 'بريد'),
    ('web', 'ويب'),
    ('manual', 'يدوي'),
  ];

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final state = ref.watch(conversationsControllerProvider);
    final notifier = ref.read(conversationsControllerProvider.notifier);
    final theme = Theme.of(context);

    return Scaffold(
      appBar: const HasimShellHeader(),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
            child: TextField(
              controller: _search,
              textInputAction: TextInputAction.search,
              decoration: InputDecoration(
                hintText: 'ابحث في المحادثات',
                prefixIcon: const Icon(Icons.search),
                filled: true,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide.none,
                ),
              ),
              onSubmitted: notifier.setSearch,
              onChanged: (v) {
                if (v.isEmpty) notifier.setSearch('');
              },
            ),
          ),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
            child: Row(
              children: [
                for (final f in _filters)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: ChoiceChip(
                      label: Text(f.$2),
                      selected: state.filter == f.$1,
                      onSelected: (_) => notifier.setFilter(f.$1),
                    ),
                  ),
                const SizedBox(width: 6),
                for (final c in _channels)
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 4),
                    child: FilterChip(
                      label: Text(c.$2),
                      selected: state.channel == c.$1,
                      onSelected: (sel) => notifier.setChannel(sel ? c.$1 : null),
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () async {
                await notifier.refresh();
                await ref.read(homeControllerProvider.notifier).refresh();
              },
              child: state.loading && state.items.isEmpty
                  ? const SkeletonList()
                  : AsyncBody(
                      loading: false,
                      error: state.error,
                      isEmpty: !state.loading && state.items.isEmpty,
                      emptyTitle: 'لا توجد محادثات حاليًا',
                      emptySubtitle: 'ستظهر هنا رسائل واتساب والويب والقنوات الموحدة.',
                      onRetry: () => notifier.refresh(),
                      child: NotificationListener<ScrollNotification>(
                        onNotification: (n) {
                          if (n.metrics.pixels > n.metrics.maxScrollExtent - 200) {
                            notifier.loadMore();
                          }
                          return false;
                        },
                        child: ListView.separated(
                          itemCount: state.items.length + (state.loadingMore ? 1 : 0),
                          separatorBuilder: (_, _) => Divider(
                            height: 1,
                            color: theme.dividerColor.withValues(alpha: 0.5),
                          ),
                          itemBuilder: (context, index) {
                            if (index >= state.items.length) {
                              return const Padding(
                                padding: EdgeInsets.all(16),
                                child: Center(child: CircularProgressIndicator()),
                              );
                            }
                            final c = state.items[index];
                            final time = relativeTimeAr(c.lastMessageAt);
                            final unread = c.unreadCount > 0;
                            return ListTile(
                              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                              onTap: () => context.push('/conversations/${c.id}'),
                              leading: CircleAvatar(
                                backgroundColor: theme.colorScheme.primary.withValues(alpha: 0.15),
                                child: Text(
                                  (c.title.isNotEmpty ? c.title[0] : '#'),
                                  style: TextStyle(
                                    color: theme.colorScheme.primary,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                              title: Row(
                                children: [
                                  Expanded(
                                    child: Text(
                                      c.title,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        fontWeight: unread ? FontWeight.w800 : FontWeight.w600,
                                      ),
                                    ),
                                  ),
                                  Text(
                                    time,
                                    style: theme.textTheme.labelSmall?.copyWith(
                                      color: unread
                                          ? theme.colorScheme.primary
                                          : theme.colorScheme.onSurfaceVariant,
                                    ),
                                  ),
                                ],
                              ),
                              subtitle: Row(
                                children: [
                                  Icon(_channelIcon(c.channel), size: 14, color: theme.colorScheme.onSurfaceVariant),
                                  const SizedBox(width: 6),
                                  Expanded(
                                    child: Text(
                                      c.preview,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: TextStyle(
                                        fontWeight: unread ? FontWeight.w600 : FontWeight.w400,
                                      ),
                                    ),
                                  ),
                                  if (unread)
                                    Padding(
                                      padding: const EdgeInsets.only(right: 4),
                                      child: CircleAvatar(
                                        radius: 11,
                                        backgroundColor: theme.colorScheme.primary,
                                        child: Text(
                                          '${c.unreadCount}',
                                          style: const TextStyle(color: Colors.white, fontSize: 11),
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                            );
                          },
                        ),
                      ),
                    ),
            ),
          ),
        ],
      ),
    );
  }

  IconData _channelIcon(String channel) {
    return switch (channel) {
      'whatsapp' => Icons.chat,
      'email' => Icons.mail_outline,
      'web' => Icons.language,
      _ => Icons.forum_outlined,
    };
  }
}
