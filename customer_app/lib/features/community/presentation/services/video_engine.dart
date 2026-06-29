import 'dart:async';
import 'dart:collection';
import 'package:flutter/foundation.dart';
import 'package:video_player/video_player.dart';
import 'package:cached_video_player_plus/cached_video_player_plus.dart';

class VideoEngine {
  VideoEngine._();
  static final instance = VideoEngine._();

  static const _maxControllers = 4;
  final _pool = LinkedHashMap<String, _PoolEntry>();
  final _preloading = <String, Future<void>>{};
  final _retryCount = <String, int>{};

  VideoPlayerController? getController(String url) {
    final entry = _pool[url];
    if (entry != null) {
      entry.lastAccess = DateTime.now();
      return entry.vpController;
    }
    return null;
  }

  Future<VideoPlayerController?> preload(String url) async {
    if (url.isEmpty) return null;

    final existing = _pool[url];
    if (existing != null && existing.initialized) {
      existing.lastAccess = DateTime.now();
      return existing.vpController;
    }

    if (_preloading.containsKey(url)) {
      await _preloading[url];
      return _pool[url]?.vpController;
    }

    _evictIfNeeded();

    final completer = Completer<void>();
    _preloading[url] = completer.future;

    try {
      final player = CachedVideoPlayerPlus.networkUrl(
        Uri.parse(url),
        httpHeaders: const {'Connection': 'keep-alive', 'Accept-Encoding': 'identity'},
        invalidateCacheIfOlderThan: const Duration(days: 7),
      );

      final entry = _PoolEntry(player: player);
      _pool[url] = entry;

      await player.initialize();
      final ctrl = player.controller;
      ctrl.setLooping(true);
      ctrl.setVolume(0);
      entry.initialized = true;
      entry.lastAccess = DateTime.now();
      _retryCount.remove(url);

      completer.complete();
      _preloading.remove(url);
      return ctrl;
    } catch (e) {
      _pool.remove(url);
      if (!completer.isCompleted) completer.complete();
      _preloading.remove(url);

      final retries = _retryCount[url] ?? 0;
      if (retries < 2) {
        _retryCount[url] = retries + 1;
        await Future.delayed(Duration(seconds: 2 + retries * 2));
        return preload(url);
      }
      debugPrint('[VideoEngine] Failed after retries: $url');
      return null;
    }
  }

  void preloadNext(List<String> urls) {
    for (final url in urls.take(2)) {
      if (url.isNotEmpty && !_pool.containsKey(url) && !_preloading.containsKey(url)) {
        preload(url);
      }
    }
  }

  void activate(String url) {
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
  }

  void pauseAll() {
    for (final e in _pool.values) {
      if (e.vpController?.value.isPlaying == true) e.vpController?.pause();
    }
  }

  void release(String url) {
    final entry = _pool.remove(url);
    if (entry != null) {
      entry.vpController?.pause();
      entry.player.dispose();
    }
  }

  void _evictIfNeeded() {
    while (_pool.length >= _maxControllers) {
      String? evictKey;
      DateTime? oldest;
      for (final e in _pool.entries) {
        if (e.value.vpController?.value.isPlaying != true) {
          if (oldest == null || e.value.lastAccess.isBefore(oldest)) {
            oldest = e.value.lastAccess;
            evictKey = e.key;
          }
        }
      }
      if (evictKey != null) release(evictKey);
      else release(_pool.keys.first);
    }
  }

  void disposeAll() {
    for (final e in _pool.values) {
      e.vpController?.pause();
      e.player.dispose();
    }
    _pool.clear();
    _preloading.clear();
    _retryCount.clear();
  }
}

class _PoolEntry {
  final CachedVideoPlayerPlus player;
  bool initialized;
  DateTime lastAccess;

  _PoolEntry({required this.player, this.initialized = false})
      : lastAccess = DateTime.now();

  VideoPlayerController? get vpController => initialized ? player.controller : null;
}
