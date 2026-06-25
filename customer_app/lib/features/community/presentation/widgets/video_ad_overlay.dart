import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import '../services/video_controller_pool.dart';
import '../screens/community_shell.dart';

class VideoAdOverlay extends ConsumerStatefulWidget {
  final VideoPlayerController mainController;
  final Widget child;
  const VideoAdOverlay({super.key, required this.mainController, required this.child});
  @override
  ConsumerState<VideoAdOverlay> createState() => _VideoAdOverlayState();
}

class _VideoAdOverlayState extends ConsumerState<VideoAdOverlay> {
  VideoPlayerController? _adCtrl;
  bool _showingAd = false;
  bool _adReady = false;
  bool _canSkip = false;
  int _countdown = 10;
  Map<String, dynamic>? _ad;

  // Multiple trigger points for long videos
  final List<double> _triggerPoints = [];
  int _nextTriggerIndex = 0;
  bool _fetchingAd = false;

  Map<String, dynamic>? _preloadedAd;

  @override
  void initState() {
    super.initState();
    widget.mainController.addListener(_onProgress);
    WidgetsBinding.instance.addPostFrameCallback((_) => _generateTriggerPoints());
    // Pre-fetch ad data + warm up video
    _prefetchAd();
  }

  void _prefetchAd() async {
    try {
      final ad = await ref.read(communityRepoProvider).getPrerollAd();
      if (ad != null && ad['media_url'] != null) {
        _preloadedAd = ad;
        VideoControllerPool().warmup(ad['media_url']);
      }
    } catch (_) {}
  }

  void _generateTriggerPoints() {
    final dur = widget.mainController.value.duration;
    if (dur <= Duration.zero) {
      // Duration not ready yet, try again
      Future.delayed(const Duration(seconds: 1), () { if (mounted) _generateTriggerPoints(); });
      return;
    }
    final totalSecs = dur.inSeconds;

    if (totalSecs < 30) {
      // Short video: 1 ad at random point
      _triggerPoints.add(0.4 + Random().nextDouble() * 0.3);
    } else if (totalSecs < 120) {
      // 30s-2min: 2 ads
      _triggerPoints.addAll([0.3, 0.7]);
    } else if (totalSecs < 300) {
      // 2-5min: 3 ads
      _triggerPoints.addAll([0.2, 0.5, 0.8]);
    } else {
      // 5min+: 1 ad every ~90 seconds
      final count = (totalSecs / 90).floor().clamp(2, 6);
      for (var i = 1; i <= count; i++) {
        _triggerPoints.add(i / (count + 1));
      }
    }
  }

  void _onProgress() {
    if (_showingAd || _fetchingAd || !mounted) return;
    if (!widget.mainController.value.isPlaying) return;
    if (_nextTriggerIndex >= _triggerPoints.length) return;

    final dur = widget.mainController.value.duration;
    if (dur <= Duration.zero) return;
    final progress = widget.mainController.value.position.inMilliseconds / dur.inMilliseconds;
    final playedSecs = widget.mainController.value.position.inSeconds;
    if (playedSecs < 5) return;

    if (progress >= _triggerPoints[_nextTriggerIndex]) {
      _nextTriggerIndex++;
      _fetchAndShowAd();
    }
  }

  Future<void> _fetchAndShowAd() async {
    _fetchingAd = true;
    try {
      // Use preloaded ad if available, else fetch fresh
      final ad = _preloadedAd ?? await ref.read(communityRepoProvider).getPrerollAd();
      _preloadedAd = null;
      if (ad == null || ad['media_url'] == null || !mounted) { _fetchingAd = false; return; }

      widget.mainController.pause();
      setState(() { _ad = ad; _showingAd = true; _canSkip = false; _countdown = 10; });

      // Try pool first (may be pre-warmed)
      final pool = VideoControllerPool();
      VideoPlayerController ctrl;
      final pooled = pool.peek(ad['media_url']);
      if (pooled != null && pooled.value.isInitialized) {
        ctrl = pooled;
        pool.release(ad['media_url']);
      } else {
        ctrl = VideoPlayerController.networkUrl(Uri.parse(ad['media_url']));
        await ctrl.initialize();
      }
      ctrl.setLooping(false);
      ctrl.setVolume(1);
      if (!mounted) { ctrl.dispose(); return; }
      setState(() { _adCtrl = ctrl; _adReady = true; });
      ctrl.play();
      ctrl.addListener(_onAdEnd);
      _runCountdown();

      // Pre-fetch next ad for later
      _prefetchAd();
    } catch (_) {
      _fetchingAd = false;
      _resumeMain();
    }
  }

  void _onAdEnd() {
    if (_adCtrl == null) return;
    if (_adCtrl!.value.position >= _adCtrl!.value.duration && _adCtrl!.value.duration > Duration.zero) {
      _dismiss();
    }
  }

  void _runCountdown() async {
    for (var i = 10; i > 0; i--) {
      await Future.delayed(const Duration(seconds: 1));
      if (!mounted || !_showingAd) return;
      setState(() => _countdown = i - 1);
    }
    if (mounted) setState(() => _canSkip = true);
  }

  void _dismiss() {
    _adCtrl?.removeListener(_onAdEnd);
    _adCtrl?.pause();
    _adCtrl?.dispose();
    _adCtrl = null;
    _fetchingAd = false;
    setState(() { _showingAd = false; _adReady = false; });
    _resumeMain();
  }

  void _resumeMain() {
    if (widget.mainController.value.isInitialized) widget.mainController.play();
  }

  @override
  void dispose() {
    widget.mainController.removeListener(_onProgress);
    _adCtrl?.removeListener(_onAdEnd);
    _adCtrl?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(children: [
      widget.child,
      if (_showingAd && _ad != null) Positioned.fill(child: Container(
        color: Colors.black,
        child: Stack(children: [
          if (_adReady && _adCtrl != null)
            Center(child: AspectRatio(
              aspectRatio: _adCtrl!.value.aspectRatio.clamp(0.5, 2.5),
              child: VideoPlayer(_adCtrl!)))
          else
            const Center(child: SizedBox(width: 24, height: 24,
              child: CircularProgressIndicator(color: kOrange, strokeWidth: 2))),

          Positioned(top: MediaQuery.of(context).padding.top + 8, left: 12,
            child: Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(4)),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Container(padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                  decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(3)),
                  child: const Text('AD', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w900))),
                const SizedBox(width: 6),
                Text(_ad!['page']?['name'] ?? '', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
              ]))),

          Positioned(bottom: 16, right: 12,
            child: GestureDetector(
              onTap: _canSkip ? _dismiss : null,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(
                  color: _canSkip ? Colors.white : Colors.white24,
                  borderRadius: BorderRadius.circular(6)),
                child: Text(
                  _canSkip ? 'Skip Ad' : '$_countdown',
                  style: TextStyle(
                    color: _canSkip ? Colors.black : Colors.white70,
                    fontWeight: FontWeight.w700, fontSize: 14))))),

          if (_ad!['cta_text'] != null) Positioned(bottom: 16, left: 12,
            child: GestureDetector(
              onTap: () { if (_ad?['id'] != null) ref.read(communityRepoProvider).trackAdClick(_ad!['id']); },
              child: Container(padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(6)),
                child: Text(_ad!['cta_text'], style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14))))),
        ]),
      )),
    ]);
  }
}
