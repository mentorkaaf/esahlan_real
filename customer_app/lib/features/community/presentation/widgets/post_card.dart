import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../screens/highlight_viewer_screen.dart';
import '../providers/community_provider.dart';

class PostCard extends ConsumerStatefulWidget {
  final CommunityPost post;
  final VoidCallback? onTap;
  final VoidCallback? onDelete;

  const PostCard({super.key, required this.post, this.onTap, this.onDelete});

  @override
  ConsumerState<PostCard> createState() => _PostCardState();
}

class _PostCardState extends ConsumerState<PostCard>
    with SingleTickerProviderStateMixin {
  final _repo = CommunityRepository();
  late AnimationController _likeCtrl;
  late Animation<double> _likeAnim;
  bool _showReactions = false;

  @override
  void initState() {
    super.initState();
    _likeCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 200));
    _likeAnim = Tween(begin: 1.0, end: 1.3).chain(CurveTween(curve: Curves.elasticOut)).animate(_likeCtrl);
  }

  @override
  void dispose() {
    _likeCtrl.dispose();
    super.dispose();
  }

  Future<void> _react(String type) async {
    setState(() => _showReactions = false);
    _likeCtrl.forward().then((_) => _likeCtrl.reverse());
    try {
      final r = await _repo.reactToPost(widget.post.id, type);
      setState(() {
        widget.post.userReaction = r['reacted'] == true ? type : null;
        widget.post.likesCount = r['likes_count'] as int? ?? widget.post.likesCount;
      });
    } catch (_) {}
  }

  Future<void> _toggleSave() async {
    try {
      final saved = await _repo.savePost(widget.post.id);
      setState(() {
        widget.post.isSaved = saved;
        widget.post.savesCount += saved ? 1 : -1;
      });
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to save: $e'), backgroundColor: Colors.red));
    }
  }

  @override
  Widget build(BuildContext context) {
    final post = widget.post;
    return GestureDetector(
      onTap: widget.onTap,
      child: Container(
        color: Colors.white,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _buildHeader(post),
            if (post.content != null && post.content!.isNotEmpty) _buildContent(post),
            if (post.hasMedia) _buildMedia(post),
            if (post.type == 'poll') _buildPoll(post),
            _buildReactionBar(post),
            _buildActionBar(post),
            Divider(height: 1, thickness: 8, color: Color(0xFFF4F5F8)),
          ],
        ),
      ),
    );
  }

  Widget _buildHeader(CommunityPost post) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 12, 8, 8),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => _openProfile(post.user.id),
            child: CircleAvatar(
              radius: 22,
              backgroundColor: const Color(0xFFEEF0FF),
              backgroundImage: post.user.avatar != null
                  ? CachedNetworkImageProvider(post.user.avatar!)
                  : null,
              child: post.user.avatar == null
                  ? Text(post.user.name[0].toUpperCase(),
                      style: TextStyle(color: Color(0xFF140465), fontWeight: FontWeight.bold))
                  : null,
            ),
          ),
          SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    GestureDetector(
                      onTap: () => _openProfile(post.user.id),
                      child: Text(post.user.name,
                          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF140465))),
                    ),
                    if (post.user.isVerified) ...[
                      SizedBox(width: 4),
                      Icon(Icons.verified, size: 15, color: Color(0xFFFF6B35)),
                    ],
                  ],
                ),
                Row(
                  children: [
                    Text(timeago.format(post.createdAt),
                        style: TextStyle(color: Colors.grey, fontSize: 11)),
                    if (post.location != null) ...[
                      Text(' · ', style: TextStyle(color: Colors.grey, fontSize: 11)),
                      Icon(Icons.location_on, size: 11, color: Colors.grey),
                      Text(post.location!, style: TextStyle(color: Colors.grey, fontSize: 11)),
                    ],
                    Text(' · ', style: TextStyle(color: Colors.grey, fontSize: 11)),
                    Icon(_privacyIcon(post.privacy), size: 11, color: Colors.grey),
                  ],
                ),
              ],
            ),
          ),
          PopupMenuButton<String>(
            icon: Icon(Icons.more_horiz, color: Colors.grey),
            onSelected: (v) => _handlePostMenu(v, post),
            itemBuilder: (_) => [
              const PopupMenuItem(value: 'save', child: Text('Save Post')),
              if (post.user.isMe) const PopupMenuItem(value: 'highlight', child: Text('Add to Highlight')),
              const PopupMenuItem(value: 'share', child: Text('Share')),
              const PopupMenuItem(value: 'report', child: Text('Report')),
              if (post.user.isMe) const PopupMenuItem(value: 'delete', child: Text('Delete', style: TextStyle(color: Colors.red))),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildContent(CommunityPost post) {
    final text = post.content!;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, 10),
      child: RichText(
        text: TextSpan(
          style: TextStyle(color: context.colors.bodyText, fontSize: 14.5, height: 1.45),
          children: _parseContent(text),
        ),
      ),
    );
  }

  List<TextSpan> _parseContent(String text) {
    final spans = <TextSpan>[];
    final regex = RegExp(r'#\w+|@\w+');
    int last = 0;
    for (final m in regex.allMatches(text)) {
      if (m.start > last) spans.add(TextSpan(text: text.substring(last, m.start)));
      spans.add(TextSpan(
        text: m.group(0),
        style: TextStyle(color: Color(0xFFFF6B35), fontWeight: FontWeight.w600),
      ));
      last = m.end;
    }
    if (last < text.length) spans.add(TextSpan(text: text.substring(last)));
    return spans;
  }

  Widget _buildMedia(CommunityPost post) {
    final media = post.media;
    if (media.length == 1) {
      final m = media.first;
      return AspectRatio(
        aspectRatio: 16 / 9,
        child: m.type == 'video'
            ? Stack(fit: StackFit.expand, children: [
                if (m.thumbnail != null)
                  NetImage(url: m.thumbnail!, fit: BoxFit.cover),
                Center(child: Icon(Icons.play_circle_fill, size: 64, color: Colors.white)),
              ])
            : NetImage(url: m.url, fit: BoxFit.cover),
      );
    }
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: 2, crossAxisSpacing: 2, mainAxisSpacing: 2),
      itemCount: media.length > 4 ? 4 : media.length,
      itemBuilder: (_, i) {
        if (i == 3 && media.length > 4) {
          return Stack(fit: StackFit.expand, children: [
            NetImage(url: media[3].url, fit: BoxFit.cover),
            Container(color: Colors.black54,
                child: Center(child: Text('+${media.length - 3}',
                    style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold)))),
          ]);
        }
        return NetImage(url: media[i].url, fit: BoxFit.cover);
      },
    );
  }

  Widget _buildPoll(CommunityPost post) {
    final total = post.pollOptions.fold(0, (s, o) => s + o.votes);
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, 12),
      child: Column(
        children: post.pollOptions.asMap().entries.map((entry) {
          final pct = total > 0 ? entry.value.votes / total : 0.0;
          return Padding(
            padding: EdgeInsets.only(bottom: 8),
            child: InkWell(
              onTap: () async {
                try {
                  final r = await _repo.votePoll(post.id, entry.key);
                  setState(() {
                    final opts = r['poll_options'] as List;
                    for (var i = 0; i < opts.length; i++) {
                      post.pollOptions[i].votes = opts[i]['votes'] as int;
                    }
                  });
                } catch (e) {
                  if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Failed to vote: $e'), backgroundColor: Colors.red));
                }
              },
              borderRadius: BorderRadius.circular(10),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Stack(children: [
                  Container(height: 44, decoration: BoxDecoration(
                    color: const Color(0xFFF0F2FF),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: context.colors.navyText.withOpacity(0.2)),
                  )),
                  FractionallySizedBox(
                    widthFactor: pct,
                    child: Container(height: 44,
                        decoration: BoxDecoration(
                          color: context.colors.navyText.withOpacity(0.15),
                          borderRadius: BorderRadius.circular(10),
                        )),
                  ),
                  Padding(
                    padding: EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(entry.value.text, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                        Text('${(pct * 100).round()}%', style: TextStyle(color: Color(0xFF140465), fontWeight: FontWeight.w700, fontSize: 13)),
                      ],
                    ),
                  ),
                ]),
              ),
            ),
          );
        }).toList(),
      ),
    );
  }

  Widget _buildReactionBar(CommunityPost post) {
    if (post.likesCount == 0 && post.commentsCount == 0) return const SizedBox.shrink();
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 4, 16, 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          if (post.likesCount > 0)
            Row(children: [
              _reactionEmoji('like'),
              SizedBox(width: 4),
              Text('${post.likesCount}', style: TextStyle(color: Colors.grey, fontSize: 13)),
            ]),
          if (post.commentsCount > 0)
            Text('${post.commentsCount} comments', style: TextStyle(color: Colors.grey, fontSize: 13)),
        ],
      ),
    );
  }

  Widget _buildActionBar(CommunityPost post) {
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: 4, vertical: 4),
      child: Row(
        children: [
          Expanded(child: _actionBtn(
            icon: post.userReaction != null ? _reactionIcon(post.userReaction!) : Icons.thumb_up_outlined,
            label: post.userReaction != null ? post.userReaction![0].toUpperCase() + post.userReaction!.substring(1) : 'Like',
            color: post.userReaction != null ? const Color(0xFF140465) : Colors.grey[700]!,
            onTap: () => post.userReaction != null ? _react(post.userReaction!) : setState(() => _showReactions = !_showReactions),
            onLongPress: () => setState(() => _showReactions = true),
          )),
          Expanded(child: _actionBtn(
            icon: Icons.chat_bubble_outline,
            label: 'Comment',
            color: Colors.grey[700]!,
            onTap: widget.onTap,
          )),
          Expanded(child: _actionBtn(
            icon: Icons.share_outlined,
            label: 'Share',
            color: Colors.grey[700]!,
            onTap: () => _sharePost(post),
          )),
          Expanded(child: _actionBtn(
            icon: post.isSaved ? Icons.bookmark : Icons.bookmark_outline,
            label: 'Save',
            color: post.isSaved ? const Color(0xFFFF6B35) : Colors.grey[700]!,
            onTap: _toggleSave,
          )),
        ],
      ),
    );
  }

  Widget _actionBtn({required IconData icon, required String label, required Color color, VoidCallback? onTap, VoidCallback? onLongPress}) {
    return GestureDetector(
      onTap: onTap,
      onLongPress: onLongPress,
      child: Padding(
        padding: EdgeInsets.symmetric(vertical: 8),
        child: Column(
          children: [
            Icon(icon, size: 22, color: color),
            SizedBox(height: 2),
            Text(label, style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w500)),
          ],
        ),
      ),
    );
  }

  Widget _reactionEmoji(String type) {
    const emojis = {'like': '👍', 'love': '❤️', 'wow': '😮', 'haha': '😄', 'sad': '😢', 'angry': '😠'};
    return Text(emojis[type] ?? '👍', style: TextStyle(fontSize: 14));
  }

  IconData _reactionIcon(String type) {
    const icons = {'like': Icons.thumb_up, 'love': Icons.favorite, 'wow': Icons.sentiment_very_satisfied,
      'haha': Icons.emoji_emotions, 'sad': Icons.sentiment_dissatisfied, 'angry': Icons.sentiment_very_dissatisfied};
    return icons[type] ?? Icons.thumb_up;
  }

  IconData _privacyIcon(String privacy) {
    switch (privacy) {
      case 'followers': return Icons.people;
      case 'private': return Icons.lock;
      default: return Icons.public;
    }
  }

  void _openProfile(int userId) {
    context.push('/community/profile/$userId');
  }

  Future<void> _sharePost(CommunityPost post) async {
    try {
      await _repo.sharePost(post.id);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Post shared!')));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to share: $e'), backgroundColor: Colors.red));
    }
  }

  void _handlePostMenu(String value, CommunityPost post) {
    switch (value) {
      case 'save': _toggleSave();
      case 'share': _sharePost(post);
      case 'delete': widget.onDelete?.call();
      case 'report': _showReportDialog(post);
      case 'highlight': _showAddToHighlight(post);
    }
  }

  void _showAddToHighlight(CommunityPost post) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => AddToHighlightSheet(
        userId: post.user.id,
        contentType: 'post',
        contentId: post.id,
      ),
    );
  }

  void _showReportDialog(CommunityPost post) {
    showModalBottomSheet(
      context: context,
      builder: (_) => Column(
        mainAxisSize: MainAxisSize.min,
        children: ['spam','hate','violence','nudity','misinformation','other'].map((reason) =>
          ListTile(
            title: Text(reason[0].toUpperCase() + reason.substring(1)),
            onTap: () async {
              Navigator.pop(context);
              await _repo.report('post', post.id, reason);
              if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Report submitted. Thank you.')));
            },
          )).toList(),
      ),
    );
  }
}
