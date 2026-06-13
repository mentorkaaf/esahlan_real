import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
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
                  padding: const EdgeInsets.symmetric(horizontal: 2),
                  child: _ProgressBar(
                    active: i == _storyIndex,
                    done: i < _storyIndex,
                    duration: story.type == 'video' ? const Duration(seconds: 15) : const Duration(seconds: 5),
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
                backgroundColor: const Color(0xFFF0F2F5),
                backgroundImage: group.user.avatar != null ? CachedNetworkImageProvider(group.user.avatar!) : null,
                child: group.user.avatar == null
                    ? Text(group.user.name[0].toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold))
                    : null,
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(group.user.name, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
                  Text(_timeLabel(story.createdAt), style: const TextStyle(color: Colors.white70, fontSize: 12)),
                ]),
              ),
              GestureDetector(
                onTap: () => Navigator.pop(context),
                child: const Icon(Icons.close_rounded, color: Colors.white, size: 28),
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
                  style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w700, height: 1.4)),
            ),
          // Location
          if (story.location != null)
            Positioned(
              bottom: 60,
              left: 24,
              child: Row(children: [
                const Icon(Icons.location_on_rounded, color: Colors.white70, size: 14),
                const SizedBox(width: 4),
                Text(story.location!, style: const TextStyle(color: Colors.white70, fontSize: 12)),
              ]),
            ),
          // Bottom reactions + comment bar
          Positioned(
            bottom: 0, left: 0, right: 0,
            child: Container(
              padding: EdgeInsets.only(left: 12, right: 12, top: 8, bottom: MediaQuery.of(context).viewInsets.bottom + 8),
              decoration: const BoxDecoration(gradient: LinearGradient(begin: Alignment.bottomCenter, end: Alignment.topCenter, colors: [Colors.black87, Colors.transparent])),
              child: _showCommentInput
                  ? Row(children: [
                      Expanded(
                        child: TextField(
                          controller: _commentCtrl,
                          autofocus: true,
                          style: const TextStyle(color: Colors.white),
                          decoration: InputDecoration(
                            hintText: 'Write a comment...',
                            hintStyle: const TextStyle(color: Colors.white54),
                            filled: true, fillColor: Colors.white12,
                            contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(24), borderSide: BorderSide.none),
                          ),
                          onSubmitted: (_) => _sendComment(),
                        ),
                      ),
                      const SizedBox(width: 8),
                      GestureDetector(
                        onTap: _sendComment,
                        child: const CircleAvatar(backgroundColor: Color(0xFF140465), child: Icon(Icons.send_rounded, color: Colors.white, size: 18)),
                      ),
                    ])
                  : Row(children: [
                      for (final emoji in ['❤️', '😮', '😂', '😢', '🔥', '👏'])
                        GestureDetector(
                          onTap: () => _sendReaction(emoji),
                          child: Container(
                            margin: const EdgeInsets.symmetric(horizontal: 4),
                            padding: const EdgeInsets.all(8),
                            decoration: const BoxDecoration(color: Colors.white12, shape: BoxShape.circle),
                            child: Text(emoji, style: const TextStyle(fontSize: 22)),
                          ),
                        ),
                      const Spacer(),
                      GestureDetector(
                        onTap: () => setState(() => _showCommentInput = true),
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                          decoration: BoxDecoration(color: Colors.white12, borderRadius: BorderRadius.circular(24)),
                          child: const Row(children: [
                            Icon(Icons.chat_bubble_outline, color: Colors.white, size: 18),
                            SizedBox(width: 6),
                            Text('Comment', style: TextStyle(color: Colors.white, fontSize: 13)),
                          ]),
                        ),
                      ),
                    ]),
            ),
          ),
        ]),
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

  @override
  void initState() {
    super.initState();
    widget.onViewed();
    if (widget.story.type == 'video' && widget.story.mediaUrl != null) {
      _videoCtrl = VideoPlayerController.networkUrl(Uri.parse(widget.story.mediaUrl!))
        ..initialize().then((_) {
          if (mounted) {
            setState(() {});
            _videoCtrl!.play();
            _videoCtrl!.addListener(() {
              if (_videoCtrl!.value.position >= _videoCtrl!.value.duration && _videoCtrl!.value.duration > Duration.zero) {
                widget.onFinished();
              }
            });
          }
        });
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
            padding: const EdgeInsets.symmetric(horizontal: 32),
            child: Text(
              story.textContent ?? '',
              textAlign: TextAlign.center,
              style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w700, height: 1.4),
            ),
          ),
        ),
      );
    }

    if (story.type == 'video' && _videoCtrl != null && _videoCtrl!.value.isInitialized) {
      return Center(
        child: AspectRatio(
          aspectRatio: _videoCtrl!.value.aspectRatio,
          child: VideoPlayer(_videoCtrl!),
        ),
      );
    }

    if (story.mediaUrl != null) {
      return CachedNetworkImage(
        imageUrl: story.mediaUrl!,
        fit: BoxFit.cover,
        placeholder: (_, __) => const Center(child: CircularProgressIndicator(color: Colors.white)),
        errorWidget: (_, __, ___) => const Center(child: Icon(Icons.broken_image_rounded, color: Colors.white54, size: 64)),
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
          ? Container(decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(1)))
          : widget.active && _ctrl != null
              ? AnimatedBuilder(
                  animation: _ctrl!,
                  builder: (_, __) => FractionallySizedBox(
                    widthFactor: _ctrl!.value,
                    alignment: Alignment.centerLeft,
                    child: Container(decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(1))),
                  ),
                )
              : const SizedBox.shrink(),
    );
  }
}

