import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';
import '../../data/repositories/community_repository.dart';

class PreloadedAd {
  final Map<String, dynamic> data;
  final VideoPlayerController? controller;
  final bool isVideo;
  bool used = false;

  PreloadedAd({required this.data, this.controller, required this.isVideo});

  void dispose() {
    controller?.pause();
    controller?.dispose();
  }
}

class AdPreloader {
  static final AdPreloader _instance = AdPreloader._();
  factory AdPreloader() => _instance;
  AdPreloader._();

  final _repo = CommunityRepository();
  final List<PreloadedAd> _feedAds = [];
  PreloadedAd? _overlayAd;
  bool _loading = false;
  bool _initialized = false;

  bool get hasPreloadedFeedAd => _feedAds.any((a) => !a.used);
  bool get hasPreloadedOverlayAd => _overlayAd != null && !_overlayAd!.used;

  Future<void> preload() async {
    if (_loading || _initialized) return;
    _loading = true;
    try {
      // Preload 2 ads for feed + 1 for overlay
      await Future.wait([_preloadForFeed(), _preloadForOverlay()]);
      _initialized = true;
    } catch (_) {} finally {
      _loading = false;
    }
  }

  Future<void> _preloadForFeed() async {
    try {
      final ad = await _repo.getPrerollAd();
      if (ad == null || ad['media_url'] == null) return;

      if (ad['ad_type'] == 'video') {
        final ctrl = VideoPlayerController.networkUrl(Uri.parse(ad['media_url']));
        await ctrl.initialize();
        ctrl.setLooping(true);
        ctrl.setVolume(1);
        ctrl.pause();
        _feedAds.add(PreloadedAd(data: ad, controller: ctrl, isVideo: true));
      } else {
        _feedAds.add(PreloadedAd(data: ad, controller: null, isVideo: false));
      }
    } catch (_) {}
  }

  Future<void> _preloadForOverlay() async {
    try {
      final ad = await _repo.getPrerollAd();
      if (ad == null || ad['media_url'] == null) return;

      final ctrl = VideoPlayerController.networkUrl(Uri.parse(ad['media_url']));
      await ctrl.initialize();
      ctrl.setLooping(false);
      ctrl.setVolume(1);
      ctrl.pause();
      _overlayAd = PreloadedAd(data: ad, controller: ctrl, isVideo: true);
    } catch (_) {}
  }

  PreloadedAd? getNextFeedAd() {
    final ad = _feedAds.where((a) => !a.used).firstOrNull;
    if (ad != null) {
      ad.used = true;
      // Start preloading replacement in background
      _preloadForFeed();
    }
    return ad;
  }

  PreloadedAd? getOverlayAd() {
    final ad = _overlayAd;
    if (ad != null && !ad.used) {
      ad.used = true;
      _overlayAd = null;
      // Preload next overlay in background
      _preloadForOverlay();
      return ad;
    }
    return null;
  }

  void disposeAll() {
    for (final ad in _feedAds) ad.dispose();
    _feedAds.clear();
    _overlayAd?.dispose();
    _overlayAd = null;
    _initialized = false;
    _loading = false;
  }
}

final adPreloaderProvider = Provider<AdPreloader>((_) => AdPreloader());
