import 'dart:typed_data';
import '../../../../core/theme/theme_x.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
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

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(children: [
              const Text('Stories', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF1A1B2E))),
              const Spacer(),
              GestureDetector(
                onTap: () {},
                child: const Text('See all', style: TextStyle(color: kOrange, fontWeight: FontWeight.w600, fontSize: 13)),
              ),
            ]),
          ),
          const SizedBox(height: 10),
          SizedBox(
            height: 90,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              itemCount: groups.length + 1,
              itemBuilder: (ctx, i) {
                if (i == 0) {
                  final avatar = myProfile.valueOrNull?.avatar;
                  final name = myProfile.valueOrNull?.name ?? 'You';
                  return _AddStoryCard(avatar: avatar, name: name);
                }
                final group = groups[i - 1];
                final allViewed = group.allViewed;
                return _StoryCard(
                  group: group,
                  allViewed: allViewed,
                  onTap: () => Navigator.push(
                    ctx,
                    MaterialPageRoute(
                      builder: (_) => StoryViewer(groups: groups, initialGroupIndex: i - 1),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _AddStoryCard extends ConsumerWidget {
  final String? avatar;
  final String name;
  const _AddStoryCard({required this.avatar, required this.name});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return GestureDetector(
      onTap: () async {
        final created = await Navigator.push<bool>(
          context,
          MaterialPageRoute(builder: (_) => const _CreateStoryScreen()),
        );
        if (created == true) {
          ref.invalidate(communityStoriesProvider);
        }
      },
      child: Container(
        width: 64,
        margin: const EdgeInsets.only(right: 10),
        child: Column(children: [
          Stack(children: [
            CircleAvatar(
              radius: 30,
              
              backgroundImage: avatar != null ? CachedNetworkImageProvider(avatar!) : null,
              child: avatar == null
                  ? Text(name[0].toUpperCase(),
                      style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 20, color: Color(0xFF6B7280)))
                  : null,
            ),
            Positioned(
              bottom: 0, right: 0,
              child: Container(
                width: 20, height: 20,
                decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
                child: const Icon(Icons.add_rounded, size: 14, color: Colors.white),
              ),
            ),
          ]),
          const SizedBox(height: 5),
          const Text('Your Story',
              style: TextStyle(fontSize: 11, color: Color(0xFF374151), fontWeight: FontWeight.w500),
              maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
        ]),
      ),
    );
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
  String _storyType = 'text'; // text | image | video
  bool _posting = false;
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
    if (vid != null) setState(() { _mediaFile = vid; _storyType = 'video'; });
  }

  Future<void> _post() async {
    if (_storyType == 'text' && _textCtrl.text.trim().isEmpty) return;
    setState(() => _posting = true);
    try {
      MultipartFile? mediaFile;
      if (_mediaFile != null) {
        final bytes = await _mediaFile!.readAsBytes();
        mediaFile = MultipartFile.fromBytes(bytes, filename: _mediaFile!.name);
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
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(icon: const Icon(Icons.close_rounded, color: Colors.white), onPressed: () => Navigator.pop(context)),
        title: const Text('Add Story', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: ElevatedButton(
              onPressed: _posting ? null : _post,
              style: ElevatedButton.styleFrom(
                backgroundColor: kOrange, foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                minimumSize: Size.zero, tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: _posting
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Text('Share', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
      body: Column(children: [
        // Preview area
        Expanded(
          child: _storyType == 'text'
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 32),
                    child: TextField(
                      controller: _textCtrl,
                      maxLines: null,
                      textAlign: TextAlign.center,
                      style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w700),
                      decoration: const InputDecoration.collapsed(
                        hintText: 'Type something...',
                        hintStyle: TextStyle(color: Colors.white54, fontSize: 22),
                      ),
                    ),
                  ),
                )
              : _mediaFile != null
                  ? _storyType == 'video'
                      ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                          const Icon(Icons.videocam_rounded, color: Colors.white, size: 80),
                          const SizedBox(height: 12),
                          Text(_mediaFile!.name, style: const TextStyle(color: Colors.white70, fontSize: 13), textAlign: TextAlign.center),
                        ]))
                      : _XFilePreview(file: _mediaFile!)
                  : Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                      const Icon(Icons.add_photo_alternate_rounded, color: Colors.white54, size: 80),
                      const SizedBox(height: 12),
                      const Text('Pick a photo or video', style: TextStyle(color: Colors.white54, fontSize: 16)),
                    ])),
        ),

        // Bottom controls
        Container(
          color: Colors.black87,
          padding: EdgeInsets.only(left: 16, right: 16, top: 12, bottom: MediaQuery.of(context).padding.bottom + 12),
          child: Column(children: [
            // Type selector
            Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
              _TypeBtn(icon: Icons.text_fields_rounded, label: 'Text', active: _storyType == 'text', onTap: () => setState(() { _storyType = 'text'; _mediaFile = null; })),
              _TypeBtn(icon: Icons.photo_rounded, label: 'Photo', active: _storyType == 'image', onTap: _pickImage),
              _TypeBtn(icon: Icons.videocam_rounded, label: 'Video', active: _storyType == 'video', onTap: _pickVideo),
            ]),
            // Background color picker (only for text)
            if (_storyType == 'text') ...[
              const SizedBox(height: 12),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(children: _bgColors.map((c) => GestureDetector(
                  onTap: () => setState(() => _bgColor = c),
                  child: Container(
                    width: 32, height: 32, margin: const EdgeInsets.only(right: 8),
                    decoration: BoxDecoration(
                      color: c, shape: BoxShape.circle,
                      border: _bgColor == c ? Border.all(color: Colors.white, width: 3) : null,
                    ),
                  ),
                )).toList()),
              ),
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
        : const Center(child: CircularProgressIndicator(color: Colors.white)),
  );
}

class _TypeBtn extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool active;
  final VoidCallback onTap;
  const _TypeBtn({required this.icon, required this.label, required this.active, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: active ? kOrange : Colors.white24,
          shape: BoxShape.circle,
        ),
        child: Icon(icon, color: Colors.white, size: 22),
      ),
      const SizedBox(height: 4),
      Text(label, style: TextStyle(color: active ? kOrange : Colors.white54, fontSize: 11, fontWeight: FontWeight.w600)),
    ]),
  );
}

class _StoryCard extends StatelessWidget {
  final StoryGroup group;
  final bool allViewed;
  final VoidCallback onTap;
  const _StoryCard({required this.group, required this.allViewed, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final avatar = group.user.avatar;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 64,
        margin: const EdgeInsets.only(right: 10),
        child: Column(children: [
          Container(
            padding: const EdgeInsets.all(2),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: allViewed
                  ? null
                  : const LinearGradient(
                      colors: [kOrange, Color(0xFFFF8C42), Color(0xFFFFB347)],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
              color: allViewed ? const Color(0xFFD1D5DB) : null,
            ),
            child: Container(
              padding: const EdgeInsets.all(2),
              decoration: BoxDecoration(color: context.colors.cardBg, shape: BoxShape.circle),
              child: CircleAvatar(
                radius: 26,
                
                backgroundImage: avatar != null ? CachedNetworkImageProvider(avatar) : null,
                child: avatar == null
                    ? Text(group.user.name[0].toUpperCase(),
                        style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: Color(0xFF6B7280)))
                    : null,
              ),
            ),
          ),
          const SizedBox(height: 5),
          Text(group.user.name.split(' ')[0],
              style: const TextStyle(fontSize: 11, color: Color(0xFF374151), fontWeight: FontWeight.w500),
              maxLines: 1, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center),
        ]),
      ),
    );
  }
}
