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
import '../services/video_preloader.dart';
import '../services/video_engine.dart';
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
    // Poll for new posts every 30s when scrolled down
    _pollTimer = Timer.periodic(const Duration(seconds: 30), (_) => _checkNewPosts());
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
    _tabCtrl.dispose();
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
          color: const Color(0xFFF0F2F5),
          shape: BoxShape.circle,
        ),
        child: Stack(
          children: [
            Center(child: Icon(icon, color: const Color(0xFF1A1B2E), size: 20)),
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
    return RefreshIndicator(
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

          const SizedBox(height: 8),

          // Feed posts
          feedState.when(
            data: (posts) {
              if (posts.isEmpty) return const _EmptyFeed();
              return Column(
                children: [
                  ...posts.map((p) => _PostCard(post: p,
                    onDelete: () { ref.read(communityRepoProvider).deletePost(p.id); ref.read(communityFeedProvider.notifier).removePost(p.id); },
                  )),
                  const SizedBox(height: 80),
                ],
              );
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
              backgroundColor: _following ? const Color(0xFFF0F2F5) : kOrange,
              foregroundColor: _following ? const Color(0xFF6B7280) : Colors.white,
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

  bool _adInitStarted = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  void _initAdVideo() {
    if (_adInitStarted || widget.post.adType != 'video' || widget.post.adMediaUrl == null) return;
    _adInitStarted = true;
    final ctrl = VideoPlayerController.networkUrl(Uri.parse(widget.post.adMediaUrl!),
      httpHeaders: const {'Connection': 'keep-alive', 'Accept-Encoding': 'identity'});
    ctrl.initialize().then((_) {
      if (!mounted) { ctrl.dispose(); return; }
      ctrl.setLooping(true); ctrl.setVolume(1); ctrl.pause();
      setState(() { _vCtrl = ctrl; _videoReady = true; });
    }).catchError((_) { ctrl.dispose(); });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (_vCtrl == null) return;
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) _vCtrl!.pause();
  }

  @override
  void dispose() { WidgetsBinding.instance.removeObserver(this); _vCtrl?.pause(); _vCtrl?.dispose(); super.dispose(); }

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
      key: ValueKey('ad_${p.id}'),
      onVisibilityChanged: (info) {
        if (info.visibleFraction > 0.5) {
          if (!_adInitStarted) _initAdVideo();
          if (_vCtrl != null && _videoReady) _vCtrl!.play();
        } else {
          if (_vCtrl != null && _videoReady) _vCtrl!.pause();
        }
      },
      child: Container(
        margin: const EdgeInsets.symmetric(vertical: 4),
        color: Colors.white,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // â”€â”€ Header (Facebook style) â”€â”€
          Padding(padding: const EdgeInsets.fromLTRB(14, 12, 14, 10), child: Row(children: [
            Container(
              decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: kOrange.withValues(alpha: 0.3), width: 2)),
              child: p.adPage?['avatar'] != null
                  ? CircleNetImage(url: p.adPage!['avatar'], size: 40, fallbackText: p.adPage?['name'] ?? '')
                  : Container(width: 40, height: 40, decoration: const BoxDecoration(color: Color(0xFFF0F2F5), shape: BoxShape.circle),
                      child: const Icon(Icons.storefront_rounded, color: kOrange, size: 20))),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Flexible(child: Text(p.adPage?['name'] ?? 'Sponsored', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF1A1B2E)))),
                const SizedBox(width: 6),
                Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                  decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(3)),
                  child: const Text('Sponsored', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800))),
              ]),
              const SizedBox(height: 2),
              Row(children: [
                Icon(Icons.public_rounded, size: 11, color: Colors.grey[400]),
                const SizedBox(width: 3),
                Text('Promoted', style: TextStyle(color: Colors.grey[400], fontSize: 11)),
              ]),
            ])),
            GestureDetector(onTap: () {},
              child: Icon(Icons.more_horiz_rounded, color: Colors.grey[400], size: 22)),
          ])),

          // â”€â”€ Content text â”€â”€
          if (p.adTitle != null) Padding(padding: const EdgeInsets.fromLTRB(14, 0, 14, 4),
            child: Text(p.adTitle!, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFF1A1B2E), height: 1.25))),
          if (p.content != null && p.content!.isNotEmpty) Padding(padding: const EdgeInsets.fromLTRB(14, 0, 14, 10),
            child: Text(p.content!, style: const TextStyle(color: Color(0xFF4B5563), fontSize: 14, height: 1.35))),

          // â”€â”€ Media â”€â”€
          GestureDetector(
            onTap: _onAdTap,
            child: p.adType == 'video'
                ? Stack(children: [
                    _videoReady && _vCtrl != null
                        ? AspectRatio(aspectRatio: _vCtrl!.value.aspectRatio.clamp(0.5, 2.0), child: VideoPlayer(_vCtrl!))
                        : const SizedBox.shrink(),
                    // Mute button
                    if (_videoReady) Positioned(bottom: 12, right: 12,
                      child: GestureDetector(
                        onTap: () { setState(() { _muted = !_muted; _vCtrl?.setVolume(_muted ? 0 : 1); }); },
                        child: Container(width: 32, height: 32,
                          decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.5), shape: BoxShape.circle),
                          child: Icon(_muted ? Icons.volume_off_rounded : Icons.volume_up_rounded, color: Colors.white, size: 16)))),
                  ])
                : (p.adMediaUrl != null ? NetImage(url: p.adMediaUrl, fit: BoxFit.cover, width: double.infinity) : const SizedBox.shrink())),

          // â”€â”€ CTA bar (Instagram style) â”€â”€
          if (p.adCtaText != null || p.adCtaUrl != null) GestureDetector(
            onTap: _onAdTap,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(color: const Color(0xFFF8F9FA),
                border: Border(top: BorderSide(color: Colors.grey[200]!))),
              child: Row(children: [
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  if (p.adCtaUrl != null) Text(p.adCtaUrl!.replaceAll(RegExp(r'https?://'), ''),
                    style: TextStyle(color: Colors.grey[500], fontSize: 11), maxLines: 1, overflow: TextOverflow.ellipsis),
                  if (p.adTitle != null) Text(p.adTitle!, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF1A1B2E)),
                    maxLines: 1, overflow: TextOverflow.ellipsis),
                ])),
                const SizedBox(width: 10),
                Container(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(6)),
                  child: Text(p.adCtaText ?? 'Learn More', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13))),
              ]),
            ),
          ),

          // â”€â”€ Engagement bar (like regular posts) â”€â”€
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

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Column(
        children: [
          Row(
            children: [
              CircleNetImage(url: avatar, size: 40),
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
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFF0F2F5),
                      borderRadius: BorderRadius.circular(24),
                    ),
                    child: const Text("What's on your mind?",
                        style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 14)),
                  ),
                ),
              ),
            ],
          ),
          const Divider(height: 16, color: Color(0xFFF0F2F5)),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceEvenly,
            children: [
              _PostTypeBtn(icon: Icons.photo_library_rounded, label: 'Photo', color: const Color(0xFF45BD62), type: 'image'),
              Container(width: 1, height: 18, color: const Color(0xFFE5E7EB)),
              _PostTypeBtn(icon: Icons.videocam_rounded, label: 'Video', color: const Color(0xFFF97316), type: 'video'),
              Container(width: 1, height: 18, color: const Color(0xFFE5E7EB)),
              _PostTypeBtn(icon: Icons.bar_chart_rounded, label: 'Poll', color: const Color(0xFF8B5CF6), type: 'poll'),
              Container(width: 1, height: 18, color: const Color(0xFFE5E7EB)),
              _PostTypeBtn(icon: Icons.emoji_emotions_rounded, label: 'Feeling', color: const Color(0xFFF59E0B), type: 'text'),
            ],
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
            Text(label, style: const TextStyle(color: Color(0xFF374151), fontSize: 12, fontWeight: FontWeight.w600)),
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
          decoration: const BoxDecoration(color: Color(0xFFF0F2F5), shape: BoxShape.circle),
          child: const Icon(Icons.dynamic_feed_rounded, size: 40, color: Color(0xFFD1D5DB)),
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

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      color: Colors.white,
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
                      const Icon(Icons.verified_rounded, color: Color(0xFF1877F2), size: 14),
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

        // Content
        if (p.content != null && p.content!.isNotEmpty)
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
            child: Text(p.content!, style: const TextStyle(color: Color(0xFF1A1B2E), fontSize: 15, height: 1.4)),
          ),

        // Shared post preview
        if (p.type == 'share' && p.sharedPost != null)
          Container(
            margin: const EdgeInsets.fromLTRB(12, 4, 12, 8),
            decoration: BoxDecoration(border: Border.all(color: const Color(0xFFE5E7EB)), borderRadius: BorderRadius.circular(12)),
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
        if (p.media.isNotEmpty) _MediaGrid(media: p.media),

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
                  decoration: BoxDecoration(borderRadius: BorderRadius.circular(10), border: Border.all(color: const Color(0xFFE5E7EB))),
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

        // Engagement counts â€” Facebook style
        if (p.likesCount > 0 || p.commentsCount > 0 || p.sharesCount > 0 || p.viewsCount > 0)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            child: Row(children: [
              if (p.likesCount > 0) Expanded(child: Row(children: [
                // Reaction icons (stacked)
                SizedBox(width: 36, height: 20, child: Stack(children: [
                  Container(width: 20, height: 20, decoration: const BoxDecoration(
                    color: Color(0xFF1877F2), shape: BoxShape.circle,
                    border: Border.fromBorderSide(BorderSide(color: Colors.white, width: 1.5))),
                    child: const Icon(Icons.thumb_up_rounded, size: 11, color: Colors.white)),
                  if (p.likesCount > 1) Positioned(left: 14, child: Container(width: 20, height: 20,
                    decoration: const BoxDecoration(color: Color(0xFFED4956), shape: BoxShape.circle,
                      border: Border.fromBorderSide(BorderSide(color: Colors.white, width: 1.5))),
                    child: const Icon(Icons.favorite_rounded, size: 11, color: Colors.white))),
                ])),
                const SizedBox(width: 4),
                Flexible(child: Text(
                  p.likesCount >= 1000 ? '${(p.likesCount / 1000).toStringAsFixed(1)}K' : '${p.likesCount}',
                  style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13),
                  overflow: TextOverflow.ellipsis)),
              ])),
              if (p.likesCount == 0) const Spacer(),
              if (p.commentsCount > 0)
                Padding(padding: const EdgeInsets.only(left: 8),
                  child: Text('${p.commentsCount} ${p.commentsCount == 1 ? 'comment' : 'comments'}',
                    style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13))),
              if (p.sharesCount > 0)
                Padding(padding: const EdgeInsets.only(left: 8),
                  child: Text('${p.sharesCount} ${p.sharesCount == 1 ? 'share' : 'shares'}',
                    style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13))),
              if (p.viewsCount > 0)
                Padding(padding: const EdgeInsets.only(left: 8),
                  child: Text('${p.viewsCount >= 1000 ? '${(p.viewsCount / 1000).toStringAsFixed(1)}K' : p.viewsCount} ${p.viewsCount == 1 ? 'view' : 'views'}',
                    style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13))),
            ]),
          ),

        const Divider(height: 1, color: Color(0xFFF0F2F5)),

        // Action buttons
        Padding(
          padding: const EdgeInsets.symmetric(vertical: 4),
          child: Row(children: [
            _ActionBtn(
              icon: _myReaction != null ? Icons.thumb_up_rounded : Icons.thumb_up_alt_outlined,
              label: _myReaction != null ? _reactionEmoji(_myReaction!) : 'Like',
              color: _myReaction != null ? kOrange : const Color(0xFF6B7280),
              onTap: () => setState(() => _showReactions = !_showReactions),
              onLongPress: () => _react('like'),
            ),
            _ActionBtn(icon: Icons.chat_bubble_outline_rounded, label: 'Comment', color: const Color(0xFF6B7280), onTap: () => showCommentsSheet(context, p.id, initialCount: p.commentsCount)),
            _ActionBtn(icon: Icons.share_outlined, label: 'Share', color: const Color(0xFF6B7280), onTap: () => _showShareDialog()),
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
        Container(padding: const EdgeInsets.all(10), decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(8)),
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

class _ActionBtn extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  final VoidCallback? onLongPress;
  const _ActionBtn({required this.icon, required this.label, required this.color, required this.onTap, this.onLongPress});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        onLongPress: onLongPress,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
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
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(30),
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

class _MediaGrid extends StatelessWidget {
  final List<CommunityPostMedia> media;
  const _MediaGrid({required this.media});

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
        child: _MediaItem(m: m, height: m.type == 'video' ? 0 : 0));
    }
    if (media.length == 2) {
      return SizedBox(height: 200, child: Row(children: [
        for (var i = 0; i < 2; i++)
          Expanded(child: Padding(padding: EdgeInsets.only(right: i == 0 ? 2 : 0),
            child: GestureDetector(
              onTap: media[i].type == 'image' ? () => _openGallery(context, i) : null,
              child: _MediaItem(m: media[i], height: 200)))),
      ]));
    }
    final extra = media.length - 3;
    return Column(children: [
      GestureDetector(
        onTap: media[0].type == 'image' ? () => _openGallery(context, 0) : null,
        child: _MediaItem(m: media[0], height: 220)),
      const SizedBox(height: 2),
      SizedBox(height: 120, child: Row(children: [
        Expanded(child: GestureDetector(
          onTap: media[1].type == 'image' ? () => _openGallery(context, 1) : null,
          child: _MediaItem(m: media[1], height: 120))),
        const SizedBox(width: 2),
        Expanded(child: GestureDetector(
          onTap: () => _openGallery(context, 2),
          child: Stack(children: [
            _MediaItem(m: media[2], height: 120),
            if (extra > 0) Positioned.fill(child: Container(
              color: Colors.black45,
              child: Center(child: Text('+$extra', style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w800))),
            )),
          ]))),
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
  late VideoPlayerController _ctrl;
  bool _ready = false;

  @override
  void initState() {
    super.initState();
    _ctrl = VideoPlayerController.networkUrl(Uri.parse(widget.url))
      ..initialize().then((_) {
        if (mounted) { setState(() => _ready = true); _ctrl.play(); }
      });
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      extendBodyBehindAppBar: true,
      body: GestureDetector(
        onTap: _ready ? () { _ctrl.value.isPlaying ? _ctrl.pause() : _ctrl.play(); setState(() {}); } : null,
        onVerticalDragEnd: (d) { if (d.primaryVelocity != null && d.primaryVelocity! > 300) Navigator.pop(context); },
        child: _ready
          ? Stack(fit: StackFit.expand, children: [
              Center(child: AspectRatio(aspectRatio: _ctrl.value.aspectRatio, child: VideoPlayer(_ctrl))),
              if (!_ctrl.value.isPlaying)
                const Center(child: Icon(Icons.play_circle_fill_rounded, color: Colors.white70, size: 64)),
              Positioned(bottom: 30, left: 16, right: 16,
                child: VideoProgressIndicator(_ctrl, allowScrubbing: true,
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
  const _MediaItem({required this.m, required this.height});

  @override
  ConsumerState<_MediaItem> createState() => _MediaItemState();
}

class _MediaItemState extends ConsumerState<_MediaItem> with WidgetsBindingObserver {
  VideoPlayerController? _ctrl;
  bool _ready = false;
  bool _paused = false;
  bool _visible = false;
  double? _aspectRatio;
  final _key = UniqueKey();
  final _engine = VideoEngine.instance;

  bool get _isVideo => widget.m.type == 'video';
  bool get _isAudio => widget.m.type == 'audio';
  bool get _isDocument => widget.m.type == 'document';

  String get _videoUrl => widget.m.hlsUrl ?? widget.m.url;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      _engine.pause(_videoUrl);
    }
  }

  void _onVideoUpdate() { if (mounted) setState(() {}); }

  Future<void> _initVideo() async {
    final cached = _engine.getController(_videoUrl);
    if (cached != null && cached.value.isInitialized) {
      cached.addListener(_onVideoUpdate);
      _aspectRatio ??= cached.value.aspectRatio;
      if (mounted) setState(() { _ctrl = cached; _ready = true; });
      if (_visible && !_paused) _engine.activate(_videoUrl);
      return;
    }
    final ctrl = await _engine.preload(_videoUrl);
    if (ctrl != null && mounted) {
      ctrl.addListener(_onVideoUpdate);
      _aspectRatio ??= ctrl.value.aspectRatio;
      setState(() { _ctrl = ctrl; _ready = true; });
      if (_visible && !_paused) _engine.activate(_videoUrl);
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _ctrl?.removeListener(_onVideoUpdate);
    super.dispose();
  }

  void _onVisibilityChanged(VisibilityInfo info) {
    final wasVisible = _visible;
    _visible = info.visibleFraction > 0.4;
    if (_visible && !wasVisible) {
      if (_ready && !_paused) _engine.activate(_videoUrl);
      else if (!_ready) _initVideo();
    } else if (!_visible && wasVisible) {
      _engine.pause(_videoUrl);
    }
  }

  void _togglePause() {
    if (!_ready || _ctrl == null) return;
    setState(() => _paused = !_paused);
    _paused ? _engine.pause(_videoUrl) : _engine.activate(_videoUrl);
  }

  String _fmtD(Duration d) => '${d.inMinutes.toString().padLeft(2, '0')}:${(d.inSeconds % 60).toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    if (_isAudio) return _AudioPlayerCard(url: widget.m.url);
    if (_isDocument) return _DocumentCard(url: widget.m.url);

    if (!_isVideo) {
      if (widget.height == 0) {
        return NetImage(url: widget.m.url, fit: BoxFit.fitWidth, width: double.infinity,
          placeholder: Container(color: const Color(0xFFE5E7EB), height: 200),
          errorWidget: Container(color: const Color(0xFFE5E7EB), height: 200, child: const Icon(Icons.broken_image_rounded, color: Color(0xFF9CA3AF), size: 32)));
      }
      return SizedBox(height: widget.height, width: double.infinity,
        child: NetImage(url: widget.m.url, fit: BoxFit.cover,
          placeholder: Container(color: const Color(0xFFE5E7EB)),
          errorWidget: Container(color: const Color(0xFFE5E7EB), child: const Icon(Icons.broken_image_rounded, color: Color(0xFF9CA3AF), size: 32))));
    }

    return VisibilityDetector(
      key: _key,
      onVisibilityChanged: _onVisibilityChanged,
      child: GestureDetector(
        onTap: _ready ? _togglePause : null,
        onDoubleTap: _ready ? () {
          _engine.pause(_videoUrl);
          Navigator.push(context, MaterialPageRoute(builder: (_) => _SimpleVideoPlayer(url: _videoUrl)));
        } : null,
        child: Stack(children: [
          Container(
            color: const Color(0xFF1A1B2E),
            width: double.infinity,
            child: AspectRatio(
              aspectRatio: (_aspectRatio ?? 9 / 16).clamp(0.5, 2.0),
              child: _ready && _ctrl != null
                ? VideoPlayer(_ctrl!)
                : widget.m.thumbnail != null
                  ? NetImage(url: widget.m.thumbnail!, fit: BoxFit.cover)
                  : const SizedBox(),
            ),
          ),
          // Pause overlay
          if (_paused && _ready)
            Positioned.fill(child: Center(child: Container(padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.4), shape: BoxShape.circle),
              child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 40)))),
          // Controls bar
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
                Text(_fmtD(_ctrl!.value.duration), style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
                const SizedBox(width: 6),
                GestureDetector(onTap: () { setState(() { _ctrl!.setVolume(_ctrl!.value.volume > 0 ? 0 : 1); }); },
                  child: Icon(_ctrl!.value.volume > 0 ? Icons.volume_up_rounded : Icons.volume_off_rounded, color: Colors.white, size: 18)),
              ]))),
        ]),
      ),
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
      decoration: BoxDecoration(color: const Color(0xFFF9FAFB), borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB))),
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