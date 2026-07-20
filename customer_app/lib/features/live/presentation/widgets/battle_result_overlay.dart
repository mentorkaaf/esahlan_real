import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';

class BattleResultOverlay extends StatefulWidget {
  final LiveBattle battle;
  final VoidCallback onDismiss;

  const BattleResultOverlay({
    super.key,
    required this.battle,
    required this.onDismiss,
  });

  @override
  State<BattleResultOverlay> createState() => _BattleResultOverlayState();
}

class _BattleResultOverlayState extends State<BattleResultOverlay>
    with SingleTickerProviderStateMixin {
  late AnimationController _anim;
  late Animation<double> _fade;
  late Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _anim = AnimationController(vsync: this, duration: const Duration(milliseconds: 600));
    _fade  = CurvedAnimation(parent: _anim, curve: Curves.easeIn);
    _scale = Tween<double>(begin: 0.5, end: 1.0)
        .animate(CurvedAnimation(parent: _anim, curve: Curves.elasticOut));
    _anim.forward();
    // Auto-dismiss after 5s
    Future.delayed(const Duration(seconds: 5), () {
      if (mounted) widget.onDismiss();
    });
  }

  @override
  void dispose() {
    _anim.dispose();
    super.dispose();
  }

  String _formatScore(int s) =>
      s >= 1000 ? '${(s / 1000).toStringAsFixed(1)}K' : '$s';

  @override
  Widget build(BuildContext context) {
    final sorted = [...widget.battle.participants]..sort((a, b) => a.rank.compareTo(b.rank));
    final winner = widget.battle.winnerHostId != null
        ? sorted.where((p) => p.hostId == widget.battle.winnerHostId).firstOrNull
        : (sorted.isNotEmpty ? sorted.first : null);

    return FadeTransition(
      opacity: _fade,
      child: Container(
        color: Colors.black.withOpacity(0.85),
        child: Center(
          child: ScaleTransition(
            scale: _scale,
            child: Container(
              margin: const EdgeInsets.symmetric(horizontal: 32),
              padding: const EdgeInsets.all(28),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF1A1A2E), Color(0xFF0F3460)],
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                ),
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: Colors.amber.withOpacity(0.6), width: 2),
                boxShadow: [
                  BoxShadow(
                    color: Colors.amber.withOpacity(0.4),
                    blurRadius: 30,
                    spreadRadius: 4,
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Text('🏆', style: TextStyle(fontSize: 52)),
                  const SizedBox(height: 8),
                  const Text(
                    'Battle Over!',
                    style: TextStyle(
                      color: Colors.amber,
                      fontSize: 22,
                      fontWeight: FontWeight.bold,
                      letterSpacing: 1,
                    ),
                  ),
                  if (winner != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      '${winner.name} wins!',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                  const SizedBox(height: 24),
                  // Scores
                  ...sorted.map((p) => _ScoreRow(
                        participant: p,
                        isWinner: p.hostId == widget.battle.winnerHostId,
                        score: _formatScore(p.score),
                      )),
                  const SizedBox(height: 24),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: widget.onDismiss,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.amber,
                        foregroundColor: Colors.black,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12)),
                      ),
                      child: const Text('Continue',
                          style: TextStyle(fontWeight: FontWeight.bold)),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ScoreRow extends StatelessWidget {
  final BattleParticipant participant;
  final bool isWinner;
  final String score;

  const _ScoreRow({
    required this.participant,
    required this.isWinner,
    required this.score,
  });

  @override
  Widget build(BuildContext context) {
    final rankEmoji = participant.rank == 1
        ? '🥇'
        : participant.rank == 2
            ? '🥈'
            : participant.rank == 3
                ? '🥉'
                : '${participant.rank}';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: BoxDecoration(
        color: isWinner
            ? Colors.amber.withOpacity(0.15)
            : Colors.white.withOpacity(0.05),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isWinner ? Colors.amber.withOpacity(0.5) : Colors.white12,
        ),
      ),
      child: Row(
        children: [
          Text(rankEmoji, style: const TextStyle(fontSize: 20)),
          const SizedBox(width: 10),
          CircleAvatar(
            radius: 18,
            backgroundImage: participant.avatar.isNotEmpty
                ? NetworkImage(participant.avatar)
                : null,
            backgroundColor: Colors.white24,
            child: participant.avatar.isEmpty
                ? Text(
                    participant.name.isNotEmpty
                        ? participant.name[0].toUpperCase()
                        : '?',
                    style: const TextStyle(color: Colors.white, fontSize: 12))
                : null,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  participant.name,
                  style: TextStyle(
                    color: isWinner ? Colors.amber : Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 13,
                  ),
                ),
                Text('@${participant.username}',
                    style: const TextStyle(color: Colors.white38, fontSize: 11)),
              ],
            ),
          ),
          Row(
            children: [
              const Text('🪙', style: TextStyle(fontSize: 12)),
              const SizedBox(width: 3),
              Text(
                score,
                style: TextStyle(
                  color: isWinner ? Colors.amber : Colors.white70,
                  fontWeight: FontWeight.bold,
                  fontSize: 14,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
