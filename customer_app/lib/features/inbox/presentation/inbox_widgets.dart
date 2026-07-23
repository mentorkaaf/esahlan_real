import 'package:flutter/material.dart';
import 'package:audioplayers/audioplayers.dart';
import '../data/inbox_models.dart';

const _kAccent  = Color(0xFF6C63FF);
const _kPrimary = Color(0xFF07003B);

// ── Message bubble ────────────────────────────────────────────────────────────

class InboxMessageBubble extends StatelessWidget {
  const InboxMessageBubble({super.key, required this.msg});
  final InboxMessage msg;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final isUser = msg.isFromUser;

    return Align(
      alignment: isUser ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: EdgeInsets.only(
          bottom: 3,
          left:  isUser ? 48 : 0,
          right: isUser ? 0  : 48,
        ),
        child: Column(
          crossAxisAlignment: isUser ? CrossAxisAlignment.end : CrossAxisAlignment.start,
          children: [
            // Agent name label
            if (!isUser && msg.senderName != null)
              Padding(
                padding: const EdgeInsets.only(left: 12, bottom: 2),
                child: Text(msg.senderName!, style: TextStyle(
                  fontSize: 10, fontWeight: FontWeight.w700,
                  color: _kAccent.withAlpha(200),
                )),
              ),

            // Bubble
            Container(
              decoration: BoxDecoration(
                color: isUser ? null : (isDark ? const Color(0xFF1E1E38) : Colors.white),
                gradient: isUser ? const LinearGradient(
                  colors: [_kAccent, Color(0xFF3a36d4)],
                  begin: Alignment.topLeft, end: Alignment.bottomRight,
                ) : null,
                borderRadius: BorderRadius.only(
                  topLeft:     const Radius.circular(18),
                  topRight:    const Radius.circular(18),
                  bottomLeft:  Radius.circular(isUser ? 18 : 4),
                  bottomRight: Radius.circular(isUser ? 4  : 18),
                ),
                boxShadow: [BoxShadow(
                  color: isUser ? _kAccent.withAlpha(30) : Colors.black.withAlpha(isDark ? 20 : 6),
                  blurRadius: 8, offset: const Offset(0, 2),
                )],
              ),
              child: msg.isDeleted
                  ? Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      child: Row(mainAxisSize: MainAxisSize.min, children: [
                        Icon(Icons.do_not_disturb_alt_outlined, size: 13, color: isUser ? Colors.white.withAlpha(120) : Colors.grey),
                        const SizedBox(width: 6),
                        Text('Message deleted', style: TextStyle(
                          fontSize: 13, fontStyle: FontStyle.italic,
                          color: isUser ? Colors.white.withAlpha(150) : Colors.grey,
                        )),
                      ]),
                    )
                  : _buildContent(context, isUser, isDark),
            ),

            // Timestamp + status
            Padding(
              padding: const EdgeInsets.only(top: 3, left: 4, right: 4, bottom: 6),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                Text(
                  _formatTime(msg.createdAt),
                  style: TextStyle(fontSize: 10, color: Colors.grey[500]),
                ),
                if (isUser) ...[
                  const SizedBox(width: 3),
                  _StatusTick(msg.status),
                ],
              ]),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildContent(BuildContext context, bool isUser, bool isDark) {
    final textColor = isUser ? Colors.white : (isDark ? Colors.white.withAlpha(230) : _kPrimary);

    switch (msg.type) {
      case MsgType.text:
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Text(msg.content ?? '', style: TextStyle(color: textColor, fontSize: 14, height: 1.4)),
        );

      case MsgType.image:
        return ClipRRect(
          borderRadius: BorderRadius.only(
            topLeft:     const Radius.circular(18),
            topRight:    const Radius.circular(18),
            bottomLeft:  Radius.circular(isUser ? 18 : 4),
            bottomRight: Radius.circular(isUser ? 4  : 18),
          ),
          child: Stack(children: [
            Image.network(
              msg.mediaUrl ?? '', fit: BoxFit.cover,
              width: 220, height: 180,
              errorBuilder: (_, __, ___) => const Padding(
                padding: EdgeInsets.all(20),
                child: Icon(Icons.broken_image_outlined, color: Colors.grey, size: 32),
              ),
            ),
            Positioned(bottom: 0, left: 0, right: 0,
              child: Container(
                height: 40,
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: [Colors.transparent, Colors.black45],
                    begin: Alignment.topCenter, end: Alignment.bottomCenter,
                  ),
                ),
              ),
            ),
          ]),
        );

      case MsgType.audio:
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
          child: _AudioBubble(url: msg.mediaUrl ?? '', duration: msg.duration, isUser: isUser),
        );

      default:
        return Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.attach_file_rounded, size: 16, color: isUser ? Colors.white : Colors.grey),
            const SizedBox(width: 6),
            Text('Attachment', style: TextStyle(color: textColor, fontSize: 13)),
          ]),
        );
    }
  }

  String _formatTime(DateTime dt) {
    final h = dt.hour.toString().padLeft(2, '0');
    final m = dt.minute.toString().padLeft(2, '0');
    return '$h:$m';
  }
}

// ── Status ticks ──────────────────────────────────────────────────────────────

class _StatusTick extends StatelessWidget {
  const _StatusTick(this.status);
  final MsgStatus status;

  @override
  Widget build(BuildContext context) {
    switch (status) {
      case MsgStatus.sent:
        return const Icon(Icons.done_rounded, size: 13, color: Colors.grey);
      case MsgStatus.delivered:
        return const Icon(Icons.done_all_rounded, size: 13, color: Colors.grey);
      case MsgStatus.seen:
        return const Icon(Icons.done_all_rounded, size: 13, color: _kAccent);
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
  Duration _pos   = Duration.zero;
  Duration _total = Duration.zero;
  bool _initialized = false;

  @override
  void initState() {
    super.initState();
    if (widget.duration != null) _total = Duration(seconds: widget.duration!);
    _player.onPositionChanged.listen((p) { if (mounted) setState(() => _pos = p); });
    _player.onDurationChanged.listen((d) { if (mounted) setState(() => _total = d); });
    _player.onPlayerComplete.listen((_) {
      if (mounted) setState(() { _playing = false; _pos = Duration.zero; });
    });
  }

  @override
  void dispose() { _player.dispose(); super.dispose(); }

  Future<void> _toggle() async {
    if (_playing) {
      await _player.pause();
    } else {
      if (!_initialized) {
        await _player.setSourceUrl(widget.url);
        _initialized = true;
      }
      await _player.resume();
    }
    if (mounted) setState(() => _playing = !_playing);
  }

  @override
  Widget build(BuildContext context) {
    final color    = widget.isUser ? Colors.white : _kAccent;
    final trackBg  = widget.isUser ? Colors.white.withAlpha(40) : _kAccent.withAlpha(25);
    final progress = _total.inMilliseconds > 0
        ? (_pos.inMilliseconds / _total.inMilliseconds).clamp(0.0, 1.0)
        : 0.0;
    final dur = _formatDur(_total.inSeconds > 0 ? _total : (widget.duration != null ? Duration(seconds: widget.duration!) : Duration.zero));

    return SizedBox(
      width: 210,
      child: Row(children: [
        // Play button
        GestureDetector(
          onTap: _toggle,
          child: Container(
            width: 38, height: 38,
            decoration: BoxDecoration(
              color: widget.isUser ? Colors.white.withAlpha(25) : _kAccent.withAlpha(20),
              shape: BoxShape.circle,
            ),
            child: Icon(
              _playing ? Icons.pause_rounded : Icons.play_arrow_rounded,
              color: color, size: 22,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            // Progress bar
            ClipRRect(
              borderRadius: BorderRadius.circular(3),
              child: LinearProgressIndicator(
                value: progress,
                backgroundColor: trackBg,
                valueColor: AlwaysStoppedAnimation<Color>(color),
                minHeight: 3,
              ),
            ),
            const SizedBox(height: 5),
            Text(dur, style: TextStyle(color: color.withAlpha(180), fontSize: 10, fontWeight: FontWeight.w600)),
          ]),
        ),
      ]),
    );
  }

  String _formatDur(Duration d) {
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }
}
