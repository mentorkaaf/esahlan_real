import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../../../shared/widgets/app_button.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../payment/mobile_pay_sheet.dart';
import '../../payment/payment_method_section.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../ads/services/ad_service.dart';
import '../../../../core/theme/theme_x.dart';

final _svc = ModuleApiService.create();
final _laundryItemsProvider = FutureProvider((_) => _svc.getLaundryItems());

class ELaundryScreen extends ConsumerStatefulWidget {
  const ELaundryScreen({super.key});

  @override
  ConsumerState<ELaundryScreen> createState() => _ELaundryScreenState();
}

double _toD(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0;

class _ELaundryScreenState extends ConsumerState<ELaundryScreen> {
  String _serviceType = 'normal'; // normal | express
  final Map<int, int> _qty = {}; // item_id → quantity

  double _calcTotal(List items) {
    double total = 0;
    for (final item in items) {
      final q = _qty[item['id']] ?? 0;
      if (q > 0) {
        final price = _serviceType == 'express'
            ? _toD(item['express_price'])
            : _toD(item['normal_price']);
        total += price * q;
      }
    }
    return total;
  }

  int get _totalQty => _qty.values.fold(0, (s, q) => s + q);

  @override
  Widget build(BuildContext context) {
    final itemsAsync = ref.watch(_laundryItemsProvider);

    final c = context.colors;
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(icon: Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: c.navyText), onPressed: () => context.pop()),
        title: Text('eLaundry', style: TextStyle(fontWeight: FontWeight.w800, color: c.navyText, fontFamily: 'Cairo')),
      ),
      body: itemsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('Error: $e')),
        data: (res) {
          final items = res['data'] as List? ?? [];
          final total = _calcTotal(items);

          // Derive package info from items for the service type cards
          final firstItem = items.isNotEmpty ? items.first : null;
          final minNormal  = items.isEmpty ? 0.0 : items.map((e) => _toD(e['normal_price'])).reduce((a, b) => a < b ? a : b);
          final minExpress = items.isEmpty ? 0.0 : items.map((e) => _toD(e['express_price'])).reduce((a, b) => a < b ? a : b);
          final normalDays  = firstItem != null ? '${firstItem['normal_days']} Day${(firstItem['normal_days'] as num? ?? 1) > 1 ? 's' : ''}' : '1-2 Days';
          final expressHrs  = firstItem != null ? '${firstItem['express_hours']} Hours' : '24 Hours';

          return Column(children: [
            // Service type selector
            Container(
              color: c.cardBg,
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Select Service', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: c.navyText)),
                const SizedBox(height: 10),
                Row(children: [
                  Expanded(child: _ServiceTypeCard(
                    label: 'Normal Wash',
                    price: 'From \$${minNormal.toStringAsFixed(2)}/item',
                    eta: normalDays,
                    icon: Icons.local_laundry_service_outlined,
                    color: const Color(0xFF2980B9),
                    selected: _serviceType == 'normal',
                    onTap: () => setState(() => _serviceType = 'normal'),
                  )),
                  const SizedBox(width: 12),
                  Expanded(child: _ServiceTypeCard(
                    label: 'Express',
                    price: 'From \$${minExpress.toStringAsFixed(2)}/item',
                    eta: expressHrs,
                    icon: Icons.flash_on_rounded,
                    color: const Color(0xFFE74C3C),
                    selected: _serviceType == 'express',
                    onTap: () => setState(() => _serviceType = 'express'),
                  )),
                ]),
              ]),
            ),
            const SizedBox(height: 8),

            // Items list header
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
              child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text('Select Items', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: c.navyText)),
                Text('${_serviceType == 'express' ? 'Express' : 'Normal'} Rate',
                    style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
              ]),
            ),
            const SizedBox(height: 8),

            // Items list
            Expanded(child: RefreshIndicator(
              color: AppColors.primary,
              onRefresh: () async => ref.invalidate(_laundryItemsProvider),
              child: ListView.separated(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (_, i) {
                  final item  = items[i];
                  final price = _serviceType == 'express'
                      ? _toD(item['express_price'])
                      : _toD(item['normal_price']);
                  final qty   = _qty[item['id']] ?? 0;

                  return Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: c.cardBg, borderRadius: BorderRadius.circular(14),
                      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
                    ),
                    child: Row(children: [
                      // Icon/Image
                      Container(
                        width: 56, height: 56,
                        decoration: BoxDecoration(
                          color: AppColors.primary.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: item['image'] != null
                            ? ClipRRect(borderRadius: BorderRadius.circular(12),
                                child: NetImage(url: item['image'], fit: BoxFit.cover))
                            : const Icon(Icons.checkroom_outlined, color: AppColors.primary, size: 28),
                      ),
                      const SizedBox(width: 12),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(item['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: c.navyText)),
                        const SizedBox(height: 2),
                        Text(
                          _serviceType == 'express'
                              ? '${item['express_hours']} hours • \$${price.toStringAsFixed(2)}'
                              : '${item['normal_days']} days • \$${price.toStringAsFixed(2)}',
                          style: const TextStyle(fontSize: 12, color: AppColors.textGrey),
                        ),
                        if (qty > 0)
                          Text('Subtotal: \$${(price * qty).toStringAsFixed(2)}',
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: AppColors.primary)),
                      ])),
                      // Qty stepper
                      Row(children: [
                        GestureDetector(
                          onTap: () => setState(() { if (qty > 0) _qty[item['id']] = qty - 1; }),
                          child: Container(
                            width: 30, height: 30,
                            decoration: BoxDecoration(
                              color: qty > 0 ? AppColors.primary : AppColors.surface,
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Icon(Icons.remove, size: 16, color: qty > 0 ? Colors.white : AppColors.textGrey),
                          ),
                        ),
                        SizedBox(width: 32, child: Text('$qty', textAlign: TextAlign.center,
                            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: c.navyText))),
                        GestureDetector(
                          onTap: () => setState(() => _qty[item['id']] = qty + 1),
                          child: Container(
                            width: 30, height: 30,
                            decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(8)),
                            child: const Icon(Icons.add, size: 16, color: Colors.white),
                          ),
                        ),
                      ]),
                    ]),
                  );
                },
              ),
            )),

            // Summary + Order button
            if (_totalQty > 0)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: c.cardBg,
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10, offset: const Offset(0, -2))],
                ),
                child: Column(children: [
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Text('$_totalQty item${_totalQty > 1 ? 's' : ''} • ${_serviceType == 'express' ? expressHrs : normalDays}',
                        style: const TextStyle(color: AppColors.textGrey, fontSize: 13)),
                    Text('\$${total.toStringAsFixed(2)}',
                        style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: AppColors.primary)),
                  ]),
                  const SizedBox(height: 10),
                  AppButton(label: 'Place Laundry Order', onPressed: () => _placeOrder(context, items)),
                ]),
              ),
          ]);
        },
      ),
    );
  }

  void _placeOrder(BuildContext context, List items) {
    final total = _calcTotal(items);
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => _OrderConfirmPage(
        serviceType: _serviceType,
        items: items,
        qty: _qty,
        total: total,
        onConfirm: (districtId, payMethod, waafiRef) async {
          final selected = <Map<String, dynamic>>[];
          _qty.forEach((id, q) { if (q > 0) selected.add({'id': id, 'qty': q}); });
          await _svc.placeLaundryOrder({
            'service_type': _serviceType,
            'items': selected,
            'pickup_district_id': districtId,
            'payment_method': payMethod,
            if (waafiRef != null) 'payment_reference': waafiRef,
          });
        },
      ),
    ));
  }
}

class _ServiceTypeCard extends StatelessWidget {
  final String label, price, eta;
  final IconData icon;
  final Color color;
  final bool selected;
  final VoidCallback onTap;

  const _ServiceTypeCard({required this.label, required this.price, required this.eta,
      required this.icon, required this.color, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: selected ? color.withValues(alpha: 0.12) : c.surfaceBg,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: selected ? color : c.borderColor, width: selected ? 2 : 1),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Icon(icon, color: selected ? color : c.mutedText, size: 26),
          const SizedBox(height: 8),
          Text(label, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: selected ? color : c.navyText)),
          Text(price, style: TextStyle(fontSize: 11, color: selected ? color : c.mutedText, fontWeight: FontWeight.w600)),
          Text(eta, style: TextStyle(fontSize: 11, color: c.mutedText)),
        ]),
      ),
    );
  }
}

// ─── Full-screen order confirmation page ────────────────────────────────────
class _OrderConfirmPage extends StatefulWidget {
  final String serviceType;
  final List items;
  final Map<int, int> qty;
  final double total;
  final Future<void> Function(int districtId, String payMethod, String? waafiRef) onConfirm;

  const _OrderConfirmPage({required this.serviceType, required this.items,
      required this.qty, required this.total, required this.onConfirm});

  @override
  State<_OrderConfirmPage> createState() => _OrderConfirmPageState();
}

class _OrderConfirmPageState extends State<_OrderConfirmPage> {
  static const _districts = [
    'Abdiaziz','Howlwadaag','Waaberi','Hamarweyne','Hamarjajab','Warta Nabadda',
    'Deyniile','Dharkeynley','Wadajir','Hiliwaa','Hodan','Kaaraan','Daarusalaam',
    'Kahda','Garasbaaley','Shibis','Shangaani','Gubadleey','Yaqshiid','Boondheere',
  ];

  int    _districtId = 1;
  String _payMethod  = 'wallet';
  String? _waafiRef;
  bool   _loading    = false;
  bool   _success    = false;

  @override
  Widget build(BuildContext context) {
    if (_success) return _SuccessView(onDone: () => Navigator.of(context).pop());

    final c = context.colors;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Confirm Order', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
            onPressed: () => Navigator.of(context).pop()),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

          // ── Order summary card ─────────────────────────────────────────
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: c.cardBg, borderRadius: BorderRadius.circular(16),
              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 12)],
            ),
            child: Column(children: [
              Row(children: [
                Container(width: 44, height: 44, decoration: BoxDecoration(
                  color: AppColors.primary.withOpacity(0.12), borderRadius: BorderRadius.circular(12)),
                  child: const Icon(Icons.local_laundry_service_rounded, color: AppColors.primary, size: 24)),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('eLaundry Order', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: c.navyText)),
                  Text(widget.serviceType == 'express' ? 'Express — 24 Hours' : 'Normal — 1-2 Days',
                      style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
                ])),
                Text('\$${widget.total.toStringAsFixed(2)}',
                    style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: AppColors.primary)),
              ]),
              const SizedBox(height: 14),
              const Divider(),
              const SizedBox(height: 10),
              ...widget.items.where((it) => (widget.qty[it['id']] ?? 0) > 0).map((it) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  Text(it['name'] ?? '', style: TextStyle(fontSize: 13, color: c.navyText)),
                  Text('×${widget.qty[it['id']]}  \$${((it['price'] ?? 0) * (widget.qty[it['id']] ?? 0)).toStringAsFixed(2)}',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: c.navyText)),
                ]),
              )),
            ]),
          ),

          const SizedBox(height: 22),

          // ── Pickup District ────────────────────────────────────────────
          Text('Pickup District', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: c.navyText)),
          const SizedBox(height: 8),
          DropdownButtonFormField<int>(
            value: _districtId,
            onChanged: (v) => setState(() => _districtId = v!),
            decoration: InputDecoration(
              filled: true, fillColor: c.inputFill,
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.divider)),
              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.divider)),
              focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AppColors.primary, width: 2)),
              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
            ),
            items: _districts.asMap().entries.map((e) =>
                DropdownMenuItem(value: e.key + 1, child: Text(e.value))).toList(),
          ),

          const SizedBox(height: 22),

          // ── Payment Method ─────────────────────────────────────────────
          Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: c.navyText)),
          const SizedBox(height: 10),
          PaymentMethodSection(
            selected: _payMethod,
            onChanged: (m) => setState(() => _payMethod = m),
          ),

          const SizedBox(height: 30),
        ]),
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
          child: AppButton(
            label: _loading ? 'Placing Order...' : 'Confirm Order',
            isLoading: _loading,
            onPressed: _loading ? null : () async {
              if (_payMethod == 'wallet') {
                final ok = await showWalletPinDialog(context);
                if (!ok) return;
              } else if (_payMethod == 'mobile_pay') {
                final result = await showMobilePaySheet(context, amount: widget.total, description: 'eLaundry Order');
                if (result?.success != true) return;
                _waafiRef = result!.account != null ? 'mobile_pay_${result.account!.id}' : 'mobile_pay';
              } else {
                final result = await showWaafiPaySheet(
                  context, amount: widget.total, type: 'order', description: 'eLaundry Order');
                if (result?.success != true) return;
                _waafiRef = result!.reference;
              }
              setState(() => _loading = true);
              try {
                await widget.onConfirm(_districtId, _payMethod, _waafiRef);
                if (mounted) setState(() { _loading = false; _success = true; });
              } catch (e) {
                if (mounted) {
                  setState(() => _loading = false);
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.error));
                }
              }
            },
          ),
        ),
      ),
    );
  }
}

class _SuccessView extends StatelessWidget {
  final VoidCallback onDone;
  const _SuccessView({required this.onDone});
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: Theme.of(context).scaffoldBackgroundColor,
    body: Center(child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(width: 90, height: 90,
          decoration: const BoxDecoration(color: AppColors.success, shape: BoxShape.circle),
          child: const Icon(Icons.check_rounded, color: Colors.white, size: 52)),
        SizedBox(height: 24),
        Text('Order Placed!', style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900, color: context.colors.navyText)),
        const SizedBox(height: 10),
        const Text('Your laundry order has been placed.\nWe\'ll notify you when it\'s picked up.',
            textAlign: TextAlign.center, style: TextStyle(fontSize: 14, color: AppColors.textGrey, height: 1.5)),
        const SizedBox(height: 32),
        AppButton(label: 'Back to eLaundry', onPressed: onDone),
      ]),
    )),
  );
}

class _PayChip extends StatelessWidget {
  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;
  const _PayChip({required this.label, required this.icon, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
        decoration: BoxDecoration(
          color: selected ? AppColors.primary.withOpacity(0.08) : AppColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: selected ? AppColors.primary : AppColors.divider, width: selected ? 2 : 1),
        ),
        child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(icon, size: 18, color: selected ? AppColors.primary : AppColors.textGrey),
          const SizedBox(width: 6),
          Text(label, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: selected ? AppColors.primary : AppColors.textGrey)),
        ]),
      ),
    );
  }
}
