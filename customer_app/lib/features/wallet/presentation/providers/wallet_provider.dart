import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/api/module_api_service.dart';
import '../../../../core/services/realtime_client.dart';
import '../../../../core/storage/local_storage.dart';
import '../../../auth/data/models/user_model.dart';

class WalletData {
  final double balance;
  final int points;
  final bool hasWallet;
  final bool isFrozen;
  final Map<String, dynamic>? pendingTopup;
  final List<Map<String, dynamic>> transactions;

  const WalletData({
    required this.balance,
    required this.points,
    required this.hasWallet,
    this.isFrozen = false,
    this.pendingTopup,
    required this.transactions,
  });

  WalletData copyWith({
    double? balance,
    int? points,
    bool? hasWallet,
    bool? isFrozen,
    Map<String, dynamic>? pendingTopup,
    bool clearPendingTopup = false,
    List<Map<String, dynamic>>? transactions,
  }) =>
      WalletData(
        balance: balance ?? this.balance,
        points: points ?? this.points,
        hasWallet: hasWallet ?? this.hasWallet,
        isFrozen: isFrozen ?? this.isFrozen,
        pendingTopup: clearPendingTopup ? null : (pendingTopup ?? this.pendingTopup),
        transactions: transactions ?? this.transactions,
      );
}

// Screen sets this to show an in-app banner when a real-time event arrives
typedef WalletEventCallback = void Function(String type, double amount, String note);

class WalletNotifier extends StateNotifier<AsyncValue<WalletData>> {
  WalletNotifier() : super(const AsyncValue.loading()) {
    _init();
  }

  int? _userId;
  WalletEventCallback? onEvent;
  bool _realtimeSubscribed = false;

  final _noCache = Options(extra: {'noCache': true});

  Future<void> _init() async {
    // Resolve user ID from local storage first (fast, no network)
    try {
      final userJson = await LocalStorage.getString('user_data');
      if (userJson != null) {
        _userId = UserModel.fromJsonString(userJson).id;
      }
    } catch (_) {}

    await _fetch();
    _subscribeRealtime();
  }

  Future<void> _fetch() async {
    try {
      final dio = ApiClient.instance;
      final res = await dio.get('/wallet', options: _noCache);
      final data = res.data['data'] ?? res.data;

      // Fallback: some API responses include user_id
      _userId ??= (data['user_id'] as num?)?.toInt();

      final txRes = await dio.get('/wallet/transactions', options: _noCache);
      final txRaw = txRes.data['data'];
      final List txList = txRaw is List
          ? txRaw
          : (txRaw is Map ? (txRaw['data'] as List? ?? []) : []);

      final pendingTopupRaw = data['pending_topup'];
      state = AsyncValue.data(WalletData(
        balance: (data['balance'] as num?)?.toDouble() ?? 0,
        points: (data['loyalty_points'] as num? ?? data['points'] as num?)?.toInt() ?? 0,
        hasWallet: data['has_wallet'] == true,
        isFrozen: data['is_frozen'] == true,
        pendingTopup: pendingTopupRaw is Map ? Map<String, dynamic>.from(pendingTopupRaw) : null,
        transactions: txList.whereType<Map<String, dynamic>>().toList(),
      ));
    } on DioException catch (e) {
      state = AsyncValue.error(ApiException.fromDio(e), StackTrace.current);
    }
  }

  void _subscribeRealtime() {
    if (_userId == null || _realtimeSubscribed) return;
    _realtimeSubscribed = true;
    RealtimeClient.instance.listen(
      'private-user.$_userId',
      'wallet.transaction',
      _onRealtimeEvent,
    );
  }

  void _onRealtimeEvent(dynamic data) {
    if (data is! Map) return;
    final current = state.valueOrNull;
    if (current == null) return;

    final type = data['type'] as String? ?? '';
    final amount = (data['amount'] as num?)?.toDouble() ?? 0;
    final newBalance = (data['balance'] as num?)?.toDouble() ?? current.balance;
    final note = data['note'] as String? ?? '';
    final method = data['method'] as String? ?? 'wallet';
    final createdAt = data['created_at'] as String? ?? DateTime.now().toIso8601String();

    // Prepend new transaction + update balance instantly — no network call needed
    final newTx = <String, dynamic>{
      'type': type,
      'amount': amount,
      'balance_after': newBalance,
      'note': note,
      'payment_method': method,
      'created_at': createdAt,
    };

    state = AsyncValue.data(current.copyWith(
      balance: newBalance,
      transactions: [newTx, ...current.transactions],
    ));

    // Tell the screen to show a real-time banner
    onEvent?.call(type, amount, note);
  }

  /// Update balance instantly (no loading flash) then re-fetch transactions in background.
  void updateBalanceImmediate(double newBalance) {
    final current = state.valueOrNull;
    if (current != null) {
      state = AsyncValue.data(current.copyWith(balance: newBalance));
    }
    // Background sync for transactions list
    _fetch();
  }

  Future<void> refresh() async {
    state = const AsyncValue.loading();
    await _fetch();
    _subscribeRealtime();
  }

  @override
  void dispose() {
    if (_userId != null) {
      RealtimeClient.instance.removeListener(
        'private-user.$_userId',
        'wallet.transaction',
        _onRealtimeEvent,
      );
    }
    super.dispose();
  }
}

final walletProvider =
    StateNotifierProvider.autoDispose<WalletNotifier, AsyncValue<WalletData>>(
  (ref) => WalletNotifier(),
);
