import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';
import 'package:media_kit/media_kit.dart' show Player;
import 'package:media_kit_video/media_kit_video.dart';
import 'package:url_launcher/url_launcher.dart';
import '../services/video_pool.dart';
import '../services/ad_video_manager.dart';
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
      // MP4 via direct nginx URL: 1 RTT to start vs HLS 3 RTTs.
      // mp4DirectUrl derives /hls/.../optimized.mp4 from the HLS URL —
      // served by nginx sendfile, Range-aware, Cloudflare-cacheable.
      return m?.mp4DirectUrl ?? m?.url ?? '';
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

  final _pool = VideoPool.reels;

  bool _lifecyclePaused = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _loadReelAds();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.paused) {
      _lifecyclePaused = true;
      _pool.pauseAll();
    } else if (state == AppLifecycleState.inactive) {
      if (!_lifecyclePaused) {
        _lifecyclePaused = true;
        _pool.pauseAll();
      }
    } else if (state == AppLifecycleState.resumed) {
      _lifecyclePaused = false;
      final items = _cachedItems;
      if (items == null || _currentIndex >= items.length) return;
      final url = items[_currentIndex].videoUrl;
      if (url.isNotEmpty) _pool.reactivate(url);
    }
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
    _pool.disposeAll();
    _pageCtrl.dispose();
    super.dispose();
  }

  void _loadReelAds() async {
    try {
      final repo = ref.read(communityRepoProvider);
      for (var i = 0; i < 3; i++) {
        final ad = await repo.getPrerollAd();
        if (ad != null && ad['media_url'] != null) {
          _reelAds.add(ad);
          // Eagerly preload the video so _ReelAdCard gets a ready controller
          final url = ad['media_url'] as String;
          if (url.isNotEmpty) AdVideoManager.instance.preload([url]);
        }
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

  /// Flat list of video URLs in feed order — ads get '' so pool skips them.
  /// Ads use their own plain VideoPlayerController; excluding them from the
  /// window prevents wasted pool slots and wrong eviction distances.
  List<String> _extractVideoUrls(List<_ReelItem> items) =>
      items.map((e) => e.isAd ? '' : e.videoUrl).toList();

  void _onPageChanged(int i, List<_ReelItem> combined) {
    setState(() => _currentIndex = i);

    // Pagination: load more when 3 from end
    if (i >= combined.length - 3) {
      ref.read(communityReelsProvider.notifier).load();
    }

    final urls = _extractVideoUrls(combined);
    _pool.setWindow(urls, i);
    final url = i < urls.length ? urls[i] : '';
    if (url.isNotEmpty) _pool.play(url);
  }

  @override
  Widget build(BuildContext context) {
    final reelsAsync = ref.watch(communityReelsProvider);
    final rentAsync = ref.watch(erentReelsProvider);
    final tabActive = ref.watch(communityNavIndexProvider) == 1;

    // Pause all reels when navigating away from this tab.
    // Resume is handled per-card so manual pauses are respected.
    ref.listen<int>(communityNavIndexProvider, (prev, next) {
      if (prev == 1 && next != 1) _pool.pauseAll();
    });

    final communityReels = reelsAsync.valueOrNull ?? [];
    final rentReels = rentAsync.valueOrNull ?? [];

    if (_cachedItems == null) {
      _cachedItems = _buildCombinedList(communityReels, rentReels);
      _foldedCommunityCount = communityReels.length;
      _foldedRentCount = rentReels.length;
      // Prime the pool with the initial feed context — only play if tab is active
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_cachedItems != null && mounted) {
          final urls = _extractVideoUrls(_cachedItems!);
          _pool.setWindow(urls, 0);
          if (urls.isNotEmpty && tabActive) _pool.play(urls[0]);
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
      // Start preloading from current position now that URLs are available.
      // Without this the pool never gets the URL list on first data arrival.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted || _cachedItems == null) return;
        final urls = _extractVideoUrls(_cachedItems!);
        _pool.setWindow(urls, _currentIndex);
        final activeUrl = _currentIndex < urls.length ? urls[_currentIndex] : '';
        if (activeUrl.isNotEmpty && tabActive) _pool.play(activeUrl);
      });
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
        title: Text('Reels',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
        actions: [
          IconButton(
            icon: Icon(Icons.videocam_outlined, color: Colors.white),
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
                SizedBox(height: 16),
                Text(
                  reelsAsync.hasError ? 'Couldn\'t load reels' : 'No reels yet',
                  style: TextStyle(color: Colors.white54, fontSize: 16),
                ),
                if (reelsAsync.hasError) ...[
                  SizedBox(height: 6),
                  Text('${reelsAsync.error}',
                      style: TextStyle(color: Colors.white30, fontSize: 12),
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis),
                  SizedBox(height: 16),
                  TextButton(
                    onPressed: () =>
                        ref.read(communityReelsProvider.notifier).refresh(),
                    child: Text('Retry',
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
  VideoController? _videoCtrl;
  StreamSubscription<bool>? _completedSub;
  bool _videoReady = false;
  bool _muted = false;
  bool _paused = false;
  bool _liked = false;
  bool _saved = false;
  bool _showHeart = false;
  int _likesCount = 0;
  final _pool = VideoPool.reels;
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
    // Retry play after first frame — covers race where pool wasn't ready at initState
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && widget.isActive && !_paused && _videoReady && _videoUrl.isNotEmpty) {
        _pool.play(_videoUrl);
      }
    });
  }

  void _subscribeRealtime() {
    void on(String event, void Function(dynamic) handler) {
      _realtimeListeners[event] = handler;
      RealtimeClient.instance.listen(_postChannel, event, handler);
    }

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

    // Prefer direct nginx MP4 (1 RTT, faststart). Fall back to HLS if MP4 fails.
    final mp4Url = media.mp4DirectUrl;
    final hlsFallback = media.hlsUrl;

    // Default to mp4Url — overridden below if HLS is what the pool has loaded.
    _videoUrl = mp4Url;

    // Fast path: pool already has this controller (preloaded by parent)
    var poolCtrl = _pool.controller(mp4Url);
    if (poolCtrl == null && hlsFallback != null && hlsFallback != mp4Url) {
      poolCtrl = _pool.controller(hlsFallback);
      if (poolCtrl != null) _videoUrl = hlsFallback;
    }

    if (poolCtrl != null) {
      _videoCtrl = poolCtrl;
      _attachCompletedListener(poolCtrl);
      if (mounted) {
        setState(() => _videoReady = true);
        if (widget.isActive && !_paused) _pool.play(_videoUrl);
      }
      return;
    }

    // Slow path: load from network. Try MP4 → HLS → PHP proxy.
    var ctrl = await _pool.preload(mp4Url);
    if (ctrl == null && hlsFallback != null && hlsFallback != mp4Url) {
      debugPrint('[Reel] MP4 failed, trying HLS: $hlsFallback');
      _videoUrl = hlsFallback;
      ctrl = await _pool.preload(hlsFallback);
    }
    // Last resort: raw stored URL (PHP media proxy, works even if nginx 403)
    final proxyUrl = media.url;
    if (ctrl == null && proxyUrl.isNotEmpty && proxyUrl != mp4Url && proxyUrl != (hlsFallback ?? '')) {
      debugPrint('[Reel] nginx failed, trying proxy: $proxyUrl');
      _videoUrl = proxyUrl;
      ctrl = await _pool.preload(proxyUrl);
    }

    if (ctrl != null && mounted) {
      _videoCtrl = ctrl;
      _attachCompletedListener(ctrl);
      setState(() => _videoReady = true);
      if (widget.isActive && !_paused) _pool.play(_videoUrl);
    }
  }

  void _attachCompletedListener(VideoController ctrl) {
    _completedSub?.cancel();
    _completedSub = ctrl.player.stream.completed.listen((completed) {
      if (completed && mounted) widget.onVideoEnd?.call();
    });
  }

  @override
  void didUpdateWidget(_CommunityReelCard old) {
    super.didUpdateWidget(old);
    if (!mounted) return;
    if (widget.isActive != old.isActive) {
      if (widget.isActive) {
        if (!_paused && _videoUrl.isNotEmpty) {
          _pool.reactivate(_videoUrl).then((_) {
            if (mounted && !_paused && _videoCtrl != null) {
              _videoCtrl!.player.setVolume(_muted ? 0 : 100);
            }
          });
        }
        // Immediately restore volume if controller already ready
        if (_videoCtrl != null) _videoCtrl!.player.setVolume(_muted ? 0 : 100);
        _trackView();
      } else {
        if (_videoUrl.isNotEmpty) _pool.pause(_videoUrl);
      }
    }
  }

  @override
  void dispose() {
    _completedSub?.cancel();
    for (final entry in _realtimeListeners.entries) {
      RealtimeClient.instance.removeListener(_postChannel, entry.key, entry.value);
    }
    // Pool manages controller lifetime — do not dispose here
    super.dispose();
  }

  void _togglePause() {
    if (!_videoReady || _videoCtrl == null) return;
    setState(() => _paused = !_paused);
    if (_paused) {
      _pool.pause(_videoUrl);
    } else {
      _pool.play(_videoUrl);
    }
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
                Icon(Icons.visibility_off_rounded, color: Colors.white70),
            title:
                Text('Not interested', style: TextStyle(color: Colors.white)),
            subtitle: Text('See fewer reels like this',
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
            leading: Icon(Icons.flag_rounded, color: Colors.white70),
            title: Text('Report', style: TextStyle(color: Colors.white)),
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

    // Resume playback when returning to the reels tab, but respect manual pause
    ref.listen<int>(communityNavIndexProvider, (prev, next) {
      if (prev != 1 && next == 1 && widget.isActive && !_paused && _videoUrl.isNotEmpty) {
        _pool.play(_videoUrl);
      }
    });

    final reelContent = GestureDetector(
      onTap: _togglePause,
      onDoubleTap: _onDoubleTap,
      child: Stack(fit: StackFit.expand, children: [
        // Thumbnail always present as background for instant color context
        if (thumbnail != null)
          NetImage(url: thumbnail, fit: BoxFit.cover)
        else if (media != null && media.type == 'image')
          NetImage(url: media.url, fit: BoxFit.cover)
        else
          Container(color: const Color(0xFF1A1A2E)),

        // Video replaces thumbnail once ready
        if (_videoReady && _videoCtrl != null)
          Positioned.fill(child: Video(
            controller: _videoCtrl!,
            fit: BoxFit.cover,
            controls: NoVideoControls,
          )),

        // Gradient overlay (always present for text legibility)
        Container(
          decoration: BoxDecoration(
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

        // Tiny corner spinner only while the video is actively re-buffering
        // (not during initial load — thumbnail is clear enough)
        if (_videoReady && _videoCtrl != null && _videoCtrl!.player.state.buffering)
          Positioned(bottom: 120, right: 14,
            child: SizedBox(width: 20, height: 20,
              child: CircularProgressIndicator(color: Colors.white54, strokeWidth: 2))),

        // Pause overlay
        if (_paused && _videoReady)
          Center(
            child: Container(
              padding: EdgeInsets.all(16),
              decoration:
                  BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
              child: Icon(Icons.play_arrow_rounded,
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
              _videoCtrl?.player.setVolume(_muted ? 0 : 100);
            },
            child: Container(
              padding: EdgeInsets.all(8),
              decoration: BoxDecoration(
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
                padding: EdgeInsets.all(8),
                decoration: BoxDecoration(
                    color: Colors.black45, shape: BoxShape.circle),
                child: Icon(Icons.more_vert_rounded,
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
                            style: TextStyle(
                                fontWeight: FontWeight.bold, fontSize: 14))
                        : null,
                  ),
                  SizedBox(width: 8),
                  Text(reel.user.name,
                      style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                          fontSize: 14)),
                  if (reel.user.isVerified) ...[
                    SizedBox(width: 4),
                    Icon(Icons.verified, size: 14, color: kOrange),
                  ],
                  SizedBox(width: 10),
                  GestureDetector(
                    onTap: () =>
                        ref.read(communityRepoProvider).toggleFollow(reel.user.id),
                    child: Container(
                      padding: EdgeInsets.symmetric(
                          horizontal: 12, vertical: 4),
                      decoration: BoxDecoration(
                          border: Border.all(color: Colors.white),
                          borderRadius: BorderRadius.circular(20)),
                      child: Text('Follow',
                          style: TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w600)),
                    ),
                  ),
                ]),
              ),
              if (reel.content != null && reel.content!.isNotEmpty) ...[
                SizedBox(height: 8),
                Text(reel.content!,
                    style:
                        TextStyle(color: Colors.white, fontSize: 13),
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
            SizedBox(height: 20),
            _sideAction(
              icon: Icons.chat_bubble_outline,
              label: '${reel.commentsCount}',
              color: Colors.white,
              onTap: () => showCommentsSheet(context, reel.id,
                  initialCount: reel.commentsCount),
            ),
            SizedBox(height: 20),
            _sideAction(
              icon: Icons.send_outlined,
              label: 'Share',
              color: Colors.white,
              onTap: () =>
                  ref.read(communityRepoProvider).sharePost(reel.id),
            ),
            SizedBox(height: 20),
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
            SizedBox(height: 20),
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
        ? VideoAdOverlay(
            mainController: _videoCtrl!.player,
            onAdStart: () => _pool.pause(_videoUrl),
            onAdEnd:   () => _pool.reactivate(_videoUrl),
            child: reelContent)
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
          SizedBox(height: 2),
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
  bool _ready  = false;
  bool _paused = false;
  bool _muted  = false;

  @override
  void initState() {
    super.initState();
    final url = widget.ad['media_url'] as String? ?? '';
    if (url.isNotEmpty) _initVideo(url);
  }

  void _togglePause() {
    if (!_ready || _ctrl == null) return;
    setState(() => _paused = !_paused);
    if (_paused) {
      _ctrl!.pause();
    } else {
      _ctrl!.setVolume(_muted ? 0 : 1);
      _ctrl!.play();
    }
  }

  void _onCta() {
    final ad = widget.ad;
    if (ad['id'] != null) ref.read(communityRepoProvider).trackAdClick(ad['id']);
    final raw = ad['cta_url'] as String?;
    if (raw != null && raw.isNotEmpty) {
      launchUrl(Uri.parse(raw.startsWith('http') ? raw : 'https://$raw'),
          mode: LaunchMode.externalApplication);
    }
  }

  void _initVideo(String url) async {
    final ctrl = await AdVideoManager.instance.awaitController(url);
    if (ctrl == null || !mounted) return;
    ctrl.setLooping(true);
    ctrl.setVolume(widget.isActive ? 1 : 0);
    setState(() { _ctrl = ctrl; _ready = true; });
    if (widget.isActive && !_paused) ctrl.play();
  }

  @override
  void didUpdateWidget(_ReelAdCard old) {
    super.didUpdateWidget(old);
    if (widget.isActive == old.isActive || _ctrl == null) return;
    if (widget.isActive) {
      // Always restart ad from beginning when it becomes active
      _ctrl!.seekTo(Duration.zero);
      _paused = false;
      _ctrl!.setVolume(_muted ? 0 : 1);
      _ctrl!.play();
    } else {
      _ctrl!.pause();
    }
  }

  @override
  void dispose() {
    _ctrl?.pause();
    // AdVideoManager owns controller lifetime — do not dispose here
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ad = widget.ad;
    final thumbnail = ad['thumbnail'] as String?;

    // Resume when returning to reels tab — respect manual pause and mute state
    ref.listen<int>(communityNavIndexProvider, (prev, next) {
      if (prev != 1 && next == 1 && widget.isActive && !_paused && _ctrl != null) {
        _ctrl!.setVolume(_muted ? 0 : 1);
        _ctrl!.play();
      }
    });

    return GestureDetector(
      onTap: _togglePause,
      child: Stack(fit: StackFit.expand, children: [
        // Thumbnail always as background — instant display
        if (thumbnail != null)
          NetImage(url: thumbnail, fit: BoxFit.cover)
        else
          Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Color(0xFF1A1B2E), Color(0xFF0D0E1A)],
              ),
            ),
          ),
        // Video overlays thumbnail once ready
        if (_ready && _ctrl != null)
          Center(child: AspectRatio(aspectRatio: _ctrl!.value.aspectRatio, child: VideoPlayer(_ctrl!))),
        // Slim loading bar at bottom while video initializes
        if (!_ready)
          Positioned(bottom: 0, left: 0, right: 0,
            child: LinearProgressIndicator(color: kOrange, backgroundColor: Colors.transparent, minHeight: 2)),

        // Gradient overlays
        Container(
          decoration: BoxDecoration(
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

        // Pause overlay
        if (_paused && _ready)
          Center(
            child: Container(
              padding: EdgeInsets.all(16),
              decoration: BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
              child: Icon(Icons.play_arrow_rounded, color: Colors.white, size: 48),
            ),
          ),

        // Sponsored badge
        Positioned(
          top: MediaQuery.of(context).padding.top + 60,
          left: 16,
          child: Container(
            padding: EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration:
                BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(6)),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
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

        // Mute button
        Positioned(
          top: MediaQuery.of(context).padding.top + 60,
          right: 16,
          child: GestureDetector(
            onTap: () {
              setState(() => _muted = !_muted);
              _ctrl?.setVolume(_muted ? 0 : 1);
            },
            child: Container(
              width: 36, height: 36,
              decoration: BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
              child: Icon(
                _muted ? Icons.volume_off_rounded : Icons.volume_up_rounded,
                color: Colors.white, size: 18)),
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
                    decoration: BoxDecoration(
                        color: Colors.white24, shape: BoxShape.circle),
                    child: Icon(Icons.storefront_rounded,
                        color: Colors.white, size: 18)),
              SizedBox(width: 10),
              Text(ad['page']?['name'] ?? 'Sponsored',
                  style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 15)),
            ]),
            if (ad['title'] != null)
              Padding(
                  padding: EdgeInsets.only(top: 10),
                  child: Text(ad['title'],
                      style: TextStyle(
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
            SizedBox(height: 20),
            _sideBtn(Icons.chat_bubble_outline, '0', Colors.white),
            SizedBox(height: 20),
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
              onTap: _onCta,
              child: Container(
                padding: EdgeInsets.symmetric(vertical: 14),
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
                      style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          fontSize: 16)),
                  SizedBox(width: 8),
                  Icon(Icons.arrow_upward_rounded,
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
        SizedBox(height: 2),
        Text(label,
            style: TextStyle(
                color: color, fontSize: 11, fontWeight: FontWeight.w600)),
      ]);
}

// ── eRent reel card ────────────────────────────────────────────────────────────

class _RentReelCard extends ConsumerStatefulWidget {
  final Map<String, dynamic> reel;
  final bool isActive;
  const _RentReelCard(
      {super.key, required this.reel, required this.isActive});

  @override
  ConsumerState<_RentReelCard> createState() => _RentReelCardState();
}

class _RentReelCardState extends ConsumerState<_RentReelCard> {
  VideoController? _videoCtrl;
  bool _videoReady = false;
  bool _muted = false;
  bool _paused = false;
  bool _showHeart = false;
  String _videoUrl = '';
  final _pool = VideoPool.reels;

  @override
  void initState() {
    super.initState();
    _videoUrl = widget.reel['video_url'] as String? ?? '';
    if (_videoUrl.isNotEmpty) _initVideo();
  }

  void _initVideo() async {
    // Fast path — pool already preloaded this controller
    final cached = _pool.controller(_videoUrl);
    if (cached != null) {
      if (mounted) {
        setState(() { _videoCtrl = cached; _videoReady = true; });
        if (widget.isActive && !_paused) _pool.play(_videoUrl);
      }
      return;
    }

    final ctrl = await _pool.preload(_videoUrl);
    if (ctrl != null && mounted) {
      setState(() { _videoCtrl = ctrl; _videoReady = true; });
      if (widget.isActive && !_paused) _pool.play(_videoUrl);
    }
  }

  @override
  void didUpdateWidget(_RentReelCard old) {
    super.didUpdateWidget(old);
    if (widget.isActive != old.isActive && _videoUrl.isNotEmpty) {
      if (widget.isActive) {
        if (!_paused) {
          _pool.reactivate(_videoUrl).then((_) {
            if (mounted && !_paused && _videoCtrl != null) {
              _videoCtrl!.player.setVolume(_muted ? 0 : 100);
            }
          });
        }
        if (_videoCtrl != null) _videoCtrl!.player.setVolume(_muted ? 0 : 100);
      } else {
        _pool.pause(_videoUrl);
      }
    }
  }

  @override
  void dispose() {
    // Pool manages controller lifetime — do not dispose here
    super.dispose();
  }

  void _togglePause() {
    if (!_videoReady || _videoCtrl == null) return;
    setState(() => _paused = !_paused);
    if (_paused) {
      _pool.pause(_videoUrl);
    } else {
      _pool.play(_videoUrl);
    }
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

    // Resume playback when returning to the reels tab, but respect manual pause
    ref.listen<int>(communityNavIndexProvider, (prev, next) {
      if (prev != 1 && next == 1 && widget.isActive && !_paused && _videoUrl.isNotEmpty) {
        _pool.play(_videoUrl);
      }
    });

    return GestureDetector(
      onTap: _togglePause,
      onDoubleTap: () {
        setState(() => _showHeart = true);
        Future.delayed(const Duration(milliseconds: 800), () {
          if (mounted) setState(() => _showHeart = false);
        });
      },
      child: Stack(fit: StackFit.expand, children: [
        // Thumbnail always present as instant background
        if (thumbnail != null)
          NetImage(url: thumbnail, fit: BoxFit.cover)
        else
          Container(color: const Color(0xFF1A1B2E)),

        // Video replaces thumbnail once ready
        if (_videoReady && _videoCtrl != null)
          Positioned.fill(child: Video(
            controller: _videoCtrl!,
            fit: BoxFit.cover,
            controls: NoVideoControls,
          )),

        // Gradient (always present for text legibility)
        Container(
          decoration: BoxDecoration(
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

        // Corner spinner only during actual re-buffering, not initial load
        if (_videoReady && _videoCtrl != null && _videoCtrl!.player.state.buffering)
          Positioned(bottom: 120, right: 14,
            child: SizedBox(width: 20, height: 20,
              child: CircularProgressIndicator(color: Colors.white54, strokeWidth: 2))),

        // Pause overlay
        if (_paused && _videoReady)
          Center(
            child: Container(
              padding: EdgeInsets.all(16),
              decoration:
                  BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
              child: Icon(Icons.play_arrow_rounded,
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
                EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration:
                BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(20)),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
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
              _videoCtrl?.player.setVolume(_muted ? 0 : 100);
            },
            child: Container(
              padding: EdgeInsets.all(8),
              decoration: BoxDecoration(
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
                    child: Icon(Icons.home_rounded,
                        color: Colors.white, size: 22),
                  ),
                  SizedBox(width: 10),
                  Expanded(
                    child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(title,
                              style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 14),
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis),
                          if (district.isNotEmpty)
                            Text(district,
                                style: TextStyle(
                                    color: Colors.white70, fontSize: 12)),
                        ]),
                  ),
                  Icon(Icons.chevron_right_rounded,
                      color: Colors.white70, size: 20),
                ]),
                if (rent != null) ...[
                  SizedBox(height: 6),
                  Container(
                    padding: EdgeInsets.symmetric(
                        horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                        color: Colors.black54,
                        borderRadius: BorderRadius.circular(12)),
                    child: Text('\$$rent/mo',
                        style: TextStyle(
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
            SizedBox(height: 20),
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
          SizedBox(height: 2),
          Text(label,
              style: TextStyle(
                  color: color, fontSize: 11, fontWeight: FontWeight.w600)),
        ]),
      );
}
