import 'dart:math';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';
import '../../../../core/widgets/network_image_widget.dart';
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
  bool _showingAd = false;
  bool _canSkip = false;
  int _countdown = 5;
  Map<String, dynamic>? _ad;
  bool _adTriggered = false;
  double _triggerPoint = 0;

  @override
  void initState() {
    super.initState();
    _triggerPoint = [0.0, 0.4 + Random().nextDouble() * 0.2, 0.8][Random().nextInt(3)];
    widget.mainController.addListener(_onProgress);
    if (_triggerPoint == 0.0) _fetchAndShowAd();
  }

  void _onProgress() {
    if (_adTriggered || !mounted) return;
    final dur = widget.mainController.value.duration;
    if (dur <= Duration.zero) return;
    final progress = widget.mainController.value.position.inMilliseconds / dur.inMilliseconds;
    if (_triggerPoint > 0 && progress >= _triggerPoint) _fetchAndShowAd();
  }

  Future<void> _fetchAndShowAd() async {
    if (_adTriggered) return;
    _adTriggered = true;
    try {
      final ad = await ref.read(communityRepoProvider).getPrerollAd();
      if (ad == null || !mounted) { _resumeMain(); return; }
      widget.mainController.pause();
      setState(() { _ad = ad; _showingAd = true; });
      _runCountdown();
    } catch (_) { _resumeMain(); }
  }

  void _runCountdown() async {
    for (var i = 5; i > 0; i--) {
      await Future.delayed(const Duration(seconds: 1));
      if (!mounted || !_showingAd) return;
      setState(() => _countdown = i - 1);
    }
    if (mounted) setState(() => _canSkip = true);
    // Auto-dismiss after 3 more seconds if not skipped
    await Future.delayed(const Duration(seconds: 3));
    if (mounted && _showingAd) _dismiss();
  }

  void _dismiss() {
    setState(() => _showingAd = false);
    _resumeMain();
  }

  void _resumeMain() {
    if (widget.mainController.value.isInitialized) widget.mainController.play();
  }

  @override
  void dispose() {
    widget.mainController.removeListener(_onProgress);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(children: [
      widget.child,
      if (_showingAd && _ad != null) Positioned.fill(child: GestureDetector(
        onTap: () {
          if (_ad?['id'] != null) ref.read(communityRepoProvider).trackAdClick(_ad!['id']);
        },
        child: Container(
          decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter, colors: [Color(0xFF1A1B2E), Color(0xFF0D0E1A)])),
          child: Stack(children: [
          // Ad title centered
          Center(child: Padding(padding: const EdgeInsets.symmetric(horizontal: 40),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              if (_ad!['page']?['avatar'] != null) CircleNetImage(url: _ad!['page']['avatar'], size: 56, fallbackText: _ad!['page']?['name'] ?? 'Ad'),
              if (_ad!['page']?['avatar'] == null) Container(width: 56, height: 56, decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle), child: const Icon(Icons.campaign_rounded, color: Colors.white, size: 28)),
              const SizedBox(height: 12),
              Text(_ad!['title'] ?? '', style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800), textAlign: TextAlign.center),
              if (_ad!['description'] != null) ...[const SizedBox(height: 6), Text(_ad!['description'], style: const TextStyle(color: Colors.white70, fontSize: 14), textAlign: TextAlign.center)],
            ]))),

          // Top bar: Ad badge + progress
          Positioned(top: 0, left: 0, right: 0, child: Container(
            padding: EdgeInsets.fromLTRB(12, MediaQuery.of(context).padding.top + 8, 12, 8),
            decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.topCenter, end: Alignment.bottomCenter,
              colors: [Colors.black.withValues(alpha: 0.6), Colors.transparent])),
            child: Row(children: [
              Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(4)),
                child: const Text('AD', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w900, letterSpacing: 1))),
              const SizedBox(width: 8),
              if (_ad!['page'] != null) Text(_ad!['page']['name'] ?? '', style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600)),
              const Spacer(),
              // Countdown ring
              SizedBox(width: 28, height: 28, child: Stack(alignment: Alignment.center, children: [
                CircularProgressIndicator(value: _canSkip ? 1.0 : (5 - _countdown) / 5, strokeWidth: 2, color: kOrange, backgroundColor: Colors.white24),
                Text('${_canSkip ? 0 : _countdown}', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700)),
              ])),
            ]))),

          // Bottom bar: CTA + Skip
          Positioned(bottom: 0, left: 0, right: 0, child: Container(
            padding: const EdgeInsets.fromLTRB(12, 16, 12, 24),
            decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.bottomCenter, end: Alignment.topCenter,
              colors: [Colors.black.withValues(alpha: 0.7), Colors.transparent])),
            child: Row(children: [
              // CTA
              if (_ad!['cta_text'] != null) GestureDetector(
                onTap: () { if (_ad?['id'] != null) ref.read(communityRepoProvider).trackAdClick(_ad!['id']); },
                child: Container(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                  decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(8)),
                  child: Text(_ad!['cta_text'], style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)))),
              const Spacer(),
              // Skip
              GestureDetector(
                onTap: _canSkip ? _dismiss : null,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    color: _canSkip ? Colors.white : Colors.white.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(8)),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Text(_canSkip ? 'Skip' : '$_countdown',
                      style: TextStyle(color: _canSkip ? Colors.black : Colors.white60, fontWeight: FontWeight.w700, fontSize: 14)),
                    if (_canSkip) const Icon(Icons.chevron_right_rounded, size: 18, color: Colors.black),
                  ]))),
            ]))),

          // Title overlay
          if (_ad!['title'] != null) Positioned(left: 16, right: 80, bottom: 70,
            child: Text(_ad!['title'], style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800, shadows: [Shadow(blurRadius: 8, color: Colors.black54)]))),
        ])),
      )),
    ]);
  }
}
