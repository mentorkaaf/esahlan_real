import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart';

final _myAdsProvider = FutureProvider<List<Map<String, dynamic>>>((ref) => ref.read(communityRepoProvider).getMyAds());

class AdAnalyticsScreen extends ConsumerWidget {
  const AdAnalyticsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final adsAsync = ref.watch(_myAdsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Ad Analytics', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
        actions: [IconButton(icon: const Icon(Icons.refresh_rounded), onPressed: () => ref.invalidate(_myAdsProvider))]),
      body: adsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
        error: (e, _) => Center(child: Text('$e')),
        data: (ads) {
          if (ads.isEmpty) return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(width: 80, height: 80, decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: const Icon(Icons.campaign_outlined, size: 40, color: kOrange)),
            const SizedBox(height: 16),
            const Text('No ads yet', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18, color: Color(0xFF1A1B2E))),
            const SizedBox(height: 6),
            const Text('Create an ad from your business page', style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 14)),
          ]));

          final totalSpent = ads.fold<double>(0, (s, a) => s + (double.tryParse('${a['spent']}') ?? 0));
          final totalBudget = ads.fold<double>(0, (s, a) => s + (double.tryParse('${a['budget']}') ?? 0));
          final totalClicks = ads.fold<int>(0, (s, a) => s + ((a['clicks'] as num?)?.toInt() ?? 0));
          final totalImpressions = ads.fold<int>(0, (s, a) => s + ((a['impressions'] as num?)?.toInt() ?? 0));
          final ctr = totalImpressions > 0 ? (totalClicks / totalImpressions * 100) : 0.0;

          return ListView(padding: const EdgeInsets.all(16), children: [
            // ── Summary Cards ──
            Row(children: [
              _MetricCard(icon: Icons.payments_rounded, label: 'Total Spent', value: '\$${totalSpent.toStringAsFixed(2)}', color: kOrange),
              const SizedBox(width: 10),
              _MetricCard(icon: Icons.touch_app_rounded, label: 'Total Clicks', value: '$totalClicks', color: const Color(0xFF3B82F6)),
            ]),
            const SizedBox(height: 10),
            Row(children: [
              _MetricCard(icon: Icons.visibility_rounded, label: 'Impressions', value: '$totalImpressions', color: const Color(0xFF10B981)),
              const SizedBox(width: 10),
              _MetricCard(icon: Icons.percent_rounded, label: 'CTR', value: '${ctr.toStringAsFixed(1)}%', color: const Color(0xFF8B5CF6)),
            ]),
            const SizedBox(height: 10),
            // Budget utilization bar
            Container(padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)]),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  const Text('Budget Used', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF1A1B2E))),
                  const Spacer(),
                  Text('\$${totalSpent.toStringAsFixed(2)} / \$${totalBudget.toStringAsFixed(2)}',
                    style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280), fontWeight: FontWeight.w600)),
                ]),
                const SizedBox(height: 10),
                ClipRRect(borderRadius: BorderRadius.circular(6),
                  child: LinearProgressIndicator(
                    value: totalBudget > 0 ? (totalSpent / totalBudget).clamp(0, 1) : 0,
                    backgroundColor: const Color(0xFFF0F2F5), color: kOrange, minHeight: 8)),
              ])),
            const SizedBox(height: 20),

            // ── Per-Ad Cards ──
            const Text('Your Campaigns', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: Color(0xFF1A1B2E))),
            const SizedBox(height: 12),

            ...ads.map((ad) {
              final status = ad['status'] as String? ?? 'draft';
              final statusColor = {'active': const Color(0xFF10B981), 'pending': const Color(0xFFF59E0B), 'rejected': Colors.red, 'completed': const Color(0xFF6B7280), 'paused': const Color(0xFF9CA3AF)}[status] ?? const Color(0xFF9CA3AF);
              final budget = double.tryParse('${ad['budget']}') ?? 0;
              final spent = double.tryParse('${ad['spent']}') ?? 0;
              final clicks = (ad['clicks'] as num?)?.toInt() ?? 0;
              final impressions = (ad['impressions'] as num?)?.toInt() ?? 0;
              final adCtr = impressions > 0 ? (clicks / impressions * 100) : 0.0;

              return Container(
                margin: const EdgeInsets.only(bottom: 14), padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)]),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  // Header
                  Row(children: [
                    if (ad['media_url'] != null)
                      ClipRRect(borderRadius: BorderRadius.circular(10),
                        child: NetImage(url: ad['media_url'], width: 56, height: 56, fit: BoxFit.cover))
                    else Container(width: 56, height: 56, decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
                      child: const Icon(Icons.campaign_rounded, color: kOrange, size: 28)),
                    const SizedBox(width: 14),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(ad['title'] ?? '', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: Color(0xFF1A1B2E))),
                      const SizedBox(height: 4),
                      Row(children: [
                        Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                          child: Text(status[0].toUpperCase() + status.substring(1), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: statusColor))),
                        const SizedBox(width: 8),
                        Text(ad['placement'] ?? '', style: const TextStyle(fontSize: 11, color: Color(0xFF9CA3AF))),
                      ]),
                    ])),
                  ]),
                  const SizedBox(height: 14),

                  // Stats grid
                  Row(children: [
                    _MiniStat(icon: Icons.visibility_outlined, value: '$impressions', label: 'Views'),
                    _MiniStat(icon: Icons.touch_app_outlined, value: '$clicks', label: 'Clicks'),
                    _MiniStat(icon: Icons.percent_rounded, value: '${adCtr.toStringAsFixed(1)}%', label: 'CTR'),
                    _MiniStat(icon: Icons.payments_outlined, value: '\$${spent.toStringAsFixed(2)}', label: 'Spent'),
                  ]),
                  const SizedBox(height: 12),

                  // Budget progress
                  Row(children: [
                    Expanded(child: ClipRRect(borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: budget > 0 ? (spent / budget).clamp(0, 1) : 0,
                        backgroundColor: const Color(0xFFF0F2F5), color: kOrange, minHeight: 6))),
                    const SizedBox(width: 10),
                    Text('${(budget > 0 ? (spent / budget * 100) : 0).toStringAsFixed(0)}%',
                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: kOrange)),
                  ]),
                ]),
              );
            }),
          ]);
        },
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  final IconData icon; final String label, value; final Color color;
  const _MetricCard({required this.icon, required this.label, required this.value, required this.color});
  @override
  Widget build(BuildContext context) => Expanded(child: Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)]),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 36, height: 36, decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
        child: Icon(icon, color: color, size: 20)),
      const SizedBox(height: 10),
      Text(value, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 22, color: color)),
      const SizedBox(height: 2),
      Text(label, style: const TextStyle(fontSize: 12, color: Color(0xFF9CA3AF))),
    ]),
  ));
}

class _MiniStat extends StatelessWidget {
  final IconData icon; final String value, label;
  const _MiniStat({required this.icon, required this.value, required this.label});
  @override
  Widget build(BuildContext context) => Expanded(child: Column(children: [
    Icon(icon, size: 16, color: const Color(0xFF9CA3AF)),
    const SizedBox(height: 4),
    Text(value, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: Color(0xFF1A1B2E))),
    Text(label, style: const TextStyle(fontSize: 10, color: Color(0xFF9CA3AF))),
  ]));
}
