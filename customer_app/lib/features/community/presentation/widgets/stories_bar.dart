import 'dart:io';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/theme_x.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import '../screens/community_shell.dart' show kOrange;
import '../screens/community_story_viewer.dart';

// ── Stories Bar ──────────────────────────────────────────────────────────────

class StoriesBar extends ConsumerWidget {
  final List<StoryGroup> groups;
  const StoriesBar({super.key, required this.groups});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final myProfile = ref.watch(communityMyProfileProvider);
    final myUser = myProfile.valueOrNull;

    return SizedBox(
      height: 96,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 8),
        itemCount: groups.length + 1,
        itemBuilder: (context, i) {
          if (i == 0) {
            return _CreateStoryButton(
              avatarUrl: myUser?.avatar,
              onTap: () => _openCreate(context, ref),
            );
          }
          final group = groups[i - 1];
          return _StoryCircle(
            group: group,
            onTap: () => _openViewer(context, groups, i - 1),
          );
        },
      ),
    );
  }

  void _openViewer(BuildContext context, List<StoryGroup> groups, int index) {
    Navigator.push(context, MaterialPageRoute(
      builder: (_) => StoryViewer(groups: groups, initialGroupIndex: index),
    ));
  }

  void _openCreate(BuildContext context, WidgetRef ref) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => _CreateStorySheet(
        onCreated: () => ref.invalidate(communityStoriesProvider),
      ),
    );
  }
}

// ── Create Button ─────────────────────────────────────────────────────────────

class _CreateStoryButton extends StatelessWidget {
  final String? avatarUrl;
  final VoidCallback onTap;
  const _CreateStoryButton({this.avatarUrl, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return GestureDetector(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
        child: Column(children: [
          Stack(children: [
            Container(
              width: 60, height: 60,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: c.borderColor, width: 2),
              ),
              child: ClipOval(child: avatarUrl != null
                ? CachedNetworkImage(imageUrl: avatarUrl!, fit: BoxFit.cover,
                    errorWidget: (_, __, ___) => _defaultAvatar(c))
                : _defaultAvatar(c)),
            ),
            Positioned(bottom: 0, right: 0,
              child: Container(
                width: 20, height: 20,
                decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle,
                  border: Border.all(color: c.scaffoldBg, width: 2)),
                child: const Icon(Icons.add, size: 12, color: Colors.white),
              ),
            ),
          ]),
          const SizedBox(height: 4),
          Text('Add Story', style: TextStyle(fontSize: 11, color: c.mutedText,
              fontWeight: FontWeight.w500)),
        ]),
      ),
    );
  }

  Widget _defaultAvatar(dynamic c) => Container(
    color: Colors.grey[200],
    child: Icon(Icons.person_rounded, color: Colors.grey[400], size: 30),
  );
}

// ── Story Circle ──────────────────────────────────────────────────────────────

class _StoryCircle extends StatelessWidget {
  final StoryGroup group;
  final VoidCallback onTap;
  const _StoryCircle({required this.group, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final viewed = group.allViewed;
    final story = group.stories.first;
    final thumb = story.thumbnail ?? story.mediaUrl;

    return GestureDetector(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 8),
        child: Column(children: [
          Container(
            width: 64, height: 64,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: viewed ? null : const LinearGradient(
                colors: [Color(0xFFFF6B6B), Color(0xFFFF8E53), Color(0xFFFFD93D)],
                begin: Alignment.topLeft, end: Alignment.bottomRight,
              ),
              border: viewed ? Border.all(color: c.borderColor, width: 2) : null,
            ),
            padding: const EdgeInsets.all(2.5),
            child: ClipOval(
              child: story.type == 'text'
                ? Container(
                    color: _parseColor(story.bgColor) ?? kOrange,
                    child: Center(child: Text(
                      story.textContent?.substring(0, story.textContent!.length.clamp(0, 20)) ?? '',
                      style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w600),
                      textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis,
                    )),
                  )
                : (thumb != null
                    ? CachedNetworkImage(imageUrl: thumb, fit: BoxFit.cover,
                        errorWidget: (_, __, ___) => Container(color: Colors.grey[300],
                          child: Icon(Icons.image_rounded, color: Colors.grey[500])))
                    : Container(color: Colors.grey[300])),
            ),
          ),
          const SizedBox(height: 4),
          SizedBox(width: 64, child: Text(
            group.user.name.split(' ').first,
            style: TextStyle(fontSize: 11, color: viewed ? c.mutedText : c.bodyText,
                fontWeight: viewed ? FontWeight.w400 : FontWeight.w600),
            textAlign: TextAlign.center, maxLines: 1, overflow: TextOverflow.ellipsis,
          )),
        ]),
      ),
    );
  }

  Color? _parseColor(String? hex) {
    if (hex == null) return null;
    try {
      final h = hex.replaceAll('#', '');
      return Color(int.parse('FF$h', radix: 16));
    } catch (_) { return null; }
  }
}

// ── Create Story Sheet ────────────────────────────────────────────────────────

class _CreateStorySheet extends StatelessWidget {
  final VoidCallback onCreated;
  const _CreateStorySheet({required this.onCreated});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: SafeArea(child: Column(mainAxisSize: MainAxisSize.min, children: [
        const SizedBox(height: 12),
        Container(width: 36, height: 4,
          decoration: BoxDecoration(color: Colors.grey[400], borderRadius: BorderRadius.circular(2))),
        const SizedBox(height: 20),
        Text('Create Story', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w700,
            color: context.colors.bodyText)),
        const SizedBox(height: 20),
        _Option(icon: Icons.text_fields_rounded, color: kOrange, label: 'Text',
          subtitle: 'Write something',
          onTap: () { Navigator.pop(context); _openText(context); }),
        _Option(icon: Icons.image_rounded, color: Colors.blue, label: 'Photo',
          subtitle: 'Share a photo',
          onTap: () { Navigator.pop(context); _pickMedia(context, ImageSource.gallery, 'image'); }),
        _Option(icon: Icons.videocam_rounded, color: Colors.purple, label: 'Video',
          subtitle: 'Share a video',
          onTap: () { Navigator.pop(context); _pickMedia(context, ImageSource.gallery, 'video'); }),
        _Option(icon: Icons.camera_alt_rounded, color: Colors.green, label: 'Camera',
          subtitle: 'Take a photo or video',
          onTap: () { Navigator.pop(context); _pickMedia(context, ImageSource.camera, 'auto'); }),
        const SizedBox(height: 8),
      ])),
    );
  }

  void _openText(BuildContext context) {
    Navigator.push(context, MaterialPageRoute(
      builder: (_) => _TextStoryEditor(onCreated: onCreated),
    ));
  }

  void _pickMedia(BuildContext context, ImageSource source, String type) async {
    final picker = ImagePicker();
    XFile? file;
    String storyType;

    if (type == 'video') {
      file = await picker.pickVideo(source: source, maxDuration: const Duration(seconds: 60));
      storyType = 'video';
    } else if (type == 'image') {
      file = await picker.pickImage(source: source, imageQuality: 85);
      storyType = 'image';
    } else {
      // camera — let user choose
      file = await picker.pickImage(source: source, imageQuality: 85);
      storyType = 'image';
    }

    if (file == null || !context.mounted) return;

    Navigator.push(context, MaterialPageRoute(
      builder: (_) => _MediaStoryPreview(file: File(file!.path), type: storyType, onCreated: onCreated),
    ));
  }
}

class _Option extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String label;
  final String subtitle;
  final VoidCallback onTap;
  const _Option({required this.icon, required this.color, required this.label,
    required this.subtitle, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Container(width: 44, height: 44,
        decoration: BoxDecoration(color: color.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(12)),
        child: Icon(icon, color: color, size: 22)),
      title: Text(label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
      subtitle: Text(subtitle, style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
      onTap: onTap,
    );
  }
}

// ── Text Story Editor ─────────────────────────────────────────────────────────

class _TextStoryEditor extends StatefulWidget {
  final VoidCallback onCreated;
  const _TextStoryEditor({required this.onCreated});
  @override State<_TextStoryEditor> createState() => _TextStoryEditorState();
}

class _TextStoryEditorState extends State<_TextStoryEditor> {
  final _ctrl = TextEditingController();
  String _bgColor = '#FF6B35';
  bool _uploading = false;

  static const _colors = ['#FF6B35', '#1A1A2E', '#16213E', '#E94560',
    '#0F3460', '#533483', '#2ECC71', '#E74C3C'];

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  Color get _bg => Color(int.parse('FF${_bgColor.replaceAll('#', '')}', radix: 16));

  Future<void> _upload() async {
    if (_ctrl.text.trim().isEmpty) return;
    setState(() => _uploading = true);
    try {
      await CommunityRepository().createStory(
        type: 'text', textContent: _ctrl.text.trim(), bgColor: _bgColor);
      widget.onCreated();
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) { ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Upload failed, try again'))); }
    } finally {
      if (mounted) setState(() => _uploading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: Colors.white,
        actions: [
          if (_uploading)
            const Padding(padding: EdgeInsets.all(14), child: SizedBox(width: 22, height: 22,
              child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)))
          else
            TextButton(onPressed: _upload,
              child: const Text('Share', style: TextStyle(color: Colors.white,
                fontWeight: FontWeight.w700, fontSize: 16))),
        ],
      ),
      body: Column(children: [
        Expanded(child: Center(child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 32),
          child: TextField(
            controller: _ctrl,
            autofocus: true,
            maxLines: null,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w600),
            decoration: const InputDecoration(
              hintText: 'Write something...',
              hintStyle: TextStyle(color: Colors.white60, fontSize: 22),
              border: InputBorder.none,
            ),
          ),
        ))),
        SizedBox(height: 80, child: ListView.builder(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 20),
          itemCount: _colors.length,
          itemBuilder: (_, i) {
            final hex = _colors[i];
            final color = Color(int.parse('FF${hex.replaceAll('#', '')}', radix: 16));
            final selected = _bgColor == hex;
            return GestureDetector(
              onTap: () => setState(() => _bgColor = hex),
              child: Container(
                width: 36, height: 36,
                margin: const EdgeInsets.only(right: 8),
                decoration: BoxDecoration(
                  color: color, shape: BoxShape.circle,
                  border: selected ? Border.all(color: Colors.white, width: 3) : null,
                ),
              ),
            );
          },
        )),
        const SizedBox(height: 16),
      ]),
    );
  }
}

// ── Media Story Preview ───────────────────────────────────────────────────────

class _MediaStoryPreview extends StatefulWidget {
  final File file;
  final String type;
  final VoidCallback onCreated;
  const _MediaStoryPreview({required this.file, required this.type, required this.onCreated});
  @override State<_MediaStoryPreview> createState() => _MediaStoryPreviewState();
}

class _MediaStoryPreviewState extends State<_MediaStoryPreview> {
  bool _uploading = false;
  double _progress = 0;

  Future<void> _upload() async {
    setState(() { _uploading = true; _progress = 0; });
    try {
      final bytes = await widget.file.readAsBytes();
      final mf = MultipartFile.fromBytes(bytes,
        filename: widget.file.path.split('/').last);
      await CommunityRepository().createStory(
        type: widget.type, mediaFile: mf);
      widget.onCreated();
      if (mounted) Navigator.pop(context);
    } catch (e) {
      if (mounted) {
        setState(() => _uploading = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Upload failed, try again')));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: Colors.white,
        actions: [
          if (_uploading)
            Padding(padding: const EdgeInsets.all(14), child: Row(children: [
              SizedBox(width: 22, height: 22,
                child: CircularProgressIndicator(value: _progress > 0 ? _progress : null,
                  color: Colors.white, strokeWidth: 2)),
              const SizedBox(width: 8),
              Text('${(_progress * 100).toInt()}%',
                style: const TextStyle(color: Colors.white, fontSize: 13)),
            ]))
          else
            TextButton(onPressed: _upload,
              child: const Text('Share', style: TextStyle(color: Colors.white,
                fontWeight: FontWeight.w700, fontSize: 16))),
        ],
      ),
      body: Center(child: Image.file(widget.file, fit: BoxFit.contain)),
    );
  }
}
