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

  // Video player — owned here so progress bar can access it
  Player? _player;
  VideoController? _videoCtrl;
  bool _hasFrame = false;
  bool _videoError = false;
  StreamSubscription? _completedSub;
  StreamSubscription? _videoParamsSub;
  StreamSubscription? _errorSub;

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
    if (_currentStory.type == 'video' && _currentStory.mediaUrl != null) {
      _initVideo(_currentStory.mediaUrl!);
    }
  }

  Future<void> _initVideo(String url) async {
    final player = Player();
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
      await player.open(Media(url));
      await player.setPlaylistMode(PlaylistMode.none);
      // Fallback frame reveal
      Future.delayed(const Duration(milliseconds: 800), () {
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
        // Thumbnail always visible immediately
        if (story.thumbnail != null)
          CachedNetworkImage(imageUrl: story.thumbnail!, fit: BoxFit.cover,
              placeholder: (_, __) => Container(color: Colors.black),
              errorWidget: (_, __, ___) => Container(color: Colors.black))
        else
          Container(color: Colors.black),

        if (_videoError)
          const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.error_outline_rounded, color: Colors.white54, size: 48),
            SizedBox(height: 8),
            Text('Video failed to load', style: TextStyle(color: Colors.white54)),
          ])),

        if (_videoCtrl != null)
          AnimatedOpacity(
            opacity: _hasFrame ? 1.0 : 0.0,
            duration: const Duration(milliseconds: 250),
            child: Video(controller: _videoCtrl!, controls: NoVideoControls, fit: BoxFit.contain),
          ),

        if (!_hasFrame && !_videoError)
          const Center(child: SizedBox(width: 28, height: 28,
              child: CircularProgressIndicator(color: Colors.white54, strokeWidth: 2))),
      ]);
    }

    // Image
    if (story.mediaUrl != null) {
      return CachedNetworkImage(imageUrl: story.mediaUrl!, fit: BoxFit.cover,
          placeholder: (_, __) => const Center(child: CircularProgressIndicator(color: Colors.white)),
          errorWidget: (_, __, ___) => const Center(
              child: Icon(Icons.broken_image_rounded, color: Colors.white54, size: 64)));
    }
    return Container(color: Colors.grey[900]);
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
