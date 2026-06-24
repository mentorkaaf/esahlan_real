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
      appBar: AppBar(title: const Text('My ads', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18))),
      body: adsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
        error: (e, _) => Center(child: Text('$e')),
        data: (ads) {
          if (ads.isEmpty) return const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.campaign_outlined, size: 56, color: Color(0xFFD1D5DB)),
            SizedBox(height: 12),
            Text('No ads yet', style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 16)),
            SizedBox(height: 4),
            Text('Create an ad from your business page', style: TextStyle(color: Color(0xFFD1D5DB), fontSize: 13)),
          ]));

          final totalSpent = ads.fold<double>(0, (s, a) => s + (double.tryParse('${a['spent']}') ?? 0));
          final totalClicks = ads.fold<int>(0, (s, a) => s + ((a['clicks'] as num?)?.toInt() ?? 0));
          final totalImpressions = ads.fold<int>(0, (s, a) => s + ((a['impressions'] as num?)?.toInt() ?? 0));

          return ListView(padding: const EdgeInsets.all(16), children: [
            // Summary stats
            Row(children: [
              _StatCard(label: 'Spent', value: '\$${totalSpent.toStringAsFixed(2)}', color: kOrange),
              const SizedBox(width: 10),
              _StatCard(label: 'Clicks', value: '$totalClicks', color: const Color(0xFF3B82F6)),
              const SizedBox(width: 10),
              _StatCard(label: 'Views', value: '$totalImpressions', color: const Color(0xFF10B981)),
            ]),
            const SizedBox(height: 20),

            const Text('Your ads', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: Color(0xFF1A1B2E))),
            const SizedBox(height: 12),

            ...ads.map((ad) {
              final status = ad['status'] as String? ?? 'draft';
              final statusColor = {'active': const Color(0xFF10B981), 'pending': const Color(0xFFF59E0B), 'rejected': Colors.red, 'completed': const Color(0xFF6B7280), 'paused': const Color(0xFF9CA3AF)}[status] ?? const Color(0xFF9CA3AF);
              final budget = double.tryParse('${ad['budget']}') ?? 0;
              final spent = double.tryParse('${ad['spent']}') ?? 0;
              final progress = budget > 0 ? (spent / budget).clamp(0.0, 1.0) : 0.0;

              return Container(
                margin: const EdgeInsets.only(bottom: 12), padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.04), blurRadius: 8)]),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    if (ad['media_url'] != null)
                      ClipRRect(borderRadius: BorderRadius.circular(8), child: NetImage(url: ad['media_url'], width: 48, height: 48, fit: BoxFit.cover))
                    else Container(width: 48, height: 48, decoration: BoxDecoration(color: const Color(0xFFF0F2F5), borderRadius: BorderRadius.circular(8)),
                      child: const Icon(Icons.campaign_rounded, color: kOrange)),
                    const SizedBox(width: 12),
                    Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(ad['title'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: Color(0xFF1A1B2E))),
                      Text(ad['page']?['name'] ?? '', style: const TextStyle(fontSize: 12, color: Color(0xFF9CA3AF))),
                    ])),
                    Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                      decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                      child: Text(status[0].toUpperCase() + status.substring(1), style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: statusColor))),
                  ]),
                  const SizedBox(height: 12),

                  // Budget progress
                  Row(children: [
                    Expanded(child: ClipRRect(borderRadius: BorderRadius.circular(4),
                      child: LinearProgressIndicator(value: progress, backgroundColor: const Color(0xFFF0F2F5), color: kOrange, minHeight: 6))),
                    const SizedBox(width: 10),
                    Text('\$${spent.toStringAsFixed(2)} / \$${budget.toStringAsFixed(2)}', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: Color(0xFF6B7280))),
                  ]),
                  const SizedBox(height: 10),

                  // Stats row
                  Row(children: [
                    _MiniStat(icon: Icons.visibility_outlined, value: '${ad['impressions'] ?? 0}', label: 'Views'),
                    const SizedBox(width: 16),
                    _MiniStat(icon: Icons.touch_app_outlined, value: '${ad['clicks'] ?? 0}', label: 'Clicks'),
                    const SizedBox(width: 16),
                    _MiniStat(icon: Icons.calendar_today_outlined, value: ad['placement'] ?? 'feed', label: 'Placement'),
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

class _StatCard extends StatelessWidget {
  final String label, value;
  final Color color;
  const _StatCard({required this.label, required this.value, required this.color});
  @override
  Widget build(BuildContext context) => Expanded(child: Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(color: color.withValues(alpha: 0.08), borderRadius: BorderRadius.circular(14)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: TextStyle(fontSize: 12, color: color, fontWeight: FontWeight.w600)),
      const SizedBox(height: 4),
      Text(value, style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: color)),
    ]),
  ));
}

class _MiniStat extends StatelessWidget {
  final IconData icon; final String value, label;
  const _MiniStat({required this.icon, required this.value, required this.label});
  @override
  Widget build(BuildContext context) => Row(children: [
    Icon(icon, size: 14, color: const Color(0xFF9CA3AF)),
    const SizedBox(width: 4),
    Text(value, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: Color(0xFF1A1B2E))),
    const SizedBox(width: 2),
    Text(label, style: const TextStyle(fontSize: 11, color: Color(0xFF9CA3AF))),
  ]);
}
