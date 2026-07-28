import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:fl_chart/fl_chart.dart';
import '../../core/services/vendor_repository.dart';
import '../../core/theme/vc.dart';
import '../orders/order_detail_sheet.dart';


final _dashProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) => VendorRepository.instance.dashboard());

class DashboardScreen extends ConsumerStatefulWidget {
  const DashboardScreen({super.key});
  @override
  ConsumerState<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends ConsumerState<DashboardScreen> {
  bool _toggling = false;

  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);

  Future<void> _toggle(bool isOpen) async {
    setState(() => _toggling = true);
    try {
      await VendorRepository.instance.toggleStore();
      ref.invalidate(_dashProvider);
    } catch (_) {} finally {
      if (mounted) setState(() => _toggling = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final dash = ref.watch(_dashProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Dashboard'),
        actions: [
          Consumer(builder: (_, ref, __) {
            final isDark = ref.watch(themeModeProvider) == ThemeMode.dark;
            return IconButton(
              icon: Icon(isDark ? Icons.light_mode_rounded : Icons.dark_mode_rounded),
              tooltip: isDark ? 'Light mode' : 'Dark mode',
              onPressed: () => ref.read(themeModeProvider.notifier).toggle(),
            );
          }),
          IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: () => ref.invalidate(_dashProvider)),
        ],
      ),
      body: dash.when(
        loading: () => const Center(child: CircularProgressIndicator(color: VC.orange)),
        error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: VC.red))),
        data: (d) {
          final vendor = d['vendor'] as Map<String, dynamic>? ?? {};
          final stats  = d['stats']  as Map<String, dynamic>? ?? {};
          final recent = d['recent_orders'] as List? ?? [];
          final chart  = d['chart_data'] as List? ?? [];
          final isOpen = vendor['is_open'] == true && vendor['temporarily_closed'] != true;
          final moduleName = (vendor['module_slug'] ?? 'efood') == 'efood' ? 'eFood' : 'eShop';

          return RefreshIndicator(
            color: VC.orange,
            onRefresh: () async => ref.invalidate(_dashProvider),
            child: ListView(padding: const EdgeInsets.all(16), children: [

              // Store banner — always dark gradient (intentional branded element)
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: isOpen ? [const Color(0xFF0D2E1E), const Color(0xFF143D28)] : [const Color(0xFF2A1A0E), const Color(0xFF3A2010)],
                    begin: Alignment.topLeft, end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(color: isOpen ? VC.green.withValues(alpha: 0.3) : VC.amber.withValues(alpha: 0.3)),
                ),
                child: Row(children: [
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(vendor['name'] ?? 'My Store', style: const TextStyle(color: VC.text, fontSize: 18, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Row(children: [
                      Container(width: 8, height: 8, decoration: BoxDecoration(color: isOpen ? VC.green : VC.amber, shape: BoxShape.circle)),
                      const SizedBox(width: 6),
                      Text(isOpen ? 'Open — Accepting Orders' : 'Temporarily Closed',
                        style: TextStyle(color: isOpen ? VC.green : VC.amber, fontSize: 12, fontWeight: FontWeight.w700)),
                    ]),
                    const SizedBox(height: 4),
                    Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(color: VC.orangeDim, borderRadius: BorderRadius.circular(6)),
                      child: Text(moduleName, style: const TextStyle(color: VC.orange, fontSize: 11, fontWeight: FontWeight.w700))),
                  ])),
                  _toggling
                    ? const SizedBox(width: 44, height: 24, child: Center(child: SizedBox(width: 18, height: 18, child: CircularProgressIndicator(color: VC.orange, strokeWidth: 2))))
                    : Switch(value: isOpen, onChanged: _toggle, activeColor: VC.green, inactiveThumbColor: VC.amber),
                ]),
              ),
              const SizedBox(height: 16),

              // Stats grid
              GridView.count(crossAxisCount: 2, shrinkWrap: true, physics: const NeverScrollableScrollPhysics(),
                crossAxisSpacing: 10, mainAxisSpacing: 10, childAspectRatio: 2.0,
                children: [
                  _statCard('Today Orders', '${stats['today_orders'] ?? 0}', Icons.receipt_long_rounded, VC.blue, context),
                  _statCard('Today Revenue', '\$${_fmt(stats['today_revenue'])}', Icons.attach_money_rounded, VC.green, context),
                  _statCard('Pending', '${stats['pending_orders'] ?? 0}', Icons.hourglass_top_rounded, VC.amber, context),
                  _statCard('This Month', '\$${_fmt(stats['this_month'])}', Icons.calendar_month_rounded, VC.purple, context),
                ],
              ),
              const SizedBox(height: 16),

              // Revenue chart
              if (chart.isNotEmpty) ...[
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(color: context.vcCard, borderRadius: BorderRadius.circular(16), border: Border.all(color: context.vcBorder)),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('Revenue — Last 7 Days', style: TextStyle(color: context.vcText, fontSize: 13, fontWeight: FontWeight.w800)),
                    const SizedBox(height: 16),
                    SizedBox(height: 120, child: BarChart(BarChartData(
                      gridData: const FlGridData(show: false),
                      borderData: FlBorderData(show: false),
                      titlesData: FlTitlesData(
                        leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                        rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                        topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
                        bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: true, reservedSize: 22,
                          getTitlesWidget: (v, _) {
                            final i = v.toInt();
                            if (i < 0 || i >= chart.length) return const SizedBox();
                            final date = chart[i]['date']?.toString() ?? '';
                            return Text(date.length >= 10 ? date.substring(5) : date,
                              style: TextStyle(color: context.vcTextMute, fontSize: 10));
                          })),
                      ),
                      barGroups: chart.asMap().entries.map((e) => BarChartGroupData(x: e.key, barRods: [
                        BarChartRodData(
                          toY: double.tryParse('${e.value['revenue'] ?? 0}') ?? 0,
                          color: VC.orange, width: 14, borderRadius: BorderRadius.circular(5)),
                      ])).toList(),
                    ))),
                  ]),
                ),
                const SizedBox(height: 16),
              ],

              // Recent orders
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text('Recent Orders', style: TextStyle(color: context.vcText, fontSize: 15, fontWeight: FontWeight.w800)),
                if ((stats['pending_orders'] ?? 0) > 0)
                  Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(color: VC.amberDim, borderRadius: BorderRadius.circular(8)),
                    child: Text('${stats['pending_orders']} pending', style: const TextStyle(color: VC.amber, fontSize: 11, fontWeight: FontWeight.w700))),
              ]),
              const SizedBox(height: 10),
              ...recent.take(5).map((o) => _orderTile(o, context)),
            ]),
          );
        },
      ),
    );
  }

  Widget _statCard(String label, String val, IconData icon, Color color, BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: context.vcCard, borderRadius: BorderRadius.circular(14), border: Border.all(color: context.vcBorder)),
    child: Row(children: [
      Container(width: 36, height: 36, decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
        child: Icon(icon, color: color, size: 18)),
      const SizedBox(width: 10),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: TextStyle(color: context.vcTextSec, fontSize: 10, fontWeight: FontWeight.w600)),
        const SizedBox(height: 2),
        Text(val, style: TextStyle(color: context.vcText, fontSize: 16, fontWeight: FontWeight.w900)),
      ])),
    ]),
  );

  Widget _orderTile(dynamic o, BuildContext context) {
    final status = o['status'] as String? ?? 'pending';
    final statusColor = {'pending': VC.amber, 'confirmed': VC.blue, 'ready_for_pickup': VC.green, 'delivered': VC.green, 'cancelled': VC.red}[status] ?? context.vcTextSec;
    return GestureDetector(
      onTap: () => showOrderDetail(context, o['id']),
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: context.vcCard, borderRadius: BorderRadius.circular(14), border: Border.all(color: context.vcBorder.withValues(alpha: 0.5))),
        child: Row(children: [
          Container(width: 40, height: 40, decoration: BoxDecoration(color: VC.orangeDim, borderRadius: BorderRadius.circular(10)),
            child: const Icon(Icons.receipt_long_rounded, color: VC.orange, size: 20)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('#${o['order_number'] ?? ''}', style: TextStyle(color: context.vcText, fontWeight: FontWeight.w800, fontSize: 13)),
            Text(o['user']?['name'] ?? 'Customer', style: TextStyle(color: context.vcTextSec, fontSize: 11)),
          ])),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text('\$${_fmt(o['subtotal'] ?? o['total_amount'])}', style: const TextStyle(color: VC.green, fontWeight: FontWeight.w800, fontSize: 14)),
            if ((double.tryParse('${o['delivery_fee'] ?? 0}') ?? 0) > 0)
              Text('+\$${_fmt(o['delivery_fee'])} del.', style: TextStyle(color: context.vcTextMute, fontSize: 10)),
            Container(margin: const EdgeInsets.only(top: 4), padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
              decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
              child: Text(status.replaceAll('_', ' '), style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.w700))),
          ]),
        ]),
      ),
    );
  }
}
