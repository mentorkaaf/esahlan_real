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
  // Wallets
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
  final  _amountCtrl = TextEditingController();

  @override
  void dispose() { _amountCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final ratesAsync = ref.watch(_exchangeRatesProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white, elevation: 0,
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: AppColors.secondary), onPressed: () => context.pop()),
        title: const Text('eExchange', style: TextStyle(fontWeight: FontWeight.w800, color: AppColors.secondary, fontFamily: 'Cairo')),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(children: [

          // ── Hero balance card ──────────────────────────────────────
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
              const Text('Exchange between wallets instantly', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(20)),
                child: const Text('1% Service Fee Applied', style: TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w700)),
              ),
            ]),
          ),
          const SizedBox(height: 20),

          // ── FROM wallet ────────────────────────────────────────────
          const Align(alignment: Alignment.centerLeft,
              child: Text('From Wallet', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary))),
          const SizedBox(height: 10),
          Row(children: _wallets.map((w) {
            final sel = _fromWallet == w['id'];
            final color = Color(w['color'] as int);
            return Expanded(child: GestureDetector(
              onTap: () => setState(() { _fromWallet = w['id'] as String; _preview = null; }),
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
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: sel ? color : AppColors.textGrey)),
                ]),
              ),
            ));
          }).toList()),
          const SizedBox(height: 16),

          // ── Swap arrow ─────────────────────────────────────────────
          Center(child: GestureDetector(
            onTap: () => setState(() { final tmp = _fromWallet; _fromWallet = _toWallet; _toWallet = tmp; _preview = null; }),
            child: Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: AppColors.primary, shape: BoxShape.circle,
                  boxShadow: [BoxShadow(color: AppColors.primary.withValues(alpha: 0.3), blurRadius: 10)]),
              child: const Icon(Icons.swap_vert_rounded, color: Colors.white, size: 24),
            ),
          )),
          const SizedBox(height: 16),

          // ── TO wallet ──────────────────────────────────────────────
          const Align(alignment: Alignment.centerLeft,
              child: Text('To Wallet', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary))),
          const SizedBox(height: 10),
          Row(children: _wallets.map((w) {
            final sel = _toWallet == w['id'];
            final color = Color(w['color'] as int);
            return Expanded(child: GestureDetector(
              onTap: () => setState(() { _toWallet = w['id'] as String; _preview = null; }),
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
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: sel ? color : AppColors.textGrey)),
                ]),
              ),
            ));
          }).toList()),
          const SizedBox(height: 20),

          // ── Amount ─────────────────────────────────────────────────
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14),
                border: Border.all(color: AppColors.divider)),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Amount', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.textGrey)),
              const SizedBox(height: 8),
              TextField(
                controller: _amountCtrl,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 28, color: AppColors.secondary),
                onChanged: (v) => setState(() { _amount = double.tryParse(v) ?? 0; _preview = null; }),
                decoration: const InputDecoration(
                  hintText: '0.00', hintStyle: TextStyle(fontWeight: FontWeight.w300, fontSize: 28, color: AppColors.divider),
                  border: InputBorder.none, prefixText: '\$  ',
                  prefixStyle: TextStyle(fontWeight: FontWeight.w900, fontSize: 28, color: AppColors.secondary),
                ),
              ),
              // Quick amounts
              Wrap(spacing: 8, children: [10, 25, 50, 100, 200].map((v) => GestureDetector(
                onTap: () { _amountCtrl.text = v.toString(); setState(() { _amount = v.toDouble(); _preview = null; }); },
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(8),
                      border: Border.all(color: AppColors.divider)),
                  child: Text('\$$v', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: AppColors.secondary)),
                ),
              )).toList()),
            ]),
          ),
          const SizedBox(height: 16),

          // ── Exchange Rates (info) ──────────────────────────────────
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
                  const Text('Live Rates', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.secondary)),
                  const SizedBox(height: 8),
                  ...rates.take(4).map((r) => Padding(
                    padding: const EdgeInsets.symmetric(vertical: 3),
                    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                      Text('${r['from_wallet']?.toString().toUpperCase()} → ${r['to_wallet']?.toString().toUpperCase()}',
                          style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
                      Text('Rate: ${r['rate']}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.secondary)),
                    ]),
                  )),
                ]),
              );
            },
          ),
          const SizedBox(height: 16),

          // ── Preview breakdown ─────────────────────────────────────
          if (_preview != null) ...[
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                gradient: const LinearGradient(colors: [AppColors.secondary, Color(0xFF1A1060)]),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Column(children: [
                _row('From',     '${(_preview!['from_wallet'] ?? _preview!['from'])?.toString().toUpperCase()}', white: true),
                _row('To',       '${(_preview!['to_wallet']   ?? _preview!['to'])?.toString().toUpperCase()}',   white: true),
                _row('You send', '\$${_preview!['amount']}',              white: true),
                _row('Rate',     '${_preview!['rate']}',                  white: true),
                _row('Fee (1%)', '-\$${_preview!['fee']}',               white: true),
                const Divider(color: Colors.white24, height: 16),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  const Text('You receive', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
                  Text('\$${_preview!['you_receive'] ?? _preview!['converted']}',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 28)),
                ]),
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
              onPressed: (_amount <= 0 || _fromWallet == _toWallet) ? null : _previewExchange,
            ),

          if (_fromWallet == _toWallet)
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text('Please select different wallets', style: TextStyle(color: AppColors.error, fontSize: 13), textAlign: TextAlign.center),
            ),

          const SizedBox(height: 40),
        ]),
      ),
    );
  }

  Widget _row(String label, String value, {bool white = false}) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(color: white ? Colors.white70 : AppColors.textGrey, fontSize: 13)),
      Text(value,  style: TextStyle(color: white ? Colors.white  : AppColors.secondary, fontWeight: FontWeight.w700, fontSize: 13)),
    ]),
  );

  Future<void> _previewExchange() async {
    setState(() => _converting = true);
    try {
      final res = await _svc.previewExchange({'from_wallet': _fromWallet, 'to_wallet': _toWallet, 'amount': _amount});
      setState(() => _preview = Map<String, dynamic>.from(res['data']));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.error));
    } finally {
      setState(() => _converting = false);
    }
  }

  Future<void> _confirm() async {
    setState(() => _confirming = true);
    try {
      await _svc.confirmExchange({'from_wallet': _fromWallet, 'to_wallet': _toWallet, 'amount': _amount});
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Exchange completed successfully!'), backgroundColor: AppColors.success));
        context.pop();
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.error));
    } finally {
      setState(() => _confirming = false);
    }
  }
}
