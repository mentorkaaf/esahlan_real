import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/driver_colors.dart';
import '../../../../core/services/location_service.dart';
import '../../../auth/presentation/providers/auth_provider.dart';

final _dashProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) {
  return ref.read(authRepoProvider).dashboard();
});

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.dc;
    final dash = ref.watch(_dashProvider);

    return Scaffold(
      backgroundColor: c.navy,
      body: dash.when(
        loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
        error: (e, _) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.error_outline, color: DC.error, size: 48),
          const SizedBox(height: 12),
          Text('$e', style: TextStyle(color: c.textSec, fontSize: 13), textAlign: TextAlign.center),
          const SizedBox(height: 16),
          ElevatedButton(onPressed: () => ref.invalidate(_dashProvider), child: const Text('Retry')),
        ])),
        data: (d) => RefreshIndicator(
          color: DC.orange,
          onRefresh: () async => ref.invalidate(_dashProvider),
          child: CustomScrollView(slivers: [
            // ── App Bar ──────────────────────────────────────────
            SliverAppBar(
              floating: true, snap: true,
              backgroundColor: c.navyLight,
              title: Row(mainAxisSize: MainAxisSize.min, children: [
                Container(width: 36, height: 36,
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(colors: [Color(0xFFFF8A00), Color(0xFFFF6B00)]),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(Icons.delivery_dining_rounded, color: Colors.white, size: 20)),
                const SizedBox(width: 10),
                Text('eSahlan Driver', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: c.text)),
              ]),
              actions: [
                Container(
                  margin: const EdgeInsets.only(right: 12),
                  decoration: BoxDecoration(color: c.surface, shape: BoxShape.circle, border: Border.all(color: c.border)),
                  child: IconButton(icon: Icon(Icons.notifications_outlined, size: 20, color: c.textSec), onPressed: () {}),
                ),
              ],
            ),

            SliverToBoxAdapter(child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              child: Column(children: [
                // ── Online/Offline Toggle ──────────────────────────
                _OnlineCard(
                  isOnline: d['is_online'] == true,
                  isTracking: DriverLocationService.isRunning,
                  onToggle: () async {
                    final goingOnline = d['is_online'] != true;
                    await ref.read(authRepoProvider).toggleStatus();
                    if (goingOnline) {
                      await DriverLocationService.startTracking();
                    } else {
                      // Stay tracking even when offline so admin map stays live;
                      // only stop if driver explicitly wants to (hold button).
                      // For now just ensure it's running.
                      if (!DriverLocationService.isRunning) {
                        await DriverLocationService.startTracking();
                      }
                    }
                    ref.invalidate(_dashProvider);
                  },
                ),
                const SizedBox(height: 20),

                // ── Today's Overview ──────────────────────────────
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: c.card,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: c.border.withValues(alpha: 0.5)),
                  ),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Text("Today's Overview", style: TextStyle(color: c.text, fontSize: 16, fontWeight: FontWeight.w800)),
                      const Spacer(),
                      GestureDetector(
                        onTap: () => context.go('/orders'),
                        child: const Text('View All', style: TextStyle(color: DC.orange, fontSize: 12, fontWeight: FontWeight.w700)),
                      ),
                    ]),
                    const SizedBox(height: 18),
                    Row(children: [
                      _OverviewStat(icon: Icons.shopping_bag_rounded, value: '${d['today_orders'] ?? 0}', label: 'Orders', color: DC.orange),
                      _OverviewStat(icon: Icons.attach_money_rounded, value: '\$${_fmt(d['today_earnings'])}', label: 'Earnings', color: DC.success),
                      _OverviewStat(icon: Icons.check_circle_rounded, value: '${d['completed_today'] ?? 0}', label: 'Completed', color: const Color(0xFF3B82F6)),
                      _OverviewStat(icon: Icons.cancel_outlined, value: '0', label: 'Cancelled', color: DC.error),
                    ]),
                    const SizedBox(height: 18),
                    Divider(color: c.divider, height: 1),
                    const SizedBox(height: 14),
                    Row(children: [
                      _MiniStat('Cash in Hand', '\$${_fmt(0)}'),
                      _MiniStat('Wallet Balance', '\$${_fmt(d['wallet_balance'])}'),
                    ]),
                    const SizedBox(height: 10),
                    Row(children: [
                      _MiniStat('Rating', '${d['rating'] ?? 5.0} ⭐'),
                      _MiniStat("Today's Earnings", '+${_pct(d['today_earnings'], d['weekly_earnings'])}%'),
                    ]),
                  ]),
                ),
                const SizedBox(height: 16),

                // ── Wallet + Earnings Row ──────────────────────────
                Row(children: [
                  Expanded(child: _GlassCard(
                    icon: Icons.account_balance_wallet_rounded,
                    title: 'Wallet Balance',
                    value: '\$${_fmt(d['wallet_balance'])}',
                    gradient: const [Color(0xFF1A2A4A), Color(0xFF243355)],
                    onTap: () => context.go('/wallet'),
                  )),
                  const SizedBox(width: 12),
                  Expanded(child: _GlassCard(
                    icon: Icons.trending_up_rounded,
                    title: 'This Week',
                    value: '\$${_fmt(d['weekly_earnings'])}',
                    gradient: const [Color(0xFF064E3B), Color(0xFF065F46)],
                    onTap: () => context.go('/earnings'),
                  )),
                ]),
                const SizedBox(height: 20),

                // ── Active Order ──────────────────────────────────
                if (d['active_order'] != null) _ActiveOrderCard(order: d['active_order'], onTap: () => context.go('/orders')),
              ]),
            )),
          ]),
        ),
      ),
    );
  }

  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);
  static String _pct(dynamic a, dynamic b) {
    final av = double.tryParse('${a ?? 0}') ?? 0;
    final bv = double.tryParse('${b ?? 0}') ?? 0;
    if (bv == 0) return '0';
    return ((av / bv) * 100).toStringAsFixed(0);
  }
}

// ── Online/Offline Card ───────────────────────────────────────────────────────

class _OnlineCard extends StatelessWidget {
  final bool isOnline;
  final bool isTracking;
  final VoidCallback onToggle;
  const _OnlineCard({required this.isOnline, required this.isTracking, required this.onToggle});

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return GestureDetector(
      onTap: onToggle,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 400),
        curve: Curves.easeOutCubic,
        padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 18),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: isOnline
                ? [const Color(0xFF064E3B), const Color(0xFF065F46)]
                : [c.card, c.cardLight],
          ),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: isOnline ? DC.success.withValues(alpha: 0.4) : c.border,
            width: 1.5,
          ),
          boxShadow: isOnline ? [BoxShadow(color: DC.success.withValues(alpha: 0.15), blurRadius: 20)] : [],
        ),
        child: Column(children: [
          Row(children: [
            AnimatedContainer(
              duration: const Duration(milliseconds: 300),
              width: 52, height: 52,
              decoration: BoxDecoration(
                color: isOnline ? DC.success.withValues(alpha: 0.2) : c.textMuted.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: Icon(
                isOnline ? Icons.power_settings_new_rounded : Icons.power_off_rounded,
                color: isOnline ? DC.success : c.textMuted, size: 26,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(
                isOnline ? 'You are Online' : 'You are Offline',
                style: TextStyle(color: isOnline ? DC.success : c.textMuted, fontSize: 17, fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 2),
              Text(
                isOnline ? 'Receiving delivery requests' : 'Tap to go online and start earning',
                style: TextStyle(color: (isOnline ? DC.success : c.textMuted).withValues(alpha: 0.7), fontSize: 12),
              ),
            ])),
            AnimatedContainer(
              duration: const Duration(milliseconds: 250),
              width: 56, height: 32,
              decoration: BoxDecoration(
                color: isOnline ? DC.success : c.textMuted.withValues(alpha: 0.3),
                borderRadius: BorderRadius.circular(16),
              ),
              padding: const EdgeInsets.all(3),
              child: AnimatedAlign(
                duration: const Duration(milliseconds: 250),
                curve: Curves.easeInOut,
                alignment: isOnline ? Alignment.centerRight : Alignment.centerLeft,
                child: Container(width: 26, height: 26,
                  decoration: BoxDecoration(color: Colors.white, shape: BoxShape.circle,
                    boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 4)])),
              ),
            ),
          ]),
          // ── GPS tracking status strip ──
          const SizedBox(height: 12),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Row(children: [
              _PulsingDot(active: isTracking),
              const SizedBox(width: 8),
              Icon(Icons.gps_fixed_rounded, size: 13,
                color: isTracking ? DC.success : c.textMuted),
              const SizedBox(width: 5),
              Text(
                isTracking
                    ? 'GPS tracking active — sending every 5 s'
                    : 'GPS tracking inactive',
                style: TextStyle(
                  color: isTracking ? DC.success : c.textMuted,
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ]),
          ),
        ]),
      ),
    );
  }
}

/// A small pulsing dot used in the GPS status strip
class _PulsingDot extends StatefulWidget {
  final bool active;
  const _PulsingDot({required this.active});
  @override
  State<_PulsingDot> createState() => _PulsingDotState();
}

class _PulsingDotState extends State<_PulsingDot> with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl;
  late final Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))
      ..repeat(reverse: true);
    _scale = Tween<double>(begin: 0.6, end: 1.0).animate(
      CurvedAnimation(parent: _ctrl, curve: Curves.easeInOut));
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!widget.active) {
      return Container(width: 8, height: 8,
        decoration: BoxDecoration(color: Colors.grey.shade600, shape: BoxShape.circle));
    }
    return ScaleTransition(
      scale: _scale,
      child: Container(width: 8, height: 8,
        decoration: const BoxDecoration(color: DC.success, shape: BoxShape.circle)),
    );
  }
}

// ── Overview Stat ─────────────────────────────────────────────────────────────

class _OverviewStat extends StatelessWidget {
  final IconData icon; final String value, label; final Color color;
  const _OverviewStat({required this.icon, required this.value, required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Expanded(child: Column(children: [
      Container(width: 40, height: 40, decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)),
        child: Icon(icon, color: color, size: 20)),
      const SizedBox(height: 8),
      Text(value, style: TextStyle(color: c.text, fontSize: 16, fontWeight: FontWeight.w900)),
      const SizedBox(height: 2),
      Text(label, style: TextStyle(color: c.textMuted, fontSize: 10)),
    ]));
  }
}

class _MiniStat extends StatelessWidget {
  final String label, value;
  const _MiniStat(this.label, this.value);
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Expanded(child: Row(children: [
      Expanded(child: Text(label, style: TextStyle(color: c.textMuted, fontSize: 11))),
      Text(value, style: TextStyle(color: c.text, fontSize: 12, fontWeight: FontWeight.w700)),
    ]));
  }
}

// ── Glass Card ────────────────────────────────────────────────────────────────

class _GlassCard extends StatelessWidget {
  final IconData icon; final String title, value; final List<Color> gradient; final VoidCallback onTap;
  const _GlassCard({required this.icon, required this.title, required this.value, required this.gradient, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(colors: gradient, begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.white.withValues(alpha: 0.06)),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: DC.orange, size: 22),
        const SizedBox(height: 12),
        Text(title, style: const TextStyle(color: Color(0xFF8B9CC7), fontSize: 11)),
        const SizedBox(height: 4),
        Text(value, style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900)),
        const SizedBox(height: 4),
        const Row(children: [
          Icon(Icons.arrow_forward_rounded, color: DC.orange, size: 14),
          SizedBox(width: 4),
          Text('View', style: TextStyle(color: DC.orange, fontSize: 11, fontWeight: FontWeight.w600)),
        ]),
      ]),
    ),
  );
}

// ── Active Order Card ─────────────────────────────────────────────────────────

class _ActiveOrderCard extends StatelessWidget {
  final Map<String, dynamic> order;
  final VoidCallback onTap;
  const _ActiveOrderCard({required this.order, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    final vendor = order['vendor'] as Map<String, dynamic>?;
    final module = (order['module_slug'] ?? 'order').toString();

    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: c.card,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: DC.orange.withValues(alpha: 0.3), width: 1.5),
          boxShadow: [BoxShadow(color: DC.orange.withValues(alpha: 0.08), blurRadius: 16)],
        ),
        child: Row(children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(14)),
            child: const Icon(Icons.delivery_dining_rounded, color: DC.orange, size: 24)),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Active Delivery', style: TextStyle(color: c.text, fontSize: 14, fontWeight: FontWeight.w800)),
            const SizedBox(height: 2),
            Text(vendor?['name'] ?? module, style: TextStyle(color: c.textSec, fontSize: 12)),
          ])),
          Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
            decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
            child: const Text('In Progress', style: TextStyle(color: DC.success, fontSize: 11, fontWeight: FontWeight.w700))),
        ]),
      ),
    );
  }
}
