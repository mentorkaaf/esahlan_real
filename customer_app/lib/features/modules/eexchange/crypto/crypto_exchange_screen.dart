import 'dart:async';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'crypto_models.dart';
import 'crypto_providers.dart';
import 'crypto_repository.dart';

// ── Theme helpers ─────────────────────────────────────────────────────────────

const _kGreen = Color(0xFF00C853);
const _kRed = Color(0xFFFF1744);
const _kGold = Color(0xFFFFB300);
const _kBg = Color(0xFF0A0E1A);
const _kCard = Color(0xFF111827);
const _kBorder = Color(0xFF1F2937);
const _kText = Color(0xFFE5E7EB);
const _kMuted = Color(0xFF6B7280);

Color _changeColor(double v) => v >= 0 ? _kGreen : _kRed;
String _pct(double v) => '${v >= 0 ? '+' : ''}${v.toStringAsFixed(2)}%';

String _fmtUsd(double v) {
  if (v >= 1e9) return '\$${(v / 1e9).toStringAsFixed(2)}B';
  if (v >= 1e6) return '\$${(v / 1e6).toStringAsFixed(2)}M';
  if (v >= 1e3) return '\$${(v / 1e3).toStringAsFixed(2)}K';
  return '\$${v.toStringAsFixed(2)}';
}

String _coinPrice(double p) {
  if (p >= 1000) return '\$${p.toStringAsFixed(2)}';
  if (p >= 1) return '\$${p.toStringAsFixed(4)}';
  return '\$${p.toStringAsFixed(6)}';
}

// ── Entry point ───────────────────────────────────────────────────────────────

class CryptoExchangeScreen extends ConsumerStatefulWidget {
  const CryptoExchangeScreen({super.key});
  @override
  ConsumerState<CryptoExchangeScreen> createState() => _CryptoExchangeScreenState();
}

class _CryptoExchangeScreenState extends ConsumerState<CryptoExchangeScreen>
    with TickerProviderStateMixin {
  late final TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 5, vsync: this);
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: ThemeData.dark().copyWith(
        scaffoldBackgroundColor: _kBg,
        colorScheme: const ColorScheme.dark(primary: _kGold, surface: _kCard),
      ),
      child: Scaffold(
        backgroundColor: _kBg,
        body: NestedScrollView(
          headerSliverBuilder: (_, __) => [
            SliverAppBar(
              backgroundColor: _kBg,
              title: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: _kGold.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(6),
                  border: Border.all(color: _kGold.withOpacity(0.3)),
                ),
                child: const Text('eSahlan Exchange',
                    style: TextStyle(color: _kGold, fontSize: 14, fontWeight: FontWeight.w700)),
              ),
              actions: [
                IconButton(icon: const Icon(Icons.notifications_outlined, color: _kText), onPressed: () {}),
              ],
              pinned: true,
              bottom: TabBar(
                controller: _tab,
                isScrollable: true,
                tabAlignment: TabAlignment.start,
                indicatorColor: _kGold,
                indicatorWeight: 2,
                labelColor: _kGold,
                unselectedLabelColor: _kMuted,
                labelStyle: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
                tabs: const [
                  Tab(text: 'Markets'),
                  Tab(text: 'Portfolio'),
                  Tab(text: 'Buy / Sell'),
                  Tab(text: 'P2P'),
                  Tab(text: 'Wallet'),
                ],
              ),
            ),
          ],
          body: TabBarView(
            controller: _tab,
            children: const [
              _MarketsTab(),
              _PortfolioTab(),
              _BuySellTab(),
              _P2PTab(),
              _WalletTab(),
            ],
          ),
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// MARKETS TAB
// ═══════════════════════════════════════════════════════════════════════════════

class _MarketsTab extends ConsumerStatefulWidget {
  const _MarketsTab();
  @override
  ConsumerState<_MarketsTab> createState() => _MarketsTabState();
}

class _MarketsTabState extends ConsumerState<_MarketsTab> {
  final _search = TextEditingController();
  String _sort = 'rank';
  String _query = '';
  Timer? _debounce;

  @override
  void dispose() {
    _search.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  void _onSearch(String v) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(milliseconds: 400), () {
      if (mounted) setState(() => _query = v);
    });
  }

  @override
  Widget build(BuildContext context) {
    final marketsAsync = ref.watch(cryptoMarketsProvider);

    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _search,
                  onChanged: _onSearch,
                  style: const TextStyle(color: _kText, fontSize: 14),
                  decoration: InputDecoration(
                    hintText: 'Search coins...',
                    hintStyle: const TextStyle(color: _kMuted),
                    prefixIcon: const Icon(Icons.search, color: _kMuted, size: 18),
                    filled: true,
                    fillColor: _kCard,
                    border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(10),
                        borderSide: const BorderSide(color: _kBorder)),
                    enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(10),
                        borderSide: const BorderSide(color: _kBorder)),
                    contentPadding: const EdgeInsets.symmetric(vertical: 10),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              PopupMenuButton<String>(
                color: _kCard,
                onSelected: (v) => setState(() => _sort = v),
                itemBuilder: (_) => [
                  const PopupMenuItem(value: 'rank', child: Text('Market Cap', style: TextStyle(color: _kText))),
                  const PopupMenuItem(value: 'price', child: Text('Price', style: TextStyle(color: _kText))),
                  const PopupMenuItem(value: 'change', child: Text('24h Change', style: TextStyle(color: _kText))),
                  const PopupMenuItem(value: 'volume', child: Text('Volume', style: TextStyle(color: _kText))),
                ],
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                  decoration: BoxDecoration(
                    color: _kCard,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: _kBorder),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.sort, color: _kMuted, size: 18),
                      const SizedBox(width: 4),
                      Text(_sort == 'rank' ? 'Cap' : '${_sort[0].toUpperCase()}${_sort.substring(1)}',
                          style: const TextStyle(color: _kMuted, fontSize: 13)),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          child: Row(
            children: const [
              SizedBox(width: 40),
              Expanded(child: Text('Name', style: TextStyle(color: _kMuted, fontSize: 11))),
              SizedBox(width: 90, child: Text('Price', style: TextStyle(color: _kMuted, fontSize: 11), textAlign: TextAlign.right)),
              SizedBox(width: 68, child: Text('24h', style: TextStyle(color: _kMuted, fontSize: 11), textAlign: TextAlign.right)),
            ],
          ),
        ),
        Expanded(
          child: marketsAsync.when(
            loading: () => const Center(child: CircularProgressIndicator(color: _kGold)),
            error: (e, _) => _ErrView(e.toString(), onRetry: () => ref.invalidate(cryptoMarketsProvider)),
            data: (coins) {
              var list = coins.where((c) =>
                _query.isEmpty ||
                c.name.toLowerCase().contains(_query.toLowerCase()) ||
                c.symbol.toLowerCase().contains(_query.toLowerCase())
              ).toList();
              list.sort((a, b) {
                switch (_sort) {
                  case 'price': return b.priceUsd.compareTo(a.priceUsd);
                  case 'change': return b.change24h.compareTo(a.change24h);
                  case 'volume': return b.volume24h.compareTo(a.volume24h);
                  default: return b.marketCap.compareTo(a.marketCap);
                }
              });
              if (list.isEmpty) {
                return const Center(child: Text('No coins found', style: TextStyle(color: _kMuted)));
              }
              return RefreshIndicator(
                color: _kGold,
                onRefresh: () async => ref.invalidate(cryptoMarketsProvider),
                child: ListView.separated(
                  padding: const EdgeInsets.only(bottom: 100),
                  itemCount: list.length,
                  separatorBuilder: (_, __) => const Divider(height: 1, color: _kBorder, indent: 56),
                  itemBuilder: (_, i) => _CoinRow(coin: list[i]),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}

class _CoinRow extends StatelessWidget {
  const _CoinRow({required this.coin});
  final CryptoCoin coin;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => _CoinDetailScreen(coin: coin)),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(
          children: [
            _CoinAvatar(coin: coin, size: 36),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(coin.symbol,
                      style: const TextStyle(color: _kText, fontWeight: FontWeight.w600, fontSize: 14)),
                  Text(coin.name, style: const TextStyle(color: _kMuted, fontSize: 11)),
                ],
              ),
            ),
            SizedBox(
              width: 90,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(coin.priceFormatted,
                      style: const TextStyle(color: _kText, fontWeight: FontWeight.w600, fontSize: 13)),
                  Text(_fmtUsd(coin.volume24h),
                      style: const TextStyle(color: _kMuted, fontSize: 10)),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Container(
              width: 64,
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
              decoration: BoxDecoration(
                color: _changeColor(coin.change24h).withOpacity(0.12),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Text(
                _pct(coin.change24h),
                style: TextStyle(
                    color: _changeColor(coin.change24h), fontSize: 11, fontWeight: FontWeight.w600),
                textAlign: TextAlign.center,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Coin Detail Screen ────────────────────────────────────────────────────────

class _CoinDetailScreen extends ConsumerStatefulWidget {
  const _CoinDetailScreen({required this.coin});
  final CryptoCoin coin;

  @override
  ConsumerState<_CoinDetailScreen> createState() => _CoinDetailScreenState();
}

class _CoinDetailScreenState extends ConsumerState<_CoinDetailScreen> {
  String _interval = '1d';
  final _intervals = ['1h', '4h', '1d', '1w', '1M'];

  @override
  Widget build(BuildContext context) {
    final chartKey = '${widget.coin.symbol}:$_interval';
    final chartAsync = ref.watch(coinChartProvider(chartKey));

    return Theme(
      data: ThemeData.dark().copyWith(scaffoldBackgroundColor: _kBg),
      child: Scaffold(
        backgroundColor: _kBg,
        appBar: AppBar(
          backgroundColor: _kBg,
          title: Row(
            children: [
              _CoinAvatar(coin: widget.coin, size: 26),
              const SizedBox(width: 8),
              Text(widget.coin.symbol,
                  style: const TextStyle(color: _kText, fontWeight: FontWeight.w700)),
              const SizedBox(width: 6),
              Text(widget.coin.name,
                  style: const TextStyle(color: _kMuted, fontSize: 13)),
            ],
          ),
        ),
        body: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(widget.coin.priceFormatted,
                      style: const TextStyle(
                          color: _kText, fontSize: 28, fontWeight: FontWeight.w700)),
                  const SizedBox(width: 12),
                  _Badge(widget.coin.change24h),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                  'H: ${_coinPrice(widget.coin.high24h)}  L: ${_coinPrice(widget.coin.low24h)}',
                  style: const TextStyle(color: _kMuted, fontSize: 12)),
              const SizedBox(height: 16),
              // Interval selector
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: _intervals.map((iv) => Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: GestureDetector(
                      onTap: () => setState(() => _interval = iv),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                        decoration: BoxDecoration(
                          color: _interval == iv ? _kGold.withOpacity(0.15) : _kCard,
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(
                              color: _interval == iv ? _kGold : _kBorder),
                        ),
                        child: Text(iv,
                            style: TextStyle(
                                color: _interval == iv ? _kGold : _kMuted,
                                fontSize: 12,
                                fontWeight: FontWeight.w600)),
                      ),
                    ),
                  )).toList(),
                ),
              ),
              const SizedBox(height: 12),
              // Chart
              Container(
                height: 180,
                decoration: BoxDecoration(
                  color: _kCard,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: _kBorder),
                ),
                child: chartAsync.when(
                  loading: () => const Center(child: CircularProgressIndicator(color: _kGold)),
                  error: (_, __) => const Center(
                      child: Text('Chart unavailable', style: TextStyle(color: _kMuted))),
                  data: (pts) => pts.isEmpty
                      ? const Center(
                          child: Text('No data', style: TextStyle(color: _kMuted)))
                      : Padding(
                          padding: const EdgeInsets.all(12),
                          child: CustomPaint(
                            size: Size.infinite,
                            painter: _ChartPainter(pts, widget.coin.change24h >= 0),
                          ),
                        ),
                ),
              ),
              const SizedBox(height: 20),
              _StatsGrid(coin: widget.coin),
              const SizedBox(height: 20),
              if (widget.coin.networks.isNotEmpty) ...[
                const Text('Networks',
                    style: TextStyle(
                        color: _kText, fontWeight: FontWeight.w600, fontSize: 15)),
                const SizedBox(height: 8),
                ...widget.coin.networks.map((n) => Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: _kCard,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: _kBorder),
                  ),
                  child: Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: _kGold.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: Text(n.chain,
                            style: const TextStyle(
                                color: _kGold, fontSize: 12, fontWeight: FontWeight.w600)),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                          child: Text(n.name,
                              style: const TextStyle(color: _kText, fontSize: 13))),
                      Text('Fee: ${n.withdrawalFee} ${widget.coin.symbol}',
                          style: const TextStyle(color: _kMuted, fontSize: 11)),
                    ],
                  ),
                )),
              ],
              const SizedBox(height: 80),
            ],
          ),
        ),
        bottomNavigationBar: Container(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
          decoration: const BoxDecoration(
            color: _kCard,
            border: Border(top: BorderSide(color: _kBorder)),
          ),
          child: Row(
            children: [
              Expanded(
                child: _GoldBtn(
                  label: 'Buy ${widget.coin.symbol}',
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) =>
                        _BuySellScreen(preselect: widget.coin, side: 'buy'),
                  )),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: OutlinedButton(
                  onPressed: () => Navigator.of(context).push(MaterialPageRoute(
                    builder: (_) =>
                        _BuySellScreen(preselect: widget.coin, side: 'sell'),
                  )),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: _kText,
                    side: const BorderSide(color: _kBorder),
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10)),
                  ),
                  child: Text('Sell ${widget.coin.symbol}'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatsGrid extends StatelessWidget {
  const _StatsGrid({required this.coin});
  final CryptoCoin coin;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      crossAxisCount: 2,
      mainAxisSpacing: 10,
      crossAxisSpacing: 10,
      childAspectRatio: 2.4,
      children: [
        _StatCard('Market Cap', coin.marketCapFormatted()),
        _StatCard('24h Volume', coin.volumeFormatted()),
        _StatCard('7d Change', _pct(coin.change7d),
            valueColor: _changeColor(coin.change7d)),
        _StatCard('Buy Fee', '${coin.buyFee}%'),
        _StatCard('Min Withdraw', '${coin.minWithdrawal} ${coin.symbol}'),
        _StatCard('Withdraw Fee', '${coin.withdrawalFee} ${coin.symbol}'),
      ],
    );
  }
}

class _StatCard extends StatelessWidget {
  const _StatCard(this.label, this.value, {this.valueColor});
  final String label, value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      color: _kCard,
      borderRadius: BorderRadius.circular(10),
      border: Border.all(color: _kBorder),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Text(label, style: const TextStyle(color: _kMuted, fontSize: 11)),
        const SizedBox(height: 4),
        Text(value,
            style: TextStyle(
                color: valueColor ?? _kText,
                fontWeight: FontWeight.w600,
                fontSize: 13)),
      ],
    ),
  );
}

class _ChartPainter extends CustomPainter {
  _ChartPainter(this.points, this.isUp);
  final List<ChartPoint> points;
  final bool isUp;

  @override
  void paint(Canvas canvas, Size size) {
    if (points.length < 2) return;
    final color = isUp ? _kGreen : _kRed;
    final minP = points.map((p) => p.price).reduce(math.min);
    final maxP = points.map((p) => p.price).reduce(math.max);
    final range = maxP - minP == 0 ? 1.0 : maxP - minP;
    final path = Path();
    final fill = Path();

    for (var i = 0; i < points.length; i++) {
      final x = size.width * i / (points.length - 1);
      final y = size.height * (1 - (points[i].price - minP) / range);
      if (i == 0) {
        path.moveTo(x, y);
        fill
          ..moveTo(x, size.height)
          ..lineTo(x, y);
      } else {
        path.lineTo(x, y);
        fill.lineTo(x, y);
      }
    }
    fill
      ..lineTo(size.width, size.height)
      ..close();

    canvas.drawPath(
        fill,
        Paint()
          ..shader = LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [color.withOpacity(0.25), color.withOpacity(0)],
          ).createShader(Rect.fromLTWH(0, 0, size.width, size.height)));

    canvas.drawPath(
        path,
        Paint()
          ..color = color
          ..strokeWidth = 2
          ..style = PaintingStyle.stroke
          ..strokeCap = StrokeCap.round);

    final lx = size.width;
    final ly = size.height * (1 - (points.last.price - minP) / range);
    canvas.drawCircle(Offset(lx, ly), 4, Paint()..color = color);
  }

  @override
  bool shouldRepaint(_ChartPainter o) => o.points != points;
}

// ═══════════════════════════════════════════════════════════════════════════════
// PORTFOLIO TAB
// ═══════════════════════════════════════════════════════════════════════════════

class _PortfolioTab extends ConsumerWidget {
  const _PortfolioTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final portfolioAsync = ref.watch(cryptoPortfolioProvider);

    return portfolioAsync.when(
      loading: () => const Center(child: CircularProgressIndicator(color: _kGold)),
      error: (e, _) =>
          _ErrView(e.toString(), onRetry: () => ref.invalidate(cryptoPortfolioProvider)),
      data: (data) {
        final assets = (data['assets'] as List? ?? []);
        final totalUsd = (data['total_usd'] ?? 0).toDouble();
        final change24h = (data['change_24h_pct'] ?? 0).toDouble();

        return RefreshIndicator(
          color: _kGold,
          onRefresh: () async => ref.invalidate(cryptoPortfolioProvider),
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Total value card
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [_kGold.withOpacity(0.15), _kCard],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: _kGold.withOpacity(0.3)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Total Portfolio Value',
                          style: TextStyle(color: _kMuted, fontSize: 13)),
                      const SizedBox(height: 8),
                      Text('\$${totalUsd.toStringAsFixed(2)}',
                          style: const TextStyle(
                              color: _kText, fontSize: 30, fontWeight: FontWeight.w700)),
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          Icon(
                              change24h >= 0
                                  ? Icons.arrow_upward
                                  : Icons.arrow_downward,
                              color: _changeColor(change24h),
                              size: 14),
                          const SizedBox(width: 4),
                          Text(_pct(change24h),
                              style: TextStyle(
                                  color: _changeColor(change24h),
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600)),
                          const Text(' today',
                              style: TextStyle(color: _kMuted, fontSize: 13)),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),
                if (assets.isEmpty)
                  const Center(
                    child: Padding(
                      padding: EdgeInsets.symmetric(vertical: 40),
                      child: Text(
                          'No holdings yet.\nBuy your first crypto!',
                          style: TextStyle(color: _kMuted),
                          textAlign: TextAlign.center),
                    ),
                  )
                else ...[
                  const Text('Holdings',
                      style: TextStyle(
                          color: _kText,
                          fontWeight: FontWeight.w600,
                          fontSize: 16)),
                  const SizedBox(height: 12),
                  ...assets.map((a) {
                    final symbol = a['symbol']?.toString() ?? '';
                    final name = a['name']?.toString() ?? '';
                    final balance = (a['balance'] ?? 0).toDouble();
                    final usdValue = (a['usd_value'] ?? 0).toDouble();
                    final price = (a['price_usd'] ?? 0).toDouble();
                    final change = (a['change_24h'] ?? 0).toDouble();
                    final logoUrl = a['logo_url']?.toString();
                    final pct = totalUsd > 0 ? (usdValue / totalUsd * 100) : 0.0;

                    return Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: _kCard,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: _kBorder),
                      ),
                      child: Column(
                        children: [
                          Row(
                            children: [
                              _CoinAvatarRaw(
                                  symbol: symbol, logoUrl: logoUrl, size: 36),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(symbol,
                                        style: const TextStyle(
                                            color: _kText,
                                            fontWeight: FontWeight.w600)),
                                    Text(name,
                                        style: const TextStyle(
                                            color: _kMuted, fontSize: 12)),
                                  ],
                                ),
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                      '\$${usdValue.toStringAsFixed(2)}',
                                      style: const TextStyle(
                                          color: _kText,
                                          fontWeight: FontWeight.w600,
                                          fontSize: 15)),
                                  Text(
                                      '${balance.toStringAsFixed(6)} $symbol',
                                      style: const TextStyle(
                                          color: _kMuted, fontSize: 11)),
                                ],
                              ),
                            ],
                          ),
                          const SizedBox(height: 10),
                          Row(
                            children: [
                              Expanded(
                                child: LinearProgressIndicator(
                                  value: pct / 100,
                                  backgroundColor: _kBorder,
                                  valueColor:
                                      const AlwaysStoppedAnimation(_kGold),
                                  minHeight: 3,
                                  borderRadius: BorderRadius.circular(4),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Text('${pct.toStringAsFixed(1)}%',
                                  style: const TextStyle(
                                      color: _kMuted, fontSize: 11)),
                              const SizedBox(width: 12),
                              Text(_coinPrice(price),
                                  style: const TextStyle(
                                      color: _kMuted, fontSize: 11)),
                              const SizedBox(width: 8),
                              _Badge(change),
                            ],
                          ),
                        ],
                      ),
                    );
                  }),
                ],
                const SizedBox(height: 80),
              ],
            ),
          ),
        );
      },
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// BUY / SELL TAB
// ═══════════════════════════════════════════════════════════════════════════════

class _BuySellTab extends ConsumerWidget {
  const _BuySellTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final marketsAsync = ref.watch(cryptoMarketsProvider);
    return marketsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator(color: _kGold)),
      error: (e, _) =>
          _ErrView(e.toString(), onRetry: () => ref.invalidate(cryptoMarketsProvider)),
      data: (coins) => _BuySellScreen(coins: coins),
    );
  }
}

class _BuySellScreen extends ConsumerStatefulWidget {
  const _BuySellScreen({this.preselect, this.side = 'buy', this.coins, super.key});
  final CryptoCoin? preselect;
  final String side;
  final List<CryptoCoin>? coins;

  @override
  ConsumerState<_BuySellScreen> createState() => _BuySellScreenState();
}

class _BuySellScreenState extends ConsumerState<_BuySellScreen> {
  late String _side;
  CryptoCoin? _coin;
  CoinNetwork? _network;
  String _paymentMethod = 'epay';
  final _amtCtrl = TextEditingController();
  bool _loading = false;
  String? _err;
  Map<String, dynamic>? _result;
  Timer? _quoteTimer;
  int _countdown = 30;

  final _methods = [
    {'key': 'epay', 'label': 'ePay Wallet', 'icon': Icons.account_balance_wallet_outlined},
    {'key': 'evc', 'label': 'EVC Plus', 'icon': Icons.phone_android},
    {'key': 'edahab', 'label': 'eDahab', 'icon': Icons.phone_outlined},
    {'key': 'waafi_pay', 'label': 'Waafi Pay', 'icon': Icons.payments_outlined},
  ];

  @override
  void initState() {
    super.initState();
    _side = widget.side;
    _coin = widget.preselect;
    if (_coin != null) _network = _coin!.firstNetwork;
  }

  @override
  void dispose() {
    _amtCtrl.dispose();
    _quoteTimer?.cancel();
    super.dispose();
  }

  void _startCountdown() {
    _quoteTimer?.cancel();
    _countdown = 30;
    _quoteTimer = Timer.periodic(const Duration(seconds: 1), (t) {
      if (!mounted) { t.cancel(); return; }
      setState(() => _countdown--);
      if (_countdown <= 0) {
        t.cancel();
        ref.read(quoteProvider.notifier).reset();
      }
    });
  }

  Future<void> _getQuote() async {
    final amt = double.tryParse(_amtCtrl.text);
    if (_coin == null || amt == null || amt <= 0) return;
    setState(() => _err = null);
    try {
      await ref.read(quoteProvider.notifier).getQuote(
        symbol: _coin!.symbol,
        side: _side,
        amountUsd: amt,
      );
      _startCountdown();
    } catch (e) {
      setState(() => _err = e.toString());
    }
  }

  Future<void> _executeTrade() async {
    final amt = double.tryParse(_amtCtrl.text);
    if (_coin == null || amt == null || _network == null) return;
    setState(() { _loading = true; _err = null; });
    try {
      final res = await ref.read(quoteProvider.notifier).executeTrade(
        symbol: _coin!.symbol,
        networkId: _network!.id,
        side: _side,
        amountUsd: amt,
        paymentMethod: _paymentMethod,
      );
      _quoteTimer?.cancel();
      ref.read(quoteProvider.notifier).reset();
      setState(() { _result = res; _loading = false; });
    } catch (e) {
      setState(() { _err = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final quoteAsync = ref.watch(quoteProvider);
    final quote = quoteAsync.value;
    final isLoadingQuote = quoteAsync is AsyncLoading;

    if (_result != null) {
      return _SuccessView(
          result: _result!,
          onDone: () => setState(() {
                _result = null;
                ref.read(quoteProvider.notifier).reset();
              }));
    }

    final isEmbedded = widget.coins != null;

    Widget body = SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Buy / Sell toggle
          Container(
            padding: const EdgeInsets.all(4),
            decoration: BoxDecoration(
              color: _kCard,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _kBorder),
            ),
            child: Row(
              children: [
                Expanded(
                    child: _ToggleBtn('Buy', _side == 'buy',
                        onTap: () => setState(() => _side = 'buy'),
                        color: _kGreen)),
                Expanded(
                    child: _ToggleBtn('Sell', _side == 'sell',
                        onTap: () => setState(() => _side = 'sell'),
                        color: _kRed)),
              ],
            ),
          ),
          const SizedBox(height: 16),

          // Coin selector
          _SectionLabel('Coin'),
          const SizedBox(height: 6),
          _CoinSelector(
            selected: _coin,
            coins: widget.coins,
            onSelect: (c) => setState(() {
              _coin = c;
              _network = c.firstNetwork;
              ref.read(quoteProvider.notifier).reset();
            }),
          ),
          const SizedBox(height: 16),

          // Network selector (if multiple)
          if (_coin != null && _coin!.networks.length > 1) ...[
            _SectionLabel('Network'),
            const SizedBox(height: 6),
            _NetworkDropdown(
              networks: _coin!.networks,
              selected: _network,
              onSelect: (n) => setState(() => _network = n),
            ),
            const SizedBox(height: 16),
          ],

          // Amount
          _SectionLabel(_side == 'buy' ? 'Amount (USD)' : 'Amount (USD to receive)'),
          const SizedBox(height: 6),
          TextField(
            controller: _amtCtrl,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            style: const TextStyle(
                color: _kText, fontSize: 18, fontWeight: FontWeight.w600),
            onChanged: (_) {
              ref.read(quoteProvider.notifier).reset();
              _quoteTimer?.cancel();
            },
            decoration: InputDecoration(
              hintText: '0.00',
              hintStyle: const TextStyle(color: _kMuted),
              prefixIcon: const Padding(
                padding: EdgeInsets.only(left: 14, top: 12),
                child: Text('\$', style: TextStyle(color: _kMuted, fontSize: 18)),
              ),
              prefixIconConstraints: const BoxConstraints(),
              filled: true,
              fillColor: _kCard,
              border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: _kBorder)),
              enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: _kBorder)),
              focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: _kGold)),
            ),
          ),
          const SizedBox(height: 16),

          // Payment method
          _SectionLabel(_side == 'buy' ? 'Payment Method' : 'Receive Method'),
          const SizedBox(height: 8),
          ..._methods.map((m) => _PayMethodTile(
            label: m['label'] as String,
            icon: m['icon'] as IconData,
            selected: _paymentMethod == m['key'],
            onTap: () => setState(() => _paymentMethod = m['key'] as String),
          )),
          const SizedBox(height: 8),

          if (_err != null)
            Container(
              padding: const EdgeInsets.all(12),
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: _kRed.withOpacity(0.1),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: _kRed.withOpacity(0.3)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.error_outline, color: _kRed, size: 16),
                  const SizedBox(width: 8),
                  Expanded(
                      child: Text(_err!,
                          style: const TextStyle(color: _kRed, fontSize: 13))),
                ],
              ),
            ),

          // Quote card
          if (quote != null) ...[
            _QuoteCard(
                quote: quote,
                symbol: _coin?.symbol ?? '',
                side: _side,
                countdown: _countdown),
            const SizedBox(height: 12),
            _GoldBtn(
              label: _loading
                  ? 'Processing...'
                  : (_side == 'buy' ? 'Confirm Buy' : 'Confirm Sell'),
              onTap: _loading ? null : _executeTrade,
            ),
          ] else ...[
            ElevatedButton(
              onPressed: isLoadingQuote ? null : _getQuote,
              style: ElevatedButton.styleFrom(
                backgroundColor: _kGold,
                foregroundColor: Colors.black,
                minimumSize: const Size(double.infinity, 50),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
              ),
              child: isLoadingQuote
                  ? const SizedBox(
                      height: 20,
                      width: 20,
                      child: CircularProgressIndicator(
                          strokeWidth: 2, color: Colors.black))
                  : const Text('Get Quote',
                      style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            ),
          ],
          const SizedBox(height: 100),
        ],
      ),
    );

    if (!isEmbedded) {
      return Theme(
        data: ThemeData.dark().copyWith(scaffoldBackgroundColor: _kBg),
        child: Scaffold(
          backgroundColor: _kBg,
          appBar: AppBar(
            backgroundColor: _kBg,
            title: Text(
                '${_side == 'buy' ? 'Buy' : 'Sell'} ${_coin?.symbol ?? 'Crypto'}',
                style: const TextStyle(color: _kText)),
          ),
          body: body,
        ),
      );
    }
    return body;
  }
}

class _QuoteCard extends StatelessWidget {
  const _QuoteCard(
      {required this.quote,
      required this.symbol,
      required this.side,
      required this.countdown});
  final Map<String, dynamic> quote;
  final String symbol, side;
  final int countdown;

  @override
  Widget build(BuildContext context) {
    final cryptoAmt = (quote['crypto_amount'] ?? 0).toDouble();
    final feeUsd = (quote['fee_usd'] ?? 0).toDouble();
    final price = (quote['price_usd'] ?? 0).toDouble();
    final totalUsd = (quote['total_usd'] ?? 0).toDouble();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _kGold.withOpacity(0.06),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _kGold.withOpacity(0.3)),
      ),
      child: Column(
        children: [
          Row(
            children: [
              const Icon(Icons.receipt_long_outlined, color: _kGold, size: 18),
              const SizedBox(width: 6),
              const Text('Quote',
                  style:
                      TextStyle(color: _kGold, fontWeight: FontWeight.w700)),
              const Spacer(),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: (countdown > 10 ? _kGreen : _kRed).withOpacity(0.15),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text('${countdown}s',
                    style: TextStyle(
                        color: countdown > 10 ? _kGreen : _kRed,
                        fontWeight: FontWeight.w700,
                        fontSize: 13)),
              ),
            ],
          ),
          const Divider(color: _kBorder, height: 20),
          _QRow('Price', _coinPrice(price)),
          _QRow('You ${side == 'buy' ? 'get' : 'sell'}',
              '${cryptoAmt.toStringAsFixed(6)} $symbol'),
          _QRow('Platform fee', '\$${feeUsd.toStringAsFixed(2)}'),
          const Divider(color: _kBorder, height: 12),
          _QRow('Total', '\$${totalUsd.toStringAsFixed(2)}', bold: true),
        ],
      ),
    );
  }
}

class _QRow extends StatelessWidget {
  const _QRow(this.label, this.value, {this.bold = false});
  final String label, value;
  final bool bold;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 3),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label,
            style: TextStyle(
                color: _kMuted,
                fontSize: 13,
                fontWeight: bold ? FontWeight.w700 : FontWeight.normal)),
        Text(value,
            style: TextStyle(
                color: bold ? _kGold : _kText,
                fontSize: 13,
                fontWeight: bold ? FontWeight.w700 : FontWeight.w500)),
      ],
    ),
  );
}

class _SuccessView extends StatelessWidget {
  const _SuccessView({required this.result, required this.onDone});
  final Map<String, dynamic> result;
  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    final data = result['data'] as Map<String, dynamic>? ?? result;
    final status = data['status']?.toString() ?? 'pending';
    final isPending = status == 'pending';

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 80,
              height: 80,
              decoration: BoxDecoration(
                color:
                    (isPending ? _kGold : _kGreen).withOpacity(0.12),
                shape: BoxShape.circle,
              ),
              child: Icon(
                  isPending
                      ? Icons.hourglass_empty
                      : Icons.check_circle_outline,
                  color: isPending ? _kGold : _kGreen,
                  size: 40),
            ),
            const SizedBox(height: 20),
            Text(isPending ? 'Order Placed!' : 'Trade Complete!',
                style: const TextStyle(
                    color: _kText,
                    fontSize: 22,
                    fontWeight: FontWeight.w700)),
            const SizedBox(height: 8),
            Text(
                isPending
                    ? 'Your order is pending admin approval.\nYou will be notified when processed.'
                    : 'Your crypto has been credited to your wallet.',
                style: const TextStyle(color: _kMuted, fontSize: 14),
                textAlign: TextAlign.center),
            const SizedBox(height: 30),
            _GoldBtn(label: 'Done', onTap: onDone),
          ],
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// P2P TAB
// ═══════════════════════════════════════════════════════════════════════════════

class _P2PTab extends ConsumerStatefulWidget {
  const _P2PTab();
  @override
  ConsumerState<_P2PTab> createState() => _P2PTabState();
}

class _P2PTabState extends ConsumerState<_P2PTab>
    with SingleTickerProviderStateMixin {
  late final TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Container(
          color: _kBg,
          child: TabBar(
            controller: _tab,
            labelColor: _kGold,
            unselectedLabelColor: _kMuted,
            indicatorColor: _kGold,
            tabs: const [
              Tab(text: 'Buy'),
              Tab(text: 'Sell'),
              Tab(text: 'My Orders'),
            ],
          ),
        ),
        Expanded(
          child: TabBarView(
            controller: _tab,
            children: const [
              _P2PAdsView(type: 'sell'), // user wants to buy → show sell ads
              _P2PAdsView(type: 'buy'),  // user wants to sell → show buy ads
              _MyP2POrders(),
            ],
          ),
        ),
      ],
    );
  }
}

class _P2PAdsView extends ConsumerWidget {
  const _P2PAdsView({required this.type});
  final String type;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final key = '$type:';
    final adsAsync = ref.watch(p2pAdsProvider(key));

    return adsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator(color: _kGold)),
      error: (e, _) =>
          _ErrView(e.toString(), onRetry: () => ref.invalidate(p2pAdsProvider(key))),
      data: (ads) {
        if (ads.isEmpty) {
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(Icons.inbox_outlined, color: _kMuted, size: 48),
                const SizedBox(height: 12),
                const Text('No ads available',
                    style: TextStyle(color: _kMuted)),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: () => ref.invalidate(p2pAdsProvider(key)),
                  child: const Text('Refresh',
                      style: TextStyle(color: _kGold)),
                ),
              ],
            ),
          );
        }
        return RefreshIndicator(
          color: _kGold,
          onRefresh: () async => ref.invalidate(p2pAdsProvider(key)),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: ads.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, i) => _P2PAdCard(ad: ads[i]),
          ),
        );
      },
    );
  }
}

class _P2PAdCard extends StatelessWidget {
  const _P2PAdCard({required this.ad});
  final P2pAd ad;

  @override
  Widget build(BuildContext context) {
    final isSell = ad.type == 'sell';
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _kCard,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _kBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: _kGold.withOpacity(0.2),
                child: Text(
                    ad.sellerName.isNotEmpty
                        ? ad.sellerName[0].toUpperCase()
                        : '?',
                    style: const TextStyle(
                        color: _kGold, fontWeight: FontWeight.w700)),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(ad.sellerName,
                        style: const TextStyle(
                            color: _kText,
                            fontWeight: FontWeight.w600,
                            fontSize: 14)),
                    Text('${ad.completedCount} orders',
                        style: const TextStyle(color: _kMuted, fontSize: 11)),
                  ],
                ),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: (isSell ? _kGreen : _kRed).withOpacity(0.12),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(isSell ? 'SELL' : 'BUY',
                    style: TextStyle(
                        color: isSell ? _kGreen : _kRed,
                        fontWeight: FontWeight.w700,
                        fontSize: 12)),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('\$${ad.priceUsd.toStringAsFixed(4)}',
                        style: const TextStyle(
                            color: _kGold,
                            fontWeight: FontWeight.w700,
                            fontSize: 18)),
                    Text('per ${ad.coinSymbol}',
                        style: const TextStyle(color: _kMuted, fontSize: 11)),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                      'Available: ${ad.remaining.toStringAsFixed(4)} ${ad.coinSymbol}',
                      style: const TextStyle(color: _kMuted, fontSize: 11)),
                  Text(
                      'Limit: \$${ad.minOrder.toStringAsFixed(0)} – \$${ad.maxOrder.toStringAsFixed(0)}',
                      style: const TextStyle(color: _kMuted, fontSize: 11)),
                ],
              ),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 6,
            children: ad.paymentMethods
                .map((m) => Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: _kBorder,
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(m,
                          style: const TextStyle(
                              color: _kMuted, fontSize: 11)),
                    ))
                .toList(),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () => showModalBottomSheet(
                context: context,
                backgroundColor: _kCard,
                isScrollControlled: true,
                builder: (_) => _P2POrderSheet(ad: ad),
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor:
                    (isSell ? _kGreen : _kRed).withOpacity(0.15),
                foregroundColor: isSell ? _kGreen : _kRed,
                elevation: 0,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                  side: BorderSide(
                      color: (isSell ? _kGreen : _kRed).withOpacity(0.3)),
                ),
              ),
              child: Text(
                  isSell
                      ? 'Buy ${ad.coinSymbol}'
                      : 'Sell ${ad.coinSymbol}',
                  style: const TextStyle(fontWeight: FontWeight.w600)),
            ),
          ),
        ],
      ),
    );
  }
}

class _P2POrderSheet extends ConsumerStatefulWidget {
  const _P2POrderSheet({required this.ad});
  final P2pAd ad;

  @override
  ConsumerState<_P2POrderSheet> createState() => _P2POrderSheetState();
}

class _P2POrderSheetState extends ConsumerState<_P2POrderSheet> {
  final _amtCtrl = TextEditingController();
  String _paymentMethod = '';
  bool _loading = false;
  String? _err;

  @override
  void initState() {
    super.initState();
    if (widget.ad.paymentMethods.isNotEmpty) {
      _paymentMethod = widget.ad.paymentMethods.first;
    }
  }

  @override
  void dispose() {
    _amtCtrl.dispose();
    super.dispose();
  }

  Future<void> _place() async {
    final amt = double.tryParse(_amtCtrl.text);
    if (amt == null || amt <= 0) return;
    setState(() { _loading = true; _err = null; });
    try {
      await CryptoRepository.create().placeP2pOrder(
        adUuid: widget.ad.uuid,
        cryptoAmount: amt,
        paymentMethod: _paymentMethod,
      );
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Order placed! Check My Orders.'),
            backgroundColor: _kGreen));
      }
    } catch (e) {
      setState(() { _err = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final ad = widget.ad;
    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Place Order — ${ad.coinSymbol}',
                style: const TextStyle(
                    color: _kText, fontSize: 16, fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            Text(
                'Price: \$${ad.priceUsd.toStringAsFixed(4)} per ${ad.coinSymbol}',
                style: const TextStyle(color: _kMuted, fontSize: 12)),
            const SizedBox(height: 16),
            TextField(
              controller: _amtCtrl,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              style: const TextStyle(color: _kText),
              decoration: InputDecoration(
                labelText: 'Amount (${ad.coinSymbol})',
                labelStyle: const TextStyle(color: _kMuted),
                hintStyle: const TextStyle(color: _kMuted),
                filled: true,
                fillColor: _kBg,
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: const BorderSide(color: _kBorder)),
                enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: const BorderSide(color: _kBorder)),
              ),
            ),
            const SizedBox(height: 12),
            if (ad.paymentMethods.length > 1) ...[
              const Text('Payment Method',
                  style: TextStyle(color: _kMuted, fontSize: 13)),
              const SizedBox(height: 6),
              Wrap(
                spacing: 8,
                children: ad.paymentMethods
                    .map((m) => ChoiceChip(
                          label: Text(m),
                          selected: _paymentMethod == m,
                          onSelected: (_) =>
                              setState(() => _paymentMethod = m),
                          selectedColor: _kGold.withOpacity(0.2),
                          labelStyle: TextStyle(
                              color:
                                  _paymentMethod == m ? _kGold : _kMuted),
                          backgroundColor: _kBg,
                          side: BorderSide(
                              color: _paymentMethod == m
                                  ? _kGold
                                  : _kBorder),
                        ))
                    .toList(),
              ),
              const SizedBox(height: 12),
            ],
            if (ad.terms.isNotEmpty) ...[
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                    color: _kBg, borderRadius: BorderRadius.circular(8)),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(Icons.info_outline,
                        color: _kMuted, size: 14),
                    const SizedBox(width: 6),
                    Expanded(
                        child: Text(ad.terms,
                            style: const TextStyle(
                                color: _kMuted, fontSize: 12))),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
            if (_err != null) ...[
              Text(_err!,
                  style: const TextStyle(color: _kRed, fontSize: 13)),
              const SizedBox(height: 8),
            ],
            _GoldBtn(
                label: _loading ? 'Placing...' : 'Place Order',
                onTap: _loading ? null : _place),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }
}

class _MyP2POrders extends ConsumerWidget {
  const _MyP2POrders();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(myP2pOrdersProvider(0));
    return ordersAsync.when(
      loading: () => const Center(child: CircularProgressIndicator(color: _kGold)),
      error: (e, _) => _ErrView(e.toString(),
          onRetry: () => ref.invalidate(myP2pOrdersProvider(0))),
      data: (orders) {
        if (orders.isEmpty) {
          return const Center(
              child: Text('No P2P orders yet',
                  style: TextStyle(color: _kMuted)));
        }
        return RefreshIndicator(
          color: _kGold,
          onRefresh: () async => ref.invalidate(myP2pOrdersProvider(0)),
          child: ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: orders.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (_, i) => _P2POrderCard(order: orders[i]),
          ),
        );
      },
    );
  }
}

class _P2POrderCard extends StatelessWidget {
  const _P2POrderCard({required this.order});
  final P2pOrder order;

  static const _statusColors = {
    'payment_waiting': _kGold,
    'paid': Colors.blue,
    'completed': _kGreen,
    'cancelled': _kMuted,
    'disputed': _kRed,
  };

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColors[order.status] ?? _kMuted;
    return GestureDetector(
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => _P2POrderDetailScreen(order: order))),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: _kCard,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: _kBorder),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(order.isBuyer ? 'Buying' : 'Selling',
                    style: TextStyle(
                        color: order.isBuyer ? _kGreen : _kRed,
                        fontWeight: FontWeight.w700,
                        fontSize: 14)),
                const SizedBox(width: 6),
                Text(
                    '${order.cryptoAmount.toStringAsFixed(6)} ${order.coinSymbol}',
                    style: const TextStyle(color: _kText, fontSize: 14)),
                const Spacer(),
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: statusColor.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                      order.status.replaceAll('_', ' ').toUpperCase(),
                      style: TextStyle(
                          color: statusColor,
                          fontSize: 10,
                          fontWeight: FontWeight.w700)),
                ),
              ],
            ),
            const SizedBox(height: 6),
            Text(
                'With: ${order.counterpartyName} · \$${order.totalUsd.toStringAsFixed(2)}',
                style: const TextStyle(color: _kMuted, fontSize: 12)),
          ],
        ),
      ),
    );
  }
}

class _P2POrderDetailScreen extends ConsumerStatefulWidget {
  const _P2POrderDetailScreen({required this.order});
  final P2pOrder order;

  @override
  ConsumerState<_P2POrderDetailScreen> createState() =>
      _P2POrderDetailScreenState();
}

class _P2POrderDetailScreenState
    extends ConsumerState<_P2POrderDetailScreen> {
  final _msgCtrl = TextEditingController();
  List<Map<String, dynamic>> _messages = [];
  bool _loadingAction = false;

  @override
  void initState() {
    super.initState();
    _loadMessages();
  }

  @override
  void dispose() {
    _msgCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadMessages() async {
    try {
      final msgs =
          await CryptoRepository.create().getP2pMessages(widget.order.uuid);
      if (mounted) setState(() => _messages = msgs);
    } catch (_) {}
  }

  Future<void> _sendMsg() async {
    final txt = _msgCtrl.text.trim();
    if (txt.isEmpty) return;
    _msgCtrl.clear();
    try {
      await CryptoRepository.create()
          .sendP2pMessage(widget.order.uuid, txt);
      _loadMessages();
    } catch (_) {}
  }

  Future<void> _action(Future<void> Function() fn, String successMsg) async {
    setState(() => _loadingAction = true);
    try {
      await fn();
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(successMsg), backgroundColor: _kGreen));
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text(e.toString()), backgroundColor: _kRed));
        setState(() => _loadingAction = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final repo = CryptoRepository.create();
    final o = widget.order;
    return Theme(
      data: ThemeData.dark().copyWith(scaffoldBackgroundColor: _kBg),
      child: Scaffold(
        backgroundColor: _kBg,
        appBar: AppBar(
          backgroundColor: _kBg,
          title: Text('P2P Order · ${o.coinSymbol}',
              style: const TextStyle(color: _kText)),
        ),
        body: Column(
          children: [
            Container(
              margin: const EdgeInsets.all(16),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: _kCard,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _kBorder),
              ),
              child: Column(
                children: [
                  _QRow('Amount',
                      '${o.cryptoAmount.toStringAsFixed(6)} ${o.coinSymbol}'),
                  _QRow('Total', '\$${o.totalUsd.toStringAsFixed(2)}'),
                  _QRow('Payment', o.paymentMethod),
                  _QRow('Counterparty', o.counterpartyName),
                  _QRow('Status', o.status.replaceAll('_', ' ')),
                  if (o.expiresAt != null) _QRow('Expires', o.expiresAt!),
                ],
              ),
            ),
            if (o.canMarkPaid || o.canRelease)
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  children: [
                    if (o.canMarkPaid)
                      Expanded(
                        child: _GoldBtn(
                          label: _loadingAction ? '...' : 'I Have Paid',
                          onTap: _loadingAction
                              ? null
                              : () => _action(
                                  () => repo.markP2pPaid(o.uuid),
                                  'Payment marked!'),
                        ),
                      ),
                    if (o.canRelease)
                      Expanded(
                        child: ElevatedButton(
                          onPressed: _loadingAction
                              ? null
                              : () => _action(
                                  () => repo.releaseCrypto(o.uuid),
                                  'Crypto released!'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: _kGreen,
                            foregroundColor: Colors.white,
                            padding:
                                const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12)),
                          ),
                          child: const Text('Release Crypto',
                              style:
                                  TextStyle(fontWeight: FontWeight.w700)),
                        ),
                      ),
                    if (o.isActive) ...[
                      const SizedBox(width: 8),
                      OutlinedButton(
                        onPressed: _loadingAction
                            ? null
                            : () => _action(
                                () => repo.cancelP2pOrder(o.uuid),
                                'Order cancelled'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: _kRed,
                          side: const BorderSide(color: _kRed),
                          padding: const EdgeInsets.symmetric(
                              vertical: 14, horizontal: 16),
                          shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12)),
                        ),
                        child: const Text('Cancel'),
                      ),
                    ],
                  ],
                ),
              ),
            const SizedBox(height: 12),
            const Divider(color: _kBorder, height: 1),
            Expanded(
              child: _messages.isEmpty
                  ? const Center(
                      child: Text('No messages',
                          style: TextStyle(color: _kMuted)))
                  : ListView.builder(
                      padding: const EdgeInsets.all(12),
                      itemCount: _messages.length,
                      itemBuilder: (_, i) {
                        final m = _messages[i];
                        final isMe = m['is_mine'] == true;
                        return Align(
                          alignment: isMe
                              ? Alignment.centerRight
                              : Alignment.centerLeft,
                          child: Container(
                            margin: const EdgeInsets.only(bottom: 8),
                            padding: const EdgeInsets.symmetric(
                                horizontal: 12, vertical: 8),
                            constraints: BoxConstraints(
                                maxWidth:
                                    MediaQuery.of(context).size.width *
                                        0.7),
                            decoration: BoxDecoration(
                              color: isMe
                                  ? _kGold.withOpacity(0.15)
                                  : _kCard,
                              borderRadius: BorderRadius.circular(12),
                              border: Border.all(
                                  color: isMe
                                      ? _kGold.withOpacity(0.3)
                                      : _kBorder),
                            ),
                            child: Text(m['message'] ?? '',
                                style: const TextStyle(
                                    color: _kText, fontSize: 13)),
                          ),
                        );
                      },
                    ),
            ),
            Container(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 20),
              decoration: const BoxDecoration(
                  border:
                      Border(top: BorderSide(color: _kBorder))),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _msgCtrl,
                      style:
                          const TextStyle(color: _kText, fontSize: 13),
                      decoration: InputDecoration(
                        hintText: 'Message...',
                        hintStyle: const TextStyle(color: _kMuted),
                        filled: true,
                        fillColor: _kCard,
                        contentPadding: const EdgeInsets.symmetric(
                            horizontal: 14, vertical: 10),
                        border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(24),
                            borderSide:
                                const BorderSide(color: _kBorder)),
                        enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(24),
                            borderSide:
                                const BorderSide(color: _kBorder)),
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton(
                    onPressed: _sendMsg,
                    icon: const Icon(Icons.send, color: _kGold),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// WALLET TAB
// ═══════════════════════════════════════════════════════════════════════════════

class _WalletTab extends ConsumerWidget {
  const _WalletTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final portfolioAsync = ref.watch(cryptoPortfolioProvider);
    final txAsync = ref.watch(cryptoTransactionsProvider);

    return portfolioAsync.when(
      loading: () => const Center(child: CircularProgressIndicator(color: _kGold)),
      error: (e, _) =>
          _ErrView(e.toString(), onRetry: () => ref.invalidate(cryptoPortfolioProvider)),
      data: (data) {
        final assets = (data['assets'] as List? ?? []);

        return RefreshIndicator(
          color: _kGold,
          onRefresh: () async {
            ref.invalidate(cryptoPortfolioProvider);
            ref.invalidate(cryptoTransactionsProvider);
          },
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('My Wallets',
                    style: TextStyle(
                        color: _kText,
                        fontWeight: FontWeight.w700,
                        fontSize: 18)),
                const SizedBox(height: 12),
                if (assets.isEmpty)
                  const Center(
                    child: Padding(
                      padding: EdgeInsets.all(32),
                      child: Text(
                          'No wallets yet.\nBuy your first crypto to get started!',
                          style: TextStyle(color: _kMuted),
                          textAlign: TextAlign.center),
                    ),
                  )
                else
                  ...assets.map((a) {
                    final symbol = a['symbol']?.toString() ?? '';
                    final name = a['name']?.toString() ?? '';
                    final balance = (a['balance'] ?? 0).toDouble();
                    final usdValue = (a['usd_value'] ?? 0).toDouble();
                    final logoUrl = a['logo_url']?.toString();

                    return Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: _kCard,
                        borderRadius: BorderRadius.circular(14),
                        border: Border.all(color: _kBorder),
                      ),
                      child: Column(
                        children: [
                          Row(
                            children: [
                              _CoinAvatarRaw(
                                  symbol: symbol,
                                  logoUrl: logoUrl,
                                  size: 40),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.start,
                                  children: [
                                    Text(symbol,
                                        style: const TextStyle(
                                            color: _kText,
                                            fontWeight: FontWeight.w700,
                                            fontSize: 16)),
                                    Text(name,
                                        style: const TextStyle(
                                            color: _kMuted, fontSize: 12)),
                                  ],
                                ),
                              ),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  Text(
                                      '${balance.toStringAsFixed(6)} $symbol',
                                      style: const TextStyle(
                                          color: _kText,
                                          fontWeight: FontWeight.w600,
                                          fontSize: 14)),
                                  Text('\$${usdValue.toStringAsFixed(2)}',
                                      style: const TextStyle(
                                          color: _kMuted, fontSize: 12)),
                                ],
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: _WalletActionBtn(
                                  icon: Icons.arrow_downward,
                                  label: 'Deposit',
                                  color: _kGreen,
                                  onTap: () => showModalBottomSheet(
                                    context: context,
                                    backgroundColor: _kCard,
                                    isScrollControlled: true,
                                    builder: (_) =>
                                        _DepositSheet(symbol: symbol),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: _WalletActionBtn(
                                  icon: Icons.arrow_upward,
                                  label: 'Withdraw',
                                  color: _kRed,
                                  onTap: () => showModalBottomSheet(
                                    context: context,
                                    backgroundColor: _kCard,
                                    isScrollControlled: true,
                                    builder: (_) =>
                                        _WithdrawSheet(symbol: symbol),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: _WalletActionBtn(
                                  icon: Icons.swap_horiz,
                                  label: 'Transfer',
                                  color: Colors.blue,
                                  onTap: () => showModalBottomSheet(
                                    context: context,
                                    backgroundColor: _kCard,
                                    isScrollControlled: true,
                                    builder: (_) =>
                                        _TransferSheet(symbol: symbol),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    );
                  }),
                const SizedBox(height: 20),
                const Text('Transactions',
                    style: TextStyle(
                        color: _kText,
                        fontWeight: FontWeight.w700,
                        fontSize: 16)),
                const SizedBox(height: 12),
                txAsync.when(
                  loading: () => const Center(
                      child: CircularProgressIndicator(color: _kGold)),
                  error: (_, __) => const Text(
                      'Could not load transactions',
                      style: TextStyle(color: _kMuted)),
                  data: (txs) {
                    if (txs.isEmpty) {
                      return const Center(
                          child: Padding(
                        padding: EdgeInsets.all(32),
                        child: Text('No transactions yet',
                            style: TextStyle(color: _kMuted)),
                      ));
                    }
                    return Column(
                      children: txs.map((tx) => _TxRow(tx: tx)).toList(),
                    );
                  },
                ),
                const SizedBox(height: 80),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _WalletActionBtn extends StatelessWidget {
  const _WalletActionBtn(
      {required this.icon,
      required this.label,
      required this.color,
      required this.onTap});
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        color: color.withOpacity(0.1),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: color.withOpacity(0.3)),
      ),
      child: Column(
        children: [
          Icon(icon, color: color, size: 18),
          const SizedBox(height: 4),
          Text(label,
              style: TextStyle(
                  color: color,
                  fontSize: 11,
                  fontWeight: FontWeight.w600)),
        ],
      ),
    ),
  );
}

class _DepositSheet extends StatefulWidget {
  const _DepositSheet({required this.symbol});
  final String symbol;

  @override
  State<_DepositSheet> createState() => _DepositSheetState();
}

class _DepositSheetState extends State<_DepositSheet> {
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _err;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final d =
          await CryptoRepository.create().getDepositAddress(widget.symbol);
      if (mounted) setState(() { _data = d; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _err = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Deposit ${widget.symbol}',
              style: const TextStyle(
                  color: _kText, fontSize: 18, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          if (_loading)
            const Center(child: CircularProgressIndicator(color: _kGold))
          else if (_err != null)
            Text(_err!, style: const TextStyle(color: _kRed))
          else if (_data != null) ...[
            const Text('Network',
                style: TextStyle(color: _kMuted, fontSize: 12)),
            const SizedBox(height: 4),
            Text(_data!['network']?.toString() ?? '—',
                style: const TextStyle(
                    color: _kText, fontWeight: FontWeight.w600)),
            const SizedBox(height: 16),
            const Text('Deposit Address',
                style: TextStyle(color: _kMuted, fontSize: 12)),
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                  color: _kBg,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: _kBorder)),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                        _data!['address']?.toString() ?? 'No address yet',
                        style: const TextStyle(
                            color: _kText,
                            fontSize: 12,
                            fontFamily: 'monospace')),
                  ),
                  IconButton(
                    icon: const Icon(Icons.copy, color: _kGold, size: 18),
                    onPressed: () {
                      Clipboard.setData(ClipboardData(
                          text: _data!['address']?.toString() ?? ''));
                      ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(
                              content: Text('Address copied'),
                              backgroundColor: _kGreen));
                    },
                  ),
                ],
              ),
            ),
            if (_data!['memo'] != null) ...[
              const SizedBox(height: 12),
              const Text('Memo / Tag',
                  style: TextStyle(color: _kMuted, fontSize: 12)),
              const SizedBox(height: 4),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: _kBg,
                  borderRadius: BorderRadius.circular(10),
                  border:
                      Border.all(color: _kGold.withOpacity(0.3)),
                ),
                child: Text(_data!['memo']?.toString() ?? '',
                    style: const TextStyle(color: _kGold, fontSize: 13)),
              ),
            ],
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: _kGold.withOpacity(0.08),
                borderRadius: BorderRadius.circular(10),
                border:
                    Border.all(color: _kGold.withOpacity(0.2)),
              ),
              child: const Row(
                children: [
                  Icon(Icons.warning_amber_outlined,
                      color: _kGold, size: 16),
                  SizedBox(width: 8),
                  Expanded(
                      child: Text(
                          'Only send this coin to this address. Wrong coin or network will result in permanent loss.',
                          style: TextStyle(
                              color: _kGold, fontSize: 11))),
                ],
              ),
            ),
          ],
          const SizedBox(height: 20),
        ],
      ),
    );
  }
}

class _WithdrawSheet extends ConsumerStatefulWidget {
  const _WithdrawSheet({required this.symbol});
  final String symbol;

  @override
  ConsumerState<_WithdrawSheet> createState() => _WithdrawSheetState();
}

class _WithdrawSheetState extends ConsumerState<_WithdrawSheet> {
  final _addrCtrl = TextEditingController();
  final _amtCtrl = TextEditingController();
  CoinNetwork? _network;
  bool _loading = false;
  String? _err;

  @override
  void dispose() {
    _addrCtrl.dispose();
    _amtCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final addr = _addrCtrl.text.trim();
    final amt = double.tryParse(_amtCtrl.text);
    if (addr.isEmpty || amt == null || _network == null) return;
    setState(() { _loading = true; _err = null; });
    try {
      await CryptoRepository.create().withdraw(
        symbol: widget.symbol,
        networkId: _network!.id,
        amount: amt,
        toAddress: addr,
      );
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Withdrawal submitted'),
            backgroundColor: _kGreen));
      }
    } catch (e) {
      setState(() { _err = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final marketsAsync = ref.watch(cryptoMarketsProvider);
    final coins = marketsAsync.value ?? [];
    final coin = coins.where((c) => c.symbol == widget.symbol).firstOrNull;

    if (_network == null && coin != null && coin.networks.isNotEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) setState(() => _network = coin.firstNetwork);
      });
    }

    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Withdraw ${widget.symbol}',
                style: const TextStyle(
                    color: _kText,
                    fontSize: 18,
                    fontWeight: FontWeight.w700)),
            const SizedBox(height: 16),
            if (coin != null && coin.networks.length > 1) ...[
              _NetworkDropdown(
                networks: coin.networks,
                selected: _network,
                onSelect: (n) => setState(() => _network = n),
              ),
              const SizedBox(height: 12),
            ],
            TextField(
              controller: _addrCtrl,
              style: const TextStyle(color: _kText, fontSize: 13),
              decoration: const InputDecoration(
                labelText: 'To Address',
                labelStyle: TextStyle(color: _kMuted),
                filled: true,
                fillColor: _kBg,
                border: OutlineInputBorder(
                    borderSide: BorderSide(color: _kBorder)),
                enabledBorder: OutlineInputBorder(
                    borderSide: BorderSide(color: _kBorder)),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _amtCtrl,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              style: const TextStyle(color: _kText),
              decoration: InputDecoration(
                labelText: 'Amount (${widget.symbol})',
                labelStyle: const TextStyle(color: _kMuted),
                helperText: _network != null
                    ? 'Fee: ${_network!.withdrawalFee} ${widget.symbol}'
                    : null,
                helperStyle: const TextStyle(color: _kMuted, fontSize: 11),
                filled: true,
                fillColor: _kBg,
                border: const OutlineInputBorder(
                    borderSide: BorderSide(color: _kBorder)),
                enabledBorder: const OutlineInputBorder(
                    borderSide: BorderSide(color: _kBorder)),
              ),
            ),
            if (_err != null) ...[
              const SizedBox(height: 10),
              Text(_err!, style: const TextStyle(color: _kRed, fontSize: 13)),
            ],
            const SizedBox(height: 16),
            _GoldBtn(
                label: _loading ? 'Submitting...' : 'Withdraw',
                onTap: _loading ? null : _submit),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }
}

class _TransferSheet extends ConsumerStatefulWidget {
  const _TransferSheet({required this.symbol});
  final String symbol;

  @override
  ConsumerState<_TransferSheet> createState() => _TransferSheetState();
}

class _TransferSheetState extends ConsumerState<_TransferSheet> {
  final _phoneCtrl = TextEditingController();
  final _amtCtrl = TextEditingController();
  CoinNetwork? _network;
  bool _loading = false;
  String? _err;

  @override
  void dispose() {
    _phoneCtrl.dispose();
    _amtCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final phone = _phoneCtrl.text.trim();
    final amt = double.tryParse(_amtCtrl.text);
    if (phone.isEmpty || amt == null || _network == null) return;
    setState(() { _loading = true; _err = null; });
    try {
      await CryptoRepository.create().transfer(
        symbol: widget.symbol,
        networkId: _network!.id,
        toPhone: phone,
        amount: amt,
      );
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
            content: Text('Transfer sent'), backgroundColor: _kGreen));
      }
    } catch (e) {
      setState(() { _err = e.toString(); _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final marketsAsync = ref.watch(cryptoMarketsProvider);
    final coins = marketsAsync.value ?? [];
    final coin = coins.where((c) => c.symbol == widget.symbol).firstOrNull;

    if (_network == null && coin != null && coin.networks.isNotEmpty) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) setState(() => _network = coin.firstNetwork);
      });
    }

    return Padding(
      padding:
          EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Container(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Transfer ${widget.symbol}',
                style: const TextStyle(
                    color: _kText,
                    fontSize: 18,
                    fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            const Text('Send to another eSahlan user',
                style: TextStyle(color: _kMuted, fontSize: 12)),
            const SizedBox(height: 16),
            if (coin != null && coin.networks.length > 1) ...[
              _NetworkDropdown(
                  networks: coin.networks,
                  selected: _network,
                  onSelect: (n) => setState(() => _network = n)),
              const SizedBox(height: 12),
            ],
            TextField(
              controller: _phoneCtrl,
              keyboardType: TextInputType.phone,
              style: const TextStyle(color: _kText),
              decoration: const InputDecoration(
                labelText: 'Recipient Phone',
                labelStyle: TextStyle(color: _kMuted),
                prefixIcon: Icon(Icons.phone, color: _kMuted, size: 18),
                filled: true,
                fillColor: _kBg,
                border: OutlineInputBorder(
                    borderSide: BorderSide(color: _kBorder)),
                enabledBorder: OutlineInputBorder(
                    borderSide: BorderSide(color: _kBorder)),
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _amtCtrl,
              keyboardType:
                  const TextInputType.numberWithOptions(decimal: true),
              style: const TextStyle(color: _kText),
              decoration: InputDecoration(
                labelText: 'Amount (${widget.symbol})',
                labelStyle: const TextStyle(color: _kMuted),
                filled: true,
                fillColor: _kBg,
                border: const OutlineInputBorder(
                    borderSide: BorderSide(color: _kBorder)),
                enabledBorder: const OutlineInputBorder(
                    borderSide: BorderSide(color: _kBorder)),
              ),
            ),
            if (_err != null) ...[
              const SizedBox(height: 10),
              Text(_err!, style: const TextStyle(color: _kRed, fontSize: 13)),
            ],
            const SizedBox(height: 16),
            _GoldBtn(
                label: _loading ? 'Sending...' : 'Send',
                onTap: _loading ? null : _submit),
            const SizedBox(height: 8),
          ],
        ),
      ),
    );
  }
}

class _TxRow extends StatelessWidget {
  const _TxRow({required this.tx});
  final CryptoTransaction tx;

  @override
  Widget build(BuildContext context) {
    final isCredit = tx.isCredit;
    final icon = switch (tx.type) {
      'buy' => Icons.shopping_cart_outlined,
      'sell' => Icons.sell_outlined,
      'deposit' => Icons.arrow_downward,
      'withdrawal' => Icons.arrow_upward,
      'transfer_in' => Icons.call_received,
      'transfer_out' => Icons.call_made,
      _ => Icons.swap_horiz,
    };

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: _kCard,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: _kBorder),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: (isCredit ? _kGreen : _kRed).withOpacity(0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon,
                color: isCredit ? _kGreen : _kRed, size: 18),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(tx.type.replaceAll('_', ' ').toUpperCase(),
                    style: const TextStyle(
                        color: _kText,
                        fontWeight: FontWeight.w600,
                        fontSize: 12)),
                Text(tx.note,
                    style: const TextStyle(color: _kMuted, fontSize: 11),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                  '${isCredit ? '+' : '-'}${tx.amount.toStringAsFixed(6)} ${tx.coinSymbol}',
                  style: TextStyle(
                      color: isCredit ? _kGreen : _kRed,
                      fontWeight: FontWeight.w600,
                      fontSize: 13)),
              Text(
                  tx.createdAt.length > 10
                      ? tx.createdAt.substring(0, 10)
                      : tx.createdAt,
                  style: const TextStyle(color: _kMuted, fontSize: 10)),
            ],
          ),
        ],
      ),
    );
  }
}

// ═══════════════════════════════════════════════════════════════════════════════
// SHARED WIDGETS
// ═══════════════════════════════════════════════════════════════════════════════

class _CoinAvatar extends StatelessWidget {
  const _CoinAvatar({required this.coin, required this.size});
  final CryptoCoin coin;
  final double size;

  @override
  Widget build(BuildContext context) =>
      _CoinAvatarRaw(symbol: coin.symbol, logoUrl: coin.logoUrl, size: size);
}

class _CoinAvatarRaw extends StatelessWidget {
  const _CoinAvatarRaw(
      {required this.symbol, this.logoUrl, required this.size});
  final String symbol;
  final String? logoUrl;
  final double size;

  @override
  Widget build(BuildContext context) {
    if (logoUrl != null && logoUrl!.isNotEmpty) {
      return CachedNetworkImage(
        imageUrl: logoUrl!,
        width: size,
        height: size,
        imageBuilder: (_, img) =>
            CircleAvatar(radius: size / 2, backgroundImage: img),
        errorWidget: (_, __, ___) =>
            _Fallback(symbol: symbol, size: size),
        placeholder: (_, __) => _Fallback(symbol: symbol, size: size),
      );
    }
    return _Fallback(symbol: symbol, size: size);
  }
}

class _Fallback extends StatelessWidget {
  const _Fallback({required this.symbol, required this.size});
  final String symbol;
  final double size;

  @override
  Widget build(BuildContext context) => CircleAvatar(
    radius: size / 2,
    backgroundColor: _kGold.withOpacity(0.2),
    child: Text(
        symbol.length >= 2 ? symbol.substring(0, 2) : symbol,
        style: TextStyle(
            color: _kGold,
            fontWeight: FontWeight.w700,
            fontSize: size * 0.32)),
  );
}

class _Badge extends StatelessWidget {
  const _Badge(this.value);
  final double value;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
    decoration: BoxDecoration(
      color: _changeColor(value).withOpacity(0.12),
      borderRadius: BorderRadius.circular(6),
    ),
    child: Text(_pct(value),
        style: TextStyle(
            color: _changeColor(value),
            fontSize: 11,
            fontWeight: FontWeight.w600)),
  );
}

class _GoldBtn extends StatelessWidget {
  const _GoldBtn({required this.label, this.onTap});
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => ElevatedButton(
    onPressed: onTap,
    style: ElevatedButton.styleFrom(
      backgroundColor: _kGold,
      foregroundColor: Colors.black,
      disabledBackgroundColor: _kGold.withOpacity(0.4),
      minimumSize: const Size(double.infinity, 50),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),
    child: Text(label,
        style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
  );
}

class _ToggleBtn extends StatelessWidget {
  const _ToggleBtn(this.label, this.selected,
      {required this.onTap, required this.color});
  final String label;
  final bool selected;
  final VoidCallback onTap;
  final Color color;

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        color: selected ? color.withOpacity(0.15) : Colors.transparent,
        borderRadius: BorderRadius.circular(10),
        border: selected ? Border.all(color: color.withOpacity(0.4)) : null,
      ),
      child: Text(label,
          textAlign: TextAlign.center,
          style: TextStyle(
              color: selected ? color : _kMuted,
              fontWeight:
                  selected ? FontWeight.w700 : FontWeight.normal,
              fontSize: 14)),
    ),
  );
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);
  final String text;

  @override
  Widget build(BuildContext context) => Text(text,
      style: const TextStyle(
          color: _kMuted, fontSize: 12, fontWeight: FontWeight.w500));
}

class _CoinSelector extends StatelessWidget {
  const _CoinSelector(
      {this.selected, this.coins, required this.onSelect});
  final CryptoCoin? selected;
  final List<CryptoCoin>? coins;
  final void Function(CryptoCoin) onSelect;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () {
        if (coins == null || coins!.isEmpty) return;
        showModalBottomSheet(
          context: context,
          backgroundColor: _kCard,
          isScrollControlled: true,
          builder: (_) =>
              _CoinPickerSheet(coins: coins!, onSelect: onSelect),
        );
      },
      child: Container(
        padding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: _kCard,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: _kBorder),
        ),
        child: Row(
          children: [
            if (selected != null) ...[
              _CoinAvatar(coin: selected!, size: 28),
              const SizedBox(width: 10),
              Text(selected!.symbol,
                  style: const TextStyle(
                      color: _kText,
                      fontWeight: FontWeight.w600,
                      fontSize: 15)),
              const SizedBox(width: 6),
              Text(selected!.name,
                  style:
                      const TextStyle(color: _kMuted, fontSize: 13)),
            ] else
              const Text('Select a coin',
                  style: TextStyle(color: _kMuted)),
            const Spacer(),
            const Icon(Icons.keyboard_arrow_down, color: _kMuted),
          ],
        ),
      ),
    );
  }
}

class _CoinPickerSheet extends StatefulWidget {
  const _CoinPickerSheet(
      {required this.coins, required this.onSelect});
  final List<CryptoCoin> coins;
  final void Function(CryptoCoin) onSelect;

  @override
  State<_CoinPickerSheet> createState() => _CoinPickerSheetState();
}

class _CoinPickerSheetState extends State<_CoinPickerSheet> {
  final _ctrl = TextEditingController();
  String _q = '';

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final filtered = widget.coins
        .where((c) =>
            _q.isEmpty ||
            c.symbol.toLowerCase().contains(_q.toLowerCase()) ||
            c.name.toLowerCase().contains(_q.toLowerCase()))
        .toList();

    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.7,
      child: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _ctrl,
              onChanged: (v) => setState(() => _q = v),
              style: const TextStyle(color: _kText),
              autofocus: true,
              decoration: InputDecoration(
                hintText: 'Search...',
                hintStyle: const TextStyle(color: _kMuted),
                prefixIcon: const Icon(Icons.search, color: _kMuted),
                filled: true,
                fillColor: _kBg,
                border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: const BorderSide(color: _kBorder)),
                enabledBorder: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(10),
                    borderSide: const BorderSide(color: _kBorder)),
              ),
            ),
          ),
          Expanded(
            child: ListView.builder(
              itemCount: filtered.length,
              itemBuilder: (_, i) {
                final c = filtered[i];
                return ListTile(
                  leading: _CoinAvatar(coin: c, size: 36),
                  title: Text(c.symbol,
                      style: const TextStyle(
                          color: _kText, fontWeight: FontWeight.w600)),
                  subtitle: Text(c.name,
                      style: const TextStyle(color: _kMuted)),
                  trailing: Text(c.priceFormatted,
                      style: const TextStyle(color: _kText)),
                  onTap: () {
                    Navigator.pop(context);
                    widget.onSelect(c);
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _NetworkDropdown extends StatelessWidget {
  const _NetworkDropdown(
      {required this.networks,
      required this.selected,
      required this.onSelect});
  final List<CoinNetwork> networks;
  final CoinNetwork? selected;
  final void Function(CoinNetwork) onSelect;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: _kCard,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: _kBorder),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<CoinNetwork>(
          value: selected,
          hint: const Text('Select Network',
              style: TextStyle(color: _kMuted)),
          dropdownColor: _kCard,
          isExpanded: true,
          iconEnabledColor: _kMuted,
          items: networks
              .map((n) => DropdownMenuItem(
                    value: n,
                    child: Text(n.label,
                        style: const TextStyle(
                            color: _kText, fontSize: 13)),
                  ))
              .toList(),
          onChanged: (n) { if (n != null) onSelect(n); },
        ),
      ),
    );
  }
}

class _PayMethodTile extends StatelessWidget {
  const _PayMethodTile(
      {required this.label,
      required this.icon,
      required this.selected,
      required this.onTap});
  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 150),
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: selected ? _kGold.withOpacity(0.08) : _kCard,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(
            color: selected ? _kGold.withOpacity(0.4) : _kBorder),
      ),
      child: Row(
        children: [
          Icon(icon, color: selected ? _kGold : _kMuted, size: 20),
          const SizedBox(width: 10),
          Text(label,
              style: TextStyle(
                  color: selected ? _kGold : _kText,
                  fontWeight: selected
                      ? FontWeight.w600
                      : FontWeight.normal)),
          const Spacer(),
          if (selected)
            const Icon(Icons.check_circle, color: _kGold, size: 18),
        ],
      ),
    ),
  );
}

class _ErrView extends StatelessWidget {
  const _ErrView(this.message, {required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.error_outline, color: _kRed, size: 48),
          const SizedBox(height: 12),
          Text(message,
              style: const TextStyle(color: _kMuted, fontSize: 13),
              textAlign: TextAlign.center),
          const SizedBox(height: 16),
          TextButton(
              onPressed: onRetry,
              child:
                  const Text('Retry', style: TextStyle(color: _kGold))),
        ],
      ),
    ),
  );
}
