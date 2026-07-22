import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:fluttertoast/fluttertoast.dart';
import 'crypto_theme.dart';
import 'crypto_models.dart';
import 'crypto_providers.dart';
import 'crypto_widgets.dart';

class CryptoTransferScreen extends ConsumerStatefulWidget {
  const CryptoTransferScreen({super.key, this.initialCoin});
  final CryptoCoin? initialCoin;

  @override
  ConsumerState<CryptoTransferScreen> createState() => _CryptoTransferScreenState();
}

class _CryptoTransferScreenState extends ConsumerState<CryptoTransferScreen> {
  CryptoCoin? _coin;
  CoinNetwork? _network;
  final _phoneCtrl = TextEditingController();
  final _amtCtrl   = TextEditingController();
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
    _phoneCtrl.dispose();
    _amtCtrl.dispose();
    super.dispose();
  }

  double get _amount => double.tryParse(_amtCtrl.text) ?? 0;

  Future<void> _submit() async {
    if (_coin == null)                  { Fluttertoast.showToast(msg: 'Select a coin'); return; }
    if (_network == null)               { Fluttertoast.showToast(msg: 'Select a network'); return; }
    if (_phoneCtrl.text.trim().isEmpty) { Fluttertoast.showToast(msg: 'Enter recipient phone number'); return; }
    if (_amount <= 0)                   { Fluttertoast.showToast(msg: 'Enter amount'); return; }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (_) => AlertDialog(
        backgroundColor: Theme.of(context).cardColor,
        title: const Text('Confirm Transfer'),
        content: Text(
          'Transfer ${_amount.toStringAsFixed(6)} ${_coin!.symbol}\n'
          'To: ${_phoneCtrl.text.trim()}',
          style: TextStyle(color: cMt(context), fontSize: 13),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Confirm', style: TextStyle(color: kCryptoPrimary)),
          ),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _loading = true);
    try {
      final repo = ref.read(cryptoRepositoryProvider);
      await repo.transfer(
        symbol: _coin!.symbol,
        networkId: _network!.id,
        toPhone: _phoneCtrl.text.trim(),
        amount: _amount,
      );
      Fluttertoast.showToast(msg: '✅ Transfer completed!');
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
      appBar: AppBar(title: const Text('Transfer')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Coin
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

            // Recipient phone
            Text('Recipient Phone (eSahlan user)', style: TextStyle(color: cMt(context), fontSize: 12)),
            const SizedBox(height: 6),
            TextField(
              controller: _phoneCtrl,
              keyboardType: TextInputType.phone,
              style: TextStyle(color: cTx(context), fontSize: 14),
              decoration: InputDecoration(
                hintText: '+252 61 234 5678',
                hintStyle: TextStyle(color: cMt(context)),
                prefixIcon: Icon(Icons.phone_outlined, color: cMt(context), size: 18),
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
            const SizedBox(height: 24),

            CryptoPrimaryButton(
              label: 'Transfer ${_coin?.symbol ?? ''}',
              onPressed: _loading ? null : _submit,
              isLoading: _loading,
              color: kCryptoPrimary,
            ),
          ],
        ),
      ),
    );
  }
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
