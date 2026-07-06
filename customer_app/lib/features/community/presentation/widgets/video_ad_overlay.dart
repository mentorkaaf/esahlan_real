import 'dart:async';
import 'dart:math';
import 'package:flutter/material.dart';
import '../../../../core/constants/app_constants.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:media_kit/media_kit.dart';
import 'package:video_player/video_player.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import '../screens/community_shell.dart';

class VideoAdOverlay extends ConsumerStatefulWidget {
  final Player mainController;
  final Widget child;
  final VoidCallback? onAdStart;
  final VoidCallback? onAdEnd;
  const VideoAdOverlay({super.key, required this.mainController, required this.child, this.onAdStart, this.onAdEnd});
  @override
  ConsumerState<VideoAdOverlay> createState() => _VideoAdOverlayState();
}

class _VideoAdOverlayState extends ConsumerState<VideoAdOverlay> {
  VideoPlayerController? _adCtrl;
  bool _showingAd = false;
  bool _adReady = false;
  bool _canSkip = false;
  int _countdown = AppConstants.adSkipCountdownSeconds;
  Map<String, dynamic>? _ad;
  bool _preloadStarted = false;
  bool _preloadDone = false;

  // Admin settings — fetched from API
  bool _overlayEnabled = true;
  int _skipSeconds = 10;
  int _maxAdsPerVideo = 4;
  int _minVideoLength = 5;
  int _freqShort = 1;
  int _freqMedium = 2;
  int _freqLong = 3;
  int _freqVeryLong = 4;

  // Trigger state
  final List<double> _triggerPoints = [];
  int _nextTriggerIndex = 0;
  bool _settingsLoaded = false;

  Timer? _progressTimer;

  @override
  void initState() {
    super.initState();
    _progressTimer = Timer.periodic(const Duration(milliseconds: 500), (_) => _onProgress());
    _loadSettingsAndPreload();
  }

  Future<void> _loadSettingsAndPreload() async {
    try {
      final settings = await ref.read(communityRepoProvider).getAdDisplaySettings();
      if (!mounted) return;
      _overlayEnabled = settings['overlay_ads_enabled'] == true;
      _skipSeconds = settings['overlay_skip_seconds'] as int? ?? 10;
      _maxAdsPerVideo = settings['overlay_max_per_video'] as int? ?? 4;
      _minVideoLength = settings['overlay_min_video_length'] as int? ?? 5;
      _freqShort = settings['freq_short'] as int? ?? 1;
      _freqMedium = settings['freq_medium'] as int? ?? 2;
      _freqLong = settings['freq_long'] as int? ?? 3;
      _freqVeryLong = settings['freq_very_long'] as int? ?? 4;
      _countdown = _skipSeconds;
      _settingsLoaded = true;
    } catch (_) {
      _settingsLoaded = true; // Use defaults
    }
    if (_overlayEnabled) _preloadAd();
  }

  void _generateTriggerPoints() {
    if (_triggerPoints.isNotEmpty) return;
    final dur = widget.mainController.state.duration;
    if (dur <= Duration.zero) return;
    final totalSecs = dur.inSeconds;

    if (totalSecs < _minVideoLength) return; // Too short

    int adCount;
    if (totalSecs < 30) adCount = _freqShort;
    else if (totalSecs < 120) adCount = _freqMedium;
    else if (totalSecs < 300) adCount = _freqLong;
    else adCount = _freqVeryLong;

    adCount = adCount.clamp(0, _maxAdsPerVideo);
    for (var i = 1; i <= adCount; i++) {
      _triggerPoints.add(i / (adCount + 1));
    }
  }

  void _preloadAd() async {
    if (_preloadStarted) return;
    _preloadStarted = true;
    try {
      final ad = await ref.read(communityRepoProvider).getPrerollAd();
      if (ad == null || ad['media_url'] == null || !mounted) return;
      _ad = ad;
      final ctrl = VideoPlayerController.networkUrl(Uri.parse(ad['media_url']),
        httpHeaders: const {'Connection': 'keep-alive'});
      await ctrl.initialize();
      ctrl.setLooping(false); ctrl.setVolume(1); ctrl.pause();
      if (!mounted) { ctrl.dispose(); return; }
      _adCtrl = ctrl;
      _preloadDone = true;
    } catch (_) {}
  }

  void _onProgress() {
    if (!_settingsLoaded || !_overlayEnabled || _showingAd || !mounted) return;
    if (!widget.mainController.state.playing) return;
    final dur = widget.mainController.state.duration;
    if (dur <= Duration.zero) return;

    // Generate trigger points once we know duration
    if (_triggerPoints.isEmpty) _generateTriggerPoints();
    if (_nextTriggerIndex >= _triggerPoints.length) return;

    final progress = widget.mainController.state.position.inMilliseconds / dur.inMilliseconds;
    if (widget.mainController.state.position.inSeconds < 2) return;

    if (progress >= _triggerPoints[_nextTriggerIndex]) {
      _nextTriggerIndex++;
      _showAdNow();
    }
  }

  void _showAdNow() {
    if (!_preloadDone || _adCtrl == null || _ad == null) return;
    widget.onAdStart?.call();
    widget.mainController.pause();
    _adCtrl!.play();
    _adCtrl!.addListener(_onAdEnd);
    _countdown = _skipSeconds;
    setState(() { _showingAd = true; _adReady = true; _canSkip = false; });
    _runCountdown();
  }

  void _onAdEnd() {
    if (_adCtrl == null) return;
    if (_adCtrl!.value.position >= _adCtrl!.value.duration && _adCtrl!.value.duration > Duration.zero) {
      _dismiss();
    }
    if (mounted) setState(() {});
  }

  void _runCountdown() async {
    for (var i = _skipSeconds; i > 0; i--) {
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
    _preloadStarted = false;
    _preloadDone = false;
    setState(() { _showingAd = false; _adReady = false; });
    widget.onAdEnd?.call();
    widget.mainController.play();
    // Preload next ad for next trigger
    if (_nextTriggerIndex < _triggerPoints.length) _preloadAd();
  }

  @override
  void dispose() {
    _progressTimer?.cancel();
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
          // Video
          Center(child: AspectRatio(
            aspectRatio: _adCtrl!.value.aspectRatio.clamp(0.5, 2.5),
            child: VideoPlayer(_adCtrl!))),

          // Progress bar top
          if (_adCtrl!.value.duration > Duration.zero) Positioned(top: 0, left: 0, right: 0,
            child: LinearProgressIndicator(
              value: _adCtrl!.value.position.inMilliseconds / _adCtrl!.value.duration.inMilliseconds,
              backgroundColor: Colors.transparent, color: kOrange, minHeight: 3)),

          // AD badge + timer
          Positioned(top: MediaQuery.of(context).padding.top + 8, left: 14,
            child: Container(padding: EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(4)),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Container(padding: EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                  decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(3)),
                  child: Text('AD', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w900))),
                SizedBox(width: 6),
                Text(_ad?['page']?['name'] ?? '', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600)),
              ]))),

          // "Ad ends in Xs"
          if (_adCtrl!.value.duration > Duration.zero) Positioned(top: MediaQuery.of(context).padding.top + 8, right: 14,
            child: Text('Ad ends in ${(_adCtrl!.value.duration - _adCtrl!.value.position).inSeconds}s',
              style: TextStyle(color: Colors.white.withValues(alpha: 0.7), fontSize: 11))),

          // Bottom bar
          Positioned(bottom: 0, left: 0, right: 0, child: Container(
            padding: EdgeInsets.fromLTRB(14, 16, 14, 24),
            decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.bottomCenter, end: Alignment.topCenter,
              colors: [Colors.black.withValues(alpha: 0.7), Colors.transparent])),
            child: Row(children: [
              // CTA
              if (_ad?['cta_text'] != null) GestureDetector(
                onTap: () {
                  if (_ad?['id'] != null) ref.read(communityRepoProvider).trackAdClick(_ad!['id']);
                  final url = _ad?['cta_url']?.toString() ?? '';
                  if (url.isNotEmpty) launchUrl(Uri.parse(url.startsWith('http') ? url : 'https://$url'), mode: LaunchMode.externalApplication);
                },
                child: Container(padding: EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                  decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(8),
                    boxShadow: [BoxShadow(color: kOrange.withValues(alpha: 0.4), blurRadius: 12)]),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    Text(_ad!['cta_text'], style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14)),
                    SizedBox(width: 6),
                    Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 16),
                  ]))),
              Spacer(),
              // Skip
              GestureDetector(
                onTap: _canSkip ? _dismiss : null,
                child: Container(
                  padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    color: _canSkip ? Colors.white : Colors.white.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(4)),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    if (!_canSkip) SizedBox(width: 18, height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2, value: (_skipSeconds - _countdown) / _skipSeconds,
                        color: Colors.white, backgroundColor: Colors.white24)),
                    if (!_canSkip) SizedBox(width: 8),
                    Text(_canSkip ? 'Skip Ad' : '$_countdown',
                      style: TextStyle(color: _canSkip ? Colors.black : Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
                    if (_canSkip) Icon(Icons.skip_next_rounded, size: 18, color: Colors.black),
                  ]))),
            ]))),
        ]),
      )),
    ]);
  }
}
