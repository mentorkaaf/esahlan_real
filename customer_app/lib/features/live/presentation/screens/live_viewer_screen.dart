import 'dart:async';
import 'package:flutter/material.dart';
import 'package:livekit_client/livekit_client.dart';
import 'package:permission_handler/permission_handler.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';
import '../widgets/gift_animation_overlay.dart';
import '../widgets/gift_sheet.dart';
import '../widgets/live_chat_overlay.dart';
import '../widgets/live_guest_widget.dart';
import '../widgets/live_tiled_layout.dart';
import '../widgets/coin_purchase_sheet.dart';
import '../../../../core/services/realtime_client.dart';
import '../widgets/stream_quality_indicator.dart';
import '../widgets/live_leaderboard_sheet.dart';
import '../widgets/live_battle_bar.dart';
import '../widgets/battle_result_overlay.dart';
import '../widgets/live_goal_bar.dart';
import '../widgets/live_qa_panel.dart';
import '../widgets/live_raid_sheet.dart';
import '../widgets/live_subscription_sheet.dart';

class LiveViewerScreen extends StatefulWidget {
  final LiveRoom room;

  const LiveViewerScreen({super.key, required this.room});

  @override
  State<LiveViewerScreen> createState() => _LiveViewerScreenState();
}

class _LiveViewerScreenState extends State<LiveViewerScreen> {
  // Main viewer room (subscribe only)
  late Room _room;
  EventsListener<RoomEvent>? _listener;

  // Guest room (publish when accepted on stage)
  Room? _guestRoom;
  EventsListener<RoomEvent>? _guestListener;

  LiveSession? _session;
  int _viewerCount = 0;
  int _coinBalance = 0;
  int _totalLikes = 0;
  bool _hasLiked = false;
  bool _isLiking = false;
  bool _loading = true;
  bool _ended = false;

  // Guest join state
  GuestJoinStatus _guestStatus = GuestJoinStatus.none;
  bool _guestLoading = false;
  final List<LiveGuest> _activeGuests = [];

  final _giftEvents = <GiftEvent>[];
  final _repo = LiveRepository();

  // ── PK Battle ──────────────────────────────────────────────────────────────
  Room? _battleRoom;
  EventsListener<RoomEvent>? _battleListener;
  LiveBattle? _activeBattle;
  bool _battleEnded = false;

  // ── Phase 5 ────────────────────────────────────────────────────────────────
  LiveGoal? _activeGoal;
  LiveQuestion? _activeQuestion;
  LiveSubscription? _mySubscription;
  // Raid
  String? _raidFromHost;
  int? _raidTargetRoomId;
  String? _raidTargetTitle;
  int _raidCount = 0;

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
        ..on<TrackSubscribedEvent>((_) { if (mounted) setState(() {}); })
        ..on<TrackUnsubscribedEvent>((_) { if (mounted) setState(() {}); })
        ..on<ParticipantConnectedEvent>((_) { if (mounted) setState(() {}); })
        ..on<ParticipantDisconnectedEvent>((_) { if (mounted) setState(() {}); })
        ..on<RoomDisconnectedEvent>((_) {
          if (mounted) setState(() => _ended = true);
        });

      await _room.connect(
        session.livekitUrl,
        session.token,
        roomOptions: const RoomOptions(adaptiveStream: true, dynacast: true),
      );

      final myId = session.userId;

      // ── Private channel for this viewer ────────────────────────────────────
      await RealtimeClient.instance.listen(
        'private-user.$myId',
        'live.guest_accepted',
        _onGuestAccepted,
      );
      await RealtimeClient.instance.listen(
        'private-user.$myId',
        'live.guest_rejected',
        (_) {
          if (mounted) {
            setState(() { _guestStatus = GuestJoinStatus.none; _guestLoading = false; });
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Request declined by host'), backgroundColor: Colors.red),
            );
          }
        },
      );
      await RealtimeClient.instance.listen(
        'private-user.$myId',
        'live.guest_removed',
        (_) {
          if (mounted) {
            _disconnectGuestRoom();
            setState(() { _guestStatus = GuestJoinStatus.none; });
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('You were removed from the stage')),
            );
          }
        },
      );

      // ── Public room channel ──────────────────────────────────────────────────
      await RealtimeClient.instance.listen(_reverbChannel, 'gift.received', _handleGiftEvent);
      await RealtimeClient.instance.listen(_reverbChannel, 'viewer.joined',
          (_) { if (mounted) setState(() => _viewerCount++); });
      await RealtimeClient.instance.listen(_reverbChannel, 'viewer.left',
          (_) { if (mounted) setState(() { if (_viewerCount > 0) _viewerCount--; }); });
      await RealtimeClient.instance.listen(_reverbChannel, 'live.ended',
          (_) { if (mounted) setState(() => _ended = true); });
      await RealtimeClient.instance.listen(_reverbChannel, 'live.liked', (data) {
        if (mounted) setState(() => _totalLikes = (data as Map?)?['total_likes'] as int? ?? _totalLikes);
      });
      await RealtimeClient.instance.listen(_reverbChannel, 'live.guests_updated', (data) {
        if (!mounted) return;
        try {
          final list = (data as Map?)!['guests'] as List? ?? [];
          setState(() {
            _activeGuests
              ..clear()
              ..addAll(list.map((e) => LiveGuest.fromJson(Map<String, dynamic>.from(e as Map))));
          });
        } catch (_) {}
      });

      // PK Battle events
      await RealtimeClient.instance.listen(_reverbChannel, 'live.battle_started', _onBattleStarted);
      await RealtimeClient.instance.listen(_reverbChannel, 'battle.score_updated', _onBattleScoreUpdated);
      await RealtimeClient.instance.listen(_reverbChannel, 'battle.ended', _onBattleEnded);

      // Goal events
      await RealtimeClient.instance.listen(_reverbChannel, 'goal.updated', (data) {
        if (!mounted) return;
        try { setState(() => _activeGoal = LiveGoal.fromJson(Map<String, dynamic>.from(data as Map))); }
        catch (_) {}
      });
      await RealtimeClient.instance.listen(_reverbChannel, 'goal.completed', (data) {
        if (!mounted) return;
        try { setState(() => _activeGoal = LiveGoal.fromJson(Map<String, dynamic>.from(data as Map))); }
        catch (_) {}
      });
      await RealtimeClient.instance.listen(_reverbChannel, 'goal.cancelled', (_) {
        if (mounted) setState(() => _activeGoal = null);
      });

      // Q&A events
      await RealtimeClient.instance.listen(_reverbChannel, 'qa.question_active', (data) {
        if (!mounted) return;
        try { setState(() => _activeQuestion = LiveQuestion.fromJson(Map<String, dynamic>.from(data as Map))); }
        catch (_) {}
      });
      await RealtimeClient.instance.listen(_reverbChannel, 'qa.question_dismissed', (_) {
        if (mounted) setState(() => _activeQuestion = null);
      });

      // Raid event
      await RealtimeClient.instance.listen(_reverbChannel, 'live.raid_started', (data) {
        if (!mounted) return;
        try {
          final map = Map<String, dynamic>.from(data as Map);
          setState(() {
            _raidFromHost    = map['from_host_name'] as String? ?? '';
            _raidTargetRoomId = map['target_room_id'] as int?;
            _raidTargetTitle  = map['target_title']  as String? ?? '';
            _raidCount        = map['raider_count']  as int? ?? 0;
          });
        } catch (_) {}
      });

      // Load initial goal + subscription
      try {
        final g = await _repo.getGoal(widget.room.id);
        if (mounted && g != null) setState(() => _activeGoal = g);
      } catch (_) {}
      try {
        final s = await _repo.getMySubscription(widget.room.host.id);
        if (mounted) setState(() => _mySubscription = s);
      } catch (_) {}

      setState(() {});
    } catch (e) {
      if (mounted) Navigator.of(context).pop();
    }
  }

  Future<void> _onGuestAccepted(dynamic data) async {
    if (!mounted) return;
    try {
      final map = Map<String, dynamic>.from(data as Map);
      final token = map['token'] as String;
      final url = map['livekit_url'] as String;

      await [Permission.camera, Permission.microphone].request();

      _guestRoom = Room();
      _guestListener = _guestRoom!.createListener()
        ..on<LocalTrackPublishedEvent>((_) { if (mounted) setState(() {}); })
        ..on<LocalTrackUnpublishedEvent>((_) { if (mounted) setState(() {}); })
        ..on<RoomDisconnectedEvent>((_) {
          if (mounted) setState(() { _guestStatus = GuestJoinStatus.none; });
        });

      await _guestRoom!.connect(url, token,
          roomOptions: const RoomOptions(adaptiveStream: true, dynacast: true));
      await _guestRoom!.localParticipant?.setCameraEnabled(true);
      await _guestRoom!.localParticipant?.setMicrophoneEnabled(true);

      if (mounted) setState(() { _guestStatus = GuestJoinStatus.accepted; _guestLoading = false; });
    } catch (e) {
      if (mounted) setState(() { _guestStatus = GuestJoinStatus.none; _guestLoading = false; });
    }
  }

  Future<void> _disconnectGuestRoom() async {
    try {
      await _guestRoom?.disconnect();
    } catch (_) {}
    _guestListener?.dispose();
    _guestRoom = null;
  }

  Future<void> _onBattleStarted(dynamic data) async {
    if (!mounted) return;
    try {
      final map    = Map<String, dynamic>.from(data as Map);
      final battle = LiveBattle.fromJson(Map<String, dynamic>.from(map['battle'] as Map));
      final url    = map['livekit_url'] as String;

      // Fetch personal viewer token for the battle room
      final tokenData = await _repo.getBattleViewerToken(battle.id);
      final token = tokenData['token'] as String;

      final bRoom = Room();
      _battleListener = bRoom.createListener()
        ..on<TrackSubscribedEvent>((_) { if (mounted) setState(() {}); })
        ..on<TrackUnsubscribedEvent>((_) { if (mounted) setState(() {}); })
        ..on<ParticipantConnectedEvent>((_) { if (mounted) setState(() {}); })
        ..on<ParticipantDisconnectedEvent>((_) { if (mounted) setState(() {}); });

      await bRoom.connect(url, token,
          roomOptions: const RoomOptions(adaptiveStream: true, dynacast: true));

      if (mounted) {
        setState(() {
          _battleRoom   = bRoom;
          _activeBattle = battle;
          _battleEnded  = false;
        });
      }
    } catch (e) {
      debugPrint('[Battle viewer] _onBattleStarted error: $e');
    }
  }

  void _onBattleScoreUpdated(dynamic data) {
    if (!mounted || _activeBattle == null) return;
    try {
      final map    = Map<String, dynamic>.from(data as Map);
      final scores = (map['scores'] as List? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
      setState(() => _activeBattle = _activeBattle!.copyWithScores(scores));
    } catch (_) {}
  }

  void _onBattleEnded(dynamic data) {
    if (!mounted) return;
    try {
      final map = Map<String, dynamic>.from(data as Map);
      LiveBattle? ended;
      if (map['battle'] != null) {
        ended = LiveBattle.fromJson(Map<String, dynamic>.from(map['battle'] as Map));
      }
      setState(() {
        if (ended != null) _activeBattle = ended;
        _battleEnded = true;
      });
    } catch (_) {}
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

  Future<void> _requestJoinStage() async {
    if (_guestLoading) return;
    setState(() => _guestLoading = true);
    try {
      await _repo.requestToJoin(widget.room.id);
      if (mounted) setState(() { _guestStatus = GuestJoinStatus.waiting; _guestLoading = false; });
    } catch (e) {
      if (mounted) {
        setState(() => _guestLoading = false);
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('$e'), backgroundColor: Colors.red));
      }
    }
  }

  Future<void> _cancelJoinRequest() async {
    if (_guestLoading) return;
    setState(() => _guestLoading = true);
    try {
      await _repo.cancelJoinRequest(widget.room.id);
      if (mounted) setState(() { _guestStatus = GuestJoinStatus.none; _guestLoading = false; });
    } catch (e) {
      if (mounted) setState(() => _guestLoading = false);
    }
  }

  Future<void> _leave() async {
    if (_guestStatus == GuestJoinStatus.accepted) await _disconnectGuestRoom();
    await _repo.leaveRoom(widget.room.id);
    await _room.disconnect();
    if (mounted) Navigator.of(context).pop();
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
    String? reason;
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
                ('spam', '🚫', 'Spam or misleading'),
                ('nudity', '🔞', 'Nudity or sexual content'),
                ('hate_speech', '💬', 'Hate speech or harassment'),
                ('violence', '⚠️', 'Violence or harmful content'),
                ('other', '🔍', 'Other'),
              ].map((r) => RadioListTile<String>(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    value: r.$1,
                    groupValue: reason,
                    onChanged: (v) => setS(() => reason = v),
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
                  onPressed: reason == null
                      ? null
                      : () async {
                          Navigator.pop(ctx);
                          try {
                            await _repo.reportRoom(widget.room.id, reason!);
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
      final gift = gifts.firstWhere((g) => g.id == result['gift_id'],
          orElse: () => gifts.first);
      final event = GiftEvent(
          gift: gift,
          quantity: result['quantity'] ?? 1,
          senderName: 'You',
          senderAvatar: '');
      setState(() {
        _giftEvents.add(event);
        _coinBalance = result['new_balance'] ?? _coinBalance;
      });
      Future.delayed(const Duration(seconds: 4),
          () { if (mounted) setState(() => _giftEvents.remove(event)); });
    }
  }

  // Build TikTok-style tiles from LiveKit remote participants
  List<LiveTile> _buildTiles() {
    final tiles = <LiveTile>[];
    if (_loading) return tiles;

    // In battle mode: use the battle room participants
    if (_battleRoom != null) {
      final bParticipants = _battleRoom!.remoteParticipants.values.toList();
      for (final p in bParticipants) {
        final identity = p.identity ?? '';
        if (!identity.startsWith('host_')) continue;
        VideoTrack? video;
        bool muted = true;
        for (final pub in p.videoTrackPublications) {
          if (pub.subscribed && pub.track != null) video = pub.track as VideoTrack;
        }
        for (final pub in p.audioTrackPublications) {
          if (pub.subscribed && !pub.muted) muted = false;
        }
        final uid = int.tryParse(identity.replaceFirst('host_', '')) ?? 0;
        final bp = _activeBattle?.participants
            .where((bp) => bp.hostId == uid)
            .firstOrNull;
        tiles.add(LiveTile(
          label: bp?.name ?? widget.room.host.name,
          sublabel: bp?.username ?? widget.room.host.username,
          video: video,
          isMuted: muted,
          isHost: true,
          battleRank: bp?.rank,
          battleScore: bp?.score,
        ));
      }
      return tiles;
    }

    // Normal mode
    final participants = _room.remoteParticipants.values.toList();
    participants.sort((a, b) {
      final aHost = (a.identity ?? '').startsWith('host_') ? 0 : 1;
      final bHost = (b.identity ?? '').startsWith('host_') ? 0 : 1;
      return aHost.compareTo(bHost);
    });

    for (final p in participants) {
      final identity = p.identity ?? '';
      VideoTrack? video;
      bool muted = true;

      for (final pub in p.videoTrackPublications) {
        if (pub.subscribed && pub.track != null) video = pub.track as VideoTrack;
      }
      for (final pub in p.audioTrackPublications) {
        if (pub.subscribed && !pub.muted) muted = false;
      }

      if (identity.startsWith('host_')) {
        tiles.insert(0, LiveTile(
          label: widget.room.host.name,
          sublabel: widget.room.host.username,
          video: video,
          isMuted: muted,
          isHost: true,
        ));
      } else if (identity.startsWith('guest_')) {
        final uid = int.tryParse(identity.replaceFirst('guest_', '')) ?? 0;
        final g = _activeGuests.where((g) => g.userId == uid).firstOrNull;
        tiles.add(LiveTile(
          label: g?.name ?? 'Guest',
          sublabel: g?.username ?? '',
          video: video,
          isMuted: muted || (g?.isMuted ?? false),
          isHost: false,
        ));
      }
    }

    // If this viewer is on stage, add their local video at the end
    if (_guestStatus == GuestJoinStatus.accepted && _guestRoom != null) {
      VideoTrack? myVideo;
      for (final pub in _guestRoom!.localParticipant?.videoTrackPublications ?? []) {
        if (pub.track != null) myVideo = pub.track as VideoTrack;
      }
      tiles.add(LiveTile(
        label: 'You',
        video: myVideo,
        isMuted: false,
        isHost: false,
      ));
    }

    return tiles;
  }

  @override
  void dispose() {
    _listener?.dispose();
    _guestListener?.dispose();
    _battleListener?.dispose();
    _room.dispose();
    _guestRoom?.dispose();
    _battleRoom?.dispose();
    RealtimeClient.instance.unsubscribe(_reverbChannel);
    if (_session != null) {
      RealtimeClient.instance.unsubscribe('private-user.${_session!.userId}');
    }
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
                  child: const Text('Go Back')),
            ],
          ),
        ),
      );
    }

    final tiles = _buildTiles();

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          // ── Video area (TikTok tiled layout) ──────────────────────────────
          if (_loading)
            const Center(child: CircularProgressIndicator(color: Colors.orange))
          else if (tiles.isNotEmpty)
            Positioned.fill(child: LiveTiledLayout(tiles: tiles))
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

          // ── Top bar ───────────────────────────────────────────────────────
          Positioned(
            top: 0, left: 0, right: 0,
            child: SafeArea(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                child: Row(
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
                                color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                        const Row(children: [
                          Icon(Icons.circle, color: Colors.red, size: 8),
                          SizedBox(width: 4),
                          Text('LIVE', style: TextStyle(color: Colors.red, fontSize: 11)),
                        ]),
                      ],
                    ),
                    const Spacer(),
                    if (!_loading) StreamQualityIndicator(room: _room),
                    const SizedBox(width: 8),
                    // Viewer count
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(
                          color: Colors.black45, borderRadius: BorderRadius.circular(20)),
                      child: Row(children: [
                        const Icon(Icons.remove_red_eye, color: Colors.white70, size: 13),
                        const SizedBox(width: 3),
                        Text('$_viewerCount',
                            style: const TextStyle(color: Colors.white, fontSize: 12)),
                      ]),
                    ),
                    const SizedBox(width: 6),
                    GestureDetector(
                      onTap: () => showModalBottomSheet(
                        context: context,
                        backgroundColor: Colors.transparent,
                        isScrollControlled: true,
                        builder: (_) => LiveLeaderboardSheet(roomId: widget.room.id),
                      ),
                      child: Container(
                        width: 30, height: 30,
                        decoration: const BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
                        child: const Center(child: Text('🏆', style: TextStyle(fontSize: 14))),
                      ),
                    ),
                    const SizedBox(width: 6),
                    GestureDetector(
                      onTap: _showReport,
                      child: Container(
                        width: 30, height: 30,
                        decoration: const BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
                        child: const Icon(Icons.flag_outlined, color: Colors.white60, size: 15),
                      ),
                    ),
                    const SizedBox(width: 6),
                    GestureDetector(
                      onTap: _leave,
                      child: Container(
                        width: 30, height: 30,
                        decoration: const BoxDecoration(color: Colors.black45, shape: BoxShape.circle),
                        child: const Icon(Icons.close, color: Colors.white, size: 16),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // ── Goal bar ──────────────────────────────────────────────────────
          if (_activeGoal != null)
            Positioned(
              top: 95, left: 0, right: 0,
              child: LiveGoalBar(goal: _activeGoal!),
            ),

          // ── Active Q&A question banner ─────────────────────────────────────
          if (_activeQuestion != null)
            Positioned(
              top: _activeGoal != null ? 155 : 100,
              left: 0, right: 0,
              child: ActiveQuestionBanner(
                question: _activeQuestion!,
                onDismiss: () => setState(() => _activeQuestion = null),
              ),
            ),

          // ── Raid overlay ───────────────────────────────────────────────────
          if (_raidTargetRoomId != null)
            Positioned(
              bottom: 90, left: 0, right: 0,
              child: LiveRaidOverlay(
                fromHostName: _raidFromHost ?? '',
                targetRoomId: _raidTargetRoomId!,
                targetTitle:  _raidTargetTitle ?? '',
                raiderCount:  _raidCount,
                onNavigate: (roomId) {
                  setState(() {
                    _raidTargetRoomId = null;
                    _raidFromHost     = null;
                  });
                  // Navigate to the raided room — pop and push new viewer
                  Navigator.pop(context);
                },
                onDismiss: () => setState(() {
                  _raidTargetRoomId = null;
                  _raidFromHost     = null;
                }),
              ),
            ),

          // ── PK Battle bar ─────────────────────────────────────────────────
          if (_activeBattle != null && !_battleEnded)
            Positioned(
              top: 100, left: 0, right: 0,
              child: LiveBattleBar(battle: _activeBattle!),
            ),

          // ── Battle result overlay ─────────────────────────────────────────
          if (_activeBattle != null && _battleEnded)
            Positioned.fill(
              child: BattleResultOverlay(
                battle: _activeBattle!,
                onDismiss: () => setState(() {
                  _activeBattle = null;
                  _battleEnded  = false;
                  _battleRoom?.dispose();
                  _battleRoom   = null;
                }),
              ),
            ),

          // ── Gift animations ───────────────────────────────────────────────
          ...(_giftEvents.map((e) => GiftAnimationOverlay(event: e))),

          // ── Like button ───────────────────────────────────────────────────
          Positioned(
            right: 12, bottom: 150,
            child: Column(children: [
              GestureDetector(
                onTap: _like,
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: 48, height: 48,
                  decoration: BoxDecoration(
                    color: _hasLiked ? Colors.red.withValues(alpha: 0.3) : Colors.black54,
                    shape: BoxShape.circle,
                    border: Border.all(color: _hasLiked ? Colors.red : Colors.white24),
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
            ]),
          ),

          // ── Chat overlay ──────────────────────────────────────────────────
          if (!_loading)
            Positioned(
              bottom: 80, left: 0, right: 60,
              child: LiveChatOverlay(
                roomId: widget.room.id,
                reverbChannel: _reverbChannel,
              ),
            ),

          // ── Bottom bar ────────────────────────────────────────────────────
          Positioned(
            bottom: 24, left: 12, right: 12,
            child: Row(children: [
              GestureDetector(
                onTap: () async {
                  await showModalBottomSheet(
                    context: context,
                    backgroundColor: Colors.transparent,
                    isScrollControlled: true,
                    builder: (_) => CoinPurchaseSheet(
                      currentBalance: _coinBalance,
                      onPurchased: (nb) { if (mounted) setState(() => _coinBalance = nb); },
                    ),
                  );
                },
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: Colors.black54,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: Colors.orange.withValues(alpha: 0.4)),
                  ),
                  child: Row(children: [
                    const Text('🪙', style: TextStyle(fontSize: 14)),
                    const SizedBox(width: 4),
                    Text('$_coinBalance',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                    const SizedBox(width: 4),
                    const Icon(Icons.add_circle_outline, color: Colors.orange, size: 14),
                  ]),
                ),
              ),
              const SizedBox(width: 8),
              if (!_loading)
                JoinRequestButton(
                  status: _guestStatus,
                  loading: _guestLoading,
                  onJoin: _requestJoinStage,
                  onCancel: _cancelJoinRequest,
                ),
              const SizedBox(width: 8),
              // Q&A button
              GestureDetector(
                onTap: () => showModalBottomSheet(
                  context: context,
                  backgroundColor: const Color(0xFF1A1A2E),
                  isScrollControlled: true,
                  builder: (_) => SubmitQuestionSheet(roomId: widget.room.id),
                ),
                child: Container(
                  width: 36, height: 36,
                  decoration: BoxDecoration(
                    color: Colors.purple.withValues(alpha: 0.3),
                    shape: BoxShape.circle,
                    border: Border.all(color: Colors.purple.withValues(alpha: 0.6)),
                  ),
                  child: const Center(child: Text('❓', style: TextStyle(fontSize: 14))),
                ),
              ),
              const SizedBox(width: 8),
              // Subscribe button
              GestureDetector(
                onTap: _mySubscription != null ? null : () => showModalBottomSheet(
                  context: context,
                  backgroundColor: Colors.transparent,
                  isScrollControlled: true,
                  builder: (_) => LiveSubscriptionSheet(
                    hostId:   widget.room.host.id,
                    hostName: widget.room.host.name,
                    onSubscribed: (s) => setState(() => _mySubscription = s),
                  ),
                ),
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
                  decoration: BoxDecoration(
                    color: _mySubscription != null
                        ? Colors.amber.withValues(alpha: 0.2)
                        : Colors.black54,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: _mySubscription != null
                          ? Colors.amber
                          : Colors.white24,
                    ),
                  ),
                  child: Text(
                    _mySubscription != null
                        ? '${_mySubscription!.emoji} Sub'
                        : '⭐ Sub',
                    style: TextStyle(
                      color: _mySubscription != null ? Colors.amber : Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
              const Spacer(),
              GestureDetector(
                onTap: _showGifts,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(colors: [Colors.orange, Colors.deepOrange]),
                    borderRadius: BorderRadius.circular(24),
                  ),
                  child: const Row(children: [
                    Text('🎁', style: TextStyle(fontSize: 16)),
                    SizedBox(width: 6),
                    Text('Gift',
                        style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                  ]),
                ),
              ),
            ]),
          ),
        ],
      ),
    );
  }
}
