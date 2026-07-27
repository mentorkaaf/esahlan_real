import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api/module_api_service.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/theme_x.dart';
import '../../core/utils/error_handler.dart';

// ── Provider ──────────────────────────────────────────────────────────────────

final _affiliateProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final res = await ModuleApiService.create().getAffiliateDashboard();
  return (res['data'] as Map<String, dynamic>?) ?? {};
});

// ── Screen ────────────────────────────────────────────────────────────────────

class AffiliateScreen extends ConsumerWidget {
  const AffiliateScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(_affiliateProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Affiliate Program'), centerTitle: true),
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error:   (e, _) => Center(child: Text(AppErrorHandler.message(e))),
        data:    (d) => d['has_affiliate'] == true
            ? _Dashboard(data: d, onRefresh: () => ref.refresh(_affiliateProvider))
            : _ApplyScreen(onApplied: () => ref.refresh(_affiliateProvider)),
      ),
    );
  }
}

// ── Apply screen (not yet an affiliate) ───────────────────────────────────────

class _ApplyScreen extends StatefulWidget {
  final VoidCallback onApplied;
  const _ApplyScreen({required this.onApplied});

  @override
  State<_ApplyScreen> createState() => _ApplyScreenState();
}

class _ApplyScreenState extends State<_ApplyScreen> {
  bool _loading = false;

  Future<void> _apply() async {
    setState(() => _loading = true);
    try {
      final res = await ModuleApiService.create().applyAffiliate();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res['message'] ?? 'Applied!')));
        widget.onApplied();
      }
    } catch (e) {
      if (mounted) AppErrorHandler.show(context, e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [AppColors.primary, AppColors.primary.withAlpha(160)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(16),
            ),
            child: Column(
              children: [
                const Text('🤝', style: TextStyle(fontSize: 52)),
                const SizedBox(height: 12),
                Text('Become an Affiliate',
                    style: context.tt.headlineSmall?.copyWith(color: Colors.white, fontWeight: FontWeight.bold)),
                const SizedBox(height: 8),
                Text(
                  'Earn ongoing commission on every order placed by users you bring to eSahlan.',
                  textAlign: TextAlign.center,
                  style: context.tt.bodyMedium?.copyWith(color: Colors.white70),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),

          // Benefits
          _BenefitCard(
            icon: Icons.repeat_rounded,
            title: 'Recurring Commission',
            body: 'Earn 3% of every order placed by your referred users — not just the first one.',
          ),
          _BenefitCard(
            icon: Icons.account_balance_wallet_rounded,
            title: 'Cash Payouts',
            body: 'Convert your earned points to wallet credit. Request when you hit 1,000 pts.',
          ),
          _BenefitCard(
            icon: Icons.bar_chart_rounded,
            title: 'Real-time Dashboard',
            body: 'Track clicks, conversions, and earnings in your personal affiliate dashboard.',
          ),
          _BenefitCard(
            icon: Icons.link_rounded,
            title: 'Unique Affiliate Code',
            body: 'Share your code on social media, blogs, or with friends — works anywhere.',
          ),

          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _loading ? null : _apply,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
              minimumSize: const Size.fromHeight(52),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            child: _loading
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : const Text('Apply Now', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
          ),
          const SizedBox(height: 12),
          Text(
            'Applications are reviewed within 24 hours. You will be notified when approved.',
            textAlign: TextAlign.center,
            style: context.tt.bodySmall?.copyWith(color: context.colors.mutedText),
          ),
        ],
      ),
    );
  }
}

// ── Dashboard (active affiliate) ───────────────────────────────────────────────

class _Dashboard extends StatefulWidget {
  final Map<String, dynamic> data;
  final VoidCallback onRefresh;
  const _Dashboard({required this.data, required this.onRefresh});

  @override
  State<_Dashboard> createState() => _DashboardState();
}

class _DashboardState extends State<_Dashboard> {
  bool _payoutLoading = false;

  Future<void> _requestPayout(int pts) async {
    setState(() => _payoutLoading = true);
    try {
      final res = await ModuleApiService.create().requestAffiliatePayout({'points': pts});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res['message'] ?? 'Submitted!')));
        widget.onRefresh();
      }
    } catch (e) {
      if (mounted) AppErrorHandler.show(context, e);
    } finally {
      if (mounted) setState(() => _payoutLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final d           = widget.data;
    final code        = d['code'] as String? ?? '';
    final status      = d['status'] as String? ?? 'pending';
    final balance     = (d['points_balance'] as num?)?.toInt() ?? 0;
    final minPayout   = (d['min_payout_pts'] as num?)?.toInt() ?? 1000;
    final canPayout   = d['can_request_payout'] == true;
    final hasPending  = d['has_pending_payout'] == true;
    final conversions = d['conversions'] != null
        ? List<dynamic>.from(d['conversions'] as Iterable)
        : <dynamic>[];
    final payouts = d['payouts'] != null
        ? List<dynamic>.from(d['payouts'] as Iterable)
        : <dynamic>[];

    return RefreshIndicator(
      onRefresh: () async => widget.onRefresh(),
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Status banner
            if (status == 'pending')
              Container(
                padding: const EdgeInsets.all(12),
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(
                  color: Colors.orange.withAlpha(20),
                  border: Border.all(color: Colors.orange.withAlpha(80)),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Row(
                  children: [
                    Icon(Icons.access_time_rounded, color: Colors.orange),
                    SizedBox(width: 8),
                    Expanded(child: Text('Your affiliate application is under review. You\'ll be notified when approved.',
                        style: TextStyle(color: Colors.orange, fontSize: 13))),
                  ],
                ),
              ),

            // Code card
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [AppColors.primary, AppColors.primary.withAlpha(170)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(
                children: [
                  Text('Your Affiliate Code', style: context.tt.bodySmall?.copyWith(color: Colors.white70)),
                  const SizedBox(height: 6),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(code, style: const TextStyle(
                          color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold, letterSpacing: 5)),
                      const SizedBox(width: 8),
                      IconButton(
                        icon: const Icon(Icons.copy_rounded, color: Colors.white70),
                        onPressed: () {
                          Clipboard.setData(ClipboardData(text: code));
                          ScaffoldMessenger.of(context).showSnackBar(
                              const SnackBar(content: Text('Code copied!'), duration: Duration(seconds: 2)));
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  ElevatedButton.icon(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Colors.white,
                      foregroundColor: AppColors.primary,
                      minimumSize: const Size(double.infinity, 40),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                    ),
                    icon: const Icon(Icons.share_rounded, size: 16),
                    label: const Text('Share Link', style: TextStyle(fontWeight: FontWeight.bold)),
                    onPressed: () {
                      final text = 'Shop on eSahlan using my link! Use code $code at checkout. https://esahlan.com';
                      Clipboard.setData(ClipboardData(text: text));
                      ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Link copied to clipboard!'), duration: Duration(seconds: 2)));
                    },
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),

            // Stats grid
            Row(
              children: [
                _StatBox('Clicks',     '${d['total_clicks'] ?? 0}',      Icons.touch_app_rounded,    Colors.blue),
                const SizedBox(width: 8),
                _StatBox('Conversions','${d['total_conversions'] ?? 0}',  Icons.shopping_bag_rounded, Colors.green),
              ],
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                _StatBox('Total Earned', '${d['total_earned_pts'] ?? 0} pts', Icons.star_rounded, AppColors.primary),
                const SizedBox(width: 8),
                _StatBox('Pts Balance',  '$balance pts',                       Icons.account_balance_wallet_rounded, Colors.purple),
              ],
            ),
            const SizedBox(height: 14),

            // Payout section
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: context.colors.cardBg,
                borderRadius: BorderRadius.circular(12),
                boxShadow: [BoxShadow(color: Colors.black.withAlpha(10), blurRadius: 6)],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Payout', style: context.tt.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
                  const SizedBox(height: 4),
                  Text(
                    hasPending
                        ? 'You have a pending payout request being processed.'
                        : balance >= minPayout
                            ? 'You can request a payout for your earned points.'
                            : 'Earn ${minPayout - balance} more pts to request a payout (min $minPayout pts).',
                    style: context.tt.bodySmall?.copyWith(color: context.colors.mutedText),
                  ),
                  const SizedBox(height: 10),
                  SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: canPayout && !_payoutLoading
                          ? () => _showPayoutDialog(context, balance, minPayout)
                          : null,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      child: _payoutLoading
                          ? const SizedBox(height: 18, width: 18, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : Text(hasPending ? 'Payout Pending' : 'Request Payout'),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Recent conversions
            if (conversions.isNotEmpty) ...[
              Text('Recent Conversions', style: context.tt.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              ...conversions.take(10).map((c) {
                final item = c as Map<String, dynamic>;
                return Container(
                  margin: const EdgeInsets.only(bottom: 6),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: context.colors.cardBg,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.shopping_bag_outlined, size: 18, color: Colors.green),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Order #${item['order_id'] ?? '—'}  •  \$${(item['order_amount'] as num?)?.toStringAsFixed(2) ?? '0'}',
                          style: context.tt.bodySmall,
                        ),
                      ),
                      Text(
                        '+${item['commission_pts'] ?? 0} pts',
                        style: const TextStyle(color: Colors.green, fontWeight: FontWeight.bold, fontSize: 13),
                      ),
                    ],
                  ),
                );
              }),
            ],

            // Payout history
            if (payouts.isNotEmpty) ...[
              const SizedBox(height: 16),
              Text('Payout History', style: context.tt.titleSmall?.copyWith(fontWeight: FontWeight.bold)),
              const SizedBox(height: 8),
              ...payouts.map((p) {
                final item   = p as Map<String, dynamic>;
                final pStatus = item['status'] as String? ?? 'pending';
                final color  = pStatus == 'paid' ? Colors.green : pStatus == 'rejected' ? Colors.red : Colors.orange;
                return Container(
                  margin: const EdgeInsets.only(bottom: 6),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(8)),
                  child: Row(
                    children: [
                      Icon(Icons.payments_rounded, size: 18, color: color),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text('${item['points_requested'] ?? 0} pts → \$${(item['dollar_value'] as num?)?.toStringAsFixed(2) ?? '0'}',
                            style: context.tt.bodySmall),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(color: color.withAlpha(20), borderRadius: BorderRadius.circular(20)),
                        child: Text(pStatus, style: TextStyle(fontSize: 11, color: color, fontWeight: FontWeight.w600)),
                      ),
                    ],
                  ),
                );
              }),
            ],
          ],
        ),
      ),
    );
  }

  void _showPayoutDialog(BuildContext context, int balance, int minPts) {
    final ctrl = TextEditingController(text: '$balance');
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Request Payout'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Available: $balance pts', style: const TextStyle(color: Colors.grey, fontSize: 13)),
            const SizedBox(height: 12),
            TextField(
              controller: ctrl,
              keyboardType: TextInputType.number,
              decoration: const InputDecoration(labelText: 'Points to redeem', border: OutlineInputBorder()),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
          ElevatedButton(
            onPressed: () {
              final pts = int.tryParse(ctrl.text.trim()) ?? 0;
              Navigator.pop(ctx);
              if (pts >= minPts) _requestPayout(pts);
              else ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Min $minPts pts required')));
            },
            child: const Text('Submit'),
          ),
        ],
      ),
    );
  }
}

// ── Helper widgets ────────────────────────────────────────────────────────────

class _BenefitCard extends StatelessWidget {
  final IconData icon;
  final String title, body;
  const _BenefitCard({required this.icon, required this.title, required this.body});

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: context.colors.cardBg,
      borderRadius: BorderRadius.circular(12),
    ),
    child: Row(
      children: [
        Container(
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(color: AppColors.primary.withAlpha(20), borderRadius: BorderRadius.circular(10)),
          child: Icon(icon, color: AppColors.primary, size: 22),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title, style: context.tt.bodyMedium?.copyWith(fontWeight: FontWeight.bold)),
              Text(body, style: context.tt.bodySmall?.copyWith(color: context.colors.mutedText)),
            ],
          ),
        ),
      ],
    ),
  );
}

class _StatBox extends StatelessWidget {
  final String label, value;
  final IconData icon;
  final Color color;
  const _StatBox(this.label, this.value, this.icon, this.color);

  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withAlpha(12),
        border: Border.all(color: color.withAlpha(40)),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Icon(icon, color: color, size: 22),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(value, style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: color)),
                Text(label, style: TextStyle(fontSize: 11, color: color.withAlpha(160))),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}
