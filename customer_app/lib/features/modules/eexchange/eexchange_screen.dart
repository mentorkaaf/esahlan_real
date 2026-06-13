import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../wallet/presentation/providers/wallet_provider.dart';

final _svc = ModuleApiService.create();
final _exchangeRatesProvider = FutureProvider.autoDispose((_) => _svc.getExchangeRates());

// ─── Helpers ─────────────────────────────────────────────────────────────────

/// Extracts a human-readable error message from a DioException or any error.
String _extractError(Object e) {
  if (e is DioException) {
    final data = e.response?.data;
    if (data is Map) {
      // Laravel validation errors
      final errors = data['errors'];
      if (errors is Map) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) return first.first.toString();
      }
      if (data['message'] != null) return data['message'].toString();
    }
    if (e.response?.statusCode == 422) return 'Please check your input and try again.';
    if (e.response?.statusCode == 500) return 'Server error. Please try again later.';
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout) return 'Connection timed out. Check your internet.';
    if (e.type == DioExceptionType.connectionError) return 'No internet connection.';
  }
  return e.toString().replaceAll('Exception: ', '');
}

// ─── Main Screen ─────────────────────────────────────────────────────────────

class EExchangeScreen extends ConsumerStatefulWidget {
  const EExchangeScreen({super.key});
  @override
  ConsumerState<EExchangeScreen> createState() => _EExchangeScreenState();
}

class _EExchangeScreenState extends ConsumerState<EExchangeScreen> {
  static const _wallets = [
    {'id': 'evc',     'name': 'EVC Plus',   'color': 0xFFE74C3C, 'icon': Icons.account_balance_wallet_rounded},
    {'id': 'edahab',  'name': 'eDahab',     'color': 0xFF27AE60, 'icon': Icons.payments_rounded},
    {'id': 'jeep',    'name': 'Jeep Money', 'color': 0xFF2980B9, 'icon': Icons.credit_card_rounded},
    {'id': 'premier', 'name': 'Premier',    'color': 0xFF8E44AD, 'icon': Icons.stars_rounded},
  ];

  // State
  String _fromWallet    = 'evc';
  String _toWallet      = 'edahab';
  double _amount        = 0;
  String _paymentMethod = 'wallet'; // 'wallet' or 'waafi_pay'
  String? _waafiRef;
  Map<String, dynamic>? _preview;
  bool   _converting = false;
  bool   _confirming = false;

  // Controllers
  final _amountCtrl  = TextEditingController();
  // Phone starts with +252 — user types the rest
  final _phoneCtrl   = TextEditingController(text: '+252 ');
  final _phoneFocus  = FocusNode();

  @override
  void dispose() {
    _amountCtrl.dispose();
    _phoneCtrl.dispose();
    _phoneFocus.dispose();
    super.dispose();
  }

  String get _toWalletName =>
      (_wallets.firstWhere((w) => w['id'] == _toWallet)['name'] as String);

  Color get _toWalletColor =>
      Color(_wallets.firstWhere((w) => w['id'] == _toWallet)['color'] as int);

  String get _cleanPhone => _phoneCtrl.text.trim();

  bool get _phoneValid {
    final p = _cleanPhone.replaceAll(RegExp(r'\s'), '');
    return p.startsWith('+252') && p.length >= 10;
  }

  @override
  Widget build(BuildContext context) {
    final ratesAsync = ref.watch(_exchangeRatesProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white, elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: AppColors.secondary),
          onPressed: () => context.pop(),
        ),
        title: const Text('eExchange',
            style: TextStyle(fontWeight: FontWeight.w800, color: AppColors.secondary, fontFamily: 'Cairo')),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(children: [

          // ── Hero ──────────────────────────────────────────────────
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [AppColors.primary, Color(0xFF2C3E8A)]),
              borderRadius: BorderRadius.circular(18),
            ),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Digital Wallet Exchange', style: TextStyle(color: Colors.white70, fontSize: 13)),
              const SizedBox(height: 4),
              const Text('Transfer between wallets instantly',
                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(20)),
                child: const Text('1% Service Fee', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
              ),
            ]),
          ),
          const SizedBox(height: 20),

          // ── FROM wallet ───────────────────────────────────────────
          const Align(alignment: Alignment.centerLeft,
              child: Text('From Wallet', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary))),
          const SizedBox(height: 10),
          _buildWalletRow(_fromWallet,
              (id) => setState(() { _fromWallet = id; _preview = null; })),
          const SizedBox(height: 16),

          // ── Swap button ────────────────────────────────────────────
          Center(child: GestureDetector(
            onTap: () => setState(() {
              final tmp = _fromWallet; _fromWallet = _toWallet; _toWallet = tmp; _preview = null;
            }),
            child: Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: AppColors.primary, shape: BoxShape.circle,
                boxShadow: [BoxShadow(color: AppColors.primary.withValues(alpha: 0.3), blurRadius: 10)],
              ),
              child: const Icon(Icons.swap_vert_rounded, color: Colors.white, size: 24),
            ),
          )),
          const SizedBox(height: 16),

          // ── TO wallet ─────────────────────────────────────────────
          const Align(alignment: Alignment.centerLeft,
              child: Text('To Wallet', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary))),
          const SizedBox(height: 10),
          _buildWalletRow(_toWallet,
              (id) => setState(() { _toWallet = id; _preview = null; })),
          const SizedBox(height: 20),

          // ── Amount ────────────────────────────────────────────────
          _buildAmountCard(),
          const SizedBox(height: 14),

          // ── Recipient Phone ───────────────────────────────────────
          _buildPhoneCard(),
          const SizedBox(height: 16),

          // ── Live Rates ────────────────────────────────────────────
          ratesAsync.when(
            loading: () => const SizedBox(),
            error: (_, __) => const SizedBox(),
            data: (res) {
              final rates = res['data'] as List? ?? [];
              if (rates.isEmpty) return const SizedBox(height: 8);
              return Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.divider)),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('Live Rates', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.secondary)),
                  const SizedBox(height: 8),
                  ...rates.take(4).map((r) => Padding(
                    padding: const EdgeInsets.symmetric(vertical: 3),
                    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      Text('${r['from_wallet']?.toString().toUpperCase()} → ${r['to_wallet']?.toString().toUpperCase()}',
                          style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
                      Text('Rate: ${r['rate']}',
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.secondary)),
                    ]),
                  )),
                ]),
              );
            },
          ),
          const SizedBox(height: 16),

          // ── Preview breakdown ─────────────────────────────────────
          if (_preview != null) ...[
            _buildPreviewCard(),
            const SizedBox(height: 16),
            _buildPaymentMethod(),
            const SizedBox(height: 14),
            AppButton(
              label: _confirming ? 'Processing...' : 'Confirm & Pay',
              isLoading: _confirming,
              onPressed: _fromWallet == _toWallet ? null : _confirm,
            ),
          ] else ...[
            AppButton(
              label: _converting ? 'Calculating...' : 'Preview Exchange',
              isLoading: _converting,
              outlined: true,
              onPressed: (_amount <= 0 || _fromWallet == _toWallet || !_phoneValid)
                  ? null
                  : _previewExchange,
            ),
            // Validation hints
            if (_fromWallet == _toWallet)
              _hint('Select different wallets', isError: true)
            else if (_amount > 0 && !_phoneValid)
              _hint('Enter a valid phone number (e.g. +252 61 234 5678)', isError: true),
          ],

          const SizedBox(height: 40),
        ]),
      ),
    );
  }

  // ─── Widgets ───────────────────────────────────────────────────────────────

  Widget _buildWalletRow(String selected, void Function(String) onSelect) {
    return Row(children: _wallets.map((w) {
      final sel   = selected == w['id'];
      final color = Color(w['color'] as int);
      return Expanded(child: GestureDetector(
        onTap: () => onSelect(w['id'] as String),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          margin: const EdgeInsets.only(right: 8),
          padding: const EdgeInsets.symmetric(vertical: 12),
          decoration: BoxDecoration(
            color: sel ? color.withValues(alpha: 0.12) : Colors.white,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: sel ? color : AppColors.divider, width: sel ? 2 : 1),
          ),
          child: Column(children: [
            Icon(w['icon'] as IconData, color: sel ? color : AppColors.textGrey, size: 22),
            const SizedBox(height: 4),
            Text((w['name'] as String).split(' ').first,
                style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700,
                    color: sel ? color : AppColors.textGrey)),
          ]),
        ),
      ));
    }).toList());
  }

  Widget _buildAmountCard() => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.divider)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Amount to Send',
          style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.textGrey)),
      const SizedBox(height: 8),
      TextField(
        controller: _amountCtrl,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 28, color: AppColors.secondary),
        onChanged: (v) => setState(() { _amount = double.tryParse(v) ?? 0; _preview = null; }),
        decoration: const InputDecoration(
          hintText: '0.00',
          hintStyle: TextStyle(fontWeight: FontWeight.w300, fontSize: 28, color: AppColors.divider),
          border: InputBorder.none,
          prefixText: '\$  ',
          prefixStyle: TextStyle(fontWeight: FontWeight.w900, fontSize: 28, color: AppColors.secondary),
        ),
      ),
      Wrap(spacing: 8, children: [10, 25, 50, 100, 200].map((v) => GestureDetector(
        onTap: () { _amountCtrl.text = v.toString(); setState(() { _amount = v.toDouble(); _preview = null; }); },
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(8),
              border: Border.all(color: AppColors.divider)),
          child: Text('\$$v',
              style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: AppColors.secondary)),
        ),
      )).toList()),
    ]),
  );

  Widget _buildPhoneCard() {
    final focusColor = _phoneFocus.hasFocus ? AppColors.primary : AppColors.divider;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 150),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: focusColor, width: _phoneFocus.hasFocus ? 2 : 1),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(
            padding: const EdgeInsets.all(7),
            decoration: BoxDecoration(
                color: _toWalletColor.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(8)),
            child: Icon(Icons.phone_rounded, color: _toWalletColor, size: 18),
          ),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('$_toWalletName Phone Number',
                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.secondary)),
            const Text('Recipient who will receive the transfer',
                style: TextStyle(fontSize: 11, color: AppColors.textGrey)),
          ])),
        ]),
        const SizedBox(height: 12),
        TextField(
          controller: _phoneCtrl,
          focusNode: _phoneFocus,
          keyboardType: TextInputType.phone,
          inputFormatters: [
            // Keep +252 prefix, allow digits and spaces after
            _PhonePrefixFormatter('+252 '),
          ],
          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 22, color: AppColors.secondary,
              letterSpacing: 1),
          onChanged: (_) => setState(() => _preview = null),
          onTap: () {
            setState(() {});
            // Put cursor at end
            _phoneCtrl.selection = TextSelection.fromPosition(
                TextPosition(offset: _phoneCtrl.text.length));
          },
          decoration: const InputDecoration(
            hintText: '+252 61 234 5678',
            hintStyle: TextStyle(fontSize: 20, color: AppColors.divider, fontWeight: FontWeight.w400,
                letterSpacing: .5),
            border: InputBorder.none,
          ),
        ),
        if (_cleanPhone.length > 5)
          Padding(
            padding: const EdgeInsets.only(top: 6),
            child: Text(
              _phoneValid ? '✓ Valid number' : 'Enter remaining digits',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: _phoneValid ? AppColors.success : AppColors.textGrey,
              ),
            ),
          ),
      ]),
    );
  }

  Widget _buildPreviewCard() => Container(
    padding: const EdgeInsets.all(18),
    decoration: BoxDecoration(
      gradient: const LinearGradient(colors: [AppColors.secondary, Color(0xFF1A1060)]),
      borderRadius: BorderRadius.circular(16),
    ),
    child: Column(children: [
      Row(children: [
        const Icon(Icons.receipt_long_rounded, color: Colors.white70, size: 18),
        const SizedBox(width: 8),
        const Text('Exchange Summary',
            style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w700)),
      ]),
      const SizedBox(height: 14),
      _row('From',     '${(_preview!['from_wallet'] ?? _preview!['from'])?.toString().toUpperCase()}', white: true),
      _row('To',       '${(_preview!['to_wallet']   ?? _preview!['to'])?.toString().toUpperCase()}',   white: true),
      _row('You Send', '\$${_preview!['amount']}',   white: true),
      _row('Rate',     '${_preview!['rate']}',        white: true),
      _row('Fee (1%)', '-\$${_preview!['fee']}',     white: true),
      const SizedBox(height: 4),
      const Divider(color: Colors.white24, height: 1),
      const SizedBox(height: 12),
      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        const Text('Recipient Gets',
            style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
        Text('\$${_preview!['converted_amount'] ?? _preview!['converted'] ?? _preview!['you_receive']}',
            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 28)),
      ]),
      const SizedBox(height: 14),
      const Divider(color: Colors.white24, height: 1),
      const SizedBox(height: 12),
      // Recipient box
      Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(10),
          border: Border.all(color: Colors.white24),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('Sending to', style: TextStyle(color: Colors.white60, fontSize: 11)),
          const SizedBox(height: 6),
          Row(children: [
            const Text('📱', style: TextStyle(fontSize: 18)),
            const SizedBox(width: 8),
            Expanded(child: Text(_cleanPhone,
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 18))),
          ]),
          const SizedBox(height: 2),
          Text(_toWalletName, style: const TextStyle(color: Colors.white60, fontSize: 11)),
        ]),
      ),
    ]),
  );

  Widget _buildPaymentMethod() => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(14),
      border: Border.all(color: AppColors.divider),
    ),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Row(children: [
        Icon(Icons.payment_rounded, size: 18, color: AppColors.primary),
        SizedBox(width: 8),
        Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary)),
      ]),
      const SizedBox(height: 12),
      _payOption('wallet',    'Wallet',     Icons.account_balance_wallet_outlined, 'Deducted from your wallet balance'),
      const SizedBox(height: 10),
      _payOption('waafi_pay', 'Waafi Pay',  Icons.phone_android_rounded,           'EVC / eDahab / Jeep / Premier'),
    ]),
  );

  Widget _payOption(String value, String label, IconData icon, String sub) {
    final sel = _paymentMethod == value;
    return GestureDetector(
      onTap: () => setState(() { _paymentMethod = value; _waafiRef = null; }),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: sel ? AppColors.primary.withValues(alpha: 0.06) : AppColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: sel ? AppColors.primary : AppColors.divider, width: sel ? 2 : 1),
        ),
        child: Row(children: [
          Container(
            width: 40, height: 40,
            decoration: BoxDecoration(
                color: sel ? AppColors.primary : Colors.white,
                borderRadius: BorderRadius.circular(10)),
            child: Icon(icon, size: 20, color: sel ? Colors.white : AppColors.textGrey),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13,
                color: sel ? AppColors.primary : AppColors.secondary)),
            Text(sub, style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
          ])),
          if (sel) const Icon(Icons.check_circle_rounded, color: AppColors.primary, size: 20),
        ]),
      ),
    );
  }

  Widget _row(String label, String value, {bool white = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(color: white ? Colors.white70 : AppColors.textGrey, fontSize: 13)),
      Text(value,  style: TextStyle(color: white ? Colors.white : AppColors.secondary,
          fontWeight: FontWeight.w700, fontSize: 13)),
    ]),
  );

  Widget _hint(String text, {bool isError = false}) => Padding(
    padding: const EdgeInsets.only(top: 10),
    child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
      Icon(isError ? Icons.info_outline : Icons.check_circle_outline,
          size: 14, color: isError ? AppColors.error : AppColors.success),
      const SizedBox(width: 6),
      Flexible(child: Text(text,
          style: TextStyle(color: isError ? AppColors.error : AppColors.success, fontSize: 12),
          textAlign: TextAlign.center)),
    ]),
  );

  // ─── Logic ─────────────────────────────────────────────────────────────────

  Future<void> _previewExchange() async {
    if (!_phoneValid) {
      _phoneFocus.requestFocus();
      _showErrorDialog('Please enter a valid Somalia phone number starting with +252');
      return;
    }
    setState(() => _converting = true);
    try {
      final res = await _svc.previewExchange({
        'from_wallet': _fromWallet,
        'to_wallet':   _toWallet,
        'amount':      _amount,
      });
      setState(() => _preview = Map<String, dynamic>.from(res['data']));
    } catch (e) {
      if (mounted) _showErrorDialog(_extractError(e));
    } finally {
      if (mounted) setState(() => _converting = false);
    }
  }

  Future<void> _confirm() async {
    // Waafi Pay — show payment sheet first, capture reference
    if (_paymentMethod == 'waafi_pay') {
      final result = await showWaafiPaySheet(
        context,
        amount: _amount,
        type: 'order',
        description: 'eExchange ${_fromWallet.toUpperCase()} → ${_toWallet.toUpperCase()}',
        prefillPhone: _cleanPhone,
      );
      if (result?.success != true) return; // user cancelled or failed
      _waafiRef = result!.reference;
    }

    if (_paymentMethod == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }

    setState(() => _confirming = true);
    try {
      final res = await _svc.confirmExchange({
        'from_wallet':      _fromWallet,
        'to_wallet':        _toWallet,
        'amount':           _amount,
        'recipient_phone':  _cleanPhone,
        'payment_method':   _paymentMethod,
        if (_waafiRef != null) 'payment_reference': _waafiRef,
      });
      if (mounted) {
        final data = Map<String, dynamic>.from(res['data'] ?? {});
        if (_paymentMethod == 'wallet') ref.invalidate(walletProvider);
        _navigateToSuccess(data);
      }
    } catch (e) {
      if (mounted) _showErrorDialog(_extractError(e));
    } finally {
      if (mounted) setState(() => _confirming = false);
    }
  }

  void _showErrorDialog(String message) {
    showDialog(
      context: context,
      builder: (_) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 64, height: 64,
              decoration: BoxDecoration(
                  color: AppColors.error.withValues(alpha: 0.1), shape: BoxShape.circle),
              child: const Icon(Icons.error_outline_rounded, color: AppColors.error, size: 36),
            ),
            const SizedBox(height: 16),
            const Text('Something went wrong',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: AppColors.secondary),
                textAlign: TextAlign.center),
            const SizedBox(height: 10),
            Text(message,
                style: const TextStyle(color: AppColors.textGrey, fontSize: 13, height: 1.5),
                textAlign: TextAlign.center),
            const SizedBox(height: 22),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: () => Navigator.of(context).pop(),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                ),
                child: const Text('Try Again', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
              ),
            ),
          ]),
        ),
      ),
    );
  }

  void _navigateToSuccess(Map<String, dynamic> data) {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => _ExchangeSuccessScreen(data: data)),
    );
  }
}

// ─── Phone Prefix Formatter ───────────────────────────────────────────────────

class _PhonePrefixFormatter extends TextInputFormatter {
  final String prefix;
  _PhonePrefixFormatter(this.prefix);

  @override
  TextEditingValue formatEditUpdate(TextEditingValue oldValue, TextEditingValue newValue) {
    String text = newValue.text;
    if (!text.startsWith(prefix)) {
      text = prefix + text.replaceAll(RegExp(r'^\+252\s*'), '');
    }
    return newValue.copyWith(
      text: text,
      selection: TextSelection.collapsed(offset: text.length),
    );
  }
}

// ─── Success Screen ───────────────────────────────────────────────────────────

class _ExchangeSuccessScreen extends StatelessWidget {
  final Map<String, dynamic> data;
  const _ExchangeSuccessScreen({required this.data});

  @override
  Widget build(BuildContext context) {
    final ref       = data['reference']        ?? '—';
    final from      = '${data['from_wallet'] ?? '—'}';
    final to        = '${data['to_wallet']   ?? '—'}';
    final sent      = data['sent_amount']      ?? data['amount']   ?? 0;
    final fee       = data['fee']              ?? 0;
    final received  = data['converted_amount'] ?? data['converted'] ?? 0;
    final phone     = data['recipient_phone']  ?? '—';

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(children: [
            const SizedBox(height: 30),

            // ── Success Icon ──────────────────────────────────────
            Container(
              width: 100, height: 100,
              decoration: BoxDecoration(
                color: AppColors.success.withValues(alpha: 0.12),
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.check_circle_rounded, color: AppColors.success, size: 58),
            ),
            const SizedBox(height: 20),
            const Text('Order Submitted!',
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: AppColors.secondary)),
            const SizedBox(height: 8),
            const Text('Your exchange request has been received.\nWe\'ll process it shortly.',
                style: TextStyle(fontSize: 14, color: AppColors.textGrey, height: 1.6),
                textAlign: TextAlign.center),
            const SizedBox(height: 32),

            // ── Reference ─────────────────────────────────────────
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [AppColors.primary, Color(0xFF2C3E8A)]),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(children: [
                const Text('Reference Number', style: TextStyle(color: Colors.white60, fontSize: 12, fontWeight: FontWeight.w600)),
                const SizedBox(height: 6),
                GestureDetector(
                  onTap: () {
                    Clipboard.setData(ClipboardData(text: ref.toString()));
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Reference copied!'), duration: Duration(seconds: 1)),
                    );
                  },
                  child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Text(ref.toString(),
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900,
                            fontSize: 20, letterSpacing: 1.5, fontFamily: 'Courier')),
                    const SizedBox(width: 8),
                    const Icon(Icons.copy_rounded, color: Colors.white54, size: 18),
                  ]),
                ),
              ]),
            ),
            const SizedBox(height: 20),

            // ── Exchange Details ───────────────────────────────────
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.divider),
              ),
              child: Column(children: [
                const Align(alignment: Alignment.centerLeft,
                    child: Text('Exchange Details',
                        style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary))),
                const SizedBox(height: 14),
                _detailRow('From Wallet',    from, icon: Icons.arrow_upward_rounded,    color: Colors.red),
                _detailRow('To Wallet',      to,   icon: Icons.arrow_downward_rounded,  color: AppColors.success),
                _detailRow('Amount Sent',    '\$$sent'),
                _detailRow('Service Fee',    '-\$$fee'),
                const Divider(height: 20),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  const Text('Recipient Receives',
                      style: TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: AppColors.secondary)),
                  Text('\$$received',
                      style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 22, color: AppColors.success)),
                ]),
              ]),
            ),
            const SizedBox(height: 16),

            // ── Recipient ──────────────────────────────────────────
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: AppColors.divider),
              ),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Sending To',
                    style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary)),
                const SizedBox(height: 12),
                Row(children: [
                  Container(
                    width: 48, height: 48,
                    decoration: BoxDecoration(
                        color: AppColors.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
                    child: const Center(child: Text('📱', style: TextStyle(fontSize: 24))),
                  ),
                  const SizedBox(width: 12),
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(phone.toString(),
                        style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 20, color: AppColors.secondary)),
                    Text('$to Wallet',
                        style: const TextStyle(color: AppColors.textGrey, fontSize: 12)),
                  ]),
                ]),
              ]),
            ),
            const SizedBox(height: 16),

            // ── Status Notice ──────────────────────────────────────
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFFFF8E1),
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFFFD54F)),
              ),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Icon(Icons.info_outline_rounded, color: Color(0xFFF9A825), size: 20),
                const SizedBox(width: 10),
                const Expanded(child: Text(
                  'Your order is pending. Please send the exact amount from your wallet to complete the exchange. '
                  'You will be notified once processed.',
                  style: TextStyle(fontSize: 12, color: Color(0xFF795548), height: 1.5),
                )),
              ]),
            ),
            const SizedBox(height: 30),

            // ── Action Buttons ─────────────────────────────────────
            AppButton(
              label: 'Done',
              onPressed: () {
                // Pop until home
                while (context.canPop()) { context.pop(); }
              },
            ),
            const SizedBox(height: 12),
            TextButton(
              onPressed: () => Navigator.of(context).pushReplacement(
                  MaterialPageRoute(builder: (_) => const EExchangeScreen())),
              child: const Text('New Exchange', style: TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700)),
            ),
            const SizedBox(height: 20),
          ]),
        ),
      ),
    );
  }

  Widget _detailRow(String label, String value, {IconData? icon, Color? color}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 7),
    child: Row(children: [
      if (icon != null) ...[
        Icon(icon, size: 16, color: color ?? AppColors.textGrey),
        const SizedBox(width: 8),
      ],
      Expanded(child: Text(label, style: const TextStyle(color: AppColors.textGrey, fontSize: 13))),
      Text(value, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13,
          color: color ?? AppColors.secondary)),
    ]),
  );
}
