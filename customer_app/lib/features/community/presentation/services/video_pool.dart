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

/// Checks disk cache only — no download.
/// Returns local file path on hit, null on miss (non-blocking, 150ms timeout).
Future<String?> _getCachedPath(String url) async {
  try {
    final cached = await _videoCache
        .getFileFromCache(url)
        .timeout(const Duration(milliseconds: 150));
    if (cached != null && await cached.file.exists()) {
      debugPrint('[cache] HIT  ${url.split('/').last}');
      return cached.file.path;
    }
  } catch (_) {}
  return null;
}

/// Caches a video to disk AFTER it has been played (called from _evict).
/// Never called while the video is actively streaming — avoids bandwidth split.
void _cacheAfterEvict(String url) {
  // Only cache if not already cached
  _videoCache.getFileFromCache(url).then((existing) {
    if (existing == null) {
      _videoCache.downloadFile(url).catchError((_) {});
      debugPrint('[cache] queued ${url.split('/').last}');
    }
  }).catchError((_) {});
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
  final _urlIdx      = <String, int>{}; // O(1) url→index lookup

  List<String> _urls        = [];
  int          _windowIndex = -1;
  String?      _activeUrl;
  String?      _pendingPlay;
  Timer?       _rebuildDebounce;
  Timer?       _dominantDebounce;

  static const _maxSlots     = 6;   // more pre-loaded players
  static const _evictDist    = 7;   // keep further videos in memory longer
  static const _preloadAhead = 4;   // preload 4 ahead
  static const _dominant     = 0.5;

  // ── Public API ────────────────────────────────────────────────────────────

  VideoController? controller(String url) => _controllers[url];
  Player?          player    (String url) => _players[url];

  bool isReady  (String url) => _controllers.containsKey(url) && !_loading.containsKey(url);
  bool isLoading(String url) => _loading.containsKey(url);
  int  get windowIndex => _windowIndex;
  int  get liveCount   => _controllers.length + _loading.length;

  void _rebuildIdx(List<String> urls) {
    _urlIdx.clear();
    for (var i = 0; i < urls.length; i++) _urlIdx[urls[i]] = i;
  }

  // Called every time the feed state changes (page 1, 2, …).
  void setFeedUrls(List<String> urls) {
    _urls = urls;
    _rebuildIdx(urls);
    if (urls.isEmpty) return;
    int pivot = _windowIndex >= 0 ? _windowIndex : 0;
    if (_activeUrl != null) {
      final ai = _urlIdx[_activeUrl!];
      if (ai != null) pivot = ai;
    }
    _rebuild(pivot);
  }

  void setWindow(List<String> urls, int index) {
    _urls = urls;
    _rebuildIdx(urls);
    _rebuild(index);
  }

  void setActiveUrl(String url) {
    if (url.isEmpty) return;
    final idx = _urlIdx[url];
    if (idx != null) {
      // Skip if already the active window — no work needed.
      if (_windowIndex == idx) return;
      // Debounce rapid calls during fast scroll — only rebuild once scroll settles.
      _rebuildDebounce?.cancel();
      _rebuildDebounce = Timer(const Duration(milliseconds: 80), () => _rebuild(idx));
    } else if (!isReady(url) && !isLoading(url)) {
      _preload(url);
    }
  }

  void setFraction(String url, double fraction) {
    if (url.isEmpty) return;
    if (fraction <= 0) _fractions.remove(url); else _fractions[url] = fraction;
    // Batch simultaneous setFraction calls (VisibilityDetector fires all visible
    // items at once) into one _updateDominant call to reduce JNI player ops.
    _dominantDebounce?.cancel();
    _dominantDebounce = Timer(const Duration(milliseconds: 50), _updateDominant);
  }

  Future<VideoController?> preload(String url) => _preload(url);

  static Future<bool> isCached(String url) async {
    return await _getCachedPath(url) != null;
  }

  /// Returns local cached file path if available, otherwise the original URL.
  /// Safe to call anywhere — falls back to URL within 150ms if cache is slow.
  static Future<String> resolveUrl(String url) async {
    if (url.isEmpty) return url;
    return await _getCachedPath(url) ?? url;
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
    _rebuildDebounce?.cancel();
    _dominantDebounce?.cancel();
    _activeUrl   = null;
    _pendingPlay = null;
    _loading.clear();
    for (final p in _players.values) { try { p.dispose(); } catch (_) {} }
    _players.clear();
    _controllers.clear();
    _fractions.clear();
    _urlIdx.clear();
    _urls        = [];
    _windowIndex = -1;
  }

  // ── Private — window ──────────────────────────────────────────────────────

  void _rebuild(int pivot) {
    if (_windowIndex == pivot) return; // already built for this index — skip evict+preload
    _windowIndex = pivot;
    _evictFar(pivot);
    _preloadNearby(pivot);
  }

  void _evictFar(int pivot) {
    final evict = _controllers.keys.where((url) {
      if (url == _activeUrl || url == _pendingPlay) return false;
      final i = _urlIdx[url] ?? -1;
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
      // Check disk cache first (non-blocking, 150ms timeout).
      // If hit → use local file (instant, no network).
      // If miss → use URL; video will be cached AFTER eviction (not during streaming).
      final cachedPath = await _getCachedPath(url);

      // Was this URL evicted while we awaited the cache check?
      if (!_loading.containsKey(url)) return null;

      final source = cachedPath ?? url;

      final player = Player(
        configuration: const PlayerConfiguration(
          // 32 MB buffer — reduces rebuffering on slow/mobile networks.
          // Each preloaded player gets its own buffer, so total memory for
          // _maxSlots=6 players ≈ 6×32MB=192MB peak (not all filled at once).
          bufferSize: 32 * 1024 * 1024,
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
      debugPrint('[$_id] ready ${url.split('/').last} (${cachedPath != null ? "CACHE" : "network"})');

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
    // Cache to disk AFTER the player is disposed so bandwidth is fully free.
    if (url.startsWith('http')) _cacheAfterEvict(url);
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
