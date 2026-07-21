import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:livekit_client/livekit_client.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';
import '../widgets/gift_animation_overlay.dart';
import '../widgets/live_chat_overlay.dart';
import '../widgets/live_guest_widget.dart';
import '../widgets/live_tiled_layout.dart';
import '../widgets/live_host_dashboard.dart';
import '../widgets/live_leaderboard_sheet.dart';
import '../widgets/live_moderation_panel.dart';
import '../widgets/live_battle_bar.dart';
import '../widgets/battle_invite_popup.dart';
import '../widgets/battle_result_overlay.dart';
import '../widgets/live_goal_bar.dart';
import '../widgets/live_qa_panel.dart';
import '../widgets/live_raid_sheet.dart';
import '../widgets/live_beauty_filter.dart';
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
  int _totalLikes = 0;
  bool _showDashboard = false;
  LiveRoomSettings? _settings;
  Timer? _timer;
  final _giftEvents = <GiftEvent>[];
  final _guestRequests = <GuestRequest>[];
  final _activeGuests = <LiveGuest>[];
  final _repo = LiveRepository();

  // ── PK Battle ──────────────────────────────────────────────────────────────
  Room? _battleRoom;
  LiveBattle? _activeBattle;
  BattleInvite? _pendingInvite;
  bool _battleEnded = false;

  // ── Phase 5 features ───────────────────────────────────────────────────────
  LiveGoal? _activeGoal;
  LiveQuestion? _activeQuestion;
  BeautyFilter _filter = BeautyFilter.none;
  bool _showFilterStrip = false;
  bool _showRaidConfirm = false;

  @override
  void initState() {
    super.initState();
    _connect();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      setState(() => _duration++);
    });
  }

  String get _reverbChannel => 'live.${widget.session.room.id}';

  Future<void> _connect() async {
    try {
      await [Permission.camera, Permission.microphone].request();
      _room = Room();
      _listener = _room.createListener()
        ..on<LocalTrackPublishedEvent>((_) { if (mounted) setState(() {}); })
        ..on<LocalTrackUnpublishedEvent>((_) { if (mounted) setState(() {}); })
        ..on<ParticipantConnectedEvent>((_) { if (mounted) setState(() => _viewerCount++); })
        ..on<ParticipantDisconnectedEvent>((_) {
          if (mounted) setState(() { if (_viewerCount > 0) _viewerCount--; });
        });

      await _room.connect(
        widget.session.livekitUrl,
        widget.session.token,
        roomOptions: const RoomOptions(adaptiveStream: true, dynacast: true),
      );

      await _room.localParticipant?.setCameraEnabled(true);
      await _room.localParticipant?.setMicrophoneEnabled(true);
    } catch (e) {
      debugPrint('[Live] connect error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Connection failed: $e'), backgroundColor: Colors.red),
        );
        Navigator.of(context).pop();
        return;
      }
    }

    // Load initial settings
    try {
      final s = await _repo.getSettings(widget.session.room.id);
      if (mounted) setState(() => _settings = s);
    } catch (_) {}

    // Live likes from Reverb
    await RealtimeClient.instance.listen(_reverbChannel, 'live.liked', (data) {
      if (mounted) setState(() => _totalLikes = (data as Map?)?['total_likes'] as int? ?? _totalLikes);
    });

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

    // Guest system — incoming requests on private-user channel
    await RealtimeClient.instance.listen(
      'private-user.${widget.session.room.host.id}',
      'live.guest_request',
      _onGuestRequest,
    );
    await RealtimeClient.instance.listen(
      _reverbChannel,
      'live.guests_updated',
      _onGuestsUpdated,
    );

    // PK Battle — incoming invite on private channel
    await RealtimeClient.instance.listen(
      'private-user.${widget.session.room.host.id}',
      'live.battle_invite',
      _onBattleInvite,
    );
    // PK Battle — accepted (host A receives token for battle room)
    await RealtimeClient.instance.listen(
      'private-user.${widget.session.room.host.id}',
      'live.battle_accepted',
      _onBattleAccepted,
    );
    // PK Battle — score updates
    await RealtimeClient.instance.listen(
      _reverbChannel,
      'battle.score_updated',
      _onBattleScoreUpdated,
    );
    // PK Battle — ended
    await RealtimeClient.instance.listen(
      _reverbChannel,
      'battle.ended',
      _onBattleEnded,
    );
    // PK Battle — rejected
    await RealtimeClient.instance.listen(
      'private-user.${widget.session.room.host.id}',
      'live.battle_rejected',
      (_) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Battle invite declined'), backgroundColor: Colors.orange),
          );
        }
      },
    );

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

    // Q&A: new question from viewer (private channel)
    await RealtimeClient.instance.listen(
      'private-user.${widget.session.room.host.id}',
      'live.new_question',
      (data) {
        if (!mounted) return;
        try {
          final q = LiveQuestion.fromJson(Map<String, dynamic>.from(data as Map));
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('❓ @${q.username}: ${q.question}'),
              backgroundColor: Colors.purple,
              duration: const Duration(seconds: 3),
            ),
          );
        } catch (_) {}
      },
    );

    // Load existing goal
    try {
      final g = await _repo.getGoal(widget.session.room.id);
      if (mounted && g != null) setState(() => _activeGoal = g);
    } catch (_) {}

    setState(() {});
  }

  void _onBattleInvite(dynamic data) {
    if (!mounted) return;
    try {
      final invite = BattleInvite.fromJson(Map<String, dynamic>.from(data as Map));
      setState(() => _pendingInvite = invite);
    } catch (_) {}
  }

  Future<void> _onBattleAccepted(dynamic data) async {
    // Host A: received battle room token, switch LiveKit to battle room
    if (!mounted) return;
    try {
      final map = Map<String, dynamic>.from(data as Map);
      final battle = LiveBattle.fromJson(Map<String, dynamic>.from(map['battle'] as Map));
      final hostToken   = map['host_token'] as String;
      final livekitUrl  = map['livekit_url'] as String;
      await _joinBattleRoom(battle, hostToken, livekitUrl);
    } catch (e) {
      debugPrint('[Battle] _onBattleAccepted error: $e');
    }
  }

  Future<void> _joinBattleRoom(LiveBattle battle, String token, String livekitUrl) async {
    try {
      final bRoom = Room();
      await bRoom.connect(
        livekitUrl, token,
        roomOptions: const RoomOptions(adaptiveStream: true, dynacast: true),
      );
      await bRoom.localParticipant?.setCameraEnabled(true);
      await bRoom.localParticipant?.setMicrophoneEnabled(true);
      if (mounted) {
        setState(() {
          _battleRoom   = bRoom;
          _activeBattle = battle;
          _battleEnded  = false;
        });
      }
    } catch (e) {
      debugPrint('[Battle] _joinBattleRoom error: $e');
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

  Future<void> _showBattleHostSheet() async {
    final hosts = await _repo.getAvailableBattleHosts(widget.session.room.id);
    if (!mounted) return;
    showModalBottomSheet(
      context: context,
      backgroundColor: const Color(0xFF1A1A2E),
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _BattleHostSheet(
        hosts: hosts,
        onInvite: (toRoomId) async {
          Navigator.pop(context);
          try {
            await _repo.inviteToBattle(widget.session.room.id, toRoomId);
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Battle invite sent! ⚔️'),
                  backgroundColor: Colors.orange,
                ),
              );
            }
          } catch (_) {}
        },
      ),
    );
  }

  void _onGuestRequest(dynamic data) {
    if (!mounted) return;
    try {
      final req = GuestRequest.fromJson(Map<String, dynamic>.from(data as Map));
      setState(() => _guestRequests.add(req));
    } catch (_) {}
  }

  void _onGuestsUpdated(dynamic data) {
    if (!mounted) return;
    try {
      final list = (data['guests'] as List? ?? []);
      setState(() {
        _activeGuests
          ..clear()
          ..addAll(list.map((e) => LiveGuest.fromJson(Map<String, dynamic>.from(e as Map))));
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
    _battleRoom?.dispose();
    RealtimeClient.instance.unsubscribe(_reverbChannel);
    super.dispose();
  }

  List<LiveTile> _buildTiles() {
    final tiles = <LiveTile>[];
    final activeRoom = _battleRoom ?? _room;

    // Host local video (always first)
    VideoTrack? localVideo;
    for (final pub in activeRoom.localParticipant?.videoTrackPublications ?? []) {
      if (pub.track != null) localVideo = pub.track as VideoTrack;
    }
    final myParticipant = _activeBattle?.participants
        .where((p) => p.hostId == widget.session.room.host.id)
        .firstOrNull;
    tiles.add(LiveTile(
      label: 'You',
      video: localVideo,
      isMuted: !_micOn,
      isHost: true,
      battleRank: myParticipant?.rank,
      battleScore: myParticipant?.score,
    ));

    if (_battleRoom != null) {
      // Battle mode: show remote hosts from battle room
      for (final p in _battleRoom!.remoteParticipants.values) {
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
          label: bp?.name ?? 'Host',
          sublabel: bp?.username != null ? '@${bp!.username}' : '',
          video: video,
          isMuted: muted,
          isHost: true,
          battleRank: bp?.rank,
          battleScore: bp?.score,
        ));
      }
    } else {
      // Normal mode: show guests
      for (final p in _room.remoteParticipants.values) {
        final identity = p.identity ?? '';
        if (!identity.startsWith('guest_')) continue;
        VideoTrack? video;
        bool muted = true;
        for (final pub in p.videoTrackPublications) {
          if (pub.subscribed && pub.track != null) video = pub.track as VideoTrack;
        }
        for (final pub in p.audioTrackPublications) {
          if (pub.subscribed && !pub.muted) muted = false;
        }
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
    return tiles;
  }

  @override
  Widget build(BuildContext context) {
    final tiles = _buildTiles();

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        children: [
          // ── TikTok-style tiled layout (with beauty filter) ────────────────
          if (tiles.isNotEmpty)
            Positioned.fill(
              child: BeautyFilterWidget(
                filter: _filter,
                child: LiveTiledLayout(tiles: tiles),
              ),
            )
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
                    // Filter button
                    _TopBtn(emoji: '✨', active: _filter != BeautyFilter.none,
                        onTap: () => setState(() => _showFilterStrip = !_showFilterStrip)),
                    // Goal button
                    _TopBtn(emoji: '🎯', active: _activeGoal != null,
                        onTap: () => showModalBottomSheet(
                          context: context,
                          backgroundColor: Colors.transparent,
                          isScrollControlled: true,
                          builder: (_) => SetGoalSheet(
                            roomId: widget.session.room.id,
                            onGoalSet: (g) => setState(() => _activeGoal = g),
                          ),
                        )),
                    // Q&A button
                    _TopBtn(emoji: '❓', active: false,
                        onTap: () => showModalBottomSheet(
                          context: context,
                          backgroundColor: Colors.transparent,
                          isScrollControlled: true,
                          builder: (_) => LiveQAPanel(roomId: widget.session.room.id),
                        )),
                    // Raid button
                    _TopBtn(emoji: '🚀', active: false,
                        onTap: () => showModalBottomSheet(
                          context: context,
                          backgroundColor: Colors.transparent,
                          isScrollControlled: true,
                          builder: (_) => LiveRaidSheet(
                            roomId: widget.session.room.id,
                            onRaided: () => ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('🚀 Raid sent!'),
                                  backgroundColor: Colors.blue),
                            ),
                          ),
                        )),
                    // PK Battle button (only when no active battle)
                    if (_activeBattle == null)
                      _TopBtn(emoji: '⚔️', active: false, onTap: _showBattleHostSheet),
                    // Dashboard toggle
                    GestureDetector(
                      onTap: () => setState(() => _showDashboard = !_showDashboard),
                      child: Container(
                        padding: const EdgeInsets.all(7),
                        margin: const EdgeInsets.only(right: 6),
                        decoration: BoxDecoration(
                          color: _showDashboard
                              ? Colors.orange.withValues(alpha: 0.3)
                              : Colors.black45,
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white24),
                        ),
                        child: const Text('📊', style: TextStyle(fontSize: 14)),
                      ),
                    ),
                    // Leaderboard
                    GestureDetector(
                      onTap: () => showModalBottomSheet(
                        context: context,
                        backgroundColor: Colors.transparent,
                        isScrollControlled: true,
                        builder: (_) => LiveLeaderboardSheet(roomId: widget.session.room.id),
                      ),
                      child: Container(
                        padding: const EdgeInsets.all(7),
                        margin: const EdgeInsets.only(right: 6),
                        decoration: BoxDecoration(
                          color: Colors.black45,
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white24),
                        ),
                        child: const Text('🏆', style: TextStyle(fontSize: 14)),
                      ),
                    ),
                    // Moderation
                    GestureDetector(
                      onTap: () {
                        final s = _settings ?? LiveRoomSettings(
                          slowMode: false, slowModeSeconds: 30,
                          followersOnly: false, commentsDisabled: false, blockedWords: []);
                        showModalBottomSheet(
                          context: context,
                          backgroundColor: Colors.transparent,
                          isScrollControlled: true,
                          builder: (_) => LiveModerationPanel(
                            roomId: widget.session.room.id,
                            initial: s,
                            onUpdated: (updated) => setState(() => _settings = updated),
                          ),
                        );
                      },
                      child: Container(
                        padding: const EdgeInsets.all(7),
                        margin: const EdgeInsets.only(right: 6),
                        decoration: BoxDecoration(
                          color: Colors.black45,
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white24),
                        ),
                        child: const Text('🛡️', style: TextStyle(fontSize: 14)),
                      ),
                    ),
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

          // Filter strip (above bottom controls)
          if (_showFilterStrip)
            Positioned(
              bottom: 100, left: 0, right: 0,
              child: FilterSelectorStrip(
                selected: _filter,
                onSelect: (f) => setState(() { _filter = f; _showFilterStrip = false; }),
              ),
            ),

          // Goal bar
          if (_activeGoal != null)
            Positioned(
              top: 95, left: 0, right: 0,
              child: LiveGoalBar(
                goal: _activeGoal!,
                onTap: () async {
                  await _repo.cancelGoal(widget.session.room.id);
                  setState(() => _activeGoal = null);
                },
              ),
            ),

          // PK Battle bar (below top bar)
          if (_activeBattle != null && !_battleEnded)
            Positioned(
              top: _activeGoal != null ? 155 : 100,
              left: 0,
              right: 0,
              child: LiveBattleBar(battle: _activeBattle!),
            ),

          // Battle result overlay
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

          // Battle invite popup (incoming from another host)
          if (_pendingInvite != null)
            Positioned(
              top: 0, bottom: 0, left: 0, right: 0,
              child: Center(
                child: BattleInvitePopup(
                  invite: _pendingInvite!,
                  onAccepted: () async {
                    // Accept was called inside popup — now listen for battle_accepted event
                    setState(() => _pendingInvite = null);
                  },
                  onDismissed: () => setState(() => _pendingInvite = null),
                ),
              ),
            ),

          // Gift animations
          ...(_giftEvents.map((e) => GiftAnimationOverlay(event: e))),

          // Guest request popup (one at a time)
          if (_guestRequests.isNotEmpty)
            GuestRequestPopup(
              request: _guestRequests.first,
              roomId: widget.session.room.id,
              onHandled: () {
                if (mounted) setState(() => _guestRequests.removeAt(0));
              },
            ),

          // Host dashboard overlay
          if (_showDashboard)
            LiveHostDashboard(
              roomId: widget.session.room.id,
              totalLikes: _totalLikes,
              onClose: () => setState(() => _showDashboard = false),
            ),

          // Chat overlay (left side, above bottom controls)
          Positioned(
            bottom: 110,
            left: 0,
            right: 60,
            child: LiveChatOverlay(
              roomId: widget.session.room.id,
              reverbChannel: _reverbChannel,
              isHost: true,
            ),
          ),

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
                    if (!_cameraOn) return;
                    _frontCamera = !_frontCamera;
                    if (mounted) setState(() {});
                    try {
                      final pos = _frontCamera ? CameraPosition.front : CameraPosition.back;
                      // Use setCameraPosition on the existing track — no unpublish needed
                      final track = _room.localParticipant
                          ?.videoTrackPublications.firstOrNull?.track;
                      if (track is LocalVideoTrack) {
                        await track.setCameraPosition(pos);
                      } else {
                        // Fallback: disable then re-enable with new position
                        await _room.localParticipant?.setCameraEnabled(false);
                        await Future.delayed(const Duration(milliseconds: 500));
                        await _room.localParticipant?.setCameraEnabled(true,
                            cameraCaptureOptions: CameraCaptureOptions(cameraPosition: pos));
                      }
                    } catch (e) {
                      debugPrint('[Live] flip error: $e');
                    }
                    if (mounted) setState(() {});
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

// ── Battle Host Selection Sheet ───────────────────────────────────────────────

class _BattleHostSheet extends StatelessWidget {
  final List<Map<String, dynamic>> hosts;
  final void Function(int toRoomId) onInvite;

  const _BattleHostSheet({required this.hosts, required this.onInvite});

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        const SizedBox(height: 16),
        Container(
          width: 40, height: 4,
          decoration: BoxDecoration(
            color: Colors.white24,
            borderRadius: BorderRadius.circular(2),
          ),
        ),
        const SizedBox(height: 16),
        const Text(
          '⚔️ Invite to PK Battle',
          style: TextStyle(
            color: Colors.white,
            fontSize: 17,
            fontWeight: FontWeight.bold,
          ),
        ),
        const SizedBox(height: 8),
        if (hosts.isEmpty)
          const Padding(
            padding: EdgeInsets.all(32),
            child: Text(
              'No live hosts available right now',
              style: TextStyle(color: Colors.white54),
            ),
          )
        else
          Flexible(
            child: ListView.separated(
              shrinkWrap: true,
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              itemCount: hosts.length,
              separatorBuilder: (_, __) => const Divider(color: Colors.white12, height: 1),
              itemBuilder: (_, i) {
                final h = hosts[i];
                return ListTile(
                  leading: CircleAvatar(
                    backgroundImage: (h['host_avatar'] as String? ?? '').isNotEmpty
                        ? NetworkImage(h['host_avatar'] as String)
                        : null,
                    backgroundColor: Colors.orange.withOpacity(0.3),
                    child: (h['host_avatar'] as String? ?? '').isEmpty
                        ? Text(
                            (h['host_name'] as String? ?? '?').substring(0, 1).toUpperCase(),
                            style: const TextStyle(color: Colors.white))
                        : null,
                  ),
                  title: Text(
                    h['host_name'] as String? ?? '',
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
                  ),
                  subtitle: Row(
                    children: [
                      Text(
                        '@${h['host_username'] ?? ''}',
                        style: const TextStyle(color: Colors.white54, fontSize: 12),
                      ),
                      const SizedBox(width: 8),
                      const Icon(Icons.remove_red_eye, size: 12, color: Colors.white38),
                      const SizedBox(width: 2),
                      Text(
                        '${h['viewer_count'] ?? 0}',
                        style: const TextStyle(color: Colors.white38, fontSize: 12),
                      ),
                    ],
                  ),
                  trailing: ElevatedButton(
                    onPressed: () => onInvite(h['room_id'] as int),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.orange,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(20)),
                    ),
                    child: const Text('Invite', style: TextStyle(fontSize: 12)),
                  ),
                );
              },
            ),
          ),
        const SizedBox(height: 24),
      ],
    );
  }
}

class _TopBtn extends StatelessWidget {
  final String emoji;
  final bool active;
  final VoidCallback onTap;

  const _TopBtn({required this.emoji, required this.active, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(7),
          margin: const EdgeInsets.only(right: 6),
          decoration: BoxDecoration(
            color: active
                ? Colors.orange.withValues(alpha: 0.3)
                : Colors.black45,
            shape: BoxShape.circle,
            border: Border.all(
              color: active ? Colors.orange : Colors.white24,
            ),
          ),
          child: Text(emoji, style: const TextStyle(fontSize: 14)),
        ),
      );
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
