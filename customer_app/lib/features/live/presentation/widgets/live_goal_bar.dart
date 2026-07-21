import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';
import '../../data/repositories/live_repository.dart';

class LiveGoalBar extends StatelessWidget {
  final LiveGoal goal;
  final VoidCallback? onTap;

  const LiveGoalBar({super.key, required this.goal, this.onTap});

  String _fmt(int n) => n >= 1000 ? '${(n / 1000).toStringAsFixed(1)}K' : '$n';

  @override
  Widget build(BuildContext context) {
    final done  = goal.status == 'completed';
    final color = done ? Colors.green : Colors.orange;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        decoration: BoxDecoration(
          color: Colors.black.withValues(alpha: 0.75),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: color.withValues(alpha: 0.5)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Text(goal.typeEmoji, style: const TextStyle(fontSize: 14)),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(goal.title,
                      style: const TextStyle(color: Colors.white, fontSize: 12,
                          fontWeight: FontWeight.w600),
                      overflow: TextOverflow.ellipsis),
                ),
                if (done)
                  const Text('✅', style: TextStyle(fontSize: 13))
                else
                  Text('${_fmt(goal.current)} / ${_fmt(goal.target)}',
                      style: TextStyle(color: color, fontSize: 11,
                          fontWeight: FontWeight.bold)),
              ],
            ),
            const SizedBox(height: 5),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: goal.percent / 100,
                minHeight: 5,
                backgroundColor: Colors.white12,
                valueColor: AlwaysStoppedAnimation(color),
              ),
            ),
            if (done) ...[
              const SizedBox(height: 3),
              const Text('🎉 Goal reached!',
                  style: TextStyle(color: Colors.green, fontSize: 10,
                      fontWeight: FontWeight.bold)),
            ],
          ],
        ),
      ),
    );
  }
}

// ── Host: Set Goal Sheet ──────────────────────────────────────────────────────

class SetGoalSheet extends StatefulWidget {
  final int roomId;
  final void Function(LiveGoal) onGoalSet;

  const SetGoalSheet({super.key, required this.roomId, required this.onGoalSet});

  @override
  State<SetGoalSheet> createState() => _SetGoalSheetState();
}

class _SetGoalSheetState extends State<SetGoalSheet> {
  String _type = 'coins';
  final _titleCtrl  = TextEditingController(text: 'Help me reach my goal!');
  final _targetCtrl = TextEditingController();
  bool _loading = false;
  final _repo = LiveRepository();

  static const _types = [
    ('coins',     '🪙', 'Coins'),
    ('gifts',     '🎁', 'Gifts'),
    ('likes',     '❤️', 'Likes'),
    ('followers', '👥', 'Follows'),
  ];

  @override
  void dispose() {
    _titleCtrl.dispose();
    _targetCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.only(
        left: 20, right: 20, top: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 24,
      ),
      decoration: const BoxDecoration(
        color: Color(0xFF1A1A2E),
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Center(child: Container(width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.white24,
                  borderRadius: BorderRadius.circular(2)))),
          const SizedBox(height: 16),
          const Text('🎯 Set Live Goal',
              style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.bold)),
          const SizedBox(height: 14),
          Row(
            children: _types.map((t) {
              final sel = _type == t.$1;
              return Expanded(
                child: GestureDetector(
                  onTap: () => setState(() => _type = t.$1),
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 3),
                    padding: const EdgeInsets.symmetric(vertical: 8),
                    decoration: BoxDecoration(
                      color: sel
                          ? Colors.orange.withValues(alpha: 0.25)
                          : Colors.white.withValues(alpha: 0.05),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: sel ? Colors.orange : Colors.white12),
                    ),
                    child: Column(children: [
                      Text(t.$2, style: const TextStyle(fontSize: 18)),
                      Text(t.$3, style: TextStyle(
                          color: sel ? Colors.orange : Colors.white38,
                          fontSize: 9)),
                    ]),
                  ),
                ),
              );
            }).toList(),
          ),
          const SizedBox(height: 12),
          _field(_titleCtrl, 'Goal title'),
          const SizedBox(height: 8),
          _field(_targetCtrl, 'Target number', numeric: true),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: _loading ? null : _submit,
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.orange, foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              child: _loading
                  ? const SizedBox(width: 20, height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Text('Set Goal', style: TextStyle(fontWeight: FontWeight.bold)),
            ),
          ),
        ],
      ),
    );
  }

  Widget _field(TextEditingController c, String hint, {bool numeric = false}) =>
      TextField(
        controller: c,
        keyboardType: numeric ? TextInputType.number : TextInputType.text,
        style: const TextStyle(color: Colors.white),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: const TextStyle(color: Colors.white38, fontSize: 13),
          filled: true,
          fillColor: Colors.white.withValues(alpha: 0.07),
          border: OutlineInputBorder(
              borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
        ),
      );

  Future<void> _submit() async {
    final title  = _titleCtrl.text.trim();
    final target = int.tryParse(_targetCtrl.text.trim()) ?? 0;
    if (title.isEmpty || target <= 0) return;
    setState(() => _loading = true);
    try {
      final goal = await _repo.setGoal(widget.roomId, _type, title, target);
      if (mounted) { Navigator.pop(context); widget.onGoalSet(goal); }
    } catch (_) { setState(() => _loading = false); }
  }
}
