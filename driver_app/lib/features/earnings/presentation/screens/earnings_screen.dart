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
    final data = ref.watch(_earningsProvider);

    return Scaffold(
      backgroundColor: DC.navy,
      appBar: AppBar(title: const Text('Earnings')),
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
        error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: DC.error))),
        data: (d) => RefreshIndicator(
          color: DC.orange,
          onRefresh: () async => ref.invalidate(_earningsProvider),
          child: ListView(padding: const EdgeInsets.all(16), children: [
            // Total earnings hero
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [Color(0xFF1A2A4A), Color(0xFF243355)]),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Column(children: [
                const Text('Total Earnings', style: TextStyle(color: DC.textSec, fontSize: 13)),
                const SizedBox(height: 6),
                Text('\$${(d['total'] ?? 0).toStringAsFixed(2)}', style: const TextStyle(color: DC.text, fontSize: 38, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text('${d['total_orders'] ?? 0} deliveries', style: const TextStyle(color: DC.textMuted, fontSize: 12)),
              ]),
            ),
            const SizedBox(height: 20),

            // Period cards
            Row(children: [
              Expanded(child: _PeriodCard(label: 'Today', value: d['today'] ?? 0, color: DC.orange)),
              const SizedBox(width: 10),
              Expanded(child: _PeriodCard(label: 'This Week', value: d['weekly'] ?? 0, color: DC.success)),
              const SizedBox(width: 10),
              Expanded(child: _PeriodCard(label: 'This Month', value: d['monthly'] ?? 0, color: Color(0xFF3B82F6))),
            ]),
            const SizedBox(height: 24),

            // Chart
            const Text('Last 7 Days', style: TextStyle(color: DC.text, fontSize: 16, fontWeight: FontWeight.w800)),
            const SizedBox(height: 14),
            Container(
              height: 180,
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(16)),
              child: _buildChart(d['daily_chart'] as List? ?? []),
            ),
            const SizedBox(height: 24),

            // Recent deliveries
            const Text('Recent Deliveries', style: TextStyle(color: DC.text, fontSize: 16, fontWeight: FontWeight.w800)),
            const SizedBox(height: 10),
            ...((d['recent'] as List? ?? []).map((e) => Container(
              margin: const EdgeInsets.only(bottom: 8),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(12)),
              child: Row(children: [
                Container(width: 36, height: 36, decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(8)),
                  child: const Icon(Icons.delivery_dining, color: DC.orange, size: 18)),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('#${e['order_number'] ?? ''}', style: const TextStyle(color: DC.text, fontWeight: FontWeight.w600, fontSize: 13)),
                  Text(e['module_slug'] ?? '', style: const TextStyle(color: DC.textMuted, fontSize: 11)),
                ])),
                Text('+\$${(e['amount'] ?? 0).toStringAsFixed(2)}', style: const TextStyle(color: DC.success, fontWeight: FontWeight.w800, fontSize: 14)),
              ]),
            ))),
          ]),
        ),
      ),
    );
  }

  Widget _buildChart(List data) {
    if (data.isEmpty) return const Center(child: Text('No data', style: TextStyle(color: DC.textMuted)));
    final spots = data.asMap().entries.map((e) => FlSpot(e.key.toDouble(), double.tryParse('${e.value['total'] ?? 0}') ?? 0)).toList();
    return LineChart(LineChartData(
      gridData: FlGridData(show: false),
      titlesData: FlTitlesData(show: false),
      borderData: FlBorderData(show: false),
      lineBarsData: [LineChartBarData(
        spots: spots, isCurved: true, color: DC.orange, barWidth: 3,
        dotData: FlDotData(show: true, getDotPainter: (_, __, ___, ____) => FlDotCirclePainter(radius: 4, color: DC.orange, strokeColor: Colors.white, strokeWidth: 1.5)),
        belowBarData: BarAreaData(show: true, color: DC.orange.withValues(alpha: 0.1)),
      )],
    ));
  }
}

class _PeriodCard extends StatelessWidget {
  final String label;
  final dynamic value;
  final Color color;
  const _PeriodCard({required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    final v = double.tryParse('$value') ?? 0;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(14)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: const TextStyle(color: DC.textMuted, fontSize: 11)),
        const SizedBox(height: 6),
        Text('\$${v.toStringAsFixed(2)}', style: TextStyle(color: color, fontSize: 18, fontWeight: FontWeight.w900)),
      ]),
    );
  }
}
