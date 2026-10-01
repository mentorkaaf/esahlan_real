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
import '../../../../core/theme/theme_x.dart';
import '../../rewards/redeem_points_bar.dart';
import '../../auth/presentation/providers/auth_provider.dart';

final _svc = ModuleApiService.create();
final _laundryItemsProvider = FutureProvider((_) => _svc.getLaundryItems());

class ELaundryScreen extends ConsumerStatefulWidget {
  const ELaundryScreen({super.key});

  @override
  ConsumerState<ELaundryScreen> createState() => _ELaundryScreenState();
}

double _toD(dynamic v) => double.tryParse(v?.toString() ?? '0') ?? 0;

/// Flatten grouped API response → flat item list
List<Map<String, dynamic>> _flatItems(List categories) {
  final out = <Map<String, dynamic>>[];
  for (final cat in categories) {
    for (final sub in (cat['sub_categories'] as List? ?? [])) {
      for (final item in (sub['items'] as List? ?? [])) {
        out.add(Map<String, dynamic>.from(item as Map));
      }
    }
  }
  return out;
}

class _ELaundryScreenState extends ConsumerState<ELaundryScreen>
    with SingleTickerProviderStateMixin {
  String _serviceType = 'normal';
  final Map<int, int> _qty = {};
  late TabController _tabController;
  final List<String> _mainCats = ['Clean & Press', 'Press Only', 'Wash & Fold', 'Bed & Bath'];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: _mainCats.length, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  double _calcTotal(List<Map<String, dynamic>> flat) {
    double total = 0;
    for (final item in flat) {
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
        leading: IconButton(
            icon: Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: c.navyText),
            onPressed: () => context.pop()),
        title: Text('eLaundry',
            style: TextStyle(fontWeight: FontWeight.w800, color: c.navyText, fontFamily: 'Cairo')),
      ),
      body: itemsAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(child: Text('Error: $e')),
        data: (res) {
          final categories = res['data'] as List? ?? [];
          final flat = _flatItems(categories);
          final total = _calcTotal(flat);

          final minNormal  = flat.isEmpty ? 0.0 : flat.map((e) => _toD(e['normal_price'])).reduce((a, b) => a < b ? a : b);
          final minExpress = flat.isEmpty ? 0.0 : flat.map((e) => _toD(e['express_price'])).reduce((a, b) => a < b ? a : b);
          final firstItem  = flat.isNotEmpty ? flat.first : null;
          final normalDays = firstItem != null
              ? '${firstItem['normal_days']} Day${(firstItem['normal_days'] as num? ?? 1) > 1 ? 's' : ''}'
              : '1-3 Days';
          final expressHrs = firstItem != null ? '${firstItem['express_hours']} Hours' : '24 Hours';

          return Column(children: [
            // Service type selector
            Container(
              color: c.cardBg,
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Select Service',
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: c.navyText)),
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

            // Main category tabs
            Container(
              color: c.cardBg,
              child: TabBar(
                controller: _tabController,
                isScrollable: true,
                tabAlignment: TabAlignment.start,
                labelColor: AppColors.primary,
                unselectedLabelColor: c.mutedText,
                indicatorColor: AppColors.primary,
                labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                tabs: _mainCats.map((cat) => Tab(text: cat)).toList(),
              ),
            ),

            // Items by tab
            Expanded(
              child: TabBarView(
                controller: _tabController,
                children: _mainCats.map((mainCat) {
                  // Find matching category from API
                  final catData = categories.cast<Map<String, dynamic>>()
                      .where((c) => c['main_category'] == mainCat)
                      .toList();

                  if (catData.isEmpty) {
                    return const Center(
                      child: Text('No items', style: TextStyle(color: AppColors.textGrey)));
                  }

                  final subCats = catData.first['sub_categories'] as List? ?? [];

                  return RefreshIndicator(
                    color: AppColors.primary,
                    onRefresh: () async => ref.invalidate(_laundryItemsProvider),
                    child: ListView.builder(
                      padding: const EdgeInsets.only(top: 8, bottom: 16, left: 16, right: 16),
                      itemCount: subCats.length,
                      itemBuilder: (_, si) {
                        final sub = subCats[si] as Map<String, dynamic>;
                        final subName = sub['name'] as String? ?? '';
                        final subItems = sub['items'] as List? ?? [];

                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Sub-category header
                            Padding(
                              padding: EdgeInsets.only(top: si == 0 ? 4 : 16, bottom: 8),
                              child: Row(children: [
                                Container(
                                  width: 4, height: 18,
                                  decoration: BoxDecoration(
                                    color: AppColors.primary,
                                    borderRadius: BorderRadius.circular(2),
                                  ),
                                ),
                                const SizedBox(width: 8),
                                Text(subName,
                                    style: TextStyle(
                                        fontWeight: FontWeight.w800,
                                        fontSize: 14,
                                        color: c.navyText)),
                              ]),
                            ),
                            // Items in this sub-category
                            ...subItems.asMap().entries.map((e) {
                              final item = e.value as Map<String, dynamic>;
                              final id    = item['id'] as int;
                              final price = _serviceType == 'express'
                                  ? _toD(item['express_price'])
                                  : _toD(item['normal_price']);
                              final qty = _qty[id] ?? 0;

                              return Container(
                                margin: const EdgeInsets.only(bottom: 8),
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: c.cardBg,
                                  borderRadius: BorderRadius.circular(14),
                                  boxShadow: [BoxShadow(
                                      color: Colors.black.withValues(alpha: 0.05),
                                      blurRadius: 8)],
                                ),
                                child: Row(children: [
                                  // Icon/Image
                                  Container(
                                    width: 50, height: 50,
                                    decoration: BoxDecoration(
                                      color: AppColors.primary.withValues(alpha: 0.1),
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                    child: item['image'] != null
                                        ? ClipRRect(
                                            borderRadius: BorderRadius.circular(12),
                                            child: NetImage(url: item['image'], fit: BoxFit.cover))
                                        : const Icon(Icons.checkroom_outlined,
                                            color: AppColors.primary, size: 26),
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(item['name'] ?? '',
                                          style: TextStyle(
                                              fontWeight: FontWeight.w700,
                                              fontSize: 13,
                                              color: c.navyText)),
                                      const SizedBox(height: 2),
                                      Text(
                                        _serviceType == 'express'
                                            ? '${item['express_hours']} hrs • \$${price.toStringAsFixed(2)}'
                                            : '${item['normal_days']} days • \$${price.toStringAsFixed(2)}',
                                        style: const TextStyle(
                                            fontSize: 11, color: AppColors.textGrey),
                                      ),
                                      if (qty > 0)
                                        Text('Subtotal: \$${(price * qty).toStringAsFixed(2)}',
                                            style: const TextStyle(
                                                fontSize: 12,
                                                fontWeight: FontWeight.w700,
                                                color: AppColors.primary)),
                                    ],
                                  )),
                                  // Qty stepper
                                  Row(children: [
                                    GestureDetector(
                                      onTap: () => setState(() {
                                        if (qty > 0) _qty[id] = qty - 1;
                                      }),
                                      child: Container(
                                        width: 28, height: 28,
                                        decoration: BoxDecoration(
                                          color: qty > 0 ? AppColors.primary : AppColors.surface,
                                          borderRadius: BorderRadius.circular(8),
                                        ),
                                        child: Icon(Icons.remove, size: 14,
                                            color: qty > 0 ? Colors.white : AppColors.textGrey),
                                      ),
                                    ),
                                    SizedBox(
                                      width: 30,
                                      child: Text('$qty',
                                          textAlign: TextAlign.center,
                                          style: TextStyle(
                                              fontWeight: FontWeight.w800,
                                              fontSize: 15,
                                              color: c.navyText)),
                                    ),
                                    GestureDetector(
                                      onTap: () => setState(() => _qty[id] = qty + 1),
                                      child: Container(
                                        width: 28, height: 28,
                                        decoration: BoxDecoration(
                                            color: AppColors.primary,
                                            borderRadius: BorderRadius.circular(8)),
                                        child: const Icon(Icons.add, size: 14, color: Colors.white),
                                      ),
                                    ),
                                  ]),
                                ]),
                              );
                            }),
                          ],
                        );
                      },
                    ),
                  );
                }).toList(),
              ),
            ),

            // Summary + Order button
            if (_totalQty > 0)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: c.cardBg,
                  boxShadow: [BoxShadow(
                      color: Colors.black.withValues(alpha: 0.06),
                      blurRadius: 10,
                      offset: const Offset(0, -2))],
                ),
                child: Column(children: [
                  Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Text(
                      '$_totalQty item${_totalQty > 1 ? 's' : ''} • ${_serviceType == 'express' ? expressHrs : normalDays}',
                      style: const TextStyle(color: AppColors.textGrey, fontSize: 13)),
                    Text('\$${total.toStringAsFixed(2)}',
                        style: const TextStyle(
                            fontWeight: FontWeight.w900,
                            fontSize: 20,
                            color: AppColors.primary)),
                  ]),
                  const SizedBox(height: 10),
                  AppButton(
                    label: 'Place Laundry Order',
                    onPressed: () => _placeOrder(context, flat),
                  ),
                ]),
              ),
          ]);
        },
      ),
    );
  }

  void _placeOrder(BuildContext context, List<Map<String, dynamic>> flat) {
    final total = _calcTotal(flat);
    Navigator.of(context).push(MaterialPageRoute(
      builder: (_) => _OrderConfirmPage(
        serviceType: _serviceType,
        items: flat,
        qty: _qty,
        subtotal: total,
        onConfirm: (districtId, payMethod, waafiRef, pointsToRedeem, selfPickup) async {
          final selected = <Map<String, dynamic>>[];
          _qty.forEach((id, q) { if (q > 0) selected.add({'id': id, 'qty': q}); });
          await _svc.placeLaundryOrder({
            'service_type': _serviceType,
            'items': selected,
            if (!selfPickup) 'pickup_district_id': districtId,
            'self_pickup': selfPickup,
            'payment_method': payMethod,
            if (waafiRef != null) 'payment_reference': waafiRef,
            if (pointsToRedeem > 0) 'points_to_redeem': pointsToRedeem,
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
          Text(label,
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13,
                  color: selected ? color : c.navyText)),
          Text(price,
              style: TextStyle(fontSize: 11, color: selected ? color : c.mutedText,
                  fontWeight: FontWeight.w600)),
          Text(eta, style: TextStyle(fontSize: 11, color: c.mutedText)),
        ]),
      ),
    );
  }
}

// ─── Full-screen order confirmation page ────────────────────────────────────
class _OrderConfirmPage extends ConsumerStatefulWidget {
  final String serviceType;
  final List<Map<String, dynamic>> items;
  final Map<int, int> qty;
  final double subtotal;
  final Future<void> Function(int districtId, String payMethod, String? waafiRef, int pointsToRedeem, bool selfPickup) onConfirm;

  const _OrderConfirmPage({required this.serviceType, required this.items,
      required this.qty, required this.subtotal, required this.onConfirm});

  @override
  ConsumerState<_OrderConfirmPage> createState() => _OrderConfirmPageState();
}

class _OrderConfirmPageState extends ConsumerState<_OrderConfirmPage> {
  List<Map<String, dynamic>> _districts = [];
  bool _districtsLoading = true;

  int     _districtId     = 0;
  bool    _selfPickup     = false;
  String  _payMethod      = 'wallet';
  String? _waafiRef;
  bool    _loading        = false;
  bool    _success        = false;
  int     _pointsToRedeem = 0;
  double  _pointsDiscount = 0.0;
  double  _deliveryFee    = 0.0;
  bool    _feeLoading     = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await _loadDistricts();
      await _loadDeliveryFee();
    });
  }

  Future<void> _loadDistricts() async {
    try {
      final res = await ModuleApiService.create().getDistricts();
      final list = (res?['data'] as List? ?? res as List? ?? []) as List;
      final districts = list.map<Map<String, dynamic>>((d) => {
        'id': d['id'] is int ? d['id'] : int.tryParse(d['id'].toString()) ?? 0,
        'name': d['name']?.toString() ?? '',
      }).where((d) => d['id'] != 0).toList();

      final user = ref.read(authStateProvider).valueOrNull;
      int defaultId = districts.isNotEmpty ? (districts.first['id'] as int) : 1;
      if (user?.districtId != null && user!.districtId! > 0) {
        final found = districts.any((d) => d['id'] == user.districtId);
        if (found) defaultId = user.districtId!;
      }
      if (mounted) setState(() { _districts = districts; _districtId = defaultId; _districtsLoading = false; });
    } catch (_) {
      if (mounted) setState(() => _districtsLoading = false);
    }
  }

  Future<void> _loadDeliveryFee() async {
    if (_selfPickup) {
      if (mounted) setState(() => _deliveryFee = 0.0);
      return;
    }
    if (!mounted) return;
    setState(() => _feeLoading = true);
    try {
      final selected = <Map<String, dynamic>>[];
      widget.qty.forEach((id, q) { if (q > 0) selected.add({'id': id, 'qty': q}); });
      final res = await ModuleApiService.create().estimateLaundry({
        'service_type': widget.serviceType,
        'items': selected,
        'pickup_district_id': _districtId,
        'self_pickup': false,
      });
      final fee = double.tryParse(res?['data']?['delivery_fee']?.toString() ?? '0') ?? 0.0;
      if (mounted) setState(() { _deliveryFee = fee; _feeLoading = false; });
    } catch (_) {
      if (mounted) setState(() => _feeLoading = false);
    }
  }

  double get _grandTotal => widget.subtotal + _deliveryFee - _pointsDiscount;

  @override
  Widget build(BuildContext context) {
    if (_success) return _SuccessView(onDone: () => Navigator.of(context).pop());

    final c = context.colors;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Confirm Order',
            style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
        leading: IconButton(
            icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
            onPressed: () => Navigator.of(context).pop()),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

          // Order summary card
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: c.cardBg, borderRadius: BorderRadius.circular(16),
              boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 12)],
            ),
            child: Column(children: [
              Row(children: [
                Container(
                  width: 44, height: 44,
                  decoration: BoxDecoration(
                    color: AppColors.primary.withOpacity(0.12),
                    borderRadius: BorderRadius.circular(12)),
                  child: const Icon(Icons.local_laundry_service_rounded,
                      color: AppColors.primary, size: 24)),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('eLaundry Order',
                      style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: c.navyText)),
                  Text(widget.serviceType == 'express' ? 'Express — 24 Hours' : 'Normal — 1-3 Days',
                      style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
                ])),
                Text('\$${_grandTotal.toStringAsFixed(2)}',
                    style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900, color: AppColors.primary)),
              ]),
              const SizedBox(height: 14),
              const Divider(),
              const SizedBox(height: 6),

              // Item lines
              ...widget.items.where((it) => (widget.qty[it['id']] ?? 0) > 0).map((it) {
                final price = widget.serviceType == 'express'
                    ? _toD(it['express_price']) : _toD(it['normal_price']);
                return Padding(
                  padding: const EdgeInsets.only(bottom: 6),
                  child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                    Text(it['name'] ?? '', style: TextStyle(fontSize: 13, color: c.navyText)),
                    Text('×${widget.qty[it['id']]}  \$${(price * (widget.qty[it['id']] ?? 0)).toStringAsFixed(2)}',
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: c.navyText)),
                  ]),
                );
              }),

              const SizedBox(height: 4),
              const Divider(),
              const SizedBox(height: 6),

              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text('Subtotal', style: TextStyle(fontSize: 13, color: c.mutedText)),
                Text('\$${widget.subtotal.toStringAsFixed(2)}',
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: c.navyText)),
              ]),
              const SizedBox(height: 6),

              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text('Delivery Fee', style: TextStyle(fontSize: 13, color: c.mutedText)),
                _feeLoading
                    ? const SizedBox(width: 60, height: 14, child: LinearProgressIndicator(minHeight: 2))
                    : Text('\$${_deliveryFee.toStringAsFixed(2)}',
                        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: c.navyText)),
              ]),

              if (_pointsDiscount > 0) ...[
                const SizedBox(height: 6),
                Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                  Text('Points Discount', style: TextStyle(fontSize: 13, color: c.mutedText)),
                  Text('-\$${_pointsDiscount.toStringAsFixed(2)}',
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.success)),
                ]),
              ],

              const SizedBox(height: 8),
              Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                Text('Total', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: c.navyText)),
                Text('\$${_grandTotal.toStringAsFixed(2)}',
                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w900, color: AppColors.primary)),
              ]),
            ]),
          ),

          const SizedBox(height: 22),

          // Delivery Type
          Text('Delivery Type', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: c.navyText)),
          const SizedBox(height: 8),
          Row(children: [
            Expanded(
              child: GestureDetector(
                onTap: () { setState(() { _selfPickup = false; }); _loadDeliveryFee(); },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  decoration: BoxDecoration(
                    color: !_selfPickup ? AppColors.primary.withValues(alpha: 0.10) : c.inputFill,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                        color: !_selfPickup ? AppColors.primary : c.borderColor,
                        width: !_selfPickup ? 2 : 1),
                  ),
                  child: Column(children: [
                    Icon(Icons.local_shipping_rounded,
                        color: !_selfPickup ? AppColors.primary : c.mutedText, size: 22),
                    const SizedBox(height: 4),
                    Text('Home Pickup',
                        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12,
                            color: !_selfPickup ? AppColors.primary : c.mutedText)),
                    Text('We collect & return', style: TextStyle(fontSize: 10, color: c.mutedText)),
                  ]),
                ),
              ),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: GestureDetector(
                onTap: () { setState(() { _selfPickup = true; _deliveryFee = 0.0; }); },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  decoration: BoxDecoration(
                    color: _selfPickup ? AppColors.primary.withValues(alpha: 0.10) : c.inputFill,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                        color: _selfPickup ? AppColors.primary : c.borderColor,
                        width: _selfPickup ? 2 : 1),
                  ),
                  child: Column(children: [
                    Icon(Icons.storefront_rounded,
                        color: _selfPickup ? AppColors.primary : c.mutedText, size: 22),
                    const SizedBox(height: 4),
                    Text('Self Pickup',
                        style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12,
                            color: _selfPickup ? AppColors.primary : c.mutedText)),
                    Text('No delivery fee', style: TextStyle(fontSize: 10, color: c.mutedText)),
                  ]),
                ),
              ),
            ),
          ]),

          if (!_selfPickup) ...[
            const SizedBox(height: 16),
            Text('Your District',
                style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: c.navyText)),
            const SizedBox(height: 4),
            Text('Delivery = Hamarweyne ↔ your district (round trip: pickup + return).',
                style: TextStyle(fontSize: 11, color: c.mutedText)),
            const SizedBox(height: 8),
            _districtsLoading
              ? const LinearProgressIndicator(minHeight: 2)
              : DropdownButtonFormField<int>(
                  value: _districts.any((d) => d['id'] == _districtId)
                      ? _districtId
                      : (_districts.isNotEmpty ? _districts.first['id'] as int : null),
                  onChanged: (v) {
                    if (v == null) return;
                    setState(() => _districtId = v);
                    _loadDeliveryFee();
                  },
                  decoration: InputDecoration(
                    filled: true, fillColor: c.inputFill,
                    border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppColors.divider)),
                    enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppColors.divider)),
                    focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(12),
                        borderSide: const BorderSide(color: AppColors.primary, width: 2)),
                    contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                  ),
                  items: _districts.map((d) => DropdownMenuItem<int>(
                    value: d['id'] as int,
                    child: Text(d['name'] as String),
                  )).toList(),
                ),
          ],

          const SizedBox(height: 22),

          RedeemPointsBar(
            orderTotal: widget.subtotal + _deliveryFee,
            onChanged: (pts, disc) => setState(() { _pointsToRedeem = pts; _pointsDiscount = disc; }),
          ),

          const SizedBox(height: 22),

          Text('Payment Method',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: c.navyText)),
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
            label: _loading ? 'Placing Order...' : 'Confirm Order • \$${_grandTotal.toStringAsFixed(2)}',
            isLoading: _loading,
            onPressed: (_loading || _feeLoading) ? null : () async {
              if (_payMethod == 'wallet') {
                final ok = await showWalletPinDialog(context);
                if (!ok) return;
              } else if (_payMethod == 'mobile_pay') {
                final result = await showMobilePaySheet(context, amount: _grandTotal, description: 'eLaundry Order');
                if (result?.success != true) return;
                _waafiRef = result!.account != null ? 'mobile_pay_${result.account!.id}' : 'mobile_pay';
              } else {
                final result = await showWaafiPaySheet(context, amount: _grandTotal,
                    type: 'order', description: 'eLaundry Order');
                if (result?.success != true) return;
                _waafiRef = result!.reference;
              }
              setState(() => _loading = true);
              try {
                await widget.onConfirm(_districtId, _payMethod, _waafiRef, _pointsToRedeem, _selfPickup);
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
        Container(
          width: 90, height: 90,
          decoration: const BoxDecoration(color: AppColors.success, shape: BoxShape.circle),
          child: const Icon(Icons.check_rounded, color: Colors.white, size: 52)),
        const SizedBox(height: 24),
        Text('Order Placed!',
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.w900,
                color: context.colors.navyText)),
        const SizedBox(height: 10),
        const Text('Your laundry order has been placed.\nWe\'ll notify you when it\'s picked up.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 14, color: AppColors.textGrey, height: 1.5)),
        const SizedBox(height: 32),
        AppButton(label: 'Back to eLaundry', onPressed: onDone),
      ]),
    )),
  );
}
