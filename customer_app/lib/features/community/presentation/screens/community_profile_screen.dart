import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../data/models/community_models.dart';
import '../providers/community_provider.dart';
import 'community_chat_screen.dart';
import 'community_shell.dart' show kOrange;
import 'edit_profile_screen.dart';
import 'transparency_center_screen.dart';
import 'copyright_screen.dart';
import 'settings_screen.dart';

// ── Profile entry points ────────────────────────────────────────────────
class CommunityProfileScreen extends ConsumerWidget {
  final int userId;
  const CommunityProfileScreen({super.key, required this.userId});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final a = ref.watch(communityProfileProvider(userId));
    return a.when(
      loading: () => const Scaffold(body: Center(child: CircularProgressIndicator(color: kOrange))),
      error: (e, _) => Scaffold(appBar: AppBar(), body: Center(child: Text('$e'))),
      data: (u) => _ProfileBody(user: u, isMe: u.isMe),
    );
  }
}

class CommunityMyProfileScreen extends ConsumerWidget {
  const CommunityMyProfileScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final a = ref.watch(communityMyProfileProvider);
    return a.when(
      loading: () => const Scaffold(body: Center(child: CircularProgressIndicator(color: kOrange))),
      error: (e, _) => Scaffold(appBar: AppBar(), body: Center(child: Text('$e'))),
      data: (u) => _ProfileBody(user: u, isMe: true),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// MAIN PROFILE BODY
// ══════════════════════════════════════════════════════════════════════════
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
  late bool _following;
  String? _localAvatar;
  String? _localCover;
  bool _followLoading = false;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 6, vsync: this);
    _following = widget.user.isFollowing;
  }

  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  String _fmt(int n) {
    if (n >= 1000000) return '${(n / 1000000).toStringAsFixed(1)}M';
    if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}K';
    return '$n';
  }

  Future<void> _toggleFollow() async {
    if (_followLoading) return;
    setState(() { _followLoading = true; _following = !_following; });
    try {
      await ref.read(communityRepoProvider).toggleFollow(widget.user.id);
    } catch (_) {
      setState(() => _following = !_following);
    } finally {
      if (mounted) setState(() => _followLoading = false);
    }
  }

  Future<void> _pickAvatar() async {
    final f = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (f == null || !mounted) return;
    try {
      final bytes = await f.readAsBytes();
      final ext = f.name.split('.').last.toLowerCase();
      final mime = ext == 'png' ? 'image/png' : 'image/jpeg';
      final mf = MultipartFile.fromBytes(bytes, filename: 'avatar.$ext', contentType: DioMediaType.parse(mime));
      final res = await ref.read(communityRepoProvider).uploadProfilePhoto(avatarFile: mf);
      setState(() => _localAvatar = res['avatar'] as String?);
      ref.invalidate(communityMyProfileProvider);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red));
    }
  }

  Future<void> _pickCover() async {
    final f = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (f == null || !mounted) return;
    try {
      final bytes = await f.readAsBytes();
      final ext = f.name.split('.').last.toLowerCase();
      final mime = ext == 'png' ? 'image/png' : 'image/jpeg';
      final mf = MultipartFile.fromBytes(bytes, filename: 'cover.$ext', contentType: DioMediaType.parse(mime));
      final res = await ref.read(communityRepoProvider).uploadProfilePhoto(coverFile: mf);
      setState(() => _localCover = res['cover_photo'] as String?);
      ref.invalidate(communityMyProfileProvider);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red));
    }
  }

  void _openMenu() {
    final c = context.colors;
    showModalBottomSheet(
      context: context,
      backgroundColor: c.cardBg,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => SafeArea(child: Column(mainAxisSize: MainAxisSize.min, children: [
        const SizedBox(height: 8),
        Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: c.dividerColor, borderRadius: BorderRadius.circular(2)))),
        const SizedBox(height: 8),
        if (widget.isMe) ...[
          ListTile(
            leading: const Icon(Icons.shield_outlined, color: kOrange),
            title: const Text('Account Status', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
            subtitle: Text('View violations, strikes & appeals', style: TextStyle(fontSize: 12, color: c.mutedText)),
            onTap: () { Navigator.pop(context); Navigator.push(context, MaterialPageRoute(builder: (_) => const TransparencyCenter())); },
          ),
          ListTile(
            leading: const Icon(Icons.copyright_rounded, color: Color(0xFF3B82F6)),
            title: const Text('Copyright', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
            subtitle: Text('Manage copyright claims', style: TextStyle(fontSize: 12, color: c.mutedText)),
            onTap: () { Navigator.pop(context); Navigator.push(context, MaterialPageRoute(builder: (_) => const CopyrightScreen())); },
          ),
        ],
        ListTile(
          leading: Icon(Icons.share_outlined, color: c.mutedText),
          title: const Text('Share Profile', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
          onTap: () => Navigator.pop(context),
        ),
        if (!widget.isMe)
          ListTile(
            leading: const Icon(Icons.flag_outlined, color: Color(0xFFDC2626)),
            title: const Text('Report User', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFFDC2626))),
            onTap: () => Navigator.pop(context),
          ),
        const SizedBox(height: 8),
      ])),
    );
  }

  @override
  Widget build(BuildContext context) {
    final u = widget.user;
    final c = context.colors;

    return Scaffold(
      backgroundColor: c.scaffoldBg,
      body: NestedScrollView(
        headerSliverBuilder: (ctx, _) => [
          // ── Cover + AppBar ────────────────────────────────────────────
          SliverAppBar(
            expandedHeight: 200,
            pinned: true,
            backgroundColor: c.cardBg,
            foregroundColor: Colors.white,
            leading: Navigator.canPop(ctx)
                ? IconButton(icon: const Icon(Icons.arrow_back_rounded, color: Colors.white), onPressed: () => Navigator.pop(ctx))
                : null,
            actions: [
              if (widget.isMe)
                IconButton(icon: const Icon(Icons.settings_outlined, color: Colors.white), onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SettingsScreen()))),
              IconButton(icon: const Icon(Icons.more_horiz_rounded, color: Colors.white), onPressed: _openMenu),
            ],
            flexibleSpace: FlexibleSpaceBar(
              background: GestureDetector(
                onTap: widget.isMe ? _pickCover : null,
                child: Stack(fit: StackFit.expand, children: [
                  () {
                    final url = _localCover ?? u.coverPhoto;
                    if (url != null) return NetImage(url: url, fit: BoxFit.cover);
                    return Container(decoration: const BoxDecoration(gradient: LinearGradient(colors: [Color(0xFF1A1A2E), Color(0xFF16213E)], begin: Alignment.topLeft, end: Alignment.bottomRight)));
                  }(),
                  Container(decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.transparent, Colors.black45]))),
                  if (widget.isMe)
                    Positioned(bottom: 12, right: 12, child: Container(padding: const EdgeInsets.all(7), decoration: BoxDecoration(color: Colors.black54, shape: BoxShape.circle), child: const Icon(Icons.camera_alt_rounded, color: Colors.white, size: 16))),
                ]),
              ),
            ),
          ),

          // ── Profile info ────────────────────────────────────────────
          SliverToBoxAdapter(
            child: ColoredBox(
              color: c.cardBg,
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

                // Avatar row
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 0),
                  child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    // Avatar overlapping cover
                    Transform.translate(
                      offset: const Offset(0, -28),
                      child: GestureDetector(
                        onTap: widget.isMe ? _pickAvatar : null,
                        child: Stack(clipBehavior: Clip.none, children: [
                          Container(
                            decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: c.cardBg, width: 3)),
                            child: CircleNetImage(url: _localAvatar ?? u.avatar, size: 84, fallbackText: u.name),
                          ),
                          // Online dot
                          Positioned(bottom: 4, right: 4, child: Container(width: 16, height: 16, decoration: BoxDecoration(color: const Color(0xFF22C55E), shape: BoxShape.circle, border: Border.all(color: c.cardBg, width: 2)))),
                          if (widget.isMe)
                            Positioned(bottom: 0, right: 0, child: Transform.translate(offset: const Offset(2, 2), child: Container(padding: const EdgeInsets.all(4), decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle, border: Border.all(color: c.cardBg, width: 1.5)), child: const Icon(Icons.camera_alt_rounded, color: Colors.white, size: 11)))),
                        ]),
                      ),
                    ),
                    const Spacer(),
                    // Action buttons
                    if (widget.isMe)
                      _ActionBtn(label: 'Edit Profile', icon: Icons.edit_rounded, outlined: true, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EditProfileScreen(user: u))))
                    else ...[
                      _ActionBtn(label: 'Message', icon: Icons.chat_bubble_rounded, outlined: true, onTap: () async {
                        try {
                          final chat = await ref.read(communityChatsProvider.notifier).startOrGetChat(widget.user.id);
                          if (context.mounted) Navigator.push(context, MaterialPageRoute(builder: (_) => CommunityChatScreen(chat: chat)));
                        } catch (e) {
                          if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: Colors.red));
                        }
                      }),
                      const SizedBox(width: 8),
                      _ActionBtn(
                        label: _following ? 'Following' : 'Follow',
                        icon: _following ? Icons.check_rounded : Icons.person_add_rounded,
                        outlined: false,
                        loading: _followLoading,
                        onTap: _toggleFollow,
                      ),
                    ],
                    const SizedBox(width: 4),
                  ]),
                ),

                // Name + badges
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
                  child: Wrap(crossAxisAlignment: WrapCrossAlignment.center, spacing: 6, children: [
                    Text(u.name, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800, color: c.bodyText)),
                    if (u.isVerified) const Icon(Icons.verified_rounded, color: Color(0xFF1877F2), size: 20),
                    if (u.isTopCreator) _InlineBadge(label: 'Top Creator', filled: true),
                    if (u.isBusiness) _InlineBadge(label: 'Business', filled: false),
                    if (u.isVerified && !u.isTopCreator) _InlineBadge(label: 'Verified', filled: false),
                  ]),
                ),

                // Username
                if (u.username != null)
                  Padding(padding: const EdgeInsets.fromLTRB(16, 2, 16, 0),
                    child: Text('@${u.username}', style: TextStyle(color: c.mutedText, fontSize: 14))),

                // Bio
                if (u.bio != null && u.bio!.isNotEmpty)
                  Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                    child: Text(u.bio!, style: TextStyle(color: c.bodyText, fontSize: 14, height: 1.45))),

                // Location + website
                if (u.location != null || u.website != null)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                    child: Wrap(spacing: 16, runSpacing: 4, children: [
                      if (u.location != null) _MetaChip(icon: Icons.location_on_rounded, label: u.location!),
                      if (u.website != null) _MetaChip(icon: Icons.link_rounded, label: u.website!, isLink: true),
                    ]),
                  ),

                // Joined date
                if (u.joinedAt != null)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 6, 16, 0),
                    child: _MetaChip(icon: Icons.calendar_today_rounded, label: 'Joined ${_fmtDate(u.joinedAt!)}'),
                  ),

                // Stats row
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                  child: Row(children: [
                    _StatCol(value: '${u.postsCount}',      label: 'Posts'),
                    _StatCol(value: _fmt(u.followersCount), label: 'Followers'),
                    _StatCol(value: _fmt(u.followingCount), label: 'Following'),
                    _StatCol(value: _fmt(u.viewsCount),     label: 'Views'),
                    _StatCol(value: _fmt(u.likesCount),     label: 'Likes'),
                  ]),
                ),

                // Interests
                if (u.interests.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  SizedBox(height: 76, child: ListView(
                    scrollDirection: Axis.horizontal,
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    children: [
                      ...u.interests.map((i) => Padding(padding: const EdgeInsets.only(right: 20), child: _InterestChip(label: i))),
                      Padding(padding: const EdgeInsets.only(right: 16), child: _InterestChip(label: 'More', isMore: true)),
                    ],
                  )),
                ],

                const SizedBox(height: 12),

                // Tabs
                TabBar(
                  controller: _tab,
                  indicatorColor: kOrange,
                  indicatorWeight: 2.5,
                  labelColor: kOrange,
                  unselectedLabelColor: c.mutedText,
                  isScrollable: false,
                  labelStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600),
                  tabs: const [
                    Tab(icon: Icon(Icons.grid_on_rounded, size: 20), text: 'Posts'),
                    Tab(icon: Icon(Icons.play_circle_outline_rounded, size: 20), text: 'Reels'),
                    Tab(icon: Icon(Icons.music_note_rounded, size: 20), text: 'Audio'),
                    Tab(icon: Icon(Icons.photo_rounded, size: 20), text: 'Photos'),
                    Tab(icon: Icon(Icons.bookmark_border_rounded, size: 20), text: 'Saved'),
                    Tab(icon: Icon(Icons.favorite_border_rounded, size: 20), text: 'Likes'),
                  ],
                ),
              ]),
            ),
          ),

          // Pinned post header (shown in Posts tab only)
          SliverToBoxAdapter(
            child: ColoredBox(
              color: c.scaffoldBg,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 6),
                child: Row(children: [
                  Icon(Icons.push_pin_rounded, size: 16, color: kOrange),
                  const SizedBox(width: 6),
                  Text('Pinned Post', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: kOrange)),
                ]),
              ),
            ),
          ),
        ],

        body: TabBarView(
          controller: _tab,
          children: [
            _PostsGrid(userId: u.id),
            _PostsGrid(userId: u.id, videoOnly: true),
            _PostsGrid(userId: u.id, type: 'audio'),
            _PostsGrid(userId: u.id, mediaOnly: true),
            _EmptyTab(icon: Icons.bookmark_outline_rounded, label: 'No saved posts yet'),
            _EmptyTab(icon: Icons.favorite_outline_rounded, label: 'No liked posts yet'),
          ],
        ),
      ),
    );
  }

  String _fmtDate(String iso) {
    try {
      final d = DateTime.parse(iso);
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      return '${months[d.month - 1]} ${d.day}, ${d.year}';
    } catch (_) { return iso; }
  }
}

// ── Reusable small widgets ──────────────────────────────────────────────

class _ActionBtn extends StatelessWidget {
  final String label;
  final IconData icon;
  final bool outlined;
  final bool loading;
  final VoidCallback onTap;
  const _ActionBtn({required this.label, required this.icon, required this.outlined, required this.onTap, this.loading = false});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return GestureDetector(
      onTap: loading ? null : onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
        decoration: BoxDecoration(
          color: outlined ? Colors.transparent : kOrange,
          border: Border.all(color: outlined ? c.borderColor : kOrange, width: 1.5),
          borderRadius: BorderRadius.circular(10),
        ),
        child: loading
            ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
            : Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(icon, size: 15, color: outlined ? c.bodyText : Colors.white),
                const SizedBox(width: 5),
                Text(label, style: TextStyle(color: outlined ? c.bodyText : Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
              ]),
      ),
    );
  }
}

class _InlineBadge extends StatelessWidget {
  final String label;
  final bool filled;
  const _InlineBadge({required this.label, required this.filled});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
    decoration: BoxDecoration(
      color: filled ? kOrange : Colors.transparent,
      border: Border.all(color: kOrange),
      borderRadius: BorderRadius.circular(6),
    ),
    child: Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: filled ? Colors.white : kOrange)),
  );
}

class _MetaChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool isLink;
  const _MetaChip({required this.icon, required this.label, this.isLink = false});

  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
    Icon(icon, size: 14, color: isLink ? kOrange : context.colors.mutedText),
    const SizedBox(width: 4),
    Text(label, style: TextStyle(fontSize: 13, color: isLink ? kOrange : context.colors.mutedText, decoration: isLink ? TextDecoration.underline : null)),
  ]);
}

class _StatCol extends StatelessWidget {
  final String value, label;
  const _StatCol({required this.value, required this.label});

  @override
  Widget build(BuildContext context) => Expanded(
    child: Column(children: [
      Text(value, style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: context.colors.bodyText)),
      const SizedBox(height: 2),
      Text(label, style: TextStyle(fontSize: 11, color: context.colors.mutedText)),
    ]),
  );
}

class _InterestChip extends StatelessWidget {
  final String label;
  final bool isMore;
  const _InterestChip({required this.label, this.isMore = false});

  static const _iconMap = <String, IconData>{
    'business':      Icons.business_center_rounded,
    'tech':          Icons.laptop_mac_rounded,
    'technology':    Icons.laptop_mac_rounded,
    'motivation':    Icons.bolt_rounded,
    'islamic':       Icons.mosque_rounded,
    'religion':      Icons.mosque_rounded,
    'travel':        Icons.flight_rounded,
    'food':          Icons.restaurant_rounded,
    'sports':        Icons.sports_soccer_rounded,
    'music':         Icons.music_note_rounded,
    'video':         Icons.videocam_rounded,
    'image':         Icons.image_rounded,
    'photo':         Icons.photo_camera_rounded,
    'photography':   Icons.camera_alt_rounded,
    'audio':         Icons.headphones_rounded,
    'share':         Icons.share_rounded,
    'text':          Icons.text_fields_rounded,
    'fashion':       Icons.checkroom_rounded,
    'health':        Icons.favorite_rounded,
    'fitness':       Icons.fitness_center_rounded,
    'education':     Icons.school_rounded,
    'entertainment': Icons.movie_rounded,
    'politics':      Icons.how_to_vote_rounded,
    'news':          Icons.newspaper_rounded,
    'comedy':        Icons.sentiment_very_satisfied_rounded,
    'gaming':        Icons.sports_esports_rounded,
    'art':           Icons.palette_rounded,
    'science':       Icons.science_rounded,
    'nature':        Icons.nature_rounded,
    'finance':       Icons.account_balance_rounded,
    'crypto':        Icons.currency_bitcoin_rounded,
    'cooking':       Icons.local_dining_rounded,
    'diy':           Icons.handyman_rounded,
    'animals':       Icons.pets_rounded,
    'cars':          Icons.directions_car_rounded,
    'shopping':      Icons.shopping_bag_rounded,
    'lifestyle':     Icons.wb_sunny_rounded,
  };

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    if (isMore) {
      return SizedBox(
        width: 56,
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: 50, height: 50,
            decoration: BoxDecoration(color: c.inputFill, shape: BoxShape.circle, border: Border.all(color: c.borderColor, width: 1.5)),
            child: Icon(Icons.more_horiz_rounded, size: 22, color: c.mutedText),
          ),
          const SizedBox(height: 5),
          Text('More', style: TextStyle(fontSize: 10, color: c.mutedText, fontWeight: FontWeight.w500), overflow: TextOverflow.ellipsis),
        ]),
      );
    }
    final key = label.toLowerCase();
    final icon = _iconMap[key] ?? Icons.interests_rounded;
    final disp = label.length > 9 ? label.substring(0, 8) : label;
    return SizedBox(
      width: 60,
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 50, height: 50,
          decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), shape: BoxShape.circle),
          child: Icon(icon, size: 24, color: kOrange),
        ),
        const SizedBox(height: 5),
        Text(disp, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: c.bodyText), overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
      ]),
    );
  }
}

class _EmptyTab extends StatelessWidget {
  final IconData icon;
  final String label;
  const _EmptyTab({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
    Icon(icon, size: 52, color: const Color(0xFFD1D5DB)),
    const SizedBox(height: 12),
    Text(label, style: TextStyle(color: context.colors.mutedText, fontSize: 15)),
  ]));
}

// ── Posts grid ─────────────────────────────────────────────────────────
class _PostsGrid extends ConsumerWidget {
  final int userId;
  final String? type;
  final bool mediaOnly;
  final bool videoOnly;
  const _PostsGrid({required this.userId, this.type, this.mediaOnly = false, this.videoOnly = false});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final a = ref.watch(communityProfilePostsProvider(userId));
    final c = context.colors;
    return a.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
      error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: Colors.red))),
      data: (posts) {
        List<CommunityPost> list;
        if (videoOnly)   list = posts.where((p) => p.type == 'video' || p.type == 'reel').toList();
        else if (type != null) list = posts.where((p) => p.type == type).toList();
        else if (mediaOnly)    list = posts.where((p) => p.media.any((m) => m.type == 'image')).toList();
        else                   list = posts;

        if (list.isEmpty) return _EmptyTab(icon: Icons.photo_library_outlined, label: 'No posts yet');

        return GridView.builder(
          padding: const EdgeInsets.all(2),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 3, crossAxisSpacing: 2, mainAxisSpacing: 2),
          itemCount: list.length,
          itemBuilder: (_, i) {
            final p = list[i];
            final media = p.media.isNotEmpty ? p.media[0] : null;
            final isVideo = media?.type == 'video';
            final imgUrl = isVideo ? media?.thumbnail : media?.url;
            return GestureDetector(
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _PostDetailScreen(post: p))),
              child: Container(
                color: c.borderColor,
                child: Stack(children: [
                  if (imgUrl != null) Positioned.fill(child: NetImage(url: imgUrl, fit: BoxFit.cover))
                  else Center(child: Padding(padding: const EdgeInsets.all(6), child: Text(p.content?.substring(0, p.content!.length.clamp(0, 50)) ?? '', style: TextStyle(fontSize: 11, color: c.mutedText), maxLines: 4, textAlign: TextAlign.center, overflow: TextOverflow.ellipsis))),
                  if (isVideo) const Positioned(top: 4, right: 4, child: Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 22)),
                  if (p.media.length > 1) const Positioned(top: 4, right: 4, child: Icon(Icons.collections_rounded, color: Colors.white, size: 18)),
                  if (p.moderationStatus == 'pending') Positioned.fill(child: Container(color: Colors.black54, child: const Column(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(Icons.hourglass_top_rounded, color: Colors.orange, size: 22), SizedBox(height: 4), Text('Under Review', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600))]))),
                  if (p.moderationStatus == 'blocked')  Positioned.fill(child: Container(color: Colors.black.withValues(alpha: 0.65), child: const Column(mainAxisAlignment: MainAxisAlignment.center, children: [Icon(Icons.block_rounded, color: Colors.red, size: 22), SizedBox(height: 4), Text('Removed', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600))]))),
                ]),
              ),
            );
          },
        );
      },
    );
  }
}

// ── Simple post detail ─────────────────────────────────────────────────
class _PostDetailScreen extends StatelessWidget {
  final CommunityPost post;
  const _PostDetailScreen({required this.post});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      appBar: AppBar(title: const Text('Post'), backgroundColor: c.cardBg, foregroundColor: c.navyText),
      backgroundColor: c.scaffoldBg,
      body: SingleChildScrollView(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Padding(padding: const EdgeInsets.all(12), child: Row(children: [
          CircleNetImage(url: post.user.avatar, size: 40, fallbackText: post.user.name),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(post.user.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
            Text(post.createdAt.toString().substring(0, 16), style: TextStyle(color: c.mutedText, fontSize: 12)),
          ])),
        ])),
        if (post.content != null && post.content!.isNotEmpty)
          Padding(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4), child: Text(post.content!, style: const TextStyle(fontSize: 15, height: 1.4))),
        for (final m in post.media)
          if (m.type == 'image') NetImage(url: m.url, fit: BoxFit.fitWidth, width: double.infinity),
        Padding(padding: const EdgeInsets.all(12), child: Row(children: [
          Icon(Icons.thumb_up_alt_rounded, size: 16, color: c.mutedText), const SizedBox(width: 4),
          Text('${post.likesCount}', style: TextStyle(color: c.mutedText, fontSize: 13)), const SizedBox(width: 16),
          Icon(Icons.chat_bubble_outline_rounded, size: 16, color: c.mutedText), const SizedBox(width: 4),
          Text('${post.commentsCount}', style: TextStyle(color: c.mutedText, fontSize: 13)),
        ])),
      ])),
    );
  }
}
