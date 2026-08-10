import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_stripe/flutter_stripe.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';
import '../providers/global_provider.dart';
import '../../data/models/global_models.dart';
import '../../data/repositories/global_repository.dart';
import '../widgets/stripe_web_modal.dart';

// ── Country data ──────────────────────────────────────────────────────────────

class _Country {
  final String code;
  final String name;
  final String flag;
  const _Country(this.code, this.name, this.flag);
}

const _kCountries = [
  _Country('US', 'United States',       '🇺🇸'),
  _Country('GB', 'United Kingdom',      '🇬🇧'),
  _Country('CA', 'Canada',              '🇨🇦'),
  _Country('AU', 'Australia',           '🇦🇺'),
  _Country('DE', 'Germany',             '🇩🇪'),
  _Country('FR', 'France',              '🇫🇷'),
  _Country('NL', 'Netherlands',         '🇳🇱'),
  _Country('SE', 'Sweden',              '🇸🇪'),
  _Country('NO', 'Norway',              '🇳🇴'),
  _Country('DK', 'Denmark',             '🇩🇰'),
  _Country('FI', 'Finland',             '🇫🇮'),
  _Country('CH', 'Switzerland',         '🇨🇭'),
  _Country('AT', 'Austria',             '🇦🇹'),
  _Country('BE', 'Belgium',             '🇧🇪'),
  _Country('IT', 'Italy',               '🇮🇹'),
  _Country('ES', 'Spain',               '🇪🇸'),
  _Country('PT', 'Portugal',            '🇵🇹'),
  _Country('IE', 'Ireland',             '🇮🇪'),
  _Country('NZ', 'New Zealand',         '🇳🇿'),
  _Country('SG', 'Singapore',           '🇸🇬'),
  _Country('AE', 'UAE',                 '🇦🇪'),
  _Country('SA', 'Saudi Arabia',        '🇸🇦'),
  _Country('QA', 'Qatar',               '🇶🇦'),
  _Country('KW', 'Kuwait',              '🇰🇼'),
  _Country('BH', 'Bahrain',             '🇧🇭'),
  _Country('OM', 'Oman',                '🇴🇲'),
  _Country('ET', 'Ethiopia',            '🇪🇹'),
  _Country('KE', 'Kenya',               '🇰🇪'),
  _Country('NG', 'Nigeria',             '🇳🇬'),
  _Country('ZA', 'South Africa',        '🇿🇦'),
  _Country('EG', 'Egypt',               '🇪🇬'),
  _Country('SO', 'Somalia',             '🇸🇴'),
  _Country('DJ', 'Djibouti',            '🇩🇯'),
  _Country('TR', 'Turkey',              '🇹🇷'),
  _Country('IN', 'India',               '🇮🇳'),
  _Country('PK', 'Pakistan',            '🇵🇰'),
  _Country('JP', 'Japan',               '🇯🇵'),
  _Country('CN', 'China',               '🇨🇳'),
  _Country('KR', 'South Korea',         '🇰🇷'),
  _Country('MY', 'Malaysia',            '🇲🇾'),
  _Country('ID', 'Indonesia',           '🇮🇩'),
  _Country('PH', 'Philippines',         '🇵🇭'),
  _Country('TH', 'Thailand',            '🇹🇭'),
  _Country('BR', 'Brazil',              '🇧🇷'),
  _Country('MX', 'Mexico',              '🇲🇽'),
  _Country('AR', 'Argentina',           '🇦🇷'),
];

_Country _findCountry(String code) =>
    _kCountries.firstWhere((c) => c.code == code,
        orElse: () => const _Country('US', 'United States', '🇺🇸'));

// ── Screen ────────────────────────────────────────────────────────────────────

class GlobalCheckoutScreen extends ConsumerStatefulWidget {
  const GlobalCheckoutScreen({super.key});

  @override
  ConsumerState<GlobalCheckoutScreen> createState() =>
      _GlobalCheckoutScreenState();
}

class _GlobalCheckoutScreenState extends ConsumerState<GlobalCheckoutScreen> {
  final _formKey = GlobalKey<FormState>();

  // Address controllers
  final _firstNameCtrl = TextEditingController();
  final _lastNameCtrl  = TextEditingController();
  final _line1Ctrl     = TextEditingController();
  final _line2Ctrl     = TextEditingController(); // optional
  final _cityCtrl      = TextEditingController();
  final _stateCtrl     = TextEditingController(); // optional
  final _zipCtrl       = TextEditingController();
  final _phoneCtrl     = TextEditingController(); // optional

  _Country _country = const _Country('US', 'United States', '🇺🇸');
  String _method    = 'stripe';
  bool   _loading   = false;

  // Tracks whether required fields are filled → enables Pay button
  bool _formFilled = false;

  @override
  void initState() {
    super.initState();
    // Listen to required fields to update button state reactively
    for (final c in [_firstNameCtrl, _line1Ctrl, _cityCtrl]) {
      c.addListener(_checkForm);
    }
    // Refresh cart so Buy Now flow always shows latest cart
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.invalidate(globalCartProvider);
    });
  }

  void _checkForm() {
    final filled = _firstNameCtrl.text.trim().isNotEmpty &&
        _line1Ctrl.text.trim().isNotEmpty &&
        _cityCtrl.text.trim().isNotEmpty;
    if (filled != _formFilled) setState(() => _formFilled = filled);
  }

  @override
  void dispose() {
    for (final c in [
      _firstNameCtrl, _lastNameCtrl, _line1Ctrl, _line2Ctrl,
      _cityCtrl, _stateCtrl, _zipCtrl, _phoneCtrl,
    ]) {
      c.dispose();
    }
    super.dispose();
  }

  // ── Build ─────────────────────────────────────────────────────────────────

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(globalCartProvider).valueOrNull;
    final auth = ref.watch(globalAuthProvider).valueOrNull;

    if (auth == null) {
      WidgetsBinding.instance
          .addPostFrameCallback((_) => context.push('/global/auth'));
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }

    final savedAddress = auth.addresses.isNotEmpty
        ? auth.addresses.firstWhere((a) => a.isDefault,
            orElse: () => auth.addresses.first)
        : null;

    // Button is active when: cart has items AND (saved address exists OR form filled)
    final canPay = !_loading &&
        (cart?.items.isNotEmpty ?? false) &&
        (savedAddress != null || _formFilled);

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      appBar: AppBar(
        title: const Text('Checkout',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
        backgroundColor: const Color(0xFF1A1A2E),
        foregroundColor: Colors.white,
        elevation: 0,
      ),

      // ── Pay button — always visible ───────────────────────────────────────
      bottomNavigationBar: SafeArea(
        child: Container(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 12),
          decoration: BoxDecoration(
            color: Colors.white,
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.08),
                blurRadius: 16,
                offset: const Offset(0, -4),
              ),
            ],
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Total row
              if (cart != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('${cart.count} item${cart.count != 1 ? 's' : ''}',
                          style: TextStyle(
                              color: Colors.grey.shade600, fontSize: 13)),
                      Text('\$${cart.subtotal.toStringAsFixed(2)} + shipping',
                          style: const TextStyle(
                              fontWeight: FontWeight.w800, fontSize: 14)),
                    ],
                  ),
                ),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: canPay ? _placeOrder : null,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: canPay
                        ? const Color(0xFFF59E0B)
                        : Colors.grey.shade300,
                    foregroundColor: const Color(0xFF1A1A2E),
                    elevation: 0,
                    shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14)),
                  ),
                  child: _loading
                      ? const SizedBox(
                          width: 22, height: 22,
                          child: CircularProgressIndicator(
                              color: Color(0xFF1A1A2E), strokeWidth: 2.5))
                      : Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              _method == 'stripe'
                                  ? Icons.credit_card_rounded
                                  : Icons.account_balance_wallet_outlined,
                              size: 18,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              _method == 'stripe'
                                  ? 'Pay with Card'
                                  : 'Pay with PayPal',
                              style: const TextStyle(
                                  fontWeight: FontWeight.w800, fontSize: 15),
                            ),
                          ],
                        ),
                ),
              ),
              if (!canPay && !_loading && savedAddress == null)
                Padding(
                  padding: const EdgeInsets.only(top: 7),
                  child: Text(
                    'Fill in your shipping address to continue',
                    style: TextStyle(
                        fontSize: 11, color: Colors.grey.shade500),
                    textAlign: TextAlign.center,
                  ),
                ),
              const SizedBox(height: 4),
              Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                const Icon(Icons.lock_rounded, size: 11, color: Colors.grey),
                const SizedBox(width: 4),
                Text('Secured by Stripe & PayPal',
                    style: TextStyle(
                        fontSize: 10, color: Colors.grey.shade400)),
              ]),
            ],
          ),
        ),
      ),

      // ── Body ─────────────────────────────────────────────────────────────
      body: Form(
        key: _formKey,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [

              // ── Shipping address ────────────────────────────────────────
              _Section(
                icon: Icons.location_on_rounded,
                title: 'Shipping Address',
                child: savedAddress != null
                    ? _SavedAddressCard(
                        address: savedAddress,
                        onEdit: () => context.push('/global/address'),
                      )
                    : _AddressForm(
                        firstNameCtrl: _firstNameCtrl,
                        lastNameCtrl:  _lastNameCtrl,
                        line1Ctrl:     _line1Ctrl,
                        line2Ctrl:     _line2Ctrl,
                        cityCtrl:      _cityCtrl,
                        stateCtrl:     _stateCtrl,
                        zipCtrl:       _zipCtrl,
                        phoneCtrl:     _phoneCtrl,
                        country:       _country,
                        onCountryChange: (c) =>
                            setState(() => _country = c),
                      ),
              ),

              const SizedBox(height: 14),

              // ── Order summary ───────────────────────────────────────────
              if (cart != null && cart.items.isNotEmpty)
                _Section(
                  icon: Icons.shopping_bag_outlined,
                  title: 'Order Summary',
                  child: Column(children: [
                    ...cart.items.map((item) => Padding(
                          padding:
                              const EdgeInsets.symmetric(vertical: 7),
                          child: Row(children: [
                            // Thumbnail
                            ClipRRect(
                              borderRadius: BorderRadius.circular(6),
                              child: item.thumbnail != null
                                  ? Image.network(item.thumbnail!,
                                      width: 46, height: 46,
                                      fit: BoxFit.cover,
                                      errorBuilder: (_, __, ___) =>
                                          _ImgPlaceholder())
                                  : _ImgPlaceholder(),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Column(
                                  crossAxisAlignment:
                                      CrossAxisAlignment.start,
                                  children: [
                                Text(item.name,
                                    maxLines: 1,
                                    overflow: TextOverflow.ellipsis,
                                    style: const TextStyle(
                                        fontWeight: FontWeight.w700,
                                        fontSize: 13)),
                                if (item.variant != null)
                                  Text(item.variant!,
                                      style: TextStyle(
                                          color: Colors.grey.shade500,
                                          fontSize: 11)),
                              ]),
                            ),
                            const SizedBox(width: 8),
                            Column(
                                crossAxisAlignment:
                                    CrossAxisAlignment.end,
                                children: [
                              Text('×${item.quantity}',
                                  style: TextStyle(
                                      color: Colors.grey.shade400,
                                      fontSize: 11)),
                              Text('\$${item.subtotal.toStringAsFixed(2)}',
                                  style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 13)),
                            ]),
                          ]),
                        )),
                    const Divider(height: 20),
                    _TotalRow('Subtotal',
                        '\$${cart.subtotal.toStringAsFixed(2)}'),
                    const SizedBox(height: 4),
                    _TotalRow('Shipping', 'Calculated at checkout',
                        muted: true),
                  ]),
                ),

              const SizedBox(height: 14),

              // ── Payment method ──────────────────────────────────────────
              _Section(
                icon: Icons.payment_rounded,
                title: 'Payment Method',
                child: Column(children: [
                  _PayOption(
                    value: 'stripe',
                    groupValue: _method,
                    label: 'Credit / Debit Card',
                    sub: 'Visa · Mastercard · Amex · via Stripe',
                    emoji: '💳',
                    onChanged: (v) => setState(() => _method = v!),
                  ),
                  const SizedBox(height: 8),
                  _PayOption(
                    value: 'paypal',
                    groupValue: _method,
                    label: 'PayPal',
                    sub: 'Pay with your PayPal account',
                    emoji: '🅿',
                    onChanged: (v) => setState(() => _method = v!),
                  ),
                ]),
              ),

              const SizedBox(height: 100),
            ],
          ),
        ),
      ),
    );
  }

  // ── Helpers ───────────────────────────────────────────────────────────────

  Map<String, dynamic> _buildShipData(GlobalUser auth) {
    final saved = auth.addresses.isNotEmpty
        ? auth.addresses.firstWhere((a) => a.isDefault,
            orElse: () => auth.addresses.first)
        : null;

    if (saved != null) {
      final parts = saved.name.split(' ');
      return {
        'address_id':    saved.id,
        'first_name':    parts.first,
        'last_name':     parts.length > 1 ? parts.sublist(1).join(' ') : '',
        'address_line1': saved.addressLine1,
        'city':          saved.city,
        'zip':           saved.zip ?? '',
        'country_code':  saved.country.toUpperCase(),
        'country_name':  saved.country,
      };
    }

    return {
      'first_name':    _firstNameCtrl.text.trim(),
      'last_name':     _lastNameCtrl.text.trim(),
      'address_line1': _line1Ctrl.text.trim() +
          (_line2Ctrl.text.trim().isNotEmpty
              ? ', ${_line2Ctrl.text.trim()}'
              : ''),
      'city':          _cityCtrl.text.trim(),
      'zip':           _zipCtrl.text.trim(),
      'country_code':  _country.code,
      'country_name':  _country.name,
    };
  }

  Future<void> _placeOrder() async {
    final auth = ref.read(globalAuthProvider).valueOrNull;
    if (auth == null) return;

    setState(() => _loading = true);
    try {
      final repo     = ref.read(globalRepoProvider);
      final shipData = _buildShipData(auth);

      if (_method == 'stripe') {
        await _doStripe(repo, shipData);
      } else {
        await _doPayPal(repo, shipData);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text('Payment error: $e'),
          backgroundColor: Colors.red.shade700,
          behavior: SnackBarBehavior.floating,
        ));
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _doStripe(GlobalRepository repo, Map<String, dynamic> ship) async {
    if (kIsWeb) {
      // Web: inline Stripe Payment Element modal (no redirect, card-only)
      final res          = await repo.createStripeCheckout(ship);
      final clientSecret = res['client_secret'] as String;
      final publicKey    = res['public_key']    as String;
      final orderId      = res['order_id']      as int;
      final totalRaw     = res['total'];
      final totalLabel   = '\$${(totalRaw is num ? totalRaw : double.tryParse('$totalRaw') ?? 0).toStringAsFixed(2)}';

      if (!mounted) return;
      final result = await showStripeWebModal(
        context:      context,
        publicKey:    publicKey,
        clientSecret: clientSecret,
        totalLabel:   totalLabel,
      );

      if (result == null || result['cancelled'] == true) {
        throw Exception('Payment cancelled.');
      }
      if (result['success'] != true) {
        throw Exception('Payment failed. Please try again.');
      }

      final piId = result['paymentIntentId'] as String? ??
          clientSecret.split('_secret_').first;
      await repo.confirmStripePayment(orderId, piId);

      if (mounted) {
        ref.invalidate(globalCartProvider);
        ref.invalidate(globalOrdersProvider);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('✓  Payment successful! Order placed.'),
          backgroundColor: Colors.green,
          behavior: SnackBarBehavior.floating,
        ));
        context.go('/global/orders');
      }
      return;
    }

    // Mobile: flutter_stripe CardField — official Stripe SDK (PCI compliant)
    final res          = await repo.createStripeCheckout(ship);
    final clientSecret = res['client_secret'] as String;
    final publicKey    = res['public_key']    as String;
    final orderId      = res['order_id']      as int;
    final totalRaw     = res['total'];
    final totalLabel   = '\$${(totalRaw is num ? totalRaw : double.tryParse('$totalRaw') ?? 0).toStringAsFixed(2)}';

    Stripe.publishableKey = publicKey;
    await Stripe.instance.applySettings();

    if (!mounted) return;
    final paid = await _showMobileCardSheet(
      clientSecret: clientSecret,
      totalLabel:   totalLabel,
    );
    if (!paid) throw Exception('Payment cancelled.');

    final piId = clientSecret.split('_secret_').first;
    await repo.confirmStripePayment(orderId, piId);

    if (mounted) {
      ref.invalidate(globalCartProvider);
      ref.invalidate(globalOrdersProvider);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('✓  Payment successful! Order placed.'),
        backgroundColor: Colors.green,
        behavior: SnackBarBehavior.floating,
      ));
      context.go('/global/orders');
    }
  }

  /// Shows a modal bottom sheet with flutter_stripe CardField.
  /// Uses Stripe's official SDK — PCI compliant, no raw card data ever leaves device.
  /// Returns true if payment succeeded, false if cancelled.
  Future<bool> _showMobileCardSheet({
    required String clientSecret,
    required String totalLabel,
  }) async {
    final result = await showModalBottomSheet<bool>(
      context:            context,
      isScrollControlled: true,   // allows sheet to resize with keyboard
      backgroundColor:    Colors.transparent,
      useSafeArea:        false,  // we handle safe area manually in the widget
      builder: (_) => _MobileCardSheet(
        clientSecret: clientSecret,
        totalLabel:   totalLabel,
      ),
    );
    return result == true;
  }

  Future<void> _doPayPal(GlobalRepository repo, Map<String, dynamic> ship) async {
    final res         = await repo.createPayPalCheckout(ship);
    final approvalUrl = res['approval_url'] as String?;
    final orderId     = res['order_id']     as int;

    if (approvalUrl == null) throw Exception('PayPal setup failed.');

    if (!mounted) return;

    if (kIsWeb) {
      // Web: open PayPal in same tab — PayPal return_url redirects back
      await launchUrl(Uri.parse(approvalUrl), mode: LaunchMode.externalApplication);
      // Capture will happen via return URL redirect; show pending message
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
          content: Text('Redirecting to PayPal... Return here after payment.'),
          behavior: SnackBarBehavior.floating,
          duration: Duration(seconds: 6),
        ));
      }
      return;
    }

    // Mobile: in-app WebView
    final success = await Navigator.of(context).push<bool>(MaterialPageRoute(
      builder: (_) => _PayPalWebView(
        url: approvalUrl,
        orderId: orderId,
        onCapture: repo.capturePaypalPayment,
      ),
    ));

    if (success == true && mounted) {
      ref.invalidate(globalCartProvider);
      ref.invalidate(globalOrdersProvider);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(
        content: Text('✓  PayPal payment successful!'),
        backgroundColor: Colors.green,
        behavior: SnackBarBehavior.floating,
      ));
      context.go('/global/orders');
    }
  }
}

// ── Address form ──────────────────────────────────────────────────────────────

class _AddressForm extends StatelessWidget {
  final TextEditingController firstNameCtrl;
  final TextEditingController lastNameCtrl;
  final TextEditingController line1Ctrl;
  final TextEditingController line2Ctrl;
  final TextEditingController cityCtrl;
  final TextEditingController stateCtrl;
  final TextEditingController zipCtrl;
  final TextEditingController phoneCtrl;
  final _Country country;
  final ValueChanged<_Country> onCountryChange;

  const _AddressForm({
    required this.firstNameCtrl,
    required this.lastNameCtrl,
    required this.line1Ctrl,
    required this.line2Ctrl,
    required this.cityCtrl,
    required this.stateCtrl,
    required this.zipCtrl,
    required this.phoneCtrl,
    required this.country,
    required this.onCountryChange,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // ── Name row ──────────────────────────────────────────────────────
        Row(children: [
          Expanded(
            child: _FField(
              ctrl: firstNameCtrl,
              label: 'First Name',
              required: true,
              textCapitalization: TextCapitalization.words,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _FField(
              ctrl: lastNameCtrl,
              label: 'Last Name',
            ),
          ),
        ]),
        const SizedBox(height: 10),

        // ── Street address ────────────────────────────────────────────────
        _FField(
          ctrl: line1Ctrl,
          label: 'Street Address',
          hint: '123 Main St',
          required: true,
        ),
        const SizedBox(height: 10),

        // ── Apartment (optional) ──────────────────────────────────────────
        _FField(
          ctrl: line2Ctrl,
          label: 'Apt / Suite / Floor',
          hint: 'Optional',
        ),
        const SizedBox(height: 10),

        // ── City + State ──────────────────────────────────────────────────
        Row(children: [
          Expanded(
            flex: 3,
            child: _FField(
              ctrl: cityCtrl,
              label: 'City',
              required: true,
              textCapitalization: TextCapitalization.words,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            flex: 2,
            child: _FField(
              ctrl: stateCtrl,
              label: 'State / Province',
              hint: 'Optional',
              textCapitalization: TextCapitalization.words,
            ),
          ),
        ]),
        const SizedBox(height: 10),

        // ── ZIP + Country ─────────────────────────────────────────────────
        Row(children: [
          SizedBox(
            width: 110,
            child: _FField(
              ctrl: zipCtrl,
              label: 'ZIP / Postal',
              hint: 'e.g. 10001',
              keyboard: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9A-Za-z\- ]'))],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(child: _CountryPicker(
            selected: country,
            onChanged: onCountryChange,
          )),
        ]),
        const SizedBox(height: 10),

        // ── Phone (optional) ──────────────────────────────────────────────
        _FField(
          ctrl: phoneCtrl,
          label: 'Phone Number',
          hint: 'Optional — for delivery updates',
          keyboard: TextInputType.phone,
          prefix: Text(
            '${country.flag} +',
            style: const TextStyle(fontSize: 14),
          ),
        ),
        const SizedBox(height: 8),

        // Required note
        Row(children: [
          const Icon(Icons.info_outline, size: 12, color: Colors.grey),
          const SizedBox(width: 4),
          Text(
            '* Required fields',
            style: TextStyle(fontSize: 11, color: Colors.grey.shade500),
          ),
        ]),
      ],
    );
  }
}

// ── Country picker ────────────────────────────────────────────────────────────

class _CountryPicker extends StatelessWidget {
  final _Country selected;
  final ValueChanged<_Country> onChanged;
  const _CountryPicker({required this.selected, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _showPicker(context),
      child: Container(
        height: 52,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Colors.grey.shade300),
        ),
        child: Row(children: [
          Text(selected.flag, style: const TextStyle(fontSize: 20)),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              selected.name,
              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600),
              overflow: TextOverflow.ellipsis,
            ),
          ),
          Icon(Icons.expand_more, color: Colors.grey.shade400, size: 18),
        ]),
      ),
    );
  }

  void _showPicker(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _CountrySheet(
        selected: selected,
        onPick: (c) {
          onChanged(c);
          Navigator.pop(context);
        },
      ),
    );
  }
}

class _CountrySheet extends StatefulWidget {
  final _Country selected;
  final ValueChanged<_Country> onPick;
  const _CountrySheet({required this.selected, required this.onPick});

  @override
  State<_CountrySheet> createState() => _CountrySheetState();
}

class _CountrySheetState extends State<_CountrySheet> {
  String _q = '';
  List<_Country> get _filtered => _q.isEmpty
      ? _kCountries
      : _kCountries
          .where((c) =>
              c.name.toLowerCase().contains(_q.toLowerCase()) ||
              c.code.toLowerCase().contains(_q.toLowerCase()))
          .toList();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: MediaQuery.of(context).size.height * 0.7,
      child: Column(children: [
        const SizedBox(height: 12),
        Container(
          width: 36, height: 4,
          decoration: BoxDecoration(
              color: Colors.grey.shade300,
              borderRadius: BorderRadius.circular(2)),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
          child: Row(children: [
            const Text('Select Country',
                style:
                    TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: TextField(
            onChanged: (v) => setState(() => _q = v),
            decoration: InputDecoration(
              prefixIcon:
                  const Icon(Icons.search, size: 18, color: Colors.grey),
              hintText: 'Search country...',
              contentPadding: const EdgeInsets.symmetric(vertical: 10),
              border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: Colors.grey.shade300)),
              enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: Colors.grey.shade300)),
            ),
          ),
        ),
        const SizedBox(height: 8),
        Expanded(
          child: ListView.builder(
            itemCount: _filtered.length,
            itemBuilder: (_, i) {
              final c = _filtered[i];
              final sel = c.code == widget.selected.code;
              return ListTile(
                leading: Text(c.flag,
                    style: const TextStyle(fontSize: 22)),
                title: Text(c.name,
                    style: TextStyle(
                        fontWeight: sel
                            ? FontWeight.w700
                            : FontWeight.w500,
                        fontSize: 14)),
                trailing: sel
                    ? const Icon(Icons.check_circle_rounded,
                        color: Color(0xFFF59E0B))
                    : null,
                onTap: () => widget.onPick(c),
              );
            },
          ),
        ),
      ]),
    );
  }
}

// ── Saved address card ────────────────────────────────────────────────────────

class _SavedAddressCard extends StatelessWidget {
  final GlobalAddress address;
  final VoidCallback onEdit;
  const _SavedAddressCard({required this.address, required this.onEdit});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: const Color(0xFFECFDF5),
            borderRadius: BorderRadius.circular(8),
          ),
          child: const Icon(Icons.check_circle_rounded,
              color: Color(0xFF065F46), size: 20),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(address.name,
                  style: const TextStyle(
                      fontWeight: FontWeight.w800, fontSize: 14)),
              const SizedBox(height: 2),
              Text(address.fullAddress,
                  style: TextStyle(
                      color: Colors.grey.shade600, fontSize: 12,
                      height: 1.4)),
            ],
          ),
        ),
        TextButton(
          onPressed: onEdit,
          style: TextButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 8),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: const Text('Change',
              style: TextStyle(
                  fontSize: 12,
                  color: Color(0xFF6366F1),
                  fontWeight: FontWeight.w700)),
        ),
      ],
    );
  }
}

// ── Reusable widgets ──────────────────────────────────────────────────────────

class _Section extends StatelessWidget {
  final IconData icon;
  final String title;
  final Widget child;
  const _Section(
      {required this.icon, required this.title, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 2))
        ],
      ),
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(icon, size: 17, color: const Color(0xFF1A1A2E)),
          const SizedBox(width: 7),
          Text(title,
              style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  fontSize: 14,
                  color: Color(0xFF1A1A2E))),
        ]),
        const SizedBox(height: 14),
        child,
      ]),
    );
  }
}

class _FField extends StatelessWidget {
  final TextEditingController ctrl;
  final String label;
  final String? hint;
  final bool required;
  final TextInputType? keyboard;
  final TextCapitalization textCapitalization;
  final List<TextInputFormatter>? inputFormatters;
  final Widget? prefix;

  const _FField({
    required this.ctrl,
    required this.label,
    this.hint,
    this.required = false,
    this.keyboard,
    this.textCapitalization = TextCapitalization.none,
    this.inputFormatters,
    this.prefix,
  });

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: ctrl,
      keyboardType: keyboard,
      textCapitalization: textCapitalization,
      inputFormatters: inputFormatters,
      validator: required
          ? (v) => (v == null || v.trim().isEmpty) ? 'Required' : null
          : null,
      decoration: InputDecoration(
        labelText: required ? '$label *' : label,
        hintText: hint,
        prefix: prefix != null
            ? Padding(
                padding: const EdgeInsets.only(right: 4),
                child: prefix)
            : null,
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(10),
            borderSide: BorderSide(color: Colors.grey.shade300)),
        enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(10),
            borderSide: BorderSide(color: Colors.grey.shade300)),
        focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(10),
            borderSide: const BorderSide(
                color: Color(0xFF6366F1), width: 1.8)),
        labelStyle: TextStyle(color: Colors.grey.shade600, fontSize: 13),
        hintStyle: TextStyle(color: Colors.grey.shade400, fontSize: 13),
        filled: true,
        fillColor: Colors.white,
      ),
      style: const TextStyle(fontSize: 14),
    );
  }
}

class _TotalRow extends StatelessWidget {
  final String label;
  final String value;
  final bool muted;
  const _TotalRow(this.label, this.value, {this.muted = false});

  @override
  Widget build(BuildContext context) {
    return Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(fontSize: 13, color: muted ? Colors.grey : null)),
      Text(value, style: TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w600,
          color: muted ? Colors.grey : null)),
    ]);
  }
}

class _PayOption extends StatelessWidget {
  final String value;
  final String groupValue;
  final String label;
  final String sub;
  final String emoji;
  final ValueChanged<String?> onChanged;

  const _PayOption({
    required this.value, required this.groupValue, required this.label,
    required this.sub, required this.emoji, required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    final sel = value == groupValue;
    return GestureDetector(
      onTap: () => onChanged(value),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: sel ? const Color(0xFFF0F4FF) : const Color(0xFFF8F9FA),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
              color: sel ? const Color(0xFF6366F1) : Colors.grey.shade200,
              width: sel ? 2 : 1),
        ),
        child: Row(children: [
          Text(emoji, style: const TextStyle(fontSize: 26)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
              Text(sub, style: TextStyle(color: Colors.grey.shade500, fontSize: 11)),
            ]),
          ),
          Container(
            width: 20, height: 20,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                  color: sel ? const Color(0xFF6366F1) : Colors.grey.shade300,
                  width: 2),
              color: sel ? const Color(0xFF6366F1) : Colors.transparent,
            ),
            child: sel
                ? const Icon(Icons.check, size: 12, color: Colors.white)
                : null,
          ),
        ]),
      ),
    );
  }
}

class _ImgPlaceholder extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      width: 46, height: 46,
      color: Colors.grey.shade100,
      child: Icon(Icons.image_outlined, size: 20, color: Colors.grey.shade400),
    );
  }
}

// ── PayPal WebView ────────────────────────────────────────────────────────────

class _PayPalWebView extends StatefulWidget {
  final String url;
  final int orderId;
  final Future<void> Function(int) onCapture;
  const _PayPalWebView({required this.url, required this.orderId, required this.onCapture});

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
      if (mounted) Navigator.of(context).pop(true);
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
      body: Stack(children: [
        WebViewWidget(controller: _ctrl),
        if (_pageLoading)
          const LinearProgressIndicator(
              backgroundColor: Colors.transparent,
              color: Color(0xFFF59E0B)),
        if (_capturing)
          Container(
            color: Colors.black38,
            child: const Center(
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                CircularProgressIndicator(color: Color(0xFFF59E0B)),
                SizedBox(height: 16),
                Text('Confirming payment...',
                    style: TextStyle(color: Colors.white, fontSize: 15)),
              ]),
            ),
          ),
      ]),
    );
  }
}

// ── Mobile card-only payment bottom sheet ────────────────────────────────────
// Uses flutter_stripe CardFormField (multi-row) — PCI compliant.
// Card data never leaves the device in plain text; Stripe SDK handles it all.

class _MobileCardSheet extends StatefulWidget {
  final String clientSecret;
  final String totalLabel;
  const _MobileCardSheet({
    required this.clientSecret,
    required this.totalLabel,
  });

  @override
  State<_MobileCardSheet> createState() => _MobileCardSheetState();
}

class _MobileCardSheetState extends State<_MobileCardSheet> {
  // CardFormField uses a controller instead of onCardChanged callback
  final _formCtrl = CardFormEditController();
  bool    _paying  = false;
  String? _error;

  static const _brand = Color(0xFF07003B);
  static const _gold  = Color(0xFFF5A623);

  @override
  void initState() {
    super.initState();
    _formCtrl.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _formCtrl.dispose();
    super.dispose();
  }

  bool get _cardComplete => _formCtrl.details?.complete == true;

  Future<void> _pay() async {
    if (!_cardComplete) {
      setState(() => _error = 'Please complete your card details.');
      return;
    }
    setState(() { _paying = true; _error = null; });
    try {
      // flutter_stripe handles tokenization internally via native Android/iOS SDK
      // Card data never passes through our server — fully PCI compliant
      final result = await Stripe.instance.confirmPayment(
        paymentIntentClientSecret: widget.clientSecret,
        data: const PaymentMethodParams.card(
          paymentMethodData: PaymentMethodData(),
        ),
      );
      final status = result.status;
      if (status == PaymentIntentsStatus.Succeeded ||
          status == PaymentIntentsStatus.Processing) {
        if (mounted) Navigator.of(context).pop(true);
      } else {
        setState(() {
          _error  = 'Payment not completed. Please try again.';
          _paying = false;
        });
      }
    } on StripeException catch (e) {
      if (e.error.code == FailureCode.Canceled) {
        setState(() { _paying = false; });
        return;
      }
      setState(() {
        _error  = e.error.localizedMessage ?? e.error.message ?? 'Payment failed.';
        _paying = false;
      });
    } catch (e) {
      setState(() { _error = e.toString(); _paying = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final mq     = MediaQuery.of(context);
    final bottom = mq.viewInsets.bottom;   // keyboard height
    final safeB  = mq.padding.bottom;      // safe area (home indicator)

    return Padding(
      // Push sheet up by exactly the keyboard height — sheet stays readable
      padding: EdgeInsets.only(bottom: bottom),
      child: Container(
        margin: const EdgeInsets.fromLTRB(12, 0, 12, 12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(24),
        ),
        // SingleChildScrollView so content is reachable when keyboard is open
        child: SingleChildScrollView(
          physics: const ClampingScrollPhysics(),
          child: Padding(
            padding: EdgeInsets.fromLTRB(20, 24, 20, 20 + safeB),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ── Drag handle ────────────────────────────────────────────
                Center(
                  child: Container(
                    width: 40, height: 4,
                    margin: const EdgeInsets.only(bottom: 16),
                    decoration: BoxDecoration(
                      color: const Color(0xFFE5E7EB),
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),

                // ── Header ─────────────────────────────────────────────────
                Row(children: [
                  Expanded(child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(children: [
                        Icon(Icons.lock, size: 13, color: Color(0xFF22c55e)),
                        SizedBox(width: 4),
                        Text('SECURE PAYMENT',
                            style: TextStyle(fontSize: 11, color: Color(0xFF22c55e),
                                fontWeight: FontWeight.w700, letterSpacing: 0.5)),
                      ]),
                      const SizedBox(height: 4),
                      const Text('Card Details',
                          style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800,
                              color: Color(0xFF111827))),
                    ],
                  )),
                  GestureDetector(
                    onTap: () => Navigator.of(context).pop(false),
                    child: Container(
                      width: 36, height: 36,
                      decoration: BoxDecoration(
                          color: const Color(0xFFF3F4F6),
                          borderRadius: BorderRadius.circular(18)),
                      child: const Icon(Icons.close, size: 18, color: Color(0xFF6B7280)),
                    ),
                  ),
                ]),
                const SizedBox(height: 24),

                // ── Stripe CardFormField ───────────────────────────────────
                // Multi-row form: card number, expiry, CVC each on own line.
                // Much more readable & easier to type than single-line CardField.
                // Native Android/iOS Stripe SDK — PCI compliant.
                CardFormField(
                  controller: _formCtrl,
                  style: CardFormStyle(
                    backgroundColor: const Color(0xFFF9FAFB),
                    borderColor: const Color(0xFFE5E7EB),
                    borderRadius: 12,
                    borderWidth: 2,
                    fontSize: 16,
                    textColor: const Color(0xFF111827),
                    placeholderColor: const Color(0xFF9CA3AF),
                    cursorColor: const Color(0xFFF5A623),
                  ),
                ),

                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF2F2),
                      border: Border.all(color: const Color(0xFFFECACA)),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Text(_error!,
                        style: const TextStyle(color: Color(0xFFDC2626), fontSize: 13)),
                  ),
                ],
                const SizedBox(height: 24),

                // ── Pay button ─────────────────────────────────────────────
                SizedBox(
                  width: double.infinity,
                  height: 54,
                  child: ElevatedButton(
                    onPressed: (_paying || !_cardComplete) ? null : _pay,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _gold,
                      foregroundColor: _brand,
                      disabledBackgroundColor: _gold.withValues(alpha: 0.5),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14)),
                      elevation: 0,
                    ),
                    child: _paying
                        ? const SizedBox(height: 22, width: 22,
                            child: CircularProgressIndicator(
                                strokeWidth: 2.5, color: Color(0xFF07003B)))
                        : Text('Pay ${widget.totalLabel}',
                            style: const TextStyle(
                                fontSize: 17, fontWeight: FontWeight.w800)),
                  ),
                ),
                const SizedBox(height: 14),
                const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.lock_outline, size: 13, color: Color(0xFF9CA3AF)),
                  SizedBox(width: 5),
                  Text('256-bit SSL · Powered by Stripe',
                      style: TextStyle(fontSize: 11, color: Color(0xFF9CA3AF))),
                ]),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
