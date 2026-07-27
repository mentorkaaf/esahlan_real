import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/api/module_api_service.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/utils/error_handler.dart';

final _referralProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final res = await ModuleApiService.create().getReferral();
  return (res['data'] as Map<String, dynamic>?) ?? {};
});

class ReferralScreen extends ConsumerWidget {
  const ReferralScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(_referralProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Referral Program'),
        centerTitle: true,
      ),
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text(AppErrorHandler.message(e))),
        data: (d) => _ReferralBody(data: d),
      ),
    );
  }
}

class _ReferralBody extends StatelessWidget {
  final Map<String, dynamic> data;
  const _ReferralBody({required this.data});

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final code     = data['referral_code']?.toString() ?? '';
    final total    = (data['referral_count'] as num?)?.toInt() ?? 0;
    final rewarded = (data['rewarded_count'] as num?)?.toInt() ?? 0;
    final pending  = (data['pending_count'] as num?)?.toInt() ?? 0;
    final earned   = (data['total_earned_pts'] as num?)?.toInt() ?? 0;

    List<Map<String, dynamic>> referrals = [];
    try {
      final raw = data['referrals'];
      if (raw != null) {
        referrals = List<dynamic>.from(raw as Iterable)
            .map((r) => r is Map<String, dynamic> ? r : Map<String, dynamic>.from(r as Map))
            .toList();
      }
    } catch (_) {}

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        // Hero card
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFF07003B), Color(0xFF2D0090)],
              begin: Alignment.topLeft, end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(16),
          ),
          child: Column(children: [
            const Icon(Icons.people_alt_rounded, color: Colors.white, size: 40),
            const SizedBox(height: 8),
            const Text('Invite Friends, Earn Points',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 4),
            const Text('Get 500 pts + 5% commission when your friend places their first order',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white70, fontSize: 12)),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text(code,
                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold,
                        letterSpacing: 4, fontSize: 20)),
                IconButton(
                  icon: const Icon(Icons.copy_rounded, color: Colors.white),
                  onPressed: () {
                    Clipboard.setData(ClipboardData(text: code));
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Code copied!'), duration: Duration(seconds: 2)),
                    );
                  },
                ),
              ]),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.white,
                  foregroundColor: AppColors.primary,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
                icon: const Icon(Icons.share_rounded),
                label: const Text('Share Referral Link'),
                onPressed: () {
                  final text = 'Join eSahlan! Use my referral code: $code\nDownload: https://esahlan.com';
                  Clipboard.setData(ClipboardData(text: text));
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Link copied to clipboard!'), duration: Duration(seconds: 2)),
                  );
                },
              ),
            ),
          ]),
        ),
        const SizedBox(height: 16),

        // Stats
        Row(children: [
          _StatCard(label: 'Total\nReferred', value: '$total', color: Colors.blue),
          const SizedBox(width: 8),
          _StatCard(label: 'Rewarded', value: '$rewarded', color: Colors.green),
          const SizedBox(width: 8),
          _StatCard(label: 'Pending', value: '$pending', color: Colors.orange),
          const SizedBox(width: 8),
          _StatCard(label: 'Points\nEarned', value: '$earned', color: AppColors.primary),
        ]),
        const SizedBox(height: 16),

        // How it works
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF1E1E2E) : Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: isDark ? Colors.white12 : Colors.black12),
          ),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('How It Works',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14,
                    color: isDark ? Colors.white : const Color(0xFF1A1A2E))),
            const SizedBox(height: 12),
            _Step(n: 1, text: 'Share your referral code with friends'),
            _Step(n: 2, text: 'Friend registers using your code'),
            _Step(n: 3, text: 'Friend places their first order (min \$5)'),
            _Step(n: 4, text: 'You earn 500 pts + 5% of their order value as bonus pts'),
            _Step(n: 5, text: 'Level 2: if your referrer referred you, they also earn 2%'),
          ]),
        ),
        const SizedBox(height: 16),

        // Referral list
        Text('Your Referrals (${referrals.length})',
            style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15,
                color: isDark ? Colors.white : const Color(0xFF1A1A2E))),
        const SizedBox(height: 8),
        if (referrals.isNotEmpty)
          ...referrals.map((item) => Builder(builder: (ctx) {
            try {
              final isRewarded = item['status']?.toString() == 'rewarded';
              final name       = item['name']?.toString() ?? '?';
              final createdAt  = item['created_at']?.toString() ?? '';
              final rewardPts  = (item['reward_amount'] is num)
                  ? (item['reward_amount'] as num).toInt()
                  : int.tryParse(item['reward_amount']?.toString() ?? '') ?? 0;

              return Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: isDark ? const Color(0xFF1E1E2E) : Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: isDark ? Colors.white12 : Colors.black12),
                ),
                child: Row(children: [
                  CircleAvatar(
                    radius: 22,
                    backgroundColor: const Color(0xFF07003B).withValues(alpha: 0.12),
                    child: Text(
                      name.isNotEmpty ? name[0].toUpperCase() : '?',
                      style: const TextStyle(color: Color(0xFF07003B), fontWeight: FontWeight.bold, fontSize: 16),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14,
                        color: isDark ? Colors.white : const Color(0xFF1A1A2E))),
                    if (createdAt.length >= 10)
                      Text(createdAt.substring(0, 10),
                          style: const TextStyle(fontSize: 12, color: Colors.grey)),
                  ])),
                  Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: isRewarded ? Colors.green.withValues(alpha: 0.12) : Colors.orange.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(isRewarded ? '✓ Rewarded' : '⏳ Pending',
                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700,
                              color: isRewarded ? Colors.green : Colors.orange)),
                    ),
                    if (rewardPts > 0) ...[
                      const SizedBox(height: 4),
                      Text('+$rewardPts pts',
                          style: const TextStyle(fontSize: 12, color: Color(0xFF07003B), fontWeight: FontWeight.bold)),
                    ],
                  ]),
                ]),
              );
            } catch (e) {
              return Container(
                margin: const EdgeInsets.only(bottom: 8),
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(color: Colors.red.shade50, borderRadius: BorderRadius.circular(8)),
                child: Text('Error: $e\nData: $item', style: const TextStyle(color: Colors.red, fontSize: 11)),
              );
            }
          }))
        else
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 32),
            decoration: BoxDecoration(
              color: AppColors.primary.withValues(alpha: 0.04),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.primary.withValues(alpha: 0.12)),
            ),
            child: const Column(children: [
              Icon(Icons.person_add_alt_rounded, size: 48, color: AppColors.primary),
              SizedBox(height: 10),
              Text('No referrals yet',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: Color(0xFF1A1A2E))),
              SizedBox(height: 4),
              Text('Share your code and start earning!',
                  style: TextStyle(fontSize: 13, color: Colors.grey)),
            ]),
          ),
        const SizedBox(height: 24),
      ]),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label, value;
  final Color color;
  const _StatCard({required this.label, required this.value, required this.color});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: color.withValues(alpha: 0.2)),
        ),
        child: Column(children: [
          Text(value, style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: color)),
          const SizedBox(height: 2),
          Text(label, textAlign: TextAlign.center,
              style: TextStyle(fontSize: 9, color: color.withValues(alpha: 0.8))),
        ]),
      ),
    );
  }
}

class _Step extends StatelessWidget {
  final int n;
  final String text;
  const _Step({required this.n, required this.text});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(children: [
        CircleAvatar(radius: 12, backgroundColor: AppColors.primary,
            child: Text('$n', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold))),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: const TextStyle(fontSize: 13))),
      ]),
    );
  }
}

class _ReferralTile extends StatelessWidget {
  final Map<String, dynamic> item;
  final bool isDark;
  const _ReferralTile({required this.item, required this.isDark});

  @override
  Widget build(BuildContext context) {
    final isRewarded = item['status']?.toString() == 'rewarded';
    final name       = item['name']?.toString() ?? '?';
    final createdAt  = item['created_at']?.toString() ?? '';
    final rewardPts  = (item['reward_amount'] as num?)?.toInt() ?? 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1E1E2E) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: isDark ? Colors.white12 : Colors.black12),
      ),
      child: Row(children: [
        CircleAvatar(
          radius: 22,
          backgroundColor: AppColors.primary.withValues(alpha: 0.12),
          child: Text(
            name.isNotEmpty ? name[0].toUpperCase() : '?',
            style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.bold, fontSize: 16),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14,
              color: isDark ? Colors.white : const Color(0xFF1A1A2E))),
          if (createdAt.length >= 10)
            Text(createdAt.substring(0, 10),
                style: const TextStyle(fontSize: 12, color: Colors.grey)),
        ])),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: isRewarded ? Colors.green.withValues(alpha: 0.12) : Colors.orange.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Text(isRewarded ? '✓ Rewarded' : '⏳ Pending',
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700,
                    color: isRewarded ? Colors.green : Colors.orange)),
          ),
          if (rewardPts > 0) ...[
            const SizedBox(height: 4),
            Text('+$rewardPts pts',
                style: const TextStyle(fontSize: 12, color: AppColors.primary, fontWeight: FontWeight.bold)),
          ],
        ]),
      ]),
    );
  }
}
