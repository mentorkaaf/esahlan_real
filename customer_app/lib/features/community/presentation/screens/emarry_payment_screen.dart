import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/api/api_client.dart';
import '../../../payment/mobile_pay_sheet.dart';
import 'community_shell.dart' show kOrange;

// ─── Models ──────────────────────────────────────────────────────────────────

class _Plan {
  final String key, name, color;
  final double price;
  final List<String> features;
  final int? dailySuperLikes;
  const _Plan({required this.key, required this.name, required this.color,
    required this.price, required this.features, this.dailySuperLikes});
  factory _Plan.fromJson(Map m) => _Plan(
    key: m['key'], name: m['name'], color: m['color'] ?? '#FF8A00',
    price: (m['price'] as num).toDouble(),
    features: List<String>.from(m['features'] ?? []),
    dailySuperLikes: m['daily_super_likes'],
  );
}

class _CreditPkg {
  final String key, label;
  final int credits;
  final double price;
  final String? badge;
  const _CreditPkg({required this.key, required this.label, required this.credits,
    required this.price, this.badge});
  factory _CreditPkg.fromJson(Map m) => _CreditPkg(
    key: m['key'], label: m['label'],
    credits: (m['credits'] as num).toInt(),
    price: (m['price'] as num).toDouble(),
    badge: m['badge'],
  );
}

class _EMarryStatus {
  final bool isPremium;
  final String? plan, planExpiresAt;
  final int credits, todaySwipes, freeDailySwipes;
  final int? swipesRemaining;
  final double epayBalance;
  const _EMarryStatus({required this.isPremium, this.plan, this.planExpiresAt,
    required this.credits, required this.todaySwipes, required this.freeDailySwipes,
    this.swipesRemaining, required this.epayBalance});
  factory _EMarryStatus.fromJson(Map m) => _EMarryStatus(
    isPremium: m['is_premium'] == true,
    plan: m['plan'],
    planExpiresAt: m['plan_expires_at'],
    credits: (m['credits'] as num? ?? 0).toInt(),
    todaySwipes: (m['today_swipes'] as num? ?? 0).toInt(),
    freeDailySwipes: (m['free_daily_swipes'] as num? ?? 10).toInt(),
    swipesRemaining: m['swipes_remaining'] != null ? (m['swipes_remaining'] as num).toInt() : null,
    epayBalance: (m['epay_balance'] as num? ?? 0).toDouble(),
  );
}

// ─── Providers ───────────────────────────────────────────────────────────────

final emarryPlansProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final res = await ApiClient.instance.get('/emarry/payment/plans');
  return Map<String, dynamic>.from(res.data['data'] as Map);
});

final emarryStatusProvider = FutureProvider.autoDispose<_EMarryStatus>((ref) async {
  final res = await ApiClient.instance.get('/emarry/payment/status');
  return _EMarryStatus.fromJson(Map<String, dynamic>.from(res.data['data'] as Map));
});

// ─── Main Screen ──────────────────────────────────────────────────────────────

class EMarryPaymentScreen extends ConsumerStatefulWidget {
  final bool startOnCredits;
  const EMarryPaymentScreen({super.key, this.startOnCredits = false});
  @override
  ConsumerState<EMarryPaymentScreen> createState() => _EMarryPaymentScreenState();
}

class _EMarryPaymentScreenState extends ConsumerState<EMarryPaymentScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 2, vsync: this, initialIndex: widget.startOnCredits ? 1 : 0);
    _tab.addListener(() => setState(() {}));
  }

  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final plansAsync = ref.watch(emarryPlansProvider);
    final statusAsync = ref.watch(emarryStatusProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18, color: Color(0xFF0F172A)),
          onPressed: () => Navigator.pop(context),
        ),
        title: RichText(text: const TextSpan(children: [
          TextSpan(text: 'e', style: TextStyle(color: kOrange, fontWeight: FontWeight.w900, fontSize: 20)),
          TextSpan(text: 'Marry ', style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.w900, fontSize: 20)),
          TextSpan(text: 'Premium', style: TextStyle(color: Color(0xFF6B7280), fontWeight: FontWeight.w500, fontSize: 14)),
        ])),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(44),
          child: Container(
            decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: Color(0xFFE5E7EB)))),
            child: TabBar(
              controller: _tab,
              labelColor: kOrange,
              unselectedLabelColor: const Color(0xFF9CA3AF),
              indicatorColor: kOrange,
              indicatorWeight: 2.5,
              labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
              tabs: const [Tab(text: 'Subscription'), Tab(text: 'Credits')],
            ),
          ),
        ),
      ),
      body: plansAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
        error: (e, _) => Center(child: Text('Error: $e')),
        data: (data) {
          final plans = (data['plans'] as List).map((p) => _Plan.fromJson(p as Map)).toList();
          final pkgs  = (data['credit_packages'] as List).map((p) => _CreditPkg.fromJson(p as Map)).toList();
          final status = statusAsync.valueOrNull;
          final enabledMethods = List<String>.from(data['enabled_methods'] as List? ?? ['waafi_pay', 'epay', 'mobile_pay']);
          final mpAccounts = (data['mobile_pay_accounts'] as List? ?? [])
              .map((a) => Map<String, dynamic>.from(a as Map)).toList();

          return Column(children: [
            if (status != null) _StatusBanner(status: status),
            Expanded(child: TabBarView(
              controller: _tab,
              children: [
                _PlansTab(plans: plans, status: status, enabledMethods: enabledMethods, mpAccounts: mpAccounts),
                _CreditsTab(pkgs: pkgs, status: status, enabledMethods: enabledMethods, mpAccounts: mpAccounts),
              ],
            )),
          ]);
        },
      ),
    );
  }
}

// ─── Status Banner ────────────────────────────────────────────────────────────

class _StatusBanner extends StatelessWidget {
  final _EMarryStatus status;
  const _StatusBanner({required this.status});

  @override
  Widget build(BuildContext context) {
    if (!status.isPremium) {
      final remaining = status.swipesRemaining ?? 0;
      final pct = remaining / status.freeDailySwipes;
      return Container(
        color: Colors.white,
        padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
        child: Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Free Plan  ·  ${status.credits} credits',
                style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
            const SizedBox(height: 4),
            ClipRRect(
              borderRadius: BorderRadius.circular(4),
              child: LinearProgressIndicator(
                value: pct.clamp(0.0, 1.0),
                backgroundColor: const Color(0xFFE5E7EB),
                color: pct > 0.3 ? kOrange : const Color(0xFFEF4444),
                minHeight: 5,
              ),
            ),
            const SizedBox(height: 3),
            Text('$remaining of ${status.freeDailySwipes} swipes remaining today',
                style: const TextStyle(fontSize: 11, color: Color(0xFF9CA3AF))),
          ])),
          const SizedBox(width: 12),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
            decoration: BoxDecoration(color: const Color(0xFFFFF7ED), borderRadius: BorderRadius.circular(20),
                border: Border.all(color: kOrange)),
            child: const Text('FREE', style: TextStyle(color: kOrange, fontWeight: FontWeight.w800, fontSize: 11)),
          ),
        ]),
      );
    }

    final plan = status.plan?.toUpperCase() ?? 'PREMIUM';
    final expires = status.planExpiresAt?.split('T').first ?? '';
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      decoration: const BoxDecoration(
        gradient: LinearGradient(colors: [Color(0xFFFF8A00), Color(0xFFF59E0B)]),
      ),
      child: Row(children: [
        const Icon(Icons.workspace_premium_rounded, color: Colors.white, size: 20),
        const SizedBox(width: 8),
        Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('$plan Active', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 13)),
          Text('Expires $expires  ·  ${status.credits} credits',
              style: const TextStyle(color: Colors.white70, fontSize: 11)),
        ]),
      ]),
    );
  }
}

// ─── Plans Tab ───────────────────────────────────────────────────────────────

class _PlansTab extends StatelessWidget {
  final List<_Plan> plans;
  final _EMarryStatus? status;
  final List<String> enabledMethods;
  final List<Map<String, dynamic>> mpAccounts;
  const _PlansTab({required this.plans, this.status, required this.enabledMethods, required this.mpAccounts});

  @override
  Widget build(BuildContext context) {
    return ListView(padding: const EdgeInsets.all(16), children: [
      const Text('Choose your plan', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
      const SizedBox(height: 4),
      const Text('Unlimited swipes. Real connections.', style: TextStyle(color: Color(0xFF6B7280), fontSize: 13)),
      const SizedBox(height: 16),
      ...plans.map((p) => _PlanCard(plan: p, isActive: status?.plan == p.key && status?.isPremium == true,
          enabledMethods: enabledMethods, mpAccounts: mpAccounts)),
      const SizedBox(height: 8),
      const Center(child: Text('Cancel anytime. No hidden fees.', style: TextStyle(fontSize: 11, color: Color(0xFF9CA3AF)))),
      const SizedBox(height: 40),
    ]);
  }
}

class _PlanCard extends StatelessWidget {
  final _Plan plan;
  final bool isActive;
  final List<String> enabledMethods;
  final List<Map<String, dynamic>> mpAccounts;
  const _PlanCard({required this.plan, required this.isActive, required this.enabledMethods, required this.mpAccounts});

  Color get _color => plan.key == 'gold' ? const Color(0xFFF59E0B) : kOrange;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: isActive ? _color : const Color(0xFFE5E7EB), width: isActive ? 2 : 1),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(.04), blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Header
        Container(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
          decoration: BoxDecoration(
            color: _color.withOpacity(.06),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(15)),
          ),
          child: Row(children: [
            Icon(plan.key == 'gold' ? Icons.star_rounded : Icons.workspace_premium_rounded, color: _color, size: 22),
            const SizedBox(width: 8),
            Text(plan.name, style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: _color)),
            if (plan.key == 'gold') ...[
              const SizedBox(width: 6),
              Container(padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                decoration: BoxDecoration(color: _color, borderRadius: BorderRadius.circular(10)),
                child: const Text('BEST', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800))),
            ],
            const Spacer(),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text('\$${plan.price.toStringAsFixed(2)}', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: _color)),
              const Text('/ month', style: TextStyle(fontSize: 10, color: Color(0xFF9CA3AF))),
            ]),
          ]),
        ),
        // Features
        Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            ...plan.features.map((f) => Padding(
              padding: const EdgeInsets.only(bottom: 7),
              child: Row(children: [
                Icon(Icons.check_circle_rounded, color: _color, size: 16),
                const SizedBox(width: 8),
                Text(f, style: const TextStyle(fontSize: 13, color: Color(0xFF374151))),
              ]),
            )),
            const SizedBox(height: 4),
            isActive
                ? Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(vertical: 13),
                    decoration: BoxDecoration(color: const Color(0xFFF0FDF4), borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: const Color(0xFF10B981))),
                    child: const Center(child: Text('Current Plan ✓',
                        style: TextStyle(color: Color(0xFF10B981), fontWeight: FontWeight.w700, fontSize: 14))),
                  )
                : SizedBox(
                    width: double.infinity,
                    child: ElevatedButton(
                      onPressed: () => _PaymentSheet.show(context, type: 'subscription', planKey: plan.key, amount: plan.price,
                          enabledMethods: enabledMethods, mpAccounts: mpAccounts),
                      style: ElevatedButton.styleFrom(backgroundColor: _color, foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          padding: const EdgeInsets.symmetric(vertical: 13)),
                      child: Text('Get ${plan.name}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                    ),
                  ),
          ]),
        ),
      ]),
    );
  }
}

// ─── Credits Tab ─────────────────────────────────────────────────────────────

class _CreditsTab extends StatelessWidget {
  final List<_CreditPkg> pkgs;
  final _EMarryStatus? status;
  final List<String> enabledMethods;
  final List<Map<String, dynamic>> mpAccounts;
  const _CreditsTab({required this.pkgs, this.status, required this.enabledMethods, required this.mpAccounts});

  @override
  Widget build(BuildContext context) {
    return ListView(padding: const EdgeInsets.all(16), children: [
      // Current balance
      Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: const LinearGradient(colors: [Color(0xFF8B5CF6), Color(0xFFEC4899)]),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Row(children: [
          const Icon(Icons.bolt_rounded, color: Colors.white, size: 28),
          const SizedBox(width: 10),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('${status?.credits ?? 0}', style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
            const Text('Credits Available', style: TextStyle(color: Colors.white70, fontSize: 12)),
          ]),
          const Spacer(),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            _CreditAction(icon: Icons.star_rounded, label: 'Super Like', cost: 1),
            const SizedBox(height: 4),
            _CreditAction(icon: Icons.rocket_launch_rounded, label: 'Boost', cost: 2),
            const SizedBox(height: 4),
            _CreditAction(icon: Icons.undo_rounded, label: 'Undo', cost: 1),
          ]),
        ]),
      ),
      const SizedBox(height: 16),
      const Text('Buy Credits', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
      const SizedBox(height: 10),
      Row(children: pkgs.map((p) => Expanded(child: _CreditCard(pkg: p, enabledMethods: enabledMethods, mpAccounts: mpAccounts))).toList()),
      const SizedBox(height: 16),
      Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12),
            border: Border.all(color: const Color(0xFFE5E7EB))),
        child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('How Credits Work', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
          SizedBox(height: 8),
          _InfoRow(icon: Icons.star_rounded, color: Color(0xFFF59E0B), text: 'Super Like  —  1 credit  (notifies them instantly)'),
          _InfoRow(icon: Icons.rocket_launch_rounded, color: kOrange, text: 'Profile Boost  —  2 credits  (top of Discover for 24h)'),
          _InfoRow(icon: Icons.undo_rounded, color: Color(0xFF8B5CF6), text: 'Undo Swipe  —  1 credit  (take back last pass)'),
        ]),
      ),
      const SizedBox(height: 40),
    ]);
  }
}

class _CreditAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final int cost;
  const _CreditAction({required this.icon, required this.label, required this.cost});
  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
    Icon(icon, size: 13, color: Colors.white70),
    const SizedBox(width: 3),
    Text('$label = ${cost}cr', style: const TextStyle(color: Colors.white70, fontSize: 10)),
  ]);
}

class _CreditCard extends StatelessWidget {
  final _CreditPkg pkg;
  final List<String> enabledMethods;
  final List<Map<String, dynamic>> mpAccounts;
  const _CreditCard({required this.pkg, required this.enabledMethods, required this.mpAccounts});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _PaymentSheet.show(context, type: 'credits', pkgKey: pkg.key, amount: pkg.price,
          enabledMethods: enabledMethods, mpAccounts: mpAccounts),
      child: Container(
        margin: const EdgeInsets.symmetric(horizontal: 4),
        padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: pkg.badge != null ? const Color(0xFF8B5CF6) : const Color(0xFFE5E7EB),
              width: pkg.badge != null ? 2 : 1),
          boxShadow: [BoxShadow(color: Colors.black.withOpacity(.03), blurRadius: 6)],
        ),
        child: Column(children: [
          if (pkg.badge != null)
            Container(
              margin: const EdgeInsets.only(bottom: 6),
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(color: const Color(0xFF8B5CF6), borderRadius: BorderRadius.circular(8)),
              child: Text(pkg.badge!, style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800)),
            ),
          const Icon(Icons.bolt_rounded, color: Color(0xFFF59E0B), size: 22),
          const SizedBox(height: 4),
          Text('${pkg.credits}', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
          Text('credits', style: const TextStyle(fontSize: 10, color: Color(0xFF9CA3AF))),
          const SizedBox(height: 8),
          Text('\$${pkg.price.toStringAsFixed(2)}',
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: kOrange)),
        ]),
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final IconData icon;
  final Color color;
  final String text;
  const _InfoRow({required this.icon, required this.color, required this.text});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 5),
    child: Row(children: [
      Icon(icon, color: color, size: 14),
      const SizedBox(width: 6),
      Expanded(child: Text(text, style: const TextStyle(fontSize: 11.5, color: Color(0xFF6B7280)))),
    ]),
  );
}

// ─── Payment Bottom Sheet ─────────────────────────────────────────────────────

class _PaymentSheet extends ConsumerStatefulWidget {
  final String type;
  final String? planKey;
  final String? pkgKey;
  final double amount;
  final List<String> enabledMethods;
  final List<Map<String, dynamic>> mpAccounts;

  const _PaymentSheet({
    required this.type, this.planKey, this.pkgKey, required this.amount,
    required this.enabledMethods, required this.mpAccounts,
  });

  static void show(BuildContext ctx, {
    required String type, String? planKey, String? pkgKey, required double amount,
    required List<String> enabledMethods, required List<Map<String, dynamic>> mpAccounts,
  }) {
    showModalBottomSheet(
      context: ctx,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _PaymentSheet(
        type: type, planKey: planKey, pkgKey: pkgKey, amount: amount,
        enabledMethods: enabledMethods, mpAccounts: mpAccounts,
      ),
    );
  }

  @override
  ConsumerState<_PaymentSheet> createState() => _PaymentSheetState();
}

class _PaymentSheetState extends ConsumerState<_PaymentSheet> {
  late String _method;
  final _phoneCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    // Default to first enabled method
    final methods = widget.enabledMethods;
    _method = methods.contains('waafi_pay') ? 'waafi_pay'
            : methods.isNotEmpty ? methods.first : 'waafi_pay';
  }
  bool _loading = false;
  String? _error;

  // WaafiPay polling
  String? _pollRef;
  Timer? _pollTimer;
  String _pollStatus = '';


  @override
  void dispose() {
    _pollTimer?.cancel();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_method == 'mobile_pay') {
      await _handleMobilePay();
      return;
    }
    if (_method == 'waafi_pay' && _phoneCtrl.text.trim().isEmpty) {
      setState(() => _error = 'Enter your WaafiPay phone number');
      return;
    }
    // epay: POST directly, response is instant
    setState(() { _loading = true; _error = null; });

    try {
      final endpoint = widget.type == 'subscription' ? '/emarry/payment/subscribe' : '/emarry/payment/credits/buy';
      final body = widget.type == 'subscription'
          ? {'plan': widget.planKey, 'payment_method': _method, if (_method == 'waafi_pay') 'phone': _phoneCtrl.text.trim()}
          : {'package': widget.pkgKey, 'payment_method': _method, if (_method == 'waafi_pay') 'phone': _phoneCtrl.text.trim()};

      final res = await ApiClient.instance.post(endpoint, data: body);
      final data = res.data;

      if (data['success'] != true) {
        setState(() { _loading = false; _error = data['message'] ?? 'Payment failed'; });
        return;
      }

      if (data['status'] == 'success') {
        _onSuccess();
      } else {
        setState(() { _pollRef = data['reference']; _pollStatus = 'Waiting for confirmation on your phone…'; });
        _startPolling(data['reference'] as String);
      }
    } catch (e) {
      setState(() { _loading = false; _error = 'Something went wrong. Try again.'; });
    }
  }

  Future<void> _handleMobilePay() async {
    if (!mounted) return;
    // Step 1: use existing MobilePaySheet — USSD dial + screenshot upload
    final result = await showMobilePaySheet(
      context,
      amount: widget.amount,
      description: widget.type == 'subscription' ? '${widget.planKey} Plan' : '${widget.pkgKey} Credits',
    );
    if (result == null || !result.success || result.proofToken == null) return;
    if (!mounted) return;

    // Step 2: submit to eMarry backend with proof_token
    setState(() { _loading = true; _error = null; });
    try {
      final endpoint = widget.type == 'subscription' ? '/emarry/payment/subscribe' : '/emarry/payment/credits/buy';
      final body = widget.type == 'subscription'
          ? {'plan': widget.planKey, 'payment_method': 'mobile_pay', 'proof_token': result.proofToken}
          : {'package': widget.pkgKey, 'payment_method': 'mobile_pay', 'proof_token': result.proofToken};

      final res = await ApiClient.instance.post(endpoint, data: body);
      if (!mounted) return;

      if (res.data['success'] == true) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Payment submitted! Admin will verify within 30 minutes.'),
          backgroundColor: Color(0xFF3B82F6),
          duration: Duration(seconds: 4),
        ));
      } else {
        setState(() { _loading = false; _error = res.data['message'] ?? 'Submission failed'; });
      }
    } catch (_) {
      if (mounted) setState(() { _loading = false; _error = 'Something went wrong. Try again.'; });
    }
  }

  void _startPolling(String ref) {
    int attempts = 0;
    _pollTimer = Timer.periodic(const Duration(seconds: 3), (t) async {
      if (!mounted) { t.cancel(); return; }
      attempts++;
      if (attempts > 40) { // 2 min timeout
        t.cancel();
        setState(() { _loading = false; _pollStatus = ''; _error = 'Payment timed out. Try again.'; });
        return;
      }
      try {
        final res = await ApiClient.instance.get('/emarry/payment/poll/$ref');
        final status = res.data['status'];
        if (status == 'success') { t.cancel(); _onSuccess(); }
        else if (status == 'failed') { t.cancel(); setState(() { _loading = false; _pollStatus = ''; _error = 'Payment failed.'; }); }
      } catch (_) {}
    });
  }

  void _onSuccess() {
    _pollTimer?.cancel();
    if (!mounted) return;
    Navigator.pop(context);
    ref.invalidate(emarryStatusProvider);
    ref.invalidate(emarryPlansProvider);
    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
      content: Text('Payment successful! 🎉'),
      backgroundColor: Color(0xFF10B981),
    ));
  }

  @override
  Widget build(BuildContext context) {
    final epayBalance = ref.watch(emarryStatusProvider).valueOrNull?.epayBalance ?? 0.0;
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SafeArea(
        top: false,
        child: SingleChildScrollView(child: Padding(
          padding: const EdgeInsets.all(20),
          child: _pollRef != null
                ? _PollingView(message: _pollStatus)
                : _PaymentForm(
                    amount: widget.amount,
                    type: widget.type,
                    method: _method,
                    phoneCtrl: _phoneCtrl,
                    error: _error,
                    loading: _loading,
                    epayBalance: epayBalance,
                    enabledMethods: widget.enabledMethods,
                    onMethodChange: (m) => setState(() => _method = m),
                    onSubmit: _submit,
                  ),
        )),
      ),
    );
  }
}

// ─── Payment Form ─────────────────────────────────────────────────────────────

class _PaymentForm extends StatelessWidget {
  final double amount;
  final String type, method;
  final TextEditingController phoneCtrl;
  final String? error;
  final bool loading;
  final double epayBalance;
  final List<String> enabledMethods;
  final ValueChanged<String> onMethodChange;
  final VoidCallback onSubmit;
  const _PaymentForm({required this.amount, required this.type, required this.method,
    required this.phoneCtrl, this.error, required this.loading, this.epayBalance = 0,
    required this.enabledMethods, required this.onMethodChange, required this.onSubmit});

  @override
  Widget build(BuildContext context) {
    return Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
      Center(child: Container(width: 36, height: 4, decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2)))),
      const SizedBox(height: 16),

      Row(children: [
        const Text('Total', style: TextStyle(color: Color(0xFF6B7280), fontSize: 14)),
        const Spacer(),
        Text('\$${amount.toStringAsFixed(2)}',
            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
      ]),
      const Divider(height: 24),

      const Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF374151))),
      const SizedBox(height: 10),

      if (enabledMethods.contains('waafi_pay'))
        _MethodTile(
          value: 'waafi_pay', groupValue: method,
          icon: '💳', title: 'WaafiPay', subtitle: 'Pay via WaafiPay mobile wallet',
          onChanged: onMethodChange,
        ),

      if (enabledMethods.contains('epay')) ...[
        _MethodTile(
          value: 'epay', groupValue: method,
          icon: '👛', title: 'ePay Wallet',
          subtitle: epayBalance < amount
              ? 'Balance: \$${epayBalance.toStringAsFixed(2)} · Insufficient'
              : 'Balance: \$${epayBalance.toStringAsFixed(2)} · Instant',
          onChanged: epayBalance >= amount ? onMethodChange : null,
          disabled: epayBalance < amount,
        ),
      ],

      if (enabledMethods.contains('mobile_pay'))
        _MethodTile(
          value: 'mobile_pay', groupValue: method,
          icon: '📱', title: 'Mobile Pay', subtitle: 'Pay via USSD · Admin verifies',
          onChanged: onMethodChange,
        ),

      if (enabledMethods.isEmpty)
        const Padding(
          padding: EdgeInsets.symmetric(vertical: 16),
          child: Center(child: Text('No payment methods available.\nContact support.', textAlign: TextAlign.center,
              style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 13))),
        ),

      const SizedBox(height: 14),

      if (method == 'waafi_pay' && enabledMethods.contains('waafi_pay')) ...[
        TextField(
          controller: phoneCtrl,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(
            labelText: 'WaafiPay Phone Number',
            hintText: '06xxxxxxxx',
            prefixIcon: const Icon(Icons.phone_rounded, size: 18),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
            contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          ),
        ),
        const SizedBox(height: 6),
        const Text('You will receive a confirmation prompt on your phone.',
            style: TextStyle(fontSize: 11, color: Color(0xFF9CA3AF))),
        const SizedBox(height: 14),
      ],

      if (method == 'epay') ...[
        Container(
          padding: const EdgeInsets.all(10),
          margin: const EdgeInsets.only(bottom: 14),
          decoration: BoxDecoration(color: const Color(0xFFF0F9FF), borderRadius: BorderRadius.circular(8)),
          child: const Row(children: [
            Icon(Icons.bolt_rounded, size: 15, color: Color(0xFF0369A1)),
            SizedBox(width: 8),
            Expanded(child: Text('Instant debit from your ePay wallet. No waiting required.',
                style: TextStyle(fontSize: 11, color: Color(0xFF0369A1), height: 1.4))),
          ]),
        ),
      ],

      if (method == 'mobile_pay') ...[
        Container(
          padding: const EdgeInsets.all(10),
          margin: const EdgeInsets.only(bottom: 14),
          decoration: BoxDecoration(color: const Color(0xFFF0FDF4), borderRadius: BorderRadius.circular(8)),
          child: const Row(children: [
            Icon(Icons.info_outline_rounded, size: 15, color: Color(0xFF16A34A)),
            SizedBox(width: 8),
            Expanded(child: Text('A payment sheet will open. Dial USSD, send payment, then upload screenshot.',
                style: TextStyle(fontSize: 11, color: Color(0xFF16A34A), height: 1.4))),
          ]),
        ),
      ],

      if (error != null)
        Container(margin: const EdgeInsets.only(bottom: 10), padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: const Color(0xFFFEF2F2), borderRadius: BorderRadius.circular(8)),
            child: Text(error!, style: const TextStyle(color: Color(0xFFDC2626), fontSize: 12))),

      if (enabledMethods.isNotEmpty)
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: loading ? null : onSubmit,
            style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                padding: const EdgeInsets.symmetric(vertical: 14)),
            child: loading
                ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : Text('Pay \$${amount.toStringAsFixed(2)}',
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 15)),
          ),
        ),
    ]);
  }
}

class _MethodTile extends StatelessWidget {
  final String value, groupValue, icon, title, subtitle;
  final ValueChanged<String>? onChanged;
  final bool disabled;
  const _MethodTile({required this.value, required this.groupValue, required this.icon,
    required this.title, required this.subtitle, this.onChanged, this.disabled = false});

  @override
  Widget build(BuildContext context) {
    final selected = value == groupValue;
    return GestureDetector(
      onTap: disabled ? null : () => onChanged?.call(value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFFFF7ED) : Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: selected ? kOrange : const Color(0xFFE5E7EB), width: selected ? 2 : 1),
        ),
        child: Row(children: [
          Text(icon, style: const TextStyle(fontSize: 20)),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13,
                color: disabled ? const Color(0xFF9CA3AF) : const Color(0xFF0F172A))),
            Text(subtitle, style: TextStyle(fontSize: 11,
                color: disabled ? const Color(0xFFEF4444) : const Color(0xFF9CA3AF))),
          ])),
          Radio<String>(value: value, groupValue: groupValue, onChanged: disabled ? null : (v) => onChanged?.call(v!),
              activeColor: kOrange, materialTapTargetSize: MaterialTapTargetSize.shrinkWrap),
        ]),
      ),
    );
  }
}

// ─── Polling View ─────────────────────────────────────────────────────────────

class _PollingView extends StatelessWidget {
  final String message;
  const _PollingView({required this.message});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 32),
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      const CircularProgressIndicator(color: kOrange, strokeWidth: 3),
      const SizedBox(height: 20),
      const Text('Waiting for payment…', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: Color(0xFF0F172A))),
      const SizedBox(height: 8),
      Text(message, style: const TextStyle(color: Color(0xFF6B7280), fontSize: 13), textAlign: TextAlign.center),
      const SizedBox(height: 8),
      const Text('Check your phone and confirm the payment.',
          style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 11), textAlign: TextAlign.center),
    ]),
  );
}

