import 'dart:async';
import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

// ── Host: Pick Raid Target ────────────────────────────────────────────────────

class LiveRaidSheet extends StatefulWidget {
  final int roomId;
  final VoidCallback onRaided;

  const LiveRaidSheet({super.key, required this.roomId, required this.onRaided});

  @override
  State<LiveRaidSheet> createState() => _LiveRaidSheetState();
}

class _LiveRaidSheetState extends State<LiveRaidSheet> {
  final _repo = LiveRepository();
  List<RaidTarget> _targets = [];
  bool _loading = true;
  int? _raiding;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final t = await _repo.getRaidTargets(widget.roomId);
      if (mounted) setState(() { _targets = t; _loading = false; });
    } catch (_) { if (mounted) setState(() => _loading = false); }
  }

  Future<void> _doRaid(int targetRoomId) async {
    setState(() => _raiding = targetRoomId);
    try {
      await _repo.raid(widget.roomId, targetRoomId);
      if (mounted) {
        Navigator.pop(context);
        widget.onRaided();
      }
    } catch (_) { setState(() => _raiding = null); }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.55,
      decoration: const BoxDecoration(
        color: Color(0xFF1A1A2E),
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        children: [
          const SizedBox(height: 12),
          Container(width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.white24,
                  borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 12),
          const Text('🚀 Send Raid',
              style: TextStyle(color: Colors.white, fontSize: 17,
                  fontWeight: FontWeight.bold)),
          const SizedBox(height: 4),
          const Text('Send your viewers to another live room',
              style: TextStyle(color: Colors.white38, fontSize: 12)),
          const SizedBox(height: 12),
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: Colors.orange))
                : _targets.isEmpty
                    ? const Center(
                        child: Text('No live rooms available',
                            style: TextStyle(color: Colors.white38)))
                    : ListView.separated(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 8),
                        itemCount: _targets.length,
                        separatorBuilder: (_, __) =>
                            const Divider(color: Colors.white10, height: 1),
                        itemBuilder: (_, i) {
                          final t = _targets[i];
                          final raiding = _raiding == t.roomId;
                          return ListTile(
                            contentPadding: EdgeInsets.zero,
                            leading: CircleAvatar(
                              backgroundImage: t.hostAvatar.isNotEmpty
                                  ? NetworkImage(t.hostAvatar)
                                  : null,
                              backgroundColor: Colors.blue.withValues(alpha: 0.3),
                              child: t.hostAvatar.isEmpty
                                  ? Text(t.hostName.isNotEmpty
                                        ? t.hostName[0].toUpperCase()
                                        : '?',
                                      style: const TextStyle(color: Colors.white))
                                  : null,
                            ),
                            title: Text(t.title,
                                style: const TextStyle(color: Colors.white,
                                    fontWeight: FontWeight.w600, fontSize: 13),
                                overflow: TextOverflow.ellipsis),
                            subtitle: Row(
                              children: [
                                Text(t.hostName,
                                    style: const TextStyle(
                                        color: Colors.white54, fontSize: 11)),
                                const SizedBox(width: 8),
                                const Icon(Icons.remove_red_eye,
                                    size: 11, color: Colors.white38),
                                const SizedBox(width: 2),
                                Text('${t.viewerCount}',
                                    style: const TextStyle(
                                        color: Colors.white38, fontSize: 11)),
                              ],
                            ),
                            trailing: ElevatedButton(
                              onPressed: raiding ? null : () => _doRaid(t.roomId),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: Colors.blue,
                                foregroundColor: Colors.white,
                                padding: const EdgeInsets.symmetric(
                                    horizontal: 14, vertical: 6),
                                shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(20)),
                              ),
                              child: raiding
                                  ? const SizedBox(width: 16, height: 16,
                                      child: CircularProgressIndicator(
                                          strokeWidth: 2, color: Colors.white))
                                  : const Text('Raid 🚀',
                                      style: TextStyle(fontSize: 12)),
                            ),
                          );
                        },
                      ),
          ),
          const SizedBox(height: 16),
        ],
      ),
    );
  }
}

// ── Viewer: Raid Incoming Overlay ─────────────────────────────────────────────

class LiveRaidOverlay extends StatefulWidget {
  final String fromHostName;
  final int targetRoomId;
  final String targetTitle;
  final int raiderCount;
  final void Function(int targetRoomId) onNavigate;
  final VoidCallback onDismiss;

  const LiveRaidOverlay({
    super.key,
    required this.fromHostName,
    required this.targetRoomId,
    required this.targetTitle,
    required this.raiderCount,
    required this.onNavigate,
    required this.onDismiss,
  });

  @override
  State<LiveRaidOverlay> createState() => _LiveRaidOverlayState();
}

class _LiveRaidOverlayState extends State<LiveRaidOverlay>
    with SingleTickerProviderStateMixin {
  late AnimationController _anim;
  late Animation<double> _slide;
  int _countdown = 5;
  Timer? _ticker;

  @override
  void initState() {
    super.initState();
    _anim  = AnimationController(vsync: this, duration: const Duration(milliseconds: 400));
    _slide = Tween<double>(begin: 100, end: 0).animate(
        CurvedAnimation(parent: _anim, curve: Curves.easeOut));
    _anim.forward();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() => _countdown--);
      if (_countdown <= 0) widget.onNavigate(widget.targetRoomId);
    });
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _anim.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _slide,
      builder: (_, child) => Transform.translate(
        offset: Offset(0, _slide.value),
        child: child,
      ),
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 16),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF0D47A1), Color(0xFF1565C0)],
          ),
          borderRadius: BorderRadius.circular(18),
          boxShadow: [
            BoxShadow(color: Colors.blue.withValues(alpha: 0.4),
                blurRadius: 20, spreadRadius: 2),
          ],
        ),
        child: Row(
          children: [
            const Text('🚀', style: TextStyle(fontSize: 32)),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    '${widget.fromHostName} is raiding!',
                    style: const TextStyle(color: Colors.white,
                        fontWeight: FontWeight.bold, fontSize: 14),
                  ),
                  Text(
                    '${widget.raiderCount} viewers → ${widget.targetTitle}',
                    style: const TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                ],
              ),
            ),
            Column(
              children: [
                Container(
                  width: 36, height: 36,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2),
                    shape: BoxShape.circle,
                  ),
                  child: Center(
                    child: Text('$_countdown',
                        style: const TextStyle(color: Colors.white,
                            fontWeight: FontWeight.bold, fontSize: 16)),
                  ),
                ),
                const SizedBox(height: 4),
                GestureDetector(
                  onTap: widget.onDismiss,
                  child: const Text('Skip',
                      style: TextStyle(color: Colors.white54, fontSize: 10)),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
