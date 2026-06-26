import 'dart:io';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/theme_x.dart';
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
  XFile? _mediaFile;
  String? _mediaType;

  @override
  void initState() { super.initState(); _loadComments(); }
  @override
  void dispose() { _textCtrl.dispose(); _focusNode.dispose(); super.dispose(); }

  Future<void> _loadComments() async {
    try {
      final comments = await _repo.getComments(widget.postId);
      if (mounted) setState(() { _comments = comments; _loading = false; });
    } catch (_) { if (mounted) setState(() => _loading = false); }
  }

  Future<void> _pickImage() async {
    final f = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 70);
    if (f != null) setState(() { _mediaFile = f; _mediaType = 'image'; });
  }

  Future<void> _send() async {
    final text = _textCtrl.text.trim();
    if (text.isEmpty && _mediaFile == null) return;
    if (_sending) return;
    setState(() => _sending = true);
    try {
      CommunityComment comment;
      if (_mediaFile != null) {
        final bytes = await _mediaFile!.readAsBytes();
        final form = FormData.fromMap({
          if (text.isNotEmpty) 'content': text,
          if (_replyToId != null) 'parent_id': _replyToId,
          'type': _mediaType ?? 'image',
          'media': MultipartFile.fromBytes(bytes, filename: _mediaFile!.name),
        });
        comment = await _repo.addMediaComment(widget.postId, form);
      } else {
        comment = await _repo.addComment(widget.postId, text, parentId: _replyToId);
      }
      _textCtrl.clear();
      setState(() { _comments.add(comment); _replyToId = null; _replyToName = null; _mediaFile = null; _mediaType = null; _sending = false; });
    } catch (_) { setState(() => _sending = false); }
  }

  @override
  Widget build(BuildContext context) {
    // Separate root comments and replies
    final roots = _comments.where((c) => c.parentId == null).toList();
    final repliesMap = <int, List<CommunityComment>>{};
    for (final c in _comments.where((c) => c.parentId != null)) {
      repliesMap.putIfAbsent(c.parentId!, () => []).add(c);
    }

    return Container(
      decoration: const BoxDecoration(color: Colors.white, borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      child: Column(children: [
        const SizedBox(height: 8),
        Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2))),
        Padding(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(children: [
            const Text('Comments', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            const SizedBox(width: 8),
            Text('(${_comments.length})', style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 14)),
          ])),
        const Divider(height: 1),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator(color: kOrange))
              : _comments.isEmpty
                  ? const Center(child: Text('No comments yet.\nBe the first!', textAlign: TextAlign.center, style: TextStyle(color: Color(0xFF9CA3AF))))
                  : ListView.builder(
                      padding: const EdgeInsets.only(top: 8, bottom: 8),
                      itemCount: roots.length,
                      itemBuilder: (_, i) {
                        final root = roots[i];
                        final replies = repliesMap[root.id] ?? [];
                        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          _CommentTile(comment: root, onReply: () => _setReply(root), isReply: false,
                            onDelete: root.user.isMe ? () async { await _repo.deleteComment(root.id); setState(() => _comments.removeWhere((c) => c.id == root.id)); } : null),
                          // Threaded replies — indented
                          if (replies.isNotEmpty)
                            Padding(padding: const EdgeInsets.only(left: 44),
                              child: Column(children: [
                                Container(width: 2, height: 8, color: const Color(0xFFE5E7EB)),
                                ...replies.map((r) => _CommentTile(comment: r, onReply: () => _setReply(root), isReply: true,
                                  onDelete: r.user.isMe ? () async { await _repo.deleteComment(r.id); setState(() => _comments.removeWhere((c) => c.id == r.id)); } : null)),
                              ])),
                        ]);
                      }),
        ),
        const Divider(height: 1),
        if (_replyToName != null) Container(
          color: const Color(0xFFF0F2F5), padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          child: Row(children: [
            const Icon(Icons.reply_rounded, size: 16, color: kOrange),
            const SizedBox(width: 6),
            Text('Replying to $_replyToName', style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13, fontWeight: FontWeight.w600)),
            const Spacer(),
            GestureDetector(onTap: () => setState(() { _replyToId = null; _replyToName = null; }),
              child: const Icon(Icons.close, size: 16, color: Color(0xFF9CA3AF))),
          ])),
        if (_mediaFile != null) Container(
          padding: const EdgeInsets.fromLTRB(12, 6, 12, 0),
          child: Row(children: [
            ClipRRect(borderRadius: BorderRadius.circular(8),
              child: Image.file(File(_mediaFile!.path), width: 60, height: 60, fit: BoxFit.cover)),
            const SizedBox(width: 8),
            Text(_mediaType == 'voice' ? 'Voice message' : 'Image', style: const TextStyle(color: Color(0xFF6B7280), fontSize: 12)),
            const Spacer(),
            GestureDetector(onTap: () => setState(() { _mediaFile = null; _mediaType = null; }),
              child: const Icon(Icons.close, size: 18, color: Color(0xFF9CA3AF))),
          ])),
        Padding(
          padding: EdgeInsets.only(left: 12, right: 12, top: 8, bottom: MediaQuery.of(context).viewInsets.bottom + 12),
          child: Row(children: [
            GestureDetector(onTap: _pickImage,
              child: Container(width: 36, height: 36, margin: const EdgeInsets.only(right: 6),
                decoration: BoxDecoration(color: const Color(0xFFF0F2F5), shape: BoxShape.circle),
                child: const Icon(Icons.image_rounded, color: kOrange, size: 18))),
            Expanded(child: Container(
              decoration: BoxDecoration(color: const Color(0xFFF0F2F5), borderRadius: BorderRadius.circular(24)),
              child: TextField(controller: _textCtrl, focusNode: _focusNode, minLines: 1, maxLines: 4,
                textInputAction: TextInputAction.send, onSubmitted: (_) => _send(),
                decoration: InputDecoration(hintText: _replyToName != null ? 'Reply to $_replyToName...' : 'Write a comment...',
                  hintStyle: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 14), border: InputBorder.none,
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10))))),
            const SizedBox(width: 8),
            GestureDetector(onTap: _send, child: Container(width: 40, height: 40,
              decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
              child: _sending ? const Padding(padding: EdgeInsets.all(10), child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.send_rounded, color: Colors.white, size: 20))),
          ])),
      ]),
    );
  }

  void _setReply(CommunityComment c) {
    setState(() { _replyToId = c.id; _replyToName = c.user.name; });
    _focusNode.requestFocus();
  }
}

class _CommentTile extends StatelessWidget {
  final CommunityComment comment;
  final VoidCallback onReply;
  final VoidCallback? onDelete;
  final bool isReply;
  const _CommentTile({required this.comment, required this.onReply, this.onDelete, required this.isReply});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(isReply ? 0 : 12, isReply ? 2 : 6, 12, isReply ? 2 : 6),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        CircleNetImage(url: comment.user.avatar, size: isReply ? 28 : 36, fallbackText: comment.user.name),
        const SizedBox(width: 8),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(color: isReply ? const Color(0xFFF9FAFB) : const Color(0xFFF0F2F5), borderRadius: BorderRadius.circular(16)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Text(comment.user.name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: isReply ? 12 : 13, color: const Color(0xFF1A1B2E))),
                if (comment.user.isVerified) const Padding(padding: EdgeInsets.only(left: 3), child: Icon(Icons.verified_rounded, size: 12, color: Color(0xFF1877F2))),
              ]),
              const SizedBox(height: 2),
              if (comment.content.isNotEmpty && comment.content != 'Voice message' && comment.content != 'Image')
                Text(comment.content, style: TextStyle(fontSize: isReply ? 13 : 14, color: const Color(0xFF374151), height: 1.3)),
              if (comment.mediaUrl != null && comment.mediaType == 'image')
                Padding(padding: const EdgeInsets.only(top: 6),
                  child: ClipRRect(borderRadius: BorderRadius.circular(10),
                    child: NetImage(url: comment.mediaUrl, width: 180, height: 120, fit: BoxFit.cover))),
              if (comment.mediaUrl != null && comment.mediaType == 'voice')
                Padding(padding: const EdgeInsets.only(top: 6),
                  child: Row(children: [
                    Container(width: 32, height: 32, decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle),
                      child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 18)),
                    const SizedBox(width: 8),
                    const Text('Voice message', style: TextStyle(color: kOrange, fontSize: 12, fontWeight: FontWeight.w600)),
                  ])),
            ]),
          ),
          const SizedBox(height: 4),
          Row(children: [
            Text(timeago.format(comment.createdAt), style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 11)),
            const SizedBox(width: 14),
            GestureDetector(onTap: onReply,
              child: const Text('Reply', style: TextStyle(color: Color(0xFF6B7280), fontSize: 11, fontWeight: FontWeight.w700))),
            if (onDelete != null) ...[const SizedBox(width: 14),
              GestureDetector(onTap: onDelete, child: const Text('Delete', style: TextStyle(color: Colors.red, fontSize: 11, fontWeight: FontWeight.w600)))],
            const SizedBox(width: 14),
            if (comment.likesCount > 0) Text('${comment.likesCount} likes', style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 11)),
          ]),
        ])),
      ]),
    );
  }
}

void showCommentsSheet(BuildContext context, int postId, {int initialCount = 0}) {
  showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: Colors.transparent,
    builder: (_) => DraggableScrollableSheet(
      initialChildSize: 0.65, maxChildSize: 0.95, minChildSize: 0.4,
      builder: (_, __) => CommentsSheet(postId: postId, initialCount: initialCount)));
}
