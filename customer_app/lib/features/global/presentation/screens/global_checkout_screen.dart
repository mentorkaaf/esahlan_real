import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../providers/global_provider.dart';

class GlobalCheckoutScreen extends ConsumerStatefulWidget {
  const GlobalCheckoutScreen({super.key});

  @override
  ConsumerState<GlobalCheckoutScreen> createState() =>
      _GlobalCheckoutScreenState();
}

class _GlobalCheckoutScreenState extends ConsumerState<GlobalCheckoutScreen> {
  String _method = 'stripe';
  bool _loading = false;
  bool _loadingSummary = false;

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(globalCartProvider).valueOrNull;
    final auth = ref.watch(globalAuthProvider).valueOrNull;

    if (auth == null) {
      WidgetsBinding.instance
          .addPostFrameCallback((_) => context.push('/global/auth'));
      return const Scaffold(
          body: Center(child: CircularProgressIndicator()));
    }

    final address = auth.addresses.isNotEmpty
        ? auth.addresses.firstWhere((a) => a.isDefault,
            orElse: () => auth.addresses.first)
        : null;

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      appBar: AppBar(
        title: const Text('Checkout',
            style: TextStyle(fontWeight: FontWeight.w700)),
        backgroundColor: const Color(0xFF1A1A2E),
        foregroundColor: Colors.white,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Shipping address
          _Card(
            title: '📍 Shipping Address',
            child: address != null
                ? Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                    Text(address.name,
                        style: const TextStyle(
                            fontWeight: FontWeight.w700, fontSize: 14)),
                    const SizedBox(height: 4),
                    Text(address.fullAddress,
                        style: TextStyle(
                            color: Colors.grey.shade600, fontSize: 13)),
                    const SizedBox(height: 8),
                    TextButton(
                      onPressed: () => context.push('/global/auth'),
                      child: const Text('Change address',
                          style: TextStyle(
                              fontSize: 12, color: Color(0xFF6366F1))),
                    ),
                  ])
                : Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                    const Text('No address saved.',
                        style: TextStyle(color: Colors.grey)),
                    const SizedBox(height: 8),
                    ElevatedButton(
                      onPressed: () => context.push('/global/auth'),
                      child: const Text('Add Address'),
                    ),
                  ]),
          ),

          const SizedBox(height: 12),

          // Order summary
          if (cart != null)
            _Card(
              title: '🛍 Order Summary',
              child: Column(children: [
                ...cart.items.map((item) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Row(children: [
                        Expanded(
                          child: Text(
                              '${item.name}${item.variant != null ? ' (${item.variant})' : ''}',
                              style: const TextStyle(fontSize: 13),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis),
                        ),
                        const SizedBox(width: 8),
                        Text('×${item.quantity}',
                            style: TextStyle(
                                color: Colors.grey.shade500, fontSize: 12)),
                        const SizedBox(width: 8),
                        Text('\$${item.subtotal.toStringAsFixed(2)}',
                            style: const TextStyle(
                                fontWeight: FontWeight.w700, fontSize: 13)),
                      ]),
                    )),
                const Divider(height: 20),
                Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                  const Text('Subtotal'),
                  Text('\$${cart.subtotal.toStringAsFixed(2)}',
                      style: const TextStyle(fontWeight: FontWeight.w600)),
                ]),
                const SizedBox(height: 4),
                Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: const [
                  Text('Shipping', style: TextStyle(color: Colors.grey)),
                  Text('Calculated below',
                      style: TextStyle(color: Colors.grey, fontSize: 12)),
                ]),
              ]),
            ),

          const SizedBox(height: 12),

          // Payment method
          _Card(
            title: '💳 Payment Method',
            child: Column(children: [
              _PaymentOption(
                value: 'stripe',
                groupValue: _method,
                label: 'Credit / Debit Card',
                sub: 'Visa, Mastercard, Amex via Stripe',
                icon: '💳',
                onChanged: (v) => setState(() => _method = v!),
              ),
              const SizedBox(height: 8),
              _PaymentOption(
                value: 'paypal',
                groupValue: _method,
                label: 'PayPal',
                sub: 'Pay with your PayPal account',
                icon: '🅿',
                onChanged: (v) => setState(() => _method = v!),
              ),
            ]),
          ),

          const SizedBox(height: 24),

          // Place Order button
          SizedBox(
            width: double.infinity,
            height: 52,
            child: ElevatedButton(
              onPressed:
                  (address == null || cart == null || cart.items.isEmpty || _loading)
                      ? null
                      : _placeOrder,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFFF59E0B),
                foregroundColor: const Color(0xFF1A1A2E),
                shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12)),
              ),
              child: _loading
                  ? const SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                          color: Color(0xFF1A1A2E), strokeWidth: 2))
                  : Text(
                      'Pay with ${_method == 'stripe' ? 'Card (Stripe)' : 'PayPal'} →',
                      style: const TextStyle(
                          fontWeight: FontWeight.w800, fontSize: 15)),
            ),
          ),
          const SizedBox(height: 12),
          Center(
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.lock, size: 12, color: Colors.grey),
              const SizedBox(width: 4),
              Text('Secured by Stripe & PayPal',
                  style: TextStyle(
                      fontSize: 11, color: Colors.grey.shade500)),
            ]),
          ),
          const SizedBox(height: 30),
        ]),
      ),
    );
  }

  Future<void> _placeOrder() async {
    final auth = ref.read(globalAuthProvider).valueOrNull;
    if (auth == null) return;

    final address = auth.addresses.isNotEmpty
        ? auth.addresses.firstWhere((a) => a.isDefault,
            orElse: () => auth.addresses.first)
        : null;
    if (address == null) return;

    setState(() => _loading = true);

    try {
      final repo = ref.read(globalRepoProvider);
      final checkoutData = {
        'shipping_name': address.name,
        'shipping_address1': address.addressLine1,
        'shipping_city': address.city,
        'shipping_zip': address.zip,
        'shipping_country': address.country,
      };

      final String checkoutUrl;
      if (_method == 'stripe') {
        checkoutUrl = await repo.createStripeCheckout(checkoutData);
      } else {
        checkoutUrl = await repo.createPayPalCheckout(checkoutData);
      }

      if (mounted) {
        await Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => _WebViewCheckout(
            url: checkoutUrl,
            onSuccess: () {
              ref.invalidate(globalCartProvider);
              ref.invalidate(globalOrdersProvider);
              context.go('/global/orders');
            },
            onCancel: () => Navigator.of(context).pop(),
          ),
        ));
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
              content: Text(e.toString()),
              backgroundColor: Colors.red.shade700),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }
}

class _PaymentOption extends StatelessWidget {
  final String value;
  final String groupValue;
  final String label;
  final String sub;
  final String icon;
  final ValueChanged<String?> onChanged;

  const _PaymentOption({
    required this.value,
    required this.groupValue,
    required this.label,
    required this.sub,
    required this.icon,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final selected = value == groupValue;
    return GestureDetector(
      onTap: () => onChanged(value),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFF0F4FF) : const Color(0xFFF8F9FA),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
              color: selected
                  ? const Color(0xFF6366F1)
                  : Colors.grey.shade200,
              width: selected ? 2 : 1),
        ),
        child: Row(children: [
          Text(icon, style: const TextStyle(fontSize: 24)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(label,
                  style: const TextStyle(
                      fontWeight: FontWeight.w700, fontSize: 14)),
              Text(sub,
                  style: TextStyle(
                      color: Colors.grey.shade500, fontSize: 11)),
            ]),
          ),
          Radio<String>(
            value: value,
            groupValue: groupValue,
            onChanged: onChanged,
            activeColor: const Color(0xFF6366F1),
          ),
        ]),
      ),
    );
  }
}

class _Card extends StatelessWidget {
  final String title;
  final Widget child;

  const _Card({required this.title, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 8,
              offset: const Offset(0, 2))
        ],
      ),
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(title,
            style: const TextStyle(
                fontWeight: FontWeight.w800, fontSize: 14)),
        const SizedBox(height: 12),
        child,
      ]),
    );
  }
}

class _WebViewCheckout extends StatefulWidget {
  final String url;
  final VoidCallback onSuccess;
  final VoidCallback onCancel;

  const _WebViewCheckout({
    required this.url,
    required this.onSuccess,
    required this.onCancel,
  });

  @override
  State<_WebViewCheckout> createState() => _WebViewCheckoutState();
}

class _WebViewCheckoutState extends State<_WebViewCheckout> {
  late final WebViewController _ctrl;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _ctrl = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(NavigationDelegate(
        onPageStarted: (_) => setState(() => _loading = true),
        onPageFinished: (url) {
          setState(() => _loading = false);
          // Detect success / cancel
          if (url.contains('success=1') || url.contains('/orders/')) {
            widget.onSuccess();
          } else if (url.contains('cancelled=1')) {
            widget.onCancel();
          }
        },
      ))
      ..loadRequest(Uri.parse(widget.url));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Complete Payment'),
        backgroundColor: const Color(0xFF1A1A2E),
        foregroundColor: Colors.white,
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: widget.onCancel,
        ),
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _ctrl),
          if (_loading)
            const LinearProgressIndicator(
                backgroundColor: Colors.transparent,
                color: Color(0xFFF59E0B)),
        ],
      ),
    );
  }
}
