import 'package:cached_video_player_plus/cached_video_player_plus.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';
import 'package:video_player/video_player.dart';

/// Preloads ad video controllers by URL as soon as feed/reel data arrives.
/// Use [awaitReady] from a card to get a controller — instant if already
/// cached on disk, fast-await if still downloading.
class AdPreloader {
  AdPreloader._();
  static final instance = AdPreloader._();

  static final _diskCache = CacheManager(Config(
    'esahlan_ad_cache',
    maxNrOfCacheObjects: 10,
    stalePeriod: const Duration(days: 7),
  ));

  final _ready    = <String, VideoPlayerController>{};
  final _wrappers = <String, CachedVideoPlayerPlus>{};
  // Keeps the in-flight (or completed) Future so duplicate calls share one download
  final _futures  = <String, Future<VideoPlayerController?>>{};

  /// Fire-and-forget: starts downloading [urls] in parallel.
  /// Safe to call repeatedly — skips URLs already ready or in flight.
  void preload(List<String> urls) {
    for (final url in urls) {
      if (url.isEmpty || _ready.containsKey(url) || _futures.containsKey(url)) continue;
      _futures[url] = _initOne(url);
    }
  }

  /// Returns the ready controller immediately if cached, otherwise awaits
  /// the in-flight download (or starts one). Never returns a stale future.
  Future<VideoPlayerController?> awaitReady(String url) {
    if (url.isEmpty) return Future.value(null);
    if (_ready.containsKey(url)) return Future.value(_ready[url]);
    if (_futures.containsKey(url)) return _futures[url]!;
    _futures[url] = _initOne(url);
    return _futures[url]!;
  }

  Future<VideoPlayerController?> _initOne(String url) async {
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
      _wrappers[url] = wrapper;
      _ready[url]   = ctrl;
      return ctrl;
    } catch (_) {
      return null;
    }
  }

  bool isLoading(String url) => _futures.containsKey(url) && !_ready.containsKey(url);

  void dispose() {
    for (final w in _wrappers.values) w.dispose();
    _wrappers.clear();
    _ready.clear();
    _futures.clear();
  }
}
