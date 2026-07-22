import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import 'crypto_models.dart';

class CryptoRepository {
  CryptoRepository(this._dio);
  final Dio _dio;
  static CryptoRepository create() => CryptoRepository(ApiClient.instance);

  // ── Markets ──────────────────────────────────────────────────────────────────

  Future<List<CryptoCoin>> getMarkets({String? search, String? sort}) async {
    final r = await _dio.get('/crypto/markets', queryParameters: {
      if (search != null && search.isNotEmpty) 'search': search,
      if (sort != null) 'sort': sort,
    });
    final list = r.data['data'] as List? ?? [];
    return list.map((j) => CryptoCoin.fromJson(j)).toList();
  }

  Future<CryptoCoin> getCoinDetail(String symbol) async {
    final r = await _dio.get('/crypto/markets/$symbol');
    return CryptoCoin.fromJson(r.data['data'] as Map<String, dynamic>);
  }

  Future<List<ChartPoint>> getCoinChart(String symbol, {String interval = '1d'}) async {
    final r = await _dio.get('/crypto/markets/$symbol/chart',
        queryParameters: {'interval': interval});
    final list = r.data['data'] as List? ?? [];
    return list.map((j) => ChartPoint.fromJson(j)).toList();
  }

  // ── Portfolio / Wallet ────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getWallet() => getPortfolio();

  Future<Map<String, dynamic>> getPortfolio() async {
    final r = await _dio.get('/crypto/wallet/portfolio');
    return r.data['data'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getDepositAddress(String symbol, {int? networkId}) async {
    final r = await _dio.get('/crypto/wallet/$symbol/deposit',
        queryParameters: {if (networkId != null) 'network_id': networkId});
    return r.data['data'] as Map<String, dynamic>;
  }

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

  Future<Map<String, dynamic>> transfer({
    required String symbol,
    required int networkId,
    required String toPhone,
    required double amount,
  }) async {
    final r = await _dio.post('/crypto/wallet/transfer', data: {
      'symbol': symbol,
      'network_id': networkId,
      'to_phone': toPhone,
      'amount': amount,
    });
    return r.data as Map<String, dynamic>;
  }

  Future<List<CryptoTransaction>> getTransactions({
    int page = 1,
    String? coinSymbol,
    String? type,
  }) async {
    final r = await _dio.get('/crypto/wallet/transactions', queryParameters: {
      'page': page,
      if (coinSymbol != null) 'coin': coinSymbol,
      if (type != null) 'type': type,
    });
    final list = r.data['data']?['data'] as List? ?? [];
    return list.map((j) => CryptoTransaction.fromJson(j)).toList();
  }

  // ── Buy / Sell ────────────────────────────────────────────────────────────────

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

  // Convenience wrappers used by simplified screens
  Future<Map<String, dynamic>> buyCoin({
    required String symbol,
    required double amountUsd,
    required String paymentMethod,
  }) => buy(symbol: symbol, networkId: 0, amountUsd: amountUsd, paymentMethod: paymentMethod);

  Future<Map<String, dynamic>> sellCoin({
    required String symbol,
    required double amount,
    required String receiveMethod,
  }) => sell(symbol: symbol, networkId: 0, cryptoAmount: amount, receiveMethod: receiveMethod);

  Future<List<CryptoOrder>> getMyOrders({int page = 1, String? side}) async {
    final r = await _dio.get('/crypto/orders',
        queryParameters: {'page': page, if (side != null) 'side': side});
    final list = r.data['data']?['data'] as List? ?? [];
    return list.map((j) => CryptoOrder.fromJson(j)).toList();
  }

  // ── P2P ──────────────────────────────────────────────────────────────────────

  Future<List<P2pAd>> getP2pAds({String? coinSymbol, String? type, int page = 1}) async {
    final r = await _dio.get('/crypto/p2p/ads', queryParameters: {
      'page': page,
      if (coinSymbol != null && coinSymbol.isNotEmpty) 'coin': coinSymbol,
      if (type != null) 'type': type,
    });
    final list = r.data['data']?['data'] as List? ?? [];
    return list.map((j) => P2pAd.fromJson(j)).toList();
  }

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

  Future<List<P2pOrder>> getMyP2pOrders(int myId, {int page = 1}) async {
    final r = await _dio.get('/crypto/p2p/orders', queryParameters: {'page': page});
    final list = r.data['data']?['data'] as List? ?? [];
    return list.map((j) => P2pOrder.fromJson(j, myId)).toList();
  }

  Future<Map<String, dynamic>> getP2pOrderDetail(String uuid, int myId) async {
    final r = await _dio.get('/crypto/p2p/orders/$uuid');
    return r.data['data'] as Map<String, dynamic>;
  }

  Future<void> markP2pPaid(String uuid) async =>
      await _dio.post('/crypto/p2p/orders/$uuid/paid');

  Future<void> releaseCrypto(String uuid) async =>
      await _dio.post('/crypto/p2p/orders/$uuid/release');

  Future<void> cancelP2pOrder(String uuid) async =>
      await _dio.post('/crypto/p2p/orders/$uuid/cancel');

  Future<void> openDispute(String uuid, String reason) async =>
      await _dio.post('/crypto/p2p/orders/$uuid/dispute', data: {'reason': reason});

  Future<List<Map<String, dynamic>>> getP2pMessages(String uuid) async {
    final r = await _dio.get('/crypto/p2p/orders/$uuid/messages');
    return (r.data['data'] as List? ?? []).cast<Map<String, dynamic>>();
  }

  Future<void> sendP2pMessage(String uuid, String message) async =>
      await _dio.post('/crypto/p2p/orders/$uuid/messages', data: {'message': message});

  Future<List<P2pAd>> getMyAds({int page = 1}) async {
    final r = await _dio.get('/crypto/p2p/ads/mine', queryParameters: {'page': page});
    final list = r.data['data']?['data'] as List? ?? [];
    return list.map((j) => P2pAd.fromJson(j)).toList();
  }

  Future<void> cancelAd(String uuid) async =>
      await _dio.delete('/crypto/p2p/ads/$uuid');
}
