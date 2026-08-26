import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../../core/theme/driver_colors.dart';

final _challengesProvider = FutureProvider<List<dynamic>>((ref) async {
  return ref.read(authRepoProvider).getChallenges();
});

class ChallengesScreen extends ConsumerWidget {
  const ChallengesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.dc;
    final async = ref.watch(_challengesProvider);
    return Scaffold(
      backgroundColor: c.navy,
      appBar: AppBar(
        backgroundColor: c.navyLight,
        title: Text('Challenges', style: TextStyle(fontWeight: FontWeight.w800, color: c.text)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: () => ref.invalidate(_challengesProvider)),
        ],
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
        error: (e, _) => Center(child: Text('Error: $e', style: TextStyle(color: c.textMuted))),
        data: (list) => list.isEmpty
            ? Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                const Icon(Icons.emoji_events_rounded, size: 64, color: DC.orange),
                const SizedBox(height: 12),
                Text('No active challenges', style: TextStyle(color: c.textMuted, fontSize: 16)),
              ]))
            : ListView.separated(
                padding: const EdgeInsets.all(16),
                itemCount: list.length,
                separatorBuilder: (_, __) => const SizedBox(height: 12),
                itemBuilder: (_, i) => _ChallengeCard(data: list[i] as Map<String, dynamic>),
              ),
      ),
    );
  }
}

class _ChallengeCard extends StatelessWidget {
  final Map<String, dynamic> data;
  const _ChallengeCard({required this.data});

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    final pct = (data['pct'] as num?)?.toDouble() ?? 0;
    final completed = data['completed'] == true;
    final current = (data['current_count'] as num?)?.toInt() ?? 0;
    final target = (data['target_count'] as num?)?.toInt() ?? 1;
    final reward = (data['reward_amount'] as num?)?.toDouble() ?? 0;
    final endsAt = data['ends_at']?.toString();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: c.card,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: completed ? DC.success.withValues(alpha: 0.4) : c.border.withValues(alpha: 0.3)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: completed ? DC.success.withValues(alpha: 0.15) : DC.orange.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(completed ? Icons.check_circle_rounded : Icons.emoji_events_rounded,
              color: completed ? DC.success : DC.orange, size: 22),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(data['title']?.toString() ?? '', style: TextStyle(color: c.text, fontWeight: FontWeight.w700, fontSize: 15)),
            if (data['description'] != null)
              Text(data['description'].toString(), style: TextStyle(color: c.textMuted, fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
          ])),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text('\$$reward', style: const TextStyle(color: DC.orange, fontWeight: FontWeight.w800, fontSize: 18)),
            Text('reward', style: TextStyle(color: c.textMuted, fontSize: 10)),
          ]),
        ]),
        const SizedBox(height: 14),
        Row(children: [
          Text('$current / $target deliveries', style: TextStyle(color: c.textSec, fontSize: 12, fontWeight: FontWeight.w600)),
          const Spacer(),
          Text('${pct.toInt()}%', style: TextStyle(color: completed ? DC.success : DC.orange, fontWeight: FontWeight.w700)),
        ]),
        const SizedBox(height: 6),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: pct / 100,
            backgroundColor: c.border,
            color: completed ? DC.success : DC.orange,
            minHeight: 8,
          ),
        ),
        if (endsAt != null) ...[
          const SizedBox(height: 8),
          Row(children: [
            Icon(Icons.schedule_rounded, size: 12, color: c.textMuted),
            const SizedBox(width: 4),
            Text('Ends $endsAt', style: TextStyle(color: c.textMuted, fontSize: 11)),
          ]),
        ],
        if (completed) ...[
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.symmetric(vertical: 6, horizontal: 12),
            decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(8)),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.check_rounded, color: DC.success, size: 14),
              const SizedBox(width: 6),
              const Text('Completed! Reward paid.', style: TextStyle(color: DC.success, fontWeight: FontWeight.w700, fontSize: 12)),
            ]),
          ),
        ],
      ]),
    );
  }
}
