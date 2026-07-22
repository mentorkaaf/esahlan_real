import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import 'crypto_models.dart';

class CryptoRepository {
  CryptoRepository(this._dio);

  final Dio _dio;

  static CryptoRepository create() => CryptoRepository(ApiClient.instance);

  // ── Markets ─────────────────────────────────────────────────────────────────

  // GET /api/v1/crypto/markets → {success, data: [...]}
  Future<List<CryptoCoin>> getMarkets({String? search, String? sort}) async {
    final r = await _dio.get('/crypto/markets', queryParameters: {
      if (search != null && search.isNotEmpty) 'search': search,
      if (sort != null) 'sort': sort,
    });
    final list = r.data['data'] as List? ?? [];
    return list.map((j) => CryptoCoin.fromJson(j)).toList();
  }

  // ── Portfolio / Wallet ───────────────────────────────────────────────────────

  // GET /api/v1/crypto/wallet/portfolio → {success, data: {total_usd, assets: [...]}}
  Future<Map<String, dynamic>> getPortfolio() async {
    final r = await _dio.get('/crypto/wallet/portfolio');
    return r.data['data'] as Map<String, dynamic>;
  }

  // GET /api/v1/crypto/wallet/{symbol}/deposit?network_id=
  Future<Map<String, dynamic>> getDepositAddress(String symbol, {int? networkId}) async {
    final r = await _dio.get(
      '/crypto/wallet/$symbol/deposit',
      queryParameters: {if (networkId != null) 'network_id': networkId},
    );
    return r.data['data'] as Map<String, dynamic>;
  }

  // POST /api/v1/crypto/wallet/withdraw
  Future<Map<String, dynamic>> withdraw({
    required String symbol,
    required int networkId,
    required double amount,
    required String toAddress,
  }) async {
    final r = await _dio.post('/crypto/wallet/withdraw', data: {
      'symbol': symbol,
      'network_id': networkId,
      'amount': amount,
      'address': toAddress,
    });
    return r.data as Map<String, dynamic>;
  }

  Future<List<Map<String, dynamic>>> getTransactions({int page = 1, String? coinSymbol}) async {
    final r = await _dio.get('/crypto/wallet/transactions', queryParameters: {
      'page': page,
      if (coinSymbol != null) 'coin': coinSymbol,
    });
    final list = r.data['data']?['data'] as List? ?? [];
    return list.cast<Map<String, dynamic>>();
  }

  // ── Buy / Sell ───────────────────────────────────────────────────────────────

  // POST /api/v1/crypto/quote → {success, data: {symbol, side, price_usd, amount_usd, fee_usd, crypto_amount, you_receive}}
  Future<Map<String, dynamic>> getQuote({
    required String symbol,
    required String side,
    required double amountUsd,
  }) async {
    final r = await _dio.post('/crypto/quote', data: {
      'symbol': symbol,
      'side': side,
      'amount_usd': amountUsd,
    });
    return r.data['data'] as Map<String, dynamic>;
  }

  // POST /api/v1/crypto/buy
  Future<Map<String, dynamic>> buy({
    required String symbol,
    required int networkId,
    required double amountUsd,
    required String paymentMethod,
    String? paymentReference,
  }) async {
    final r = await _dio.post('/crypto/buy', data: {
      'symbol': symbol,
      'network_id': networkId,
      'amount_usd': amountUsd,
      'payment_method': paymentMethod,
      if (paymentReference != null) 'payment_reference': paymentReference,
    });
    return r.data as Map<String, dynamic>;
  }

  // POST /api/v1/crypto/sell
  Future<Map<String, dynamic>> sell({
    required String symbol,
    required int networkId,
    required double cryptoAmount,
    required String receiveMethod,
  }) async {
    final r = await _dio.post('/crypto/sell', data: {
      'symbol': symbol,
      'network_id': networkId,
      'crypto_amount': cryptoAmount,
      'receive_method': receiveMethod,
    });
    return r.data as Map<String, dynamic>;
  }

  // GET /api/v1/crypto/orders
  Future<List<CryptoOrder>> getMyOrders({int page = 1}) async {
    final r = await _dio.get('/crypto/orders', queryParameters: {'page': page});
    final list = r.data['data']?['data'] as List? ?? [];
    return list.map((j) => CryptoOrder.fromJson(j)).toList();
  }

  // ── P2P ──────────────────────────────────────────────────────────────────────

  // GET /api/v1/crypto/p2p/ads
  Future<List<P2pAd>> getP2pAds({String? coinSymbol, String? type, int page = 1}) async {
    final r = await _dio.get('/crypto/p2p/ads', queryParameters: {
      'page': page,
      if (coinSymbol != null && coinSymbol.isNotEmpty) 'coin': coinSymbol,
      if (type != null) 'type': type,
    });
    final list = r.data['data']?['data'] as List? ?? [];
    return list.map((j) => P2pAd.fromJson(j)).toList();
  }

  // POST /api/v1/crypto/p2p/ads
  Future<Map<String, dynamic>> createP2pAd({
    required String coinSymbol,
    required String type,
    required double priceUsd,
    required double amount,
    required double minOrder,
    required double maxOrder,
    required List<String> paymentMethods,
    String? terms,
  }) async {
    final r = await _dio.post('/crypto/p2p/ads', data: {
      'coin_symbol': coinSymbol,
      'type': type,
      'price_usd': priceUsd,
      'amount': amount,
      'min_order_usd': minOrder,
      'max_order_usd': maxOrder,
      'payment_methods': paymentMethods,
      'terms': terms ?? '',
    });
    return r.data as Map<String, dynamic>;
  }

  // POST /api/v1/crypto/p2p/orders — requires ad_uuid
  Future<Map<String, dynamic>> placeP2pOrder({
    required String adUuid,
    required double cryptoAmount,
    required String paymentMethod,
  }) async {
    final r = await _dio.post('/crypto/p2p/orders', data: {
      'ad_uuid': adUuid,
      'crypto_amount': cryptoAmount,
      'payment_method': paymentMethod,
    });
    return r.data as Map<String, dynamic>;
  }

  Future<void> markP2pPaid(String uuid) async {
    await _dio.post('/crypto/p2p/orders/$uuid/paid');
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
    final r = await _dio.get('/crypto/p2p/orders', queryParameters: {'page': page});
    final list = r.data['data']?['data'] as List? ?? [];
    return list.map((j) => P2pOrder.fromJson(j, myId)).toList();
  }
}
