import 'dart:async';
import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:hasim/core/di/providers.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_exception.dart';
import 'package:hasim/core/utils/idempotency.dart';

class ConversationsState {
  const ConversationsState({
    this.loading = false,
    this.loadingMore = false,
    this.items = const [],
    this.nextCursor,
    this.filter = 'all',
    this.channel,
    this.search = '',
    this.error,
  });

  final bool loading;
  final bool loadingMore;
  final List<ConversationModel> items;
  final String? nextCursor;
  final String filter;
  final String? channel;
  final String search;
  final String? error;

  ConversationsState copyWith({
    bool? loading,
    bool? loadingMore,
    List<ConversationModel>? items,
    String? nextCursor,
    String? filter,
    String? channel,
    bool clearChannel = false,
    String? search,
    String? error,
    bool clearCursor = false,
  }) {
    return ConversationsState(
      loading: loading ?? this.loading,
      loadingMore: loadingMore ?? this.loadingMore,
      items: items ?? this.items,
      nextCursor: clearCursor ? nextCursor : (nextCursor ?? this.nextCursor),
      filter: filter ?? this.filter,
      channel: clearChannel ? channel : (channel ?? this.channel),
      search: search ?? this.search,
      error: error,
    );
  }
}

class ConversationsController extends StateNotifier<ConversationsState> {
  ConversationsController(this._ref) : super(const ConversationsState()) {
    _sub = _ref.read(realtimeServiceProvider).conversationsChanged.listen((_) => refresh(silent: true));
    final cached = _ref.read(localCacheProvider).readConversations();
    if (cached != null && cached.isNotEmpty) {
      state = state.copyWith(items: cached);
    }
    refresh();
  }

  final Ref _ref;
  StreamSubscription? _sub;

  @override
  void dispose() {
    _sub?.cancel();
    super.dispose();
  }

  Future<void> setFilter(String filter) async {
    state = state.copyWith(filter: filter);
    await refresh();
  }

  Future<void> setChannel(String? channel) async {
    state = state.copyWith(channel: channel, clearChannel: true);
    await refresh();
  }

  Future<void> setSearch(String search) async {
    state = state.copyWith(search: search);
    await refresh();
  }

  Future<void> refresh({bool silent = false}) async {
    if (!silent) state = state.copyWith(loading: true, error: null, clearCursor: true, nextCursor: null);
    try {
      final page = await _ref.read(conversationRepositoryProvider).list(
            filter: state.filter,
            channel: state.channel,
            search: state.search,
          );
      await _ref.read(localCacheProvider).saveConversations(page.items);
      state = state.copyWith(loading: false, items: page.items, nextCursor: page.nextCursor, clearCursor: true);
    } on ApiException catch (e) {
      state = state.copyWith(loading: false, error: e.message);
    } catch (_) {
      state = state.copyWith(loading: false, error: 'تعذر تحميل المحادثات.');
    }
  }

  Future<void> loadMore() async {
    final cursor = state.nextCursor;
    if (cursor == null || state.loadingMore) return;
    state = state.copyWith(loadingMore: true);
    try {
      final page = await _ref.read(conversationRepositoryProvider).list(
            filter: state.filter,
            channel: state.channel,
            search: state.search,
            cursor: cursor,
          );
      state = state.copyWith(
        loadingMore: false,
        items: [...state.items, ...page.items],
        nextCursor: page.nextCursor,
        clearCursor: true,
      );
    } catch (_) {
      state = state.copyWith(loadingMore: false);
    }
  }
}

final conversationsControllerProvider =
    StateNotifierProvider<ConversationsController, ConversationsState>((ref) => ConversationsController(ref));

class ChatState {
  const ChatState({
    this.loading = true,
    this.sending = false,
    this.loadingOlder = false,
    this.conversation,
    this.messages = const [],
    this.nextCursor,
    this.error,
    this.aiBusy = false,
  });

  final bool loading;
  final bool sending;
  final bool loadingOlder;
  final ConversationModel? conversation;
  final List<MessageModel> messages;
  final String? nextCursor;
  final String? error;
  final bool aiBusy;

  ChatState copyWith({
    bool? loading,
    bool? sending,
    bool? loadingOlder,
    ConversationModel? conversation,
    List<MessageModel>? messages,
    String? nextCursor,
    String? error,
    bool? aiBusy,
    bool clearCursor = false,
  }) {
    return ChatState(
      loading: loading ?? this.loading,
      sending: sending ?? this.sending,
      loadingOlder: loadingOlder ?? this.loadingOlder,
      conversation: conversation ?? this.conversation,
      messages: messages ?? this.messages,
      nextCursor: clearCursor ? nextCursor : (nextCursor ?? this.nextCursor),
      error: error,
      aiBusy: aiBusy ?? this.aiBusy,
    );
  }
}

class ChatController extends StateNotifier<ChatState> {
  ChatController(this._ref, this.conversationId) : super(const ChatState()) {
    _init();
  }

  final Ref _ref;
  final int conversationId;
  StreamSubscription? _sub;

  Future<void> _init() async {
    final cached = _ref.read(localCacheProvider).readMessages(conversationId);
    if (cached != null && cached.isNotEmpty) {
      state = state.copyWith(messages: cached, loading: true);
    }
    _ref.read(realtimeServiceProvider).watchConversation(conversationId);
    _sub = _ref.read(realtimeServiceProvider).messageCreated.listen((m) {
      if (m.conversationId != conversationId) return;
      if (state.messages.any((x) => x.id == m.id)) return;
      state = state.copyWith(messages: [...state.messages, m]);
    });
    await refresh();
  }

  @override
  void dispose() {
    _sub?.cancel();
    _ref.read(realtimeServiceProvider).watchConversation(null);
    super.dispose();
  }

  Future<void> refresh() async {
    state = state.copyWith(loading: true, error: null);
    try {
      final repo = _ref.read(conversationRepositoryProvider);
      final conversation = await repo.getById(conversationId);
      final page = await repo.messages(conversationId);
      final chronological = page.items.reversed.toList();
      await _ref.read(localCacheProvider).saveMessages(conversationId, chronological);
      state = ChatState(
        loading: false,
        conversation: conversation,
        messages: chronological,
        nextCursor: page.nextCursor,
      );
      await repo.markRead(conversationId);
    } on ApiException catch (e) {
      state = state.copyWith(loading: false, error: e.message);
    } catch (_) {
      state = state.copyWith(loading: false, error: 'تعذر فتح المحادثة.');
    }
  }

  Future<void> loadOlder() async {
    final cursor = state.nextCursor;
    if (cursor == null || state.loadingOlder) return;
    state = state.copyWith(loadingOlder: true);
    try {
      final page = await _ref.read(conversationRepositoryProvider).messages(conversationId, cursor: cursor);
      final olderChronological = page.items.reversed.toList();
      final merged = [...olderChronological, ...state.messages];
      await _ref.read(localCacheProvider).saveMessages(conversationId, merged);
      state = state.copyWith(
        loadingOlder: false,
        messages: merged,
        nextCursor: page.nextCursor,
        clearCursor: true,
      );
    } catch (_) {
      state = state.copyWith(loadingOlder: false);
    }
  }

  Future<void> send(String content) async {
    final text = content.trim();
    if (text.isEmpty) return;
    final clientId = newIdempotencyKey();
    final optimistic = MessageModel(
      id: -DateTime.now().millisecondsSinceEpoch,
      conversationId: conversationId,
      direction: 'outbound',
      messageType: 'text',
      content: text,
      createdAt: DateTime.now(),
      localPending: true,
      clientId: clientId,
    );
    state = state.copyWith(messages: [...state.messages, optimistic], sending: true);
    try {
      final saved = await _ref.read(conversationRepositoryProvider).sendMessageWithKey(
            conversationId,
            text,
            clientId,
          );
      final updated = state.messages.map((m) {
        if (m.clientId == clientId) return saved.copyWith(localPending: false, clientId: clientId);
        return m;
      }).toList();
      state = state.copyWith(messages: updated, sending: false);
      await _ref.read(localCacheProvider).saveMessages(conversationId, updated);
    } catch (_) {
      final updated = state.messages.map((m) {
        if (m.clientId == clientId) return m.copyWith(localPending: false, localFailed: true);
        return m;
      }).toList();
      state = state.copyWith(messages: updated, sending: false, error: 'تعذر إرسال الرسالة.');
    }
  }

  Future<void> retryFailed(MessageModel message) async {
    if (!message.localFailed || message.clientId == null) return;
    final clientId = message.clientId!;
    final updatedPending = state.messages.map((m) {
      if (m.clientId == clientId) return m.copyWith(localPending: true, localFailed: false);
      return m;
    }).toList();
    state = state.copyWith(messages: updatedPending, sending: true, error: null);
    try {
      final saved = await _ref.read(conversationRepositoryProvider).sendMessageWithKey(
            conversationId,
            message.content,
            clientId,
          );
      final updated = state.messages.map((m) {
        if (m.clientId == clientId) return saved.copyWith(localPending: false, clientId: clientId);
        return m;
      }).toList();
      state = state.copyWith(messages: updated, sending: false);
    } catch (_) {
      final updated = state.messages.map((m) {
        if (m.clientId == clientId) return m.copyWith(localPending: false, localFailed: true);
        return m;
      }).toList();
      state = state.copyWith(messages: updated, sending: false);
    }
  }

  Future<MessageModel?> sendThenAttach(File file, {String caption = ''}) async {
    final text = caption.trim().isEmpty ? '📎' : caption.trim();
    final clientId = newIdempotencyKey();
    final optimistic = MessageModel(
      id: -DateTime.now().millisecondsSinceEpoch,
      conversationId: conversationId,
      direction: 'outbound',
      messageType: 'text',
      content: text,
      createdAt: DateTime.now(),
      localPending: true,
      clientId: clientId,
    );
    state = state.copyWith(messages: [...state.messages, optimistic], sending: true);
    try {
      final saved = await _ref.read(conversationRepositoryProvider).sendMessageWithKey(
            conversationId,
            text,
            clientId,
          );
      final attachment = await _ref.read(conversationRepositoryProvider).uploadAttachment(saved.id, file);
      final withAttach = saved.copyWith(
        localPending: false,
        clientId: clientId,
        attachments: [...saved.attachments, attachment],
      );
      final updated = state.messages.map((m) {
        if (m.clientId == clientId) return withAttach;
        return m;
      }).toList();
      state = state.copyWith(messages: updated, sending: false);
      await _ref.read(localCacheProvider).saveMessages(conversationId, updated);
      return withAttach;
    } catch (_) {
      final updated = state.messages.map((m) {
        if (m.clientId == clientId) return m.copyWith(localPending: false, localFailed: true);
        return m;
      }).toList();
      state = state.copyWith(messages: updated, sending: false, error: 'تعذر إرسال المرفق.');
      return null;
    }
  }

  Future<String?> suggestReply() async {
    state = state.copyWith(aiBusy: true);
    try {
      final text = await _ref.read(aiRepositoryProvider).suggestReply(conversationId);
      state = state.copyWith(aiBusy: false);
      return text;
    } on ApiException catch (e) {
      state = state.copyWith(aiBusy: false, error: e.message);
      return null;
    } catch (_) {
      state = state.copyWith(aiBusy: false, error: 'تعذر توليد اقتراح الرد.');
      return null;
    }
  }

  Future<String?> summarize() async {
    state = state.copyWith(aiBusy: true);
    try {
      final text = await _ref.read(aiRepositoryProvider).summarize(conversationId);
      state = state.copyWith(aiBusy: false);
      return text;
    } on ApiException catch (e) {
      state = state.copyWith(aiBusy: false, error: e.message);
      return null;
    } catch (_) {
      state = state.copyWith(aiBusy: false, error: 'تعذر تلخيص المحادثة.');
      return null;
    }
  }
}

final chatControllerProvider = StateNotifierProvider.family<ChatController, ChatState, int>(
  (ref, id) => ChatController(ref, id),
);
