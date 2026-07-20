import 'dart:async';
import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';
import '../../../../core/services/realtime_client.dart';

class LiveChatOverlay extends StatefulWidget {
  final int roomId;
  final String reverbChannel;

  const LiveChatOverlay({
    super.key,
    required this.roomId,
    required this.reverbChannel,
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

  @override
  void initState() {
    super.initState();
    RealtimeClient.instance.listen(
      widget.reverbChannel,
      'live.chat',
      _onChatMessage,
    );
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

  Future<void> _send() async {
    final text = _inputCtrl.text.trim();
    if (text.isEmpty) return;
    _inputCtrl.clear();
    try {
      await _repo.sendMessage(widget.roomId, text);
    } catch (_) {}
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
        // Message list — left side, semi-transparent
        SizedBox(
          height: 220,
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
              itemBuilder: (_, i) => _ChatBubble(msg: _messages[i]),
            ),
          ),
        ),
        const SizedBox(height: 8),
        // Input row
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Row(
            children: [
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
                    onTap: () => setState(() => _inputFocused = true),
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
                  decoration: const BoxDecoration(
                    color: Colors.orange,
                    shape: BoxShape.circle,
                  ),
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
  const _ChatBubble({required this.msg});

  @override
  Widget build(BuildContext context) {
    return Padding(
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
    );
  }
}
