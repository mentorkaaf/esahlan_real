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
              Expanded(child: _ActionBtn(Icons.arrow_upward_rounded, 'Withdraw', DC.success, () {})),
              const SizedBox(width: 12),
              Expanded(child: _ActionBtn(Icons.history_rounded, 'History', const Color(0xFF3B82F6), () {})),
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

            // Transaction history
            const Text('Recent Transactions', style: TextStyle(color: DC.text, fontSize: 16, fontWeight: FontWeight.w800)),
            const SizedBox(height: 12),
            txns.when(
              loading: () => const Center(child: Padding(padding: EdgeInsets.all(20), child: CircularProgressIndicator(color: DC.orange))),
              error: (e, _) => Text('$e', style: const TextStyle(color: DC.error)),
              data: (txData) {
                final list = txData['data'] as List? ?? [];
                if (list.isEmpty) return Container(
                  padding: const EdgeInsets.all(32),
                  decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(16)),
                  child: const Column(children: [
                    Icon(Icons.receipt_long_rounded, color: DC.textMuted, size: 40),
                    SizedBox(height: 8),
                    Text('No transactions yet', style: TextStyle(color: DC.textMuted)),
                  ]),
                );
                return Column(children: list.map<Widget>((tx) {
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
                      Text('${isCredit ? '+' : '-'}\$${_fmt(tx['amount'])}',
                        style: TextStyle(color: isCredit ? DC.success : DC.error, fontWeight: FontWeight.w800, fontSize: 15)),
                    ]),
                  );
                }).toList());
              },
            ),
            const SizedBox(height: 20),
          ]),
        ),
      ),
    );
  }

  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);
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
