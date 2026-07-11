import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:video_player/video_player.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../data/models/community_models.dart';
import '../providers/community_provider.dart';

const _kOrange = Color(0xFFFF7A00);

class HighlightViewerScreen extends ConsumerStatefulWidget {
  final CommunityHighlight highlight;
  final int initialIndex;
  const HighlightViewerScreen({super.key, required this.highlight, this.initialIndex = 0});

  @override
  ConsumerState<HighlightViewerScreen> createState() => _HighlightViewerScreenState();
}

class _HighlightViewerScreenState extends ConsumerState<HighlightViewerScreen> {
  late PageController _pageCtrl;
  int _currentIndex = 0;
  Timer? _timer;
  double _progress = 0;
  bool _paused = false;
  VideoPlayerController? _videoCtrl;
  static const _imageDuration = Duration(seconds: 5);

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex;
    _pageCtrl = PageController(initialPage: widget.initialIndex);
  }

  @override
  void dispose() {
    _timer?.cancel();
    _videoCtrl?.dispose();
    _pageCtrl.dispose();
    super.dispose();
  }

  void _startTimer(List<CommunityHighlightItem> items) {
    _timer?.cancel();
    final item = items[_currentIndex];
    if (item.isVideo) {
      _startVideo(item);
      return;
    }
    // Image: auto-advance every 5s
    _progress = 0;
    const tick = Duration(milliseconds: 50);
    final total = _imageDuration.inMilliseconds.toDouble();
    _timer = Timer.periodic(tick, (t) {
      if (_paused) return;
      setState(() => _progress = (t.tick * 50) / total);
      if (t.tick * 50 >= total) {
        t.cancel();
        _goNext(items);
      }
    });
  }

  void _startVideo(CommunityHighlightItem item) {
    _videoCtrl?.dispose();
    _videoCtrl = null;
    final url = item.hlsUrl ?? item.mediaUrl;
    if (url == null || url.isEmpty) { _goNext([]); return; }
    final ctrl = VideoPlayerController.networkUrl(Uri.parse(url));
    _videoCtrl = ctrl;
    ctrl.initialize().then((_) {
      if (!mounted) return;
      setState(() {});
      ctrl.play();
      ctrl.setLooping(false);
      ctrl.addListener(() {
        if (!mounted) return;
        final pos = ctrl.value.position.inMilliseconds;
        final dur = ctrl.value.duration.inMilliseconds;
        if (dur > 0) setState(() => _progress = pos / dur);
        if (ctrl.value.position >= ctrl.value.duration && !ctrl.value.isPlaying) {
          // video ended — go next (checked via listener, only once)
        }
      });
    });
  }

  void _goNext(List<CommunityHighlightItem> items) {
    if (_currentIndex < items.length - 1) {
      _pageCtrl.nextPage(duration: const Duration(milliseconds: 300), curve: Curves.easeInOut);
    } else {
      if (mounted) context.pop();
    }
  }

  void _goPrev() {
    if (_currentIndex > 0) {
      _pageCtrl.previousPage(duration: const Duration(milliseconds: 300), curve: Curves.easeInOut);
    }
  }

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(communityHighlightItemsProvider(widget.highlight.id));

    return Scaffold(
      backgroundColor: Colors.black,
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator(color: _kOrange)),
        error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: Colors.white))),
        data: (items) {
          if (items.isEmpty) {
            return Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.photo_library_outlined, color: Colors.white54, size: 56),
                const SizedBox(height: 12),
                Text('No items in this highlight', style: TextStyle(color: Colors.white54, fontSize: 15)),
                const SizedBox(height: 20),
                TextButton(onPressed: () => context.pop(), child: const Text('Back', style: TextStyle(color: _kOrange))),
              ]),
            );
          }

          return Stack(children: [
            // ── Content PageView ─────────────────────────────────────────
            PageView.builder(
              controller: _pageCtrl,
              itemCount: items.length,
              onPageChanged: (i) {
                setState(() { _currentIndex = i; _progress = 0; });
                _startTimer(items);
              },
              itemBuilder: (_, i) => _ItemPage(item: items[i], videoCtrl: i == _currentIndex ? _videoCtrl : null),
            ),

            // ── Top: progress bars + close ────────────────────────────────
            SafeArea(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                // Progress bars
                Padding(
                  padding: const EdgeInsets.fromLTRB(8, 8, 8, 0),
                  child: Row(
                    children: List.generate(items.length, (i) => Expanded(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 2),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(2),
                          child: LinearProgressIndicator(
                            value: i < _currentIndex ? 1.0 : i == _currentIndex ? _progress : 0.0,
                            backgroundColor: Colors.white30,
                            valueColor: const AlwaysStoppedAnimation(_kOrange),
                            minHeight: 2.5,
                          ),
                        ),
                      ),
                    )),
                  ),
                ),
                const SizedBox(height: 10),
                // Header row
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  child: Row(children: [
                    CircleAvatar(radius: 18, backgroundColor: Colors.white24,
                      child: widget.highlight.coverImage != null
                          ? ClipOval(child: NetImage(url: widget.highlight.coverImage!, fit: BoxFit.cover))
                          : Text(widget.highlight.title.isNotEmpty ? widget.highlight.title[0].toUpperCase() : '?',
                              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700))),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(widget.highlight.title,
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
                        Text('${_currentIndex + 1} / ${items.length}',
                            style: const TextStyle(color: Colors.white60, fontSize: 11)),
                      ]),
                    ),
                    IconButton(
                      icon: const Icon(Icons.close_rounded, color: Colors.white),
                      onPressed: () => context.pop(),
                    ),
                  ]),
                ),
              ]),
            ),

            // ── Tap zones: left = prev, right = next ──────────────────────
            Positioned.fill(
              child: Row(children: [
                Expanded(child: GestureDetector(
                  onTap: _goPrev,
                  onLongPressStart: (_) => setState(() => _paused = true),
                  onLongPressEnd: (_) => setState(() => _paused = false),
                  child: const ColoredBox(color: Colors.transparent),
                )),
                Expanded(child: GestureDetector(
                  onTap: () => _goNext(items),
                  onLongPressStart: (_) => setState(() => _paused = true),
                  onLongPressEnd: (_) => setState(() => _paused = false),
                  child: const ColoredBox(color: Colors.transparent),
                )),
              ]),
            ),

            // ── Bottom: caption ───────────────────────────────────────────
            if (items.isNotEmpty && (items[_currentIndex].caption?.isNotEmpty ?? false))
              Positioned(
                bottom: 40,
                left: 16, right: 16,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: Colors.black54,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(items[_currentIndex].caption!,
                      style: const TextStyle(color: Colors.white, fontSize: 14, height: 1.4),
                      maxLines: 4, overflow: TextOverflow.ellipsis),
                ),
              ),
          ]);
        },
      ),
    );
  }
}

// ── Single item page ──────────────────────────────────────────────────────
class _ItemPage extends StatelessWidget {
  final CommunityHighlightItem item;
  final VideoPlayerController? videoCtrl;
  const _ItemPage({required this.item, this.videoCtrl});

  @override
  Widget build(BuildContext context) {
    if (item.isVideo && videoCtrl != null && videoCtrl!.value.isInitialized) {
      return Center(
        child: AspectRatio(
          aspectRatio: videoCtrl!.value.aspectRatio,
          child: VideoPlayer(videoCtrl!),
        ),
      );
    }

    final imgUrl = item.thumbnailUrl ?? item.mediaUrl;
    if (imgUrl != null && imgUrl.isNotEmpty) {
      return NetImage(url: imgUrl, fit: BoxFit.contain, width: double.infinity, height: double.infinity);
    }

    return const Center(child: Icon(Icons.image_not_supported_outlined, color: Colors.white30, size: 64));
  }
}

// ── "Add to Highlight" bottom sheet ─────────────────────────────────────
class AddToHighlightSheet extends ConsumerWidget {
  final int userId;
  final String contentType; // 'post' | 'story'
  final int contentId;
  const AddToHighlightSheet({super.key, required this.userId, required this.contentType, required this.contentId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(communityHighlightsProvider(userId));
    final c = context.colors;

    return Container(
      decoration: BoxDecoration(
        color: c.cardBg,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        const SizedBox(height: 12),
        Container(width: 40, height: 4, decoration: BoxDecoration(color: c.dividerColor, borderRadius: BorderRadius.circular(2))),
        const SizedBox(height: 16),
        Text('Add to Highlight', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700, color: c.bodyText)),
        const SizedBox(height: 12),
        async.when(
          loading: () => const Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator(color: _kOrange)),
          error: (e, _) => Padding(padding: const EdgeInsets.all(16), child: Text('$e')),
          data: (highlights) {
            if (highlights.isEmpty) {
              return Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
                child: Column(children: [
                  Text('No highlights yet. Create one from your profile.', style: TextStyle(color: c.mutedText, fontSize: 14), textAlign: TextAlign.center),
                  const SizedBox(height: 12),
                  TextButton(onPressed: () => Navigator.pop(context), child: const Text('Close', style: TextStyle(color: _kOrange))),
                ]),
              );
            }
            return Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                ...highlights.map((h) => ListTile(
                  leading: _HighlightAvatar(h: h),
                  title: Text(h.title, style: TextStyle(fontWeight: FontWeight.w600, color: c.bodyText)),
                  subtitle: Text('${h.itemsCount} items', style: TextStyle(color: c.mutedText, fontSize: 12)),
                  onTap: () async {
                    Navigator.pop(context);
                    try {
                      await ref.read(communityRepoProvider).addToHighlight(
                        h.id,
                        contentType: contentType,
                        contentId: contentId,
                      );
                      ref.invalidate(communityHighlightItemsProvider(h.id));
                      ref.invalidate(communityHighlightsProvider(userId));
                      if (context.mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Added to "${h.title}"'), backgroundColor: _kOrange, duration: const Duration(seconds: 2)),
                        );
                      }
                    } catch (e) {
                      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('$e'), backgroundColor: Colors.red),
                      );
                    }
                  },
                )),
                const SizedBox(height: 16),
              ],
            );
          },
        ),
      ]),
    );
  }
}

class _HighlightAvatar extends StatelessWidget {
  final CommunityHighlight h;
  const _HighlightAvatar({required this.h});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 44, height: 44,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: h.coverImage == null ? const LinearGradient(colors: [_kOrange, Color(0xFFFF6B00)], begin: Alignment.topLeft, end: Alignment.bottomRight) : null,
        border: Border.all(color: _kOrange.withValues(alpha: 0.6), width: 1.5),
      ),
      child: ClipOval(child: h.coverImage != null
          ? NetImage(url: h.coverImage!, fit: BoxFit.cover)
          : Center(child: Text(h.title.isNotEmpty ? h.title[0].toUpperCase() : '?',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18)))),
    );
  }
}
