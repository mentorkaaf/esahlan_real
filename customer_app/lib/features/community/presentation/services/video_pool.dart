import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:video_player/video_player.dart';

/// Unified video controller pool — replaces VideoEngine + ReelPool + VideoPreloader.
///
/// Two named instances:
///   VideoPool.reels — for the vertical reels PageView
///   VideoPool.feed  — for the scrollable feed
///
/// Key design:
///   • play(url) before the controller is ready registers a "pending play" intent.
///     As soon as preload completes for that URL, playback starts automatically.
///   • Retries keep the Completer alive — callers always get the final result,
///     not a premature null.
class VideoPool {
  VideoPool._();

  static final reels = VideoPool._();
  static final feed  = VideoPool._();

  static const _maxSlots = 5;

  final _controllers = <String, VideoPlayerController>{};
  final _ready       = <String, bool>{};
  final _loading     = <String, Completer<VideoPlayerController?>>{};

  List<String> _window      = [];
  int          _windowIndex = -1;
  String?      _activeUrl;
  String?      _pendingPlay; // URL to auto-play as soon as its preload finishes

  // ─── Read ─────────────────────────────────────────────────────────────────

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

    final toEvict = _controllers.keys.where((url) {
      final i = urls.indexOf(url);
      return i >= 0 && (i - index).abs() > 2;
    }).toList();
    for (final u in toEvict) _evict(u);

    for (var i = (index - 1).clamp(0, urls.length - 1);
         i <= (index + 1).clamp(0, urls.length - 1);
         i++) {
      final u = urls[i];
      if (u.isNotEmpty && !isReady(u) && !isLoading(u)) preload(u);
    }
  }

  // ─── Preloading ───────────────────────────────────────────────────────────

  /// Ensure [url] is loaded. Deduplicates concurrent calls.
  /// Retries up to 2 times — callers wait for the final result, never get
  /// a premature null.
  Future<VideoPlayerController?> preload(String url) async {
    if (url.isEmpty) return null;
    if (_ready[url] == true) return _controllers[url];
    if (_loading.containsKey(url)) return _loading[url]!.future;

    _evictIfNeeded(keep: url);

    final c = Completer<VideoPlayerController?>();
    _loading[url] = c;

    VideoPlayerController? result;

    for (var attempt = 0; attempt <= 2; attempt++) {
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
        result = ctrl;
        debugPrint('[VideoPool] ready (attempt $attempt): $url');
        break;
      } catch (e) {
        debugPrint('[VideoPool] attempt $attempt failed: $url — $e');
        if (attempt < 2) {
          await Future.delayed(Duration(seconds: 2 + attempt * 2));
        }
      }
    }

    _loading.remove(url);
    c.complete(result);

    // If play() was called while we were loading, start playback now.
    if (result != null && _pendingPlay == url) {
      _pendingPlay = null;
      result.setVolume(1);
      try { result.play(); } catch (_) {}
    }

    return result;
  }

  // ─── Playback ─────────────────────────────────────────────────────────────

  /// Play [url] and silence all other controllers.
  ///
  /// If the controller isn't ready yet, the intent is remembered and playback
  /// starts automatically the moment preload finishes.
  void play(String url) {
    if (url.isEmpty) return;
    _activeUrl   = url;
    _pendingPlay = url; // remembered even if not ready yet

    for (final entry in _controllers.entries) {
      if (entry.key == url) {
        if (_ready[url] == true) {
          _pendingPlay = null; // controller already here — clear the pending flag
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

  /// Re-acquire playback after app resume.
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
    if (_pendingPlay == url) _pendingPlay = null;
    ctrl?.pause();
    ctrl?.dispose();
    debugPrint('[VideoPool] evicted: $url');
  }
}
