import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:video_player/video_player.dart';

/// 3-slot controller pool dedicated to the Reels PageView.
///
/// Keeps at most {index-1, index, index+1} controllers alive at any time.
/// Uses VideoPlayerController.networkUrl() directly so ExoPlayer/AVPlayer
/// handle HLS ABR natively without any proxy or cache layer.
///
/// Usage:
///   ReelPool.instance.setUrls(orderedUrls);
///   await ReelPool.instance.setCurrentIndex(i);  // evicts & preloads
///   ReelPool.instance.play(url);                 // activate current
///   ReelPool.instance.pauseAll();                // lifecycle / background
class ReelPool {
  ReelPool._();
  static final instance = ReelPool._();

  List<String> _urls = [];
  int _currentIndex = -1;

  final Map<String, VideoPlayerController> _controllers = {};
  final Map<String, bool> _ready = {};
  final Map<String, Completer<VideoPlayerController?>> _inflight = {};

  // ── Public read ─────────────────────────────────────────────────────────────

  /// Returns an initialized controller for [url] if already ready, else null.
  VideoPlayerController? getByUrl(String url) =>
      _ready[url] == true ? _controllers[url] : null;

  bool isReady(String url) => _ready[url] == true;
  bool isLoading(String url) => _inflight.containsKey(url);

  // ── Feed context ─────────────────────────────────────────────────────────────

  /// Register the ordered URL list. Call whenever the combined reel list changes.
  void setUrls(List<String> urls) {
    _urls = urls;
  }

  /// Shift the 3-slot window to [index].
  ///
  /// 1. Evicts controllers whose slot distance > 1 from [index].
  /// 2. Preloads {index-1, index, index+1} in background.
  ///
  /// Returns the controller for [index] once initialized (or null on error).
  Future<VideoPlayerController?> setCurrentIndex(int index) async {
    _currentIndex = index;

    // Evict controllers outside the window
    final toEvict = _controllers.keys
        .where((url) {
          final i = _urls.indexOf(url);
          return i < 0 || (i - index).abs() > 1;
        })
        .toList();
    for (final url in toEvict) {
      _disposeUrl(url);
    }

    // Fire-and-forget preloads for adjacent slots
    if (index - 1 >= 0) _load(_urls[index - 1]);
    if (index + 1 < _urls.length) _load(_urls[index + 1]);

    // Await the current slot
    if (index >= 0 && index < _urls.length) {
      return _load(_urls[index]);
    }
    return null;
  }

  /// Ensure [url] is loaded (called from cards for their own slow path).
  Future<VideoPlayerController?> ensureLoaded(String url) => _load(url);

  // ── Playback control ─────────────────────────────────────────────────────────

  /// Play [url], pause and silence all others.
  void play(String url) {
    for (final entry in _controllers.entries) {
      final ctrl = entry.value;
      if (entry.key == url) {
        if (_ready[url] == true) {
          ctrl.setVolume(1);
          if (!ctrl.value.isPlaying) ctrl.play();
        }
      } else {
        if (ctrl.value.isPlaying) ctrl.pause();
        ctrl.setVolume(0);
      }
    }
  }

  void pause(String url) {
    _controllers[url]?.pause();
  }

  void pauseAll() {
    for (final ctrl in _controllers.values) {
      if (ctrl.value.isPlaying) ctrl.pause();
    }
  }

  /// Restore playback after app resume. Re-initializes the controller if
  /// needed (OS may have released codec resources in background).
  Future<void> reactivate(String url) async {
    final ctrl = _controllers[url];
    if (ctrl == null || _ready[url] != true) {
      final loaded = await ensureLoaded(url);
      if (loaded != null) play(url);
      return;
    }
    ctrl.setVolume(1);
    try { ctrl.play(); } catch (_) {}
  }

  // ── Lifecycle ────────────────────────────────────────────────────────────────

  void disposeAll() {
    final urls = _controllers.keys.toList();
    for (final url in urls) {
      _disposeUrl(url);
    }
    _urls = [];
    _currentIndex = -1;
  }

  // ── Internal ─────────────────────────────────────────────────────────────────

  Future<VideoPlayerController?> _load(String url) async {
    if (url.isEmpty) return null;
    if (_ready[url] == true) return _controllers[url];
    if (_inflight.containsKey(url)) return _inflight[url]!.future;

    final completer = Completer<VideoPlayerController?>();
    _inflight[url] = completer;

    try {
      final ctrl = VideoPlayerController.networkUrl(
        Uri.parse(url),
        httpHeaders: const {
          'Connection': 'keep-alive',
          'Accept-Encoding': 'identity',
        },
      );
      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(0);

      // Check window is still valid (page may have changed during init)
      final idx = _urls.indexOf(url);
      if (idx >= 0 && (_currentIndex - idx).abs() <= 1) {
        _controllers[url] = ctrl;
        _ready[url] = true;
        debugPrint('[ReelPool] Ready: idx=$idx $url');
        completer.complete(ctrl);
      } else {
        // Fell out of window while loading — drop it
        ctrl.dispose();
        debugPrint('[ReelPool] Dropped out-of-window: idx=$idx');
        completer.complete(null);
      }
    } catch (e) {
      debugPrint('[ReelPool] Error loading $url: $e');
      if (!completer.isCompleted) completer.complete(null);
    }

    _inflight.remove(url);
    return completer.future;
  }

  void _disposeUrl(String url) {
    // If still loading, let it finish then dispose the result
    final inflight = _inflight.remove(url);
    if (inflight != null) {
      inflight.future.then((ctrl) {
        ctrl?.pause();
        ctrl?.dispose();
      });
    }
    final ctrl = _controllers.remove(url);
    _ready.remove(url);
    if (ctrl != null) {
      ctrl.pause();
      ctrl.dispose();
      debugPrint('[ReelPool] Disposed: $url');
    }
  }
}
