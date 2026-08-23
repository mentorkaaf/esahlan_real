import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shimmer/shimmer.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/utils/error_handler.dart';
import '../providers/order_provider.dart';
import '../../data/models/order_model.dart';
import '../../../../core/l10n/app_strings.dart';

// ── Module metadata ───────────────────────────────────────────────────────────
const _moduleInfo = {
  'efood':      {'icon': '🍔', 'color': 0xFFE53935, 'label': 'eFood'},
  'egrocery':   {'icon': '🛒', 'color': 0xFF43A047, 'label': 'eGrocery'},
  'eshop':      {'icon': '🛍️', 'color': 0xFF8E24AA, 'label': 'eShop'},
  'eparcel':    {'icon': '📦', 'color': 0xFFE65100, 'label': 'eParcel'},
  'emoving':    {'icon': '🚛', 'color': 0xFF0277BD, 'label': 'eMoving'},
  'erent':      {'icon': '🏠', 'color': 0xFF00695C, 'label': 'eRent'},
  'edata':      {'icon': '📡', 'color': 0xFF1565C0, 'label': 'eData'},
  'elaundry':   {'icon': '👔', 'color': 0xFF6A1B9A, 'label': 'eLaundry'},
  'elearning':  {'icon': '📚', 'color': 0xFF558B2F, 'label': 'eLearning'},
  'eexchange':  {'icon': '💱', 'color': 0xFF00838F, 'label': 'eExchange'},
};

Color _modColor(String? slug) =>
    Color(_moduleInfo[slug?.toLowerCase()]?['color'] as int? ?? 0xFF07003B);
String _modIcon(String? slug) =>
    _moduleInfo[slug?.toLowerCase()]?['icon'] as String? ?? '📋';
String _modLabel(String? slug) =>
    _moduleInfo[slug?.toLowerCase()]?['label'] as String? ?? (slug ?? 'Order');

// ── Main Screen ───────────────────────────────────────────────────────────────
class OrdersScreen extends ConsumerStatefulWidget {
  const OrdersScreen({super.key});
  @override
  ConsumerState<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends ConsumerState<OrdersScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  final _statusFilters = [null, 'pending', 'preparing', 'delivered', 'cancelled'];

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: _statusFilters.length, vsync: this);
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final tabLabels = [l.tabAll, l.tabPending, l.tabPreparing, l.tabDelivered, l.tabCancelled];
    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
      body: NestedScrollView(
        headerSliverBuilder: (ctx, inner) => [
          SliverAppBar(
            title: Text(l.myOrders,
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
            foregroundColor: context.colors.navyText,
            backgroundColor: context.colors.scaffoldBg,
            surfaceTintColor: Colors.transparent,
            elevation: 0,
            floating: true,
            snap: true,
            bottom: TabBar(
              controller: _tabs,
              isScrollable: true,
              tabAlignment: TabAlignment.start,
              labelColor: AppColors.primary,
              unselectedLabelColor: AppColors.textGrey,
              indicatorColor: AppColors.primary,
              indicatorSize: TabBarIndicatorSize.label,
              labelStyle:
                  const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
              tabs: tabLabels.map((t) => Tab(text: t)).toList(),
            ),
          ),
        ],
        body: TabBarView(
          controller: _tabs,
          children: _statusFilters
              .map((s) => _OrdersList(status: s, showAnalytics: s == null))
              .toList(),
        ),
      ),
    );
  }
}

// ── Orders List (with optional analytics header) ──────────────────────────────
class _OrdersList extends ConsumerWidget {
  final String? status;
  final bool showAnalytics;
  const _OrdersList({this.status, this.showAnalytics = false});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(ordersProvider(status));
    final analyticsAsync = ref.watch(orderAnalyticsProvider);
    final l = AppL10n.of(context);

    return ordersAsync.when(
      loading: () => _buildShimmer(context),
      error: (e, _) => _buildError(context, e),
      data: (orders) => RefreshIndicator(
        color: AppColors.primary,
        onRefresh: () async {
          ref.refresh(ordersProvider(status));
          if (showAnalytics) ref.refresh(orderAnalyticsProvider);
        },
        child: CustomScrollView(
          slivers: [
            // ── Analytics Dashboard (only on "All" tab) ──
            if (showAnalytics) ...[
              SliverToBoxAdapter(
                child: analyticsAsync.when(
                  loading: () => _AnalyticsShimmer(),
                  error: (e, _) => Padding(
                    padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                    child: GestureDetector(
                      onTap: () => ref.refresh(orderAnalyticsProvider),
                      child: Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF3E0),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFFF8A00).withValues(alpha: 0.3)),
                        ),
                        child: Row(children: [
                          const Icon(Icons.refresh_rounded, color: Color(0xFFFF8A00), size: 18),
                          const SizedBox(width: 8),
                          const Expanded(child: Text('Analytics loading failed — tap to retry', style: TextStyle(color: Color(0xFFE65100), fontSize: 12))),
                        ]),
                      ),
                    ),
                  ),
                  data: (data) => _AnalyticsDashboard(data: data),
                ),
              ),
            ],

            // ── Empty state ──
            if (orders.isEmpty)
              SliverFillRemaining(
                child: Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Text('📦', style: TextStyle(fontSize: 56)),
                      const SizedBox(height: 16),
                      Text(l.noOrdersYet,
                          style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              color: context.colors.navyText)),
                      const SizedBox(height: 6),
                      Text(l.ordersWillAppear,
                          style:
                              const TextStyle(color: AppColors.textGrey)),
                    ],
                  ),
                ),
              )
            else
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 24),
                sliver: SliverList.separated(
                  itemCount: orders.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (_, i) => _OrderCard(order: orders[i]),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildShimmer(BuildContext context) => ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: 4,
        separatorBuilder: (_, __) => const SizedBox(height: 12),
        itemBuilder: (_, __) => Shimmer.fromColors(
          baseColor: Colors.grey.shade200,
          highlightColor: Colors.grey.shade100,
          child: Container(
              height: 110,
              decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(14))),
        ),
      );

  Widget _buildError(BuildContext context, Object e) => Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.receipt_long_outlined,
                size: 56, color: AppColors.textLight),
            const SizedBox(height: 12),
            Text(AppErrorHandler.message(e),
                style: const TextStyle(color: AppColors.textGrey)),
          ],
        ),
      );
}

// ── Analytics Dashboard (compact single card) ────────────────────────────────
class _AnalyticsDashboard extends StatelessWidget {
  final Map<String, dynamic> data;
  const _AnalyticsDashboard({required this.data});

  @override
  Widget build(BuildContext context) {
    final total   = (data['total'] as num?)?.toInt() ?? 0;
    final today   = (data['today'] as num?)?.toInt() ?? 0;
    final week    = (data['this_week'] as num?)?.toInt() ?? 0;
    final month   = (data['this_month'] as num?)?.toInt() ?? 0;
    final year    = (data['this_year'] as num?)?.toInt() ?? 0;
    final spent   = (data['total_spent'] as num?)?.toDouble() ?? 0;
    final modules = (data['modules'] as List?) ?? [];
    final trend   = (data['trend'] as List?) ?? [];
    final maxTrend = trend.fold<int>(1, (m, t) => math.max(m, (t['count'] as int? ?? 0)));

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 14, 16, 6),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF07003B), Color(0xFF160B5C)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [BoxShadow(color: const Color(0xFF07003B).withValues(alpha: 0.3), blurRadius: 16, offset: const Offset(0, 6))],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Row 1: totals + mini bar chart ──────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                // Left: total orders + spent
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('My Orders', style: TextStyle(color: Colors.white.withValues(alpha: 0.5), fontSize: 10, fontWeight: FontWeight.w600, letterSpacing: 0.5)),
                    const SizedBox(height: 2),
                    Row(crossAxisAlignment: CrossAxisAlignment.baseline, textBaseline: TextBaseline.alphabetic, children: [
                      Text('$total', style: const TextStyle(color: Colors.white, fontSize: 30, fontWeight: FontWeight.w900, height: 1)),
                      const SizedBox(width: 6),
                      Text('orders', style: TextStyle(color: Colors.white.withValues(alpha: 0.45), fontSize: 11, fontWeight: FontWeight.w500)),
                      const SizedBox(width: 14),
                      Text('\$${spent.toStringAsFixed(2)}', style: const TextStyle(color: Color(0xFFFF8A00), fontSize: 16, fontWeight: FontWeight.w800)),
                    ]),
                  ]),
                ),
                // Right: mini sparkline (6 bars)
                if (trend.isNotEmpty)
                  SizedBox(
                    width: 72, height: 34,
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: trend.map((t) {
                        final c = (t['count'] as int? ?? 0);
                        final h = maxTrend > 0 ? (c / maxTrend).clamp(0.06, 1.0) : 0.06;
                        final isLast = t == trend.last;
                        return Expanded(child: Container(
                          margin: const EdgeInsets.symmetric(horizontal: 1.5),
                          height: 34 * h,
                          decoration: BoxDecoration(
                            color: isLast ? const Color(0xFFFF8A00) : Colors.white.withValues(alpha: 0.25),
                            borderRadius: BorderRadius.circular(3),
                          ),
                        ));
                      }).toList(),
                    ),
                  ),
              ],
            ),
          ),

          const SizedBox(height: 12),

          // ── Row 2: period pills ──────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(children: [
              _PeriodPill('Today', today),
              const SizedBox(width: 6),
              _PeriodPill('Week', week),
              const SizedBox(width: 6),
              _PeriodPill('Month', month),
              const SizedBox(width: 6),
              _PeriodPill('Year', year),
            ]),
          ),

          const SizedBox(height: 12),

          // ── Divider ──────────────────────────────────────────────────────
          Container(height: 1, color: Colors.white.withValues(alpha: 0.08), margin: const EdgeInsets.symmetric(horizontal: 16)),

          // ── Row 3: module chips (auto-scrolling marquee) ─────────────────
          if (modules.isNotEmpty)
            _ModuleMarquee(modules: modules)
          else
            const SizedBox(height: 12),
        ],
      ),
    );
  }
}

// ── Module auto-scrolling marquee ─────────────────────────────────────────────
class _ModuleMarquee extends StatefulWidget {
  final List modules;
  const _ModuleMarquee({required this.modules});
  @override
  State<_ModuleMarquee> createState() => _ModuleMarqueeState();
}

class _ModuleMarqueeState extends State<_ModuleMarquee>
    with SingleTickerProviderStateMixin {
  late final ScrollController _ctrl;
  late final AnimationController _anim;

  // Width of one chip + separator (estimated; marquee scrolls by pixel)
  static const double _speed = 35.0; // pixels per second

  @override
  void initState() {
    super.initState();
    _ctrl = ScrollController();
    // Use an animation controller purely as a ticker source
    _anim = AnimationController(vsync: this, duration: const Duration(seconds: 1))
      ..addListener(_tick)
      ..repeat();
  }

  DateTime? _last;

  void _tick() {
    if (!_ctrl.hasClients) return;
    final now = DateTime.now();
    final dt = _last == null ? 0.016 : now.difference(_last!).inMicroseconds / 1e6;
    _last = now;

    final max = _ctrl.position.maxScrollExtent;
    if (max <= 0) return;
    final next = _ctrl.offset + _speed * dt;
    if (next >= max) {
      // Jump back to start seamlessly (items are duplicated)
      _ctrl.jumpTo(next - max / 2);
    } else {
      _ctrl.jumpTo(next);
    }
  }

  @override
  void dispose() {
    _anim.dispose();
    _ctrl.dispose();
    super.dispose();
  }

  Widget _chip(Map m) {
    final slug  = m['slug'] as String? ?? '';
    final count = (m['count'] as num?)?.toInt() ?? 0;
    final color = _modColor(slug);
    return Container(
      margin: const EdgeInsets.only(right: 8),
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.35), width: 1),
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Text(_modIcon(slug), style: const TextStyle(fontSize: 12)),
        const SizedBox(width: 5),
        Text(_modLabel(slug),
            style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
        const SizedBox(width: 5),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
          decoration: BoxDecoration(
              color: color.withValues(alpha: 0.35), borderRadius: BorderRadius.circular(10)),
          child: Text('$count',
              style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800)),
        ),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    // Duplicate items so seamless loop works
    final items = [...widget.modules, ...widget.modules];
    return SizedBox(
      height: 48,
      child: ListView.builder(
        controller: _ctrl,
        scrollDirection: Axis.horizontal,
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        itemCount: items.length,
        itemBuilder: (_, i) => _chip(Map<String, dynamic>.from(items[i] as Map)),
      ),
    );
  }
}

class _PeriodPill extends StatelessWidget {
  final String label;
  final int value;
  const _PeriodPill(this.label, this.value);

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 5),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(8),
        ),
        child: Column(children: [
          Text('$value', style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w900, height: 1)),
          const SizedBox(height: 2),
          Text(label, style: TextStyle(color: Colors.white.withValues(alpha: 0.5), fontSize: 9, fontWeight: FontWeight.w600)),
        ]),
      ),
    );
  }
}

// ── Analytics shimmer ─────────────────────────────────────────────────────────
class _AnalyticsShimmer extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: Colors.grey.shade200,
      highlightColor: Colors.grey.shade100,
      child: Container(
        margin: const EdgeInsets.fromLTRB(16, 14, 16, 6),
        height: 120,
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18)),
      ),
    );
  }
}

// ── Order Card ────────────────────────────────────────────────────────────────
class _OrderCard extends StatelessWidget {
  final OrderModel order;
  const _OrderCard({required this.order});

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final color = _modColor(order.moduleSlug);
    return GestureDetector(
      onTap: () => context.push('/orders/${order.id}'),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: context.colors.cardBg,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
        ),
        child: Column(children: [
          Row(children: [
            // Module icon
            Container(
              width: 46, height: 46,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Center(child: Text(_modIcon(order.moduleSlug), style: const TextStyle(fontSize: 22))),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Expanded(
                    child: Text(order.orderNumber,
                        style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: context.colors.navyText)),
                  ),
                  _StatusBadge(status: order.status),
                ]),
                const SizedBox(height: 3),
                Text(_modLabel(order.moduleSlug),
                    style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w700)),
                if (order.vendorName != null && order.vendorName!.isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(order.vendorName!,
                      style: const TextStyle(fontSize: 11, color: AppColors.textGrey),
                      maxLines: 1, overflow: TextOverflow.ellipsis),
                ],
              ]),
            ),
          ]),
          const SizedBox(height: 12),
          Container(height: 1, color: context.colors.borderColor),
          const SizedBox(height: 12),
          Row(children: [
            Icon(Icons.access_time_rounded, size: 13, color: AppColors.textLight),
            const SizedBox(width: 4),
            Expanded(
              child: Text(_formatDate(order.createdAt),
                  style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
            ),
            Text('\$${order.totalAmount.toStringAsFixed(2)}',
                style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15, color: context.colors.navyText)),
          ]),
        ]),
      ),
    );
  }

  String _formatDate(String raw) {
    try {
      final dt = DateTime.parse(raw).toLocal();
      const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
      return '${months[dt.month-1]} ${dt.day}, ${dt.year}  ${dt.hour.toString().padLeft(2,'0')}:${dt.minute.toString().padLeft(2,'0')}';
    } catch (_) { return raw; }
  }
}

class _StatusBadge extends StatelessWidget {
  final String status;
  const _StatusBadge({required this.status});

  @override
  Widget build(BuildContext context) {
    final cfg = switch (status.toLowerCase()) {
      'delivered'  => (color: const Color(0xFF22C55E), bg: const Color(0xFFDCFCE7), icon: Icons.check_circle_rounded),
      'pending'    => (color: const Color(0xFFFF8A00), bg: const Color(0xFFFFF3E0), icon: Icons.schedule_rounded),
      'preparing'  => (color: const Color(0xFF3B82F6), bg: const Color(0xFFEFF6FF), icon: Icons.local_fire_department_rounded),
      'cancelled'  => (color: const Color(0xFFEF4444), bg: const Color(0xFFFEE2E2), icon: Icons.cancel_rounded),
      _            => (color: AppColors.textGrey, bg: const Color(0xFFF3F4F6), icon: Icons.info_outline_rounded),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(color: cfg.bg, borderRadius: BorderRadius.circular(20)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(cfg.icon, size: 11, color: cfg.color),
        const SizedBox(width: 4),
        Text(status[0].toUpperCase() + status.substring(1),
            style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: cfg.color)),
      ]),
    );
  }
}
