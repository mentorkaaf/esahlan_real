import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../screens/community_shell.dart';

class CommentsSheet extends ConsumerStatefulWidget {
  final int postId;
  final int initialCount;
  const CommentsSheet({super.key, required this.postId, required this.initialCount});

  @override
  ConsumerState<CommentsSheet> createState() => _CommentsSheetState();
}

class _CommentsSheetState extends ConsumerState<CommentsSheet> {
  final _repo = CommunityRepository();
  final _textCtrl = TextEditingController();
  final _focusNode = FocusNode();
  List<CommunityComment> _comments = [];
  bool _loading = true;
  bool _sending = false;
  int? _replyToId;
  String? _replyToName;

  @override
  void initState() {
    super.initState();
    _loadComments();
  }

  @override
  void dispose() {
    _textCtrl.dispose();
    _focusNode.dispose();
    super.dispose();
  }

  Future<void> _loadComments() async {
    try {
      final comments = await _repo.getComments(widget.postId);
      if (mounted) setState(() { _comments = comments; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _send() async {
    final text = _textCtrl.text.trim();
    if (text.isEmpty || _sending) return;
    setState(() => _sending = true);
    try {
      final comment = await _repo.addComment(widget.postId, text, parentId: _replyToId);
      _textCtrl.clear();
      setState(() {
        _comments.insert(0, comment);
        _replyToId = null;
        _replyToName = null;
        _sending = false;
      });
    } catch (_) {
      setState(() => _sending = false);
    }
  }

  void _setReply(CommunityComment c) {
    setState(() { _replyToId = c.id; _replyToName = c.user.name; });
    _focusNode.requestFocus();
  }

  void _clearReply() => setState(() { _replyToId = null; _replyToName = null; });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(children: [
        const SizedBox(height: 8),
        Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2))),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(children: [
            Text('Comments', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            const SizedBox(width: 8),
            Text('(${_comments.length})', style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 14)),
          ]),
        ),
        const Divider(height: 1),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator(color: kOrange))
              : _comments.isEmpty
                  ? const Center(child: Text('No comments yet.\nBe the first to comment!',
                      textAlign: TextAlign.center, style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 14, height: 1.6)))
                  : ListView.builder(
                      padding: const EdgeInsets.only(top: 8, bottom: 8),
                      itemCount: _comments.length,
                      itemBuilder: (_, i) => _CommentTile(
                        comment: _comments[i],
                        onReply: () => _setReply(_comments[i]),
                        onDelete: _comments[i].user.isMe
                            ? () async {
                                await _repo.deleteComment(_comments[i].id);
                                setState(() => _comments.removeAt(i));
                              }
                            : null,
                      ),
                    ),
        ),
        const Divider(height: 1),
        if (_replyToName != null)
          Container(
            color: const Color(0xFFF0F2F5),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
            child: Row(children: [
              Text('Replying to $_replyToName', style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13)),
              const Spacer(),
              GestureDetector(onTap: _clearReply, child: const Icon(Icons.close, size: 16, color: Color(0xFF9CA3AF))),
            ]),
          ),
        Padding(
          padding: EdgeInsets.only(
            left: 12, right: 12, top: 8,
            bottom: MediaQuery.of(context).viewInsets.bottom + 12,
          ),
          child: Row(children: [
            Expanded(
              child: Container(
                decoration: BoxDecoration(
                  color: const Color(0xFFF0F2F5),
                  borderRadius: BorderRadius.circular(24),
                ),
                child: TextField(
                  controller: _textCtrl,
                  focusNode: _focusNode,
                  minLines: 1,
                  maxLines: 4,
                  textInputAction: TextInputAction.send,
                  onSubmitted: (_) => _send(),
                  decoration: const InputDecoration(
                    hintText: 'Write a comment...',
                    hintStyle: TextStyle(color: Color(0xFF9CA3AF), fontSize: 14),
                    border: InputBorder.none,
                    contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  ),
                ),
              ),
            ),
            const SizedBox(width: 8),
            GestureDetector(
              onTap: _send,
              child: Container(
                width: 40, height: 40,
                decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
                child: _sending
                    ? const Padding(padding: EdgeInsets.all(10), child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : const Icon(Icons.send_rounded, color: Colors.white, size: 20),
              ),
            ),
          ]),
        ),
      ]),
    );
  }
}

class _CommentTile extends StatelessWidget {
  final CommunityComment comment;
  final VoidCallback onReply;
  final VoidCallback? onDelete;
  const _CommentTile({required this.comment, required this.onReply, this.onDelete});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        CircleAvatar(
          radius: 18,
          backgroundColor: const Color(0xFFF0F2F5),
          backgroundImage: comment.user.avatar != null ? CachedNetworkImageProvider(comment.user.avatar!) : null,
          child: comment.user.avatar == null
              ? Text(comment.user.name[0].toUpperCase(), style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14))
              : null,
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFFF0F2F5),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(comment.user.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF1A1B2E))),
                const SizedBox(height: 2),
                Text(comment.content, style: const TextStyle(fontSize: 14, color: Color(0xFF374151), height: 1.3)),
              ]),
            ),
            const SizedBox(height: 4),
            Row(children: [
              Text(timeago.format(comment.createdAt), style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 12)),
              const SizedBox(width: 16),
              GestureDetector(
                onTap: onReply,
                child: const Text('Reply', style: TextStyle(color: Color(0xFF6B7280), fontSize: 12, fontWeight: FontWeight.w600)),
              ),
              if (onDelete != null) ...[
                const SizedBox(width: 16),
                GestureDetector(
                  onTap: onDelete,
                  child: const Text('Delete', style: TextStyle(color: Colors.red, fontSize: 12, fontWeight: FontWeight.w600)),
                ),
              ],
              const SizedBox(width: 16),
              Text('${comment.likesCount} likes', style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 12)),
            ]),
          ]),
        ),
      ]),
    );
  }
}

void showCommentsSheet(BuildContext context, int postId, {int initialCount = 0}) {
  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => DraggableScrollableSheet(
      initialChildSize: 0.65,
      maxChildSize: 0.95,
      minChildSize: 0.4,
      builder: (_, __) => CommentsSheet(postId: postId, initialCount: initialCount),
    ),
  );
}
