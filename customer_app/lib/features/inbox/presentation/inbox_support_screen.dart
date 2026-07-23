import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:audio_waveforms/audio_waveforms.dart';
import 'package:livekit_client/livekit_client.dart';
import '../../../../core/services/realtime_client.dart';
import '../data/inbox_models.dart';
import '../data/inbox_repository.dart';
import 'inbox_widgets.dart';

class InboxSupportScreen extends ConsumerStatefulWidget {
  const InboxSupportScreen({super.key, required this.conversation, required this.repo});
  final InboxConversation conversation;
  final InboxRepository repo;

  @override
  ConsumerState<InboxSupportScreen> createState() => _InboxSupportScreenState();
}

class _InboxSupportScreenState extends ConsumerState<InboxSupportScreen> {
  final _msgCtrl      = TextEditingController();
  final _scrollCtrl   = ScrollController();
  final _recorder     = RecorderController();
  final List<InboxMessage> _messages = [];
  InboxConversation? _conv;

  bool _loading    = true;
  bool _recording  = false;
  bool _agentTyping = false;
  bool _sending    = false;
  Timer? _typingTimer;
  Timer? _typingClearTimer;

  // LiveKit call
  Room? _room;
  bool _inCall = false;
  bool _callMuted = false;

  @override
  void initState() {
    super.initState();
    _loadMessages();
    _subscribeRealtime();
  }

  @override
  void dispose() {
    _msgCtrl.dispose();
    _scrollCtrl.dispose();
    _recorder.dispose();
    _typingTimer?.cancel();
    _typingClearTimer?.cancel();
    _unsubscribeRealtime();
    _room?.disconnect();
    super.dispose();
  }

  Future<void> _loadMessages() async {
    try {
      final data = await widget.repo.getMessages(widget.conversation.uuid);
      if (mounted) setState(() {
        _conv = data['conversation'] as InboxConversation;
        _messages.addAll(data['messages'] as List<InboxMessage>);
        _loading = false;
      });
      _scrollToBottom();
    } catch (e) {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _subscribeRealtime() {
    final client  = RealtimeClient.instance;
    final channel = 'private-inbox.${widget.conversation.uuid}';

    client.listen(channel, 'message', _onRealtimeMessage);
    client.listen(channel, 'typing',  _onRealtimeTyping);
  }

  void _onRealtimeMessage(dynamic raw) {
    final data = raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
    final event = data['event'] as String?;

    if (event == 'seen' || event == 'delivered') {
      setState(() {
        for (var i = 0; i < _messages.length; i++) {
          if (_messages[i].isFromUser) {
            _messages[i] = InboxMessage(
              uuid: _messages[i].uuid, senderId: _messages[i].senderId,
              senderType: _messages[i].senderType, senderName: _messages[i].senderName,
              type: _messages[i].type, content: _messages[i].content,
              mediaUrl: _messages[i].mediaUrl, duration: _messages[i].duration,
              status: event == 'seen' ? MsgStatus.seen : MsgStatus.delivered,
              isDeleted: _messages[i].isDeleted, createdAt: _messages[i].createdAt,
            );
          }
        }
      });
    } else if (event == 'call_initiated') {
      _onIncomingCall(data);
    } else if (data.containsKey('uuid')) {
      // New message from agent
      try {
        final msg = InboxMessage.fromJson(data);
        if (mounted) setState(() { _messages.add(msg); _agentTyping = false; });
        _scrollToBottom();
      } catch (_) {}
    }
  }

  void _onRealtimeTyping(dynamic raw) {
    final data = raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
    if (data['sender_type'] == 'agent') {
      if (mounted) setState(() => _agentTyping = true);
      _typingClearTimer?.cancel();
      _typingClearTimer = Timer(const Duration(seconds: 3), () {
        if (mounted) setState(() => _agentTyping = false);
      });
    }
  }

  void _unsubscribeRealtime() {
    RealtimeClient.instance.unsubscribe('private-inbox.${widget.conversation.uuid}');
  }

  void _onTypingChanged(String _) {
    _typingTimer?.cancel();
    _typingTimer = Timer(const Duration(milliseconds: 500), () {
      widget.repo.sendTyping(widget.conversation.uuid);
    });
  }

  Future<void> _sendText() async {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty || _sending) return;
    _msgCtrl.clear();
    setState(() => _sending = true);
    try {
      final msg = await widget.repo.sendTextMessage(widget.conversation.uuid, text);
      setState(() => _messages.add(msg));
      _scrollToBottom();
    } catch (_) {} finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _pickImage() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 70);
    if (picked == null || !mounted) return;
    setState(() => _sending = true);
    try {
      final msg = await widget.repo.sendMediaMessage(
          widget.conversation.uuid, File(picked.path), 'image');
      setState(() => _messages.add(msg));
      _scrollToBottom();
    } catch (_) {} finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _toggleRecording() async {
    if (_recording) {
      final path = await _recorder.stop();
      setState(() => _recording = false);
      if (path == null || !mounted) return;
      setState(() => _sending = true);
      try {
        final durationMs = _recorder.elapsedDuration.inSeconds;
        final msg = await widget.repo.sendMediaMessage(
            widget.conversation.uuid, File(path), 'audio', duration: durationMs);
        setState(() => _messages.add(msg));
        _scrollToBottom();
      } catch (_) {} finally {
        if (mounted) setState(() => _sending = false);
      }
    } else {
      await _recorder.record();
      setState(() => _recording = true);
    }
  }

  Future<void> _startCall() async {
    try {
      final data = await widget.repo.initiateCall(widget.conversation.uuid);
      final url   = data['livekit_url'] as String? ?? '';
      final token = data['token'] as String? ?? '';
      if (url.isEmpty || token.isEmpty) {
        _toast('Audio call not available');
        return;
      }
      _room = Room();
      await _room!.connect(url, token);
      await _room!.localParticipant?.setMicrophoneEnabled(true);
      if (mounted) setState(() => _inCall = true);
    } catch (e) {
      _toast('Could not start call');
    }
  }

  Future<void> _endCall() async {
    await _room?.disconnect();
    setState(() { _inCall = false; _room = null; });
  }

  void _toggleMute() {
    _room?.localParticipant?.setMicrophoneEnabled(_callMuted);
    setState(() => _callMuted = !_callMuted);
  }

  void _onIncomingCall(Map data) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        title: const Text('Incoming Call'),
        content: const Text('Support agent is calling you'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Decline')),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              final callUuid = data['call_uuid'] as String?;
              if (callUuid == null) return;
              final d = await widget.repo.joinCall(callUuid);
              final url   = d['livekit_url'] as String? ?? '';
              final token = d['token'] as String? ?? '';
              if (url.isEmpty || token.isEmpty) return;
              _room = Room();
              await _room!.connect(url, token);
              await _room!.localParticipant?.setMicrophoneEnabled(true);
              if (mounted) setState(() => _inCall = true);
            },
            child: const Text('Answer'),
          ),
        ],
      ),
    );
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(
          _scrollCtrl.position.maxScrollExtent,
          duration: const Duration(milliseconds: 300),
          curve: Curves.easeOut,
        );
      }
    });
  }

  void _toast(String msg) =>
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final conv   = _conv ?? widget.conversation;
    final mod    = kSupportModules.firstWhere((m) => m.id == conv.module, orElse: () => kSupportModules.first);

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF0F0F1A) : const Color(0xFFF5F6FA),
      appBar: AppBar(
        backgroundColor: isDark ? const Color(0xFF1A1A2E) : Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: isDark ? Colors.white : const Color(0xFF07003B)),
          onPressed: () => Navigator.pop(context),
        ),
        title: Row(
          children: [
            Container(
              width: 36, height: 36,
              decoration: BoxDecoration(
                color: const Color(0xFF6C63FF).withAlpha(30),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Center(child: Text(mod.icon, style: const TextStyle(fontSize: 18))),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(conv.subject ?? mod.label, style: TextStyle(
                    fontSize: 13, fontWeight: FontWeight.w700,
                    color: isDark ? Colors.white : const Color(0xFF07003B),
                  ), maxLines: 1, overflow: TextOverflow.ellipsis),
                  if (conv.agent != null)
                    Text(conv.agent!['name'] ?? 'Support Agent',
                        style: const TextStyle(fontSize: 10, color: Color(0xFF00BFA5))),
                ],
              ),
            ),
          ],
        ),
        actions: [
          if (!conv.isResolved)
            IconButton(
              icon: Icon(_inCall ? Icons.call_end : Icons.call,
                  color: _inCall ? Colors.red : const Color(0xFF6C63FF)),
              onPressed: _inCall ? _endCall : _startCall,
            ),
        ],
      ),
      body: Column(
        children: [
          // Active call bar
          if (_inCall) _CallBar(onEnd: _endCall, onMute: _toggleMute, muted: _callMuted),

          // Resolved banner
          if (conv.isResolved) Container(
            padding: const EdgeInsets.symmetric(vertical: 8),
            color: Colors.green.withAlpha(20),
            child: const Center(child: Text('✅ This conversation is resolved',
                style: TextStyle(color: Colors.green, fontSize: 12))),
          ),

          // Messages
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: Color(0xFF6C63FF)))
                : ListView.builder(
                    controller: _scrollCtrl,
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
                    itemCount: _messages.length + (_agentTyping ? 1 : 0),
                    itemBuilder: (ctx, i) {
                      if (_agentTyping && i == _messages.length) return const _TypingBubble();
                      final msg = _messages[i];
                      return InboxMessageBubble(msg: msg);
                    },
                  ),
          ),

          // Input bar
          if (!conv.isResolved)
            _InputBar(
              controller: _msgCtrl,
              recording: _recording,
              sending: _sending,
              onChanged: _onTypingChanged,
              onSend: _sendText,
              onImage: _pickImage,
              onRecord: _toggleRecording,
            ),
        ],
      ),
    );
  }
}

// ── Call bar ──────────────────────────────────────────────────────────────────

class _CallBar extends StatefulWidget {
  const _CallBar({required this.onEnd, required this.onMute, required this.muted});
  final VoidCallback onEnd, onMute;
  final bool muted;

  @override
  State<_CallBar> createState() => _CallBarState();
}

class _CallBarState extends State<_CallBar> {
  late Timer _timer;
  int _seconds = 0;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => setState(() => _seconds++));
  }

  @override
  void dispose() { _timer.cancel(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final m = _seconds ~/ 60;
    final s = _seconds % 60;
    return Container(
      color: const Color(0xFF1B5E20),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      child: Row(
        children: [
          const Icon(Icons.call, color: Colors.white, size: 18),
          const SizedBox(width: 8),
          Text('${m.toString().padLeft(2,'0')}:${s.toString().padLeft(2,'0')}',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600)),
          const Spacer(),
          IconButton(
            icon: Icon(widget.muted ? Icons.mic_off : Icons.mic, color: Colors.white, size: 20),
            onPressed: widget.onMute,
          ),
          IconButton(
            icon: const Icon(Icons.call_end, color: Colors.red, size: 20),
            onPressed: widget.onEnd,
          ),
        ],
      ),
    );
  }
}

// ── Typing bubble ─────────────────────────────────────────────────────────────

class _TypingBubble extends StatefulWidget {
  const _TypingBubble();
  @override
  State<_TypingBubble> createState() => _TypingBubbleState();
}

class _TypingBubbleState extends State<_TypingBubble> with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _anim;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 800))..repeat(reverse: true);
    _anim = Tween<double>(begin: 0.3, end: 1.0).animate(_ctrl);
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => Align(
    alignment: Alignment.centerLeft,
    child: Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
      decoration: BoxDecoration(
        color: const Color(0xFF6C63FF).withAlpha(20),
        borderRadius: const BorderRadius.only(
          topLeft: Radius.circular(16), topRight: Radius.circular(16),
          bottomRight: Radius.circular(16),
        ),
      ),
      child: FadeTransition(
        opacity: _anim,
        child: Row(mainAxisSize: MainAxisSize.min, children: const [
          Text('●', style: TextStyle(color: Color(0xFF6C63FF), fontSize: 8)),
          SizedBox(width: 3),
          Text('●', style: TextStyle(color: Color(0xFF6C63FF), fontSize: 8)),
          SizedBox(width: 3),
          Text('●', style: TextStyle(color: Color(0xFF6C63FF), fontSize: 8)),
        ]),
      ),
    ),
  );
}

// ── Input bar ─────────────────────────────────────────────────────────────────

class _InputBar extends StatelessWidget {
  const _InputBar({
    required this.controller, required this.recording,
    required this.sending, required this.onChanged,
    required this.onSend, required this.onImage, required this.onRecord,
  });
  final TextEditingController controller;
  final bool recording, sending;
  final ValueChanged<String> onChanged;
  final VoidCallback onSend, onImage, onRecord;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      color: isDark ? const Color(0xFF1A1A2E) : Colors.white,
      padding: EdgeInsets.only(
        left: 8, right: 8, top: 8,
        bottom: MediaQuery.of(context).padding.bottom + 8,
      ),
      child: Row(
        children: [
          IconButton(
            icon: const Icon(Icons.image_outlined, color: Color(0xFF6C63FF)),
            onPressed: onImage,
          ),
          Expanded(
            child: recording
                ? Container(
                    height: 40,
                    padding: const EdgeInsets.symmetric(horizontal: 14),
                    decoration: BoxDecoration(
                      color: isDark ? const Color(0xFF0F0F1A) : const Color(0xFFF5F6FA),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: Colors.red.withAlpha(100)),
                    ),
                    child: Row(children: const [
                      Icon(Icons.fiber_manual_record, color: Colors.red, size: 12),
                      SizedBox(width: 6),
                      Text('Recording... tap to send', style: TextStyle(color: Colors.red, fontSize: 12)),
                    ]),
                  )
                : TextField(
                    controller: controller,
                    onChanged: onChanged,
                    maxLines: 4,
                    minLines: 1,
                    style: TextStyle(color: isDark ? Colors.white : const Color(0xFF07003B), fontSize: 14),
                    decoration: InputDecoration(
                      hintText: 'Message...',
                      hintStyle: TextStyle(color: Colors.grey[500]),
                      filled: true,
                      fillColor: isDark ? const Color(0xFF0F0F1A) : const Color(0xFFF5F6FA),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(20),
                        borderSide: BorderSide.none,
                      ),
                    ),
                  ),
          ),
          ValueListenableBuilder<TextEditingValue>(
            valueListenable: controller,
            builder: (_, val, __) {
              if (val.text.isNotEmpty) {
                return IconButton(
                  icon: sending
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF6C63FF)))
                      : const Icon(Icons.send_rounded, color: Color(0xFF6C63FF)),
                  onPressed: onSend,
                );
              }
              return IconButton(
                icon: Icon(
                  recording ? Icons.stop_circle : Icons.mic_outlined,
                  color: recording ? Colors.red : const Color(0xFF6C63FF),
                ),
                onPressed: onRecord,
              );
            },
          ),
        ],
      ),
    );
  }
}
