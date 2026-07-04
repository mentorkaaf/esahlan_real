import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';

class ModernPostCard extends ConsumerStatefulWidget {
  final CommunityPost post;
  final VoidCallback? onDelete;

  const ModernPostCard({super.key, required this.post, this.onDelete});

  @override
  ConsumerState<ModernPostCard> createState() => _ModernPostCardState();
}

class _ModernPostCardState extends ConsumerState<ModernPostCard>
    with SingleTickerProviderStateMixin {
  final _repo = CommunityRepository();
  bool _showReactions = false;
  late AnimationController _heartCtrl;

  static const _reactions = [
    {'type': 'like', 'emoji': '👍', 'label': 'Like', 'color': Color(0xFF1877F2)},
    {'type': 'love', 'emoji': '❤️', 'label': 'Love', 'color': Color(0xFFE41E3F)},
    {'type': 'haha', 'emoji': '😂', 'label': 'Haha', 'color': Color(0xFFF7B928)},
    {'type': 'wow', 'emoji': '😮', 'label': 'Wow', 'color': Color(0xFFF7B928)},
    {'type': 'sad', 'emoji': '😢', 'label': 'Sad', 'color': Color(0xFFF7B928)},
    {'type': 'angry', 'emoji': '😡', 'label': 'Angry', 'color': Color(0xFFE87722)},
  ];

  @override
  void initState() {
    super.initState();
    _heartCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 300));
  }

  @override
  void dispose() {
    _heartCtrl.dispose();
    super.dispose();
  }

  Future<void> _react(String type) async {
    setState(() => _showReactions = false);
    _heartCtrl.forward().then((_) => _heartCtrl.reverse());
    try {
      final r = await _repo.reactToPost(widget.post.id, type);
      setState(() {
        widget.post.userReaction = r['reacted'] == true ? type : null;
        widget.post.likesCount = r['likes_count'] as int? ?? widget.post.likesCount;
      });
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    final post = widget.post;
    return Container(
      color: const Color(0xFF242526),
      margin: EdgeInsets.only(bottom: 8),
      child: Stack(
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildHeader(post),
              if (post.content != null && post.content!.isNotEmpty) _buildContent(post),
              if (post.hasMedia) _buildMedia(post),
              if (post.type == 'poll') _buildPoll(post),
              _buildReactionSummary(post),
              _buildDivider(),
              _buildActionRow(post),
            ],
          ),
          // Reactions popup
          if (_showReactions) _buildReactionsPopup(),
        ],
      ),
    );
  }

  Widget _buildHeader(CommunityPost post) {
    return Padding(
      padding: EdgeInsets.fromLTRB(12, 12, 8, 8),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => context.push('/community/profile/${post.user.id}'),
            child: CircleAvatar(
              radius: 22,
              backgroundColor: const Color(0xFF3A3B3C),
              backgroundImage: post.user.avatar != null
                  ? CachedNetworkImageProvider(post.user.avatar!)
                  : null,
              child: post.user.avatar == null
                  ? Text(post.user.name[0].toUpperCase(),
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold))
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
                      onTap: () => context.push('/community/profile/${post.user.id}'),
                      child: Text(post.user.name,
                          style: TextStyle(
                              color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14.5)),
                    ),
                    if (post.user.isVerified) ...[
                      SizedBox(width: 4),
                      Icon(Icons.verified_rounded, size: 14, color: Color(0xFF1877F2)),
                    ],
                  ],
                ),
                SizedBox(height: 1),
                Row(
                  children: [
                    Text(timeago.format(post.createdAt),
                        style: TextStyle(color: Color(0xFF8A8D91), fontSize: 11.5)),
                    SizedBox(width: 4),
                    Icon(_privacyIcon(post.privacy), size: 11, color: const Color(0xFF8A8D91)),
                  ],
                ),
              ],
            ),
          ),
          PopupMenuButton<String>(
            icon: Icon(Icons.more_horiz, color: Color(0xFF8A8D91)),
            color: const Color(0xFF3A3B3C),
            onSelected: (v) => _handleMenu(v, post),
            itemBuilder: (_) => [
              _menuItem(Icons.bookmark_outline, 'Save Post', 'save'),
              _menuItem(Icons.share_outlined, 'Share', 'share'),
              _menuItem(Icons.report_outlined, 'Report', 'report'),
              if (post.user.isMe) _menuItemDanger(Icons.delete_outline, 'Delete Post', 'delete'),
            ],
          ),
        ],
      ),
    );
  }

  PopupMenuItem<String> _menuItem(IconData icon, String label, String value) {
    return PopupMenuItem(
      value: value,
      child: Row(children: [
        Icon(icon, size: 18, color: Colors.white70),
        SizedBox(width: 10),
        Text(label, style: TextStyle(color: Colors.white, fontSize: 14)),
      ]),
    );
  }

  PopupMenuItem<String> _menuItemDanger(IconData icon, String label, String value) {
    return PopupMenuItem(
      value: value,
      child: Row(children: [
        Icon(icon, size: 18, color: Colors.redAccent),
        SizedBox(width: 10),
        Text(label, style: TextStyle(color: Colors.redAccent, fontSize: 14)),
      ]),
    );
  }

  Widget _buildContent(CommunityPost post) {
    return Padding(
      padding: EdgeInsets.fromLTRB(12, 0, 12, 10),
      child: RichText(
        text: TextSpan(
          style: TextStyle(color: Colors.white, fontSize: 15, height: 1.45),
          children: _parseText(post.content!),
        ),
      ),
    );
  }

  List<TextSpan> _parseText(String text) {
    final spans = <TextSpan>[];
    final regex = RegExp(r'#\w+|@\w+');
    int last = 0;
    for (final m in regex.allMatches(text)) {
      if (m.start > last) {
        spans.add(TextSpan(text: text.substring(last, m.start)));
      }
      spans.add(TextSpan(
        text: m.group(0),
        style: TextStyle(color: Color(0xFF1877F2), fontWeight: FontWeight.w600),
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
      if (m.type == 'video') {
        return AspectRatio(
          aspectRatio: 16 / 9,
          child: Stack(fit: StackFit.expand, children: [
            if (m.thumbnail != null)
              NetImage(url: m.thumbnail!, fit: BoxFit.cover)
            else
              Container(color: Colors.black),
            Container(color: Colors.black26),
            Center(
              child: Container(
                padding: EdgeInsets.all(14),
                decoration: BoxDecoration(color: Colors.black54, shape: BoxShape.circle),
                child: Icon(Icons.play_arrow_rounded, size: 42, color: Colors.white),
              ),
            ),
          ]),
        );
      }
      return NetImage(
        url: m.url,
        fit: BoxFit.fitWidth,
        width: double.infinity,
      );
    }

    if (media.length == 2) {
      return Row(
        children: media.take(2).map((m) => Expanded(
          child: AspectRatio(
            aspectRatio: 1,
            child: Padding(
              padding: EdgeInsets.all(1),
              child: NetImage(url: m.url, fit: BoxFit.cover),
            ),
          ),
        )).toList(),
      );
    }

    return SizedBox(
      height: 260,
      child: Row(
        children: [
          Expanded(
            flex: 2,
            child: Padding(
              padding: EdgeInsets.only(right: 1),
              child: NetImage(url: media[0].url, fit: BoxFit.cover, height: double.infinity),
            ),
          ),
          Expanded(
            child: Column(
              children: [
                for (var i = 1; i < media.length && i < 4; i++)
                  Expanded(
                    child: Padding(
                      padding: EdgeInsets.only(bottom: 1),
                      child: i == 3 && media.length > 4
                          ? Stack(fit: StackFit.expand, children: [
                              NetImage(url: media[3].url, fit: BoxFit.cover),
                              Container(
                                color: Colors.black54,
                                child: Center(
                                  child: Text('+${media.length - 3}',
                                      style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold)),
                                ),
                              ),
                            ])
                          : NetImage(url: media[i].url, fit: BoxFit.cover),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPoll(CommunityPost post) {
    final total = post.pollOptions.fold(0, (s, o) => s + o.votes);
    return Padding(
      padding: EdgeInsets.fromLTRB(12, 0, 12, 12),
      child: Column(
        children: post.pollOptions.asMap().entries.map((e) {
          final pct = total > 0 ? e.value.votes / total : 0.0;
          return Padding(
            padding: EdgeInsets.only(bottom: 8),
            child: GestureDetector(
              onTap: () async {
                try {
                  final r = await _repo.votePoll(post.id, e.key);
                  setState(() {
                    final opts = r['poll_options'] as List;
                    for (var i = 0; i < opts.length; i++) {
                      post.pollOptions[i].votes = opts[i]['votes'] as int;
                    }
                  });
                } catch (err) {
                  if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Failed to vote: $err'), backgroundColor: Colors.red));
                }
              },
              child: ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: Stack(children: [
                  Container(height: 42, color: const Color(0xFF3A3B3C)),
                  FractionallySizedBox(
                    widthFactor: pct,
                    child: Container(height: 42, color: const Color(0xFF1877F2).withOpacity(0.3)),
                  ),
                  Padding(
                    padding: EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(e.value.text, style: TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13.5)),
                        Text('${(pct * 100).round()}%', style: TextStyle(color: Color(0xFF1877F2), fontWeight: FontWeight.w700, fontSize: 13.5)),
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

  Widget _buildReactionSummary(CommunityPost post) {
    if (post.likesCount == 0 && post.commentsCount == 0 && post.viewsCount == 0) return const SizedBox.shrink();
    return Padding(
      padding: EdgeInsets.fromLTRB(12, 8, 12, 6),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(children: [
            if (post.likesCount > 0) ...[
              Container(
                padding: EdgeInsets.all(3),
                decoration: BoxDecoration(color: Color(0xFF1877F2), shape: BoxShape.circle),
                child: Text('👍', style: TextStyle(fontSize: 10)),
              ),
              SizedBox(width: 5),
              Text('${post.likesCount}', style: TextStyle(color: Color(0xFF8A8D91), fontSize: 13.5)),
              SizedBox(width: 10),
            ],
            if (post.viewsCount > 0) ...[
              Icon(Icons.visibility_outlined, color: Color(0xFF8A8D91), size: 14),
              SizedBox(width: 3),
              Text('${post.viewsCount}', style: TextStyle(color: Color(0xFF8A8D91), fontSize: 13.5)),
            ],
          ]),
          if (post.commentsCount > 0)
            Text('${post.commentsCount} comments',
                style: TextStyle(color: Color(0xFF8A8D91), fontSize: 13.5)),
        ],
      ),
    );
  }

  Widget _buildDivider() => Divider(height: 1, color: Color(0xFF3A3B3C));

  Widget _buildActionRow(CommunityPost post) {
    final reacted = post.userReaction != null;
    final reactionData = reacted
        ? _reactions.firstWhere((r) => r['type'] == post.userReaction, orElse: () => _reactions[0])
        : null;

    return Padding(
      padding: EdgeInsets.symmetric(vertical: 2),
      child: Row(
        children: [
          Expanded(
            child: GestureDetector(
              onTap: () {
                if (reacted) {
                  _react(post.userReaction!);
                } else {
                  setState(() => _showReactions = !_showReactions);
                }
              },
              onLongPress: () => setState(() => _showReactions = true),
              child: _actionBtn(
                icon: reacted ? null : Icons.thumb_up_outlined,
                emoji: reacted ? reactionData!['emoji'] as String : null,
                label: reacted ? reactionData!['label'] as String : 'Like',
                color: reacted ? reactionData!['color'] as Color : const Color(0xFF8A8D91),
              ),
            ),
          ),
          Expanded(child: _actionBtn(icon: Icons.chat_bubble_outline_rounded, label: 'Comment', color: const Color(0xFF8A8D91))),
          Expanded(child: _actionBtn(icon: Icons.share_outlined, label: 'Share', color: const Color(0xFF8A8D91), onTap: () => _repo.sharePost(post.id))),
        ],
      ),
    );
  }

  Widget _actionBtn({IconData? icon, String? emoji, required String label, required Color color, VoidCallback? onTap}) {
    return GestureDetector(
      onTap: onTap,
      child: Padding(
        padding: EdgeInsets.symmetric(vertical: 8),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            if (emoji != null)
              Text(emoji, style: TextStyle(fontSize: 18))
            else if (icon != null)
              Icon(icon, size: 20, color: color),
            SizedBox(width: 6),
            Text(label, style: TextStyle(color: color, fontSize: 13.5, fontWeight: FontWeight.w600)),
          ],
        ),
      ),
    );
  }

  Widget _buildReactionsPopup() {
    return Positioned(
      bottom: 44,
      left: 8,
      child: Material(
        color: Colors.transparent,
        child: GestureDetector(
          onTap: () {},
          child: Container(
            padding: EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFF3A3B3C),
              borderRadius: BorderRadius.circular(30),
              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.4), blurRadius: 12, offset: const Offset(0, 4))],
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: _reactions.map((r) => GestureDetector(
                onTap: () => _react(r['type'] as String),
                child: Padding(
                  padding: EdgeInsets.symmetric(horizontal: 4),
                  child: Text(r['emoji'] as String, style: TextStyle(fontSize: 28)),
                ),
              )).toList(),
            ),
          ),
        ),
      ),
    );
  }

  IconData _privacyIcon(String p) {
    switch (p) {
      case 'followers': return Icons.people_rounded;
      case 'private': return Icons.lock_rounded;
      default: return Icons.public_rounded;
    }
  }

  void _handleMenu(String v, CommunityPost post) {
    switch (v) {
      case 'save': _repo.savePost(post.id);
      case 'share': _repo.sharePost(post.id);
      case 'delete': widget.onDelete?.call();
      case 'report':
        showModalBottomSheet(
          context: context,
          backgroundColor: const Color(0xFF3A3B3C),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
          builder: (_) => Column(
            mainAxisSize: MainAxisSize.min,
            children: ['spam', 'hate', 'violence', 'nudity', 'misinformation', 'other'].map((reason) =>
              ListTile(
                title: Text(reason[0].toUpperCase() + reason.substring(1), style: TextStyle(color: Colors.white)),
                onTap: () { Navigator.pop(context); _repo.report('post', post.id, reason); },
              )).toList(),
          ),
        );
    }
  }
}
