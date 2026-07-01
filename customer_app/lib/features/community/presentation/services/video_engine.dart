import 'dart:async';
import 'dart:collection';
import 'package:flutter/foundation.dart';
import 'package:video_player/video_player.dart';
import 'package:cached_video_player_plus/cached_video_player_plus.dart';

// ── Metrics ──────────────────────────────────────────────────────────────────

class VideoEngineMetrics {
  int cacheHits = 0;
  int cacheMisses = 0;
  int preloadSuccesses = 0;
  int preloadFailures = 0;
  int disposals = 0;
  int bufferEvents = 0;
  final List<int> _startupTimesMs = [];

  double get cacheHitRatio =>
      (cacheHits + cacheMisses) == 0 ? 0 : cacheHits / (cacheHits + cacheMisses);

  int get avgStartupMs => _startupTimesMs.isEmpty
      ? 0
      : _startupTimesMs.reduce((a, b) => a + b) ~/ _startupTimesMs.length;

  int get p95StartupMs {
    if (_startupTimesMs.length < 2) return avgStartupMs;
    final sorted = List<int>.from(_startupTimesMs)..sort();
    return sorted[(sorted.length * 0.95).floor().clamp(0, sorted.length - 1)];
  }

  void recordStartup(int ms) {
    _startupTimesMs.add(ms);
    if (_startupTimesMs.length > 200) _startupTimesMs.removeAt(0);
  }

  @override
  String toString() =>
      '[VideoEngine] hits=$cacheHits misses=$cacheMisses '
      'hitRatio=${(cacheHitRatio * 100).toStringAsFixed(1)}% '
      'avgStartup=${avgStartupMs}ms p95=${p95StartupMs}ms '
      'buffers=$bufferEvents disposals=$disposals';
}

// ── Pool entry ────────────────────────────────────────────────────────────────

class _PoolEntry {
  final CachedVideoPlayerPlus? cachedPlayer;
  final VideoPlayerController? directController; // used for HLS streams
  bool initialized;
  DateTime lastAccess;
  int feedIndex;

  _PoolEntry.cached(CachedVideoPlayerPlus player, {this.initialized = false, this.feedIndex = -1})
      : cachedPlayer = player,
        directController = null,
        lastAccess = DateTime.now();

  _PoolEntry.direct(VideoPlayerController ctrl, {this.initialized = false, this.feedIndex = -1})
      : directController = ctrl,
        cachedPlayer = null,
        lastAccess = DateTime.now();

  VideoPlayerController? get vpController {
    if (!initialized) return null;
    return directController ?? cachedPlayer?.controller;
  }

  void dispose() {
    if (directController != null) {
      directController!.pause();
      directController!.dispose();
    } else {
      cachedPlayer?.controller.pause();
      cachedPlayer?.dispose();
    }
  }
}

// ── VideoEngine ───────────────────────────────────────────────────────────────

/// Ultra-low-latency video playback engine.
///
/// Design pillars:
///   1. Position-aware pool — evicts videos farthest from current playback position.
///   2. Velocity-adaptive preloading — fast scroll → fewer preloads to save bandwidth.
///   3. Priority queue — videos closest to current position initialise first.
///   4. Metrics — startup latency, cache hit ratio, buffer events for debugging.
///   5. Silent failover — auto-retry with back-off, never freezes UI.
class VideoEngine {
  VideoEngine._();
  static final instance = VideoEngine._();

  // Pool capacity: current(1) + ahead(3) + behind(2) + buffer(4) = 10
  // Larger pool means more videos survive scroll-back without re-buffering.
  static const _maxControllers = 10;
  static const _maxPreloadAhead = 3;
  static const _maxPreloadBehind = 2;

  final _pool = LinkedHashMap<String, _PoolEntry>();
  final _preloading = <String, Completer<VideoPlayerController?>>{};
  final _retryCount = <String, int>{};

  // Feed position context — enables distance-based eviction
  List<String> _orderedUrls = [];
  int _currentIndex = -1;
  String? _activeUrl;

  // Scroll velocity (absolute px/s) — controls preload aggressiveness
  double _scrollVelocity = 0;
  Timer? _velocityDecayTimer;

  final metrics = VideoEngineMetrics();

  // ── Position context ────────────────────────────────────────────────────────

  /// Inform the engine of the current feed order and active position.
  /// Call this on every page/index change in reels and feed.
  void setFeedContext(List<String> urls, int currentIndex) {
    _orderedUrls = urls;
    _currentIndex = currentIndex;
    // Update cached feed indices for existing entries
    for (final e in _pool.entries) {
      e.value.feedIndex = _orderedUrls.indexOf(e.key);
    }
    _evictDistantControllers();
  }

  /// Inform the engine of the current scroll speed (absolute value).
  /// Call from ScrollController.addListener or PageController.addListener.
  void notifyScrollVelocity(double pixelsPerSecond) {
    _scrollVelocity = pixelsPerSecond.abs();
    _velocityDecayTimer?.cancel();
    _velocityDecayTimer = Timer(const Duration(milliseconds: 600), () {
      _scrollVelocity = 0;
    });
  }

  int get _dynamicPreloadCount {
    if (_scrollVelocity > 4000) return 1; // very fast — save bandwidth
    if (_scrollVelocity > 2000) return 2; // medium
    return _maxPreloadAhead; // slow / stopped — preload fully
  }

  // ── Controller access ────────────────────────────────────────────────────────

  VideoPlayerController? getController(String url) {
    final entry = _pool[url];
    if (entry != null && entry.initialized) {
      entry.lastAccess = DateTime.now();
      return entry.vpController;
    }
    return null;
  }

  bool isReady(String url) => _pool[url]?.initialized == true;
  bool isPreloading(String url) => _preloading.containsKey(url);

  // ── Preloading ───────────────────────────────────────────────────────────────

  /// Preload a single video in the background.
  /// Returns immediately if already cached; otherwise initialises the decoder.
  Future<VideoPlayerController?> preload(String url, {int feedIndex = -1}) async {
    if (url.isEmpty) return null;

    final existing = _pool[url];
    if (existing != null && existing.initialized) {
      existing.lastAccess = DateTime.now();
      metrics.cacheHits++;
      return existing.vpController;
    }

    // Deduplicate concurrent preload requests for the same URL
    if (_preloading.containsKey(url)) {
      return _preloading[url]!.future;
    }

    metrics.cacheMisses++;
    _evictIfNeeded(preferKeep: url);

    final completer = Completer<VideoPlayerController?>();
    _preloading[url] = completer;

    final sw = Stopwatch()..start();
    try {
      late _PoolEntry entry;
      late VideoPlayerController ctrl;

      // All URLs — including HLS — go through CachedVideoPlayerPlus.
      // HLS manifests now use absolute https:// segment URLs so the local
      // proxy can intercept and cache every .ts segment correctly.
      // Second play (and app-resume) is instant from the on-device cache.
      final player = CachedVideoPlayerPlus.networkUrl(
        Uri.parse(url),
        httpHeaders: const {
          'Connection': 'keep-alive',
          'Accept-Encoding': 'identity',
        },
        invalidateCacheIfOlderThan: const Duration(days: 7),
      );
      entry = _PoolEntry.cached(player, feedIndex: feedIndex);
      _pool[url] = entry;
      await player.initialize();
      ctrl = player.controller;
      ctrl.setLooping(true);
      ctrl.setVolume(0);
      // Seek to start to warm up decoder and buffer first segment
      await ctrl.seekTo(Duration.zero);

      entry.initialized = true;
      entry.lastAccess = DateTime.now();
      _retryCount.remove(url);

      sw.stop();
      metrics.recordStartup(sw.elapsedMilliseconds);
      metrics.preloadSuccesses++;
      debugPrint('[VideoEngine] Preloaded in ${sw.elapsedMilliseconds}ms: $url');

      completer.complete(ctrl);
      _preloading.remove(url);
      return ctrl;
    } catch (e) {
      sw.stop();
      _pool.remove(url);
      metrics.preloadFailures++;

      if (!completer.isCompleted) completer.complete(null);
      _preloading.remove(url);

      final retries = _retryCount[url] ?? 0;
      if (retries < 2) {
        _retryCount[url] = retries + 1;
        final delay = Duration(seconds: 2 + retries * 2);
        debugPrint('[VideoEngine] Retry ${retries + 1} for $url in ${delay.inSeconds}s');
        await Future.delayed(delay);
        return preload(url, feedIndex: feedIndex);
      }
      debugPrint('[VideoEngine] Gave up after retries: $url');
      return null;
    }
  }

  /// Predictive bulk preload centred on [currentIndex] in [urls].
  /// Preloads [_dynamicPreloadCount] ahead and [_maxPreloadBehind] behind,
  /// skipping already-cached or in-flight URLs.
  void preloadFromIndex(int currentIndex, List<String> urls) {
    final count = _dynamicPreloadCount;
    final candidates = <int, String>{};

    // Ahead (highest priority)
    for (var i = currentIndex + 1; i <= currentIndex + count && i < urls.length; i++) {
      candidates[i] = urls[i];
    }
    // Behind (lower priority — user might scroll back)
    for (var i = currentIndex - 1; i >= currentIndex - _maxPreloadBehind && i >= 0; i--) {
      candidates[i] = urls[i];
    }

    // Sort by distance so closest fires first
    final sorted = candidates.entries.toList()
      ..sort((a, b) => (a.key - currentIndex).abs().compareTo((b.key - currentIndex).abs()));

    for (final e in sorted) {
      final url = e.value;
      if (url.isNotEmpty && !isReady(url) && !isPreloading(url)) {
        preload(url, feedIndex: e.key);
      }
    }
  }

  /// Legacy shim — used by existing reels onPageChanged code.
  void preloadNext(List<String> urls) {
    final count = _dynamicPreloadCount;
    for (final url in urls.take(count)) {
      if (url.isNotEmpty && !isReady(url) && !isPreloading(url)) {
        preload(url);
      }
    }
  }

  // ── Activation ───────────────────────────────────────────────────────────────

  /// Make [url] the active (playing) video; pause and silence everything else.
  void activate(String url) {
    if (_activeUrl == url) {
      final entry = _pool[url];
      final ctrl = entry?.vpController;
      if (ctrl != null && entry!.initialized && !ctrl.value.isPlaying) {
        ctrl.setVolume(1);
        ctrl.play();
      }
      return;
    }
    _activeUrl = url;
    for (final e in _pool.entries) {
      final ctrl = e.value.vpController;
      if (ctrl == null) continue;
      if (e.key == url) {
        ctrl.setVolume(1);
        if (e.value.initialized && !ctrl.value.isPlaying) ctrl.play();
        e.value.lastAccess = DateTime.now();
      } else {
        if (ctrl.value.isPlaying) ctrl.pause();
        ctrl.setVolume(0);
      }
    }
  }

  void pause(String url) {
    _pool[url]?.vpController?.pause();
    if (_activeUrl == url) _activeUrl = null;
  }

  void pauseAll() {
    for (final e in _pool.values) {
      if (e.vpController?.value.isPlaying == true) e.vpController?.pause();
    }
    _activeUrl = null;
  }

  /// Called on app resume — seeks to current position first to force
  /// ExoPlayer/AVPlayer to re-buffer (avoids silent play() failure after
  /// the OS releases media resources in the background), then plays.
  /// Falls back to re-preloading if the controller was evicted from the pool.
  Future<void> reactivate(String url) async {
    if (url.isEmpty) return;

    final entry = _pool[url];
    if (entry == null || !entry.initialized) {
      // Controller was evicted — preload from scratch then play
      final ctrl = await preload(url);
      if (ctrl != null) activate(url);
      return;
    }

    final ctrl = entry.vpController;
    if (ctrl == null) return;

    _activeUrl = url;

    // Pause and silence all other controllers
    for (final e in _pool.entries) {
      if (e.key != url) {
        if (e.value.vpController?.value.isPlaying == true) {
          e.value.vpController?.pause();
        }
        e.value.vpController?.setVolume(0);
      }
    }

    // Play directly — segments are cached locally by CachedVideoPlayerPlus
    // so there is no network re-fetch on resume. seekTo would invalidate
    // the buffer unnecessarily.
    ctrl.setVolume(1);
    try {
      ctrl.play();
    } catch (_) {}
  }

  void release(String url) {
    if (_preloading.containsKey(url)) return;
    final entry = _pool.remove(url);
    if (entry != null) {
      entry.dispose();
      metrics.disposals++;
    }
    if (_activeUrl == url) _activeUrl = null;
  }

  // ── Eviction ─────────────────────────────────────────────────────────────────

  void _evictIfNeeded({String? preferKeep}) {
    while (_pool.length >= _maxControllers) {
      final candidate = _chooseEvictionCandidate(protect: preferKeep);
      if (candidate != null) {
        release(candidate);
      } else {
        break; // All remaining controllers are protected
      }
    }
  }

  /// Distance-based eviction: the controller farthest from the current
  /// playback position is evicted first. Never evicts the active video.
  String? _chooseEvictionCandidate({String? protect}) {
    String? best;
    int bestDist = -1;

    for (final e in _pool.entries) {
      if (e.key == _activeUrl) continue;
      if (e.key == protect) continue;
      if (e.value.vpController?.value.isPlaying == true) continue;

      final dist = _distanceFromCurrent(e.key);
      if (dist > bestDist) {
        bestDist = dist;
        best = e.key;
      }
    }
    return best;
  }

  int _distanceFromCurrent(String url) {
    if (_orderedUrls.isEmpty || _currentIndex < 0) {
      // No feed context — fall back to LRU distance via timestamp
      final entry = _pool[url];
      if (entry == null) return 999;
      return DateTime.now().difference(entry.lastAccess).inSeconds;
    }
    final idx = _orderedUrls.indexOf(url);
    return idx < 0 ? 999 : (idx - _currentIndex).abs();
  }

  /// Proactively release controllers that are too far from the current position
  /// to be needed in the near future.
  void _evictDistantControllers() {
    if (_orderedUrls.isEmpty || _currentIndex < 0) return;
    final safeRadius = _maxPreloadAhead + _maxPreloadBehind + 3;

    final toEvict = <String>[];
    for (final url in _pool.keys) {
      if (url == _activeUrl) continue;
      if (_distanceFromCurrent(url) > safeRadius) toEvict.add(url);
    }
    for (final url in toEvict) {
      release(url);
    }
  }

  // ── Lifecycle ─────────────────────────────────────────────────────────────────

  void disposeAll() {
    _velocityDecayTimer?.cancel();
    for (final e in _pool.values) {
      e.dispose();
    }
    _pool.clear();
    _preloading.clear();
    _retryCount.clear();
    _orderedUrls = [];
    _currentIndex = -1;
    _activeUrl = null;
  }

  // ── Debug ─────────────────────────────────────────────────────────────────────

  void logStatus() {
    debugPrint('[VideoEngine] pool=${_pool.length}/$_maxControllers '
        'active=$_activeUrl velocity=${_scrollVelocity.toStringAsFixed(0)}px/s');
    debugPrint(metrics.toString());
  }
}
