import 'dart:async';
import '../../../../core/theme/theme_x.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:video_player/video_player.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../screens/community_shell.dart';
import 'highlight_viewer_screen.dart';

class StoryViewer extends StatefulWidget {
  final List<StoryGroup> groups;
  final int initialGroupIndex;
  const StoryViewer({super.key, required this.groups, required this.initialGroupIndex});

  @override
  State<StoryViewer> createState() => _StoryViewerState();
}

class _StoryViewerState extends State<StoryViewer> {
  late int _groupIndex;
  int _storyIndex = 0;
  final _repo = CommunityRepository();
  final _commentCtrl = TextEditingController();
  bool _showCommentInput = false;

  // Active video
  VideoPlayerController? _ctrl;
  bool _initialized = false;
  bool _videoError  = false;
  bool _advancing   = false;
  bool _paused      = false; // hold-to-pause state

  // 1-slot preload
  VideoPlayerController? _nextCtrl;
  String?               _nextUrl;

  @override
  void initState() {
    super.initState();
    _groupIndex = widget.initialGroupIndex;
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
    _loadCurrentStory();
  }

  @override
  void dispose() {
    _commentCtrl.dispose();
    _ctrl?.removeListener(_onProgress);
    _ctrl?.dispose();
    _nextCtrl?.dispose();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    super.dispose();
  }

  // ── Navigation ────────────────────────────────────────────────────────────

  StoryGroup get _currentGroup => widget.groups[_groupIndex];
  CommunityStory get _currentStory => _currentGroup.stories[_storyIndex];

  void _nextStory() {
    if (!mounted) return;
    if (_storyIndex < _currentGroup.stories.length - 1) {
      setState(() => _storyIndex++);
    } else if (_groupIndex < widget.groups.length - 1) {
      setState(() { _groupIndex++; _storyIndex = 0; });
    } else {
      Navigator.pop(context);
      return;
    }
    _loadCurrentStory();
  }

  void _prevStory() {
    if (!mounted) return;
    if (_storyIndex > 0) {
      setState(() => _storyIndex--);
    } else if (_groupIndex > 0) {
      setState(() { _groupIndex--; _storyIndex = widget.groups[_groupIndex].stories.length - 1; });
    } else return;
    _loadCurrentStory();
  }

  // ── Story loading ─────────────────────────────────────────────────────────

  void _loadCurrentStory() {
    _repo.viewStory(_currentStory.id);
    _advancing = false;
    setState(() { _initialized = false; _videoError = false; });

    // Precache thumbnail immediately so there's something to show
    final story = _currentStory;
    final thumbUrl = story.thumbnail ?? (story.type == 'image' ? story.mediaUrl : null);
    if (thumbUrl != null) {
      precacheImage(CachedNetworkImageProvider(thumbUrl), context);
    }

    if (story.type == 'video' && story.mediaUrl != null) {
      _startVideo(story.mediaUrl!);
    } else {
      // Image / text: dispose old video, schedule preload of next
      _disposeActive();
      Future.delayed(const Duration(milliseconds: 300), _preloadNext);
    }
  }

  void _disposeActive() {
    _ctrl?.removeListener(_onProgress);
    _ctrl?.dispose();
    _ctrl = null;
  }

  VideoPlayerController _makeController(String url) =>
      VideoPlayerController.networkUrl(
        Uri.parse(url),
        videoPlayerOptions: VideoPlayerOptions(mixWithOthers: false),
      );

  Future<void> _startVideo(String url) async {
    _disposeActive();
    _paused = false;

    VideoPlayerController ctrl;
    bool wasPreloaded = false;

    if (_nextCtrl != null && _nextUrl == url) {
      // Reuse preloaded controller — instant start
      ctrl = _nextCtrl!;
      _nextCtrl = null;
      _nextUrl  = null;
      wasPreloaded = true;
    } else {
      _nextCtrl?.dispose();
      _nextCtrl = null;
      _nextUrl  = null;
      ctrl = _makeController(url);
      try {
        await ctrl.initialize();
      } catch (_) {
        if (!mounted) { ctrl.dispose(); return; }
        setState(() => _videoError = true);
        ctrl.dispose();
        return;
      }
    }

    if (!mounted) { ctrl.dispose(); return; }

    _ctrl = ctrl;
    ctrl.addListener(_onProgress);
    await ctrl.setLooping(false);
    if (wasPreloaded) {
      // Restore volume (preload sets it to 0) and seek to start
      await ctrl.setVolume(1.0);
      await ctrl.seekTo(Duration.zero);
    }
    await ctrl.play();

    if (!mounted) return;
    setState(() => _initialized = true);

    Future.delayed(const Duration(seconds: 1), _preloadNext);
  }

  void _onProgress() {
    if (_advancing || _paused) return;
    final c = _ctrl;
    if (c == null || !c.value.isInitialized) return;
    final dur = c.value.duration.inMilliseconds;
    final pos = c.value.position.inMilliseconds;
    if (dur > 0 && pos >= dur - 400 && !c.value.isBuffering) {
      _advancing = true;
      _nextStory();
    }
  }

  Future<void> _preloadNext() async {
    if (!mounted) return;

    String? url;
    if (_storyIndex < _currentGroup.stories.length - 1) {
      final s = _currentGroup.stories[_storyIndex + 1];
      if (s.type == 'video') url = s.mediaUrl;
    } else if (_groupIndex < widget.groups.length - 1) {
      final s = widget.groups[_groupIndex + 1].stories.first;
      if (s.type == 'video') url = s.mediaUrl;
    }

    if (url == null || url == _nextUrl) return;

    _nextCtrl?.dispose();
    _nextCtrl = null;
    _nextUrl  = null;

    final ctrl = _makeController(url);
    try {
      await ctrl.initialize();
    } catch (_) {
      ctrl.dispose();
      return;
    }
    if (!mounted) { ctrl.dispose(); return; }

    _nextCtrl = ctrl;
    _nextUrl  = url;
    await ctrl.setVolume(0);
    await ctrl.pause();
    await ctrl.seekTo(Duration.zero);
  }

  // ── UI ────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final group = _currentGroup;
    final story = _currentStory;
    final isVideo = story.type == 'video';

    return Scaffold(
      backgroundColor: Colors.black,
      resizeToAvoidBottomInset: false,
      body: GestureDetector(
        onTapUp: (d) {
          if (_showCommentInput) {
            FocusScope.of(context).unfocus();
            setState(() => _showCommentInput = false);
            return;
          }
          final w = MediaQuery.of(context).size.width;
          if (d.globalPosition.dx < w * 0.35) _prevStory(); else _nextStory();
        },
        onLongPressStart: (_) {
          _paused = true;
          _ctrl?.pause();
        },
        onLongPressEnd: (_) {
          _paused = false;
          if (_initialized) _ctrl?.play();
        },
        child: Stack(fit: StackFit.expand, children: [

          // ── Story content ──────────────────────────────────────────────
          _buildContent(story),

          // ── Progress bars ──────────────────────────────────────────────
          Positioned(
            top: MediaQuery.of(context).padding.top + 8,
            left: 8, right: 8,
            child: Row(
              children: List.generate(_currentGroup.stories.length, (i) => Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 2),
                  child: _ProgressBar(
                    key: ValueKey('bar_${_groupIndex}_${_storyIndex}_$i'),
                    active: i == _storyIndex,
                    done:   i < _storyIndex,
                    isVideo: isVideo && i == _storyIndex,
                    imageDuration: const Duration(seconds: 5),
                    ctrl: isVideo && i == _storyIndex ? _ctrl : null,
                    onDone: i == _storyIndex ? _nextStory : null,
                  ),
                ),
              )),
            ),
          ),

          // ── Header ────────────────────────────────────────────────────
          Positioned(
            top: MediaQuery.of(context).padding.top + 24,
            left: 12, right: 12,
            child: Row(children: [
              CircleAvatar(
                radius: 18,
                backgroundImage: group.user.avatar != null
                    ? CachedNetworkImageProvider(group.user.avatar!) : null,
                child: group.user.avatar == null
                    ? Text(group.user.name[0].toUpperCase(),
                        style: const TextStyle(fontWeight: FontWeight.bold)) : null,
              ),
              const SizedBox(width: 8),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(group.user.name,
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
                Text(_timeLabel(story.createdAt),
                    style: const TextStyle(color: Colors.white70, fontSize: 12)),
              ])),
              GestureDetector(
                onTap: () => _showStoryOptions(context, story, group),
                child: const Icon(Icons.more_vert_rounded, color: Colors.white, size: 26)),
              const SizedBox(width: 4),
              GestureDetector(
                onTap: () => Navigator.pop(context),
                child: const Icon(Icons.close_rounded, color: Colors.white, size: 28)),
            ]),
          ),

          // ── Text overlay ───────────────────────────────────────────────
          if (story.textContent != null && story.textContent!.isNotEmpty && story.type == 'text')
            Positioned(
              bottom: 80, left: 24, right: 24,
              child: Text(story.textContent!, textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white, fontSize: 22,
                      fontWeight: FontWeight.w700, height: 1.4))),

          if (story.location != null)
            Positioned(bottom: 60, left: 24,
              child: Row(children: [
                const Icon(Icons.location_on_rounded, color: Colors.white70, size: 14),
                const SizedBox(width: 4),
                Text(story.location!, style: const TextStyle(color: Colors.white70, fontSize: 12)),
              ])),

          // ── Bottom bar ─────────────────────────────────────────────────
          Positioned(
            bottom: 0, left: 0, right: 0,
            child: Container(
              padding: EdgeInsets.only(
                  left: 12, right: 12, top: 8,
                  bottom: MediaQuery.of(context).viewInsets.bottom +
                      MediaQuery.of(context).padding.bottom + 8),
              decoration: const BoxDecoration(gradient: LinearGradient(
                  begin: Alignment.bottomCenter, end: Alignment.topCenter,
                  colors: [Colors.black87, Colors.transparent])),
              child: group.user.isMe
                  ? GestureDetector(
                      onTap: () => _showViewersSheet(context, story),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                        child: Row(children: [
                          const Icon(Icons.visibility_rounded, color: Colors.white, size: 20),
                          const SizedBox(width: 8),
                          Text('${story.viewsCount} ${story.viewsCount == 1 ? 'viewer' : 'viewers'}',
                              style: const TextStyle(color: Colors.white, fontSize: 14,
                                  fontWeight: FontWeight.w600)),
                          const Spacer(),
                          const Icon(Icons.keyboard_arrow_up_rounded, color: Colors.white70, size: 24),
                        ]),
                      ))
                  : _showCommentInput
                      ? Row(children: [
                          Expanded(child: TextField(
                            controller: _commentCtrl, autofocus: true,
                            style: const TextStyle(color: Colors.white),
                            decoration: InputDecoration(
                              hintText: 'Send message...',
                              hintStyle: const TextStyle(color: Colors.white54),
                              filled: true, fillColor: Colors.white24,
                              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                              border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(24),
                                  borderSide: BorderSide.none)),
                            onSubmitted: (_) => _sendComment())),
                          const SizedBox(width: 8),
                          GestureDetector(
                            onTap: _sendComment,
                            child: const CircleAvatar(radius: 18, backgroundColor: kOrange,
                                child: Icon(Icons.send_rounded, color: Colors.white, size: 16))),
                        ])
                      : Row(children: [
                          Expanded(child: GestureDetector(
                            onTap: () => setState(() => _showCommentInput = true),
                            child: Container(
                              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                              decoration: BoxDecoration(
                                  color: Colors.white24,
                                  borderRadius: BorderRadius.circular(24)),
                              child: const Text('Send message...',
                                  style: TextStyle(color: Colors.white54, fontSize: 14))),
                          )),
                          const SizedBox(width: 8),
                          for (final emoji in ['❤️', '👍', '😂'])
                            GestureDetector(
                              onTap: () => _sendReaction(emoji),
                              child: Padding(
                                  padding: const EdgeInsets.symmetric(horizontal: 4),
                                  child: Text(emoji, style: const TextStyle(fontSize: 26)))),
                        ]),
            )),
        ]),
      ),
    );
  }

  static const _bgGradient = BoxDecoration(gradient: LinearGradient(
      begin: Alignment.topLeft, end: Alignment.bottomRight,
      colors: [Color(0xFF1A0533), Color(0xFF0D1B2A)]));

  Widget _buildContent(CommunityStory story) {
    if (story.type == 'text') {
      final bg = story.bgColor != null
          ? Color(int.parse('0xFF${story.bgColor!.replaceFirst('#', '')}'))
          : kOrange;
      return Container(color: bg,
          child: Center(child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 32),
              child: Text(story.textContent ?? '', textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white, fontSize: 26,
                      fontWeight: FontWeight.w700, height: 1.4)))));
    }

    if (story.type == 'video') {
      return Stack(fit: StackFit.expand, children: [
        // Gradient background — never pure black
        Container(decoration: _bgGradient),

        // Thumbnail visible instantly while video initialises
        if (story.thumbnail != null)
          CachedNetworkImage(
              imageUrl: story.thumbnail!, fit: BoxFit.cover,
              fadeInDuration: const Duration(milliseconds: 80),
              placeholder: (_, __) => const SizedBox.shrink(),
              errorWidget: (_, __, ___) => const SizedBox.shrink()),

        // Video — shown once controller is ready
        if (_initialized && _ctrl != null)
          AnimatedOpacity(
            opacity: 1.0,
            duration: const Duration(milliseconds: 150),
            child: FittedBox(
              fit: BoxFit.cover,
              child: SizedBox(
                width:  _ctrl!.value.size.width,
                height: _ctrl!.value.size.height,
                child:  VideoPlayer(_ctrl!),
              ),
            ),
          ),

        // Spinner while loading or buffering
        if (!_initialized && !_videoError)
          const Center(child: SizedBox(width: 32, height: 32,
              child: CircularProgressIndicator(color: Colors.white70, strokeWidth: 2.5))),
        if (_initialized && _ctrl != null && _ctrl!.value.isBuffering)
          const Center(child: SizedBox(width: 24, height: 24,
              child: CircularProgressIndicator(color: Colors.white38, strokeWidth: 2))),

        if (_videoError)
          Center(child: GestureDetector(
            onTap: () {
              setState(() { _videoError = false; _initialized = false; });
              if (story.mediaUrl != null) _startVideo(story.mediaUrl!);
            },
            child: Column(mainAxisSize: MainAxisSize.min, children: const [
              Icon(Icons.refresh_rounded, color: Colors.white70, size: 48),
              SizedBox(height: 8),
              Text('Tap to retry', style: TextStyle(color: Colors.white54, fontSize: 13)),
            ]),
          )),
      ]);
    }

    // Image story
    return Stack(fit: StackFit.expand, children: [
      Container(decoration: _bgGradient),
      if (story.mediaUrl != null)
        CachedNetworkImage(
            imageUrl: story.mediaUrl!, fit: BoxFit.cover,
            fadeInDuration: const Duration(milliseconds: 100),
            placeholder: (_, __) => const SizedBox.shrink(),
            errorWidget: (_, __, ___) => const Center(
                child: Icon(Icons.broken_image_rounded, color: Colors.white38, size: 64))),
    ]);
  }

  // ── Actions ───────────────────────────────────────────────────────────────

  void _sendReaction(String emoji) {
    _repo.reactToStory(_currentStory.id, emoji);
    ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Reacted $emoji'),
            duration: const Duration(seconds: 1)));
  }

  void _sendComment() {
    final text = _commentCtrl.text.trim();
    if (text.isEmpty) return;
    _repo.commentOnStory(_currentStory.id, text);
    _commentCtrl.clear();
    FocusScope.of(context).unfocus();
    setState(() => _showCommentInput = false);
  }

  void _showStoryOptions(BuildContext context, CommunityStory story, StoryGroup group) {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1A1A2E),
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const SizedBox(height: 12),
          Container(width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 8),
          if (group.user.isMe) ListTile(
            leading: const Icon(Icons.bookmark_added_rounded, color: kOrange),
            title: const Text('Add to Highlight', style: TextStyle(color: Colors.white)),
            onTap: () {
              Navigator.pop(ctx);
              showModalBottomSheet(
                context: context, isScrollControlled: true,
                backgroundColor: Colors.transparent,
                builder: (_) => AddToHighlightSheet(
                    userId: group.user.id, contentType: 'story', contentId: story.id));
            },
          ),
          if (!group.user.isMe) ListTile(
            leading: const Icon(Icons.flag_rounded, color: Color(0xFFDC2626)),
            title: const Text('Report Story', style: TextStyle(color: Color(0xFFDC2626))),
            onTap: () {
              Navigator.pop(ctx);
              _repo.report('story', story.id, 'inappropriate');
              if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Report submitted. Thank you.')));
            },
          ),
          if (group.user.isMe) ListTile(
            leading: const Icon(Icons.delete_rounded, color: Colors.red),
            title: const Text('Delete Story', style: TextStyle(color: Colors.red)),
            onTap: () async {
              Navigator.pop(ctx);
              await _repo.deleteStory(story.id);
              if (context.mounted) Navigator.pop(context);
            },
          ),
          const SizedBox(height: 8),
        ]),
      ),
    );
  }

  void _showViewersSheet(BuildContext context, CommunityStory story) {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1A1B2E),
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => FutureBuilder<List<Map<String, dynamic>>>(
        future: _repo.getStoryViewers(story.id),
        builder: (ctx, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const SizedBox(height: 200,
                child: Center(child: CircularProgressIndicator(color: kOrange)));
          }
          final viewers = snap.data ?? [];
          return Column(mainAxisSize: MainAxisSize.min, children: [
            const SizedBox(height: 8),
            Container(width: 40, height: 4,
                decoration: BoxDecoration(color: Colors.white24,
                    borderRadius: BorderRadius.circular(2))),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Row(children: [
                const Icon(Icons.visibility_rounded, color: Colors.white, size: 20),
                const SizedBox(width: 8),
                Text('${viewers.length} ${viewers.length == 1 ? 'viewer' : 'viewers'}',
                    style: const TextStyle(color: Colors.white,
                        fontWeight: FontWeight.w700, fontSize: 16)),
              ])),
            const Divider(color: Colors.white12, height: 1),
            if (viewers.isEmpty)
              const Padding(padding: EdgeInsets.all(32),
                  child: Text('No viewers yet', style: TextStyle(color: Colors.white54)))
            else
              SizedBox(
                height: (viewers.length * 64.0).clamp(64, 300),
                child: ListView.builder(
                  itemCount: viewers.length,
                  itemBuilder: (_, i) {
                    final v = viewers[i];
                    final name = v['name']?.toString() ?? '';
                    return ListTile(
                      tileColor: const Color(0xFF1A1B2E),
                      leading: CircleNetImage(
                          url: v['avatar']?.toString(), size: 44, fallbackText: name),
                      title: Text(name, style: const TextStyle(
                          color: Colors.white, fontWeight: FontWeight.w600, fontSize: 15)),
                      subtitle: v['username'] != null
                          ? Text('@${v['username']}',
                              style: const TextStyle(color: Colors.white54, fontSize: 12))
                          : null,
                    );
                  },
                ),
              ),
            SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
          ]);
        },
      ),
    );
  }

  String _timeLabel(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inHours < 1) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    return '${diff.inDays}d ago';
  }
}

// ── Progress Bar ──────────────────────────────────────────────────────────────

class _ProgressBar extends StatefulWidget {
  final bool active;
  final bool done;
  final bool isVideo;
  final Duration imageDuration;
  final VideoPlayerController? ctrl;
  final VoidCallback? onDone;

  const _ProgressBar({
    super.key,
    required this.active,
    required this.done,
    required this.isVideo,
    required this.imageDuration,
    this.ctrl,
    this.onDone,
  });

  @override
  State<_ProgressBar> createState() => _ProgressBarState();
}

class _ProgressBarState extends State<_ProgressBar>
    with SingleTickerProviderStateMixin {
  AnimationController? _anim;

  @override
  void initState() {
    super.initState();
    if (!widget.active) return;
    if (widget.isVideo) {
      // Video progress is driven by the controller — never start a timer.
      // ctrl may be null while the video is still initializing; didUpdateWidget
      // will wire up the listener once the controller is available.
      if (widget.ctrl != null) widget.ctrl!.addListener(_rebuild);
    } else {
      _anim = AnimationController(vsync: this, duration: widget.imageDuration)
        ..addStatusListener((s) {
          if (s == AnimationStatus.completed) widget.onDone?.call();
        })
        ..forward();
    }
  }

  @override
  void didUpdateWidget(_ProgressBar old) {
    super.didUpdateWidget(old);
    if (widget.isVideo && old.ctrl != widget.ctrl) {
      old.ctrl?.removeListener(_rebuild);
      if (widget.ctrl != null) widget.ctrl!.addListener(_rebuild);
    }
  }

  void _rebuild() { if (mounted) setState(() {}); }

  @override
  void dispose() {
    widget.ctrl?.removeListener(_rebuild);
    _anim?.dispose();
    super.dispose();
  }

  double get _fraction {
    if (widget.isVideo) {
      final c = widget.ctrl;
      if (c == null || !c.value.isInitialized) return 0.0;
      final dur = c.value.duration.inMilliseconds;
      if (dur <= 0) return 0.0;
      return (c.value.position.inMilliseconds / dur).clamp(0.0, 1.0);
    }
    return _anim?.value ?? 0.0;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 2.5,
      decoration: BoxDecoration(
          color: Colors.white30, borderRadius: BorderRadius.circular(2)),
      child: widget.done
          ? Container(decoration: BoxDecoration(
              color: Colors.white, borderRadius: BorderRadius.circular(2)))
          : widget.active
              ? widget.isVideo
                  ? FractionallySizedBox(
                      widthFactor: _fraction,
                      alignment: Alignment.centerLeft,
                      child: Container(decoration: BoxDecoration(
                          color: Colors.white, borderRadius: BorderRadius.circular(2))))
                  : _anim != null
                      ? AnimatedBuilder(
                          animation: _anim!,
                          builder: (_, __) => FractionallySizedBox(
                            widthFactor: _anim!.value,
                            alignment: Alignment.centerLeft,
                            child: Container(decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(2)))))
                      : const SizedBox.shrink()
              : const SizedBox.shrink(),
    );
  }
}
