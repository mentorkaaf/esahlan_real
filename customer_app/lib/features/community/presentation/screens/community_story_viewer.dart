import 'dart:async';
import '../../../../core/theme/theme_x.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:media_kit/media_kit.dart';
import 'package:media_kit_video/media_kit_video.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../screens/community_shell.dart';
import '../services/video_pool.dart';
import 'highlight_viewer_screen.dart';

class StoryViewer extends StatefulWidget {
  final List<StoryGroup> groups;
  final int initialGroupIndex;
  const StoryViewer({super.key, required this.groups, required this.initialGroupIndex});

  @override
  State<StoryViewer> createState() => _StoryViewerState();
}

// Pre-buffered video ready to use instantly when the user swipes to next story
class _PreloadedVideo {
  final Player player;
  final VideoController ctrl;
  bool hasFrame = false;
  StreamSubscription? paramsSub;

  _PreloadedVideo(this.player, this.ctrl);

  void dispose() {
    paramsSub?.cancel();
    player.dispose();
  }
}

class _StoryViewerState extends State<StoryViewer> {
  late int _groupIndex;
  int _storyIndex = 0;
  final _repo = CommunityRepository();
  final _commentCtrl = TextEditingController();
  bool _showCommentInput = false;

  // Active video player
  Player? _player;
  VideoController? _videoCtrl;
  bool _hasFrame = false;
  bool _videoError = false;
  StreamSubscription? _completedSub;
  StreamSubscription? _videoParamsSub;
  StreamSubscription? _errorSub;

  // Preloaded next story (1-slot lookahead)
  _PreloadedVideo? _preloaded;
  String? _preloadedUrl;

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
    _disposePlayer();
    _preloaded?.dispose();
    _preloaded = null;
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    super.dispose();
  }

  void _disposePlayer() {
    _completedSub?.cancel(); _completedSub = null;
    _videoParamsSub?.cancel(); _videoParamsSub = null;
    _errorSub?.cancel(); _errorSub = null;
    _player?.dispose(); _player = null;
    _videoCtrl = null;
  }

  void _loadCurrentStory() {
    _repo.viewStory(_currentStory.id);
    _disposePlayer();
    setState(() { _hasFrame = false; _videoError = false; });
    final story = _currentStory;

    if (story.thumbnail != null) {
      precacheImage(CachedNetworkImageProvider(story.thumbnail!), context);
    } else if (story.mediaUrl != null && story.type == 'image') {
      precacheImage(CachedNetworkImageProvider(story.mediaUrl!), context);
    }

    if (story.type == 'video' && story.mediaUrl != null) {
      _initVideo(story.mediaUrl!);
    } else {
      // Image/text story: preload next video now
      _schedulePreload();
    }
  }

  /// Pre-buffer the next story video so it plays instantly on swipe.
  void _schedulePreload() {
    final nextUrl = _nextStoryVideoUrl();
    if (nextUrl == null || nextUrl == _preloadedUrl) return;

    // Cancel stale preload
    if (_preloadedUrl != nextUrl) {
      _preloaded?.dispose();
      _preloaded = null;
      _preloadedUrl = null;
    }

    _preloadedUrl = nextUrl;
    _doPreload(nextUrl);
  }

  String? _nextStoryVideoUrl() {
    if (_storyIndex < _currentGroup.stories.length - 1) {
      final s = _currentGroup.stories[_storyIndex + 1];
      if (s.type == 'video') return s.mediaUrl;
    } else if (_groupIndex < widget.groups.length - 1) {
      final s = widget.groups[_groupIndex + 1].stories.first;
      if (s.type == 'video') return s.mediaUrl;
    }
    return null;
  }

  Future<void> _doPreload(String url) async {
    try {
      final source = await VideoPool.resolveUrl(url);
      if (!mounted || _preloadedUrl != url) return;

      final player = Player(
        configuration: const PlayerConfiguration(bufferSize: 8 * 1024 * 1024),
      );
      final ctrl = VideoController(player);
      final pre = _PreloadedVideo(player, ctrl);

      pre.paramsSub = player.stream.videoParams.listen((vp) {
        if ((vp.w ?? 0) > 0) pre.hasFrame = true;
      });

      await player.open(Media(source));
      await player.setVolume(0);
      await player.pause(); // buffer but don't audibly play

      if (mounted && _preloadedUrl == url) {
        _preloaded = pre;
      } else {
        pre.dispose();
      }
    } catch (_) {}
  }

  Future<void> _initVideo(String url) async {
    // If we have this URL preloaded, use it directly — no network wait
    if (_preloaded != null && _preloadedUrl == url) {
      final pre = _preloaded!;
      _preloaded = null;
      _preloadedUrl = null;

      _player = pre.player;
      _videoCtrl = pre.ctrl;
      pre.paramsSub?.cancel();

      if (pre.hasFrame) {
        setState(() => _hasFrame = true);
      } else {
        _videoParamsSub = _player!.stream.videoParams.listen((vp) {
          if (!_hasFrame && (vp.w ?? 0) > 0 && mounted) setState(() => _hasFrame = true);
        });
        Future.delayed(const Duration(milliseconds: 300), () {
          if (mounted && !_hasFrame) setState(() => _hasFrame = true);
        });
      }

      _completedSub = _player!.stream.completed.listen((done) {
        if (done && mounted) _nextStory();
      });
      _errorSub = _player!.stream.error.listen((_) {
        if (mounted && !_hasFrame) setState(() => _videoError = true);
      });

      await _player!.setVolume(100);
      await _player!.play();
      _schedulePreload();
      return;
    }

    // Fresh init from network / disk cache
    final source = await VideoPool.resolveUrl(url);
    if (!mounted) return;

    final player = Player(
      configuration: const PlayerConfiguration(bufferSize: 8 * 1024 * 1024),
    );
    final ctrl = VideoController(player);
    _player = player;
    _videoCtrl = ctrl;

    _completedSub = player.stream.completed.listen((done) {
      if (done && mounted) _nextStory();
    });
    _videoParamsSub = player.stream.videoParams.listen((vp) {
      if (!_hasFrame && (vp.w ?? 0) > 0 && mounted) setState(() => _hasFrame = true);
    });
    _errorSub = player.stream.error.listen((_) {
      if (mounted && !_hasFrame) setState(() => _videoError = true);
    });

    try {
      await player.open(Media(source));
      await player.setPlaylistMode(PlaylistMode.none);
      // Start preloading next story immediately after this one opens
      _schedulePreload();
      // Fallback reveal — eliminate black screen after 500ms regardless
      Future.delayed(const Duration(milliseconds: 500), () {
        if (mounted && !_hasFrame && !_videoError) setState(() => _hasFrame = true);
      });
    } catch (_) {
      if (mounted) setState(() => _videoError = true);
    }
  }

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

  void _sendReaction(String emoji) {
    _repo.reactToStory(_currentStory.id, emoji);
    ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Reacted $emoji'), duration: const Duration(seconds: 1)));
  }

  void _sendComment() {
    final text = _commentCtrl.text.trim();
    if (text.isEmpty) return;
    _repo.commentOnStory(_currentStory.id, text);
    _commentCtrl.clear();
    FocusScope.of(context).unfocus();
    setState(() => _showCommentInput = false);
  }

  @override
  Widget build(BuildContext context) {
    final group = _currentGroup;
    final story = _currentStory;
    final isVideo = story.type == 'video';

    return Scaffold(
      backgroundColor: Colors.black,
      body: GestureDetector(
        onTapDown: (d) {
          if (_showCommentInput) { FocusScope.of(context).unfocus(); setState(() => _showCommentInput = false); return; }
          final w = MediaQuery.of(context).size.width;
          if (d.globalPosition.dx < w * 0.35) _prevStory(); else _nextStory();
        },
        child: Stack(fit: StackFit.expand, children: [
          // ── Story content ──
          _buildContent(story),

          // ── Progress bars ──
          Positioned(
            top: MediaQuery.of(context).padding.top + 8,
            left: 8, right: 8,
            child: Row(children: List.generate(_currentGroup.stories.length, (i) => Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 2),
                child: _ProgressBar(
                  key: ValueKey('bar_${_groupIndex}_${_storyIndex}_$i'),
                  active: i == _storyIndex,
                  done: i < _storyIndex,
                  isVideo: isVideo && i == _storyIndex,
                  imageDuration: const Duration(seconds: 5),
                  player: isVideo && i == _storyIndex ? _player : null,
                  onDone: i == _storyIndex ? _nextStory : null,
                ),
              ),
            ))),
          ),

          // ── Header ──
          Positioned(
            top: MediaQuery.of(context).padding.top + 24,
            left: 12, right: 12,
            child: Row(children: [
              CircleAvatar(
                radius: 18,
                backgroundImage: group.user.avatar != null ? CachedNetworkImageProvider(group.user.avatar!) : null,
                child: group.user.avatar == null ? Text(group.user.name[0].toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold)) : null,
              ),
              const SizedBox(width: 8),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(group.user.name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
                Text(_timeLabel(story.createdAt), style: const TextStyle(color: Colors.white70, fontSize: 12)),
              ])),
              GestureDetector(
                onTap: () => _showStoryOptions(context, story, group),
                child: const Icon(Icons.more_vert_rounded, color: Colors.white, size: 26),
              ),
              const SizedBox(width: 4),
              GestureDetector(onTap: () => Navigator.pop(context),
                  child: const Icon(Icons.close_rounded, color: Colors.white, size: 28)),
            ]),
          ),

          // ── Text story overlay ──
          if (story.textContent != null && story.textContent!.isNotEmpty && story.type == 'text')
            Positioned(bottom: 80, left: 24, right: 24,
              child: Text(story.textContent!, textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w700, height: 1.4))),

          if (story.location != null)
            Positioned(bottom: 60, left: 24,
              child: Row(children: [
                const Icon(Icons.location_on_rounded, color: Colors.white70, size: 14),
                const SizedBox(width: 4),
                Text(story.location!, style: const TextStyle(color: Colors.white70, fontSize: 12)),
              ])),

          // ── Bottom bar ──
          Positioned(bottom: 0, left: 0, right: 0,
            child: Container(
              padding: EdgeInsets.only(left: 12, right: 12, top: 8,
                  bottom: MediaQuery.of(context).viewInsets.bottom + 8),
              decoration: const BoxDecoration(gradient: LinearGradient(
                begin: Alignment.bottomCenter, end: Alignment.topCenter,
                colors: [Colors.black87, Colors.transparent])),
              child: group.user.isMe
                ? GestureDetector(
                    onTap: () => _showViewersSheet(context, story),
                    child: Padding(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                      child: Row(children: [
                        const Icon(Icons.visibility_rounded, color: Colors.white, size: 20),
                        const SizedBox(width: 8),
                        Text('${story.viewsCount} ${story.viewsCount == 1 ? 'viewer' : 'viewers'}',
                            style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600)),
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
                          hintText: 'Send message...', hintStyle: const TextStyle(color: Colors.white54),
                          filled: true, fillColor: Colors.white24,
                          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none)),
                        onSubmitted: (_) => _sendComment())),
                      const SizedBox(width: 8),
                      GestureDetector(onTap: _sendComment,
                          child: const CircleAvatar(radius: 18, backgroundColor: kOrange,
                              child: Icon(Icons.send_rounded, color: Colors.white, size: 16))),
                    ])
                  : Row(children: [
                      Expanded(child: GestureDetector(
                        onTap: () => setState(() => _showCommentInput = true),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                          decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(24)),
                          child: const Text('Send message...', style: TextStyle(color: Colors.white54, fontSize: 14)),
                        ),
                      )),
                      const SizedBox(width: 8),
                      for (final emoji in ['❤️', '👍', '😂'])
                        GestureDetector(onTap: () => _sendReaction(emoji),
                          child: Padding(padding: const EdgeInsets.symmetric(horizontal: 4),
                              child: Text(emoji, style: const TextStyle(fontSize: 26)))),
                    ]),
            )),
        ]),
      ),
    );
  }

  void _showStoryOptions(BuildContext context, CommunityStory story, StoryGroup group) {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1A1A2E),
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const SizedBox(height: 12),
          Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 8),
          if (group.user.isMe) ListTile(
            leading: const Icon(Icons.bookmark_added_rounded, color: kOrange),
            title: const Text('Add to Highlight', style: TextStyle(color: Colors.white)),
            onTap: () {
              Navigator.pop(ctx);
              showModalBottomSheet(
                context: context,
                isScrollControlled: true,
                backgroundColor: Colors.transparent,
                builder: (_) => AddToHighlightSheet(
                  userId: group.user.id,
                  contentType: 'story',
                  contentId: story.id,
                ),
              );
            },
          ),
          if (!group.user.isMe) ListTile(
            leading: const Icon(Icons.flag_rounded, color: Color(0xFFDC2626)),
            title: const Text('Report Story', style: TextStyle(color: Color(0xFFDC2626))),
            onTap: () {
              Navigator.pop(ctx);
              _repo.report('story', story.id, 'inappropriate');
              if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Report submitted. Thank you.')),
              );
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

  static const _bgGradient = BoxDecoration(gradient: LinearGradient(
    begin: Alignment.topLeft, end: Alignment.bottomRight,
    colors: [Color(0xFF1A0533), Color(0xFF0D1B2A)]));

  Widget _buildContent(CommunityStory story) {
    if (story.type == 'text') {
      final bg = story.bgColor != null
          ? Color(int.parse('0xFF${story.bgColor!.replaceFirst('#', '')}')) : kOrange;
      return Container(color: bg,
        child: Center(child: Padding(padding: const EdgeInsets.symmetric(horizontal: 32),
          child: Text(story.textContent ?? '', textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w700, height: 1.4)))));
    }

    if (story.type == 'video') {
      return Stack(fit: StackFit.expand, children: [
        // Dark gradient shown INSTANTLY — never black
        Container(decoration: _bgGradient),

        // Thumbnail fades in quickly from cache (pre-warmed in _loadCurrentStory)
        if (story.thumbnail != null)
          CachedNetworkImage(
            imageUrl: story.thumbnail!, fit: BoxFit.cover,
            fadeInDuration: const Duration(milliseconds: 100),
            placeholder: (_, __) => const SizedBox.shrink(),
            errorWidget: (_, __, ___) => const SizedBox.shrink()),

        // Video overlays once first frame ready
        if (_videoCtrl != null)
          AnimatedOpacity(
            opacity: _hasFrame ? 1.0 : 0.0,
            duration: const Duration(milliseconds: 200),
            child: Video(controller: _videoCtrl!, controls: NoVideoControls, fit: BoxFit.contain)),

        if (!_hasFrame && !_videoError)
          Positioned(bottom: 120, left: 0, right: 0,
            child: const Center(child: SizedBox(width: 22, height: 22,
                child: CircularProgressIndicator(color: Colors.white38, strokeWidth: 2)))),

        if (_videoError)
          const Center(child: Icon(Icons.play_circle_outline_rounded, color: Colors.white38, size: 56)),
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

  void _showViewersSheet(BuildContext context, CommunityStory story) {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1A1B2E),
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => FutureBuilder<List<Map<String, dynamic>>>(
        future: _repo.getStoryViewers(story.id),
        builder: (ctx, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return const SizedBox(height: 200, child: Center(child: CircularProgressIndicator(color: kOrange)));
          }
          final viewers = snap.data ?? [];
          return Container(color: const Color(0xFF1A1B2E),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const SizedBox(height: 8),
              Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(2))),
              Padding(padding: const EdgeInsets.all(16),
                child: Row(children: [
                  const Icon(Icons.visibility_rounded, color: Colors.white, size: 20),
                  const SizedBox(width: 8),
                  Text('${viewers.length} ${viewers.length == 1 ? 'viewer' : 'viewers'}',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16)),
                ])),
              const Divider(color: Colors.white12, height: 1),
              if (viewers.isEmpty)
                const Padding(padding: EdgeInsets.all(32), child: Text('No viewers yet', style: TextStyle(color: Colors.white54)))
              else
                SizedBox(
                  height: (viewers.length * 64.0).clamp(64, 300),
                  child: ListView.builder(
                    itemCount: viewers.length,
                    itemBuilder: (_, i) {
                      final v = viewers[i];
                      final name = v['name']?.toString() ?? '';
                      return Container(color: const Color(0xFF1A1B2E),
                        child: ListTile(
                          leading: CircleNetImage(url: v['avatar']?.toString(), size: 44, fallbackText: name),
                          title: Text(name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 15)),
                          subtitle: v['username'] != null
                              ? Text('@${v['username']}', style: const TextStyle(color: Colors.white54, fontSize: 12)) : null,
                        ),
                      );
                    },
                  ),
                ),
              SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
            ]));
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

// ── Progress Bar — syncs with Player stream for video ─────────────────────────

class _ProgressBar extends StatefulWidget {
  final bool active;
  final bool done;
  final bool isVideo;
  final Duration imageDuration;
  final Player? player;
  final VoidCallback? onDone;

  const _ProgressBar({
    super.key,
    required this.active,
    required this.done,
    required this.isVideo,
    required this.imageDuration,
    this.player,
    this.onDone,
  });

  @override
  State<_ProgressBar> createState() => _ProgressBarState();
}

class _ProgressBarState extends State<_ProgressBar> with SingleTickerProviderStateMixin {
  // Image / text stories use an AnimationController
  AnimationController? _ctrl;
  // Video stories stream position from player
  StreamSubscription? _posSub;
  StreamSubscription? _durSub;
  Duration _position = Duration.zero;
  Duration _duration = Duration.zero;

  @override
  void initState() {
    super.initState();
    if (!widget.active) return;
    if (widget.isVideo && widget.player != null) {
      _listenToPlayer(widget.player!);
    } else {
      _startTimer();
    }
  }

  void _startTimer() {
    _ctrl = AnimationController(vsync: this, duration: widget.imageDuration)
      ..addStatusListener((s) {
        if (s == AnimationStatus.completed) widget.onDone?.call();
      })
      ..forward();
  }

  void _listenToPlayer(Player player) {
    // Combine position + duration via separate subscriptions
    _posSub = player.stream.position.listen((pos) {
      if (!mounted) return;
      setState(() => _position = pos);
    });
    _durSub = player.stream.duration.listen((dur) {
      if (!mounted) return;
      setState(() => _duration = dur);
    });
  }

  @override
  void dispose() {
    _ctrl?.dispose();
    _posSub?.cancel();
    _durSub?.cancel();
    super.dispose();
  }

  double get _fraction {
    if (widget.isVideo) {
      if (_duration.inMilliseconds <= 0) return 0.0;
      return (_position.inMilliseconds / _duration.inMilliseconds).clamp(0.0, 1.0);
    }
    return _ctrl?.value ?? 0.0;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 2.5,
      decoration: BoxDecoration(color: Colors.white30, borderRadius: BorderRadius.circular(2)),
      child: widget.done
          ? Container(decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(2)))
          : widget.active
              ? widget.isVideo
                  // Video: rebuild on each position tick (setState in listener)
                  ? FractionallySizedBox(
                      widthFactor: _fraction,
                      alignment: Alignment.centerLeft,
                      child: Container(decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(2))),
                    )
                  // Image/text: AnimationController drives rebuild
                  : _ctrl != null
                      ? AnimatedBuilder(
                          animation: _ctrl!,
                          builder: (_, __) => FractionallySizedBox(
                            widthFactor: _ctrl!.value,
                            alignment: Alignment.centerLeft,
                            child: Container(decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(2))),
                          ))
                      : const SizedBox.shrink()
              : const SizedBox.shrink(),
    );
  }
}
