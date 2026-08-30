import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/driver_colors.dart';
import '../../../../core/services/location_service.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
// driverNameProvider imported via auth_provider
import '../../../orders/presentation/screens/incoming_order_screen.dart';

final _dashProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) {
  return ref.read(authRepoProvider).dashboard();
});

final _bonusProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) {
  return ref.read(authRepoProvider).bonusStatus();
});

final _nameProvider = FutureProvider.autoDispose<String>((ref) async {
  return ref.watch(driverNameProvider).valueOrNull ?? 'Driver';
});

// ──────────────────────────────────────────────────────────────────────────────
class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});
  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen>
    with TickerProviderStateMixin {
  late AnimationController _pulseCtrl;
  late Animation<double> _pulseScale;

  @override
  void initState() {
    super.initState();
    _pulseCtrl = AnimationController(
        vsync: this, duration: const Duration(seconds: 2))
      ..repeat(reverse: true);
    _pulseScale = Tween<double>(begin: 1.0, end: 1.06).animate(
        CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeInOut));
  }

  @override
  void dispose() {
    _pulseCtrl.dispose();
    super.dispose();
  }

  static String _fmt(dynamic v) {
    final d = double.tryParse('${v ?? 0}') ?? 0;
    return d.toStringAsFixed(0);
  }

  static String _fmtDec(dynamic v) {
    final d = double.tryParse('${v ?? 0}') ?? 0;
    return d.toStringAsFixed(2);
  }

  @override
  Widget build(BuildContext context) {
    final dash = ref.watch(_dashProvider);
    return Scaffold(
      backgroundColor: const Color(0xFF080D18),
      body: dash.when(
        loading: () => const Center(
            child: CircularProgressIndicator(color: DC.orange)),
        error: (e, _) => _ErrorState(
            error: '$e', onRetry: () => ref.invalidate(_dashProvider)),
        data: (d) => _Body(
          d: d,
          driverName: ref.watch(driverNameProvider).valueOrNull ?? 'Driver',
          bonus: ref.watch(_bonusProvider).valueOrNull ?? {'is_active': false},
          pulseCtrl: _pulseCtrl,
          pulseScale: _pulseScale,
          onRefresh: () async {
            ref.invalidate(_dashProvider);
            ref.invalidate(_bonusProvider);
          },
          onToggle: () async {
            HapticFeedback.mediumImpact();
            final goingOnline = d['is_online'] != true;
            await ref.read(authRepoProvider).toggleStatus();
            if (goingOnline && !DriverLocationService.isRunning) {
              await DriverLocationService.startTracking();
            }
            ref.invalidate(_dashProvider);
          },
        ),
      ),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
class _Body extends StatelessWidget {
  final Map<String, dynamic> d;
  final Map<String, dynamic> bonus;
  final String driverName;
  final AnimationController pulseCtrl;
  final Animation<double> pulseScale;
  final Future<void> Function() onRefresh;
  final VoidCallback onToggle;

  const _Body({
    required this.d,
    required this.bonus,
    required this.driverName,
    required this.pulseCtrl,
    required this.pulseScale,
    required this.onRefresh,
    required this.onToggle,
  });

  static String _fmt(dynamic v) =>
      (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(0);
  static String _fmtDec(dynamic v) =>
      (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);

  @override
  Widget build(BuildContext context) {
    final isOnline = d['is_online'] == true;
    final isTracking = DriverLocationService.isRunning;
    final todayEarnings = double.tryParse('${d['today_earnings'] ?? 0}') ?? 0;
    final weeklyEarnings = double.tryParse('${d['weekly_earnings'] ?? 0}') ?? 0;
    final todayOrders = (d['today_orders'] as num?)?.toInt() ?? 0;
    final completedToday = (d['completed_today'] as num?)?.toInt() ?? 0;
    final rating = double.tryParse('${d['rating'] ?? 5.0}') ?? 5.0;
    final walletBal = double.tryParse('${d['wallet_balance'] ?? 0}') ?? 0;
    final activeOrder = d['active_order'];

    return RefreshIndicator(
      color: DC.orange,
      backgroundColor: const Color(0xFF12233D),
      onRefresh: onRefresh,
      child: CustomScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          // ── HEADER ────────────────────────────────────────────────────────
          SliverToBoxAdapter(
            child: _Header(
              isOnline: isOnline,
              name: driverName,
              walletBal: walletBal,
            ),
          ),

          // ── EARNINGS HERO ─────────────────────────────────────────────────
          SliverToBoxAdapter(
            child: _EarningsHero(
              todayEarnings: todayEarnings,
              weeklyEarnings: weeklyEarnings,
              completedToday: completedToday,
              todayOrders: todayOrders,
            ),
          ),

          // ── PEAK BONUS BANNER ─────────────────────────────────────────────
          if (bonus['is_active'] == true)
            SliverToBoxAdapter(
              child: _PeakBonusBanner(bonus: bonus),
            ),

          // ── ONLINE TOGGLE ─────────────────────────────────────────────────
          SliverToBoxAdapter(
            child: _OnlineToggle(
              isOnline: isOnline,
              isTracking: isTracking,
              pulseScale: pulseScale,
              onToggle: onToggle,
            ),
          ),

          // ── ACTIVE ORDER ───────────────────────────────────────────────────
          if (activeOrder != null)
            SliverToBoxAdapter(
              child: _ActiveOrderCard(
                  order: activeOrder,
                  onTap: () => context.go('/orders')),
            ),

          // ── STATS ROW ─────────────────────────────────────────────────────
          SliverToBoxAdapter(
            child: _StatsRow(
              orders: todayOrders,
              completed: completedToday,
              rating: rating,
              wallet: walletBal,
            ),
          ),

          // ── QUICK ACTIONS ─────────────────────────────────────────────────
          SliverToBoxAdapter(
            child: _QuickActions(weekEarnings: weeklyEarnings),
          ),

          // ── TEST ORDER BUTTON (DEV) ────────────────────────────────────────
          SliverToBoxAdapter(child: _TestOrderButton()),

          const SliverToBoxAdapter(child: SizedBox(height: 32)),
        ],
      ),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// HEADER
// ──────────────────────────────────────────────────────────────────────────────
class _Header extends StatelessWidget {
  final bool isOnline;
  final String name;
  final double walletBal;
  const _Header(
      {required this.isOnline,
      required this.name,
      required this.walletBal});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: EdgeInsets.fromLTRB(
          20, MediaQuery.of(context).padding.top + 16, 20, 16),
      child: Row(children: [
        // Avatar with online ring
        Stack(children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: const LinearGradient(
                  colors: [Color(0xFFFF6B00), Color(0xFFFF9A00)]),
            ),
            child: const Center(
              child: Icon(Icons.person_rounded, color: Colors.white, size: 26),
            ),
          ),
          Positioned(
            right: 2,
            bottom: 2,
            child: Container(
              width: 12,
              height: 12,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: isOnline ? DC.success : Colors.grey,
                border: Border.all(color: const Color(0xFF080D18), width: 2),
              ),
            ),
          ),
        ]),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Good day 👋',
                style: TextStyle(color: Colors.white54, fontSize: 12)),
            Text(name,
                style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 16)),
          ]),
        ),
        // Wallet chip
        GestureDetector(
          onTap: () => context.go('/wallet'),
          child: Container(
            padding:
                const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFF1A2A4A),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: const Color(0xFF2A3A5C)),
            ),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.account_balance_wallet_rounded,
                  color: DC.orange, size: 16),
              const SizedBox(width: 6),
              Text(
                '\$ ${walletBal.toStringAsFixed(0)}',
                style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                    fontSize: 13),
              ),
            ]),
          ),
        ),
      ]),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// EARNINGS HERO
// ──────────────────────────────────────────────────────────────────────────────
class _EarningsHero extends StatelessWidget {
  final double todayEarnings;
  final double weeklyEarnings;
  final int completedToday;
  final int todayOrders;
  const _EarningsHero(
      {required this.todayEarnings,
      required this.weeklyEarnings,
      required this.completedToday,
      required this.todayOrders});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF1A1200), Color(0xFF2D1F00), Color(0xFF1A0A00)],
        ),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(
            color: DC.orange.withValues(alpha: 0.25), width: 1.5),
        boxShadow: [
          BoxShadow(
              color: DC.orange.withValues(alpha: 0.08),
              blurRadius: 24,
              spreadRadius: 2),
        ],
      ),
      child: Column(children: [
        Row(children: [
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text(
                "TODAY'S EARNINGS",
                style: TextStyle(
                    color: Colors.white38,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.2),
              ),
              const SizedBox(height: 6),
              FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerLeft,
                child: Text(
                  '\$ ${todayEarnings.toStringAsFixed(0)}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    fontSize: 42,
                    height: 1,
                  ),
                ),
              ),
              const SizedBox(height: 6),
              Text(
                'This week: \$ ${weeklyEarnings.toStringAsFixed(0)}',
                style: const TextStyle(
                    color: Colors.white38, fontSize: 12),
              ),
            ]),
          ),
          const SizedBox(width: 16),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            _HeroStat(
              label: 'Deliveries',
              value: '$completedToday',
              icon: Icons.check_circle_rounded,
              color: DC.success,
            ),
            const SizedBox(height: 12),
            _HeroStat(
              label: 'Assigned',
              value: '$todayOrders',
              icon: Icons.assignment_rounded,
              color: DC.orange,
            ),
          ]),
        ]),

        // ── Mini bar chart ──
        const SizedBox(height: 20),
        Container(height: 1, color: Colors.white.withValues(alpha: 0.06)),
        const SizedBox(height: 16),
        Row(children: [
          const Icon(Icons.trending_up_rounded, color: DC.success, size: 16),
          const SizedBox(width: 6),
          Text(
            weeklyEarnings > 0
                ? '+${((todayEarnings / (weeklyEarnings / 7)) * 100).toStringAsFixed(0)}% vs daily avg'
                : 'Start delivering to see your stats',
            style:
                const TextStyle(color: DC.success, fontSize: 12, fontWeight: FontWeight.w600),
          ),
        ]),
      ]),
    );
  }
}

class _HeroStat extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  final Color color;
  const _HeroStat(
      {required this.label,
      required this.value,
      required this.icon,
      required this.color});

  @override
  Widget build(BuildContext context) {
    return Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, color: color, size: 14),
      const SizedBox(width: 4),
      Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
        Text(value,
            style: TextStyle(
                color: color,
                fontWeight: FontWeight.w900,
                fontSize: 20)),
        Text(label,
            style: const TextStyle(
                color: Colors.white38, fontSize: 10)),
      ]),
    ]);
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// ONLINE TOGGLE — bold, DoorDash-style
// ──────────────────────────────────────────────────────────────────────────────
class _OnlineToggle extends StatelessWidget {
  final bool isOnline;
  final bool isTracking;
  final Animation<double> pulseScale;
  final VoidCallback onToggle;
  const _OnlineToggle(
      {required this.isOnline,
      required this.isTracking,
      required this.pulseScale,
      required this.onToggle});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: GestureDetector(
        onTap: onToggle,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 500),
          curve: Curves.easeOutCubic,
          height: 88,
          decoration: BoxDecoration(
            gradient: isOnline
                ? const LinearGradient(
                    colors: [Color(0xFF052E16), Color(0xFF064E3B)])
                : const LinearGradient(
                    colors: [Color(0xFF111827), Color(0xFF1F2937)]),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(
              color: isOnline
                  ? DC.success.withValues(alpha: 0.5)
                  : Colors.white.withValues(alpha: 0.08),
              width: 1.5,
            ),
            boxShadow: isOnline
                ? [
                    BoxShadow(
                        color: DC.success.withValues(alpha: 0.2),
                        blurRadius: 20,
                        offset: const Offset(0, 4)),
                  ]
                : [],
          ),
          child: Row(children: [
            const SizedBox(width: 20),
            // Pulsing indicator
            ScaleTransition(
              scale: isOnline ? pulseScale : const AlwaysStoppedAnimation(1.0),
              child: Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: (isOnline ? DC.success : Colors.grey)
                      .withValues(alpha: 0.15),
                  border: Border.all(
                    color: isOnline ? DC.success : Colors.grey,
                    width: 2,
                  ),
                ),
                child: Icon(
                  isOnline
                      ? Icons.radio_button_checked_rounded
                      : Icons.radio_button_off_rounded,
                  color: isOnline ? DC.success : Colors.grey,
                  size: 24,
                ),
              ),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                Text(
                  isOnline ? 'You\'re Online' : 'You\'re Offline',
                  style: TextStyle(
                    color: isOnline ? DC.success : Colors.grey,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  isOnline
                      ? isTracking
                          ? '📡 GPS active · receiving orders'
                          : '⚡ Tap to start GPS tracking'
                      : 'Tap to go online and earn',
                  style: TextStyle(
                      color: (isOnline ? DC.success : Colors.grey)
                          .withValues(alpha: 0.6),
                      fontSize: 11),
                ),
              ]),
            ),
            // Toggle pill
            Padding(
              padding: const EdgeInsets.only(right: 20),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 300),
                width: 58,
                height: 34,
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: isOnline ? DC.success : Colors.grey.shade700,
                  borderRadius: BorderRadius.circular(17),
                ),
                child: AnimatedAlign(
                  duration: const Duration(milliseconds: 250),
                  curve: Curves.easeInOut,
                  alignment: isOnline
                      ? Alignment.centerRight
                      : Alignment.centerLeft,
                  child: Container(
                    width: 26,
                    height: 26,
                    decoration: const BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white,
                    ),
                  ),
                ),
              ),
            ),
          ]),
        ),
      ),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// ACTIVE ORDER CARD
// ──────────────────────────────────────────────────────────────────────────────
class _ActiveOrderCard extends StatelessWidget {
  final Map<String, dynamic> order;
  final VoidCallback onTap;
  const _ActiveOrderCard({required this.order, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final o = order;
    final delivery = (o['delivery'] as Map<String, dynamic>?) ?? {};
    final fee = double.tryParse('${o['delivery_fee'] ?? 0}') ?? 0;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [Color(0xFF1A1A2E), Color(0xFF16213E)],
          ),
          borderRadius: BorderRadius.circular(20),
          border:
              Border.all(color: DC.orange.withValues(alpha: 0.4), width: 1.5),
          boxShadow: [
            BoxShadow(
                color: DC.orange.withValues(alpha: 0.12),
                blurRadius: 16,
                offset: const Offset(0, 4)),
          ],
        ),
        child: Row(children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: DC.orangeDim,
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(Icons.delivery_dining_rounded,
                color: DC.orange, size: 26),
          ),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('ACTIVE ORDER',
                style: TextStyle(
                    color: DC.orange,
                    fontSize: 10,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.2)),
            const SizedBox(height: 3),
            Text(
              '#${o['order_number'] ?? ''}',
              style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 16),
            ),
            const SizedBox(height: 2),
            Text(
              '→ ${delivery['district'] ?? 'Destination'}',
              style: const TextStyle(color: Colors.white54, fontSize: 12),
            ),
          ])),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text(
              '\$ ${fee.toStringAsFixed(0)}',
              style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  fontSize: 20),
            ),
            const SizedBox(height: 4),
            Container(
              padding:
                  const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: DC.orange.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Row(mainAxisSize: MainAxisSize.min, children: [
                Text('Continue',
                    style: TextStyle(
                        color: DC.orange,
                        fontSize: 11,
                        fontWeight: FontWeight.w700)),
                SizedBox(width: 3),
                Icon(Icons.arrow_forward_ios_rounded,
                    color: DC.orange, size: 10),
              ]),
            ),
          ]),
        ]),
      ),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// STATS ROW
// ──────────────────────────────────────────────────────────────────────────────
class _StatsRow extends StatelessWidget {
  final int orders;
  final int completed;
  final double rating;
  final double wallet;
  const _StatsRow(
      {required this.orders,
      required this.completed,
      required this.rating,
      required this.wallet});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: Row(children: [
        _StatCard(
          icon: Icons.shopping_bag_rounded,
          label: 'Today',
          value: '$orders',
          subLabel: 'orders',
          color: DC.orange,
        ),
        const SizedBox(width: 10),
        _StatCard(
          icon: Icons.check_circle_rounded,
          label: 'Done',
          value: '$completed',
          subLabel: 'delivered',
          color: DC.success,
        ),
        const SizedBox(width: 10),
        _StatCard(
          icon: Icons.star_rounded,
          label: 'Rating',
          value: rating.toStringAsFixed(1),
          subLabel: '/ 5.0',
          color: const Color(0xFFF59E0B),
        ),
      ]),
    );
  }
}

class _StatCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final String subLabel;
  final Color color;
  const _StatCard(
      {required this.icon,
      required this.label,
      required this.value,
      required this.subLabel,
      required this.color});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 12),
        decoration: BoxDecoration(
          color: const Color(0xFF111827),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: Colors.white.withValues(alpha: 0.06)),
        ),
        child: Column(children: [
          Icon(icon, color: color, size: 22),
          const SizedBox(height: 8),
          Text(value,
              style: TextStyle(
                  color: color, fontWeight: FontWeight.w900, fontSize: 22)),
          Text(subLabel,
              style: const TextStyle(color: Colors.white38, fontSize: 10)),
        ]),
      ),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// QUICK ACTIONS
// ──────────────────────────────────────────────────────────────────────────────
class _QuickActions extends StatelessWidget {
  final double weekEarnings;
  const _QuickActions({required this.weekEarnings});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Padding(
          padding: EdgeInsets.only(bottom: 12),
          child: Text('Quick Actions',
              style: TextStyle(
                  color: Colors.white54,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0.8)),
        ),
        Row(children: [
          _ActionButton(
            icon: Icons.delivery_dining_rounded,
            label: 'My Orders',
            color: DC.orange,
            onTap: () => context.go('/orders'),
          ),
          const SizedBox(width: 10),
          _ActionButton(
            icon: Icons.bar_chart_rounded,
            label: 'Earnings',
            color: DC.success,
            onTap: () => context.go('/earnings'),
          ),
          const SizedBox(width: 10),
          _ActionButton(
            icon: Icons.emoji_events_rounded,
            label: 'Challenges',
            color: const Color(0xFFF59E0B),
            onTap: () => context.go('/challenges'),
          ),
          const SizedBox(width: 10),
          _ActionButton(
            icon: Icons.account_balance_wallet_rounded,
            label: 'Wallet',
            color: const Color(0xFF3B82F6),
            onTap: () => context.go('/wallet'),
          ),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          _ActionButton(
            icon: Icons.person_rounded,
            label: 'Profile',
            color: Colors.white38,
            onTap: () => context.go('/profile'),
          ),
          const SizedBox(width: 10),
          _ActionButton(
            icon: Icons.map_rounded,
            label: 'Heatmap',
            color: const Color(0xFFEC4899),
            onTap: () => context.go('/heatmap'),
          ),
          const SizedBox(width: 10),
          _ActionButton(
            icon: Icons.insert_chart_rounded,
            label: 'My Stats',
            color: Colors.white38,
            onTap: () => context.go('/driver-stats'),
          ),
          const SizedBox(width: 10),
          const Expanded(child: SizedBox()),
        ]),
      ]),
    );
  }
}

class _ActionButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  const _ActionButton(
      {required this.icon,
      required this.label,
      required this.color,
      required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            color: const Color(0xFF111827),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: Colors.white.withValues(alpha: 0.06)),
          ),
          child: Column(children: [
            Icon(icon, color: color, size: 22),
            const SizedBox(height: 6),
            Text(label,
                style: TextStyle(
                    color: color.withValues(alpha: 0.9),
                    fontSize: 10,
                    fontWeight: FontWeight.w700)),
          ]),
        ),
      ),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// TEST ORDER BUTTON — so you can see IncomingOrderScreen without real FCM
// ──────────────────────────────────────────────────────────────────────────────
class _TestOrderButton extends StatelessWidget {
  const _TestOrderButton();

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: GestureDetector(
        onTap: () {
          // Push fake order to demonstrate IncomingOrderScreen
          context.push('/incoming-order', extra: {
            'id': 9999,
            'order_number': 'TEST-001',
            'module_slug': 'efood',
            'delivery_fee': '8.50',
            'distance_km': '3.2',
            'estimated_minutes': 18,
            'driver_to_pickup_km': '1.1',
            'pickup': {
              'district': 'KM4, Mogadishu',
              'address': 'Banadir Restaurant',
              'lat': '2.0469',
              'lng': '45.3182',
            },
            'delivery': {
              'district': 'Hodan, Mogadishu',
              'address': 'Makka Al-Mukarama Rd',
              'lat': '2.0382',
              'lng': '45.3421',
            },
          });
        },
        child: Container(
          height: 56,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [
                Colors.white.withValues(alpha: 0.07),
                Colors.white.withValues(alpha: 0.04),
              ],
            ),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
                color: Colors.white.withValues(alpha: 0.12), width: 1),
          ),
          child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            Icon(Icons.science_rounded, color: Colors.white38, size: 18),
            SizedBox(width: 8),
            Text('Simulate Incoming Order',
                style: TextStyle(
                    color: Colors.white38,
                    fontWeight: FontWeight.w700,
                    fontSize: 13)),
            SizedBox(width: 6),
            Text('(test)',
                style: TextStyle(color: Colors.white24, fontSize: 11)),
          ]),
        ),
      ),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// ERROR STATE
// ──────────────────────────────────────────────────────────────────────────────
class _ErrorState extends StatelessWidget {
  final String error;
  final VoidCallback onRetry;
  const _ErrorState({required this.error, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: 72,
            height: 72,
            decoration: BoxDecoration(
              color: DC.error.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.wifi_off_rounded, color: DC.error, size: 34),
          ),
          const SizedBox(height: 20),
          const Text('Connection Error',
              style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 18)),
          const SizedBox(height: 8),
          Text(error,
              style:
                  const TextStyle(color: Colors.white38, fontSize: 13),
              textAlign: TextAlign.center),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: onRetry,
            style: ElevatedButton.styleFrom(
              backgroundColor: DC.orange,
              foregroundColor: Colors.white,
              padding:
                  const EdgeInsets.symmetric(horizontal: 32, vertical: 14),
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12)),
            ),
            child: const Text('Retry',
                style: TextStyle(fontWeight: FontWeight.w700)),
          ),
        ]),
      ),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// PEAK BONUS BANNER
// ──────────────────────────────────────────────────────────────────────────────
class _PeakBonusBanner extends StatelessWidget {
  final Map<String, dynamic> bonus;
  const _PeakBonusBanner({required this.bonus});

  @override
  Widget build(BuildContext context) {
    final amount = (bonus['bonus_amount'] as num?)?.toDouble() ?? 0;
    final label  = (bonus['label'] as String?)  ?? 'Peak Hours';
    final mins   = (bonus['ends_in_minutes'] as num?)?.toInt() ?? 0;
    final hoursLeft  = mins ~/ 60;
    final minsLeft   = mins % 60;
    final timeStr = hoursLeft > 0 ? '${hoursLeft}h ${minsLeft}m' : '${minsLeft}m';

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF7C3AED), Color(0xFF4F46E5)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(color: const Color(0xFF7C3AED).withValues(alpha: 0.35), blurRadius: 16, offset: const Offset(0, 6)),
        ],
      ),
      child: Row(children: [
        // Fire icon badge
        Container(
          width: 44, height: 44,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.15),
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Center(child: Text('🔥', style: TextStyle(fontSize: 22))),
        ),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14)),
          const SizedBox(height: 2),
          Text('+\$${amount.toStringAsFixed(2)} per delivery · ends in $timeStr',
              style: TextStyle(color: Colors.white.withValues(alpha: 0.80), fontSize: 12)),
        ])),
        const SizedBox(width: 8),
        // Bonus amount chip
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.20),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Text('+\$${amount.toStringAsFixed(2)}',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 15)),
        ),
      ]),
    );
  }
}
