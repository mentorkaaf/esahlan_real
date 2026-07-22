import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'crypto_theme.dart';
import 'crypto_models.dart';
import 'crypto_providers.dart';
import 'crypto_widgets.dart';
import 'crypto_buy_sell_screen.dart';

enum _Filter { all, favorites, gainers, losers }

class CryptoMarketsScreen extends ConsumerStatefulWidget {
  const CryptoMarketsScreen({super.key});

  @override
  ConsumerState<CryptoMarketsScreen> createState() => _CryptoMarketsScreenState();
}

class _CryptoMarketsScreenState extends ConsumerState<CryptoMarketsScreen> {
  _Filter _filter = _Filter.all;
  String _search = '';
  final _searchCtrl = TextEditingController();

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  List<CryptoCoin> _applyFilters(List<CryptoCoin> coins) {
    var list = coins.where((c) {
      final q = _search.toLowerCase();
      return c.symbol.toLowerCase().contains(q) || c.name.toLowerCase().contains(q);
    }).toList();

    switch (_filter) {
      case _Filter.gainers:
        list = list.where((c) => c.changePercent24h >= 0).toList()
          ..sort((a, b) => b.changePercent24h.compareTo(a.changePercent24h));
        break;
      case _Filter.losers:
        list = list.where((c) => c.changePercent24h < 0).toList()
          ..sort((a, b) => a.changePercent24h.compareTo(b.changePercent24h));
        break;
      case _Filter.favorites:
        list = list.where((c) => c.isFavorite).toList();
        break;
      case _Filter.all:
        list.sort((a, b) => b.marketCapUsd.compareTo(a.marketCapUsd));
        break;
    }
    return list;
  }

  @override
  Widget build(BuildContext context) {
    final marketsAsync = ref.watch(cryptoMarketsProvider);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Markets'),
        actions: [
          IconButton(
            icon: Icon(Icons.search, color: cTx(context)),
            onPressed: () => setState(() {}),
          ),
        ],
      ),
      body: Column(
        children: [
          // Search bar
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
            child: TextField(
              controller: _searchCtrl,
              style: TextStyle(color: cTx(context), fontSize: 14),
              onChanged: (v) => setState(() => _search = v),
              decoration: InputDecoration(
                hintText: 'Search coins...',
                hintStyle: TextStyle(color: cMt(context)),
                prefixIcon: Icon(Icons.search, color: cMt(context), size: 18),
                filled: true,
                fillColor: cCard(context),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: cBd(context)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: cBd(context)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: kCryptoPrimary),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              ),
            ),
          ),
          // Filter chips
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: [
                  _Chip(label: 'All Coins',    active: _filter == _Filter.all,       onTap: () => setState(() => _filter = _Filter.all)),
                  const SizedBox(width: 8),
                  _Chip(label: 'Favorites',    active: _filter == _Filter.favorites, onTap: () => setState(() => _filter = _Filter.favorites)),
                  const SizedBox(width: 8),
                  _Chip(label: 'Top Gainers',  active: _filter == _Filter.gainers,   onTap: () => setState(() => _filter = _Filter.gainers)),
                  const SizedBox(width: 8),
                  _Chip(label: 'Top Losers',   active: _filter == _Filter.losers,    onTap: () => setState(() => _filter = _Filter.losers)),
                ],
              ),
            ),
          ),
          // Table header
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Row(
              children: [
                Expanded(flex: 3, child: Text('Coin', style: TextStyle(color: cMt(context), fontSize: 11))),
                SizedBox(width: 60, child: Text('Chart', style: TextStyle(color: cMt(context), fontSize: 11))),
                Expanded(flex: 2, child: Text('Price', textAlign: TextAlign.right, style: TextStyle(color: cMt(context), fontSize: 11))),
                const SizedBox(width: 8),
                SizedBox(width: 60, child: Text('24h', textAlign: TextAlign.right, style: TextStyle(color: cMt(context), fontSize: 11))),
              ],
            ),
          ),
          Divider(color: cBd(context), height: 1),
          // List
          Expanded(
            child: marketsAsync.when(
              loading: () => const Center(child: CircularProgressIndicator(color: kCryptoPrimary)),
              error: (e, _) => Center(
                child: Text('Error loading markets', style: TextStyle(color: cMt(context)))),
              data: (coins) {
                final filtered = _applyFilters(coins);
                if (filtered.isEmpty) {
                  return Center(
                    child: Text('No coins found', style: TextStyle(color: cMt(context))));
                }
                return RefreshIndicator(
                  color: kCryptoPrimary,
                  onRefresh: () async => ref.invalidate(cryptoMarketsProvider),
                  child: ListView.separated(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    itemCount: filtered.length,
                    separatorBuilder: (_, __) => Divider(color: cBd(context), height: 1, indent: 16, endIndent: 16),
                    itemBuilder: (_, i) => _CoinRow(
                      coin: filtered[i],
                      onTap: () => Navigator.of(context).push(MaterialPageRoute(
                          builder: (_) => CryptoBuySellScreen(initialCoin: filtered[i]))),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.label, required this.active, required this.onTap});
  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
      decoration: BoxDecoration(
        color: active ? kCryptoPrimary : cCard(context),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: active ? kCryptoPrimary : cBd(context)),
      ),
      child: Text(label,
          style: TextStyle(
            color: active ? Colors.white : cMt(context),
            fontSize: 12,
            fontWeight: active ? FontWeight.w700 : FontWeight.normal,
          )),
    ),
  );
}

class _CoinRow extends StatelessWidget {
  const _CoinRow({required this.coin, required this.onTap});
  final CryptoCoin coin;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(
          children: [
            // Coin info
            Expanded(
              flex: 3,
              child: Row(
                children: [
                  CoinAvatarWidget(symbol: coin.symbol, logoUrl: coin.logoUrl, size: 36),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(coin.symbol,
                            style: TextStyle(color: cTx(context), fontWeight: FontWeight.w600, fontSize: 13)),
                        Text(coin.name,
                            style: TextStyle(color: cMt(context), fontSize: 10),
                            overflow: TextOverflow.ellipsis),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            // Sparkline
            if (coin.sparkline7d.isNotEmpty)
              MiniSparkline(coin.sparkline7d, width: 60, height: 28)
            else
              const SizedBox(width: 60, height: 28),
            // Price
            Expanded(
              flex: 2,
              child: Text(
                cryptoCoinPrice(coin.priceUsd),
                textAlign: TextAlign.right,
                style: TextStyle(color: cTx(context), fontSize: 13, fontWeight: FontWeight.w600),
              ),
            ),
            const SizedBox(width: 8),
            // 24h change
            SizedBox(
              width: 60,
              child: Align(
                alignment: Alignment.centerRight,
                child: ChangeBadge(coin.changePercent24h),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
