import 'dart:async';
import 'package:flutter/material.dart';
import 'package:livekit_client/livekit_client.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';
import '../widgets/gift_animation_overlay.dart';
import 'package:permission_handler/permission_handler.dart';
import '../../../../core/services/realtime_client.dart';

class LiveHostScreen extends StatefulWidget {
  final LiveSession session;

  const LiveHostScreen({super.key, required this.session});

  @override
  State<LiveHostScreen> createState() => _LiveHostScreenState();
}

class _LiveHostScreenState extends State<LiveHostScreen> {
  late Room _room;
  EventsListener<RoomEvent>? _listener;
  bool _micOn = true;
  bool _cameraOn = true;
  bool _frontCamera = true;
  int _viewerCount = 0;
  int _duration = 0;
  Timer? _timer;
  final _giftEvents = <GiftEvent>[];
  final _repo = LiveRepository();

  @override
  void initState() {
    super.initState();
    _connect();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      setState(() => _duration++);
    });
  }

  String get _reverbChannel => 'presence-live.${widget.session.room.id}';

  Future<void> _connect() async {
    await [Permission.camera, Permission.microphone].request();
    _room = Room();
    _listener = _room.createListener()
      ..on<LocalTrackPublishedEvent>((_) => setState(() {}))
      ..on<LocalTrackUnpublishedEvent>((_) => setState(() {}))
      ..on<ParticipantConnectedEvent>((_) => setState(() => _viewerCount++))
      ..on<ParticipantDisconnectedEvent>((_) => setState(() {
            if (_viewerCount > 0) _viewerCount--;
          }));

    await _room.connect(
      widget.session.livekitUrl,
      widget.session.token,
      roomOptions: const RoomOptions(adaptiveStream: true, dynacast: true),
    );

    await _room.localParticipant?.setCameraEnabled(true);
    await _room.localParticipant?.setMicrophoneEnabled(true);

    // Subscribe to gift events via Reverb
    await RealtimeClient.instance.listen(
      _reverbChannel,
      'gift.received',
      _handleGiftEvent,
    );

    // Viewer join/leave counts from Reverb (supplements LiveKit events)
    await RealtimeClient.instance.listen(
      _reverbChannel,
      'viewer.joined',
      (_) { if (mounted) setState(() => _viewerCount++); },
    );
    await RealtimeClient.instance.listen(
      _reverbChannel,
      'viewer.left',
      (_) { if (mounted) setState(() { if (_viewerCount > 0) _viewerCount--; }); },
    );

    setState(() {});
  }

  void _handleGiftEvent(dynamic data) {
    if (!mounted) return;
    try {
      final map = Map<String, dynamic>.from(data as Map);
      final giftData = Map<String, dynamic>.from(map['gift'] as Map);
      final gift = GiftModel.fromJson(giftData);
      final event = GiftEvent(
        gift: gift,
        quantity: map['quantity'] as int? ?? 1,
        senderName: map['sender']?['name'] as String? ?? '',
        senderAvatar: map['sender']?['avatar'] as String? ?? '',
      );
      _onGiftReceived(event);
    } catch (_) {}
  }

  Future<void> _endLive() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        backgroundColor: const Color(0xFF1A1A2E),
        title: const Text('End Live?', style: TextStyle(color: Colors.white)),
        content: const Text('Your live stream will end for all viewers.',
            style: TextStyle(color: Colors.white60)),
        actions: [
          TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Cancel')),
          TextButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('End', style: TextStyle(color: Colors.red))),
        ],
      ),
    );
    if (confirmed != true) return;
    try { await _repo.endRoom(widget.session.room.id); } catch (_) {}
    try { await _room.disconnect(); } catch (_) {}
    if (mounted) Navigator.of(context).pop();
  }

  void _onGiftReceived(GiftEvent gift) {
    setState(() => _giftEvents.add(gift));
    Future.delayed(const Duration(seconds: 4), () {
      if (mounted) setState(() => _giftEvents.remove(gift));
    });
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
    RealtimeClient.instance.unsubscribe(_reverbChannel);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    // Local video track
    VideoTrack? localVideo;
    for (final pub in _room.localParticipant?.videoTrackPublications ?? []) {
      if (pub.track != null) localVideo = pub.track as VideoTrack;
    }

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          // Camera preview
          if (localVideo != null)
            Positioned.fill(child: VideoTrackRenderer(localVideo))
          else
            const Positioned.fill(
              child: Center(child: CircularProgressIndicator(color: Colors.orange)),
            ),

          // Top bar
          Positioned(
            top: 0,
            left: 0,
            right: 0,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: Row(
                  children: [
                    // LIVE badge
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.red,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Container(
                            width: 6, height: 6,
                            decoration: const BoxDecoration(
                                color: Colors.white, shape: BoxShape.circle),
                          ),
                          const SizedBox(width: 4),
                          const Text('LIVE',
                              style: TextStyle(
                                  color: Colors.white,
                                  fontSize: 12,
                                  fontWeight: FontWeight.bold)),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    Text(_durationStr,
                        style: const TextStyle(color: Colors.white, fontSize: 13)),
                    const Spacer(),
                    // Viewer count
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.black45,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.remove_red_eye, color: Colors.white70, size: 14),
                          const SizedBox(width: 4),
                          Text('$_viewerCount',
                              style: const TextStyle(color: Colors.white, fontSize: 13)),
                        ],
                      ),
                    ),
                    const SizedBox(width: 8),
                    // End button
                    GestureDetector(
                      onTap: _endLive,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: Colors.red,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Text('End',
                            style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 13)),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // Gift animations
          ...(_giftEvents.map((e) => GiftAnimationOverlay(event: e))),

          // Bottom controls
          Positioned(
            bottom: 40,
            left: 0,
            right: 0,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                _LiveControl(
                  icon: _micOn ? Icons.mic : Icons.mic_off,
                  active: _micOn,
                  onTap: () async {
                    _micOn = !_micOn;
                    await _room.localParticipant?.setMicrophoneEnabled(_micOn);
                    setState(() {});
                  },
                ),
                const SizedBox(width: 24),
                _LiveControl(
                  icon: _cameraOn ? Icons.videocam : Icons.videocam_off,
                  active: _cameraOn,
                  onTap: () async {
                    _cameraOn = !_cameraOn;
                    await _room.localParticipant?.setCameraEnabled(_cameraOn);
                    setState(() {});
                  },
                ),
                const SizedBox(width: 24),
                _LiveControl(
                  icon: Icons.flip_camera_ios,
                  active: true,
                  onTap: () async {
                    _frontCamera = !_frontCamera;
                    final pos = _frontCamera ? CameraPosition.front : CameraPosition.back;
                    await _room.localParticipant?.setCameraEnabled(false);
                    await _room.localParticipant?.setCameraEnabled(true,
                        cameraCaptureOptions: CameraCaptureOptions(cameraPosition: pos));
                    setState(() {});
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _LiveControl extends StatelessWidget {
  final IconData icon;
  final bool active;
  final VoidCallback onTap;

  const _LiveControl({required this.icon, required this.active, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 52,
        height: 52,
        decoration: BoxDecoration(
          color: active ? Colors.white24 : Colors.red.withOpacity(0.8),
          shape: BoxShape.circle,
        ),
        child: Icon(icon, color: Colors.white, size: 24),
      ),
    );
  }
}
