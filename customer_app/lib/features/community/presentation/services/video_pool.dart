import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter_cache_manager/flutter_cache_manager.dart';
import 'package:media_kit/media_kit.dart';
import 'package:media_kit_video/media_kit_video.dart';
import 'package:connectivity_plus/connectivity_plus.dart';
import '../../../../core/providers/app_settings_provider.dart';

// ── Video disk cache (max 40 videos, 7 days) ─────────────────────────────────
final _videoCache = CacheManager(
  Config(
    'esahlan_video_cache',
    maxNrOfCacheObjects: 40,
    stalePeriod: const Duration(days: 7),
  ),
);

// ── Connectivity helper ───────────────────────────────────────────────────────
// Cached so every preload decision doesn't await a platform call.
bool _isWifi = true; // optimistic default
StreamSubscription<List<ConnectivityResult>>? _connectivitySub;

void _initConnectivity() {
  _connectivitySub ??= Connectivity().onConnectivityChanged.listen((results) {
    _isWifi = results.contains(ConnectivityResult.wifi) ||
              results.contains(ConnectivityResult.ethernet);
  });
  // Kick off an immediate check so _isWifi is correct before first preload.
  Connectivity().checkConnectivity().then((results) {
    _isWifi = results.contains(ConnectivityResult.wifi) ||
              results.contains(ConnectivityResult.ethernet);
  });
}

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

/// Background cache disabled — competes with active streaming bandwidth.
/// Videos are cached naturally by flutter_cache_manager during normal streaming.
void _cacheAfterEvict(String url) {}

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

  /// Call once at app startup (main.dart or community shell) to start
  /// tracking connectivity so WiFi/cellular preload decisions are accurate.
  static void initConnectivity() => _initConnectivity();

  /// Pick the best video URL for the current network condition.
  ///
  /// WiFi   → MP4 direct (1 RTT to first frame, Range-request, CDN-cacheable).
  /// Cellular → HLS (adaptive bitrate: starts at 360p in <1s, upgrades when
  ///            bandwidth allows; better than waiting for MP4 moov atom on slow net).
  ///
  /// Falls back to mp4Url if hlsUrl is null or empty.
  static String selectVideoUrl(String mp4Url, String? hlsUrl) {
    if (!_isWifi && hlsUrl != null && hlsUrl.isNotEmpty) return hlsUrl;
    return mp4Url;
  }

  /// Preload BOTH mp4 and hls urls for a video so whichever is selected
  /// by selectVideoUrl() is already in the pool. Call on WiFi when idle.
  Future<void> preloadBoth(String mp4Url, String? hlsUrl) async {
    await _preload(mp4Url);
    if (hlsUrl != null && hlsUrl.isNotEmpty && hlsUrl != mp4Url) {
      await _preload(hlsUrl);
    }
  }

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

  static const _maxSlotsWifi     = 6;   // WiFi: enough slots without competing with scroll CPU
  static const _maxSlotsCellular = 4;   // Cellular: fewer to save RAM/bandwidth
  static const _evictDist        = 8;   // keep videos in memory a bit longer
  static const _preloadAheadWifi = 4;   // WiFi: 4 ahead balances smoothness vs CPU during scroll
  static const _preloadAheadCell = 2;   // Cellular: only 2 ahead (save bandwidth)
  static const _dominant         = 0.5;

  int get _maxSlots     => _isWifi ? _maxSlotsWifi     : _maxSlotsCellular;
  int get _preloadAhead => _isWifi ? _preloadAheadWifi : _preloadAheadCell;

  // ── Public API ────────────────────────────────────────────────────────────

  VideoController? controller(String url) => _controllers[url];
  Player?          player    (String url) => _players[url];

  bool isReady  (String url) => _controllers.containsKey(url) && !_loading.containsKey(url);
  bool isLoading(String url) => _loading.containsKey(url);
  bool get isWifi => _isWifi;
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
      _rebuildDebounce = Timer(const Duration(milliseconds: 120), () => _rebuild(idx));
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
    _dominantDebounce = Timer(const Duration(milliseconds: 100), _updateDominant);
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
    final ahead = _preloadAhead;
    // Prioritize: pivot first, then 1 ahead, then spread outward.
    // This ensures the current video starts ASAP, not after ahead videos init.
    final priorities = [
      pivot,
      ...List.generate(ahead, (i) => pivot + i + 1),
      pivot - 1, // one behind (going back)
    ];
    for (final i in priorities) {
      if (i < 0 || i >= _urls.length) continue;
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

      // 8 MB buffer — enough for smooth playback, small enough to start fast.
      final player = Player(
        configuration: const PlayerConfiguration(bufferSize: 8 * 1024 * 1024),
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
    final limit = _maxSlots;
    if (liveCount < limit) return;
    String? victim;
    int maxDist = -1;
    for (final url in _controllers.keys) {
      if (url == _activeUrl || url == _pendingPlay || url == protect) continue;
      final i    = _urlIdx[url] ?? -1;
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
