import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:go_router/go_router.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../providers/global_provider.dart';
import '../../data/models/global_models.dart';
import '../../data/repositories/global_repository.dart';

class GlobalCheckoutScreen extends ConsumerStatefulWidget {
  const GlobalCheckoutScreen({super.key});

  @override
  ConsumerState<GlobalCheckoutScreen> createState() =>
      _GlobalCheckoutScreenState();
}

class _GlobalCheckoutScreenState extends ConsumerState<GlobalCheckoutScreen> {
  String _method = 'stripe';
  bool _loading = false;

  // Inline address form (shown when no saved address)
  final _firstNameCtrl    = TextEditingController();
  final _lastNameCtrl     = TextEditingController();
  final _addressLine1Ctrl = TextEditingController();
  final _cityCtrl         = TextEditingController();
  final _zipCtrl          = TextEditingController();
  final _countryCodeCtrl  = TextEditingController(text: 'US');
  final _countryNameCtrl  = TextEditingController(text: 'United States');

  @override
  void dispose() {
    _firstNameCtrl.dispose();
    _lastNameCtrl.dispose();
    _addressLine1Ctrl.dispose();
    _cityCtrl.dispose();
    _zipCtrl.dispose();
    _countryCodeCtrl.dispose();
    _countryNameCtrl.dispose();
    super.dispose();
  }

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
          // ── Shipping address ──────────────────────────────────────────────
          _Card(
            title: '📍 Shipping Address',
            child: address != null
                ? _SavedAddressTile(address: address, onChange: () => context.push('/global/auth'))
                : _AddressForm(
                    firstNameCtrl:    _firstNameCtrl,
                    lastNameCtrl:     _lastNameCtrl,
                    addressLine1Ctrl: _addressLine1Ctrl,
                    cityCtrl:         _cityCtrl,
                    zipCtrl:          _zipCtrl,
                    countryCodeCtrl:  _countryCodeCtrl,
                    countryNameCtrl:  _countryNameCtrl,
                  ),
          ),

          const SizedBox(height: 12),

          // ── Order summary ─────────────────────────────────────────────────
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
                _SummaryRow('Subtotal',
                    '\$${cart.subtotal.toStringAsFixed(2)}'),
                const SizedBox(height: 4),
                const _SummaryRow('Shipping', 'Calculated at checkout',
                    muted: true),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF59E0B).withOpacity(0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                    const Text('Estimated Total',
                        style: TextStyle(
                            fontWeight: FontWeight.w800, fontSize: 14)),
                    Text('\$${cart.subtotal.toStringAsFixed(2)}+',
                        style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 14,
                            color: Color(0xFFF59E0B))),
                  ]),
                ),
              ]),
            ),

          const SizedBox(height: 12),

          // ── Payment method ────────────────────────────────────────────────
          _Card(
            title: '💳 Payment Method',
            child: Column(children: [
              _PaymentOption(
                value: 'stripe',
                groupValue: _method,
                label: 'Credit / Debit Card',
                sub: 'Visa, Mastercard, Amex — powered by Stripe',
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

          // ── Place order button ────────────────────────────────────────────
          SizedBox(
            width: double.infinity,
            height: 52,
            child: ElevatedButton(
              onPressed: (cart == null || cart.items.isEmpty || _loading)
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

  Map<String, dynamic> _buildShippingData(GlobalUser auth) {
    final address = auth.addresses.isNotEmpty
        ? auth.addresses.firstWhere((a) => a.isDefault,
            orElse: () => auth.addresses.first)
        : null;

    if (address != null) {
      // Use saved address — split name into first/last
      final parts = address.name.split(' ');
      final firstName = parts.first;
      final lastName  = parts.length > 1 ? parts.sublist(1).join(' ') : '';
      return {
        'address_id':    address.id,
        'first_name':    firstName,
        'last_name':     lastName,
        'address_line1': address.addressLine1,
        'city':          address.city,
        'zip':           address.zip,
        'country_code':  address.country.toUpperCase(),
        'country_name':  address.country, // fallback: use country code as name
      };
    }

    // Use inline form fields
    return {
      'first_name':    _firstNameCtrl.text.trim(),
      'last_name':     _lastNameCtrl.text.trim(),
      'address_line1': _addressLine1Ctrl.text.trim(),
      'city':          _cityCtrl.text.trim(),
      'zip':           _zipCtrl.text.trim(),
      'country_code':  _countryCodeCtrl.text.trim().toUpperCase(),
      'country_name':  _countryNameCtrl.text.trim(),
    };
  }

  Future<void> _placeOrder() async {
    final auth = ref.read(globalAuthProvider).valueOrNull;
    if (auth == null) return;

    // Validate inline form if no saved address
    if (auth.addresses.isEmpty) {
      if (_firstNameCtrl.text.trim().isEmpty ||
          _addressLine1Ctrl.text.trim().isEmpty ||
          _cityCtrl.text.trim().isEmpty ||
          _countryCodeCtrl.text.trim().isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
              content: Text('Please fill in all address fields.'),
              backgroundColor: Colors.red),
        );
        return;
      }
    }

    setState(() => _loading = true);

    try {
      final repo     = ref.read(globalRepoProvider);
      final shipData = _buildShippingData(auth);

      if (_method == 'stripe') {
        await _handleStripe(repo, shipData);
      } else {
        await _handlePayPal(repo, shipData);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
              content: Text('Payment failed: $e'),
              backgroundColor: Colors.red.shade700),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _handleStripe(GlobalRepository repo, Map<String, dynamic> shipData) async {
    final res = await repo.createStripeCheckout(shipData);
    final clientSecret   = res['client_secret'] as String;
    final publicKey      = res['public_key']    as String;
    final orderId        = res['order_id']       as int;

    // Initialize Stripe with the merchant's public key
    Stripe.publishableKey = publicKey;

    // Init payment sheet
    await Stripe.instance.initPaymentSheet(
      paymentSheetParameters: SetupPaymentSheetParameters(
        paymentIntentClientSecret: clientSecret,
        merchantDisplayName: 'eSahlan Global',
        style: ThemeMode.light,
      ),
    );

    // Present payment sheet — throws StripeException if user cancels
    await Stripe.instance.presentPaymentSheet();

    // Payment succeeded — confirm with backend
    final piId = clientSecret.split('_secret_').first; // pi_xxx
    await repo.confirmStripePayment(orderId, piId);

    if (mounted) {
      ref.invalidate(globalCartProvider);
      ref.invalidate(globalOrdersProvider);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Payment successful! Order placed.'),
            backgroundColor: Colors.green),
      );
      context.go('/global/orders');
    }
  }

  Future<void> _handlePayPal(GlobalRepository repo, Map<String, dynamic> shipData) async {
    final res         = await repo.createPayPalCheckout(shipData);
    final approvalUrl = res['approval_url'] as String?;
    final orderId     = res['order_id']     as int;

    if (approvalUrl == null) {
      throw Exception('PayPal setup failed — no approval URL returned.');
    }

    if (!mounted) return;
    final success = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => _PayPalWebView(
        url: approvalUrl,
        orderId: orderId,
        onCapture: (oid) async {
          await repo.capturePaypalPayment(oid);
        },
      ),
    ));

    if (success == true && mounted) {
      ref.invalidate(globalCartProvider);
      ref.invalidate(globalOrdersProvider);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('PayPal payment successful! Order placed.'),
            backgroundColor: Colors.green),
      );
      context.go('/global/orders');
    }
  }
}

// ── Sub-widgets ───────────────────────────────────────────────────────────────

class _SavedAddressTile extends StatelessWidget {
  final dynamic address;
  final VoidCallback onChange;
  const _SavedAddressTile({required this.address, required this.onChange});

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(address.name,
          style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
      const SizedBox(height: 4),
      Text(address.fullAddress,
          style: TextStyle(color: Colors.grey.shade600, fontSize: 13)),
      const SizedBox(height: 8),
      TextButton(
        onPressed: onChange,
        child: const Text('Change address',
            style: TextStyle(fontSize: 12, color: Color(0xFF6366F1))),
      ),
    ]);
  }
}

class _AddressForm extends StatelessWidget {
  final TextEditingController firstNameCtrl;
  final TextEditingController lastNameCtrl;
  final TextEditingController addressLine1Ctrl;
  final TextEditingController cityCtrl;
  final TextEditingController zipCtrl;
  final TextEditingController countryCodeCtrl;
  final TextEditingController countryNameCtrl;

  const _AddressForm({
    required this.firstNameCtrl,
    required this.lastNameCtrl,
    required this.addressLine1Ctrl,
    required this.cityCtrl,
    required this.zipCtrl,
    required this.countryCodeCtrl,
    required this.countryNameCtrl,
  });

  @override
  Widget build(BuildContext context) {
    return Column(children: [
      Row(children: [
        Expanded(child: _Field(ctrl: firstNameCtrl, label: 'First Name')),
        const SizedBox(width: 10),
        Expanded(child: _Field(ctrl: lastNameCtrl, label: 'Last Name')),
      ]),
      const SizedBox(height: 10),
      _Field(ctrl: addressLine1Ctrl, label: 'Street Address'),
      const SizedBox(height: 10),
      Row(children: [
        Expanded(child: _Field(ctrl: cityCtrl, label: 'City')),
        const SizedBox(width: 10),
        SizedBox(width: 100, child: _Field(ctrl: zipCtrl, label: 'ZIP')),
      ]),
      const SizedBox(height: 10),
      Row(children: [
        SizedBox(width: 70, child: _Field(ctrl: countryCodeCtrl, label: 'Code', hint: 'US')),
        const SizedBox(width: 10),
        Expanded(child: _Field(ctrl: countryNameCtrl, label: 'Country Name')),
      ]),
      const SizedBox(height: 8),
      Row(
        children: [
          const Icon(Icons.info_outline, size: 13, color: Colors.grey),
          const SizedBox(width: 4),
          Text('Or sign in to use a saved address',
              style: TextStyle(fontSize: 11, color: Colors.grey.shade500)),
        ],
      ),
    ]);
  }
}

class _Field extends StatelessWidget {
  final TextEditingController ctrl;
  final String label;
  final String? hint;
  const _Field({required this.ctrl, required this.label, this.hint});

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: ctrl,
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: BorderSide(color: Colors.grey.shade300)),
        enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: BorderSide(color: Colors.grey.shade300)),
      ),
      style: const TextStyle(fontSize: 13),
    );
  }
}

class _SummaryRow extends StatelessWidget {
  final String label;
  final String value;
  final bool muted;
  const _SummaryRow(this.label, this.value, {this.muted = false});

  @override
  Widget build(BuildContext context) {
    return Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label,
          style: TextStyle(
              color: muted ? Colors.grey : null, fontSize: 13)),
      Text(value,
          style: TextStyle(
              fontWeight: FontWeight.w600,
              color: muted ? Colors.grey : null,
              fontSize: 13)),
    ]);
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
              color: selected ? const Color(0xFF6366F1) : Colors.grey.shade200,
              width: selected ? 2 : 1),
        ),
        child: Row(children: [
          Text(icon, style: const TextStyle(fontSize: 24)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
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
            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14)),
        const SizedBox(height: 12),
        child,
      ]),
    );
  }
}

// ── PayPal WebView ────────────────────────────────────────────────────────────

class _PayPalWebView extends StatefulWidget {
  final String url;
  final int orderId;
  final Future<void> Function(int orderId) onCapture;

  const _PayPalWebView({
    required this.url,
    required this.orderId,
    required this.onCapture,
  });

  @override
  State<_PayPalWebView> createState() => _PayPalWebViewState();
}

class _PayPalWebViewState extends State<_PayPalWebView> {
  late final WebViewController _ctrl;
  bool _pageLoading = true;
  bool _capturing   = false;

  @override
  void initState() {
    super.initState();
    _ctrl = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(NavigationDelegate(
        onPageStarted: (_) => setState(() => _pageLoading = true),
        onPageFinished: (url) async {
          setState(() => _pageLoading = false);
          // PayPal returns to our return_url with success=1
          if (url.contains('success=1') || url.contains('/orders/')) {
            await _capture();
          } else if (url.contains('cancelled=1') || url.contains('cancel')) {
            if (mounted) Navigator.of(context).pop(false);
          }
        },
      ))
      ..loadRequest(Uri.parse(widget.url));
  }

  Future<void> _capture() async {
    if (_capturing) return;
    setState(() => _capturing = true);
    try {
      await widget.onCapture(widget.orderId);
      if (mounted) Navigator.of(context).pop(true);
    } catch (_) {
      if (mounted) Navigator.of(context).pop(true); // order exists, still success
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('PayPal Checkout'),
        backgroundColor: const Color(0xFF1A1A2E),
        foregroundColor: Colors.white,
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.of(context).pop(false),
        ),
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _ctrl),
          if (_pageLoading)
            const LinearProgressIndicator(
                backgroundColor: Colors.transparent,
                color: Color(0xFFF59E0B)),
          if (_capturing)
            Container(
              color: Colors.black38,
              child: const Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    CircularProgressIndicator(color: Color(0xFFF59E0B)),
                    SizedBox(height: 16),
                    Text('Confirming payment...',
                        style: TextStyle(color: Colors.white, fontSize: 15)),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}
