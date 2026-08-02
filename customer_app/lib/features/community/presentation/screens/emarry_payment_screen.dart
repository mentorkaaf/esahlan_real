import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/api/api_client.dart';
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

          return Column(children: [
            // Status banner
            if (status != null) _StatusBanner(status: status),
            Expanded(child: TabBarView(
              controller: _tab,
              children: [
                _PlansTab(plans: plans, status: status),
                _CreditsTab(pkgs: pkgs, status: status),
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
  const _PlansTab({required this.plans, this.status});

  @override
  Widget build(BuildContext context) {
    return ListView(padding: const EdgeInsets.all(16), children: [
      const Text('Choose your plan', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
      const SizedBox(height: 4),
      const Text('Unlimited swipes. Real connections.', style: TextStyle(color: Color(0xFF6B7280), fontSize: 13)),
      const SizedBox(height: 16),
      ...plans.map((p) => _PlanCard(plan: p, isActive: status?.plan == p.key && status?.isPremium == true)),
      const SizedBox(height: 8),
      const Center(child: Text('Cancel anytime. No hidden fees.', style: TextStyle(fontSize: 11, color: Color(0xFF9CA3AF)))),
      const SizedBox(height: 40),
    ]);
  }
}

class _PlanCard extends StatelessWidget {
  final _Plan plan;
  final bool isActive;
  const _PlanCard({required this.plan, required this.isActive});

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
                      onPressed: () => _PaymentSheet.show(context, type: 'subscription', planKey: plan.key, amount: plan.price),
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
  const _CreditsTab({required this.pkgs, this.status});

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
      Row(children: pkgs.map((p) => Expanded(child: _CreditCard(pkg: p))).toList()),
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
  const _CreditCard({required this.pkg});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _PaymentSheet.show(context, type: 'credits', pkgKey: pkg.key, amount: pkg.price),
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
  final String type;           // 'subscription' or 'credits'
  final String? planKey;
  final String? pkgKey;
  final double amount;

  const _PaymentSheet({required this.type, this.planKey, this.pkgKey, required this.amount});

  static void show(BuildContext ctx, {required String type, String? planKey, String? pkgKey, required double amount}) {
    showModalBottomSheet(
      context: ctx,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _PaymentSheet(type: type, planKey: planKey, pkgKey: pkgKey, amount: amount),
    );
  }

  @override
  ConsumerState<_PaymentSheet> createState() => _PaymentSheetState();
}

class _PaymentSheetState extends ConsumerState<_PaymentSheet> {
  String _method = 'waafi_pay';
  final _phoneCtrl = TextEditingController();
  bool _loading = false;
  String? _error;

  // WaafiPay polling
  String? _pollRef;
  Timer? _pollTimer;
  String _pollStatus = '';

  // Mobile Pay
  Map<String, dynamic>? _mobilePayInfo;
  int? _mobilePayRequestId;

  @override
  void dispose() {
    _pollTimer?.cancel();
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_method == 'waafi_pay' && _phoneCtrl.text.trim().isEmpty) {
      setState(() => _error = 'Enter your WaafiPay phone number');
      return;
    }
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

      if (_method == 'waafi_pay') {
        if (data['status'] == 'success') {
          _onSuccess();
        } else {
          // Start polling
          setState(() { _pollRef = data['reference']; _pollStatus = 'Waiting for confirmation on your phone…'; });
          _startPolling(data['reference'] as String);
        }
      } else if (_method == 'epay') {
        _onSuccess();
      } else if (_method == 'mobile_pay') {
        setState(() {
          _loading = false;
          _mobilePayInfo = Map<String, dynamic>.from(data['mobile_pay'] as Map);
          _mobilePayRequestId = data['request_id'] as int?;
        });
      }
    } catch (e) {
      setState(() { _loading = false; _error = 'Something went wrong. Try again.'; });
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
          child: _mobilePayInfo != null
              ? _MobilePayInstructions(
                  info: _mobilePayInfo!,
                  requestId: _mobilePayRequestId,
                  onDone: () { Navigator.pop(context); ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Screenshot submitted. Admin will verify shortly.'), backgroundColor: Color(0xFF3B82F6))); },
                )
              : _pollRef != null
                ? _PollingView(message: _pollStatus)
                : _PaymentForm(
                    amount: widget.amount,
                    type: widget.type,
                    method: _method,
                    phoneCtrl: _phoneCtrl,
                    error: _error,
                    loading: _loading,
                    onMethodChange: (m) => setState(() => _method = m),
                    onSubmit: _submit,
                  ),
        )),
      ),
    );
  }
}

// ─── Payment Form ─────────────────────────────────────────────────────────────

class _PaymentForm extends ConsumerWidget {
  final double amount;
  final String type, method;
  final TextEditingController phoneCtrl;
  final String? error;
  final bool loading;
  final ValueChanged<String> onMethodChange;
  final VoidCallback onSubmit;
  const _PaymentForm({required this.amount, required this.type, required this.method,
    required this.phoneCtrl, this.error, required this.loading, required this.onMethodChange, required this.onSubmit});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final statusAsync = ref.watch(emarryStatusProvider);
    final epayBalance = statusAsync.valueOrNull?.epayBalance ?? 0.0;
    final enoughEPay  = epayBalance >= amount;

    return Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
      // Handle
      Center(child: Container(width: 36, height: 4, decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2)))),
      const SizedBox(height: 16),

      // Amount header
      Row(children: [
        const Text('Total', style: TextStyle(color: Color(0xFF6B7280), fontSize: 14)),
        const Spacer(),
        Text('\$${amount.toStringAsFixed(2)}',
            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: Color(0xFF0F172A))),
      ]),
      const Divider(height: 24),

      const Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: Color(0xFF374151))),
      const SizedBox(height: 10),

      // WaafiPay
      _MethodTile(
        value: 'waafi_pay', groupValue: method,
        icon: '💳', title: 'WaafiPay', subtitle: 'Pay via WaafiPay mobile wallet',
        onChanged: onMethodChange,
      ),

      // ePay
      _MethodTile(
        value: 'epay', groupValue: method,
        icon: '🏦', title: 'ePay Wallet',
        subtitle: 'Balance: \$${epayBalance.toStringAsFixed(2)}${!enoughEPay ? '  (insufficient)' : ''}',
        onChanged: enoughEPay ? onMethodChange : null,
        disabled: !enoughEPay,
      ),

      // Mobile Pay
      _MethodTile(
        value: 'mobile_pay', groupValue: method,
        icon: '📱', title: 'Mobile Pay', subtitle: 'Pay via USSD · Admin verifies',
        onChanged: onMethodChange,
      ),

      const SizedBox(height: 14),

      // WaafiPay phone input
      if (method == 'waafi_pay') ...[
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

      if (method == 'mobile_pay') ...[
        const Text('You will receive USSD instructions to send payment.\nUpload screenshot for admin verification.',
            style: TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
        const SizedBox(height: 14),
      ],

      if (error != null)
        Container(margin: const EdgeInsets.only(bottom: 10), padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(color: const Color(0xFFFEF2F2), borderRadius: BorderRadius.circular(8)),
            child: Text(error!, style: const TextStyle(color: Color(0xFFDC2626), fontSize: 12))),

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

// ─── Mobile Pay Instructions ──────────────────────────────────────────────────

class _MobilePayInstructions extends StatefulWidget {
  final Map<String, dynamic> info;
  final int? requestId;
  final VoidCallback onDone;
  const _MobilePayInstructions({required this.info, this.requestId, required this.onDone});
  @override
  State<_MobilePayInstructions> createState() => _MobilePayInstructionsState();
}

class _MobilePayInstructionsState extends State<_MobilePayInstructions> {
  bool _uploading = false;

  Future<void> _uploadScreenshot() async {
    // TODO: Image picker integration
    // For now, just mark as done (screenshot picker to be added)
    widget.onDone();
  }

  @override
  Widget build(BuildContext context) {
    final info = widget.info;
    return Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
      Center(child: Container(width: 36, height: 4, decoration: BoxDecoration(color: const Color(0xFFE5E7EB), borderRadius: BorderRadius.circular(2)))),
      const SizedBox(height: 16),
      const Text('Mobile Pay Instructions', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: Color(0xFF0F172A))),
      const SizedBox(height: 14),

      _InstructionStep(num: '1', text: 'Dial the USSD code below on your phone'),
      Container(
        margin: const EdgeInsets.fromLTRB(8, 4, 0, 12),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(color: const Color(0xFFF8FAFC), borderRadius: BorderRadius.circular(8),
            border: Border.all(color: const Color(0xFFE5E7EB))),
        child: Row(children: [
          Expanded(child: Text(info['ussd'] ?? '', style: const TextStyle(fontFamily: 'monospace', fontSize: 14, fontWeight: FontWeight.w700))),
          IconButton(onPressed: () {}, icon: const Icon(Icons.copy_rounded, size: 16, color: kOrange), padding: EdgeInsets.zero, constraints: const BoxConstraints()),
        ]),
      ),

      _InstructionStep(num: '2', text: 'Send \$${(info['amount'] as num).toStringAsFixed(2)} to ${info['account_name']} (${info['account_number']})'),
      const SizedBox(height: 8),

      _InstructionStep(num: '3', text: 'Take a screenshot of the confirmation message'),
      const SizedBox(height: 8),

      _InstructionStep(num: '4', text: 'Upload screenshot below for admin verification'),
      const SizedBox(height: 16),

      if (info['instructions'] != null)
        Container(
          padding: const EdgeInsets.all(10),
          margin: const EdgeInsets.only(bottom: 14),
          decoration: BoxDecoration(color: const Color(0xFFFFF7ED), borderRadius: BorderRadius.circular(8)),
          child: Text(info['instructions'] as String, style: const TextStyle(fontSize: 12, color: Color(0xFF92400E))),
        ),

      SizedBox(
        width: double.infinity,
        child: ElevatedButton.icon(
          onPressed: _uploading ? null : _uploadScreenshot,
          icon: const Icon(Icons.upload_rounded, size: 18),
          label: const Text('Upload Screenshot', style: TextStyle(fontWeight: FontWeight.w700)),
          style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              padding: const EdgeInsets.symmetric(vertical: 13)),
        ),
      ),
      const SizedBox(height: 8),
      const Center(child: Text('Admin typically verifies within 30 minutes.',
          style: TextStyle(fontSize: 11, color: Color(0xFF9CA3AF)))),
    ]);
  }
}

class _InstructionStep extends StatelessWidget {
  final String num, text;
  const _InstructionStep({required this.num, required this.text});
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 4),
    child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 22, height: 22, margin: const EdgeInsets.only(right: 8, top: 1),
          decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
          child: Center(child: Text(num, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w800)))),
      Expanded(child: Text(text, style: const TextStyle(fontSize: 13, color: Color(0xFF374151)))),
    ]),
  );
}
