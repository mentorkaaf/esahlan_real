import 'package:video_player/video_player.dart';

class VideoControllerPool {
  static final VideoControllerPool _instance = VideoControllerPool._();
  factory VideoControllerPool() => _instance;
  VideoControllerPool._();

  final Map<String, _PoolEntry> _pool = {};
  final Set<String> _initializing = {};
  static const _maxControllers = 5;

  Future<VideoPlayerController> acquire(String url) async {
    // Return existing if initialized
    if (_pool.containsKey(url) && _pool[url]!.controller.value.isInitialized) {
      _pool[url]!.lastUsed = DateTime.now();
      return _pool[url]!.controller;
    }

    // If already initializing, wait for it
    if (_initializing.contains(url)) {
      for (var i = 0; i < 30; i++) {
        await Future.delayed(const Duration(milliseconds: 200));
        if (_pool.containsKey(url) && _pool[url]!.controller.value.isInitialized) {
          _pool[url]!.lastUsed = DateTime.now();
          return _pool[url]!.controller;
        }
      }
    }

    // Evict oldest if at capacity
    while (_pool.length >= _maxControllers) {
      _evictOldest(exclude: url);
    }

    // Create and init
    _initializing.add(url);
    final ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
    try {
      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(1);
      _pool[url] = _PoolEntry(controller: ctrl);
      _initializing.remove(url);
      return ctrl;
    } catch (e) {
      _initializing.remove(url);
      ctrl.dispose();
      rethrow;
    }
  }

  void warmup(String url) {
    if (_pool.containsKey(url) || _initializing.contains(url) || _pool.length >= _maxControllers) return;
    _initializing.add(url);
    final ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
    ctrl.initialize().then((_) {
      ctrl.setLooping(true);
      ctrl.setVolume(1);
      ctrl.pause();
      _pool[url] = _PoolEntry(controller: ctrl);
      _initializing.remove(url);
    }).catchError((_) {
      ctrl.dispose();
      _initializing.remove(url);
    });
  }

  void release(String url) {
    final entry = _pool.remove(url);
    if (entry != null) {
      entry.controller.pause();
      entry.controller.dispose();
    }
  }

  void pauseAll() {
    for (final e in _pool.values) {
      if (e.controller.value.isPlaying) e.controller.pause();
    }
  }

  VideoPlayerController? peek(String url) {
    final entry = _pool[url];
    if (entry != null && entry.controller.value.isInitialized) return entry.controller;
    return null;
  }

  void _evictOldest({String? exclude}) {
    if (_pool.isEmpty) return;
    String? oldest;
    DateTime? oldestTime;
    for (final e in _pool.entries) {
      if (e.key == exclude) continue;
      if (oldestTime == null || e.value.lastUsed.isBefore(oldestTime)) {
        oldest = e.key;
        oldestTime = e.value.lastUsed;
      }
    }
    if (oldest != null) release(oldest);
  }

  void disposeAll() {
    for (final e in _pool.values) {
      e.controller.pause();
      e.controller.dispose();
    }
    _pool.clear();
    _initializing.clear();
  }
}

class _PoolEntry {
  final VideoPlayerController controller;
  DateTime lastUsed;
  _PoolEntry({required this.controller}) : lastUsed = DateTime.now();
}
