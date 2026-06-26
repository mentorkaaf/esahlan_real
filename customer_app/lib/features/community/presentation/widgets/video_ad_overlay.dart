import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
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
  bool _adTriggered = false;
  double _triggerPoint = 0;

  // Pre-loaded state
  bool _preloadStarted = false;
  bool _preloadDone = false;

  @override
  void initState() {
    super.initState();
    _triggerPoint = [0.3, 0.5, 0.7][Random().nextInt(3)];
    widget.mainController.addListener(_onProgress);
    // Start preloading ad immediately (silent, in background)
    _preloadAd();
  }

  /// Fetch ad data + initialize video controller BEFORE trigger point
  void _preloadAd() async {
    if (_preloadStarted) return;
    _preloadStarted = true;
    try {
      final ad = await ref.read(communityRepoProvider).getPrerollAd();
      if (ad == null || ad['media_url'] == null || !mounted) return;
      _ad = ad;

      final ctrl = VideoPlayerController.networkUrl(Uri.parse(ad['media_url']));
      await ctrl.initialize();
      ctrl.setLooping(false);
      ctrl.setVolume(1);
      ctrl.pause(); // Keep paused until trigger
      if (!mounted) { ctrl.dispose(); return; }
      _adCtrl = ctrl;
      _preloadDone = true;
    } catch (_) {}
  }

  void _onProgress() {
    if (_adTriggered || !mounted) return;
    if (!widget.mainController.value.isPlaying) return;
    final dur = widget.mainController.value.duration;
    if (dur <= Duration.zero) return;
    final progress = widget.mainController.value.position.inMilliseconds / dur.inMilliseconds;
    if (widget.mainController.value.position.inSeconds < 1) return;
    if (progress >= _triggerPoint) _showAd();
  }

  void _showAd() {
    if (_adTriggered) return;
    _adTriggered = true;

    // Only show if preloaded and ready
    if (!_preloadDone || _adCtrl == null || _ad == null) return;

    widget.mainController.pause();
    _adCtrl!.play();
    _adCtrl!.addListener(_onAdEnd);
    setState(() { _showingAd = true; _adReady = true; });
    _runCountdown();
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
    setState(() { _showingAd = false; _adReady = false; });
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
      if (_showingAd && _adReady && _adCtrl != null) Positioned.fill(child: Container(
        color: Colors.black,
        child: Stack(children: [
          Center(child: AspectRatio(
            aspectRatio: _adCtrl!.value.aspectRatio.clamp(0.5, 2.5),
            child: VideoPlayer(_adCtrl!))),

          Positioned(top: MediaQuery.of(context).padding.top + 8, left: 12,
            child: Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(4)),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Container(padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                  decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(3)),
                  child: const Text('AD', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w900))),
                const SizedBox(width: 6),
                Text(_ad?['page']?['name'] ?? '', style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
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

          if (_ad?['cta_text'] != null) Positioned(bottom: 16, left: 12,
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
