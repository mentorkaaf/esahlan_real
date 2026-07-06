import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:video_player/video_player.dart';

/// Streaming video controller pool — feed and reels.
///
/// Each feed item calls [setFraction] whenever the VisibilityDetector fires.
/// The pool internally picks the URL with the highest fraction (above a
/// minimum threshold) as the "dominant" URL and plays it, pausing all others.
/// This eliminates the _pendingPlay race where multiple items simultaneously
/// call play() and the last one always wins.
///
/// Reels use [play] directly (PageView — only one item active at a time).
class VideoPool {
  VideoPool._();

  static final reels = VideoPool._();
  static final feed  = VideoPool._();

  static const _maxSlots      = 10;
  static const _evictDistance = 5;
  static const _preloadBehind = 1;
  static const _preloadAhead  = 4;
  static const _dominantMin   = 0.6; // minimum fraction to be considered (>60% visible)

  final _controllers = <String, VideoPlayerController>{};
  final _ready       = <String, bool>{};
  final _loading     = <String, Completer<VideoPlayerController?>>{};

  // Feed scroll tracking
  List<String> _window    = [];
  int  _windowIndex       = -1;
  String? _activeUrl;

  // Dominant selection (feed only)
  final _fractions = <String, double>{};
  String? _pendingUrl; // URL waiting to become active (preload in progress)

  List<String> _feedUrls = [];

  // ─── Public state ─────────────────────────────────────────────────────────

  VideoPlayerController? controller(String url) =>
      (_ready[url] == true) ? _controllers[url] : null;

  bool isReady(String url)   => _ready[url] == true;
  bool isLoading(String url) => _loading.containsKey(url);
  bool get hasRoom           => _controllers.length < _maxSlots;
  int  get windowIndex       => _windowIndex;
  /// Number of live (initialized) controllers — must never exceed [_maxSlots].
  int  get liveCount         => _controllers.length;

  // ─── Feed URL registry ────────────────────────────────────────────────────

  void setFeedUrls(List<String> urls) {
    _feedUrls = urls;
  }

  void setActiveUrl(String url) {
    if (url.isEmpty) return;
    final idx = _feedUrls.indexOf(url);
    if (idx >= 0) setWindow(_feedUrls, idx);
  }

  /// Report the visibility fraction for a URL (feed only).
  /// The pool picks the URL with the highest fraction and plays it.
  void setFraction(String url, double fraction) {
    if (url.isEmpty) return;
    if (fraction <= 0) {
      _fractions.remove(url);
    } else {
      _fractions[url] = fraction;
    }
    _maybeChangeDominant();
  }

  void _maybeChangeDominant() {
    String? best;
    double bestFraction = _dominantMin;
    for (final e in _fractions.entries) {
      if (e.value > bestFraction) {
        best = e.key;
        bestFraction = e.value;
      }
    }

    if (best == null) {
      // Nothing dominant — pause whoever is playing
      if (_activeUrl != null) {
        _controllers[_activeUrl!]?.pause();
        _controllers[_activeUrl!]?.setVolume(0);
      }
      _activeUrl = null;
      _pendingUrl = null;
      return;
    }

    if (best == _activeUrl) return; // already playing the right one

    // Switch to best
    _play(best);
  }

  // ─── Window ───────────────────────────────────────────────────────────────

  void setWindow(List<String> urls, int index) {
    _window      = urls;
    _windowIndex = index;

    final toEvict = _controllers.keys.where((url) {
      if (url == _activeUrl) return false;
      final i = urls.indexOf(url);
      // i < 0 = URL no longer in window list (feed refresh / pagination) → evict
      return i < 0 || (i - index).abs() > _evictDistance;
    }).toList();
    for (final u in toEvict) _evict(u);

    final from = (index - _preloadBehind).clamp(0, urls.length - 1);
    final to   = (index + _preloadAhead).clamp(0, urls.length - 1);
    for (var i = from; i <= to; i++) {
      final u = urls[i];
      if (u.isNotEmpty && !isReady(u) && !isLoading(u)) preload(u);
    }
  }

  // ─── Preloading ───────────────────────────────────────────────────────────

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

    // Auto-play if this URL is still the intended dominant one
    if (result != null && _pendingUrl == url) {
      _pendingUrl = null;
      _pauseOthers(url);
      result.setVolume(1);
      try { result.play(); } catch (_) {}
    }

    return result;
  }

  // ─── Playback (used by reels and by _maybeChangeDominant) ─────────────────

  /// Direct play — used by reels (PageView, single active item).
  /// Also called internally by [_maybeChangeDominant] for feed.
  void play(String url) => _play(url);

  void _play(String url) {
    if (url.isEmpty) return;
    _activeUrl  = url;
    _pendingUrl = url;

    final ctrl = _controllers[url];
    if (ctrl != null && _ready[url] == true) {
      _pendingUrl = null;
      _pauseOthers(url);  // pause others only when we are about to actually play
      ctrl.setVolume(1);  // always restore volume (fixes muted-loop bug)
      if (!ctrl.value.isPlaying) {
        try { ctrl.play(); } catch (_) {}
      }
    }
    // If not ready: _pendingUrl stays set; preload() fires _pauseOthers + play
    // when the controller finishes loading — avoids silencing the feed prematurely.
  }

  void _pauseOthers(String exceptUrl) {
    for (final entry in _controllers.entries) {
      if (entry.key == exceptUrl) continue;
      if (entry.value.value.isPlaying) entry.value.pause();
      entry.value.setVolume(0);
    }
  }

  void pause(String url) {
    if (_pendingUrl == url) _pendingUrl = null;
    _controllers[url]?.pause();
    if (_activeUrl == url) _activeUrl = null;
  }

  void pauseAll() {
    _pendingUrl = null;
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
      if (loaded != null) _play(url);
      return;
    }
    _activeUrl  = url;
    _pendingUrl = null;
    ctrl.setVolume(1);
    try { ctrl.play(); } catch (_) {}
  }

  // ─── Lifecycle ────────────────────────────────────────────────────────────

  void disposeAll() {
    _pendingUrl = null;
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
    _fractions.clear();
    _window      = [];
    _windowIndex = -1;
    _activeUrl   = null;
    _feedUrls    = [];
  }

  // ─── Private ──────────────────────────────────────────────────────────────

  void _evictIfNeeded({String? keep}) {
    if (_controllers.length >= _maxSlots) {
      debugPrint('[VideoPool] at capacity (${_controllers.length}/$_maxSlots) — evicting');
    }
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
    _fractions.remove(url);
    if (_pendingUrl == url) _pendingUrl = null;
    if (_activeUrl  == url) _activeUrl  = null;
    ctrl?.pause();
    ctrl?.dispose();
    debugPrint('[VideoPool] evicted: $url');
  }
}
