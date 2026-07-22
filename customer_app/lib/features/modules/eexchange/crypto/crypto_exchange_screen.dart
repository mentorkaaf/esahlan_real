import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/theme_x.dart';
import 'crypto_models.dart';
import 'crypto_providers.dart';
import 'crypto_repository.dart';

// ═════════════════════════════════════════════════════════════════════════════
// ENTRY POINT
// ═════════════════════════════════════════════════════════════════════════════

class CryptoExchangeScreen extends ConsumerStatefulWidget {
  const CryptoExchangeScreen({super.key});
  @override
  ConsumerState<CryptoExchangeScreen> createState() => _CryptoExchangeScreenState();
}

class _CryptoExchangeScreenState extends ConsumerState<CryptoExchangeScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tab;

  static const _tabs = ['Markets', 'Portfolio', 'Buy/Sell', 'P2P', 'Wallet'];

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: _tabs.length, vsync: this);
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return Scaffold(
      backgroundColor: cs.surface,
      body: NestedScrollView(
        headerSliverBuilder: (_, __) => [
          SliverAppBar(
            pinned: true,
            backgroundColor: cs.surface,
            foregroundColor: cs.onSurface,
            elevation: 0,
            title: const Text('Crypto Exchange', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18)),
            bottom: TabBar(
              controller: _tab,
              isScrollable: true,
              tabAlignment: TabAlignment.start,
              tabs: _tabs.map((t) => Tab(text: t)).toList(),
              labelStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 12),
              unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w500, fontSize: 12),
              indicatorWeight: 3,
            ),
          ),
        ],
        body: TabBarView(
          controller: _tab,
          children: [
            _MarketsTab(onCoinTap: (c) => _tab.animateTo(2)),
            _PortfolioTab(),
            _BuySellTab(),
            _P2PTab(),
            _WalletTab(),
          ],
        ),
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════════════════
// MARKETS TAB
// ═════════════════════════════════════════════════════════════════════════════

class _MarketsTab extends ConsumerStatefulWidget {
  final void Function(CryptoCoin) onCoinTap;
  const _MarketsTab({required this.onCoinTap});
  @override
  ConsumerState<_MarketsTab> createState() => _MarketsTabState();
}

class _MarketsTabState extends ConsumerState<_MarketsTab> {
  String _search = '';

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final marketsAsync = ref.watch(cryptoMarketsProvider);

    return Column(
      children: [
        // Search bar
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
          child: TextField(
            onChanged: (v) => setState(() => _search = v.toLowerCase()),
            decoration: InputDecoration(
              hintText: 'Search coins…',
              prefixIcon: const Icon(Icons.search, size: 18),
              filled: true,
              fillColor: cs.surfaceVariant,
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
              contentPadding: const EdgeInsets.symmetric(vertical: 10),
            ),
          ),
        ),
        // Header
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          child: Row(
            children: [
              Expanded(child: Text('Coin', style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: cs.outline, letterSpacing: .6))),
              SizedBox(width: 90, child: Text('Price', textAlign: TextAlign.right, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: cs.outline, letterSpacing: .6))),
              SizedBox(width: 70, child: Text('24h %', textAlign: TextAlign.right, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: cs.outline, letterSpacing: .6))),
            ],
          ),
        ),
        Expanded(
          child: marketsAsync.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(child: Text('$e')),
            data: (coins) {
              final filtered = _search.isEmpty ? coins : coins.where((c) => c.symbol.toLowerCase().contains(_search) || c.name.toLowerCase().contains(_search)).toList();
              return RefreshIndicator(
                onRefresh: () => ref.refresh(cryptoMarketsProvider.future),
                child: ListView.builder(
                  itemCount: filtered.length,
                  itemBuilder: (_, i) => _CoinRow(coin: filtered[i], onTap: widget.onCoinTap),
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
  final CryptoCoin coin;
  final void Function(CryptoCoin) onTap;
  const _CoinRow({required this.coin, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final isUp = coin.isUp;
    final changeColor = isUp ? const Color(0xFF15803D) : const Color(0xFFB91C1C);
    return InkWell(
      onTap: () => onTap(coin),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Row(
          children: [
            // Coin circle avatar
            Container(
              width: 38, height: 38,
              decoration: BoxDecoration(shape: BoxShape.circle, color: _coinColor(coin.symbol).withValues(alpha: .15)),
              child: Center(child: Text(coin.symbol[0], style: TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: _coinColor(coin.symbol)))),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(coin.symbol, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: cs.onSurface)),
                  Text(coin.name, style: TextStyle(fontSize: 11, color: cs.outline)),
                ],
              ),
            ),
            SizedBox(
              width: 90,
              child: Text(coin.priceFormatted, textAlign: TextAlign.right, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: cs.onSurface)),
            ),
            SizedBox(
              width: 70,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: changeColor.withValues(alpha: .12),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  '${isUp ? '+' : ''}${coin.change24h.toStringAsFixed(2)}%',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: changeColor),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Color _coinColor(String symbol) {
    const map = {'BTC': Color(0xFFF7931A), 'ETH': Color(0xFF627EEA), 'USDT': Color(0xFF26A17B), 'BNB': Color(0xFFF3BA2F), 'SOL': Color(0xFF9945FF), 'XRP': Color(0xFF0085C0), 'DOGE': Color(0xFFC2A633), 'ADA': Color(0xFF0033AD)};
    return map[symbol] ?? const Color(0xFF6366F1);
  }
}

// ═════════════════════════════════════════════════════════════════════════════
// PORTFOLIO TAB
// ═════════════════════════════════════════════════════════════════════════════

class _PortfolioTab extends ConsumerWidget {
  const _PortfolioTab();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = context.cs;
    final portfolioAsync = ref.watch(cryptoPortfolioProvider);

    return portfolioAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [const Icon(Icons.error_outline), const SizedBox(height: 8), Text('$e', textAlign: TextAlign.center), const SizedBox(height: 12), ElevatedButton(onPressed: () => ref.invalidate(cryptoPortfolioProvider), child: const Text('Retry'))])),
      data: (data) {
        final totalUsd = (data['total_usd'] ?? 0).toDouble();
        final wallets = (data['wallets'] as List? ?? []).map((j) => CryptoWalletBalance.fromJson(j)).toList();

        return RefreshIndicator(
          onRefresh: () => ref.refresh(cryptoPortfolioProvider.future),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // Total value card
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(colors: [Color(0xFF6366F1), Color(0xFF8B5CF6)], begin: Alignment.topLeft, end: Alignment.bottomRight),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Total Portfolio', style: TextStyle(color: Colors.white.withValues(alpha: .8), fontSize: 12, fontWeight: FontWeight.w600, letterSpacing: .6)),
                    const SizedBox(height: 6),
                    Text('\$${totalUsd.toStringAsFixed(2)}', style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900, letterSpacing: -1)),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              Text('Your Holdings', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: cs.onSurface)),
              const SizedBox(height: 8),
              ...wallets.where((w) => w.balance > 0).map((w) => _HoldingRow(wallet: w)),
              if (wallets.every((w) => w.balance == 0))
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 40),
                  child: Column(children: [
                    Icon(Icons.account_balance_wallet_outlined, size: 48, color: cs.outline),
                    const SizedBox(height: 8),
                    Text('No holdings yet', style: TextStyle(color: cs.outline)),
                  ]),
                ),
            ],
          ),
        );
      },
    );
  }
}

class _HoldingRow extends StatelessWidget {
  final CryptoWalletBalance wallet;
  const _HoldingRow({required this.wallet});

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: cs.surfaceVariant, borderRadius: BorderRadius.circular(14)),
      child: Row(
        children: [
          Container(
            width: 36, height: 36,
            decoration: BoxDecoration(shape: BoxShape.circle, color: const Color(0xFF6366F1).withValues(alpha: .12)),
            child: Center(child: Text(wallet.symbol[0], style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 13, color: Color(0xFF6366F1)))),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(wallet.symbol, style: TextStyle(fontWeight: FontWeight.w800, color: cs.onSurface)),
              Text('${wallet.balance.toStringAsFixed(6)} ${wallet.symbol}', style: TextStyle(fontSize: 11, color: cs.outline)),
            ],
          )),
          Text('\$${wallet.balanceUsd.toStringAsFixed(2)}', style: TextStyle(fontWeight: FontWeight.w800, color: cs.onSurface, fontSize: 15)),
        ],
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════════════════
// BUY / SELL TAB
// ═════════════════════════════════════════════════════════════════════════════

class _BuySellTab extends ConsumerStatefulWidget {
  const _BuySellTab();
  @override
  ConsumerState<_BuySellTab> createState() => _BuySellTabState();
}

class _BuySellTabState extends ConsumerState<_BuySellTab> {
  String _side = 'buy';
  CryptoCoin? _coin;
  String _payMethod = 'epay_wallet';
  final _amountCtrl = TextEditingController();
  bool _loading = false;
  String? _error;
  Map<String, dynamic>? _result;

  static const _payMethods = [
    {'id': 'epay_wallet', 'label': 'ePay Wallet'},
    {'id': 'evc',         'label': 'EVC Plus'},
    {'id': 'edahab',      'label': 'eDahab'},
    {'id': 'waafi',       'label': 'Waafi Pay'},
  ];

  @override
  void dispose() {
    _amountCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final marketsAsync = ref.watch(cryptoMarketsProvider);
    final quoteAsync = ref.watch(quoteProvider);

    return marketsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(child: Text('$e')),
      data: (coins) {
        _coin ??= coins.isNotEmpty ? coins.first : null;
        return SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Buy/Sell toggle
              Container(
                decoration: BoxDecoration(color: cs.surfaceVariant, borderRadius: BorderRadius.circular(12)),
                padding: const EdgeInsets.all(4),
                child: Row(
                  children: ['buy', 'sell'].map((s) => Expanded(
                    child: GestureDetector(
                      onTap: () { setState(() { _side = s; _result = null; }); ref.read(quoteProvider.notifier).reset(); },
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                        decoration: BoxDecoration(
                          color: _side == s ? (s == 'buy' ? const Color(0xFF15803D) : const Color(0xFFB91C1C)) : Colors.transparent,
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text(s == 'buy' ? 'BUY' : 'SELL', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w900, color: _side == s ? Colors.white : cs.outline, fontSize: 13)),
                      ),
                    ),
                  )).toList(),
                ),
              ),
              const SizedBox(height: 16),

              // Coin selector
              Text('Coin', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: cs.outline, letterSpacing: .6)),
              const SizedBox(height: 6),
              DropdownButtonFormField<CryptoCoin>(
                value: _coin,
                onChanged: (c) => setState(() { _coin = c; _result = null; }),
                decoration: InputDecoration(
                  filled: true, fillColor: cs.surfaceVariant,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                ),
                items: coins.map((c) => DropdownMenuItem(value: c, child: Text('${c.symbol}  •  ${c.priceFormatted}', style: const TextStyle(fontWeight: FontWeight.w700)))).toList(),
              ),
              const SizedBox(height: 12),

              // Amount
              Text('Amount (USD)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: cs.outline, letterSpacing: .6)),
              const SizedBox(height: 6),
              TextField(
                controller: _amountCtrl,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[\d.]'))],
                decoration: InputDecoration(
                  hintText: '0.00',
                  prefixText: '\$  ',
                  filled: true, fillColor: cs.surfaceVariant,
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                  contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                ),
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
              ),
              const SizedBox(height: 12),

              // Payment method
              Text('Payment Method', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: cs.outline, letterSpacing: .6)),
              const SizedBox(height: 6),
              Wrap(
                spacing: 8, runSpacing: 8,
                children: _payMethods.map((m) {
                  final sel = _payMethod == m['id'];
                  return GestureDetector(
                    onTap: () => setState(() => _payMethod = m['id']!),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 150),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                      decoration: BoxDecoration(
                        color: sel ? const Color(0xFF6366F1) : cs.surfaceVariant,
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: sel ? const Color(0xFF6366F1) : Colors.transparent, width: 2),
                      ),
                      child: Text(m['label']!, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: sel ? Colors.white : cs.onSurface)),
                    ),
                  );
                }).toList(),
              ),
              const SizedBox(height: 20),

              // Quote result
              if (quoteAsync.hasValue && quoteAsync.value != null) ...[
                _QuoteCard(quote: quoteAsync.value!, side: _side, coin: _coin!),
                const SizedBox(height: 12),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _loading ? null : _executeTrade,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _side == 'buy' ? const Color(0xFF15803D) : const Color(0xFFB91C1C),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    child: _loading ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : Text('Confirm ${_side == 'buy' ? 'Buy' : 'Sell'}', style: const TextStyle(fontWeight: FontWeight.w900)),
                  ),
                ),
              ] else ...[
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: _getQuote,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF6366F1),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                    ),
                    child: quoteAsync.isLoading
                        ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                        : const Text('Get Quote', style: TextStyle(fontWeight: FontWeight.w900)),
                  ),
                ),
              ],

              if (quoteAsync.hasError) ...[
                const SizedBox(height: 8),
                Text('${quoteAsync.error}', style: const TextStyle(color: Color(0xFFB91C1C), fontSize: 12)),
              ],

              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(_error!, style: const TextStyle(color: Color(0xFFB91C1C), fontSize: 12)),
              ],

              if (_result != null) ...[
                const SizedBox(height: 16),
                _SuccessCard(result: _result!, side: _side),
              ],
            ],
          ),
        );
      },
    );
  }

  void _getQuote() {
    if (_coin == null) return;
    final amount = double.tryParse(_amountCtrl.text.trim());
    if (amount == null || amount <= 0) {
      setState(() => _error = 'Enter a valid amount');
      return;
    }
    setState(() => _error = null);
    ref.read(quoteProvider.notifier).getQuote(coinId: _coin!.id, side: _side, amountUsd: amount, paymentMethod: _payMethod);
  }

  Future<void> _executeTrade() async {
    if (_coin == null) return;
    final amount = double.tryParse(_amountCtrl.text.trim()) ?? 0;
    setState(() { _loading = true; _error = null; });
    try {
      final res = await ref.read(quoteProvider.notifier).executeTrade(coinId: _coin!.id, side: _side, amountUsd: amount, paymentMethod: _payMethod);
      setState(() { _result = res; });
      ref.read(quoteProvider.notifier).reset();
      ref.invalidate(cryptoPortfolioProvider);
      ref.invalidate(cryptoOrdersProvider);
    } catch (e) {
      setState(() => _error = '$e');
    } finally {
      setState(() => _loading = false);
    }
  }
}

class _QuoteCard extends StatelessWidget {
  final Map<String, dynamic> quote;
  final String side;
  final CryptoCoin coin;
  const _QuoteCard({required this.quote, required this.side, required this.coin});

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final qty = (quote['quantity'] ?? 0).toDouble();
    final price = (quote['price_usd'] ?? 0).toDouble();
    final fee = (quote['fee_usd'] ?? 0).toDouble();
    final total = (quote['total_usd'] ?? 0).toDouble();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: cs.surfaceVariant, borderRadius: BorderRadius.circular(14)),
      child: Column(
        children: [
          _QuoteRow('You ${side == 'buy' ? 'receive' : 'sell'}', '${qty.toStringAsFixed(6)} ${coin.symbol}'),
          _QuoteRow('Price', '\$${price.toStringAsFixed(2)}'),
          _QuoteRow('Fee', '\$${fee.toStringAsFixed(2)}'),
          const Divider(),
          _QuoteRow('Total', '\$${total.toStringAsFixed(2)}', bold: true),
          const SizedBox(height: 4),
          Text('Quote valid for 30 seconds', style: TextStyle(fontSize: 10, color: cs.outline)),
        ],
      ),
    );
  }
}

class _QuoteRow extends StatelessWidget {
  final String label;
  final String value;
  final bool bold;
  const _QuoteRow(this.label, this.value, {this.bold = false});

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(label, style: TextStyle(fontSize: 13, color: bold ? cs.onSurface : cs.outline, fontWeight: bold ? FontWeight.w800 : FontWeight.w500)),
          Text(value, style: TextStyle(fontSize: 13, fontWeight: bold ? FontWeight.w900 : FontWeight.w700, color: cs.onSurface)),
        ],
      ),
    );
  }
}

class _SuccessCard extends StatelessWidget {
  final Map<String, dynamic> result;
  final String side;
  const _SuccessCard({required this.result, required this.side});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFFDCFCE7),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFF86EFAC)),
      ),
      child: Row(
        children: [
          const Icon(Icons.check_circle_rounded, color: Color(0xFF15803D), size: 28),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('Order Completed!', style: TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF15803D), fontSize: 15)),
                Text(result['message'] ?? 'Transaction successful', style: const TextStyle(fontSize: 12, color: Color(0xFF15803D))),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ═════════════════════════════════════════════════════════════════════════════
// P2P TAB
// ═════════════════════════════════════════════════════════════════════════════

class _P2PTab extends ConsumerStatefulWidget {
  const _P2PTab();
  @override
  ConsumerState<_P2PTab> createState() => _P2PTabState();
}

class _P2PTabState extends ConsumerState<_P2PTab> {
  String _adType = 'sell'; // show sell ads (I want to buy)
  String? _coin;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final params = {'coin': _coin, 'type': _adType};
    final adsAsync = ref.watch(p2pAdsProvider(params));

    return Column(
      children: [
        // Filter row
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          child: Row(
            children: [
              // I want to buy/sell toggle
              Expanded(
                child: Container(
                  decoration: BoxDecoration(color: cs.surfaceVariant, borderRadius: BorderRadius.circular(10)),
                  padding: const EdgeInsets.all(3),
                  child: Row(
                    children: [
                      _TabChip('Buy', _adType == 'sell', () => setState(() => _adType = 'sell')),
                      _TabChip('Sell', _adType == 'buy', () => setState(() => _adType = 'buy')),
                    ],
                  ),
                ),
              ),
              const SizedBox(width: 10),
              // Post Ad button
              OutlinedButton(
                onPressed: _showCreateAdSheet,
                style: OutlinedButton.styleFrom(padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
                child: const Text('+ Post Ad', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12)),
              ),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Expanded(
          child: adsAsync.when(
            loading: () => const Center(child: CircularProgressIndicator()),
            error: (e, _) => Center(child: Text('$e')),
            data: (ads) {
              if (ads.isEmpty) {
                return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.store_outlined, size: 48, color: cs.outline),
                  const SizedBox(height: 8),
                  Text('No ads available', style: TextStyle(color: cs.outline)),
                ]));
              }
              return RefreshIndicator(
                onRefresh: () => ref.refresh(p2pAdsProvider(params).future),
                child: ListView.builder(
                  itemCount: ads.length,
                  itemBuilder: (_, i) => _AdRow(ad: ads[i], isBuying: _adType == 'sell'),
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  void _showCreateAdSheet() {
    final marketsAsync = ref.read(cryptoMarketsProvider);
    final coins = marketsAsync.valueOrNull ?? [];
    if (coins.isEmpty) return;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _CreateAdSheet(coins: coins, repo: CryptoRepository.create(), onDone: () {
        final params = {'coin': _coin, 'type': _adType};
        ref.invalidate(p2pAdsProvider(params));
      }),
    );
  }
}

class _TabChip extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _TabChip(this.label, this.selected, this.onTap);

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: selected ? const Color(0xFF6366F1) : Colors.transparent,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Text(label, textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 12, color: selected ? Colors.white : context.cs.outline)),
        ),
      ),
    );
  }
}

class _AdRow extends ConsumerWidget {
  final P2pAd ad;
  final bool isBuying;
  const _AdRow({required this.ad, required this.isBuying});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cs = context.cs;
    return InkWell(
      onTap: () => _showPlaceOrderSheet(context, ref),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(ad.coinSymbol, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15, color: cs.onSurface)),
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                  decoration: BoxDecoration(color: const Color(0xFFEEF2FF), borderRadius: BorderRadius.circular(6)),
                  child: Text(ad.completedCount > 0 ? '${ad.completedCount} trades' : 'New', style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: Color(0xFF4338CA))),
                ),
                const Spacer(),
                Text('\$${ad.priceUsd.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: Color(0xFF6366F1))),
              ],
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                Text(ad.sellerName, style: TextStyle(fontSize: 12, color: cs.outline)),
                const SizedBox(width: 8),
                Text('Limit: \$${ad.minOrder.toStringAsFixed(0)}–\$${ad.maxOrder.toStringAsFixed(0)}', style: TextStyle(fontSize: 12, color: cs.outline)),
              ],
            ),
            const SizedBox(height: 4),
            Wrap(spacing: 6, children: ad.paymentMethods.map((m) => Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
              decoration: BoxDecoration(color: cs.surfaceVariant, borderRadius: BorderRadius.circular(6)),
              child: Text(m, style: TextStyle(fontSize: 10, color: cs.onSurface, fontWeight: FontWeight.w600)),
            )).toList()),
            const Divider(height: 20),
          ],
        ),
      ),
    );
  }

  void _showPlaceOrderSheet(BuildContext context, WidgetRef ref) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _PlaceOrderSheet(ad: ad, isBuying: isBuying, repo: CryptoRepository.create(), onDone: () {
        ref.invalidate(cryptoPortfolioProvider);
      }),
    );
  }
}

class _CreateAdSheet extends StatefulWidget {
  final List<CryptoCoin> coins;
  final CryptoRepository repo;
  final VoidCallback onDone;
  const _CreateAdSheet({required this.coins, required this.repo, required this.onDone});

  @override
  State<_CreateAdSheet> createState() => _CreateAdSheetState();
}

class _CreateAdSheetState extends State<_CreateAdSheet> {
  CryptoCoin? _coin;
  String _type = 'sell';
  final _priceCtrl = TextEditingController();
  final _amtCtrl   = TextEditingController();
  final _minCtrl   = TextEditingController();
  final _maxCtrl   = TextEditingController();
  final _payMethods = <String>{'EVC Plus'};
  bool _loading = false;
  String? _error;

  static const _methods = ['EVC Plus', 'eDahab', 'Waafi Pay', 'Jeep Money', 'ePay Wallet'];

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    _coin ??= widget.coins.isNotEmpty ? widget.coins.first : null;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(context).viewInsets.bottom + 20),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Create P2P Ad', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18)),
        const SizedBox(height: 16),
        DropdownButtonFormField<CryptoCoin>(
          value: _coin, onChanged: (c) => setState(() => _coin = c),
          decoration: InputDecoration(labelText: 'Coin', filled: true, fillColor: cs.surfaceVariant, border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none)),
          items: widget.coins.map((c) => DropdownMenuItem(value: c, child: Text(c.symbol))).toList(),
        ),
        const SizedBox(height: 10),
        Row(children: [
          Expanded(child: RadioListTile<String>(title: const Text('Sell'), value: 'sell', groupValue: _type, onChanged: (v) => setState(() => _type = v!), dense: true, contentPadding: EdgeInsets.zero)),
          Expanded(child: RadioListTile<String>(title: const Text('Buy'), value: 'buy', groupValue: _type, onChanged: (v) => setState(() => _type = v!), dense: true, contentPadding: EdgeInsets.zero)),
        ]),
        Row(children: [
          Expanded(child: TextField(controller: _priceCtrl, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: 'Price USD', filled: true, fillColor: cs.surfaceVariant, border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none)))),
          const SizedBox(width: 10),
          Expanded(child: TextField(controller: _amtCtrl, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: 'Amount (crypto)', filled: true, fillColor: cs.surfaceVariant, border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none)))),
        ]),
        const SizedBox(height: 10),
        Row(children: [
          Expanded(child: TextField(controller: _minCtrl, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: 'Min USD', filled: true, fillColor: cs.surfaceVariant, border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none)))),
          const SizedBox(width: 10),
          Expanded(child: TextField(controller: _maxCtrl, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: 'Max USD', filled: true, fillColor: cs.surfaceVariant, border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none)))),
        ]),
        const SizedBox(height: 10),
        Text('Payment Methods', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: cs.outline)),
        Wrap(spacing: 8, runSpacing: 4, children: _methods.map((m) {
          final sel = _payMethods.contains(m);
          return FilterChip(label: Text(m, style: const TextStyle(fontSize: 11)), selected: sel, onSelected: (v) => setState(() => v ? _payMethods.add(m) : _payMethods.remove(m)));
        }).toList()),
        if (_error != null) ...[const SizedBox(height: 6), Text(_error!, style: const TextStyle(color: Color(0xFFB91C1C), fontSize: 12))],
        const SizedBox(height: 14),
        SizedBox(width: double.infinity, child: ElevatedButton(
          onPressed: _loading ? null : _submit,
          style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF6366F1), foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
          child: _loading ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Post Ad', style: TextStyle(fontWeight: FontWeight.w900)),
        )),
      ]),
    );
  }

  Future<void> _submit() async {
    if (_coin == null) return;
    final price = double.tryParse(_priceCtrl.text) ?? 0;
    final amount = double.tryParse(_amtCtrl.text) ?? 0;
    final min = double.tryParse(_minCtrl.text) ?? 0;
    final max = double.tryParse(_maxCtrl.text) ?? 0;
    if (price <= 0 || amount <= 0 || min <= 0 || max <= 0) { setState(() => _error = 'Fill all fields'); return; }
    setState(() { _loading = true; _error = null; });
    try {
      await widget.repo.createP2pAd(coinId: _coin!.id, type: _type, priceUsd: price, amount: amount, minOrder: min, maxOrder: max, paymentMethods: _payMethods.toList());
      widget.onDone();
      if (mounted) Navigator.pop(context);
    } catch (e) {
      setState(() => _error = '$e');
    } finally {
      setState(() => _loading = false);
    }
  }
}

class _PlaceOrderSheet extends StatefulWidget {
  final P2pAd ad;
  final bool isBuying;
  final CryptoRepository repo;
  final VoidCallback onDone;
  const _PlaceOrderSheet({required this.ad, required this.isBuying, required this.repo, required this.onDone});

  @override
  State<_PlaceOrderSheet> createState() => _PlaceOrderSheetState();
}

class _PlaceOrderSheetState extends State<_PlaceOrderSheet> {
  final _amtCtrl = TextEditingController();
  String _payMethod = '';
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _payMethod = widget.ad.paymentMethods.isNotEmpty ? widget.ad.paymentMethods.first : 'EVC Plus';
  }

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(context).viewInsets.bottom + 20),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text('${widget.isBuying ? 'Buy' : 'Sell'} ${widget.ad.coinSymbol}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18))),
          Text('\$${widget.ad.priceUsd.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: Color(0xFF6366F1))),
        ]),
        const SizedBox(height: 4),
        Text('Limit: \$${widget.ad.minOrder.toStringAsFixed(0)} – \$${widget.ad.maxOrder.toStringAsFixed(0)}', style: TextStyle(fontSize: 12, color: cs.outline)),
        const SizedBox(height: 16),
        TextField(
          controller: _amtCtrl,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(
            labelText: 'Amount (${widget.ad.coinSymbol})',
            filled: true, fillColor: cs.surfaceVariant,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
          ),
        ),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(
          value: _payMethod,
          onChanged: (v) => setState(() => _payMethod = v!),
          decoration: InputDecoration(labelText: 'Payment Method', filled: true, fillColor: cs.surfaceVariant, border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none)),
          items: widget.ad.paymentMethods.map((m) => DropdownMenuItem(value: m, child: Text(m))).toList(),
        ),
        if (_error != null) ...[const SizedBox(height: 6), Text(_error!, style: const TextStyle(color: Color(0xFFB91C1C), fontSize: 12))],
        const SizedBox(height: 14),
        SizedBox(width: double.infinity, child: ElevatedButton(
          onPressed: _loading ? null : _submit,
          style: ElevatedButton.styleFrom(backgroundColor: widget.isBuying ? const Color(0xFF15803D) : const Color(0xFFB91C1C), foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
          child: _loading ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : Text('Place ${widget.isBuying ? 'Buy' : 'Sell'} Order', style: const TextStyle(fontWeight: FontWeight.w900)),
        )),
      ]),
    );
  }

  Future<void> _submit() async {
    final amt = double.tryParse(_amtCtrl.text) ?? 0;
    if (amt <= 0) { setState(() => _error = 'Enter amount'); return; }
    setState(() { _loading = true; _error = null; });
    try {
      await widget.repo.placeP2pOrder(adId: widget.ad.id, cryptoAmount: amt, paymentMethod: _payMethod);
      widget.onDone();
      if (mounted) Navigator.pop(context);
    } catch (e) {
      setState(() => _error = '$e');
    } finally {
      setState(() => _loading = false);
    }
  }
}

// ═════════════════════════════════════════════════════════════════════════════
// WALLET TAB
// ═════════════════════════════════════════════════════════════════════════════

class _WalletTab extends ConsumerStatefulWidget {
  const _WalletTab();
  @override
  ConsumerState<_WalletTab> createState() => _WalletTabState();
}

class _WalletTabState extends ConsumerState<_WalletTab> {
  CryptoWalletBalance? _selected;

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    final portfolioAsync = ref.watch(cryptoPortfolioProvider);

    return portfolioAsync.when(
      loading: () => const Center(child: CircularProgressIndicator()),
      error: (e, _) => Center(child: Text('$e')),
      data: (data) {
        final wallets = (data['wallets'] as List? ?? []).map((j) => CryptoWalletBalance.fromJson(j)).toList();
        _selected ??= wallets.isNotEmpty ? wallets.first : null;

        return Column(
          children: [
            // Coin selector horizontal scroll
            SizedBox(
              height: 60,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                itemCount: wallets.length,
                itemBuilder: (_, i) {
                  final w = wallets[i];
                  final sel = _selected?.coinId == w.coinId;
                  return GestureDetector(
                    onTap: () => setState(() => _selected = w),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 150),
                      margin: const EdgeInsets.only(right: 8),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                      decoration: BoxDecoration(
                        color: sel ? const Color(0xFF6366F1) : cs.surfaceVariant,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(w.symbol, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 12, color: sel ? Colors.white : cs.onSurface)),
                          Text('\$${w.balanceUsd.toStringAsFixed(2)}', style: TextStyle(fontSize: 9, color: sel ? Colors.white.withValues(alpha: .8) : cs.outline)),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
            // Selected coin detail
            if (_selected != null)
              Expanded(child: _WalletDetail(wallet: _selected!, repo: CryptoRepository.create())),
          ],
        );
      },
    );
  }
}

class _WalletDetail extends StatelessWidget {
  final CryptoWalletBalance wallet;
  final CryptoRepository repo;
  const _WalletDetail({required this.wallet, required this.repo});

  @override
  Widget build(BuildContext context) {
    final cs = context.cs;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Balance card
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(24),
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [Color(0xFF1E1B4B), Color(0xFF312E81)], begin: Alignment.topLeft, end: Alignment.bottomRight),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(wallet.symbol, style: TextStyle(color: Colors.white.withValues(alpha: .7), fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: .8)),
                const SizedBox(height: 6),
                Text('${wallet.balance.toStringAsFixed(6)} ${wallet.symbol}', style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text('≈ \$${wallet.balanceUsd.toStringAsFixed(2)} USD', style: TextStyle(color: Colors.white.withValues(alpha: .7), fontSize: 13)),
                if (wallet.lockedBalance > 0) ...[
                  const SizedBox(height: 6),
                  Text('Locked: ${wallet.lockedBalance.toStringAsFixed(6)} ${wallet.symbol}', style: TextStyle(color: Colors.white.withValues(alpha: .6), fontSize: 11)),
                ],
              ],
            ),
          ),
          const SizedBox(height: 20),
          // Action buttons
          Row(
            children: [
              Expanded(child: _WalletAction(icon: Icons.arrow_downward_rounded, label: 'Deposit', color: const Color(0xFF15803D), onTap: () => _showDeposit(context))),
              const SizedBox(width: 10),
              Expanded(child: _WalletAction(icon: Icons.arrow_upward_rounded, label: 'Withdraw', color: const Color(0xFFB91C1C), onTap: () => _showWithdraw(context))),
            ],
          ),
          const SizedBox(height: 20),
          Text('Deposit Address', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: cs.outline, letterSpacing: .6)),
          const SizedBox(height: 8),
          if (wallet.depositAddress.isNotEmpty)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: cs.surfaceVariant, borderRadius: BorderRadius.circular(12)),
              child: Row(
                children: [
                  Expanded(child: Text(wallet.depositAddress, style: const TextStyle(fontFamily: 'monospace', fontSize: 11))),
                  IconButton(
                    icon: const Icon(Icons.copy, size: 18),
                    onPressed: () {
                      Clipboard.setData(ClipboardData(text: wallet.depositAddress));
                      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Address copied'), duration: Duration(seconds: 2)));
                    },
                  ),
                ],
              ),
            )
          else
            Text('Generate by tapping Deposit', style: TextStyle(color: cs.outline, fontSize: 12)),
        ],
      ),
    );
  }

  void _showDeposit(BuildContext context) {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => Padding(
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Text('Deposit ${wallet.symbol}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18)),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: const Color(0xFFF8FAFC), borderRadius: BorderRadius.circular(12)),
            child: Column(children: [
              Text('Your ${wallet.symbol} address:', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              Text(wallet.depositAddress.isNotEmpty ? wallet.depositAddress : 'Tap copy to generate', style: const TextStyle(fontFamily: 'monospace', fontSize: 13, fontWeight: FontWeight.w700), textAlign: TextAlign.center),
            ]),
          ),
          const SizedBox(height: 12),
          ElevatedButton.icon(
            icon: const Icon(Icons.copy),
            label: const Text('Copy Address'),
            onPressed: () {
              Clipboard.setData(ClipboardData(text: wallet.depositAddress));
              Navigator.pop(context);
            },
            style: ElevatedButton.styleFrom(minimumSize: const Size(double.infinity, 48), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
          ),
          const SizedBox(height: 8),
          Text('Only send ${wallet.symbol} to this address', style: TextStyle(fontSize: 11, color: Colors.grey[600])),
        ]),
      ),
    );
  }

  void _showWithdraw(BuildContext context) {
    final addrCtrl = TextEditingController();
    final amtCtrl  = TextEditingController();
    bool loading = false;
    String? error;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => StatefulBuilder(
        builder: (ctx, setS) => Padding(
          padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(ctx).viewInsets.bottom + 20),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Withdraw ${wallet.symbol}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18)),
            Text('Available: ${wallet.balance.toStringAsFixed(6)} ${wallet.symbol}', style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
            const SizedBox(height: 16),
            TextField(controller: addrCtrl, decoration: InputDecoration(labelText: 'To Address', filled: true, fillColor: const Color(0xFFF1F5F9), border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none))),
            const SizedBox(height: 10),
            TextField(controller: amtCtrl, keyboardType: const TextInputType.numberWithOptions(decimal: true), decoration: InputDecoration(labelText: 'Amount', filled: true, fillColor: const Color(0xFFF1F5F9), border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none))),
            if (error != null) ...[const SizedBox(height: 6), Text(error!, style: const TextStyle(color: Color(0xFFB91C1C), fontSize: 12))],
            const SizedBox(height: 14),
            SizedBox(width: double.infinity, child: ElevatedButton(
              onPressed: loading ? null : () async {
                final addr = addrCtrl.text.trim();
                final amt = double.tryParse(amtCtrl.text) ?? 0;
                if (addr.isEmpty || amt <= 0) { setS(() => error = 'Fill all fields'); return; }
                setS(() { loading = true; error = null; });
                try {
                  await repo.withdraw(coinId: wallet.coinId, networkId: 1, amount: amt, toAddress: addr);
                  if (ctx.mounted) Navigator.pop(ctx);
                  ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Withdrawal submitted successfully')));
                } catch (e) {
                  setS(() { loading = false; error = '$e'; });
                }
              },
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFFB91C1C), foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
              child: loading ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Text('Submit Withdrawal', style: TextStyle(fontWeight: FontWeight.w900)),
            )),
          ]),
        ),
      ),
    );
  }
}

class _WalletAction extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  const _WalletAction({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(color: color.withValues(alpha: .1), borderRadius: BorderRadius.circular(14)),
        child: Column(
          children: [
            Icon(icon, color: color, size: 22),
            const SizedBox(height: 4),
            Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w800, fontSize: 12)),
          ],
        ),
      ),
    );
  }
}
