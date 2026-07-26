import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api/module_api_service.dart';

// ── Points balance (auto-refreshed) ──────────────────────────────────────────

class RewardsState {
  final int balance;
  final int totalEarned;
  final int totalRedeemed;
  final double dollarValue;
  final int pointsToDollar;
  final bool enabled;
  final bool loading;

  const RewardsState({
    this.balance = 0,
    this.totalEarned = 0,
    this.totalRedeemed = 0,
    this.dollarValue = 0,
    this.pointsToDollar = 100,
    this.enabled = true,
    this.loading = true,
  });

  RewardsState copyWith({int? balance, int? totalEarned, int? totalRedeemed,
      double? dollarValue, int? pointsToDollar, bool? enabled, bool? loading}) =>
      RewardsState(
        balance: balance ?? this.balance,
        totalEarned: totalEarned ?? this.totalEarned,
        totalRedeemed: totalRedeemed ?? this.totalRedeemed,
        dollarValue: dollarValue ?? this.dollarValue,
        pointsToDollar: pointsToDollar ?? this.pointsToDollar,
        enabled: enabled ?? this.enabled,
        loading: loading ?? this.loading,
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
      return RewardsState(
        balance:       (d['balance'] as num).toInt(),
        totalEarned:   (d['total_earned'] as num).toInt(),
        totalRedeemed: (d['total_redeemed'] as num).toInt(),
        dollarValue:   (d['dollar_value'] as num).toDouble(),
        pointsToDollar:(d['points_to_dollar'] as num).toInt(),
        enabled:       d['enabled'] as bool? ?? true,
        loading:       false,
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
        balance:     s.balance + pts,
        totalEarned: s.totalEarned + pts,
        dollarValue: (s.balance + pts) / s.pointsToDollar,
      ));
    });
  }
}

final rewardsProvider = AsyncNotifierProvider<RewardsNotifier, RewardsState>(RewardsNotifier.new);
