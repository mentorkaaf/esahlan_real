import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../api/module_api_service.dart';

/// Fetches the list of payment methods enabled by the admin.
/// Falls back to all methods if the request fails.
final enabledPaymentMethodsProvider = FutureProvider<List<String>>((ref) async {
  return ModuleApiService.create().getEnabledPaymentMethods();
});

/// Synchronous helper — checks if a given method key is in the enabled list.
/// [method]: 'cod' | 'waafi_pay' | 'wallet' | 'mobile_pay'
bool isMethodEnabled(List<String>? enabled, String method) {
  if (enabled == null || enabled.isEmpty) return true; // fail-open
  return enabled.contains(method);
}
