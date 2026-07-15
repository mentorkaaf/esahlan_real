import 'dart:async';
import 'package:video_player/video_player.dart';
import '../../data/models/community_models.dart';

/// Singleton sliding-window pool of pre-initialized VideoPlayerControllers.
///
/// Call [StoryPool.prewarm] as soon as StoryGroup data is available
/// (from the stories bar) so controllers are ready before the viewer opens.
/// The viewer then calls [advance] to shift the window to the tapped story.
///
/// Window: current-1 behind + current+3 ahead (5 slots max).
class StoryPool {
  StoryPool._();

  // ── Singleton ─────────────────────────────────────────────────────────────

  static StoryPool? _instance;
  static StoryPool get instance => _instance ??= StoryPool._();

  /// Extract all video URLs from groups (flat, in display order) and
  /// immediately start initializing the first story's controller.
  /// Call this from the stories bar whenever groups update.
  static void prewarm(List<StoryGroup> groups) {
    final urls = _extractUrls(groups);
    final pool = instance;
    pool._updateUrls(urls);
    if (urls.isNotEmpty) pool.advance(0);
  }

  static List<String> _extractUrls(List<StoryGroup> groups) {
    final result = <String>[];
    final seen   = <String>{};
    for (final g in groups) {
      for (final s in g.stories) {
        if (s.type == 'video') {
          final url = s.mediaUrl ?? '';
          if (url.isNotEmpty && seen.add(url)) result.add(url);
        }
      }
    }
    return result;
  }

  // ── State ─────────────────────────────────────────────────────────────────

  List<String> _urls = [];
  final Map<String, _Slot> _slots = {};
  int _center = -1;

  static const _kBehind = 1;
  static const _kAhead  = 3;

  // ── Public API ────────────────────────────────────────────────────────────

  /// All video story URLs in flat display order.
  List<String> get urls => List.unmodifiable(_urls);

  /// Shift window to [centerIdx]. Call this:
  ///   - from the stories bar tap handler (before Navigator.push) to start
  ///     warming at the correct story index
  ///   - from the viewer on every story navigation
  void advance(int centerIdx) {
    if (_urls.isEmpty) return;
    _center = centerIdx.clamp(0, _urls.length - 1);
    _evict();
    _warmUp();
  }

  /// Returns the controller immediately if already initialized; null if loading.
  VideoPlayerController? ready(String url) {
    final s = _slots[url];
    return (s != null && s.isReady) ? s.ctrl : null;
  }

  /// Waits up to [timeout] for the controller to be initialized.
  /// Returns null on timeout or error.
  Future<VideoPlayerController?> awaitReady(
    String url, {
    Duration timeout = const Duration(seconds: 10),
  }) async {
    var s = _slots[url];
    if (s == null) {
      s = _Slot(url);
      _slots[url] = s;
      s.init();
    }
    try {
      await s.ready.future.timeout(timeout);
    } catch (_) {}
    return s.isReady ? s.ctrl : null;
  }

  /// Index of [url] in the flat URL list, or -1 if not found.
  int indexOf(String url) => _urls.indexOf(url);

  /// Dispose all slots (called when viewer closes). Singleton remains alive
  /// for the next open — slots are just cleared so they can be re-initialized.
  void releaseAll() {
    for (final s in _slots.values) s.dispose();
    _slots.clear();
    _center = -1;
  }

  // ── Internal ──────────────────────────────────────────────────────────────

  void _updateUrls(List<String> urls) {
    if (_listEquals(_urls, urls)) return;
    // URL list changed (new story posted etc.) — evict all stale slots.
    final stale = _slots.keys
        .where((u) => !urls.contains(u))
        .toList();
    for (final u in stale) {
      _slots[u]!.dispose();
      _slots.remove(u);
    }
    _urls = urls;
    _center = -1;
  }

  bool _listEquals(List<String> a, List<String> b) {
    if (a.length != b.length) return false;
    for (var i = 0; i < a.length; i++) if (a[i] != b[i]) return false;
    return true;
  }

  void _evict() {
    if (_urls.isEmpty) return;
    final lo = (_center - _kBehind).clamp(0, _urls.length - 1);
    final hi = (_center + _kAhead).clamp(0, _urls.length - 1);
    final dead = _slots.keys.where((u) {
      final i = _urls.indexOf(u);
      return i < 0 || i < lo || i > hi;
    }).toList();
    for (final u in dead) {
      _slots[u]!.dispose();
      _slots.remove(u);
    }
  }

  void _warmUp() {
    if (_urls.isEmpty) return;
    final lo = (_center - _kBehind).clamp(0, _urls.length - 1);
    final hi = (_center + _kAhead).clamp(0, _urls.length - 1);

    // Priority: current → +1 → +2 → +3 → -1
    final priority = <int>[_center];
    for (var i = 1; i <= _kAhead; i++) {
      if (_center + i <= hi) priority.add(_center + i);
    }
    if (_center - 1 >= lo) priority.add(_center - 1);

    for (final idx in priority) {
      final url = _urls[idx];
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
      videoPlayerOptions: VideoPlayerOptions(mixWithOthers: false),
    );
    ctrl.initialize().then((_) {
      if (_dead) return;
      isReady = true;
      ctrl.setVolume(0);
      ctrl.seekTo(Duration.zero);
      if (!ready.isCompleted) ready.complete();
    }).catchError((e) {
      if (!ready.isCompleted) ready.completeError(e);
    });
  }

  void dispose() {
    _dead   = true;
    isReady = false;
    try { ctrl.dispose(); } catch (_) {}
  }
}
