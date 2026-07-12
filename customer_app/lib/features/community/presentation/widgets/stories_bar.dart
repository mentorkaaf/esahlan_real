import 'dart:math' show pi;
import 'dart:typed_data';
import 'package:video_compress/video_compress.dart';
import '../../../../core/theme/theme_x.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:media_kit/media_kit.dart';
import 'package:media_kit_video/media_kit_video.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import '../screens/community_shell.dart';
import '../screens/community_story_viewer.dart';

class StoriesBar extends ConsumerWidget {
  final List<StoryGroup> groups;
  const StoriesBar({super.key, required this.groups});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final myProfile = ref.watch(communityMyProfileProvider);

    return SizedBox(
      height: 200,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        itemCount: groups.length + 1,
        itemBuilder: (ctx, i) {
          if (i == 0) {
            return _CreateStoryCard(
              avatar: myProfile.valueOrNull?.avatar,
              coverPhoto: myProfile.valueOrNull?.coverPhoto,
            );
          }
          final group = groups[i - 1];
          // Only first 3 video story cards get live Player preview (memory limit)
          final enableVideoPreview = i <= 3;
          return _StoryCard(
            group: group,
            enableVideoPreview: enableVideoPreview,
            onTap: () => Navigator.push(ctx,
              MaterialPageRoute(builder: (_) => StoryViewer(groups: groups, initialGroupIndex: i - 1))),
          );
        },
      ),
    );
  }
}

class _CreateStoryCard extends ConsumerWidget {
  final String? avatar;
  final String? coverPhoto;
  const _CreateStoryCard({this.avatar, this.coverPhoto});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return GestureDetector(
      onTap: () async {
        final created = await Navigator.push<bool>(context,
          MaterialPageRoute(builder: (_) => const _CreateStoryScreen()));
        if (created == true) ref.invalidate(communityStoriesProvider);
      },
      child: Container(
        width: 120,
        margin: EdgeInsets.only(right: 8),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          color: const Color(0xFFF3F4F6),
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(fit: StackFit.expand, children: [
          // Cover/avatar as background
          if (avatar != null)
            CachedNetworkImage(imageUrl: avatar!, fit: BoxFit.cover, color: Colors.black26, colorBlendMode: BlendMode.darken)
          else
            Container(color: const Color(0xFF1A1B2E)),

          // Gradient overlay
          Container(decoration: BoxDecoration(gradient: LinearGradient(
            begin: Alignment.topCenter, end: Alignment.bottomCenter,
            colors: [Colors.transparent, Colors.black.withValues(alpha: 0.6)],
            stops: const [0.4, 1.0],
          ))),

          // Blue "+" circle in center
          Positioned(left: 0, right: 0, bottom: 40,
            child: Center(child: Container(
              width: 36, height: 36,
              decoration: BoxDecoration(
                color: kOrange, shape: BoxShape.circle,
                border: Border.all(color: Colors.white, width: 3),
              ),
              child: Icon(Icons.add_rounded, color: Colors.white, size: 20),
            )),
          ),

          // "Create story" text
          Positioned(left: 8, right: 8, bottom: 10,
            child: Text('Create story',
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
          ),
        ]),
      ),
    );
  }
}

class _StoryCard extends StatelessWidget {
  final StoryGroup group;
  final VoidCallback onTap;
  final bool enableVideoPreview;
  const _StoryCard({required this.group, required this.onTap, this.enableVideoPreview = true});

  @override
  Widget build(BuildContext context) {
    final avatar = group.user.avatar;
    final firstStory = group.stories.isNotEmpty ? group.stories.first : null;
    final hasUnviewed = !group.allViewed;
    final isVideo = firstStory?.type == 'video';

    final card = Container(
        width: 120,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(12),
          color: const Color(0xFF1A1B2E),
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(fit: StackFit.expand, children: [
          // Story preview background
          if (isVideo && firstStory?.mediaUrl != null && enableVideoPreview)
            _VideoStoryPreview(
              videoUrl: firstStory!.mediaUrl!,
              thumbnailUrl: firstStory.thumbnail,
            )
          else if (isVideo && firstStory?.thumbnail != null)
            CachedNetworkImage(imageUrl: firstStory!.thumbnail!, fit: BoxFit.cover,
              memCacheHeight: 400, fadeInDuration: const Duration(milliseconds: 150),
              placeholder: (_, __) => Container(decoration: const BoxDecoration(gradient: LinearGradient(
                begin: Alignment.topLeft, end: Alignment.bottomRight,
                colors: [Color(0xFF2A1B3D), Color(0xFF1A1B2E)]))),
              errorWidget: (_, __, ___) => Container(color: const Color(0xFF1A1B2E)))
          else if (firstStory?.type == 'image' && firstStory?.mediaUrl != null)
            CachedNetworkImage(imageUrl: firstStory!.mediaUrl!, fit: BoxFit.cover,
              memCacheHeight: 400,
              fadeInDuration: const Duration(milliseconds: 150),
              placeholder: (_, __) => Container(decoration: const BoxDecoration(gradient: LinearGradient(
                begin: Alignment.topLeft, end: Alignment.bottomRight,
                colors: [Color(0xFF2A1B3D), Color(0xFF1A1B2E)]))),
              errorWidget: (_, __, ___) => Container(color: const Color(0xFF1A1B2E)))
          else if (firstStory?.type == 'text')
            Container(
              color: _parseColor(firstStory?.bgColor),
              padding: const EdgeInsets.all(12),
              alignment: Alignment.center,
              child: Text(firstStory?.textContent ?? '',
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 11),
                maxLines: 5, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
            )
          else
            Container(color: const Color(0xFF1A1B2E)),

          // Gradient
          Container(decoration: BoxDecoration(gradient: LinearGradient(
            begin: Alignment.topCenter, end: Alignment.bottomCenter,
            colors: [Colors.black.withValues(alpha: 0.3), Colors.transparent, Colors.black.withValues(alpha: 0.6)],
            stops: const [0.0, 0.3, 1.0],
          ))),

          // User avatar at top-left with ring
          Positioned(top: 8, left: 8,
            child: Container(
              padding: const EdgeInsets.all(2),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: hasUnviewed ? kOrange : const Color(0xFF6B7280),
                  width: hasUnviewed ? 2.5 : 1.5),
              ),
              child: CircleAvatar(
                radius: 16,
                backgroundImage: avatar != null ? CachedNetworkImageProvider(avatar) : null,
                backgroundColor: const Color(0xFF374151),
                child: avatar == null
                  ? Text(group.user.name[0].toUpperCase(),
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 12))
                  : null,
              ),
            ),
          ),

          // User name at bottom
          Positioned(left: 8, right: 8, bottom: 10,
            child: Text(group.user.name,
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12,
                shadows: [Shadow(color: Colors.black54, blurRadius: 4)]),
              maxLines: 2, overflow: TextOverflow.ellipsis),
          ),
        ]),
      );

    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 120,
        margin: const EdgeInsets.only(right: 8),
        child: hasUnviewed
            ? _AnimGradBorder(borderRadius: 14, child: card)
            : ClipRRect(
                borderRadius: BorderRadius.circular(14),
                child: card,
              ),
      ),
    );
  }

  Color _parseColor(String? hex) {
    if (hex == null || hex.length < 6) return const Color(0xFF140465);
    hex = hex.replaceAll('#', '');
    return Color(int.parse('FF$hex', radix: 16));
  }
}

// ── Animated gradient border for unviewed stories ─────────────────────────────

class _AnimGradBorder extends StatefulWidget {
  final Widget child;
  final double borderRadius;
  const _AnimGradBorder({required this.child, this.borderRadius = 14});

  @override
  State<_AnimGradBorder> createState() => _AnimGradBorderState();
}

class _AnimGradBorderState extends State<_AnimGradBorder> with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(seconds: 3))..repeat();
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _ctrl,
      builder: (_, child) => CustomPaint(
        painter: _GradBorderPainter(progress: _ctrl.value, radius: widget.borderRadius),
        child: child,
      ),
      child: Padding(
        padding: const EdgeInsets.all(2.5),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(widget.borderRadius - 2.5),
          child: widget.child,
        ),
      ),
    );
  }
}

class _GradBorderPainter extends CustomPainter {
  final double progress;
  final double radius;
  const _GradBorderPainter({required this.progress, required this.radius});

  static const _colors = [
    Color(0xFFFF3B3B), // red
    Color(0xFFFF7A00), // eSahlan orange
    Color(0xFFFFCC00), // yellow
    Color(0xFF00C853), // green
    Color(0xFF2979FF), // blue
    Color(0xFFAA00FF), // purple
    Color(0xFFFF3B3B), // back to red
  ];

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final paint = Paint()
      ..shader = SweepGradient(
        colors: _colors,
        transform: GradientRotation(progress * 2 * pi),
      ).createShader(rect)
      ..strokeWidth = 2.5
      ..style = PaintingStyle.stroke;

    canvas.drawRRect(
      RRect.fromRectAndRadius(rect.deflate(1.25), Radius.circular(radius)),
      paint,
    );
  }

  @override
  bool shouldRepaint(_GradBorderPainter old) => old.progress != progress;
}

// ── Animated video preview for story cards ────────────────────────────────────

class _VideoStoryPreview extends StatefulWidget {
  final String videoUrl;
  final String? thumbnailUrl;
  const _VideoStoryPreview({required this.videoUrl, this.thumbnailUrl});

  @override
  State<_VideoStoryPreview> createState() => _VideoStoryPreviewState();
}

// Global limit — at most 2 story preview players open at the same time.
// With many video stories in the bar, this prevents 10+ simultaneous decoders.
int _activeStoryPreviews = 0;

class _VideoStoryPreviewState extends State<_VideoStoryPreview> {
  Player? _player;
  VideoController? _ctrl;
  bool _hasFrame = false;
  bool _claimed = false;

  @override
  void initState() {
    super.initState();
    if (_activeStoryPreviews < 2) {
      _claimed = true;
      _activeStoryPreviews++;
      _init();
    }
    // If limit is reached, we just show the static thumbnail (no video).
  }

  Future<void> _init() async {
    final player = Player();
    final ctrl = VideoController(player);
    if (!mounted) return;
    setState(() { _player = player; _ctrl = ctrl; });
    player.stream.videoParams.listen((vp) {
      if (!_hasFrame && (vp.w ?? 0) > 0 && mounted) setState(() => _hasFrame = true);
    });
    await player.open(Media(widget.videoUrl));
    await player.setVolume(0);
    // Play briefly for motion preview, then pause
    Future.delayed(const Duration(milliseconds: 1500), () async {
      if (!mounted) return;
      if (!_hasFrame) setState(() => _hasFrame = true);
      await player.pause();
    });
  }

  @override
  void dispose() {
    if (_claimed) _activeStoryPreviews--;
    _player?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(fit: StackFit.expand, children: [
      // Gradient placeholder shown instantly while thumbnail network-loads
      Container(decoration: const BoxDecoration(gradient: LinearGradient(
        begin: Alignment.topLeft, end: Alignment.bottomRight,
        colors: [Color(0xFF2A1B3D), Color(0xFF1A1B2E)]))),

      // Thumbnail shown immediately from cache (or loads over gradient)
      if (widget.thumbnailUrl != null)
        CachedNetworkImage(imageUrl: widget.thumbnailUrl!, fit: BoxFit.cover,
          fadeInDuration: const Duration(milliseconds: 150),
          placeholder: (_, __) => const SizedBox.shrink(),
          errorWidget: (_, __, ___) => const SizedBox.shrink()),

      // Video crossfades in once first frame ready
      if (_ctrl != null)
        AnimatedOpacity(
          opacity: _hasFrame ? 1.0 : 0.0,
          duration: const Duration(milliseconds: 300),
          child: Video(controller: _ctrl!, controls: NoVideoControls, fit: BoxFit.cover),
        ),
    ]);
  }
}

// ── Create Story Screen ────────────────────────────────────────────────────────

class _CreateStoryScreen extends StatefulWidget {
  const _CreateStoryScreen();
  @override
  State<_CreateStoryScreen> createState() => _CreateStoryScreenState();
}

class _CreateStoryScreenState extends State<_CreateStoryScreen> {
  final _repo = CommunityRepository();
  final _picker = ImagePicker();
  final _textCtrl = TextEditingController();
  XFile? _mediaFile;
  String _storyType = 'text';
  bool _posting = false;
  bool _trimming = false;
  Color _bgColor = const Color(0xFF140465);

  static const _bgColors = [
    Color(0xFF140465), Color(0xFFF97316), Color(0xFF1A1B2E),
    Color(0xFF10B981), Color(0xFFEF4444), Color(0xFF8B5CF6),
    Color(0xFF0EA5E9), Color(0xFF000000),
  ];

  @override
  void dispose() { _textCtrl.dispose(); super.dispose(); }

  Future<void> _pickImage() async {
    final img = await _picker.pickImage(source: ImageSource.gallery);
    if (img != null) setState(() { _mediaFile = img; _storyType = 'image'; });
  }

  Future<void> _pickVideo() async {
    final vid = await _picker.pickVideo(source: ImageSource.gallery);
    if (vid == null) return;

    // Check duration — auto-trim if > 60s
    final info = await VideoCompress.getMediaInfo(vid.path);
    final durationMs = info.duration ?? 0;

    if (durationMs > 60000) {
      setState(() => _trimming = true);
      try {
        final trimmed = await VideoCompress.compressVideo(
          vid.path,
          quality: VideoQuality.MediumQuality,
          startTime: 0,
          duration: 60,
          includeAudio: true,
          deleteOrigin: false,
        );
        if (mounted && trimmed?.path != null) {
          setState(() { _mediaFile = XFile(trimmed!.path!); _storyType = 'video'; _trimming = false; });
        }
      } catch (_) {
        if (mounted) setState(() => _trimming = false);
      }
    } else {
      setState(() { _mediaFile = vid; _storyType = 'video'; });
    }
  }

  Future<void> _post() async {
    if (_storyType == 'text' && _textCtrl.text.trim().isEmpty) return;
    setState(() => _posting = true);
    try {
      MultipartFile? mediaFile;
      if (_mediaFile != null) {
        mediaFile = await MultipartFile.fromFile(
          _mediaFile!.path,
          filename: _mediaFile!.name,
        );
      }
      await _repo.createStory(
        type: _storyType,
        textContent: _storyType == 'text' ? _textCtrl.text.trim() : null,
        bgColor: '#${_bgColor.value.toRadixString(16).substring(2).toUpperCase()}',
        mediaFile: mediaFile,
      );
      if (mounted) Navigator.pop(context, true);
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _posting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _storyType == 'text' ? _bgColor : Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.transparent, elevation: 0,
        leading: IconButton(icon: Icon(Icons.close_rounded, color: Colors.white), onPressed: () => Navigator.pop(context)),
        title: Text('Add Story', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
        actions: [
          Padding(padding: EdgeInsets.only(right: 12),
            child: ElevatedButton(
              onPressed: (_posting || _trimming) ? null : _post,
              style: ElevatedButton.styleFrom(
                backgroundColor: kOrange, foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                padding: EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                minimumSize: Size.zero, tapTargetSize: MaterialTapTargetSize.shrinkWrap),
              child: _posting
                ? SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : Text('Share', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
      body: Column(children: [
        Expanded(
          child: _storyType == 'text'
            ? Center(child: Padding(padding: EdgeInsets.symmetric(horizontal: 32),
                child: TextField(controller: _textCtrl, maxLines: null, textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w700),
                  decoration: const InputDecoration.collapsed(hintText: 'Type something...', hintStyle: TextStyle(color: Colors.white54, fontSize: 22)))))
            : _trimming
              ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                  CircularProgressIndicator(color: Colors.white), SizedBox(height: 16),
                  Text('Trimming to 1 minute...', style: TextStyle(color: Colors.white70, fontSize: 15))]))
              : _mediaFile != null
              ? _storyType == 'video'
                ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Icon(Icons.videocam_rounded, color: Colors.white, size: 80), SizedBox(height: 12),
                    Text(_mediaFile!.name, style: TextStyle(color: Colors.white70, fontSize: 13), textAlign: TextAlign.center)]))
                : _XFilePreview(file: _mediaFile!)
              : Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.add_photo_alternate_rounded, color: Colors.white54, size: 80), SizedBox(height: 12),
                  Text('Pick a photo or video', style: TextStyle(color: Colors.white54, fontSize: 16))])),
        ),
        Container(
          color: Colors.black87,
          padding: EdgeInsets.only(left: 16, right: 16, top: 12, bottom: MediaQuery.of(context).padding.bottom + 12),
          child: Column(children: [
            Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
              _TypeBtn(icon: Icons.text_fields_rounded, label: 'Text', active: _storyType == 'text', onTap: () => setState(() { _storyType = 'text'; _mediaFile = null; })),
              _TypeBtn(icon: Icons.photo_rounded, label: 'Photo', active: _storyType == 'image', onTap: _trimming ? null : _pickImage),
              _TypeBtn(icon: Icons.videocam_rounded, label: 'Video', active: _storyType == 'video', onTap: _trimming ? null : _pickVideo),
            ]),
            if (_storyType == 'text') ...[
              SizedBox(height: 12),
              SingleChildScrollView(scrollDirection: Axis.horizontal,
                child: Row(children: _bgColors.map((c) => GestureDetector(
                  onTap: () => setState(() => _bgColor = c),
                  child: Container(width: 32, height: 32, margin: EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(color: c, shape: BoxShape.circle,
                      border: _bgColor == c ? Border.all(color: Colors.white, width: 3) : null)),
                )).toList())),
            ],
          ]),
        ),
      ]),
    );
  }
}

class _XFilePreview extends StatefulWidget {
  final XFile file;
  const _XFilePreview({required this.file});
  @override
  State<_XFilePreview> createState() => _XFilePreviewState();
}

class _XFilePreviewState extends State<_XFilePreview> {
  late Future<Uint8List> _bytesFuture;
  @override
  void initState() { super.initState(); _bytesFuture = widget.file.readAsBytes(); }
  @override
  Widget build(BuildContext context) => FutureBuilder<Uint8List>(
    future: _bytesFuture,
    builder: (ctx, snap) => snap.hasData
      ? Image.memory(snap.data!, fit: BoxFit.contain)
      : Center(child: CircularProgressIndicator(color: Colors.white)),
  );
}

class _TypeBtn extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool active;
  final VoidCallback? onTap;
  const _TypeBtn({required this.icon, required this.label, required this.active, required this.onTap});
  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      Container(padding: EdgeInsets.all(10),
        decoration: BoxDecoration(color: active ? kOrange : Colors.white24, shape: BoxShape.circle),
        child: Icon(icon, color: Colors.white, size: 22)),
      SizedBox(height: 4),
      Text(label, style: TextStyle(color: active ? kOrange : Colors.white54, fontSize: 11, fontWeight: FontWeight.w600)),
    ]),
  );
}
