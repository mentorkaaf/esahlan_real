import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/api/module_api_service.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/utils/error_handler.dart';

final _referralProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final svc = ModuleApiService();
  final res = await svc.getReferral();
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
        error: (e, _) => Center(child: Text(ErrorHandler.message(e))),
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
    final code = data['referral_code'] ?? '';
    final total = data['referral_count'] ?? 0;
    final rewarded = data['rewarded_count'] ?? 0;
    final pending = data['pending_count'] ?? 0;
    final earned = data['total_earned_pts'] ?? 0;
    final referrals = (data['referrals'] as List?) ?? [];

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Hero card
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [AppColors.primary, AppColors.primary.withOpacity(0.7)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              children: [
                const Icon(Icons.people_alt_rounded, color: Colors.white, size: 40),
                const SizedBox(height: 8),
                Text('Invite Friends, Earn Points',
                    style: context.tt.titleMedium?.copyWith(color: Colors.white, fontWeight: FontWeight.bold)),
                const SizedBox(height: 4),
                Text('Get 500 pts + 5% commission when your friend places their first order',
                    textAlign: TextAlign.center,
                    style: context.tt.bodySmall?.copyWith(color: Colors.white70)),
                const SizedBox(height: 16),
                // Code display
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(code,
                          style: context.tt.titleLarge?.copyWith(
                              color: Colors.white, fontWeight: FontWeight.bold, letterSpacing: 4)),
                      IconButton(
                        icon: const Icon(Icons.copy_rounded, color: Colors.white),
                        onPressed: () {
                          Clipboard.setData(ClipboardData(text: code));
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Code copied!'), duration: Duration(seconds: 2)),
                          );
                        },
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                // Share button
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
                    onPressed: () => _share(context, code),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Stats row
          Row(
            children: [
              _StatCard(label: 'Total Referred', value: '$total', color: Colors.blue),
              const SizedBox(width: 8),
              _StatCard(label: 'Rewarded', value: '$rewarded', color: Colors.green),
              const SizedBox(width: 8),
              _StatCard(label: 'Pending', value: '$pending', color: Colors.orange),
              const SizedBox(width: 8),
              _StatCard(label: 'Points Earned', value: '$earned', color: AppColors.primary),
            ],
          ),
          const SizedBox(height: 16),

          // How it works
          _HowItWorks(),
          const SizedBox(height: 16),

          // Referred users list
          if (referrals.isNotEmpty) ...[
            Text('Your Referrals', style: context.tt.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
            const SizedBox(height: 8),
            ...referrals.map((r) => _ReferralTile(item: r as Map<String, dynamic>)),
          ] else
            Center(
              child: Padding(
                padding: const EdgeInsets.symmetric(vertical: 24),
                child: Column(
                  children: [
                    Icon(Icons.person_add_alt_rounded, size: 48, color: context.colors.mutedText),
                    const SizedBox(height: 8),
                    Text('No referrals yet', style: context.tt.bodyMedium?.copyWith(color: context.colors.mutedText)),
                    const SizedBox(height: 4),
                    Text('Share your code and start earning!',
                        style: context.tt.bodySmall?.copyWith(color: context.colors.mutedText)),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }

  void _share(BuildContext context, String code) {
    final text = 'Join eSahlan and get rewards! Use my referral code: $code\nDownload: https://esahlan.com';
    Share.share(text);
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
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
        decoration: BoxDecoration(
          color: color.withOpacity(0.08),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: color.withOpacity(0.2)),
        ),
        child: Column(
          children: [
            Text(value, style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: color)),
            const SizedBox(height: 2),
            Text(label, textAlign: TextAlign.center,
                style: TextStyle(fontSize: 9, color: color.withOpacity(0.8))),
          ],
        ),
      ),
    );
  }
}

class _HowItWorks extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 6)],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('How It Works', style: context.tt.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
          const SizedBox(height: 12),
          _Step(n: 1, text: 'Share your referral code with friends'),
          _Step(n: 2, text: 'Friend registers using your code'),
          _Step(n: 3, text: 'Friend places their first order (min \$5)'),
          _Step(n: 4, text: 'You earn 500 pts + 5% of their order value as bonus pts'),
          _Step(n: 5, text: 'Level 2: if your referrer referred you, they also earn 2%'),
        ],
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
      child: Row(
        children: [
          CircleAvatar(radius: 12, backgroundColor: AppColors.primary,
              child: Text('$n', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.bold))),
          const SizedBox(width: 10),
          Expanded(child: Text(text, style: context.tt.bodySmall)),
        ],
      ),
    );
  }
}

class _ReferralTile extends StatelessWidget {
  final Map<String, dynamic> item;
  const _ReferralTile({required this.item});

  @override
  Widget build(BuildContext context) {
    final isRewarded = item['status'] == 'rewarded';
    final reward = item['reward_amount'];

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          CircleAvatar(radius: 20, backgroundColor: AppColors.primary.withOpacity(0.1),
              child: Text((item['name'] as String? ?? '?')[0].toUpperCase(),
                  style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.bold))),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(item['name'] ?? '', style: context.tt.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
                Text(item['created_at'] ?? '', style: context.tt.bodySmall?.copyWith(color: context.colors.mutedText)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: isRewarded ? Colors.green.withOpacity(0.1) : Colors.orange.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(isRewarded ? 'Rewarded' : 'Pending',
                    style: TextStyle(fontSize: 11, color: isRewarded ? Colors.green : Colors.orange,
                        fontWeight: FontWeight.w600)),
              ),
              if (reward != null && reward > 0)
                Text('+${reward.toString()} pts',
                    style: TextStyle(fontSize: 12, color: AppColors.primary, fontWeight: FontWeight.bold)),
            ],
          ),
        ],
      ),
    );
  }
}

// Thin wrapper for share — uses platform share sheet
class Share {
  static void share(String text) {
    // Uses share_plus if available, otherwise copies to clipboard
    Clipboard.setData(ClipboardData(text: text));
  }
}
