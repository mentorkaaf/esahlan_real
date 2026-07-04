import 'dart:typed_data';
import '../../../../core/theme/theme_x.dart';
import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import '../../data/models/community_models.dart';
import 'package:video_compress/video_compress.dart';
import 'package:file_picker/file_picker.dart';
import '../../data/repositories/community_repository.dart';
import '../services/video_optimizer.dart';
import '../services/background_upload_service.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart';

class CreatePostScreen extends ConsumerStatefulWidget {
  final String? initialType;
  const CreatePostScreen({super.key, this.initialType});

  @override
  ConsumerState<CreatePostScreen> createState() => _CreatePostScreenState();
}

class _CreatePostScreenState extends ConsumerState<CreatePostScreen> {
  final _textCtrl = TextEditingController();
  final _picker = ImagePicker();
  String _privacy = 'Public';
  String? _feeling;
  String? _location;
  List<XFile> _mediaFiles = [];
  bool _hasVideo = false;
  bool _posting = false;

  static const _feelings = ['😀 Happy', '😢 Sad', '😎 Cool', '🥳 Celebrating', '😍 Loved', '😤 Angry', '🤔 Thinking', '💪 Motivated'];
  static const _privacyOptions = ['Public', 'Followers', 'Private'];

  @override
  void dispose() {
    _textCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickMedia() async {
    final imgs = await _picker.pickMultiImage();
    if (imgs.isNotEmpty) {
      setState(() { _mediaFiles = imgs; _hasVideo = false; });
    }
  }

  Future<void> _pickVideo() async {
    final video = await _picker.pickVideo(source: ImageSource.gallery);
    if (video != null) {
      setState(() { _mediaFiles = [video]; _hasVideo = true; _postType = 'video'; });
    }
  }

  String _postType = 'text';

  Future<void> _pickAudio() async {
    final result = await FilePicker.platform.pickFiles(type: FileType.audio);
    if (result != null && result.files.single.path != null) {
      setState(() { _mediaFiles = [XFile(result.files.single.path!)]; _hasVideo = false; _postType = 'audio'; });
    }
  }

  Future<void> _pickDocument() async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom, allowedExtensions: ['pdf', 'doc', 'docx']);
    if (result != null && result.files.single.path != null) {
      setState(() { _mediaFiles = [XFile(result.files.single.path!)]; _hasVideo = false; _postType = 'document'; });
    }
  }

  Future<void> _post() async {
    final text = _textCtrl.text.trim();
    if (text.isEmpty && _mediaFiles.isEmpty) return;

    final type = _postType != 'text' ? _postType : (_hasVideo ? 'video' : (_mediaFiles.isNotEmpty ? 'image' : 'text'));

    // Background upload for media posts
    if (_mediaFiles.isNotEmpty) {
      ref.read(backgroundUploadProvider.notifier).uploadPost(
        type: type,
        content: text.isEmpty ? null : text,
        privacy: _privacy.toLowerCase(),
        feeling: _feeling,
        location: _location,
        mediaFiles: List<XFile>.from(_mediaFiles),
        hasVideo: _hasVideo,
      );
      if (mounted) Navigator.pop(context);
      return;
    }

    // Text-only posts: upload immediately
    setState(() => _posting = true);
    try {
      final repo = CommunityRepository();
      final post = await repo.createPost(
        type: type,
        content: text.isEmpty ? null : text,
        privacy: _privacy.toLowerCase(),
        feeling: _feeling,
        location: _location,
      );
      if (mounted) Navigator.pop(context, post);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() => _posting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final myProfile = ref.watch(communityMyProfileProvider);
    final avatar = myProfile.valueOrNull?.avatar;
    final name = myProfile.valueOrNull?.name ?? 'You';

    return Scaffold(
      
      appBar: AppBar(
        
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.close_rounded, color: context.colors.bodyText),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text('Create Post',
            style: TextStyle(color: context.colors.bodyText, fontWeight: FontWeight.w800, fontSize: 18)),
        actions: [
          Padding(
            padding: EdgeInsets.only(right: 12),
            child: ElevatedButton(
              onPressed: _posting ? null : _post,
              style: ElevatedButton.styleFrom(
                backgroundColor: kOrange,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                padding: EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: _posting
                  ? SizedBox(width: 16, height: 16,
                      child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : Text('Post', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
            ),
          ),
        ],
      ),
      body: Column(children: [
        Expanded(
          child: SingleChildScrollView(
            padding: EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              // User row
              Row(children: [
                CircleAvatar(
                  radius: 22,
                  
                  backgroundImage: avatar != null ? CachedNetworkImageProvider(avatar) : null,
                  child: avatar == null ? Text(name[0].toUpperCase(),
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18)) : null,
                ),
                SizedBox(width: 10),
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: context.colors.bodyText)),
                  SizedBox(height: 4),
                  GestureDetector(
                    onTap: () => _showPrivacyPicker(context),
                    child: Container(
                      padding: EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF0F2F5),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Row(mainAxisSize: MainAxisSize.min, children: [
                        Icon(Icons.public_rounded, size: 13, color: context.colors.bodyText),
                        SizedBox(width: 4),
                        Text(_privacy, style: TextStyle(color: context.colors.bodyText, fontSize: 12, fontWeight: FontWeight.w600)),
                        Icon(Icons.arrow_drop_down_rounded, size: 16, color: context.colors.bodyText),
                      ]),
                    ),
                  ),
                ]),
              ]),

              // Feeling / Location chips
              if (_feeling != null || _location != null)
                Padding(
                  padding: EdgeInsets.only(top: 10),
                  child: Wrap(spacing: 8, children: [
                    if (_feeling != null) _Chip(label: _feeling!, onRemove: () => setState(() => _feeling = null), color: const Color(0xFFFFF3E0)),
                    if (_location != null) _Chip(label: '📍 $_location', onRemove: () => setState(() => _location = null), color: const Color(0xFFFFF3E0)),
                  ]),
                ),

              // Text field
              SizedBox(height: 14),
              TextField(
                controller: _textCtrl,
                maxLines: null,
                minLines: 4,
                style: TextStyle(fontSize: 16, color: context.colors.bodyText, height: 1.5),
                decoration: InputDecoration.collapsed(
                  hintText: "What's on your mind?",
                  hintStyle: TextStyle(color: context.colors.mutedText, fontSize: 16),
                ),
              ),

              // Media preview
              if (_mediaFiles.isNotEmpty) ...[
                SizedBox(height: 14),
                GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2, crossAxisSpacing: 4, mainAxisSpacing: 4,
                  ),
                  itemCount: _mediaFiles.length,
                  itemBuilder: (ctx, i) => Stack(children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: _hasVideo
                          ? Container(
                              color: const Color(0xFF1A1B2E),
                              width: double.infinity,
                              height: double.infinity,
                              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                                Icon(Icons.videocam_rounded, color: Colors.white54, size: 40),
                                SizedBox(height: 6),
                                Text(_mediaFiles[i].name, style: TextStyle(color: Colors.white54, fontSize: 10), textAlign: TextAlign.center, maxLines: 2, overflow: TextOverflow.ellipsis),
                              ]),
                            )
                          : _XFileImage(file: _mediaFiles[i]),
                    ),
                    Positioned(
                      top: 4, right: 4,
                      child: GestureDetector(
                        onTap: () => setState(() => _mediaFiles.removeAt(i)),
                        child: Container(
                          padding: EdgeInsets.all(4),
                          decoration: BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                          child: Icon(Icons.close_rounded, size: 14, color: Colors.white),
                        ),
                      ),
                    ),
                  ]),
                ),
              ],

              SizedBox(height: 14),
              // Add to post row
              Row(children: [
                Text('Add to post: ', style: TextStyle(color: context.colors.mutedText, fontSize: 13, fontWeight: FontWeight.w600)),
                GestureDetector(
                  onTap: () => _showFeelingPicker(context),
                  child: const _AddBtn(emoji: '😊'),
                ),
                GestureDetector(
                  onTap: () => _showLocationInput(context),
                  child: const _AddBtn(emoji: '📍'),
                ),
                GestureDetector(
                  onTap: () {},
                  child: const _AddBtn(emoji: '#'),
                ),
              ]),
            ]),
          ),
        ),

        // Bottom toolbar
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            border: Border(top: BorderSide(color: Color(0xFFF0F2F5))),
          ),
          padding: EdgeInsets.only(
            left: 8, right: 8, top: 8,
            bottom: MediaQuery.of(context).padding.bottom + 8,
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _ToolbarBtn(icon: Icons.photo_library_rounded, label: 'Photo', color: const Color(0xFF45BD62), onTap: _pickMedia),
              _ToolbarBtn(icon: Icons.videocam_rounded, label: 'Video', color: kOrange, onTap: _pickVideo),
              _ToolbarBtn(icon: Icons.headphones_rounded, label: 'Audio', color: const Color(0xFF3B82F6), onTap: _pickAudio),
              _ToolbarBtn(icon: Icons.picture_as_pdf_rounded, label: 'PDF', color: const Color(0xFFEF4444), onTap: _pickDocument),
              _ToolbarBtn(icon: Icons.emoji_emotions_rounded, label: 'Feeling', color: const Color(0xFFF59E0B), onTap: () => _showFeelingPicker(context)),
            ],
          ),
        ),
      ]),
    );
  }

  void _showPrivacyPicker(BuildContext context) {
    showModalBottomSheet(
      context: context,
      builder: (_) => Column(mainAxisSize: MainAxisSize.min, children: [
        SizedBox(height: 12),
        Text('Who can see your post?',
            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
        SizedBox(height: 8),
        ..._privacyOptions.map((p) => ListTile(
          leading: Icon(
            p == 'Public' ? Icons.public_rounded : p == 'Followers' ? Icons.people_rounded : Icons.lock_rounded,
            color: kOrange,
          ),
          title: Text(p),
          trailing: _privacy == p ? Icon(Icons.check_rounded, color: kOrange) : null,
          onTap: () {
            setState(() => _privacy = p);
            Navigator.pop(context);
          },
        )),
        SizedBox(height: 16),
      ]),
    );
  }

  void _showFeelingPicker(BuildContext context) {
    showModalBottomSheet(
      context: context,
      builder: (_) => GridView.builder(
        padding: EdgeInsets.all(16),
        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 4, crossAxisSpacing: 8, mainAxisSpacing: 8, childAspectRatio: 1.2,
        ),
        itemCount: _feelings.length,
        itemBuilder: (ctx, i) => GestureDetector(
          onTap: () {
            setState(() => _feeling = _feelings[i]);
            Navigator.pop(context);
          },
          child: Container(
            decoration: BoxDecoration(
              color: const Color(0xFFF0F2F5),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Center(
              child: Text(_feelings[i], style: TextStyle(fontSize: 12), textAlign: TextAlign.center),
            ),
          ),
        ),
      ),
    );
  }

  void _showLocationInput(BuildContext context) {
    final ctrl = TextEditingController();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (_) => Padding(
        padding: EdgeInsets.only(
          left: 16, right: 16, top: 20,
          bottom: MediaQuery.of(context).viewInsets.bottom + 20,
        ),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Text('Add Location', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
          SizedBox(height: 12),
          TextField(
            controller: ctrl,
            autofocus: true,
            decoration: InputDecoration(
              hintText: 'Where are you?',
              filled: true,
              fillColor: const Color(0xFFF0F2F5),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
              prefixIcon: Icon(Icons.location_on_rounded, color: kOrange),
            ),
          ),
          SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              style: ElevatedButton.styleFrom(
                backgroundColor: kOrange,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              onPressed: () {
                if (ctrl.text.trim().isNotEmpty) {
                  setState(() => _location = ctrl.text.trim());
                }
                Navigator.pop(context);
              },
              child: Text('Add'),
            ),
          ),
        ]),
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  final String label;
  final VoidCallback onRemove;
  final Color color;
  const _Chip({required this.label, required this.onRemove, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(20)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Text(label, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500)),
        SizedBox(width: 4),
        GestureDetector(
          onTap: onRemove,
          child: Icon(Icons.close_rounded, size: 14, color: context.colors.mutedText),
        ),
      ]),
    );
  }
}

class _AddBtn extends StatelessWidget {
  final String emoji;
  const _AddBtn({required this.emoji});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: EdgeInsets.only(right: 6),
      width: 34, height: 34,
      decoration: BoxDecoration(color: const Color(0xFFF0F2F5), shape: BoxShape.circle),
      child: Center(child: Text(emoji, style: TextStyle(fontSize: 16))),
    );
  }
}

class _XFileImage extends StatefulWidget {
  final XFile file;
  const _XFileImage({required this.file});
  @override
  State<_XFileImage> createState() => _XFileImageState();
}

class _XFileImageState extends State<_XFileImage> {
  late Future<Uint8List> _bytesFuture;

  @override
  void initState() {
    super.initState();
    _bytesFuture = widget.file.readAsBytes();
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Uint8List>(
      future: _bytesFuture,
      builder: (ctx, snap) {
        if (snap.hasData) {
          return Image.memory(snap.data!, fit: BoxFit.cover, width: double.infinity, height: double.infinity);
        }
        return Container(color: const Color(0xFFE5E7EB));
      },
    );
  }
}

class _ToolbarBtn extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  const _ToolbarBtn({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 44, height: 44,
          decoration: BoxDecoration(color: color.withOpacity(0.1), shape: BoxShape.circle),
          child: Icon(icon, color: color, size: 22),
        ),
        SizedBox(height: 3),
        Text(label, style: TextStyle(fontSize: 10, color: context.colors.mutedText, fontWeight: FontWeight.w500)),
      ]),
    );
  }
}
