import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';
import '../services/video_engine.dart';
import '../../data/models/community_models.dart';
import '../../../../features/modules/erent/erent_screen.dart';
import '../providers/community_provider.dart';
import '../widgets/comments_sheet.dart';
import '../widgets/video_ad_overlay.dart';
import '../../../../core/services/realtime_client.dart';
import 'community_shell.dart';

// Unified reel item — community post, eRent reel, or ad
class _ReelItem {
  final CommunityPost? communityPost;
  final Map<String, dynamic>? rentReel;
  final Map<String, dynamic>? adData;

  const _ReelItem.community(this.communityPost) : rentReel = null, adData = null;
  const _ReelItem.rent(this.rentReel) : communityPost = null, adData = null;
  const _ReelItem.ad(this.adData) : communityPost = null, rentReel = null;

  bool get isRent => rentReel != null;
  bool get isAd => adData != null;

  String get videoUrl {
    if (communityPost != null) {
      final m = communityPost!.media.where((m) => m.type == 'video').firstOrNull;
      return m?.hlsUrl ?? m?.url ?? '';
    }
    if (rentReel != null) return rentReel!['video_url'] as String? ?? '';
    if (adData != null) return adData!['media_url'] as String? ?? '';
    return '';
  }
}

class ReelsScreen extends ConsumerStatefulWidget {
  const ReelsScreen({super.key});

  @override
  ConsumerState<ReelsScreen> createState() => _ReelsScreenState();
}

class _ReelsScreenState extends ConsumerState<ReelsScreen>
    with WidgetsBindingObserver {
  final PageController _pageCtrl = PageController();
  int _currentIndex = 0;
  List<_ReelItem>? _cachedItems;

  int _foldedCommunityCount = 0;
  int _foldedRentCount = 0;
  int _adInterleaveCounter = 0;
  List<Map<String, dynamic>> _reelAds = [];

  final _engine = VideoEngine.instance;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadReelAds();
    _pageCtrl.addListener(_onPageScroll);
  }

  bool _lifecyclePaused = false;

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused) {
      _lifecyclePaused = true;
      _engine.pauseAll();
    } else if (state == AppLifecycleState.inactive) {
      // 'inactive' fires both when going to background (after resumed) AND
      // when returning on iOS (before resumed). Only pause on the way OUT.
      if (!_lifecyclePaused) {
        _lifecyclePaused = true;
        _engine.pauseAll();
      }
    } else if (state == AppLifecycleState.resumed) {
      _lifecyclePaused = false;
      // Re-activate the current reel. Uses seekTo internally so ExoPlayer/
      // AVPlayer re-buffers after the OS may have released codec resources.
      final items = _cachedItems;
      if (items == null || _currentIndex >= items.length) return;
      final url = items[_currentIndex].videoUrl;
      if (url.isNotEmpty) _engine.reactivate(url);
    }
  }

  void _onPageScroll() {
    // Report scroll velocity — no setState here, onPageChanged handles index updates.
    // Calling setState on every scroll frame caused ANR (continuous rebuilds).
    if (!_pageCtrl.hasClients) return;
    try {
      final velocity = _pageCtrl.position.activity?.velocity ?? 0.0;
      _engine.notifyScrollVelocity(velocity.abs());
    } catch (_) {}
  }

  void _autoScrollNext(int current, int total) {
    if (!mounted || !_pageCtrl.hasClients) return;
    if (current + 1 < total) {
      _pageCtrl.animateToPage(
        current + 1,
        duration: const Duration(milliseconds: 350),
        curve: Curves.easeOutCubic,
      );
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _engine.pauseAll();
    _pageCtrl.removeListener(_onPageScroll);
    _pageCtrl.dispose();
    super.dispose();
  }

  void _loadReelAds() async {
    try {
      final repo = ref.read(communityRepoProvider);
      for (var i = 0; i < 3; i++) {
        final ad = await repo.getPrerollAd();
        if (ad != null && ad['media_url'] != null) _reelAds.add(ad);
      }
      if (mounted) setState(() {});
    } catch (_) {}
  }

  List<_ReelItem> _buildCombinedList(
      List<CommunityPost> communityReels, List<Map<String, dynamic>> rentReels) {
    final items = <_ReelItem>[];
    int ci = 0, ri = 0, ai = 0;
    final shuffled = List<CommunityPost>.from(communityReels)..shuffle();

    while (ci < shuffled.length || ri < rentReels.length) {
      for (int i = 0; i < 2 && ci < shuffled.length; i++, ci++) {
        items.add(_ReelItem.community(shuffled[ci]));
        _adInterleaveCounter++;
        if (_adInterleaveCounter % 4 == 0 && ai < _reelAds.length) {
          items.add(_ReelItem.ad(_reelAds[ai++]));
        }
      }
      if (ri < rentReels.length) {
        items.add(_ReelItem.rent(rentReels[ri++]));
      }
    }
    return items;
  }

  /// Flat list of all video URLs in current feed order — fed to the engine
  /// so it can do position-aware eviction and predictive preloading.
  List<String> _extractVideoUrls(List<_ReelItem> items) =>
      items.map((e) => e.videoUrl).toList();

  void _onPageChanged(int i, List<_ReelItem> combined) {
    setState(() => _currentIndex = i);

    // Pagination: load more when 3 from end
    if (i >= combined.length - 3) {
      ref.read(communityReelsProvider.notifier).load();
    }

    // Update engine position context — enables distance-based eviction
    final urls = _extractVideoUrls(combined);
    _engine.setFeedContext(urls, i);

    // Predictive preload — velocity-adaptive
    _engine.preloadFromIndex(i, urls);
  }

  @override
  Widget build(BuildContext context) {
    final reelsAsync = ref.watch(communityReelsProvider);
    final rentAsync = ref.watch(erentReelsProvider);
    final tabActive = ref.watch(communityNavIndexProvider) == 1;

    final communityReels = reelsAsync.valueOrNull ?? [];
    final rentReels = rentAsync.valueOrNull ?? [];

    if (_cachedItems == null) {
      _cachedItems = _buildCombinedList(communityReels, rentReels);
      _foldedCommunityCount = communityReels.length;
      _foldedRentCount = rentReels.length;
      // Prime the engine with the initial feed context
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_cachedItems != null && mounted) {
          final urls = _extractVideoUrls(_cachedItems!);
          _engine.setFeedContext(urls, 0);
          _engine.preloadFromIndex(0, urls);
        }
      });
    } else if (!reelsAsync.isLoading && !rentAsync.isLoading &&
        (communityReels.length < _foldedCommunityCount ||
        rentReels.length < _foldedRentCount)) {
      // Guard: only shrink the list when providers are NOT loading.
      // A refresh (e.g. triggered by resume) temporarily sets valueOrNull=null
      // (empty list) which would wipe _cachedItems and dispose all reel cards.
      _cachedItems = _buildCombinedList(communityReels, rentReels);
      _foldedCommunityCount = communityReels.length;
      _foldedRentCount = rentReels.length;
      _adInterleaveCounter = 0;
      if (_currentIndex > 0 && _pageCtrl.hasClients) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (_pageCtrl.hasClients) _pageCtrl.jumpToPage(0);
          setState(() => _currentIndex = 0);
        });
      }
    } else if (communityReels.length > _foldedCommunityCount ||
        rentReels.length > _foldedRentCount) {
      final newCommunity = communityReels.sublist(_foldedCommunityCount);
      final newRent = rentReels.sublist(_foldedRentCount);
      _cachedItems!.addAll(_buildCombinedList(newCommunity, newRent));
      _foldedCommunityCount = communityReels.length;
      _foldedRentCount = rentReels.length;
    }

    final combined = _cachedItems!;

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
        title: const Text('Reels',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
        actions: [
          IconButton(
            icon: const Icon(Icons.videocam_outlined, color: Colors.white),
            onPressed: () {},
          ),
        ],
      ),
      body: combined.isEmpty
          ? Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(
                    reelsAsync.hasError
                        ? Icons.wifi_off_rounded
                        : Icons.videocam_off,
                    size: 64,
                    color: Colors.white54),
                const SizedBox(height: 16),
                Text(
                  reelsAsync.hasError ? 'Couldn\'t load reels' : 'No reels yet',
                  style: const TextStyle(color: Colors.white54, fontSize: 16),
                ),
                if (reelsAsync.hasError) ...[
                  const SizedBox(height: 6),
                  Text('${reelsAsync.error}',
                      style: const TextStyle(color: Colors.white30, fontSize: 12),
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 16),
                  TextButton(
                    onPressed: () =>
                        ref.read(communityReelsProvider.notifier).refresh(),
                    child: const Text('Retry',
                        style: TextStyle(
                            color: kOrange, fontWeight: FontWeight.w700)),
                  ),
                ],
              ]),
            )
          : PageView.builder(
              controller: _pageCtrl,
              scrollDirection: Axis.vertical,
              itemCount: combined.length,
              onPageChanged: (i) => _onPageChanged(i, combined),
              itemBuilder: (_, i) {
                final item = combined[i];
                final isPageActive = i == _currentIndex && tabActive;

                if (item.isAd) {
                  return RepaintBoundary(
                    child: _ReelAdCard(
                      ad: item.adData!,
                      isActive: isPageActive,
                      key: ValueKey('reelad_$i'),
                    ),
                  );
                }
                if (item.isRent) {
                  return RepaintBoundary(
                    child: _RentReelCard(
                      reel: item.rentReel!,
                      isActive: isPageActive,
                      key: ValueKey(
                          'rent_${item.rentReel!['property_id']}_${item.rentReel!['video_url']}'),
                    ),
                  );
                }
                return RepaintBoundary(
                  child: _CommunityReelCard(
                    reel: item.communityPost!,
                    isActive: isPageActive,
                    onVideoEnd: () => _autoScrollNext(i, combined.length),
                    onSkip: () => _autoScrollNext(i, combined.length),
                    key: ValueKey('comm_${item.communityPost!.id}'),
                  ),
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
  final VoidCallback? onVideoEnd;
  final VoidCallback? onSkip;
  const _CommunityReelCard(
      {super.key,
      required this.reel,
      required this.isActive,
      this.onVideoEnd,
      this.onSkip});

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
  final _engine = VideoEngine.instance;
  String _videoUrl = '';
  String get _postChannel => 'community.post.${widget.reel.id}';
  final Map<String, void Function(dynamic)> _realtimeListeners = {};

  @override
  void initState() {
    super.initState();
    _liked = widget.reel.userReaction != null;
    _saved = widget.reel.isSaved;
    _likesCount = widget.reel.likesCount;
    _initVideo();
    if (widget.isActive) _trackView();
    if (!widget.reel.isAd) _subscribeRealtime();
  }

  void _subscribeRealtime() {
    void on(String event, void Function(dynamic) handler) {
      _realtimeListeners[event] = handler;
      RealtimeClient.instance.listen(_postChannel, event, handler);
    }

    on('post.views_changed', (data) {
      if (!mounted) return;
      setState(() => widget.reel.viewsCount = data['views_count'] as int);
    });
    on('post.likes_changed', (data) {
      if (!mounted) return;
      setState(() {
        widget.reel.likesCount = data['likes_count'] as int;
        _likesCount = widget.reel.likesCount;
      });
    });
    on('post.comment_added', (data) {
      if (!mounted) return;
      setState(() => widget.reel.commentsCount = data['comments_count'] as int);
    });
    on('post.shares_changed', (data) {
      if (!mounted) return;
      setState(() => widget.reel.sharesCount = data['shares_count'] as int);
    });
  }

  void _trackView() {
    if (widget.reel.id > 0) {
      ref.read(communityRepoProvider).trackImpressions([widget.reel.id]);
    }
  }

  void _initVideo() async {
    final media = widget.reel.media.where((m) => m.type == 'video').firstOrNull ??
        (widget.reel.media.isNotEmpty ? widget.reel.media.first : null);
    if (media == null || media.type != 'video') return;

    _videoUrl = media.hlsUrl ?? media.url;

    // Fast path: engine already has this controller (from predictive preload)
    final cached = _engine.getController(_videoUrl);
    if (cached != null && cached.value.isInitialized) {
      _videoCtrl = cached;
      _videoCtrl!.setLooping(false);
      try {
        _videoCtrl!.addListener(_onVideoProgress);
      } catch (_) {}
      if (mounted) {
        _videoCtrl = cached;
        _videoCtrl!.setLooping(false);
        try { _videoCtrl!.addListener(_onVideoProgress); } catch (_) {}
        setState(() => _videoReady = true);
        if (widget.isActive && !_paused) _engine.activate(_videoUrl);
      }
      return;
    }

    // Slow path: preload now (engine queues and deduplicates)
    final ctrl = await _engine.preload(_videoUrl);
    if (ctrl != null && mounted) {
      _videoCtrl = ctrl;
      ctrl.setLooping(false);
      try {
        ctrl.addListener(_onVideoProgress);
      } catch (_) {
        _videoCtrl = null;
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) _initVideo();
        });
        return;
      }
      setState(() => _videoReady = true);
      if (widget.isActive && !_paused) _engine.activate(_videoUrl);
    }
  }

  void _onVideoProgress() {
    if (!mounted || _videoCtrl == null || !_videoReady) return;
    try {
      final pos = _videoCtrl!.value.position;
      final dur = _videoCtrl!.value.duration;
      if (dur > Duration.zero && pos >= dur - const Duration(milliseconds: 500)) {
        try {
          _videoCtrl!.removeListener(_onVideoProgress);
        } catch (_) {}
        if (mounted) widget.onVideoEnd?.call();
      }
    } catch (_) {
      if (mounted) setState(() => _videoReady = false);
    }
  }

  @override
  void didUpdateWidget(_CommunityReelCard old) {
    super.didUpdateWidget(old);
    if (!mounted) return;
    if (widget.isActive != old.isActive) {
      if (widget.isActive) {
        if (!_paused && _videoUrl.isNotEmpty) _engine.activate(_videoUrl);
        _trackView();
      } else {
        if (_videoUrl.isNotEmpty) _engine.pause(_videoUrl);
      }
    }
  }

  @override
  void dispose() {
    try {
      _videoCtrl?.removeListener(_onVideoProgress);
    } catch (_) {}
    for (final entry in _realtimeListeners.entries) {
      RealtimeClient.instance.removeListener(_postChannel, entry.key, entry.value);
    }
    super.dispose();
  }

  void _togglePause() {
    if (!_videoReady) return;
    setState(() => _paused = !_paused);
    _paused ? _engine.pause(_videoUrl) : _engine.activate(_videoUrl);
  }

  void _onDoubleTap() {
    setState(() {
      _showHeart = true;
      if (!_liked) {
        _liked = true;
        _likesCount++;
        ref.read(communityRepoProvider).reactToPost(widget.reel.id, 'like');
      }
    });
    Future.delayed(const Duration(milliseconds: 800), () {
      if (mounted) setState(() => _showHeart = false);
    });
  }

  void _showOptions() {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1A1B2E),
      builder: (_) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          ListTile(
            leading:
                const Icon(Icons.visibility_off_rounded, color: Colors.white70),
            title:
                const Text('Not interested', style: TextStyle(color: Colors.white)),
            subtitle: const Text('See fewer reels like this',
                style: TextStyle(fontSize: 12, color: Colors.white54)),
            onTap: () {
              Navigator.pop(context);
              ref
                  .read(communityRepoProvider)
                  .trackInteraction(widget.reel.id, 'skip');
              widget.onSkip?.call();
            },
          ),
          ListTile(
            leading: const Icon(Icons.flag_rounded, color: Colors.white70),
            title: const Text('Report', style: TextStyle(color: Colors.white)),
            onTap: () => Navigator.pop(context),
          ),
        ]),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final reel = widget.reel;
    final media = reel.media.isNotEmpty ? reel.media.first : null;
    final thumbnail = media?.thumbnail;

    final reelContent = GestureDetector(
      onTap: _togglePause,
      onDoubleTap: _onDoubleTap,
      child: Stack(fit: StackFit.expand, children: [
        // Video or thumbnail/placeholder
        if (_videoReady && _videoCtrl != null)
          Center(child: AspectRatio(aspectRatio: _videoCtrl!.value.aspectRatio, child: VideoPlayer(_videoCtrl!)))
        else if (thumbnail != null)
          NetImage(url: thumbnail, fit: BoxFit.cover)
        else if (media != null && media.type == 'image')
          NetImage(url: media.url, fit: BoxFit.cover)
        else
          Container(color: const Color(0xFF1A1A2E)),

        // Gradient overlay
        Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [
                Colors.black45,
                Colors.transparent,
                Colors.transparent,
                Colors.black87
              ],
              stops: [0, 0.2, 0.5, 1.0],
            ),
          ),
        ),

        // Play icon overlay on thumbnail while video is loading
        if (!_videoReady && media?.type == 'video')
          Center(child: Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
            child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 48),
          )),

        // Buffering spinner — corner indicator while loading or re-buffering
        if (!_videoReady || (_videoCtrl != null && _videoCtrl!.value.isBuffering))
          Positioned(bottom: 120, right: 14,
            child: SizedBox(width: 24, height: 24,
              child: CircularProgressIndicator(color: Colors.white70, strokeWidth: 2.5))),

        // Pause overlay
        if (_paused && _videoReady)
          Center(
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration:
                  BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
              child: const Icon(Icons.play_arrow_rounded,
                  color: Colors.white, size: 48),
            ),
          ),

        if (_showHeart)
          Center(
              child: Icon(Icons.favorite,
                  size: 100, color: Colors.red.withOpacity(0.85))),

        // Mute button
        Positioned(
          top: MediaQuery.of(context).padding.top + 64,
          right: 12,
          child: GestureDetector(
            onTap: () {
              setState(() => _muted = !_muted);
              _videoCtrl?.setVolume(_muted ? 0 : 1);
            },
            child: Container(
              padding: const EdgeInsets.all(8),
              decoration: const BoxDecoration(
                  color: Colors.black45, shape: BoxShape.circle),
              child: Icon(_muted ? Icons.volume_off : Icons.volume_up,
                  color: Colors.white, size: 20),
            ),
          ),
        ),

        // Options
        if (!reel.user.isMe)
          Positioned(
            top: MediaQuery.of(context).padding.top + 16,
            right: 12,
            child: GestureDetector(
              onTap: _showOptions,
              child: Container(
                padding: const EdgeInsets.all(8),
                decoration: const BoxDecoration(
                    color: Colors.black45, shape: BoxShape.circle),
                child: const Icon(Icons.more_vert_rounded,
                    color: Colors.white, size: 20),
              ),
            ),
          ),

        // Bottom author info
        Positioned(
          bottom: 80,
          left: 16,
          right: 80,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              GestureDetector(
                onTap: () {},
                child: Row(children: [
                  CircleAvatar(
                    radius: 18,
                    backgroundColor: const Color(0xFFEEF0FF),
                    backgroundImage: reel.user.avatar != null
                        ? CachedNetworkImageProvider(reel.user.avatar!)
                        : null,
                    child: reel.user.avatar == null
                        ? Text(reel.user.name[0],
                            style: const TextStyle(
                                fontWeight: FontWeight.bold, fontSize: 14))
                        : null,
                  ),
                  const SizedBox(width: 8),
                  Text(reel.user.name,
                      style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                          fontSize: 14)),
                  if (reel.user.isVerified) ...[
                    const SizedBox(width: 4),
                    const Icon(Icons.verified, size: 14, color: kOrange),
                  ],
                  const SizedBox(width: 10),
                  GestureDetector(
                    onTap: () =>
                        ref.read(communityRepoProvider).toggleFollow(reel.user.id),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 12, vertical: 4),
                      decoration: BoxDecoration(
                          border: Border.all(color: Colors.white),
                          borderRadius: BorderRadius.circular(20)),
                      child: const Text('Follow',
                          style: TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w600)),
                    ),
                  ),
                ]),
              ),
              if (reel.content != null && reel.content!.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(reel.content!,
                    style:
                        const TextStyle(color: Colors.white, fontSize: 13),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis),
              ],
            ],
          ),
        ),

        // Right action buttons
        Positioned(
          right: 10,
          bottom: 100,
          child: Column(children: [
            _sideAction(
              icon: _liked ? Icons.favorite : Icons.favorite_outline,
              label: '$_likesCount',
              color: _liked ? Colors.red : Colors.white,
              onTap: () {
                setState(() {
                  _liked = !_liked;
                  _likesCount += _liked ? 1 : -1;
                });
                ref
                    .read(communityRepoProvider)
                    .reactToPost(reel.id, 'like');
              },
            ),
            const SizedBox(height: 20),
            _sideAction(
              icon: Icons.chat_bubble_outline,
              label: '${reel.commentsCount}',
              color: Colors.white,
              onTap: () => showCommentsSheet(context, reel.id,
                  initialCount: reel.commentsCount),
            ),
            const SizedBox(height: 20),
            _sideAction(
              icon: Icons.send_outlined,
              label: 'Share',
              color: Colors.white,
              onTap: () =>
                  ref.read(communityRepoProvider).sharePost(reel.id),
            ),
            const SizedBox(height: 20),
            _sideAction(
              icon: _saved ? Icons.bookmark : Icons.bookmark_outline,
              label: 'Save',
              color: _saved ? kOrange : Colors.white,
              onTap: () async {
                final s = await ref
                    .read(communityRepoProvider)
                    .savePost(reel.id);
                setState(() => _saved = s);
              },
            ),
            const SizedBox(height: 20),
            _sideAction(
              icon: Icons.visibility_outlined,
              label: '${reel.viewsCount}',
              color: Colors.white70,
              onTap: () {},
            ),
          ]),
        ),
      ]),
    );

    return _videoReady && _videoCtrl != null
        ? VideoAdOverlay(mainController: _videoCtrl!, child: reelContent)
        : reelContent;
  }

  Widget _sideAction(
          {required IconData icon,
          required String label,
          required Color color,
          required VoidCallback onTap}) =>
      GestureDetector(
        onTap: onTap,
        child: Column(children: [
          Icon(icon, color: color, size: 28),
          const SizedBox(height: 2),
          Text(label,
              style: TextStyle(
                  color: color, fontSize: 11, fontWeight: FontWeight.w600)),
        ]),
      );
}

// ── Reel Ad Card ──────────────────────────────────────────────────────────────

class _ReelAdCard extends ConsumerStatefulWidget {
  final Map<String, dynamic> ad;
  final bool isActive;
  const _ReelAdCard({super.key, required this.ad, required this.isActive});
  @override
  ConsumerState<_ReelAdCard> createState() => _ReelAdCardState();
}

class _ReelAdCardState extends ConsumerState<_ReelAdCard> {
  VideoPlayerController? _ctrl;
  bool _ready = false;
  String _adUrl = '';
  final _engine = VideoEngine.instance;

  @override
  void initState() {
    super.initState();
    _adUrl = widget.ad['media_url'] as String? ?? '';
    if (_adUrl.isNotEmpty) _initVideo();
  }

  void _initVideo() async {
    final cached = _engine.getController(_adUrl);
    if (cached != null && cached.value.isInitialized) {
      if (mounted) {
        setState(() { _ctrl = cached; _ready = true; });
        if (widget.isActive) _engine.activate(_adUrl);
      }
      return;
    }

    final ctrl = await _engine.preload(_adUrl);
    if (ctrl != null && mounted) {
      ctrl.setLooping(true);
      setState(() { _ctrl = ctrl; _ready = true; });
      if (widget.isActive) _engine.activate(_adUrl);
    }
  }

  @override
  void didUpdateWidget(_ReelAdCard old) {
    super.didUpdateWidget(old);
    if (widget.isActive != old.isActive && _adUrl.isNotEmpty) {
      if (widget.isActive) {
        _engine.activate(_adUrl);
      } else {
        _engine.pause(_adUrl);
      }
    }
  }

  @override
  void dispose() {
    // Return to pool — don't dispose, engine manages lifetime
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ad = widget.ad;
    final thumbnail = ad['thumbnail'] as String?;

    return GestureDetector(
      onTap: () {
        if (ad['id'] != null) ref.read(communityRepoProvider).trackAdClick(ad['id']);
      },
      child: Stack(fit: StackFit.expand, children: [
        // Video or thumbnail/gradient placeholder
        if (_ready && _ctrl != null)
          Center(child: AspectRatio(aspectRatio: _ctrl!.value.aspectRatio, child: VideoPlayer(_ctrl!)))
        else if (thumbnail != null)
          NetImage(url: thumbnail, fit: BoxFit.cover)
        else
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Color(0xFF1A1B2E), Color(0xFF0D0E1A)],
              ),
            ),
          ),

        // Gradient overlays
        Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [
                Colors.black45,
                Colors.transparent,
                Colors.transparent,
                Colors.black87
              ],
              stops: [0, 0.15, 0.6, 1.0],
            ),
          ),
        ),

        // Sponsored badge
        Positioned(
          top: MediaQuery.of(context).padding.top + 60,
          left: 16,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration:
                BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(6)),
            child: const Row(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.campaign_rounded, color: Colors.white, size: 14),
              SizedBox(width: 4),
              Text('Sponsored',
                  style: TextStyle(
                      color: Colors.white,
                      fontSize: 12,
                      fontWeight: FontWeight.w800)),
            ]),
          ),
        ),

        // Bottom info
        Positioned(
          bottom: 90,
          left: 16,
          right: 80,
          child:
              Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            Row(children: [
              if (ad['page']?['avatar'] != null)
                CircleAvatar(
                    radius: 18,
                    backgroundImage:
                        CachedNetworkImageProvider(ad['page']['avatar']))
              else
                Container(
                    width: 36,
                    height: 36,
                    decoration: const BoxDecoration(
                        color: Colors.white24, shape: BoxShape.circle),
                    child: const Icon(Icons.storefront_rounded,
                        color: Colors.white, size: 18)),
              const SizedBox(width: 10),
              Text(ad['page']?['name'] ?? 'Sponsored',
                  style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 15)),
            ]),
            if (ad['title'] != null)
              Padding(
                  padding: const EdgeInsets.only(top: 10),
                  child: Text(ad['title'],
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.w600,
                          height: 1.3))),
          ]),
        ),

        // Right side
        Positioned(
          right: 12,
          bottom: 120,
          child: Column(children: [
            _sideBtn(Icons.favorite_outline, '0', Colors.white),
            const SizedBox(height: 20),
            _sideBtn(Icons.chat_bubble_outline, '0', Colors.white),
            const SizedBox(height: 20),
            _sideBtn(Icons.share_outlined, 'Share', Colors.white),
          ]),
        ),

        // CTA
        if (ad['cta_text'] != null)
          Positioned(
            bottom: 30,
            left: 16,
            right: 16,
            child: GestureDetector(
              onTap: () {
                if (ad['id'] != null)
                  ref.read(communityRepoProvider).trackAdClick(ad['id']);
              },
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 14),
                decoration: BoxDecoration(
                  color: kOrange,
                  borderRadius: BorderRadius.circular(10),
                  boxShadow: [
                    BoxShadow(
                        color: kOrange.withValues(alpha: 0.4), blurRadius: 16)
                  ],
                ),
                child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Text(ad['cta_text'],
                      style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          fontSize: 16)),
                  const SizedBox(width: 8),
                  const Icon(Icons.arrow_upward_rounded,
                      color: Colors.white, size: 18),
                ]),
              ),
            ),
          ),

        Positioned(
          bottom: 10,
          left: 0,
          right: 0,
          child: Center(
              child: Text('Swipe up for more',
                  style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.4), fontSize: 11))),
        ),
      ]),
    );
  }

  Widget _sideBtn(IconData icon, String label, Color color) => Column(children: [
        Icon(icon, color: color, size: 28),
        const SizedBox(height: 2),
        Text(label,
            style: TextStyle(
                color: color, fontSize: 11, fontWeight: FontWeight.w600)),
      ]);
}

// ── eRent reel card ────────────────────────────────────────────────────────────
// Now uses VideoEngine pool — no more raw VideoPlayerController creation.

class _RentReelCard extends ConsumerStatefulWidget {
  final Map<String, dynamic> reel;
  final bool isActive;
  const _RentReelCard(
      {super.key, required this.reel, required this.isActive});

  @override
  ConsumerState<_RentReelCard> createState() => _RentReelCardState();
}

class _RentReelCardState extends ConsumerState<_RentReelCard> {
  VideoPlayerController? _videoCtrl;
  bool _videoReady = false;
  bool _muted = false;
  bool _paused = false;
  bool _showHeart = false;
  String _videoUrl = '';
  final _engine = VideoEngine.instance;

  @override
  void initState() {
    super.initState();
    _videoUrl = widget.reel['video_url'] as String? ?? '';
    if (_videoUrl.isNotEmpty) _initVideo();
  }

  void _initVideo() async {
    // Fast path — already in pool from predictive preload
    final cached = _engine.getController(_videoUrl);
    if (cached != null && cached.value.isInitialized) {
      if (mounted) {
        setState(() { _videoCtrl = cached; _videoReady = true; });
        if (widget.isActive && !_paused) _engine.activate(_videoUrl);
      }
      return;
    }

    final ctrl = await _engine.preload(_videoUrl);
    if (ctrl != null && mounted) {
      ctrl.setLooping(true);
      setState(() { _videoCtrl = ctrl; _videoReady = true; });
      if (widget.isActive && !_paused) _engine.activate(_videoUrl);
    }
  }

  @override
  void didUpdateWidget(_RentReelCard old) {
    super.didUpdateWidget(old);
    if (widget.isActive != old.isActive && _videoUrl.isNotEmpty) {
      if (widget.isActive) {
        if (!_paused) _engine.activate(_videoUrl);
      } else {
        _engine.pause(_videoUrl);
      }
    }
  }

  @override
  void dispose() {
    // Engine manages controller lifetime — no dispose here
    super.dispose();
  }

  void _togglePause() {
    if (!_videoReady) return;
    setState(() => _paused = !_paused);
    _paused ? _engine.pause(_videoUrl) : _engine.activate(_videoUrl);
  }

  void _openProperty(BuildContext context) {
    final propertyId = widget.reel['property_id'];
    if (propertyId == null) return;
    Navigator.of(context, rootNavigator: true).push(
      MaterialPageRoute(
          builder: (_) => PropertyDetailScreen(
              propertyId: int.parse(propertyId.toString()))),
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
      onDoubleTap: () {
        setState(() => _showHeart = true);
        Future.delayed(const Duration(milliseconds: 800), () {
          if (mounted) setState(() => _showHeart = false);
        });
      },
      child: Stack(fit: StackFit.expand, children: [
        // Thumbnail always present — instant first frame
        // Video or thumbnail placeholder
        if (_videoReady && _videoCtrl != null)
          Center(child: AspectRatio(aspectRatio: _videoCtrl!.value.aspectRatio, child: VideoPlayer(_videoCtrl!)))
        else if (thumbnail != null)
          NetImage(url: thumbnail, fit: BoxFit.cover)
        else
          Container(color: const Color(0xFF1A1B2E)),

        // Gradient
        Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [
                Colors.black45,
                Colors.transparent,
                Colors.transparent,
                Colors.black87
              ],
              stops: [0, 0.2, 0.5, 1.0],
            ),
          ),
        ),

        // Play icon overlay on thumbnail while loading
        if (!_videoReady)
          Center(child: Container(
            padding: const EdgeInsets.all(16),
            decoration: const BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
            child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 48),
          )),

        // Buffering spinner
        if (!_videoReady || (_videoCtrl != null && _videoCtrl!.value.isBuffering))
          Positioned(bottom: 120, right: 14,
            child: SizedBox(width: 24, height: 24,
              child: CircularProgressIndicator(color: Colors.white70, strokeWidth: 2.5))),

        // Pause overlay
        if (_paused && _videoReady)
          Center(
            child: Container(
              padding: const EdgeInsets.all(16),
              decoration:
                  BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
              child: const Icon(Icons.play_arrow_rounded,
                  color: Colors.white, size: 48),
            ),
          ),

        if (_showHeart)
          Center(
              child: Icon(Icons.favorite,
                  size: 100, color: Colors.red.withOpacity(0.85))),

        // eRent badge
        Positioned(
          top: MediaQuery.of(context).padding.top + 64,
          left: 16,
          child: Container(
            padding:
                const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration:
                BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(20)),
            child: const Row(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.home_rounded, color: Colors.white, size: 14),
              SizedBox(width: 5),
              Text('eRent',
                  style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 12)),
            ]),
          ),
        ),

        // Mute
        Positioned(
          top: MediaQuery.of(context).padding.top + 64,
          right: 12,
          child: GestureDetector(
            onTap: () {
              setState(() => _muted = !_muted);
              if (_videoCtrl != null) {
                _engine.activate(_videoUrl); // ensure active
                _videoCtrl!.setVolume(_muted ? 0 : 1);
              }
            },
            child: Container(
              padding: const EdgeInsets.all(8),
              decoration: const BoxDecoration(
                  color: Colors.black45, shape: BoxShape.circle),
              child: Icon(_muted ? Icons.volume_off : Icons.volume_up,
                  color: Colors.white, size: 20),
            ),
          ),
        ),

        // Bottom property info
        Positioned(
          bottom: 80,
          left: 16,
          right: 80,
          child: GestureDetector(
            onTap: () => _openProperty(context),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Row(children: [
                  Container(
                    width: 40,
                    height: 40,
                    decoration: BoxDecoration(
                        color: kOrange,
                        borderRadius: BorderRadius.circular(10)),
                    child: const Icon(Icons.home_rounded,
                        color: Colors.white, size: 22),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(title,
                              style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 14),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis),
                          if (district.isNotEmpty)
                            Text(district,
                                style: const TextStyle(
                                    color: Colors.white70, fontSize: 12)),
                        ]),
                  ),
                  const Icon(Icons.chevron_right_rounded,
                      color: Colors.white70, size: 20),
                ]),
                if (rent != null) ...[
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(
                        horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                        color: Colors.black54,
                        borderRadius: BorderRadius.circular(12)),
                    child: Text('\$$rent/mo',
                        style: const TextStyle(
                            color: kOrange,
                            fontWeight: FontWeight.w800,
                            fontSize: 14)),
                  ),
                ],
              ],
            ),
          ),
        ),

        // Right actions
        Positioned(
          right: 10,
          bottom: 100,
          child: Column(children: [
            _sideAction(
              icon: Icons.home_work_outlined,
              label: 'View',
              color: Colors.white,
              onTap: () => _openProperty(context),
            ),
            const SizedBox(height: 20),
            _sideAction(
              icon: Icons.send_outlined,
              label: 'Share',
              color: Colors.white,
              onTap: () {},
            ),
          ]),
        ),
      ]),
    );
  }

  Widget _sideAction(
          {required IconData icon,
          required String label,
          required Color color,
          required VoidCallback onTap}) =>
      GestureDetector(
        onTap: onTap,
        child: Column(children: [
          Icon(icon, color: color, size: 28),
          const SizedBox(height: 2),
          Text(label,
              style: TextStyle(
                  color: color, fontSize: 11, fontWeight: FontWeight.w600)),
        ]),
      );
}
