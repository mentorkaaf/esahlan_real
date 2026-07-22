import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'crypto_theme.dart';
import 'crypto_models.dart';
import 'crypto_providers.dart';
import 'crypto_widgets.dart';
import 'crypto_deposit_screen.dart';
import 'crypto_buy_sell_screen.dart';

class CryptoWalletScreen extends ConsumerStatefulWidget {
  const CryptoWalletScreen({super.key});

  @override
  ConsumerState<CryptoWalletScreen> createState() => _CryptoWalletScreenState();
}

class _CryptoWalletScreenState extends ConsumerState<CryptoWalletScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabCtrl;

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final walletAsync      = ref.watch(cryptoWalletProvider);
    final txAsync          = ref.watch(cryptoTransactionsProvider);
    final marketsAsync     = ref.watch(cryptoMarketsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Wallet')),
      body: RefreshIndicator(
        color: kCryptoPrimary,
        onRefresh: () async {
          ref.invalidate(cryptoWalletProvider);
          ref.invalidate(cryptoTransactionsProvider);
        },
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          child: Column(
            children: [
              // Balance gradient card
              walletAsync.when(
                loading: () => const _BalanceCardSkeleton(),
                error:   (_, __) => const _BalanceCardSkeleton(),
                data:    (d) => _BalanceCard(
                  data: d,
                  onDeposit: () => marketsAsync.whenData((coins) {
                    if (coins.isNotEmpty) {
                      Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) => CryptoDepositScreen(coin: coins.first)));
                    }
                  }),
                  onBuySell: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const CryptoBuySellScreen())),
                ),
              ),

              // Tab bar
              Container(
                color: cBg(context),
                child: TabBar(
                  controller: _tabCtrl,
                  tabs: const [Tab(text: 'Assets'), Tab(text: 'Transactions')],
                ),
              ),

              // Tab views — fixed height
              SizedBox(
                height: MediaQuery.of(context).size.height * 0.55,
                child: TabBarView(
                  controller: _tabCtrl,
                  children: [
                    // Assets tab
                    walletAsync.when(
                      loading: () => const Center(child: CircularProgressIndicator(color: kCryptoPrimary)),
                      error: (_, __) => Center(child: Text('Error', style: TextStyle(color: cMt(context)))),
                      data: (d) {
                        final assets = (d['assets'] as List? ?? []);
                        if (assets.isEmpty) {
                          return Center(
                            child: Text('No assets', style: TextStyle(color: cMt(context))));
                        }
                        return ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
                          itemCount: assets.length,
                          separatorBuilder: (_, __) =>
                              Divider(color: cBd(context), height: 1),
                          itemBuilder: (_, i) => _WalletAssetRow(asset: assets[i]),
                        );
                      },
                    ),
                    // Transactions tab
                    txAsync.when(
                      loading: () => const Center(child: CircularProgressIndicator(color: kCryptoPrimary)),
                      error: (_, __) => Center(child: Text('Error', style: TextStyle(color: cMt(context)))),
                      data: (txList) {
                        if (txList.isEmpty) {
                          return Center(
                            child: Text('No transactions', style: TextStyle(color: cMt(context))));
                        }
                        return ListView.separated(
                          padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
                          itemCount: txList.length,
                          separatorBuilder: (_, __) =>
                              Divider(color: cBd(context), height: 1),
                          itemBuilder: (_, i) => _TxRow(tx: txList[i]),
                        );
                      },
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ── Balance Card ──────────────────────────────────────────────────────────────

class _BalanceCard extends StatelessWidget {
  const _BalanceCard({required this.data, required this.onDeposit, required this.onBuySell});
  final Map<String, dynamic> data;
  final VoidCallback onDeposit, onBuySell;

  @override
  Widget build(BuildContext context) {
    final total     = (data['total_usd'] ?? 0).toDouble();
    final available = (data['available_usd'] ?? 0).toDouble();
    final locked    = (data['locked_usd'] ?? 0).toDouble();

    return Container(
      margin: const EdgeInsets.all(16),
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF1A237E), Color(0xFF283593), Color(0xFF1565C0)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        boxShadow: [
          BoxShadow(color: const Color(0xFF1A237E).withAlpha(80), blurRadius: 20, offset: const Offset(0, 8)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Total Balance', style: TextStyle(color: Colors.white70, fontSize: 12)),
          const SizedBox(height: 6),
          Text('\$${total.toStringAsFixed(2)}',
              style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w800)),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _BalanceStat(label: 'Available', value: '\$${available.toStringAsFixed(2)}')),
              Container(width: 1, height: 30, color: Colors.white24),
              Expanded(child: _BalanceStat(label: 'Locked', value: '\$${locked.toStringAsFixed(2)}')),
            ],
          ),
          const SizedBox(height: 16),
          // 4 action buttons
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceAround,
            children: [
              _CardAction(icon: Icons.arrow_downward, label: 'Deposit',  onTap: onDeposit),
              _CardAction(icon: Icons.arrow_upward,   label: 'Withdraw', onTap: () {}),
              _CardAction(icon: Icons.swap_horiz,     label: 'Transfer', onTap: () {}),
              _CardAction(icon: Icons.history,        label: 'History',  onTap: () {}),
            ],
          ),
        ],
      ),
    );
  }
}

class _BalanceStat extends StatelessWidget {
  const _BalanceStat({required this.label, required this.value});
  final String label, value;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Text(label, style: const TextStyle(color: Colors.white60, fontSize: 11)),
      const SizedBox(height: 2),
      Text(value, style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w700)),
    ],
  );
}

class _CardAction extends StatelessWidget {
  const _CardAction({required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Column(
      children: [
        Container(
          width: 42, height: 42,
          decoration: BoxDecoration(
            color: Colors.white.withAlpha(30),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: Colors.white, size: 18),
        ),
        const SizedBox(height: 4),
        Text(label, style: const TextStyle(color: Colors.white70, fontSize: 10)),
      ],
    ),
  );
}

class _BalanceCardSkeleton extends StatelessWidget {
  const _BalanceCardSkeleton();
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.all(16),
    height: 180,
    decoration: BoxDecoration(
      gradient: const LinearGradient(
          colors: [Color(0xFF1A237E), Color(0xFF1565C0)]),
      borderRadius: BorderRadius.circular(18),
    ),
    child: const Center(child: CircularProgressIndicator(color: Colors.white)),
  );
}

// ── Asset row ─────────────────────────────────────────────────────────────────

class _WalletAssetRow extends StatelessWidget {
  const _WalletAssetRow({required this.asset});
  final Map<String, dynamic> asset;

  @override
  Widget build(BuildContext context) {
    // Portfolio response nests coin info inside 'coin' key
    final coin     = asset['coin'] as Map<String, dynamic>? ?? asset;
    final symbol   = coin['symbol']?.toString() ?? '';
    final name     = coin['name']?.toString() ?? '';
    final balance  = (asset['balance'] ?? 0).toDouble();
    final wallets  = asset['wallets'] as List? ?? [];
    final locked   = wallets.fold<double>(0, (s, w) => s + ((w['locked'] ?? 0) as num).toDouble());
    final usdValue = (asset['usd_value'] ?? 0).toDouble();
    final logoUrl  = coin['logo_url']?.toString();

    if (balance <= 0) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        children: [
          CoinAvatarWidget(symbol: symbol, logoUrl: logoUrl, size: 36),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(symbol,
                    style: TextStyle(color: cTx(context), fontWeight: FontWeight.w600, fontSize: 14)),
                Text(name,
                    style: TextStyle(color: cMt(context), fontSize: 11)),
                if (locked > 0)
                  Text('Locked: $locked $symbol',
                      style: const TextStyle(color: kCryptoGold, fontSize: 10)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('${balance.toStringAsFixed(6)} $symbol',
                  style: TextStyle(color: cTx(context), fontWeight: FontWeight.w600, fontSize: 12)),
              Text('\$${usdValue.toStringAsFixed(2)}',
                  style: TextStyle(color: cMt(context), fontSize: 11)),
            ],
          ),
        ],
      ),
    );
  }
}

// ── Tx row ────────────────────────────────────────────────────────────────────

class _TxRow extends StatelessWidget {
  const _TxRow({required this.tx});
  final CryptoTransaction tx;

  @override
  Widget build(BuildContext context) {
    final isIn = ['deposit', 'buy'].contains(tx.type);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          Container(
            width: 36, height: 36,
            decoration: BoxDecoration(
              color: isIn ? kCryptoGreen.withAlpha(30) : kCryptoRed.withAlpha(30),
              shape: BoxShape.circle,
            ),
            child: Icon(
              isIn ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded,
              color: isIn ? kCryptoGreen : kCryptoRed,
              size: 18,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(tx.type[0].toUpperCase() + tx.type.substring(1),
                    style: TextStyle(color: cTx(context), fontWeight: FontWeight.w600, fontSize: 13)),
                Text(tx.createdAt,
                    style: TextStyle(color: cMt(context), fontSize: 11)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('${isIn ? '+' : '-'}${tx.amount.toStringAsFixed(6)} ${tx.symbol}',
                  style: TextStyle(
                    color: isIn ? kCryptoGreen : kCryptoRed,
                    fontWeight: FontWeight.w600, fontSize: 13,
                  )),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: _statusColor(tx.status).withAlpha(30),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(tx.status,
                    style: TextStyle(color: _statusColor(tx.status), fontSize: 9)),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Color _statusColor(String s) {
    switch (s) {
      case 'completed': return kCryptoGreen;
      case 'pending':   return kCryptoGold;
      default:          return kCryptoRed;
    }
  }
}
