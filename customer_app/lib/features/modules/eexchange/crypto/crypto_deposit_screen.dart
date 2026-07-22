import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:fluttertoast/fluttertoast.dart';
import 'crypto_theme.dart';
import 'crypto_models.dart';
import 'crypto_providers.dart';
import 'crypto_widgets.dart';

class CryptoDepositScreen extends ConsumerStatefulWidget {
  const CryptoDepositScreen({super.key, required this.coin});
  final CryptoCoin coin;

  @override
  ConsumerState<CryptoDepositScreen> createState() => _CryptoDepositScreenState();
}

class _CryptoDepositScreenState extends ConsumerState<CryptoDepositScreen> {
  CryptoCoin? _selectedCoin;

  @override
  void initState() {
    super.initState();
    _selectedCoin = widget.coin;
  }

  @override
  Widget build(BuildContext context) {
    final coin = _selectedCoin ?? widget.coin;
    final marketsAsync = ref.watch(cryptoMarketsProvider);
    final addressAsync = ref.watch(depositAddressProvider(coin.symbol));

    return Scaffold(
      appBar: AppBar(
        title: Text('Deposit ${coin.symbol}'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            // Coin selector
            Align(
              alignment: Alignment.centerLeft,
              child: Text('Select Coin', style: TextStyle(color: cMt(context), fontSize: 12)),
            ),
            const SizedBox(height: 6),
            marketsAsync.when(
              loading: () => const SizedBox.shrink(),
              error: (_, __) => const SizedBox.shrink(),
              data: (coins) => Container(
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
                decoration: BoxDecoration(
                  color: cCard(context),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: cBd(context)),
                ),
                child: DropdownButton<CryptoCoin>(
                  value: coins.any((c) => c.symbol == coin.symbol) ? coin : null,
                  isExpanded: true,
                  dropdownColor: cCard(context),
                  underline: const SizedBox.shrink(),
                  items: coins.map((c) => DropdownMenuItem(
                    value: c,
                    child: Row(
                      children: [
                        CoinAvatarWidget(symbol: c.symbol, logoUrl: c.logoUrl, size: 24),
                        const SizedBox(width: 8),
                        Text('${c.name} (${c.symbol})',
                            style: TextStyle(color: cTx(context), fontSize: 13)),
                      ],
                    ),
                  )).toList(),
                  onChanged: (c) { if (c != null) setState(() => _selectedCoin = c); },
                ),
              ),
            ),
            const SizedBox(height: 28),

            // Coin icon
            CoinAvatarWidget(symbol: coin.symbol, logoUrl: coin.logoUrl, size: 64),
            const SizedBox(height: 8),
            Text(coin.name,
                style: TextStyle(color: cTx(context), fontSize: 18, fontWeight: FontWeight.w700)),
            Text(coin.symbol, style: TextStyle(color: cMt(context), fontSize: 13)),
            const SizedBox(height: 28),

            // QR Code + address
            addressAsync.when(
              loading: () => const CircularProgressIndicator(color: kCryptoPrimary),
              error: (e, _) => Column(
                children: [
                  const Icon(Icons.error_outline, color: kCryptoRed, size: 40),
                  const SizedBox(height: 8),
                  Text(e.toString(),
                      style: TextStyle(color: cMt(context), fontSize: 12),
                      textAlign: TextAlign.center),
                ],
              ),
              data: (address) => _AddressDisplay(symbol: coin.symbol, address: address),
            ),

            const SizedBox(height: 24),

            // Warnings
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: kCryptoGold.withAlpha(20),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: kCryptoGold.withAlpha(60)),
              ),
              child: Column(
                children: [
                  const Row(
                    children: [
                      Icon(Icons.warning_amber_rounded, color: kCryptoGold, size: 16),
                      SizedBox(width: 6),
                      Text('Important', style: TextStyle(color: kCryptoGold, fontWeight: FontWeight.w700, fontSize: 13)),
                    ],
                  ),
                  const SizedBox(height: 8),
                  _warning(context, 'Only send ${coin.symbol} to this address. Sending other coins will result in permanent loss.'),
                  _warning(context, 'Minimum deposit: 0.0001 ${coin.symbol}'),
                  _warning(context, 'Deposits require network confirmation before being credited.'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _warning(BuildContext context, String text) => Padding(
    padding: const EdgeInsets.only(top: 4),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('• ', style: TextStyle(color: kCryptoGold, fontSize: 11)),
        Expanded(child: Text(text, style: TextStyle(color: cMt(context), fontSize: 11))),
      ],
    ),
  );
}

class _AddressDisplay extends StatelessWidget {
  const _AddressDisplay({required this.symbol, required this.address});
  final String symbol, address;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        // QR Code — always white background for scanner compatibility
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(16),
          ),
          child: QrImageView(
            data: address,
            version: QrVersions.auto,
            size: 180,
            backgroundColor: Colors.white,
          ),
        ),
        const SizedBox(height: 20),

        // Address box
        Text('Deposit Address', style: TextStyle(color: cMt(context), fontSize: 12)),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          decoration: BoxDecoration(
            color: cCard(context),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: cBd(context)),
          ),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  address,
                  style: TextStyle(color: cTx(context), fontSize: 12, letterSpacing: 0.3),
                ),
              ),
              const SizedBox(width: 8),
              GestureDetector(
                onTap: () {
                  Clipboard.setData(ClipboardData(text: address));
                  Fluttertoast.showToast(msg: 'Address copied!');
                },
                child: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: kCryptoPrimary.withAlpha(30),
                    borderRadius: BorderRadius.circular(6),
                  ),
                  child: const Icon(Icons.copy, color: kCryptoPrimary, size: 16),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),

        // Share button
        SizedBox(
          width: double.infinity,
          height: 46,
          child: OutlinedButton.icon(
            icon: const Icon(Icons.share_outlined, size: 16),
            label: const Text('Share Address'),
            style: OutlinedButton.styleFrom(
              foregroundColor: kCryptoPrimary,
              side: const BorderSide(color: kCryptoPrimary),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            ),
            onPressed: () {
              Clipboard.setData(ClipboardData(text: address));
              Fluttertoast.showToast(msg: 'Address copied to clipboard');
            },
          ),
        ),
      ],
    );
  }
}
