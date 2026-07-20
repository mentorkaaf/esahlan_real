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
import '../widgets/stream_quality_indicator.dart';
import '../widgets/live_leaderboard_sheet.dart';

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
  int _totalLikes = 0;
  bool _hasLiked = false;
  bool _isLiking = false;
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

      // Like count updates
      await RealtimeClient.instance.listen(
        _reverbChannel,
        'live.liked',
        (data) {
          if (mounted) setState(() => _totalLikes = (data as Map?)?['total_likes'] as int? ?? _totalLikes);
        },
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

  Future<void> _like() async {
    if (_hasLiked || _isLiking) return;
    setState(() => _isLiking = true);
    try {
      final total = await _repo.likeRoom(widget.room.id);
      if (mounted) setState(() { _totalLikes = total; _hasLiked = true; });
    } catch (_) {}
    if (mounted) setState(() => _isLiking = false);
  }

  void _showReport() {
    String? _reason;
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1A1A2E),
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setS) => Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Report Stream',
                  style: TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.bold)),
              const SizedBox(height: 4),
              const Text('Why are you reporting this stream?',
                  style: TextStyle(color: Colors.white54, fontSize: 12)),
              const SizedBox(height: 16),
              ...[
                ('spam',        '🚫', 'Spam or misleading'),
                ('nudity',      '🔞', 'Nudity or sexual content'),
                ('hate_speech', '💬', 'Hate speech or harassment'),
                ('violence',    '⚠️', 'Violence or harmful content'),
                ('other',       '🔍', 'Other'),
              ].map((r) => RadioListTile<String>(
                dense: true,
                contentPadding: EdgeInsets.zero,
                value: r.$1,
                groupValue: _reason,
                onChanged: (v) => setS(() => _reason = v),
                activeColor: Colors.orange,
                title: Row(children: [
                  Text(r.$2, style: const TextStyle(fontSize: 16)),
                  const SizedBox(width: 8),
                  Text(r.$3, style: const TextStyle(color: Colors.white, fontSize: 13)),
                ]),
              )),
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: _reason == null ? null : () async {
                    Navigator.pop(ctx);
                    try {
                      await _repo.reportRoom(widget.room.id, _reason!);
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Report submitted. Thank you.')));
                      }
                    } catch (_) {}
                  },
                  style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.red,
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12))),
                  child: const Text('Submit Report', style: TextStyle(fontWeight: FontWeight.bold)),
                ),
              ),
              SizedBox(height: MediaQuery.of(ctx).padding.bottom),
            ],
          ),
        ),
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
                    // Stream quality
                    if (!_loading)
                      StreamQualityIndicator(room: _loading ? null : _room),
                    const SizedBox(width: 8),
                    // Viewer count
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                        color: Colors.black45,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.remove_red_eye, color: Colors.white70, size: 13),
                          const SizedBox(width: 3),
                          Text('$_viewerCount',
                              style: const TextStyle(color: Colors.white, fontSize: 12)),
                        ],
                      ),
                    ),
                    const SizedBox(width: 6),
                    // Leaderboard
                    GestureDetector(
                      onTap: () => showModalBottomSheet(
                        context: context,
                        backgroundColor: Colors.transparent,
                        isScrollControlled: true,
                        builder: (_) => LiveLeaderboardSheet(roomId: widget.room.id),
                      ),
                      child: Container(
                        width: 30, height: 30,
                        decoration: BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
                        child: const Center(child: Text('🏆', style: TextStyle(fontSize: 14))),
                      ),
                    ),
                    const SizedBox(width: 6),
                    // Report
                    GestureDetector(
                      onTap: _showReport,
                      child: Container(
                        width: 30, height: 30,
                        decoration: BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
                        child: const Icon(Icons.flag_outlined, color: Colors.white60, size: 15),
                      ),
                    ),
                    const SizedBox(width: 6),
                    // Close
                    GestureDetector(
                      onTap: _leave,
                      child: Container(
                        width: 30, height: 30,
                        decoration: BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
                        child: const Icon(Icons.close, color: Colors.white, size: 16),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // Gift animations
          ...(_giftEvents.map((e) => GiftAnimationOverlay(event: e))),

          // Like button — right side, TikTok-style
          Positioned(
            right: 12,
            bottom: 150,
            child: Column(
              children: [
                GestureDetector(
                  onTap: _like,
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    width: 48, height: 48,
                    decoration: BoxDecoration(
                      color: _hasLiked
                          ? Colors.red.withValues(alpha: 0.3)
                          : Colors.black54,
                      shape: BoxShape.circle,
                      border: Border.all(
                          color: _hasLiked ? Colors.red : Colors.white24),
                    ),
                    child: Icon(
                      _hasLiked ? Icons.favorite : Icons.favorite_border,
                      color: _hasLiked ? Colors.red : Colors.white,
                      size: 22,
                    ),
                  ),
                ),
                if (_totalLikes > 0) ...[
                  const SizedBox(height: 4),
                  Text(
                    _totalLikes >= 1000
                        ? '${(_totalLikes / 1000).toStringAsFixed(1)}K'
                        : '$_totalLikes',
                    style: const TextStyle(color: Colors.white, fontSize: 11),
                  ),
                ],
              ],
            ),
          ),

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
