import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:video_player/video_player.dart';
import '../../../../core/theme/theme_x.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart' show kOrange;

// ── Story Viewer ──────────────────────────────────────────────────────────────

class StoryViewer extends ConsumerStatefulWidget {
  final List<StoryGroup> groups;
  final int initialGroupIndex;
  const StoryViewer({super.key, required this.groups, this.initialGroupIndex = 0});

  @override
  ConsumerState<StoryViewer> createState() => _StoryViewerState();
}

class _StoryViewerState extends ConsumerState<StoryViewer> {
  late final PageController _groupCtrl;
  int _groupIdx = 0;

  @override
  void initState() {
    super.initState();
    _groupIdx = widget.initialGroupIndex;
    _groupCtrl = PageController(initialPage: _groupIdx);
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
  }

  @override
  void dispose() {
    _groupCtrl.dispose();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    super.dispose();
  }

  void _nextGroup() {
    if (_groupIdx < widget.groups.length - 1) {
      setState(() => _groupIdx++);
      _groupCtrl.nextPage(duration: const Duration(milliseconds: 300), curve: Curves.easeInOut);
    } else {
      Navigator.pop(context);
    }
  }

  void _prevGroup() {
    if (_groupIdx > 0) {
      setState(() => _groupIdx--);
      _groupCtrl.previousPage(duration: const Duration(milliseconds: 300), curve: Curves.easeInOut);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: PageView.builder(
        controller: _groupCtrl,
        itemCount: widget.groups.length,
        onPageChanged: (i) => setState(() => _groupIdx = i),
        itemBuilder: (_, i) => _GroupPage(
          group: widget.groups[i],
          isActive: i == _groupIdx,
          onComplete: _nextGroup,
          onPrevGroup: _prevGroup,
        ),
      ),
    );
  }
}

// ── Per-user story page ───────────────────────────────────────────────────────

class _GroupPage extends ConsumerStatefulWidget {
  final StoryGroup group;
  final bool isActive;
  final VoidCallback onComplete;
  final VoidCallback onPrevGroup;
  const _GroupPage({
    required this.group, required this.isActive,
    required this.onComplete, required this.onPrevGroup,
  });

  @override
  ConsumerState<_GroupPage> createState() => _GroupPageState();
}

class _GroupPageState extends ConsumerState<_GroupPage>
    with SingleTickerProviderStateMixin {
  int _idx = 0;
  late AnimationController _timer;
  VideoPlayerController? _videoCtrl;
  VideoPlayerController? _nextVideo;
  bool _videoReady = false;
  final _repo = CommunityRepository();
  final _replyCtrl = TextEditingController();

  CommunityStory get _story => widget.group.stories[_idx];
  bool get _isVideo => _story.type == 'video';

  @override
  void initState() {
    super.initState();
    _timer = AnimationController(vsync: this)
      ..addStatusListener((s) { if (s == AnimationStatus.completed) _advance(); });
    if (widget.isActive) _loadStory(_idx);
  }

  @override
  void didUpdateWidget(_GroupPage old) {
    super.didUpdateWidget(old);
    if (widget.isActive && !old.isActive) _loadStory(_idx);
    if (!widget.isActive && old.isActive) _suspend();
  }

  @override
  void dispose() {
    _timer.dispose();
    _videoCtrl?.dispose();
    _nextVideo?.dispose();
    _replyCtrl.dispose();
    super.dispose();
  }

  void _suspend() {
    _timer.stop();
    _videoCtrl?.pause();
  }

  void _loadStory(int idx) async {
    _timer.stop();
    _timer.reset();
    setState(() => _videoReady = false);

    final s = widget.group.stories[idx];
    _repo.viewStory(s.id).catchError((_) {});

    if (s.type == 'video' && s.mediaUrl != null) {
      VideoPlayerController ctrl;
      if (_nextVideo != null) {
        ctrl = _nextVideo!;
        _nextVideo = null;
      } else {
        ctrl = VideoPlayerController.networkUrl(Uri.parse(s.mediaUrl!));
        await ctrl.initialize().catchError((_) {});
      }

      if (!mounted) { ctrl.dispose(); return; }

      _videoCtrl?.removeListener(_onVideoTick);
      _videoCtrl?.dispose();
      _videoCtrl = ctrl;
      _videoCtrl!.addListener(_onVideoTick);
      _videoCtrl!.setLooping(false);
      setState(() => _videoReady = _videoCtrl!.value.isInitialized);
      if (_videoReady) _videoCtrl!.play();

      _preloadNext(idx);
    } else {
      _timer.duration = const Duration(seconds: 5);
      _timer.forward();
    }
  }

  void _preloadNext(int cur) {
    final ni = cur + 1;
    if (ni >= widget.group.stories.length) return;
    final ns = widget.group.stories[ni];
    if (ns.type != 'video' || ns.mediaUrl == null) return;
    _nextVideo?.dispose();
    _nextVideo = VideoPlayerController.networkUrl(Uri.parse(ns.mediaUrl!));
    _nextVideo!.initialize().catchError((_) {});
  }

  void _onVideoTick() {
    if (_videoCtrl == null || !mounted) return;
    final dur = _videoCtrl!.value.duration.inMilliseconds;
    if (dur <= 0) return;
    final pos = _videoCtrl!.value.position.inMilliseconds;
    final p = (pos / dur).clamp(0.0, 1.0);
    if ((p - _timer.value).abs() > 0.005) { _timer.value = p; }
    if (_videoCtrl!.value.position >= _videoCtrl!.value.duration - const Duration(milliseconds: 150)) {
      _advance();
    }
  }

  void _advance() {
    if (!mounted) return;
    if (_idx < widget.group.stories.length - 1) {
      _videoCtrl?.removeListener(_onVideoTick);
      _videoCtrl?.dispose();
      _videoCtrl = null;
      setState(() { _idx++; _videoReady = false; });
      _loadStory(_idx);
    } else {
      widget.onComplete();
    }
  }

  void _prev() {
    if (_idx > 0) {
      _videoCtrl?.removeListener(_onVideoTick);
      _videoCtrl?.dispose();
      _videoCtrl = null;
      _nextVideo?.dispose();
      _nextVideo = null;
      setState(() { _idx--; _videoReady = false; });
      _loadStory(_idx);
    } else {
      widget.onPrevGroup();
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = _story;
    return GestureDetector(
      onLongPressStart: (_) {
        _timer.stop();
        _videoCtrl?.pause();
      },
      onLongPressEnd: (_) {
        if (_isVideo) { _videoCtrl?.play(); } else { _timer.forward(); }
      },
      child: Stack(children: [
        Positioned.fill(child: _buildContent(s)),

        // Tap zones
        Positioned.fill(child: Row(children: [
          Expanded(child: GestureDetector(onTap: _prev,
            behavior: HitTestBehavior.translucent)),
          Expanded(child: GestureDetector(onTap: _advance,
            behavior: HitTestBehavior.translucent)),
        ])),

        // Progress bars
        Positioned(top: 0, left: 0, right: 0, child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(8, 8, 8, 0),
            child: Row(children: List.generate(widget.group.stories.length, (i) {
              return Expanded(child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2),
                child: AnimatedBuilder(animation: _timer, builder: (_, __) {
                  final v = i < _idx ? 1.0 : i == _idx ? _timer.value : 0.0;
                  return LinearProgressIndicator(value: v, minHeight: 2,
                    backgroundColor: Colors.white30,
                    valueColor: const AlwaysStoppedAnimation(Colors.white));
                }),
              ));
            })),
          ),
        )),

        // Header
        Positioned(top: 0, left: 0, right: 0, child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 24, 12, 0),
            child: Row(children: [
              CircleAvatar(radius: 18,
                backgroundImage: s.user.avatar != null
                  ? CachedNetworkImageProvider(s.user.avatar!) : null,
                child: s.user.avatar == null
                  ? Text(s.user.name.isNotEmpty ? s.user.name[0] : '?',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700))
                  : null),
              const SizedBox(width: 8),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min, children: [
                  Text(s.user.name,
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
                  Text(_timeAgo(s.createdAt),
                    style: const TextStyle(color: Colors.white70, fontSize: 11)),
                ])),
              if (s.user.isMe)
                IconButton(icon: const Icon(Icons.more_vert, color: Colors.white),
                  onPressed: () => _showOptions(s)),
              IconButton(icon: const Icon(Icons.close, color: Colors.white),
                onPressed: () => Navigator.pop(context)),
            ]),
          ),
        )),

        // Bottom
        Positioned(bottom: 0, left: 0, right: 0, child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: s.user.isMe
              ? _ViewersBar(story: s)
              : _ReplyBar(ctrl: _replyCtrl, storyId: s.id),
          ),
        )),
      ]),
    );
  }

  Widget _buildContent(CommunityStory s) {
    if (s.type == 'text') {
      final color = _hexColor(s.bgColor) ?? kOrange;
      return Container(color: color,
        child: Center(child: Padding(padding: const EdgeInsets.all(32),
          child: Text(s.textContent ?? '',
            style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w700),
            textAlign: TextAlign.center))));
    }

    if (s.type == 'video') {
      if (_videoReady && _videoCtrl != null) {
        return Container(color: Colors.black,
          child: Center(child: AspectRatio(
            aspectRatio: _videoCtrl!.value.aspectRatio,
            child: VideoPlayer(_videoCtrl!))));
      }
      return Stack(fit: StackFit.expand, children: [
        if (s.thumbnail != null)
          CachedNetworkImage(imageUrl: s.thumbnail!, fit: BoxFit.cover),
        const Center(child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)),
      ]);
    }

    if (s.mediaUrl != null) {
      return CachedNetworkImage(imageUrl: s.mediaUrl!, fit: BoxFit.cover,
        placeholder: (_, __) => const ColoredBox(color: Colors.black,
          child: Center(child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))),
        errorWidget: (_, __, ___) => const ColoredBox(color: Colors.black12,
          child: Center(child: Icon(Icons.broken_image_rounded, color: Colors.white54, size: 48))));
    }

    return const ColoredBox(color: Colors.black);
  }

  void _showOptions(CommunityStory s) {
    showModalBottomSheet(context: context, builder: (_) => SafeArea(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        ListTile(
          leading: const Icon(Icons.delete_rounded, color: Colors.red),
          title: const Text('Delete Story', style: TextStyle(color: Colors.red)),
          onTap: () async {
            Navigator.pop(context);
            await _repo.deleteStory(s.id).catchError((_) {});
            ref.invalidate(communityStoriesProvider);
            if (mounted) Navigator.pop(context);
          },
        ),
      ]),
    ));
  }

  String _timeAgo(DateTime dt) {
    final d = DateTime.now().difference(dt);
    if (d.inHours > 0) return '${d.inHours}h ago';
    if (d.inMinutes > 0) return '${d.inMinutes}m ago';
    return 'now';
  }

  Color? _hexColor(String? hex) {
    if (hex == null) return null;
    try { return Color(int.parse('FF${hex.replaceAll('#', '')}', radix: 16)); }
    catch (_) { return null; }
  }
}

// ── Viewers bar (own story) ───────────────────────────────────────────────────

class _ViewersBar extends StatelessWidget {
  final CommunityStory story;
  const _ViewersBar({required this.story});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => showModalBottomSheet(context: context,
        isScrollControlled: true,
        builder: (_) => _ViewersSheet(storyId: story.id)),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(color: Colors.black45, borderRadius: BorderRadius.circular(24)),
        child: Row(children: [
          const Icon(Icons.visibility_rounded, color: Colors.white, size: 18),
          const SizedBox(width: 8),
          Text('${story.viewsCount} viewers',
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 14)),
          const Spacer(),
          const Icon(Icons.keyboard_arrow_up_rounded, color: Colors.white60, size: 20),
        ]),
      ),
    );
  }
}

class _ViewersSheet extends StatefulWidget {
  final int storyId;
  const _ViewersSheet({required this.storyId});
  @override State<_ViewersSheet> createState() => _ViewersSheetState();
}

class _ViewersSheetState extends State<_ViewersSheet> {
  List<Map<String, dynamic>>? _viewers;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    CommunityRepository().getStoryViewers(widget.storyId)
        .then((v) { if (mounted) setState(() { _viewers = v; _loading = false; }); })
        .catchError((_) { if (mounted) setState(() => _loading = false); });
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.5, maxChildSize: 0.9, minChildSize: 0.3, expand: false,
      builder: (_, ctrl) => Column(children: [
        const SizedBox(height: 12),
        Container(width: 36, height: 4,
          decoration: BoxDecoration(color: Colors.grey[400], borderRadius: BorderRadius.circular(2))),
        const SizedBox(height: 16),
        Text('Viewers', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700,
          color: context.colors.bodyText)),
        const SizedBox(height: 8),
        Expanded(child: _loading
          ? const Center(child: CircularProgressIndicator())
          : (_viewers?.isEmpty ?? true)
            ? Center(child: Text('No viewers yet',
                style: TextStyle(color: context.colors.mutedText)))
            : ListView.builder(
                controller: ctrl,
                itemCount: _viewers!.length,
                itemBuilder: (_, i) {
                  final v = _viewers![i];
                  return ListTile(
                    leading: CircleAvatar(
                      backgroundImage: v['avatar'] != null
                        ? CachedNetworkImageProvider(v['avatar'] as String) : null,
                      child: v['avatar'] == null
                        ? Text(((v['name'] as String?) ?? '?').isNotEmpty
                            ? (v['name'] as String)[0] : '?') : null,
                    ),
                    title: Text((v['name'] as String?) ?? '',
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                    subtitle: v['username'] != null
                      ? Text('@${v['username']}') : null,
                  );
                }),
        ),
      ]),
    );
  }
}

// ── Reply bar (others' stories) ───────────────────────────────────────────────

class _ReplyBar extends StatefulWidget {
  final TextEditingController ctrl;
  final int storyId;
  const _ReplyBar({required this.ctrl, required this.storyId});
  @override State<_ReplyBar> createState() => _ReplyBarState();
}

class _ReplyBarState extends State<_ReplyBar> {
  bool _sending = false;

  Future<void> _send() async {
    final text = widget.ctrl.text.trim();
    if (text.isEmpty || _sending) return;
    setState(() => _sending = true);
    try {
      await CommunityRepository().commentOnStory(widget.storyId, text);
      widget.ctrl.clear();
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Row(children: [
      ...[' ❤️', '😂', '😮'].map((e) => GestureDetector(
        onTap: () => CommunityRepository().reactToStory(widget.storyId, e.trim())
            .catchError((_) {}),
        child: Padding(padding: const EdgeInsets.only(right: 8),
          child: Text(e, style: const TextStyle(fontSize: 26))),
      )),
      const SizedBox(width: 4),
      Expanded(child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.black45, borderRadius: BorderRadius.circular(24),
          border: Border.all(color: Colors.white30),
        ),
        child: Row(children: [
          Expanded(child: TextField(
            controller: widget.ctrl,
            style: const TextStyle(color: Colors.white, fontSize: 14),
            decoration: const InputDecoration.collapsed(
              hintText: 'Reply...',
              hintStyle: TextStyle(color: Colors.white54),
            ),
          )),
          if (_sending)
            const SizedBox(width: 18, height: 18,
              child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
          else
            GestureDetector(onTap: _send,
              child: const Icon(Icons.send_rounded, color: Colors.white, size: 18)),
        ]),
      )),
    ]);
  }
}
