import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/api/module_api_service.dart';

class WalletData {
  final double balance;
  final int points;
  final bool hasWallet;
  final List<Map<String, dynamic>> transactions;
  const WalletData({
    required this.balance,
    required this.points,
    required this.hasWallet,
    required this.transactions,
  });
}

final walletProvider = FutureProvider<WalletData>((ref) async {
  try {
    final dio = ApiClient.instance;
    final res = await dio.get('/wallet');
    final data = res.data['data'] ?? res.data;
    final txRes = await dio.get('/wallet/transactions');
    final txRaw = txRes.data['data'];
    final List txList = txRaw is List
        ? txRaw
        : (txRaw is Map ? (txRaw['data'] as List? ?? []) : []);
    return WalletData(
      balance:      (data['balance'] as num?)?.toDouble() ?? 0,
      points:       (data['loyalty_points'] as num? ?? data['points'] as num?)?.toInt() ?? 0,
      hasWallet:    data['has_wallet'] == true,
      transactions: txList.whereType<Map<String, dynamic>>().toList(),
    );
  } on DioException catch (e) {
    throw ApiException.fromDio(e);
  }
});
