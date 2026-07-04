import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart';
import 'community_feed_screen.dart';

class PostDetailScreen extends ConsumerStatefulWidget {
  final int postId;
  const PostDetailScreen({super.key, required this.postId});

  @override
  ConsumerState<PostDetailScreen> createState() => _PostDetailScreenState();
}

class _PostDetailScreenState extends ConsumerState<PostDetailScreen> {
  CommunityPost? _post;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadPost();
  }

  Future<void> _loadPost() async {
    try {
      final repo = CommunityRepository();
      final post = await repo.getPost(widget.postId);
      if (mounted) setState(() { _post = post; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator(color: kOrange)));
    }
    if (_error != null || _post == null) {
      return Scaffold(
        appBar: AppBar(title: Text('Post')),
        body: Center(child: Text(_error ?? 'Post not found')),
      );
    }

    final p = _post!;
    return Scaffold(
      appBar: AppBar(title: Text('Post'), backgroundColor: context.colors.cardBg, foregroundColor: context.colors.navyText),
      body: SingleChildScrollView(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Padding(padding: EdgeInsets.all(12), child: Row(children: [
            CircleNetImage(url: p.user.avatar, size: 44, fallbackText: p.user.name),
            SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(p.user.name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
              Text(p.createdAt.toString().substring(0, 16), style: TextStyle(color: context.colors.mutedText, fontSize: 12)),
            ])),
          ])),
          if (p.content != null && p.content!.isNotEmpty)
            Padding(padding: EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              child: Text(p.content!, style: TextStyle(fontSize: 15, height: 1.4))),
          for (final m in p.media)
            if (m.type == 'image')
              Padding(padding: EdgeInsets.only(bottom: 2),
                child: NetImage(url: m.url, fit: BoxFit.fitWidth, width: double.infinity))
            else if (m.type == 'video')
              Container(height: 250, color: Colors.black,
                child: Center(child: Icon(Icons.play_circle_outline_rounded, color: Colors.white, size: 64))),
          Padding(padding: EdgeInsets.all(12), child: Row(children: [
            Icon(Icons.thumb_up_alt_rounded, size: 18, color: kOrange),
            SizedBox(width: 4),
            Text('${p.likesCount}', style: TextStyle(fontSize: 14)),
            SizedBox(width: 20),
            Icon(Icons.chat_bubble_outline_rounded, size: 18, color: context.colors.mutedText),
            SizedBox(width: 4),
            Text('${p.commentsCount}', style: TextStyle(fontSize: 14)),
            SizedBox(width: 20),
            Icon(Icons.share_outlined, size: 18, color: context.colors.mutedText),
            SizedBox(width: 4),
            Text('${p.sharesCount}', style: TextStyle(fontSize: 14)),
          ])),
        ]),
      ),
    );
  }
}
