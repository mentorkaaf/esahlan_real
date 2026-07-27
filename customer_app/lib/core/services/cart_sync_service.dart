import 'dart:async';
import '../api/api_client.dart';

/// Syncs cart items to backend for abandoned cart notifications.
/// Call [sync] whenever cart changes, [clear] after successful checkout.
class CartSyncService {
  CartSyncService._();
  static final instance = CartSyncService._();

  final _client = ApiClient.instance;

  Timer? _debounce;

  /// Debounced sync — waits 3 sec after last change before calling API.
  /// [items] format: [{'product_id': 1, 'product_name': 'x', 'price': 5.0, 'quantity': 2}]
  void syncDebounced(String module, List<Map<String, dynamic>> items) {
    _debounce?.cancel();
    _debounce = Timer(const Duration(seconds: 3), () => _doSync(module, items));
  }

  Future<void> _doSync(String module, List<Map<String, dynamic>> items) async {
    try {
      await _client.post('/cart/sync', data: {
        'module': module,
        'items': items,
      });
    } catch (_) {
      // Silent fail — notifications are best-effort
    }
  }

  /// Call after successful checkout to stop abandonment notifications.
  Future<void> clearModule(String module) async {
    _debounce?.cancel();
    try {
      await _client.post('/cart/clear-module', data: {'module': module});
    } catch (_) {}
  }
}
