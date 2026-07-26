import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api/module_api_service.dart';

// ── Points balance + Tier (auto-refreshed) ────────────────────────────────────

class RewardsState {
  final int balance;
  final int totalEarned;
  final int totalRedeemed;
  final double dollarValue;
  final int pointsToDollar;
  final bool enabled;
  final bool loading;

  // Tier fields
  final String tier;
  final int totalPtsEarned;
  final int earnBonusPct;
  final int redeemExtraPct;
  final String? nextTier;
  final int ptsToNextTier;
  final int nextThreshold;
  final List<Map<String, dynamic>> tiers;

  const RewardsState({
    this.balance = 0,
    this.totalEarned = 0,
    this.totalRedeemed = 0,
    this.dollarValue = 0,
    this.pointsToDollar = 100,
    this.enabled = true,
    this.loading = true,
    this.tier = 'bronze',
    this.totalPtsEarned = 0,
    this.earnBonusPct = 0,
    this.redeemExtraPct = 0,
    this.nextTier,
    this.ptsToNextTier = 0,
    this.nextThreshold = 0,
    this.tiers = const [],
  });

  RewardsState copyWith({
    int? balance, int? totalEarned, int? totalRedeemed,
    double? dollarValue, int? pointsToDollar, bool? enabled, bool? loading,
    String? tier, int? totalPtsEarned, int? earnBonusPct, int? redeemExtraPct,
    String? nextTier, int? ptsToNextTier, int? nextThreshold, List<Map<String, dynamic>>? tiers,
  }) => RewardsState(
    balance:        balance ?? this.balance,
    totalEarned:    totalEarned ?? this.totalEarned,
    totalRedeemed:  totalRedeemed ?? this.totalRedeemed,
    dollarValue:    dollarValue ?? this.dollarValue,
    pointsToDollar: pointsToDollar ?? this.pointsToDollar,
    enabled:        enabled ?? this.enabled,
    loading:        loading ?? this.loading,
    tier:           tier ?? this.tier,
    totalPtsEarned: totalPtsEarned ?? this.totalPtsEarned,
    earnBonusPct:   earnBonusPct ?? this.earnBonusPct,
    redeemExtraPct: redeemExtraPct ?? this.redeemExtraPct,
    nextTier:       nextTier ?? this.nextTier,
    ptsToNextTier:  ptsToNextTier ?? this.ptsToNextTier,
    nextThreshold:  nextThreshold ?? this.nextThreshold,
    tiers:          tiers ?? this.tiers,
  );
}

class RewardsNotifier extends AsyncNotifier<RewardsState> {
  final _svc = ModuleApiService.create();

  @override
  Future<RewardsState> build() => _fetch();

  Future<RewardsState> _fetch() async {
    try {
      final res = await _svc.getRewards();
      final d = res['data'] as Map<String, dynamic>;
      final rawTiers = (d['tiers'] as List?)?.cast<Map<String, dynamic>>() ?? [];
      return RewardsState(
        balance:        (d['balance'] as num).toInt(),
        totalEarned:    (d['total_earned'] as num).toInt(),
        totalRedeemed:  (d['total_redeemed'] as num).toInt(),
        dollarValue:    (d['dollar_value'] as num).toDouble(),
        pointsToDollar: (d['points_to_dollar'] as num).toInt(),
        enabled:        d['enabled'] as bool? ?? true,
        loading:        false,
        tier:           d['tier'] as String? ?? 'bronze',
        totalPtsEarned: (d['total_pts_earned'] as num?)?.toInt() ?? 0,
        earnBonusPct:   (d['earn_bonus_pct'] as num?)?.toInt() ?? 0,
        redeemExtraPct: (d['redeem_extra_pct'] as num?)?.toInt() ?? 0,
        nextTier:       d['next_tier'] as String?,
        ptsToNextTier:  (d['pts_to_next_tier'] as num?)?.toInt() ?? 0,
        nextThreshold:  (d['next_threshold'] as num?)?.toInt() ?? 0,
        tiers:          rawTiers,
      );
    } catch (_) {
      return const RewardsState(loading: false, enabled: true);
    }
  }

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    state = await AsyncValue.guard(() => _fetch());
  }

  void creditPoints(int pts) {
    state.whenData((s) {
      state = AsyncValue.data(s.copyWith(
        balance:       s.balance + pts,
        totalEarned:   s.totalEarned + pts,
        dollarValue:   (s.balance + pts) / s.pointsToDollar,
        totalPtsEarned: s.totalPtsEarned + pts,
      ));
    });
  }
}

final rewardsProvider = AsyncNotifierProvider<RewardsNotifier, RewardsState>(RewardsNotifier.new);
