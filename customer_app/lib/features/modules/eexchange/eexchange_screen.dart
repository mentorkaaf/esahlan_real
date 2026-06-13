import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_button.dart';

final _svc = ModuleApiService.create();
final _exchangeRatesProvider = FutureProvider.autoDispose((_) => _svc.getExchangeRates());

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

  String _fromWallet = 'evc';
  String _toWallet   = 'edahab';
  double _amount     = 0;
  Map<String, dynamic>? _preview;
  bool   _converting = false;
  bool   _confirming = false;

  final _amountCtrl = TextEditingController();
  final _phoneCtrl  = TextEditingController();
  final _phoneFocus = FocusNode();

  @override
  void dispose() {
    _amountCtrl.dispose();
    _phoneCtrl.dispose();
    _phoneFocus.dispose();
    super.dispose();
  }

  String get _toWalletName => (_wallets.firstWhere((w) => w['id'] == _toWallet)['name'] as String);

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

          // ── Hero card ──────────────────────────────────────────────
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
              const Text('Exchange between wallets instantly',
                  style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(20)),
                child: const Text('1% Service Fee Applied',
                    style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
              ),
            ]),
          ),
          const SizedBox(height: 20),

          // ── FROM wallet ────────────────────────────────────────────
          const Align(alignment: Alignment.centerLeft,
              child: Text('From Wallet',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary))),
          const SizedBox(height: 10),
          _buildWalletRow(_fromWallet, (id) => setState(() { _fromWallet = id; _preview = null; })),
          const SizedBox(height: 16),

          // ── Swap arrow ─────────────────────────────────────────────
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

          // ── TO wallet ──────────────────────────────────────────────
          const Align(alignment: Alignment.centerLeft,
              child: Text('To Wallet',
                  style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary))),
          const SizedBox(height: 10),
          _buildWalletRow(_toWallet, (id) => setState(() { _toWallet = id; _preview = null; })),
          const SizedBox(height: 20),

          // ── Amount ─────────────────────────────────────────────────
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
                color: Colors.white, borderRadius: BorderRadius.circular(14),
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
              // Quick amounts
              Wrap(spacing: 8, children: [10, 25, 50, 100, 200].map((v) => GestureDetector(
                onTap: () { _amountCtrl.text = v.toString(); setState(() { _amount = v.toDouble(); _preview = null; }); },
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                      color: AppColors.surface, borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: AppColors.divider)),
                  child: Text('\$$v',
                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: AppColors.secondary)),
                ),
              )).toList()),
            ]),
          ),
          const SizedBox(height: 14),

          // ── Recipient Phone Number ─────────────────────────────────
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: _phoneFocus.hasFocus ? AppColors.primary : AppColors.divider,
                width: _phoneFocus.hasFocus ? 2 : 1,
              ),
            ),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Container(
                  padding: const EdgeInsets.all(7),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Icon(Icons.phone_rounded, color: AppColors.primary, size: 18),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(
                      '$_toWalletName Phone Number',
                      style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: AppColors.textGrey),
                    ),
                    const Text(
                      'Number to receive the transfer',
                      style: TextStyle(fontSize: 10, color: AppColors.textGrey),
                    ),
                  ]),
                ),
              ]),
              const SizedBox(height: 10),
              TextField(
                controller: _phoneCtrl,
                focusNode: _phoneFocus,
                keyboardType: TextInputType.phone,
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 20, color: AppColors.secondary),
                onChanged: (_) => setState(() => _preview = null),
                onTap: () => setState(() {}),
                decoration: InputDecoration(
                  hintText: '+252 61 234 5678',
                  hintStyle: const TextStyle(fontSize: 18, color: AppColors.divider, fontWeight: FontWeight.w400),
                  border: InputBorder.none,
                  prefixIcon: Container(
                    margin: const EdgeInsets.only(right: 8),
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 14),
                    child: const Text(
                      '📱',
                      style: TextStyle(fontSize: 20),
                    ),
                  ),
                  prefixIconConstraints: const BoxConstraints(minWidth: 0, minHeight: 0),
                ),
              ),
            ]),
          ),
          const SizedBox(height: 16),

          // ── Live Rates ─────────────────────────────────────────────
          ratesAsync.when(
            loading: () => const SizedBox(),
            error: (_, __) => const SizedBox(),
            data: (res) {
              final rates = res['data'] as List? ?? [];
              if (rates.isEmpty) return const SizedBox();
              return Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: AppColors.divider)),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('Live Rates',
                      style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.secondary)),
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

          // ── Preview breakdown ──────────────────────────────────────
          if (_preview != null) ...[
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [AppColors.secondary, Color(0xFF1A1060)]),
                borderRadius: BorderRadius.circular(16),
              ),
              child: Column(children: [
                // Header
                Row(children: [
                  const Icon(Icons.receipt_long_rounded, color: Colors.white70, size: 18),
                  const SizedBox(width: 8),
                  const Text('Exchange Summary',
                      style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w700)),
                ]),
                const SizedBox(height: 14),
                _row('From',       '${(_preview!['from_wallet'] ?? _preview!['from'])?.toString().toUpperCase()}', white: true),
                _row('To',         '${(_preview!['to_wallet']   ?? _preview!['to'])?.toString().toUpperCase()}',   white: true),
                _row('You Send',   '\$${_preview!['amount']}',             white: true),
                _row('Rate',       '${_preview!['rate']}',                 white: true),
                _row('Fee (1%)',   '-\$${_preview!['fee']}',              white: true),
                const SizedBox(height: 4),
                const Divider(color: Colors.white24, height: 1),
                const SizedBox(height: 12),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  const Text('You Receive',
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
                  Text('\$${_preview!['converted_amount'] ?? _preview!['converted'] ?? _preview!['you_receive']}',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 28)),
                ]),
                const SizedBox(height: 14),
                const Divider(color: Colors.white24, height: 1),
                const SizedBox(height: 12),
                // Recipient info box
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
                    const SizedBox(height: 4),
                    Row(children: [
                      const Icon(Icons.phone_rounded, color: Colors.white70, size: 16),
                      const SizedBox(width: 6),
                      Text(
                        _phoneCtrl.text,
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16),
                      ),
                    ]),
                    const SizedBox(height: 2),
                    Text(
                      _toWalletName,
                      style: const TextStyle(color: Colors.white60, fontSize: 11),
                    ),
                  ]),
                ),
              ]),
            ),
            const SizedBox(height: 12),
            AppButton(
              label: _confirming ? 'Processing...' : 'Confirm Exchange',
              isLoading: _confirming,
              onPressed: _fromWallet == _toWallet ? null : _confirm,
            ),
          ] else
            AppButton(
              label: _converting ? 'Calculating...' : 'Preview Exchange',
              isLoading: _converting,
              outlined: true,
              onPressed: (_amount <= 0 || _fromWallet == _toWallet || _phoneCtrl.text.trim().isEmpty)
                  ? null
                  : _previewExchange,
            ),

          // ── Validation hints ───────────────────────────────────────
          if (_fromWallet == _toWallet)
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text('Please select different wallets',
                  style: TextStyle(color: AppColors.error, fontSize: 13), textAlign: TextAlign.center),
            )
          else if (_phoneCtrl.text.trim().isEmpty && _amount > 0)
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text('Please enter the recipient phone number',
                  style: TextStyle(color: AppColors.error, fontSize: 13), textAlign: TextAlign.center),
            ),

          const SizedBox(height: 40),
        ]),
      ),
    );
  }

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
            Text(
              (w['name'] as String).split(' ').first,
              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: sel ? color : AppColors.textGrey),
            ),
          ]),
        ),
      ));
    }).toList());
  }

  Widget _row(String label, String value, {bool white = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(color: white ? Colors.white70 : AppColors.textGrey, fontSize: 13)),
      Text(value,  style: TextStyle(color: white ? Colors.white  : AppColors.secondary,
          fontWeight: FontWeight.w700, fontSize: 13)),
    ]),
  );

  Future<void> _previewExchange() async {
    final phone = _phoneCtrl.text.trim();
    if (phone.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter recipient phone number'), backgroundColor: AppColors.error));
      _phoneFocus.requestFocus();
      return;
    }
    setState(() => _converting = true);
    try {
      final res = await _svc.previewExchange({'from_wallet': _fromWallet, 'to_wallet': _toWallet, 'amount': _amount});
      setState(() => _preview = Map<String, dynamic>.from(res['data']));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.error));
    } finally {
      setState(() => _converting = false);
    }
  }

  Future<void> _confirm() async {
    setState(() => _confirming = true);
    try {
      final res = await _svc.confirmExchange({
        'from_wallet':     _fromWallet,
        'to_wallet':       _toWallet,
        'amount':          _amount,
        'recipient_phone': _phoneCtrl.text.trim(),
      });
      if (mounted) {
        final data = res['data'] as Map? ?? {};
        _showSuccessDialog(data);
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.error));
    } finally {
      setState(() => _confirming = false);
    }
  }

  void _showSuccessDialog(Map data) {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 70, height: 70,
              decoration: const BoxDecoration(color: Color(0xFFE8F5E9), shape: BoxShape.circle),
              child: const Icon(Icons.check_circle_rounded, color: AppColors.success, size: 42),
            ),
            const SizedBox(height: 16),
            const Text('Exchange Successful!',
                style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: AppColors.secondary)),
            const SizedBox(height: 6),
            Text('Your transfer has been completed',
                style: TextStyle(color: AppColors.textGrey, fontSize: 13)),
            const SizedBox(height: 20),
            // Details
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(12)),
              child: Column(children: [
                _detailRow('Reference', '${data['reference'] ?? '—'}'),
                _detailRow('From', '${data['from_wallet'] ?? '—'}'),
                _detailRow('To', '${data['to_wallet'] ?? '—'}'),
                _detailRow('Sent', '\$${data['sent_amount'] ?? data['amount'] ?? '—'}'),
                _detailRow('Fee', '-\$${data['fee'] ?? '—'}'),
                _detailRow('Received', '\$${data['converted_amount'] ?? '—'}'),
                _detailRow('Phone', '${data['recipient_phone'] ?? _phoneCtrl.text}'),
              ]),
            ),
            const SizedBox(height: 20),
            SizedBox(
              width: double.infinity,
              child: AppButton(
                label: 'Done',
                onPressed: () { Navigator.of(context).pop(); context.pop(); },
              ),
            ),
          ]),
        ),
      ),
    );
  }

  Widget _detailRow(String label, String value) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: const TextStyle(color: AppColors.textGrey, fontSize: 12)),
      Text(value, style: const TextStyle(color: AppColors.secondary, fontWeight: FontWeight.w700, fontSize: 12)),
    ]),
  );
}
