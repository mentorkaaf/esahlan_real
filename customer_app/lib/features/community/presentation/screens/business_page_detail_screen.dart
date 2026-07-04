import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import '../../data/models/community_models.dart';
import 'create_ad_screen.dart';
import 'community_shell.dart';

final _businessPagesProvider = FutureProvider<List<Map<String, dynamic>>>((ref) {
  return ref.read(communityRepoProvider).getBusinessPages();
});
final _myPagesProvider = FutureProvider<List<Map<String, dynamic>>>((ref) {
  return ref.read(communityRepoProvider).getMyPages();
});
final _pageDetailProvider = FutureProvider.family<Map<String, dynamic>, int>((ref, id) {
  return ref.read(communityRepoProvider).getBusinessPage(id);
});
final _pagePostsProvider = FutureProvider.family<List<CommunityPost>, int>((ref, id) {
  return ref.read(communityRepoProvider).getPagePosts(id);
});

// ══════════════════════════════════════════════════════════════════
// BUSINESS PAGES LIST (used in Businesses tab)
// ══════════════════════════════════════════════════════════════════

class BusinessPagesListWidget extends ConsumerWidget {
  const BusinessPagesListWidget({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final pagesAsync = ref.watch(_businessPagesProvider);
    final myPagesAsync = ref.watch(_myPagesProvider);

    return RefreshIndicator(
      color: kOrange,
      onRefresh: () async { ref.invalidate(_businessPagesProvider); ref.invalidate(_myPagesProvider); },
      child: ListView(padding: EdgeInsets.all(12), children: [
        // Create page CTA
        GestureDetector(
          onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const _CreatePageScreen())),
          child: Container(
            padding: EdgeInsets.all(16), margin: EdgeInsets.only(bottom: 16),
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [kOrange, Color(0xFFFF6B35)]),
              borderRadius: BorderRadius.circular(16)),
            child: Row(children: [
              Icon(Icons.add_business_rounded, color: Colors.white, size: 28),
              SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Create Business Page', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
                Text('Promote your business to thousands', style: TextStyle(color: Colors.white70, fontSize: 12)),
              ])),
              Icon(Icons.arrow_forward_ios_rounded, color: Colors.white70, size: 16),
            ]),
          ),
        ),

        // My Pages
        myPagesAsync.when(
          loading: () => SizedBox(),
          error: (_, __) => SizedBox(),
          data: (pages) {
            if (pages.isEmpty) return SizedBox();
            return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Padding(padding: EdgeInsets.only(bottom: 10),
                child: Text('My Pages', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.bodyText))),
              ...pages.map((p) => _PageCard(page: p, isOwner: true)),
              SizedBox(height: 16),
            ]);
          },
        ),

        // All Pages
        Padding(padding: EdgeInsets.only(bottom: 10),
          child: Text('Discover Pages', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.bodyText))),
        pagesAsync.when(
          loading: () => Center(child: Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator(color: kOrange))),
          error: (_, __) => Center(child: Text('Could not load pages', style: TextStyle(color: context.colors.mutedText))),
          data: (pages) {
            if (pages.isEmpty) return Center(child: Padding(padding: EdgeInsets.all(40),
              child: Text('No business pages yet. Be the first!', style: TextStyle(color: context.colors.mutedText))));
            return Column(children: pages.map((p) => _PageCard(page: p, isOwner: false)).toList());
          },
        ),
      ]),
    );
  }
}

class _PageCard extends ConsumerStatefulWidget {
  final Map<String, dynamic> page;
  final bool isOwner;
  const _PageCard({required this.page, required this.isOwner});
  @override
  ConsumerState<_PageCard> createState() => _PageCardState();
}

class _PageCardState extends ConsumerState<_PageCard> {
  late bool _following;

  @override
  void initState() {
    super.initState();
    _following = widget.page['is_following'] == true;
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.page;
    return GestureDetector(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BusinessPageDetailScreen(pageId: p['id']))),
      child: Container(
        margin: EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10)]),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Cover
          ClipRRect(borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
            child: p['cover_photo'] != null
                ? NetImage(url: p['cover_photo'], height: 100, width: double.infinity, fit: BoxFit.cover)
                : Container(height: 100, decoration: BoxDecoration(gradient: LinearGradient(colors: [kOrange, Color(0xFFFF6B35)])))),
          Padding(padding: EdgeInsets.all(14), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              CircleNetImage(url: p['avatar'], size: 48, fallbackText: p['name'] ?? '?'),
              SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Flexible(child: Text(p['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.bodyText), maxLines: 1, overflow: TextOverflow.ellipsis)),
                  if (p['is_verified'] == true) Padding(padding: EdgeInsets.only(left: 4), child: Icon(Icons.verified_rounded, color: Color(0xFF1877F2), size: 16)),
                ]),
                if (p['category'] != null) Text(p['category'], style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
                Text('${p['followers_count'] ?? 0} followers', style: TextStyle(fontSize: 12, color: context.colors.mutedText, fontWeight: FontWeight.w600)),
              ])),
            ]),
            SizedBox(height: 10),
            Row(children: [
              if (widget.isOwner)
                Expanded(child: ElevatedButton.icon(
                  onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CreateAdScreen(pageId: p['id'], pageName: p['name'] ?? ''))),
                  icon: Icon(Icons.campaign_rounded, size: 16),
                  label: Text('Promote', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                  style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    padding: EdgeInsets.symmetric(vertical: 10)),
                ))
              else
                Expanded(child: OutlinedButton(
                  onPressed: () async {
                    setState(() => _following = !_following);
                    try { await ref.read(communityRepoProvider).togglePageFollow(p['id']); } catch (_) { setState(() => _following = !_following); }
                  },
                  style: OutlinedButton.styleFrom(
                    foregroundColor: _following ? const Color(0xFF6B7280) : kOrange,
                    side: BorderSide(color: _following ? context.colors.subtleText : kOrange),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                    padding: EdgeInsets.symmetric(vertical: 10)),
                  child: Text(_following ? 'Following' : 'Follow', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
                )),
            ]),
          ])),
        ]),
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
// BUSINESS PAGE DETAIL
// ══════════════════════════════════════════════════════════════════

class BusinessPageDetailScreen extends ConsumerStatefulWidget {
  final int pageId;
  const BusinessPageDetailScreen({super.key, required this.pageId});
  @override
  ConsumerState<BusinessPageDetailScreen> createState() => _BusinessPageDetailScreenState();
}

class _BusinessPageDetailScreenState extends ConsumerState<BusinessPageDetailScreen> {
  bool _following = false;

  @override
  Widget build(BuildContext context) {
    final pageAsync = ref.watch(_pageDetailProvider(widget.pageId));

    return Scaffold(
      body: pageAsync.when(
        loading: () => Center(child: CircularProgressIndicator(color: kOrange)),
        error: (e, _) => Center(child: Text('$e')),
        data: (page) {
          _following = page['is_following'] == true;
          return CustomScrollView(slivers: [
            SliverAppBar(
              expandedHeight: 200, pinned: true,
              leading: GestureDetector(onTap: () => Navigator.pop(context),
                child: Container(margin: EdgeInsets.all(8), decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.3), shape: BoxShape.circle),
                  child: Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 18))),
              flexibleSpace: FlexibleSpaceBar(background: Stack(fit: StackFit.expand, children: [
                page['cover_photo'] != null
                    ? NetImage(url: page['cover_photo'], fit: BoxFit.cover)
                    : Container(decoration: BoxDecoration(gradient: LinearGradient(colors: [kOrange, Color(0xFFFF6B35)]))),
                Container(decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter,
                  colors: [Colors.transparent, Colors.black.withValues(alpha: 0.5)]))),
              ])),
            ),

            SliverToBoxAdapter(child: Container(color: context.colors.cardBg, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              // Avatar row — fully below cover
              Padding(padding: EdgeInsets.fromLTRB(16, 14, 16, 0),
                child: Row(children: [
                  Container(
                    decoration: BoxDecoration(shape: BoxShape.circle, border: Border.all(color: context.colors.cardBg, width: 3),
                      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.1), blurRadius: 6)]),
                    child: CircleNetImage(url: page['avatar'], size: 76, fallbackText: page['name'] ?? '?')),
                  Spacer(),
                  if (page['is_owner'] == true)
                    ElevatedButton.icon(
                      onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CreateAdScreen(pageId: widget.pageId, pageName: page['name'] ?? ''))),
                      icon: Icon(Icons.campaign_rounded, size: 16),
                      label: Text('Create Ad'),
                      style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))))
                  else
                    ElevatedButton(
                      onPressed: () async {
                        setState(() => _following = !_following);
                        try { await ref.read(communityRepoProvider).togglePageFollow(widget.pageId); } catch (_) { setState(() => _following = !_following); }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _following ? context.colors.chipBg : kOrange,
                        foregroundColor: _following ? context.colors.navyText : Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                      child: Text(_following ? 'Following' : 'Follow', style: TextStyle(fontWeight: FontWeight.w700)),
                    ),
                ])),
              SizedBox(height: 12),

              // Name
              Padding(padding: EdgeInsets.fromLTRB(16, 0, 16, 4),
                child: Row(children: [
                  Flexible(child: Text(page['name'] ?? '', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: context.colors.bodyText))),
                  if (page['is_verified'] == true) Padding(padding: EdgeInsets.only(left: 6), child: Icon(Icons.verified_rounded, color: Color(0xFF1877F2), size: 20)),
                ])),

              if (page['category'] != null)
                Padding(padding: EdgeInsets.fromLTRB(16, 0, 16, 8),
                  child: Container(padding: EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                    child: Text(page['category'], style: TextStyle(color: kOrange, fontSize: 12, fontWeight: FontWeight.w700)))),

              if (page['description'] != null)
                Padding(padding: EdgeInsets.fromLTRB(16, 0, 16, 12),
                  child: Text(page['description'], style: TextStyle(color: context.colors.bodyText, fontSize: 14, height: 1.4))),

              // Stats
              Padding(padding: EdgeInsets.fromLTRB(16, 0, 16, 16),
                child: Row(children: [
                  _stat('${page['followers_count'] ?? 0}', 'Followers'),
                  SizedBox(width: 24),
                  _stat('${page['posts_count'] ?? 0}', 'Posts'),
                ])),

              // Contact info
              if (page['phone'] != null || page['email'] != null || page['website'] != null || page['address'] != null) ...[
                Divider(height: 1),
                Padding(padding: EdgeInsets.all(16), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Contact', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.bodyText)),
                  SizedBox(height: 10),
                  if (page['phone'] != null) _contactRow(Icons.phone_rounded, page['phone']),
                  if (page['email'] != null) _contactRow(Icons.email_rounded, page['email']),
                  if (page['website'] != null) _contactRow(Icons.language_rounded, page['website']),
                  if (page['address'] != null) _contactRow(Icons.location_on_rounded, page['address']),
                ])),
              ],

              Divider(height: 1),
              Padding(padding: EdgeInsets.fromLTRB(16, 16, 16, 8),
                child: Row(children: [
                  Text('Posts', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.bodyText)),
                  Spacer(),
                  if (page['is_owner'] == true) GestureDetector(
                    onTap: () async {
                      await Navigator.push(context, MaterialPageRoute(builder: (_) => _PageCreatePostScreen(pageId: widget.pageId, pageName: page['name'] ?? '')));
                      ref.invalidate(_pagePostsProvider(widget.pageId));
                    },
                    child: Container(padding: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                      decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(8)),
                      child: Row(mainAxisSize: MainAxisSize.min, children: [
                        Icon(Icons.add_rounded, color: Colors.white, size: 16),
                        SizedBox(width: 4),
                        Text('New Post', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
                      ])),
                  ),
                ])),
            ]))),

            // Posts
            Consumer(builder: (context, ref, _) {
              final postsAsync = ref.watch(_pagePostsProvider(widget.pageId));
              return postsAsync.when(
                loading: () => const SliverToBoxAdapter(child: Center(child: Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator(color: kOrange)))),
                error: (_, __) => const SliverToBoxAdapter(child: SizedBox()),
                data: (posts) {
                  if (posts.isEmpty) {
                    return SliverToBoxAdapter(child: Padding(padding: EdgeInsets.all(40),
                      child: Center(child: Text('No posts yet', style: TextStyle(color: context.colors.mutedText, fontSize: 14)))));
                  }
                  return SliverList(delegate: SliverChildBuilderDelegate(
                    (_, i) => _SimplePostCard(post: posts[i]),
                    childCount: posts.length,
                  ));
                },
              );
            }),
          ]);
        },
      ),
    );
  }

  Widget _stat(String value, String label) => Column(children: [
    Text(value, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: context.colors.bodyText)),
    Text(label, style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
  ]);

  Widget _contactRow(IconData icon, String text) => Padding(
    padding: EdgeInsets.only(bottom: 8),
    child: Row(children: [
      Icon(icon, size: 18, color: kOrange),
      SizedBox(width: 10),
      Expanded(child: Text(text, style: TextStyle(fontSize: 14, color: context.colors.bodyText))),
    ]),
  );
}

// ══════════════════════════════════════════════════════════════════
// CREATE BUSINESS PAGE
// ══════════════════════════════════════════════════════════════════

class _CreatePageScreen extends ConsumerStatefulWidget {
  const _CreatePageScreen();
  @override
  ConsumerState<_CreatePageScreen> createState() => _CreatePageScreenState();
}

class _CreatePageScreenState extends ConsumerState<_CreatePageScreen> {
  final _nameCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  String? _category;
  bool _creating = false;

  static const _categories = ['Restaurant', 'Shop', 'Services', 'Real Estate', 'Education', 'Health', 'Technology', 'Fashion', 'Other'];

  @override
  void dispose() { _nameCtrl.dispose(); _descCtrl.dispose(); _phoneCtrl.dispose(); _emailCtrl.dispose(); super.dispose(); }

  Future<void> _create() async {
    if (_nameCtrl.text.trim().isEmpty) return;
    setState(() => _creating = true);
    try {
      final form = FormData.fromMap({
        'name': _nameCtrl.text.trim(),
        if (_descCtrl.text.trim().isNotEmpty) 'description': _descCtrl.text.trim(),
        if (_category != null) 'category': _category,
        if (_phoneCtrl.text.trim().isNotEmpty) 'phone': _phoneCtrl.text.trim(),
        if (_emailCtrl.text.trim().isNotEmpty) 'email': _emailCtrl.text.trim(),
      });
      await ref.read(communityRepoProvider).createBusinessPage(form);
      ref.invalidate(_myPagesProvider);
      ref.invalidate(_businessPagesProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Business page created!'), backgroundColor: Color(0xFF10B981)));
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: Colors.red));
    } finally { if (mounted) setState(() => _creating = false); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Create Business Page', style: TextStyle(fontWeight: FontWeight.w800))),
      body: ListView(padding: EdgeInsets.all(16), children: [
        _field('Business Name *', _nameCtrl, Icons.store_rounded),
        SizedBox(height: 14),
        _field('Description', _descCtrl, Icons.description_rounded, maxLines: 3),
        SizedBox(height: 14),
        Text('Category', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.bodyText)),
        SizedBox(height: 6),
        Wrap(spacing: 8, runSpacing: 8, children: _categories.map((c) => ChoiceChip(
          label: Text(c), selected: _category == c,
          onSelected: (s) => setState(() => _category = s ? c : null),
          selectedColor: kOrange.withValues(alpha: 0.2),
          labelStyle: TextStyle(fontWeight: FontWeight.w600, color: _category == c ? kOrange : const Color(0xFF6B7280)),
        )).toList()),
        SizedBox(height: 14),
        _field('Phone', _phoneCtrl, Icons.phone_rounded, inputType: TextInputType.phone),
        SizedBox(height: 14),
        _field('Email', _emailCtrl, Icons.email_rounded, inputType: TextInputType.emailAddress),
        SizedBox(height: 24),
        ElevatedButton(
          onPressed: _creating || _nameCtrl.text.trim().isEmpty ? null : _create,
          style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
            padding: EdgeInsets.symmetric(vertical: 14),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
          child: _creating ? SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : Text('Create Page', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
        ),
      ]),
    );
  }

  Widget _field(String label, TextEditingController ctrl, IconData icon, {int maxLines = 1, TextInputType? inputType}) =>
    TextField(controller: ctrl, maxLines: maxLines, keyboardType: inputType, onChanged: (_) => setState(() {}),
      decoration: InputDecoration(labelText: label, prefixIcon: Icon(icon, color: kOrange, size: 20),
        filled: true, fillColor: context.colors.surfaceBg,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: context.colors.borderColor)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: context.colors.borderColor))));
}

// ── Simple Post Card for Page Detail ──────────────────────────────

class _SimplePostCard extends StatelessWidget {
  final CommunityPost post;
  const _SimplePostCard({required this.post});

  @override
  Widget build(BuildContext context) {
    final p = post;
    return Container(
      margin: EdgeInsets.symmetric(horizontal: 0, vertical: 0),
      color: context.colors.cardBg,
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Header
        Padding(padding: EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Row(children: [
            CircleNetImage(url: p.user.avatar, size: 36, fallbackText: p.user.name),
            SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(p.user.name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.bodyText)),
              Text(_timeAgo(p.createdAt), style: TextStyle(color: context.colors.mutedText, fontSize: 11)),
            ])),
          ]),
        ),

        // Content
        if (p.content != null && p.content!.isNotEmpty)
          Padding(padding: EdgeInsets.fromLTRB(16, 0, 16, 8),
            child: Text(p.content!, style: TextStyle(fontSize: 14, color: context.colors.bodyText, height: 1.4))),

        // Media
        if (p.media.isNotEmpty)
          p.media.first.type == 'image'
              ? NetImage(url: p.media.first.url, fit: BoxFit.cover, width: double.infinity)
              : Stack(alignment: Alignment.center, children: [
                  NetImage(url: p.media.first.thumbnail ?? p.media.first.url, fit: BoxFit.cover, width: double.infinity, height: 200),
                  Container(width: 48, height: 48, decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.5), shape: BoxShape.circle),
                    child: Icon(Icons.play_arrow_rounded, color: Colors.white, size: 28)),
                ]),

        // Stats
        Padding(padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Row(children: [
            Icon(Icons.favorite_rounded, size: 16, color: context.colors.mutedText),
            SizedBox(width: 4),
            Text('${p.likesCount}', style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
            SizedBox(width: 16),
            Icon(Icons.chat_bubble_outline_rounded, size: 16, color: context.colors.mutedText),
            SizedBox(width: 4),
            Text('${p.commentsCount}', style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
            SizedBox(width: 16),
            Icon(Icons.share_rounded, size: 16, color: context.colors.mutedText),
            SizedBox(width: 4),
            Text('${p.sharesCount}', style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
          ]),
        ),
        Divider(height: 1),
      ]),
    );
  }

  String _timeAgo(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 1) return 'now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m';
    if (diff.inHours < 24) return '${diff.inHours}h';
    if (diff.inDays < 7) return '${diff.inDays}d';
    return '${dt.day}/${dt.month}/${dt.year}';
  }
}

// ── Page Create Post Screen ─────────────────────────────────────

class _PageCreatePostScreen extends ConsumerStatefulWidget {
  final int pageId;
  final String pageName;
  const _PageCreatePostScreen({required this.pageId, required this.pageName});
  @override
  ConsumerState<_PageCreatePostScreen> createState() => _PageCreatePostScreenState();
}

class _PageCreatePostScreenState extends ConsumerState<_PageCreatePostScreen> {
  final _contentCtrl = TextEditingController();
  List<XFile> _mediaFiles = [];
  String _postType = 'text';
  bool _posting = false;

  Future<void> _pickMedia() async {
    final picker = ImagePicker();
    final files = await picker.pickMultiImage(imageQuality: 80);
    if (files.isNotEmpty) setState(() { _mediaFiles = files; _postType = 'image'; });
  }

  Future<void> _pickVideo() async {
    final picker = ImagePicker();
    final file = await picker.pickVideo(source: ImageSource.gallery, maxDuration: const Duration(minutes: 5));
    if (file != null) setState(() { _mediaFiles = [file]; _postType = 'video'; });
  }

  Future<void> _post() async {
    if (_contentCtrl.text.trim().isEmpty && _mediaFiles.isEmpty) return;
    setState(() => _posting = true);
    try {
      final mediaMultiparts = <MultipartFile>[];
      for (final f in _mediaFiles) {
        final bytes = await f.readAsBytes();
        final ext = f.name.split('.').last.toLowerCase();
        final mime = ['mp4', 'mov'].contains(ext) ? 'video/$ext' : (ext == 'png' ? 'image/png' : 'image/jpeg');
        mediaMultiparts.add(MultipartFile.fromBytes(bytes, filename: f.name, contentType: DioMediaType.parse(mime)));
      }
      await ref.read(communityRepoProvider).createPost(
        type: _postType,
        content: _contentCtrl.text.trim(),
        pageId: widget.pageId,
        mediaFiles: mediaMultiparts,
      );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Posted!'), backgroundColor: Color(0xFF10B981)));
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red));
    } finally { if (mounted) setState(() => _posting = false); }
  }

  @override
  void dispose() { _contentCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Post to ${widget.pageName}', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
        actions: [
          TextButton(
            onPressed: _posting ? null : _post,
            child: _posting
                ? SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: kOrange))
                : Text('Post', style: TextStyle(color: kOrange, fontWeight: FontWeight.w800, fontSize: 16)),
          ),
        ],
      ),
      body: Column(children: [
        Expanded(child: TextField(
          controller: _contentCtrl, maxLines: null, expands: true, textAlignVertical: TextAlignVertical.top,
          decoration: InputDecoration(hintText: "What's on your mind?", border: InputBorder.none, contentPadding: EdgeInsets.all(16)),
        )),
        if (_mediaFiles.isNotEmpty) SizedBox(height: 100, child: ListView(
          scrollDirection: Axis.horizontal, padding: EdgeInsets.symmetric(horizontal: 16),
          children: _mediaFiles.map((f) => Padding(padding: EdgeInsets.only(right: 8),
            child: Stack(children: [
              ClipRRect(borderRadius: BorderRadius.circular(10),
                child: Image.file(File(f.path), width: 100, height: 100, fit: BoxFit.cover)),
              Positioned(top: 4, right: 4, child: GestureDetector(
                onTap: () => setState(() => _mediaFiles.remove(f)),
                child: Container(width: 22, height: 22, decoration: BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                  child: Icon(Icons.close, color: Colors.white, size: 14)))),
            ]))).toList(),
        )),
        Container(
          padding: EdgeInsets.symmetric(horizontal: 8, vertical: 8),
          decoration: BoxDecoration(border: Border(top: BorderSide(color: const Color(0xFFE5E7EB).withValues(alpha: 0.5)))),
          child: Row(children: [
            IconButton(icon: Icon(Icons.image_rounded, color: kOrange), onPressed: _pickMedia),
            IconButton(icon: Icon(Icons.videocam_rounded, color: kOrange), onPressed: _pickVideo),
          ]),
        ),
      ]),
    );
  }
}
