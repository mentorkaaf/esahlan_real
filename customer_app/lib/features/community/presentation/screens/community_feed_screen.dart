import 'dart:async';
import '../../../../core/widgets/network_image_widget.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../../../core/widgets/app_shimmer.dart';
import 'package:video_player/video_player.dart';
import 'package:audioplayers/audioplayers.dart' as ap;
import 'package:webview_flutter/webview_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:visibility_detector/visibility_detector.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import '../widgets/stories_bar.dart';
import 'community_shell.dart';
import 'community_notifications_screen.dart';
import 'create_post_screen.dart';
import '../widgets/comments_sheet.dart';
import '../widgets/video_ad_overlay.dart';
import '../services/ad_preloader.dart';
import '../services/video_pool.dart';
import '../../../../core/services/realtime_client.dart';
import '../../../../core/widgets/realtime_status_banner.dart';
import 'business_page_detail_screen.dart';
import 'community_search_screen.dart';

class CommunityFeedScreen extends ConsumerStatefulWidget {
  const CommunityFeedScreen({super.key});

  static final scrollController = ScrollController();

  static void scrollToTop() {
    if (scrollController.hasClients) {
      scrollController.animateTo(0, duration: const Duration(milliseconds: 300), curve: Curves.easeOut);
    }
  }

  @override
  ConsumerState<CommunityFeedScreen> createState() => _CommunityFeedScreenState();
}

class _CommunityFeedScreenState extends ConsumerState<CommunityFeedScreen>
    with SingleTickerProviderStateMixin {
  ScrollController get _scrollCtrl => CommunityFeedScreen.scrollController;
  late TabController _tabCtrl;
  bool _hasNewPosts = false;
  int _lastPostCount = 0;
  int _lastFirstPostId = 0;
  Timer? _pollTimer;
  Timer? _heartbeatTimer;
  int? _myUserId;
  void Function(dynamic)? _newPostListener;
  void Function(dynamic)? _newStoryListener;

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 4, vsync: this);
    _scrollCtrl.addListener(() {
      if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 300) {
        ref.read(communityFeedProvider.notifier).load();
      }
      if (_scrollCtrl.offset < 100 && _hasNewPosts) {
        setState(() => _hasNewPosts = false);
      }
    });
    // Fallback poll (slow — realtime is the primary path, this just covers
    // the rare case where the socket is down for an extended period).
    _pollTimer = Timer.periodic(const Duration(seconds: 90), (_) => _checkNewPosts());
    // Presence heartbeat — tells the admin dashboard this user is actively on the feed.
    CommunityRepository().feedHeartbeat();
    _heartbeatTimer = Timer.periodic(const Duration(seconds: 30), (_) => CommunityRepository().feedHeartbeat());
    _subscribeNewPostFeed();
  }

  Future<void> _subscribeNewPostFeed() async {
    // Public channel — every connected client sees the "new posts" hint,
    // no auth/profile wait needed to start listening. The feed itself still
    // applies normal ranking/privacy whenever the user actually refreshes —
    // this listener never triggers that refresh automatically, it only
    // ever shows a pill the user taps. Auto-refreshing here would blow away
    // the locally-prepended feed state (including the poster's own just-
    // created post and everyone else's posts already loaded) and replace it
    // with a freshly re-ranked page 1, which is what made other people's
    // posts appear to "vanish".
    _newPostListener = (data) {
      if (!mounted) return;
      if (_myUserId != null && (data['author_id'] as int?) == _myUserId) return; // don't notify yourself about your own post
      if (_scrollCtrl.hasClients && _scrollCtrl.offset > 200) {
        setState(() => _hasNewPosts = true);
      }
      // Already at/near the top — nothing to do, they'll see it naturally
      // next time they pull-to-refresh or reopen the tab.
    };
    RealtimeClient.instance.listen('community.feed', 'feed.new_post', _newPostListener!);

    // Stories stay follower-scoped (private per-user channel).
    try {
      final me = await ref.read(communityMyProfileProvider.future);
      _myUserId = me.id;
      _newStoryListener = (data) {
        if (mounted) ref.invalidate(communityStoriesProvider);
      };
      RealtimeClient.instance.listen('private-user.${me.id}', 'story.new', _newStoryListener!);
    } catch (_) {}
  }

  Future<void> _checkNewPosts() async {
    if (!_scrollCtrl.hasClients || _scrollCtrl.offset < 200) return;
    try {
      final repo = CommunityRepository();
      final fresh = await repo.getFeed(page: 1);
      if (fresh.isNotEmpty && _lastFirstPostId > 0 && fresh.first.id != _lastFirstPostId) {
        if (mounted) setState(() => _hasNewPosts = true);
      }
    } catch (_) {}
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    _heartbeatTimer?.cancel();
    CommunityRepository().feedLeave();
    _tabCtrl.dispose();
    if (_newPostListener != null) {
      RealtimeClient.instance.removeListener('community.feed', 'feed.new_post', _newPostListener!);
    }
    if (_myUserId != null && _newStoryListener != null) {
      RealtimeClient.instance.removeListener('private-user.$_myUserId', 'story.new', _newStoryListener!);
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final feedState = ref.watch(communityFeedProvider);
    final storiesState = ref.watch(communityStoriesProvider);
    final unread = ref.watch(communityUnreadCountProvider);

    // Detect new posts added while scrolled down
    feedState.whenData((posts) {
      final firstId = posts.isNotEmpty ? posts.first.id : 0;
      if (_lastFirstPostId > 0 && firstId != _lastFirstPostId && _scrollCtrl.hasClients && _scrollCtrl.offset > 200) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted && !_hasNewPosts) setState(() => _hasNewPosts = true);
        });
      }
      _lastFirstPostId = firstId;
      _lastPostCount = posts.length;
    });

    return Scaffold(
      body: Stack(children: [
      NestedScrollView(
        controller: _scrollCtrl,
        headerSliverBuilder: (context, _) => [
          SliverAppBar(
            pinned: true,
            floating: true,
            elevation: 0,
            
            title: RichText(
              text: const TextSpan(
                children: [
                  TextSpan(text: 'e', style: TextStyle(color: kOrange, fontWeight: FontWeight.w900, fontSize: 22)),
                  TextSpan(text: 'Sahlan.', style: TextStyle(color: Color(0xFF1A1B2E), fontWeight: FontWeight.w700, fontSize: 20)),
                ],
              ),
            ),
            actions: [
              _AppBarBtn(icon: Icons.search_rounded, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CommunitySearchScreen()))),
              _AppBarBtn(
                icon: Icons.notifications_rounded,
                badge: unread > 0 ? unread : null,
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CommunityNotificationsScreen())),
              ),
              _AppBarBtn(icon: Icons.home_rounded, onTap: () => context.go('/home')),
              const SizedBox(width: 4),
            ],
            bottom: TabBar(
              controller: _tabCtrl,
              indicatorColor: kOrange,
              indicatorWeight: 2.5,
              labelColor: kOrange,
              unselectedLabelColor: const Color(0xFF6B7280),
              labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
              unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w500, fontSize: 13),
              tabs: const [
                Tab(icon: Icon(Icons.home_rounded, size: 20), text: 'For You'),
                Tab(icon: Icon(Icons.local_fire_department_rounded, size: 20), text: 'Trending'),
                Tab(icon: Icon(Icons.people_rounded, size: 20), text: 'People'),
                Tab(icon: Icon(Icons.store_rounded, size: 20), text: 'Business'),
              ],
            ),
          ),
        ],
        body: TabBarView(
          controller: _tabCtrl,
          children: [
            _FeedTab(feedState: feedState, storiesState: storiesState),
            _TrendingTab(),
            _PeopleTab(),
            const _BusinessesTab(),
          ],
        ),
      ),
      // "New posts" pill â€” Twitter-style
      if (_hasNewPosts) Positioned(top: MediaQuery.of(context).padding.top + 56, left: 0, right: 0,
        child: Center(child: GestureDetector(
          onTap: () {
            setState(() => _hasNewPosts = false);
            CommunityFeedScreen.scrollToTop();
          },
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(20),
              boxShadow: [BoxShadow(color: kOrange.withValues(alpha: 0.4), blurRadius: 8, offset: const Offset(0, 2))]),
            child: const Row(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.arrow_upward_rounded, color: Colors.white, size: 16),
              SizedBox(width: 4),
              Text('New posts', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
            ]),
          ),
        )),
      ),
      Positioned(top: 0, left: 0, right: 0, child: SafeArea(bottom: false, child: RealtimeStatusBanner())),
      ]),
    );
  }
}

class _AppBarBtn extends StatelessWidget {
  final IconData icon;
  final int? badge;
  final VoidCallback onTap;
  const _AppBarBtn({required this.icon, required this.onTap, this.badge});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 36, height: 36,
        margin: const EdgeInsets.only(right: 6),
        decoration: BoxDecoration(
          color: context.colors.chipBg,
          shape: BoxShape.circle,
        ),
        child: Stack(
          children: [
            Center(child: Icon(icon, color: context.colors.navyText, size: 20)),
            if (badge != null)
              Positioned(
                right: 4, top: 4,
                child: Container(
                  width: 14, height: 14,
                  decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
                  child: Center(
                    child: Text(
                      badge! > 9 ? '9+' : '$badge',
                      style: const TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w700),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _FeedTab extends ConsumerWidget {
  final AsyncValue<List<CommunityPost>> feedState;
  final AsyncValue<List<StoryGroup>> storiesState;
  const _FeedTab({required this.feedState, required this.storiesState});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return ColoredBox(
      color: context.colors.scaffoldBg,
      child: RefreshIndicator(
      color: kOrange,
      onRefresh: () => ref.read(communityFeedProvider.notifier).refresh(),
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          // Stories
          storiesState.when(
            data: (groups) => StoriesBar(groups: groups),
            loading: () => const SizedBox(height: 100),
            error: (_, __) => const SizedBox.shrink(),
          ),

          // Create post bar
          const _CreatePostBar(),

          const SizedBox(height: 4),

          // Feed posts
          feedState.when(
            data: (posts) {
              if (posts.isEmpty) return const _EmptyFeed();
              final suggestions = ref.watch(communitySuggestionsProvider).valueOrNull ?? [];
              final reels = ref.watch(communityReelsProvider).valueOrNull ?? [];

              // Register all video URLs (feed order) so VideoPool can preload ahead.
              // Also eagerly kick off the first 4 — they're above the fold and need
              // to be ready before the user even starts scrolling.
              WidgetsBinding.instance.addPostFrameCallback((_) {
                final videoUrls = posts
                  .expand((p) => p.media.where((m) => m.type == 'video').map((m) => m.mp4DirectUrl))
                  .where((u) => u.isNotEmpty)
                  .toList();
                VideoPool.feed.setFeedUrls(videoUrls);
                // Prime the first window immediately (index 0, preloads 0..3)
                if (videoUrls.isNotEmpty) VideoPool.feed.setWindow(videoUrls, 0);
              });

              final widgets = <Widget>[];
              for (var i = 0; i < posts.length; i++) {
                widgets.add(_PostCard(post: posts[i],
                  onDelete: () { ref.read(communityRepoProvider).deletePost(posts[i].id); ref.read(communityFeedProvider.notifier).removePost(posts[i].id); },
                ));
                if (i == 4 && suggestions.where((u) => !u.isMe && !u.isFollowing).isNotEmpty) {
                  widgets.add(_PeopleYouMayKnow(users: suggestions.where((u) => !u.isMe && !u.isFollowing).take(10).toList()));
                }
                if (i == 8 && reels.isNotEmpty) {
                  widgets.add(_ReelsCarousel(reels: reels.take(6).toList()));
                }
                if (i > 12 && (i - 12) % 10 == 0 && suggestions.where((u) => !u.isMe && !u.isFollowing).length > 10) {
                  final offset = ((i - 12) ~/ 10) * 5;
                  final batch = suggestions.where((u) => !u.isMe && !u.isFollowing).skip(offset).take(10).toList();
                  if (batch.isNotEmpty) widgets.add(_PeopleYouMayKnow(users: batch));
                }
                if (i > 16 && (i - 16) % 12 == 0 && reels.length > 6) {
                  final offset = ((i - 16) ~/ 12) * 4;
                  final batch = reels.skip(offset).take(6).toList();
                  if (batch.isNotEmpty) widgets.add(_ReelsCarousel(reels: batch));
                }
              }
              widgets.add(const SizedBox(height: 80));
              return Column(children: widgets);
            },
            loading: () => const Padding(
              padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              child: ShimmerPostList(count: 3),
            ),
            error: (e, _) => Center(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Text('Error: $e', style: const TextStyle(color: Colors.red)),
              ),
            ),
          ),
        ],
      ),
    ),
    );
  }
}

final _trendingHashtagsProvider = FutureProvider<List<Map<String, dynamic>>>((ref) => ref.read(communityRepoProvider).getTrendingHashtags());

class _TrendingTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final exploreState = ref.watch(communityExploreProvider);
    final hashtagsAsync = ref.watch(_trendingHashtagsProvider);
    return RefreshIndicator(
      color: kOrange,
      onRefresh: () async { ref.read(communityExploreProvider.notifier).refresh(); ref.invalidate(_trendingHashtagsProvider); },
      child: ListView(padding: const EdgeInsets.only(bottom: 80), children: [
        // Trending Hashtags
        hashtagsAsync.when(
          data: (tags) {
            if (tags.isEmpty) return const SizedBox();
            return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Padding(padding: EdgeInsets.fromLTRB(16, 12, 16, 8),
                child: Row(children: [
                  Icon(Icons.tag_rounded, color: kOrange, size: 20),
                  SizedBox(width: 6),
                  Text('Trending Hashtags', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFF1A1B2E))),
                ])),
              SizedBox(height: 40, child: ListView.builder(
                scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 12),
                itemCount: tags.length,
                itemBuilder: (_, i) => Container(
                  margin: const EdgeInsets.only(right: 8),
                  padding: const EdgeInsets.symmetric(horizontal: 14),
                  decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: kOrange.withValues(alpha: 0.2))),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Text('#${tags[i]['name']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: kOrange)),
                    const SizedBox(width: 6),
                    Text('${tags[i]['posts_count']}', style: TextStyle(fontSize: 11, color: kOrange.withValues(alpha: 0.6), fontWeight: FontWeight.w600)),
                  ]),
                ),
              )),
              const SizedBox(height: 8),
              const Divider(height: 1),
            ]);
          },
          loading: () => const SizedBox(),
          error: (_, __) => const SizedBox(),
        ),

        // Trending Posts (100+ engagement)
        const Padding(padding: EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Row(children: [
            Icon(Icons.local_fire_department_rounded, color: kOrange, size: 20),
            SizedBox(width: 6),
            Text('Trending Posts', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFF1A1B2E))),
            Spacer(),
            Text('100+ engagement', style: TextStyle(fontSize: 11, color: Color(0xFF9CA3AF))),
          ])),

        exploreState.when(
          data: (posts) {
            if (posts.isEmpty) return const Padding(padding: EdgeInsets.all(40),
              child: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.trending_up_rounded, size: 48, color: Color(0xFFD1D5DB)),
                SizedBox(height: 8),
                Text('No trending posts yet', style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 14)),
                SizedBox(height: 4),
                Text('Posts need 100+ total likes, comments, views or shares', style: TextStyle(color: Color(0xFFD1D5DB), fontSize: 12), textAlign: TextAlign.center),
              ])));
            return Column(children: posts.map((p) => _PostCard(post: p, onDelete: () {})).toList());
          },
          loading: () => const Padding(padding: EdgeInsets.all(16), child: ShimmerPostList(count: 3)),
          error: (e, _) => const _EmptyTab(message: 'Error loading trending', icon: Icons.error_outline_rounded),
        ),
      ]),
    );
  }
}

class _PeopleTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final suggestionsAsync = ref.watch(communitySuggestionsProvider);
    return RefreshIndicator(
      color: kOrange,
      onRefresh: () async => ref.invalidate(communitySuggestionsProvider),
      child: suggestionsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
        data: (users) {
          if (users.isEmpty) return const _EmptyTab(message: 'No people found', icon: Icons.people_rounded);
          return ListView.builder(
            padding: const EdgeInsets.symmetric(vertical: 8),
            itemCount: users.length,
            itemBuilder: (_, i) => _PersonTile(user: users[i]),
          );
        },
        error: (_, __) => const _EmptyTab(message: 'Could not load people', icon: Icons.people_rounded),
      ),
    );
  }
}

class _EmptyTab extends StatelessWidget {
  final String message;
  final IconData icon;
  const _EmptyTab({required this.message, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, size: 50, color: const Color(0xFFD1D5DB)),
        const SizedBox(height: 12),
        Text(message, style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 15)),
      ]),
    );
  }
}

class _PersonTile extends ConsumerStatefulWidget {
  final CommunityUser user;
  const _PersonTile({required this.user});
  @override
  ConsumerState<_PersonTile> createState() => _PersonTileState();
}

class _PersonTileState extends ConsumerState<_PersonTile> {
  late bool _following;

  @override
  void initState() { super.initState(); _following = widget.user.isFollowing; }

  @override
  Widget build(BuildContext context) {
    final u = widget.user;
    return InkWell(
      onTap: () => context.push('/community/profile/${u.id}'),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(children: [
          CircleNetImage(url: u.avatar, size: 52, fallbackText: u.name),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Flexible(child: Text(u.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF1A1B2E)), maxLines: 1, overflow: TextOverflow.ellipsis)),
              if (u.isVerified) const Padding(padding: EdgeInsets.only(left: 4), child: Icon(Icons.verified_rounded, color: Color(0xFF1877F2), size: 16)),
            ]),
            if (u.username != null) Text('@${u.username}', style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 13)),
            if (u.bio != null && u.bio!.isNotEmpty) Text(u.bio!, style: const TextStyle(color: Color(0xFF6B7280), fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 4),
            Row(children: [
              Text('${u.followersCount} followers', style: const TextStyle(fontSize: 12, color: Color(0xFF9CA3AF), fontWeight: FontWeight.w600)),
              const Text('  Â·  ', style: TextStyle(color: Color(0xFFD1D5DB))),
              Text('${u.postsCount} posts', style: const TextStyle(fontSize: 12, color: Color(0xFF9CA3AF), fontWeight: FontWeight.w600)),
            ]),
          ])),
          const SizedBox(width: 10),
          if (!u.isMe) SizedBox(width: 90, child: ElevatedButton(
            onPressed: () async {
              setState(() => _following = !_following);
              try { await ref.read(communityRepoProvider).toggleFollow(u.id); } catch (_) { setState(() => _following = !_following); }
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: _following ? context.colors.chipBg : kOrange,
              foregroundColor: _following ? context.colors.mutedText : Colors.white,
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              padding: const EdgeInsets.symmetric(vertical: 8)),
            child: Text(_following ? 'Following' : 'Follow', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
          )),
        ]),
      ),
    );
  }
}

// â”€â”€ Ad Card â€” video auto-plays, image loads instantly â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
class _AdCard extends ConsumerStatefulWidget {
  final CommunityPost post;
  const _AdCard({required this.post});
  @override
  ConsumerState<_AdCard> createState() => _AdCardState();
}

class _AdCardState extends ConsumerState<_AdCard> with WidgetsBindingObserver {
  VideoPlayerController? _vCtrl;
  bool _videoReady = false;
  bool _muted = false;
  bool _visible = false;

  String? get _adUrl => widget.post.adMediaUrl;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (widget.post.adType == 'video' && _adUrl != null) {
      _initAdVideo();
    }
  }

  void _initAdVideo() async {
    try {
      final ctrl = VideoPlayerController.networkUrl(
        Uri.parse(_adUrl!),
        httpHeaders: const {'Connection': 'keep-alive'},
      );
      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(0);
      if (!mounted) { ctrl.dispose(); return; }
      setState(() { _vCtrl = ctrl; _videoReady = true; });
      if (_visible) {
        ctrl.setVolume(_muted ? 0 : 1);
        ctrl.play();
      }
    } catch (_) {}
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (_vCtrl == null) return;
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      _vCtrl!.pause();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _vCtrl?.pause();
    _vCtrl?.dispose();
    super.dispose();
  }

  void _onAdTap() {
    if (widget.post.id > 0) ref.read(communityRepoProvider).trackAdClick(widget.post.id);
    final url = widget.post.adCtaUrl;
    if (url != null && url.isNotEmpty) {
      final uri = url.startsWith('http') ? url : 'https://$url';
      launchUrl(Uri.parse(uri), mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.post;
    return VisibilityDetector(
      key: ValueKey('ad_${p.id}_${p.hashCode}'),
      onVisibilityChanged: (info) {
        _visible = info.visibleFraction > 0.5;
        if (_adUrl == null) return;
        if (_visible) {
          if (_vCtrl != null && _videoReady) {
            _vCtrl!.setVolume(_muted ? 0 : 1);
            if (!_vCtrl!.value.isPlaying) _vCtrl!.play();
          }
        } else {
          if (_vCtrl != null && _vCtrl!.value.isPlaying) _vCtrl!.pause();
        }
      },
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        color: context.colors.cardBg,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Header
          Padding(padding: const EdgeInsets.fromLTRB(14, 12, 14, 10), child: Row(children: [
            p.adPage?['avatar'] != null
                ? CircleNetImage(url: p.adPage!['avatar'], size: 40, fallbackText: p.adPage?['name'] ?? '')
                : Container(width: 40, height: 40, decoration: BoxDecoration(color: context.colors.chipBg, shape: BoxShape.circle),
                    child: const Icon(Icons.storefront_rounded, color: kOrange, size: 20)),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Flexible(child: Text(p.adPage?['name'] ?? 'Sponsored', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF1A1B2E)))),
                const SizedBox(width: 6),
                Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                  decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(3)),
                  child: const Text('Sponsored', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800))),
              ]),
              const SizedBox(height: 2),
              Text('Promoted', style: TextStyle(color: Colors.grey[400], fontSize: 11)),
            ])),
          ])),

          // Content
          if (p.content != null && p.content!.isNotEmpty) Padding(padding: const EdgeInsets.fromLTRB(14, 0, 14, 8),
            child: Text(p.content!, style: const TextStyle(color: Color(0xFF4B5563), fontSize: 14))),

          // Media
          GestureDetector(
            onTap: _onAdTap,
            child: p.adType == 'video'
                ? Container(
                    color: const Color(0xFF1A1B2E),
                    width: double.infinity,
                    height: 250,
                    child: Stack(children: [
                      if (_videoReady && _vCtrl != null)
                        Center(child: AspectRatio(
                          aspectRatio: _vCtrl!.value.aspectRatio.clamp(0.5, 2.5),
                          child: VideoPlayer(_vCtrl!)))
                      else if (p.adThumbnailUrl != null)
                        Center(child: NetImage(url: p.adThumbnailUrl!, fit: BoxFit.contain))
                      else
                        const Center(child: CircularProgressIndicator(color: kOrange, strokeWidth: 2)),
                      // Mute toggle
                      Positioned(bottom: 10, right: 10,
                        child: GestureDetector(
                          onTap: () { setState(() { _muted = !_muted; }); _vCtrl?.setVolume(_muted ? 0 : 1); },
                          child: Container(width: 30, height: 30,
                            decoration: BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
                            child: Icon(_muted ? Icons.volume_off_rounded : Icons.volume_up_rounded, color: Colors.white, size: 15)))),
                    ]))
                : (p.adMediaUrl != null
                    ? NetImage(url: p.adMediaUrl!, fit: BoxFit.cover, width: double.infinity)
                    : const SizedBox(height: 200))),

          // CTA bar
          if (p.adCtaText != null) GestureDetector(
            onTap: _onAdTap,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              color: context.colors.surfaceBg,
              child: Row(children: [
                Expanded(child: Text(p.adTitle ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF1A1B2E)),
                  maxLines: 1, overflow: TextOverflow.ellipsis)),
                const SizedBox(width: 10),
                Container(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(6)),
                  child: Text(p.adCtaText!, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13))),
              ]),
            ),
          ),

          // Action bar
          Padding(padding: const EdgeInsets.symmetric(vertical: 2),
            child: Row(children: [
              Expanded(child: TextButton.icon(onPressed: () {}, icon: const Icon(Icons.thumb_up_alt_outlined, size: 18), label: const Text('Like'),
                style: TextButton.styleFrom(foregroundColor: const Color(0xFF6B7280)))),
              Expanded(child: TextButton.icon(onPressed: () {}, icon: const Icon(Icons.chat_bubble_outline_rounded, size: 18), label: const Text('Comment'),
                style: TextButton.styleFrom(foregroundColor: const Color(0xFF6B7280)))),
              Expanded(child: TextButton.icon(onPressed: _onAdTap, icon: const Icon(Icons.share_outlined, size: 18), label: const Text('Share'),
                style: TextButton.styleFrom(foregroundColor: const Color(0xFF6B7280)))),
            ])),
        ]),
      ),
    );
  }
}

class _CreatePostBar extends ConsumerWidget {
  const _CreatePostBar();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final myProfile = ref.watch(communityMyProfileProvider);
    final avatar = myProfile.valueOrNull?.avatar;

    final c = context.colors;
    return Container(
      margin: const EdgeInsets.fromLTRB(12, 8, 12, 4),
      decoration: BoxDecoration(
        color: c.cardBg,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.07), blurRadius: 14, offset: const Offset(0, 3))],
      ),
      child: Column(
        children: [
          // Left orange accent bar
          Container(
            height: 3,
            decoration: const BoxDecoration(
              gradient: LinearGradient(colors: [kOrange, Color(0xFFFFB347)]),
              borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 4),
            child: Row(
              children: [
                CircleNetImage(url: avatar, size: 42),
                const SizedBox(width: 10),
                Expanded(
                  child: GestureDetector(
                    onTap: () async {
                      final post = await Navigator.push<CommunityPost>(
                        context,
                        MaterialPageRoute(builder: (_) => CreatePostScreen()),
                      );
                      if (post != null) ref.read(communityFeedProvider.notifier).prependPost(post);
                    },
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
                      decoration: BoxDecoration(
                        color: c.surfaceBg,
                        borderRadius: BorderRadius.circular(24),
                        border: Border.all(color: c.borderColor, width: 1),
                      ),
                      child: Text('Share something...',
                          style: TextStyle(color: c.subtleText, fontSize: 14, fontWeight: FontWeight.w400)),
                    ),
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(8, 4, 8, 10),
            child: Row(
              children: [
                _PostTypeBtn(icon: Icons.photo_library_rounded, label: 'Photo', color: const Color(0xFF34C759), type: 'image'),
                _PostTypeBtn(icon: Icons.videocam_rounded, label: 'Video', color: kOrange, type: 'video'),
                _PostTypeBtn(icon: Icons.bar_chart_rounded, label: 'Poll', color: const Color(0xFF8B5CF6), type: 'poll'),
                _PostTypeBtn(icon: Icons.emoji_emotions_rounded, label: 'Feeling', color: const Color(0xFFF59E0B), type: 'text'),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PostTypeBtn extends ConsumerWidget {
  final IconData icon;
  final String label;
  final Color color;
  final String type;
  const _PostTypeBtn({required this.icon, required this.label, required this.color, required this.type});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Expanded(
      child: GestureDetector(
        onTap: () async {
          final post = await Navigator.push<CommunityPost>(
            context,
            MaterialPageRoute(builder: (_) => CreatePostScreen(initialType: type)),
          );
          if (post != null) ref.read(communityFeedProvider.notifier).prependPost(post);
        },
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(width: 5),
            Text(label, style: TextStyle(color: context.colors.bodyText, fontSize: 12, fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }
}

class _EmptyFeed extends ConsumerWidget {
  const _EmptyFeed();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Padding(
      padding: const EdgeInsets.all(40),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 80, height: 80,
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: [Color(0xFFFFEDD5), Color(0xFFFFF7ED)], begin: Alignment.topLeft, end: Alignment.bottomRight),
            shape: BoxShape.circle,
            boxShadow: [BoxShadow(color: kOrange.withValues(alpha: 0.15), blurRadius: 20, spreadRadius: 2)],
          ),
          child: const Icon(Icons.dynamic_feed_rounded, size: 40, color: kOrange),
        ),
        const SizedBox(height: 16),
        const Text('Your feed is empty', style: TextStyle(color: Color(0xFF1A1B2E), fontSize: 18, fontWeight: FontWeight.w700)),
        const SizedBox(height: 6),
        const Text('Follow people to see their posts', style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 14)),
        const SizedBox(height: 20),
        ElevatedButton(
          style: ElevatedButton.styleFrom(
            backgroundColor: kOrange,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          ),
          onPressed: () => context.push('/community/explore'),
          child: const Text('Find People', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
        ),
      ]),
    );
  }
}

// â”€â”€ Post Card â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

class _PostCard extends ConsumerStatefulWidget {
  final CommunityPost post;
  final VoidCallback onDelete;
  const _PostCard({required this.post, required this.onDelete});

  @override
  ConsumerState<_PostCard> createState() => _PostCardState();
}

class _PostCardState extends ConsumerState<_PostCard> {
  bool _showReactions = false;
  String? _myReaction;
  String get _postChannel => 'community.post.${widget.post.id}';
  final Map<String, void Function(dynamic)> _realtimeListeners = {};

  static const _reactions = [
    {'type': 'like', 'emoji': '👍', 'color': Color(0xFF1877F2)},
    {'type': 'love', 'emoji': '❤️', 'color': Color(0xFFE41E3F)},
    {'type': 'haha', 'emoji': '😂', 'color': Color(0xFFF7B928)},
    {'type': 'wow', 'emoji': '😮', 'color': Color(0xFFF7B928)},
    {'type': 'sad', 'emoji': '😢', 'color': Color(0xFFF7B928)},
    {'type': 'angry', 'emoji': '😡', 'color': Color(0xFFE47820)},
  ];

  @override
  void initState() {
    super.initState();
    _myReaction = widget.post.userReaction;
    if (!widget.post.isAd) _subscribeRealtime();
  }

  @override
  void dispose() {
    for (final entry in _realtimeListeners.entries) {
      RealtimeClient.instance.removeListener(_postChannel, entry.key, entry.value);
    }
    super.dispose();
  }

  void _subscribeRealtime() {
    void on(String event, void Function(dynamic) handler) {
      _realtimeListeners[event] = handler;
      RealtimeClient.instance.listen(_postChannel, event, handler);
    }

    on('post.likes_changed', (data) {
      if (!mounted) return;
      setState(() => widget.post.likesCount = data['likes_count'] as int);
    });
    on('post.views_changed', (data) {
      if (!mounted) return;
      setState(() => widget.post.viewsCount = data['views_count'] as int);
    });
    on('post.comment_added', (data) {
      if (!mounted) return;
      setState(() => widget.post.commentsCount = data['comments_count'] as int);
    });
    on('post.comment_removed', (data) {
      if (!mounted) return;
      setState(() => widget.post.commentsCount = data['comments_count'] as int);
    });
    on('post.shares_changed', (data) {
      if (!mounted) return;
      setState(() => widget.post.sharesCount = data['shares_count'] as int);
    });
    on('post.saves_changed', (data) {
      if (!mounted) return;
      setState(() => widget.post.savesCount = data['saves_count'] as int);
    });
  }

  void _showEditDialog() {
    final ctrl = TextEditingController(text: widget.post.content ?? '');
    showDialog(context: context, builder: (ctx) => AlertDialog(
      title: const Text('Edit post', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
      content: TextField(controller: ctrl, maxLines: 5, decoration: const InputDecoration(hintText: 'Edit your post...', border: OutlineInputBorder())),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
        TextButton(onPressed: () async {
          Navigator.pop(ctx);
          try {
            final updated = await ref.read(communityRepoProvider).updatePost(widget.post.id, content: ctrl.text.trim());
            ref.read(communityFeedProvider.notifier).updatePost(updated);
          } catch (_) {}
        }, child: const Text('Save', style: TextStyle(color: kOrange, fontWeight: FontWeight.w700))),
      ],
    ));
  }

  String _reactionEmoji(String type) => {'like': '👍', 'love': '❤️', 'haha': '😂', 'wow': '😮', 'sad': '😢', 'angry': '😡'}[type] ?? 'Like';

  void _react(String type) async {
    setState(() {
      _myReaction = _myReaction == type ? null : type;
      _showReactions = false;
    });
    try {
      await ref.read(communityRepoProvider).reactToPost(widget.post.id, type);
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.post;

    // â”€â”€ Ad Card â”€â”€
    if (p.isAd) return _AdCard(post: p);

    final c = context.colors;
    return Container(
      margin: const EdgeInsets.fromLTRB(12, 0, 12, 12),
      decoration: BoxDecoration(
        color: c.cardBg,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 12, offset: const Offset(0, 2))],
      ),
      clipBehavior: Clip.hardEdge,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Header
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
          child: Row(children: [
            GestureDetector(
              onTap: () => p.pageId != null
                  ? Navigator.push(context, MaterialPageRoute(builder: (_) => BusinessPageDetailScreen(pageId: p.pageId!)))
                  : context.push('/community/profile/${p.user.id}'),
              child: CircleNetImage(url: p.user.avatar, size: 40, fallbackText: p.user.name),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: GestureDetector(
                onTap: () => p.pageId != null
                    ? Navigator.push(context, MaterialPageRoute(builder: (_) => BusinessPageDetailScreen(pageId: p.pageId!)))
                    : context.push('/community/profile/${p.user.id}'),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Text(p.user.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF1A1B2E))),
                    if (p.user.isVerified) ...[
                      const SizedBox(width: 4),
                      const Icon(Icons.verified_rounded, color: kOrange, size: 14),
                    ],
                  ]),
                  Row(children: [
                    const Icon(Icons.public_rounded, size: 12, color: Color(0xFF9CA3AF)),
                    const SizedBox(width: 3),
                    Text(timeago.format(p.createdAt), style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 12)),
                    if (p.location != null) ...[
                      const Text(' Â· ', style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 12)),
                      const Icon(Icons.location_on_rounded, size: 12, color: Color(0xFF9CA3AF)),
                      Text(p.location!, style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 12)),
                    ],
                  ]),
                ]),
              ),
            ),
            GestureDetector(
              onTap: () => _showOptions(context),
              child: const Icon(Icons.more_horiz_rounded, color: Color(0xFF6B7280)),
            ),
          ]),
        ),

        // Feeling
        if (p.feeling != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 6, 12, 0),
            child: Text('is feeling ${p.feeling}', style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13)),
          ),

        // Content with "See more"
        if (p.content != null && p.content!.isNotEmpty)
          _ExpandableText(text: p.content!),

        // Shared post preview
        if (p.type == 'share' && p.sharedPost != null)
          Container(
            margin: const EdgeInsets.fromLTRB(12, 4, 12, 8),
            decoration: BoxDecoration(border: Border.all(color: c.borderColor), borderRadius: BorderRadius.circular(12)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Padding(padding: const EdgeInsets.fromLTRB(12, 10, 12, 6), child: Row(children: [
                CircleNetImage(url: (p.sharedPost!['user'] as Map?)?['avatar'], size: 28, fallbackText: (p.sharedPost!['user'] as Map?)?['name'] ?? '?'),
                const SizedBox(width: 8),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text((p.sharedPost!['user'] as Map?)?['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF1A1B2E))),
                  Text(p.sharedPost!['created_at'] != null ? timeago.format(DateTime.tryParse('${p.sharedPost!['created_at']}') ?? DateTime.now()) : '', style: const TextStyle(fontSize: 11, color: Color(0xFF9CA3AF))),
                ])),
              ])),
              if (p.sharedPost!['content'] != null)
                Padding(padding: const EdgeInsets.fromLTRB(12, 0, 12, 8), child: Text('${p.sharedPost!['content']}', style: const TextStyle(fontSize: 14, color: Color(0xFF374151)))),
              if (p.sharedPost!['media'] is List && (p.sharedPost!['media'] as List).isNotEmpty)
                ClipRRect(borderRadius: const BorderRadius.vertical(bottom: Radius.circular(12)),
                  child: () {
                    final m = (p.sharedPost!['media'] as List).first;
                    final mType = m['type'] ?? 'image';
                    final mUrl = m['url'] ?? '';
                    final mThumb = m['thumbnail'];
                    if (mType == 'video') {
                      return GestureDetector(
                        onTap: () => Navigator.push(context, MaterialPageRoute(
                          builder: (_) => _SimpleVideoPlayer(url: mUrl))),
                        child: Stack(children: [
                          NetImage(url: mThumb ?? mUrl, fit: BoxFit.cover, width: double.infinity, height: 200),
                          const Positioned.fill(child: Center(child: Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 56))),
                        ]),
                      );
                    }
                    return NetImage(url: mUrl, fit: BoxFit.cover, width: double.infinity);
                  }()),
            ]),
          ),

        // Media
        if (p.media.isNotEmpty) _MediaGrid(media: p.media, postId: p.id, isOwner: p.user.isMe),

        // Poll
        if (p.type == 'poll' && p.pollOptions.isNotEmpty)
          Padding(padding: const EdgeInsets.fromLTRB(12, 4, 12, 8),
            child: Column(children: p.pollOptions.asMap().entries.map((e) {
              final opt = e.value;
              final totalVotes = p.pollOptions.fold<int>(0, (s, o) => s + o.votes);
              final pct = totalVotes > 0 ? (opt.votes / totalVotes * 100).round() : 0;
              return GestureDetector(
                onTap: () async {
                  try {
                    await ref.read(communityRepoProvider).votePoll(p.id, e.key);
                    setState(() => opt.votes++);
                  } catch (_) {}
                },
                child: Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(10), border: Border.all(color: c.borderColor)),
                  child: Stack(children: [
                    FractionallySizedBox(widthFactor: pct / 100, child: Container(
                      height: 44, decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(9)))),
                    Container(height: 44, padding: const EdgeInsets.symmetric(horizontal: 14),
                      child: Row(children: [
                        Expanded(child: Text(opt.text, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: Color(0xFF1A1B2E)))),
                        Text('$pct%', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: kOrange)),
                      ])),
                  ]),
                ),
              );
            }).toList()),
          ),

        // Engagement counts — compact pill style
        if (p.likesCount > 0 || p.commentsCount > 0 || p.sharesCount > 0 || p.viewsCount > 0)
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 2),
            child: Row(children: [
              if (p.likesCount > 0) _EngagementChip(emoji: '👍', count: p.likesCount),
              if (p.commentsCount > 0) _EngagementChip(emoji: '💬', count: p.commentsCount),
              if (p.sharesCount > 0) _EngagementChip(emoji: '↗', count: p.sharesCount),
              if (p.viewsCount > 0) _EngagementChip(emoji: '👁', count: p.viewsCount),
            ]),
          ),

        const Divider(height: 1, thickness: 1, color: Color(0xFFF2F4F7)),

        // Action buttons
        Padding(
          padding: const EdgeInsets.fromLTRB(6, 4, 6, 6),
          child: Row(children: [
            _ActionBtn(
              icon: _myReaction != null ? Icons.thumb_up_rounded : Icons.thumb_up_alt_outlined,
              label: _myReaction != null ? _reactionEmoji(_myReaction!) : 'Like',
              color: _myReaction != null ? kOrange : const Color(0xFF8A94A6),
              active: _myReaction != null,
              onTap: () => setState(() => _showReactions = !_showReactions),
              onLongPress: () => _react('like'),
            ),
            _ActionBtn(icon: Icons.mode_comment_outlined, label: 'Comment', color: const Color(0xFF8A94A6), onTap: () => showCommentsSheet(context, p.id, initialCount: p.commentsCount)),
            _ActionBtn(icon: Icons.reply_rounded, label: 'Share', color: const Color(0xFF8A94A6), onTap: () => _showShareDialog()),
          ]),
        ),

        // Reaction picker â€” shown above action buttons
        if (_showReactions)
          Container(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
            child: _ReactionPicker(reactions: _reactions, onPick: _react, onDismiss: () => setState(() => _showReactions = false)),
          ),
      ]),
    );
  }

  void _showShareDialog() {
    final ctrl = TextEditingController();
    showDialog(context: context, builder: (ctx) => AlertDialog(
      title: const Text('Share post', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(padding: const EdgeInsets.all(10), decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(8)),
          child: Row(children: [
            CircleNetImage(url: widget.post.user.avatar, size: 28, fallbackText: widget.post.user.name),
            const SizedBox(width: 8),
            Expanded(child: Text(widget.post.content ?? 'Post by ${widget.post.user.name}', style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280)), maxLines: 2, overflow: TextOverflow.ellipsis)),
          ])),
        const SizedBox(height: 12),
        TextField(controller: ctrl, maxLines: 3, decoration: const InputDecoration(hintText: 'Add your thoughts...', border: OutlineInputBorder())),
      ]),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
        TextButton(onPressed: () async {
          Navigator.pop(ctx);
          try {
            final shared = await ref.read(communityRepoProvider).sharePost(widget.post.id, content: ctrl.text.trim().isEmpty ? null : ctrl.text.trim());
            ref.read(communityFeedProvider.notifier).prependPost(shared);
            if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Shared!'), backgroundColor: Color(0xFF10B981)));
          } catch (_) {}
        }, child: const Text('Share', style: TextStyle(color: kOrange, fontWeight: FontWeight.w700))),
      ],
    ));
  }

  void _showBoostDialog() {
    double budget = 5;
    int hours = 24;
    showDialog(context: context, builder: (ctx) => StatefulBuilder(
      builder: (ctx, setD) => AlertDialog(
        title: Row(children: [
          const Icon(Icons.rocket_launch_rounded, color: kOrange, size: 22),
          const SizedBox(width: 8),
          const Text('Boost Post', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
        ]),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          const Text('Promote your post to reach more people', style: TextStyle(color: Color(0xFF6B7280), fontSize: 13)),
          const SizedBox(height: 16),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            const Text('Budget', style: TextStyle(fontWeight: FontWeight.w600)),
            Text('\$${budget.toStringAsFixed(0)}', style: const TextStyle(fontWeight: FontWeight.w800, color: kOrange, fontSize: 18)),
          ]),
          Slider(value: budget, min: 1, max: 100, divisions: 20, activeColor: kOrange,
            onChanged: (v) => setD(() => budget = v)),
          const SizedBox(height: 8),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            const Text('Duration', style: TextStyle(fontWeight: FontWeight.w600)),
            Text('${hours}h', style: const TextStyle(fontWeight: FontWeight.w800, color: kOrange)),
          ]),
          Slider(value: hours.toDouble(), min: 1, max: 168, divisions: 7, activeColor: kOrange,
            onChanged: (v) => setD(() => hours = v.toInt())),
          const SizedBox(height: 8),
          Container(padding: const EdgeInsets.all(10), decoration: BoxDecoration(
            color: kOrange.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(8)),
            child: Row(children: [
              const Icon(Icons.people_rounded, color: kOrange, size: 18),
              const SizedBox(width: 8),
              Text('Est. ${(budget * 50).toInt()} - ${(budget * 150).toInt()} people',
                style: const TextStyle(fontSize: 13, color: Color(0xFF374151))),
            ])),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: kOrange),
            onPressed: () async {
              Navigator.pop(ctx);
              try {
                await ref.read(communityRepoProvider).boostPost(widget.post.id, budget: budget, durationHours: hours);
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Post boosted!'), backgroundColor: Color(0xFF10B981)));
              } catch (e) {
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red));
              }
            },
            child: const Text('Boost Now', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    ));
  }

  void _showOptions(BuildContext context) {
    showModalBottomSheet(
      context: context,
      builder: (_) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          if (widget.post.user.isMe) ListTile(
            leading: const Icon(Icons.edit_rounded, color: kOrange),
            title: const Text('Edit Post'),
            onTap: () { Navigator.pop(context); _showEditDialog(); },
          ),
          if (widget.post.user.isMe) ListTile(
            leading: const Icon(Icons.delete_rounded, color: Colors.red),
            title: const Text('Delete Post', style: TextStyle(color: Colors.red)),
            onTap: () {
              Navigator.pop(context);
              widget.onDelete();
            },
          ),
          if (widget.post.user.isMe) ListTile(
            leading: const Icon(Icons.rocket_launch_rounded, color: kOrange),
            title: const Text('Boost Post'),
            subtitle: const Text('Promote to more people', style: TextStyle(fontSize: 12, color: Color(0xFF9CA3AF))),
            onTap: () { Navigator.pop(context); _showBoostDialog(); },
          ),
          if (!widget.post.user.isMe) ListTile(
            leading: const Icon(Icons.visibility_off_rounded, color: Color(0xFF6B7280)),
            title: const Text('Not interested'),
            subtitle: const Text('See fewer posts like this', style: TextStyle(fontSize: 12, color: Color(0xFF9CA3AF))),
            onTap: () {
              Navigator.pop(context);
              ref.read(communityRepoProvider).trackInteraction(widget.post.id, 'skip');
              ref.read(communityFeedProvider.notifier).removePost(widget.post.id);
            },
          ),
          ListTile(
            leading: const Icon(Icons.flag_rounded),
            title: const Text('Report Post'),
            onTap: () => Navigator.pop(context),
          ),
        ]),
      ),
    );
  }
}

class _ExpandableText extends StatefulWidget {
  final String text;
  const _ExpandableText({required this.text});
  @override
  State<_ExpandableText> createState() => _ExpandableTextState();
}

class _ExpandableTextState extends State<_ExpandableText> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(widget.text,
          style: const TextStyle(color: Color(0xFF1A1B2E), fontSize: 15, height: 1.4),
          maxLines: _expanded ? null : 3,
          overflow: _expanded ? null : TextOverflow.ellipsis),
        if (!_expanded && _isLongText())
          GestureDetector(
            onTap: () => setState(() => _expanded = true),
            child: const Padding(
              padding: EdgeInsets.only(top: 4),
              child: Text('See more', style: TextStyle(color: Color(0xFF6B7280), fontWeight: FontWeight.w700, fontSize: 14)),
            ),
          ),
      ]),
    );
  }

  bool _isLongText() {
    final tp = TextPainter(
      text: TextSpan(text: widget.text, style: const TextStyle(fontSize: 15, height: 1.4)),
      maxLines: 3,
      textDirection: TextDirection.ltr,
    )..layout(maxWidth: MediaQuery.of(context).size.width - 24);
    return tp.didExceedMaxLines;
  }
}

class _EngagementChip extends StatelessWidget {
  final String emoji;
  final int count;
  const _EngagementChip({required this.emoji, required this.count});

  @override
  Widget build(BuildContext context) {
    final label = count >= 1000 ? '${(count / 1000).toStringAsFixed(1)}K' : '$count';
    return Padding(
      padding: const EdgeInsets.only(right: 10),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Text(emoji, style: const TextStyle(fontSize: 13)),
        const SizedBox(width: 3),
        Text(label, style: const TextStyle(color: Color(0xFF8A94A6), fontSize: 12, fontWeight: FontWeight.w600)),
      ]),
    );
  }
}

class _ActionBtn extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final bool active;
  final VoidCallback onTap;
  final VoidCallback? onLongPress;
  const _ActionBtn({required this.icon, required this.label, required this.color, required this.onTap, this.active = false, this.onLongPress});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        onLongPress: onLongPress,
        child: Container(
          margin: const EdgeInsets.symmetric(horizontal: 3, vertical: 4),
          padding: const EdgeInsets.symmetric(vertical: 7),
          decoration: active
              ? BoxDecoration(color: kOrange.withValues(alpha: 0.10), borderRadius: BorderRadius.circular(10))
              : null,
          child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(icon, color: color, size: 20),
            const SizedBox(width: 5),
            Text(label, style: TextStyle(color: color, fontSize: 13, fontWeight: FontWeight.w600)),
          ]),
        ),
      ),
    );
  }
}

class _ReactionPicker extends StatefulWidget {
  final List<Map<String, dynamic>> reactions;
  final void Function(String) onPick;
  final VoidCallback onDismiss;
  const _ReactionPicker({required this.reactions, required this.onPick, required this.onDismiss});
  @override
  State<_ReactionPicker> createState() => _ReactionPickerState();
}

class _ReactionPickerState extends State<_ReactionPicker> with SingleTickerProviderStateMixin {
  late AnimationController _animCtrl;
  late Animation<double> _scaleAnim;

  @override
  void initState() {
    super.initState();
    _animCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 300));
    _scaleAnim = CurvedAnimation(parent: _animCtrl, curve: Curves.elasticOut);
    _animCtrl.forward();
  }

  @override
  void dispose() { _animCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return ScaleTransition(
      scale: _scaleAnim,
      alignment: Alignment.bottomLeft,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
        decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(30),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 16, offset: const Offset(0, 4))]),
        child: Row(mainAxisSize: MainAxisSize.min,
          children: widget.reactions.asMap().entries.map((e) {
            final r = e.value;
            return _AnimatedReactionEmoji(
              emoji: r['emoji'] as String, delay: e.key * 50,
              onTap: () => widget.onPick(r['type'] as String));
          }).toList()),
      ),
    );
  }
}

class _AnimatedReactionEmoji extends StatefulWidget {
  final String emoji;
  final int delay;
  final VoidCallback onTap;
  const _AnimatedReactionEmoji({required this.emoji, required this.delay, required this.onTap});
  @override
  State<_AnimatedReactionEmoji> createState() => _AnimatedReactionEmojiState();
}

class _AnimatedReactionEmojiState extends State<_AnimatedReactionEmoji> with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  double _scale = 1.0;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 400));
    Future.delayed(Duration(milliseconds: widget.delay), () { if (mounted) _ctrl.forward(); });
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: CurvedAnimation(parent: _ctrl, curve: Curves.easeOut),
      child: SlideTransition(
        position: Tween(begin: const Offset(0, 0.5), end: Offset.zero).animate(CurvedAnimation(parent: _ctrl, curve: Curves.elasticOut)),
        child: GestureDetector(
          onTapDown: (_) => setState(() => _scale = 1.4),
          onTapUp: (_) { setState(() => _scale = 1.0); widget.onTap(); },
          onTapCancel: () => setState(() => _scale = 1.0),
          child: AnimatedScale(scale: _scale, duration: const Duration(milliseconds: 150), curve: Curves.elasticOut,
            child: Padding(padding: const EdgeInsets.symmetric(horizontal: 5),
              child: Text(widget.emoji, style: const TextStyle(fontSize: 30)))),
        ),
      ),
    );
  }
}

// â”€â”€ Businesses Tab â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

class _BusinessesTab extends StatelessWidget {
  const _BusinessesTab();

  @override
  Widget build(BuildContext context) => const BusinessPagesListWidget();
}

// People you may know — Facebook-style horizontal cards
class _PeopleYouMayKnow extends ConsumerWidget {
  final List<CommunityUser> users;
  const _PeopleYouMayKnow({required this.users});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (users.isEmpty) return const SizedBox.shrink();
    return Container(
      color: context.colors.cardBg,
      margin: const EdgeInsets.only(bottom: 8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
          child: Row(children: [
            const Icon(Icons.people_alt_rounded, color: kOrange, size: 20),
            const SizedBox(width: 8),
            const Text('People you may know', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF1A1B2E))),
          ])),
        SizedBox(height: 220,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 10),
            itemCount: users.length,
            itemBuilder: (_, i) => _SuggestionCard(user: users[i]),
          )),
        const SizedBox(height: 8),
      ]),
    );
  }
}

class _SuggestionCard extends ConsumerStatefulWidget {
  final CommunityUser user;
  const _SuggestionCard({required this.user});
  @override
  ConsumerState<_SuggestionCard> createState() => _SuggestionCardState();
}

class _SuggestionCardState extends ConsumerState<_SuggestionCard> {
  late bool _following;
  bool _removed = false;

  @override
  void initState() { super.initState(); _following = widget.user.isFollowing; }

  @override
  Widget build(BuildContext context) {
    if (_removed) return const SizedBox.shrink();
    final u = widget.user;
    return Container(
      width: 160, margin: const EdgeInsets.only(right: 8),
      decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(14),
        border: Border.all(color: context.colors.borderColor)),
      clipBehavior: Clip.antiAlias,
      child: Column(children: [
        // Cover/avatar area
        Stack(children: [
          GestureDetector(
            onTap: () => context.push('/community/profile/${u.id}'),
            child: SizedBox(height: 100, width: double.infinity,
              child: u.avatar != null
                ? NetImage(url: u.avatar!, fit: BoxFit.cover)
                : Container(color: kOrange.withValues(alpha: 0.1),
                    child: Center(child: Text(u.name[0].toUpperCase(),
                      style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w800, color: kOrange)))))),
          Positioned(top: 4, right: 4,
            child: GestureDetector(onTap: () => setState(() => _removed = true),
              child: Container(width: 24, height: 24,
                decoration: BoxDecoration(color: Colors.black38, shape: BoxShape.circle),
                child: const Icon(Icons.close, size: 14, color: Colors.white)))),
        ]),
        Padding(padding: const EdgeInsets.fromLTRB(8, 8, 8, 4),
          child: Text(u.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF1A1B2E)),
            maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center)),
        Text('${u.followersCount} followers', style: const TextStyle(fontSize: 11, color: Color(0xFF9CA3AF))),
        const Spacer(),
        Padding(padding: const EdgeInsets.fromLTRB(10, 0, 10, 10),
          child: SizedBox(width: double.infinity,
            child: ElevatedButton(
              onPressed: () async {
                setState(() => _following = !_following);
                try { await ref.read(communityRepoProvider).toggleFollow(u.id); } catch (_) { setState(() => _following = !_following); }
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: _following ? context.colors.chipBg : kOrange,
                foregroundColor: _following ? context.colors.mutedText : Colors.white,
                elevation: 0, padding: const EdgeInsets.symmetric(vertical: 8),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
              child: Text(_following ? 'Following' : 'Follow', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
            ))),
      ]),
    );
  }
}

// Reels carousel — Facebook-style horizontal preview in feed
class _ReelsCarousel extends ConsumerWidget {
  final List<CommunityPost> reels;
  const _ReelsCarousel({required this.reels});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (reels.isEmpty) return const SizedBox.shrink();
    return Container(
      color: context.colors.cardBg,
      margin: const EdgeInsets.only(bottom: 8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
          child: Row(children: [
            const Icon(Icons.play_circle_filled_rounded, color: kOrange, size: 20),
            const SizedBox(width: 8),
            const Text('Reels', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF1A1B2E))),
            const Spacer(),
            GestureDetector(
              onTap: () {
                ref.read(communityNavIndexProvider.notifier).state = 1;
              },
              child: const Text('See all', style: TextStyle(color: kOrange, fontWeight: FontWeight.w700, fontSize: 13))),
          ])),
        SizedBox(height: 200,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 10),
            itemCount: reels.length,
            itemBuilder: (_, i) {
              final reel = reels[i];
              final media = reel.media.isNotEmpty ? reel.media.first : null;
              final thumb = media?.thumbnail ?? media?.url;
              return GestureDetector(
                onTap: () {
                  ref.read(communityNavIndexProvider.notifier).state = 1;
                },
                child: Container(
                  width: 120, margin: const EdgeInsets.only(right: 8),
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(14), color: const Color(0xFF1A1B2E)),
                  clipBehavior: Clip.antiAlias,
                  child: Stack(fit: StackFit.expand, children: [
                    if (thumb != null) NetImage(url: thumb, fit: BoxFit.cover),
                    Container(decoration: BoxDecoration(gradient: LinearGradient(
                      begin: Alignment.topCenter, end: Alignment.bottomCenter,
                      colors: [Colors.transparent, Colors.black.withValues(alpha: 0.6)]))),
                    const Center(child: Icon(Icons.play_circle_outline_rounded, color: Colors.white70, size: 36)),
                    Positioned(bottom: 8, left: 8, right: 8,
                      child: Row(children: [
                        CircleNetImage(url: reel.user.avatar, size: 22, fallbackText: reel.user.name),
                        const SizedBox(width: 6),
                        Expanded(child: Text(reel.user.name, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600),
                          maxLines: 1, overflow: TextOverflow.ellipsis)),
                      ])),
                    if (reel.viewsCount > 0) Positioned(top: 6, right: 6,
                      child: Container(padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                        decoration: BoxDecoration(color: Colors.black45, borderRadius: BorderRadius.circular(4)),
                        child: Text(reel.viewsCount >= 1000 ? '${(reel.viewsCount / 1000).toStringAsFixed(1)}K' : '${reel.viewsCount}',
                          style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600)))),
                  ]),
                ),
              );
            },
          )),
        const SizedBox(height: 8),
      ]),
    );
  }
}

class _MediaGrid extends StatelessWidget {
  final List<CommunityPostMedia> media;
  final int? postId;
  final bool isOwner;
  const _MediaGrid({required this.media, this.postId, this.isOwner = false});

  void _openGallery(BuildContext context, int index) {
    final images = media.where((m) => m.type == 'image').toList();
    if (images.isEmpty) return;
    Navigator.push(context, MaterialPageRoute(builder: (_) => _ImageGalleryScreen(images: images, initialIndex: index)));
  }

  @override
  Widget build(BuildContext context) {
    if (media.length == 1) {
      final m = media[0];
      return GestureDetector(
        onTap: m.type == 'image' ? () => _openGallery(context, 0) : null,
        child: _MediaItem(m: m, height: m.type == 'video' ? 0 : 0, postId: postId, isOwner: isOwner));
    }
    if (media.length == 2) {
      return SizedBox(height: 200, child: Row(children: [
        for (var i = 0; i < 2; i++)
          Expanded(child: Padding(padding: EdgeInsets.only(right: i == 0 ? 2 : 0),
            child: GestureDetector(
              onTap: media[i].type == 'image' ? () => _openGallery(context, i) : null,
              child: _MediaItem(m: media[i], height: 200, postId: postId, isOwner: isOwner)))),
      ]));
    }
    final extra = media.length - 3;
    return Column(children: [
      GestureDetector(
        onTap: media[0].type == 'image' ? () => _openGallery(context, 0) : null,
        child: _MediaItem(m: media[0], height: 220, postId: postId)),
      const SizedBox(height: 2),
      SizedBox(height: 120, child: Row(children: [
        Expanded(child: GestureDetector(
          onTap: media[1].type == 'image' ? () => _openGallery(context, 1) : null,
          child: _MediaItem(m: media[1], height: 120, postId: postId, isOwner: isOwner))),
        const SizedBox(width: 2),
        Expanded(child: GestureDetector(
          onTap: () => _openGallery(context, 2),
          child: Stack(children: [
            _MediaItem(m: media[2], height: 120, postId: postId, isOwner: isOwner),
            if (extra > 0) Positioned.fill(child: Container(
              color: Colors.black45,
              child: Center(child: Text('+$extra', style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800))),
            )),
          ]))),
      ])),
    ]);
  }
}

// ── Transcoding placeholder ────────────────────────────────────────────────────
// Shown while the backend is transcoding a freshly-uploaded video.
// Polls /community/media/{id}/transcoding-status every 8 seconds until ready.
// Also listens for the realtime post.media_ready event to avoid polling lag.

class _TranscodingPlaceholder extends ConsumerStatefulWidget {
  final int mediaId;
  final int progress;
  final String? thumbnail;
  final bool isOwner;
  const _TranscodingPlaceholder({required this.mediaId, required this.progress, this.thumbnail, this.isOwner = false});
  @override
  ConsumerState<_TranscodingPlaceholder> createState() => _TranscodingPlaceholderState();
}

class _TranscodingPlaceholderState extends ConsumerState<_TranscodingPlaceholder> {
  int _progress = 0;
  bool _done = false;
  Timer? _pollTimer;

  @override
  void initState() {
    super.initState();
    _progress = widget.progress;
    // Only the post owner polls for status — other users never see the post
    // while it's transcoding (video_ready=false filters it from their feed).
    if (widget.isOwner) _startPolling();
  }

  void _startPolling() {
    _pollTimer = Timer.periodic(const Duration(seconds: 8), (_) => _poll());
    // Also fire immediately after a short delay (don't block initState)
    Future.delayed(const Duration(seconds: 2), _poll);
  }

  Future<void> _poll() async {
    if (!mounted || _done) return;
    try {
      final data = await ref.read(communityRepoProvider).getTranscodingStatus(widget.mediaId);
      if (!mounted) return;
      final status = data['transcoding_status'] as String? ?? 'pending';
      final pct    = data['transcoding_progress'] as int? ?? _progress;
      if (status == 'ready' || status == 'failed') {
        _done = true;
        _pollTimer?.cancel();
        ref.invalidate(communityFeedProvider);
        if (!mounted) return;
        final isReady = status == 'ready';
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Row(children: [
            Icon(isReady ? Icons.check_circle_rounded : Icons.error_outline_rounded,
                color: Colors.white, size: 20),
            const SizedBox(width: 10),
            Text(isReady ? 'Muuqaalkaagu waa diyaar!' : 'Processing-ku wuu fashilmay',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
          ]),
          backgroundColor: isReady ? Colors.green.shade700 : Colors.red.shade700,
          duration: const Duration(seconds: 4),
          behavior: SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ));
      } else {
        setState(() => _progress = pct);
      }
    } catch (_) {}
  }

  @override
  void dispose() {
    _pollTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(children: [
      // Show thumbnail if available while processing
      if (widget.thumbnail != null)
        SizedBox(
          height: 220, width: double.infinity,
          child: NetImage(url: widget.thumbnail!, fit: BoxFit.cover,
            placeholder: Container(color: const Color(0xFF1A1B2E)),
            errorWidget: Container(color: const Color(0xFF1A1B2E))),
        )
      else
        Container(height: 220, color: const Color(0xFF1A1B2E)),

      // Dark overlay
      Positioned.fill(child: Container(color: Colors.black.withValues(alpha: 0.55))),

      // Processing indicator
      Positioned.fill(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        SizedBox(
          width: 56, height: 56,
          child: CircularProgressIndicator(
            value: _progress > 0 ? _progress / 100 : null,
            strokeWidth: 3,
            color: kOrange,
            backgroundColor: Colors.white24,
          ),
        ),
        const SizedBox(height: 10),
        Text(
          _progress > 0 ? 'Processing... $_progress%' : 'Processing video...',
          style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
        ),
        const SizedBox(height: 4),
        const Text('Will be ready shortly', style: TextStyle(color: Colors.white60, fontSize: 11)),
      ])),
    ]);
  }
}

class _SimpleVideoPlayer extends StatefulWidget {
  final String url;
  const _SimpleVideoPlayer({required this.url});
  @override
  State<_SimpleVideoPlayer> createState() => _SimpleVideoPlayerState();
}

class _SimpleVideoPlayerState extends State<_SimpleVideoPlayer> {
  VideoPlayerController? _ctrl;
  bool _ready = false;
  final _pool = VideoPool.feed;

  @override
  void initState() {
    super.initState();
    _initFromPool();
  }

  void _initFromPool() async {
    final cached = _pool.controller(widget.url);
    if (cached != null) {
      if (mounted) {
        setState(() { _ctrl = cached; _ready = true; });
        _pool.play(widget.url);
      }
      return;
    }
    final ctrl = await _pool.preload(widget.url);
    if (ctrl != null && mounted) {
      setState(() { _ctrl = ctrl; _ready = true; });
      _pool.play(widget.url);
    }
  }

  @override
  void dispose() {
    if (widget.url.isNotEmpty) _pool.pause(widget.url);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      extendBodyBehindAppBar: true,
      body: GestureDetector(
        onTap: _ready && _ctrl != null ? () { _ctrl!.value.isPlaying ? _ctrl!.pause() : _ctrl!.play(); setState(() {}); } : null,
        onVerticalDragEnd: (d) { if (d.primaryVelocity != null && d.primaryVelocity! > 300) Navigator.pop(context); },
        child: _ready && _ctrl != null
          ? Stack(fit: StackFit.expand, children: [
              Center(child: AspectRatio(aspectRatio: _ctrl!.value.aspectRatio, child: VideoPlayer(_ctrl!))),
              if (!_ctrl!.value.isPlaying)
                const Center(child: Icon(Icons.play_circle_fill_rounded, color: Colors.white70, size: 64)),
              Positioned(bottom: 30, left: 16, right: 16,
                child: VideoProgressIndicator(_ctrl!, allowScrubbing: true,
                  colors: const VideoProgressColors(playedColor: kOrange, bufferedColor: Colors.white30, backgroundColor: Colors.white12))),
              Positioned(top: MediaQuery.of(context).padding.top + 8, left: 8,
                child: GestureDetector(onTap: () => Navigator.pop(context),
                  child: Container(padding: const EdgeInsets.all(8),
                    decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.4), shape: BoxShape.circle),
                    child: const Icon(Icons.close_rounded, color: Colors.white, size: 22)))),
            ])
          : const Center(child: CircularProgressIndicator(color: kOrange)),
      ),
    );
  }
}

class _ImageGalleryScreen extends StatelessWidget {
  final List<CommunityPostMedia> images;
  final int initialIndex;
  const _ImageGalleryScreen({required this.images, required this.initialIndex});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(backgroundColor: Colors.black, foregroundColor: Colors.white,
        title: Text('${initialIndex + 1} / ${images.length}', style: const TextStyle(fontSize: 16))),
      body: PageView.builder(
        controller: PageController(initialPage: initialIndex),
        itemCount: images.length,
        itemBuilder: (_, i) => InteractiveViewer(
          child: Center(child: NetImage(url: images[i].url, fit: BoxFit.contain)),
        ),
      ),
    );
  }
}

class _MediaItem extends ConsumerStatefulWidget {
  final CommunityPostMedia m;
  final double height;
  final int? postId;
  final bool isOwner;
  const _MediaItem({required this.m, required this.height, this.postId, this.isOwner = false});

  @override
  ConsumerState<_MediaItem> createState() => _MediaItemState();
}

class _MediaItemState extends ConsumerState<_MediaItem> with WidgetsBindingObserver {
  VideoPlayerController? _ctrl;
  bool _ready = false;
  bool _paused = false;
  bool _visible = false;
  final _key = UniqueKey();
  final _pool = VideoPool.feed;
  DateTime? _watchStart;

  bool get _isVideo => widget.m.type == 'video';
  bool get _isAudio => widget.m.type == 'audio';
  bool get _isDocument => widget.m.type == 'document';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    // Start preloading immediately on widget build.
    // VideoPool caps at 12 slots and evicts by distance-from-active,
    // so this is safe even when many video widgets are built at once.
    if (_isVideo) {
      final mp4 = _mp4Url;
      if (mp4.isNotEmpty && !_pool.isReady(mp4) && !_pool.isLoading(mp4)) {
        _pool.preload(mp4);
      }
    }
  }

  bool _lifecyclePaused = false;

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused) {
      if (!_lifecyclePaused) {
        _lifecyclePaused = true;
        if (_ctrl != null && _ready) _pool.pause(_videoUrl);
      }
    } else if (state == AppLifecycleState.inactive) {
      if (!_lifecyclePaused) {
        _lifecyclePaused = true;
        if (_ctrl != null && _ready) _pool.pause(_videoUrl);
      }
    } else if (state == AppLifecycleState.resumed) {
      _lifecyclePaused = false;
      if (_visible && !_paused && _ctrl != null && _ready) {
        _pool.reactivate(_videoUrl);
      }
    }
  }

  // Prefer direct nginx MP4 (1 RTT). Fall back to HLS if MP4 fails.
  String get _mp4Url => widget.m.mp4DirectUrl;
  String? get _hlsUrl => widget.m.hlsUrl;

  // Tracks whichever URL actually loaded successfully.
  String _resolvedUrl = '';
  String get _videoUrl => _resolvedUrl.isNotEmpty ? _resolvedUrl : _mp4Url;

  Future<void> _initVideo() async {
    final mp4 = _mp4Url;
    if (mp4.isEmpty) return;

    // Fast path — pool already has it (either MP4 or HLS)
    var cached = _pool.controller(mp4);
    String url = mp4;
    if (cached == null && _hlsUrl != null && _hlsUrl != mp4) {
      cached = _pool.controller(_hlsUrl!);
      if (cached != null) url = _hlsUrl!;
    }
    if (cached != null && mounted) {
      final c = cached;
      _resolvedUrl = url;
      setState(() { _ctrl = c; _ready = true; });
      c.addListener(_onControllerUpdate);
      if (_visible && !_paused) _pool.play(url);
      return;
    }

    // Slow path: load from network
    var ctrl = await _pool.preload(mp4);
    url = mp4;
    if (ctrl == null && _hlsUrl != null && _hlsUrl != mp4) {
      debugPrint('[Feed] MP4 failed, trying HLS: ${_hlsUrl!}');
      url = _hlsUrl!;
      ctrl = await _pool.preload(url);
    }

    if (ctrl != null && mounted) {
      final c = ctrl;
      _resolvedUrl = url;
      setState(() { _ctrl = c; _ready = true; });
      c.addListener(_onControllerUpdate);
      if (_visible && !_paused) _pool.play(url);
    }
  }

  void _onControllerUpdate() {
    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    _ctrl?.removeListener(_onControllerUpdate);
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  void _onVisibilityChanged(VisibilityInfo info) {
    final fraction = info.visibleFraction;

    // Start loading as soon as any part of the card is visible (5%) —
    // gives the HLS stream ~1-2s head start before the user reaches it.
    if (fraction > 0.05 && _isVideo && !_ready) {
      _initVideo();
    }

    _visible = fraction > 0.5;
    if (_visible) {
      if (_ready && _ctrl != null && !_paused) {
        _pool.play(_videoUrl);
        _watchStart ??= DateTime.now();
      }
      // Shift preload window to ±2 around this video in the feed.
      _pool.setActiveUrl(_videoUrl);
    } else {
      if (_ctrl != null && _ctrl!.value.isPlaying) _pool.pause(_videoUrl);
      if (_watchStart != null && _isVideo) {
        final ms = DateTime.now().difference(_watchStart!).inMilliseconds;
        if (ms > 1000 && widget.postId != null) {
          ref.read(communityRepoProvider).trackInteraction(
            widget.postId!, 'watch', durationMs: ms);
        }
        _watchStart = null;
      }
    }
  }

  void _togglePause() {
    if (!_ready || _ctrl == null) return;
    setState(() => _paused = !_paused);
    _paused ? _pool.pause(_videoUrl) : _pool.play(_videoUrl);
  }

  String _formatDuration(Duration d) {
    final m = d.inMinutes;
    final s = d.inSeconds % 60;
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    if (_isAudio) return _AudioPlayerCard(url: widget.m.url);
    if (_isDocument) return _DocumentCard(url: widget.m.url);

    // Video is still being transcoded.
    // Non-owners never receive transcoding posts (video_ready=false filters them).
    // As an extra safety layer, render nothing for non-owners.
    if (_isVideo && widget.m.isTranscoding) {
      if (!widget.isOwner) return const SizedBox.shrink();
      return _TranscodingPlaceholder(
        mediaId: widget.m.id,
        progress: widget.m.transcodingProgress,
        thumbnail: widget.m.thumbnail,
        isOwner: true,
      );
    }
    if (_isVideo && widget.m.transcodingFailed) {
      return Container(
        height: 220, color: const Color(0xFF1A1B2E),
        child: const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.error_outline_rounded, color: Colors.white54, size: 36),
          SizedBox(height: 8),
          Text('Video processing failed', style: TextStyle(color: Colors.white54, fontSize: 13)),
        ])),
      );
    }

    if (!_isVideo) {
      if (widget.height == 0) {
        return NetImage(url: widget.m.url, fit: BoxFit.fitWidth, width: double.infinity,
          placeholder: Container(color: context.colors.borderColor, height: 200),
          errorWidget: Container(color: context.colors.borderColor, height: 200, child: const Icon(Icons.broken_image_rounded, color: Color(0xFF9CA3AF), size: 32)));
      }
      return SizedBox(height: widget.height, width: double.infinity,
        child: NetImage(url: widget.m.url, fit: BoxFit.cover,
          placeholder: Container(color: context.colors.borderColor),
          errorWidget: Container(color: context.colors.borderColor, child: const Icon(Icons.broken_image_rounded, color: Color(0xFF9CA3AF), size: 32))));
    }

    final screenW = MediaQuery.of(context).size.width;
    final serverAr = widget.m.aspectRatio;
    double videoH;
    if (_ready && _ctrl != null) {
      final ar = _ctrl!.value.aspectRatio;
      videoH = (screenW / ar).clamp(200.0, 400.0);
    } else if (serverAr != null) {
      videoH = (screenW / serverAr).clamp(200.0, 400.0);
    } else {
      videoH = 300;
    }

    final videoContent = GestureDetector(
        onTap: _ready ? _togglePause : null,
        onDoubleTap: _ready && _ctrl != null ? () {
          _pool.pause(_videoUrl);
          Navigator.push(context, MaterialPageRoute(builder: (_) => _SimpleVideoPlayer(url: _videoUrl)));
        } : null,
        child: Stack(
          children: [
            // Video or thumbnail — simple stack, no complex crossfade layers
            Container(
              color: const Color(0xFF1A1B2E),
              width: double.infinity,
              height: videoH,
              child: _ready && _ctrl != null
                  ? FittedBox(
                      fit: BoxFit.contain,
                      child: SizedBox(
                        width: _ctrl!.value.size.width,
                        height: _ctrl!.value.size.height,
                        child: VideoPlayer(_ctrl!),
                      ))
                  : widget.m.thumbnail != null
                      ? NetImage(
                          url: widget.m.thumbnail!,
                          fit: BoxFit.contain,
                          placeholder: Container(color: const Color(0xFF1A1B2E)),
                          errorWidget: Container(color: const Color(0xFF1A1B2E)))
                      : const SizedBox(),
            ),
            // Tiny corner spinner only during actual re-buffering (not initial load)
            if (_ready && _ctrl != null && _ctrl!.value.isBuffering)
              Positioned(bottom: 50, right: 12,
                child: SizedBox(width: 18, height: 18,
                  child: CircularProgressIndicator(color: kOrange, strokeWidth: 2))),
            // Paused icon when user manually paused
            if (_paused && _ready)
              Positioned.fill(child: Center(child: Container(padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.4), shape: BoxShape.circle),
                child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 40)))),
            if (_ready && _ctrl != null) Positioned(bottom: 0, left: 0, right: 0,
              child: Container(
                padding: const EdgeInsets.fromLTRB(10, 20, 10, 8),
                decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter,
                  colors: [Colors.transparent, Colors.black.withValues(alpha: 0.6)])),
                child: Row(children: [
                  GestureDetector(onTap: _togglePause,
                    child: Icon(_paused ? Icons.play_arrow_rounded : Icons.pause_rounded, color: Colors.white, size: 22)),
                  const SizedBox(width: 8),
                  Expanded(child: ClipRRect(borderRadius: BorderRadius.circular(2),
                    child: VideoProgressIndicator(_ctrl!, allowScrubbing: true, colors: const VideoProgressColors(
                      playedColor: kOrange, bufferedColor: Colors.white30, backgroundColor: Colors.white12)))),
                  const SizedBox(width: 8),
                  Text(_formatDuration(_ctrl!.value.duration), style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
                  const SizedBox(width: 6),
                  GestureDetector(onTap: () { setState(() { _ctrl!.setVolume(_ctrl!.value.volume > 0 ? 0 : 1); }); },
                    child: Icon(_ctrl!.value.volume > 0 ? Icons.volume_up_rounded : Icons.volume_off_rounded, color: Colors.white, size: 18)),
                ]))),
          ],
        ),
      );

    return VisibilityDetector(
      key: _key,
      onVisibilityChanged: _onVisibilityChanged,
      child: _ready && _ctrl != null
          ? VideoAdOverlay(mainController: _ctrl!, child: videoContent)
          : videoContent,
    );
  }
}

// AUDIO PLAYER CARD
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class _AudioPlayerCard extends StatefulWidget {
  final String url;
  const _AudioPlayerCard({required this.url});
  @override
  State<_AudioPlayerCard> createState() => _AudioPlayerCardState();
}

class _AudioPlayerCardState extends State<_AudioPlayerCard> {
  final _player = ap.AudioPlayer();
  bool _playing = false;
  bool _loaded = false;
  Duration _duration = Duration.zero;
  Duration _position = Duration.zero;

  @override
  void initState() {
    super.initState();
    _player.onDurationChanged.listen((d) { if (mounted) setState(() => _duration = d); });
    _player.onPositionChanged.listen((p) { if (mounted) setState(() => _position = p); });
    _player.onPlayerComplete.listen((_) { if (mounted) setState(() { _playing = false; _position = Duration.zero; }); });
    _player.onPlayerStateChanged.listen((s) { if (mounted) setState(() => _playing = s == ap.PlayerState.playing); });
    _preload();
  }

  void _preload() async {
    try {
      await _player.setSourceUrl(widget.url);
      _loaded = true;
    } catch (_) {}
  }

  @override
  void dispose() { _player.dispose(); super.dispose(); }

  void _toggle() async {
    if (_playing) {
      await _player.pause();
    } else if (_loaded) {
      await _player.resume();
    } else {
      await _player.play(ap.UrlSource(widget.url));
      _loaded = true;
    }
  }

  String _fmt(Duration d) => '${d.inMinutes.toString().padLeft(2, '0')}:${(d.inSeconds % 60).toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final progress = _duration.inMilliseconds > 0 ? _position.inMilliseconds / _duration.inMilliseconds : 0.0;
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: [kOrange.withValues(alpha: 0.08), kOrange.withValues(alpha: 0.02)]),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: kOrange.withValues(alpha: 0.2))),
      child: Row(children: [
        GestureDetector(
          onTap: _toggle,
          child: Container(width: 48, height: 48,
            decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle,
              boxShadow: [BoxShadow(color: kOrange.withValues(alpha: 0.3), blurRadius: 10)]),
            child: Icon(_playing ? Icons.pause_rounded : Icons.play_arrow_rounded, color: Colors.white, size: 28))),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Waveform-style bars
          SizedBox(height: 28, child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: List.generate(30, (i) {
              final barProgress = i / 30;
              final isActive = barProgress <= progress;
              final height = (8 + (i % 5) * 4.0 + (i % 3) * 3.0).clamp(6.0, 24.0);
              return Expanded(child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 0.5),
                height: height,
                decoration: BoxDecoration(
                  color: isActive ? kOrange : kOrange.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(2))));
            }))),
          const SizedBox(height: 6),
          Row(children: [
            Text(_fmt(_position), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: kOrange)),
            const Spacer(),
            Text(_fmt(_duration), style: const TextStyle(fontSize: 11, color: Color(0xFF9CA3AF))),
          ]),
        ])),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// DOCUMENT CARD
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class _DocumentCard extends StatelessWidget {
  final String url;
  const _DocumentCard({required this.url});

  String get _fileName {
    final uri = Uri.tryParse(url);
    if (uri == null) return 'Document';
    final path = uri.queryParameters['f'] ?? uri.path;
    return path.split('/').last;
  }

  String get _ext {
    final name = _fileName.toLowerCase();
    if (name.endsWith('.pdf')) return 'PDF';
    if (name.endsWith('.doc') || name.endsWith('.docx')) return 'DOC';
    return 'FILE';
  }

  IconData get _icon {
    switch (_ext) {
      case 'PDF': return Icons.picture_as_pdf_rounded;
      case 'DOC': return Icons.description_rounded;
      default: return Icons.insert_drive_file_rounded;
    }
  }

  Color get _color {
    switch (_ext) {
      case 'PDF': return const Color(0xFFE53935);
      case 'DOC': return const Color(0xFF1565C0);
      default: return const Color(0xFF6B7280);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.colors.borderColor)),
      child: Row(children: [
        Container(width: 52, height: 52,
          decoration: BoxDecoration(color: _color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
          child: Icon(_icon, color: _color, size: 28)),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(_fileName, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF1A1B2E)), maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 4),
          Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
            decoration: BoxDecoration(color: _color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
            child: Text(_ext, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: _color))),
        ])),
        const SizedBox(width: 8),
        Column(children: [
          GestureDetector(
            onTap: () => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
            child: Container(width: 36, height: 36,
              decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.download_rounded, color: kOrange, size: 20))),
          const SizedBox(height: 6),
          GestureDetector(
            onTap: () => Navigator.push(context, MaterialPageRoute(
              builder: (_) => Scaffold(
                appBar: AppBar(title: Text(_fileName, style: const TextStyle(fontSize: 14))),
                body: WebViewWidget(controller: WebViewController()..loadRequest(Uri.parse(url)))))),
            child: Container(width: 36, height: 36,
              decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.visibility_rounded, color: Color(0xFF6B7280), size: 20))),
        ]),
      ]),
    );
  }
}