import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';
import 'package:hasim/core/models/models.dart';

class LocalCache {
  LocalCache({
    Box? conversationsBox,
    Box? messagesBox,
  })  : _conversations = conversationsBox ?? Hive.box('conversations_cache'),
        _messages = messagesBox ?? Hive.box('messages_cache'),
        _memoryConversations = null,
        _memoryMessages = null;

  /// مخزن في الذاكرة لاختبارات الواجهة دون تهيئة Hive.
  LocalCache.memory()
      : _conversations = null,
        _messages = null,
        _memoryConversations = <String, String>{},
        _memoryMessages = <String, String>{};

  final Box? _conversations;
  final Box? _messages;
  final Map<String, String>? _memoryConversations;
  final Map<String, String>? _memoryMessages;

  Future<void> saveConversations(List<ConversationModel> items, {String key = 'list'}) async {
    final encoded = jsonEncode(items.map((e) => e.toCacheJson()).toList());
    if (_conversations != null) {
      await _conversations.put(key, encoded);
    } else {
      _memoryConversations![key] = encoded;
    }
  }

  List<ConversationModel>? readConversations({String key = 'list'}) {
    final raw = _conversations != null ? _conversations.get(key) : _memoryConversations![key];
    if (raw is! String || raw.isEmpty) return null;
    try {
      final list = jsonDecode(raw) as List<dynamic>;
      return list
          .whereType<Map>()
          .map((e) => ConversationModel.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } catch (_) {
      return null;
    }
  }

  Future<void> saveMessages(int conversationId, List<MessageModel> items) async {
    final encoded = jsonEncode(items.map((e) => e.toCacheJson()).toList());
    final key = 'c_$conversationId';
    if (_messages != null) {
      await _messages.put(key, encoded);
    } else {
      _memoryMessages![key] = encoded;
    }
  }

  List<MessageModel>? readMessages(int conversationId) {
    final key = 'c_$conversationId';
    final raw = _messages != null ? _messages.get(key) : _memoryMessages![key];
    if (raw is! String || raw.isEmpty) return null;
    try {
      final list = jsonDecode(raw) as List<dynamic>;
      return list
          .whereType<Map>()
          .map((e) => MessageModel.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } catch (_) {
      return null;
    }
  }
}
