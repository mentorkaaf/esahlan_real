import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:video_player/video_player.dart';

/// Unified video controller pool — replaces VideoEngine + ReelPool + VideoPreloader.
///
/// Two named instances:
///   VideoPool.reels — for the vertical reels PageView
///   VideoPool.feed  — for the scrollable feed
///
/// Each instance keeps at most [_maxSlots] initialized controllers.
/// Distance-based LRU evicts the farthest controller when the pool is full.
class VideoPool {
  VideoPool._();

  static final reels = VideoPool._();
  static final feed  = VideoPool._();

  static const _maxSlots = 5;

  final _controllers = <String, VideoPlayerController>{};
  final _ready       = <String, bool>{};
  final _loading     = <String, Completer<VideoPlayerController?>>{};
  final _retries     = <String, int>{};

  List<String> _window      = [];
  int          _windowIndex = -1;
  String?      _activeUrl;

  // ─── Read ─────────────────────────────────────────────────────────────────

  /// Initialized controller for [url], or null if not ready yet.
  VideoPlayerController? controller(String url) =>
      (_ready[url] == true) ? _controllers[url] : null;

  bool isReady(String url)   => _ready[url] == true;
  bool isLoading(String url) => _loading.containsKey(url);

  // ─── Window (for paged views like Reels) ──────────────────────────────────

  /// Shift the preload window to [index] within [urls].
  /// Evicts controllers outside ±2 of [index]; preloads {index-1, index, index+1}.
  void setWindow(List<String> urls, int index) {
    _window      = urls;
    _windowIndex = index;

    // Evict far-away controllers that ARE in this URL list
    final toEvict = _controllers.keys.where((url) {
      final i = urls.indexOf(url);
      return i >= 0 && (i - index).abs() > 2;
    }).toList();
    for (final u in toEvict) _evict(u);

    // Preload current + neighbours
    for (var i = (index - 1).clamp(0, urls.length - 1);
         i <= (index + 1).clamp(0, urls.length - 1);
         i++) {
      final u = urls[i];
      if (u.isNotEmpty && !isReady(u) && !isLoading(u)) preload(u);
    }
  }

  // ─── Preloading ───────────────────────────────────────────────────────────

  /// Ensure [url] is loaded. Deduplicates concurrent calls.
  /// Returns the controller when ready, or null on failure.
  Future<VideoPlayerController?> preload(String url) async {
    if (url.isEmpty) return null;
    if (_ready[url] == true) return _controllers[url];
    if (_loading.containsKey(url)) return _loading[url]!.future;

    _evictIfNeeded(keep: url);

    final c = Completer<VideoPlayerController?>();
    _loading[url] = c;

    try {
      final ctrl = VideoPlayerController.networkUrl(
        Uri.parse(url),
        httpHeaders: const {'Connection': 'keep-alive'},
      );
      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(0);

      _controllers[url] = ctrl;
      _ready[url]       = true;
      _retries.remove(url);
      _loading.remove(url);
      c.complete(ctrl);
      debugPrint('[VideoPool] ready: $url');
    } catch (e) {
      _loading.remove(url);
      c.complete(null);

      final r = _retries[url] ?? 0;
      if (r < 2) {
        _retries[url] = r + 1;
        await Future.delayed(Duration(seconds: 2 + r * 2));
        return preload(url);
      }
      debugPrint('[VideoPool] failed ($r retries): $url');
    }

    return c.future;
  }

  // ─── Playback ─────────────────────────────────────────────────────────────

  /// Play [url] and silence all other controllers.
  void play(String url) {
    if (url.isEmpty) return;
    _activeUrl = url;

    for (final entry in _controllers.entries) {
      if (entry.key == url) {
        if (_ready[url] == true && !entry.value.value.isPlaying) {
          entry.value.setVolume(1);
          entry.value.play();
        }
      } else {
        if (entry.value.value.isPlaying) entry.value.pause();
        entry.value.setVolume(0);
      }
    }
  }

  void pause(String url) {
    _controllers[url]?.pause();
    if (_activeUrl == url) _activeUrl = null;
  }

  void pauseAll() {
    for (final ctrl in _controllers.values) {
      if (ctrl.value.isPlaying) ctrl.pause();
    }
    _activeUrl = null;
  }

  /// Re-acquire playback after app resume. Re-inits if the controller was lost.
  Future<void> reactivate(String url) async {
    if (url.isEmpty) return;
    final ctrl = controller(url);
    if (ctrl == null) {
      final loaded = await preload(url);
      if (loaded != null) play(url);
      return;
    }
    _activeUrl = url;
    ctrl.setVolume(1);
    try { ctrl.play(); } catch (_) {}
  }

  // ─── Lifecycle ────────────────────────────────────────────────────────────

  void disposeAll() {
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
    _retries.clear();
    _window      = [];
    _windowIndex = -1;
    _activeUrl   = null;
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

      final i    = _window.isEmpty ? -1 : _window.indexOf(url);
      final dist = i < 0 ? 999 : (i - _windowIndex).abs();

      if (dist > bestDist) {
        bestDist = dist;
        best     = url;
      }
    }
    return best;
  }

  void _evict(String url) {
    _loading.remove(url)?.future.then((c) { c?.pause(); c?.dispose(); });
    final ctrl = _controllers.remove(url);
    _ready.remove(url);
    ctrl?.pause();
    ctrl?.dispose();
    debugPrint('[VideoPool] evicted: $url');
  }
}
