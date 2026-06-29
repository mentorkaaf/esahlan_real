import 'dart:async';
import 'dart:collection';
import 'package:flutter/foundation.dart';
import 'package:video_player/video_player.dart';

class VideoEngine {
  VideoEngine._();
  static final instance = VideoEngine._();

  static const _maxControllers = 4;
  final _controllers = LinkedHashMap<String, _PoolEntry>();
  final _preloading = <String, Future<void>>{};
  final _retryCount = <String, int>{};

  VideoPlayerController? getController(String url) {
    if (url.isEmpty) return null;
    final entry = _controllers[url];
    if (entry != null) {
      entry.lastAccess = DateTime.now();
      return entry.controller;
    }
    return null;
  }

  Future<VideoPlayerController?> preload(String url) async {
    if (url.isEmpty) return null;

    final existing = _controllers[url];
    if (existing != null && existing.initialized) {
      existing.lastAccess = DateTime.now();
      return existing.controller;
    }

    if (_preloading.containsKey(url)) {
      await _preloading[url];
      return _controllers[url]?.controller;
    }

    _evictIfNeeded();

    final completer = Completer<void>();
    _preloading[url] = completer.future;

    try {
      final ctrl = VideoPlayerController.networkUrl(
        Uri.parse(url),
        httpHeaders: const {'Connection': 'keep-alive', 'Accept-Encoding': 'identity'},
      );

      final entry = _PoolEntry(controller: ctrl);
      _controllers[url] = entry;

      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(0);
      entry.initialized = true;
      entry.lastAccess = DateTime.now();
      _retryCount.remove(url);

      completer.complete();
      _preloading.remove(url);
      return ctrl;
    } catch (e) {
      _controllers.remove(url);
      if (!completer.isCompleted) completer.complete();
      _preloading.remove(url);

      // Auto-retry up to 2 times with delay
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
      if (url.isNotEmpty && !_controllers.containsKey(url) && !_preloading.containsKey(url)) {
        preload(url);
      }
    }
  }

  void activate(String url) {
    for (final e in _controllers.entries) {
      if (e.key == url) {
        e.value.controller.setVolume(1);
        if (e.value.initialized && !e.value.controller.value.isPlaying) {
          e.value.controller.play();
        }
        e.value.lastAccess = DateTime.now();
      } else {
        if (e.value.controller.value.isPlaying) e.value.controller.pause();
        e.value.controller.setVolume(0);
      }
    }
  }

  void pause(String url) {
    _controllers[url]?.controller.pause();
  }

  void pauseAll() {
    for (final e in _controllers.values) {
      if (e.controller.value.isPlaying) e.controller.pause();
    }
  }

  void release(String url) {
    final entry = _controllers.remove(url);
    if (entry != null) {
      entry.controller.pause();
      entry.controller.dispose();
    }
  }

  void _evictIfNeeded() {
    while (_controllers.length >= _maxControllers) {
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
        release(_controllers.keys.first);
      }
    }
  }

  void disposeAll() {
    for (final e in _controllers.values) {
      e.controller.pause();
      e.controller.dispose();
    }
    _controllers.clear();
    _preloading.clear();
    _retryCount.clear();
  }
}

class _PoolEntry {
  final VideoPlayerController controller;
  bool initialized;
  DateTime lastAccess;
  _PoolEntry({required this.controller, this.initialized = false})
      : lastAccess = DateTime.now();
}
