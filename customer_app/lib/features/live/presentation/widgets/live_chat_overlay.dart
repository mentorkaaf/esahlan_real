import 'dart:async';
import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';
import '../../../../core/services/realtime_client.dart';

const _kEmojis = [
  '😂','❤️','🔥','👏','😍','🥰','😮','😢','👍','🎉',
  '💯','🙌','😁','🤣','💪','✨','🥳','😎','🤩','💕',
  '👀','😱','🤯','🥺','💀','😤','🫶','⚡','🌟','🎁',
];

class LiveChatOverlay extends StatefulWidget {
  final int roomId;
  final String reverbChannel;
  final bool isHost;

  const LiveChatOverlay({
    super.key,
    required this.roomId,
    required this.reverbChannel,
    this.isHost = false,
  });

  @override
  State<LiveChatOverlay> createState() => _LiveChatOverlayState();
}

class _LiveChatOverlayState extends State<LiveChatOverlay> {
  final _messages = <LiveChatMessage>[];
  final _scrollCtrl = ScrollController();
  final _inputCtrl = TextEditingController();
  final _repo = LiveRepository();
  bool _inputFocused = false;
  bool _showEmoji = false;
  PinnedMessage? _pinned;

  @override
  void initState() {
    super.initState();
    RealtimeClient.instance.listen(widget.reverbChannel, 'live.chat', _onChatMessage);
    RealtimeClient.instance.listen(widget.reverbChannel, 'live.message_pinned', _onPinned);
    RealtimeClient.instance.listen(widget.reverbChannel, 'live.message_unpinned', _onUnpinned);
  }

  void _onChatMessage(dynamic data) {
    if (!mounted) return;
    try {
      final msg = LiveChatMessage.fromJson(Map<String, dynamic>.from(data as Map));
      setState(() {
        _messages.add(msg);
        if (_messages.length > 100) _messages.removeAt(0);
      });
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scrollCtrl.hasClients) {
          _scrollCtrl.animateTo(
            _scrollCtrl.position.maxScrollExtent,
            duration: const Duration(milliseconds: 200),
            curve: Curves.easeOut,
          );
        }
      });
    } catch (_) {}
  }

  void _onPinned(dynamic data) {
    if (!mounted) return;
    try {
      setState(() => _pinned = PinnedMessage.fromJson(Map<String, dynamic>.from(data as Map)));
    } catch (_) {}
  }

  void _onUnpinned(dynamic _) {
    if (mounted) setState(() => _pinned = null);
  }

  Future<void> _send() async {
    final text = _inputCtrl.text.trim();
    if (text.isEmpty) return;
    _inputCtrl.clear();
    setState(() => _showEmoji = false);
    try {
      await _repo.sendMessage(widget.roomId, text);
    } catch (_) {}
  }

  void _addEmoji(String e) {
    final sel = _inputCtrl.selection;
    final text = _inputCtrl.text;
    final start = sel.start < 0 ? text.length : sel.start;
    final newText = text.substring(0, start) + e + text.substring(sel.end < 0 ? start : sel.end);
    _inputCtrl.value = TextEditingValue(
      text: newText,
      selection: TextSelection.collapsed(offset: start + e.length),
    );
  }

  void _showMessageOptions(BuildContext ctx, LiveChatMessage msg) {
    if (!widget.isHost) return;
    showModalBottomSheet(
      context: ctx,
      backgroundColor: const Color(0xFF1A1A2E),
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (_) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 12),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Text('📌', style: TextStyle(fontSize: 20)),
              title: Text('Pin Message', style: const TextStyle(color: Colors.white)),
              onTap: () {
                Navigator.pop(ctx);
                _repo.pinMessage(widget.roomId, msg.id);
                setState(() => _pinned = PinnedMessage(
                    messageId: msg.id, message: msg.message, username: msg.username));
              },
            ),
            ListTile(
              leading: const Text('🔇', style: TextStyle(fontSize: 20)),
              title: Text('Mute @${msg.username} (5 min)', style: const TextStyle(color: Colors.white)),
              onTap: () {
                Navigator.pop(ctx);
                _repo.muteChatUser(widget.roomId, msg.userId, minutes: 5);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('@${msg.username} muted for 5 minutes')));
              },
            ),
            ListTile(
              leading: const Text('⛔', style: TextStyle(fontSize: 20)),
              title: Text('Mute @${msg.username} (1 hour)', style: const TextStyle(color: Colors.orange)),
              onTap: () {
                Navigator.pop(ctx);
                _repo.muteChatUser(widget.roomId, msg.userId, minutes: 60);
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text('@${msg.username} muted for 1 hour')));
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  void dispose() {
    _scrollCtrl.dispose();
    _inputCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.end,
      children: [
        // Pinned message banner
        if (_pinned != null)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
              decoration: BoxDecoration(
                color: Colors.orange.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: Colors.orange.withValues(alpha: 0.4)),
              ),
              child: Row(
                children: [
                  const Text('📌', style: TextStyle(fontSize: 12)),
                  const SizedBox(width: 6),
                  Expanded(
                    child: RichText(
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      text: TextSpan(children: [
                        TextSpan(
                            text: '${_pinned!.username}: ',
                            style: const TextStyle(color: Colors.orange, fontWeight: FontWeight.bold, fontSize: 11)),
                        TextSpan(
                            text: _pinned!.message,
                            style: const TextStyle(color: Colors.white, fontSize: 11)),
                      ]),
                    ),
                  ),
                  if (widget.isHost)
                    GestureDetector(
                      onTap: () {
                        _repo.unpinMessage(widget.roomId);
                        setState(() => _pinned = null);
                      },
                      child: const Icon(Icons.close, color: Colors.orange, size: 14),
                    ),
                ],
              ),
            ),
          ),
        // Message list
        SizedBox(
          height: 200,
          child: ShaderMask(
            shaderCallback: (bounds) => const LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Colors.transparent, Colors.white],
              stops: [0.0, 0.35],
            ).createShader(bounds),
            blendMode: BlendMode.dstIn,
            child: ListView.builder(
              controller: _scrollCtrl,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              itemCount: _messages.length,
              itemBuilder: (_, i) => _ChatBubble(
                msg: _messages[i],
                onLongPress: widget.isHost
                    ? () => _showMessageOptions(context, _messages[i])
                    : null,
              ),
            ),
          ),
        ),
        const SizedBox(height: 6),
        // Emoji picker row
        if (_showEmoji)
          SizedBox(
            height: 44,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 12),
              itemCount: _kEmojis.length,
              itemBuilder: (_, i) => GestureDetector(
                onTap: () => _addEmoji(_kEmojis[i]),
                child: Container(
                  width: 38, height: 38,
                  margin: const EdgeInsets.only(right: 4),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Center(
                    child: Text(_kEmojis[i], style: const TextStyle(fontSize: 18)),
                  ),
                ),
              ),
            ),
          ),
        // Input row
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
              // Emoji toggle
              GestureDetector(
                onTap: () => setState(() => _showEmoji = !_showEmoji),
                child: Container(
                  width: 36, height: 36,
                  decoration: BoxDecoration(
                    color: _showEmoji
                        ? Colors.orange.withValues(alpha: 0.3)
                        : Colors.black45,
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.white24),
                  ),
                  child: const Center(child: Text('😊', style: TextStyle(fontSize: 16))),
                ),
              ),
              const SizedBox(width: 6),
              // Text field
              Expanded(
                child: Container(
                  height: 40,
                  decoration: BoxDecoration(
                    color: Colors.black45,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: _inputFocused ? Colors.orange : Colors.white24,
                      width: 1,
                    ),
                  ),
                  child: TextField(
                    controller: _inputCtrl,
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                    maxLength: 300,
                    maxLines: 1,
                    textInputAction: TextInputAction.send,
                    onSubmitted: (_) => _send(),
                    onTap: () => setState(() { _inputFocused = true; _showEmoji = false; }),
                    onEditingComplete: () => setState(() => _inputFocused = false),
                    decoration: const InputDecoration(
                      hintText: 'Say something...',
                      hintStyle: TextStyle(color: Colors.white38, fontSize: 13),
                      border: InputBorder.none,
                      counterText: '',
                      contentPadding: EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              GestureDetector(
                onTap: _send,
                child: Container(
                  width: 40, height: 40,
                  decoration: const BoxDecoration(color: Colors.orange, shape: BoxShape.circle),
                  child: const Icon(Icons.send, color: Colors.white, size: 18),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ChatBubble extends StatelessWidget {
  final LiveChatMessage msg;
  final VoidCallback? onLongPress;
  const _ChatBubble({required this.msg, this.onLongPress});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onLongPress: onLongPress,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Flexible(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                decoration: BoxDecoration(
                  color: Colors.black54,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: RichText(
                  text: TextSpan(
                    children: [
                      TextSpan(
                        text: msg.isHost ? '👑 ${msg.username}  ' : '${msg.username}  ',
                        style: TextStyle(
                          color: msg.isHost ? Colors.orange : Colors.white70,
                          fontWeight: FontWeight.bold,
                          fontSize: 12,
                        ),
                      ),
                      TextSpan(
                        text: msg.message,
                        style: const TextStyle(color: Colors.white, fontSize: 13),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
