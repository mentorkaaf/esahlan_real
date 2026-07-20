import '../../../../core/api/api_client.dart';
import '../models/call_models.dart';

class CallRepository {
  final _dio = ApiClient.instance;

  Future<CallSession> initiateCall({
    required int receiverId,
    required String type, // audio | video
  }) async {
    final res = await _dio.post('/calls', data: {
      'receiver_id': receiverId,
      'type': type,
    });
    return CallSession.fromJson(res.data['data']);
  }

  Future<CallSession> acceptCall(int callId) async {
    final res = await _dio.post('/calls/$callId/accept');
    return CallSession.fromJson(res.data['data']);
  }

  Future<void> rejectCall(int callId) async {
    await _dio.post('/calls/$callId/reject');
  }

  Future<int> endCall(int callId) async {
    final res = await _dio.post('/calls/$callId/end');
    return res.data['data']['duration'] ?? 0;
  }

  Future<List<CallModel>> getHistory() async {
    final res = await _dio.get('/calls/history');
    final list = res.data['data'];
    if (list is List) return list.map((e) => CallModel.fromJson(e)).toList();
    final data = (list as Map)['data'] as List? ?? [];
    return data.map((e) => CallModel.fromJson(e)).toList();
  }
}
