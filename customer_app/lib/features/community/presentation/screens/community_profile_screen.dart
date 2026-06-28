import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart';
import 'community_chat_screen.dart';
import 'edit_profile_screen.dart';
import 'ad_analytics_screen.dart';

class CommunityProfileScreen extends ConsumerWidget {
  final int userId;
  const CommunityProfileScreen({super.key, required this.userId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profileAsync = ref.watch(communityProfileProvider(userId));
    return profileAsync.when(
      loading: () => const Scaffold(
        body: Center(child: CircularProgressIndicator(color: kOrange)),
      ),
      error: (e, _) => Scaffold(
        appBar: AppBar(),
        body: Center(child: Text('Error: $e')),
      ),
      data: (user) => _ProfileBody(user: user, isMe: user.isMe),
    );
  }
}

class CommunityMyProfileScreen extends ConsumerWidget {
  const CommunityMyProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final myAsync = ref.watch(communityMyProfileProvider);
    return myAsync.when(
      loading: () => const Scaffold(body: Center(child: CircularProgressIndicator(color: kOrange))),
      error: (e, _) => Scaffold(appBar: AppBar(), body: Center(child: Text('$e'))),
      data: (user) => _ProfileBody(user: user, isMe: true),
    );
  }
}

class _ProfileBody extends ConsumerStatefulWidget {
  final CommunityUser user;
  final bool isMe;
  const _ProfileBody({required this.user, required this.isMe});

  @override
  ConsumerState<_ProfileBody> createState() => _ProfileBodyState();
}

class _ProfileBodyState extends ConsumerState<_ProfileBody>
    with SingleTickerProviderStateMixin {
  late TabController _tab;
  bool _following = false;
  String? _localAvatar;
  String? _localCover;

  Future<void> _pickAvatar() async {
    final picker = ImagePicker();
    final f = await picker.pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (f == null) return;
    try {
      final bytes = await f.readAsBytes();
      final ext = f.name.split('.').last.toLowerCase();
      final mime = ext == 'png' ? 'image/png' : 'image/jpeg';
      final mf = MultipartFile.fromBytes(bytes, filename: 'avatar.$ext', contentType: DioMediaType.parse(mime));
      final res = await ref.read(communityRepoProvider).uploadProfilePhoto(avatarFile: mf);
      setState(() => _localAvatar = res['avatar'] as String?);
      ref.invalidate(communityMyProfileProvider);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to upload photo: $e'), backgroundColor: Colors.red));
    }
  }

  Future<void> _pickCover() async {
    final picker = ImagePicker();
    final f = await picker.pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (f == null) return;
    try {
      final bytes = await f.readAsBytes();
      final ext = f.name.split('.').last.toLowerCase();
      final mime = ext == 'png' ? 'image/png' : 'image/jpeg';
      final mf = MultipartFile.fromBytes(bytes, filename: 'cover.$ext', contentType: DioMediaType.parse(mime));
      final res = await ref.read(communityRepoProvider).uploadProfilePhoto(coverFile: mf);
      setState(() => _localCover = res['cover_photo'] as String?);
      ref.invalidate(communityMyProfileProvider);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Cover photo updated!'), backgroundColor: Color(0xFF10B981)));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to upload cover: $e'), backgroundColor: Colors.red));
    }
  }

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 4, vsync: this);
    _following = widget.user.isFollowing;
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  Future<void> _toggleFollow() async {
    setState(() => _following = !_following);
    try {
      await ref.read(communityRepoProvider).toggleFollow(widget.user.id);
    } catch (_) {
      setState(() => _following = !_following);
    }
  }

  @override
  Widget build(BuildContext context) {
    final u = widget.user;
    final postsAsync = ref.watch(
      communityProfilePostsProvider(u.id),
    );

    return Scaffold(
      
      body: NestedScrollView(
        headerSliverBuilder: (context, _) => [
          SliverAppBar(
            expandedHeight: 220,
            pinned: true,
            
            leading: Navigator.canPop(context)
                ? IconButton(
                    icon: const Icon(Icons.arrow_back_rounded, color: Colors.white),
                    onPressed: () => Navigator.pop(context),
                  )
                : null,
            actions: [
              IconButton(
                icon: const Icon(Icons.more_horiz_rounded, color: Colors.white),
                onPressed: () {},
              ),
            ],
            flexibleSpace: FlexibleSpaceBar(
              background: GestureDetector(
                onTap: widget.isMe ? _pickCover : null,
                child: Stack(fit: StackFit.expand, children: [
                  // Cover photo
                  () {
                    final coverUrl = _localCover ?? u.coverPhoto;
                    if (coverUrl != null) return NetImage(url: coverUrl, fit: BoxFit.cover);
                    return Container(decoration: const BoxDecoration(gradient: LinearGradient(colors: [kOrange, Color(0xFFFF8C42)], begin: Alignment.topLeft, end: Alignment.bottomRight)));
                  }(),
                  // Gradient overlay
                  Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [Colors.transparent, Colors.black.withOpacity(0.4)],
                      ),
                    ),
                  ),
                  if (widget.isMe)
                    const Positioned(bottom: 12, right: 12,
                      child: CircleAvatar(radius: 14, backgroundColor: Colors.black54, child: Icon(Icons.camera_alt_rounded, color: Colors.white, size: 16))),
                ]),
              ),
            ),
          ),

          SliverToBoxAdapter(
            child: Container(
              color: Colors.white,
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                // Avatar row — avatar sits fully below cover
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
                  child: Row(children: [
                    GestureDetector(
                      onTap: widget.isMe ? _pickAvatar : null,
                      child: Stack(clipBehavior: Clip.none, children: [
                        Container(
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 3),
                            boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 6)],
                          ),
                          child: CircleNetImage(
                            url: _localAvatar ?? u.avatar,
                            size: 80,
                            fallbackText: u.name,
                          ),
                        ),
                        if (widget.isMe)
                          Positioned(bottom: 0, right: 0,
                            child: Container(
                              padding: const EdgeInsets.all(5),
                              decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle,
                                border: Border.all(color: Colors.white, width: 2)),
                              child: const Icon(Icons.camera_alt_rounded, color: Colors.white, size: 12))),
                      ]),
                    ),
                    const Spacer(),
                    if (widget.isMe)
                      _OutlineBtn(label: 'Edit Profile', icon: Icons.edit_rounded, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EditProfileScreen(user: u))))
                    else ...[
                      _OutlineBtn(
                        label: _following ? 'Following' : 'Follow',
                        icon: _following ? Icons.check_rounded : Icons.person_add_rounded,
                        onTap: _toggleFollow,
                        filled: !_following,
                      ),
                      const SizedBox(width: 8),
                      _OutlineBtn(label: 'Message', icon: Icons.chat_bubble_rounded, onTap: () async {
                        try {
                          final chat = await ref.read(communityChatsProvider.notifier).startOrGetChat(widget.user.id);
                          if (context.mounted) {
                            Navigator.push(context, MaterialPageRoute(builder: (_) => CommunityChatScreen(chat: chat)));
                          }
                        } catch (e) {
                          if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text('Failed to open chat: $e'), backgroundColor: Colors.red));
                        }
                      }),
                    ],
                  ]),
                ),

                // Name + verified + username
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 6),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Text(u.name,
                          style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: Color(0xFF1A1B2E))),
                      if (u.isVerified) ...[
                        const SizedBox(width: 6),
                        const Icon(Icons.verified_rounded, color: Color(0xFF1877F2), size: 18),
                      ],
                    ]),
                    if (u.username != null)
                      Text('@${u.username}', style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 14)),
                    if (u.bio != null && u.bio!.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Text(u.bio!, style: const TextStyle(color: Color(0xFF374151), fontSize: 14, height: 1.4)),
                    ],
                    if (u.location != null) ...[
                      const SizedBox(height: 4),
                      Row(children: [
                        const Icon(Icons.location_on_rounded, size: 14, color: Color(0xFF9CA3AF)),
                        const SizedBox(width: 3),
                        Text(u.location!, style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 13)),
                      ]),
                    ],
                  ]),
                ),

                // Stats
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                  child: Row(children: [
                    _Stat(value: '${u.postsCount}', label: 'Posts'),
                    const SizedBox(width: 24),
                    _Stat(value: _fmt(u.followersCount), label: 'Followers'),
                    const SizedBox(width: 24),
                    _Stat(value: _fmt(u.followingCount), label: 'Following'),
                  ]),
                ),

                // Quick action icons (only for me)
                if (widget.isMe)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceAround,
                      children: [
                        _QuickAction(icon: Icons.campaign_rounded, label: 'My Ads', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdAnalyticsScreen()))),
                        _QuickAction(icon: Icons.shopping_bag_rounded, label: 'Marketplace', onTap: () {}),
                        _QuickAction(icon: Icons.bookmark_rounded, label: 'Saved', onTap: () {}),
                        _QuickAction(icon: Icons.star_rounded, label: 'Highlights', onTap: () {}),
                      ],
                    ),
                  ),

                // Tab bar
                TabBar(
                  controller: _tab,
                  indicatorColor: kOrange,
                  indicatorWeight: 2.5,
                  labelColor: kOrange,
                  unselectedLabelColor: const Color(0xFF9CA3AF),
                  tabs: const [
                    Tab(icon: Icon(Icons.grid_on_rounded, size: 22)),
                    Tab(icon: Icon(Icons.photo_library_rounded, size: 22)),
                    Tab(icon: Icon(Icons.chat_bubble_outline_rounded, size: 22)),
                    Tab(icon: Icon(Icons.camera_alt_rounded, size: 22)),
                  ],
                ),
              ]),
            ),
          ),
        ],

        body: TabBarView(
          controller: _tab,
          children: [
            _PostsGrid(userId: u.id),
            _PostsGrid(userId: u.id, mediaOnly: true),
            _PostsGrid(userId: u.id, type: 'text'),
            _PostsGrid(userId: u.id, videoOnly: true),
          ],
        ),
      ),
    );
  }

  String _fmt(int n) {
    if (n >= 1000000) return '${(n / 1000000).toStringAsFixed(1)}M';
    if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}K';
    return '$n';
  }
}

class _Stat extends StatelessWidget {
  final String value;
  final String label;
  const _Stat({required this.value, required this.label});

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(value, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Color(0xFF1A1B2E))),
      Text(label, style: const TextStyle(fontSize: 13, color: Color(0xFF9CA3AF))),
    ]);
  }
}

class _OutlineBtn extends StatelessWidget {
  final String label;
  final IconData icon;
  final VoidCallback onTap;
  final bool filled;
  const _OutlineBtn({required this.label, required this.icon, required this.onTap, this.filled = false});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: filled ? kOrange : Colors.transparent,
          border: filled ? null : Border.all(color: const Color(0xFFE5E7EB), width: 1.5),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 16, color: filled ? Colors.white : const Color(0xFF374151)),
          const SizedBox(width: 5),
          Text(label,
              style: TextStyle(
                color: filled ? Colors.white : const Color(0xFF374151),
                fontWeight: FontWeight.w600,
                fontSize: 13,
              )),
        ]),
      ),
    );
  }
}

class _QuickAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  const _QuickAction({required this.icon, required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Column(children: [
        Container(
          width: 52, height: 52,
          decoration: BoxDecoration(
            color: kOrange.withOpacity(0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: kOrange, size: 24),
        ),
        const SizedBox(height: 5),
        Text(label, style: const TextStyle(fontSize: 12, color: Color(0xFF374151), fontWeight: FontWeight.w500)),
      ]),
    );
  }
}

class _PostsGrid extends ConsumerWidget {
  final int userId;
  final String? type;
  final bool mediaOnly;
  final bool videoOnly;
  const _PostsGrid({required this.userId, this.type, this.mediaOnly = false, this.videoOnly = false});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final postsAsync = ref.watch(communityProfilePostsProvider(userId));
    return postsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
      error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: Colors.red))),
      data: (posts) {
        List<CommunityPost> filtered;
        if (videoOnly) {
          filtered = posts.where((p) => p.type == 'video' || p.type == 'reel').toList();
        } else if (mediaOnly) {
          filtered = posts.where((p) => p.media.any((m) => m.type == 'image')).toList();
        } else if (type != null) {
          filtered = posts.where((p) => p.type == type).toList();
        } else {
          filtered = posts;
        }
        if (filtered.isEmpty) {
          return const Center(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.photo_library_outlined, size: 48, color: Color(0xFFD1D5DB)),
              SizedBox(height: 10),
              Text('No posts yet', style: TextStyle(color: Color(0xFF9CA3AF))),
            ]),
          );
        }
        return GridView.builder(
          padding: const EdgeInsets.all(2),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 3, crossAxisSpacing: 2, mainAxisSpacing: 2,
          ),
          itemCount: filtered.length,
          itemBuilder: (ctx, i) {
            final p = filtered[i];
            final media = p.media.isNotEmpty ? p.media[0] : null;
            final isVideo = media?.type == 'video';
            final thumb = media?.thumbnail;
            final imgUrl = isVideo ? thumb : media?.url;

            return GestureDetector(
              onTap: () => Navigator.push(ctx, MaterialPageRoute(
                builder: (_) => _PostDetailScreen(post: p))),
              child: Container(
                color: const Color(0xFFE5E7EB),
                child: Stack(children: [
                  if (imgUrl != null)
                    Positioned.fill(child: NetImage(url: imgUrl, fit: BoxFit.cover))
                  else
                    Center(child: Padding(
                      padding: const EdgeInsets.all(6),
                      child: Text(
                        p.content?.substring(0, p.content!.length.clamp(0, 50)) ?? '',
                        style: const TextStyle(fontSize: 11, color: Color(0xFF6B7280)),
                        maxLines: 4, textAlign: TextAlign.center, overflow: TextOverflow.ellipsis))),
                  if (isVideo)
                    const Positioned(top: 4, right: 4,
                      child: Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 22)),
                  if (p.media.length > 1)
                    const Positioned(top: 4, right: 4,
                      child: Icon(Icons.collections_rounded, color: Colors.white, size: 18)),
                ]),
              ),
            );
          },
        );
      },
    );
  }
}

class _PostDetailScreen extends StatelessWidget {
  final CommunityPost post;
  const _PostDetailScreen({required this.post});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Post'), backgroundColor: Colors.white, foregroundColor: const Color(0xFF1A1B2E)),
      body: SingleChildScrollView(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // User header
          Padding(padding: const EdgeInsets.all(12), child: Row(children: [
            CircleNetImage(url: post.user.avatar, size: 40, fallbackText: post.user.name),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(post.user.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
              Text(post.createdAt.toString().substring(0, 16), style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 12)),
            ])),
          ])),
          // Content
          if (post.content != null && post.content!.isNotEmpty)
            Padding(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              child: Text(post.content!, style: const TextStyle(fontSize: 15, height: 1.4))),
          // Media
          for (final m in post.media)
            if (m.type == 'image')
              NetImage(url: m.url, fit: BoxFit.fitWidth, width: double.infinity)
            else if (m.type == 'video')
              Container(height: 300, color: Colors.black,
                child: Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.play_circle_outline_rounded, color: Colors.white, size: 64),
                  const SizedBox(height: 8),
                  Text(m.url.split('/').last, style: const TextStyle(color: Colors.white54, fontSize: 12)),
                ]))),
          // Stats
          Padding(padding: const EdgeInsets.all(12), child: Row(children: [
            const Icon(Icons.thumb_up_alt_rounded, size: 16, color: Color(0xFF9CA3AF)),
            const SizedBox(width: 4),
            Text('${post.likesCount}', style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 13)),
            const SizedBox(width: 16),
            const Icon(Icons.chat_bubble_outline_rounded, size: 16, color: Color(0xFF9CA3AF)),
            const SizedBox(width: 4),
            Text('${post.commentsCount}', style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 13)),
            const SizedBox(width: 16),
            const Icon(Icons.remove_red_eye_rounded, size: 16, color: Color(0xFF9CA3AF)),
            const SizedBox(width: 4),
            Text('${post.viewsCount}', style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 13)),
          ])),
        ]),
      ),
    );
  }
}
