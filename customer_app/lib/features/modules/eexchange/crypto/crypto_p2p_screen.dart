import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:fluttertoast/fluttertoast.dart';
import 'crypto_theme.dart';
import 'crypto_models.dart';
import 'crypto_providers.dart';
import 'crypto_widgets.dart';

class CryptoP2PScreen extends ConsumerStatefulWidget {
  const CryptoP2PScreen({super.key});

  @override
  ConsumerState<CryptoP2PScreen> createState() => _CryptoP2PScreenState();
}

class _CryptoP2PScreenState extends ConsumerState<CryptoP2PScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabCtrl;
  String _coinFilter = 'All';
  String _payFilter  = 'All';

  @override
  void initState() {
    super.initState();
    _tabCtrl = TabController(length: 4, vsync: this);
  }

  @override
  void dispose() {
    _tabCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('P2P Trading'),
        actions: [
          TextButton.icon(
            icon: const Icon(Icons.add, color: kCryptoPrimary, size: 16),
            label: const Text('Post Ad', style: TextStyle(color: kCryptoPrimary, fontSize: 12)),
            onPressed: () => _showPostAdSheet(context),
          ),
        ],
        bottom: TabBar(
          controller: _tabCtrl,
          tabs: const [
            Tab(text: 'Buy'),
            Tab(text: 'Sell'),
            Tab(text: 'My Ads'),
            Tab(text: 'Orders'),
          ],
          indicatorColor: kCryptoPrimary,
          labelColor: kCryptoPrimary,
          unselectedLabelColor: null,
          labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
        ),
      ),
      body: Column(
        children: [
          // Filters
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 4),
            child: Row(
              children: [
                Expanded(child: _FilterDropdown(
                  label: 'Coin',
                  value: _coinFilter,
                  items: const ['All', 'USDT', 'BTC', 'ETH', 'BNB'],
                  onChanged: (v) => setState(() => _coinFilter = v),
                )),
                const SizedBox(width: 10),
                Expanded(child: _FilterDropdown(
                  label: 'Payment',
                  value: _payFilter,
                  items: const ['All', 'ePay', 'WaafiPay', 'Bank'],
                  onChanged: (v) => setState(() => _payFilter = v),
                )),
              ],
            ),
          ),
          Expanded(
            child: TabBarView(
              controller: _tabCtrl,
              children: [
                _P2PAdList(type: 'sell', coinFilter: _coinFilter, payFilter: _payFilter),
                _P2PAdList(type: 'buy',  coinFilter: _coinFilter, payFilter: _payFilter),
                const _MyAdsList(),
                const _MyOrdersList(),
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _showPostAdSheet(BuildContext context) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Theme.of(context).cardColor,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      isScrollControlled: true,
      builder: (_) => const _PostAdSheet(),
    );
  }
}

// ── P2P Ad List ───────────────────────────────────────────────────────────────

class _P2PAdList extends ConsumerWidget {
  const _P2PAdList({required this.type, required this.coinFilter, required this.payFilter});
  final String type, coinFilter, payFilter;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final adsAsync = ref.watch(p2pAdsProvider(type));

    return adsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kCryptoPrimary)),
      error: (e, _) => Center(child: Text(e.toString(), style: TextStyle(color: cMt(context)))),
      data: (ads) {
        final filtered = ads.where((a) {
          final coinMatch = coinFilter == 'All' || a.coinSymbol == coinFilter;
          final payMatch  = payFilter  == 'All' ||
              a.paymentMethods.any((p) => p.toLowerCase().contains(payFilter.toLowerCase()));
          return coinMatch && payMatch;
        }).toList();

        if (filtered.isEmpty) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(Icons.storefront_outlined, color: cMt(context), size: 48),
                const SizedBox(height: 12),
                Text('No ${type == 'sell' ? 'sell' : 'buy'} orders available',
                    style: TextStyle(color: cMt(context))),
              ],
            ),
          );
        }

        return RefreshIndicator(
          color: kCryptoPrimary,
          onRefresh: () async => ref.invalidate(p2pAdsProvider(type)),
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
            itemCount: filtered.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (ctx, i) => _TraderCard(ad: filtered[i], isBuyTab: type == 'sell'),
          ),
        );
      },
    );
  }
}

class _TraderCard extends StatelessWidget {
  const _TraderCard({required this.ad, required this.isBuyTab});
  final P2pAd ad;
  final bool isBuyTab;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: cCard(context),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: cBd(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 18,
                backgroundColor: kCryptoPrimary.withAlpha(40),
                child: Text(
                  ad.traderName.isNotEmpty ? ad.traderName[0].toUpperCase() : '?',
                  style: const TextStyle(color: kCryptoPrimary, fontWeight: FontWeight.w700),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(ad.traderName,
                            style: TextStyle(color: cTx(context), fontWeight: FontWeight.w600, fontSize: 13)),
                        if (ad.isVerified) ...[
                          const SizedBox(width: 4),
                          const Icon(Icons.verified, color: kCryptoPrimary, size: 14),
                        ],
                      ],
                    ),
                    Text('${ad.completedOrders} orders  •  ${ad.successRate.toStringAsFixed(0)}% completion',
                        style: TextStyle(color: cMt(context), fontSize: 10)),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(ad.coinSymbol,
                      style: TextStyle(color: cMt(context), fontSize: 10)),
                  Text('\$${ad.price.toStringAsFixed(2)}',
                      style: const TextStyle(color: kCryptoGold, fontWeight: FontWeight.w700, fontSize: 14)),
                ],
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Limit', style: TextStyle(color: cMt(context), fontSize: 10)),
                    Text('\$${ad.minAmount.toStringAsFixed(0)} – \$${ad.maxAmount.toStringAsFixed(0)}',
                        style: TextStyle(color: cTx(context), fontSize: 12)),
                  ],
                ),
              ),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('Available', style: TextStyle(color: cMt(context), fontSize: 10)),
                    Text('${ad.available.toStringAsFixed(4)} ${ad.coinSymbol}',
                        style: TextStyle(color: cTx(context), fontSize: 12)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 6,
            children: ad.paymentMethods.map((m) => Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                color: kCryptoPrimary.withAlpha(20),
                borderRadius: BorderRadius.circular(4),
                border: Border.all(color: kCryptoPrimary.withAlpha(60)),
              ),
              child: Text(m, style: const TextStyle(color: kCryptoPrimary, fontSize: 10)),
            )).toList(),
          ),
          const SizedBox(height: 12),
          _P2PActionButton(ad: ad, isBuyTab: isBuyTab),
        ],
      ),
    );
  }
}

class _P2PActionButton extends ConsumerStatefulWidget {
  const _P2PActionButton({required this.ad, required this.isBuyTab});
  final P2pAd ad;
  final bool isBuyTab;

  @override
  ConsumerState<_P2PActionButton> createState() => _P2PActionButtonState();
}

class _P2PActionButtonState extends ConsumerState<_P2PActionButton> {
  bool _loading = false;

  @override
  Widget build(BuildContext context) {
    final label = widget.isBuyTab ? 'Buy ${widget.ad.coinSymbol}' : 'Sell ${widget.ad.coinSymbol}';
    final color = widget.isBuyTab ? kCryptoGreen : kCryptoRed;

    return SizedBox(
      width: double.infinity,
      height: 38,
      child: ElevatedButton(
        onPressed: _loading ? null : () => _showTradeDialog(context),
        style: ElevatedButton.styleFrom(
          backgroundColor: color.withAlpha(40),
          foregroundColor: color,
          side: BorderSide(color: color.withAlpha(120)),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        child: _loading
            ? SizedBox(width: 16, height: 16,
                child: CircularProgressIndicator(color: color, strokeWidth: 2))
            : Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
      ),
    );
  }

  void _showTradeDialog(BuildContext context) {
    final amtCtrl    = TextEditingController();
    final ad         = widget.ad;
    final maxCrypto  = ad.available;
    final maxUsd     = maxCrypto * ad.price;
    final payMethods = ad.paymentMethods.isNotEmpty ? ad.paymentMethods : ['epay'];
    String selectedPay = payMethods.first;

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx2, setS) => AlertDialog(
          backgroundColor: Theme.of(context).cardColor,
          title: Text(
            widget.isBuyTab ? 'Buy ${ad.coinSymbol}' : 'Sell ${ad.coinSymbol}',
            style: TextStyle(color: cTx(context), fontSize: 15),
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Price:', style: TextStyle(color: cMt(context), fontSize: 12)),
                  Text('\$${ad.price.toStringAsFixed(2)}/unit',
                      style: TextStyle(color: cTx(context), fontSize: 12, fontWeight: FontWeight.w600)),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Available:', style: TextStyle(color: cMt(context), fontSize: 12)),
                  Text('${maxCrypto.toStringAsFixed(4)} ${ad.coinSymbol}  (\$${maxUsd.toStringAsFixed(2)})',
                      style: TextStyle(color: kCryptoGreen, fontSize: 12, fontWeight: FontWeight.w600)),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text('Limit:', style: TextStyle(color: cMt(context), fontSize: 12)),
                  Text('\$${ad.minAmount.toStringAsFixed(0)} – \$${ad.maxAmount.toStringAsFixed(0)}',
                      style: TextStyle(color: cMt(context), fontSize: 12)),
                ],
              ),
              const SizedBox(height: 12),
              TextField(
                controller: amtCtrl,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                style: TextStyle(color: cTx(context)),
                decoration: InputDecoration(
                  labelText: 'Amount (USD)',
                  labelStyle: TextStyle(color: cMt(context)),
                  hintText: '\$${ad.minAmount.toStringAsFixed(0)} – \$${ad.maxAmount.toStringAsFixed(0)}',
                  hintStyle: TextStyle(color: cMt(context).withAlpha(100), fontSize: 11),
                  enabledBorder: UnderlineInputBorder(borderSide: BorderSide(color: cBd(context))),
                  focusedBorder: const UnderlineInputBorder(borderSide: BorderSide(color: kCryptoPrimary)),
                ),
              ),
              const SizedBox(height: 12),
              Text('Payment method', style: TextStyle(color: cMt(context), fontSize: 11)),
              const SizedBox(height: 4),
              DropdownButton<String>(
                value: selectedPay,
                isExpanded: true,
                dropdownColor: Theme.of(context).cardColor,
                style: TextStyle(color: cTx(context), fontSize: 13),
                underline: Container(height: 1, color: cBd(context)),
                items: payMethods.map((m) => DropdownMenuItem(value: m, child: Text(m))).toList(),
                onChanged: (v) { if (v != null) setS(() => selectedPay = v); },
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx),
              child: Text('Cancel', style: TextStyle(color: cMt(context))),
            ),
            ElevatedButton(
              style: ElevatedButton.styleFrom(backgroundColor: kCryptoPrimary),
              onPressed: () async {
                final usdAmt = double.tryParse(amtCtrl.text) ?? 0;
                if (usdAmt <= 0) {
                  Fluttertoast.showToast(msg: 'Enter amount');
                  return;
                }
                final cryptoAmt = usdAmt / (ad.price > 0 ? ad.price : 1);
                if (cryptoAmt > maxCrypto) {
                  Fluttertoast.showToast(msg: 'Max available: ${maxCrypto.toStringAsFixed(4)} ${ad.coinSymbol}');
                  return;
                }
                if (usdAmt < ad.minAmount) {
                  Fluttertoast.showToast(msg: 'Minimum order: \$${ad.minAmount.toStringAsFixed(0)}');
                  return;
                }
                if (ad.maxAmount > 0 && usdAmt > ad.maxAmount) {
                  Fluttertoast.showToast(msg: 'Maximum order: \$${ad.maxAmount.toStringAsFixed(0)}');
                  return;
                }
                Navigator.pop(ctx);
                setState(() => _loading = true);
                try {
                  final repo = ref.read(cryptoRepositoryProvider);
                  await repo.placeP2pOrder(
                    adUuid: ad.uuid,
                    cryptoAmount: cryptoAmt,
                    paymentMethod: selectedPay,
                  );
                  Fluttertoast.showToast(msg: '✅ P2P order placed successfully!');
                  ref.invalidate(myP2pOrdersProvider);
                } catch (e) {
                  Fluttertoast.showToast(msg: _serverError(e), toastLength: Toast.LENGTH_LONG);
                } finally {
                  if (mounted) setState(() => _loading = false);
                }
              },
              child: const Text('Confirm', style: TextStyle(color: Colors.white)),
            ),
          ],
        ),
      ),
    );
  }

  String _serverError(Object e) {
    try {
      final dio = e as dynamic;
      final msg = dio.response?.data['message'] as String?;
      if (msg != null && msg.isNotEmpty) return msg;
    } catch (_) {}
    return 'Something went wrong. Please try again.';
  }
}

// ── My Ads ────────────────────────────────────────────────────────────────────

class _MyAdsList extends ConsumerWidget {
  const _MyAdsList();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final adsAsync = ref.watch(myAdsProvider);

    return adsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kCryptoPrimary)),
      error: (_, __) => Center(child: Text('Error loading ads', style: TextStyle(color: cMt(context)))),
      data: (ads) {
        if (ads.isEmpty) {
          return Center(child: Text('No active ads', style: TextStyle(color: cMt(context))));
        }
        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
          itemCount: ads.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (_, i) => _MyAdRow(ad: ads[i], ref: ref),
        );
      },
    );
  }
}

class _MyAdRow extends StatelessWidget {
  const _MyAdRow({required this.ad, required this.ref});
  final P2pAd ad;
  final WidgetRef ref;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: cCard(context),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: cBd(context)),
      ),
      child: Row(
        children: [
          CoinAvatarWidget(symbol: ad.coinSymbol, size: 32),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('${ad.type.toUpperCase()} ${ad.coinSymbol}',
                    style: TextStyle(color: cTx(context), fontWeight: FontWeight.w600, fontSize: 13)),
                Text('\$${ad.price}/unit  •  ${ad.available} available',
                    style: TextStyle(color: cMt(context), fontSize: 11)),
              ],
            ),
          ),
          TextButton(
            onPressed: () async {
              try {
                final repo = ref.read(cryptoRepositoryProvider);
                await repo.cancelAd(ad.uuid);
                ref.invalidate(myAdsProvider);
                Fluttertoast.showToast(msg: 'Ad cancelled');
              } catch (e) {
                Fluttertoast.showToast(msg: e.toString());
              }
            },
            child: const Text('Cancel', style: TextStyle(color: kCryptoRed, fontSize: 12)),
          ),
        ],
      ),
    );
  }
}

// ── My Orders ─────────────────────────────────────────────────────────────────

class _MyOrdersList extends ConsumerWidget {
  const _MyOrdersList();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final ordersAsync = ref.watch(myP2pOrdersProvider);

    return ordersAsync.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kCryptoPrimary)),
      error: (_, __) => Center(child: Text('Error', style: TextStyle(color: cMt(context)))),
      data: (orders) {
        if (orders.isEmpty) {
          return Center(child: Text('No orders yet', style: TextStyle(color: cMt(context))));
        }
        return ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 80),
          itemCount: orders.length,
          separatorBuilder: (_, __) => const SizedBox(height: 8),
          itemBuilder: (_, i) => _OrderRow(order: orders[i]),
        );
      },
    );
  }
}

class _OrderRow extends ConsumerStatefulWidget {
  const _OrderRow({required this.order});
  final P2pOrder order;

  @override
  ConsumerState<_OrderRow> createState() => _OrderRowState();
}

class _OrderRowState extends ConsumerState<_OrderRow> {
  bool _loading = false;

  Color _statusColor(String s) {
    switch (s) {
      case 'completed': return kCryptoGreen;
      case 'cancelled': case 'refunded': return kCryptoRed;
      default: return kCryptoGold;
    }
  }

  @override
  Widget build(BuildContext context) {
    final o = widget.order;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: cCard(context),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: cBd(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CoinAvatarWidget(symbol: o.coinSymbol, size: 30),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${o.type.toUpperCase()} ${o.coinSymbol}',
                        style: TextStyle(color: cTx(context), fontWeight: FontWeight.w600, fontSize: 12)),
                    Text('${o.cryptoAmount} ${o.coinSymbol}  •  \$${o.amountUsd.toStringAsFixed(2)}',
                        style: TextStyle(color: cMt(context), fontSize: 10)),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: _statusColor(o.status).withAlpha(30),
                  borderRadius: BorderRadius.circular(4),
                ),
                child: Text(o.status.toUpperCase(),
                    style: TextStyle(color: _statusColor(o.status), fontSize: 10, fontWeight: FontWeight.w700)),
              ),
            ],
          ),
          if (o.canMarkPaid || o.canRelease) ...[
            const SizedBox(height: 8),
            Row(
              children: [
                if (o.canMarkPaid)
                  Expanded(child: _actionBtn('Mark Paid', kCryptoPrimary, () => _markPaid(o.uuid))),
                if (o.canMarkPaid && o.canRelease) const SizedBox(width: 8),
                if (o.canRelease)
                  Expanded(child: _actionBtn('Release Crypto', kCryptoGreen, () => _release(o.uuid))),
              ],
            ),
          ],
        ],
      ),
    );
  }

  Widget _actionBtn(String label, Color color, VoidCallback onTap) => SizedBox(
    height: 32,
    child: ElevatedButton(
      onPressed: _loading ? null : onTap,
      style: ElevatedButton.styleFrom(
        backgroundColor: color.withAlpha(40),
        foregroundColor: color,
        side: BorderSide(color: color.withAlpha(120)),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
      ),
      child: Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
    ),
  );

  Future<void> _markPaid(String uuid) async {
    setState(() => _loading = true);
    try {
      await ref.read(cryptoRepositoryProvider).markP2pPaid(uuid);
      ref.invalidate(myP2pOrdersProvider);
      Fluttertoast.showToast(msg: 'Payment marked');
    } catch (e) {
      Fluttertoast.showToast(msg: e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _release(String uuid) async {
    setState(() => _loading = true);
    try {
      await ref.read(cryptoRepositoryProvider).releaseCrypto(uuid);
      ref.invalidate(myP2pOrdersProvider);
      Fluttertoast.showToast(msg: 'Crypto released!');
    } catch (e) {
      Fluttertoast.showToast(msg: e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }
}

// ── Post Ad Sheet ─────────────────────────────────────────────────────────────

class _PostAdSheet extends ConsumerStatefulWidget {
  const _PostAdSheet();

  @override
  ConsumerState<_PostAdSheet> createState() => _PostAdSheetState();
}

class _PostAdSheetState extends ConsumerState<_PostAdSheet> {
  String _type = 'sell';
  final String _coin = 'USDT';
  final _priceCtrl  = TextEditingController();
  final _amountCtrl = TextEditingController();
  final _minCtrl    = TextEditingController();
  final _maxCtrl    = TextEditingController();
  bool _loading = false;

  @override
  void dispose() {
    _priceCtrl.dispose(); _amountCtrl.dispose();
    _minCtrl.dispose(); _maxCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, MediaQuery.of(context).viewInsets.bottom + 16),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Post P2P Ad',
                style: TextStyle(color: cTx(context), fontSize: 16, fontWeight: FontWeight.w700)),
            const SizedBox(height: 16),
            Row(
              children: [
                Expanded(child: _TypeBtn(label: 'Sell', active: _type == 'sell',
                    onTap: () => setState(() => _type = 'sell'))),
                const SizedBox(width: 8),
                Expanded(child: _TypeBtn(label: 'Buy', active: _type == 'buy',
                    onTap: () => setState(() => _type = 'buy'))),
              ],
            ),
            const SizedBox(height: 12),
            _field(context, 'Price (USD)', _priceCtrl, hint: '1.01'),
            const SizedBox(height: 10),
            _field(context, 'Amount', _amountCtrl, hint: '100'),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(child: _field(context, 'Min (USD)', _minCtrl, hint: '10')),
                const SizedBox(width: 8),
                Expanded(child: _field(context, 'Max (USD)', _maxCtrl, hint: '500')),
              ],
            ),
            const SizedBox(height: 16),
            CryptoPrimaryButton(
              label: 'Post Ad',
              isLoading: _loading,
              onPressed: _submit,
            ),
          ],
        ),
      ),
    );
  }

  Widget _field(BuildContext context, String label, TextEditingController ctrl, {String hint = ''}) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: TextStyle(color: cMt(context), fontSize: 11)),
        const SizedBox(height: 4),
        TextField(
          controller: ctrl,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          style: TextStyle(color: cTx(context), fontSize: 13),
          decoration: InputDecoration(
            hintText: hint,
            hintStyle: TextStyle(color: cMt(context)),
            filled: true,
            fillColor: cBg(context),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8),
                borderSide: BorderSide(color: cBd(context))),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8),
                borderSide: BorderSide(color: cBd(context))),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8),
                borderSide: const BorderSide(color: kCryptoPrimary)),
            contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          ),
        ),
      ],
    );
  }

  Future<void> _submit() async {
    setState(() => _loading = true);
    try {
      await ref.read(cryptoRepositoryProvider).createP2pAd(
        coinSymbol: _coin,
        type: _type,
        priceUsd: double.tryParse(_priceCtrl.text) ?? 0,
        amount: double.tryParse(_amountCtrl.text) ?? 0,
        minOrder: double.tryParse(_minCtrl.text) ?? 0,
        maxOrder: double.tryParse(_maxCtrl.text) ?? 0,
        paymentMethods: ['epay'],
      );
      ref.invalidate(myAdsProvider);
      if (mounted) Navigator.pop(context);
      Fluttertoast.showToast(msg: 'Ad posted!');
    } catch (e) {
      Fluttertoast.showToast(msg: e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }
}

class _TypeBtn extends StatelessWidget {
  const _TypeBtn({required this.label, required this.active, required this.onTap});
  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        color: active ? kCryptoPrimary.withAlpha(40) : Colors.transparent,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: active ? kCryptoPrimary : cBd(context)),
      ),
      child: Text(label,
        textAlign: TextAlign.center,
        style: TextStyle(
          color: active ? kCryptoPrimary : cMt(context),
          fontWeight: FontWeight.w600,
          fontSize: 13,
        ),
      ),
    ),
  );
}

// ── Filter dropdown ───────────────────────────────────────────────────────────

class _FilterDropdown extends StatelessWidget {
  const _FilterDropdown({
    required this.label, required this.value,
    required this.items, required this.onChanged,
  });
  final String label, value;
  final List<String> items;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
    decoration: BoxDecoration(
      color: cCard(context),
      borderRadius: BorderRadius.circular(8),
      border: Border.all(color: cBd(context)),
    ),
    child: DropdownButton<String>(
      value: value,
      isExpanded: true,
      dropdownColor: cCard(context),
      underline: const SizedBox.shrink(),
      style: TextStyle(color: cTx(context), fontSize: 12),
      items: items.map((i) => DropdownMenuItem(value: i, child: Text(i))).toList(),
      onChanged: (v) { if (v != null) onChanged(v); },
    ),
  );
}
