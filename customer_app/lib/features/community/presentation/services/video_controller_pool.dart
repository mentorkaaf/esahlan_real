import 'package:video_player/video_player.dart';

class VideoControllerPool {
  static final VideoControllerPool _instance = VideoControllerPool._();
  factory VideoControllerPool() => _instance;
  VideoControllerPool._();

  final Map<String, _PoolEntry> _pool = {};
  static const _maxControllers = 6;

  Future<VideoPlayerController> acquire(String url) async {
    // Return existing if already loaded
    if (_pool.containsKey(url)) {
      _pool[url]!.lastUsed = DateTime.now();
      return _pool[url]!.controller;
    }

    // Evict oldest if at capacity
    while (_pool.length >= _maxControllers) {
      _evictOldest(exclude: url);
    }

    // Create new
    final ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
    await ctrl.initialize();
    ctrl.setLooping(true);
    ctrl.setVolume(1);
    _pool[url] = _PoolEntry(controller: ctrl);
    return ctrl;
  }

  void warmup(String url) {
    if (_pool.containsKey(url) || _pool.length >= _maxControllers) return;
    final ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
    ctrl.initialize().then((_) {
      ctrl.setLooping(true);
      ctrl.setVolume(1);
      ctrl.pause();
      _pool[url] = _PoolEntry(controller: ctrl);
    }).catchError((_) { ctrl.dispose(); });
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

  VideoPlayerController? peek(String url) => _pool[url]?.controller;

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
  }
}

class _PoolEntry {
  final VideoPlayerController controller;
  DateTime lastUsed;
  _PoolEntry({required this.controller}) : lastUsed = DateTime.now();
}
