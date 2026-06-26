import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';
import 'package:url_launcher/url_launcher.dart';
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

class _VideoAdOverlayState extends ConsumerState<VideoAdOverlay> with SingleTickerProviderStateMixin {
  VideoPlayerController? _adCtrl;
  bool _showingAd = false;
  bool _adReady = false;
  bool _canSkip = false;
  int _countdown = 10;
  Map<String, dynamic>? _ad;
  bool _adTriggered = false;
  double _triggerPoint = 0;
  bool _preloadStarted = false;
  bool _preloadDone = false;
  late AnimationController _progressAnim;

  @override
  void initState() {
    super.initState();
    _triggerPoint = [0.3, 0.5, 0.7][Random().nextInt(3)];
    _progressAnim = AnimationController(vsync: this, duration: const Duration(seconds: 10));
    widget.mainController.addListener(_onProgress);
    _preloadAd();
  }

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
      ctrl.pause();
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

  void _showAd() async {
    if (_adTriggered) return;
    _adTriggered = true;

    // If preload didn't finish, try loading now
    if (!_preloadDone || _adCtrl == null || _ad == null) {
      try {
        final ad = await ref.read(communityRepoProvider).getPrerollAd();
        if (ad == null || ad['media_url'] == null || !mounted) return;
        _ad = ad;
        final ctrl = VideoPlayerController.networkUrl(Uri.parse(ad['media_url']));
        await ctrl.initialize();
        ctrl.setLooping(false); ctrl.setVolume(1); ctrl.pause();
        if (!mounted) { ctrl.dispose(); return; }
        _adCtrl = ctrl;
      } catch (_) { return; }
    }

    widget.mainController.pause();
    _adCtrl!.play();
    _adCtrl!.addListener(_onAdProgress);
    _progressAnim.forward();
    setState(() { _showingAd = true; _adReady = true; });
    _runCountdown();
  }

  void _onAdProgress() {
    if (_adCtrl == null) return;
    if (_adCtrl!.value.position >= _adCtrl!.value.duration && _adCtrl!.value.duration > Duration.zero) {
      _dismiss();
    }
    if (mounted) setState(() {});
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
    _adCtrl?.removeListener(_onAdProgress);
    _adCtrl?.pause();
    _adCtrl?.dispose();
    _adCtrl = null;
    _progressAnim.reset();
    setState(() { _showingAd = false; _adReady = false; });
    if (widget.mainController.value.isInitialized) widget.mainController.play();
  }

  @override
  void dispose() {
    widget.mainController.removeListener(_onProgress);
    _adCtrl?.removeListener(_onAdProgress);
    _adCtrl?.dispose();
    _progressAnim.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(children: [
      widget.child,
      if (_showingAd && _adReady && _adCtrl != null) Positioned.fill(child: Container(
        color: Colors.black,
        child: Stack(children: [
          // Video
          Center(child: AspectRatio(
            aspectRatio: _adCtrl!.value.aspectRatio.clamp(0.5, 2.5),
            child: VideoPlayer(_adCtrl!))),

          // Top gradient + ad info
          Positioned(top: 0, left: 0, right: 0, child: Container(
            padding: EdgeInsets.fromLTRB(14, MediaQuery.of(context).padding.top + 8, 14, 12),
            decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter,
              colors: [Colors.black.withValues(alpha: 0.6), Colors.transparent])),
            child: Row(children: [
              Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(4)),
                child: const Row(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.campaign_rounded, color: Colors.white, size: 12),
                  SizedBox(width: 4),
                  Text('AD', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900, letterSpacing: 1)),
                ])),
              const SizedBox(width: 10),
              Expanded(child: Text(_ad?['page']?['name'] ?? '', style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600))),
              // Ad duration remaining
              if (_adCtrl!.value.duration > Duration.zero) Text(
                'Ad ends in ${(_adCtrl!.value.duration - _adCtrl!.value.position).inSeconds}s',
                style: TextStyle(color: Colors.white.withValues(alpha: 0.7), fontSize: 11)),
            ]))),

          // Ad progress bar at very top (YouTube style yellow bar)
          if (_adCtrl!.value.duration > Duration.zero) Positioned(top: 0, left: 0, right: 0,
            child: LinearProgressIndicator(
              value: _adCtrl!.value.position.inMilliseconds / _adCtrl!.value.duration.inMilliseconds,
              backgroundColor: Colors.transparent, color: kOrange, minHeight: 3)),

          // Bottom bar
          Positioned(bottom: 0, left: 0, right: 0, child: Container(
            padding: const EdgeInsets.fromLTRB(14, 16, 14, 20),
            decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.bottomCenter, end: Alignment.topCenter,
              colors: [Colors.black.withValues(alpha: 0.7), Colors.transparent])),
            child: Row(children: [
              // CTA button
              if (_ad?['cta_text'] != null) GestureDetector(
                onTap: () {
                  if (_ad?['id'] != null) ref.read(communityRepoProvider).trackAdClick(_ad!['id']);
                  final url = _ad?['cta_url'];
                  if (url != null) launchUrl(Uri.parse(url.toString().startsWith('http') ? url : 'https://$url'), mode: LaunchMode.externalApplication);
                },
                child: Container(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                  decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(8),
                    boxShadow: [BoxShadow(color: kOrange.withValues(alpha: 0.4), blurRadius: 12)]),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Text(_ad!['cta_text'], style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14)),
                    const SizedBox(width: 6),
                    const Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 16),
                  ]))),
              const Spacer(),
              // Skip button (YouTube style)
              GestureDetector(
                onTap: _canSkip ? _dismiss : null,
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    color: _canSkip ? Colors.white : Colors.white.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(4),
                    border: Border.all(color: _canSkip ? Colors.white : Colors.white.withValues(alpha: 0.3))),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    if (!_canSkip) SizedBox(width: 20, height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, value: (10 - _countdown) / 10, color: Colors.white, backgroundColor: Colors.white24)),
                    if (!_canSkip) const SizedBox(width: 8),
                    Text(_canSkip ? 'Skip Ad' : '$_countdown',
                      style: TextStyle(color: _canSkip ? Colors.black : Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
                    if (_canSkip) const SizedBox(width: 4),
                    if (_canSkip) const Icon(Icons.skip_next_rounded, size: 18, color: Colors.black),
                  ]))),
            ]))),
        ]),
      )),
    ]);
  }
}

