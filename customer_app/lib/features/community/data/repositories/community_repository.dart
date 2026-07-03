import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import '../models/community_models.dart';

class CommunityRepository {
  final _dio = ApiClient.instance;
  static Dio get dioInstance => ApiClient.instance;

  // ── Feed ───────────────────────────────────────────────────────────────────
  Future<List<CommunityPost>> getFeed({int page = 1}) async {
    final r = await _dio.get('/community/feed', queryParameters: {'page': page});
    return _parsePosts(r.data['data']);
  }

  // ── Feed interaction tracking ──────────────────────────────────────────
  Future<void> trackInteraction(int postId, String type, {int? durationMs}) async {
    try {
      await _dio.post('/community/feed/track', data: {
        'post_id': postId,
        'type': type,
        if (durationMs != null) 'duration_ms': durationMs,
      });
    } catch (_) {}
  }

  Future<void> trackImpressions(List<int> postIds) async {
    if (postIds.isEmpty) return;
    try {
      await _dio.post('/community/feed/impressions', data: {'post_ids': postIds});
    } catch (_) {}
  }

  Future<void> feedHeartbeat() async {
    try { await _dio.post('/community/feed/heartbeat'); } catch (_) {}
  }

  Future<void> feedLeave() async {
    try { await _dio.delete('/community/feed/heartbeat'); } catch (_) {}
  }

  Future<List<CommunityPost>> getExploreFeed({int page = 1}) async {
    final r = await _dio.get('/community/explore', queryParameters: {'page': page});
    return _parsePosts(r.data['data']);
  }

  Future<List<Map<String, dynamic>>> getTrendingHashtags() async {
    final r = await _dio.get('/community/explore');
    return ((r.data['hashtags'] ?? []) as List).cast<Map<String, dynamic>>();
  }

  Future<List<CommunityPost>> getReels({int page = 1}) async {
    final r = await _dio.get('/community/reels', queryParameters: {'page': page});
    return _parsePosts(r.data['data']);
  }

  Future<List<StoryGroup>> getStories() async {
    final r = await _dio.get('/community/stories');
    return (r.data['data'] as List)
        .map((e) => StoryGroup.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<CommunityUser>> getSuggestions() async {
    final r = await _dio.get('/community/suggestions');
    return (r.data['data'] as List)
        .map((e) => CommunityUser.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<Map<String, dynamic>> search(String query, {String type = 'posts', int page = 1}) async {
    final r = await _dio.get('/community/search', queryParameters: {'q': query, 'type': type, 'page': page});
    return r.data as Map<String, dynamic>;
  }

  // ── Posts ──────────────────────────────────────────────────────────────────
  Future<CommunityPost> createPost({
    required String type,
    String? content,
    String? privacy,
    String? location,
    String? feeling,
    int? groupId,
    int? pageId,
    List<String>? pollOptions,
    List<dynamic>? mediaFiles,
  }) async {
    final form = FormData.fromMap({
      'type': type,
      if (content != null) 'content': content,
      if (privacy != null) 'privacy': privacy,
      if (location != null) 'location': location,
      if (feeling != null) 'feeling': feeling,
      if (pageId != null) 'page_id': pageId,
      if (groupId != null) 'group_id': groupId,
      if (pollOptions != null) ...{for (var i = 0; i < pollOptions.length; i++) 'poll_options[$i]': pollOptions[i]},
    });

    if (mediaFiles != null) {
      for (final file in mediaFiles) {
        if (file is MultipartFile) form.files.add(MapEntry('media[]', file));
      }
    }

    final r = await _dio.post('/community/posts', data: form);
    return CommunityPost.fromJson(r.data['data'] as Map<String, dynamic>);
  }

  Future<CommunityPost> getPost(int id) async {
    final r = await _dio.get('/community/posts/$id');
    return CommunityPost.fromJson(r.data['data'] as Map<String, dynamic>);
  }

  Future<void> deletePost(int id) => _dio.delete('/community/posts/$id');

  Future<Map<String, dynamic>> reactToPost(int postId, String type) async {
    final r = await _dio.post('/community/posts/$postId/react', data: {'type': type});
    return r.data as Map<String, dynamic>;
  }

  Future<CommunityPost> sharePost(int postId, {String? content}) async {
    final r = await _dio.post('/community/posts/$postId/share', data: {'content': content});
    return CommunityPost.fromJson(r.data['data'] as Map<String, dynamic>);
  }

  Future<bool> savePost(int postId) async {
    final r = await _dio.post('/community/posts/$postId/save');
    return r.data['saved'] as bool;
  }

  Future<List<CommunityPost>> getSavedPosts() async {
    final r = await _dio.get('/community/posts/saved');
    return _parsePosts(r.data['data']);
  }

  Future<Map<String, dynamic>> votePoll(int postId, int optionIndex) async {
    final r = await _dio.post('/community/posts/$postId/vote', data: {'option_index': optionIndex});
    return r.data as Map<String, dynamic>;
  }

  // ── Comments ───────────────────────────────────────────────────────────────
  Future<List<CommunityComment>> getComments(int postId, {int page = 1}) async {
    final r = await _dio.get('/community/posts/$postId/comments', queryParameters: {'page': page});
    final raw = r.data['data'];
    final list = raw is List ? raw : (raw as Map<String, dynamic>)['data'] as List;
    return list.map((e) => CommunityComment.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<CommunityComment> addComment(int postId, String content, {int? parentId}) async {
    final r = await _dio.post('/community/posts/$postId/comments',
        data: {'content': content, if (parentId != null) 'parent_id': parentId});
    return CommunityComment.fromJson(r.data['data'] as Map<String, dynamic>);
  }

  Future<CommunityComment> addMediaComment(int postId, FormData form) async {
    final r = await _dio.post('/community/posts/$postId/comments', data: form);
    return CommunityComment.fromJson(r.data['data'] as Map<String, dynamic>);
  }

  Future<void> deleteComment(int commentId) => _dio.delete('/community/comments/$commentId');

  Future<void> updateComment(int commentId, String content) =>
      _dio.put('/community/comments/$commentId', data: {'content': content});

  // ── Profile ────────────────────────────────────────────────────────────────
  Future<CommunityUser> getProfile(int userId) async {
    final r = await _dio.get('/community/profile/$userId');
    return CommunityUser.fromJson(r.data['data'] as Map<String, dynamic>);
  }

  Future<CommunityUser> getMyProfile() async {
    final r = await _dio.get('/community/profile/me');
    return CommunityUser.fromJson(r.data['data'] as Map<String, dynamic>);
  }

  Future<List<CommunityPost>> getProfilePosts(int userId, {String type = 'posts', int page = 1}) async {
    final r = await _dio.get('/community/profile/$userId/posts',
        queryParameters: {'type': type, 'page': page});
    return _parsePosts(r.data['data']);
  }

  Future<void> updateProfile(Map<String, dynamic> data) async {
    final form = FormData.fromMap(data);
    await _dio.put('/community/profile', data: form);
  }

  Future<Map<String, dynamic>> uploadProfilePhoto({dynamic avatarFile, dynamic coverFile}) async {
    final form = FormData.fromMap({
      if (avatarFile is MultipartFile) 'avatar': avatarFile,
      if (coverFile is MultipartFile) 'cover_photo': coverFile,
    });
    final r = await _dio.post('/community/profile/avatar', data: form);
    return r.data['data'] as Map<String, dynamic>;
  }

  // ── Follow ─────────────────────────────────────────────────────────────────
  Future<bool> toggleFollow(int userId) async {
    final r = await _dio.post('/community/follow/$userId');
    return r.data['following'] as bool;
  }

  // ── Stories ────────────────────────────────────────────────────────────────
  // Ads
  Future<Map<String, dynamic>> boostPost(int postId, {required double budget, required int durationHours}) async {
    final r = await _dio.post('/community/posts/$postId/boost', data: {'budget': budget, 'duration_hours': durationHours});
    return r.data['data'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getAdAnalytics() async {
    final r = await _dio.get('/community/ads/analytics');
    return r.data['data'] as Map<String, dynamic>;
  }

  Future<void> viewStory(int storyId) => _dio.post('/community/stories/$storyId/view');
  Future<void> reactToStory(int storyId, String emoji) => _dio.post('/community/stories/$storyId/react', data: {'emoji': emoji});
  Future<void> commentOnStory(int storyId, String content) => _dio.post('/community/stories/$storyId/comment', data: {'content': content});
  Future<List<Map<String, dynamic>>> getStoryViewers(int storyId) async {
    final r = await _dio.get('/community/stories/$storyId/viewers');
    return List<Map<String, dynamic>>.from(r.data['data'] ?? []);
  }

  Future<void> createStory({
    required String type,
    String? textContent,
    String? bgColor,
    dynamic mediaFile,
  }) async {
    final form = FormData.fromMap({
      'type': type,
      if (textContent != null) 'text_content': textContent,
      if (bgColor != null) 'bg_color': bgColor,
      if (mediaFile is MultipartFile) 'media': mediaFile,
    });
    await _dio.post('/community/stories', data: form);
  }

  // ── Groups ─────────────────────────────────────────────────────────────────
  Future<List<CommunityGroup>> getGroups({String type = 'suggested', int page = 1}) async {
    final r = await _dio.get('/community/groups', queryParameters: {'type': type, 'page': page});
    return (r.data['data'] as List)
        .map((e) => CommunityGroup.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<CommunityGroup> createGroup({required String name, String? description, String privacy = 'public'}) async {
    final r = await _dio.post('/community/groups', data: {
      'name': name,
      if (description != null) 'description': description,
      'privacy': privacy,
    });
    return CommunityGroup.fromJson(r.data['data'] as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> joinGroup(int groupId) async {
    final r = await _dio.post('/community/groups/$groupId/join');
    return r.data as Map<String, dynamic>;
  }

  Future<void> leaveGroup(int groupId) => _dio.delete('/community/groups/$groupId/leave');

  Future<List<CommunityPost>> getGroupPosts(int groupId, {int page = 1}) async {
    final r = await _dio.get('/community/groups/$groupId/posts', queryParameters: {'page': page});
    return _parsePosts(r.data['data']);
  }

  // ── Chats ──────────────────────────────────────────────────────────────────
  Future<List<CommunityChat>> getChats() async {
    final r = await _dio.get('/community/chats');
    return (r.data['data'] as List)
        .map((e) => CommunityChat.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<CommunityChat> startChat(int userId) async {
    final r = await _dio.post('/community/chats/start/$userId');
    return CommunityChat.fromJson(r.data['data'] as Map<String, dynamic>);
  }

  Future<List<CommunityMessage>> getMessages(int chatId, int myId, {int page = 1}) async {
    final r = await _dio.get('/community/chats/$chatId/messages', queryParameters: {'page': page});
    return (r.data['data'] as List)
        .map((e) => CommunityMessage.fromJson(e as Map<String, dynamic>, myId))
        .toList();
  }

  Future<CommunityMessage> sendMessage(int chatId, int myId, {String? content, String type = 'text', dynamic mediaFile}) async {
    final form = FormData.fromMap({
      'type': type,
      if (content != null) 'content': content,
      if (mediaFile is MultipartFile) 'media': mediaFile,
    });
    final r = await _dio.post('/community/chats/$chatId/messages', data: form);
    return CommunityMessage.fromJson(r.data['data'] as Map<String, dynamic>, myId);
  }

  // ── Notifications ──────────────────────────────────────────────────────────
  Future<Map<String, dynamic>> getNotifications({int page = 1}) async {
    final r = await _dio.get('/community/notifications', queryParameters: {'page': page});
    return r.data as Map<String, dynamic>;
  }

  Future<void> markNotificationRead(int id) => _dio.post('/community/notifications/$id/read');
  Future<void> markAllNotificationsRead() => _dio.post('/community/notifications/read-all');

  Future<int> getUnreadNotificationCount() async {
    final r = await _dio.get('/community/notifications/unread-count');
    return r.data['count'] as int? ?? 0;
  }

  // ── Onboarding ─────────────────────────────────────────────────────────────
  Future<bool> checkOnboarding() async {
    final r = await _dio.get('/community/profile/onboarding-check');
    return r.data['completed'] as bool? ?? false;
  }

  Future<void> submitOnboarding(Map<String, dynamic> data) async {
    await _dio.post('/community/profile/onboarding', data: data);
  }

  Future<Map<String, dynamic>> estimateAudience(Map<String, dynamic> params) async {
    final r = await _dio.post('/community/ads/estimate-audience', data: params);
    return r.data['data'] as Map<String, dynamic>;
  }

  // ── Post Edit ──────────────────────────────────────────────────────────────
  Future<CommunityPost> updatePost(int postId, {String? content, String? privacy}) async {
    final r = await _dio.put('/community/posts/$postId', data: {
      if (content != null) 'content': content,
      if (privacy != null) 'privacy': privacy,
    });
    return CommunityPost.fromJson(r.data['data'] as Map<String, dynamic>);
  }

  // ── Chat Extras ───────────────────────────────────────────────────────────
  Future<void> markChatRead(int chatId) => _dio.post('/community/chats/$chatId/read');

  Future<void> sendTyping(int chatId, bool isTyping) async {
    try {
      await _dio.post('/community/chats/$chatId/typing', data: {'is_typing': isTyping});
    } catch (_) {}
  }

  Future<void> reactToMessage(int msgId, String emoji) =>
    _dio.post('/community/messages/$msgId/react', data: {'emoji': emoji});

  Future<CommunityMessage> sendVoiceMessage(int chatId, int myId, {required dynamic mediaFile}) async {
    final form = FormData.fromMap({
      'type': 'voice',
      if (mediaFile is MultipartFile) 'media': mediaFile,
    });
    final r = await _dio.post('/community/chats/$chatId/messages', data: form);
    return CommunityMessage.fromJson(r.data['data'] as Map<String, dynamic>, myId);
  }

  // ── Highlights ────────────────────────────────────────────────────────────
  Future<List<Map<String, dynamic>>> getHighlights(int userId) async {
    final r = await _dio.get('/community/highlights/$userId');
    return (r.data['data'] as List).cast<Map<String, dynamic>>();
  }

  Future<void> createHighlight({required String title, required List<int> storyIds}) =>
    _dio.post('/community/highlights', data: {'title': title, 'story_ids': storyIds});

  Future<void> deleteHighlight(int id) => _dio.delete('/community/highlights/$id');

  // ── Trending ──────────────────────────────────────────────────────────────
  Future<List<Map<String, dynamic>>> getTrending() async {
    final r = await _dio.get('/community/trending');
    return (r.data['data'] as List).cast<Map<String, dynamic>>();
  }

  // ── Report ─────────────────────────────────────────────────────────────────
  Future<void> report(String type, int id, String reason, {String? description}) async {
    await _dio.post('/community/report', data: {
      'reportable_type': type,
      'reportable_id': id,
      'reason': reason,
      if (description != null) 'description': description,
    });
  }

  Future<bool> blockUser(int userId) async {
    final r = await _dio.post('/community/block/$userId');
    return r.data['blocked'] as bool;
  }

  // ── Business Pages ─────────────────────────────────────────────────────────
  Future<List<Map<String, dynamic>>> getBusinessPages({int page = 1}) async {
    final r = await _dio.get('/community/pages', queryParameters: {'page': page});
    return (r.data['data'] as List).cast<Map<String, dynamic>>();
  }

  Future<List<Map<String, dynamic>>> getMyPages() async {
    final r = await _dio.get('/community/pages/mine');
    return (r.data['data'] as List).cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> createBusinessPage(FormData data) async {
    final r = await _dio.post('/community/pages', data: data);
    return r.data['data'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getBusinessPage(int id) async {
    final r = await _dio.get('/community/pages/$id');
    return r.data['data'] as Map<String, dynamic>;
  }

  Future<List<CommunityPost>> getPagePosts(int pageId, {int page = 1}) async {
    final r = await _dio.get('/community/pages/$pageId/posts', queryParameters: {'page': page});
    return _parsePosts(r.data['data']);
  }

  Future<bool> togglePageFollow(int pageId) async {
    final r = await _dio.post('/community/pages/$pageId/follow');
    return r.data['is_following'] as bool;
  }

  // ── Ads ────────────────────────────────────────────────────────────────────
  Future<List<Map<String, dynamic>>> getAdPricing() async {
    final r = await _dio.get('/community/ads/pricing');
    return (r.data['data'] as List).cast<Map<String, dynamic>>();
  }

  Future<List<Map<String, dynamic>>> getMyAds() async {
    final r = await _dio.get('/community/ads/mine');
    return (r.data['data'] as List).cast<Map<String, dynamic>>();
  }

  Future<Map<String, dynamic>> createAd(FormData data) async {
    final r = await _dio.post('/community/ads', data: data);
    return r.data as Map<String, dynamic>;
  }

  Future<void> trackAdClick(int adId) => _dio.post('/community/ads/$adId/click');

  Future<Map<String, dynamic>> getAdDisplaySettings() async {
    final r = await _dio.get('/community/ads/settings');
    return r.data['data'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>?> getPrerollAd() async {
    final r = await _dio.get('/community/ads/preroll');
    return r.data['data'] as Map<String, dynamic>?;
  }

  /// Poll transcoding status for a single media item.
  /// Returns map with transcoding_status, transcoding_progress, hls_url, thumbnail.
  Future<Map<String, dynamic>> getTranscodingStatus(int mediaId) async {
    final r = await _dio.get('/community/media/$mediaId/transcoding-status');
    return r.data as Map<String, dynamic>;
  }

  // ── Helper ─────────────────────────────────────────────────────────────────
  List<CommunityPost> _parsePosts(dynamic data) {
    List raw;
    if (data is List) {
      raw = data;
    } else if (data is Map && data['data'] is List) {
      raw = data['data'] as List;
    } else {
      return [];
    }
    return raw.map((e) {
      final m = e as Map<String, dynamic>;
      if (m['is_ad'] == true) return CommunityPost.ad(m);
      return CommunityPost.fromJson(m);
    }).toList();
  }
}
