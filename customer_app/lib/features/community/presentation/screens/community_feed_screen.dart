import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:timeago/timeago.dart' as timeago;
import 'package:video_player/video_player.dart';
import 'package:visibility_detector/visibility_detector.dart';
import '../../data/models/community_models.dart';
import '../providers/community_provider.dart';
import '../widgets/stories_bar.dart';
import 'community_shell.dart';
import 'community_notifications_screen.dart';
import 'groups_screen.dart';
import 'create_post_screen.dart';
import '../widgets/comments_sheet.dart';

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
      backgroundColor: kBg,
      body: NestedScrollView(
        controller: _scrollCtrl,
        headerSliverBuilder: (context, _) => [
          SliverAppBar(
            pinned: true,
            floating: true,
            elevation: 0,
            backgroundColor: Colors.white,
            title: RichText(
              text: const TextSpan(
                children: [
                  TextSpan(text: 'e', style: TextStyle(color: kOrange, fontWeight: FontWeight.w900, fontSize: 22)),
                  TextSpan(text: 'Sahlan.', style: TextStyle(color: Color(0xFF1A1B2E), fontWeight: FontWeight.w700, fontSize: 20)),
                ],
              ),
            ),
            actions: [
              _AppBarBtn(icon: Icons.search_rounded, onTap: () => context.push('/community/explore')),
              _AppBarBtn(
                icon: Icons.notifications_rounded,
                badge: unread > 0 ? unread : null,
                onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const CommunityNotificationsScreen())),
              ),
              _AppBarBtn(icon: Icons.shopping_basket_rounded, onTap: () {}),
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
                Tab(text: 'Following'),
                Tab(text: 'Trending'),
                Tab(text: 'Nearby'),
                Tab(text: 'Businesses'),
              ],
            ),
          ),
        ],
        body: TabBarView(
          controller: _tabCtrl,
          children: [
            _FeedTab(feedState: feedState, storiesState: storiesState),
            _TrendingTab(),
            const _EmptyTab(message: 'No nearby posts yet', icon: Icons.location_on_rounded),
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
              return Column(
                children: [
                  ...posts.map((p) => _PostCard(post: p,
                    onDelete: () => ref.read(communityFeedProvider.notifier).removePost(p.id),
                  )),
                  const SizedBox(height: 80),
                ],
              );
            },
            loading: () => const Center(
              child: Padding(
                padding: EdgeInsets.all(40),
                child: CircularProgressIndicator(color: kOrange),
              ),
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
        loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
        error: (e, _) => const _EmptyTab(message: 'No trending posts yet', icon: Icons.trending_up_rounded),
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
              CircleAvatar(
                radius: 20,
                backgroundColor: const Color(0xFFF0F2F5),
                backgroundImage: avatar != null ? CachedNetworkImageProvider(avatar) : null,
                child: avatar == null ? const Icon(Icons.person, color: Color(0xFF9CA3AF), size: 22) : null,
              ),
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
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      color: Colors.white,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Header
        Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
          child: Row(children: [
            GestureDetector(
              onTap: () => context.push('/community/profile/${p.user.id}'),
              child: CircleAvatar(
                radius: 20,
                backgroundColor: const Color(0xFFF0F2F5),
                backgroundImage: p.user.avatar != null ? CachedNetworkImageProvider(p.user.avatar!) : null,
                child: p.user.avatar == null ? Text(p.user.name[0].toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold)) : null,
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: GestureDetector(
                onTap: () => context.push('/community/profile/${p.user.id}'),
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

        // Media
        if (p.media.isNotEmpty) _MediaGrid(media: p.media),

        // Counts row
        if (p.likesCount > 0 || p.commentsCount > 0 || p.sharesCount > 0)
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
                _ActionBtn(icon: Icons.share_outlined, label: 'Share', color: const Color(0xFF6B7280), onTap: () {}),
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

  void _showOptions(BuildContext context) {
    showModalBottomSheet(
      context: context,
      builder: (_) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(
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

class _BusinessesTab extends ConsumerWidget {
  const _BusinessesTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(efoodRestaurantsProvider);

    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
      error: (_, __) => const _EmptyTab(message: 'Could not load restaurants', icon: Icons.restaurant_rounded),
      data: (restaurants) {
        if (restaurants.isEmpty) {
          return const _EmptyTab(message: 'No restaurants registered yet', icon: Icons.restaurant_rounded);
        }
        return RefreshIndicator(
          color: kOrange,
          onRefresh: () => ref.refresh(efoodRestaurantsProvider.future),
          child: ListView.separated(
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 12),
            itemCount: restaurants.length,
            separatorBuilder: (_, __) => const SizedBox(height: 12),
            itemBuilder: (_, i) => _RestaurantFeedCard(restaurant: restaurants[i]),
          ),
        );
      },
    );
  }
}

class _RestaurantFeedCard extends StatelessWidget {
  final Map<String, dynamic> restaurant;
  const _RestaurantFeedCard({required this.restaurant});

  @override
  Widget build(BuildContext context) {
    final r = restaurant;
    final isOpen = (r['is_open'] ?? r['is_active'] ?? 1) == 1 || r['is_open'] == true || r['is_active'] == true;
    final rating = r['rating'];
    final deliveryTime = r['delivery_time'];
    final deliveryFee = r['delivery_fee'];
    final feeNum = deliveryFee == null ? null : double.tryParse('$deliveryFee');
    final isFreeDelivery = feeNum == null || feeNum == 0;

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 10, offset: const Offset(0, 2))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Cover image
        Stack(children: [
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
            child: r['cover_image'] != null
                ? CachedNetworkImage(imageUrl: r['cover_image'], height: 140, width: double.infinity, fit: BoxFit.cover,
                    errorWidget: (_, __, ___) => Container(height: 140, color: const Color(0xFFF0F2F5), child: const Center(child: Text('🍽️', style: TextStyle(fontSize: 48)))))
                : Container(height: 140, color: const Color(0xFFF0F2F5), child: const Center(child: Text('🍽️', style: TextStyle(fontSize: 48)))),
          ),
          if (!isOpen)
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
              child: Container(height: 140, color: Colors.black54, child: const Center(child: Text('CLOSED', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18, letterSpacing: 2)))),
            ),
          Positioned(top: 10, left: 10,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(20)),
              child: const Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.fastfood_rounded, color: Colors.white, size: 12),
                SizedBox(width: 4),
                Text('eFood', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 11)),
              ]),
            ),
          ),
        ]),

        Padding(
          padding: const EdgeInsets.all(12),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              if (r['logo'] != null) ...[
                ClipRRect(
                  borderRadius: BorderRadius.circular(8),
                  child: CachedNetworkImage(imageUrl: r['logo'], width: 40, height: 40, fit: BoxFit.cover,
                      errorWidget: (_, __, ___) => Container(width: 40, height: 40, color: const Color(0xFFF0F2F5), child: const Icon(Icons.restaurant_rounded, color: kOrange, size: 20))),
                ),
                const SizedBox(width: 10),
              ],
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(r['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF1A1B2E)), maxLines: 1, overflow: TextOverflow.ellipsis),
                Text(r['cuisine_type'] ?? 'Restaurant', style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
              ])),
              if (rating != null)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(color: Colors.amber.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    const Icon(Icons.star_rounded, color: Colors.amber, size: 14),
                    const SizedBox(width: 3),
                    Text('$rating', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
                  ]),
                ),
            ]),
            const SizedBox(height: 8),
            Row(children: [
              if (deliveryTime != null) ...[
                const Icon(Icons.access_time_rounded, size: 14, color: Color(0xFF9CA3AF)),
                const SizedBox(width: 3),
                Text('$deliveryTime min', style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
                const SizedBox(width: 12),
              ],
              Icon(Icons.delivery_dining_rounded, size: 14, color: isFreeDelivery ? Colors.green : const Color(0xFF9CA3AF)),
              const SizedBox(width: 3),
              Text(isFreeDelivery ? 'Free delivery' : '\$${feeNum!.toStringAsFixed(2)} delivery',
                  style: TextStyle(fontSize: 12, color: isFreeDelivery ? Colors.green : const Color(0xFF6B7280), fontWeight: isFreeDelivery ? FontWeight.w600 : FontWeight.w400)),
            ]),
            const SizedBox(height: 10),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                  backgroundColor: isOpen ? kOrange : Colors.grey,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  elevation: 0,
                ),
                onPressed: isOpen ? () => context.push('/efood') : null,
                child: Text(isOpen ? 'Order Now' : 'Closed', style: const TextStyle(fontWeight: FontWeight.w700)),
              ),
            ),
          ]),
        ),
      ]),
    );
  }
}

class _MediaGrid extends StatelessWidget {
  final List<CommunityPostMedia> media;
  const _MediaGrid({required this.media});

  @override
  Widget build(BuildContext context) {
    if (media.length == 1) {
      return _MediaItem(m: media[0], height: 260);
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

class _MediaItem extends StatefulWidget {
  final CommunityPostMedia m;
  final double height;
  const _MediaItem({required this.m, required this.height});

  @override
  State<_MediaItem> createState() => _MediaItemState();
}

class _MediaItemState extends State<_MediaItem> {
  VideoPlayerController? _ctrl;
  bool _ready = false;
  bool _paused = false;
  final _key = UniqueKey();

  bool get _isVideo => widget.m.type == 'video';

  @override
  void initState() {
    super.initState();
    if (_isVideo) _initVideo();
  }

  Future<void> _initVideo() async {
    final url = widget.m.url;
    if (url.isEmpty) return;
    final ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
    try {
      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(0);
      if (mounted) setState(() { _ctrl = ctrl; _ready = true; });
    } catch (_) {
      ctrl.dispose();
    }
  }

  @override
  void dispose() {
    _ctrl?.dispose();
    super.dispose();
  }

  void _onVisibilityChanged(VisibilityInfo info) {
    if (!_ready || _ctrl == null) return;
    if (info.visibleFraction > 0.5) {
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

  @override
  Widget build(BuildContext context) {
    if (!_isVideo) {
      return SizedBox(
        height: widget.height,
        width: double.infinity,
        child: CachedNetworkImage(
          imageUrl: widget.m.url,
          fit: BoxFit.cover,
          placeholder: (_, __) => Container(color: const Color(0xFFE5E7EB)),
          errorWidget: (_, __, ___) => Container(
            color: const Color(0xFFE5E7EB),
            child: const Icon(Icons.broken_image_rounded, color: Color(0xFF9CA3AF), size: 32),
          ),
        ),
      );
    }

    return VisibilityDetector(
      key: _key,
      onVisibilityChanged: _onVisibilityChanged,
      child: GestureDetector(
        onTap: _ready ? _togglePause : null,
        child: Stack(
          children: [
            SizedBox(
              height: widget.height,
              width: double.infinity,
              child: _ready && _ctrl != null
                  ? FittedBox(
                      fit: BoxFit.cover,
                      clipBehavior: Clip.hardEdge,
                      child: SizedBox(
                        width: _ctrl!.value.size.width,
                        height: _ctrl!.value.size.height,
                        child: VideoPlayer(_ctrl!),
                      ),
                    )
                  : widget.m.thumbnail != null
                      ? CachedNetworkImage(
                          imageUrl: widget.m.thumbnail!,
                          fit: BoxFit.cover,
                          placeholder: (_, __) => Container(color: const Color(0xFF1A1B2E)),
                          errorWidget: (_, __, ___) => Container(color: const Color(0xFF1A1B2E)),
                        )
                      : Container(
                          height: widget.height,
                          color: const Color(0xFF1A1B2E),
                        ),
            ),
            if (!_ready || _paused)
              Positioned.fill(
                child: Center(
                  child: Container(
                    padding: const EdgeInsets.all(10),
                    decoration: const BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
                    child: Icon(
                      _paused ? Icons.pause_rounded : Icons.play_arrow_rounded,
                      color: Colors.white, size: 32,
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

class _VideoPlayerScreen extends StatefulWidget {
  final String url;
  const _VideoPlayerScreen({required this.url});

  @override
  State<_VideoPlayerScreen> createState() => _VideoPlayerScreenState();
}

class _VideoPlayerScreenState extends State<_VideoPlayerScreen> {
  late VideoPlayerController _ctrl;
  bool _ready = false;

  @override
  void initState() {
    super.initState();
    _ctrl = VideoPlayerController.networkUrl(Uri.parse(widget.url))
      ..initialize().then((_) {
        if (mounted) { setState(() => _ready = true); _ctrl.play(); }
      }).catchError((_) {});
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(backgroundColor: Colors.black, iconTheme: const IconThemeData(color: Colors.white)),
      body: Center(
        child: _ready
            ? GestureDetector(
                onTap: () { _ctrl.value.isPlaying ? _ctrl.pause() : _ctrl.play(); setState(() {}); },
                child: AspectRatio(aspectRatio: _ctrl.value.aspectRatio, child: VideoPlayer(_ctrl)),
              )
            : const CircularProgressIndicator(color: Colors.white),
      ),
    );
  }
}
