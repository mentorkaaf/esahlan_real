import '../../../../core/widgets/network_image_widget.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../../../core/widgets/app_shimmer.dart';
import 'package:video_player/video_player.dart';
import 'package:visibility_detector/visibility_detector.dart';
import '../../data/models/community_models.dart';
import '../providers/community_provider.dart';
import '../widgets/stories_bar.dart';
import 'community_shell.dart';
import 'community_notifications_screen.dart';
import 'create_post_screen.dart';
import '../widgets/comments_sheet.dart';
import '../widgets/video_ad_overlay.dart';
import '../services/ad_preloader.dart';
import '../services/video_preloader.dart';
import 'business_page_detail_screen.dart';
import 'community_search_screen.dart';

class CommunityFeedScreen extends ConsumerStatefulWidget {
  const CommunityFeedScreen({super.key});

  @override
  ConsumerState<CommunityFeedScreen> createState() => _CommunityFeedScreenState();
}

class _CommunityFeedScreenState extends ConsumerState<CommunityFeedScreen>
    with SingleTickerProviderStateMixin {
  final _scrollCtrl = ScrollController();
  late TabController _tabCtrl;

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 4, vsync: this);
    // Preload ads silently in background
    AdPreloader().preload();
    _scrollCtrl.addListener(() {
      if (_scrollCtrl.position.pixels >= _scrollCtrl.position.maxScrollExtent - 300) {
        ref.read(communityFeedProvider.notifier).load();
      }
    });
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    _tabCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final feedState = ref.watch(communityFeedProvider);
    final storiesState = ref.watch(communityStoriesProvider);
    final unread = ref.watch(communityUnreadCountProvider);

    return Scaffold(
      
      body: NestedScrollView(
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
              // Preload all video URLs silently (posts + ads)
              final videoUrls = <String>[];
              for (final p in posts) {
                if (p.isAd && p.adType == 'video' && p.adMediaUrl != null) videoUrls.add(p.adMediaUrl!);
                if (p.isVideo && p.media.isNotEmpty) videoUrls.add(p.media.first.url);
              }
              if (videoUrls.isNotEmpty) VideoPreloader().preloadUrls(videoUrls.take(6).toList());
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

class _TrendingTab extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final exploreState = ref.watch(communityExploreProvider);
    return RefreshIndicator(
      color: kOrange,
      onRefresh: () => ref.read(communityExploreProvider.notifier).refresh(),
      child: exploreState.when(
        data: (posts) {
          if (posts.isEmpty) return const _EmptyTab(message: 'No trending posts yet', icon: Icons.trending_up_rounded);
          return ListView(
            padding: const EdgeInsets.only(bottom: 80),
            children: posts.map((p) => _PostCard(post: p, onDelete: () {})).toList(),
          );
        },
        loading: () => const Padding(padding: EdgeInsets.all(16), child: ShimmerPostList(count: 3)),
        error: (e, _) => const _EmptyTab(message: 'No trending posts yet', icon: Icons.trending_up_rounded),
      ),
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
              const Text('  ·  ', style: TextStyle(color: Color(0xFFD1D5DB))),
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

// ── Ad Card — video auto-plays, image loads instantly ────────────────────────
class _AdCard extends ConsumerStatefulWidget {
  final CommunityPost post;
  const _AdCard({required this.post});
  @override
  ConsumerState<_AdCard> createState() => _AdCardState();
}

class _AdCardState extends ConsumerState<_AdCard> with WidgetsBindingObserver {
  VideoPlayerController? _vCtrl;
  bool _videoReady = false;
  bool _initStarted = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  void _initVideo() {
    if (_initStarted || widget.post.adType != 'video' || widget.post.adMediaUrl == null) return;
    _initStarted = true;
    final url = widget.post.adMediaUrl!;

    // Try preloaded controller (instant, 0ms)
    final preloaded = VideoPreloader().get(url);
    if (preloaded != null && preloaded.value.isInitialized) {
      preloaded.setLooping(true);
      preloaded.setVolume(1);
      preloaded.play();
      setState(() { _vCtrl = preloaded; _videoReady = true; });
      return;
    }

    // Fallback: load fresh
    final ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
    ctrl.initialize().then((_) {
      if (!mounted) { ctrl.dispose(); return; }
      ctrl.setLooping(true);
      ctrl.setVolume(1);
      ctrl.play();
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

  @override
  Widget build(BuildContext context) {
    final p = widget.post;
    return VisibilityDetector(
      key: ValueKey('ad_${p.id}'),
      onVisibilityChanged: (info) {
        if (info.visibleFraction > 0.5) {
          if (!_initStarted) _initVideo();
          if (_vCtrl != null && _videoReady && !_vCtrl!.value.isPlaying) _vCtrl!.play();
        } else {
          if (_vCtrl != null && _vCtrl!.value.isPlaying) _vCtrl!.pause();
        }
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 8), color: Colors.white,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Header
          Padding(padding: const EdgeInsets.fromLTRB(12, 10, 12, 6), child: Row(children: [
            if (p.adPage?['avatar'] != null)
              CircleNetImage(url: p.adPage!['avatar'], size: 32, fallbackText: p.adPage?['name'] ?? 'Ad')
            else Container(width: 32, height: 32, decoration: const BoxDecoration(color: Color(0xFFF0F2F5), shape: BoxShape.circle),
              child: const Icon(Icons.campaign_rounded, color: kOrange, size: 16)),
            const SizedBox(width: 8),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(p.adPage?['name'] ?? 'Sponsored', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF1A1B2E))),
              const Text('Sponsored', style: TextStyle(color: kOrange, fontSize: 11, fontWeight: FontWeight.w600)),
            ])),
            Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2), decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(4)),
              child: const Text('AD', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w900, letterSpacing: 1))),
          ])),

          if (p.adTitle != null) Padding(padding: const EdgeInsets.fromLTRB(12, 0, 12, 4),
            child: Text(p.adTitle!, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF1A1B2E)))),
          if (p.content != null && p.content!.isNotEmpty) Padding(padding: const EdgeInsets.fromLTRB(12, 0, 12, 6),
            child: Text(p.content!, style: const TextStyle(color: Color(0xFF374151), fontSize: 13))),

          // Media
          if (p.adMediaUrl != null) GestureDetector(
            onTap: () { if (p.id > 0) ref.read(communityRepoProvider).trackAdClick(p.id); },
            child: p.adType == 'video'
                ? Container(color: const Color(0xFF1A1B2E),
                    child: _videoReady && _vCtrl != null
                        ? AspectRatio(aspectRatio: _vCtrl!.value.aspectRatio.clamp(0.5, 2.5), child: VideoPlayer(_vCtrl!))
                        : const AspectRatio(aspectRatio: 16 / 9, child: Center(child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: kOrange, strokeWidth: 2)))))
                : NetImage(url: p.adMediaUrl, fit: BoxFit.cover, width: double.infinity)),

          if (p.adCtaText != null) Padding(padding: const EdgeInsets.fromLTRB(12, 8, 12, 12),
            child: SizedBox(width: double.infinity, child: ElevatedButton(
              onPressed: () { if (p.id > 0) ref.read(communityRepoProvider).trackAdClick(p.id); },
              style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)), padding: const EdgeInsets.symmetric(vertical: 11)),
              child: Text(p.adCtaText!, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14))))),
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

// ── Post Card ──────────────────────────────────────────────────────────────────

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

    // ── Ad Card ──
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
                      const Text(' · ', style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 12)),
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
                  child: NetImage(url: (p.sharedPost!['media'] as List).first['url'], fit: BoxFit.cover, width: double.infinity)),
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

        // Counts row
        if (p.likesCount > 0 || p.commentsCount > 0 || p.sharesCount > 0 || p.viewsCount > 0)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            child: Row(children: [
              if (p.likesCount > 0) Row(children: [
                Container(
                  padding: const EdgeInsets.all(3),
                  decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
                  child: const Icon(Icons.thumb_up_rounded, size: 10, color: Colors.white),
                ),
                const SizedBox(width: 4),
                Text('${p.likesCount}', style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13)),
              ]),
              if (p.viewsCount > 0) ...[
                const SizedBox(width: 10),
                const Icon(Icons.visibility_outlined, size: 13, color: Color(0xFF6B7280)),
                const SizedBox(width: 3),
                Text('${p.viewsCount}', style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13)),
              ],
              const Spacer(),
              if (p.commentsCount > 0)
                Text('${p.commentsCount} Comments', style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13)),
              if (p.sharesCount > 0) ...[
                const SizedBox(width: 8),
                Text('${p.sharesCount} Shares', style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13)),
              ],
            ]),
          ),

        const Divider(height: 1, color: Color(0xFFF0F2F5)),

        // Action buttons
        Stack(
          children: [
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Row(children: [
                _ActionBtn(
                  icon: _myReaction != null ? Icons.thumb_up_rounded : Icons.thumb_up_alt_outlined,
                  label: 'Like',
                  color: _myReaction != null ? kOrange : const Color(0xFF6B7280),
                  onTap: () => _react('like'),
                  onLongPress: () => setState(() => _showReactions = true),
                ),
                _ActionBtn(icon: Icons.chat_bubble_outline_rounded, label: 'Comment', color: const Color(0xFF6B7280), onTap: () => showCommentsSheet(context, p.id, initialCount: p.commentsCount)),
                _ActionBtn(icon: Icons.share_outlined, label: 'Share', color: const Color(0xFF6B7280), onTap: () => _showShareDialog()),
              ]),
            ),
            // Reaction popup
            if (_showReactions)
              Positioned(
                left: 8, bottom: 44,
                child: _ReactionPicker(reactions: _reactions, onPick: _react, onDismiss: () => setState(() => _showReactions = false)),
              ),
          ],
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

class _ReactionPicker extends StatelessWidget {
  final List<Map<String, dynamic>> reactions;
  final void Function(String) onPick;
  final VoidCallback onDismiss;
  const _ReactionPicker({required this.reactions, required this.onPick, required this.onDismiss});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onDismiss,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(30),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.15), blurRadius: 12, offset: const Offset(0, 4))],
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: reactions.map((r) => GestureDetector(
            onTap: () => onPick(r['type'] as String),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: Text(r['emoji'] as String, style: const TextStyle(fontSize: 28)),
            ),
          )).toList(),
        ),
      ),
    );
  }
}

// ── Businesses Tab ─────────────────────────────────────────────────────────────

class _BusinessesTab extends StatelessWidget {
  const _BusinessesTab();

  @override
  Widget build(BuildContext context) => const BusinessPagesListWidget();
}

class _MediaGrid extends StatelessWidget {
  final List<CommunityPostMedia> media;
  const _MediaGrid({required this.media});

  @override
  Widget build(BuildContext context) {
    if (media.length == 1) {
      return _MediaItem(m: media[0], height: media[0].type == 'video' ? 0 : 0);
    }
    if (media.length == 2) {
      return Row(children: media.map((m) => Expanded(child: _MediaItem(m: m, height: 200))).toList());
    }
    return Column(children: [
      _MediaItem(m: media[0], height: 220),
      Row(children: media.skip(1).take(2).map((m) => Expanded(child: _MediaItem(m: m, height: 120))).toList()),
    ]);
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
  final _key = UniqueKey();

  bool get _isVideo => widget.m.type == 'video';

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    if (_isVideo) _initVideo();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (_ctrl == null || !_ready) return;
    if (state == AppLifecycleState.paused || state == AppLifecycleState.inactive) {
      _ctrl!.pause();
    }
  }

  Future<void> _initVideo() async {
    final url = widget.m.url;
    if (url.isEmpty) return;

    // Try preloaded controller first (instant)
    final preloaded = VideoPreloader().get(url);
    if (preloaded != null && preloaded.value.isInitialized) {
      if (mounted) setState(() { _ctrl = preloaded; _ready = true; });
      return;
    }

    // Fallback: load fresh
    final ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
    try {
      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(1);
      if (mounted) setState(() { _ctrl = ctrl; _ready = true; });
    } catch (_) {
      ctrl.dispose();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _ctrl?.pause();
    _ctrl?.dispose();
    super.dispose();
  }

  void _onVisibilityChanged(VisibilityInfo info) {
    if (!_ready || _ctrl == null) return;
    _visible = info.visibleFraction > 0.5;
    if (_visible) {
      if (!_paused && !_ctrl!.value.isPlaying) _ctrl!.play();
    } else {
      if (_ctrl!.value.isPlaying) _ctrl!.pause();
    }
  }

  void _togglePause() {
    if (!_ready || _ctrl == null) return;
    setState(() => _paused = !_paused);
    _paused ? _ctrl!.pause() : _ctrl!.play();
  }

  String _formatDuration(Duration d) {
    final m = d.inMinutes;
    final s = d.inSeconds % 60;
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    if (!_isVideo) {
      // Single image: show full without cropping; multi-image grid uses fixed height
      if (widget.height == 0) {
        return NetImage(
          url: widget.m.url,
          fit: BoxFit.fitWidth,
          width: double.infinity,
          placeholder: Container(color: const Color(0xFFE5E7EB), height: 200),
          errorWidget: Container(
            color: const Color(0xFFE5E7EB), height: 200,
            child: const Icon(Icons.broken_image_rounded, color: Color(0xFF9CA3AF), size: 32),
          ),
        );
      }
      return SizedBox(
        height: widget.height,
        width: double.infinity,
        child: NetImage(
          url: widget.m.url,
          fit: BoxFit.cover,
          placeholder: Container(color: const Color(0xFFE5E7EB)),
          errorWidget: Container(
            color: const Color(0xFFE5E7EB),
            child: const Icon(Icons.broken_image_rounded, color: Color(0xFF9CA3AF), size: 32),
          ),
        ),
      );
    }

    final screenW = MediaQuery.of(context).size.width;
    double videoH = widget.height > 0 ? widget.height : 300;
    if (_ready && _ctrl != null) {
      final ar = _ctrl!.value.aspectRatio;
      videoH = (screenW / ar).clamp(200.0, screenW * 1.6);
    }

    final videoContent = GestureDetector(
        onTap: _ready ? _togglePause : null,
        onDoubleTap: _ready && _ctrl != null ? () {
          _ctrl!.pause();
          Navigator.push(context, MaterialPageRoute(builder: (_) => _VideoPlayerScreen(url: widget.m.url)));
        } : null,
        child: Stack(
          children: [
            Container(
              color: const Color(0xFF1A1B2E),
              width: double.infinity,
              height: videoH,
              child: _ready && _ctrl != null
                  ? FittedBox(fit: BoxFit.contain, child: SizedBox(
                      width: _ctrl!.value.size.width, height: _ctrl!.value.size.height, child: VideoPlayer(_ctrl!)))
                  : widget.m.thumbnail != null
                      ? NetImage(url: widget.m.thumbnail!, fit: BoxFit.contain,
                          placeholder: Container(color: const Color(0xFF1A1B2E)),
                          errorWidget: Container(color: const Color(0xFF1A1B2E)))
                      : const SizedBox(),
            ),
            if (!_ready)
              Positioned.fill(child: Center(child: CircularProgressIndicator(color: kOrange.withValues(alpha: 0.7), strokeWidth: 2))),
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

class _VideoPlayerScreen extends ConsumerStatefulWidget {
  final String url;
  const _VideoPlayerScreen({required this.url});
  @override
  ConsumerState<_VideoPlayerScreen> createState() => _VideoPlayerScreenState();
}

class _VideoPlayerScreenState extends ConsumerState<_VideoPlayerScreen> {
  late VideoPlayerController _mainCtrl;
  VideoPlayerController? _adCtrl;
  bool _mainReady = false;
  bool _adReady = false;
  bool _showingAd = true;
  int _skipCountdown = 5;
  bool _canSkip = false;
  Map<String, dynamic>? _adData;

  @override
  void initState() {
    super.initState();
    _mainCtrl = VideoPlayerController.networkUrl(Uri.parse(widget.url))
      ..initialize().then((_) { if (mounted) setState(() => _mainReady = true); }).catchError((_) {});
    _loadPrerollAd();
  }

  Future<void> _loadPrerollAd() async {
    try {
      final ad = await ref.read(communityRepoProvider).getPrerollAd();
      if (ad == null || ad['media_url'] == null) { _startMainVideo(); return; }
      setState(() => _adData = ad);
      final ctrl = VideoPlayerController.networkUrl(Uri.parse(ad['media_url']));
      await ctrl.initialize();
      ctrl.setLooping(false);
      ctrl.setVolume(1);
      if (!mounted) { ctrl.dispose(); return; }
      setState(() { _adCtrl = ctrl; _adReady = true; });
      ctrl.play();
      ctrl.addListener(_onAdProgress);
      _startSkipTimer();
    } catch (_) { _startMainVideo(); }
  }

  void _onAdProgress() {
    if (_adCtrl == null) return;
    if (_adCtrl!.value.position >= _adCtrl!.value.duration && _adCtrl!.value.duration > Duration.zero) {
      _skipAd();
    }
    if (mounted) setState(() {});
  }

  void _startSkipTimer() async {
    for (var i = 5; i > 0; i--) {
      await Future.delayed(const Duration(seconds: 1));
      if (!mounted || !_showingAd) return;
      setState(() => _skipCountdown = i - 1);
    }
    if (mounted) setState(() => _canSkip = true);
  }

  void _skipAd() {
    _adCtrl?.removeListener(_onAdProgress);
    _adCtrl?.pause();
    _adCtrl?.dispose();
    _adCtrl = null;
    setState(() { _showingAd = false; _adReady = false; });
    _startMainVideo();
  }

  void _startMainVideo() {
    setState(() => _showingAd = false);
    if (_mainReady) _mainCtrl.play();
  }

  @override
  void dispose() { _mainCtrl.dispose(); _adCtrl?.removeListener(_onAdProgress); _adCtrl?.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      extendBodyBehindAppBar: true,
      appBar: _showingAd ? null : AppBar(backgroundColor: Colors.transparent, elevation: 0, iconTheme: const IconThemeData(color: Colors.white)),
      body: _showingAd ? _buildAdPlayer() : _buildMainPlayer(),
    );
  }

  // ── Pre-roll Ad Player ──
  Widget _buildAdPlayer() {
    return Stack(children: [
      // Ad video
      Center(child: _adReady && _adCtrl != null
          ? AspectRatio(aspectRatio: _adCtrl!.value.aspectRatio.clamp(0.5, 2.5), child: VideoPlayer(_adCtrl!))
          : const CircularProgressIndicator(color: kOrange)),

      // "Ad" badge top-left
      Positioned(top: MediaQuery.of(context).padding.top + 12, left: 16,
        child: Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
          decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.6), borderRadius: BorderRadius.circular(6)),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.campaign_rounded, color: kOrange, size: 14),
            const SizedBox(width: 4),
            Text(_adData?['page']?['name'] ?? 'Ad', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
          ]))),

      // Ad progress bar at top
      if (_adReady && _adCtrl != null) Positioned(top: 0, left: 0, right: 0,
        child: VideoProgressIndicator(_adCtrl!, allowScrubbing: false,
          colors: const VideoProgressColors(playedColor: kOrange, backgroundColor: Colors.white24))),

      // Skip button bottom-right
      Positioned(bottom: 60, right: 16,
        child: GestureDetector(
          onTap: _canSkip ? _skipAd : null,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 300),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(
              color: _canSkip ? Colors.white : Colors.white.withValues(alpha: 0.2),
              borderRadius: BorderRadius.circular(8)),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Text(
                _canSkip ? 'Skip Ad' : 'Skip in $_skipCountdown',
                style: TextStyle(color: _canSkip ? const Color(0xFF1A1B2E) : Colors.white70,
                  fontWeight: FontWeight.w700, fontSize: 14)),
              if (_canSkip) ...[const SizedBox(width: 4), const Icon(Icons.skip_next_rounded, size: 18, color: Color(0xFF1A1B2E))],
            ]),
          ),
        )),

      // CTA button bottom-left
      if (_adData?['cta_text'] != null) Positioned(bottom: 60, left: 16,
        child: GestureDetector(
          onTap: () { if (_adData?['id'] != null) ref.read(communityRepoProvider).trackAdClick(_adData!['id']); },
          child: Container(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(8)),
            child: Text(_adData!['cta_text'], style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14))))),

      // Close button
      Positioned(top: MediaQuery.of(context).padding.top + 12, right: 16,
        child: GestureDetector(onTap: () => Navigator.pop(context),
          child: Container(padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.4), shape: BoxShape.circle),
            child: const Icon(Icons.close_rounded, color: Colors.white, size: 20)))),
    ]);
  }

  // ── Main Video Player ──
  Widget _buildMainPlayer() {
    if (!_mainReady) return const Center(child: CircularProgressIndicator(color: kOrange));
    return GestureDetector(
      onTap: () { _mainCtrl.value.isPlaying ? _mainCtrl.pause() : _mainCtrl.play(); setState(() {}); },
      child: Stack(children: [
        Center(child: AspectRatio(aspectRatio: _mainCtrl.value.aspectRatio.clamp(0.5, 2.5), child: VideoPlayer(_mainCtrl))),
        if (!_mainCtrl.value.isPlaying)
          Center(child: Container(padding: const EdgeInsets.all(16), decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.4), shape: BoxShape.circle),
            child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 48))),
        Positioned(bottom: 0, left: 0, right: 0, child: Container(
          padding: const EdgeInsets.fromLTRB(12, 24, 12, 30),
          decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter,
            colors: [Colors.transparent, Colors.black.withValues(alpha: 0.7)])),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            VideoProgressIndicator(_mainCtrl, allowScrubbing: true, colors: const VideoProgressColors(
              playedColor: kOrange, bufferedColor: Colors.white30, backgroundColor: Colors.white12)),
            const SizedBox(height: 8),
            Row(children: [
              GestureDetector(onTap: () { _mainCtrl.value.isPlaying ? _mainCtrl.pause() : _mainCtrl.play(); setState(() {}); },
                child: Icon(_mainCtrl.value.isPlaying ? Icons.pause_rounded : Icons.play_arrow_rounded, color: Colors.white, size: 28)),
              const SizedBox(width: 12),
              ValueListenableBuilder(valueListenable: _mainCtrl, builder: (_, v, __) =>
                Text('${_fmtD(v.position)} / ${_fmtD(v.duration)}', style: const TextStyle(color: Colors.white70, fontSize: 12))),
              const Spacer(),
              GestureDetector(onTap: () { _mainCtrl.setVolume(_mainCtrl.value.volume > 0 ? 0 : 1); setState(() {}); },
                child: Icon(_mainCtrl.value.volume > 0 ? Icons.volume_up_rounded : Icons.volume_off_rounded, color: Colors.white, size: 22)),
            ]),
          ]),
        )),
      ]),
    );
  }

  String _fmtD(Duration d) => '${d.inMinutes.toString().padLeft(2,'0')}:${(d.inSeconds%60).toString().padLeft(2,'0')}';
}
