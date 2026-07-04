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
      appBar: AppBar(title: Text('Ad Analytics', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
        actions: [IconButton(icon: Icon(Icons.refresh_rounded), onPressed: () => ref.invalidate(_myAdsProvider))]),
      body: adsAsync.when(
        loading: () => Center(child: CircularProgressIndicator(color: kOrange)),
        error: (e, _) => Center(child: Text('$e')),
        data: (ads) {
          if (ads.isEmpty) return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(width: 80, height: 80, decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: Icon(Icons.campaign_outlined, size: 40, color: kOrange)),
            SizedBox(height: 16),
            Text('No ads yet', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18, color: context.colors.bodyText)),
            SizedBox(height: 6),
            Text('Create an ad from your business page', style: TextStyle(color: context.colors.mutedText, fontSize: 14)),
          ]));

          final totalSpent = ads.fold<double>(0, (s, a) => s + (double.tryParse('${a['spent']}') ?? 0));
          final totalBudget = ads.fold<double>(0, (s, a) => s + (double.tryParse('${a['budget']}') ?? 0));
          final totalClicks = ads.fold<int>(0, (s, a) => s + ((a['clicks'] as num?)?.toInt() ?? 0));
          final totalImpressions = ads.fold<int>(0, (s, a) => s + ((a['impressions'] as num?)?.toInt() ?? 0));
          final ctr = totalImpressions > 0 ? (totalClicks / totalImpressions * 100) : 0.0;

          return ListView(padding: EdgeInsets.all(16), children: [
            // ── Summary Cards ──
            Row(children: [
              _MetricCard(icon: Icons.payments_rounded, label: 'Total Spent', value: '\$${totalSpent.toStringAsFixed(2)}', color: kOrange),
              SizedBox(width: 10),
              _MetricCard(icon: Icons.touch_app_rounded, label: 'Total Clicks', value: '$totalClicks', color: const Color(0xFF3B82F6)),
            ]),
            SizedBox(height: 10),
            Row(children: [
              _MetricCard(icon: Icons.visibility_rounded, label: 'Impressions', value: '$totalImpressions', color: const Color(0xFF10B981)),
              SizedBox(width: 10),
              _MetricCard(icon: Icons.percent_rounded, label: 'CTR', value: '${ctr.toStringAsFixed(1)}%', color: const Color(0xFF8B5CF6)),
            ]),
            SizedBox(height: 10),
            // Budget utilization bar
            Container(padding: EdgeInsets.all(16),
              decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)]),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Text('Budget Used', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.bodyText)),
                  Spacer(),
                  Text('\$${totalSpent.toStringAsFixed(2)} / \$${totalBudget.toStringAsFixed(2)}',
                    style: TextStyle(fontSize: 12, color: context.colors.mutedText, fontWeight: FontWeight.w600)),
                ]),
                SizedBox(height: 10),
                ClipRRect(borderRadius: BorderRadius.circular(6),
                  child: LinearProgressIndicator(
                    value: totalBudget > 0 ? (totalSpent / totalBudget).clamp(0, 1) : 0,
                    backgroundColor: const Color(0xFFF0F2F5), color: kOrange, minHeight: 8)),
              ])),
            SizedBox(height: 20),

            // ── Per-Ad Cards ──
            Text('Your Campaigns', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: context.colors.bodyText)),
            SizedBox(height: 12),

            ...ads.map((ad) {
              final status = ad['status'] as String? ?? 'draft';
              final statusColor = {'active': const Color(0xFF10B981), 'pending': const Color(0xFFF59E0B), 'rejected': Colors.red, 'completed': const Color(0xFF6B7280), 'paused': const Color(0xFF9CA3AF)}[status] ?? const Color(0xFF9CA3AF);
              final budget = double.tryParse('${ad['budget']}') ?? 0;
              final spent = double.tryParse('${ad['spent']}') ?? 0;
              final clicks = (ad['clicks'] as num?)?.toInt() ?? 0;
              final impressions = (ad['impressions'] as num?)?.toInt() ?? 0;
              final adCtr = impressions > 0 ? (clicks / impressions * 100) : 0.0;

              return Container(
                margin: EdgeInsets.only(bottom: 14), padding: EdgeInsets.all(16),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)]),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  // Header
                  Row(children: [
                    if (ad['media_url'] != null)
                      ClipRRect(borderRadius: BorderRadius.circular(10),
                        child: NetImage(url: ad['media_url'], width: 56, height: 56, fit: BoxFit.cover))
                    else Container(width: 56, height: 56, decoration: BoxDecoration(color: kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
                      child: Icon(Icons.campaign_rounded, color: kOrange, size: 28)),
                    SizedBox(width: 14),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(ad['title'] ?? '', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.bodyText)),
                      SizedBox(height: 4),
                      Row(children: [
                        Container(padding: EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                          decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                          child: Text(status[0].toUpperCase() + status.substring(1), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: statusColor))),
                        SizedBox(width: 8),
                        Text(ad['placement'] ?? '', style: TextStyle(fontSize: 11, color: context.colors.mutedText)),
                      ]),
                    ])),
                  ]),
                  SizedBox(height: 14),

                  // Stats grid
                  Row(children: [
                    _MiniStat(icon: Icons.visibility_outlined, value: '$impressions', label: 'Views'),
                    _MiniStat(icon: Icons.touch_app_outlined, value: '$clicks', label: 'Clicks'),
                    _MiniStat(icon: Icons.percent_rounded, value: '${adCtr.toStringAsFixed(1)}%', label: 'CTR'),
                    _MiniStat(icon: Icons.payments_outlined, value: '\$${spent.toStringAsFixed(2)}', label: 'Spent'),
                  ]),
                  SizedBox(height: 12),

                  // Budget progress
                  Row(children: [
                    Expanded(child: ClipRRect(borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(
                        value: budget > 0 ? (spent / budget).clamp(0, 1) : 0,
                        backgroundColor: const Color(0xFFF0F2F5), color: kOrange, minHeight: 6))),
                    SizedBox(width: 10),
                    Text('${(budget > 0 ? (spent / budget * 100) : 0).toStringAsFixed(0)}%',
                      style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: kOrange)),
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
    padding: EdgeInsets.all(16),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16),
      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)]),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 36, height: 36, decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
        child: Icon(icon, color: color, size: 20)),
      SizedBox(height: 10),
      Text(value, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 22, color: color)),
      SizedBox(height: 2),
      Text(label, style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
    ]),
  ));
}

class _MiniStat extends StatelessWidget {
  final IconData icon; final String value, label;
  const _MiniStat({required this.icon, required this.value, required this.label});
  @override
  Widget build(BuildContext context) => Expanded(child: Column(children: [
    Icon(icon, size: 16, color: const Color(0xFF9CA3AF)),
    SizedBox(height: 4),
    Text(value, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: context.colors.bodyText)),
    Text(label, style: TextStyle(fontSize: 10, color: context.colors.mutedText)),
  ]));
}
