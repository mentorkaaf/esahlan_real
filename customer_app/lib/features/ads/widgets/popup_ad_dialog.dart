import 'package:chewie/chewie.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:video_player/video_player.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:youtube_player_flutter/youtube_player_flutter.dart';
import '../models/ad_model.dart';
import '../services/ad_service.dart';
import '../../../core/widgets/network_image_widget.dart';

// ──────────────────────────────────────────────
// URL helpers
// ──────────────────────────────────────────────

String? _extractYoutubeId(String url) {
  final uri = Uri.tryParse(url);
  if (uri == null) return null;
  final host = uri.host;
  if (host == 'youtu.be') {
    return uri.pathSegments.isNotEmpty ? uri.pathSegments.first : null;
  }
  if (host.contains('youtube.com')) {
    final v = uri.queryParameters['v'];
    if (v != null && v.isNotEmpty) return v;
    // Shorts: /shorts/ID
    final idx = uri.pathSegments.indexOf('shorts');
    if (idx != -1 && idx + 1 < uri.pathSegments.length) {
      return uri.pathSegments[idx + 1];
    }
  }
  return null;
}

bool _isTikTokUrl(String url) {
  final host = Uri.tryParse(url)?.host ?? '';
  return host.contains('tiktok.com');
}

bool _isYouTubeUrl(String url) {
  final host = Uri.tryParse(url)?.host ?? '';
  return host.contains('youtube.com') || host == 'youtu.be';
}

String? _extractTikTokVideoId(String url) {
  final uri = Uri.tryParse(url);
  if (uri == null) return null;
  final idx = uri.pathSegments.indexOf('video');
  if (idx != -1 && idx + 1 < uri.pathSegments.length) {
    return uri.pathSegments[idx + 1];
  }
  return null;
}

/// Resolves any TikTok URL (short or full) to a video ID via TikTok oEmbed.
Future<String?> _resolveTikTokId(String url) async {
  final direct = _extractTikTokVideoId(url);
  if (direct != null && direct.isNotEmpty) return direct;
  try {
    final resp = await Dio().get(
      'https://www.tiktok.com/oembed',
      queryParameters: {'url': url},
      options: Options(
        receiveTimeout: const Duration(seconds: 8),
        sendTimeout: const Duration(seconds: 5),
      ),
    );
    final html = resp.data['html']?.toString() ?? '';
    final match = RegExp(r'data-video-id="(\d+)"').firstMatch(html);
    return match?.group(1);
  } catch (_) {
    return null;
  }
}

// ──────────────────────────────────────────────
// Public API
// ──────────────────────────────────────────────

Future<bool> showPopupAd(
  BuildContext context,
  AdModel ad, {
  required AdService adService,
}) async {
  if (ad.isFullScreen) {
    return await _showFullScreen(context, ad, adService) ?? false;
  } else {
    return await _showModal(context, ad, adService) ?? false;
  }
}

Future<bool?> _showModal(BuildContext context, AdModel ad, AdService adService) {
  return showGeneralDialog<bool>(
    context: context,
    barrierDismissible: false,
    barrierColor: Colors.black.withOpacity(0.6),
    transitionDuration: const Duration(milliseconds: 280),
    transitionBuilder: (_, anim, __, child) => ScaleTransition(
      scale: CurvedAnimation(parent: anim, curve: Curves.easeOutBack),
      child: FadeTransition(opacity: anim, child: child),
    ),
    pageBuilder: (ctx, _, __) =>
        _PopupAdSheet(ad: ad, adService: adService, fullScreen: false),
  );
}

Future<bool?> _showFullScreen(BuildContext context, AdModel ad, AdService adService) {
  return showGeneralDialog<bool>(
    context: context,
    barrierDismissible: false,
    barrierColor: Colors.black,
    transitionDuration: const Duration(milliseconds: 350),
    transitionBuilder: (_, anim, __, child) => SlideTransition(
      position: Tween<Offset>(begin: const Offset(0, 1), end: Offset.zero)
          .animate(CurvedAnimation(parent: anim, curve: Curves.easeOutCubic)),
      child: child,
    ),
    pageBuilder: (ctx, _, __) =>
        _PopupAdSheet(ad: ad, adService: adService, fullScreen: true),
  );
}

// ──────────────────────────────────────────────
// Sheet widget
// ──────────────────────────────────────────────

class _PopupAdSheet extends StatefulWidget {
  final AdModel ad;
  final AdService adService;
  final bool fullScreen;

  const _PopupAdSheet({
    required this.ad,
    required this.adService,
    required this.fullScreen,
  });

  @override
  State<_PopupAdSheet> createState() => _PopupAdSheetState();
}

class _PopupAdSheetState extends State<_PopupAdSheet> {
  bool _dontShowToday = false;

  YoutubePlayerController? _ytController;
  WebViewController? _tikTokController;
  VideoPlayerController? _videoController;
  ChewieController? _chewieController;

  bool _videoLoading = false;
  bool _videoError = false;

  AdModel get ad => widget.ad;

  @override
  void initState() {
    super.initState();
    _initVideo();
  }

  @override
  void dispose() {
    _ytController?.close();
    _chewieController?.dispose();
    _videoController?.dispose();
    super.dispose();
  }

  // ── video init ──────────────────────────────

  void _initVideo() {
    final url = ad.videoUrl;
    if (url == null || url.isEmpty) return;

    if (_isYouTubeUrl(url)) {
      _initYouTube(url);
    } else if (_isTikTokUrl(url)) {
      _initTikTok(url);
    } else {
      _initDirectVideo(url);
    }
  }

  void _initYouTube(String url) {
    final id = _extractYoutubeId(url);
    if (id == null || id.isEmpty) return;
    _ytController = YoutubePlayerController.fromVideoId(
      videoId: id,
      autoPlay: true,
      params: const YoutubePlayerParams(
        mute: false,
        enableCaption: false,
        strictRelatedVideos: true,
        showControls: true,
        showFullscreenButton: true,
      ),
    );
  }

  Future<void> _initTikTok(String url) async {
    if (!mounted) return;
    setState(() => _videoLoading = true);

    final videoId = await _resolveTikTokId(url);
    if (!mounted) return;

    final ctrl = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.black);


    if (videoId != null && videoId.isNotEmpty) {
      await ctrl.loadRequest(Uri.parse(
        'https://www.tiktok.com/player/v1/$videoId?autoplay=1&loop=0&rel=0',
      ));
    } else {
      await ctrl.loadRequest(Uri.parse(url));
    }

    if (!mounted) return;
    setState(() {
      _tikTokController = ctrl;
      _videoLoading = false;
    });
  }

  Future<void> _initDirectVideo(String url) async {
    if (!mounted) return;
    setState(() => _videoLoading = true);
    try {
      final ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
      await ctrl.initialize();
      final chewie = ChewieController(
        videoPlayerController: ctrl,
        autoPlay: true,
        looping: false,
        showControls: true,
        aspectRatio: ctrl.value.aspectRatio,
      );
      if (!mounted) {
        ctrl.dispose();
        chewie.dispose();
        return;
      }
      setState(() {
        _videoController = ctrl;
        _chewieController = chewie;
        _videoLoading = false;
      });
    } catch (_) {
      if (mounted) setState(() { _videoLoading = false; _videoError = true; });
    }
  }

  // ── actions ─────────────────────────────────

  void _close({bool clicked = false}) {
    if (_dontShowToday) widget.adService.markDontShowToday(ad.id);
    _ytController?.pauseVideo();
    _tikTokController?.loadHtmlString('<html><body style="background:#000"></body></html>');
    _videoController?.pause();
    Navigator.of(context).pop(clicked);
  }

  void _onCtaPressed() {
    widget.adService.trackClick(ad.id);
    _handleAction();
    _close(clicked: true);
  }

  void _handleAction() {
    final val = ad.actionValue ?? '';
    switch (ad.actionType) {
      case 'module':
        if (val.isNotEmpty) context.push('/$val');
        break;
      case 'vendor':
        final id = int.tryParse(val);
        if (id != null) context.push('/vendor/$id');
        break;
      case 'product':
        final id = int.tryParse(val);
        if (id != null) context.push('/eshop/products/$id');
        break;
      default:
        break;
    }
  }

  // ── helpers ─────────────────────────────────

  bool get _hasVideo =>
      _ytController != null ||
      _tikTokController != null ||
      _chewieController != null;

  Widget _buildVideoArea({required double height}) {
    if (_videoLoading) {
      return Container(
        height: height,
        color: Colors.black,
        child: const Center(child: CircularProgressIndicator(color: Colors.white)),
      );
    }
    if (_ytController != null) return YoutubePlayer(controller: _ytController!);
    if (_tikTokController != null) {
      return SizedBox(height: height, child: WebViewWidget(controller: _tikTokController!));
    }
    if (_chewieController != null) {
      return SizedBox(height: height, child: Chewie(controller: _chewieController!));
    }
    return const SizedBox.shrink();
  }

  // ── build ────────────────────────────────────

  @override
  Widget build(BuildContext context) =>
      widget.fullScreen ? _buildFullScreen() : _buildModal();

  Widget _buildFullScreen() {
    final screenH = MediaQuery.of(context).size.height;
    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(children: [
        if (_hasVideo || _videoLoading)
          Positioned.fill(child: _buildVideoArea(height: screenH))
        else if (ad.imageUrl != null)
          Positioned.fill(child: NetImage(url: ad.imageUrl!, fit: BoxFit.cover)),

        if (!_hasVideo && !_videoLoading)
          Positioned.fill(
            child: Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  stops: [0.4, 1.0],
                  colors: [Colors.transparent, Colors.black87],
                ),
              ),
            ),
          ),

        Positioned(
          top: MediaQuery.of(context).padding.top + 12,
          right: 16,
          child: _CloseButton(onTap: _close),
        ),
        Positioned(
          left: 0, right: 0, bottom: 0,
          child: _buildBottomContent(dark: true),
        ),
      ]),
    );
  }

  Widget _buildModal() {
    final size = MediaQuery.of(context).size;
    final videoH = size.height * 0.55;
    return Center(
      child: Material(
        color: Colors.transparent,
        child: Container(
          margin: const EdgeInsets.symmetric(horizontal: 24),
          constraints: BoxConstraints(maxWidth: 420, maxHeight: size.height * 0.88),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(24),
            boxShadow: [
              BoxShadow(
                  color: Colors.black.withOpacity(0.25),
                  blurRadius: 40,
                  offset: const Offset(0, 12)),
            ],
          ),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                if (_hasVideo || _videoLoading)
                  Stack(children: [
                    _buildVideoArea(height: videoH),
                    Positioned(top: 8, right: 8, child: _CloseButton(onTap: _close)),
                  ])
                else if (ad.imageUrl != null)
                  AspectRatio(
                    aspectRatio: 16 / 9,
                    child: Stack(children: [
                      NetImage(
                          url: ad.imageUrl!,
                          fit: BoxFit.cover,
                          width: double.infinity,
                          height: double.infinity),
                      Positioned(top: 10, right: 10, child: _CloseButton(onTap: _close)),
                    ]),
                  )
                else
                  Align(
                    alignment: Alignment.topRight,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(0, 10, 12, 0),
                      child: _CloseButton(onTap: _close),
                    ),
                  ),

                Flexible(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.fromLTRB(24, 20, 24, 24),
                    child: _buildTextContent(dark: false),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildBottomContent({required bool dark}) {
    return Padding(
      padding: EdgeInsets.fromLTRB(24, 0, 24, MediaQuery.of(context).padding.bottom + 32),
      child: _buildTextContent(dark: dark),
    );
  }

  Widget _buildTextContent({required bool dark}) {
    final titleColor = dark ? Colors.white : const Color(0xFF1F2937);
    final bodyColor = dark ? Colors.white70 : const Color(0xFF6B7280);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          ad.title,
          style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w900,
              color: titleColor,
              height: 1.2,
              letterSpacing: -0.5),
        ),
        if (ad.description != null && ad.description!.isNotEmpty) ...[
          const SizedBox(height: 10),
          Text(ad.description!,
              style: TextStyle(fontSize: 14, color: bodyColor, height: 1.55)),
        ],
        const SizedBox(height: 20),
        if (ad.actionType != 'none')
          SizedBox(
            width: double.infinity,
            height: 52,
            child: ElevatedButton(
              onPressed: _onCtaPressed,
              style: ElevatedButton.styleFrom(
                backgroundColor: ad.buttonColor,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: Text(ad.buttonText,
                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            ),
          ),
        if (ad.allowDontShowToday) ...[
          const SizedBox(height: 14),
          GestureDetector(
            onTap: () => setState(() => _dontShowToday = !_dontShowToday),
            child: Row(children: [
              AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                width: 18, height: 18,
                decoration: BoxDecoration(
                  color: _dontShowToday ? ad.buttonColor : Colors.transparent,
                  border: Border.all(
                    color: _dontShowToday
                        ? ad.buttonColor
                        : (dark ? Colors.white38 : const Color(0xFFD1D5DB)),
                    width: 1.5,
                  ),
                  borderRadius: BorderRadius.circular(5),
                ),
                child: _dontShowToday
                    ? const Icon(Icons.check, size: 13, color: Colors.white)
                    : null,
              ),
              const SizedBox(width: 8),
              Text(
                "Don't show today",
                style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: dark ? Colors.white60 : const Color(0xFF9CA3AF)),
              ),
            ]),
          ),
        ],
      ],
    );
  }
}

// ──────────────────────────────────────────────
// Close button
// ──────────────────────────────────────────────

class _CloseButton extends StatelessWidget {
  final VoidCallback onTap;
  final bool small;

  const _CloseButton({required this.onTap, this.small = false});

  @override
  Widget build(BuildContext context) {
    final size = small ? 28.0 : 34.0;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: size, height: size,
        decoration: BoxDecoration(
            color: Colors.black.withOpacity(0.45), shape: BoxShape.circle),
        child: Icon(Icons.close_rounded, color: Colors.white, size: size * 0.55),
      ),
    );
  }
}
