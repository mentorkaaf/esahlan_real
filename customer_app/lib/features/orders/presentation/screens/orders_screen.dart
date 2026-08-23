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
                  error: (_, __) => const SizedBox.shrink(),
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

// ── Analytics Dashboard ───────────────────────────────────────────────────────
class _AnalyticsDashboard extends StatelessWidget {
  final Map<String, dynamic> data;
  const _AnalyticsDashboard({required this.data});

  @override
  Widget build(BuildContext context) {
    final total   = data['total'] ?? 0;
    final today   = data['today'] ?? 0;
    final week    = data['this_week'] ?? 0;
    final month   = data['this_month'] ?? 0;
    final year    = data['this_year'] ?? 0;
    final spent   = (data['total_spent'] as num?)?.toDouble() ?? 0;
    final modules = (data['modules'] as List?) ?? [];
    final trend   = (data['trend'] as List?) ?? [];

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Hero total card ──────────────────────────────────────────────
          _HeroCard(total: total, spent: spent),
          const SizedBox(height: 14),

          // ── Period stats row ─────────────────────────────────────────────
          Row(children: [
            Expanded(child: _PeriodStat(label: 'Today', value: today, icon: Icons.wb_sunny_rounded, color: const Color(0xFFFF8A00))),
            const SizedBox(width: 10),
            Expanded(child: _PeriodStat(label: 'This Week', value: week, icon: Icons.date_range_rounded, color: const Color(0xFF5C6BC0))),
            const SizedBox(width: 10),
            Expanded(child: _PeriodStat(label: 'This Month', value: month, icon: Icons.calendar_month_rounded, color: const Color(0xFF26A69A))),
            const SizedBox(width: 10),
            Expanded(child: _PeriodStat(label: 'This Year', value: year, icon: Icons.bar_chart_rounded, color: const Color(0xFFAB47BC))),
          ]),
          const SizedBox(height: 14),

          // ── 6-month trend chart ──────────────────────────────────────────
          if (trend.isNotEmpty) ...[
            _SectionHeader('Order Trend', '6 months'),
            const SizedBox(height: 10),
            _TrendChart(trend: trend.cast<Map>()),
            const SizedBox(height: 14),
          ],

          // ── Module breakdown ─────────────────────────────────────────────
          if (modules.isNotEmpty) ...[
            _SectionHeader('By Module', '${modules.length} services'),
            const SizedBox(height: 10),
            ...modules.map((m) => _ModuleRow(m: Map<String, dynamic>.from(m as Map), total: total)),
            const SizedBox(height: 6),
          ],
        ],
      ),
    );
  }
}

class _HeroCard extends StatelessWidget {
  final int total;
  final double spent;
  const _HeroCard({required this.total, required this.spent});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF07003B), Color(0xFF1B0F6E)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [BoxShadow(color: const Color(0xFF07003B).withValues(alpha: 0.35), blurRadius: 20, offset: const Offset(0, 8))],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Total Orders', style: TextStyle(color: Colors.white.withValues(alpha: 0.65), fontSize: 12, fontWeight: FontWeight.w600)),
              const SizedBox(height: 4),
              Text('$total', style: const TextStyle(color: Colors.white, fontSize: 40, fontWeight: FontWeight.w900, height: 1)),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
                child: Text('All Time', style: TextStyle(color: Colors.white.withValues(alpha: 0.8), fontSize: 11, fontWeight: FontWeight.w600)),
              ),
            ]),
          ),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text('Total Spent', style: TextStyle(color: Colors.white.withValues(alpha: 0.65), fontSize: 12, fontWeight: FontWeight.w600)),
            const SizedBox(height: 4),
            Text('\$${spent.toStringAsFixed(2)}',
                style: const TextStyle(color: Color(0xFFFF8A00), fontSize: 26, fontWeight: FontWeight.w900)),
          ]),
        ],
      ),
    );
  }
}

class _PeriodStat extends StatelessWidget {
  final String label;
  final int value;
  final IconData icon;
  final Color color;
  const _PeriodStat({required this.label, required this.value, required this.icon, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)],
      ),
      child: Column(children: [
        Container(
          width: 36, height: 36,
          decoration: BoxDecoration(color: color.withValues(alpha: 0.12), shape: BoxShape.circle),
          child: Icon(icon, color: color, size: 18),
        ),
        const SizedBox(height: 8),
        Text('$value', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: context.colors.navyText)),
        const SizedBox(height: 2),
        Text(label, style: const TextStyle(fontSize: 9, color: AppColors.textGrey, fontWeight: FontWeight.w600), textAlign: TextAlign.center, maxLines: 2),
      ]),
    );
  }
}

class _SectionHeader extends StatelessWidget {
  final String title, sub;
  const _SectionHeader(this.title, this.sub);

  @override
  Widget build(BuildContext context) {
    return Row(children: [
      Text(title, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: context.colors.navyText)),
      const SizedBox(width: 8),
      Text(sub, style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
    ]);
  }
}

// ── 6-month bar chart ─────────────────────────────────────────────────────────
class _TrendChart extends StatelessWidget {
  final List<Map> trend;
  const _TrendChart({required this.trend});

  @override
  Widget build(BuildContext context) {
    final maxVal = trend.fold<int>(1, (m, t) => math.max(m, (t['count'] as int? ?? 0)));
    return Container(
      height: 100,
      padding: const EdgeInsets.fromLTRB(8, 8, 8, 0),
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: trend.map((t) {
          final count = t['count'] as int? ?? 0;
          final frac  = maxVal > 0 ? count / maxVal : 0.0;
          return Expanded(
            child: Column(mainAxisAlignment: MainAxisAlignment.end, children: [
              if (count > 0)
                Text('$count', style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w700, color: AppColors.primary)),
              const SizedBox(height: 3),
              Flexible(
                child: FractionallySizedBox(
                  heightFactor: frac.clamp(0.04, 1.0),
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 4),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [AppColors.primary, const Color(0xFF4A3AE0)],
                        begin: Alignment.bottomCenter,
                        end: Alignment.topCenter,
                      ),
                      borderRadius: BorderRadius.circular(6),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 4),
              Text(t['month'] as String? ?? '', style: const TextStyle(fontSize: 9, color: AppColors.textGrey, fontWeight: FontWeight.w600)),
            ]),
          );
        }).toList(),
      ),
    );
  }
}

// ── Module row ────────────────────────────────────────────────────────────────
class _ModuleRow extends StatelessWidget {
  final Map<String, dynamic> m;
  final int total;
  const _ModuleRow({required this.m, required this.total});

  @override
  Widget build(BuildContext context) {
    final slug  = m['slug'] as String? ?? '';
    final count = m['count'] as int? ?? 0;
    final spent = (m['spent'] as num?)?.toDouble() ?? 0;
    final color = _modColor(slug);
    final pct   = total > 0 ? count / total : 0.0;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 6)],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Text(_modIcon(slug), style: const TextStyle(fontSize: 18)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(_modLabel(slug),
                style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText)),
          ),
          Text('$count orders',
              style: const TextStyle(fontSize: 12, color: AppColors.textGrey, fontWeight: FontWeight.w600)),
          const SizedBox(width: 8),
          Text('\$${spent.toStringAsFixed(2)}',
              style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800, color: color)),
        ]),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: pct,
            minHeight: 5,
            backgroundColor: color.withValues(alpha: 0.12),
            valueColor: AlwaysStoppedAnimation(color),
          ),
        ),
      ]),
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
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
        child: Column(children: [
          Container(height: 100, decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20))),
          const SizedBox(height: 14),
          Row(children: List.generate(4, (_) => Expanded(child: Container(
            height: 80, margin: const EdgeInsets.symmetric(horizontal: 4),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
          )))),
          const SizedBox(height: 14),
          Container(height: 100, decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16))),
        ]),
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
