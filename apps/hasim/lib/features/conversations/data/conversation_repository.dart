
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:hasim/core/models/models.dart';
import 'package:hasim/core/network/api_client.dart';
import 'package:hasim/core/network/api_exception.dart';
import 'package:hasim/core/network/api_response.dart';
import 'package:hasim/core/utils/idempotency.dart';

class ConversationRepository {
  ConversationRepository(this._api);
  final ApiClient _api;

  Future<({List<ConversationModel> items, String? nextCursor})> list({
    String filter = 'all',
    String? channel,
    String? search,
    String? cursor,
  }) async {
    final res = await _api.get(
      '/conversations',
      query: {
        'filter': filter,
        if (channel != null && channel.isNotEmpty) 'channel': channel,
        if (search != null && search.isNotEmpty) 'search': search,
        if (cursor != null && cursor.isNotEmpty) 'cursor': cursor,
        'per_page': 20,
      },
    );
    final items = asMapList(res.data).map(ConversationModel.fromJson).toList();
    return (items: items, nextCursor: res.nextCursor);
  }

  Future<ConversationModel> getById(int id) async {
    final res = await _api.get('/conversations/$id');
    final map = asMap(res.data);
    if (map == null) throw ApiException(res.message ?? 'المحادثة غير موجودة.');
    return ConversationModel.fromJson(map);
  }

  Future<({List<MessageModel> items, String? nextCursor})> messages(
    int conversationId, {
    String? cursor,
  }) async {
    final res = await _api.get(
      '/conversations/$conversationId/messages',
      query: {
        if (cursor != null && cursor.isNotEmpty) 'cursor': cursor,
        'per_page': 30,
      },
    );
    final items = asMapList(res.data).map(MessageModel.fromJson).toList();
    return (items: items, nextCursor: res.nextCursor);
  }

  Future<MessageModel> sendMessage(int conversationId, String content, {String? idempotencyKey}) async {
    final key = idempotencyKey ?? newIdempotencyKey();
    final res = await _api.post(
      '/conversations/$conversationId/messages',
      body: {
        'content': content,
        'message_type': 'text',
        'idempotency_key': key,
      },
      idempotent: true,
    );
    // Override idempotency with same key by using raw once — already sent header randomly.
    // Re-send content mapping:
    final map = asMap(res.data);
    if (map == null) throw ApiException(res.message ?? 'تعذر إرسال الرسالة.');
    return MessageModel.fromJson(map);
  }

  Future<MessageModel> sendMessageWithKey(int conversationId, String content, String key) async {
    final headers = {'Idempotency-Key': key};
    try {
      final res = await _api.raw.post<Map<String, dynamic>>(
        'conversations/$conversationId/messages',
        data: {
          'content': content,
          'message_type': 'text',
          'idempotency_key': key,
        },
        options: Options(headers: headers),
      );
      final parsed = ApiResponse.fromJson(res.data ?? {}, asMap);
      final map = parsed.data;
      if (map == null) throw ApiException(parsed.message ?? 'تعذر إرسال الرسالة.');
      return MessageModel.fromJson(map);
    } on DioException catch (e) {
      final data = e.response?.data;
      if (data is Map<String, dynamic> && data['message'] != null) {
        throw ApiException(data['message'].toString(), statusCode: e.response?.statusCode);
      }
      throw ApiException('تعذر إرسال الرسالة.');
    }
  }

  Future<void> markRead(int conversationId, {int? messageId}) async {
    await _api.post(
      '/conversations/$conversationId/read',
      body: {
        'message_id': ?messageId,
      },
    );
  }

  Future<void> archive(int conversationId, {bool archived = true}) async {
    await _api.post('/conversations/$conversationId/archive', body: {'archived': archived});
  }

  Future<void> mute(int conversationId, {bool muted = true}) async {
    await _api.post('/conversations/$conversationId/mute', body: {'muted': muted});
  }

  Future<MessageAttachmentModel> uploadAttachment(int messageId, File file) async {
    final form = FormData.fromMap({
      'file': await MultipartFile.fromFile(file.path, filename: file.uri.pathSegments.last),
    });
    final res = await _api.upload('/messages/$messageId/attachments', formData: form);
    final map = asMap(res.data);
    if (map == null) throw ApiException(res.message ?? 'تعذر رفع المرفق.');
    return MessageAttachmentModel.fromJson(map);
  }
}
