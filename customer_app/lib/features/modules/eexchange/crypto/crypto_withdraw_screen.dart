import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:fluttertoast/fluttertoast.dart';
import 'crypto_theme.dart';
import 'crypto_models.dart';
import 'crypto_providers.dart';
import 'crypto_widgets.dart';

class CryptoWithdrawScreen extends ConsumerStatefulWidget {
  const CryptoWithdrawScreen({super.key, this.initialCoin});
  final CryptoCoin? initialCoin;

  @override
  ConsumerState<CryptoWithdrawScreen> createState() => _CryptoWithdrawScreenState();
}

class _CryptoWithdrawScreenState extends ConsumerState<CryptoWithdrawScreen> {
  CryptoCoin? _coin;
  CoinNetwork? _network;
  final _amtCtrl  = TextEditingController();
  final _addrCtrl = TextEditingController();
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _coin = widget.initialCoin;
    if (_coin != null) {
      _network = _coin!.networks.where((n) => n.isActive).firstOrNull ?? _coin!.networks.firstOrNull;
    }
  }

  @override
  void dispose() {
    _amtCtrl.dispose();
    _addrCtrl.dispose();
    super.dispose();
  }

  double get _amount => double.tryParse(_amtCtrl.text) ?? 0;
  double get _fee    => _coin?.withdrawalFee ?? 0;
  double get _youReceive => (_amount - _fee).clamp(0, double.infinity);

  Future<void> _submit() async {
    if (_coin == null)                { Fluttertoast.showToast(msg: 'Select a coin'); return; }
    if (_network == null)             { Fluttertoast.showToast(msg: 'Select a network'); return; }
    if (_amount <= 0)                 { Fluttertoast.showToast(msg: 'Enter amount'); return; }
    if (_addrCtrl.text.trim().isEmpty){ Fluttertoast.showToast(msg: 'Enter destination address'); return; }
    if (_amount < (_coin!.minWithdrawal)) {
      Fluttertoast.showToast(msg: 'Minimum withdrawal: ${_coin!.minWithdrawal} ${_coin!.symbol}');
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        backgroundColor: Theme.of(context).cardColor,
        title: const Text('Confirm Withdrawal'),
        content: Text(
          'Send ${_amount.toStringAsFixed(6)} ${_coin!.symbol}\n'
          'Fee: $_fee ${_coin!.symbol}\n'
          'You receive: ${_youReceive.toStringAsFixed(6)} ${_coin!.symbol}\n'
          'To: ${_addrCtrl.text.trim()}',
          style: TextStyle(color: cMt(context), fontSize: 13),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm', style: TextStyle(color: kCryptoRed)),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _loading = true);
    try {
      final repo = ref.read(cryptoRepositoryProvider);
      await repo.withdraw(
        symbol: _coin!.symbol,
        networkId: _network!.id,
        amount: _amount,
        toAddress: _addrCtrl.text.trim(),
      );
      Fluttertoast.showToast(msg: '✅ Withdrawal submitted! Pending review.');
      if (mounted) Navigator.of(context).pop();
    } catch (e) {
      Fluttertoast.showToast(msg: e.toString().replaceAll('Exception: ', ''), toastLength: Toast.LENGTH_LONG);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final marketsAsync = ref.watch(cryptoMarketsProvider);

    return Scaffold(
      appBar: AppBar(title: const Text('Withdraw')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Coin selector
            Text('Coin', style: TextStyle(color: cMt(context), fontSize: 12)),
            const SizedBox(height: 6),
            marketsAsync.when(
              loading: () => const Center(child: CircularProgressIndicator(color: kCryptoPrimary)),
              error: (_, __) => Text('Error', style: TextStyle(color: kCryptoRed)),
              data: (coins) {
                if (_coin == null && coins.isNotEmpty) {
                  WidgetsBinding.instance.addPostFrameCallback((_) {
                    setState(() {
                      _coin    = coins.first;
                      _network = _coin!.networks.where((n) => n.isActive).firstOrNull;
                    });
                  });
                }
                return _Dropdown<CryptoCoin>(
                  items: coins,
                  selected: _coin,
                  label: (c) => '${c.name} (${c.symbol})',
                  leading: (c) => CoinAvatarWidget(symbol: c.symbol, logoUrl: c.logoUrl, size: 24),
                  onChanged: (c) => setState(() {
                    _coin    = c;
                    _network = c.networks.where((n) => n.isActive).firstOrNull ?? c.networks.firstOrNull;
                  }),
                );
              },
            ),
            const SizedBox(height: 12),

            // Network
            if (_coin != null && _coin!.networks.isNotEmpty) ...[
              Text('Network', style: TextStyle(color: cMt(context), fontSize: 12)),
              const SizedBox(height: 6),
              _Dropdown<CoinNetwork>(
                items: _coin!.networks.where((n) => n.isActive).toList(),
                selected: _network,
                label: (n) => n.label,
                onChanged: (n) => setState(() => _network = n),
              ),
              const SizedBox(height: 12),
            ],

            // Destination address
            Text('Destination Address', style: TextStyle(color: cMt(context), fontSize: 12)),
            const SizedBox(height: 6),
            TextField(
              controller: _addrCtrl,
              style: TextStyle(color: cTx(context), fontSize: 13, fontFamily: 'monospace'),
              decoration: InputDecoration(
                hintText: 'Paste wallet address here',
                hintStyle: TextStyle(color: cMt(context)),
                filled: true,
                fillColor: cCard(context),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: cBd(context)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: cBd(context)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: kCryptoPrimary),
                ),
                suffixIcon: IconButton(
                  icon: const Icon(Icons.paste_rounded, color: kCryptoPrimary, size: 18),
                  onPressed: () async {
                    final data = await Clipboard.getData('text/plain');
                    if (data?.text != null) _addrCtrl.text = data!.text!;
                  },
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              ),
            ),
            const SizedBox(height: 12),

            // Amount
            Text('Amount (${_coin?.symbol ?? ''})', style: TextStyle(color: cMt(context), fontSize: 12)),
            const SizedBox(height: 6),
            TextField(
              controller: _amtCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              style: TextStyle(color: cTx(context), fontSize: 15),
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                hintText: '0.000000',
                hintStyle: TextStyle(color: cMt(context)),
                suffixText: _coin?.symbol ?? '',
                suffixStyle: const TextStyle(color: kCryptoPrimary, fontWeight: FontWeight.w600),
                filled: true,
                fillColor: cCard(context),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: cBd(context)),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: BorderSide(color: cBd(context)),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: kCryptoPrimary),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              ),
            ),
            const SizedBox(height: 8),

            // Min withdrawal hint
            if (_coin != null)
              Text('Min: ${_coin!.minWithdrawal} ${_coin!.symbol}',
                  style: TextStyle(color: cMt(context), fontSize: 11)),
            const SizedBox(height: 16),

            // Fee summary
            if (_coin != null && _amount > 0) ...[
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: cCard(context),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: cBd(context)),
                ),
                child: Column(
                  children: [
                    _SumRow('Amount', '${_amount.toStringAsFixed(6)} ${_coin!.symbol}'),
                    _SumRow('Network Fee', '${_fee.toStringAsFixed(6)} ${_coin!.symbol}'),
                    Divider(color: cBd(context)),
                    _SumRow('You receive', '${_youReceive.toStringAsFixed(6)} ${_coin!.symbol}',
                        bold: true, valueColor: kCryptoPrimary),
                  ],
                ),
              ),
              const SizedBox(height: 16),
            ],

            CryptoPrimaryButton(
              label: 'Withdraw ${_coin?.symbol ?? ''}',
              onPressed: _loading ? null : _submit,
              isLoading: _loading,
              color: kCryptoRed,
            ),
          ],
        ),
      ),
    );
  }
}

class _SumRow extends StatelessWidget {
  const _SumRow(this.label, this.value, {this.bold = false, this.valueColor});
  final String label, value;
  final bool bold;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: TextStyle(color: cMt(context), fontSize: 12)),
        Text(value, style: TextStyle(
          color: valueColor ?? cTx(context),
          fontSize: 12,
          fontWeight: bold ? FontWeight.w700 : FontWeight.normal,
        )),
      ],
    ),
  );
}

class _Dropdown<T> extends StatelessWidget {
  const _Dropdown({required this.items, required this.selected, required this.label,
      required this.onChanged, this.leading});
  final List<T> items;
  final T? selected;
  final String Function(T) label;
  final Widget Function(T)? leading;
  final ValueChanged<T> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      decoration: BoxDecoration(
        color: cCard(context),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: cBd(context)),
      ),
      child: DropdownButton<T>(
        value: selected,
        isExpanded: true,
        dropdownColor: cCard(context),
        underline: const SizedBox.shrink(),
        hint: Text('Select', style: TextStyle(color: cMt(context))),
        items: items.map((item) => DropdownMenuItem<T>(
          value: item,
          child: Row(
            children: [
              if (leading != null) ...[leading!(item), const SizedBox(width: 8)],
              Expanded(child: Text(label(item), style: TextStyle(color: cTx(context), fontSize: 13))),
            ],
          ),
        )).toList(),
        onChanged: (v) { if (v != null) onChanged(v); },
      ),
    );
  }
}
