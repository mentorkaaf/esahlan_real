import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';
import 'package:media_kit/media_kit.dart';
import 'package:media_kit_video/media_kit_video.dart';
import '../../../../core/providers/app_settings_provider.dart';

// ── Video disk cache (max 30 videos, 7 days) ─────────────────────────────────
final _videoCache = CacheManager(
  Config(
    'esahlan_video_cache',
    maxNrOfCacheObjects: 30,
    stalePeriod: const Duration(days: 7),
  ),
);

/// Returns the local cached file path if the video is already on disk,
/// otherwise returns the original URL so playback starts immediately.
/// Also schedules a background download so the NEXT view is instant.
Future<String> _resolveVideoUrl(String url) async {
  try {
    final cached = await _videoCache.getFileFromCache(url);
    if (cached != null && await cached.file.exists()) {
      debugPrint('[cache] HIT  ${url.split('/').last}');
      return cached.file.path;
    }
  } catch (_) {}
  // Miss — kick off background cache, play from network now
  _cacheInBackground(url);
  debugPrint('[cache] MISS ${url.split('/').last}');
  return url;
}

void _cacheInBackground(String url) {
  _videoCache.downloadFile(url).catchError((_) {});
}

// ── VideoPool ─────────────────────────────────────────────────────────────────
//
// Two singletons: VideoPool.feed  (feed scroll)
//                 VideoPool.reels (reels scroll)
//
// Uses media_kit Player (ExoPlayer on Android, AVPlayer on iOS) for native
// hardware-accelerated decoding, consistent buffering, and smooth playback.

class VideoPool {
  VideoPool._({required String id, bool loop = true}) : _id = id, _loop = loop;

  static final feed  = VideoPool._(id: 'feed',  loop: true);
  static final reels = VideoPool._(id: 'reels', loop: false);

  final bool _loop;

  final String _id;

  final _players     = <String, Player>{};
  final _controllers = <String, VideoController>{};
  final _loading     = <String, Future<VideoController?>>{};
  final _fractions   = <String, double>{};

  List<String> _urls        = [];
  int          _windowIndex = -1;
  String?      _activeUrl;
  String?      _pendingPlay;

  // Fewer concurrent preloads → current video gets more bandwidth on slow networks.
  static const _maxSlots     = 4;
  static const _evictDist    = 5;
  static const _preloadAhead = 2;
  static const _dominant     = 0.5;

  // ── Public API ────────────────────────────────────────────────────────────

  VideoController? controller(String url) => _controllers[url];
  Player?          player    (String url) => _players[url];

  bool isReady  (String url) => _controllers.containsKey(url) && !_loading.containsKey(url);
  bool isLoading(String url) => _loading.containsKey(url);
  int  get windowIndex => _windowIndex;
  int  get liveCount   => _controllers.length + _loading.length;

  // Called every time the feed state changes (page 1, 2, …).
  void setFeedUrls(List<String> urls) {
    _urls = urls;
    if (urls.isEmpty) return;
    int pivot = _windowIndex >= 0 ? _windowIndex : 0;
    if (_activeUrl != null) {
      final ai = urls.indexOf(_activeUrl!);
      if (ai >= 0) pivot = ai;
    }
    _rebuild(pivot);
  }

  void setWindow(List<String> urls, int index) {
    _urls = urls;
    _rebuild(index);
  }

  void setActiveUrl(String url) {
    if (url.isEmpty) return;
    final idx = _urls.indexOf(url);
    if (idx >= 0) _rebuild(idx);
    else if (!isReady(url) && !isLoading(url)) _preload(url);
  }

  void setFraction(String url, double fraction) {
    if (url.isEmpty) return;
    if (fraction <= 0) _fractions.remove(url); else _fractions[url] = fraction;
    _updateDominant();
  }

  Future<VideoController?> preload(String url) => _preload(url);

  /// Cache videos to disk in the background (no Player created — just disk).
  /// Call this for off-screen videos that should be ready instantly later.
  static void warmDiskCache(List<String> urls) {
    for (final url in urls) {
      if (url.isEmpty) continue;
      _cacheInBackground(url);
    }
  }

  static Future<bool> isCached(String url) async {
    try {
      final f = await _videoCache.getFileFromCache(url);
      return f != null && await f.file.exists();
    } catch (_) { return false; }
  }

  void play(String url) => _doPlay(url);

  void pause(String url) {
    _players[url]?.setVolume(0);
    _players[url]?.pause();
    if (_activeUrl   == url) _activeUrl   = null;
    if (_pendingPlay == url) _pendingPlay = null;
  }

  void pauseAll() {
    _activeUrl   = null;
    _pendingPlay = null;
    for (final p in _players.values) { p.setVolume(0); p.pause(); }
  }

  Future<void> reactivate(String url) async {
    if (url.isEmpty) return;
    if (isReady(url)) { _doPlay(url); return; }
    await _preload(url);
    if (isReady(url)) _doPlay(url);
  }

  void disposeAll() {
    _activeUrl   = null;
    _pendingPlay = null;
    _loading.clear();
    for (final p in _players.values) { try { p.dispose(); } catch (_) {} }
    _players.clear();
    _controllers.clear();
    _fractions.clear();
    _urls        = [];
    _windowIndex = -1;
  }

  // ── Private — window ──────────────────────────────────────────────────────

  void _rebuild(int pivot) {
    _windowIndex = pivot;
    _evictFar(pivot);
    _preloadNearby(pivot);
  }

  void _evictFar(int pivot) {
    final evict = _controllers.keys.where((url) {
      if (url == _activeUrl || url == _pendingPlay) return false;
      final i = _urls.indexOf(url);
      return i < 0 || (i - pivot).abs() > _evictDist;
    }).toList();
    for (final url in evict) _evict(url);
  }

  void _preloadNearby(int pivot) {
    if (_urls.isEmpty) return;
    final from = (pivot - 1          ).clamp(0, _urls.length - 1);
    final to   = (pivot + _preloadAhead).clamp(0, _urls.length - 1);
    for (var i = from; i <= to; i++) {
      final url = _urls[i];
      if (url.isEmpty || isReady(url) || isLoading(url)) continue;
      _preload(url);
    }
  }

  // ── Private — preload ─────────────────────────────────────────────────────

  Future<VideoController?> _preload(String url) {
    if (url.isEmpty)    return Future.value(null);
    if (isReady(url))   return Future.value(_controllers[url]);
    if (isLoading(url)) return _loading[url]!;

    final future = _doInit(url);
    _loading[url] = future;
    return future;
  }

  Future<VideoController?> _doInit(String url) async {
    _makeRoom(protect: url);
    try {
      // Resolve to local file if cached, otherwise use network URL
      final source = await _resolveVideoUrl(url);

      // Was this URL evicted while we awaited the cache check?
      if (!_loading.containsKey(url)) return null;

      final player = Player(
        configuration: const PlayerConfiguration(
          // 8 MB — enough for ~5s of 1080p. Smaller = less bandwidth fighting
          // between concurrent preloads on slow mobile networks.
          bufferSize: 8 * 1024 * 1024,
        ),
      );
      final controller = VideoController(player);

      await player.open(Media(source), play: false);
      await player.setVolume(0);
      // Pool default (_loop) combined with user's video loop setting
      final loopEnabled = _loop && AppSettingsNotifier.current.videoLoop;
      await player.setPlaylistMode(loopEnabled ? PlaylistMode.single : PlaylistMode.none);

      // Was this URL evicted while awaiting open?
      if (!_loading.containsKey(url)) {
        player.dispose();
        return null;
      }

      _players[url]     = player;
      _controllers[url] = controller;
      _loading.remove(url);
      debugPrint('[$_id] ready ${url.split('/').last} (${source == url ? "network" : "cache"})');

      if (_pendingPlay == url) {
        _pendingPlay = null;
        _doPlay(url);
      }

      return controller;
    } catch (e) {
      _loading.remove(url);
      debugPrint('[$_id] failed ${url.split('/').last} — $e');
      return null;
    }
  }

  // ── Private — eviction ────────────────────────────────────────────────────

  void _makeRoom({String? protect}) {
    if (liveCount < _maxSlots) return;
    String? victim;
    int maxDist = -1;
    for (final url in _controllers.keys) {
      if (url == _activeUrl || url == _pendingPlay || url == protect) continue;
      final i    = _urls.isEmpty ? -1 : _urls.indexOf(url);
      final dist = (i < 0 || _windowIndex < 0) ? 999 : (i - _windowIndex).abs();
      if (dist > maxDist) { maxDist = dist; victim = url; }
    }
    if (victim != null) _evict(victim);
  }

  void _evict(String url) {
    _loading.remove(url);
    _controllers.remove(url);
    final p = _players.remove(url);
    try { p?.dispose(); } catch (_) {}
    _fractions.remove(url);
    debugPrint('[$_id] evicted ${url.split('/').last}');
  }

  // ── Private — playback ────────────────────────────────────────────────────

  void _doPlay(String url) {
    if (url.isEmpty) return;
    // Respect Data Saver / Video autoplay settings
    final s = AppSettingsNotifier.current;
    if (s.disableAutoplay) return;
    if (s.videoAutoplay == 'never') return;
    // 'wifi_only' — we can't check connectivity here without a plugin, skip for now

    _activeUrl   = url;
    _pendingPlay = url;

    final player = _players[url];
    if (player != null) {
      _pendingPlay = null;
      _pauseOthers(url);
      // Volume is controlled by the widget layer (_globalMuted) — pool just plays
      player.play();
    }
    // Not ready → _pendingPlay set; _doInit completion will trigger play.
  }

  void _pauseOthers(String except) {
    for (final e in _players.entries) {
      if (e.key == except) continue;
      e.value.setVolume(0);
      e.value.pause();
    }
  }

  void _updateDominant() {
    String? best;
    double  bestF = _dominant;
    for (final e in _fractions.entries) {
      if (e.value > bestF) { best = e.key; bestF = e.value; }
    }
    if (best == null) {
      // Nothing above dominance threshold — pause whatever is currently active
      if (_activeUrl != null) {
        _players[_activeUrl!]?.setVolume(0);
        _players[_activeUrl!]?.pause();
        _activeUrl = null;
      }
      return;
    }
    if (best == _activeUrl) return;
    _doPlay(best);
  }
}
