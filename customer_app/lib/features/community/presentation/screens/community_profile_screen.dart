import 'dart:io' as io;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import 'community_chat_screen.dart';
import 'package:go_router/go_router.dart';
import 'community_shell.dart' show kOrange;
import 'edit_profile_screen.dart';
import 'follow_list_screen.dart';
import 'highlight_viewer_screen.dart';
import 'transparency_center_screen.dart';
import 'copyright_screen.dart';
import 'settings_screen.dart';
import 'community_feed_screen.dart' show PostDetailScreen;

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
  late bool _requested;
  String? _localAvatar;
  String? _localCover;
  bool _followLoading = false;
  bool _isBlocked = false;
  bool _isBlockedByThem = false;
  bool _isMuted = false;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 6, vsync: this);
    _tab.addListener(() { if (mounted) setState(() {}); });
    _following = widget.user.isFollowing;
    _requested = widget.user.isRequested;
    if (!widget.isMe) _loadBlockStatus();
  }

  Future<void> _loadBlockStatus() async {
    try {
      final result = await ref.read(communityRepoProvider).checkBlock(widget.user.id);
      if (mounted) {
        setState(() {
          _isBlocked = result['is_blocked'] as bool? ?? false;
          _isBlockedByThem = result['is_blocked_by'] as bool? ?? false;
        });
      }
    } catch (_) {}
  }

  @override
  void didUpdateWidget(_ProfileBody old) {
    super.didUpdateWidget(old);
    if (!_followLoading) {
      if (old.user.isFollowing != widget.user.isFollowing) setState(() => _following = widget.user.isFollowing);
      if (old.user.isRequested != widget.user.isRequested) setState(() => _requested = widget.user.isRequested);
    }
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
    setState(() => _followLoading = true);
    try {
      final result = await ref.read(communityRepoProvider).toggleFollow(widget.user.id);
      final action = result['action'] as String;
      setState(() {
        _following  = action == 'followed';
        _requested  = action == 'requested';
      });
      ref.invalidate(communityProfileProvider(widget.user.id));
      ref.invalidate(communityMyProfileProvider);
    } catch (_) {
      // revert
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
        if (!widget.isMe) ...[
          ListTile(
            leading: Icon(_isBlocked ? Icons.person_add_outlined : Icons.block_rounded,
                color: _isBlocked ? kOrange : const Color(0xFFDC2626)),
            title: Text(
              _isBlocked ? 'Unblock @${widget.user.username ?? widget.user.name}' : 'Block @${widget.user.username ?? widget.user.name}',
              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14,
                  color: _isBlocked ? kOrange : const Color(0xFFDC2626)),
            ),
            onTap: () async {
              Navigator.pop(context);
              try {
                final result = await ref.read(communityRepoProvider).toggleBlock(widget.user.id);
                final blocked = result['blocked'] as bool? ?? false;
                if (mounted) {
                  setState(() => _isBlocked = blocked);
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(blocked ? 'User blocked' : 'User unblocked')),
                  );
                }
              } catch (e) {
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red),
                );
              }
            },
          ),
          ListTile(
            leading: Icon(_isMuted ? Icons.volume_up_outlined : Icons.volume_off_outlined,
                color: context.colors.mutedText),
            title: Text(
              _isMuted ? 'Unmute @${widget.user.username ?? widget.user.name}' : 'Mute @${widget.user.username ?? widget.user.name}',
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14),
            ),
            onTap: () async {
              Navigator.pop(context);
              try {
                final result = await ref.read(communityRepoProvider).toggleMute(widget.user.id);
                final muted = result['muted'] as bool? ?? false;
                if (mounted) {
                  setState(() => _isMuted = muted);
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text(muted ? 'User muted' : 'User unmuted')),
                  );
                }
              } catch (e) {
                if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('Failed: $e'), backgroundColor: Colors.red),
                );
              }
            },
          ),
          ListTile(
            leading: const Icon(Icons.flag_outlined, color: Color(0xFFDC2626)),
            title: const Text('Report User', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFFDC2626))),
            onTap: () => Navigator.pop(context),
          ),
        ],
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
            // expandedHeight = 200 (cover) + 44 (avatar lower half in card)
            expandedHeight: 244,
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
              background: Stack(children: [
                // Cover photo — top 200px only
                Positioned(top: 0, left: 0, right: 0, height: 200,
                  child: GestureDetector(
                    onTap: widget.isMe ? _pickCover : null,
                    child: Stack(fit: StackFit.expand, children: [
                      () {
                        final url = _localCover ?? u.coverPhoto;
                        if (url != null) return NetImage(url: url, fit: BoxFit.cover);
                        return Container(decoration: const BoxDecoration(gradient: LinearGradient(colors: [Color(0xFF1A1A2E), Color(0xFF16213E)], begin: Alignment.topLeft, end: Alignment.bottomRight)));
                      }(),
                      Container(decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.transparent, Colors.black45]))),
                      if (widget.isMe)
                        Positioned(bottom: 8, right: 12, child: Container(padding: const EdgeInsets.all(7), decoration: BoxDecoration(color: Colors.black54, shape: BoxShape.circle), child: const Icon(Icons.camera_alt_rounded, color: Colors.white, size: 16))),
                    ]),
                  ),
                ),
                // Card-color area below cover (bottom 44px = avatar lower half)
                Positioned(bottom: 0, left: 0, right: 0, height: 44,
                  child: ColoredBox(color: c.cardBg)),
                // Avatar straddles cover/card boundary — top half on cover, bottom half on card
                Positioned(
                  bottom: 0, left: 16,
                  child: GestureDetector(
                    onTap: widget.isMe ? _pickAvatar : null,
                    child: Stack(clipBehavior: Clip.none, children: [
                      Container(
                        decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: c.cardBg, width: 3)),
                        child: CircleNetImage(url: _localAvatar ?? u.avatar, size: 88, fallbackText: u.name),
                      ),
                      Positioned(bottom: 6, right: 6, child: Container(width: 16, height: 16, decoration: BoxDecoration(color: const Color(0xFF22C55E), shape: BoxShape.circle, border: Border.all(color: c.cardBg, width: 2)))),
                      if (widget.isMe)
                        Positioned(bottom: 2, right: 2, child: Container(padding: const EdgeInsets.all(4), decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle, border: Border.all(color: c.cardBg, width: 1.5)), child: const Icon(Icons.camera_alt_rounded, color: Colors.white, size: 11))),
                    ]),
                  ),
                ),
              ]),
            ),
          ),

          // ── Profile info ────────────────────────────────────────────
          SliverToBoxAdapter(
            child: ColoredBox(
              color: c.cardBg,
              child: _isBlockedByThem
                  ? Padding(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                      child: Column(children: [
                        const SizedBox(width: 96),
                        const SizedBox(height: 24),
                        Container(
                          width: 72, height: 72,
                          decoration: BoxDecoration(color: Colors.grey.shade200, shape: BoxShape.circle),
                          child: Icon(Icons.block_rounded, size: 36, color: Colors.grey.shade400),
                        ),
                        const SizedBox(height: 16),
                        Text('You can\'t view this profile',
                            style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: c.bodyText)),
                        const SizedBox(height: 8),
                        Text('This user has blocked you.',
                            textAlign: TextAlign.center,
                            style: TextStyle(fontSize: 13, color: c.mutedText)),
                      ]),
                    )
                  : Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    // Action buttons row (right-aligned, beside avatar space)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                      child: Row(children: [
                          const SizedBox(width: 96), // leave space beside avatar
                          const Spacer(),
                          if (widget.isMe)
                            _ActionBtn(label: 'Edit Profile', icon: Icons.edit_rounded, outlined: true, onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EditProfileScreen(user: u))))
                          else ...[
                            _ActionBtn(label: 'Message', icon: Icons.chat_bubble_rounded, outlined: true, onTap: () async {
                              try {
                                final chat = await ref.read(communityChatsProvider.notifier).startOrGetChat(widget.user.id);
                                if (context.mounted) Navigator.push(context, MaterialPageRoute(builder: (_) => CommunityChatScreen(chat: chat)));
                              } catch (e) {
                                if (!context.mounted) return;
                                if (e is DioException && e.response?.statusCode == 403) {
                                  final msg = e.response?.data['message'] as String? ?? 'This user has restricted their messages';
                                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                                    content: Row(children: [
                                      const Icon(Icons.lock_rounded, color: Colors.white, size: 16),
                                      const SizedBox(width: 8),
                                      Expanded(child: Text(msg)),
                                    ]),
                                    backgroundColor: const Color(0xFF374151),
                                  ));
                                } else {
                                  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: Colors.red));
                                }
                              }
                            }),
                            const SizedBox(width: 8),
                            _ActionBtn(
                              label: _following ? 'Following' : (_requested ? 'Requested' : 'Follow'),
                              icon: _following ? Icons.check_rounded : (_requested ? Icons.hourglass_top_rounded : Icons.person_add_rounded),
                              outlined: _requested,
                              loading: _followLoading, onTap: _toggleFollow,
                            ),
                          ],
                        ]),
                    ),

                      // Name + badges
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
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
                          _StatCol(value: '${u.postsCount}', label: 'Posts'),
                          _StatCol(value: _fmt(u.followersCount), label: 'Followers',
                            onTap: () => context.push('/community/follow-list',
                                extra: {'userId': u.id, 'type': 'followers'})),
                          _StatCol(value: _fmt(u.followingCount), label: 'Following',
                            onTap: () => context.push('/community/follow-list',
                                extra: {'userId': u.id, 'type': 'following'})),
                          _StatCol(value: _fmt(u.viewsCount), label: 'Views'),
                          _StatCol(value: _fmt(u.likesCount), label: 'Likes'),
                        ]),
                      ),

                      // Highlights
                      const SizedBox(height: 16),
                      _HighlightsSection(userId: u.id, isMe: widget.isMe),
                      const SizedBox(height: 12),

                      // Private account banner — show lock if private and not me and not following
                      if (u.isPrivate && !widget.isMe && !_following)
                        const SizedBox(height: 8),

                      // Tabs (hidden if private + not following)
                      if (!u.isPrivate || widget.isMe || _following)
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

          if (!_isBlockedByThem && (!u.isPrivate || widget.isMe || _following) && _tab.index == 0)
            // Pinned post header (Posts tab only)
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

        body: _isBlockedByThem
            ? const SizedBox.shrink()
            : (u.isPrivate && !widget.isMe && !_following)
            ? _LockedProfileView(user: u)
            : TabBarView(
                controller: _tab,
                children: [
                  _PostsGrid(userId: u.id, isMe: widget.isMe),
                  _PostsGrid(userId: u.id, videoOnly: true, isMe: widget.isMe),
                  _PostsGrid(userId: u.id, type: 'audio', isMe: widget.isMe),
                  _PostsGrid(userId: u.id, mediaOnly: true, isMe: widget.isMe),
                  _SavedPostsGrid(userId: u.id, isMe: widget.isMe),
                  _LikedPostsGrid(userId: u.id),
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
  final VoidCallback? onTap;
  const _StatCol({required this.value, required this.label, this.onTap});

  @override
  Widget build(BuildContext context) => Expanded(
    child: GestureDetector(
      onTap: onTap,
      child: Column(children: [
        Text(value, style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800,
            color: onTap != null ? kOrange : context.colors.bodyText)),
        const SizedBox(height: 2),
        Text(label, style: TextStyle(fontSize: 11, color: context.colors.mutedText)),
      ]),
    ),
  );
}

// ══════════════════════════════════════════════════════════════════════════
// HIGHLIGHTS — Instagram-style story circles
// ══════════════════════════════════════════════════════════════════════════

class _HighlightsSection extends ConsumerWidget {
  final int userId;
  final bool isMe;
  const _HighlightsSection({required this.userId, required this.isMe});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(communityHighlightsProvider(userId));
    final highlights = async.valueOrNull ?? [];

    if (!isMe && highlights.isEmpty) return const SizedBox.shrink();

    return SizedBox(
      height: 88,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        children: [
          if (isMe)
            Padding(
              padding: const EdgeInsets.only(right: 18),
              child: _HighlightCircle(
                isAdd: true,
                onTap: () => _showCreateSheet(context, ref),
              ),
            ),
          ...highlights.map((h) => Padding(
            padding: const EdgeInsets.only(right: 18),
            child: _HighlightCircle(
              highlight: h,
              onLongPress: isMe ? () => _confirmDelete(context, ref, h) : null,
              onTap: () => isMe
                  ? _showOwnerOptions(context, ref, h)
                  : _openViewer(context, h),
            ),
          )),
        ],
      ),
    );
  }

  Future<void> _showCreateSheet(BuildContext context, WidgetRef ref) async {
    final ctrl = TextEditingController();
    String? localPath;
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: context.colors.cardBg,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => StatefulBuilder(builder: (ctx, setS) {
        return Padding(
          padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(ctx).viewInsets.bottom + 32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(width: 40, height: 4, decoration: BoxDecoration(color: ctx.colors.dividerColor, borderRadius: BorderRadius.circular(2))),
            const SizedBox(height: 16),
            Text('New Highlight', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: ctx.colors.bodyText)),
            const SizedBox(height: 20),
            // Cover picker
            GestureDetector(
              onTap: () async {
                final f = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
                if (f != null) setS(() => localPath = f.path);
              },
              child: Stack(alignment: Alignment.center, children: [
                Container(
                  width: 80, height: 80,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: kOrange.withValues(alpha: 0.12),
                    border: Border.all(color: kOrange, width: 2),
                  ),
                  child: localPath != null
                      ? ClipOval(child: Image.file(io.File(localPath!), fit: BoxFit.cover, width: 80, height: 80))
                      : const Icon(Icons.add_photo_alternate_rounded, color: kOrange, size: 30),
                ),
                if (localPath != null)
                  Positioned(bottom: 0, right: 0,
                    child: Container(width: 26, height: 26, decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle, border: Border.all(color: ctx.colors.cardBg, width: 2)),
                      child: const Icon(Icons.edit_rounded, color: Colors.white, size: 12))),
              ]),
            ),
            const SizedBox(height: 6),
            Text('Tap to set cover', style: TextStyle(fontSize: 11, color: ctx.colors.subtleText)),
            const SizedBox(height: 20),
            TextField(
              controller: ctrl,
              maxLength: 20,
              autofocus: true,
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: ctx.colors.bodyText),
              decoration: InputDecoration(
                hintText: 'Highlight name...',
                hintStyle: TextStyle(color: ctx.colors.mutedText),
                filled: true, fillColor: ctx.colors.inputFill,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                counterText: '',
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), padding: const EdgeInsets.symmetric(vertical: 14)),
                onPressed: () async {
                  final title = ctrl.text.trim();
                  if (title.isEmpty) return;
                  Navigator.pop(ctx);
                  try {
                    MultipartFile? mf;
                    if (localPath != null) {
                      final bytes = await _readBytes(localPath!);
                      final ext = localPath!.split('.').last.toLowerCase();
                      mf = MultipartFile.fromBytes(bytes, filename: 'cover.$ext', contentType: DioMediaType.parse(ext == 'png' ? 'image/png' : 'image/jpeg'));
                    }
                    await ref.read(communityRepoProvider).createHighlight(title, coverFile: mf);
                    ref.invalidate(communityHighlightsProvider(userId));
                  } catch (e) {
                    if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: Colors.red));
                  }
                },
                child: const Text('Add Highlight', style: TextStyle(fontWeight: FontWeight.w700)),
              ),
            ),
          ]),
        );
      }),
    );
  }

  void _openViewer(BuildContext context, CommunityHighlight h) {
    context.push('/community/highlight-viewer', extra: {'highlight': h, 'initialIndex': 0});
  }

  Future<void> _showOwnerOptions(BuildContext context, WidgetRef ref, CommunityHighlight h) async {
    await showModalBottomSheet(
      context: context,
      backgroundColor: context.colors.cardBg,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const SizedBox(height: 8),
          Container(width: 40, height: 4, decoration: BoxDecoration(color: ctx.colors.dividerColor, borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 8),
          ListTile(
            leading: const Icon(Icons.play_circle_outline_rounded),
            title: const Text('View Highlight'),
            onTap: () { Navigator.pop(ctx); _openViewer(context, h); },
          ),
          ListTile(
            leading: const Icon(Icons.edit_outlined),
            title: const Text('Edit Highlight'),
            onTap: () { Navigator.pop(ctx); _openEditSheet(context, ref, h); },
          ),
          ListTile(
            leading: const Icon(Icons.delete_outline_rounded, color: Colors.red),
            title: const Text('Delete Highlight', style: TextStyle(color: Colors.red)),
            onTap: () { Navigator.pop(ctx); _confirmDelete(context, ref, h); },
          ),
          const SizedBox(height: 8),
        ]),
      ),
    );
  }

  Future<void> _openEditSheet(BuildContext context, WidgetRef ref, CommunityHighlight h) async {
    final ctrl = TextEditingController(text: h.title);
    String? localPath;
    await showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: context.colors.cardBg,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => StatefulBuilder(builder: (ctx, setS) {
        return Padding(
          padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(ctx).viewInsets.bottom + 32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(width: 40, height: 4, decoration: BoxDecoration(color: ctx.colors.dividerColor, borderRadius: BorderRadius.circular(2))),
            const SizedBox(height: 16),
            Text('Edit Highlight', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: ctx.colors.bodyText)),
            const SizedBox(height: 20),
            GestureDetector(
              onTap: () async {
                final f = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
                if (f != null) setS(() => localPath = f.path);
              },
              child: _HighlightCircle(highlight: h, localPath: localPath, isInteractive: false),
            ),
            const SizedBox(height: 20),
            TextField(
              controller: ctrl,
              maxLength: 20,
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: ctx.colors.bodyText),
              decoration: InputDecoration(
                hintText: 'Highlight name...',
                hintStyle: TextStyle(color: ctx.colors.mutedText),
                filled: true, fillColor: ctx.colors.inputFill,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                counterText: '',
              ),
            ),
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)), padding: const EdgeInsets.symmetric(vertical: 14)),
                onPressed: () async {
                  final title = ctrl.text.trim();
                  if (title.isEmpty) return;
                  Navigator.pop(ctx);
                  try {
                    MultipartFile? mf;
                    if (localPath != null) {
                      final bytes = await _readBytes(localPath!);
                      final ext = localPath!.split('.').last.toLowerCase();
                      mf = MultipartFile.fromBytes(bytes, filename: 'cover.$ext', contentType: DioMediaType.parse(ext == 'png' ? 'image/png' : 'image/jpeg'));
                    }
                    await ref.read(communityRepoProvider).updateHighlight(h.id, title: title, coverFile: mf);
                    ref.invalidate(communityHighlightsProvider(userId));
                  } catch (e) {
                    if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: Colors.red));
                  }
                },
                child: const Text('Save', style: TextStyle(fontWeight: FontWeight.w700)),
              ),
            ),
            const SizedBox(height: 10),
            // Delete button
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.red,
                  side: const BorderSide(color: Colors.red),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                onPressed: () async {
                  Navigator.pop(ctx);
                  if (!context.mounted) return;
                  await _confirmDelete(context, ref, h);
                },
                child: const Text('Delete Highlight', style: TextStyle(fontWeight: FontWeight.w700)),
              ),
            ),
          ]),
        );
      }),
    );
  }

  Future<void> _confirmDelete(BuildContext context, WidgetRef ref, CommunityHighlight h) async {
    if (!context.mounted) return;
    final ok = await showDialog<bool>(
      context: context,
      builder: (dlgCtx) => AlertDialog(
        title: const Text('Delete Highlight?'),
        content: Text('Delete "${h.title}"?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(dlgCtx, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(dlgCtx, true), child: const Text('Delete', style: TextStyle(color: Colors.red))),
        ],
      ),
    );
    if (ok == true) {
      await ref.read(communityRepoProvider).deleteHighlight(h.id);
      ref.invalidate(communityHighlightsProvider(userId));
    }
  }

  static Future<List<int>> _readBytes(String path) =>
      io.File(path).readAsBytes();
}

class _HighlightCircle extends StatelessWidget {
  final CommunityHighlight? highlight;
  final bool isAdd;
  final String? localPath;
  final bool isInteractive;
  final VoidCallback? onTap;
  final VoidCallback? onLongPress;
  const _HighlightCircle({
    this.highlight,
    this.isAdd = false,
    this.localPath,
    this.isInteractive = true,
    this.onTap,
    this.onLongPress,
  });

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    Widget circle;

    if (isAdd) {
      circle = Container(
        width: 64, height: 64,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: c.borderColor, width: 1.5),
          color: c.inputFill,
        ),
        child: Icon(Icons.add_rounded, color: c.subtleText, size: 28),
      );
    } else {
      final h = highlight!;
      final hasLocalCover = localPath != null;
      final hasCover = h.coverImage != null || hasLocalCover;

      Widget inner;
      if (hasLocalCover) {
        inner = _LocalFileImage(path: localPath!);
      } else if (h.coverImage != null) {
        inner = NetImage(url: h.coverImage!, fit: BoxFit.cover);
      } else {
        inner = Center(
          child: Text(
            h.title.isNotEmpty ? h.title[0].toUpperCase() : '?',
            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800, color: Colors.white),
          ),
        );
      }

      circle = Container(
        width: 64, height: 64,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: hasCover ? null : const LinearGradient(colors: [kOrange, Color(0xFFFF6B00)], begin: Alignment.topLeft, end: Alignment.bottomRight),
          border: Border.all(color: kOrange.withValues(alpha: 0.6), width: 2),
        ),
        child: ClipOval(child: inner),
      );
    }

    final label = isAdd ? 'New' : (highlight!.title.length > 9 ? '${highlight!.title.substring(0, 8)}...' : highlight!.title);

    return GestureDetector(
      onTap: onTap,
      onLongPress: onLongPress,
      child: SizedBox(
        width: 70,
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          circle,
          const SizedBox(height: 5),
          Text(label, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: isAdd ? c.mutedText : c.bodyText), overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
        ]),
      ),
    );
  }
}

class _LocalFileImage extends StatelessWidget {
  final String path;
  const _LocalFileImage({required this.path});
  @override
  Widget build(BuildContext context) {
    return Image.file(io.File(path), fit: BoxFit.cover, width: 64, height: 64,
      errorBuilder: (_, __, ___) => const Icon(Icons.image_rounded, color: Colors.white));
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

// ── Blocked-by view ────────────────────────────────────────────────────
class _BlockedByView extends StatelessWidget {
  final CommunityUser user;
  const _BlockedByView({required this.user});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return SingleChildScrollView(
      child: Column(children: [
        const SizedBox(height: 24),
        Container(
          width: 88, height: 88,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: c.borderColor,
          ),
          child: Icon(Icons.block_rounded, size: 38, color: c.mutedText),
        ),
        const SizedBox(height: 20),
        Text(
          "You can't view this profile",
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: c.bodyText, letterSpacing: -0.3),
        ),
        const SizedBox(height: 40),
      ]),
    );
  }
}

// ── Locked profile view ────────────────────────────────────────────────
class _LockedProfileView extends StatelessWidget {
  final CommunityUser user;
  const _LockedProfileView({required this.user});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final firstName = user.name.split(' ').first;
    return SingleChildScrollView(
      child: Column(children: [
        const SizedBox(height: 24),

        // Gradient lock icon
        Container(
          width: 88, height: 88,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: const LinearGradient(
              colors: [Color(0xFFFF8A00), Color(0xFFFF5C00)],
              begin: Alignment.topLeft, end: Alignment.bottomRight,
            ),
            boxShadow: [BoxShadow(color: kOrange.withValues(alpha: 0.35), blurRadius: 20, offset: const Offset(0, 8))],
          ),
          child: const Icon(Icons.lock_rounded, size: 38, color: Colors.white),
        ),
        const SizedBox(height: 20),

        Text(
          'This Account is Private',
          style: TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: c.bodyText, letterSpacing: -0.3),
        ),
        const SizedBox(height: 10),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 40),
          child: Text(
            'Follow $firstName to see their photos and videos.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 14, color: c.subtleText, height: 1.55),
          ),
        ),
        const SizedBox(height: 40),
      ]),
    );
  }
}

// ── Posts grid ─────────────────────────────────────────────────────────
class _PostsGrid extends ConsumerStatefulWidget {
  final int userId;
  final String? type;
  final bool mediaOnly;
  final bool videoOnly;
  final bool isMe;
  const _PostsGrid({required this.userId, this.type, this.mediaOnly = false, this.videoOnly = false, this.isMe = false});
  @override
  ConsumerState<_PostsGrid> createState() => _PostsGridState();
}

class _PostsGridState extends ConsumerState<_PostsGrid> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final a = ref.watch(communityProfilePostsProvider(widget.userId));
    final c = context.colors;
    return a.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
      error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: Colors.red))),
      data: (posts) {
        List<CommunityPost> list;
        if (widget.videoOnly)        list = posts.where((p) => p.type == 'video' || p.type == 'reel').toList();
        else if (widget.type != null) list = posts.where((p) => p.type == widget.type).toList();
        else if (widget.mediaOnly)    list = posts.where((p) => p.media.any((m) => m.type == 'image')).toList();
        else                          list = posts;

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
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PostDetailScreen(post: p))),
              onLongPress: widget.isMe ? () async {
                final confirmed = await showDialog<bool>(
                  context: context,
                  builder: (dlg) => AlertDialog(
                    title: const Text('Delete Post?'),
                    content: const Text('This post will be permanently deleted.'),
                    actions: [
                      TextButton(onPressed: () => Navigator.pop(dlg, false), child: const Text('Cancel')),
                      TextButton(onPressed: () => Navigator.pop(dlg, true), child: const Text('Delete', style: TextStyle(color: Colors.red))),
                    ],
                  ),
                );
                if (confirmed != true || !mounted) return;
                await ref.read(communityRepoProvider).deletePost(p.id);
                ref.invalidate(communityProfilePostsProvider(widget.userId));
                ref.read(communityFeedProvider.notifier).removePost(p.id);
                ref.read(communityExploreProvider.notifier).removePost(p.id);
              } : null,
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

class _SavedPostsGrid extends ConsumerStatefulWidget {
  final int userId;
  final bool isMe;
  const _SavedPostsGrid({required this.userId, required this.isMe});
  @override
  ConsumerState<_SavedPostsGrid> createState() => _SavedPostsGridState();
}

class _SavedPostsGridState extends ConsumerState<_SavedPostsGrid>
    with AutomaticKeepAliveClientMixin, SingleTickerProviderStateMixin {
  @override
  bool get wantKeepAlive => true;

  List<CommunityPost>? _posts;
  bool _loading = true;
  late TabController _catTab;

  static const _cats = [
    {'key': 'all',      'label': 'All',    'icon': Icons.bookmark_rounded},
    {'key': 'video',    'label': 'Videos', 'icon': Icons.videocam_rounded},
    {'key': 'image',    'label': 'Images', 'icon': Icons.image_rounded},
    {'key': 'audio',    'label': 'Audio',  'icon': Icons.music_note_rounded},
    {'key': 'text',     'label': 'Text',   'icon': Icons.text_fields_rounded},
    {'key': 'document', 'label': 'Files',  'icon': Icons.picture_as_pdf_rounded},
  ];

  @override
  void initState() {
    super.initState();
    _catTab = TabController(length: _cats.length, vsync: this);
    _catTab.addListener(() { if (mounted) setState(() {}); });
    _load();
  }

  @override
  void dispose() {
    _catTab.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final repo = CommunityRepository();
    final posts = widget.isMe
        ? await repo.getSavedPosts()
        : await repo.getSavedPostsByUser(widget.userId);
    if (mounted) setState(() { _posts = posts; _loading = false; });
  }

  List<CommunityPost> _filtered(String key) {
    final all = _posts ?? [];
    if (key == 'all') return all;
    if (key == 'video')    return all.where((p) => p.media.any((m) => m.type == 'video') || p.type == 'video' || p.type == 'reel').toList();
    if (key == 'image')    return all.where((p) => p.media.any((m) => m.type == 'image') && p.type != 'video' && p.type != 'reel').toList();
    if (key == 'audio')    return all.where((p) => p.media.any((m) => m.type == 'audio') || p.type == 'audio').toList();
    if (key == 'document') return all.where((p) => p.media.any((m) => m.type == 'document') || p.type == 'document').toList();
    if (key == 'text')     return all.where((p) => p.media.isEmpty && p.type == 'text').toList();
    return all;
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final c = context.colors;

    if (_loading) return const Center(child: CircularProgressIndicator(color: kOrange));

    final catKey = (_cats[_catTab.index]['key'] as String);
    final filtered = _filtered(catKey);

    return Column(children: [
      // Category tab bar
      Container(
        color: c.cardBg,
        child: TabBar(
          controller: _catTab,
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          indicatorColor: kOrange,
          indicatorWeight: 2.5,
          labelColor: kOrange,
          unselectedLabelColor: c.mutedText,
          labelStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
          unselectedLabelStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w500),
          tabs: _cats.map((cat) {
            final cnt = _filtered(cat['key'] as String).length;
            return Tab(
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(cat['icon'] as IconData, size: 15),
                const SizedBox(width: 5),
                Text('${cat['label']}'),
                if (cnt > 0) ...[
                  const SizedBox(width: 4),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                    decoration: BoxDecoration(
                      color: _catTab.index == _cats.indexOf(cat) ? kOrange : c.borderColor,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text('$cnt',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: _catTab.index == _cats.indexOf(cat) ? Colors.white : c.mutedText,
                        )),
                  ),
                ],
              ]),
            );
          }).toList(),
        ),
      ),

      // Grid
      Expanded(
        child: filtered.isEmpty
            ? _EmptyTab(icon: Icons.bookmark_outline_rounded, label: 'No saved ${catKey == 'all' ? 'posts' : catKey} yet')
            : _PostListGrid(posts: filtered),
      ),
    ]);
  }
}

class _LikedPostsGrid extends ConsumerStatefulWidget {
  final int userId;
  const _LikedPostsGrid({required this.userId});
  @override
  ConsumerState<_LikedPostsGrid> createState() => _LikedPostsGridState();
}

class _LikedPostsGridState extends ConsumerState<_LikedPostsGrid> with AutomaticKeepAliveClientMixin {
  @override
  bool get wantKeepAlive => true;
  @override
  Widget build(BuildContext context) {
    super.build(context);
    final a = ref.watch(communityLikedPostsProvider(widget.userId));
    return a.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
      error: (_, __) => _EmptyTab(icon: Icons.favorite_outline_rounded, label: 'No liked posts yet'),
      data: (posts) {
        if (posts.isEmpty) return _EmptyTab(icon: Icons.favorite_outline_rounded, label: 'No liked posts yet');
        return _PostListGrid(posts: posts);
      },
    );
  }
}

class _PostListGrid extends StatelessWidget {
  final List<CommunityPost> posts;
  const _PostListGrid({required this.posts});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return GridView.builder(
      padding: const EdgeInsets.all(2),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 3, crossAxisSpacing: 2, mainAxisSpacing: 2),
      itemCount: posts.length,
      itemBuilder: (_, i) {
        final p = posts[i];
        final media = p.media.isNotEmpty ? p.media[0] : null;
        final isVideo = media?.type == 'video';
        final imgUrl = isVideo ? media?.thumbnail : media?.url;
        return GestureDetector(
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PostDetailScreen(post: p))),
          child: Container(
            color: c.borderColor,
            child: Stack(children: [
              if (imgUrl != null) Positioned.fill(child: NetImage(url: imgUrl, fit: BoxFit.cover))
              else Center(child: Padding(padding: const EdgeInsets.all(6), child: Text(p.content?.substring(0, p.content!.length.clamp(0, 50)) ?? '', style: TextStyle(fontSize: 11, color: c.mutedText), maxLines: 4, textAlign: TextAlign.center, overflow: TextOverflow.ellipsis))),
              if (isVideo) const Positioned(top: 4, right: 4, child: Icon(Icons.play_circle_fill_rounded, color: Colors.white, size: 22)),
              if (p.media.length > 1) const Positioned(top: 4, right: 4, child: Icon(Icons.collections_rounded, color: Colors.white, size: 18)),
            ]),
          ),
        );
      },
    );
  }
}

