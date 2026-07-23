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

// ── Design tokens ─────────────────────────────────────────────────────────────
const _kPrimary  = Color(0xFF07003B);
const _kAccent   = Color(0xFF6C63FF);
const _kBgLight  = Color(0xFFF0F2F8);
const _kBgDark   = Color(0xFF0D0D1A);

class InboxSupportScreen extends ConsumerStatefulWidget {
  const InboxSupportScreen({super.key, required this.conversation, required this.repo});
  final InboxConversation conversation;
  final InboxRepository repo;

  @override
  ConsumerState<InboxSupportScreen> createState() => _InboxSupportScreenState();
}

class _InboxSupportScreenState extends ConsumerState<InboxSupportScreen>
    with SingleTickerProviderStateMixin {
  final _msgCtrl    = TextEditingController();
  final _scrollCtrl = ScrollController();
  final _recorder   = RecorderController()
    ..androidEncoder = AndroidEncoder.aac
    ..androidOutputFormat = AndroidOutputFormat.mpeg4
    ..iosEncoder = IosEncoder.kAudioFormatMPEG4AAC
    ..sampleRate = 44100
    ..bitRate = 128000;

  final List<InboxMessage> _messages = [];
  InboxConversation? _conv;

  bool _loading      = true;
  bool _recording    = false;
  bool _agentTyping  = false;
  bool _sending      = false;
  Timer? _typingTimer;
  Timer? _typingClearTimer;

  // LiveKit call
  Room? _room;
  bool _inCall  = false;
  bool _callMuted = false;
  bool _callInitiatedByMe = false;

  // Animation for recording pulse
  late AnimationController _recAnim;
  late Animation<double>   _recScale;

  @override
  void initState() {
    super.initState();
    _recAnim  = AnimationController(vsync: this, duration: const Duration(milliseconds: 700))..repeat(reverse: true);
    _recScale = Tween<double>(begin: 1.0, end: 1.25).animate(CurvedAnimation(parent: _recAnim, curve: Curves.easeInOut));
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
    _recAnim.dispose();
    _unsubscribeRealtime();
    _room?.disconnect();
    super.dispose();
  }

  // ── Data ─────────────────────────────────────────────────────────────────────

  Future<void> _loadMessages() async {
    try {
      final res = await widget.repo.getMessages(widget.conversation.uuid);
      if (mounted) setState(() {
        _conv     = res['conversation'] as InboxConversation;
        _messages.addAll(res['messages'] as List<InboxMessage>);
        _loading  = false;
      });
      _scrollToBottom();
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  // ── Realtime ─────────────────────────────────────────────────────────────────

  void _subscribeRealtime() {
    final client  = RealtimeClient.instance;
    final channel = 'private-inbox.${widget.conversation.uuid}';
    client.listen(channel, 'message', _onRealtimeMessage);
    client.listen(channel, 'typing',  _onRealtimeTyping);
  }

  void _unsubscribeRealtime() {
    RealtimeClient.instance.unsubscribe('private-inbox.${widget.conversation.uuid}');
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
      // Deduplicate by uuid to prevent echo double-add
      final uuid = data['uuid'] as String?;
      if (uuid != null && _messages.any((m) => m.uuid == uuid)) return;
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

  void _onTypingChanged(String _) {
    _typingTimer?.cancel();
    _typingTimer = Timer(const Duration(milliseconds: 500), () {
      widget.repo.sendTyping(widget.conversation.uuid);
    });
  }

  // ── Send ──────────────────────────────────────────────────────────────────────

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

  // ── Calls ─────────────────────────────────────────────────────────────────────

  Future<void> _startCall() async {
    _callInitiatedByMe = true;
    try {
      final data  = await widget.repo.initiateCall(widget.conversation.uuid);
      final url   = data['livekit_url'] as String? ?? '';
      final token = data['token'] as String? ?? '';
      if (url.isEmpty || token.isEmpty) {
        _toast('Audio call not available yet');
        _callInitiatedByMe = false;
        return;
      }
      _room = Room();
      await _room!.connect(url, token);
      await _room!.localParticipant?.setMicrophoneEnabled(true);
      if (mounted) setState(() => _inCall = true);
    } catch (_) {
      _toast('Could not start call');
      _callInitiatedByMe = false;
    }
  }

  Future<void> _endCall() async {
    await _room?.disconnect();
    setState(() { _inCall = false; _room = null; _callInitiatedByMe = false; });
  }

  void _toggleMute() {
    _room?.localParticipant?.setMicrophoneEnabled(_callMuted);
    setState(() => _callMuted = !_callMuted);
  }

  void _onIncomingCall(Map data) {
    // Skip — user pressed call themselves
    if (_callInitiatedByMe) return;
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => _IncomingCallDialog(
        onDecline: () => Navigator.pop(ctx),
        onAnswer: () async {
          Navigator.pop(ctx);
          final callUuid = data['call_uuid'] as String?;
          if (callUuid == null) return;
          final d     = await widget.repo.joinCall(callUuid);
          final url   = d['livekit_url'] as String? ?? '';
          final token = d['token'] as String? ?? '';
          if (url.isEmpty || token.isEmpty) return;
          _room = Room();
          await _room!.connect(url, token);
          await _room!.localParticipant?.setMicrophoneEnabled(true);
          if (mounted) setState(() => _inCall = true);
        },
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

  void _toast(String msg) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(
    content: Text(msg),
    behavior: SnackBarBehavior.floating,
    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
  ));

  // ── Build ─────────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final conv   = _conv ?? widget.conversation;
    final mod    = kSupportModules.firstWhere((m) => m.id == conv.module, orElse: () => kSupportModules.first);
    final bg     = isDark ? _kBgDark : _kBgLight;

    return Scaffold(
      backgroundColor: bg,
      body: Column(children: [
        // ── AppBar ──
        _ChatAppBar(
          conv: conv, mod: mod, isDark: isDark,
          inCall: _inCall,
          onBack: () => Navigator.pop(context),
          onCall: _inCall ? _endCall : _startCall,
        ),

        // ── Active call bar ──
        if (_inCall) _ActiveCallBar(muted: _callMuted, onMute: _toggleMute, onEnd: _endCall),

        // ── Resolved notice ──
        if (conv.isResolved)
          _StatusBanner(
            icon: Icons.check_circle_outline,
            message: 'This ticket is resolved',
            color: const Color(0xFF16a34a),
          ),

        // ── Messages ──
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator(color: _kAccent, strokeWidth: 2))
              : _MessageList(
                  messages: _messages,
                  scrollCtrl: _scrollCtrl,
                  agentTyping: _agentTyping,
                ),
        ),

        // ── Input ──
        if (!conv.isResolved)
          _InputBar(
            controller: _msgCtrl,
            recording: _recording,
            sending: _sending,
            recAnim: _recAnim,
            recScale: _recScale,
            isDark: isDark,
            onChanged: _onTypingChanged,
            onSend: _sendText,
            onImage: _pickImage,
            onRecord: _toggleRecording,
          ),
      ]),
    );
  }
}

// ── Custom AppBar ─────────────────────────────────────────────────────────────

class _ChatAppBar extends StatelessWidget {
  const _ChatAppBar({
    required this.conv, required this.mod, required this.isDark,
    required this.inCall, required this.onBack, required this.onCall,
  });
  final InboxConversation conv;
  final SupportModule mod;
  final bool isDark, inCall;
  final VoidCallback onBack, onCall;

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(conv.status);
    return Container(
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF181830) : Colors.white,
        boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 12, offset: const Offset(0, 2))],
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(4, 6, 12, 10),
          child: Row(children: [
            IconButton(
              icon: Icon(Icons.arrow_back_ios_new_rounded, size: 18, color: isDark ? Colors.white : _kPrimary),
              onPressed: onBack,
            ),
            // Module avatar
            Container(
              width: 42, height: 42,
              decoration: BoxDecoration(
                gradient: LinearGradient(colors: [_kAccent.withAlpha(60), _kAccent.withAlpha(30)]),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Center(child: Text(mod.icon, style: const TextStyle(fontSize: 20))),
            ),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(
                conv.subject ?? mod.label,
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: isDark ? Colors.white : _kPrimary),
                maxLines: 1, overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: 2),
              Row(children: [
                Container(
                  width: 6, height: 6,
                  decoration: BoxDecoration(color: statusColor, shape: BoxShape.circle),
                ),
                const SizedBox(width: 4),
                Text(
                  conv.agent?['name'] != null ? 'Agent: ${conv.agent!['name']}' : _statusLabel(conv.status),
                  style: TextStyle(fontSize: 11, color: statusColor, fontWeight: FontWeight.w600),
                ),
              ]),
            ])),
            // Call button
            if (!conv.isResolved)
              GestureDetector(
                onTap: onCall,
                child: Container(
                  width: 40, height: 40,
                  decoration: BoxDecoration(
                    color: inCall ? Colors.red.withAlpha(20) : _kAccent.withAlpha(15),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    inCall ? Icons.call_end_rounded : Icons.call_rounded,
                    color: inCall ? Colors.red : _kAccent, size: 20,
                  ),
                ),
              ),
          ]),
        ),
      ),
    );
  }

  Color _statusColor(String s) {
    switch (s) {
      case 'open':     return _kAccent;
      case 'assigned': return const Color(0xFF00BFA5);
      case 'resolved': return const Color(0xFF16a34a);
      default:         return Colors.grey;
    }
  }

  String _statusLabel(String s) {
    switch (s) {
      case 'open':     return 'Waiting for agent';
      case 'assigned': return 'Agent assigned';
      case 'resolved': return 'Resolved';
      default:         return 'Closed';
    }
  }
}

// ── Active call bar ───────────────────────────────────────────────────────────

class _ActiveCallBar extends StatefulWidget {
  const _ActiveCallBar({required this.muted, required this.onMute, required this.onEnd});
  final bool muted;
  final VoidCallback onMute, onEnd;

  @override
  State<_ActiveCallBar> createState() => _ActiveCallBarState();
}

class _ActiveCallBarState extends State<_ActiveCallBar> {
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
    final m = (_seconds ~/ 60).toString().padLeft(2, '0');
    final s = (_seconds % 60).toString().padLeft(2, '0');
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF0d3320), Color(0xFF16533a)]),
        boxShadow: [BoxShadow(color: Colors.green.withAlpha(40), blurRadius: 8)],
      ),
      child: Row(children: [
        const Icon(Icons.call_rounded, color: Colors.white, size: 16),
        const SizedBox(width: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
          decoration: BoxDecoration(color: Colors.white.withAlpha(20), borderRadius: BorderRadius.circular(8)),
          child: Text('$m:$s', style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w800, letterSpacing: 1)),
        ),
        const Spacer(),
        GestureDetector(
          onTap: widget.onMute,
          child: Container(
            padding: const EdgeInsets.all(7),
            decoration: BoxDecoration(color: Colors.white.withAlpha(20), borderRadius: BorderRadius.circular(8)),
            child: Icon(widget.muted ? Icons.mic_off_rounded : Icons.mic_rounded, color: Colors.white, size: 16),
          ),
        ),
        const SizedBox(width: 10),
        GestureDetector(
          onTap: widget.onEnd,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
            decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(8)),
            child: const Row(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.call_end_rounded, color: Colors.white, size: 14),
              SizedBox(width: 5),
              Text('End', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
            ]),
          ),
        ),
      ]),
    );
  }
}

// ── Status banner ─────────────────────────────────────────────────────────────

class _StatusBanner extends StatelessWidget {
  const _StatusBanner({required this.icon, required this.message, required this.color});
  final IconData icon; final String message; final Color color;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 16),
    color: color.withAlpha(15),
    child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
      Icon(icon, color: color, size: 14),
      const SizedBox(width: 6),
      Text(message, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
    ]),
  );
}

// ── Message list ──────────────────────────────────────────────────────────────

class _MessageList extends StatelessWidget {
  const _MessageList({required this.messages, required this.scrollCtrl, required this.agentTyping});
  final List<InboxMessage> messages;
  final ScrollController scrollCtrl;
  final bool agentTyping;

  @override
  Widget build(BuildContext context) => ListView.builder(
    controller: scrollCtrl,
    padding: const EdgeInsets.fromLTRB(12, 14, 12, 10),
    itemCount: messages.length + (agentTyping ? 1 : 0),
    itemBuilder: (ctx, i) {
      if (agentTyping && i == messages.length) return const _TypingBubble();
      // Date separator
      final msg  = messages[i];
      final prev = i > 0 ? messages[i - 1] : null;
      final showDate = prev == null ||
          !_sameDay(prev.createdAt, msg.createdAt);
      return Column(mainAxisSize: MainAxisSize.min, children: [
        if (showDate) _DateSeparator(msg.createdAt),
        InboxMessageBubble(msg: msg),
      ]);
    },
  );

  static bool _sameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;
}

class _DateSeparator extends StatelessWidget {
  const _DateSeparator(this.dt);
  final DateTime dt;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final now = DateTime.now();
    String label;
    if (_sameDay(dt, now))              label = 'Today';
    else if (_sameDay(dt, now.subtract(const Duration(days: 1)))) label = 'Yesterday';
    else                                label = '${dt.day}/${dt.month}/${dt.year}';
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(children: [
        Expanded(child: Divider(color: isDark ? const Color(0xFF2A2A3E) : const Color(0xFFDDE2F0))),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF252540) : const Color(0xFFE8EBF8),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(label, style: TextStyle(
              fontSize: 10, fontWeight: FontWeight.w700,
              color: isDark ? Colors.grey[400] : Colors.grey[600],
            )),
          ),
        ),
        Expanded(child: Divider(color: isDark ? const Color(0xFF2A2A3E) : const Color(0xFFDDE2F0))),
      ]),
    );
  }

  static bool _sameDay(DateTime a, DateTime b) =>
      a.year == b.year && a.month == b.month && a.day == b.day;
}

// ── Typing bubble ─────────────────────────────────────────────────────────────

class _TypingBubble extends StatefulWidget {
  const _TypingBubble();
  @override
  State<_TypingBubble> createState() => _TypingBubbleState();
}

class _TypingBubbleState extends State<_TypingBubble> with TickerProviderStateMixin {
  final List<AnimationController> _ctrls = [];

  @override
  void initState() {
    super.initState();
    for (int i = 0; i < 3; i++) {
      final ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 500))
        ..repeat(reverse: true);
      Future.delayed(Duration(milliseconds: i * 150), () {
        if (mounted) ctrl.forward();
      });
      _ctrls.add(ctrl);
    }
  }

  @override
  void dispose() { for (final c in _ctrls) c.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Align(
      alignment: Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: isDark ? const Color(0xFF1E1E38) : Colors.white,
          borderRadius: const BorderRadius.only(
            topLeft: Radius.circular(16), topRight: Radius.circular(16),
            bottomRight: Radius.circular(16), bottomLeft: Radius.circular(4),
          ),
          boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 6)],
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          for (int i = 0; i < 3; i++) ...[
            if (i > 0) const SizedBox(width: 4),
            AnimatedBuilder(
              animation: _ctrls[i],
              builder: (_, __) => Transform.translate(
                offset: Offset(0, -4 * _ctrls[i].value),
                child: Container(
                  width: 7, height: 7,
                  decoration: BoxDecoration(color: _kAccent, shape: BoxShape.circle),
                ),
              ),
            ),
          ],
        ]),
      ),
    );
  }
}

// ── Input bar ─────────────────────────────────────────────────────────────────

class _InputBar extends StatelessWidget {
  const _InputBar({
    required this.controller, required this.recording,
    required this.sending, required this.isDark,
    required this.recAnim, required this.recScale,
    required this.onChanged, required this.onSend,
    required this.onImage, required this.onRecord,
  });
  final TextEditingController controller;
  final bool recording, sending, isDark;
  final AnimationController recAnim;
  final Animation<double> recScale;
  final ValueChanged<String> onChanged;
  final VoidCallback onSend, onImage, onRecord;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF181830) : Colors.white,
        boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 12, offset: const Offset(0, -2))],
      ),
      padding: EdgeInsets.only(
        left: 10, right: 10, top: 10,
        bottom: MediaQuery.of(context).padding.bottom + 10,
      ),
      child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [

        // Image button
        _IconBtn(
          icon: Icons.image_outlined,
          color: _kAccent,
          onTap: onImage,
        ),
        const SizedBox(width: 6),

        // Input / recording area
        Expanded(
          child: recording
              ? _RecordingIndicator(recAnim: recAnim, recScale: recScale, isDark: isDark)
              : _TextField(controller: controller, isDark: isDark, onChanged: onChanged),
        ),
        const SizedBox(width: 6),

        // Send / Mic button
        ValueListenableBuilder<TextEditingValue>(
          valueListenable: controller,
          builder: (_, val, __) {
            if (recording) {
              return _SendBtn(
                onTap: onRecord,
                child: sending
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Icon(Icons.send_rounded, color: Colors.white, size: 20),
              );
            }
            if (val.text.isNotEmpty) {
              return _SendBtn(
                onTap: onSend,
                child: sending
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Icon(Icons.send_rounded, color: Colors.white, size: 20),
              );
            }
            return _IconBtn(icon: Icons.mic_rounded, color: _kAccent, onTap: onRecord);
          },
        ),
      ]),
    );
  }
}

class _RecordingIndicator extends StatelessWidget {
  const _RecordingIndicator({required this.recAnim, required this.recScale, required this.isDark});
  final AnimationController recAnim;
  final Animation<double> recScale;
  final bool isDark;

  @override
  Widget build(BuildContext context) => Container(
    height: 46,
    padding: const EdgeInsets.symmetric(horizontal: 14),
    decoration: BoxDecoration(
      color: isDark ? const Color(0xFF2A1A1A) : const Color(0xFFFFF0F0),
      borderRadius: BorderRadius.circular(24),
      border: Border.all(color: Colors.red.withAlpha(80)),
    ),
    child: Row(children: [
      ScaleTransition(
        scale: recScale,
        child: Container(
          width: 10, height: 10,
          decoration: const BoxDecoration(color: Colors.red, shape: BoxShape.circle),
        ),
      ),
      const SizedBox(width: 10),
      const Text('Recording…  tap ➤ to send', style: TextStyle(color: Colors.red, fontSize: 12, fontWeight: FontWeight.w600)),
    ]),
  );
}

class _TextField extends StatelessWidget {
  const _TextField({required this.controller, required this.isDark, required this.onChanged});
  final TextEditingController controller;
  final bool isDark;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => TextField(
    controller: controller,
    onChanged: onChanged,
    maxLines: 4, minLines: 1,
    style: TextStyle(color: isDark ? Colors.white : _kPrimary, fontSize: 14),
    decoration: InputDecoration(
      hintText: 'Write a message…',
      hintStyle: TextStyle(color: Colors.grey[500], fontSize: 13),
      filled: true,
      fillColor: isDark ? const Color(0xFF0D0D1A) : const Color(0xFFF4F6FB),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 11),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(24),
        borderSide: BorderSide.none,
      ),
    ),
  );
}

class _IconBtn extends StatelessWidget {
  const _IconBtn({required this.icon, required this.color, required this.onTap});
  final IconData icon; final Color color; final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      width: 42, height: 42,
      decoration: BoxDecoration(color: color.withAlpha(15), borderRadius: BorderRadius.circular(12)),
      child: Icon(icon, color: color, size: 20),
    ),
  );
}

class _SendBtn extends StatelessWidget {
  const _SendBtn({required this.onTap, required this.child});
  final VoidCallback onTap; final Widget child;

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      width: 46, height: 46,
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [_kAccent, Color(0xFF3a36d4)]),
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: _kAccent.withAlpha(60), blurRadius: 8, offset: const Offset(0, 3))],
      ),
      child: Center(child: child),
    ),
  );
}

// ── Incoming call dialog ──────────────────────────────────────────────────────

class _IncomingCallDialog extends StatefulWidget {
  const _IncomingCallDialog({required this.onDecline, required this.onAnswer});
  final VoidCallback onDecline;
  final VoidCallback onAnswer;

  @override
  State<_IncomingCallDialog> createState() => _IncomingCallDialogState();
}

class _IncomingCallDialogState extends State<_IncomingCallDialog>
    with SingleTickerProviderStateMixin {
  late AnimationController _ring;
  late Animation<double>   _pulse;

  @override
  void initState() {
    super.initState();
    _ring  = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))..repeat(reverse: true);
    _pulse = Tween<double>(begin: 0.9, end: 1.1).animate(CurvedAnimation(parent: _ring, curve: Curves.easeInOut));
  }

  @override
  void dispose() { _ring.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => Dialog(
    backgroundColor: Colors.transparent,
    child: Container(
      padding: const EdgeInsets.all(28),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF07003B), Color(0xFF1a0070)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Text('Incoming Call', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700, letterSpacing: 1.5)),
        const SizedBox(height: 20),
        ScaleTransition(
          scale: _pulse,
          child: Container(
            width: 72, height: 72,
            decoration: BoxDecoration(
              color: Colors.white.withAlpha(20),
              shape: BoxShape.circle,
              border: Border.all(color: Colors.white.withAlpha(40), width: 2),
            ),
            child: const Icon(Icons.headset_mic_rounded, color: Colors.white, size: 34),
          ),
        ),
        const SizedBox(height: 14),
        const Text('eSahlan Support', style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900)),
        const SizedBox(height: 6),
        Text('Agent is calling you', style: TextStyle(color: Colors.white.withAlpha(160), fontSize: 13)),
        const SizedBox(height: 32),
        Row(children: [
          Expanded(
            child: GestureDetector(
              onTap: widget.onDecline,
              child: Container(
                height: 50,
                decoration: BoxDecoration(color: Colors.white.withAlpha(15), borderRadius: BorderRadius.circular(14)),
                child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.call_end_rounded, color: Colors.red, size: 20),
                  SizedBox(width: 6),
                  Text('Decline', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                ]),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: GestureDetector(
              onTap: widget.onAnswer,
              child: Container(
                height: 50,
                decoration: BoxDecoration(color: const Color(0xFF16a34a), borderRadius: BorderRadius.circular(14)),
                child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.call_rounded, color: Colors.white, size: 20),
                  SizedBox(width: 6),
                  Text('Answer', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                ]),
              ),
            ),
          ),
        ]),
      ]),
    ),
  );
}
