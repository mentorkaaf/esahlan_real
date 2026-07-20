import 'dart:async';
import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import '../../data/models/call_models.dart';
import '../../data/repositories/call_repository.dart';
import '../../../../core/utils/media_url.dart';
import 'active_call_screen.dart';

class IncomingCallScreen extends StatefulWidget {
  final Map<String, dynamic> payload; // from FCM data

  const IncomingCallScreen({super.key, required this.payload});

  @override
  State<IncomingCallScreen> createState() => _IncomingCallScreenState();
}

class _IncomingCallScreenState extends State<IncomingCallScreen>
    with SingleTickerProviderStateMixin {
  late AnimationController _pulse;
  Timer? _missedTimer;
  bool _processing = false;
  final _repo = CallRepository();

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 1),
    )..repeat(reverse: true);

    // Auto-miss after 30 seconds
    _missedTimer = Timer(const Duration(seconds: 30), () {
      if (mounted) Navigator.of(context).pop();
    });
  }

  @override
  void dispose() {
    _pulse.dispose();
    _missedTimer?.cancel();
    super.dispose();
  }

  Future<void> _accept() async {
    if (_processing) return;
    setState(() => _processing = true);
    try {
      final isVideo = (widget.payload['call_type'] ?? '') == 'video';
      if (isVideo) {
        await [Permission.camera, Permission.microphone].request();
      } else {
        await Permission.microphone.request();
      }
      final callId = int.parse(widget.payload['call_id'].toString());
      final session = await _repo.acceptCall(callId);
      if (mounted) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => ActiveCallScreen(session: session)),
        );
      }
    } catch (_) {
      if (mounted) Navigator.of(context).pop();
    }
  }

  Future<void> _reject() async {
    if (_processing) return;
    setState(() => _processing = true);
    try {
      final callId = int.parse(widget.payload['call_id'].toString());
      await _repo.rejectCall(callId);
    } catch (_) {}
    if (mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final callerName   = widget.payload['caller_name'] ?? 'Unknown';
    final callerAvatar = widget.payload['caller_avatar'] ?? '';
    final callType     = widget.payload['call_type'] ?? 'audio';
    final isVideo      = callType == 'video';

    return Scaffold(
      backgroundColor: Colors.black,
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [Color(0xFF1A0A2E), Color(0xFF0D0D0D)],
          ),
        ),
        child: SafeArea(
          child: Column(
            children: [
              const Spacer(),

              // Incoming call label
              Text(
                'Incoming ${isVideo ? 'Video' : 'Audio'} Call',
                style: const TextStyle(color: Colors.white54, fontSize: 16),
              ),
              const SizedBox(height: 32),

              // Pulsing avatar
              AnimatedBuilder(
                animation: _pulse,
                builder: (_, child) => Container(
                  width: 140 + _pulse.value * 20,
                  height: 140 + _pulse.value * 20,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: Colors.orange.withOpacity(0.5 - _pulse.value * 0.4),
                      width: 4,
                    ),
                  ),
                  child: child,
                ),
                child: CircleAvatar(
                  radius: 60,
                  backgroundColor: Colors.orange,
                  backgroundImage: callerAvatar.isNotEmpty
                      ? NetworkImage(fixMediaUrl(callerAvatar))
                      : null,
                  child: callerAvatar.isEmpty
                      ? Text(
                          callerName.isNotEmpty ? callerName[0].toUpperCase() : '?',
                          style: const TextStyle(
                              fontSize: 44,
                              color: Colors.white,
                              fontWeight: FontWeight.bold),
                        )
                      : null,
                ),
              ),

              const SizedBox(height: 24),
              Text(
                callerName,
                style: const TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                isVideo ? '📹 Video call' : '📞 Audio call',
                style: const TextStyle(color: Colors.white54, fontSize: 16),
              ),

              const Spacer(),

              // Accept / Reject buttons
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 60, vertical: 40),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    // Reject
                    _CallButton(
                      icon: Icons.call_end,
                      color: Colors.red,
                      label: 'Decline',
                      onTap: _reject,
                    ),
                    // Accept
                    _CallButton(
                      icon: isVideo ? Icons.videocam : Icons.call,
                      color: Colors.green,
                      label: 'Accept',
                      onTap: _accept,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CallButton extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String label;
  final VoidCallback onTap;

  const _CallButton({
    required this.icon,
    required this.color,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        children: [
          Container(
            width: 70,
            height: 70,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            child: Icon(icon, color: Colors.white, size: 32),
          ),
          const SizedBox(height: 8),
          Text(label, style: const TextStyle(color: Colors.white70, fontSize: 14)),
        ],
      ),
    );
  }
}
