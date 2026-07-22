import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'crypto_models.dart';
import 'crypto_repository.dart';

// ── Helper ────────────────────────────────────────────────────────────────────

String _msg(Object e) {
  if (e is DioException) {
    final data = e.response?.data;
    if (data is Map) return data['message']?.toString() ?? e.message ?? '$e';
    return e.message ?? '$e';
  }
  return '$e';
}

// ── Repository ────────────────────────────────────────────────────────────────

final cryptoRepoProvider = Provider<CryptoRepository>((_) => CryptoRepository.create());

// ── Markets ───────────────────────────────────────────────────────────────────

final cryptoMarketsProvider = FutureProvider.autoDispose<List<CryptoCoin>>((ref) {
  return ref.read(cryptoRepoProvider).getMarkets();
});

// ── Portfolio ─────────────────────────────────────────────────────────────────

final cryptoPortfolioProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) {
  return ref.read(cryptoRepoProvider).getPortfolio();
});

// ── Orders ────────────────────────────────────────────────────────────────────

final cryptoOrdersProvider = FutureProvider.autoDispose<List<CryptoOrder>>((ref) {
  return ref.read(cryptoRepoProvider).getMyOrders();
});

// ── Transactions ──────────────────────────────────────────────────────────────

final cryptoTransactionsProvider = FutureProvider.autoDispose<List<CryptoTransaction>>((ref) {
  return ref.read(cryptoRepoProvider).getTransactions();
});

// ── Chart ─────────────────────────────────────────────────────────────────────

final coinChartProvider = FutureProvider.autoDispose.family<List<ChartPoint>, String>((ref, key) {
  // key: "SYMBOL:interval" e.g. "BTC:1d"
  final parts = key.split(':');
  final symbol = parts[0];
  final interval = parts.length > 1 ? parts[1] : '1d';
  return ref.read(cryptoRepoProvider).getCoinChart(symbol, interval: interval);
});

// ── P2P Ads ───────────────────────────────────────────────────────────────────

final p2pAdsProvider = FutureProvider.autoDispose.family<List<P2pAd>, String>((ref, key) {
  // key: "type:coin" e.g. "sell:" or "sell:BTC"
  final parts = key.split(':');
  final type = parts[0].isNotEmpty ? parts[0] : null;
  final coin = parts.length > 1 && parts[1].isNotEmpty ? parts[1] : null;
  return ref.read(cryptoRepoProvider).getP2pAds(coinSymbol: coin, type: type);
});

// ── My P2P Orders ─────────────────────────────────────────────────────────────

final myP2pOrdersProvider = FutureProvider.autoDispose.family<List<P2pOrder>, int>((ref, myId) {
  return ref.read(cryptoRepoProvider).getMyP2pOrders(myId);
});

// ── Quote / Trade ─────────────────────────────────────────────────────────────

class QuoteNotifier extends StateNotifier<AsyncValue<Map<String, dynamic>?>> {
  QuoteNotifier(this._repo) : super(const AsyncData(null));
  final CryptoRepository _repo;
  Map<String, dynamic>? _lastQuote;

  Future<void> getQuote({
    required String symbol,
    required String side,
    required double amountUsd,
  }) async {
    state = const AsyncLoading();
    try {
      final q = await _repo.getQuote(symbol: symbol, side: side, amountUsd: amountUsd);
      _lastQuote = q;
      state = AsyncData(q);
    } catch (e, st) {
      state = AsyncError(_msg(e), st);
    }
  }

  Future<Map<String, dynamic>> executeTrade({
    required String symbol,
    required int networkId,
    required String side,
    required double amountUsd,
    required String paymentMethod,
  }) async {
    final q = _lastQuote;
    if (q == null) throw Exception('No quote available');
    if (side == 'buy') {
      return _repo.buy(
        symbol: symbol,
        networkId: networkId,
        amountUsd: amountUsd,
        paymentMethod: paymentMethod,
      );
    } else {
      final cryptoAmount = (q['crypto_amount'] ?? 0).toDouble();
      return _repo.sell(
        symbol: symbol,
        networkId: networkId,
        cryptoAmount: cryptoAmount,
        receiveMethod: paymentMethod,
      );
    }
  }

  void reset() {
    _lastQuote = null;
    state = const AsyncData(null);
  }
}

final quoteProvider = StateNotifierProvider.autoDispose<QuoteNotifier, AsyncValue<Map<String, dynamic>?>>((ref) {
  return QuoteNotifier(ref.read(cryptoRepoProvider));
});
