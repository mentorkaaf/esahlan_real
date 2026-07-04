import 'package:video_player/video_player.dart';

/// Preloads ad video controllers by URL as soon as feed/reel data arrives.
/// AdCard and ReelAdCard call [take] to get a pre-initialized controller
/// instead of waiting for their own initState download.
class AdPreloader {
  AdPreloader._();
  static final instance = AdPreloader._();

  final _ready   = <String, VideoPlayerController>{};
  final _loading = <String, bool>{};

  /// Fire-and-forget: begins downloading [urls] in parallel.
  void preload(List<String> urls) {
    for (final url in urls) {
      if (url.isEmpty || _ready.containsKey(url) || _loading[url] == true) continue;
      _loading[url] = true;
      _initOne(url);
    }
  }

  Future<void> _initOne(String url) async {
    try {
      final ctrl = VideoPlayerController.networkUrl(
        Uri.parse(url),
        httpHeaders: const {'Connection': 'keep-alive'},
      );
      await ctrl.initialize();
      ctrl.setLooping(true);
      ctrl.setVolume(0);
      _ready[url] = ctrl;
    } catch (_) {
      // falls through — card will init its own on mount
    } finally {
      _loading.remove(url);
    }
  }

  /// Returns a ready controller for [url] and removes it from the cache.
  /// Returns null if not ready yet (caller should init its own).
  VideoPlayerController? take(String url) => _ready.remove(url);

  /// True while the controller for [url] is still downloading.
  bool isLoading(String url) => _loading[url] == true;

  void dispose() {
    for (final ctrl in _ready.values) {
      ctrl.dispose();
    }
    _ready.clear();
    _loading.clear();
  }
}
