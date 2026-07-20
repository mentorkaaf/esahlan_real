import 'dart:async';
import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

class LiveHostDashboard extends StatefulWidget {
  final int roomId;
  final int totalLikes;
  final VoidCallback onClose;

  const LiveHostDashboard({
    super.key,
    required this.roomId,
    required this.totalLikes,
    required this.onClose,
  });

  @override
  State<LiveHostDashboard> createState() => _LiveHostDashboardState();
}

class _LiveHostDashboardState extends State<LiveHostDashboard>
    with SingleTickerProviderStateMixin {
  final _repo = LiveRepository();
  LiveStats? _stats;
  Timer? _refresh;
  late AnimationController _entryCtrl;
  late Animation<Offset> _slideAnim;

  @override
  void initState() {
    super.initState();
    _entryCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 350));
    _slideAnim = Tween<Offset>(begin: const Offset(1, 0), end: Offset.zero)
        .animate(CurvedAnimation(parent: _entryCtrl, curve: Curves.easeOut));
    _entryCtrl.forward();
    _load();
    _refresh = Timer.periodic(const Duration(seconds: 10), (_) => _load());
  }

  Future<void> _load() async {
    try {
      final stats = await _repo.getStats(widget.roomId);
      if (mounted) setState(() => _stats = stats);
    } catch (_) {}
  }

  @override
  void dispose() {
    _refresh?.cancel();
    _entryCtrl.dispose();
    super.dispose();
  }

  String _fmt(int n) {
    if (n >= 1000000) return '${(n / 1000000).toStringAsFixed(1)}M';
    if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}K';
    return '$n';
  }

  String get _duration {
    final secs = _stats?.durationSeconds ?? 0;
    final h = secs ~/ 3600;
    final m = (secs % 3600) ~/ 60;
    final s = secs % 60;
    if (h > 0) return '${h}h ${m}m';
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    return Positioned(
      top: 80,
      right: 12,
      child: SlideTransition(
        position: _slideAnim,
        child: Container(
          width: 200,
          decoration: BoxDecoration(
            color: Colors.black.withValues(alpha: 0.85),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Colors.white12),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Header
              Padding(
                padding: const EdgeInsets.fromLTRB(12, 10, 8, 0),
                child: Row(
                  children: [
                    const Text('📊',
                        style: TextStyle(fontSize: 14)),
                    const SizedBox(width: 6),
                    const Text('Dashboard',
                        style: TextStyle(
                            color: Colors.white, fontWeight: FontWeight.bold, fontSize: 13)),
                    const Spacer(),
                    GestureDetector(
                      onTap: widget.onClose,
                      child: const Icon(Icons.close, color: Colors.white38, size: 16),
                    ),
                  ],
                ),
              ),
              const Divider(color: Colors.white12, height: 14),
              if (_stats == null)
                const Padding(
                  padding: EdgeInsets.all(16),
                  child: Center(child: CircularProgressIndicator(color: Colors.orange, strokeWidth: 2)),
                )
              else
                Padding(
                  padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
                  child: Column(
                    children: [
                      // Live duration
                      _Row(icon: '🔴', label: 'Duration', value: _duration, color: Colors.red),
                      // Viewers
                      _Row(icon: '👁', label: 'Viewers', value: _fmt(_stats!.viewerCount), color: Colors.blue[300]!),
                      _Row(icon: '📈', label: 'Peak', value: _fmt(_stats!.peakViewers), color: Colors.blue[200]!),
                      // Engagement
                      _Row(icon: '❤️', label: 'Likes', value: _fmt(_stats!.likes + widget.totalLikes), color: Colors.pink),
                      _Row(icon: '💬', label: 'Messages', value: _fmt(_stats!.messages), color: Colors.purple[300]!),
                      // Revenue
                      _Row(icon: '🪙', label: 'Coins', value: _fmt(_stats!.coinsEarned), color: Colors.orange),
                      _Row(icon: '🎁', label: 'Gifts', value: _fmt(_stats!.giftsReceived), color: Colors.yellow[700]!),
                      // Growth
                      _Row(icon: '➕', label: 'New followers', value: '+${_stats!.newFollowers}', color: Colors.green),
                      const SizedBox(height: 6),
                      // Settings quick-view
                      if (_stats!.settings.slowMode)
                        _Badge('⏱ Slow mode ${_stats!.settings.slowModeSeconds}s', Colors.orange),
                      if (_stats!.settings.followersOnly)
                        _Badge('👥 Followers only', Colors.blue),
                      if (_stats!.settings.commentsDisabled)
                        _Badge('🔇 Comments off', Colors.red),
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

class _Row extends StatelessWidget {
  final String icon;
  final String label;
  final String value;
  final Color color;

  const _Row({required this.icon, required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Text(icon, style: const TextStyle(fontSize: 12)),
          const SizedBox(width: 6),
          Text(label, style: const TextStyle(color: Colors.white54, fontSize: 11)),
          const Spacer(),
          Text(value,
              style: TextStyle(
                  color: color, fontSize: 12, fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}

class _Badge extends StatelessWidget {
  final String text;
  final Color color;
  const _Badge(this.text, this.color);

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 4),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: color.withValues(alpha: 0.4)),
      ),
      child: Text(text, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold)),
    );
  }
}
