import 'package:video_player/video_player.dart';

class VideoPreloader {
  static final VideoPreloader _instance = VideoPreloader._();
  factory VideoPreloader() => _instance;
  VideoPreloader._();

  final Map<String, VideoPlayerController> _cache = {};
  final Set<String> _loading = {};
  static const _maxCached = 6;

  /// Preload a list of video URLs silently in background
  void preloadUrls(List<String> urls) {
    for (final url in urls) {
      if (_cache.length >= _maxCached) break;
      _preload(url);
    }
  }

  Future<void> _preload(String url) async {
    if (_cache.containsKey(url) || _loading.contains(url)) return;
    _loading.add(url);
    try {
      final ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(1);
      ctrl.pause();
      _cache[url] = ctrl;
    } catch (_) {}
    _loading.remove(url);
  }

  /// Get preloaded controller if available, otherwise null
  VideoPlayerController? get(String url) {
    final ctrl = _cache.remove(url);
    return ctrl;
  }

  /// Check if a URL is preloaded
  bool has(String url) => _cache.containsKey(url);

  void disposeAll() {
    for (final ctrl in _cache.values) {
      ctrl.pause();
      ctrl.dispose();
    }
    _cache.clear();
    _loading.clear();
  }
}
