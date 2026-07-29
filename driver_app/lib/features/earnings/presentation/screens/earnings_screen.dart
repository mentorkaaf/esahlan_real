import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../../../core/theme/driver_colors.dart';
import '../../../auth/presentation/providers/auth_provider.dart';

final _earningsProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.read(authRepoProvider).earnings());

class EarningsScreen extends ConsumerWidget {
  const EarningsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.dc;
    final data = ref.watch(_earningsProvider);

    return Scaffold(
      backgroundColor: c.navy,
      appBar: AppBar(
        backgroundColor: c.navyLight,
        title: Text('Earnings', style: TextStyle(fontWeight: FontWeight.w800, color: c.text)),
      ),
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
        error: (e, _) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.error_outline, color: DC.error, size: 40),
          const SizedBox(height: 8),
          Text('$e', style: TextStyle(color: c.textSec, fontSize: 13)),
          const SizedBox(height: 12),
          ElevatedButton(onPressed: () => ref.invalidate(_earningsProvider), child: const Text('Retry')),
        ])),
        data: (d) => RefreshIndicator(
          color: DC.orange,
          onRefresh: () async => ref.invalidate(_earningsProvider),
          child: ListView(padding: const EdgeInsets.all(16), children: [
            // Total hero
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [Color(0xFF1A2A4A), Color(0xFF243355)], begin: Alignment.topLeft, end: Alignment.bottomRight),
                borderRadius: BorderRadius.circular(22),
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.2), blurRadius: 16)],
              ),
              child: Column(children: [
                const Text('Total Earnings', style: TextStyle(color: Color(0xFF8B9CC7), fontSize: 13, letterSpacing: 0.5)),
                const SizedBox(height: 8),
                Text('\$${_fmt(d['total'])}', style: const TextStyle(color: Colors.white, fontSize: 42, fontWeight: FontWeight.w900, letterSpacing: -1)),
                const SizedBox(height: 6),
                Container(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                  decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(20)),
                  child: Text('${d['total_orders'] ?? 0} deliveries completed', style: const TextStyle(color: DC.success, fontSize: 11, fontWeight: FontWeight.w600))),
              ]),
            ),
            const SizedBox(height: 16),

            // Period cards
            Row(children: [
              Expanded(child: _PeriodCard('Today', d['today'], DC.orange)),
              const SizedBox(width: 10),
              Expanded(child: _PeriodCard('This Week', d['weekly'], DC.success)),
              const SizedBox(width: 10),
              Expanded(child: _PeriodCard('This Month', d['monthly'], const Color(0xFF3B82F6))),
            ]),
            const SizedBox(height: 24),

            // Chart
            Row(children: [
              Text('Last 7 Days', style: TextStyle(color: c.text, fontSize: 16, fontWeight: FontWeight.w800)),
              const Spacer(),
              Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(6)),
                child: const Text('This Week', style: TextStyle(color: DC.orange, fontSize: 10, fontWeight: FontWeight.w700))),
            ]),
            const SizedBox(height: 14),
            Container(
              height: 200, padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(18), border: Border.all(color: c.border.withValues(alpha: 0.5))),
              child: _buildChart(d['daily_chart'] as List? ?? [], c.textMuted),
            ),
            const SizedBox(height: 24),

            // Breakdown section
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(18)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Breakdown', style: TextStyle(color: c.text, fontSize: 15, fontWeight: FontWeight.w800)),
                const SizedBox(height: 14),
                Builder(builder: (_) {
                  final commission = double.tryParse('${d['total_platform_commission'] ?? 0}') ?? 0;
                  final net = double.tryParse('${d['total'] ?? 0}') ?? 0;
                  final original = net + commission;
                  if (commission > 0) {
                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: c.surface,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: c.border.withValues(alpha: 0.5)),
                      ),
                      child: Column(children: [
                        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                          Row(children: [
                            Container(width: 32, height: 32, decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
                              child: const Icon(Icons.delivery_dining_rounded, color: DC.success, size: 17)),
                            const SizedBox(width: 10),
                            Text('Gross Delivery Fee', style: TextStyle(color: c.textMuted, fontSize: 12, fontWeight: FontWeight.w500)),
                          ]),
                          Text('\$${_fmt(original)}', style: TextStyle(color: c.text, fontSize: 13, fontWeight: FontWeight.w700)),
                        ]),
                        const SizedBox(height: 10),
                        Divider(color: c.divider, height: 1),
                        const SizedBox(height: 10),
                        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                          Row(children: [
                            Container(width: 32, height: 32, decoration: BoxDecoration(color: DC.error.withValues(alpha: 0.10), borderRadius: BorderRadius.circular(8)),
                              child: const Icon(Icons.percent_rounded, color: DC.error, size: 16)),
                            const SizedBox(width: 10),
                            Text('Platform Commission', style: TextStyle(color: c.textMuted, fontSize: 12, fontWeight: FontWeight.w500)),
                          ]),
                          Text('-\$${_fmt(commission)}', style: const TextStyle(color: DC.error, fontSize: 13, fontWeight: FontWeight.w700)),
                        ]),
                        const SizedBox(height: 10),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.10), borderRadius: BorderRadius.circular(10)),
                          child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                            const Text('Your Earnings', style: TextStyle(color: DC.success, fontSize: 13, fontWeight: FontWeight.w800)),
                            Text('\$${_fmt(net)}', style: const TextStyle(color: DC.success, fontSize: 15, fontWeight: FontWeight.w900)),
                          ]),
                        ),
                      ]),
                    );
                  }
                  return _BreakdownRow(Icons.delivery_dining_rounded, 'Delivery Earnings', '\$${_fmt(net)}', DC.success);
                }),
                _BreakdownRow(Icons.star_rounded, 'Tips', '\$0.00', DC.busy),
                _BreakdownRow(Icons.card_giftcard_rounded, 'Bonuses', '\$0.00', DC.orange),
              ]),
            ),
            const SizedBox(height: 24),

            // Recent deliveries
            Text('Recent Deliveries', style: TextStyle(color: c.text, fontSize: 16, fontWeight: FontWeight.w800)),
            const SizedBox(height: 10),
            ...((d['recent'] as List? ?? []).map((e) {
              final commission = double.tryParse('${e['delivery_fee_commission'] ?? 0}') ?? 0;
              final original = double.tryParse('${e['original_delivery_fee'] ?? 0}') ?? 0;
              final net = double.tryParse('${e['amount'] ?? 0}') ?? 0;
              return Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: c.border.withValues(alpha: 0.3))),
                child: Row(children: [
                  Container(width: 40, height: 40, decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(10)),
                    child: const Icon(Icons.receipt_long_rounded, color: DC.orange, size: 20)),
                  const SizedBox(width: 12),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('#${e['order_number'] ?? ''}', style: TextStyle(color: c.text, fontWeight: FontWeight.w700, fontSize: 13)),
                    const SizedBox(height: 2),
                    Row(children: [
                      if ((e['module_slug'] ?? '').toString().isNotEmpty)
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(5)),
                          child: Text(e['module_slug'] ?? '', style: const TextStyle(color: DC.orange, fontSize: 10, fontWeight: FontWeight.w600)),
                        ),
                      if (commission > 0) ...[
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                          decoration: BoxDecoration(color: DC.error.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(5)),
                          child: Text('fee -\$${_fmt(commission)}', style: const TextStyle(color: DC.error, fontSize: 10, fontWeight: FontWeight.w600)),
                        ),
                      ],
                    ]),
                  ])),
                  Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Text('+\$${_fmt(net)}', style: const TextStyle(color: DC.success, fontWeight: FontWeight.w800, fontSize: 15)),
                    if (original > 0 && commission > 0)
                      Text('gross \$${_fmt(original)}', style: TextStyle(color: c.textMuted, fontSize: 10)),
                  ]),
                ]),
              );
            })),
            const SizedBox(height: 20),
          ]),
        ),
      ),
    );
  }

  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);

  Widget _buildChart(List data, Color textMuted) {
    if (data.isEmpty) return Center(child: Text('No data yet', style: TextStyle(color: textMuted)));
    final bars = data.asMap().entries.map((e) {
      final v = double.tryParse('${e.value['total'] ?? 0}') ?? 0;
      return BarChartGroupData(x: e.key, barRods: [
        BarChartRodData(toY: v, color: DC.orange, width: 20, borderRadius: BorderRadius.circular(6),
          backDrawRodData: BackgroundBarChartRodData(show: true, toY: v + 2, color: DC.orange.withValues(alpha: 0.08))),
      ]);
    }).toList();

    return BarChart(BarChartData(
      barGroups: bars, gridData: const FlGridData(show: false),
      titlesData: FlTitlesData(
        leftTitles: AxisTitles(sideTitles: SideTitles(showTitles: true, reservedSize: 40,
          getTitlesWidget: (v, _) => Text('\$${v.toInt()}', style: TextStyle(color: textMuted, fontSize: 10)))),
        bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: true,
          getTitlesWidget: (v, _) {
            const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            return Text(v.toInt() < days.length ? days[v.toInt()] : '', style: TextStyle(color: textMuted, fontSize: 10));
          })),
        topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
        rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
      ),
      borderData: FlBorderData(show: false),
    ));
  }
}

class _PeriodCard extends StatelessWidget {
  final String label; final dynamic value; final Color color;
  const _PeriodCard(this.label, this.value, this.color);
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    final v = double.tryParse('${value ?? 0}') ?? 0;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(16), border: Border.all(color: c.border.withValues(alpha: 0.3))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: TextStyle(color: c.textMuted, fontSize: 11)),
        const SizedBox(height: 8),
        Text('\$${v.toStringAsFixed(2)}', style: TextStyle(color: color, fontSize: 20, fontWeight: FontWeight.w900)),
      ]),
    );
  }
}

class _BreakdownRow extends StatelessWidget {
  final IconData icon; final String label, value; final Color color;
  const _BreakdownRow(this.icon, this.label, this.value, this.color);
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(children: [
        Container(width: 36, height: 36, decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
          child: Icon(icon, color: color, size: 18)),
        const SizedBox(width: 12),
        Expanded(child: Text(label, style: TextStyle(color: c.textSec, fontSize: 13))),
        Text(value, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 14)),
      ]),
    );
  }
}
