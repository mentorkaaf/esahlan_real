import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/theme_x.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../../../../core/api/module_api_service.dart';

extension _CacheFor on Ref {
  void cacheFor(Duration d) {
    final link = keepAlive();
    final t = Timer(d, link.close);
    onDispose(t.cancel);
  }
}

final _repo = CommunityRepository();

// Stores the post ID to jump to when the reels tab opens
final communityReelsJumpPostIdProvider = StateProvider<int?>((ref) => null);

// ── Feed providers ─────────────────────────────────────────────────────────
final communityFeedProvider =
    StateNotifierProvider<FeedNotifier, AsyncValue<List<CommunityPost>>>(
        (ref) => FeedNotifier('feed'));

final communityExploreProvider =
    StateNotifierProvider<FeedNotifier, AsyncValue<List<CommunityPost>>>(
        (ref) => FeedNotifier('explore'));

final communityReelsProvider =
    StateNotifierProvider<FeedNotifier, AsyncValue<List<CommunityPost>>>(
        (ref) => FeedNotifier('reels'));

class FeedNotifier extends StateNotifier<AsyncValue<List<CommunityPost>>> {
  FeedNotifier(this._feedType) : super(const AsyncValue.loading()) {
    load();
  }

  final String _feedType;
  int _page = 1;
  bool _hasMore = true;
  bool _loading = false;

  Future<void> load({bool refresh = false}) async {
    if (_loading) return;
    if (refresh) {
      _page = 1;
      _hasMore = true;
      state = const AsyncValue.loading();
    }
    if (!_hasMore) return;
    _loading = true;
    try {
      final posts = await _fetchPage(_page);
      if (posts.isEmpty) {
        if (_feedType == 'reels') {
          _page = 1; // Cycle back to page 1 for endless reels
        } else {
          _hasMore = false;
        }
      }
      final current = refresh ? <CommunityPost>[] : (state.valueOrNull ?? []);
      // Dedup real posts by id — the ranking algorithm can occasionally
      // resurface a post across adjacent pages (scores shift between
      // requests). Ads are excluded from this check: their id comes from a
      // separate community_ads sequence that can collide with a real post's
      // id, so comparing them as the same "id space" would wrongly drop one.
      final existingIds = current.where((p) => !p.isAd).map((p) => p.id).toSet();
      final newPosts = posts.where((p) => p.isAd || !existingIds.contains(p.id)).toList();
      state = AsyncValue.data([...current, ...newPosts]);
      if (posts.isNotEmpty) _page++;
    } catch (e, s) {
      if (!refresh) state = AsyncValue.error(e, s);
    } finally {
      _loading = false;
    }
  }

  Future<void> refresh() => load(refresh: true);

  void updatePost(CommunityPost updated) {
    state = state.whenData((posts) =>
        posts.map((p) => p.id == updated.id ? updated : p).toList());
  }

  void removePost(int postId) {
    state = state.whenData((posts) => posts.where((p) => p.id != postId).toList());
  }

  void prependPost(CommunityPost post) {
    state = state.whenData((posts) => [post, ...posts]);
  }

  Future<List<CommunityPost>> _fetchPage(int page) {
    switch (_feedType) {
      case 'explore': return _repo.getExploreFeed(page: page);
      case 'reels': return _repo.getReels(page: page);
      default: return _repo.getFeed(page: page);
    }
  }

  bool get hasMore => _hasMore;
}

// ── Stories provider ───────────────────────────────────────────────────────
final communityStoriesProvider =
    FutureProvider<List<StoryGroup>>((ref) => _repo.getStories());

// ── Suggestions provider ───────────────────────────────────────────────────
final communitySuggestionsProvider =
    FutureProvider<List<CommunityUser>>((ref) => _repo.getSuggestions());

// ── Groups provider ────────────────────────────────────────────────────────
final communityGroupsProvider =
    StateNotifierProvider<GroupsNotifier, AsyncValue<List<CommunityGroup>>>(
        (ref) => GroupsNotifier());

class GroupsNotifier extends StateNotifier<AsyncValue<List<CommunityGroup>>> {
  GroupsNotifier() : super(const AsyncValue.loading()) {
    load();
  }

  Future<void> load({String type = 'suggested'}) async {
    state = const AsyncValue.loading();
    try {
      state = AsyncValue.data(await _repo.getGroups(type: type));
    } catch (e, s) {
      state = AsyncValue.error(e, s);
    }
  }

  Future<void> joinGroup(int groupId) async {
    final result = await _repo.joinGroup(groupId);
    final joined = result['joined'] as bool? ?? false;
    if (joined) {
      state = state.whenData((groups) => groups
          .map((g) => g.id == groupId
              ? CommunityGroup(
                  id: g.id, name: g.name, slug: g.slug, description: g.description,
                  coverPhoto: g.coverPhoto, avatar: g.avatar, privacy: g.privacy,
                  category: g.category, location: g.location,
                  membersCount: g.membersCount + 1, postsCount: g.postsCount,
                  isMember: true, membershipStatus: 'active', isOwner: g.isOwner)
              : g)
          .toList());
    }
  }
}

// ── Chats provider ─────────────────────────────────────────────────────────
final communityChatsProvider =
    StateNotifierProvider<ChatsNotifier, AsyncValue<List<CommunityChat>>>(
        (ref) => ChatsNotifier());

class ChatsNotifier extends StateNotifier<AsyncValue<List<CommunityChat>>> {
  ChatsNotifier() : super(const AsyncValue.loading()) {
    load();
  }

  Future<void> load() async {
    try {
      state = AsyncValue.data(await _repo.getChats());
    } catch (e, s) {
      state = AsyncValue.error(e, s);
    }
  }

  Future<CommunityChat> startOrGetChat(int userId) async {
    final chat = await _repo.startChat(userId);
    final current = state.valueOrNull ?? [];
    if (!current.any((c) => c.id == chat.id)) {
      state = AsyncValue.data([chat, ...current]);
    }
    return chat;
  }
}

// ── Messages provider ──────────────────────────────────────────────────────
final communityMessagesProvider = StateNotifierProvider.family<
    MessagesNotifier, AsyncValue<List<CommunityMessage>>, int>(
  (ref, chatId) => MessagesNotifier(chatId),
);

class MessagesNotifier
    extends StateNotifier<AsyncValue<List<CommunityMessage>>> {
  MessagesNotifier(this._chatId) : super(const AsyncValue.loading()) {
    load();
  }

  final int _chatId;
  static int _myId = 0;
  static int get myId => _myId;
  static void setMyId(int id) => _myId = id;

  Future<void> load() async {
    try {
      final msgs = await _repo.getMessages(_chatId, _myId);
      state = AsyncValue.data(msgs.reversed.toList());
    } catch (e, s) {
      state = AsyncValue.error(e, s);
    }
  }

  Future<void> send({String? content, String type = 'text'}) async {
    final msg = await _repo.sendMessage(_chatId, _myId, content: content, type: type);
    state = state.whenData((msgs) => [...msgs, msg]);
  }

  /// Append a message received over the realtime channel. Skips it if we
  /// already have this id (covers the sender's own optimistic `send()` echo
  /// arriving back over the socket).
  void appendIncoming(CommunityMessage msg) {
    state = state.whenData((msgs) {
      if (msgs.any((m) => m.id == msg.id)) return msgs;
      return [...msgs, msg];
    });
  }

  void markAllReadLocally() {
    state = state.whenData((msgs) => msgs
        .map((m) => m.isRead ? m : CommunityMessage(
              id: m.id, chatId: m.chatId, user: m.user, type: m.type,
              content: m.content, mediaUrl: m.mediaUrl, duration: m.duration,
              isDeleted: m.isDeleted, isMe: m.isMe, isRead: true, createdAt: m.createdAt,
            ))
        .toList());
  }
}

// ── Notifications provider ─────────────────────────────────────────────────
final communityNotifProvider =
    StateNotifierProvider<NotifNotifier, AsyncValue<List<CommunityNotification>>>(
        (ref) => NotifNotifier());

final communityUnreadCountProvider = StateProvider<int>((ref) => 0);

class NotifNotifier
    extends StateNotifier<AsyncValue<List<CommunityNotification>>> {
  NotifNotifier() : super(const AsyncValue.loading()) {
    load();
  }

  Future<void> load() async {
    try {
      final r = await _repo.getNotifications();
      final rawData = r['data'];
      final list = rawData is List ? rawData : (rawData as Map<String, dynamic>)['data'] as List;
      final notifs = list
          .map((e) => CommunityNotification.fromJson(e as Map<String, dynamic>))
          .toList();
      state = AsyncValue.data(notifs);
    } catch (e, s) {
      state = AsyncValue.error(e, s);
    }
  }

  Future<void> markRead(int id) async {
    await _repo.markNotificationRead(id);
    state = state.whenData(
        (ns) => ns.map((n) => n.id == id ? _markAsRead(n) : n).toList());
  }

  Future<void> markAllRead() async {
    await _repo.markAllNotificationsRead();
    state = state.whenData((ns) => ns.map(_markAsRead).toList());
  }

  CommunityNotification _markAsRead(CommunityNotification n) =>
      CommunityNotification(
        id: n.id, actor: n.actor, type: n.type, notifiableType: n.notifiableType,
        notifiableId: n.notifiableId, isRead: true, createdAt: n.createdAt,
      );
}

// ── Profile provider ───────────────────────────────────────────────────────
final communityProfileProvider = FutureProvider.family<CommunityUser, int>(
    (ref, userId) { ref.cacheFor(const Duration(seconds: 30)); return _repo.getProfile(userId); });

final communityMyProfileProvider =
    FutureProvider<CommunityUser>((ref) { ref.cacheFor(const Duration(seconds: 30)); return _repo.getMyProfile(); });

final communityProfilePostsProvider =
    FutureProvider.family<List<CommunityPost>, int>(
        (ref, userId) { ref.cacheFor(const Duration(seconds: 30)); return _repo.getProfilePosts(userId); });

final communityGroupPostsProvider =
    FutureProvider.family<List<CommunityPost>, int>(
        (ref, groupId) { ref.cacheFor(const Duration(seconds: 30)); return _repo.getGroupPosts(groupId); });

final communityHighlightsProvider =
    FutureProvider.family.autoDispose<List<CommunityHighlight>, int>(
        (ref, userId) => _repo.getHighlights(userId));

// ── Repo accessor ──────────────────────────────────────────────────────────
final communityRepoProvider = Provider<CommunityRepository>((ref) => _repo);

// ── Cross-module providers ─────────────────────────────────────────────────
final _modSvc = ModuleApiService.create();

final erentReelsProvider = FutureProvider<List<Map<String, dynamic>>>((ref) async {
  final r = await _modSvc.getRentReels();
  final list = r is Map ? (r['data'] ?? []) : (r is List ? r : []);
  return List<Map<String, dynamic>>.from(
      (list as List).map((e) => Map<String, dynamic>.from(e as Map)));
});

final efoodRestaurantsProvider = FutureProvider<List<Map<String, dynamic>>>((ref) async {
  final r = await _modSvc.getRestaurants();
  final list = r is Map ? (r['data'] ?? []) : (r is List ? r : []);
  return List<Map<String, dynamic>>.from(
      (list as List).map((e) => Map<String, dynamic>.from(e as Map)));
});
