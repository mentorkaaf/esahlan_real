import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';
import '../../data/models/community_models.dart';
import '../../../../features/modules/erent/erent_screen.dart';
import '../providers/community_provider.dart';
import '../widgets/comments_sheet.dart';
import '../widgets/video_ad_overlay.dart';
import '../services/video_controller_pool.dart';
import 'community_shell.dart';

// Unified reel item — either a community post or an eRent property reel
class _ReelItem {
  final CommunityPost? communityPost;
  final Map<String, dynamic>? rentReel;

  const _ReelItem.community(this.communityPost) : rentReel = null;
  const _ReelItem.rent(this.rentReel) : communityPost = null;

  bool get isRent => rentReel != null;
}

class ReelsScreen extends ConsumerStatefulWidget {
  const ReelsScreen({super.key});

  @override
  ConsumerState<ReelsScreen> createState() => _ReelsScreenState();
}

class _ReelsScreenState extends ConsumerState<ReelsScreen> {
  final PageController _pageCtrl = PageController();
  int _currentIndex = 0;

  @override
  void dispose() {
    _pageCtrl.dispose();
    super.dispose();
  }

  List<_ReelItem> _buildCombinedList(
      List<CommunityPost> communityReels, List<Map<String, dynamic>> rentReels) {
    final items = <_ReelItem>[];
    int ci = 0, ri = 0;
    while (ci < communityReels.length || ri < rentReels.length) {
      for (int i = 0; i < 2 && ci < communityReels.length; i++, ci++) {
        items.add(_ReelItem.community(communityReels[ci]));
      }
      if (ri < rentReels.length) {
        items.add(_ReelItem.rent(rentReels[ri++]));
      }
    }
    return items;
  }

  @override
  Widget build(BuildContext context) {
    final reelsAsync = ref.watch(communityReelsProvider);
    final rentAsync = ref.watch(erentReelsProvider);
    // Only play video when this tab (index 1) is active
    final tabActive = ref.watch(communityNavIndexProvider) == 1;

    final communityReels = reelsAsync.valueOrNull ?? [];
    final rentReels = rentAsync.valueOrNull ?? [];
    final combined = _buildCombinedList(communityReels, rentReels);


    if (reelsAsync.isLoading && rentAsync.isLoading) {
      return const Scaffold(
        backgroundColor: Colors.black,
        body: Center(child: CircularProgressIndicator(color: Colors.white)),
      );
    }

    return Scaffold(
      backgroundColor: Colors.black,
      extendBodyBehindAppBar: true,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        title: const Text('Reels', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
        actions: [
          IconButton(
            icon: const Icon(Icons.videocam_outlined, color: Colors.white),
            onPressed: () {},
          ),
        ],
      ),
      body: combined.isEmpty
          ? const Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.videocam_off, size: 64, color: Colors.white54),
                SizedBox(height: 16),
                Text('No reels yet', style: TextStyle(color: Colors.white54, fontSize: 16)),
              ]),
            )
          : PageView.builder(
              controller: _pageCtrl,
              scrollDirection: Axis.vertical,
              itemCount: combined.length,
              onPageChanged: (i) {
                setState(() => _currentIndex = i);
                if (i >= combined.length - 3) {
                  ref.read(communityReelsProvider.notifier).load();
                }
                // Warmup next reel
                if (i + 1 < combined.length) {
                  final next = combined[i + 1];
                  if (!next.isRent && next.communityPost != null) {
                    final m = next.communityPost!.media.where((m) => m.type == 'video').firstOrNull;
                    if (m != null) VideoControllerPool().warmup(m.url);
                  }
                }
              },
              itemBuilder: (_, i) {
                final item = combined[i];
                final isPageActive = i == _currentIndex && tabActive;
                if (item.isRent) {
                  return _RentReelCard(
                    reel: item.rentReel!,
                    isActive: isPageActive,
                    key: ValueKey('rent_${item.rentReel!['property_id']}_${item.rentReel!['video_url']}'),
                  );
                }
                return _CommunityReelCard(
                  reel: item.communityPost!,
                  isActive: isPageActive,
                  key: ValueKey('comm_${item.communityPost!.id}'),
                );
              },
            ),
    );
  }
}

// ── Community reel card ────────────────────────────────────────────────────────

class _CommunityReelCard extends ConsumerStatefulWidget {
  final CommunityPost reel;
  final bool isActive;
  const _CommunityReelCard({super.key, required this.reel, required this.isActive});

  @override
  ConsumerState<_CommunityReelCard> createState() => _CommunityReelCardState();
}

class _CommunityReelCardState extends ConsumerState<_CommunityReelCard> {
  VideoPlayerController? _videoCtrl;
  bool _videoReady = false;
  bool _muted = false;
  bool _paused = false;
  bool _liked = false;
  bool _saved = false;
  bool _showHeart = false;
  int _likesCount = 0;
  String? _videoUrl;
  final _pool = VideoControllerPool();

  @override
  void initState() {
    super.initState();
    _liked = widget.reel.userReaction != null;
    _saved = widget.reel.isSaved;
    _likesCount = widget.reel.likesCount;
    _initVideo();
  }

  void _initVideo() async {
    final media = widget.reel.media.where((m) => m.type == 'video').firstOrNull ??
        (widget.reel.media.isNotEmpty ? widget.reel.media.first : null);
    if (media == null || media.type != 'video') return;
    _videoUrl = media.url;
    try {
      final ctrl = await _pool.acquire(media.url);
      if (!mounted) return;
      ctrl.setVolume(_muted ? 0 : 1);
      setState(() { _videoCtrl = ctrl; _videoReady = true; });
      if (widget.isActive && !_paused) ctrl.play();
    } catch (_) {}
  }

  @override
  void didUpdateWidget(_CommunityReelCard old) {
    super.didUpdateWidget(old);
    if (widget.isActive != old.isActive) {
      if (widget.isActive) {
        if (!_paused) _videoCtrl?.play();
      } else {
        _videoCtrl?.pause();
      }
    }
  }

  @override
  void dispose() {
    _videoCtrl?.pause();
    if (_videoUrl != null) _pool.release(_videoUrl!);
    super.dispose();
  }

  void _togglePause() {
    if (_videoCtrl == null || !_videoReady) return;
    setState(() => _paused = !_paused);
    _paused ? _videoCtrl!.pause() : _videoCtrl!.play();
  }

  void _onDoubleTap() {
    setState(() { _showHeart = true; if (!_liked) { _liked = true; _likesCount++; ref.read(communityRepoProvider).reactToPost(widget.reel.id, 'like'); } });
    Future.delayed(const Duration(milliseconds: 800), () { if (mounted) setState(() => _showHeart = false); });
  }

  @override
  Widget build(BuildContext context) {
    final reel = widget.reel;
    final media = reel.media.isNotEmpty ? reel.media.first : null;

    final reelContent = GestureDetector(
      onTap: _togglePause,
      onDoubleTap: _onDoubleTap,
      child: Stack(fit: StackFit.expand, children: [
        // Video directly
        if (_videoReady && _videoCtrl != null)
          Center(child: AspectRatio(aspectRatio: _videoCtrl!.value.aspectRatio, child: VideoPlayer(_videoCtrl!)))
        else
          Container(color: const Color(0xFF0D0E1A)),

        // Gradient
        Container(decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.black45, Colors.transparent, Colors.transparent, Colors.black87], stops: [0, 0.2, 0.5, 1.0]))),

        // Pause icon overlay
        if (_paused && _videoReady)
          Center(child: Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
            child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 48),
          )),

        if (_showHeart)
          Center(child: Icon(Icons.favorite, size: 100, color: Colors.red.withOpacity(0.85))),

        // Mute
        Positioned(top: MediaQuery.of(context).padding.top + 64, right: 12,
          child: GestureDetector(onTap: () { setState(() => _muted = !_muted); _videoCtrl?.setVolume(_muted ? 0 : 1); },
            child: Container(padding: const EdgeInsets.all(8), decoration: const BoxDecoration(color: Colors.black45, shape: BoxShape.circle), child: Icon(_muted ? Icons.volume_off : Icons.volume_up, color: Colors.white, size: 20)))),

        // Bottom info
        Positioned(bottom: 80, left: 16, right: 80,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            GestureDetector(
              onTap: () {},
              child: Row(children: [
                CircleAvatar(radius: 18, backgroundColor: const Color(0xFFEEF0FF), backgroundImage: reel.user.avatar != null ? CachedNetworkImageProvider(reel.user.avatar!) : null, child: reel.user.avatar == null ? Text(reel.user.name[0], style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14)) : null),
                const SizedBox(width: 8),
                Text(reel.user.name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
                if (reel.user.isVerified) ...[const SizedBox(width: 4), const Icon(Icons.verified, size: 14, color: kOrange)],
                const SizedBox(width: 10),
                GestureDetector(onTap: () => ref.read(communityRepoProvider).toggleFollow(reel.user.id),
                  child: Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4), decoration: BoxDecoration(border: Border.all(color: Colors.white), borderRadius: BorderRadius.circular(20)), child: const Text('Follow', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)))),
              ]),
            ),
            if (reel.content != null && reel.content!.isNotEmpty) ...[const SizedBox(height: 8), Text(reel.content!, style: const TextStyle(color: Colors.white, fontSize: 13), maxLines: 2, overflow: TextOverflow.ellipsis)],
          ])),

        // Right actions
        Positioned(right: 10, bottom: 100, child: Column(children: [
          _sideAction(icon: _liked ? Icons.favorite : Icons.favorite_outline, label: '$_likesCount', color: _liked ? Colors.red : Colors.white, onTap: () { setState(() { _liked = !_liked; _likesCount += _liked ? 1 : -1; }); ref.read(communityRepoProvider).reactToPost(reel.id, 'like'); }),
          const SizedBox(height: 20),
          _sideAction(icon: Icons.chat_bubble_outline, label: '${reel.commentsCount}', color: Colors.white, onTap: () => showCommentsSheet(context, reel.id, initialCount: reel.commentsCount)),
          const SizedBox(height: 20),
          _sideAction(icon: Icons.send_outlined, label: 'Share', color: Colors.white, onTap: () => ref.read(communityRepoProvider).sharePost(reel.id)),
          const SizedBox(height: 20),
          _sideAction(icon: _saved ? Icons.bookmark : Icons.bookmark_outline, label: 'Save', color: _saved ? kOrange : Colors.white, onTap: () async { final s = await ref.read(communityRepoProvider).savePost(reel.id); setState(() => _saved = s); }),
          const SizedBox(height: 20),
          _sideAction(icon: Icons.visibility_outlined, label: '${reel.viewsCount}', color: Colors.white70, onTap: () {}),
        ])),
      ]),
    );

    return _videoReady && _videoCtrl != null
        ? VideoAdOverlay(mainController: _videoCtrl!, child: reelContent)
        : reelContent;
  }

  Widget _sideAction({required IconData icon, required String label, required Color color, required VoidCallback onTap}) =>
      GestureDetector(onTap: onTap, child: Column(children: [Icon(icon, color: color, size: 28), const SizedBox(height: 2), Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600))]));
}

// ── eRent reel card ────────────────────────────────────────────────────────────

class _RentReelCard extends ConsumerStatefulWidget {
  final Map<String, dynamic> reel;
  final bool isActive;
  const _RentReelCard({super.key, required this.reel, required this.isActive});

  @override
  ConsumerState<_RentReelCard> createState() => _RentReelCardState();
}

class _RentReelCardState extends ConsumerState<_RentReelCard> {
  VideoPlayerController? _videoCtrl;
  bool _videoReady = false;
  bool _muted = false;
  bool _paused = false; // manual pause by user tap
  bool _showHeart = false;

  @override
  void initState() {
    super.initState();
    _initVideo();
  }

  void _initVideo() {
    final url = widget.reel['video_url'] as String? ?? '';
    if (url.isEmpty) return;
    _videoCtrl = VideoPlayerController.networkUrl(Uri.parse(url))
      ..initialize().then((_) {
        if (mounted) {
          setState(() => _videoReady = true);
          _videoCtrl!.setLooping(true);
          _videoCtrl!.setVolume(_muted ? 0 : 1);
          if (widget.isActive && !_paused) _videoCtrl!.play();
        }
      }).catchError((_) {});
  }

  @override
  void didUpdateWidget(_RentReelCard old) {
    super.didUpdateWidget(old);
    if (widget.isActive != old.isActive) {
      if (widget.isActive) {
        if (!_paused) _videoCtrl?.play();
      } else {
        _videoCtrl?.pause();
      }
    }
  }

  @override
  void dispose() { _videoCtrl?.dispose(); super.dispose(); }

  void _togglePause() {
    if (_videoCtrl == null || !_videoReady) return;
    setState(() => _paused = !_paused);
    _paused ? _videoCtrl!.pause() : _videoCtrl!.play();
  }

  void _openProperty(BuildContext context) {
    final propertyId = widget.reel['property_id'];
    if (propertyId == null) return;
    Navigator.of(context, rootNavigator: true).push(
      MaterialPageRoute(builder: (_) => PropertyDetailScreen(propertyId: int.parse(propertyId.toString()))),
    );
  }

  @override
  Widget build(BuildContext context) {
    final r = widget.reel;
    final thumbnail = r['thumbnail'] as String?;
    final title = r['property_title'] as String? ?? 'Property';
    final district = r['district_name'] as String? ?? '';
    final rent = r['monthly_rent'];

    return GestureDetector(
      onTap: _togglePause,
      onDoubleTap: () { setState(() => _showHeart = true); Future.delayed(const Duration(milliseconds: 800), () { if (mounted) setState(() => _showHeart = false); }); },
      child: Stack(fit: StackFit.expand, children: [
        // Video or thumbnail
        if (_videoReady && _videoCtrl != null)
          Center(child: AspectRatio(aspectRatio: _videoCtrl!.value.aspectRatio, child: VideoPlayer(_videoCtrl!)))
        else if (thumbnail != null)
          NetImage(url: thumbnail, fit: BoxFit.cover)
        else
          Container(color: const Color(0xFF1A1B2E)),

        // Gradient
        Container(decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Colors.black45, Colors.transparent, Colors.transparent, Colors.black87], stops: [0, 0.2, 0.5, 1.0]))),

        if (!_videoReady)
          const Center(child: CircularProgressIndicator(color: Colors.white54, strokeWidth: 2)),

        // Pause icon overlay
        if (_paused && _videoReady)
          Center(child: Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
            child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 48),
          )),

        if (_showHeart)
          Center(child: Icon(Icons.favorite, size: 100, color: Colors.red.withOpacity(0.85))),

        // eRent badge top-left
        Positioned(top: MediaQuery.of(context).padding.top + 64, left: 16,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(20)),
            child: const Row(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.home_rounded, color: Colors.white, size: 14),
              SizedBox(width: 5),
              Text('eRent', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 12)),
            ]),
          )),

        // Mute
        Positioned(top: MediaQuery.of(context).padding.top + 64, right: 12,
          child: GestureDetector(onTap: () { setState(() => _muted = !_muted); _videoCtrl?.setVolume(_muted ? 0 : 1); },
            child: Container(padding: const EdgeInsets.all(8), decoration: const BoxDecoration(color: Colors.black45, shape: BoxShape.circle), child: Icon(_muted ? Icons.volume_off : Icons.volume_up, color: Colors.white, size: 20)))),

        // Bottom info — tappable title navigates to property detail
        Positioned(bottom: 80, left: 16, right: 80,
          child: GestureDetector(
            onTap: () => _openProperty(context),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
              Row(children: [
                Container(width: 40, height: 40, decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(10)), child: const Icon(Icons.home_rounded, color: Colors.white, size: 22)),
                const SizedBox(width: 10),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14), maxLines: 1, overflow: TextOverflow.ellipsis),
                  if (district.isNotEmpty) Text(district, style: const TextStyle(color: Colors.white70, fontSize: 12)),
                ])),
                const Icon(Icons.chevron_right_rounded, color: Colors.white70, size: 20),
              ]),
              if (rent != null) ...[
                const SizedBox(height: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(12)),
                  child: Text('\$$rent/mo', style: const TextStyle(color: kOrange, fontWeight: FontWeight.w800, fontSize: 14)),
                ),
              ],
            ]),
          )),

        // Right actions
        Positioned(right: 10, bottom: 100, child: Column(children: [
          _sideAction(icon: Icons.home_work_outlined, label: 'View', color: Colors.white, onTap: () => _openProperty(context)),
          const SizedBox(height: 20),
          _sideAction(icon: Icons.send_outlined, label: 'Share', color: Colors.white, onTap: () {}),
        ])),
      ]),
    );
  }

  Widget _sideAction({required IconData icon, required String label, required Color color, required VoidCallback onTap}) =>
      GestureDetector(onTap: onTap, child: Column(children: [Icon(icon, color: color, size: 28), const SizedBox(height: 2), Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w600))]));
}
