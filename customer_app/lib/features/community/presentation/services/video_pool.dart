import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:video_player/video_player.dart';

/// Streaming video controller pool — feed and reels.
///
/// Uses VideoPlayerController.networkUrl() which leverages the platform's
/// native player (ExoPlayer on Android, AVPlayer on iOS). This sends HTTP
/// Range requests and starts playback after buffering ~2-5 s — regardless of
/// whether the video was previously seen. CachedVideoPlayerPlus was removed
/// because it downloads the ENTIRE file before initialising the controller,
/// causing 30-second+ waits for new (non-cached) videos.
///
/// In-session caching: the _controllers map holds initialised controllers for
/// the active window (8 slots). Scrolling back to a recently-seen video reuses
/// the in-memory controller — no re-download within the same session.
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

  // In-memory controller map (URL → VideoPlayerController)
  final _controllers = <String, VideoPlayerController>{};
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

    // Evict far-away in-memory controllers.
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
  /// Uses VideoPlayerController.networkUrl() which streams via HTTP Range
  /// requests — playback begins after 2-5 s of buffering, not after a full
  /// file download. In-memory controller is reused within the same session.
  Future<VideoPlayerController?> preload(String url) async {
    if (url.isEmpty) return null;
    if (_ready[url] == true) return _controllers[url];
    if (_loading.containsKey(url)) return _loading[url]!.future;

    _evictIfNeeded(keep: url);

    final c = Completer<VideoPlayerController?>();
    _loading[url] = c;

    VideoPlayerController? result;
    try {
      final ctrl = VideoPlayerController.networkUrl(
        Uri.parse(url),
        httpHeaders: const {'Connection': 'keep-alive', 'Accept-Ranges': 'bytes'},
      );
      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(0);
      _controllers[url] = ctrl;
      _ready[url]       = true;
      result = ctrl;
      debugPrint('[VideoPool] ready: $url');
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
    for (final ctrl in _controllers.values) {
      ctrl.pause();
      ctrl.dispose();
    }
    _controllers.clear();
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
    _loading.remove(url)?.future.then((c) { c?.pause(); c?.dispose(); });
    final ctrl = _controllers.remove(url);
    _ready.remove(url);
    if (_pendingPlay == url) _pendingPlay = null;
    ctrl?.pause();
    ctrl?.dispose();
    debugPrint('[VideoPool] evicted: $url');
  }
}
