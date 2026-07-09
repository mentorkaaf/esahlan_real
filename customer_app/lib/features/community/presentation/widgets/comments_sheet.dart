import 'dart:async';
import 'dart:io';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import 'package:audioplayers/audioplayers.dart' as ap;
import 'package:audio_waveforms/audio_waveforms.dart';
import 'package:path_provider/path_provider.dart';
import 'package:record/record.dart';
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
  List<CommunityComment> _roots = [];
  Map<int, List<CommunityComment>> _repliesMap = {};
  bool _loading = true;
  bool _sending = false;
  int? _replyToId;
  String? _replyToName;
  String? _mentionUser; // @username prepended when replying to a reply
  XFile? _mediaFile;
  String? _mediaType;
  RecorderController? _recorderCtrl; // waveform visualization only
  AudioRecorder? _audioRecorder;     // actual high-quality capture
  String? _recordPath;
  bool _recording = false;
  int _recordSeconds = 0;
  Timer? _recordTimer;

  @override
  void initState() { super.initState(); _loadComments(); }

  Future<void> _loadComments() async {
    try {
      final comments = await _repo.getComments(widget.postId);
      if (mounted) setState(() { _comments = comments; _loading = false; _rebuildIndex(); });
    } catch (_) { if (mounted) setState(() => _loading = false); }
  }

  void _rebuildIndex() {
    _roots = _comments.where((c) => c.parentId == null).toList();
    _repliesMap = {};
    for (final c in _comments.where((c) => c.parentId != null)) {
      _repliesMap.putIfAbsent(c.parentId!, () => []).add(c);
    }
  }

  Future<void> _pickImage() async {
    final f = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 70);
    if (f != null) setState(() { _mediaFile = f; _mediaType = 'image'; });
  }

  Future<void> _toggleRecording() async {
    if (_recording) {
      _recordTimer?.cancel();
      try {
        await _audioRecorder?.stop();
        _recorderCtrl?.stop();
      } catch (_) {}
      final path = _recordPath;
      if (path != null && mounted) {
        final file = File(path);
        if (await file.exists()) {
          setState(() { _mediaFile = XFile(path); _mediaType = 'voice'; _recording = false; _recordSeconds = 0; });
        } else {
          ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Recording failed — file not saved'), backgroundColor: Colors.red));
          setState(() { _recording = false; _recordSeconds = 0; });
        }
      } else {
        setState(() { _recording = false; _recordSeconds = 0; });
      }
    } else {
      try {
        final hasPermission = await AudioRecorder().hasPermission();
        if (!hasPermission) {
          if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Microphone permission denied'), backgroundColor: Colors.red));
          return;
        }
        final dir = await getTemporaryDirectory();
        _recordPath = '${dir.path}/voice_${DateTime.now().millisecondsSinceEpoch}.m4a';

        // High-quality voice recording with hardware noise suppression
        _audioRecorder = AudioRecorder();
        await _audioRecorder!.start(
          const RecordConfig(
            encoder: AudioEncoder.aacLc,
            sampleRate: 16000,    // 16kHz — optimal for voice (less noise than 44.1kHz)
            bitRate: 96000,       // 96kbps — clear voice quality
            numChannels: 1,       // mono — voice doesn't need stereo
            noiseSuppress: true,  // Android hardware noise suppressor
            echoCancellation: true, // Android hardware echo canceller
            autoGain: true,       // Automatic gain control
          ),
          path: _recordPath!,
        );

        // Waveform visualization (separate controller, not used for audio output)
        _recorderCtrl = RecorderController()..androidEncoder = AndroidEncoder.aac;

        _recordTimer = Timer.periodic(const Duration(seconds: 1), (_) {
          if (mounted) setState(() => _recordSeconds++);
        });
        setState(() => _recording = true);
      } catch (e) {
        if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Recording failed: $e'), backgroundColor: Colors.red));
      }
    }
  }

  @override
  void dispose() {
    _textCtrl.dispose(); _focusNode.dispose();
    _recorderCtrl?.dispose(); _recordTimer?.cancel();
    _audioRecorder?.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final rawText = _textCtrl.text.trim();
    if (rawText.isEmpty && _mediaFile == null) return;
    if (_sending) return;
    setState(() => _sending = true);
    // Prepend @mention when replying to a specific user within a thread
    final mention = _mentionUser != null ? '@$_mentionUser ' : '';
    final text = mention + rawText;
    try {
      if (_mediaFile != null) {
        final file = File(_mediaFile!.path);
        if (!await file.exists()) { setState(() => _sending = false); return; }
        final bytes = await file.readAsBytes();
        final ext = _mediaFile!.path.split('.').last.toLowerCase();
        final fname = _mediaType == 'voice' ? 'voice.aac' : 'comment_media.$ext';
        final mime = _mediaType == 'voice' ? 'audio/aac' : (ext == 'png' ? 'image/png' : 'image/jpeg');
        final form = FormData.fromMap({
          'content': rawText.isNotEmpty ? text : (_mediaType == 'voice' ? 'Voice message' : 'Image'),
          if (_replyToId != null) 'parent_id': _replyToId,
          'type': _mediaType ?? 'image',
          'media': MultipartFile.fromBytes(bytes, filename: fname, contentType: DioMediaType.parse(mime)),
        });
        await _repo.addMediaComment(widget.postId, form);
      } else {
        await _repo.addComment(widget.postId, text, parentId: _replyToId);
      }
      _textCtrl.clear();
      setState(() { _replyToId = null; _replyToName = null; _mentionUser = null; _mediaFile = null; _mediaType = null; _sending = false; });
      await _loadComments();
    } catch (e) {
      setState(() => _sending = false);
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: Colors.red));
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Container(
      decoration: BoxDecoration(color: c.cardBg, borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      child: Column(children: [
        SizedBox(height: 8),
        Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2))),
        Padding(padding: EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(children: [
            Text('Comments', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            SizedBox(width: 8),
            Text('(${_comments.length})', style: TextStyle(color: context.colors.mutedText, fontSize: 14)),
          ])),
        Divider(height: 1),
        Expanded(
          child: _loading
              ? Center(child: CircularProgressIndicator(color: kOrange))
              : _roots.isEmpty
                  ? Center(child: Text('No comments yet.\nBe the first!', textAlign: TextAlign.center, style: TextStyle(color: context.colors.mutedText)))
                  : ListView.builder(
                      padding: EdgeInsets.only(top: 8, bottom: 8),
                      itemCount: _roots.length,
                      itemBuilder: (_, i) {
                        final root = _roots[i];
                        final replies = _repliesMap[root.id] ?? [];
                        return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          _CommentTile(comment: root, onReply: () => _setReply(root), isReply: false,
                            onEdit: root.user.isMe ? (newContent) async {
                              await _repo.updateComment(root.id, newContent);
                              setState(() { final idx = _comments.indexWhere((c) => c.id == root.id); if (idx >= 0) _comments[idx] = _comments[idx].copyWith(content: newContent); _rebuildIndex(); });
                            } : null,
                            onDelete: root.user.isMe ? () async {
                              await _repo.deleteComment(root.id);
                              setState(() { _comments.removeWhere((c) => c.id == root.id || c.parentId == root.id); _rebuildIndex(); });
                            } : null),
                          if (replies.isNotEmpty)
                            Padding(padding: EdgeInsets.only(left: 44),
                              child: Column(children: [
                                Container(width: 2, height: 8, color: context.colors.dividerColor),
                                ...replies.map((r) => _CommentTile(comment: r, onReply: () => _setReplyToReply(root, r), isReply: true,
                                  onEdit: r.user.isMe ? (newContent) async {
                                    await _repo.updateComment(r.id, newContent);
                                    setState(() { final idx = _comments.indexWhere((c) => c.id == r.id); if (idx >= 0) _comments[idx] = _comments[idx].copyWith(content: newContent); _rebuildIndex(); });
                                  } : null,
                                  onDelete: r.user.isMe ? () async {
                                    await _repo.deleteComment(r.id);
                                    setState(() { _comments.removeWhere((c) => c.id == r.id); _rebuildIndex(); });
                                  } : null)),
                              ])),
                        ]);
                      }),
        ),
        Divider(height: 1),
        if (_replyToName != null) Container(
          color: c.inputFill, padding: EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          child: Row(children: [
            Icon(Icons.reply_rounded, size: 16, color: kOrange),
            SizedBox(width: 6),
            Text('Replying to $_replyToName', style: TextStyle(color: context.colors.mutedText, fontSize: 13, fontWeight: FontWeight.w600)),
            Spacer(),
            GestureDetector(onTap: () => setState(() { _replyToId = null; _replyToName = null; _mentionUser = null; }),
              child: Icon(Icons.close, size: 16, color: context.colors.mutedText)),
          ])),
        if (_mediaFile != null) Container(
          margin: EdgeInsets.fromLTRB(12, 6, 12, 0),
          padding: EdgeInsets.all(10),
          decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.05), borderRadius: BorderRadius.circular(12),
            border: Border.all(color: kOrange.withValues(alpha: 0.2))),
          child: Row(children: [
            if (_mediaType == 'image')
              ClipRRect(borderRadius: BorderRadius.circular(8),
                child: Image.file(File(_mediaFile!.path), width: 50, height: 50, fit: BoxFit.cover))
            else
              Container(width: 42, height: 42, decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(10)),
                child: Icon(Icons.mic_rounded, color: Colors.white, size: 22)),
            SizedBox(width: 10),
            Expanded(child: _mediaType == 'voice'
              ? Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Voice message', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: context.colors.bodyText)),
                  SizedBox(height: 4),
                  _MiniLocalAudioPlayer(path: _mediaFile!.path),
                ])
              : Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('Image attached', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.bodyText)),
                  Text('Tap send to post', style: TextStyle(fontSize: 11, color: kOrange.withValues(alpha: 0.7))),
                ])),
            GestureDetector(onTap: () => setState(() { _mediaFile = null; _mediaType = null; }),
              child: Container(width: 28, height: 28, margin: EdgeInsets.only(right: 8),
                decoration: BoxDecoration(color: Colors.red.withValues(alpha: 0.1), shape: BoxShape.circle),
                child: Icon(Icons.close, size: 16, color: Colors.red))),
            GestureDetector(onTap: _send,
              child: Container(width: 40, height: 40,
                decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle),
                child: _sending ? Padding(padding: EdgeInsets.all(10), child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                    : Icon(Icons.send_rounded, color: Colors.white, size: 20))),
          ])),
        Padding(
          padding: EdgeInsets.only(left: 12, right: 12, top: 8, bottom: MediaQuery.of(context).viewInsets.bottom + 12),
          child: Row(children: [
            GestureDetector(onTap: _pickImage,
              child: Container(width: 34, height: 34, margin: EdgeInsets.only(right: 4),
                decoration: BoxDecoration(color: c.inputFill, shape: BoxShape.circle),
                child: Icon(Icons.image_rounded, color: kOrange, size: 16))),
            GestureDetector(onTap: _toggleRecording,
              child: Container(width: 34, height: 34, margin: EdgeInsets.only(right: 4),
                decoration: BoxDecoration(color: _recording ? Colors.red : c.inputFill, shape: BoxShape.circle),
                child: _recording
                    ? Row(mainAxisAlignment: MainAxisAlignment.center, mainAxisSize: MainAxisSize.min, children: [
                        Icon(Icons.stop_rounded, color: Colors.white, size: 14),
                      ])
                    : Icon(Icons.mic_rounded, color: kOrange, size: 16))),
            if (_recording) Padding(padding: EdgeInsets.only(right: 6),
              child: Text('${(_recordSeconds ~/ 60).toString().padLeft(2,'0')}:${(_recordSeconds % 60).toString().padLeft(2,'0')}',
                style: TextStyle(color: Colors.red, fontSize: 12, fontWeight: FontWeight.w700))),
            Expanded(child: Container(
              decoration: BoxDecoration(color: c.inputFill, borderRadius: BorderRadius.circular(24)),
              child: TextField(controller: _textCtrl, focusNode: _focusNode, minLines: 1, maxLines: 4,
                textInputAction: TextInputAction.send, onSubmitted: (_) => _send(),
                decoration: InputDecoration(hintText: _replyToName != null ? 'Reply to $_replyToName...' : 'Write a comment...',
                  hintStyle: TextStyle(color: context.colors.mutedText, fontSize: 14), border: InputBorder.none,
                  contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 10))))),
            SizedBox(width: 8),
            GestureDetector(onTap: _send, child: Container(width: 40, height: 40,
              decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle),
              child: _sending ? Padding(padding: EdgeInsets.all(10), child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : Icon(Icons.send_rounded, color: Colors.white, size: 20))),
          ])),
      ]),
    );
  }

  void _setReply(CommunityComment c) {
    setState(() { _replyToId = c.id; _replyToName = c.user.name; _mentionUser = null; });
    _focusNode.requestFocus();
  }

  // Replying to a reply — thread stays under root but @mentions the target user
  void _setReplyToReply(CommunityComment root, CommunityComment target) {
    setState(() {
      _replyToId = root.id;
      _replyToName = target.user.name;
      _mentionUser = target.user.username ?? target.user.name;
    });
    _focusNode.requestFocus();
  }
}

class _CommentTile extends StatefulWidget {
  final CommunityComment comment;
  final VoidCallback onReply;
  final VoidCallback? onDelete;
  final void Function(String)? onEdit;
  final bool isReply;
  const _CommentTile({required this.comment, required this.onReply, this.onDelete, this.onEdit, required this.isReply});

  @override
  State<_CommentTile> createState() => _CommentTileState();
}

class _CommentTileState extends State<_CommentTile> {
  static const _reactions = [
    ('like', '👍'), ('love', '❤️'), ('haha', '😂'), ('wow', '😮'), ('sad', '😢'),
  ];
  final _repo = CommunityRepository();
  late int _likesCount;
  late String? _userReaction;
  bool _reacting = false;

  @override
  void initState() {
    super.initState();
    _likesCount = widget.comment.likesCount;
    _userReaction = widget.comment.userReaction;
  }

  Future<void> _react(String type) async {
    if (_reacting) return;
    setState(() { _reacting = true; });
    final prev = _userReaction;
    final prevCount = _likesCount;
    // Optimistic update
    if (_userReaction == type) {
      setState(() { _userReaction = null; _likesCount = (_likesCount - 1).clamp(0, 9999); });
    } else {
      setState(() { _userReaction = type; if (prev == null) _likesCount++; });
    }
    try {
      final r = await _repo.reactToComment(widget.comment.id, type);
      if (mounted) setState(() {
        _likesCount = r['likes_count'] as int? ?? _likesCount;
        _userReaction = (r['reacted'] as bool? ?? false) ? (r['reaction'] as String?) : null;
      });
    } catch (_) {
      if (mounted) setState(() { _userReaction = prev; _likesCount = prevCount; });
    } finally {
      if (mounted) setState(() => _reacting = false);
    }
  }

  Widget _buildContent(String content, dynamic c, bool isReply) {
    final fontSize = isReply ? 13.0 : 14.0;
    final match = RegExp(r'^(@\S+)\s*').firstMatch(content);
    if (match != null) {
      final mention = match.group(1)!;
      final rest = content.substring(match.end);
      return RichText(text: TextSpan(children: [
        TextSpan(text: '$mention ', style: TextStyle(color: kOrange, fontWeight: FontWeight.w700, fontSize: fontSize)),
        if (rest.isNotEmpty) TextSpan(text: rest, style: TextStyle(color: c.bodyText, fontSize: fontSize, height: 1.3)),
      ]));
    }
    return Text(content, style: TextStyle(fontSize: fontSize, color: c.bodyText, height: 1.3));
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final comment = widget.comment;
    final isReply = widget.isReply;
    final isText = comment.mediaType == null;

    return Padding(
      padding: EdgeInsets.fromLTRB(isReply ? 0 : 12, isReply ? 2 : 6, 12, isReply ? 2 : 6),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        CircleNetImage(url: comment.user.avatar, size: isReply ? 28 : 36, fallbackText: comment.user.name),
        SizedBox(width: 8),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(
            padding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(color: isReply ? c.surfaceBg : c.inputFill, borderRadius: BorderRadius.circular(16)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Text(comment.user.name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: isReply ? 12 : 13, color: c.bodyText)),
                if (comment.user.isVerified) Padding(padding: EdgeInsets.only(left: 3), child: Icon(Icons.verified_rounded, size: 12, color: Color(0xFF1877F2))),
              ]),
              SizedBox(height: 2),
              if (comment.content.isNotEmpty && comment.content != 'Voice message' && comment.content != 'Image')
                _buildContent(comment.content, c, isReply),
              if (comment.mediaUrl != null && comment.mediaType == 'image')
                Padding(padding: EdgeInsets.only(top: 6),
                  child: ClipRRect(borderRadius: BorderRadius.circular(10),
                    child: NetImage(url: comment.mediaUrl, width: 180, height: 120, fit: BoxFit.cover))),
              if (comment.mediaUrl != null && comment.mediaType == 'voice')
                Padding(padding: EdgeInsets.only(top: 6), child: _MiniAudioPlayer(url: comment.mediaUrl!)),
            ]),
          ),
          SizedBox(height: 4),
          // Reaction bar
          Row(children: [
            ..._reactions.map((rec) {
              final isActive = _userReaction == rec.$1;
              return GestureDetector(
                onTap: () => _react(rec.$1),
                child: Container(
                  margin: EdgeInsets.only(right: 4),
                  padding: EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  decoration: BoxDecoration(
                    color: isActive ? kOrange.withValues(alpha: 0.15) : c.inputFill,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: isActive ? kOrange : Colors.transparent, width: 1),
                  ),
                  child: Text(rec.$2, style: TextStyle(fontSize: 14)),
                ),
              );
            }),
            if (_likesCount > 0) ...[
              SizedBox(width: 4),
              Text('$_likesCount', style: TextStyle(color: _userReaction != null ? kOrange : c.mutedText, fontSize: 11, fontWeight: FontWeight.w600)),
            ],
          ]),
          SizedBox(height: 2),
          Row(children: [
            Text(timeago.format(comment.createdAt), style: TextStyle(color: c.mutedText, fontSize: 11)),
            SizedBox(width: 14),
            GestureDetector(onTap: widget.onReply,
              child: Text('Reply', style: TextStyle(color: c.mutedText, fontSize: 11, fontWeight: FontWeight.w700))),
            if (widget.onEdit != null && isText) ...[SizedBox(width: 14),
              GestureDetector(onTap: () {
                final ctrl = TextEditingController(text: comment.content);
                showDialog(context: context, builder: (ctx) => AlertDialog(
                  title: Text('Edit comment', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16)),
                  content: TextField(controller: ctrl, maxLines: 4, decoration: InputDecoration(border: OutlineInputBorder())),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(ctx), child: Text('Cancel')),
                    TextButton(onPressed: () { Navigator.pop(ctx); widget.onEdit!(ctrl.text.trim()); },
                      child: Text('Save', style: TextStyle(color: kOrange, fontWeight: FontWeight.w700))),
                  ],
                ));
              }, child: Text('Edit', style: TextStyle(color: kOrange, fontSize: 11, fontWeight: FontWeight.w600)))],
            if (widget.onDelete != null) ...[SizedBox(width: 14),
              GestureDetector(onTap: widget.onDelete, child: Text('Delete', style: TextStyle(color: Colors.red, fontSize: 11, fontWeight: FontWeight.w600)))],
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

// Plays a local recorded file (before upload) — uses DeviceFileSource.
class _MiniLocalAudioPlayer extends StatefulWidget {
  final String path;
  const _MiniLocalAudioPlayer({required this.path});
  @override
  State<_MiniLocalAudioPlayer> createState() => _MiniLocalAudioPlayerState();
}

class _MiniLocalAudioPlayerState extends State<_MiniLocalAudioPlayer> {
  final _player = ap.AudioPlayer();
  bool _playing = false;
  Duration _duration = Duration.zero;
  Duration _position = Duration.zero;

  @override
  void initState() {
    super.initState();
    _player.onDurationChanged.listen((d) { if (mounted) setState(() => _duration = d); });
    _player.onPositionChanged.listen((p) { if (mounted) setState(() => _position = p); });
    _player.onPlayerComplete.listen((_) { if (mounted) setState(() { _playing = false; _position = Duration.zero; }); });
  }

  @override
  void dispose() { _player.dispose(); super.dispose(); }

  String _fmt(Duration d) =>
      '${(d.inSeconds ~/ 60).toString().padLeft(2,'0')}:${(d.inSeconds % 60).toString().padLeft(2,'0')}';

  @override
  Widget build(BuildContext context) {
    return Row(mainAxisSize: MainAxisSize.min, children: [
      GestureDetector(
        onTap: () async {
          if (_playing) {
            await _player.pause();
            setState(() => _playing = false);
          } else {
            await _player.play(ap.DeviceFileSource(widget.path));
            setState(() => _playing = true);
          }
        },
        child: Container(width: 30, height: 30, decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle),
          child: Icon(_playing ? Icons.pause_rounded : Icons.play_arrow_rounded, color: Colors.white, size: 16))),
      SizedBox(width: 6),
      SizedBox(width: 80, child: LinearProgressIndicator(
        value: _duration.inMilliseconds > 0 ? _position.inMilliseconds / _duration.inMilliseconds : 0,
        backgroundColor: kOrange.withValues(alpha: 0.15), color: kOrange, minHeight: 3)),
      SizedBox(width: 5),
      Text(_fmt(_duration > Duration.zero ? _duration - _position : Duration.zero),
        style: TextStyle(fontSize: 10, color: context.colors.mutedText)),
    ]);
  }
}

class _MiniAudioPlayer extends StatefulWidget {
  final String url;
  const _MiniAudioPlayer({required this.url});
  @override
  State<_MiniAudioPlayer> createState() => _MiniAudioPlayerState();
}

class _MiniAudioPlayerState extends State<_MiniAudioPlayer> {
  final _player = ap.AudioPlayer();
  bool _playing = false;
  Duration _duration = Duration.zero;
  Duration _position = Duration.zero;

  @override
  void initState() {
    super.initState();
    _player.onDurationChanged.listen((d) { if (mounted) setState(() => _duration = d); });
    _player.onPositionChanged.listen((p) { if (mounted) setState(() => _position = p); });
    _player.onPlayerComplete.listen((_) { if (mounted) setState(() { _playing = false; _position = Duration.zero; }); });
  }

  @override
  void dispose() { _player.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return Row(mainAxisSize: MainAxisSize.min, children: [
      GestureDetector(
        onTap: () async {
          if (_playing) { await _player.pause(); setState(() => _playing = false); }
          else { await _player.play(ap.UrlSource(widget.url)); setState(() => _playing = true); }
        },
        child: Container(width: 30, height: 30, decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle),
          child: Icon(_playing ? Icons.pause_rounded : Icons.play_arrow_rounded, color: Colors.white, size: 16))),
      SizedBox(width: 8),
      SizedBox(width: 100, child: LinearProgressIndicator(
        value: _duration.inMilliseconds > 0 ? _position.inMilliseconds / _duration.inMilliseconds : 0,
        backgroundColor: kOrange.withValues(alpha: 0.15), color: kOrange, minHeight: 3)),
      SizedBox(width: 6),
      Text('${(_position.inSeconds ~/ 60).toString().padLeft(2,'0')}:${(_position.inSeconds % 60).toString().padLeft(2,'0')}',
        style: TextStyle(fontSize: 10, color: context.colors.mutedText)),
    ]);
  }
}
