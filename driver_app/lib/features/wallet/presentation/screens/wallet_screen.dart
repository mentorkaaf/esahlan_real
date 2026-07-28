import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/driver_colors.dart';
import '../../../auth/presentation/providers/auth_provider.dart';

final _walletProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.read(authRepoProvider).wallet());
final _txnProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.read(authRepoProvider).walletTransactions());

class WalletScreen extends ConsumerWidget {
  const WalletScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final wallet = ref.watch(_walletProvider);
    final txns = ref.watch(_txnProvider);

    return Scaffold(
      backgroundColor: DC.navy,
      appBar: AppBar(backgroundColor: DC.navyLight, title: const Text('Wallet', style: TextStyle(fontWeight: FontWeight.w800))),
      body: wallet.when(
        loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
        error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: DC.error))),
        data: (d) => RefreshIndicator(
          color: DC.orange,
          onRefresh: () async { ref.invalidate(_walletProvider); ref.invalidate(_txnProvider); },
          child: ListView(padding: const EdgeInsets.all(16), children: [
            // Balance card
            Container(
              padding: const EdgeInsets.all(28),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [Color(0xFF0F1B30), Color(0xFF1A2A4A), Color(0xFF243355)], begin: Alignment.topLeft, end: Alignment.bottomRight),
                borderRadius: BorderRadius.circular(24),
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.3), blurRadius: 24)],
              ),
              child: Column(children: [
                Container(width: 56, height: 56, decoration: BoxDecoration(color: DC.orangeDim, shape: BoxShape.circle),
                  child: const Icon(Icons.account_balance_wallet_rounded, color: DC.orange, size: 28)),
                const SizedBox(height: 14),
                const Text('Current Balance', style: TextStyle(color: DC.textSec, fontSize: 13, letterSpacing: 0.5)),
                const SizedBox(height: 6),
                Text('\$${_fmt(d['balance'])}', style: const TextStyle(color: Colors.white, fontSize: 44, fontWeight: FontWeight.w900, letterSpacing: -1)),
              ]),
            ),
            const SizedBox(height: 16),

            // Action buttons
            Row(children: [
              Expanded(child: _ActionBtn(Icons.arrow_upward_rounded, 'Withdraw', DC.success, () {
                _showWithdrawDialog(context, ref, double.tryParse('${d['balance'] ?? 0}') ?? 0);
              })),
              const SizedBox(width: 12),
              Expanded(child: _ActionBtn(Icons.history_rounded, 'History', const Color(0xFF3B82F6), () {
                Navigator.push(context, MaterialPageRoute(builder: (_) => const WalletHistoryScreen()));
              })),
            ]),
            const SizedBox(height: 20),

            // Summary
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(18), border: Border.all(color: DC.border.withValues(alpha: 0.3))),
              child: Column(children: [
                _SummaryRow('Total Earned', '\$${_fmt(d['total_earned'])}', DC.success),
                Padding(padding: const EdgeInsets.symmetric(vertical: 12), child: Divider(color: DC.divider, height: 1)),
                _SummaryRow('Total Withdrawn', '\$${_fmt(d['total_withdrawn'])}', DC.error),
                Padding(padding: const EdgeInsets.symmetric(vertical: 12), child: Divider(color: DC.divider, height: 1)),
                _SummaryRow('Pending', '\$${_fmt(d['pending_withdrawal'])}', DC.busy),
              ]),
            ),
            const SizedBox(height: 24),

            // Transaction history preview
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              const Text('Recent Transactions', style: TextStyle(color: DC.text, fontSize: 16, fontWeight: FontWeight.w800)),
              TextButton(
                onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const WalletHistoryScreen())),
                child: const Text('See All', style: TextStyle(color: DC.orange, fontSize: 12, fontWeight: FontWeight.w700)),
              ),
            ]),
            const SizedBox(height: 8),
            txns.when(
              loading: () => const Center(child: Padding(padding: EdgeInsets.all(20), child: CircularProgressIndicator(color: DC.orange))),
              error: (e, _) => Text('$e', style: const TextStyle(color: DC.error)),
              data: (txData) {
                final list = (txData['data'] as List? ?? []).take(5).toList();
                if (list.isEmpty) return Container(
                  padding: const EdgeInsets.all(32),
                  decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(16)),
                  child: const Column(children: [
                    Icon(Icons.receipt_long_rounded, color: DC.textMuted, size: 40),
                    SizedBox(height: 8),
                    Text('No transactions yet', style: TextStyle(color: DC.textMuted)),
                  ]),
                );
                return Column(children: list.map<Widget>((tx) => _TxnTile(tx)).toList());
              },
            ),
            const SizedBox(height: 20),
          ]),
        ),
      ),
    );
  }

  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);

  void _showWithdrawDialog(BuildContext context, WidgetRef ref, double balance) {
    final amountCtrl = TextEditingController();
    final accountCtrl = TextEditingController();
    final nameCtrl = TextEditingController();
    String selectedMethod = 'waafi';
    bool loading = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: DC.navyLight,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setState) => Padding(
          padding: EdgeInsets.only(left: 20, right: 20, top: 24, bottom: MediaQuery.of(ctx).viewInsets.bottom + 24),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            // Header
            Row(children: [
              Container(width: 44, height: 44, decoration: BoxDecoration(color: DC.success.withValues(alpha: 0.12), shape: BoxShape.circle),
                child: const Icon(Icons.arrow_upward_rounded, color: DC.success, size: 22)),
              const SizedBox(width: 12),
              Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Withdraw Funds', style: TextStyle(color: DC.text, fontSize: 17, fontWeight: FontWeight.w800)),
                Text('Available: \$${balance.toStringAsFixed(2)}', style: const TextStyle(color: DC.textMuted, fontSize: 12)),
              ]),
            ]),
            const SizedBox(height: 20),

            // Amount
            _FieldLabel('Amount (USD)'),
            _Input(controller: amountCtrl, hint: '0.00', keyboardType: TextInputType.numberWithOptions(decimal: true),
              prefix: const Text('\$  ', style: TextStyle(color: DC.textMuted, fontSize: 16))),
            const SizedBox(height: 14),

            // Method
            _FieldLabel('Payment Method'),
            Container(
              padding: const EdgeInsets.all(4),
              decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(12)),
              child: Row(children: [
                for (final m in [('waafi', 'Waafi'), ('evc', 'EVC Plus'), ('bank', 'Bank')]) ...[
                  Expanded(child: GestureDetector(
                    onTap: () => setState(() => selectedMethod = m.$1),
                    child: Container(
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      decoration: BoxDecoration(
                        color: selectedMethod == m.$1 ? DC.success : Colors.transparent,
                        borderRadius: BorderRadius.circular(9),
                      ),
                      child: Text(m.$2, textAlign: TextAlign.center,
                        style: TextStyle(color: selectedMethod == m.$1 ? Colors.white : DC.textMuted, fontSize: 12, fontWeight: FontWeight.w600)),
                    ),
                  )),
                ],
              ]),
            ),
            const SizedBox(height: 14),

            // Account number
            _FieldLabel('Account Number / Phone'),
            _Input(controller: accountCtrl, hint: 'e.g. 252615000000', keyboardType: TextInputType.phone),
            const SizedBox(height: 14),

            // Account name
            _FieldLabel('Account Name'),
            _Input(controller: nameCtrl, hint: 'Full name on account'),
            const SizedBox(height: 20),

            // Submit
            SizedBox(width: double.infinity, height: 52,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(backgroundColor: DC.success, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
                onPressed: loading ? null : () async {
                  final amount = double.tryParse(amountCtrl.text.trim()) ?? 0;
                  if (amount <= 0 || amount > balance) {
                    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
                      content: Text(amount <= 0 ? 'Enter a valid amount' : 'Insufficient balance'),
                      backgroundColor: DC.error,
                    ));
                    return;
                  }
                  if (accountCtrl.text.trim().isEmpty || nameCtrl.text.trim().isEmpty) {
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Fill all fields'), backgroundColor: DC.error));
                    return;
                  }
                  setState(() => loading = true);
                  try {
                    await ref.read(authRepoProvider).withdrawRequest(
                      amount: amount,
                      method: selectedMethod,
                      accountNumber: accountCtrl.text.trim(),
                      accountName: nameCtrl.text.trim(),
                    );
                    Navigator.pop(ctx);
                    ref.invalidate(_walletProvider);
                    ref.invalidate(_txnProvider);
                    ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Withdrawal request submitted!'), backgroundColor: DC.success));
                  } catch (e) {
                    setState(() => loading = false);
                    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: DC.error));
                  }
                },
                child: loading
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : const Text('Submit Withdrawal', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
              ),
            ),
          ]),
        ),
      ),
    );
  }
}

class WalletHistoryScreen extends ConsumerStatefulWidget {
  const WalletHistoryScreen({super.key});
  @override
  ConsumerState<WalletHistoryScreen> createState() => _WalletHistoryScreenState();
}

class _WalletHistoryScreenState extends ConsumerState<WalletHistoryScreen> {
  int _page = 1;
  bool _loading = false;
  bool _hasMore = true;
  final List<dynamic> _txns = [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (_loading || !_hasMore) return;
    setState(() => _loading = true);
    try {
      final data = await ref.read(authRepoProvider).walletTransactions(page: _page);
      final list = data['data'] as List? ?? [];
      final meta = data['meta'] as Map? ?? {};
      setState(() {
        _txns.addAll(list);
        _page++;
        _hasMore = (_txns.length < (meta['total'] ?? list.length));
        _loading = false;
      });
    } catch (_) {
      setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: DC.navy,
    appBar: AppBar(backgroundColor: DC.navyLight, title: const Text('Transaction History', style: TextStyle(fontWeight: FontWeight.w800))),
    body: _txns.isEmpty && _loading
      ? const Center(child: CircularProgressIndicator(color: DC.orange))
      : _txns.isEmpty
        ? const Center(child: Text('No transactions', style: TextStyle(color: DC.textMuted)))
        : NotificationListener<ScrollNotification>(
            onNotification: (n) { if (n.metrics.pixels >= n.metrics.maxScrollExtent - 100) _load(); return false; },
            child: ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _txns.length + (_loading ? 1 : 0),
              itemBuilder: (_, i) {
                if (i == _txns.length) return const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator(color: DC.orange)));
                return _TxnTile(_txns[i]);
              },
            ),
          ),
  );
}

class _TxnTile extends StatelessWidget {
  final dynamic tx;
  const _TxnTile(this.tx);
  @override
  Widget build(BuildContext context) {
    final isCredit = tx['type'] == 'credit';
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(14), border: Border.all(color: DC.border.withValues(alpha: 0.2))),
      child: Row(children: [
        Container(width: 40, height: 40,
          decoration: BoxDecoration(color: (isCredit ? DC.success : DC.error).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
          child: Icon(isCredit ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded, color: isCredit ? DC.success : DC.error, size: 20)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(tx['note'] ?? (isCredit ? 'Credit' : 'Debit'), style: const TextStyle(color: DC.text, fontWeight: FontWeight.w600, fontSize: 13), maxLines: 1, overflow: TextOverflow.ellipsis),
          Text(tx['created_at']?.toString().substring(0, 16) ?? '', style: const TextStyle(color: DC.textMuted, fontSize: 11)),
        ])),
        Text('${isCredit ? '+' : '-'}\$${(double.tryParse('${tx['amount'] ?? 0}') ?? 0).toStringAsFixed(2)}',
          style: TextStyle(color: isCredit ? DC.success : DC.error, fontWeight: FontWeight.w800, fontSize: 15)),
      ]),
    );
  }
}

class _ActionBtn extends StatelessWidget {
  final IconData icon; final String label; final Color color; final VoidCallback onTap;
  const _ActionBtn(this.icon, this.label, this.color, this.onTap);
  @override
  Widget build(BuildContext context) => GestureDetector(onTap: onTap, child: Container(
    padding: const EdgeInsets.symmetric(vertical: 18),
    decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(16), border: Border.all(color: DC.border.withValues(alpha: 0.3))),
    child: Column(children: [
      Container(width: 48, height: 48, decoration: BoxDecoration(color: color.withValues(alpha: 0.12), shape: BoxShape.circle),
        child: Icon(icon, color: color, size: 22)),
      const SizedBox(height: 8),
      Text(label, style: const TextStyle(color: DC.text, fontSize: 12, fontWeight: FontWeight.w600)),
    ]),
  ));
}

class _SummaryRow extends StatelessWidget {
  final String label, value; final Color color;
  const _SummaryRow(this.label, this.value, this.color);
  @override
  Widget build(BuildContext context) => Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
    Text(label, style: const TextStyle(color: DC.textSec, fontSize: 13)),
    Text(value, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 16)),
  ]);
}

class _FieldLabel extends StatelessWidget {
  final String text;
  const _FieldLabel(this.text);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 6),
    child: Text(text, style: const TextStyle(color: DC.textMuted, fontSize: 12, fontWeight: FontWeight.w600)),
  );
}

class _Input extends StatelessWidget {
  final TextEditingController controller;
  final String hint;
  final TextInputType? keyboardType;
  final Widget? prefix;
  const _Input({required this.controller, required this.hint, this.keyboardType, this.prefix});
  @override
  Widget build(BuildContext context) => TextField(
    controller: controller,
    keyboardType: keyboardType,
    style: const TextStyle(color: DC.text, fontSize: 15),
    decoration: InputDecoration(
      hintText: hint,
      hintStyle: const TextStyle(color: DC.textMuted),
      prefixIcon: prefix != null ? Padding(padding: const EdgeInsets.only(left: 14, top: 14), child: prefix) : null,
      filled: true,
      fillColor: DC.card,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: DC.border.withValues(alpha: 0.3))),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: DC.border.withValues(alpha: 0.3))),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: DC.orange, width: 1.5)),
    ),
  );
}
