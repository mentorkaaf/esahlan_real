import 'dart:async';
import 'package:flutter/material.dart';
import 'package:livekit_client/livekit_client.dart';
import '../../data/models/call_models.dart';
import '../../data/repositories/call_repository.dart';
import '../../../../core/utils/media_url.dart';

class ActiveCallScreen extends StatefulWidget {
  final CallSession session;

  const ActiveCallScreen({super.key, required this.session});

  @override
  State<ActiveCallScreen> createState() => _ActiveCallScreenState();
}

class _ActiveCallScreenState extends State<ActiveCallScreen> {
  late Room _room;
  EventsListener<RoomEvent>? _listener;
  bool _connected = false;
  bool _micOn = true;
  bool _cameraOn = true;
  bool _speakerOn = true;
  bool _frontCamera = true;
  int _duration = 0;
  Timer? _timer;

  final _repo = CallRepository();

  @override
  void initState() {
    super.initState();
    _connect();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (_connected) setState(() => _duration++);
    });
  }

  Future<void> _connect() async {
    _room = Room();
    _listener = _room.createListener();

    _listener!
      ..on<ParticipantConnectedEvent>((_) => setState(() {}))
      ..on<ParticipantDisconnectedEvent>((_) => setState(() {}))
      ..on<TrackSubscribedEvent>((_) => setState(() {}))
      ..on<TrackUnsubscribedEvent>((_) => setState(() {}))
      ..on<RoomDisconnectedEvent>((_) => _onDisconnected());

    await _room.connect(
      widget.session.livekitUrl,
      widget.session.token,
      roomOptions: const RoomOptions(
        adaptiveStream: true,
        dynacast: true,
      ),
    );

    if (widget.session.call.type == 'video') {
      await _room.localParticipant?.setCameraEnabled(true);
    }
    await _room.localParticipant?.setMicrophoneEnabled(true);

    setState(() => _connected = true);
  }

  void _onDisconnected() {
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _endCall() async {
    await _repo.endCall(widget.session.call.id);
    await _room.disconnect();
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _toggleMic() async {
    _micOn = !_micOn;
    await _room.localParticipant?.setMicrophoneEnabled(_micOn);
    setState(() {});
  }

  Future<void> _toggleCamera() async {
    _cameraOn = !_cameraOn;
    await _room.localParticipant?.setCameraEnabled(_cameraOn);
    setState(() {});
  }

  Future<void> _flipCamera() async {
    _frontCamera = !_frontCamera;
    await _room.localParticipant?.switchCamera();
    setState(() {});
  }

  String get _durationStr {
    final m = (_duration ~/ 60).toString().padLeft(2, '0');
    final s = (_duration % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  @override
  void dispose() {
    _timer?.cancel();
    _listener?.dispose();
    _room.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final call = widget.session.call;
    final other = call.otherParty;
    final isVideo = call.type == 'video';

    // Find remote video track
    final remoteParticipants = _room.remoteParticipants.values.toList();
    VideoTrack? remoteVideo;
    for (final p in remoteParticipants) {
      for (final pub in p.videoTrackPublications) {
        if (pub.subscribed && pub.track != null) {
          remoteVideo = pub.track as VideoTrack;
          break;
        }
      }
    }

    VideoTrack? localVideo;
    for (final pub in _room.localParticipant?.videoTrackPublications ?? []) {
      if (pub.track != null) localVideo = pub.track as VideoTrack;
    }

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          // Remote video (full screen) or avatar background
          if (isVideo && remoteVideo != null)
            Positioned.fill(
              child: VideoTrackRenderer(remoteVideo),
            )
          else
            Positioned.fill(
              child: Container(
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Color(0xFF1A0A2E), Color(0xFF0D1117)],
                  ),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircleAvatar(
                      radius: 60,
                      backgroundColor: Colors.orange,
                      backgroundImage: other.avatar.isNotEmpty
                          ? NetworkImage(fixMediaUrl(other.avatar))
                          : null,
                      child: other.avatar.isEmpty
                          ? Text(other.name.isNotEmpty ? other.name[0].toUpperCase() : '?',
                              style: const TextStyle(fontSize: 40, color: Colors.white, fontWeight: FontWeight.bold))
                          : null,
                    ),
                    const SizedBox(height: 20),
                    Text(other.name,
                        style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.bold)),
                    const SizedBox(height: 8),
                    Text(
                      _connected && remoteParticipants.isNotEmpty ? _durationStr : 'Connecting...',
                      style: const TextStyle(color: Colors.white70, fontSize: 16),
                    ),
                  ],
                ),
              ),
            ),

          // Local video PiP (top right) for video calls
          if (isVideo && localVideo != null)
            Positioned(
              top: 60,
              right: 16,
              width: 100,
              height: 140,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: VideoTrackRenderer(localVideo, mirror: _frontCamera),
              ),
            ),

          // Duration top center (non-video)
          if (!isVideo)
            Positioned(
              top: 60,
              left: 0,
              right: 0,
              child: Text(
                _connected ? _durationStr : 'Ringing...',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white70, fontSize: 16),
              ),
            ),

          // Control bar bottom
          Positioned(
            bottom: 40,
            left: 0,
            right: 0,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceEvenly,
              children: [
                _ControlButton(
                  icon: _micOn ? Icons.mic : Icons.mic_off,
                  label: _micOn ? 'Mute' : 'Unmute',
                  color: _micOn ? Colors.white24 : Colors.orange,
                  onTap: _toggleMic,
                ),
                if (isVideo)
                  _ControlButton(
                    icon: _cameraOn ? Icons.videocam : Icons.videocam_off,
                    label: _cameraOn ? 'Camera' : 'No cam',
                    color: _cameraOn ? Colors.white24 : Colors.orange,
                    onTap: _toggleCamera,
                  ),
                _ControlButton(
                  icon: Icons.call_end,
                  label: 'End',
                  color: Colors.red,
                  onTap: _endCall,
                  size: 64,
                ),
                if (isVideo)
                  _ControlButton(
                    icon: Icons.flip_camera_ios,
                    label: 'Flip',
                    color: Colors.white24,
                    onTap: _flipCamera,
                  ),
                _ControlButton(
                  icon: _speakerOn ? Icons.volume_up : Icons.volume_off,
                  label: _speakerOn ? 'Speaker' : 'Earpiece',
                  color: Colors.white24,
                  onTap: () => setState(() => _speakerOn = !_speakerOn),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ControlButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  final double size;

  const _ControlButton({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
    this.size = 52,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: size,
            height: size,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            child: Icon(icon, color: Colors.white, size: size * 0.45),
          ),
          const SizedBox(height: 6),
          Text(label, style: const TextStyle(color: Colors.white70, fontSize: 11)),
        ],
      ),
    );
  }
}
