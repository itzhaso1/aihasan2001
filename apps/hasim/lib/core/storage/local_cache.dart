import 'dart:convert';

import 'package:hive_flutter/hive_flutter.dart';
import 'package:hasim/core/models/models.dart';

class LocalCache {
  LocalCache({
    Box? conversationsBox,
    Box? messagesBox,
  })  : _conversations = conversationsBox ?? Hive.box('conversations_cache'),
        _messages = messagesBox ?? Hive.box('messages_cache');

  final Box _conversations;
  final Box _messages;

  Future<void> saveConversations(List<ConversationModel> items, {String key = 'list'}) async {
    final encoded = items.map((e) => e.toCacheJson()).toList();
    await _conversations.put(key, jsonEncode(encoded));
  }

  List<ConversationModel>? readConversations({String key = 'list'}) {
    final raw = _conversations.get(key);
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
    final encoded = items.map((e) => e.toCacheJson()).toList();
    await _messages.put('c_$conversationId', jsonEncode(encoded));
  }

  List<MessageModel>? readMessages(int conversationId) {
    final raw = _messages.get('c_$conversationId');
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
