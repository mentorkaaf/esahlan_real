import 'dart:async';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:video_player/video_player.dart';
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
  bool _isOwnStory = false;

  @override
  void dispose() {
    _commentCtrl.dispose();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    super.dispose();
  }

  void _sendReaction(String emoji) {
    final storyId = _currentStory.id;
    _repo.reactToStory(storyId, emoji);
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Reacted $emoji'), duration: const Duration(seconds: 1)));
  }

  void _sendComment() {
    final text = _commentCtrl.text.trim();
    if (text.isEmpty) return;
    _repo.commentOnStory(_currentStory.id, text);
    _commentCtrl.clear();
    FocusScope.of(context).unfocus();
    setState(() => _showCommentInput = false);
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Comment sent'), duration: Duration(seconds: 1)));
  }

  @override
  void initState() {
    super.initState();
    _groupIndex = widget.initialGroupIndex;
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);
  }

  StoryGroup get _currentGroup => widget.groups[_groupIndex];
  CommunityStory get _currentStory => _currentGroup.stories[_storyIndex];

  void _nextStory() {
    if (_storyIndex < _currentGroup.stories.length - 1) {
      setState(() => _storyIndex++);
    } else if (_groupIndex < widget.groups.length - 1) {
      setState(() { _groupIndex++; _storyIndex = 0; });
    } else {
      Navigator.pop(context);
    }
  }

  void _prevStory() {
    if (_storyIndex > 0) {
      setState(() => _storyIndex--);
    } else if (_groupIndex > 0) {
      setState(() { _groupIndex--; _storyIndex = widget.groups[_groupIndex].stories.length - 1; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final group = _currentGroup;
    final story = _currentStory;
    final totalStories = group.stories.length;

    return Scaffold(
      backgroundColor: Colors.black,
      body: GestureDetector(
        onTapDown: (d) {
          final w = MediaQuery.of(context).size.width;
          if (d.globalPosition.dx < w * 0.35) {
            _prevStory();
          } else {
            _nextStory();
          }
        },
        child: Stack(fit: StackFit.expand, children: [
          _StoryContent(
            story: story,
            key: ValueKey('${_groupIndex}_$_storyIndex'),
            onFinished: _nextStory,
            onViewed: () => _repo.viewStory(story.id),
          ),
          // Progress bars
          Positioned(
            top: MediaQuery.of(context).padding.top + 8,
            left: 8, right: 8,
            child: Row(
              children: List.generate(totalStories, (i) => Expanded(
                child: Padding(
                  padding: EdgeInsets.symmetric(horizontal: 2),
                  child: _ProgressBar(
                    active: i == _storyIndex,
                    done: i < _storyIndex,
                    duration: story.type == 'video' ? AppConstants.storyVideoDuration : AppConstants.storyImageDuration,
                    key: ValueKey('bar_${_groupIndex}_${_storyIndex}_$i'),
                    onDone: i == _storyIndex ? _nextStory : null,
                  ),
                ),
              )),
            ),
          ),
          // Header
          Positioned(
            top: MediaQuery.of(context).padding.top + 24,
            left: 12, right: 12,
            child: Row(children: [
              CircleAvatar(
                radius: 18,
                
                backgroundImage: group.user.avatar != null ? CachedNetworkImageProvider(group.user.avatar!) : null,
                child: group.user.avatar == null
                    ? Text(group.user.name[0].toUpperCase(), style: TextStyle(fontWeight: FontWeight.bold))
                    : null,
              ),
              SizedBox(width: 8),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(group.user.name, style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
                  Text(_timeLabel(story.createdAt), style: TextStyle(color: Colors.white70, fontSize: 12)),
                ]),
              ),
              GestureDetector(
                onTap: () => Navigator.pop(context),
                child: Icon(Icons.close_rounded, color: Colors.white, size: 28),
              ),
            ]),
          ),
          // Bottom text if text story
          if (story.textContent != null && story.textContent!.isNotEmpty && story.type == 'text')
            Positioned(
              bottom: 80,
              left: 24, right: 24,
              child: Text(story.textContent!,
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w700, height: 1.4)),
            ),
          // Location
          if (story.location != null)
            Positioned(
              bottom: 60,
              left: 24,
              child: Row(children: [
                Icon(Icons.location_on_rounded, color: Colors.white70, size: 14),
                SizedBox(width: 4),
                Text(story.location!, style: TextStyle(color: Colors.white70, fontSize: 12)),
              ]),
            ),
          // Bottom bar — viewers for own stories, reactions for others
          Positioned(
            bottom: 0, left: 0, right: 0,
            child: Container(
              padding: EdgeInsets.only(left: 12, right: 12, top: 8, bottom: MediaQuery.of(context).viewInsets.bottom + 8),
              decoration: BoxDecoration(gradient: LinearGradient(begin: Alignment.bottomCenter, end: Alignment.topCenter, colors: [Colors.black87, Colors.transparent])),
              child: group.user.isMe
                  // Own story — show viewers
                  ? GestureDetector(
                      onTap: () => _showViewersSheet(context, story),
                      child: Container(
                        padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                        child: Row(children: [
                          Icon(Icons.visibility_rounded, color: Colors.white, size: 20),
                          SizedBox(width: 8),
                          Text('${story.viewsCount} ${story.viewsCount == 1 ? 'viewer' : 'viewers'}',
                            style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600)),
                          Spacer(),
                          Icon(Icons.keyboard_arrow_up_rounded, color: Colors.white70, size: 24),
                        ]),
                      ),
                    )
                  // Others' story — reactions + comment
                  : _showCommentInput
                    ? Row(children: [
                        Expanded(child: TextField(controller: _commentCtrl, autofocus: true,
                          style: TextStyle(color: Colors.white),
                          decoration: InputDecoration(hintText: 'Send message...', hintStyle: TextStyle(color: Colors.white54),
                            filled: true, fillColor: Colors.white24, contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none)),
                          onSubmitted: (_) => _sendComment())),
                        SizedBox(width: 8),
                        GestureDetector(onTap: _sendComment,
                          child: const CircleAvatar(radius: 18, backgroundColor: kOrange, child: Icon(Icons.send_rounded, color: Colors.white, size: 16))),
                      ])
                    : Row(children: [
                        Expanded(child: GestureDetector(
                          onTap: () => setState(() => _showCommentInput = true),
                          child: Container(
                            padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                            decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(24)),
                            child: Text('Send message...', style: TextStyle(color: Colors.white54, fontSize: 14)),
                          ),
                        )),
                        SizedBox(width: 8),
                        for (final emoji in ['❤️', '👍', '😂'])
                          GestureDetector(
                            onTap: () => _sendReaction(emoji),
                            child: Padding(padding: EdgeInsets.symmetric(horizontal: 4),
                              child: Text(emoji, style: TextStyle(fontSize: 26))),
                          ),
                      ]),
            ),
          ),
        ]),
      ),
    );
  }

  void _showViewersSheet(BuildContext context, CommunityStory story) {
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1A1B2E),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => FutureBuilder<List<Map<String, dynamic>>>(
        future: _repo.getStoryViewers(story.id),
        builder: (ctx, snap) {
          if (snap.connectionState == ConnectionState.waiting) {
            return SizedBox(height: 200, child: Center(child: CircularProgressIndicator(color: kOrange)));
          }
          final viewers = snap.data ?? [];
          return Container(
            color: const Color(0xFF1A1B2E),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              SizedBox(height: 8),
              Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.white24, borderRadius: BorderRadius.circular(2))),
              Padding(padding: EdgeInsets.all(16),
                child: Row(children: [
                  Icon(Icons.visibility_rounded, color: Colors.white, size: 20),
                  SizedBox(width: 8),
                  Text('${viewers.length} ${viewers.length == 1 ? 'viewer' : 'viewers'}',
                    style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16)),
                ])),
              Divider(color: Colors.white12, height: 1),
              if (viewers.isEmpty)
                Padding(padding: EdgeInsets.all(32), child: Text('No viewers yet', style: TextStyle(color: Colors.white54)))
              else
                SizedBox(
                  height: (viewers.length * 64.0).clamp(64, 300),
                  child: ListView.builder(
                    itemCount: viewers.length,
                    itemBuilder: (_, i) {
                      final v = viewers[i];
                      final name = v['name']?.toString() ?? '';
                      final avatar = v['avatar']?.toString();
                      return Container(
                        color: const Color(0xFF1A1B2E),
                        child: ListTile(
                          leading: CircleNetImage(url: avatar, size: 44, fallbackText: name),
                          title: Text(name, style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 15)),
                          subtitle: v['username'] != null
                            ? Text('@${v['username']}', style: TextStyle(color: Colors.white54, fontSize: 12))
                            : null,
                        ),
                      );
                    },
                  ),
                ),
              SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
            ]),
          );
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

class _StoryContent extends StatefulWidget {
  final CommunityStory story;
  final VoidCallback onFinished;
  final VoidCallback onViewed;
  const _StoryContent({super.key, required this.story, required this.onFinished, required this.onViewed});

  @override
  State<_StoryContent> createState() => _StoryContentState();
}

class _StoryContentState extends State<_StoryContent> {
  VideoPlayerController? _videoCtrl;
  bool _videoReady = false;
  bool _videoError = false;

  @override
  void initState() {
    super.initState();
    widget.onViewed();
    if (widget.story.type == 'video' && widget.story.mediaUrl != null) {
      _initVideo();
    }
  }

  Future<void> _initVideo() async {
    try {
      final ctrl = VideoPlayerController.networkUrl(
        Uri.parse(widget.story.mediaUrl!),
        httpHeaders: const {'Connection': 'keep-alive'},
      );
      await ctrl.initialize();
      if (!mounted) { ctrl.dispose(); return; }
      _videoCtrl = ctrl;
      _videoCtrl!.setLooping(false);
      _videoCtrl!.play();
      _videoCtrl!.addListener(() {
        if (_videoCtrl!.value.position >= _videoCtrl!.value.duration && _videoCtrl!.value.duration > Duration.zero) {
          widget.onFinished();
        }
      });
      setState(() => _videoReady = true);
    } catch (_) {
      if (mounted) setState(() => _videoError = true);
    }
  }

  @override
  void dispose() {
    _videoCtrl?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final story = widget.story;

    if (story.type == 'text') {
      return Container(
        color: story.bgColor != null ? Color(int.parse('0xFF${story.bgColor!.replaceFirst('#', '')}')) : kOrange,
        child: Center(
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: 32),
            child: Text(
              story.textContent ?? '',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w700, height: 1.4),
            ),
          ),
        ),
      );
    }

    if (story.type == 'video') {
      if (_videoReady && _videoCtrl != null) {
        return Center(child: AspectRatio(aspectRatio: _videoCtrl!.value.aspectRatio,
          child: VideoPlayer(_videoCtrl!)));
      }
      if (_videoError) {
        return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.error_outline_rounded, color: Colors.white54, size: 48),
          SizedBox(height: 8),
          Text('Video failed to load', style: TextStyle(color: Colors.white54)),
        ]));
      }
      return Center(child: SizedBox(width: 28, height: 28,
        child: CircularProgressIndicator(color: Colors.white54, strokeWidth: 2)));
    }

    if (story.mediaUrl != null) {
      return NetImage(
        url: story.mediaUrl!,
        fit: BoxFit.cover,
        placeholder: Center(child: CircularProgressIndicator(color: Colors.white)),
        errorWidget: Center(child: Icon(Icons.broken_image_rounded, color: Colors.white54, size: 64)),
      );
    }

    return Container(color: Colors.grey[900]);
  }
}

class _ProgressBar extends StatefulWidget {
  final bool active;
  final bool done;
  final Duration duration;
  final VoidCallback? onDone;
  const _ProgressBar({super.key, required this.active, required this.done, required this.duration, this.onDone});

  @override
  State<_ProgressBar> createState() => _ProgressBarState();
}

class _ProgressBarState extends State<_ProgressBar> with SingleTickerProviderStateMixin {
  AnimationController? _ctrl;

  @override
  void initState() {
    super.initState();
    if (widget.active) {
      _ctrl = AnimationController(vsync: this, duration: widget.duration)
        ..addStatusListener((status) {
          if (status == AnimationStatus.completed) widget.onDone?.call();
        })
        ..forward();
    }
  }

  @override
  void dispose() {
    _ctrl?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 2,
      decoration: BoxDecoration(
        color: Colors.white30,
        borderRadius: BorderRadius.circular(1),
      ),
      child: widget.done
          ? Container(decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(1)))
          : widget.active && _ctrl != null
              ? AnimatedBuilder(
                  animation: _ctrl!,
                  builder: (_, __) => FractionallySizedBox(
                    widthFactor: _ctrl!.value,
                    alignment: Alignment.centerLeft,
                    child: Container(decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(1))),
                  ),
                )
              : const SizedBox.shrink(),
    );
  }
}

