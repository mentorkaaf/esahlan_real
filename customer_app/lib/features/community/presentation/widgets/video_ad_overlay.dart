import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import '../screens/community_shell.dart';

enum AdTiming { preroll, midroll, postroll }

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
  int _skipCountdown = 5;
  Map<String, dynamic>? _adData;
  AdTiming _timing = AdTiming.preroll;
  bool _adShown = false;
  bool _midrollTriggered = false;

  @override
  void initState() {
    super.initState();
    _timing = AdTiming.values[Random().nextInt(3)];
    widget.mainController.addListener(_onMainProgress);
    if (_timing == AdTiming.preroll) _loadAd();
  }

  void _onMainProgress() {
    if (_adShown || !mounted) return;
    final pos = widget.mainController.value.position;
    final dur = widget.mainController.value.duration;
    if (dur <= Duration.zero) return;

    final progress = pos.inMilliseconds / dur.inMilliseconds;

    if (_timing == AdTiming.midroll && !_midrollTriggered && progress > 0.4 && progress < 0.6) {
      _midrollTriggered = true;
      _loadAd();
    }
    if (_timing == AdTiming.postroll && progress > 0.85) {
      _loadAd();
    }
  }

  Future<void> _loadAd() async {
    if (_adShown) return;
    try {
      final ad = await ref.read(communityRepoProvider).getPrerollAd();
      if (ad == null || ad['media_url'] == null) return;

      widget.mainController.pause();
      setState(() { _adData = ad; _showingAd = true; _adShown = true; });

      final ctrl = VideoPlayerController.networkUrl(Uri.parse(ad['media_url']));
      await ctrl.initialize();
      ctrl.setLooping(false);
      ctrl.setVolume(1);
      if (!mounted) { ctrl.dispose(); return; }
      setState(() { _adCtrl = ctrl; _adReady = true; });
      ctrl.play();
      ctrl.addListener(_onAdEnd);
      _startSkipTimer();
    } catch (_) {
      setState(() => _showingAd = false);
      widget.mainController.play();
    }
  }

  void _onAdEnd() {
    if (_adCtrl == null) return;
    if (_adCtrl!.value.position >= _adCtrl!.value.duration && _adCtrl!.value.duration > Duration.zero) {
      _skipAd();
    }
    if (mounted) setState(() {});
  }

  void _startSkipTimer() async {
    for (var i = 5; i > 0; i--) {
      await Future.delayed(const Duration(seconds: 1));
      if (!mounted || !_showingAd) return;
      setState(() => _skipCountdown = i - 1);
    }
    if (mounted) setState(() => _canSkip = true);
  }

  void _skipAd() {
    _adCtrl?.removeListener(_onAdEnd);
    _adCtrl?.pause();
    _adCtrl?.dispose();
    _adCtrl = null;
    setState(() { _showingAd = false; _adReady = false; });
    widget.mainController.play();
  }

  @override
  void dispose() {
    widget.mainController.removeListener(_onMainProgress);
    _adCtrl?.removeListener(_onAdEnd);
    _adCtrl?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(children: [
      widget.child,
      if (_showingAd) Positioned.fill(child: Container(
        color: Colors.black,
        child: Stack(children: [
          // Ad video
          Center(child: _adReady && _adCtrl != null
              ? AspectRatio(aspectRatio: _adCtrl!.value.aspectRatio.clamp(0.5, 2.5), child: VideoPlayer(_adCtrl!))
              : const CircularProgressIndicator(color: kOrange)),

          // Progress bar
          if (_adReady && _adCtrl != null) Positioned(top: 0, left: 0, right: 0,
            child: VideoProgressIndicator(_adCtrl!, allowScrubbing: false,
              colors: const VideoProgressColors(playedColor: kOrange, backgroundColor: Colors.white24))),

          // "Ad" badge
          Positioned(top: 8, left: 8, child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(4)),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.campaign_rounded, color: kOrange, size: 12),
              const SizedBox(width: 4),
              Text(_adData?['page']?['name'] ?? 'Ad', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
            ]))),

          // Skip button
          Positioned(bottom: 12, right: 12, child: GestureDetector(
            onTap: _canSkip ? _skipAd : null,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: _canSkip ? Colors.white : Colors.white.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(6)),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Text(_canSkip ? 'Skip Ad' : 'Skip in $_skipCountdown',
                  style: TextStyle(color: _canSkip ? Colors.black : Colors.white70, fontWeight: FontWeight.w700, fontSize: 13)),
                if (_canSkip) const Icon(Icons.skip_next_rounded, size: 16, color: Colors.black),
              ])),
          )),

          // CTA
          if (_adData?['cta_text'] != null) Positioned(bottom: 12, left: 12,
            child: GestureDetector(
              onTap: () { if (_adData?['id'] != null) ref.read(communityRepoProvider).trackAdClick(_adData!['id']); },
              child: Container(padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(6)),
                child: Text(_adData!['cta_text'], style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13))))),
        ]),
      )),
    ]);
  }
}
