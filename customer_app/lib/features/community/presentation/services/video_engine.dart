import 'dart:async';
import 'dart:collection';
import 'package:flutter/foundation.dart';
import 'package:video_player/video_player.dart';

/// TikTok-level video engine with controller pooling, predictive preloading,
/// and smart memory management.
class VideoEngine {
  VideoEngine._();
  static final instance = VideoEngine._();

  // ── Controller Pool ──────────────────────────────────────────────
  // Max controllers alive at once — keeps memory bounded
  static const _maxControllers = 5;
  final _controllers = LinkedHashMap<String, _PoolEntry>();
  final _preloading = <String, Future<void>>{};

  /// Get or create a controller for [url]. Returns immediately if cached.
  /// The controller may or may not be initialized yet — check `.value.isInitialized`.
  VideoPlayerController? getController(String url) {
    if (url.isEmpty) return null;
    final entry = _controllers[url];
    if (entry != null) {
      entry.lastAccess = DateTime.now();
      return entry.controller;
    }
    return null;
  }

  /// Preload a video: create controller + initialize + buffer first segments.
  /// Returns the ready controller. Safe to call multiple times (deduped).
  Future<VideoPlayerController?> preload(String url, {Map<String, String>? headers}) async {
    if (url.isEmpty) return null;

    // Already cached and initialized
    final existing = _controllers[url];
    if (existing != null && existing.initialized) {
      existing.lastAccess = DateTime.now();
      return existing.controller;
    }

    // Already preloading — wait for it
    if (_preloading.containsKey(url)) {
      await _preloading[url];
      return _controllers[url]?.controller;
    }

    // Evict if at capacity
    _evictIfNeeded();

    final completer = Completer<void>();
    _preloading[url] = completer.future;

    try {
      final ctrl = VideoPlayerController.networkUrl(
        Uri.parse(url),
        httpHeaders: headers ?? const {'Connection': 'keep-alive'},
      );

      final entry = _PoolEntry(controller: ctrl);
      _controllers[url] = entry;

      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(0); // silent until user sees it
      entry.initialized = true;
      entry.lastAccess = DateTime.now();

      completer.complete();
      _preloading.remove(url);
      return ctrl;
    } catch (e) {
      _controllers.remove(url);
      if (!completer.isCompleted) completer.complete();
      _preloading.remove(url);
      debugPrint('[VideoEngine] Preload failed for $url: $e');
      return null;
    }
  }

  /// Preload multiple URLs in priority order (current, next, next+1, next+2).
  Future<void> preloadBatch(List<String> urls) async {
    for (final url in urls) {
      if (url.isNotEmpty && !_controllers.containsKey(url)) {
        // Don't await — fire and forget in order
        preload(url);
      }
    }
  }

  /// Mark a controller as "active" — unmute + play. Pauses all others.
  void activate(String url) {
    for (final e in _controllers.entries) {
      if (e.key == url) {
        e.value.controller.setVolume(1);
        if (e.value.initialized && !e.value.controller.value.isPlaying) {
          e.value.controller.play();
        }
        e.value.lastAccess = DateTime.now();
      } else {
        if (e.value.controller.value.isPlaying) {
          e.value.controller.pause();
        }
        e.value.controller.setVolume(0);
      }
    }
  }

  /// Pause a specific controller.
  void pause(String url) {
    _controllers[url]?.controller.pause();
  }

  /// Pause all controllers.
  void pauseAll() {
    for (final e in _controllers.values) {
      if (e.controller.value.isPlaying) e.controller.pause();
    }
  }

  /// Release a specific controller.
  void release(String url) {
    final entry = _controllers.remove(url);
    if (entry != null) {
      entry.controller.pause();
      entry.controller.dispose();
    }
  }

  /// Evict the oldest controller if we're at capacity.
  void _evictIfNeeded() {
    while (_controllers.length >= _maxControllers) {
      // Find the least recently accessed that isn't currently playing
      String? evictKey;
      DateTime? oldest;
      for (final e in _controllers.entries) {
        if (!e.value.controller.value.isPlaying) {
          if (oldest == null || e.value.lastAccess.isBefore(oldest)) {
            oldest = e.value.lastAccess;
            evictKey = e.key;
          }
        }
      }
      if (evictKey != null) {
        release(evictKey);
      } else {
        // All playing (shouldn't happen) — evict the first
        final key = _controllers.keys.first;
        release(key);
      }
    }
  }

  /// Clean up everything.
  void disposeAll() {
    for (final e in _controllers.values) {
      e.controller.pause();
      e.controller.dispose();
    }
    _controllers.clear();
    _preloading.clear();
  }

  /// Stats for debugging.
  int get activeCount => _controllers.length;
  int get playingCount => _controllers.values.where((e) => e.controller.value.isPlaying).length;
}

class _PoolEntry {
  final VideoPlayerController controller;
  bool initialized;
  DateTime lastAccess;

  _PoolEntry({required this.controller, this.initialized = false})
      : lastAccess = DateTime.now();
}
