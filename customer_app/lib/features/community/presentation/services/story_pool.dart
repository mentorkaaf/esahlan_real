import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:media_kit/media_kit.dart';
import 'package:media_kit_video/media_kit_video.dart';
import '../../data/models/community_models.dart';

/// Singleton sliding-window pool of pre-initialized media_kit Players for story videos.
///
/// Uses the same engine as the feed VideoPool (ExoPlayer on Android,
/// AVPlayer on iOS) — hardware-accelerated, fast startup, no black screen.
///
/// Window: current-1 behind + current+3 ahead (5 slots max).
class StoryPool {
  StoryPool._();

  static StoryPool? _instance;
  static StoryPool get instance => _instance ??= StoryPool._();

  static const _kBehind   = 1;
  static const _kAhead    = 3;
  static const _kMaxSlots = 5;

  List<String>          _urls   = [];
  final Map<String, _Slot> _slots = {};
  int _center = -1;

  // ── Public API ────────────────────────────────────────────────────────────

  List<String> get urls => List.unmodifiable(_urls);

  /// Called from the stories bar/provider whenever groups update.
  static void prewarm(List<StoryGroup> groups) {
    final urls = _extractUrls(groups);
    final pool = instance;
    pool._updateUrls(urls);
    if (urls.isNotEmpty && pool._center < 0) pool.advance(0);
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

  /// Shift window to [centerIdx]. Call before opening the viewer and on
  /// every story navigation inside the viewer.
  void advance(int centerIdx) {
    if (_urls.isEmpty) return;
    _center = centerIdx.clamp(0, _urls.length - 1);
    _evict();
    _warmUp();
  }

  int indexOf(String url) => _urls.indexOf(url);

  /// Returns (player, controller) immediately if the slot is ready, or null.
  (Player, VideoController)? ready(String url) {
    final s = _slots[url];
    return (s != null && s.isReady) ? (s.player, s.controller) : null;
  }

  /// Waits up to [timeout] for the slot to be ready.
  Future<(Player, VideoController)?> awaitReady(
    String url, {
    Duration timeout = const Duration(seconds: 12),
  }) async {
    var s = _slots[url];
    if (s == null) {
      _makeRoom();
      s = _Slot(url);
      _slots[url] = s;
      unawaited(s.init());
    }
    try {
      await s.readyCompleter.future.timeout(timeout);
    } catch (_) {}
    return s.isReady ? (s.player, s.controller) : null;
  }

  /// Returns the player immediately if slot exists (even not fully ready yet),
  /// for seekTo / volume calls before awaiting readiness.
  Player? playerFor(String url) => _slots[url]?.player;

  /// Dispose a borrowed controller and return it to idle state for reuse.
  void returnSlot(String url) {
    final s = _slots[url];
    if (s == null || !s.isReady) return;
    s.player.pause();
    s.player.setVolume(0);
    s.player.seek(Duration.zero);
  }

  /// Dispose all slots on app shutdown (not called on viewer close — pool
  /// survives across viewer sessions for instant re-open).
  void disposeAll() {
    for (final s in _slots.values) s.dispose();
    _slots.clear();
    _center = -1;
  }

  // ── Internal ──────────────────────────────────────────────────────────────

  void _updateUrls(List<String> urls) {
    if (_listEquals(_urls, urls)) return;
    final stale = _slots.keys.where((u) => !urls.contains(u)).toList();
    for (final u in stale) {
      _slots[u]!.dispose();
      _slots.remove(u);
    }
    _urls = urls;
  }

  bool _listEquals(List<String> a, List<String> b) {
    if (a.length != b.length) return false;
    for (var i = 0; i < a.length; i++) if (a[i] != b[i]) return false;
    return true;
  }

  void _makeRoom({String? protect}) {
    while (_slots.length >= _kMaxSlots) {
      String? victim;
      int maxDist = -1;
      for (final url in _slots.keys) {
        if (url == protect) continue;
        final i    = _urls.indexOf(url);
        final dist = (i < 0 || _center < 0) ? 999 : (i - _center).abs();
        if (dist > maxDist) { maxDist = dist; victim = url; }
      }
      if (victim == null) break;
      _slots[victim]!.dispose();
      _slots.remove(victim);
      debugPrint('[StoryPool] evicted ${victim.split('/').last}');
    }
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
      debugPrint('[StoryPool] evicted ${u.split('/').last}');
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

    var delay = 0;
    for (final idx in priority) {
      final url = _urls[idx];
      if (!_slots.containsKey(url)) {
        _makeRoom(protect: url);
        final s = _Slot(url);
        _slots[url] = s;
        // First 2 slots (current + next) init immediately for instant playback.
        // Remaining slots stagger to spread JNI cost.
        if (delay < 2) {
          unawaited(s.init());
        } else {
          Future.delayed(Duration(milliseconds: (delay - 1) * 220), () {
            if (!s._dead) unawaited(s.init());
          });
        }
        delay++;
        debugPrint('[StoryPool] warming ${url.split('/').last}');
      }
    }
  }
}

// ── Internal slot ─────────────────────────────────────────────────────────────

class _Slot {
  _Slot(this.url);

  final String url;
  final readyCompleter = Completer<void>();

  late final Player player;
  late final VideoController controller;

  bool isReady = false;
  bool _dead   = false;

  Future<void> init() async {
    player     = Player(configuration: const PlayerConfiguration(bufferSize: 16 * 1024 * 1024));
    controller = VideoController(player);
    try {
      await player.open(Media(url), play: false);
      if (_dead) { player.dispose(); return; }
      await player.setVolume(0);
      await player.seek(Duration.zero);
      isReady = true;
      if (!readyCompleter.isCompleted) readyCompleter.complete();
      debugPrint('[StoryPool] ready ${url.split('/').last}');
    } catch (e) {
      if (!readyCompleter.isCompleted) readyCompleter.completeError(e);
      debugPrint('[StoryPool] failed ${url.split('/').last} — $e');
    }
  }

  void dispose() {
    _dead   = true;
    isReady = false;
    try { player.dispose(); } catch (_) {}
  }
}
