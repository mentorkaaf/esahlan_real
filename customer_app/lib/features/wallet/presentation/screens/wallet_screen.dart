import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/api/module_api_service.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/utils/error_handler.dart';
import '../../../payment/waafi_pay_sheet.dart';
import '../../../payment/mobile_pay_sheet.dart';
import '../providers/wallet_provider.dart';
import '../../../../shared/widgets/wallet_pin_dialog.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../rewards/tier_widgets.dart';
import '../../../gamification/gamification_screen.dart';
import '../../../rewards/rewards_provider.dart';

// ─── Screen ──────────────────────────────────────────────────────────────────

class WalletScreen extends ConsumerStatefulWidget {
  const WalletScreen({super.key});

  @override
  ConsumerState<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends ConsumerState<WalletScreen> with RouteAware {
  int _tab = 0;
  bool _pinVerified = false;
  final _scrollCtrl = ScrollController();
  OverlayEntry? _bannerEntry;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _checkPin());
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Wire up real-time event callback once PIN is verified
    WidgetsBinding.instance.addPostFrameCallback((_) => _wireRealtime());
  }

  void _wireRealtime() {
    ref.read(walletProvider.notifier).onEvent = (type, amount, note) {
      if (!mounted) return;
      _showRealtimeBanner(type, amount, note);
    };
  }

  void _showRealtimeBanner(String type, double amount, String note) {
    _bannerEntry?.remove();
    final isCredit = type == 'credit';
    final color = isCredit ? const Color(0xFF15803D) : const Color(0xFFB91C1C);
    final icon = isCredit ? Icons.arrow_circle_down_rounded : Icons.arrow_circle_up_rounded;
    final sign = isCredit ? '+' : '-';
    final label = note.isNotEmpty ? note : (isCredit ? 'ePay Credit' : 'ePay Debit');

    _bannerEntry = OverlayEntry(
      builder: (_) => Positioned(
        top: MediaQuery.of(context).padding.top + 12,
        left: 16,
        right: 16,
        child: Material(
          color: Colors.transparent,
          child: TweenAnimationBuilder<double>(
            tween: Tween(begin: 0.0, end: 1.0),
            duration: const Duration(milliseconds: 300),
            curve: Curves.easeOutBack,
            builder: (_, v, child) => Transform.translate(
              offset: Offset(0, -20 * (1 - v)),
              child: Opacity(opacity: v, child: child),
            ),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                color: color,
                borderRadius: BorderRadius.circular(14),
                boxShadow: [BoxShadow(color: color.withValues(alpha: 0.35), blurRadius: 16, offset: const Offset(0, 4))],
              ),
              child: Row(children: [
                Icon(icon, color: Colors.white, size: 22),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                    Text('$sign\$${amount.toStringAsFixed(2)}',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16)),
                    Text(label, style: const TextStyle(color: Colors.white70, fontSize: 12), maxLines: 1, overflow: TextOverflow.ellipsis),
                  ]),
                ),
                const Icon(Icons.account_balance_wallet_rounded, color: Colors.white54, size: 18),
              ]),
            ),
          ),
        ),
      ),
    );

    Overlay.of(context).insert(_bannerEntry!);
    Future.delayed(const Duration(seconds: 4), () {
      _bannerEntry?.remove();
      _bannerEntry = null;
    });
  }

  Future<void> _checkPin() async {
    final ok = await showWalletPinDialog(context);
    if (!mounted) return;
    if (ok) {
      setState(() => _pinVerified = true);
      ref.read(walletProvider.notifier).refresh();
      _wireRealtime();
    } else {
      // User cancelled PIN — go back
      Navigator.of(context).maybePop();
    }
  }

  @override
  void dispose() {
    _bannerEntry?.remove();
    _scrollCtrl.dispose();
    super.dispose();
  }

  void _scrollToTransactions() {
    _scrollCtrl.animateTo(360, duration: const Duration(milliseconds: 400), curve: Curves.easeInOut);
  }


  @override
  Widget build(BuildContext context) {
    final walletAsync = ref.watch(walletProvider);

    if (!_pinVerified) {
      return const Scaffold(backgroundColor: AppColors.background, body: SizedBox.shrink());
    }

    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
            body: walletAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
        error: (e, _) => Center(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.account_balance_wallet_outlined, size: 56, color: AppColors.textLight),
            const SizedBox(height: 12),
            Text(AppErrorHandler.message(e), textAlign: TextAlign.center, style: const TextStyle(color: AppColors.textGrey)),
            const SizedBox(height: 16),
            ElevatedButton(onPressed: () => ref.read(walletProvider.notifier).refresh(), child: const Text('Retry')),
          ]),
        ),
        data: (wallet) => RefreshIndicator(
          color: AppColors.primary,
          onRefresh: () => ref.read(walletProvider.notifier).refresh(),
          child: CustomScrollView(
            controller: _scrollCtrl,
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              _buildSliverAppBar(wallet),
              if (wallet.isFrozen)
                SliverToBoxAdapter(child: _FrozenBanner()),
              if (wallet.pendingTopup != null)
                SliverToBoxAdapter(child: _PendingTopupBanner(topup: wallet.pendingTopup!)),
              SliverToBoxAdapter(child: _buildActions(context, wallet)),
              SliverToBoxAdapter(child: _buildStats(wallet)),
              SliverToBoxAdapter(child: _buildTabBar()),
              _buildTransactionList(wallet),
            ],
          ),
        ),
      ),
    );
  }

  // ─── Sliver App Bar (gradient card) ───────────────────────────────────────

  Widget _buildSliverAppBar(WalletData wallet) {
    return SliverAppBar(
      expandedHeight: 200,
      pinned: true,
      backgroundColor: AppColors.secondary,
      foregroundColor: Colors.white,
      title: const Text('My ePay', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18)),
      flexibleSpace: FlexibleSpaceBar(
        background: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              colors: [AppColors.secondary, Color(0xFF1a237e)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
          ),
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(24, 56, 24, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  const Text('Available Balance', style: TextStyle(color: Colors.white60, fontSize: 13)),
                  const SizedBox(height: 4),
                  Text(
                    '\$${wallet.balance.toStringAsFixed(2)}',
                    style: const TextStyle(color: Colors.white, fontSize: 38, fontWeight: FontWeight.w900, letterSpacing: -1),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  // ─── Quick action buttons ──────────────────────────────────────────────────

  Widget _buildActions(BuildContext context, WalletData wallet) {
    return Container(
      color: context.colors.cardBg,
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 8),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: [
          _ActionBtn(icon: Icons.add_circle_rounded,        label: 'Top Up',      color: AppColors.primary,       onTap: wallet.isFrozen ? null : () => _showTopUp(context)),
          _ActionBtn(icon: Icons.send_rounded,              label: 'Send',        color: const Color(0xFF7B1FA2), onTap: wallet.isFrozen ? null : () async {
            final ok = await showWalletPinDialog(context);
            if (ok && mounted) _showSend(context, wallet.balance);
          }),
          _ActionBtn(icon: Icons.arrow_circle_up_rounded,  label: 'Withdraw',    color: const Color(0xFFC62828), onTap: wallet.isFrozen ? null : () async {
            final ok = await showWalletPinDialog(context);
            if (ok && mounted) _showWithdraw(context, wallet.balance);
          }),
          _ActionBtn(icon: Icons.history_rounded,           label: 'History',     color: const Color(0xFF00695C), onTap: () { setState(() => _tab = 0); _scrollToTransactions(); }),
        ],
      ),
    );
  }

  // ─── Stats row ────────────────────────────────────────────────────────────

  Widget _buildStats(WalletData wallet) {
    // Use server-computed totals: totalDeposited = real top-ups only (refunds excluded)
    // totalSpent = net purchases after refunds (total_withdrawn reduced on refund)
    final deposited = wallet.totalDeposited;
    final spent     = wallet.totalSpent;

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 4),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)],
      ),
      child: Row(
        children: [
          Expanded(child: _StatItem(label: 'Deposited',  value: '+\$${deposited.toStringAsFixed(2)}', color: Colors.green.shade600, icon: Icons.trending_up_rounded)),
          Container(width: 1, height: 36, color: Colors.grey.shade200),
          Expanded(child: _StatItem(label: 'Net Spent',  value: '-\$${spent.toStringAsFixed(2)}',     color: Colors.red.shade600,   icon: Icons.trending_down_rounded)),
          Container(width: 1, height: 36, color: Colors.grey.shade200),
          Expanded(child: _StatItem(label: 'Transactions', value: '${wallet.transactions.length}',    color: AppColors.primary,     icon: Icons.receipt_long_rounded)),
        ],
      ),
    );
  }

  // ─── Tab filter ───────────────────────────────────────────────────────────

  Widget _buildTabBar() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 8),
      child: Row(
        children: [
          Expanded(child: Text('Transactions', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800, color: context.isDark ? AppColors.primary : context.colors.navyText))),
          _TabChip(label: 'All',    selected: _tab == 0, onTap: () => setState(() => _tab = 0)),
          const SizedBox(width: 6),
          _TabChip(label: 'In',     selected: _tab == 1, color: Colors.green, onTap: () => setState(() => _tab = 1)),
          const SizedBox(width: 6),
          _TabChip(label: 'Out',    selected: _tab == 2, color: Colors.red,   onTap: () => setState(() => _tab = 2)),
        ],
      ),
    );
  }

  // ─── Transaction list ─────────────────────────────────────────────────────

  Widget _buildTransactionList(WalletData wallet) {
    final filtered = wallet.transactions.where((t) {
      // "In" tab = real deposits (excludes refunds)
      if (_tab == 1) return t['type'] == 'credit' && t['payment_method'] != 'refund';
      // "Out" tab = purchases/withdrawals only (debits)
      if (_tab == 2) return t['type'] == 'debit';
      return true;
    }).toList();

    if (filtered.isEmpty) {
      return SliverToBoxAdapter(
        child: Padding(
          padding: const EdgeInsets.all(48),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.receipt_long_rounded, size: 56, color: Colors.grey.shade300),
            const SizedBox(height: 12),
            Text('No transactions yet', style: TextStyle(fontWeight: FontWeight.w700, color: context.colors.navyText)),
            const SizedBox(height: 4),
            const Text('Top up your ePay to get started', style: TextStyle(color: AppColors.textGrey, fontSize: 13)),
          ]),
        ),
      );
    }

    return SliverList(
      delegate: SliverChildBuilderDelegate(
        (_, i) {
          if (i >= filtered.length) return const SizedBox(height: 24);
          return _TransactionTile(tx: filtered[i]);
        },
        childCount: filtered.length + 1,
      ),
    );
  }

  // ─── Top Up sheet ─────────────────────────────────────────────────────────

  void _showTopUp(BuildContext context) {
    double amount = 0;
    String method = 'waafi'; // 'waafi' | 'mobile_pay'
    final ctrl = TextEditingController();

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useRootNavigator: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setModal) {
        return Container(
          decoration: BoxDecoration(color: context.colors.cardBg,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: EdgeInsets.only(
            left: 24, right: 24, top: 24,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + MediaQuery.of(ctx).padding.bottom + kBottomNavigationBarHeight + 24,
          ),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)))),
            SizedBox(height: 20),
            Text('Top Up ePay', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: context.colors.navyText)),
            const SizedBox(height: 4),
            const Text('Add money to your eSahlan ePay wallet', style: TextStyle(color: AppColors.textGrey, fontSize: 13)),
            const SizedBox(height: 20),
            TextField(
              controller: ctrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}'))],
              onChanged: (v) => setModal(() => amount = double.tryParse(v) ?? 0),
              style: const TextStyle(fontSize: 24, fontWeight: FontWeight.w800),
              decoration: InputDecoration(
                labelText: 'Amount (USD)',
                prefixText: '\$ ',
                prefixStyle: const TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: AppColors.primary),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.primary, width: 2)),
              ),
            ),
            const SizedBox(height: 12),
            Row(
              children: [5, 10, 20, 50].map((q) => Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 3),
                  child: OutlinedButton(
                    onPressed: () { ctrl.text = q.toString(); setModal(() => amount = q.toDouble()); },
                    style: OutlinedButton.styleFrom(
                      side: BorderSide(color: amount == q ? AppColors.primary : Colors.grey.shade300),
                      backgroundColor: amount == q ? AppColors.primary.withOpacity(0.07) : null,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                      padding: const EdgeInsets.symmetric(vertical: 8),
                    ),
                    child: Text('\$$q', style: TextStyle(color: amount == q ? AppColors.primary : AppColors.textGrey, fontWeight: FontWeight.w700, fontSize: 13)),
                  ),
                ),
              )).toList(),
            ),
            const SizedBox(height: 20),
            // Payment method selector
            Row(children: [
              Expanded(child: _TopUpMethodTile(
                label: 'Waafi Pay',
                icon: Icons.phone_android_rounded,
                color: AppColors.primary,
                selected: method == 'waafi',
                onTap: () => setModal(() => method = 'waafi'),
              )),
              const SizedBox(width: 10),
              Expanded(child: _TopUpMethodTile(
                label: 'Mobile Pay',
                icon: Icons.smartphone_rounded,
                color: const Color(0xFF2E7D32),
                selected: method == 'mobile_pay',
                onTap: () => setModal(() => method = 'mobile_pay'),
              )),
            ]),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity, height: 52,
              child: ElevatedButton.icon(
                onPressed: amount < 0.1 ? null : () async {
                  Navigator.pop(ctx);
                  if (method == 'mobile_pay') {
                    await _doMobilePayTopUp(context, amount);
                  } else {
                    await _doWaafiTopUp(context, amount);
                  }
                },
                icon: Icon(method == 'mobile_pay' ? Icons.smartphone_rounded : Icons.account_balance_wallet_rounded),
                label: Text(
                  amount >= 0.1
                    ? 'Pay \$${amount.toStringAsFixed(2)} via ${method == 'mobile_pay' ? 'Mobile Pay' : 'Waafi Pay'}'
                    : 'Enter Amount',
                  style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: method == 'mobile_pay' ? const Color(0xFF2E7D32) : AppColors.primary,
                  foregroundColor: Colors.white,
                  disabledBackgroundColor: Colors.grey.shade200,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
              ),
            ),
          ]),
        );
      }),
    );
  }

  Future<void> _doWaafiTopUp(BuildContext context, double amount) async {
    final result = await showWaafiPaySheet(
      context,
      amount: amount,
      type: 'topup',
      description: 'eSahlan ePay Top Up',
    );
    if (result?.success == true) {
      if (result!.newBalance != null) {
        ref.read(walletProvider.notifier).updateBalanceImmediate(result.newBalance!);
      } else {
        ref.read(walletProvider.notifier).refresh();
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('ePay topped up with \$${amount.toStringAsFixed(2)}!'), backgroundColor: Colors.green),
        );
      }
    }
  }

  Future<void> _doMobilePayTopUp(BuildContext context, double amount) async {
    final result = await showMobilePaySheet(
      context,
      amount: amount,
      description: 'eSahlan ePay Top Up',
    );
    if (result == null || !result.success || result.proofToken == null) return;

    try {
      final svc = ModuleApiService.create();
      final res = await svc.walletTopupMobilePay({
        'amount': amount,
        'proof_token': result.proofToken!,
      });
      if (res['success'] == true) {
        final newBalance = (res['balance'] as num?)?.toDouble();
        if (newBalance != null) {
          ref.read(walletProvider.notifier).updateBalanceImmediate(newBalance);
        } else {
          ref.read(walletProvider.notifier).refresh();
        }
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('ePay topped up with \$${amount.toStringAsFixed(2)}!'), backgroundColor: Colors.green),
          );
        }
      } else {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(res['message'] ?? 'Top-up failed'), backgroundColor: Colors.red),
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppErrorHandler.message(e)), backgroundColor: Colors.red),
        );
      }
    }
  }

  // ─── Send Money sheet ─────────────────────────────────────────────────────

  void _showSend(BuildContext context, double currentBalance) {
    final phoneCtrl = TextEditingController();
    final amtCtrl   = TextEditingController();
    bool loading = false;
    String? error;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useRootNavigator: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setModal) {
        return Container(
          decoration: BoxDecoration(color: context.colors.cardBg,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: EdgeInsets.only(left: 24, right: 24, top: 24, bottom: MediaQuery.of(ctx).viewInsets.bottom + 24),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)))),
            SizedBox(height: 20),
            Text('Send Money', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: context.colors.navyText)),
            const SizedBox(height: 4),
            Text('Balance: \$${currentBalance.toStringAsFixed(2)}', style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 13)),
            const SizedBox(height: 20),
            TextField(
              controller: phoneCtrl,
              keyboardType: TextInputType.phone,
              decoration: InputDecoration(
                labelText: 'Recipient Phone Number',
                prefixIcon: const Icon(Icons.person_rounded, color: AppColors.primary),
                prefixText: '+252 ',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.primary, width: 2)),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: amtCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}'))],
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
              decoration: InputDecoration(
                labelText: 'Amount (USD)',
                prefixText: '\$ ',
                prefixStyle: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: AppColors.primary),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.primary, width: 2)),
              ),
            ),
            if (error != null) ...[
              const SizedBox(height: 8),
              Text(error!, style: TextStyle(color: Colors.red.shade700, fontSize: 13)),
            ],
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity, height: 52,
              child: ElevatedButton.icon(
                onPressed: loading ? null : () async {
                  final phone  = phoneCtrl.text.trim();
                  final amount = double.tryParse(amtCtrl.text.trim()) ?? 0;
                  if (phone.length < 9) { setModal(() => error = 'Enter a valid phone number'); return; }
                  if (amount < 0.01)    { setModal(() => error = 'Enter a valid amount'); return; }
                  if (amount > currentBalance) { setModal(() => error = 'Insufficient balance'); return; }

                  setModal(() { loading = true; error = null; });
                  try {
                    final svc = ModuleApiService.create();
                    // Include country code so backend finds the user (stored as +252XXXXXXXXX)
                    final fullPhone = '252$phone';
                    final res = await svc.walletSend({'phone': fullPhone, 'amount': amount});
                    if (res['success'] == true) {
                      Navigator.pop(ctx);
                      ref.read(walletProvider.notifier).refresh();
                      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                        SnackBar(content: Text('Sent \$${amount.toStringAsFixed(2)} successfully!'), backgroundColor: Colors.green),
                      );
                    } else {
                      setModal(() { loading = false; error = res['message'] ?? 'Transfer failed'; });
                    }
                  } catch (e) {
                    setModal(() { loading = false; error = AppErrorHandler.message(e); });
                  }
                },
                icon: loading ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.send_rounded),
                label: const Text('Send Money', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF7B1FA2),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
              ),
            ),
          ]),
        );
      }),
    );
  }

  // ─── Convert Points to Wallet ─────────────────────────────────────────────

  void _showConvertPoints(BuildContext context, int pointsBalance) {
    final svc = ModuleApiService.create();
    final rewards = ref.read(rewardsProvider).valueOrNull;
    final ptsToDollar = rewards?.pointsToDollar ?? 100;
    const minPts = 500;

    int ptsToRedeem = 0;
    bool converting = false;
    String? error;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useRootNavigator: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(builder: (ctx, ss) {
        final dollarValue = ptsToRedeem / ptsToDollar;
        return Container(
          padding: EdgeInsets.fromLTRB(20, 24, 20, MediaQuery.of(ctx).viewInsets.bottom + 28),
          decoration: BoxDecoration(
            color: context.colors.cardBg,
            borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Container(width: 40, height: 40,
                decoration: BoxDecoration(color: const Color(0xFFF59E0B).withOpacity(0.12), shape: BoxShape.circle),
                child: const Icon(Icons.currency_exchange_rounded, color: Color(0xFFF59E0B), size: 22)),
              const SizedBox(width: 12),
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Convert Points to Wallet', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
                Text('$ptsToDollar pts = \$1.00  •  Min $minPts pts', style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
              ]),
            ]),
            const SizedBox(height: 20),
            Text('Available: $pointsBalance pts', style: TextStyle(fontSize: 13, color: context.colors.mutedText)),
            const SizedBox(height: 8),
            TextField(
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                hintText: 'Enter points (min $minPts)',
                suffixText: 'pts',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                filled: true, fillColor: context.colors.inputFill,
              ),
              onChanged: (v) => ss(() {
                ptsToRedeem = int.tryParse(v) ?? 0;
                error = null;
              }),
            ),
            if (ptsToRedeem > 0) ...[
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(color: const Color(0xFFF59E0B).withOpacity(0.1), borderRadius: BorderRadius.circular(10)),
                child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  Text('You will receive:', style: TextStyle(fontSize: 13, color: context.colors.mutedText)),
                  Text('\$${dollarValue.toStringAsFixed(2)}', style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: Color(0xFFF59E0B))),
                ]),
              ),
            ],
            if (error != null) ...[
              const SizedBox(height: 8),
              Text(error!, style: const TextStyle(color: Colors.red, fontSize: 12)),
            ],
            const SizedBox(height: 20),
            SizedBox(width: double.infinity,
              child: ElevatedButton(
                onPressed: converting || ptsToRedeem < minPts || ptsToRedeem > pointsBalance ? null : () async {
                  ss(() { converting = true; error = null; });
                  try {
                    await svc.redeemPointsToWallet(ptsToRedeem);
                    ref.invalidate(walletProvider);
                    ref.invalidate(rewardsProvider);
                    if (ctx.mounted) {
                      Navigator.pop(ctx);
                      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                        content: Text('Successfully converted $ptsToRedeem pts to \$${dollarValue.toStringAsFixed(2)}!'),
                        backgroundColor: Colors.green, behavior: SnackBarBehavior.floating,
                      ));
                    }
                  } catch (e) {
                    ss(() { converting = false; error = e.toString().replaceAll('Exception: ', ''); });
                  }
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFFF59E0B),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: converting
                    ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                    : const Text('Convert to Wallet', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
              ),
            ),
          ]),
        );
      }),
    );
  }

  // ─── Withdraw sheet ───────────────────────────────────────────────────────

  void _showWithdraw(BuildContext context, double currentBalance) {
    // Auto-fill from logged-in user profile
    final user = ref.read(authStateProvider).value;
    final amtCtrl     = TextEditingController();
    final accountCtrl = TextEditingController(text: user?.phone ?? '');
    final nameCtrl    = TextEditingController(text: user?.name ?? '');
    // Backend accepts: waafi, evc, others
    String method = 'evc';
    bool loading = false;
    String? error;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      useRootNavigator: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(builder: (ctx, setModal) {
        // When switching to "others", clear auto-filled fields so user can type freely
        void switchMethod(String m) {
          setModal(() => method = m);
          if (m == 'others') {
            nameCtrl.clear();
            accountCtrl.clear();
          } else {
            nameCtrl.text = user?.name ?? '';
            accountCtrl.text = user?.phone ?? '';
          }
        }

        return SingleChildScrollView(
          child: Container(
            decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
            padding: EdgeInsets.only(left: 24, right: 24, top: 24, bottom: MediaQuery.of(ctx).viewInsets.bottom + 32),
            child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
              Center(child: Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)))),
              SizedBox(height: 20),
              Text('Withdraw', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w900, color: context.colors.navyText)),
              const SizedBox(height: 4),
              Text('Balance: \$${currentBalance.toStringAsFixed(2)}', style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 13)),
              const SizedBox(height: 20),
              Text('Payment Method', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: context.colors.navyText)),
              const SizedBox(height: 8),
              Row(
                children: [
                  {'key': 'evc',    'label': 'EVC Plus'},
                  {'key': 'waafi',  'label': 'Waafi'},
                  {'key': 'others', 'label': 'Others'},
                ].map((m) => Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 3),
                    child: GestureDetector(
                      onTap: () => switchMethod(m['key']!),
                      child: Container(
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        decoration: BoxDecoration(
                          color: method == m['key'] ? AppColors.primary : Colors.grey.shade100,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Center(child: Text(m['label']!, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: method == m['key'] ? Colors.white : AppColors.textGrey))),
                      ),
                    ),
                  ),
                )).toList(),
              ),
              if (method != 'others') ...[
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                  decoration: BoxDecoration(color: AppColors.primary.withOpacity(0.06), borderRadius: BorderRadius.circular(10)),
                  child: Row(children: [
                    const Icon(Icons.info_outline_rounded, size: 16, color: AppColors.primary),
                    const SizedBox(width: 8),
                    Flexible(child: Text('Auto-filled from your registered account', style: TextStyle(fontSize: 12, color: AppColors.primary.withOpacity(0.8)))),
                  ]),
                ),
              ],
              const SizedBox(height: 12),
              TextField(
                controller: nameCtrl,
                readOnly: method != 'others',
                decoration: InputDecoration(
                  labelText: 'Account Name',
                  prefixIcon: const Icon(Icons.person_rounded, color: AppColors.primary),
                  filled: method != 'others',
                  fillColor: method != 'others' ? Colors.grey.shade50 : null,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                  focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.primary, width: 2)),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: accountCtrl,
                keyboardType: TextInputType.phone,
                readOnly: method != 'others',
                decoration: InputDecoration(
                  labelText: 'Account Number / Phone',
                  prefixIcon: const Icon(Icons.phone_rounded, color: AppColors.primary),
                  filled: method != 'others',
                  fillColor: method != 'others' ? Colors.grey.shade50 : null,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                  focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AppColors.primary, width: 2)),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: amtCtrl,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'^\d+\.?\d{0,2}'))],
                style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
                decoration: InputDecoration(
                  labelText: 'Amount (USD)',
                  prefixText: '\$ ',
                  prefixStyle: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: Colors.red),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                  focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: Colors.red, width: 2)),
                ),
              ),
              if (error != null) ...[
                const SizedBox(height: 8),
                Text(error!, style: TextStyle(color: Colors.red.shade700, fontSize: 13)),
              ],
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity, height: 52,
                child: ElevatedButton.icon(
                  onPressed: loading ? null : () async {
                    final amount  = double.tryParse(amtCtrl.text.trim()) ?? 0;
                    final account = accountCtrl.text.trim();
                    final name    = nameCtrl.text.trim();
                    if (name.isEmpty)            { setModal(() => error = 'Enter account name'); return; }
                    if (account.isEmpty)         { setModal(() => error = 'Enter account number'); return; }
                    if (amount < 1)              { setModal(() => error = 'Minimum withdrawal is \$1'); return; }
                    if (amount > currentBalance) { setModal(() => error = 'Insufficient balance'); return; }

                    setModal(() { loading = true; error = null; });
                    try {
                      final svc = ModuleApiService.create();
                      final res = await svc.walletWithdraw({
                        'amount':         amount,
                        'payment_method': method,
                        'account_number': account,
                        'account_name':   name,
                      });
                      if (res['success'] == true) {
                        Navigator.pop(ctx);
                        ref.read(walletProvider.notifier).refresh();
                        if (mounted) ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Withdrawal request submitted. Admin will process it shortly.'), backgroundColor: Colors.orange),
                        );
                      } else {
                        setModal(() { loading = false; error = res['message'] ?? 'Withdrawal failed'; });
                      }
                    } catch (e) {
                      setModal(() { loading = false; error = AppErrorHandler.message(e); });
                    }
                  },
                  icon: loading ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.arrow_circle_up_rounded),
                  label: const Text('Request Withdrawal', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.red.shade700,
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                ),
              ),
            ]),
          ),
        );
      }),
    );
  }
}

// ─── Widgets ─────────────────────────────────────────────────────────────────

class _ActionBtn extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback? onTap;
  const _ActionBtn({required this.icon, required this.label, required this.color, this.onTap});

  @override
  Widget build(BuildContext context) {
    final disabled = onTap == null;
    final effectiveColor = disabled ? const Color(0xFFCBD5E1) : color;
    return SizedBox(
      width: 72,
      child: GestureDetector(
        onTap: onTap,
        child: Opacity(
          opacity: disabled ? 0.5 : 1.0,
          child: Column(children: [
          Container(
            width: 52, height: 52,
            decoration: BoxDecoration(
              color: effectiveColor,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(icon, color: Colors.white, size: 24),
          ),
          const SizedBox(height: 6),
          Text(label, textAlign: TextAlign.center,
              style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: context.isDark ? AppColors.primary : effectiveColor)),
        ]),
        ),
      ),
    );
  }
}

class _StatItem extends StatelessWidget {
  final String label;
  final String value;
  final Color color;
  final IconData icon;
  const _StatItem({required this.label, required this.value, required this.color, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      Icon(icon, color: color, size: 18),
      const SizedBox(height: 4),
      Text(value, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: color)),
      Text(label, style: const TextStyle(fontSize: 10, color: AppColors.textGrey)),
    ]);
  }
}

class _TabChip extends StatelessWidget {
  final String label;
  final bool selected;
  final Color? color;
  final VoidCallback onTap;
  const _TabChip({required this.label, required this.selected, this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final c = color ?? AppColors.primary;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
        decoration: BoxDecoration(
          color: selected ? c : Colors.grey.shade100,
          borderRadius: BorderRadius.circular(20),
        ),
        child: Text(label, style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: selected ? Colors.white : AppColors.textGrey)),
      ),
    );
  }
}

class _TransactionTile extends StatelessWidget {
  final Map<String, dynamic> tx;
  const _TransactionTile({required this.tx});

  static const _categoryIcons = {
    'topup':         Icons.add_circle_rounded,
    'purchase':      Icons.shopping_bag_rounded,
    'transfer_in':   Icons.call_received_rounded,
    'transfer_out':  Icons.call_made_rounded,
    'withdrawal':    Icons.arrow_circle_up_rounded,
    'refund':        Icons.replay_rounded,
    'admin_credit':  Icons.admin_panel_settings_rounded,
    'credit':        Icons.arrow_downward_rounded,
    'debit':         Icons.arrow_upward_rounded,
  };

  static const _categoryColors = {
    'topup':         Color(0xFF1565C0),
    'purchase':      Color(0xFF7B1FA2),
    'transfer_in':   Color(0xFF2E7D32),
    'transfer_out':  Color(0xFFC62828),
    'withdrawal':    Color(0xFFE65100),
    'refund':        Color(0xFF00695C),
    'admin_credit':  Color(0xFF0097A7),
    'credit':        Color(0xFF2E7D32),
    'debit':         Color(0xFFC62828),
  };

  @override
  Widget build(BuildContext context) {
    final isCredit = (tx['type'] ?? '') == 'credit';
    final amount   = (tx['amount'] as num?)?.toDouble() ?? 0;
    final category = tx['category'] ?? (isCredit ? 'credit' : 'debit');
    final color    = _categoryColors[category] ?? (isCredit ? const Color(0xFF2E7D32) : const Color(0xFFC62828));
    final icon     = _categoryIcons[category]  ?? (isCredit ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded);

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 8)],
      ),
      child: Row(children: [
        Container(
          width: 44, height: 44,
          decoration: BoxDecoration(color: color.withOpacity(context.isDark ? 0.3 : 0.1), borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, color: context.isDark ? Colors.white : color, size: 20),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(
              tx['description'] ?? tx['note'] ?? (isCredit ? 'Credit' : 'Debit'),
              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.isDark ? AppColors.primary : context.colors.navyText),
              maxLines: 1, overflow: TextOverflow.ellipsis,
            ),
            Row(children: [
              if (tx['category'] != null) ...[
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                  margin: const EdgeInsets.only(right: 6, top: 2),
                  decoration: BoxDecoration(color: color.withOpacity(0.1), borderRadius: BorderRadius.circular(6)),
                  child: Text(
                    (tx['category'] as String).replaceAll('_', ' ').toUpperCase(),
                    style: TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: context.isDark ? Colors.white : color),
                  ),
                ),
              ],
              Text(
                tx['created_at']?.toString().substring(0, 10) ?? '',
                style: const TextStyle(fontSize: 11, color: AppColors.textGrey),
              ),
            ]),
          ]),
        ),
        Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
          Text(
            '${isCredit ? '+' : '-'}\$${amount.toStringAsFixed(2)}',
            style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15, color: context.isDark ? Colors.white : color),
          ),
          if (tx['balance_after'] != null)
            Text(
              'bal: \$${(tx['balance_after'] as num).toStringAsFixed(2)}',
              style: TextStyle(fontSize: 10, color: context.isDark ? Colors.white70 : AppColors.textGrey),
            ),
        ]),
      ]),
    );
  }
}

class _TopUpMethodTile extends StatelessWidget {
  final String label;
  final IconData icon;
  final Color color;
  final bool selected;
  final VoidCallback onTap;
  const _TopUpMethodTile({required this.label, required this.icon, required this.color, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 10),
        decoration: BoxDecoration(
          color: selected ? color.withOpacity(0.1) : context.colors.surfaceBg,
          border: Border.all(color: selected ? color : Colors.grey.shade300, width: selected ? 2 : 1),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(icon, color: selected ? color : AppColors.textGrey, size: 18),
          const SizedBox(width: 6),
          Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: selected ? color : AppColors.textGrey)),
        ]),
      ),
    );
  }
}

// ─── Frozen Wallet Banner ─────────────────────────────────────────────────────

class _FrozenBanner extends StatelessWidget {
  const _FrozenBanner();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFFEF2F2),
        border: Border.all(color: const Color(0xFFFECACA)),
        borderRadius: BorderRadius.circular(12),
      ),
      child: const Row(children: [
        Icon(Icons.lock_rounded, color: Color(0xFFDC2626), size: 20),
        SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Wallet Frozen', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: Color(0xFFDC2626))),
          SizedBox(height: 2),
          Text('Your wallet has been frozen by admin. Top-up, transfers and withdrawals are disabled. Contact support to resolve.',
              style: TextStyle(fontSize: 11.5, color: Color(0xFFB91C1C), height: 1.4)),
        ])),
      ]),
    );
  }
}

// ─── Pending Top-up Banner ────────────────────────────────────────────────────

class _PendingTopupBanner extends StatelessWidget {
  final Map<String, dynamic> topup;
  const _PendingTopupBanner({required this.topup});

  @override
  Widget build(BuildContext context) {
    final amount = (topup['amount'] as num?)?.toDouble() ?? 0;
    final date = (topup['created_at'] as String? ?? '').substring(0, 10);
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 0),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFBEB),
        border: Border.all(color: const Color(0xFFFDE68A)),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(children: [
        const Icon(Icons.access_time_rounded, color: Color(0xFFD97706), size: 20),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Top-up Pending: \$${amount.toStringAsFixed(2)}',
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: Color(0xFFB45309))),
          const SizedBox(height: 2),
          Text('Submitted $date · Admin is reviewing your Mobile Pay proof. Your balance will be credited once approved.',
              style: const TextStyle(fontSize: 11.5, color: Color(0xFF92400E), height: 1.4)),
        ])),
      ]),
    );
  }
}
