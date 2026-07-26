import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/theme_x.dart';
import 'rewards_provider.dart';

/// Drop-in widget for any checkout screen.
/// Shows available points, lets user toggle how many to apply.
/// Calls [onChanged] with the points to redeem (0 = not using).
///
/// Usage:
///   RedeemPointsBar(
///     orderTotal: total,
///     onChanged: (pts, discount) => setState(() { _pointsToRedeem = pts; total -= discount; }),
///   )
class RedeemPointsBar extends ConsumerStatefulWidget {
  final double orderTotal;
  final void Function(int points, double discount) onChanged;

  const RedeemPointsBar({
    super.key,
    required this.orderTotal,
    required this.onChanged,
  });

  @override
  ConsumerState<RedeemPointsBar> createState() => _RedeemPointsBarState();
}

class _RedeemPointsBarState extends ConsumerState<RedeemPointsBar> {
  bool _applying = false;

  @override
  Widget build(BuildContext context) {
    final rewardsAsync = ref.watch(rewardsProvider);

    return rewardsAsync.when(
      loading: () => const SizedBox.shrink(),
      error: (_, __) => const SizedBox.shrink(),
      data: (rewards) {
        if (!rewards.enabled || rewards.balance <= 0) return const SizedBox.shrink();

        final maxDiscount = widget.orderTotal * 0.5; // max 50%
        final maxPoints   = (maxDiscount * rewards.pointsToDollar).ceil();
        final usable      = rewards.balance.clamp(0, maxPoints);
        final discount    = usable / rewards.pointsToDollar;

        return AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          margin: const EdgeInsets.only(bottom: 12),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: _applying
                  ? [const Color(0xFF1a237e).withValues(alpha: 0.08), AppColors.primary.withValues(alpha: 0.06)]
                  : [context.colors.surfaceBg, context.colors.surfaceBg],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: _applying ? AppColors.primary : AppColors.divider,
              width: _applying ? 1.5 : 1,
            ),
          ),
          child: Row(children: [
            // Star icon
            Container(
              width: 40, height: 40,
              decoration: BoxDecoration(
                color: _applying ? AppColors.primary : context.colors.cardBg,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(
                Icons.star_rounded,
                color: _applying ? Colors.white : const Color(0xFFF59E0B),
                size: 22,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(
                _applying
                    ? 'Using $usable pts → -\$${discount.toStringAsFixed(2)}'
                    : '${rewards.balance} pts available (\$${(rewards.balance / rewards.pointsToDollar).toStringAsFixed(2)})',
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                  color: _applying ? AppColors.primary : context.colors.navyText,
                ),
              ),
              if (!_applying)
                Text(
                  'Use up to $usable pts for \$${discount.toStringAsFixed(2)} off',
                  style: const TextStyle(fontSize: 11, color: AppColors.textGrey),
                ),
            ])),
            Switch(
              value: _applying,
              activeColor: AppColors.primary,
              onChanged: (val) {
                setState(() => _applying = val);
                widget.onChanged(val ? usable : 0, val ? discount : 0.0);
              },
            ),
          ]),
        );
      },
    );
  }
}

/// Simple read-only points badge (for order success screens)
class PointsEarnedBadge extends StatelessWidget {
  final int points;
  const PointsEarnedBadge({super.key, required this.points});

  @override
  Widget build(BuildContext context) {
    if (points <= 0) return const SizedBox.shrink();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF8E1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFF59E0B)),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.star_rounded, color: Color(0xFFF59E0B), size: 16),
        const SizedBox(width: 6),
        Text(
          '+$points eSahlan Points earned!',
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF92400E)),
        ),
      ]),
    );
  }
}
