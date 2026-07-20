import '../../../../core/api/api_client.dart';
import '../models/live_models.dart';

class LiveRepository {
  final _dio = ApiClient.instance;

  Future<List<LiveRoom>> getRooms() async {
    final res  = await _dio.get('/live/rooms');
    final data = res.data['data'];
    final list = data is List ? data : (data['data'] as List? ?? []);
    return list.map((e) => LiveRoom.fromJson(e)).toList();
  }

  Future<List<LiveRoom>> getPastRooms() async {
    final res  = await _dio.get('/live/rooms/past');
    final data = res.data['data'];
    final list = data is List ? data : (data['data'] as List? ?? []);
    return list.map((e) => LiveRoom.fromJson(e)).toList();
  }

  Future<LiveSession> createRoom({required String title}) async {
    final res = await _dio.post('/live/rooms', data: {'title': title});
    return LiveSession.fromJson(res.data['data']);
  }

  Future<LiveSession> joinRoom(int roomId) async {
    final res = await _dio.post('/live/rooms/$roomId/join');
    return LiveSession.fromJson(res.data['data']);
  }

  Future<void> leaveRoom(int roomId) async {
    await _dio.post('/live/rooms/$roomId/leave');
  }

  Future<void> endRoom(int roomId) async {
    await _dio.post('/live/rooms/$roomId/end');
  }

  Future<void> sendMessage(int roomId, String message) async {
    await _dio.post('/live/rooms/$roomId/message', data: {'message': message});
  }

  Future<List<GiftModel>> getGifts() async {
    final res  = await _dio.get('/live/gifts');
    final list = res.data['data'] as List? ?? [];
    return list.map((e) => GiftModel.fromJson(e)).toList();
  }

  Future<int> getCoinBalance() async {
    final res = await _dio.get('/live/coins/balance');
    return res.data['data']['balance'] ?? 0;
  }

  Future<Map<String, dynamic>> sendGift({
    required int roomId,
    required int giftId,
    int quantity = 1,
  }) async {
    final res = await _dio.post('/live/rooms/$roomId/gifts', data: {
      'gift_id': giftId,
      'quantity': quantity,
    });
    return res.data['data'] as Map<String, dynamic>;
  }
}
