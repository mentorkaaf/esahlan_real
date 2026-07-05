import 'dart:async';
import 'package:cached_video_player_plus/cached_video_player_plus.dart';
import '../../../../core/constants/app_constants.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';
import 'package:video_player/video_player.dart';

/// Disk-cached video controller pool — for reels (paged) and feed (scrollable).
///
/// Caching design:
///   • First play: downloads MP4, writes to disk via flutter_cache_manager.
///   • Subsequent plays (same session OR after app restart): served from disk.
///   • Cache limit: 30 videos on disk (~600 MB typical). LRU eviction by manager.
///   • Stale period: 7 days (videos are re-fetched after a week).
///
/// Instant-playback design:
///   • Pool holds 12 in-memory controllers (1 screen + 3-ahead buffer).
///   • setWindow(urls, i) → preloads i-1…i+3 (1 behind, 3 ahead) — lightweight.
///   • setActiveUrl(url) → used by feed scroll to slide the window.
///   • play(url) before ready → _pendingPlay fires the moment init completes.
///   • Eviction: drops in-memory controllers outside ±5 of current index (disk
///     cache is kept — re-init from disk is instant, no network round-trip).
///   • Fail-fast: single attempt per URL; callers supply fallback URLs.
class VideoPool {
  VideoPool._();

  static final reels = VideoPool._();
  static final feed  = VideoPool._();

  // 8 in-memory slots = current + 3 ahead + 1 behind + extras
  static const _maxSlots      = 8;
  // Evict in-memory controller if further than this from current index
  static const _evictDistance = 4;
  // Preload: 1 behind, 3 ahead of current index
  static const _preloadBehind = 1;
  static const _preloadAhead  = 3;

  /// Shared disk cache: 15 videos max, stale after 3 days.
  static final _diskCache = CacheManager(
    Config(
      'esahlan_video_cache',
      maxNrOfCacheObjects: 15,
      stalePeriod: AppConstants.videoCacheStalePeriod,
    ),
  );

  // In-memory controller map (URL → inner VideoPlayerController for playback)
  final _controllers = <String, VideoPlayerController>{};
  // CachedVideoPlayerPlus wrappers — needed for correct disposal
  final _wrappers    = <String, CachedVideoPlayerPlus>{};
  final _ready       = <String, bool>{};
  final _loading     = <String, Completer<VideoPlayerController?>>{};

  List<String> _window      = [];
  int          _windowIndex = -1;
  String?      _activeUrl;
  String?      _pendingPlay;

  // Feed-mode: ordered list of all video URLs in the feed (scroll order).
  List<String> _feedUrls = [];

  // ─── Public state ─────────────────────────────────────────────────────────

  VideoPlayerController? controller(String url) =>
      (_ready[url] == true) ? _controllers[url] : null;

  bool isReady(String url)   => _ready[url] == true;
  bool isLoading(String url) => _loading.containsKey(url);
  bool get hasRoom           => _controllers.length < _maxSlots;
  /// -1 = not yet initialised (use to guard first setWindow call in feed).
  int  get windowIndex       => _windowIndex;

  // ─── Feed URL registry ────────────────────────────────────────────────────

  void setFeedUrls(List<String> urls) {
    _feedUrls = urls;
  }

  void setActiveUrl(String url) {
    if (url.isEmpty) return;
    final idx = _feedUrls.indexOf(url);
    if (idx >= 0) setWindow(_feedUrls, idx);
  }

  // ─── Window ───────────────────────────────────────────────────────────────

  void setWindow(List<String> urls, int index) {
    _window      = urls;
    _windowIndex = index;

    // Evict far-away in-memory controllers (disk cache kept intact).
    // Never evict _activeUrl — that is the currently-playing video;
    // disposing it would kill the video mid-playback.
    final toEvict = _controllers.keys.where((url) {
      if (url == _activeUrl) return false;
      final i = urls.indexOf(url);
      return i >= 0 && (i - index).abs() > _evictDistance;
    }).toList();
    for (final u in toEvict) _evict(u);

    // Preload the sliding window (asymmetric: more ahead than behind)
    final from = (index - _preloadBehind).clamp(0, urls.length - 1);
    final to   = (index + _preloadAhead).clamp(0, urls.length - 1);
    for (var i = from; i <= to; i++) {
      final u = urls[i];
      if (u.isNotEmpty && !isReady(u) && !isLoading(u)) preload(u);
    }
  }

  // ─── Preloading ───────────────────────────────────────────────────────────

  /// Ensure [url] is initialised. Returns null on failure so callers can
  /// immediately try a fallback URL.
  ///
  /// On first call: downloads the MP4 and writes it to disk cache.
  /// On subsequent calls (same session or after restart): served from disk
  /// via flutter_cache_manager — no network round-trip.
  Future<VideoPlayerController?> preload(String url) async {
    if (url.isEmpty) return null;
    if (_ready[url] == true) return _controllers[url];
    if (_loading.containsKey(url)) return _loading[url]!.future;

    _evictIfNeeded(keep: url);

    final c = Completer<VideoPlayerController?>();
    _loading[url] = c;

    VideoPlayerController? result;
    try {
      final wrapper = CachedVideoPlayerPlus.networkUrl(
        Uri.parse(url),
        httpHeaders: const {'Connection': 'keep-alive'},
        cacheManager: _diskCache,
      );
      await wrapper.initialize();
      final ctrl = wrapper.controller;
      ctrl.setLooping(true);
      ctrl.setVolume(0);
      _wrappers[url]    = wrapper;
      _controllers[url] = ctrl;
      _ready[url]       = true;
      result = ctrl;
      debugPrint('[VideoPool] ready (disk-cached): $url');
    } catch (e) {
      debugPrint('[VideoPool] failed: $url — $e');
    }

    _loading.remove(url);
    c.complete(result);

    if (result != null && _pendingPlay == url) {
      _pendingPlay = null;
      result.setVolume(1);
      try { result.play(); } catch (_) {}
    }

    return result;
  }

  // ─── Playback ─────────────────────────────────────────────────────────────

  void play(String url) {
    if (url.isEmpty) return;
    _activeUrl   = url;
    _pendingPlay = url;

    for (final entry in _controllers.entries) {
      if (entry.key == url) {
        if (_ready[url] == true) {
          _pendingPlay = null;
          if (!entry.value.value.isPlaying) {
            entry.value.setVolume(1);
            entry.value.play();
          }
        }
      } else {
        if (entry.value.value.isPlaying) entry.value.pause();
        entry.value.setVolume(0);
      }
    }
  }

  void pause(String url) {
    if (_pendingPlay == url) _pendingPlay = null;
    _controllers[url]?.pause();
    if (_activeUrl == url) _activeUrl = null;
  }

  void pauseAll() {
    _pendingPlay = null;
    for (final ctrl in _controllers.values) {
      if (ctrl.value.isPlaying) ctrl.pause();
    }
    _activeUrl = null;
  }

  Future<void> reactivate(String url) async {
    if (url.isEmpty) return;
    final ctrl = controller(url);
    if (ctrl == null) {
      final loaded = await preload(url);
      if (loaded != null) play(url);
      return;
    }
    _activeUrl   = url;
    _pendingPlay = null;
    ctrl.setVolume(1);
    try { ctrl.play(); } catch (_) {}
  }

  // ─── Lifecycle ────────────────────────────────────────────────────────────

  void disposeAll() {
    _pendingPlay = null;
    for (final c in _loading.values) {
      if (!c.isCompleted) c.complete(null);
    }
    for (final entry in _wrappers.entries) {
      _controllers[entry.key]?.pause();
      entry.value.dispose();
    }
    _controllers.clear();
    _wrappers.clear();
    _ready.clear();
    _loading.clear();
    _window      = [];
    _windowIndex = -1;
    _activeUrl   = null;
    _feedUrls    = [];
  }

  // ─── Private ──────────────────────────────────────────────────────────────

  void _evictIfNeeded({String? keep}) {
    while (_controllers.length >= _maxSlots) {
      final victim = _chooseLRU(protect: keep);
      if (victim != null) _evict(victim);
      else break;
    }
  }

  String? _chooseLRU({String? protect}) {
    String? best;
    int     bestDist = -1;
    for (final url in _controllers.keys) {
      if (url == _activeUrl || url == protect) continue;
      if (_controllers[url]?.value.isPlaying == true) continue;
      final orderList = _window.isNotEmpty ? _window : _feedUrls;
      final pivot     = _windowIndex >= 0 ? _windowIndex
          : (_feedUrls.isNotEmpty ? _feedUrls.indexOf(_activeUrl ?? '') : -1);
      final i    = orderList.isEmpty ? -1 : orderList.indexOf(url);
      final dist = (i < 0 || pivot < 0) ? 999 : (i - pivot).abs();
      if (dist > bestDist) { bestDist = dist; best = url; }
    }
    return best;
  }

  void _evict(String url) {
    _loading.remove(url)?.future.then((c) { c?.pause(); });
    final ctrl = _controllers.remove(url);
    _ready.remove(url);
    if (_pendingPlay == url) _pendingPlay = null;
    ctrl?.pause();
    // Dispose via wrapper (keeps disk cache intact, just frees memory)
    _wrappers.remove(url)?.dispose();
    debugPrint('[VideoPool] evicted from memory (disk cache kept): $url');
  }
}
