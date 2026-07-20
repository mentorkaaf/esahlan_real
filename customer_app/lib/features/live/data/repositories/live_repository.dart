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

  // ── Discovery ──────────────────────────────────────────────────────────────

  Future<LiveDiscovery> getDiscovery({String category = 'general', String search = ''}) async {
    final res = await _dio.get('/live/discovery', queryParameters: {
      'category': category,
      if (search.isNotEmpty) 'search': search,
    });
    return LiveDiscovery.fromJson(Map<String, dynamic>.from(res.data['data'] as Map));
  }

  Future<List<LiveCategory>> getCategories() async {
    final res  = await _dio.get('/live/discovery/categories');
    final list = res.data['data'] as List? ?? [];
    return list.map((e) => LiveCategory.fromJson(Map<String, dynamic>.from(e as Map))).toList();
  }

  Future<List<LiveRoom>> getRecommended() async {
    final res  = await _dio.get('/live/discovery/recommended');
    final list = res.data['data'] as List? ?? [];
    return list.map((e) => LiveRoom.fromJson(Map<String, dynamic>.from(e as Map))).toList();
  }

  Future<List<LiveRoom>> searchRooms(String q) async {
    final res  = await _dio.get('/live/discovery/search', queryParameters: {'q': q});
    final list = res.data['data'] as List? ?? [];
    return list.map((e) => LiveRoom.fromJson(Map<String, dynamic>.from(e as Map))).toList();
  }

  Future<List<LiveRoom>> getPastRooms() async {
    final res  = await _dio.get('/live/rooms/past');
    final data = res.data['data'];
    final list = data is List ? data : (data['data'] as List? ?? []);
    return list.map((e) => LiveRoom.fromJson(e)).toList();
  }

  Future<LiveSession> createRoom({required String title, String category = 'general', List<String> tags = const []}) async {
    final res = await _dio.post('/live/rooms', data: {
      'title':    title,
      'category': category,
      'tags':     tags,
    });
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

  // ── Gifts ──────────────────────────────────────────────────────────────────

  Future<List<GiftModel>> getGifts() async {
    final res  = await _dio.get('/live/gifts');
    final list = res.data['data'] as List? ?? [];
    return list.map((e) => GiftModel.fromJson(e)).toList();
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

  // ── Coins ──────────────────────────────────────────────────────────────────

  Future<int> getCoinBalance() async {
    final res = await _dio.get('/live/coins/balance');
    return res.data['data']['balance'] ?? 0;
  }

  Future<List<CoinPackage>> getCoinPackages() async {
    final res  = await _dio.get('/live/coins/packages');
    final list = res.data['data'] as List? ?? [];
    return list.map((e) => CoinPackage.fromJson(e)).toList();
  }

  Future<Map<String, dynamic>> buyCoins({
    required int packageId,
    required String paymentMethod,
    required String paymentReference,
    Map<String, dynamic>? metadata,
  }) async {
    final res = await _dio.post('/live/coins/buy', data: {
      'package_id':        packageId,
      'payment_method':    paymentMethod,
      'payment_reference': paymentReference,
      ...?metadata,
    });
    return res.data['data'] as Map<String, dynamic>;
  }

  // ── Multi-guest ────────────────────────────────────────────────────────────

  Future<List<LiveGuest>> getGuests(int roomId) async {
    final res  = await _dio.get('/live/rooms/$roomId/guests');
    final list = res.data['data'] as List? ?? [];
    return list.map((e) => LiveGuest.fromJson(e)).toList();
  }

  Future<int> requestToJoin(int roomId) async {
    final res = await _dio.post('/live/rooms/$roomId/guest/request');
    return res.data['data']['request_id'] ?? 0;
  }

  Future<void> cancelJoinRequest(int roomId) async {
    await _dio.post('/live/rooms/$roomId/guest/cancel');
  }

  Future<void> acceptGuest(int roomId, int requestId) async {
    await _dio.post('/live/rooms/$roomId/guest/accept/$requestId');
  }

  Future<void> rejectGuest(int roomId, int requestId) async {
    await _dio.post('/live/rooms/$roomId/guest/reject/$requestId');
  }

  Future<void> removeGuest(int roomId, int userId) async {
    await _dio.post('/live/rooms/$roomId/guest/$userId/remove');
  }

  Future<void> muteGuest(int roomId, int userId, {bool muted = true}) async {
    await _dio.post('/live/rooms/$roomId/guest/$userId/mute', data: {'muted': muted});
  }

  // ── Stats ──────────────────────────────────────────────────────────────────

  Future<LiveStats> getStats(int roomId) async {
    final res = await _dio.get('/live/rooms/$roomId/stats');
    return LiveStats.fromJson(Map<String, dynamic>.from(res.data['data'] as Map));
  }

  Future<int> likeRoom(int roomId) async {
    final res = await _dio.post('/live/rooms/$roomId/like');
    return res.data['data']['total_likes'] ?? 0;
  }

  // ── Leaderboard ────────────────────────────────────────────────────────────

  Future<List<LeaderboardEntry>> getRoomLeaderboard(int roomId, {String period = 'all'}) async {
    final res  = await _dio.get('/live/rooms/$roomId/leaderboard', queryParameters: {'period': period});
    final list = res.data['data'] as List? ?? [];
    return list.map((e) => LeaderboardEntry.fromJson(Map<String, dynamic>.from(e as Map))).toList();
  }

  // ── Moderation ─────────────────────────────────────────────────────────────

  Future<LiveRoomSettings> getSettings(int roomId) async {
    final res = await _dio.get('/live/rooms/$roomId/settings');
    return LiveRoomSettings.fromJson(Map<String, dynamic>.from(res.data['data'] as Map));
  }

  Future<LiveRoomSettings> updateSettings(int roomId, Map<String, dynamic> data) async {
    final res = await _dio.patch('/live/rooms/$roomId/settings', data: data);
    return LiveRoomSettings.fromJson(Map<String, dynamic>.from(res.data['data'] as Map));
  }

  Future<void> muteChatUser(int roomId, int userId, {int minutes = 0}) async {
    await _dio.post('/live/rooms/$roomId/chat/$userId/mute', data: {'minutes': minutes});
  }

  Future<void> unmuteChatUser(int roomId, int userId) async {
    await _dio.delete('/live/rooms/$roomId/chat/$userId/mute');
  }

  Future<void> pinMessage(int roomId, int messageId) async {
    await _dio.post('/live/rooms/$roomId/chat/pin/$messageId');
  }

  Future<void> unpinMessage(int roomId) async {
    await _dio.delete('/live/rooms/$roomId/chat/pin');
  }

  // ── Safety ─────────────────────────────────────────────────────────────────

  Future<void> reportRoom(int roomId, String reason, {String? description}) async {
    await _dio.post('/live/rooms/$roomId/report', data: {
      'reason':      reason,
      if (description != null) 'description': description,
    });
  }

  // ── PK Battle ──────────────────────────────────────────────────────────────

  Future<List<Map<String, dynamic>>> getAvailableBattleHosts(int roomId) async {
    final res  = await _dio.get('/live/rooms/$roomId/battle/hosts');
    final list = res.data['data'] as List? ?? [];
    return list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }

  Future<Map<String, dynamic>> inviteToBattle(int roomId, int toRoomId) async {
    final res = await _dio.post('/live/rooms/$roomId/battle/invite', data: {'to_room_id': toRoomId});
    return Map<String, dynamic>.from(res.data['data'] as Map);
  }

  Future<Map<String, dynamic>> acceptBattleInvite(int inviteId) async {
    final res = await _dio.post('/live/battle/accept/$inviteId');
    return Map<String, dynamic>.from(res.data['data'] as Map);
  }

  Future<void> rejectBattleInvite(int inviteId) async {
    await _dio.post('/live/battle/reject/$inviteId');
  }

  Future<Map<String, dynamic>> getBattleViewerToken(int battleId) async {
    final res = await _dio.get('/live/battle/$battleId/viewer-token');
    return Map<String, dynamic>.from(res.data['data'] as Map);
  }

  Future<LiveBattle?> getBattleStatus(int roomId) async {
    final res = await _dio.get('/live/rooms/$roomId/battle/status');
    final data = res.data['data'];
    if (data == null) return null;
    return LiveBattle.fromJson(Map<String, dynamic>.from(data as Map));
  }

  Future<void> endBattle(int battleId) async {
    await _dio.post('/live/battle/$battleId/end');
  }
}
