import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../../../shared/widgets/app_button.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';

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

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white, elevation: 0,
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: AppColors.secondary), onPressed: () => context.pop()),
        title: const Text('eLaundry', style: TextStyle(fontWeight: FontWeight.w800, color: AppColors.secondary, fontFamily: 'Cairo')),
      ),
      body: itemsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('Error: $e')),
        data: (res) {
          final items = res['data'] as List? ?? [];
          final total = _calcTotal(items);

          return Column(children: [
            // Service type selector
            Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Select Service', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: AppColors.secondary)),
                const SizedBox(height: 10),
                Row(children: [
                  Expanded(child: _ServiceTypeCard(
                    label: 'Normal Wash',
                    price: '\$1.00 / item',
                    eta: '1-2 Days',
                    icon: Icons.local_laundry_service_outlined,
                    color: const Color(0xFF2980B9),
                    selected: _serviceType == 'normal',
                    onTap: () => setState(() => _serviceType = 'normal'),
                  )),
                  const SizedBox(width: 12),
                  Expanded(child: _ServiceTypeCard(
                    label: 'Express',
                    price: '\$2.00 / item',
                    eta: '24 Hours',
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
                const Text('Select Items', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: AppColors.secondary)),
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
                      color: Colors.white, borderRadius: BorderRadius.circular(14),
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
                                child: Image.network(fixImgUrl(item['image']), fit: BoxFit.cover))
                            : const Icon(Icons.checkroom_outlined, color: AppColors.primary, size: 28),
                      ),
                      const SizedBox(width: 12),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(item['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: AppColors.secondary)),
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
                            style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: AppColors.secondary))),
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
                  color: Colors.white,
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10, offset: const Offset(0, -2))],
                ),
                child: Column(children: [
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Text('$_totalQty item${_totalQty > 1 ? 's' : ''} • ${_serviceType == 'express' ? '24h express' : '1-2 days'}',
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
    showModalBottomSheet(
      context: context,
      useRootNavigator: true,
      backgroundColor: Colors.white,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => _OrderSheet(
        serviceType: _serviceType,
        items: items,
        qty: _qty,
        total: total,
        onConfirm: (districtId, address, payMethod, waafiRef) async {
          try {
            final selected = <Map<String, dynamic>>[];
            _qty.forEach((id, q) { if (q > 0) selected.add({'id': id, 'qty': q}); });
            await _svc.placeLaundryOrder({
              'service_type': _serviceType,
              'items': selected,
              'pickup_district_id': districtId,
              'pickup_address': address,
              'payment_method': payMethod,
              if (waafiRef != null) 'payment_reference': waafiRef,
            });
            if (context.mounted) {
              Navigator.pop(context);
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Laundry order placed!'), backgroundColor: AppColors.success));
            }
          } catch (e) {
            if (context.mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.error));
            }
          }
        },
      ),
    );
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
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: selected ? color.withValues(alpha: 0.12) : AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: selected ? color : AppColors.divider, width: selected ? 2 : 1),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: selected ? color : AppColors.textGrey, size: 26),
        const SizedBox(height: 8),
        Text(label, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: selected ? color : AppColors.secondary)),
        Text(price, style: TextStyle(fontSize: 11, color: selected ? color : AppColors.textGrey, fontWeight: FontWeight.w600)),
        Text(eta, style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
      ]),
    ),
  );
}

class _OrderSheet extends StatefulWidget {
  final String serviceType;
  final List items;
  final Map<int, int> qty;
  final double total;
  final Function(int, String, String, String?) onConfirm;

  const _OrderSheet({required this.serviceType, required this.items, required this.qty,
      required this.total, required this.onConfirm});

  @override
  State<_OrderSheet> createState() => _OrderSheetState();
}

class _OrderSheetState extends State<_OrderSheet> {
  final _districts = ['Abdiaziz','Howlwadaag','Waaberi','Hamarweyne','Hamarjajab','Warta Nabadda',
      'Deyniile','Dharkeynley','Wadajir','Hiliwaa','Hodan','Kaaraan','Daarusalaam',
      'Kahda','Garasbaaley','Shibis','Shangaani','Gubadleey','Yaqshiid','Boondheere'];
  int _districtId = 1;
  final _addrCtrl = TextEditingController();
  String _payMethod = 'wallet';
  String? _waafiRef;
  bool _loading = false;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            const Text('Confirm Order', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: AppColors.secondary)),
            IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
          ]),
          const Divider(),
          const Text('Pickup District', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.secondary)),
          const SizedBox(height: 8),
          DropdownButtonFormField<int>(
            value: _districtId,
            onChanged: (v) => setState(() => _districtId = v!),
            items: _districts.asMap().entries.map((e) =>
                DropdownMenuItem(value: e.key + 1, child: Text(e.value))).toList(),
            decoration: InputDecoration(
              filled: true, fillColor: AppColors.surface,
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
            ),
          ),
          const SizedBox(height: 14),
          const Text('Pickup Address', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.secondary)),
          const SizedBox(height: 8),
          TextField(
            controller: _addrCtrl,
            decoration: InputDecoration(
              hintText: 'House number, street name...',
              filled: true, fillColor: AppColors.surface,
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
            ),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: AppColors.surface, borderRadius: BorderRadius.circular(12)),
            child: Column(children: [
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                const Text('Service type', style: TextStyle(color: AppColors.textGrey, fontSize: 13)),
                Text(widget.serviceType == 'express' ? 'Express (24h)' : 'Normal (1-2 days)',
                    style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.secondary)),
              ]),
              const SizedBox(height: 8),
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                const Text('Total', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: AppColors.secondary)),
                Text('\$${widget.total.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: AppColors.primary)),
              ]),
            ]),
          ),
          const SizedBox(height: 16),
          const Text('Payment Method', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.secondary)),
          const SizedBox(height: 8),
          Row(children: [
            Expanded(child: _PayChip(label: 'Wallet',    icon: Icons.account_balance_wallet_rounded, selected: _payMethod == 'wallet',    onTap: () => setState(() => _payMethod = 'wallet'))),
            const SizedBox(width: 10),
            Expanded(child: _PayChip(label: 'Waafi Pay', icon: Icons.phone_android_rounded,          selected: _payMethod == 'waafi_pay', onTap: () => setState(() => _payMethod = 'waafi_pay'))),
          ]),
          const SizedBox(height: 16),
          AppButton(
            label: _loading ? 'Placing Order...' : 'Confirm Order',
            isLoading: _loading,
            onPressed: _addrCtrl.text.trim().isEmpty ? null : () async {
              if (_payMethod == 'wallet') {
                final ok = await showWalletPinDialog(context);
                if (!ok) return;
              } else if (_payMethod == 'waafi_pay') {
                final result = await showWaafiPaySheet(
                  context, amount: widget.total, type: 'order', description: 'eLaundry Order',
                );
                if (result?.success != true) return;
                _waafiRef = result!.reference;
              }
              setState(() => _loading = true);
              await widget.onConfirm(_districtId, _addrCtrl.text.trim(), _payMethod, _waafiRef);
              setState(() => _loading = false);
            },
          ),
          const SizedBox(height: 8),
        ]),
      ),
    );
  }
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
