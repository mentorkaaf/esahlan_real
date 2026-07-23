import 'package:flutter/material.dart';
import 'package:audioplayers/audioplayers.dart';
import '../data/inbox_models.dart';

// ── Message bubble ────────────────────────────────────────────────────────────

class InboxMessageBubble extends StatelessWidget {
  const InboxMessageBubble({super.key, required this.msg});
  final InboxMessage msg;

  @override
  Widget build(BuildContext context) {
    final isDark   = Theme.of(context).brightness == Brightness.dark;
    final isUser   = msg.isFromUser;
    const accent   = Color(0xFF6C63FF);
    final agentBg  = isDark ? const Color(0xFF1A1A2E) : Colors.white;

    return Align(
      alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.75),
        child: Column(
          crossAxisAlignment: isUser ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            // Agent name
            if (!isUser && msg.senderName != null)
              Padding(
                padding: const EdgeInsets.only(left: 4, bottom: 2),
                child: Text(msg.senderName!, style: const TextStyle(color: Color(0xFF00BFA5), fontSize: 10, fontWeight: FontWeight.w600)),
              ),
            // Bubble
            Container(
              padding: msg.type == MsgType.image ? EdgeInsets.zero : const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              decoration: BoxDecoration(
                color: isUser ? accent : agentBg,
                borderRadius: BorderRadius.only(
                  topLeft:     const Radius.circular(16),
                  topRight:    const Radius.circular(16),
                  bottomLeft:  Radius.circular(isUser ? 16 : 4),
                  bottomRight: Radius.circular(isUser ? 4  : 16),
                ),
                boxShadow: [BoxShadow(color: Colors.black.withAlpha(10), blurRadius: 4)],
              ),
              child: msg.isDeleted
                  ? Text('Message deleted', style: TextStyle(
                      color: isUser ? Colors.white.withAlpha(150) : Colors.grey,
                      fontSize: 12, fontStyle: FontStyle.italic))
                  : _buildContent(context, isUser),
            ),
            const SizedBox(height: 3),
            // Time + status
            Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(_formatTime(msg.createdAt),
                    style: TextStyle(color: Colors.grey[500], fontSize: 10)),
                if (isUser) ...[
                  const SizedBox(width: 4),
                  _StatusIcon(msg.status),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildContent(BuildContext context, bool isUser) {
    final textColor = isUser ? Colors.white : Theme.of(context).textTheme.bodyMedium?.color;
    switch (msg.type) {
      case MsgType.text:
        return Text(msg.content ?? '', style: TextStyle(color: textColor, fontSize: 14));
      case MsgType.image:
        return ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: Image.network(msg.mediaUrl ?? '', fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => const Padding(
                padding: EdgeInsets.all(12),
                child: Icon(Icons.broken_image, color: Colors.grey),
              )),
        );
      case MsgType.audio:
        return _AudioBubble(url: msg.mediaUrl ?? '', duration: msg.duration, isUser: isUser);
      default:
        return Row(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.attach_file, size: 16, color: isUser ? Colors.white : Colors.grey),
          const SizedBox(width: 6),
          Text('Attachment', style: TextStyle(color: isUser ? Colors.white : null, fontSize: 13)),
        ]);
    }
  }

  String _formatTime(DateTime dt) {
    final h = dt.hour.toString().padLeft(2, '0');
    final m = dt.minute.toString().padLeft(2, '0');
    return '$h:$m';
  }
}

// ── Status double-tick ────────────────────────────────────────────────────────

class _StatusIcon extends StatelessWidget {
  const _StatusIcon(this.status);
  final MsgStatus status;

  @override
  Widget build(BuildContext context) {
    switch (status) {
      case MsgStatus.sent:
        return const Icon(Icons.check, size: 12, color: Colors.grey);
      case MsgStatus.delivered:
        return const Icon(Icons.done_all, size: 12, color: Colors.grey);
      case MsgStatus.seen:
        return const Icon(Icons.done_all, size: 12, color: Color(0xFF6C63FF));
    }
  }
}

// ── Audio bubble ──────────────────────────────────────────────────────────────

class _AudioBubble extends StatefulWidget {
  const _AudioBubble({required this.url, required this.duration, required this.isUser});
  final String url;
  final int? duration;
  final bool isUser;

  @override
  State<_AudioBubble> createState() => _AudioBubbleState();
}

class _AudioBubbleState extends State<_AudioBubble> {
  final _player = AudioPlayer();
  bool _playing = false;
  Duration _pos = Duration.zero;
  Duration _total = Duration.zero;

  @override
  void initState() {
    super.initState();
    if (widget.duration != null) _total = Duration(seconds: widget.duration!);
    _player.onPositionChanged.listen((p) { if (mounted) setState(() => _pos = p); });
    _player.onPlayerComplete.listen((_) { if (mounted) setState(() { _playing = false; _pos = Duration.zero; }); });
    _player.onDurationChanged.listen((d) { if (mounted) setState(() => _total = d); });
  }

  @override
  void dispose() { _player.dispose(); super.dispose(); }

  Future<void> _toggle() async {
    if (_playing) {
      await _player.pause();
    } else {
      await _player.play(UrlSource(widget.url));
    }
    if (mounted) setState(() => _playing = !_playing);
  }

  @override
  Widget build(BuildContext context) {
    final color = widget.isUser ? Colors.white : const Color(0xFF6C63FF);
    final trackBg = widget.isUser ? Colors.white.withAlpha(50) : const Color(0xFF6C63FF).withAlpha(30);
    final progress = _total.inMilliseconds > 0
        ? (_pos.inMilliseconds / _total.inMilliseconds).clamp(0.0, 1.0)
        : 0.0;
    final dur = widget.duration != null
        ? _formatDur(Duration(seconds: widget.duration!))
        : _formatDur(_total);

    return SizedBox(
      width: 200,
      child: Row(
        children: [
          GestureDetector(
            onTap: _toggle,
            child: Icon(_playing ? Icons.pause_circle_filled : Icons.play_circle_filled,
                color: color, size: 32),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(
                    value: progress,
                    backgroundColor: trackBg,
                    valueColor: AlwaysStoppedAnimation<Color>(color),
                    minHeight: 4,
                  ),
                ),
                const SizedBox(height: 4),
                Text(dur, style: TextStyle(color: color.withAlpha(180), fontSize: 10)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _formatDur(Duration d) {
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }
}
