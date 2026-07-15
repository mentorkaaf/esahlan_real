import 'dart:async';
import 'package:video_player/video_player.dart';

/// Sliding-window pool of pre-initialized VideoPlayerControllers for Stories.
///
/// Window: current-1 behind, current+3 ahead (5 slots total).
/// Controllers are initialized in priority order:
///   current → +1 → +2 → +3 → -1
///
/// Usage:
///   final pool = StoryPool(videoUrls);
///   pool.advance(0);                        // open at index 0
///   final ctrl = pool.ready(url);          // non-null if already loaded
///   final ctrl = await pool.awaitReady(url); // wait for it
///   pool.dispose();                         // on viewer close
class StoryPool {
  StoryPool(this.urls);

  final List<String> urls;

  final Map<String, _Slot> _slots = {};
  int _center = -1;

  static const _kBehind = 1;
  static const _kAhead  = 3;

  // ── Public API ──────────────────────────────────────────────────────────────

  /// Shift the window to [centerIdx] and fire background initialization.
  void advance(int centerIdx) {
    if (urls.isEmpty) return;
    _center = centerIdx.clamp(0, urls.length - 1);
    _evict();
    _warmUp();
  }

  /// Returns the controller immediately if it is already initialized.
  /// Returns null while still loading (caller should show thumbnail).
  VideoPlayerController? ready(String url) {
    final s = _slots[url];
    return (s != null && s.isReady) ? s.ctrl : null;
  }

  /// Waits up to [timeout] for the controller to finish initializing.
  /// Returns null on error or timeout.
  Future<VideoPlayerController?> awaitReady(
    String url, {
    Duration timeout = const Duration(seconds: 10),
  }) async {
    var s = _slots[url];
    if (s == null) {
      // On-demand slot for stories outside the normal window
      s = _Slot(url);
      _slots[url] = s;
      s.init();
    }
    try {
      await s.ready.future.timeout(timeout);
    } catch (_) {
      // Timeout or network error — caller shows thumbnail
    }
    return s.isReady ? s.ctrl : null;
  }

  void dispose() {
    for (final s in _slots.values) {
      s.dispose();
    }
    _slots.clear();
  }

  // ── Internal ────────────────────────────────────────────────────────────────

  void _evict() {
    final lo = (_center - _kBehind).clamp(0, urls.length - 1);
    final hi = (_center + _kAhead).clamp(0, urls.length - 1);

    final dead = _slots.keys.where((u) {
      final i = urls.indexOf(u);
      return i < 0 || i < lo || i > hi;
    }).toList();

    for (final u in dead) {
      _slots[u]!.dispose();
      _slots.remove(u);
    }
  }

  void _warmUp() {
    final lo = (_center - _kBehind).clamp(0, urls.length - 1);
    final hi = (_center + _kAhead).clamp(0, urls.length - 1);

    // Priority: current first so it's ready ASAP, then ahead, then behind
    final priority = <int>[_center];
    for (var i = 1; i <= _kAhead; i++) {
      if (_center + i <= hi) priority.add(_center + i);
    }
    if (_center - 1 >= lo) priority.add(_center - 1);

    for (final idx in priority) {
      final url = urls[idx];
      if (!_slots.containsKey(url)) {
        final s = _Slot(url);
        _slots[url] = s;
        s.init();
      }
    }
  }
}

// ── Internal slot ─────────────────────────────────────────────────────────────

class _Slot {
  _Slot(this.url);

  final String url;
  final ready = Completer<void>();

  late VideoPlayerController ctrl;
  bool isReady = false;
  bool _dead   = false;

  void init() {
    ctrl = VideoPlayerController.networkUrl(
      Uri.parse(url),
      // mixWithOthers: false so this slot claims audio focus when played.
      // Volume is set to 0 until this slot becomes the active story.
      videoPlayerOptions: VideoPlayerOptions(mixWithOthers: false),
    );

    ctrl.initialize().then((_) {
      if (_dead) return;
      isReady = true;
      // Pre-position at frame 0, silenced — ready to play instantly.
      ctrl.setVolume(0);
      ctrl.seekTo(Duration.zero);
      if (!ready.isCompleted) ready.complete();
    }).catchError((e) {
      if (!ready.isCompleted) ready.completeError(e);
    });
  }

  void dispose() {
    _dead    = true;
    isReady  = false;
    try { ctrl.dispose(); } catch (_) {}
  }
}
