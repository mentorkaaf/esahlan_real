import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import 'crypto_models.dart';

class CryptoRepository {
  CryptoRepository(this._dio);

  final Dio _dio;

  static CryptoRepository create() => CryptoRepository(ApiClient.instance);

  // ── Markets ─────────────────────────────────────────────────────────────────

  Future<List<CryptoCoin>> getMarkets() async {
    final r = await _dio.get('/crypto/markets');
    final list = r.data['coins'] as List? ?? [];
    return list.map((j) => CryptoCoin.fromJson(j)).toList();
  }

  Future<Map<String, dynamic>> getCoinDetail(int coinId) async {
    final r = await _dio.get('/crypto/markets/$coinId');
    return r.data as Map<String, dynamic>;
  }

  // ── Portfolio / Wallet ───────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getPortfolio() async {
    final r = await _dio.get('/crypto/wallet/portfolio');
    return r.data as Map<String, dynamic>;
  }

  Future<String> getDepositAddress(int coinId, int networkId) async {
    final r = await _dio.get('/crypto/wallet/deposit-address', queryParameters: {'coin_id': coinId, 'network_id': networkId});
    return r.data['address'] as String? ?? '';
  }

  Future<Map<String, dynamic>> withdraw({required int coinId, required int networkId, required double amount, required String toAddress}) async {
    final r = await _dio.post('/crypto/wallet/withdraw', data: {'coin_id': coinId, 'network_id': networkId, 'amount': amount, 'to_address': toAddress});
    return r.data as Map<String, dynamic>;
  }

  Future<List<Map<String, dynamic>>> getTransactions({int page = 1, int? coinId}) async {
    final r = await _dio.get('/crypto/wallet/transactions', queryParameters: {'page': page, if (coinId != null) 'coin_id': coinId});
    final list = r.data['data'] as List? ?? [];
    return list.cast<Map<String, dynamic>>();
  }

  // ── Buy / Sell ───────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getQuote({required int coinId, required String side, required double amount, required String paymentMethod}) async {
    final r = await _dio.post('/crypto/trade/quote', data: {'coin_id': coinId, 'side': side, 'amount_usd': amount, 'payment_method': paymentMethod});
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> executeTrade({required int coinId, required String side, required double amountUsd, required String paymentMethod, required String quoteId}) async {
    final r = await _dio.post('/crypto/trade/execute', data: {'coin_id': coinId, 'side': side, 'amount_usd': amountUsd, 'payment_method': paymentMethod, 'quote_id': quoteId});
    return r.data as Map<String, dynamic>;
  }

  Future<List<CryptoOrder>> getMyOrders({int page = 1}) async {
    final r = await _dio.get('/crypto/trade/orders', queryParameters: {'page': page});
    final list = r.data['data'] as List? ?? [];
    return list.map((j) => CryptoOrder.fromJson(j)).toList();
  }

  // ── P2P ──────────────────────────────────────────────────────────────────────

  Future<List<P2pAd>> getP2pAds({String? coinSymbol, String? type, int page = 1}) async {
    final r = await _dio.get('/crypto/p2p/ads', queryParameters: {'page': page, if (coinSymbol != null) 'coin': coinSymbol, if (type != null) 'type': type});
    final list = r.data['data'] as List? ?? [];
    return list.map((j) => P2pAd.fromJson(j)).toList();
  }

  Future<Map<String, dynamic>> createP2pAd({required int coinId, required String type, required double priceUsd, required double amount, required double minOrder, required double maxOrder, required List<String> paymentMethods, String? terms}) async {
    final r = await _dio.post('/crypto/p2p/ads', data: {'coin_id': coinId, 'type': type, 'price_usd': priceUsd, 'amount': amount, 'min_order_usd': minOrder, 'max_order_usd': maxOrder, 'payment_methods': paymentMethods, 'terms': terms ?? ''});
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> placeP2pOrder({required int adId, required double cryptoAmount, required String paymentMethod}) async {
    final r = await _dio.post('/crypto/p2p/ads/$adId/order', data: {'crypto_amount': cryptoAmount, 'payment_method': paymentMethod});
    return r.data as Map<String, dynamic>;
  }

  Future<void> markP2pPaid(String uuid) async {
    await _dio.post('/crypto/p2p/orders/$uuid/mark-paid');
  }

  Future<void> releaseCrypto(String uuid) async {
    await _dio.post('/crypto/p2p/orders/$uuid/release');
  }

  Future<void> cancelP2pOrder(String uuid) async {
    await _dio.post('/crypto/p2p/orders/$uuid/cancel');
  }

  Future<void> openDispute(String uuid, String reason) async {
    await _dio.post('/crypto/p2p/orders/$uuid/dispute', data: {'reason': reason});
  }

  Future<List<P2pOrder>> getMyP2pOrders(int myId, {int page = 1}) async {
    final r = await _dio.get('/crypto/p2p/my-orders', queryParameters: {'page': page});
    final list = r.data['data'] as List? ?? [];
    return list.map((j) => P2pOrder.fromJson(j, myId)).toList();
  }
}
