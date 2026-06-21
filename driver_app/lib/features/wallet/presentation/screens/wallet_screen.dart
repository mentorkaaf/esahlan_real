import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/driver_colors.dart';
import '../../../auth/presentation/providers/auth_provider.dart';

final _walletProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.read(authRepoProvider).wallet());

class WalletScreen extends ConsumerWidget {
  const WalletScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(_walletProvider);

    return Scaffold(
      backgroundColor: DC.navy,
      appBar: AppBar(title: const Text('Wallet')),
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
        error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: DC.error))),
        data: (d) => RefreshIndicator(
          color: DC.orange,
          onRefresh: () async => ref.invalidate(_walletProvider),
          child: ListView(padding: const EdgeInsets.all(16), children: [
            // Balance card
            Container(
              padding: const EdgeInsets.all(28),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [Color(0xFF0F1B30), Color(0xFF1A2A4A)], begin: Alignment.topLeft, end: Alignment.bottomRight),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Column(children: [
                const Icon(Icons.account_balance_wallet_rounded, color: DC.orange, size: 36),
                const SizedBox(height: 12),
                const Text('Current Balance', style: TextStyle(color: DC.textSec, fontSize: 13)),
                const SizedBox(height: 4),
                Text('\$${(d['balance'] ?? 0).toStringAsFixed(2)}', style: const TextStyle(color: DC.text, fontSize: 40, fontWeight: FontWeight.w900)),
              ]),
            ),
            const SizedBox(height: 16),

            // Actions
            Row(children: [
              Expanded(child: _ActionBtn(icon: Icons.arrow_upward_rounded, label: 'Withdraw', color: DC.success, onTap: () {})),
              const SizedBox(width: 12),
              Expanded(child: _ActionBtn(icon: Icons.history_rounded, label: 'History', color: Color(0xFF3B82F6), onTap: () {})),
            ]),
            const SizedBox(height: 20),

            // Summary
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(16)),
              child: Column(children: [
                _SummaryRow('Total Earned', '\$${(d['total_earned'] ?? 0).toStringAsFixed(2)}', DC.success),
                const Divider(color: DC.divider, height: 20),
                _SummaryRow('Total Withdrawn', '\$${(d['total_withdrawn'] ?? 0).toStringAsFixed(2)}', DC.error),
                const Divider(color: DC.divider, height: 20),
                _SummaryRow('Pending Withdrawal', '\$${(d['pending_withdrawal'] ?? 0).toStringAsFixed(2)}', DC.busy),
              ]),
            ),
          ]),
        ),
      ),
    );
  }
}

class _ActionBtn extends StatelessWidget {
  final IconData icon; final String label; final Color color; final VoidCallback onTap;
  const _ActionBtn({required this.icon, required this.label, required this.color, required this.onTap});
  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 16),
      decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(14)),
      child: Column(children: [
        Container(width: 44, height: 44, decoration: BoxDecoration(color: color.withValues(alpha: 0.15), shape: BoxShape.circle),
          child: Icon(icon, color: color, size: 22)),
        const SizedBox(height: 8),
        Text(label, style: const TextStyle(color: DC.text, fontSize: 12, fontWeight: FontWeight.w600)),
      ]),
    ),
  );
}

class _SummaryRow extends StatelessWidget {
  final String label, value; final Color color;
  const _SummaryRow(this.label, this.value, this.color);
  @override
  Widget build(BuildContext context) => Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
    Text(label, style: const TextStyle(color: DC.textSec, fontSize: 13)),
    Text(value, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 15)),
  ]);
}
