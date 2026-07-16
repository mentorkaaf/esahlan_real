import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:media_kit/media_kit.dart';
import 'package:media_kit_video/media_kit_video.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../screens/community_shell.dart';
import '../services/story_pool.dart';
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

  final _repo        = CommunityRepository();
  final _commentCtrl = TextEditingController();
  bool _showCommentInput = false;
  bool _paused           = false;
  bool _advancing        = false;

  final _pool = StoryPool.instance;
  late final Map<String, int> _urlToPoolIdx;

  // media_kit player/controller for the active video story
  Player?          _player;
  VideoController? _videoController;
  bool _ctrlIsOrphan = false;
  StreamSubscription<Duration>? _positionSub;

  // Guard stale async callbacks after story navigation
  String? _activeUrl;

  // ── Lifecycle ──────────────────────────────────────────────────────────────

  @override
  void initState() {
    super.initState();
    _groupIndex = widget.initialGroupIndex;
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
    _buildVideoIndex();
    _activateStory();
  }

  @override
  void dispose() {
    _detach();
    _commentCtrl.dispose();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    super.dispose();
  }

  // ── Video index ────────────────────────────────────────────────────────────

  void _buildVideoIndex() {
    final urlMap = <String, int>{};
    for (var i = 0; i < _pool.urls.length; i++) {
      urlMap[_pool.urls[i]] = i;
    }
    _urlToPoolIdx = urlMap;
  }

  // ── Story activation ───────────────────────────────────────────────────────

  void _activateStory() {
    _repo.viewStory(_currentStory.id);
    _advancing = false;
    _paused    = false;

    final story = _currentStory;

    // Precache thumbnail
    final thumb = story.thumbnail ?? (story.type == 'image' ? story.mediaUrl : null);
    if (thumb != null) precacheImage(CachedNetworkImageProvider(thumb), context);

    if (story.type == 'video') {
      final url = story.mediaUrl ?? '';
      if (url.isEmpty) { setState(() {}); return; }

      _activeUrl = url;
      final poolIdx = _urlToPoolIdx[url] ?? _pool.indexOf(url);

      if (poolIdx >= 0) _pool.advance(poolIdx);

      final pair = _pool.ready(url);
      if (pair != null) {
        unawaited(_attachAndPlay(pair.$1, pair.$2, url, isOrphan: false));
      } else if (poolIdx >= 0) {
        setState(() {});
        _pool.awaitReady(url).then((pair) {
          if (!mounted || _activeUrl != url) return;
          if (pair != null) {
            unawaited(_attachAndPlay(pair.$1, pair.$2, url, isOrphan: false));
          } else {
            if (mounted) setState(() {});
          }
        });
      } else {
        _activateOrphan(url);
      }
    } else {
      _detach();
      setState(() {});
    }
  }

  void _activateOrphan(String url) {
    _detach();
    setState(() {});
    final player     = Player(configuration: const PlayerConfiguration(bufferSize: 16 * 1024 * 1024));
    final controller = VideoController(player);
    player.open(Media(url), play: false).then((_) {
      if (!mounted || _activeUrl != url) { player.dispose(); return; }
      unawaited(_attachAndPlay(player, controller, url, isOrphan: true));
    }).catchError((_) {
      player.dispose();
      if (mounted && _activeUrl == url) setState(() {});
    });
  }

  Future<void> _attachAndPlay(Player player, VideoController controller, String url, {required bool isOrphan}) async {
    _detach();
    _player          = player;
    _videoController = controller;
    _ctrlIsOrphan    = isOrphan;

    // Await setVolume so audio is guaranteed at 100 before play() sends audio frames.
    await player.setVolume(100);
    if (!mounted || _activeUrl != url) return;
    player.seek(Duration.zero);
    player.play();

    _positionSub = player.stream.position.listen(_onPosition);
    if (mounted) setState(() {});
  }

  void _detach() {
    _positionSub?.cancel();
    _positionSub = null;

    if (_player != null) {
      if (_ctrlIsOrphan) {
        _player!.dispose();
      } else {
        // Return pool slot to idle
        _pool.returnSlot(_activeUrl ?? '');
      }
      _player          = null;
      _videoController = null;
      _ctrlIsOrphan    = false;
    }
  }

  void _onPosition(Duration pos) {
    if (_advancing || _paused) return;
    final player = _player;
    if (player == null) return;
    final dur = player.state.duration;
    if (dur > Duration.zero &&
        pos >= dur - const Duration(milliseconds: 400) &&
        !player.state.buffering) {
      _advancing = true;
      _nextStory();
    }
  }

  // ── Navigation ─────────────────────────────────────────────────────────────

  StoryGroup    get _currentGroup => widget.groups[_groupIndex];
  CommunityStory get _currentStory => _currentGroup.stories[_storyIndex];

  void _nextStory() {
    if (!mounted) return;
    _detach();
    if (_storyIndex < _currentGroup.stories.length - 1) {
      setState(() => _storyIndex++);
    } else if (_groupIndex < widget.groups.length - 1) {
      setState(() { _groupIndex++; _storyIndex = 0; });
    } else {
      Navigator.pop(context);
      return;
    }
    _activateStory();
  }

  void _prevStory() {
    if (!mounted) return;
    _detach();
    if (_storyIndex > 0) {
      setState(() => _storyIndex--);
    } else if (_groupIndex > 0) {
      setState(() {
        _groupIndex--;
        _storyIndex = widget.groups[_groupIndex].stories.length - 1;
      });
    } else return;
    _activateStory();
  }

  // ── Build ──────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final group   = _currentGroup;
    final story   = _currentStory;
    final isVideo = story.type == 'video';
    final ready   = _videoController != null;

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
          if (d.localPosition.dx < w * 0.35) _prevStory(); else _nextStory();
        },
        onLongPressStart: (_) {
          _paused = true;
          _player?.pause();
        },
        onLongPressEnd: (_) {
          _paused = false;
          if (ready) _player?.play();
        },
        child: Stack(fit: StackFit.expand, children: [

          // ── Story content ──────────────────────────────────────────────────
          _buildContent(story, ready),

          // ── Subtle loading stripe (only while video initialises) ───────────
          if (isVideo && !ready && story.mediaUrl?.isNotEmpty == true)
            Positioned(
              bottom: 0, left: 0, right: 0,
              child: LinearProgressIndicator(
                backgroundColor: Colors.transparent,
                valueColor: AlwaysStoppedAnimation(Colors.white.withValues(alpha: 0.35)),
              ),
            ),

          // ── Progress bars ──────────────────────────────────────────────────
          Positioned(
            top: MediaQuery.of(context).padding.top + 6,
            left: 8, right: 8,
            child: Row(
              children: List.generate(_currentGroup.stories.length, (i) => Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 2),
                  child: _ProgressBar(
                    key: ValueKey('bar_${_groupIndex}_${_storyIndex}_$i'),
                    active:        i == _storyIndex,
                    done:          i < _storyIndex,
                    isVideo:       isVideo && i == _storyIndex,
                    imageDuration: const Duration(seconds: 5),
                    player:        isVideo && i == _storyIndex ? _player : null,
                    onDone:        i == _storyIndex ? _nextStory : null,
                  ),
                ),
              )),
            ),
          ),

          // ── Header ─────────────────────────────────────────────────────────
          Positioned(
            top: MediaQuery.of(context).padding.top + 22,
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
              Expanded(child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(group.user.name,
                      style: const TextStyle(color: Colors.white,
                          fontWeight: FontWeight.w700, fontSize: 14)),
                  Text(_timeLabel(story.createdAt),
                      style: const TextStyle(color: Colors.white70, fontSize: 12)),
                ],
              )),
              GestureDetector(
                onTap: () => _showStoryOptions(context, story, group),
                child: const Icon(Icons.more_vert_rounded, color: Colors.white, size: 26)),
              const SizedBox(width: 4),
              GestureDetector(
                onTap: () => Navigator.pop(context),
                child: const Icon(Icons.close_rounded, color: Colors.white, size: 28)),
            ]),
          ),

          // ── Location ───────────────────────────────────────────────────────
          if (story.location != null)
            Positioned(bottom: 68, left: 20,
              child: Row(children: [
                const Icon(Icons.location_on_rounded, color: Colors.white70, size: 14),
                const SizedBox(width: 4),
                Text(story.location!,
                    style: const TextStyle(color: Colors.white70, fontSize: 12)),
              ])),

          // ── Bottom action bar ──────────────────────────────────────────────
          Positioned(
            bottom: 0, left: 0, right: 0,
            child: Container(
              padding: EdgeInsets.only(
                left: 12, right: 12, top: 8,
                bottom: MediaQuery.of(context).viewInsets.bottom +
                    MediaQuery.of(context).padding.bottom + 8,
              ),
              decoration: const BoxDecoration(gradient: LinearGradient(
                  begin: Alignment.bottomCenter, end: Alignment.topCenter,
                  colors: [Colors.black87, Colors.transparent])),
              child: group.user.isMe
                  ? GestureDetector(
                      onTap: () => _showViewersSheet(context, story),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 8),
                        child: Row(children: [
                          const Icon(Icons.visibility_rounded, color: Colors.white, size: 20),
                          const SizedBox(width: 8),
                          Text(
                            '${story.viewsCount} '
                            '${story.viewsCount == 1 ? 'viewer' : 'viewers'}',
                            style: const TextStyle(color: Colors.white, fontSize: 14,
                                fontWeight: FontWeight.w600)),
                          const Spacer(),
                          const Icon(Icons.keyboard_arrow_up_rounded,
                              color: Colors.white70, size: 24),
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
                              contentPadding: const EdgeInsets.symmetric(
                                  horizontal: 16, vertical: 10),
                              border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(24),
                                  borderSide: BorderSide.none)),
                            onSubmitted: (_) => _sendComment())),
                          const SizedBox(width: 8),
                          GestureDetector(
                            onTap: _sendComment,
                            child: const CircleAvatar(
                                radius: 18, backgroundColor: kOrange,
                                child: Icon(Icons.send_rounded,
                                    color: Colors.white, size: 16))),
                        ])
                      : Row(children: [
                          Expanded(child: GestureDetector(
                            onTap: () => setState(() => _showCommentInput = true),
                            child: Container(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 16, vertical: 10),
                              decoration: BoxDecoration(
                                  color: Colors.white24,
                                  borderRadius: BorderRadius.circular(24)),
                              child: const Text('Send message...',
                                  style: TextStyle(color: Colors.white54,
                                      fontSize: 14))),
                          )),
                          const SizedBox(width: 8),
                          for (final emoji in ['❤️', '👍', '😂'])
                            GestureDetector(
                              onTap: () => _sendReaction(emoji),
                              child: Padding(
                                padding: const EdgeInsets.symmetric(horizontal: 4),
                                child: Text(emoji,
                                    style: const TextStyle(fontSize: 26)))),
                        ]),
            )),
        ]),
      ),
    );
  }

  // ── Content layers ─────────────────────────────────────────────────────────

  static const _bgGradient = BoxDecoration(gradient: LinearGradient(
      begin: Alignment.topLeft, end: Alignment.bottomRight,
      colors: [Color(0xFF1A0533), Color(0xFF0D1B2A)]));

  Color _userAccent(int userId) {
    const palette = [
      Color(0xFF1A237E), Color(0xFF004D40), Color(0xFF311B92),
      Color(0xFF880E4F), Color(0xFF1B5E20), Color(0xFF0D47A1),
      Color(0xFF4A148C), Color(0xFF212121),
    ];
    return palette[userId % palette.length];
  }

  Widget _buildContent(CommunityStory story, bool ready) {
    if (story.type == 'text') {
      final bg = story.bgColor != null
          ? Color(int.parse('0xFF${story.bgColor!.replaceFirst('#', '')}'))
          : kOrange;
      return Container(
        color: bg,
        child: Center(child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 32),
          child: Text(story.textContent ?? '',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white, fontSize: 26,
                  fontWeight: FontWeight.w700, height: 1.4)))),
      );
    }

    if (story.type == 'video') {
      final group = _currentGroup;
      return Stack(fit: StackFit.expand, children: [
        // Accent background — never pure black while loading
        Container(color: _userAccent(group.user.id)),

        // Avatar placeholder while video loads
        if (!ready && story.thumbnail == null)
          Center(child: CircleAvatar(
            radius: 48,
            backgroundImage: group.user.avatar != null
                ? CachedNetworkImageProvider(group.user.avatar!) : null,
            backgroundColor: Colors.white12,
            child: group.user.avatar == null
                ? Text(group.user.name[0].toUpperCase(),
                    style: const TextStyle(fontSize: 36,
                        fontWeight: FontWeight.bold, color: Colors.white))
                : null,
          )),

        // Thumbnail — shows instantly from cache while player opens
        if (story.thumbnail != null)
          CachedNetworkImage(
            imageUrl: story.thumbnail!,
            fit: BoxFit.cover,
            fadeInDuration: Duration.zero,
            placeholder: (_, __) => const SizedBox.shrink(),
            errorWidget: (_, __, ___) => const SizedBox.shrink(),
          ),

        // media_kit Video widget — hardware-decoded, appears over thumbnail
        if (ready)
          AnimatedOpacity(
            opacity: 1.0,
            duration: const Duration(milliseconds: 80),
            child: Video(
              controller: _videoController!,
              fit: BoxFit.cover,
              controls: NoVideoControls,
            ),
          ),
      ]);
    }

    // Image story
    return Stack(fit: StackFit.expand, children: [
      Container(decoration: _bgGradient),
      if (story.mediaUrl != null)
        CachedNetworkImage(
          imageUrl: story.mediaUrl!,
          fit: BoxFit.cover,
          fadeInDuration: const Duration(milliseconds: 80),
          placeholder: (_, __) => const SizedBox.shrink(),
          errorWidget: (_, __, ___) => const Center(
              child: Icon(Icons.broken_image_rounded,
                  color: Colors.white38, size: 64)),
        ),
    ]);
  }

  // ── Actions ────────────────────────────────────────────────────────────────

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

  void _showStoryOptions(BuildContext ctx, CommunityStory story, StoryGroup group) {
    showModalBottomSheet(
      context: ctx,
      backgroundColor: const Color(0xFF1A1A2E),
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (c) => SafeArea(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const SizedBox(height: 12),
          Container(width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.white24,
                  borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 8),
          if (group.user.isMe) ListTile(
            leading: const Icon(Icons.bookmark_added_rounded, color: kOrange),
            title: const Text('Add to Highlight',
                style: TextStyle(color: Colors.white)),
            onTap: () {
              Navigator.pop(c);
              showModalBottomSheet(
                context: ctx, isScrollControlled: true,
                backgroundColor: Colors.transparent,
                builder: (_) => AddToHighlightSheet(
                    userId: group.user.id,
                    contentType: 'story',
                    contentId: story.id));
            },
          ),
          if (!group.user.isMe) ListTile(
            leading: const Icon(Icons.flag_rounded, color: Color(0xFFDC2626)),
            title: const Text('Report Story',
                style: TextStyle(color: Color(0xFFDC2626))),
            onTap: () {
              Navigator.pop(c);
              _repo.report('story', story.id, 'inappropriate');
              if (ctx.mounted) ScaffoldMessenger.of(ctx).showSnackBar(
                  const SnackBar(content: Text('Report submitted. Thank you.')));
            },
          ),
          if (group.user.isMe) ListTile(
            leading: const Icon(Icons.delete_rounded, color: Colors.red),
            title: const Text('Delete Story', style: TextStyle(color: Colors.red)),
            onTap: () async {
              Navigator.pop(c);
              await _repo.deleteStory(story.id);
              if (ctx.mounted) Navigator.pop(ctx);
            },
          ),
          const SizedBox(height: 8),
        ]),
      ),
    );
  }

  void _showViewersSheet(BuildContext ctx, CommunityStory story) {
    showModalBottomSheet(
      context: ctx,
      backgroundColor: const Color(0xFF1A1B2E),
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => FutureBuilder<List<Map<String, dynamic>>>(
        future: _repo.getStoryViewers(story.id),
        builder: (c, snap) {
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
                  child: Text('No viewers yet',
                      style: TextStyle(color: Colors.white54)))
            else
              SizedBox(
                height: (viewers.length * 64.0).clamp(64, 300),
                child: ListView.builder(
                  itemCount: viewers.length,
                  itemBuilder: (_, i) {
                    final v    = viewers[i];
                    final name = v['name']?.toString() ?? '';
                    return ListTile(
                      tileColor: const Color(0xFF1A1B2E),
                      leading: CircleNetImage(
                          url: v['avatar']?.toString(),
                          size: 44, fallbackText: name),
                      title: Text(name, style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w600, fontSize: 15)),
                      subtitle: v['username'] != null
                          ? Text('@${v['username']}',
                              style: const TextStyle(
                                  color: Colors.white54, fontSize: 12))
                          : null,
                    );
                  },
                ),
              ),
            SizedBox(height: MediaQuery.of(ctx).padding.bottom + 8),
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

// ── Progress Bar ───────────────────────────────────────────────────────────────

class _ProgressBar extends StatefulWidget {
  final bool          active;
  final bool          done;
  final bool          isVideo;
  final Duration      imageDuration;
  final Player?       player;
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

class _ProgressBarState extends State<_ProgressBar>
    with SingleTickerProviderStateMixin {
  AnimationController? _anim;
  StreamSubscription<Duration>? _posSub;
  Duration _pos = Duration.zero;
  Duration _dur = Duration.zero;

  @override
  void initState() {
    super.initState();
    if (!widget.active) return;
    if (widget.isVideo && widget.player != null) {
      _subscribePlayer(widget.player!);
    } else {
      _startTimer();
    }
  }

  @override
  void didUpdateWidget(_ProgressBar old) {
    super.didUpdateWidget(old);
    if (widget.isVideo && old.player != widget.player) {
      _posSub?.cancel();
      _posSub = null;
      if (widget.player != null && widget.active) _subscribePlayer(widget.player!);
    }
  }

  void _subscribePlayer(Player player) {
    _dur = player.state.duration;
    _pos = player.state.position;
    _posSub = player.stream.position.listen((pos) {
      if (!mounted) return;
      setState(() {
        _pos = pos;
        _dur = player.state.duration;
      });
    });
  }

  void _startTimer() {
    _anim = AnimationController(vsync: this, duration: widget.imageDuration)
      ..addListener(() { if (mounted) setState(() {}); })
      ..addStatusListener((s) {
        if (s == AnimationStatus.completed) widget.onDone?.call();
      })
      ..forward();
  }

  @override
  void dispose() {
    _posSub?.cancel();
    _anim?.dispose();
    super.dispose();
  }

  double get _fraction {
    if (widget.isVideo) {
      if (_dur <= Duration.zero) return 0.0;
      return (_pos.inMilliseconds / _dur.inMilliseconds).clamp(0.0, 1.0);
    }
    return _anim?.value ?? 0.0;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 2.5,
      decoration: BoxDecoration(
          color: Colors.white30,
          borderRadius: BorderRadius.circular(2)),
      child: widget.done
          ? Container(decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(2)))
          : widget.active
              ? FractionallySizedBox(
                  widthFactor: _fraction,
                  alignment: Alignment.centerLeft,
                  child: Container(decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(2))))
              : const SizedBox.shrink(),
    );
  }
}
