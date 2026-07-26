import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/theme/theme_x.dart';
import 'rewards_provider.dart';

// ── Tier config ───────────────────────────────────────────────────────────────

const _tierEmoji  = {'bronze': '🥉', 'silver': '🥈', 'gold': '🥇', 'platinum': '💎'};
const _tierColors = {
  'bronze':   Color(0xFFCD7F32),
  'silver':   Color(0xFFA0A0A0),
  'gold':     Color(0xFFFFD700),
  'platinum': Color(0xFF4FC3F7),
};

Color tierColor(String tier) => _tierColors[tier] ?? const Color(0xFFCD7F32);
String tierEmoji(String tier) => _tierEmoji[tier] ?? '🥉';

// ── Small inline badge (e.g. next to username) ────────────────────────────────

class TierBadge extends StatelessWidget {
  final String tier;
  final double size;
  const TierBadge({super.key, required this.tier, this.size = 13});

  @override
  Widget build(BuildContext context) {
    final color = tierColor(tier);
    return Container(
      padding: EdgeInsets.symmetric(horizontal: size * 0.6, vertical: size * 0.2),
      decoration: BoxDecoration(
        color: color.withAlpha(30),
        border: Border.all(color: color.withAlpha(80)),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(tierEmoji(tier), style: TextStyle(fontSize: size)),
          SizedBox(width: size * 0.3),
          Text(
            tier[0].toUpperCase() + tier.substring(1),
            style: TextStyle(fontSize: size, fontWeight: FontWeight.bold, color: color),
          ),
        ],
      ),
    );
  }
}

// ── Tier progress card (shown on wallet screen) ────────────────────────────────

class TierProgressCard extends ConsumerWidget {
  const TierProgressCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(rewardsProvider).valueOrNull;
    if (state == null || state.tier.isEmpty) return const SizedBox.shrink();

    final tier       = state.tier;
    final color      = tierColor(tier);
    final nextTier   = state.nextTier;
    final progress   = nextTier == null ? 1.0 :
        state.nextThreshold > 0
            ? (state.totalPtsEarned / state.nextThreshold).clamp(0.0, 1.0)
            : 1.0;

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [color.withAlpha(25), color.withAlpha(10)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        border: Border.all(color: color.withAlpha(60)),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              TierBadge(tier: tier, size: 14),
              const Spacer(),
              if (state.earnBonusPct > 0)
                _Chip('+${state.earnBonusPct}% earn', Colors.green),
              if (state.redeemExtraPct > 0) ...[
                const SizedBox(width: 6),
                _Chip('+${state.redeemExtraPct}% redeem', Colors.blue),
              ],
            ],
          ),
          if (nextTier != null) ...[
            const SizedBox(height: 10),
            Row(
              children: [
                Text(
                  '${_fmt(state.totalPtsEarned)} / ${_fmt(state.nextThreshold)} pts',
                  style: context.tt.bodySmall?.copyWith(color: color, fontWeight: FontWeight.w600),
                ),
                const Spacer(),
                Text(
                  '${_fmt(state.ptsToNextTier)} to ${tierEmoji(nextTier)} ${nextTier[0].toUpperCase()}${nextTier.substring(1)}',
                  style: context.tt.bodySmall?.copyWith(color: context.colors.mutedText),
                ),
              ],
            ),
            const SizedBox(height: 6),
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: LinearProgressIndicator(
                value: progress,
                minHeight: 6,
                backgroundColor: color.withAlpha(30),
                valueColor: AlwaysStoppedAnimation(color),
              ),
            ),
          ] else ...[
            const SizedBox(height: 6),
            Text(
              '💎 Maximum tier reached — you enjoy the best rewards!',
              style: context.tt.bodySmall?.copyWith(color: color, fontWeight: FontWeight.w500),
            ),
          ],
        ],
      ),
    );
  }

  String _fmt(int n) => n >= 1000 ? '${(n / 1000).toStringAsFixed(1)}K' : '$n';
}

class _Chip extends StatelessWidget {
  final String label;
  final Color color;
  const _Chip(this.label, this.color);

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
    decoration: BoxDecoration(
      color: color.withAlpha(20),
      border: Border.all(color: color.withAlpha(70)),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Text(label, style: TextStyle(fontSize: 10, color: color, fontWeight: FontWeight.bold)),
  );
}

// ── Full Tier Info Screen (tap from badge to see all tiers) ───────────────────

class TierInfoScreen extends ConsumerWidget {
  const TierInfoScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final state = ref.watch(rewardsProvider).valueOrNull;
    final tiers = state?.tiers ?? [];
    final currentTier = state?.tier ?? 'bronze';

    return Scaffold(
      appBar: AppBar(title: const Text('Loyalty Tiers'), centerTitle: true),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Hero
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [tierColor(currentTier), tierColor(currentTier).withAlpha(160)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(
                children: [
                  Text(tierEmoji(currentTier), style: const TextStyle(fontSize: 48)),
                  const SizedBox(height: 6),
                  Text(
                    'You are ${currentTier[0].toUpperCase()}${currentTier.substring(1)}',
                    style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.bold),
                  ),
                  Text(
                    '${state?.totalPtsEarned ?? 0} lifetime points earned',
                    style: const TextStyle(color: Colors.white70, fontSize: 13),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Tiers table
            Text('All Tiers', style: context.tt.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 10),
            ...tiers.map((t) {
              final key    = t['key'] as String? ?? 'bronze';
              final label  = t['label'] as String? ?? key;
              final minPts = t['min_pts'] as int? ?? 0;
              final bonus  = t['earn_bonus_pct'] as int? ?? 0;
              final extra  = t['redeem_extra'] as int? ?? 0;
              final isCurrent = key == currentTier;

              return Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: isCurrent
                      ? tierColor(key).withAlpha(20)
                      : context.colors.cardBg,
                  border: Border.all(
                    color: isCurrent ? tierColor(key) : Colors.transparent,
                    width: isCurrent ? 1.5 : 0,
                  ),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    Text(tierEmoji(key), style: const TextStyle(fontSize: 28)),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Text(label, style: context.tt.bodyMedium?.copyWith(
                                  fontWeight: FontWeight.bold, color: tierColor(key))),
                              if (isCurrent) ...[
                                const SizedBox(width: 6),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                  decoration: BoxDecoration(
                                    color: tierColor(key), borderRadius: BorderRadius.circular(10)),
                                  child: const Text('Current', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.bold)),
                                ),
                              ],
                            ],
                          ),
                          Text(
                            minPts == 0 ? 'Starting tier' : '${_fmt(minPts)}+ pts earned',
                            style: context.tt.bodySmall?.copyWith(color: context.colors.mutedText),
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        if (bonus > 0)
                          Text('+$bonus% earn', style: const TextStyle(fontSize: 11, color: Colors.green, fontWeight: FontWeight.bold)),
                        if (extra > 0)
                          Text('+$extra% redeem', style: const TextStyle(fontSize: 11, color: Colors.blue, fontWeight: FontWeight.bold)),
                        if (bonus == 0 && extra == 0)
                          Text('Base', style: TextStyle(fontSize: 11, color: context.colors.mutedText)),
                      ],
                    ),
                  ],
                ),
              );
            }),

            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: context.colors.cardBg,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('How tiers work', style: context.tt.bodySmall?.copyWith(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 6),
                  Text('• Tier is based on total lifetime points earned', style: context.tt.bodySmall),
                  Text('• Spending points never drops your tier', style: context.tt.bodySmall),
                  Text('• Higher tiers earn bonus points + unlock higher redeem cap', style: context.tt.bodySmall),
                  Text('• Upgrade is automatic when you cross a threshold', style: context.tt.bodySmall),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _fmt(int n) => n >= 1000 ? '${(n / 1000).toStringAsFixed(1)}K' : '$n';
}
