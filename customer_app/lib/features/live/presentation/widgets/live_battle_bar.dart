import 'dart:async';
import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';

class LiveBattleBar extends StatefulWidget {
  final LiveBattle battle;

  const LiveBattleBar({super.key, required this.battle});

  @override
  State<LiveBattleBar> createState() => _LiveBattleBarState();
}

class _LiveBattleBarState extends State<LiveBattleBar> {
  Timer? _ticker;
  int _remaining = 0;

  @override
  void initState() {
    super.initState();
    _updateRemaining();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(_updateRemaining);
    });
  }

  void _updateRemaining() {
    if (widget.battle.endsAt != null) {
      final diff = widget.battle.endsAt!.difference(DateTime.now()).inSeconds;
      _remaining = diff < 0 ? 0 : diff;
    } else {
      _remaining = widget.battle.durationSeconds;
    }
  }

  @override
  void didUpdateWidget(LiveBattleBar old) {
    super.didUpdateWidget(old);
    _updateRemaining();
  }

  @override
  void dispose() {
    _ticker?.cancel();
    super.dispose();
  }

  String get _timerStr {
    final m = (_remaining ~/ 60).toString().padLeft(2, '0');
    final s = (_remaining % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  String _formatScore(int coins) {
    if (coins >= 1000) return '${(coins / 1000).toStringAsFixed(1)}K';
    return '$coins';
  }

  @override
  Widget build(BuildContext context) {
    final participants = widget.battle.participants;
    if (participants.isEmpty) return const SizedBox.shrink();

    final total = widget.battle.totalScore;
    final p1 = participants[0];
    final p2 = participants.length > 1 ? participants[1] : null;

    // Proportional widths
    final p1Frac = total == 0 ? 0.5 : (p1.score / total).clamp(0.05, 0.95);

    return Container(
      height: 52,
      margin: const EdgeInsets.symmetric(horizontal: 12),
      decoration: BoxDecoration(
        color: Colors.black.withOpacity(0.75),
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: Colors.white12),
      ),
      child: Row(
        children: [
          const SizedBox(width: 8),
          // Left host avatar + score
          _HostChip(
            name: p1.name,
            avatar: p1.avatar,
            score: _formatScore(p1.score),
            color: const Color(0xFFFF6B35),
            rank: p1.rank,
            alignRight: false,
          ),
          // Score bar in center
          Expanded(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  '⚔️ $_timerStr',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 0.5,
                  ),
                ),
                const SizedBox(height: 4),
                ClipRRect(
                  borderRadius: BorderRadius.circular(3),
                  child: SizedBox(
                    height: 6,
                    child: LayoutBuilder(builder: (ctx, box) {
                      return Row(children: [
                        AnimatedContainer(
                          duration: const Duration(milliseconds: 400),
                          width: box.maxWidth * p1Frac,
                          color: const Color(0xFFFF6B35),
                        ),
                        Expanded(
                          child: Container(color: const Color(0xFF4FC3F7)),
                        ),
                      ]);
                    }),
                  ),
                ),
              ],
            ),
          ),
          // Right host avatar + score
          if (p2 != null)
            _HostChip(
              name: p2.name,
              avatar: p2.avatar,
              score: _formatScore(p2.score),
              color: const Color(0xFF4FC3F7),
              rank: p2.rank,
              alignRight: true,
            ),
          const SizedBox(width: 8),
        ],
      ),
    );
  }
}

class _HostChip extends StatelessWidget {
  final String name;
  final String avatar;
  final String score;
  final Color color;
  final int rank;
  final bool alignRight;

  const _HostChip({
    required this.name,
    required this.avatar,
    required this.score,
    required this.color,
    required this.rank,
    required this.alignRight,
  });

  @override
  Widget build(BuildContext context) {
    final medal = rank == 1 ? '👑' : '';
    final widgets = [
      CircleAvatar(
        radius: 14,
        backgroundColor: color.withOpacity(0.3),
        backgroundImage: avatar.isNotEmpty ? NetworkImage(avatar) : null,
        child: avatar.isEmpty
            ? Text(name.isNotEmpty ? name[0].toUpperCase() : '?',
                style: const TextStyle(fontSize: 10, color: Colors.white))
            : null,
      ),
      const SizedBox(width: 4),
      Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment:
            alignRight ? CrossAxisAlignment.end : CrossAxisAlignment.start,
        children: [
          Text(
            '$medal${name.length > 6 ? name.substring(0, 6) : name}',
            style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600),
          ),
          Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Text('🪙', style: TextStyle(fontSize: 9)),
              Text(score,
                  style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.bold)),
            ],
          ),
        ],
      ),
    ];

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: alignRight ? widgets.reversed.toList() : widgets,
    );
  }
}
