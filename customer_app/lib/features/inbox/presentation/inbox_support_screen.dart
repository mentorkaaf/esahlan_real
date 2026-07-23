import 'dart:async';
import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:audio_waveforms/audio_waveforms.dart';
import 'package:audioplayers/audioplayers.dart';
import 'package:flutter_webrtc/flutter_webrtc.dart';
import 'package:permission_handler/permission_handler.dart';
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

  // Voice recording preview
  bool    _previewMode     = false;
  String? _recordedPath;
  int     _previewDuration = 0;

  // WebRTC call
  RTCPeerConnection? _pc;
  MediaStream?       _localStream;
  bool   _inCall              = false;
  bool   _callMuted           = false;
  bool   _callInitiatedByMe   = false;
  String? _activeCallUuid;

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
    _cleanupCall();
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

  Future<void> _onRealtimeMessage(dynamic raw) async {
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
    } else if (event == 'status_update') {
      final newStatus = data['status'] as String?;
      if (newStatus != null && mounted) {
        final c = _conv ?? widget.conversation;
        setState(() {
          _conv = InboxConversation(
            uuid: c.uuid, module: c.module, subject: c.subject,
            status: newStatus, priority: c.priority,
            lastMessage: c.lastMessage, lastMessageAt: c.lastMessageAt,
            unread: c.unread, agent: c.agent, createdAt: c.createdAt,
          );
        });
      }
    } else if (event == 'call_initiated') {
      _onIncomingCall(data);
    } else if (event == 'call_answered') {
      final answerSdp = data['answer_sdp'] as String?;
      if (answerSdp != null && _pc != null) {
        try {
          await _pc!.setRemoteDescription(RTCSessionDescription(answerSdp, 'answer'));
          if (mounted) _toast('Connected');
        } catch (_) {}
      }
    } else if (event == 'ice_candidate') {
      final cand = data['candidate'];
      if (cand is Map && _pc != null) {
        try {
          await _pc!.addCandidate(RTCIceCandidate(
            cand['candidate'] as String? ?? '',
            cand['sdpMid'] as String?,
            (cand['sdpMLineIndex'] as num?)?.toInt() ?? 0,
          ));
        } catch (_) {}
      }
    } else if (event == 'call_ended') {
      if (_inCall) {
        await _cleanupCall();
        if (mounted) _toast('Call ended');
      }
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

  // Adds msg only if not already present (Reverb echo may have arrived first)
  void _addMessage(InboxMessage msg) {
    if (!_messages.any((m) => m.uuid == msg.uuid)) {
      _messages.add(msg);
    }
  }

  Future<void> _sendText() async {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty || _sending) return;
    _msgCtrl.clear();
    setState(() => _sending = true);
    try {
      final msg = await widget.repo.sendTextMessage(widget.conversation.uuid, text);
      if (mounted) setState(() => _addMessage(msg));
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
      if (mounted) setState(() => _addMessage(msg));
      _scrollToBottom();
    } catch (_) {} finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _toggleRecording() async {
    if (_recording) {
      // Capture duration BEFORE stopping (elapsedDuration resets after stop)
      final durationSec = _recorder.elapsedDuration.inSeconds;
      final path = await _recorder.stop();
      if (!mounted) return;
      if (path == null) {
        setState(() => _recording = false);
        return;
      }
      // Enter preview mode — user can listen before sending
      setState(() {
        _recording        = false;
        _previewMode      = true;
        _recordedPath     = path;
        _previewDuration  = durationSec;
      });
    } else {
      await _recorder.record();
      setState(() => _recording = true);
    }
  }

  Future<void> _sendVoice() async {
    final path = _recordedPath;
    final dur  = _previewDuration;
    if (path == null) return;
    setState(() { _previewMode = false; _recordedPath = null; _sending = true; });
    try {
      final msg = await widget.repo.sendMediaMessage(
          widget.conversation.uuid, File(path), 'audio', duration: dur);
      if (mounted) setState(() => _addMessage(msg));
      _scrollToBottom();
    } catch (_) {} finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _cancelVoice() {
    final path = _recordedPath;
    setState(() { _previewMode = false; _recordedPath = null; _previewDuration = 0; });
    if (path != null) {
      try { File(path).deleteSync(); } catch (_) {}
    }
  }

  // ── Calls (WebRTC) ───────────────────────────────────────────────────────────

  /// Waits for ICE gathering to complete (all candidates embedded in SDP).
  /// Max 8 seconds — after that we proceed with whatever candidates we have.
  Future<void> _waitForIceComplete(RTCPeerConnection pc) async {
    final done = Completer<void>();
    pc.onIceGatheringState = (state) {
      if (state == RTCIceGatheringState.RTCIceGatheringStateComplete &&
          !done.isCompleted) {
        done.complete();
      }
    };
    await done.future.timeout(const Duration(seconds: 8), onTimeout: () {});
  }

  Future<void> _startCall() async {
    if (_inCall) { await _cleanupCall(); return; }
    _callInitiatedByMe = true;
    try {
      // Request and verify microphone permission
      final micStatus = await Permission.microphone.request();
      if (!micStatus.isGranted) {
        _callInitiatedByMe = false;
        _toast('Microphone permission required');
        return;
      }

      _pc = await createPeerConnection(<String, dynamic>{
        'iceServers': [
          {'urls': 'stun:stun.l.google.com:19302'},
          {'urls': 'stun:stun1.l.google.com:19302'},
        ],
        'sdpSemantics': 'unified-plan',
      });

      _localStream = await navigator.mediaDevices
          .getUserMedia(<String, dynamic>{'audio': true, 'video': false});
      for (final track in _localStream!.getTracks()) {
        await _pc!.addTrack(track, _localStream!);
      }

      // Create offer and start ICE gathering
      final offer = await _pc!.createOffer(<String, dynamic>{'offerToReceiveAudio': 1});
      await _pc!.setLocalDescription(offer);

      // Wait for ICE gathering to complete — candidates are embedded in final SDP
      // This avoids trickle ICE timing bugs (no separate candidate exchange needed)
      await _waitForIceComplete(_pc!);

      final localDesc = await _pc!.getLocalDescription();
      final fullOfferSdp = localDesc?.sdp ?? offer.sdp ?? '';
      if (fullOfferSdp.isEmpty) {
        _toast('Could not prepare call');
        await _cleanupCall();
        return;
      }

      // Send offer with ALL ICE candidates already embedded
      final result = await widget.repo.initiateCall(
        widget.conversation.uuid,
        offerSdp: fullOfferSdp,
      );

      _activeCallUuid = result['call_uuid'] as String?;
      if (_activeCallUuid == null) {
        _toast('Call failed');
        await _cleanupCall();
        return;
      }

      if (mounted) setState(() => _inCall = true);
      _toast('Calling… waiting for agent');

    } catch (e) {
      debugPrint('[Call] _startCall error: $e');
      _toast('Could not start call');
      await _cleanupCall();
    }
  }

  Future<void> _cleanupCall() async {
    _callInitiatedByMe = false;
    if (_activeCallUuid != null) {
      try { await widget.repo.endCall(_activeCallUuid!); } catch (_) {}
    }
    _localStream?.getTracks().forEach((t) => t.stop());
    await _localStream?.dispose();
    await _pc?.close();
    _localStream = null;
    _pc = null;
    _activeCallUuid = null;
    if (mounted) setState(() { _inCall = false; _callMuted = false; });
  }

  Future<void> _endCall() => _cleanupCall();

  void _toggleMute() {
    _callMuted = !_callMuted;
    _localStream?.getAudioTracks().forEach((t) => t.enabled = !_callMuted);
    if (mounted) setState(() {});
  }

  void _onIncomingCall(Map data) {
    // In this app, the user is always the caller — ignore incoming call events
    if (_callInitiatedByMe) return;
  }

  Future<void> _reopenTicket() async {
    try {
      await widget.repo.reopenConversation(widget.conversation.uuid);
      if (mounted) setState(() {
        _conv = InboxConversation(
          uuid: (_conv ?? widget.conversation).uuid,
          module: (_conv ?? widget.conversation).module,
          subject: (_conv ?? widget.conversation).subject,
          status: 'open',
          priority: (_conv ?? widget.conversation).priority,
          lastMessage: (_conv ?? widget.conversation).lastMessage,
          lastMessageAt: (_conv ?? widget.conversation).lastMessageAt,
          unread: (_conv ?? widget.conversation).unread,
          agent: (_conv ?? widget.conversation).agent,
          createdAt: (_conv ?? widget.conversation).createdAt,
        );
      });
      _toast('Ticket reopened');
    } catch (_) {
      _toast('Could not reopen ticket');
    }
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

        // ── Reopen button (when resolved) ──
        if (conv.isResolved)
          _ReopenBar(onReopen: _reopenTicket),

        // ── Voice preview bar (after recording) ──
        if (!conv.isResolved && _previewMode && _recordedPath != null)
          _VoicePreviewBar(
            path: _recordedPath!,
            durationSec: _previewDuration,
            isDark: isDark,
            onCancel: _cancelVoice,
            onSend: _sendVoice,
            sending: _sending,
          ),

        // ── Input ──
        if (!conv.isResolved && !_previewMode)
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

// ── Reopen bar ────────────────────────────────────────────────────────────────

class _ReopenBar extends StatelessWidget {
  const _ReopenBar({required this.onReopen});
  final VoidCallback onReopen;

  @override
  Widget build(BuildContext context) => Container(
    padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.of(context).padding.bottom + 12),
    decoration: BoxDecoration(
      color: Theme.of(context).brightness == Brightness.dark
          ? const Color(0xFF181830) : Colors.white,
      boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 12, offset: const Offset(0, -2))],
    ),
    child: SizedBox(
      width: double.infinity,
      child: GestureDetector(
        onTap: onReopen,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 13),
          decoration: BoxDecoration(
            gradient: const LinearGradient(colors: [Color(0xFF6C63FF), Color(0xFF3a36d4)]),
            borderRadius: BorderRadius.circular(14),
          ),
          child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(Icons.refresh_rounded, color: Colors.white, size: 18),
            SizedBox(width: 8),
            Text('Reopen Ticket', style: TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w700)),
          ]),
        ),
      ),
    ),
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

// ── Voice preview bar ─────────────────────────────────────────────────────────

class _VoicePreviewBar extends StatefulWidget {
  const _VoicePreviewBar({
    required this.path, required this.durationSec, required this.isDark,
    required this.onCancel, required this.onSend, required this.sending,
  });
  final String path;
  final int durationSec;
  final bool isDark, sending;
  final VoidCallback onCancel, onSend;

  @override
  State<_VoicePreviewBar> createState() => _VoicePreviewBarState();
}

class _VoicePreviewBarState extends State<_VoicePreviewBar> {
  final _player = AudioPlayer();
  bool     _playing     = false;
  Duration _pos         = Duration.zero;
  Duration _total       = Duration.zero;
  bool     _initialized = false;

  @override
  void initState() {
    super.initState();
    _total = Duration(seconds: widget.durationSec);
    _player.onPositionChanged.listen((p) { if (mounted) setState(() => _pos = p); });
    _player.onDurationChanged.listen((d) { if (mounted) setState(() => _total = d); });
    _player.onPlayerComplete.listen((_) async {
      await _player.seek(Duration.zero);
      if (mounted) setState(() { _playing = false; _pos = Duration.zero; });
    });
  }

  @override
  void dispose() { _player.dispose(); super.dispose(); }

  Future<void> _toggle() async {
    if (_playing) {
      await _player.pause();
      if (mounted) setState(() => _playing = false);
    } else {
      if (!_initialized) {
        await _player.setSource(DeviceFileSource(widget.path));
        _initialized = true;
      }
      await _player.resume();
      if (mounted) setState(() => _playing = true);
    }
  }

  String _fmt(Duration d) {
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    final progress = _total.inMilliseconds > 0
        ? (_pos.inMilliseconds / _total.inMilliseconds).clamp(0.0, 1.0)
        : 0.0;

    return Container(
      decoration: BoxDecoration(
        color: widget.isDark ? const Color(0xFF181830) : Colors.white,
        boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 12, offset: const Offset(0, -2))],
      ),
      padding: EdgeInsets.only(
        left: 10, right: 10, top: 10,
        bottom: MediaQuery.of(context).padding.bottom + 10,
      ),
      child: Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
        // Cancel
        GestureDetector(
          onTap: widget.onCancel,
          child: Container(
            width: 42, height: 42,
            decoration: BoxDecoration(color: Colors.red.withAlpha(15), borderRadius: BorderRadius.circular(12)),
            child: const Icon(Icons.delete_outline_rounded, color: Colors.red, size: 20),
          ),
        ),
        const SizedBox(width: 8),

        // Play/pause button
        GestureDetector(
          onTap: _toggle,
          child: Container(
            width: 42, height: 42,
            decoration: BoxDecoration(color: _kAccent.withAlpha(20), borderRadius: BorderRadius.circular(12)),
            child: Icon(_playing ? Icons.pause_rounded : Icons.play_arrow_rounded, color: _kAccent, size: 22),
          ),
        ),
        const SizedBox(width: 10),

        // Progress + duration
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(3),
              child: LinearProgressIndicator(
                value: progress,
                backgroundColor: _kAccent.withAlpha(25),
                valueColor: const AlwaysStoppedAnimation<Color>(_kAccent),
                minHeight: 3,
              ),
            ),
            const SizedBox(height: 5),
            Text(
              _fmt(_total.inSeconds > 0 ? _total : Duration(seconds: widget.durationSec)),
              style: TextStyle(color: _kAccent.withAlpha(180), fontSize: 10, fontWeight: FontWeight.w600),
            ),
          ]),
        ),
        const SizedBox(width: 8),

        // Send button
        GestureDetector(
          onTap: widget.sending ? null : widget.onSend,
          child: Container(
            width: 46, height: 46,
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [_kAccent, Color(0xFF3a36d4)]),
              borderRadius: BorderRadius.circular(14),
              boxShadow: [BoxShadow(color: _kAccent.withAlpha(60), blurRadius: 8, offset: const Offset(0, 3))],
            ),
            child: Center(
              child: widget.sending
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Icon(Icons.send_rounded, color: Colors.white, size: 20),
            ),
          ),
        ),
      ]),
    );
  }
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
