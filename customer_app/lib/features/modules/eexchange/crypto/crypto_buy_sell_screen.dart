import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:fluttertoast/fluttertoast.dart';
import 'crypto_theme.dart';
import 'crypto_models.dart';
import 'crypto_providers.dart';
import 'crypto_widgets.dart';

class CryptoBuySellScreen extends ConsumerStatefulWidget {
  const CryptoBuySellScreen({super.key, this.initialCoin});
  final CryptoCoin? initialCoin;

  @override
  ConsumerState<CryptoBuySellScreen> createState() => _CryptoBuySellScreenState();
}

class _CryptoBuySellScreenState extends ConsumerState<CryptoBuySellScreen> {
  bool _isBuy = true;
  CryptoCoin? _selectedCoin;
  String _payMethod = 'epay';
  final _amountCtrl = TextEditingController();
  bool _loading = false;

  double get _rate => _selectedCoin?.priceUsd ?? 0;
  double get _inputAmt => double.tryParse(_amountCtrl.text) ?? 0;
  double get _spread => _rate * 0.01; // 1% spread
  double get _fee   => _inputAmt * 0.005; // 0.5%

  double get _youReceive {
    if (_isBuy) {
      return _rate > 0 ? (_inputAmt / (_rate + _spread)) : 0;
    } else {
      final gross = _inputAmt * (_rate - _spread);
      return gross - (gross * 0.005);
    }
  }

  @override
  void dispose() {
    _amountCtrl.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_selectedCoin == null) {
      Fluttertoast.showToast(msg: 'Select a coin first');
      return;
    }
    if (_inputAmt <= 0) {
      Fluttertoast.showToast(msg: 'Enter amount');
      return;
    }
    setState(() => _loading = true);
    try {
      final repo = ref.read(cryptoRepositoryProvider);
      if (_isBuy) {
        await repo.buyCoin(
          symbol: _selectedCoin!.symbol,
          amountUsd: _inputAmt,
          paymentMethod: _payMethod,
        );
        Fluttertoast.showToast(msg: 'Buy order placed!');
      } else {
        await repo.sellCoin(
          symbol: _selectedCoin!.symbol,
          amount: _inputAmt,
          receiveMethod: _payMethod,
        );
        Fluttertoast.showToast(msg: 'Sell order placed!');
      }
      _amountCtrl.clear();
      ref.invalidate(cryptoPortfolioProvider);
      ref.invalidate(cryptoWalletProvider);
    } catch (e) {
      Fluttertoast.showToast(msg: e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final marketsAsync = ref.watch(cryptoMarketsProvider);

    if (_selectedCoin == null && widget.initialCoin != null) {
      _selectedCoin = widget.initialCoin;
    }
    if (_selectedCoin == null) {
      marketsAsync.whenData((coins) {
        if (coins.isNotEmpty && _selectedCoin == null) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            setState(() => _selectedCoin = coins.first);
          });
        }
      });
    }

    final isStandalone = widget.initialCoin != null ||
        ModalRoute.of(context)?.settings.name != null;

    return Scaffold(
      backgroundColor: kCryptoBg,
      appBar: isStandalone
          ? AppBar(title: const Text('Buy / Sell'))
          : null,
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (!isStandalone)
              const Padding(
                padding: EdgeInsets.only(bottom: 16, top: 8),
                child: Text('Buy / Sell',
                    style: TextStyle(color: kCryptoText, fontSize: 18, fontWeight: FontWeight.w800)),
              ),

            // Buy / Sell toggle
            Container(
              decoration: BoxDecoration(
                color: kCryptoCard,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: kCryptoBorder),
              ),
              child: Row(
                children: [
                  _ToggleBtn(label: 'Buy',  active: _isBuy,  color: kCryptoGreen,
                      onTap: () => setState(() => _isBuy = true)),
                  _ToggleBtn(label: 'Sell', active: !_isBuy, color: kCryptoRed,
                      onTap: () => setState(() => _isBuy = false)),
                ],
              ),
            ),
            const SizedBox(height: 20),

            // Coin selector
            const Text('Select Coin', style: TextStyle(color: kCryptoMuted, fontSize: 12)),
            const SizedBox(height: 6),
            marketsAsync.when(
              loading: () => const Center(child: CircularProgressIndicator(color: kCryptoPrimary)),
              error: (_, __) => const Text('Failed to load coins', style: TextStyle(color: kCryptoRed)),
              data: (coins) => _CoinDropdown(
                coins: coins,
                selected: _selectedCoin,
                onChanged: (c) => setState(() => _selectedCoin = c),
              ),
            ),
            const SizedBox(height: 16),

            // Amount input
            const Text('Amount (USD)', style: TextStyle(color: kCryptoMuted, fontSize: 12)),
            const SizedBox(height: 6),
            TextField(
              controller: _amountCtrl,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              style: const TextStyle(color: kCryptoText, fontSize: 15),
              onChanged: (_) => setState(() {}),
              decoration: InputDecoration(
                hintText: '0.00',
                hintStyle: const TextStyle(color: kCryptoMuted),
                prefixText: '\$ ',
                prefixStyle: const TextStyle(color: kCryptoMuted),
                suffixText: 'USD',
                suffixStyle: const TextStyle(color: kCryptoPrimary, fontWeight: FontWeight.w600),
                filled: true,
                fillColor: kCryptoCard,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: kCryptoBorder),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: kCryptoBorder),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(10),
                  borderSide: const BorderSide(color: kCryptoPrimary),
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
              ),
            ),
            const SizedBox(height: 8),

            // Quick amounts
            Wrap(
              spacing: 8,
              children: [50, 100, 200, 500].map((v) => GestureDetector(
                onTap: () => setState(() => _amountCtrl.text = v.toString()),
                child: Chip(
                  label: Text('\$$v', style: const TextStyle(color: kCryptoText, fontSize: 11)),
                  backgroundColor: kCryptoCard,
                  side: const BorderSide(color: kCryptoBorder),
                  padding: EdgeInsets.zero,
                  materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                ),
              )).toList(),
            ),
            const SizedBox(height: 16),

            // Live calculation card
            if (_selectedCoin != null && _inputAmt > 0)
              _LiveCalc(
                isBuy: _isBuy,
                coin: _selectedCoin!,
                inputAmt: _inputAmt,
                youReceive: _youReceive,
                fee: _fee,
                spread: _spread,
              ),
            const SizedBox(height: 16),

            // Payment method
            const Text('Payment Method', style: TextStyle(color: kCryptoMuted, fontSize: 12)),
            const SizedBox(height: 8),
            _PayMethodSelector(
              selected: _payMethod,
              isBuy: _isBuy,
              onChanged: (v) => setState(() => _payMethod = v),
            ),
            const SizedBox(height: 24),

            CryptoPrimaryButton(
              label: _isBuy ? 'Buy ${_selectedCoin?.symbol ?? ''}' : 'Sell ${_selectedCoin?.symbol ?? ''}',
              onPressed: _loading ? null : _submit,
              isLoading: _loading,
              color: _isBuy ? kCryptoGreen : kCryptoRed,
            ),
          ],
        ),
      ),
    );
  }
}

// ── Coin dropdown ─────────────────────────────────────────────────────────────

class _CoinDropdown extends StatelessWidget {
  const _CoinDropdown({required this.coins, required this.selected, required this.onChanged});
  final List<CryptoCoin> coins;
  final CryptoCoin? selected;
  final ValueChanged<CryptoCoin> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
      decoration: BoxDecoration(
        color: kCryptoCard,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: kCryptoBorder),
      ),
      child: DropdownButton<CryptoCoin>(
        value: selected,
        isExpanded: true,
        dropdownColor: kCryptoCard,
        underline: const SizedBox.shrink(),
        hint: const Text('Select a coin', style: TextStyle(color: kCryptoMuted)),
        items: coins.map((c) => DropdownMenuItem(
          value: c,
          child: Row(
            children: [
              CoinAvatarWidget(symbol: c.symbol, logoUrl: c.logoUrl, size: 28),
              const SizedBox(width: 10),
              Expanded(child: Text('${c.name} (${c.symbol})',
                  style: const TextStyle(color: kCryptoText, fontSize: 13))),
              Text(cryptoCoinPrice(c.priceUsd),
                  style: const TextStyle(color: kCryptoMuted, fontSize: 12)),
            ],
          ),
        )).toList(),
        onChanged: (c) { if (c != null) onChanged(c); },
      ),
    );
  }
}

// ── Live calc ─────────────────────────────────────────────────────────────────

class _LiveCalc extends StatelessWidget {
  const _LiveCalc({
    required this.isBuy, required this.coin, required this.inputAmt,
    required this.youReceive, required this.fee, required this.spread,
  });
  final bool isBuy;
  final CryptoCoin coin;
  final double inputAmt, youReceive, fee, spread;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: kCryptoCard,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: kCryptoBorder),
      ),
      child: Column(
        children: [
          _Row(label: 'Market Price', value: cryptoCoinPrice(coin.priceUsd)),
          _Row(label: 'Spread (1%)',  value: cryptoCoinPrice(spread)),
          _Row(label: 'Fee (0.5%)',   value: '\$${fee.toStringAsFixed(4)}'),
          const Divider(color: kCryptoBorder),
          _Row(
            label: 'You will receive',
            value: isBuy
                ? '${youReceive.toStringAsFixed(6)} ${coin.symbol}'
                : '\$${youReceive.toStringAsFixed(2)} USD',
            valueColor: kCryptoPrimary,
            bold: true,
          ),
        ],
      ),
    );
  }
}

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value, this.valueColor, this.bold = false});
  final String label, value;
  final Color? valueColor;
  final bool bold;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: kCryptoMuted, fontSize: 12)),
        Text(value, style: TextStyle(
          color: valueColor ?? kCryptoText,
          fontSize: 12,
          fontWeight: bold ? FontWeight.w700 : FontWeight.normal,
        )),
      ],
    ),
  );
}

// ── Toggle btn ────────────────────────────────────────────────────────────────

class _ToggleBtn extends StatelessWidget {
  const _ToggleBtn({required this.label, required this.active, required this.color, required this.onTap});
  final String label;
  final bool active;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Expanded(
    child: GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        margin: const EdgeInsets.all(4),
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: active ? color.withAlpha(40) : Colors.transparent,
          borderRadius: BorderRadius.circular(8),
          border: active ? Border.all(color: color.withAlpha(120)) : null,
        ),
        child: Text(label,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: active ? color : kCryptoMuted,
            fontWeight: FontWeight.w700,
            fontSize: 14,
          ),
        ),
      ),
    ),
  );
}

// ── Payment method ────────────────────────────────────────────────────────────

class _PayMethodSelector extends StatelessWidget {
  const _PayMethodSelector({required this.selected, required this.isBuy, required this.onChanged});
  final String selected;
  final bool isBuy;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final methods = isBuy
        ? [('epay', 'ePay Wallet', 'Instant >'), ('waafi', 'WaafiPay', 'Instant >')]
        : [('epay', 'ePay Wallet', 'Instant >'), ('bank', 'Bank Transfer', '1-2 days')];

    return Column(
      children: methods.map((m) {
        final isSelected = selected == m.$1;
        return GestureDetector(
          onTap: () => onChanged(m.$1),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 150),
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              color: kCryptoCard,
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: isSelected ? kCryptoPrimary : kCryptoBorder),
            ),
            child: Row(
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 150),
                  width: 18, height: 18,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: isSelected ? kCryptoPrimary : kCryptoMuted, width: 2),
                    color: isSelected ? kCryptoPrimary : Colors.transparent,
                  ),
                  child: isSelected ? const Icon(Icons.check, size: 11, color: Colors.white) : null,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(m.$2,
                      style: TextStyle(
                        color: isSelected ? kCryptoText : kCryptoMuted,
                        fontSize: 13,
                        fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
                      )),
                ),
                Text(m.$3, style: const TextStyle(color: kCryptoPrimary, fontSize: 12, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        );
      }).toList(),
    );
  }
}
