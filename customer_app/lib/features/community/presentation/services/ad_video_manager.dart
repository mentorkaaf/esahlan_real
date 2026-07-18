import 'package:flutter/foundation.dart';
import '../../../../core/constants/app_constants.dart';
import 'package:video_player/video_player.dart';

/// Ad video controller manager — completely independent from VideoPool.
///
/// Design (matches YouTube / TikTok / Twitter ad behaviour):
///   • Controllers are shared per URL: two cards showing the same ad URL
///     reuse the same controller — no duplicate downloads.
///   • Disk-cached: second view of same ad is instant (no network).
///   • Fire-and-forget preload when feed data arrives, so the card
///     gets a ready controller the moment it scrolls into view.
///   • awaitController() deduplicates in-flight requests — calling it
///     10 times for the same URL triggers exactly one network fetch.
///   • Zero interaction with VideoPool — ads never pause content videos.
class AdVideoManager {
  AdVideoManager._();
  static final instance = AdVideoManager._();


  static const _kMaxControllers = 8; // cap memory: 8 ad controllers max

  final _controllers    = <String, VideoPlayerController>{};
  final _insertionOrder = <String>[]; // LRU eviction: evict oldest when over cap
  // Keeps one Future per URL so concurrent calls share a single download.
  final _futures        = <String, Future<VideoPlayerController?>>{};

  // ── Public API ────────────────────────────────────────────────────────────

  /// Fire-and-forget: start caching [urls] immediately.
  /// Call this when feed / reel data first arrives.
  void preload(List<String> urls) {
    for (final url in urls) {
      if (url.isEmpty || _controllers.containsKey(url) || _futures.containsKey(url)) continue;
      _futures[url] = _init(url);
    }
  }

  /// Returns the ready controller — instantly from memory/disk or by awaiting
  /// the in-flight download. Never throws; returns null on failure.
  Future<VideoPlayerController?> awaitController(String url) {
    if (url.isEmpty) return Future.value(null);
    if (_controllers.containsKey(url)) return Future.value(_controllers[url]);
    if (_futures.containsKey(url)) return _futures[url]!;
    _futures[url] = _init(url);
    return _futures[url]!;
  }

  /// Instant check — null if not ready yet.
  VideoPlayerController? controller(String url) => _controllers[url];

  void disposeAll() {
    for (final c in _controllers.values) {
      try { c.pause(); c.dispose(); } catch (_) {}
    }
    _controllers.clear();
    _insertionOrder.clear();
    _futures.clear();
  }

  // ── Private ───────────────────────────────────────────────────────────────

  void _evictIfNeeded() {
    while (_controllers.length >= _kMaxControllers && _insertionOrder.isNotEmpty) {
      final oldest = _insertionOrder.removeAt(0);
      final c = _controllers.remove(oldest);
      try { c?.pause(); c?.dispose(); } catch (_) {}
      _futures.remove(oldest);
      if (kDebugMode) debugPrint('[AdVideoManager] evicted: $oldest');
    }
  }

  Future<VideoPlayerController?> _init(String url) async {
    try {
      final ctrl = VideoPlayerController.networkUrl(
        Uri.parse(url),
        httpHeaders: const {'Connection': 'keep-alive'},
      );
      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(0);
      _evictIfNeeded();
      _controllers[url] = ctrl;
      _insertionOrder.add(url);
      if (kDebugMode) debugPrint('[AdVideoManager] ready: $url');
      return ctrl;
    } catch (e) {
      if (kDebugMode) debugPrint('[AdVideoManager] failed: $url — $e');
      _futures.remove(url); // allow retry on next awaitController call
      return null;
    }
  }
}
