import 'package:flutter/material.dart';
import '../../../core/services/agent_repository.dart';
import '../../../core/theme/vc.dart';

const _kTeal = Color(0xFF0EA5E9);
const _kGold = Color(0xFFF59E0B);

class AgentWalletScreen extends StatefulWidget {
  const AgentWalletScreen({super.key});
  @override
  State<AgentWalletScreen> createState() => _AgentWalletScreenState();
}

class _AgentWalletScreenState extends State<AgentWalletScreen> {
  Map<String, dynamic>? _data;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    try {
      final res = await AgentRepository.instance.wallet();
      if (mounted) setState(() { _data = res['data']; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg   = isDark ? VC.navy     : VC.lightBg;
    final card = isDark ? VC.navyCard : VC.lightSurface;
    final txt  = isDark ? VC.text     : const Color(0xFF1A2340);
    final sec  = isDark ? VC.textSec  : const Color(0xFF5A6B82);

    final txns = List<Map>.from(_data?['transactions'] ?? []);

    return Scaffold(
      backgroundColor: bg,
      body: RefreshIndicator(
        color: _kTeal,
        onRefresh: _load,
        child: CustomScrollView(
          slivers: [
            // ── Balance Header ─────────────────────────────────────────
            SliverToBoxAdapter(
              child: Container(
                margin: const EdgeInsets.all(16),
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft, end: Alignment.bottomRight,
                    colors: [Color(0xFF0369A1), Color(0xFF0EA5E9)],
                  ),
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: [BoxShadow(color: _kTeal.withValues(alpha: 0.3), blurRadius: 20, offset: const Offset(0, 8))],
                ),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  SafeArea(bottom: false, child: Row(children: [
                    const Icon(Icons.account_balance_wallet_rounded, color: Colors.white70, size: 20),
                    const SizedBox(width: 8),
                    const Text('Agent Wallet', style: TextStyle(color: Colors.white70, fontSize: 14)),
                    const Spacer(),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.15), borderRadius: BorderRadius.circular(20)),
                      child: const Text('USD', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
                    ),
                  ])),
                  const SizedBox(height: 16),
                  if (_loading)
                    const CircularProgressIndicator(color: Colors.white)
                  else
                    Text(
                      '\$${(_data?['balance'] ?? 0.0).toStringAsFixed(2)}',
                      style: const TextStyle(color: Colors.white, fontSize: 38, fontWeight: FontWeight.w900, letterSpacing: -1),
                    ),
                  const SizedBox(height: 4),
                  const Text('Available Balance', style: TextStyle(color: Colors.white60, fontSize: 13)),
                  const SizedBox(height: 20),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
                    child: Row(mainAxisAlignment: MainAxisAlignment.center, children: const [
                      Icon(Icons.info_outline_rounded, color: Colors.white70, size: 14),
                      SizedBox(width: 8),
                      Text('10% commission on each booking brokerage fee', style: TextStyle(color: Colors.white70, fontSize: 12)),
                    ]),
                  ),
                ]),
              ),
            ),

            // ── Transactions header ────────────────────────────────────
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: Row(children: [
                  Text('Commission History', style: TextStyle(color: txt, fontSize: 16, fontWeight: FontWeight.w800)),
                  const Spacer(),
                  if (!_loading) Text('${txns.length} transactions', style: TextStyle(color: sec, fontSize: 12)),
                ]),
              ),
            ),

            if (_loading)
              const SliverFillRemaining(child: Center(child: CircularProgressIndicator(color: _kTeal)))
            else if (txns.isEmpty)
              SliverFillRemaining(
                child: Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.receipt_long_rounded, size: 64, color: Colors.grey.withValues(alpha: 0.3)),
                  const SizedBox(height: 16),
                  Text('No commissions yet', style: TextStyle(color: txt, fontWeight: FontWeight.w700, fontSize: 16)),
                  const SizedBox(height: 8),
                  const Text('Your earnings will appear here', style: TextStyle(color: Colors.grey, fontSize: 13)),
                ])),
              )
            else
              SliverList(
                delegate: SliverChildBuilderDelegate(
                  (ctx, i) => _TxnTile(txn: txns[i], card: card, txt: txt, sec: sec),
                  childCount: txns.length,
                ),
              ),

            const SliverToBoxAdapter(child: SizedBox(height: 24)),
          ],
        ),
      ),
    );
  }
}

class _TxnTile extends StatelessWidget {
  final Map txn;
  final Color card, txt, sec;
  const _TxnTile({required this.txn, required this.card, required this.txt, required this.sec});

  @override
  Widget build(BuildContext context) {
    final isCredit = (txn['type'] ?? '') == 'credit';
    final amount = double.tryParse('${txn['amount'] ?? 0}') ?? 0;
    final date = txn['created_at']?.toString().substring(0, 10) ?? '';
    final desc = txn['description'] ?? 'Transaction';

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey.withValues(alpha: 0.08)),
      ),
      child: Row(children: [
        Container(
          width: 42, height: 42,
          decoration: BoxDecoration(
            color: isCredit ? _kGold.withValues(alpha: 0.12) : VC.red.withValues(alpha: 0.12),
            shape: BoxShape.circle,
          ),
          child: Icon(
            isCredit ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded,
            color: isCredit ? _kGold : VC.red, size: 20,
          ),
        ),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(desc, style: TextStyle(color: txt, fontWeight: FontWeight.w600, fontSize: 13), maxLines: 2, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 3),
          Text(date, style: TextStyle(color: sec, fontSize: 11)),
        ])),
        Text(
          '${isCredit ? '+' : '-'}\$${amount.toStringAsFixed(2)}',
          style: TextStyle(color: isCredit ? _kGold : VC.red, fontWeight: FontWeight.w800, fontSize: 14),
        ),
      ]),
    );
  }
}
