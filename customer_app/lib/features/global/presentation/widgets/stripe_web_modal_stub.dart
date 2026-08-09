// Stub for non-web platforms — Stripe web modal is web-only.
Future<Map<String, dynamic>?> showStripeWebModal({
  required dynamic context,
  required String publicKey,
  required String clientSecret,
  required String totalLabel,
}) async {
  throw UnsupportedError('Stripe web modal is only available on web.');
}
