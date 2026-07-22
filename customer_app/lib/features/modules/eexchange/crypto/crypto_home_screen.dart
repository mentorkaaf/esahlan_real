import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'crypto_theme.dart';
import 'crypto_providers.dart';
import 'crypto_widgets.dart';
import 'crypto_deposit_screen.dart';
import 'crypto_wallet_screen.dart';
import 'crypto_buy_sell_screen.dart';

class CryptoHomeScreen extends ConsumerWidget {
  const CryptoHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final portfolioAsync = ref.watch(cryptoPortfolioProvider);
    final marketsAsync   = ref.watch(cryptoMarketsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('eSahlan Exchange',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
        actions: [
          IconButton(
            icon: Icon(Icons.notifications_outlined, color: cTx(context)),
            onPressed: () {},
          ),
        ],
      ),
      body: RefreshIndicator(
        color: kCryptoPrimary,
        onRefresh: () async {
          ref.invalidate(cryptoPortfolioProvider);
          ref.invalidate(cryptoMarketsProvider);
        },
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 100),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Portfolio card
              portfolioAsync.when(
                loading: () => const _PortfolioCardSkeleton(),
                error:   (e, _) => _PortfolioCardError(e.toString()),
                data:    (d) => _PortfolioCard(data: d),
              ),
              const SizedBox(height: 20),

              // Quick actions
              _QuickActions(
                onDeposit:  () => marketsAsync.whenData((coins) {
                  if (coins.isNotEmpty) {
                    Navigator.of(context).push(MaterialPageRoute(
                        builder: (_) => CryptoDepositScreen(coin: coins.first)));
                  }
                }),
                onWithdraw:  () {},
                onTransfer:  () {},
                onBuySell:   () => Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const CryptoBuySellScreen())),
              ),
              const SizedBox(height: 24),

              // My Assets
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('My Assets',
                      style: TextStyle(color: cTx(context), fontSize: 16, fontWeight: FontWeight.w700)),
                  GestureDetector(
                    onTap: () => Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => const CryptoWalletScreen())),
                    child: const Text('See All',
                        style: TextStyle(color: kCryptoPrimary, fontSize: 13)),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              portfolioAsync.when(
                loading: () => const Center(child: CircularProgressIndicator(color: kCryptoPrimary)),
                error:   (_, __) => const SizedBox.shrink(),
                data:    (d) {
                  final assets = (d['assets'] as List? ?? []).take(5).toList();
                  if (assets.isEmpty) {
                    return Center(
                      child: Padding(
                        padding: const EdgeInsets.all(32),
                        child: Column(
                          children: [
                            Icon(Icons.currency_bitcoin, color: cMt(context), size: 48),
                            const SizedBox(height: 12),
                            Text('No assets yet', style: TextStyle(color: cMt(context))),
                            const SizedBox(height: 8),
                            ElevatedButton(
                              onPressed: () => Navigator.of(context).push(
                                  MaterialPageRoute(builder: (_) => const CryptoBuySellScreen())),
                              style: ElevatedButton.styleFrom(
                                  backgroundColor: kCryptoPrimary,
                                  foregroundColor: Colors.white),
                              child: const Text('Buy Crypto'),
                            ),
                          ],
                        ),
                      ),
                    );
                  }
                  return Column(
                    children: assets.map((a) => _AssetRow(asset: a)).toList(),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ── Portfolio Card ────────────────────────────────────────────────────────────

class _PortfolioCard extends StatelessWidget {
  const _PortfolioCard({required this.data});
  final Map<String, dynamic> data;

  @override
  Widget build(BuildContext context) {
    final totalUsd  = (data['total_usd'] ?? 0).toDouble();
    final change    = (data['change_24h_usd'] ?? 0).toDouble();
    final changePct = (data['change_24h_pct'] ?? 0).toDouble();
    final sparkline = (data['sparkline'] as List? ?? [])
        .map((e) => (e as num).toDouble()).toList();

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF1B2A6B), Color(0xFF0D1B3E)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Total Portfolio Value',
              style: TextStyle(color: Colors.white70, fontSize: 13)),
          const SizedBox(height: 6),
          Row(
            children: [
              Expanded(
                child: Text('\$${totalUsd.toStringAsFixed(2)}',
                    style: const TextStyle(
                        color: Colors.white, fontSize: 26, fontWeight: FontWeight.w800)),
              ),
              // Mini sparkline
              if (sparkline.length >= 2)
                SizedBox(
                  width: 80, height: 36,
                  child: CustomPaint(painter: _SparkPainter(sparkline, true)),
                ),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              Icon(changePct >= 0 ? Icons.arrow_upward : Icons.arrow_downward,
                  color: cryptoChangeColor(changePct), size: 14),
              const SizedBox(width: 4),
              Text('\$${change.abs().toStringAsFixed(2)} (${cryptoPct(changePct)})',
                  style: TextStyle(
                      color: cryptoChangeColor(changePct),
                      fontSize: 13, fontWeight: FontWeight.w600)),
              const SizedBox(width: 6),
              const Text("Today's P/L",
                  style: TextStyle(color: Colors.white54, fontSize: 12)),
            ],
          ),
        ],
      ),
    );
  }
}

class _PortfolioCardSkeleton extends StatelessWidget {
  const _PortfolioCardSkeleton();
  @override
  Widget build(BuildContext context) => Container(
    height: 110,
    decoration: BoxDecoration(
      gradient: const LinearGradient(
          colors: [Color(0xFF1B2A6B), Color(0xFF0D1B3E)]),
      borderRadius: BorderRadius.circular(16),
    ),
    child: const Center(child: CircularProgressIndicator(color: Colors.white)),
  );
}

class _PortfolioCardError extends StatelessWidget {
  const _PortfolioCardError(this.msg);
  final String msg;
  @override
  Widget build(BuildContext context) => Container(
    height: 110,
    decoration: BoxDecoration(
      color: cCard(context), borderRadius: BorderRadius.circular(16)),
    child: Center(child: Text(msg, style: TextStyle(color: cMt(context), fontSize: 12))),
  );
}

// ── Sparkline painter ─────────────────────────────────────────────────────────

class _SparkPainter extends CustomPainter {
  const _SparkPainter(this.pts, this.positive);
  final List<double> pts;
  final bool positive;

  @override
  void paint(Canvas canvas, Size size) {
    if (pts.length < 2) return;
    final min = pts.reduce(math.min);
    final max = pts.reduce(math.max);
    final range = (max - min).abs();
    final r = range == 0 ? 1.0 : range;
    final path = Path();
    for (var i = 0; i < pts.length; i++) {
      final x = size.width * i / (pts.length - 1);
      final y = size.height - size.height * (pts[i] - min) / r;
      i == 0 ? path.moveTo(x, y) : path.lineTo(x, y);
    }
    canvas.drawPath(path, Paint()
      ..color = positive ? kCryptoGreen : kCryptoRed
      ..strokeWidth = 1.5
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round);
  }

  @override
  bool shouldRepaint(_SparkPainter o) => o.pts != pts;
}

// ── Quick Actions ─────────────────────────────────────────────────────────────

class _QuickActions extends StatelessWidget {
  const _QuickActions({
    required this.onDeposit, required this.onWithdraw,
    required this.onTransfer, required this.onBuySell,
  });
  final VoidCallback onDeposit, onWithdraw, onTransfer, onBuySell;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceAround,
      children: [
        _ActionBtn(icon: Icons.arrow_downward_rounded,  label: 'Deposit',  color: kCryptoGreen,   onTap: onDeposit),
        _ActionBtn(icon: Icons.arrow_upward_rounded,    label: 'Withdraw', color: kCryptoRed,     onTap: onWithdraw),
        _ActionBtn(icon: Icons.swap_horiz_rounded,      label: 'Transfer', color: Colors.blueAccent, onTap: onTransfer),
        _ActionBtn(icon: Icons.currency_exchange,       label: 'Buy/Sell', color: kCryptoPrimary, onTap: onBuySell),
      ],
    );
  }
}

class _ActionBtn extends StatelessWidget {
  const _ActionBtn({required this.icon, required this.label, required this.color, required this.onTap});
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Column(
      children: [
        Container(
          width: 52, height: 52,
          decoration: BoxDecoration(
            color: color.withAlpha(30),
            shape: BoxShape.circle,
            border: Border.all(color: color.withAlpha(80)),
          ),
          child: Icon(icon, color: color, size: 22),
        ),
        const SizedBox(height: 6),
        Text(label, style: TextStyle(color: cMt(context), fontSize: 11)),
      ],
    ),
  );
}

// ── Asset Row ─────────────────────────────────────────────────────────────────

class _AssetRow extends StatelessWidget {
  const _AssetRow({required this.asset});
  final Map<String, dynamic> asset;

  @override
  Widget build(BuildContext context) {
    // Portfolio response nests coin info inside 'coin' key
    final coin     = asset['coin'] as Map<String, dynamic>? ?? asset;
    final symbol   = coin['symbol']?.toString() ?? '';
    final name     = coin['name']?.toString() ?? '';
    final balance  = (asset['balance'] ?? 0).toDouble();
    final usdValue = (asset['usd_value'] ?? 0).toDouble();
    final change   = (coin['change_24h'] ?? 0).toDouble();
    final logoUrl  = coin['logo_url']?.toString();

    if (balance <= 0) return const SizedBox.shrink();

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: cCard(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: cBd(context)),
      ),
      child: Row(
        children: [
          CoinAvatarWidget(symbol: symbol, logoUrl: logoUrl, size: 36),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(symbol, style: TextStyle(color: cTx(context), fontWeight: FontWeight.w600, fontSize: 14)),
                Text(name, style: TextStyle(color: cMt(context), fontSize: 11)),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text('${balance.toStringAsFixed(4)} $symbol',
                  style: TextStyle(color: cTx(context), fontWeight: FontWeight.w600, fontSize: 13)),
              Row(
                children: [
                  Text('\$${usdValue.toStringAsFixed(2)}',
                      style: TextStyle(color: cMt(context), fontSize: 11)),
                  const SizedBox(width: 6),
                  ChangeBadge(change),
                ],
              ),
            ],
          ),
        ],
      ),
    );
  }
}
