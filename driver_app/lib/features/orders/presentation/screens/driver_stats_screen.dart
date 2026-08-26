import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../../core/theme/driver_colors.dart';

final _statsProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  return ref.read(authRepoProvider).getStats();
});

class DriverStatsScreen extends ConsumerWidget {
  const DriverStatsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.dc;
    final async = ref.watch(_statsProvider);
    return Scaffold(
      backgroundColor: c.navy,
      appBar: AppBar(
        backgroundColor: c.navyLight,
        title: Text('My Performance', style: TextStyle(fontWeight: FontWeight.w800, color: c.text)),
        actions: [
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: () => ref.invalidate(_statsProvider)),
        ],
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
        error: (e, _) => Center(child: Text('Error: $e', style: TextStyle(color: c.textMuted))),
        data: (data) => _StatsBody(data: data),
      ),
    );
  }
}

class _StatsBody extends StatelessWidget {
  final Map<String, dynamic> data;
  const _StatsBody({required this.data});

  static const _tierColors = {
    'bronze': Color(0xFFCD7F32),
    'silver': Color(0xFFC0C0C0),
    'gold': Color(0xFFFFD700),
    'diamond': Color(0xFF00BFFF),
  };
  static const _tierIcons = {
    'bronze': Icons.shield_rounded,
    'silver': Icons.workspace_premium_rounded,
    'gold': Icons.star_rounded,
    'diamond': Icons.diamond_rounded,
  };

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    final tier = data['tier']?.toString() ?? 'bronze';
    final tierColor = _tierColors[tier] ?? DC.orange;
    final tierIcon = _tierIcons[tier] ?? Icons.shield_rounded;
    final tierNext = data['tier_next'] as Map<String, dynamic>?;
    final ar = (data['acceptance_rate'] as num?)?.toDouble() ?? 100.0;
    final cr = (data['completion_rate'] as num?)?.toDouble() ?? 100.0;
    final rating = (data['rating'] as num?)?.toDouble() ?? 5.0;
    final totalDeliveries = (data['total_deliveries'] as num?)?.toInt() ?? 0;

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(children: [
        // Tier card
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: LinearGradient(colors: [tierColor.withValues(alpha: 0.2), tierColor.withValues(alpha: 0.05)]),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: tierColor.withValues(alpha: 0.4)),
          ),
          child: Column(children: [
            Icon(tierIcon, color: tierColor, size: 56),
            const SizedBox(height: 8),
            Text(tier.toUpperCase(), style: TextStyle(color: tierColor, fontWeight: FontWeight.w900, fontSize: 24, letterSpacing: 2)),
            Text('Driver Tier', style: TextStyle(color: c.textMuted, fontSize: 13)),
            const SizedBox(height: 12),
            Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
              _TierStat('$totalDeliveries', 'Deliveries', c),
              _TierStat(rating.toStringAsFixed(1), 'Rating', c),
              _TierStat('${ar.toInt()}%', 'Acceptance', c),
            ]),
          ]),
        ),
        const SizedBox(height: 16),

        // Next tier progress
        if (tierNext != null) Container(
          width: double.infinity,
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(16), border: Border.all(color: c.border.withValues(alpha: 0.3))),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Path to ${tierNext['name']}', style: TextStyle(color: c.text, fontWeight: FontWeight.w700, fontSize: 15)),
            const SizedBox(height: 12),
            _NextTierRow('Deliveries', totalDeliveries, tierNext['deliveries'] as int, c),
            const SizedBox(height: 8),
            _NextTierRow('Rating', (rating * 10).toInt(), ((tierNext['rating'] as num) * 10).toInt(), c, suffix: '×0.1'),
            const SizedBox(height: 8),
            _NextTierRow('Acceptance %', ar.toInt(), tierNext['acceptance'] as int, c),
          ]),
        ),
        const SizedBox(height: 16),

        // Rate cards
        Row(children: [
          Expanded(child: _RateCard('Acceptance Rate', ar, DC.orange)),
          const SizedBox(width: 12),
          Expanded(child: _RateCard('Completion Rate', cr, DC.success)),
        ]),
        const SizedBox(height: 16),

        // Count cards
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(16), border: Border.all(color: c.border.withValues(alpha: 0.3))),
          child: Row(children: [
            _CountItem(Icons.check_circle_rounded, '${data['completed_count'] ?? 0}', 'Completed', DC.success),
            _CountItem(Icons.thumb_up_rounded, '${data['accepted_count'] ?? 0}', 'Accepted', DC.orange),
            _CountItem(Icons.cancel_rounded, '${data['rejected_count'] ?? 0}', 'Rejected', DC.error),
          ]),
        ),
      ]),
    );
  }
}

class _TierStat extends StatelessWidget {
  final String value, label;
  final dynamic c;
  const _TierStat(this.value, this.label, this.c);
  @override
  Widget build(BuildContext context) => Column(children: [
    Text(value, style: TextStyle(color: c.text, fontWeight: FontWeight.w800, fontSize: 18)),
    Text(label, style: TextStyle(color: c.textMuted, fontSize: 11)),
  ]);
}

class _NextTierRow extends StatelessWidget {
  final String label;
  final int current, target;
  final dynamic c;
  final String? suffix;
  const _NextTierRow(this.label, this.current, this.target, this.c, {this.suffix});
  @override
  Widget build(BuildContext context) {
    final done = current >= target;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Icon(done ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
          size: 14, color: done ? DC.success : c.textMuted),
        const SizedBox(width: 6),
        Text(label, style: TextStyle(color: c.textSec, fontSize: 12)),
        const Spacer(),
        Text('$current / $target${suffix ?? ''}', style: TextStyle(color: done ? DC.success : c.textMuted, fontSize: 12, fontWeight: FontWeight.w600)),
      ]),
      const SizedBox(height: 4),
      ClipRRect(borderRadius: BorderRadius.circular(3), child: LinearProgressIndicator(
        value: (current / target).clamp(0.0, 1.0),
        backgroundColor: c.border,
        color: done ? DC.success : DC.orange,
        minHeight: 4,
      )),
    ]);
  }
}

class _RateCard extends StatelessWidget {
  final String label;
  final double value;
  final Color color;
  const _RateCard(this.label, this.value, this.color);
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(16), border: Border.all(color: c.border.withValues(alpha: 0.3))),
      child: Column(children: [
        Text('${value.toInt()}%', style: TextStyle(color: color, fontWeight: FontWeight.w900, fontSize: 28)),
        const SizedBox(height: 4),
        Text(label, style: TextStyle(color: c.textMuted, fontSize: 12), textAlign: TextAlign.center),
        const SizedBox(height: 10),
        ClipRRect(borderRadius: BorderRadius.circular(4), child: LinearProgressIndicator(
          value: value / 100,
          backgroundColor: c.border,
          color: color,
          minHeight: 8,
        )),
      ]),
    );
  }
}

class _CountItem extends StatelessWidget {
  final IconData icon;
  final String value, label;
  final Color color;
  const _CountItem(this.icon, this.value, this.label, this.color);
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Expanded(child: Column(children: [
      Icon(icon, color: color, size: 24),
      const SizedBox(height: 4),
      Text(value, style: TextStyle(color: c.text, fontWeight: FontWeight.w800, fontSize: 20)),
      Text(label, style: TextStyle(color: c.textMuted, fontSize: 11)),
    ]));
  }
}
