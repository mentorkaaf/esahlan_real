import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../api/module_api_service.dart';

class PaymentMethodsData {
  final List<String> enabled;
  final Map<String, String> logos; // method key → logo URL

  const PaymentMethodsData({required this.enabled, required this.logos});

  static const fallback = PaymentMethodsData(
    enabled: ['cod', 'waafi_pay', 'wallet', 'mobile_pay'],
    logos: {},
  );
}

/// Fetches enabled payment methods + logos from admin.
final enabledPaymentMethodsProvider = FutureProvider<PaymentMethodsData>((ref) async {
  return ModuleApiService.create().getPaymentMethodsData();
});

/// Synchronous helper — checks if a given method key is in the enabled list.
bool isMethodEnabled(List<String>? enabled, String method) {
  if (enabled == null || enabled.isEmpty) return true;
  return enabled.contains(method);
}
