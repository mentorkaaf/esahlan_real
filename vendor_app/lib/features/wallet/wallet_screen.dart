import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/services/vendor_repository.dart';
import '../../core/theme/vc.dart';

final _walletProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) => VendorRepository.instance.wallet());
final _txnProvider    = FutureProvider.autoDispose<Map<String, dynamic>>((ref) => VendorRepository.instance.walletTransactions());

class WalletScreen extends ConsumerWidget {
  const WalletScreen({super.key});

  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final wallet = ref.watch(_walletProvider);
    final txns   = ref.watch(_txnProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Wallet'),
        actions: [IconButton(icon: Icon(Icons.refresh_rounded, color: context.vcTextSec), onPressed: () { ref.invalidate(_walletProvider); ref.invalidate(_txnProvider); })]),
      body: wallet.when(
        loading: () => const Center(child: CircularProgressIndicator(color: VC.orange)),
        error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: VC.red))),
        data: (d) => RefreshIndicator(
          color: VC.orange,
          onRefresh: () async { ref.invalidate(_walletProvider); ref.invalidate(_txnProvider); },
          child: ListView(padding: const EdgeInsets.all(16), children: [
            // Balance card — always dark gradient (branded element)
            Container(
              padding: const EdgeInsets.all(28),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [Color(0xFF0F1B30), Color(0xFF1A2A4A)], begin: Alignment.topLeft, end: Alignment.bottomRight),
                borderRadius: BorderRadius.circular(24),
                boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.3), blurRadius: 24)],
              ),
              child: Column(children: [
                Container(width: 56, height: 56, decoration: const BoxDecoration(color: VC.orangeDim, shape: BoxShape.circle),
                  child: const Icon(Icons.account_balance_wallet_rounded, color: VC.orange, size: 28)),
                const SizedBox(height: 14),
                const Text('Available Balance', style: TextStyle(color: VC.textSec, fontSize: 13, letterSpacing: 0.5)),
                const SizedBox(height: 6),
                Text('\$${_fmt(d['stats']?['balance'] ?? d['wallet']?['balance'])}',
                  style: const TextStyle(color: Colors.white, fontSize: 44, fontWeight: FontWeight.w900, letterSpacing: -1)),
              ]),
            ),
            const SizedBox(height: 16),

            // Actions
            Row(children: [
              Expanded(child: _ActionBtn(Icons.arrow_upward_rounded, 'Withdraw', VC.green, () {
                _showWithdrawDialog(context, ref, double.tryParse('${d['stats']?['balance'] ?? d['wallet']?['balance'] ?? 0}') ?? 0);
              })),
              const SizedBox(width: 12),
              Expanded(child: _ActionBtn(Icons.history_rounded, 'History', VC.blue, () {
                Navigator.push(context, MaterialPageRoute(builder: (_) => const WalletHistoryScreen()));
              })),
            ]),
            const SizedBox(height: 20),

            // Stats
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: context.vcCard, borderRadius: BorderRadius.circular(18), border: Border.all(color: context.vcBorder.withValues(alpha: 0.3))),
              child: Column(children: [
                _statRow('Total Earned', '\$${_fmt(d['stats']?['total_earned'])}', VC.green, context),
                Divider(color: context.vcBorder, height: 24),
                _statRow('Pending Commission', '\$${_fmt(d['stats']?['pending_commission'])}', VC.amber, context),
                Divider(color: context.vcBorder, height: 24),
                _statRow('Total Withdrawn', '\$${_fmt(d['stats']?['total_withdrawn'])}', VC.red, context),
              ]),
            ),
            const SizedBox(height: 24),

            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('Recent Transactions', style: TextStyle(color: context.vcText, fontSize: 15, fontWeight: FontWeight.w800)),
              TextButton(onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const WalletHistoryScreen())),
                child: const Text('See All', style: TextStyle(color: VC.orange, fontSize: 12, fontWeight: FontWeight.w700))),
            ]),
            const SizedBox(height: 8),
            txns.when(
              loading: () => const Center(child: Padding(padding: EdgeInsets.all(20), child: CircularProgressIndicator(color: VC.orange))),
              error: (e, _) => Text('$e', style: const TextStyle(color: VC.red)),
              data: (txData) {
                final list = ((txData['data'] as List?) ?? []).take(5).toList();
                if (list.isEmpty) return Container(padding: const EdgeInsets.all(24), decoration: BoxDecoration(color: context.vcCard, borderRadius: BorderRadius.circular(14)),
                  child: Column(children: [Icon(Icons.receipt_long_rounded, color: context.vcTextMute, size: 36), const SizedBox(height: 8), Text('No transactions yet', style: TextStyle(color: context.vcTextMute))]));
                return Column(children: list.map<Widget>((tx) => _TxnTile(tx)).toList());
              },
            ),
            const SizedBox(height: 20),
          ]),
        ),
      ),
    );
  }

  Widget _statRow(String label, String val, Color color, BuildContext context) => Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
    Text(label, style: TextStyle(color: context.vcTextSec, fontSize: 13)),
    Text(val, style: TextStyle(color: color, fontWeight: FontWeight.w900, fontSize: 16)),
  ]);

  void _showWithdrawDialog(BuildContext context, WidgetRef ref, double balance) {
    final amountCtrl  = TextEditingController();
    final accountCtrl = TextEditingController();
    final nameCtrl    = TextEditingController();
    String method = 'waafi';
    bool loading = false;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: context.vcSurface,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (ctx) => StatefulBuilder(builder: (ctx, setState) => Padding(
        padding: EdgeInsets.only(left: 20, right: 20, top: 24, bottom: MediaQuery.of(ctx).viewInsets.bottom + 24),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Container(width: 44, height: 44, decoration: const BoxDecoration(color: VC.greenDim, shape: BoxShape.circle), child: const Icon(Icons.arrow_upward_rounded, color: VC.green, size: 22)),
            const SizedBox(width: 12),
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Request Withdrawal', style: TextStyle(color: ctx.vcText, fontSize: 16, fontWeight: FontWeight.w800)),
              Text('Available: \$${balance.toStringAsFixed(2)} · Min: \$10', style: TextStyle(color: ctx.vcTextMute, fontSize: 11)),
            ]),
          ]),
          const SizedBox(height: 20),
          Text('Amount (USD)', style: TextStyle(color: ctx.vcTextSec, fontSize: 12, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          TextField(controller: amountCtrl, keyboardType: TextInputType.numberWithOptions(decimal: true), style: TextStyle(color: ctx.vcText),
            decoration: _inputDec('\$0.00', ctx)),
          const SizedBox(height: 14),
          Text('Payment Method', style: TextStyle(color: ctx.vcTextSec, fontSize: 12, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          Container(padding: const EdgeInsets.all(4), decoration: BoxDecoration(color: ctx.vcCard, borderRadius: BorderRadius.circular(12)),
            child: Row(children: [
              for (final m in [('waafi','Waafi'), ('bank_transfer','Bank Transfer')]) Expanded(child: GestureDetector(
                onTap: () => setState(() => method = m.$1),
                child: Container(padding: const EdgeInsets.symmetric(vertical: 10),
                  decoration: BoxDecoration(color: method == m.$1 ? VC.green : Colors.transparent, borderRadius: BorderRadius.circular(9)),
                  child: Text(m.$2, textAlign: TextAlign.center,
                    style: TextStyle(color: method == m.$1 ? Colors.white : ctx.vcTextMute, fontSize: 12, fontWeight: FontWeight.w700))),
              )),
            ])),
          const SizedBox(height: 14),
          Text('Account Number', style: TextStyle(color: ctx.vcTextSec, fontSize: 12, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          TextField(controller: accountCtrl, keyboardType: TextInputType.phone, style: TextStyle(color: ctx.vcText), decoration: _inputDec('e.g. 252615000000', ctx)),
          const SizedBox(height: 14),
          Text('Account Name', style: TextStyle(color: ctx.vcTextSec, fontSize: 12, fontWeight: FontWeight.w600)),
          const SizedBox(height: 6),
          TextField(controller: nameCtrl, style: TextStyle(color: ctx.vcText), decoration: _inputDec('Full name', ctx)),
          const SizedBox(height: 20),
          SizedBox(width: double.infinity, height: 52, child: ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: VC.green, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
            onPressed: loading ? null : () async {
              final amount = double.tryParse(amountCtrl.text.trim()) ?? 0;
              if (amount < 10 || amount > balance) {
                ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(amount < 10 ? 'Minimum withdrawal is \$10' : 'Insufficient balance'), backgroundColor: VC.red));
                return;
              }
              if (accountCtrl.text.trim().isEmpty || nameCtrl.text.trim().isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Fill all fields'), backgroundColor: VC.red));
                return;
              }
              setState(() => loading = true);
              try {
                await VendorRepository.instance.withdraw(amount: amount, method: method, account: accountCtrl.text.trim(), name: nameCtrl.text.trim());
                Navigator.pop(ctx);
                ref.invalidate(_walletProvider);
                ref.invalidate(_txnProvider);
                ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Withdrawal request submitted!'), backgroundColor: VC.green));
              } catch (e) {
                setState(() => loading = false);
                ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: VC.red));
              }
            },
            child: loading ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : const Text('Submit Withdrawal', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
          )),
        ]),
      )),
    );
  }

  static InputDecoration _inputDec(String hint, BuildContext ctx) => InputDecoration(
    hintText: hint, hintStyle: TextStyle(color: ctx.vcTextMute),
    filled: true, fillColor: ctx.vcInputFill,
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: ctx.vcBorder)),
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: ctx.vcBorder)),
    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: VC.orange, width: 1.5)),
  );
}

class WalletHistoryScreen extends ConsumerStatefulWidget {
  const WalletHistoryScreen({super.key});
  @override
  ConsumerState<WalletHistoryScreen> createState() => _WalletHistoryState();
}

class _WalletHistoryState extends ConsumerState<WalletHistoryScreen> {
  final List _txns = [];
  int _page = 1;
  bool _loading = false, _hasMore = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    if (_loading || !_hasMore) return;
    setState(() => _loading = true);
    try {
      final data = await VendorRepository.instance.walletTransactions(page: _page);
      final list = data['data'] as List? ?? [];
      final meta = data['meta'] as Map? ?? {};
      setState(() {
        _txns.addAll(list); _page++;
        _hasMore = _txns.length < (meta['total'] ?? list.length);
        _loading = false;
      });
    } catch (_) { setState(() => _loading = false); }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Transaction History')),
    body: _txns.isEmpty && _loading ? const Center(child: CircularProgressIndicator(color: VC.orange))
      : _txns.isEmpty ? Center(child: Text('No transactions', style: TextStyle(color: context.vcTextMute)))
      : NotificationListener<ScrollNotification>(
          onNotification: (n) { if (n.metrics.pixels >= n.metrics.maxScrollExtent - 100) _load(); return false; },
          child: ListView.builder(
            padding: const EdgeInsets.all(14),
            itemCount: _txns.length + (_loading ? 1 : 0),
            itemBuilder: (_, i) => i == _txns.length
              ? const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator(color: VC.orange)))
              : _TxnTile(_txns[i]),
          )),
  );
}

class _TxnTile extends StatelessWidget {
  final dynamic tx;
  const _TxnTile(this.tx);
  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);
  @override
  Widget build(BuildContext context) {
    final isCredit = tx['type'] == 'credit';
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: context.vcCard, borderRadius: BorderRadius.circular(14), border: Border.all(color: context.vcBorder.withValues(alpha: 0.2))),
      child: Row(children: [
        Container(width: 40, height: 40, decoration: BoxDecoration(color: (isCredit ? VC.green : VC.red).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
          child: Icon(isCredit ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded, color: isCredit ? VC.green : VC.red, size: 20)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(tx['note'] ?? (isCredit ? 'Credit' : 'Debit'), style: TextStyle(color: context.vcText, fontWeight: FontWeight.w600, fontSize: 13), maxLines: 1, overflow: TextOverflow.ellipsis),
          Text(tx['created_at']?.toString().substring(0, 16) ?? '', style: TextStyle(color: context.vcTextMute, fontSize: 11)),
        ])),
        Text('${isCredit ? '+' : '-'}\$${_fmt(tx['amount'])}', style: TextStyle(color: isCredit ? VC.green : VC.red, fontWeight: FontWeight.w800, fontSize: 15)),
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
    decoration: BoxDecoration(color: context.vcCard, borderRadius: BorderRadius.circular(16), border: Border.all(color: context.vcBorder.withValues(alpha: 0.3))),
    child: Column(children: [
      Container(width: 48, height: 48, decoration: BoxDecoration(color: color.withValues(alpha: 0.12), shape: BoxShape.circle), child: Icon(icon, color: color, size: 22)),
      const SizedBox(height: 8),
      Text(label, style: TextStyle(color: context.vcText, fontSize: 12, fontWeight: FontWeight.w600)),
    ]),
  ));
}
