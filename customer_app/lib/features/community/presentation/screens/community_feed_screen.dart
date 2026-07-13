import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../../../core/widgets/app_shimmer.dart';
import 'package:media_kit/media_kit.dart' show Player, Media;
import 'package:media_kit_video/media_kit_video.dart';
import 'package:video_player/video_player.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:visibility_detector/visibility_detector.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import '../widgets/stories_bar.dart';
import 'highlight_viewer_screen.dart';
import 'community_shell.dart';
import 'community_notifications_screen.dart';
import 'create_post_screen.dart';
import '../widgets/comments_sheet.dart';
import '../widgets/video_ad_overlay.dart';
import '../services/video_pool.dart';
import '../services/ad_video_manager.dart';
import '../../../../core/services/realtime_client.dart';
import 'copyright_screen.dart';
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
      if (_scrollCtrl.offset < 100 && _hasNewPosts) {
        setState(() => _hasNewPosts = false);
      }
    });
    // Fallback poll (slow — realtime is the primary path, this just covers
    // the rare case where the socket is down for an extended period).
    _pollTimer = Timer.periodic(AppConstants.feedPollInterval, (_) => _checkNewPosts());
    // Presence heartbeat — tells the admin dashboard this user is actively on the feed.
    CommunityRepository().feedHeartbeat();
    _heartbeatTimer = Timer.periodic(AppConstants.feedHeartbeatInterval, (_) => CommunityRepository().feedHeartbeat());
    _subscribeNewPostFeed();
    // Throttle VisibilityDetector callbacks to 250ms — the default 100ms fires
    // too often during scroll and wastes CPU on setFraction calls.
    VisibilityDetectorController.instance.updateInterval = const Duration(milliseconds: 250);
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
    });

    return Scaffold(
      body: Stack(children: [
      NestedScrollView(
        controller: _scrollCtrl,
        physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
        headerSliverBuilder: (context, _) => [
          SliverAppBar(
            pinned: true,
            floating: true,
            elevation: 0,
            
            title: RichText(
              text: TextSpan(
                children: [
                  TextSpan(text: 'e', style: TextStyle(color: kOrange, fontWeight: FontWeight.w900, fontSize: 22)),
                  TextSpan(text: 'Sahlan.', style: TextStyle(color: context.colors.bodyText, fontWeight: FontWeight.w700, fontSize: 20)),
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
              SizedBox(width: 4),
            ],
            bottom: TabBar(
              controller: _tabCtrl,
              indicatorColor: kOrange,
              indicatorWeight: 2.5,
              labelColor: kOrange,
              unselectedLabelColor: const Color(0xFF6B7280),
              labelStyle: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
              unselectedLabelStyle: TextStyle(fontWeight: FontWeight.w500, fontSize: 13),
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
            padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(20),
              boxShadow: [BoxShadow(color: kOrange.withValues(alpha: 0.4), blurRadius: 8, offset: const Offset(0, 2))]),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
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
        margin: EdgeInsets.only(right: 6),
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
                  decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle),
                  child: Center(
                    child: Text(
                      badge! > 9 ? '9+' : '$badge',
                      style: TextStyle(color: Colors.white, fontSize: 8, fontWeight: FontWeight.w700),
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

class _FeedTab extends ConsumerStatefulWidget {
  final AsyncValue<List<CommunityPost>> feedState;
  final AsyncValue<List<StoryGroup>> storiesState;
  const _FeedTab({required this.feedState, required this.storiesState});

  @override
  ConsumerState<_FeedTab> createState() => _FeedTabState();
}

class _FeedTabState extends ConsumerState<_FeedTab> {
  int _lastLoadMs = 0;

  @override
  Widget build(BuildContext context) {
    // Watch at top level so suggestions/reels updates don't cause extra rebuilds
    // inside the data callback when the feed itself hasn't changed.
    final suggestions = ref.watch(communitySuggestionsProvider).valueOrNull ?? [];
    final reels       = ref.watch(communityReelsProvider).valueOrNull ?? [];

    return ColoredBox(
      color: context.colors.scaffoldBg,
      child: NotificationListener<ScrollNotification>(
        onNotification: (n) {
          if (n is ScrollUpdateNotification) {
            final m = n.metrics;
            if (m.pixels >= m.maxScrollExtent - 800) {
              final now = DateTime.now().millisecondsSinceEpoch;
              if (now - _lastLoadMs > 400) {
                _lastLoadMs = now;
                ref.read(communityFeedProvider.notifier).load();
              }
            }
          }
          return false;
        },
        child: RefreshIndicator(
          color: kOrange,
          onRefresh: () => ref.read(communityFeedProvider.notifier).refresh(),
          // CustomScrollView + SliverList: posts are built LAZILY (only visible ones).
          // The old ListView + Column built ALL 30+ cards at once — this is the main perf fix.
          child: CustomScrollView(
            physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
            cacheExtent: 1500,
            slivers: [
              SliverToBoxAdapter(child: widget.storiesState.when(
                data: (groups) => StoriesBar(groups: groups),
                loading: () => const SizedBox(height: 100),
                error: (_, __) => const SizedBox.shrink(),
              )),
              const SliverToBoxAdapter(child: _CreatePostBar()),
              const SliverToBoxAdapter(child: SizedBox(height: 4)),

              ...widget.feedState.when<List<Widget>>(
                data: (posts) {
                  if (posts.isEmpty) return [const SliverToBoxAdapter(child: _EmptyFeed())];

              // Register regular post video URLs in the pool (ads are separate).
              // Preload ad videos via AdPreloader so they don't compete for pool slots.
              WidgetsBinding.instance.addPostFrameCallback((_) {
                final videoUrls = posts.expand((p) {
                  if (p.isAd) return <String>[];
                  return p.media
                      .where((m) => m.type == 'video')
                      .map((m) => m.mp4DirectUrl)
                      .where((u) => u.isNotEmpty);
                }).toList();
                VideoPool.feed.setFeedUrls(videoUrls);
                // Only initialise the window on first data arrival (windowIndex == -1).
                // Subsequent calls (page 2, 3 …) must NOT reset to index 0 — that
                // would evict the currently-visible controller mid-scroll and kill the
                // playing video. After first init, setActiveUrl() called from
                // _onVisibilityChanged keeps the window correctly centred.
                if (VideoPool.feed.windowIndex < 0 && videoUrls.isNotEmpty) {
                  VideoPool.feed.setWindow(videoUrls, 0);
                }

                final adUrls = posts
                    .where((p) => p.isAd && p.adType == 'video' && p.adMediaUrl != null)
                    .map((p) => p.adMediaUrl!)
                    .toList();
                if (adUrls.isNotEmpty) AdVideoManager.instance.preload(adUrls);
              });

                  // Build widget object list — SliverList calls .build() lazily
                  // only for visible items, so this is fast (no tree inflation).
                  int postsSinceLastAd = 999;
                  final items = <Widget>[];
                  final followable = suggestions.where((u) => !u.isMe && !u.isFollowing).toList();
                  for (var i = 0; i < posts.length; i++) {
                    final post = posts[i];
                    if (post.isAd && postsSinceLastAd < 3) continue;
                    if (post.isAd) { postsSinceLastAd = 0; } else { postsSinceLastAd++; }
                    items.add(RepaintBoundary(child: _PostCard(post: posts[i],
                      onDelete: () {
                        ref.read(communityRepoProvider).deletePost(posts[i].id);
                        ref.read(communityFeedProvider.notifier).removePost(posts[i].id);
                      },
                    )));
                    if (i == 4 && followable.isNotEmpty) {
                      items.add(_PeopleYouMayKnow(users: followable.take(10).toList()));
                    }
                    if (i == 8 && reels.isNotEmpty) {
                      items.add(_ReelsCarousel(reels: reels.take(6).toList()));
                    }
                    if (i > 12 && (i - 12) % 10 == 0 && followable.length > 10) {
                      final offset = ((i - 12) ~/ 10) * 5;
                      final batch = followable.skip(offset).take(10).toList();
                      if (batch.isNotEmpty) items.add(_PeopleYouMayKnow(users: batch));
                    }
                    if (i > 16 && (i - 16) % 12 == 0 && reels.length > 6) {
                      final offset = ((i - 16) ~/ 12) * 4;
                      final batch = reels.skip(offset).take(6).toList();
                      if (batch.isNotEmpty) items.add(_ReelsCarousel(reels: batch));
                    }
                  }
                  final hasMore = ref.watch(communityFeedProvider.notifier).hasMore;
                  items.add(_FeedLoadMore(hasMore: hasMore));

                  return [
                    SliverList(
                      delegate: SliverChildBuilderDelegate(
                        (_, i) => items[i],
                        childCount: items.length,
                        addAutomaticKeepAlives: false,
                        addRepaintBoundaries: false, // already wrapped manually above
                      ),
                    ),
                  ];
                },
                loading: () => [SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    child: ShimmerPostList(count: 3),
                  ),
                )],
                error: (e, _) => [SliverToBoxAdapter(
                  child: Center(
                    child: Padding(
                      padding: const EdgeInsets.all(20),
                      child: Text('Error: $e', style: const TextStyle(color: Colors.red)),
                    ),
                  ),
                )],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FeedLoadMore extends StatelessWidget {
  final bool hasMore;
  const _FeedLoadMore({required this.hasMore});

  @override
  Widget build(BuildContext context) {
    // Facebook-style: no visible indicator while loading more.
    // Only show end-of-feed message when there truly is nothing left.
    if (hasMore) return const SizedBox(height: 60);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 24),
      child: Center(
        child: Text('Dhammaatay · Dib u eeg danbe',
            style: TextStyle(color: Colors.grey, fontSize: 13)),
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
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
        SliverToBoxAdapter(child: hashtagsAsync.when(
          data: (tags) {
            if (tags.isEmpty) return const SizedBox.shrink();
            return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Padding(padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                child: Row(children: [
                  Icon(Icons.tag_rounded, color: kOrange, size: 20),
                  const SizedBox(width: 6),
                  Text('Trending Hashtags', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.bodyText)),
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
                    Text('#${tags[i]['name']}', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: kOrange)),
                    const SizedBox(width: 6),
                    Text('${tags[i]['posts_count']}', style: TextStyle(fontSize: 11, color: kOrange.withValues(alpha: 0.6), fontWeight: FontWeight.w600)),
                  ]),
                ),
              )),
              const SizedBox(height: 8),
              const Divider(height: 1),
            ]);
          },
          loading: () => const SizedBox.shrink(),
          error: (_, __) => const SizedBox.shrink(),
        )),

        SliverToBoxAdapter(child: Padding(padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Row(children: [
            Icon(Icons.local_fire_department_rounded, color: kOrange, size: 20),
            const SizedBox(width: 6),
            Text('Trending Posts', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.bodyText)),
            const Spacer(),
            Text('100+ engagement', style: TextStyle(fontSize: 11, color: context.colors.mutedText)),
          ]))),

        ...exploreState.when<List<Widget>>(
          data: (posts) {
            if (posts.isEmpty) return [SliverToBoxAdapter(child: Padding(padding: const EdgeInsets.all(40),
              child: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.trending_up_rounded, size: 48, color: Color(0xFFD1D5DB)),
                const SizedBox(height: 8),
                Text('No trending posts yet', style: TextStyle(color: context.colors.mutedText, fontSize: 14)),
                const SizedBox(height: 4),
                const Text('Posts need 100+ total likes, comments, views or shares', style: TextStyle(color: Color(0xFFD1D5DB), fontSize: 12), textAlign: TextAlign.center),
              ]))))];
            return [SliverList(
              delegate: SliverChildBuilderDelegate(
                (_, i) => RepaintBoundary(child: _PostCard(post: posts[i], onDelete: () {})),
                childCount: posts.length,
              ),
            )];
          },
          loading: () => [SliverToBoxAdapter(child: Padding(padding: const EdgeInsets.all(16), child: ShimmerPostList(count: 3)))],
          error: (e, _) => [const SliverToBoxAdapter(child: _EmptyTab(message: 'Error loading trending', icon: Icons.error_outline_rounded))],
        ),

        const SliverToBoxAdapter(child: SizedBox(height: 80)),
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
        loading: () => Center(child: CircularProgressIndicator(color: kOrange)),
        data: (users) {
          if (users.isEmpty) return const _EmptyTab(message: 'No people found', icon: Icons.people_rounded);
          return ListView.builder(
            padding: EdgeInsets.symmetric(vertical: 8),
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
        SizedBox(height: 12),
        Text(message, style: TextStyle(color: context.colors.mutedText, fontSize: 15)),
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
        padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(children: [
          CircleNetImage(url: u.avatar, size: 52, fallbackText: u.name),
          SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Flexible(child: Text(u.name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: context.colors.bodyText), maxLines: 1, overflow: TextOverflow.ellipsis)),
              if (u.isVerified) Padding(padding: EdgeInsets.only(left: 4), child: Icon(Icons.verified_rounded, color: Color(0xFF1877F2), size: 16)),
            ]),
            if (u.username != null) Text('@${u.username}', style: TextStyle(color: context.colors.mutedText, fontSize: 13)),
            if (u.bio != null && u.bio!.isNotEmpty) Text(u.bio!, style: TextStyle(color: context.colors.mutedText, fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
            SizedBox(height: 4),
            Row(children: [
              Text('${u.followersCount} followers', style: TextStyle(fontSize: 12, color: context.colors.mutedText, fontWeight: FontWeight.w600)),
              Text('  Â·  ', style: TextStyle(color: Color(0xFFD1D5DB))),
              Text('${u.postsCount} posts', style: TextStyle(fontSize: 12, color: context.colors.mutedText, fontWeight: FontWeight.w600)),
            ]),
          ])),
          SizedBox(width: 10),
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
              padding: EdgeInsets.symmetric(vertical: 8)),
            child: Text(_following ? 'Following' : 'Follow', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
          )),
        ]),
      ),
    );
  }
}

// ── Ad Card ───────────────────────────────────────────────────────────────────
// Architecture: AdVideoManager owns and caches controllers per URL.
// Card just plays/pauses; VideoPool is never touched → content videos unaffected.
class _AdCard extends ConsumerStatefulWidget {
  final CommunityPost post;
  const _AdCard({required this.post});
  @override
  ConsumerState<_AdCard> createState() => _AdCardState();
}

class _AdCardState extends ConsumerState<_AdCard> with WidgetsBindingObserver {
  VideoPlayerController? _ctrl;
  bool _ready   = false;
  bool _muted   = false;
  bool _visible = false;

  String? get _url => widget.post.adMediaUrl;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (widget.post.adType == 'video' && _url != null) _init();
  }

  void _init() async {
    final ctrl = await AdVideoManager.instance.awaitController(_url!);
    if (ctrl == null || !mounted) return;
    ctrl.setLooping(true);
    ctrl.setVolume(_muted ? 0 : 1);
    setState(() { _ctrl = ctrl; _ready = true; });
    if (_visible) ctrl.play();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      _ctrl?.pause();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _ctrl?.pause();
    // AdVideoManager owns the controller lifetime — do not dispose here
    super.dispose();
  }

  void _onTap() {
    if (widget.post.id > 0) ref.read(communityRepoProvider).trackAdClick(widget.post.id);
    final raw = widget.post.adCtaUrl;
    if (raw != null && raw.isNotEmpty) {
      launchUrl(Uri.parse(raw.startsWith('http') ? raw : 'https://$raw'),
          mode: LaunchMode.externalApplication);
    }
  }

  void _toggleMute() {
    setState(() => _muted = !_muted);
    _ctrl?.setVolume(_muted ? 0 : 1);
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.post;
    final c = context.colors;

    return VisibilityDetector(
      key: ValueKey('ad_${p.id}'),
      onVisibilityChanged: (info) {
        final nowVisible = info.visibleFraction > 0.5;
        if (nowVisible == _visible) return;
        _visible = nowVisible;
        if (p.adType != 'video') return;
        if (_visible) {
          if (_ctrl != null && _ready) _ctrl!.play();
        } else {
          _ctrl?.pause();
        }
      },
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        color: c.cardBg,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

          // ── Sponsor header ─────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 10),
            child: Row(children: [
              p.adPage?['avatar'] != null
                ? CircleNetImage(url: p.adPage!['avatar'], size: 40,
                    fallbackText: p.adPage?['name'] ?? '')
                : Container(
                    width: 40, height: 40,
                    decoration: BoxDecoration(color: c.chipBg, shape: BoxShape.circle),
                    child: Icon(Icons.storefront_rounded, color: kOrange, size: 20)),
              const SizedBox(width: 10),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Flexible(child: Text(
                    p.adPage?['name'] ?? 'Sponsored',
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: c.bodyText))),
                  const SizedBox(width: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                    decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(3)),
                    child: const Text('Sponsored',
                        style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800))),
                ]),
                const SizedBox(height: 2),
                Text('Promoted', style: TextStyle(color: Colors.grey[400], fontSize: 11)),
              ])),
            ]),
          ),

          // ── Body text ──────────────────────────────────────────────────────
          if (p.content != null && p.content!.isNotEmpty)
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 0, 14, 8),
              child: Text(p.content!, style: TextStyle(color: c.mutedText, fontSize: 14))),

          // ── Media ──────────────────────────────────────────────────────────
          GestureDetector(
            onTap: _onTap,
            child: p.adType == 'video'
              ? _AdVideoPlayer(ctrl: _ctrl, ready: _ready, muted: _muted, onMute: _toggleMute)
              : (p.adMediaUrl != null
                  ? NetImage(url: p.adMediaUrl!, fit: BoxFit.cover, width: double.infinity)
                  : const SizedBox(height: 200))),

          // ── CTA bar ────────────────────────────────────────────────────────
          if (p.adCtaText != null)
            GestureDetector(
              onTap: _onTap,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                color: c.surfaceBg,
                child: Row(children: [
                  Expanded(child: Text(p.adTitle ?? '',
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: c.bodyText),
                    maxLines: 1, overflow: TextOverflow.ellipsis)),
                  const SizedBox(width: 10),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(6)),
                    child: Text(p.adCtaText!,
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13))),
                ]),
              ),
            ),

          // ── Action row ─────────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.symmetric(vertical: 2),
            child: Row(children: [
              Expanded(child: TextButton.icon(onPressed: () {},
                icon: const Icon(Icons.thumb_up_alt_outlined, size: 18), label: const Text('Like'),
                style: TextButton.styleFrom(foregroundColor: const Color(0xFF6B7280)))),
              Expanded(child: TextButton.icon(onPressed: () {},
                icon: const Icon(Icons.chat_bubble_outline_rounded, size: 18), label: const Text('Comment'),
                style: TextButton.styleFrom(foregroundColor: const Color(0xFF6B7280)))),
              Expanded(child: TextButton.icon(onPressed: _onTap,
                icon: const Icon(Icons.share_outlined, size: 18), label: const Text('Share'),
                style: TextButton.styleFrom(foregroundColor: const Color(0xFF6B7280)))),
            ]),
          ),
        ]),
      ),
    );
  }
}

// Pure display widget — black until controller is ready, then video fills frame.
class _AdVideoPlayer extends StatelessWidget {
  final VideoPlayerController? ctrl;
  final bool ready;
  final bool muted;
  final VoidCallback onMute;
  const _AdVideoPlayer({required this.ctrl, required this.ready, required this.muted, required this.onMute});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      height: 260,
      child: Stack(fit: StackFit.expand, children: [
        // Black while loading, video once ready
        if (ready && ctrl != null)
          Center(child: AspectRatio(
            aspectRatio: ctrl!.value.aspectRatio.clamp(0.5, 2.5),
            child: VideoPlayer(ctrl!)))
        else
          const ColoredBox(color: Colors.black),

        // Mute toggle — bottom-right, always present for video ads
        Positioned(
          bottom: 10, right: 10,
          child: GestureDetector(
            onTap: onMute,
            behavior: HitTestBehavior.opaque,
            child: Container(
              width: 32, height: 32,
              decoration: const BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
              child: Icon(
                muted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
                color: Colors.white, size: 16)))),
      ]),
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
      margin: EdgeInsets.fromLTRB(12, 8, 12, 4),
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
            decoration: BoxDecoration(
              gradient: LinearGradient(colors: [kOrange, Color(0xFFFFB347)]),
              borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
            ),
          ),
          Padding(
            padding: EdgeInsets.fromLTRB(14, 12, 14, 4),
            child: Row(
              children: [
                CircleNetImage(url: avatar, size: 42),
                SizedBox(width: 10),
                Expanded(
                  child: GestureDetector(
                    onTap: () async {
                      final post = await Navigator.push<CommunityPost>(
                        context,
                        MaterialPageRoute(builder: (_) => CreatePostScreen()),
                      );
                      if (post != null) {
                        if (post.moderationStatus == 'pending') {
                          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                            content: Text('Your post is under review and will appear once approved.'),
                            duration: Duration(seconds: 4),
                          ));
                        } else {
                          ref.read(communityFeedProvider.notifier).prependPost(post);
                          final myId = ref.read(communityMyProfileProvider).valueOrNull?.id;
                          if (myId != null) ref.invalidate(communityProfilePostsProvider(myId));
                        }
                      }
                    },
                    child: Container(
                      padding: EdgeInsets.symmetric(horizontal: 16, vertical: 11),
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
            padding: EdgeInsets.fromLTRB(8, 4, 8, 10),
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
          if (post != null) {
            if (post.moderationStatus == 'pending') {
              ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
                content: Text('Your post is under review and will appear once approved.'),
                duration: Duration(seconds: 4),
              ));
            } else {
              ref.read(communityFeedProvider.notifier).prependPost(post);
              final myId = ref.read(communityMyProfileProvider).valueOrNull?.id;
              if (myId != null) ref.invalidate(communityProfilePostsProvider(myId));
            }
          }
        },
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: color, size: 20),
            SizedBox(width: 5),
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
      padding: EdgeInsets.all(40),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 80, height: 80,
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: [Color(0xFFFFEDD5), Color(0xFFFFF7ED)], begin: Alignment.topLeft, end: Alignment.bottomRight),
            shape: BoxShape.circle,
            boxShadow: [BoxShadow(color: kOrange.withValues(alpha: 0.15), blurRadius: 20, spreadRadius: 2)],
          ),
          child: Icon(Icons.dynamic_feed_rounded, size: 40, color: kOrange),
        ),
        SizedBox(height: 16),
        Text('Your feed is empty', style: TextStyle(color: context.colors.bodyText, fontSize: 18, fontWeight: FontWeight.w700)),
        SizedBox(height: 6),
        Text('Follow people to see their posts', style: TextStyle(color: context.colors.mutedText, fontSize: 14)),
        SizedBox(height: 20),
        ElevatedButton(
          style: ElevatedButton.styleFrom(
            backgroundColor: kOrange,
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
            padding: EdgeInsets.symmetric(horizontal: 24, vertical: 12),
          ),
          onPressed: () => context.push('/community/explore'),
          child: Text('Find People', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
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
  String get _postChannel => 'community.post.${widget.post.id}';
  final Map<String, void Function(dynamic)> _realtimeListeners = {};

  @override
  void initState() {
    super.initState();
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
      setState(() {
        widget.post.likesCount = data['likes_count'] as int;
        final rc = data['reaction_counts'];
        if (rc is Map) {
          widget.post.reactionCounts = Map<String, int>.from(
              rc.map((k, v) => MapEntry(k.toString(), (v as num).toInt())));
        }
      });
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
      title: Text('Edit post', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
      content: TextField(controller: ctrl, maxLines: 5, decoration: InputDecoration(hintText: 'Edit your post...', border: OutlineInputBorder())),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text('Cancel')),
        TextButton(onPressed: () async {
          Navigator.pop(ctx);
          try {
            final updated = await ref.read(communityRepoProvider).updatePost(widget.post.id, content: ctrl.text.trim());
            ref.read(communityFeedProvider.notifier).updatePost(updated);
          } catch (_) {}
        }, child: Text('Save', style: TextStyle(color: kOrange, fontWeight: FontWeight.w700))),
      ],
    ));
  }

  String _reactionEmoji(String type) => {'like': '👍', 'love': '❤️', 'haha': '😂', 'wow': '😮', 'sad': '😢', 'angry': '😡'}[type] ?? 'Like';
  String _fmtCount(int n) => n >= 1000 ? '${(n / 1000).toStringAsFixed(n >= 10000 ? 0 : 1)}K' : '$n';

  @override
  Widget build(BuildContext context) {
    final p = widget.post;

    // â”€â”€ Ad Card â”€â”€
    if (p.isAd) return _AdCard(post: p);

    final c = context.colors;
    return Container(
      margin: EdgeInsets.fromLTRB(12, 0, 12, 12),
      decoration: BoxDecoration(
        color: c.cardBg,
        borderRadius: BorderRadius.circular(18),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 4, offset: const Offset(0, 1))],
      ),
      clipBehavior: Clip.hardEdge,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Header
        Padding(
          padding: EdgeInsets.fromLTRB(12, 12, 12, 0),
          child: Row(children: [
            GestureDetector(
              onTap: () => p.pageId != null
                  ? Navigator.push(context, MaterialPageRoute(builder: (_) => BusinessPageDetailScreen(pageId: p.pageId!)))
                  : context.push('/community/profile/${p.user.id}'),
              child: CircleNetImage(url: p.user.avatar, size: 40, fallbackText: p.user.name),
            ),
            SizedBox(width: 10),
            Expanded(
              child: GestureDetector(
                onTap: () => p.pageId != null
                    ? Navigator.push(context, MaterialPageRoute(builder: (_) => BusinessPageDetailScreen(pageId: p.pageId!)))
                    : context.push('/community/profile/${p.user.id}'),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Text(p.user.name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.bodyText)),
                    if (p.user.isVerified) ...[
                      SizedBox(width: 4),
                      Icon(Icons.verified_rounded, color: kOrange, size: 14),
                    ],
                  ]),
                  Row(children: [
                    Icon(Icons.public_rounded, size: 12, color: context.colors.mutedText),
                    SizedBox(width: 3),
                    Text(timeago.format(p.createdAt), style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
                    if (p.location != null) ...[
                      Text(' Â· ', style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
                      Icon(Icons.location_on_rounded, size: 12, color: context.colors.mutedText),
                      Text(p.location!, style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
                    ],
                  ]),
                ]),
              ),
            ),
            GestureDetector(
              onTap: () => _showOptions(context),
              child: Icon(Icons.more_horiz_rounded, color: context.colors.mutedText),
            ),
          ]),
        ),

        // Feeling
        if (p.feeling != null)
          Padding(
            padding: EdgeInsets.fromLTRB(12, 6, 12, 0),
            child: Text('is feeling ${p.feeling}', style: TextStyle(color: context.colors.mutedText, fontSize: 13)),
          ),

        // Content with "See more"
        if (p.content != null && p.content!.isNotEmpty)
          _ExpandableText(text: p.content!),

        // Shared post preview
        if (p.type == 'share' && p.sharedPost != null)
          Container(
            margin: EdgeInsets.fromLTRB(12, 4, 12, 8),
            decoration: BoxDecoration(border: Border.all(color: c.borderColor), borderRadius: BorderRadius.circular(12)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Padding(padding: EdgeInsets.fromLTRB(12, 10, 12, 6), child: Row(children: [
                CircleNetImage(url: (p.sharedPost!['user'] as Map?)?['avatar'], size: 28, fallbackText: (p.sharedPost!['user'] as Map?)?['name'] ?? '?'),
                SizedBox(width: 8),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text((p.sharedPost!['user'] as Map?)?['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.bodyText)),
                  Text(p.sharedPost!['created_at'] != null ? timeago.format(DateTime.tryParse('${p.sharedPost!['created_at']}') ?? DateTime.now()) : '', style: TextStyle(fontSize: 11, color: context.colors.mutedText)),
                ])),
              ])),
              if (p.sharedPost!['content'] != null)
                Padding(padding: EdgeInsets.fromLTRB(12, 0, 12, 8), child: Text('${p.sharedPost!['content']}', style: TextStyle(fontSize: 14, color: context.colors.bodyText))),
              if (p.sharedPost!['media'] is List && (p.sharedPost!['media'] as List).isNotEmpty)
                ClipRRect(borderRadius: BorderRadius.vertical(bottom: Radius.circular(12)),
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
        if (p.media.isNotEmpty)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: _MediaGrid(media: p.media, postId: p.id, isOwner: p.user.isMe),
            ),
          ),

        // Poll
        if (p.type == 'poll' && p.pollOptions.isNotEmpty)
          Padding(padding: EdgeInsets.fromLTRB(12, 4, 12, 8),
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
                  margin: EdgeInsets.only(bottom: 8),
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(10), border: Border.all(color: c.borderColor)),
                  child: Stack(children: [
                    FractionallySizedBox(widthFactor: pct / 100, child: Container(
                      height: 44, decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(9)))),
                    Container(height: 44, padding: EdgeInsets.symmetric(horizontal: 14),
                      child: Row(children: [
                        Expanded(child: Text(opt.text, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: context.colors.bodyText))),
                        Text('$pct%', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: kOrange)),
                      ])),
                  ]),
                ),
              );
            }).toList()),
          ),

        // Per-reaction emoji breakdown row (left) + stats row (right)
        Builder(builder: (_) {
          final chips = p.reactionCounts.entries.where((e) => e.value > 0).toList();
          final hasStats = p.viewsCount > 0 || p.commentsCount > 0 || p.sharesCount > 0 || p.savesCount > 0;
          if (chips.isEmpty && !hasStats) return const SizedBox.shrink();
          return Padding(
            padding: const EdgeInsets.fromLTRB(14, 4, 14, 2),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              if (chips.isNotEmpty)
                Wrap(
                  spacing: 8,
                  runSpacing: 4,
                  children: chips.map((e) {
                    final label = _fmtCount(e.value);
                    return GestureDetector(
                      onTap: () => _showReactionsList(e.key),
                      child: Row(mainAxisSize: MainAxisSize.min, children: [
                        Text(_reactionEmoji(e.key), style: const TextStyle(fontSize: 14)),
                        const SizedBox(width: 3),
                        Text(label, style: TextStyle(color: context.colors.mutedText, fontSize: 12, fontWeight: FontWeight.w600)),
                      ]),
                    );
                  }).toList(),
                ),
              if (chips.isNotEmpty && hasStats) const SizedBox(height: 4),
              Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
              const Spacer(),
              // Right: 👁 views · 💬 comments · ↪ shares · 🔖 saves
              Row(mainAxisSize: MainAxisSize.min, children: [
                if (p.viewsCount > 0) ...[
                  _StatIcon(icon: Icons.remove_red_eye_outlined, count: p.viewsCount, color: const Color(0xFF8A94A6)),
                  const SizedBox(width: 10),
                ],
                if (p.commentsCount > 0) ...[
                  _StatIcon(
                    icon: Icons.mode_comment_outlined,
                    count: p.commentsCount,
                    color: const Color(0xFF8A94A6),
                    onTap: () => showCommentsSheet(context, p.id, initialCount: p.commentsCount, commentsDisabled: p.commentsDisabled),
                  ),
                  const SizedBox(width: 10),
                ],
                if (p.sharesCount > 0) ...[
                  _StatIcon(icon: Icons.reply_rounded, count: p.sharesCount, color: const Color(0xFF8A94A6)),
                  const SizedBox(width: 10),
                ],
                if (p.savesCount > 0)
                  _StatIcon(icon: Icons.bookmark_border_rounded, count: p.savesCount, color: const Color(0xFF8A94A6)),
              ]),
            ]),  // inner Row (stats)
            ]),  // Column
          );
        }),

        _PostActionBar(post: p, onShare: _showShareDialog),
      ]),
    );
  }

  void _showReactionsList(String initialType) {
    final allChips = widget.post.reactionCounts.entries
        .where((e) => e.value > 0)
        .toList();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: context.colors.elevatedBg,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => StatefulBuilder(
        builder: (ctx, setSheetState) {
          String selectedType = initialType;
          return StatefulBuilder(
            builder: (ctx2, setTab) {
              return DraggableScrollableSheet(
                initialChildSize: 0.55,
                minChildSize: 0.35,
                maxChildSize: 0.9,
                expand: false,
                builder: (_, scroll) => Column(children: [
                  // Drag handle
                  Container(
                    margin: const EdgeInsets.only(top: 10),
                    width: 38, height: 4,
                    decoration: BoxDecoration(
                      color: context.colors.borderColor,
                      borderRadius: BorderRadius.circular(2)),
                  ),
                  const SizedBox(height: 12),
                  // Emoji tabs row — each type tappable
                  SingleChildScrollView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Row(
                      children: allChips.map((e) {
                        final isSelected = e.key == selectedType;
                        return GestureDetector(
                          onTap: () => setTab(() => selectedType = e.key),
                          child: AnimatedContainer(
                            duration: const Duration(milliseconds: 180),
                            margin: const EdgeInsets.only(right: 8),
                            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                            decoration: BoxDecoration(
                              color: isSelected ? kOrange.withValues(alpha: 0.12) : Colors.transparent,
                              borderRadius: BorderRadius.circular(20),
                              border: Border.all(
                                color: isSelected ? kOrange : context.colors.borderColor,
                                width: isSelected ? 1.5 : 1,
                              ),
                            ),
                            child: Row(mainAxisSize: MainAxisSize.min, children: [
                              Text(_reactionEmoji(e.key), style: const TextStyle(fontSize: 18)),
                              const SizedBox(width: 6),
                              Text(
                                _fmtCount(e.value),
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  color: isSelected ? kOrange : context.colors.mutedText,
                                ),
                              ),
                            ]),
                          ),
                        );
                      }).toList(),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Divider(height: 1, color: context.colors.borderColor),
                  // Users list for selected type
                  Expanded(
                    child: FutureBuilder<List<Map<String, dynamic>>>(
                      key: ValueKey(selectedType),
                      future: ref.read(communityRepoProvider)
                          .getPostReactions(widget.post.id, type: selectedType),
                      builder: (_, snap) {
                        if (snap.connectionState == ConnectionState.waiting) {
                          return const Center(child: CircularProgressIndicator());
                        }
                        if (snap.hasError || snap.data == null) {
                          return Center(child: Text('Failed to load',
                              style: TextStyle(color: context.colors.mutedText)));
                        }
                        final users = snap.data!;
                        if (users.isEmpty) {
                          return Center(child: Text('No reactions yet',
                              style: TextStyle(color: context.colors.mutedText)));
                        }
                        return ListView.builder(
                          controller: scroll,
                          itemCount: users.length,
                          itemBuilder: (_, i) {
                            final u = users[i];
                            return ListTile(
                              leading: CircleNetImage(
                                  url: u['avatar'] as String?,
                                  size: 40,
                                  fallbackText: u['name'] as String? ?? '?'),
                              title: Text(u['name'] as String? ?? '',
                                  style: TextStyle(
                                      fontWeight: FontWeight.w600,
                                      color: context.colors.bodyText)),
                              subtitle: u['username'] != null
                                  ? Text('@${u['username']}',
                                      style: TextStyle(
                                          color: context.colors.mutedText,
                                          fontSize: 12))
                                  : null,
                              trailing: Text(_reactionEmoji(selectedType),
                                  style: const TextStyle(fontSize: 20)),
                            );
                          },
                        );
                      },
                    ),
                  ),
                ]),
              );
            },
          );
        },
      ),
    );
  }

  void _showShareDialog() {
    final ctrl = TextEditingController();
    showDialog(context: context, builder: (ctx) => AlertDialog(
      title: Text('Share post', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
      content: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(padding: EdgeInsets.all(10), decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(8)),
          child: Row(children: [
            CircleNetImage(url: widget.post.user.avatar, size: 28, fallbackText: widget.post.user.name),
            SizedBox(width: 8),
            Expanded(child: Text(widget.post.content ?? 'Post by ${widget.post.user.name}', style: TextStyle(fontSize: 12, color: context.colors.mutedText), maxLines: 2, overflow: TextOverflow.ellipsis)),
          ])),
        SizedBox(height: 12),
        TextField(controller: ctrl, maxLines: 3, decoration: InputDecoration(hintText: 'Add your thoughts...', border: OutlineInputBorder())),
      ]),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text('Cancel')),
        TextButton(onPressed: () async {
          Navigator.pop(ctx);
          try {
            final shared = await ref.read(communityRepoProvider).sharePost(widget.post.id, content: ctrl.text.trim().isEmpty ? null : ctrl.text.trim());
            ref.read(communityFeedProvider.notifier).prependPost(shared);
            if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Shared!'), backgroundColor: Color(0xFF10B981)));
          } catch (_) {}
        }, child: Text('Share', style: TextStyle(color: kOrange, fontWeight: FontWeight.w700))),
      ],
    ));
  }

  void _showBoostDialog() {
    double budget = 5;
    int hours = 24;
    showDialog(context: context, builder: (ctx) => StatefulBuilder(
      builder: (ctx, setD) => AlertDialog(
        title: Row(children: [
          Icon(Icons.rocket_launch_rounded, color: kOrange, size: 22),
          SizedBox(width: 8),
          Text('Boost Post', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
        ]),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Text('Promote your post to reach more people', style: TextStyle(color: context.colors.mutedText, fontSize: 13)),
          SizedBox(height: 16),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('Budget', style: TextStyle(fontWeight: FontWeight.w600)),
            Text('\$${budget.toStringAsFixed(0)}', style: TextStyle(fontWeight: FontWeight.w800, color: kOrange, fontSize: 18)),
          ]),
          Slider(value: budget, min: 1, max: 100, divisions: 20, activeColor: kOrange,
            onChanged: (v) => setD(() => budget = v)),
          SizedBox(height: 8),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('Duration', style: TextStyle(fontWeight: FontWeight.w600)),
            Text('${hours}h', style: TextStyle(fontWeight: FontWeight.w800, color: kOrange)),
          ]),
          Slider(value: hours.toDouble(), min: 1, max: 168, divisions: 7, activeColor: kOrange,
            onChanged: (v) => setD(() => hours = v.toInt())),
          SizedBox(height: 8),
          Container(padding: EdgeInsets.all(10), decoration: BoxDecoration(
            color: kOrange.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(8)),
            child: Row(children: [
              Icon(Icons.people_rounded, color: kOrange, size: 18),
              SizedBox(width: 8),
              Text('Est. ${(budget * 50).toInt()} - ${(budget * 150).toInt()} people',
                style: TextStyle(fontSize: 13, color: context.colors.bodyText)),
            ])),
        ]),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: Text('Cancel')),
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
            child: Text('Boost Now', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    ));
  }

  void _showReportDialog(BuildContext context) {
    const reasons = [
      ('spam', 'Spam'),
      ('violence', 'Violence'),
      ('fake_news', 'Fake News'),
      ('scam', 'Scam'),
      ('harassment', 'Harassment'),
      ('pornography', 'Nudity / Sexual Content'),
      ('copyright', 'Copyright'),
      ('other', 'Other'),
    ];
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const SizedBox(height: 12),
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 14),
              child: Text('Report Post', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
            ),
            ...reasons.map((r) => ListTile(
              dense: true,
              title: Text(r.$2, style: const TextStyle(fontSize: 14)),
              onTap: () async {
                Navigator.pop(ctx);
                try {
                  await ref.read(communityRepoProvider).reportPost(widget.post.id, r.$1);
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Report submitted. Thank you.'), backgroundColor: Color(0xFF16A34A)),
                    );
                  }
                } catch (_) {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Already reported or error occurred')),
                    );
                  }
                }
              },
            )),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }

  void _showOptions(BuildContext context) {
    showModalBottomSheet(
      context: context,
      builder: (_) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          if (widget.post.user.isMe) ListTile(
            leading: Icon(Icons.edit_rounded, color: kOrange),
            title: Text('Edit Post'),
            onTap: () { Navigator.pop(context); _showEditDialog(); },
          ),
          if (widget.post.user.isMe) ListTile(
            leading: Icon(Icons.delete_rounded, color: Colors.red),
            title: Text('Delete Post', style: TextStyle(color: Colors.red)),
            onTap: () {
              Navigator.pop(context);
              widget.onDelete();
            },
          ),
          if (widget.post.user.isMe) ListTile(
            leading: Icon(Icons.rocket_launch_rounded, color: kOrange),
            title: Text('Boost Post'),
            subtitle: Text('Promote to more people', style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
            onTap: () { Navigator.pop(context); _showBoostDialog(); },
          ),
          if (widget.post.user.isMe) ListTile(
            leading: Icon(Icons.bookmark_added_rounded, color: kOrange),
            title: const Text('Add to Highlight'),
            onTap: () {
              Navigator.pop(context);
              showModalBottomSheet(
                context: context,
                isScrollControlled: true,
                backgroundColor: Colors.transparent,
                builder: (_) => AddToHighlightSheet(
                  userId: widget.post.user.id,
                  contentType: 'post',
                  contentId: widget.post.id,
                ),
              );
            },
          ),
          if (!widget.post.user.isMe) ListTile(
            leading: Icon(Icons.visibility_off_rounded, color: context.colors.mutedText),
            title: Text('Not interested'),
            subtitle: Text('See fewer posts like this', style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
            onTap: () {
              Navigator.pop(context);
              ref.read(communityRepoProvider).trackInteraction(widget.post.id, 'skip');
              ref.read(communityFeedProvider.notifier).removePost(widget.post.id);
            },
          ),
          ListTile(
            leading: const Icon(Icons.copyright_rounded, color: Color(0xFF3B82F6)),
            title: const Text('Copyright Claim', style: TextStyle(color: Color(0xFF3B82F6), fontWeight: FontWeight.w600)),
            onTap: () {
              Navigator.pop(context);
              showModalBottomSheet(
                context: context,
                isScrollControlled: true,
                backgroundColor: Colors.white,
                shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
                builder: (_) => _CopyrightQuickClaim(postId: widget.post.id),
              );
            },
          ),
          ListTile(
            leading: const Icon(Icons.flag_rounded, color: Color(0xFFDC2626)),
            title: const Text('Report Post', style: TextStyle(color: Color(0xFFDC2626), fontWeight: FontWeight.w600)),
            onTap: () {
              Navigator.pop(context);
              _showReportDialog(context);
            },
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
  bool? _isLong; // cached — TextPainter layout is expensive, skip on re-builds

  @override
  Widget build(BuildContext context) {
    // Compute once per text content; re-compute if text changes.
    _isLong ??= _computeIsLong(context);

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(widget.text,
          style: TextStyle(color: context.colors.bodyText, fontSize: 15, height: 1.4),
          maxLines: _expanded ? null : 3,
          overflow: _expanded ? null : TextOverflow.ellipsis),
        if (!_expanded && (_isLong ?? false))
          GestureDetector(
            onTap: () => setState(() => _expanded = true),
            child: Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Text('See more', style: TextStyle(color: context.colors.mutedText, fontWeight: FontWeight.w700, fontSize: 14)),
            ),
          ),
      ]),
    );
  }

  bool _computeIsLong(BuildContext context) {
    final tp = TextPainter(
      text: TextSpan(text: widget.text, style: const TextStyle(fontSize: 15, height: 1.4)),
      maxLines: 3,
      textDirection: TextDirection.ltr,
    )..layout(maxWidth: MediaQuery.of(context).size.width - 24);
    return tp.didExceedMaxLines;
  }
}


// Isolated action bar: owns reaction picker state + save state.
// Extracted so that like/save/share taps only rebuild this small widget,
// not the entire post card (which includes media grid, text, stats row).
class _PostActionBar extends ConsumerStatefulWidget {
  final CommunityPost post;
  final VoidCallback onShare;
  const _PostActionBar({required this.post, required this.onShare});

  @override
  ConsumerState<_PostActionBar> createState() => _PostActionBarState();
}

class _PostActionBarState extends ConsumerState<_PostActionBar> {
  bool _showReactions = false;
  late String? _myReaction;
  late bool _isSaved;

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
    _isSaved = widget.post.isSaved;
  }

  String _emoji(String type) =>
      {'like': '👍', 'love': '❤️', 'haha': '😂', 'wow': '😮', 'sad': '😢', 'angry': '😡'}[type] ?? 'Like';

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
    return Column(mainAxisSize: MainAxisSize.min, children: [
      const Divider(height: 1, thickness: 1, color: Color(0xFFF2F4F7)),
      Padding(
        padding: const EdgeInsets.fromLTRB(6, 4, 6, 6),
        child: Row(children: [
          _myReaction != null
            ? _ReactionActiveBtn(
                emoji: _emoji(_myReaction!),
                onTap: () => setState(() => _showReactions = !_showReactions),
                onLongPress: () => _react('like'),
              )
            : _ActionBtn(
                icon: Icons.thumb_up_alt_outlined,
                label: 'Like',
                color: const Color(0xFF8A94A6),
                onTap: () => setState(() => _showReactions = !_showReactions),
                onLongPress: () => _react('like'),
              ),
          _ActionBtn(
            icon: Icons.mode_comment_outlined, label: 'Comment',
            color: const Color(0xFF8A94A6),
            onTap: () => showCommentsSheet(context, p.id, initialCount: p.commentsCount, commentsDisabled: p.commentsDisabled),
          ),
          _ActionBtn(icon: Icons.reply_rounded, label: 'Share', color: const Color(0xFF8A94A6), onTap: widget.onShare),
          _SaveBtn(
            postId: p.id,
            isSaved: _isSaved,
            onToggle: (saved) {
              setState(() => _isSaved = saved);
              p.isSaved = saved;
            },
          ),
        ]),
      ),
      if (_showReactions)
        Container(
          padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
          child: _ReactionPicker(
            reactions: _reactions,
            onPick: _react,
            onDismiss: () => setState(() => _showReactions = false),
          ),
        ),
    ]);
  }
}

class _SaveBtn extends ConsumerWidget {
  final int postId;
  final bool isSaved;
  final void Function(bool) onToggle;
  const _SaveBtn({required this.postId, required this.isSaved, required this.onToggle});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return GestureDetector(
      onTap: () async {
        try {
          final saved = await ref.read(communityRepoProvider).savePost(postId);
          onToggle(saved);
        } catch (_) {}
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        child: Icon(
          isSaved ? Icons.bookmark_rounded : Icons.bookmark_border_rounded,
          color: isSaved ? kOrange : const Color(0xFF8A94A6),
          size: 22,
        ),
      ),
    );
  }
}

// Shown when the user has reacted — displays only the chosen emoji, no thumbs-up icon
class _ReactionActiveBtn extends StatelessWidget {
  final String emoji;
  final VoidCallback onTap;
  final VoidCallback? onLongPress;
  const _ReactionActiveBtn({required this.emoji, required this.onTap, this.onLongPress});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        onLongPress: onLongPress,
        child: Container(
          margin: const EdgeInsets.symmetric(horizontal: 3, vertical: 4),
          padding: const EdgeInsets.symmetric(vertical: 7),
          decoration: BoxDecoration(
            color: kOrange.withValues(alpha: 0.10),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Center(
            child: Text(emoji, style: const TextStyle(fontSize: 18)),
          ),
        ),
      ),
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
          margin: EdgeInsets.symmetric(horizontal: 3, vertical: 4),
          padding: EdgeInsets.symmetric(vertical: 7),
          decoration: active
              ? BoxDecoration(color: kOrange.withValues(alpha: 0.10), borderRadius: BorderRadius.circular(10))
              : null,
          child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(icon, color: color, size: 20),
            SizedBox(width: 5),
            Text(label, style: TextStyle(color: color, fontSize: 13, fontWeight: FontWeight.w600)),
          ]),
        ),
      ),
    );
  }
}

// Icon + count (no label) — for the right-side stats row
class _StatIcon extends StatelessWidget {
  final IconData icon;
  final int count;
  final Color color;
  final VoidCallback? onTap;
  const _StatIcon({required this.icon, required this.count, required this.color, this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, color: color, size: 18),
        if (count > 0) ...[
          const SizedBox(width: 4),
          Text(
            count >= 1000 ? '${(count / 1000).toStringAsFixed(1)}K' : '$count',
            style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600),
          ),
        ],
      ]),
    );
  }
}

// Save icon + count — toggles saved state
class _SaveStatIcon extends ConsumerStatefulWidget {
  final int postId;
  final bool isSaved;
  final int count;
  final void Function(bool) onToggle;
  const _SaveStatIcon({required this.postId, required this.isSaved, required this.count, required this.onToggle});

  @override
  ConsumerState<_SaveStatIcon> createState() => _SaveStatIconState();
}

class _SaveStatIconState extends ConsumerState<_SaveStatIcon> {
  late bool _saved;
  late int  _count;

  @override
  void initState() {
    super.initState();
    _saved = widget.isSaved;
    _count = widget.count;
  }

  void _toggle() async {
    final next = !_saved;
    setState(() { _saved = next; _count += next ? 1 : -1; });
    widget.onToggle(next);
    await ref.read(communityRepoProvider).savePost(widget.postId);
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: _toggle,
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(
          _saved ? Icons.bookmark_rounded : Icons.bookmark_border_rounded,
          color: _saved ? kOrange : const Color(0xFF8A94A6),
          size: 18,
        ),
        if (_count > 0) ...[
          const SizedBox(width: 4),
          Text(
            _count >= 1000 ? '${(_count / 1000).toStringAsFixed(1)}K' : '$_count',
            style: TextStyle(
              color: _saved ? kOrange : const Color(0xFF8A94A6),
              fontSize: 12, fontWeight: FontWeight.w600),
          ),
        ],
      ]),
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
        padding: EdgeInsets.symmetric(horizontal: 8, vertical: 6),
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
            child: Padding(padding: EdgeInsets.symmetric(horizontal: 5),
              child: Text(widget.emoji, style: TextStyle(fontSize: 30)))),
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
      margin: EdgeInsets.only(bottom: 8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(padding: EdgeInsets.fromLTRB(14, 14, 14, 10),
          child: Row(children: [
            Icon(Icons.people_alt_rounded, color: kOrange, size: 20),
            SizedBox(width: 8),
            Text('People you may know', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.bodyText)),
          ])),
        SizedBox(height: 220,
          child: ListView.builder(
            scrollDirection: Axis.horizontal,
            padding: EdgeInsets.symmetric(horizontal: 10),
            itemCount: users.length,
            itemBuilder: (_, i) => _SuggestionCard(user: users[i]),
          )),
        SizedBox(height: 8),
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
      width: 160, margin: EdgeInsets.only(right: 8),
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
                      style: TextStyle(fontSize: 32, fontWeight: FontWeight.w800, color: kOrange)))))),
          Positioned(top: 4, right: 4,
            child: GestureDetector(onTap: () => setState(() => _removed = true),
              child: Container(width: 24, height: 24,
                decoration: BoxDecoration(color: Colors.black38, shape: BoxShape.circle),
                child: Icon(Icons.close, size: 14, color: Colors.white)))),
        ]),
        Padding(padding: EdgeInsets.fromLTRB(8, 8, 8, 4),
          child: Text(u.name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.bodyText),
            maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center)),
        Text('${u.followersCount} followers', style: TextStyle(fontSize: 11, color: context.colors.mutedText)),
        Spacer(),
        Padding(padding: EdgeInsets.fromLTRB(10, 0, 10, 10),
          child: SizedBox(width: double.infinity,
            child: ElevatedButton(
              onPressed: () async {
                setState(() => _following = !_following);
                try { await ref.read(communityRepoProvider).toggleFollow(u.id); } catch (_) { setState(() => _following = !_following); }
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: _following ? context.colors.chipBg : kOrange,
                foregroundColor: _following ? context.colors.mutedText : Colors.white,
                elevation: 0, padding: EdgeInsets.symmetric(vertical: 8),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
              child: Text(_following ? 'Following' : 'Follow', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
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
      margin: EdgeInsets.only(bottom: 8),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(padding: EdgeInsets.fromLTRB(14, 14, 14, 10),
          child: Row(children: [
            Icon(Icons.play_circle_filled_rounded, color: kOrange, size: 20),
            SizedBox(width: 8),
            Text('Reels', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.bodyText)),
            Spacer(),
            GestureDetector(
              onTap: () {
                ref.read(communityNavIndexProvider.notifier).state = 1;
              },
              child: Text('See all', style: TextStyle(color: kOrange, fontWeight: FontWeight.w700, fontSize: 13))),
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
                  ref.read(communityReelsJumpPostIdProvider.notifier).state = reel.id;
                  ref.read(communityNavIndexProvider.notifier).state = 1;
                },
                child: Container(
                  width: 120, margin: const EdgeInsets.only(right: 8),
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(14), color: const Color(0xFF1A1B2E)),
                  clipBehavior: Clip.antiAlias,
                  child: Stack(fit: StackFit.expand, children: [
                    _VideoReelPreview(thumbnailUrl: thumb),

                    Container(decoration: const BoxDecoration(gradient: LinearGradient(
                      begin: Alignment.topCenter, end: Alignment.bottomCenter,
                      colors: [Colors.transparent, Colors.black87]))),
                    Positioned(bottom: 8, left: 8, right: 8,
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                        Row(children: [
                          CircleNetImage(url: reel.user.avatar, size: 22, fallbackText: reel.user.name),
                          const SizedBox(width: 6),
                          Expanded(child: Text(reel.user.name, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600),
                            maxLines: 1, overflow: TextOverflow.ellipsis)),
                        ]),
                        if (reel.likesCount > 0 || reel.commentsCount > 0) ...[
                          const SizedBox(height: 5),
                          Row(children: [
                            if (reel.likesCount > 0) ...[
                              const Icon(Icons.thumb_up_rounded, color: Colors.white, size: 12),
                              const SizedBox(width: 3),
                              Text(reel.likesCount >= 1000 ? '${(reel.likesCount/1000).toStringAsFixed(1)}K' : '${reel.likesCount}',
                                style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                              const SizedBox(width: 8),
                            ],
                            if (reel.commentsCount > 0) ...[
                              const Icon(Icons.mode_comment_outlined, color: Colors.white, size: 12),
                              const SizedBox(width: 3),
                              Text(reel.commentsCount >= 1000 ? '${(reel.commentsCount/1000).toStringAsFixed(1)}K' : '${reel.commentsCount}',
                                style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                            ],
                          ]),
                        ],
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
        SizedBox(height: 8),
      ]),
    );
  }
}

// ── Lightweight reel card preview — thumbnail + pulsing play ring (no Player) ─

class _VideoReelPreview extends StatefulWidget {
  final String? thumbnailUrl;
  const _VideoReelPreview({this.thumbnailUrl});

  @override
  State<_VideoReelPreview> createState() => _VideoReelPreviewState();
}

class _VideoReelPreviewState extends State<_VideoReelPreview>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse;
  late final Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200))
      ..repeat(reverse: true);
    _scale = Tween<double>(begin: 0.85, end: 1.0).animate(
        CurvedAnimation(parent: _pulse, curve: Curves.easeInOut));
  }

  @override
  void dispose() { _pulse.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Stack(fit: StackFit.expand, children: [
      if (widget.thumbnailUrl != null)
        NetImage(url: widget.thumbnailUrl!, fit: BoxFit.cover)
      else
        Container(color: const Color(0xFF1A1B2E)),
      Center(child: ScaleTransition(
        scale: _scale,
        child: Container(
          width: 40, height: 40,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Colors.black38,
            border: Border.all(color: Colors.white70, width: 2)),
          child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 24)),
      )),
    ]);
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
      SizedBox(height: 2),
      SizedBox(height: 120, child: Row(children: [
        Expanded(child: GestureDetector(
          onTap: media[1].type == 'image' ? () => _openGallery(context, 1) : null,
          child: _MediaItem(m: media[1], height: 120, postId: postId, isOwner: isOwner))),
        SizedBox(width: 2),
        Expanded(child: GestureDetector(
          onTap: () => _openGallery(context, 2),
          child: Stack(children: [
            _MediaItem(m: media[2], height: 120, postId: postId, isOwner: isOwner),
            if (extra > 0) Positioned.fill(child: Container(
              color: Colors.black45,
              child: Center(child: Text('+$extra', style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800))),
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
  final int postId;
  final int progress;
  final String? thumbnail;
  final bool isOwner;
  const _TranscodingPlaceholder({required this.mediaId, required this.postId, required this.progress, this.thumbnail, this.isOwner = false});
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
    _pollTimer = Timer.periodic(const Duration(seconds: 5), (_) => _poll());
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
        // Refresh just this post in-place (avoids resetting the whole feed scroll position).
        if (status == 'ready' && widget.postId > 0) {
          try {
            final fresh = await ref.read(communityRepoProvider).getPost(widget.postId);
            if (mounted) {
              ref.read(communityFeedProvider.notifier).updatePost(fresh);
              // Also refresh the profile so the processed video appears there too.
              final myId = ref.read(communityMyProfileProvider).valueOrNull?.id;
              if (myId != null) ref.invalidate(communityProfilePostsProvider(myId));
            }
          } catch (_) {
            // Fallback: reload the whole feed if the single-post fetch fails.
            if (mounted) ref.invalidate(communityFeedProvider);
          }
        } else {
          ref.invalidate(communityFeedProvider);
        }
        if (!mounted) return;
        final isReady = status == 'ready';
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Row(children: [
            Icon(isReady ? Icons.check_circle_rounded : Icons.error_outline_rounded,
                color: Colors.white, size: 20),
            SizedBox(width: 10),
            Text(isReady ? 'Muuqaalkaagu waa diyaar!' : 'Processing-ku wuu fashilmay',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
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
        SizedBox(height: 10),
        Text(
          _progress > 0 ? 'Processing... $_progress%' : 'Processing video...',
          style: TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600),
        ),
        SizedBox(height: 4),
        Text('Will be ready shortly', style: TextStyle(color: Colors.white60, fontSize: 11)),
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
  VideoController? _ctrl;
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
        onTap: _ready && _ctrl != null ? () {
          _ctrl!.player.state.playing ? _pool.pause(widget.url) : _pool.play(widget.url);
          setState(() {});
        } : null,
        onVerticalDragEnd: (d) { if (d.primaryVelocity != null && d.primaryVelocity! > 300) Navigator.pop(context); },
        child: _ready && _ctrl != null
          ? Stack(fit: StackFit.expand, children: [
              Video(controller: _ctrl!, fit: BoxFit.contain, controls: NoVideoControls),
              if (!_ctrl!.player.state.playing)
                Center(child: Icon(Icons.play_circle_fill_rounded, color: Colors.white70, size: 64)),
              Positioned(bottom: 30, left: 16, right: 16,
                child: StreamBuilder<Duration>(
                  stream: _ctrl!.player.stream.position,
                  builder: (ctx, snap) {
                    final pos = snap.data ?? Duration.zero;
                    final dur = _ctrl!.player.state.duration;
                    final progress = dur.inMilliseconds > 0
                        ? (pos.inMilliseconds / dur.inMilliseconds).clamp(0.0, 1.0)
                        : 0.0;
                    return LinearProgressIndicator(
                      value: progress, color: kOrange,
                      backgroundColor: Colors.white12, minHeight: 3);
                  })),
              Positioned(top: MediaQuery.of(context).padding.top + 8, left: 8,
                child: GestureDetector(onTap: () => Navigator.pop(context),
                  child: Container(padding: EdgeInsets.all(8),
                    decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.4), shape: BoxShape.circle),
                    child: Icon(Icons.close_rounded, color: Colors.white, size: 22)))),
            ])
          : Center(child: CircularProgressIndicator(color: kOrange)),
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
        title: Text('${initialIndex + 1} / ${images.length}', style: TextStyle(fontSize: 16))),
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

// Global mute state — like Facebook: unmuting one video unmutes all subsequent ones.
bool _globalMuted = false;

class _MediaItemState extends ConsumerState<_MediaItem> with WidgetsBindingObserver {
  VideoController? _controller;
  bool _ready = false;
  bool _hasFrame = false;
  bool _initStarted = false;
  bool _paused = false;
  bool _visible = false;
  bool _loadFailed = false;
  double _lastFraction = 0;
  final _key = UniqueKey();
  final _pool = VideoPool.feed;
  DateTime? _watchStart;
  StreamSubscription<dynamic>? _playerSub;
  StreamSubscription<dynamic>? _bufferingSub;
  StreamSubscription<dynamic>? _videoParamsSub;

  // Controls auto-hide (Facebook-style: show on tap, hide after 3s)
  bool _showControls = false;
  Timer? _controlsTimer;

  bool get _isVideo => widget.m.type == 'video';
  bool get _isAudio => widget.m.type == 'audio';
  bool get _isDocument => widget.m.type == 'document';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (_isVideo) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted) return;
        // Precache thumbnail immediately so it appears without any delay.
        // By the time user scrolls to this item the thumbnail is already in
        // Flutter's image cache → zero black-screen between scroll and thumbnail.
        final thumb = widget.m.thumbnail;
        if (thumb != null && thumb.isNotEmpty) {
          precacheImage(CachedNetworkImageProvider(thumb), context);
        }
        // Fast-path: attach pool controller if already preloaded.
        if (!_initStarted && _pool.isReady(widget.m.mp4DirectUrl)) {
          _initStarted = true;
          _initVideo();
        }
      });
    }
  }

  void _attachPlayerListeners(VideoController ctrl) {
    _playerSub?.cancel();
    _bufferingSub?.cancel();
    _videoParamsSub?.cancel();
    _playerSub = ctrl.player.stream.playing.listen((_) {
      if (mounted) setState(() {});
    });
    _bufferingSub = ctrl.player.stream.buffering.listen((_) {
      if (mounted) setState(() {});
    });
    _videoParamsSub = ctrl.player.stream.videoParams.listen((vp) {
      if (!_hasFrame && (vp.w ?? 0) > 0 && mounted) {
        setState(() => _hasFrame = true);
      }
    });
    // Re-check immediately: videoParams may have fired before we subscribed
    // (broadcast stream — missed events are not replayed).
    if (!_hasFrame && (ctrl.player.state.width ?? 0) > 0 && mounted) {
      setState(() => _hasFrame = true);
    }
    // Fallback: if videoParams never fires (codec quirk / platform edge case),
    // reveal the video after 600 ms so we don't stay stuck on thumbnail forever.
    Future.delayed(const Duration(milliseconds: 600), () {
      if (mounted && _ready && !_hasFrame) setState(() => _hasFrame = true);
    });
  }

  @override
  void didUpdateWidget(_MediaItem old) {
    super.didUpdateWidget(old);
    // Handles: post.media_ready realtime event → mp4DirectUrl just became
    // available (transcoding completed). Reset so visibility can re-trigger init.
    if (_isVideo && !_ready && !widget.m.isTranscoding &&
        (old.m.mp4DirectUrl != widget.m.mp4DirectUrl ||
         old.m.isTranscoding != widget.m.isTranscoding)) {
      _initStarted = false;
      _loadFailed = false;
      if (_lastFraction > 0.05) {
        _initStarted = true;
        _initVideo();
      }
    }
  }

  bool _lifecyclePaused = false;

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused) {
      if (!_lifecyclePaused) {
        _lifecyclePaused = true;
        if (_controller != null && _ready) _pool.pause(_previewUrl);
      }
    } else if (state == AppLifecycleState.inactive) {
      if (!_lifecyclePaused) {
        _lifecyclePaused = true;
        if (_controller != null && _ready) _pool.pause(_previewUrl);
      }
    } else if (state == AppLifecycleState.resumed) {
      _lifecyclePaused = false;
      if (_visible && !_paused && _controller != null && _ready) {
        _pool.reactivate(_previewUrl);
      }
    }
  }

  // Feed always uses the direct nginx MP4 (preview.mp4 / optimized.mp4).
  String get _previewUrl => widget.m.mp4DirectUrl;

  Future<void> _initVideo() async {
    final url = _previewUrl;
    if (url.isEmpty) { _initStarted = false; return; }

    // Fast path — pool already has this controller (preloaded by setWindow).
    final cached = _pool.controller(url);
    if (cached != null && mounted) {
      cached.player.setVolume(_globalMuted ? 0 : 100);
      setState(() { _controller = cached; _ready = true; _loadFailed = false; _hasFrame = (cached.player.state.width ?? 0) > 0; });
      _attachPlayerListeners(cached);
      if (!_paused) _pool.setFraction(url, _lastFraction);
      return;
    }

    // Slow path — ask pool to initialize the controller from the network.
    final ctrl = await _pool.preload(url);
    if (ctrl != null && mounted && _pool.isReady(url)) {
      ctrl.player.setVolume(_globalMuted ? 0 : 100);
      setState(() { _controller = ctrl; _ready = true; _loadFailed = false; _hasFrame = (ctrl.player.state.width ?? 0) > 0; });
      _attachPlayerListeners(ctrl);
      if (!_paused) _pool.setFraction(url, _lastFraction);
    } else if (ctrl != null && mounted) {
      _initStarted = false;
    } else if (mounted) {
      setState(() { _loadFailed = true; });
      _initStarted = false;
    }
  }

  @override
  void dispose() {
    _controlsTimer?.cancel();
    _playerSub?.cancel();
    _bufferingSub?.cancel();
    _videoParamsSub?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  void _showControlsBriefly() {
    setState(() => _showControls = true);
    _controlsTimer?.cancel();
    _controlsTimer = Timer(const Duration(seconds: 3), () {
      if (mounted) setState(() => _showControls = false);
    });
  }

  void _onVisibilityChanged(VisibilityInfo info) {
    final fraction = info.visibleFraction;
    _lastFraction = fraction;

    // ── Stale-controller guard ─────────────────────────────────────────────
    if (_ready && _controller != null && !_pool.isReady(_previewUrl)) {
      _playerSub?.cancel();
      _bufferingSub?.cancel();
      _initStarted = false;
      _loadFailed = false;
      setState(() { _controller = null; _ready = false; _hasFrame = false; });
    }

    // ── Preload trigger (>5%) ──────────────────────────────────────────────
    if (fraction > 0.05 && _isVideo && !_ready && !_initStarted && !_loadFailed) {
      _initStarted = true;
      _initVideo();
    }
    // Reset error state when item drops below trigger threshold — eliminates
    // the 0.01–0.05 dead band where items were stuck permanently.
    if (fraction < 0.05 && _loadFailed) {
      _loadFailed = false;
      _initStarted = false;
    }

    // ── Report fraction to pool — pool plays the most-visible URL (>60%) ───
    if (_isVideo && _previewUrl.isNotEmpty && !_paused) {
      _pool.setFraction(_previewUrl, fraction);
      if (fraction > 0.15) _pool.setActiveUrl(_previewUrl);
      // Restore volume after pool pause/pauseOthers — pool sets vol=0 on pause
      // but doesn't restore it when the video becomes dominant again.
      if (_ready && _controller != null && fraction > 0.5) {
        _controller!.player.setVolume(_globalMuted ? 0 : 100);
      }
    }

    // ── Watch-time tracking ────────────────────────────────────────────────
    _visible = fraction > 0.5;
    if (_visible && _ready && _controller != null) {
      _watchStart ??= DateTime.now();
    } else if (!_visible) {
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
    if (!_ready || _controller == null) return;
    setState(() => _paused = !_paused);
    if (_paused) {
      // Clear fraction so this video is no longer a dominant candidate —
      // otherwise the stale high fraction blocks videos below from autoplaying.
      _pool.setFraction(_previewUrl, 0);
      _pool.pause(_previewUrl);
    } else {
      _pool.setFraction(_previewUrl, _lastFraction);
      _pool.play(_previewUrl);
      _controller!.player.setVolume(_globalMuted ? 0 : 100);
    }
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

    // Non-owners never see transcoding posts (video_ready=false filters feed).
    if (_isVideo && widget.m.isTranscoding && !widget.isOwner) {
      return const SizedBox.shrink();
    }
    // Owner sees a real-time transcoding placeholder that polls progress every 5s.
    if (_isVideo && widget.m.isTranscoding && widget.isOwner) {
      return _TranscodingPlaceholder(
        mediaId: widget.m.id,
        postId: widget.postId ?? 0,
        progress: widget.m.transcodingProgress,
        thumbnail: widget.m.thumbnail,
        isOwner: true,
      );
    }
    if (_isVideo && widget.m.transcodingFailed) {
      return Container(
        height: 220, color: const Color(0xFF1A1B2E),
        child: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
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
          errorWidget: Container(color: context.colors.borderColor, height: 200, child: Icon(Icons.broken_image_rounded, color: context.colors.mutedText, size: 32)));
      }
      return SizedBox(height: widget.height, width: double.infinity,
        child: NetImage(url: widget.m.url, fit: BoxFit.cover,
          placeholder: Container(color: context.colors.borderColor),
          errorWidget: Container(color: context.colors.borderColor, child: Icon(Icons.broken_image_rounded, color: context.colors.mutedText, size: 32))));
    }

    final screenW = MediaQuery.of(context).size.width;
    final serverAr = widget.m.aspectRatio;
    double videoH = 300;
    if (_ready && _controller != null) {
      final w = _controller!.player.state.width?.toDouble();
      final h = _controller!.player.state.height?.toDouble();
      final ar = (w != null && h != null && h > 0) ? w / h : serverAr;
      if (ar != null && ar > 0) videoH = (screenW / ar).clamp(200.0, 400.0);
    } else if (serverAr != null) {
      videoH = (screenW / serverAr).clamp(200.0, 400.0);
    } else {
      videoH = 300;
    }

    final videoContent = GestureDetector(
        // Tap: show controls (Facebook-style — controls hidden by default)
        onTap: () {
          if (!_ready) return;
          if (_showControls) { _togglePause(); } else { _showControlsBriefly(); }
        },
        onDoubleTap: _ready && _controller != null ? () {
          _pool.pause(_previewUrl);
          Navigator.push(context, MaterialPageRoute(builder: (_) => _SimpleVideoPlayer(url: _previewUrl)));
        } : null,
        child: Stack(
          children: [
            Container(
              color: const Color(0xFF0A0A0A),
              width: double.infinity,
              height: videoH,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  // Thumbnail — NetImage handles proxy auth + caching.
                  // Always visible as background until video fades in on top.
                  Positioned.fill(
                    child: widget.m.thumbnail != null && widget.m.thumbnail!.isNotEmpty
                        ? NetImage(
                            url: widget.m.thumbnail!,
                            fit: BoxFit.cover,
                            placeholder: Container(color: const Color(0xFF1A1A2E)),
                            errorWidget: Container(color: const Color(0xFF1A1A2E)),
                          )
                        : Container(color: const Color(0xFF1A1A2E)),
                  ),
                  // Video fades in when the first frame is decoded (thumbnail stays visible until then)
                  if (_ready && _controller != null)
                    Positioned.fill(
                      child: AnimatedOpacity(
                        opacity: _hasFrame ? 1.0 : 0.0,
                        duration: const Duration(milliseconds: 200),
                        child: Video(
                          controller: _controller!,
                          fit: BoxFit.contain,
                          controls: NoVideoControls,
                        ),
                      ),
                    ),
                ],
              ),
            ),

            // Re-buffering indicator (only during actual stall, not initial load)
            if (_ready && _controller != null && _controller!.player.state.buffering)
              Positioned(bottom: 50, right: 12,
                child: SizedBox(width: 18, height: 18,
                  child: CircularProgressIndicator(color: kOrange, strokeWidth: 2))),

            // Global mute button — always visible (top-right corner, like Facebook)
            if (_ready && _controller != null)
              Positioned(top: 8, right: 8,
                child: GestureDetector(
                  onTap: () {
                    setState(() {
                      _globalMuted = !_globalMuted;
                      _controller!.player.setVolume(_globalMuted ? 0 : 100);
                    });
                  },
                  child: Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      color: Colors.black.withValues(alpha: 0.55),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      _globalMuted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
                      color: Colors.white, size: 16,
                    ),
                  ),
                )),

            // Controls overlay — visible only on tap, auto-hides after 3s (Facebook-style)
            if (_ready && _controller != null)
              Positioned.fill(
                child: AnimatedOpacity(
                  opacity: _showControls ? 1.0 : 0.0,
                  duration: const Duration(milliseconds: 200),
                  child: Stack(children: [
                    // Dim overlay
                    Positioned.fill(child: Container(color: Colors.black.withValues(alpha: 0.25))),
                    // Pause/play centre button
                    Positioned.fill(child: Center(child: Container(
                      padding: EdgeInsets.all(14),
                      decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.45), shape: BoxShape.circle),
                      child: Icon(_paused ? Icons.play_arrow_rounded : Icons.pause_rounded, color: Colors.white, size: 40)))),
                    // Bottom progress bar + duration
                    Positioned(bottom: 0, left: 0, right: 0,
                      child: Container(
                        padding: EdgeInsets.fromLTRB(10, 20, 10, 8),
                        decoration: BoxDecoration(gradient: LinearGradient(
                          begin: Alignment.topCenter, end: Alignment.bottomCenter,
                          colors: [Colors.transparent, Colors.black.withValues(alpha: 0.6)])),
                        child: Row(children: [
                          Expanded(child: StreamBuilder<Duration>(
                            stream: _controller!.player.stream.position,
                            builder: (ctx, snap) {
                              final pos = snap.data ?? Duration.zero;
                              final dur = _controller!.player.state.duration;
                              final progress = dur.inMilliseconds > 0
                                  ? (pos.inMilliseconds / dur.inMilliseconds).clamp(0.0, 1.0)
                                  : 0.0;
                              return GestureDetector(
                                onHorizontalDragUpdate: (details) {
                                  final box = ctx.findRenderObject() as RenderBox?;
                                  if (box == null || dur.inMilliseconds == 0) return;
                                  final ratio = (details.localPosition.dx / box.size.width).clamp(0.0, 1.0);
                                  _controller!.player.seek(Duration(milliseconds: (ratio * dur.inMilliseconds).round()));
                                },
                                child: ClipRRect(
                                  borderRadius: BorderRadius.circular(2),
                                  child: LinearProgressIndicator(
                                    value: progress,
                                    color: kOrange,
                                    backgroundColor: Colors.white12,
                                    minHeight: 3,
                                  ),
                                ),
                              );
                            },
                          )),
                          SizedBox(width: 8),
                          Text(_formatDuration(_controller!.player.state.duration),
                            style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
                        ]))),
                  ]),
                ),
              ),
          ],
        ),
      );

    return VisibilityDetector(
      key: _key,
      onVisibilityChanged: _onVisibilityChanged,
      child: _ready && _controller != null
          ? VideoAdOverlay(
              mainController: _controller!.player,
              onAdStart: () => _pool.pause(_previewUrl),
              onAdEnd:   () {
                _pool.reactivate(_previewUrl);
                _controller?.player.setVolume(_globalMuted ? 0 : 100);
              },
              child: videoContent)
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
  VideoPlayerController? _ctrl;
  bool _initialized = false;

  bool get _playing => _ctrl?.value.isPlaying ?? false;
  Duration get _duration => _ctrl?.value.duration ?? Duration.zero;
  Duration get _position => _ctrl?.value.position ?? Duration.zero;

  @override
  void initState() {
    super.initState();
    _ctrl = VideoPlayerController.networkUrl(Uri.parse(widget.url));
    _ctrl!.initialize().then((_) {
      if (mounted) setState(() => _initialized = true);
      _ctrl!.addListener(_onUpdate);
    }).catchError((e) {
      debugPrint('[AudioCard] init error: $e');
    });
  }

  void _onUpdate() { if (mounted) setState(() {}); }

  @override
  void dispose() {
    _ctrl?.removeListener(_onUpdate);
    _ctrl?.pause();
    _ctrl?.dispose();
    super.dispose();
  }

  void _toggle() {
    if (!_initialized || _ctrl == null) return;
    if (_playing) {
      _ctrl!.pause();
    } else {
      _ctrl!.play();
    }
    setState(() {});
  }

  String _fmt(Duration d) => '${d.inMinutes.toString().padLeft(2, '0')}:${(d.inSeconds % 60).toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    final progress = _duration.inMilliseconds > 0 ? _position.inMilliseconds / _duration.inMilliseconds : 0.0;
    return Container(
      margin: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      padding: EdgeInsets.all(14),
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
        SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Waveform-style bars
          SizedBox(height: 28, child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: List.generate(30, (i) {
              final barProgress = i / 30;
              final isActive = barProgress <= progress;
              final height = (8 + (i % 5) * 4.0 + (i % 3) * 3.0).clamp(6.0, 24.0);
              return Expanded(child: Container(
                margin: EdgeInsets.symmetric(horizontal: 0.5),
                height: height,
                decoration: BoxDecoration(
                  color: isActive ? kOrange : kOrange.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(2))));
            }))),
          SizedBox(height: 6),
          Row(children: [
            Text(_fmt(_position), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: kOrange)),
            Spacer(),
            Text(_fmt(_duration), style: TextStyle(fontSize: 11, color: context.colors.mutedText)),
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
      margin: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      padding: EdgeInsets.all(16),
      decoration: BoxDecoration(color: context.colors.surfaceBg, borderRadius: BorderRadius.circular(16),
        border: Border.all(color: context.colors.borderColor)),
      child: Row(children: [
        Container(width: 52, height: 52,
          decoration: BoxDecoration(color: _color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
          child: Icon(_icon, color: _color, size: 28)),
        SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(_fileName, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.bodyText), maxLines: 1, overflow: TextOverflow.ellipsis),
          SizedBox(height: 4),
          Container(padding: EdgeInsets.symmetric(horizontal: 8, vertical: 2),
            decoration: BoxDecoration(color: _color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
            child: Text(_ext, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: _color))),
        ])),
        SizedBox(width: 8),
        Column(children: [
          GestureDetector(
            onTap: () => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
            child: Container(width: 36, height: 36,
              decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
              child: Icon(Icons.download_rounded, color: kOrange, size: 20))),
          SizedBox(height: 6),
          GestureDetector(
            onTap: () => Navigator.push(context, MaterialPageRoute(
              builder: (_) => Scaffold(
                appBar: AppBar(title: Text(_fileName, style: TextStyle(fontSize: 14))),
                body: WebViewWidget(controller: WebViewController()..loadRequest(Uri.parse(url)))))),
            child: Container(width: 36, height: 36,
              decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(10)),
              child: Icon(Icons.visibility_rounded, color: context.colors.mutedText, size: 20))),
        ]),
      ]),
    );
  }
}

// ── Quick copyright claim from feed post options ───────────────────────────
class _CopyrightQuickClaim extends StatefulWidget {
  final int postId;
  const _CopyrightQuickClaim({required this.postId});

  @override
  State<_CopyrightQuickClaim> createState() => _CopyrightQuickClaimState();
}

class _CopyrightQuickClaimState extends State<_CopyrightQuickClaim> {
  final _nameCtrl  = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _descCtrl  = TextEditingController();
  bool _loading    = false;

  @override
  void dispose() {
    _nameCtrl.dispose(); _emailCtrl.dispose(); _descCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 20, bottom: MediaQuery.of(context).viewInsets.bottom + 24),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2)))),
            const SizedBox(height: 16),
            const Text('File Copyright Claim', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF111827))),
            const SizedBox(height: 4),
            Text('Post #${widget.postId}', style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
            const SizedBox(height: 18),
            _buildField(_nameCtrl, 'Your Name', 'Legal name'),
            const SizedBox(height: 12),
            _buildField(_emailCtrl, 'Your Email', 'Contact email', type: TextInputType.emailAddress),
            const SizedBox(height: 12),
            _buildField(_descCtrl, 'Work Description', 'Describe your original work...', lines: 3),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF3B82F6),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                onPressed: _loading ? null : _submit,
                child: _loading
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Submit Claim', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildField(TextEditingController ctrl, String label, String hint, {int lines = 1, TextInputType type = TextInputType.text}) =>
    Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: Color(0xFF374151))),
        const SizedBox(height: 6),
        TextField(
          controller: ctrl, maxLines: lines, keyboardType: type,
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: const TextStyle(color: Color(0xFFD1D5DB), fontSize: 13),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFEEF0F6))),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFFEEF0F6))),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: Color(0xFF3B82F6))),
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          ),
        ),
      ],
    );

  Future<void> _submit() async {
    if (_nameCtrl.text.trim().isEmpty || _emailCtrl.text.trim().isEmpty || _descCtrl.text.trim().length < 20) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Please fill in all fields (description min 20 chars)')));
      return;
    }
    setState(() => _loading = true);
    try {
      await CommunityRepository().submitCopyrightClaim(
        reportedType: 'post', reportedId: widget.postId,
        claimantName: _nameCtrl.text.trim(), claimantEmail: _emailCtrl.text.trim(),
        workDescription: _descCtrl.text.trim(),
      );
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Copyright claim submitted'), backgroundColor: Color(0xFF3B82F6)),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString()), backgroundColor: const Color(0xFFDC2626)));
      }
    }
  }
}

// ── Public post detail screen — reuses _PostCard for full feed-identical UX ──
// Used from profile, saved, liked, and any other non-feed context.
class PostDetailScreen extends StatelessWidget {
  final CommunityPost post;
  const PostDetailScreen({super.key, required this.post});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: AppBar(
        backgroundColor: c.cardBg,
        foregroundColor: c.navyText,
        elevation: 0,
        title: Text('Post',
            style: TextStyle(color: c.navyText, fontWeight: FontWeight.w700, fontSize: 16)),
      ),
      body: SingleChildScrollView(
        physics: const BouncingScrollPhysics(parent: AlwaysScrollableScrollPhysics()),
        child: Column(children: [
          const SizedBox(height: 8),
          _PostCard(
            post: post,
            onDelete: () => Navigator.pop(context),
          ),
          const SizedBox(height: 24),
        ]),
      ),
    );
  }
}
