import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'crypto_models.dart';
import 'crypto_repository.dart';

final cryptoRepoProvider = Provider<CryptoRepository>((_) => CryptoRepository.create());

// Markets
final cryptoMarketsProvider = FutureProvider.autoDispose<List<CryptoCoin>>((ref) {
  return ref.read(cryptoRepoProvider).getMarkets();
});

// Portfolio
final cryptoPortfolioProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) {
  return ref.read(cryptoRepoProvider).getPortfolio();
});

// My Orders
final cryptoOrdersProvider = FutureProvider.autoDispose<List<CryptoOrder>>((ref) {
  return ref.read(cryptoRepoProvider).getMyOrders();
});

// P2P Ads (parameterized)
final p2pAdsProvider = FutureProvider.autoDispose.family<List<P2pAd>, Map<String, String?>>((ref, params) {
  return ref.read(cryptoRepoProvider).getP2pAds(coinSymbol: params['coin'], type: params['type']);
});

// Quote state notifier
class QuoteNotifier extends StateNotifier<AsyncValue<Map<String, dynamic>?>> {
  QuoteNotifier(this._repo) : super(const AsyncData(null));

  final CryptoRepository _repo;
  String? _lastQuoteId;
  String? get lastQuoteId => _lastQuoteId;

  Future<void> getQuote({required int coinId, required String side, required double amountUsd, required String paymentMethod}) async {
    state = const AsyncLoading();
    try {
      final q = await _repo.getQuote(coinId: coinId, side: side, amount: amountUsd, paymentMethod: paymentMethod);
      _lastQuoteId = q['quote_id'] as String?;
      state = AsyncData(q);
    } catch (e, st) {
      state = AsyncError(e, st);
    }
  }

  Future<Map<String, dynamic>?> executeTrade({required int coinId, required String side, required double amountUsd, required String paymentMethod}) async {
    if (_lastQuoteId == null) return null;
    return _repo.executeTrade(coinId: coinId, side: side, amountUsd: amountUsd, paymentMethod: paymentMethod, quoteId: _lastQuoteId!);
  }

  void reset() {
    _lastQuoteId = null;
    state = const AsyncData(null);
  }
}

final quoteProvider = StateNotifierProvider.autoDispose<QuoteNotifier, AsyncValue<Map<String, dynamic>?>>((ref) {
  return QuoteNotifier(ref.read(cryptoRepoProvider));
});
