import 'dart:io';
import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import 'inbox_models.dart';

class InboxRepository {
  InboxRepository(this._dio);
  final Dio _dio;
  static InboxRepository create() => InboxRepository(ApiClient.instance);

  // ── Marketing ─────────────────────────────────────────────────────────────

  Future<List<MarketingBroadcast>> getBroadcasts() async {
    final r = await _dio.get('/inbox/broadcasts');
    final list = r.data['data']?['data'] as List? ?? [];
    return list.map((j) => MarketingBroadcast.fromJson(j)).toList();
  }

  Future<void> markBroadcastRead(String uuid) async =>
      await _dio.post('/inbox/broadcasts/$uuid/read');

  Future<String?> trackCtaClick(String uuid) async {
    final r = await _dio.post('/inbox/broadcasts/$uuid/cta');
    return r.data['data']?['route'] as String?;
  }

  // ── Conversations ─────────────────────────────────────────────────────────

  Future<List<InboxConversation>> getConversations() async {
    final r = await _dio.get('/inbox/conversations');
    final list = r.data['data']?['data'] as List? ?? [];
    return list.map((j) => InboxConversation.fromJson(j)).toList();
  }

  Future<InboxConversation> createConversation({
    required String module,
    required String subject,
    required String message,
  }) async {
    final r = await _dio.post('/inbox/conversations', data: {
      'module': module, 'subject': subject, 'message': message,
    });
    return InboxConversation.fromJson(r.data['data']);
  }

  Future<Map<String, dynamic>> getMessages(String uuid, {int page = 1}) async {
    final r = await _dio.get('/inbox/conversations/$uuid/messages',
        queryParameters: {'page': page});
    final conv = InboxConversation.fromJson(r.data['data']['conversation']);
    final msgs = (r.data['data']['messages']?['data'] as List? ?? [])
        .map((j) => InboxMessage.fromJson(j))
        .toList();
    return {'conversation': conv, 'messages': msgs};
  }

  Future<InboxMessage> sendTextMessage(String uuid, String content) async {
    final r = await _dio.post('/inbox/conversations/$uuid/messages',
        data: {'type': 'text', 'content': content});
    return InboxMessage.fromJson(r.data['data']);
  }

  Future<InboxMessage> sendMediaMessage(
    String uuid, File file, String type, {int? duration}) async {
    final form = FormData.fromMap({
      'type': type,
      'media': await MultipartFile.fromFile(file.path),
      if (duration != null) 'duration': duration,
    });
    final r = await _dio.post('/inbox/conversations/$uuid/messages', data: form);
    return InboxMessage.fromJson(r.data['data']);
  }

  Future<void> sendTyping(String uuid) async {
    try { await _dio.post('/inbox/conversations/$uuid/typing'); } catch (_) {}
  }

  // ── Calls ─────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> initiateCall(String uuid) async {
    final r = await _dio.post('/inbox/conversations/$uuid/call');
    return r.data['data'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> joinCall(String callUuid) async {
    final r = await _dio.post('/inbox/calls/$callUuid/join');
    return r.data['data'] as Map<String, dynamic>;
  }

  Future<void> endCall(String callUuid) async =>
      await _dio.post('/inbox/calls/$callUuid/end');
}
