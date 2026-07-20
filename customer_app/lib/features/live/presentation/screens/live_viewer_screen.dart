import 'dart:async';
import 'package:flutter/material.dart';
import 'package:livekit_client/livekit_client.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';
import '../widgets/gift_animation_overlay.dart';
import '../widgets/gift_sheet.dart';
import '../widgets/live_chat_overlay.dart';
import '../widgets/live_guest_widget.dart';
import '../widgets/coin_purchase_sheet.dart';
import '../../../../core/services/realtime_client.dart';

class LiveViewerScreen extends StatefulWidget {
  final LiveRoom room;

  const LiveViewerScreen({super.key, required this.room});

  @override
  State<LiveViewerScreen> createState() => _LiveViewerScreenState();
}

class _LiveViewerScreenState extends State<LiveViewerScreen> {
  late Room _room;
  EventsListener<RoomEvent>? _listener;
  LiveSession? _session;
  int _viewerCount = 0;
  int _coinBalance = 0;
  final _giftEvents = <GiftEvent>[];
  final _repo = LiveRepository();
  bool _loading = true;
  bool _ended = false;

  String get _reverbChannel => 'live.${widget.room.id}';

  @override
  void initState() {
    super.initState();
    _viewerCount = widget.room.viewerCount;
    _join();
  }

  Future<void> _join() async {
    try {
      final session = await _repo.joinRoom(widget.room.id);
      _coinBalance = await _repo.getCoinBalance();
      setState(() {
        _session = session;
        _loading = false;
      });

      _room = Room();
      _listener = _room.createListener()
        ..on<TrackSubscribedEvent>((_) => setState(() {}))
        ..on<TrackUnsubscribedEvent>((_) => setState(() {}))
        ..on<RoomDisconnectedEvent>((_) {
          if (mounted) setState(() => _ended = true);
        });

      await _room.connect(
        session.livekitUrl,
        session.token,
        roomOptions: const RoomOptions(adaptiveStream: true, dynacast: true),
      );

      // Guest accepted — receive LiveKit token
      await RealtimeClient.instance.listen(
        'private-user.${_session!.room.host.id}',
        'live.guest_accepted',
        (_) {}, // handled by host screen; viewer receives on private channel below
      );

      // Subscribe to gift events from all viewers
      await RealtimeClient.instance.listen(
        _reverbChannel,
        'gift.received',
        _handleGiftEvent,
      );

      // Viewer count updates
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

      // Host ended the stream
      await RealtimeClient.instance.listen(
        _reverbChannel,
        'live.ended',
        (_) { if (mounted) setState(() => _ended = true); },
      );

      setState(() {});
    } catch (e) {
      if (mounted) Navigator.of(context).pop();
    }
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
      setState(() => _giftEvents.add(event));
      Future.delayed(const Duration(seconds: 4), () {
        if (mounted) setState(() => _giftEvents.remove(event));
      });
    } catch (_) {}
  }

  Future<void> _leave() async {
    await _repo.leaveRoom(widget.room.id);
    await _room.disconnect();
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _showCoinPurchase() async {
    await showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (_) => CoinPurchaseSheet(
        currentBalance: _coinBalance,
        onPurchased: (newBalance) {
          if (mounted) setState(() => _coinBalance = newBalance);
        },
      ),
    );
  }

  Future<void> _showGifts() async {
    final gifts = await _repo.getGifts();
    if (!mounted) return;
    final result = await showModalBottomSheet<Map<String, dynamic>>(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (_) => GiftSheet(
        gifts: gifts,
        coinBalance: _coinBalance,
        roomId: widget.room.id,
        repo: _repo,
      ),
    );

    if (result != null) {
      // Show gift animation
      final gift = gifts.firstWhere((g) => g.id == result['gift_id'],
          orElse: () => gifts.first);
      final event = GiftEvent(
        gift: gift,
        quantity: result['quantity'] ?? 1,
        senderName: 'You',
        senderAvatar: '',
      );
      setState(() {
        _giftEvents.add(event);
        _coinBalance = result['new_balance'] ?? _coinBalance;
      });
      Future.delayed(const Duration(seconds: 4), () {
        if (mounted) setState(() => _giftEvents.remove(event));
      });
    }
  }

  @override
  void dispose() {
    _listener?.dispose();
    if (_session != null) _room.dispose();
    RealtimeClient.instance.unsubscribe(_reverbChannel);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_ended) {
      return Scaffold(
        backgroundColor: Colors.black,
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.live_tv_outlined, color: Colors.white30, size: 64),
              const SizedBox(height: 16),
              const Text('Live stream ended',
                  style: TextStyle(color: Colors.white, fontSize: 18)),
              const SizedBox(height: 24),
              ElevatedButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Go Back'),
              ),
            ],
          ),
        ),
      );
    }

    // Remote video track
    VideoTrack? remoteVideo;
    for (final p in (_loading ? [] : _room.remoteParticipants.values.toList())) {
      for (final pub in p.videoTrackPublications) {
        if (pub.subscribed && pub.track != null) {
          remoteVideo = pub.track as VideoTrack;
          break;
        }
      }
    }

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          // Video
          if (_loading)
            const Center(child: CircularProgressIndicator(color: Colors.orange))
          else if (remoteVideo != null)
            Positioned.fill(child: VideoTrackRenderer(remoteVideo))
          else
            const Positioned.fill(
              child: Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircularProgressIndicator(color: Colors.orange),
                    SizedBox(height: 12),
                    Text('Waiting for host...', style: TextStyle(color: Colors.white54)),
                  ],
                ),
              ),
            ),

          // Top bar
          Positioned(
            top: 0, left: 0, right: 0,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: Row(
                  children: [
                    // Host info
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 18,
                          backgroundColor: Colors.orange,
                          child: Text(
                            widget.room.host.name.isNotEmpty
                                ? widget.room.host.name[0].toUpperCase()
                                : '?',
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(widget.room.host.name,
                                style: const TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.bold,
                                    fontSize: 13)),
                            const Row(
                              children: [
                                Icon(Icons.circle, color: Colors.red, size: 8),
                                SizedBox(width: 4),
                                Text('LIVE', style: TextStyle(color: Colors.red, fontSize: 11)),
                              ],
                            ),
                          ],
                        ),
                      ],
                    ),
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
                    // Close
                    GestureDetector(
                      onTap: _leave,
                      child: Container(
                        width: 32, height: 32,
                        decoration: BoxDecoration(
                          color: Colors.black45,
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: const Icon(Icons.close, color: Colors.white, size: 18),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // Gift animations
          ...(_giftEvents.map((e) => GiftAnimationOverlay(event: e))),

          // Chat overlay (left side, above bottom bar)
          if (!_loading)
            Positioned(
              bottom: 80,
              left: 0,
              right: 60,
              child: LiveChatOverlay(
                roomId: widget.room.id,
                reverbChannel: _reverbChannel,
              ),
            ),

          // Bottom bar
          Positioned(
            bottom: 24, left: 12, right: 12,
            child: Row(
              children: [
                // Coin balance — tap to buy
                GestureDetector(
                  onTap: _showCoinPurchase,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color: Colors.black54,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: Colors.orange.withValues(alpha: 0.4), width: 1),
                    ),
                    child: Row(
                      children: [
                        const Text('🪙', style: TextStyle(fontSize: 14)),
                        const SizedBox(width: 4),
                        Text('$_coinBalance',
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                        const SizedBox(width: 4),
                        const Icon(Icons.add_circle_outline, color: Colors.orange, size: 14),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                // Join stage
                if (!_loading)
                  JoinRequestButton(roomId: widget.room.id),
                const Spacer(),
                // Gift button
                GestureDetector(
                  onTap: _showGifts,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                          colors: [Colors.orange, Colors.deepOrange]),
                      borderRadius: BorderRadius.circular(24),
                    ),
                    child: const Row(
                      children: [
                        Text('🎁', style: TextStyle(fontSize: 16)),
                        SizedBox(width: 6),
                        Text('Gift',
                            style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                                fontSize: 14)),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
