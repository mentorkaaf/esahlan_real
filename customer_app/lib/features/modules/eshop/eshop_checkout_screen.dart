import 'package:flutter/material.dart';
import '../../../core/theme/theme_x.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/constants/app_constants.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../payment/waafi_pay_sheet.dart';
import '../../payment/mobile_pay_sheet.dart';
import '../../payment/payment_method_section.dart';
import '../../wallet/presentation/providers/wallet_provider.dart';
import '../../auth/data/models/district_model.dart';
import '../../auth/data/repositories/district_repository.dart';
import '../../auth/presentation/providers/auth_provider.dart';
import 'eshop_providers.dart';
import '../../rewards/redeem_points_bar.dart';
import '../../../core/l10n/app_strings.dart';

final _eshopDistrictsProvider = FutureProvider<List<DistrictModel>>(
  (_) => DistrictRepository().getDistricts(),
);

class EShopCheckoutScreen extends ConsumerStatefulWidget {
  const EShopCheckoutScreen({super.key});
  @override
  ConsumerState<EShopCheckoutScreen> createState() => _EShopCheckoutScreenState();
}

class _EShopCheckoutScreenState extends ConsumerState<EShopCheckoutScreen> {
  final _nameCtrl  = TextEditingController();
  final _phoneCtrl = TextEditingController();
  int?    _districtId;
  String? _districtName;
  String _paymentMethod = 'wallet';
  String? _waafiReference;
  String? _mobileProofToken;
  bool _placing = false;
  bool _districtInitialized = false;
  int    _pointsToRedeem  = 0;
  double _pointsDiscount  = 0.0;

  double _deliveryFee = AppConstants.eshopDeliveryFee;
  final _svc = ModuleApiService.create();

  Future<void> _fetchDeliveryFee() async {
    if (_districtId == null) return;
    try {
      final cart = ref.read(eshopCartProvider);
      final vendorId = cart.isNotEmpty ? (cart.first.product['vendor_id'] as num?)?.toInt() : null;
      final res = await _svc.getEshopDeliveryFee(_districtId!, vendorId: vendorId);
      // Backend returns both formats: {delivery_fee:X} and {data:{delivery_fee:X}}
      final raw = res?['data']?['delivery_fee'] ?? res?['delivery_fee'];
      final fee = double.tryParse('${raw ?? ''}') ?? AppConstants.eshopDeliveryFee;
      if (mounted) setState(() => _deliveryFee = fee);
    } catch (_) {}
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _initDistrict());
  }

  void _initDistrict() {
    if (_districtInitialized) return;
    final user = ref.read(authStateProvider).valueOrNull;
    if (user != null) {
      if (_nameCtrl.text.isEmpty)  _nameCtrl.text  = user.name;
      if (_phoneCtrl.text.isEmpty) _phoneCtrl.text = user.phone;
      if (user.districtId != null) {
        _districtId   = user.districtId;
        _districtName = user.districtName;
        _fetchDeliveryFee();
      }
      _districtInitialized = true;
      setState(() {});
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    super.dispose();
  }

  bool get _addressFilled => _districtId != null;

  Future<void> _pickDistrict() async {
    final districts = await ref.read(_eshopDistrictsProvider.future);
    if (!mounted) return;
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _DistrictPickerSheet(
        districts: districts,
        selectedId: _districtId,
        onSelected: (d) {
          setState(() { _districtId = d.id; _districtName = d.name; });
          _fetchDeliveryFee();
          Navigator.pop(context);
        },
      ),
    );
  }

  Future<void> _placeOrder() async {
    if (!_addressFilled) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(
        content: Text(AppL10n.current.selectDistrict2),
        backgroundColor: AppColors.error,
        behavior: SnackBarBehavior.floating,
      ));
      return;
    }

    if (_paymentMethod == 'mobile_pay' || _paymentMethod == 'waafi_pay') {
      final cart     = ref.read(eshopCartProvider);
      final coupon   = ref.read(eshopCouponProvider);
      final subtotal = cart.fold(0.0, (s, c) => s + c.lineTotal);
      final discount = coupon.calculateDiscount(subtotal);
      final total    = subtotal - discount + _deliveryFee;

      if (_paymentMethod == 'mobile_pay') {
        final result = await showMobilePaySheet(context, amount: total, description: 'eSahlan Shop Order');
        if (result?.success != true) return;
        _waafiReference = result!.account != null ? 'mobile_pay_${result.account!.id}' : 'mobile_pay';
      _mobileProofToken = result.proofToken;
      } else {
        final result = await showWaafiPaySheet(
          context,
          amount: total,
          type: 'order',
          description: 'eSahlan Shop Order',
          prefillPhone: _phoneCtrl.text.trim(),
        );
        if (result?.success != true) return;
        _waafiReference = result!.reference;
      }
    }

    if (_paymentMethod == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }

    setState(() => _placing = true);
    try {
      final cart   = ref.read(eshopCartProvider);
      final coupon = ref.read(eshopCouponProvider);
      final items  = cart.map((c) => {
        'product_id': c.productId,
        'quantity':   c.qty,
        if (c.variantId != null) 'variant_id': c.variantId,
      }).toList();

      final body = <String, dynamic>{
        'items':      items,
        'district_id': _districtId,
        'delivery_address': {
          'district':    _districtName ?? '',
          'city':        _districtName ?? '',
          if (_nameCtrl.text.trim().isNotEmpty)  'name':  _nameCtrl.text.trim(),
          if (_phoneCtrl.text.trim().isNotEmpty) 'phone': _phoneCtrl.text.trim(),
        },
        'payment_method': _paymentMethod,
        if (coupon.isValid && coupon.code != null) 'coupon_code': coupon.code,
        if (_waafiReference != null) 'payment_reference': _waafiReference,
        if (_pointsToRedeem > 0) 'points_to_redeem': _pointsToRedeem,
      };

      final orderRes = await _svc.placeShopOrderV2(body);
      final orderNum = (orderRes is Map) ? ((orderRes['data'] ?? orderRes)['order_number'] as String?) : null;
      if (_mobileProofToken != null && orderNum != null) {
        _svc.attachMobilePayProof(orderNum, _mobileProofToken!);
      }

      ref.read(eshopCartProvider.notifier).clear();
      ref.read(eshopCouponProvider.notifier).clear();
      if (_paymentMethod == 'wallet') ref.invalidate(walletProvider);

      if (mounted) _showSuccessDialog();
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text('Failed to place order: $e'),
          backgroundColor: AppColors.error,
          behavior: SnackBarBehavior.floating,
        ));
      }
    } finally {
      if (mounted) setState(() => _placing = false);
    }
  }

  void _showSuccessDialog() {
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (dialogCtx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        content: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: 80, height: 80,
            decoration: BoxDecoration(color: AppColors.success.withValues(alpha: 0.1), shape: BoxShape.circle),
            child: const Icon(Icons.check_circle_rounded, size: 50, color: AppColors.success),
          ),
          const SizedBox(height: 20),
          Text(AppL10n.of(context).orderConfirmed, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 22, color: context.colors.navyText)),
          const SizedBox(height: 8),
          Text(AppL10n.of(context).orderPlacedSuccess,
            textAlign: TextAlign.center, style: const TextStyle(color: AppColors.textGrey, fontSize: 13, height: 1.5)),
          const SizedBox(height: 24),
          AppButton(label: AppL10n.of(context).continueShopping, onPressed: () {
            Navigator.of(dialogCtx).pop();
            context.go('/eshop');
          }),
          const SizedBox(height: 8),
          AppButton(label: AppL10n.of(context).viewOrders, outlined: true, onPressed: () {
            Navigator.of(dialogCtx).pop();
            context.go('/orders');
          }),
        ]),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    // Try to auto-fill district on first build if authState resolves
    ref.listen(authStateProvider, (_, next) {
      if (!_districtInitialized) _initDistrict();
    });

    final cart     = ref.watch(eshopCartProvider);
    final coupon   = ref.watch(eshopCouponProvider);
    final subtotal = cart.fold(0.0, (s, c) => s + c.lineTotal);
    final discount = coupon.calculateDiscount(subtotal);
    final total    = subtotal - discount + _deliveryFee - _pointsDiscount;

    return Scaffold(
      appBar: AppBar(
        elevation: 0,
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20), onPressed: () => context.pop()),
        title: Text(AppL10n.of(context).checkout, style: const TextStyle(fontWeight: FontWeight.w800, fontFamily: 'Cairo')),
      ),
      body: ListView(padding: const EdgeInsets.fromLTRB(16, 16, 16, 100), children: [
        // ── Delivery District ─────────────────────────────────────
        _section(AppL10n.of(context).deliveryAddress, Icons.location_on_outlined, children: [
          _DistrictCard(
            districtName: _districtName,
            onChangeTap: _pickDistrict,
          ),
          const SizedBox(height: 12),
          _field(_nameCtrl, AppL10n.of(context).fullName, Icons.person_outline_rounded),
          const SizedBox(height: 12),
          _field(_phoneCtrl, AppL10n.of(context).phoneNumber, Icons.phone_outlined, keyboardType: TextInputType.phone),
        ]),

        const SizedBox(height: 16),

        // ── Order Items ───────────────────────────────────────────
        _section(AppL10n.of(context).orderSummaryLabel, Icons.receipt_long_outlined, children: [
          ...cart.map((item) => Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Row(children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: item.product['thumbnail'] != null
                    ? NetImage(url: item.product['thumbnail'], width: 50, height: 50, fit: BoxFit.cover,
                        errorWidget: Container(width: 50, height: 50, color: AppColors.surface))
                    : Container(width: 50, height: 50, color: AppColors.surface),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(item.product['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText), maxLines: 1, overflow: TextOverflow.ellipsis),
                if (item.variant != null) Container(
                  margin: const EdgeInsets.only(top: 3),
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.08),
                    borderRadius: BorderRadius.circular(5),
                    border: Border.all(color: AppColors.primary.withValues(alpha: 0.2)),
                  ),
                  child: Text(item.variant!['name'] ?? '', style: const TextStyle(fontSize: 11, color: AppColors.primary, fontWeight: FontWeight.w600)),
                ),
                const SizedBox(height: 3),
                Text('\$${item.effectivePrice.toStringAsFixed(2)} × ${item.qty}', style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
              ])),
              Text('\$${item.lineTotal.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: AppColors.primary)),
            ]),
          )),
          const Divider(height: 8),
          const SizedBox(height: 8),
          _row(AppL10n.of(context).subtotal, '\$${subtotal.toStringAsFixed(2)}'),
          if (coupon.isValid && discount > 0)
            _row('${AppL10n.of(context).coupon} (${coupon.code})', '-\$${discount.toStringAsFixed(2)}', valueColor: AppColors.success),
          if (_pointsDiscount > 0)
            _row('Points ($_pointsToRedeem pts)', '-\$${_pointsDiscount.toStringAsFixed(2)}', valueColor: const Color(0xFFF59E0B)),
          _row(AppL10n.of(context).deliveryFee, '\$${_deliveryFee.toStringAsFixed(2)}'),
          const Divider(height: 16),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text(AppL10n.of(context).total, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: context.colors.navyText)),
            Text('\$${total.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: AppColors.primary)),
          ]),
        ]),

        const SizedBox(height: 16),

        // ── Points Redeem ─────────────────────────────────────────
        RedeemPointsBar(
          orderTotal: subtotal - discount + _deliveryFee,
          onChanged: (pts, disc) => setState(() { _pointsToRedeem = pts; _pointsDiscount = disc; }),
        ),

        // ── Payment Method ────────────────────────────────────────
        _section(AppL10n.of(context).paymentMethod, Icons.payment_outlined, children: [
          PaymentMethodSection(
            selected: _paymentMethod,
            onChanged: (v) => setState(() => _paymentMethod = v),
          ),
        ]),
      ]),
      bottomNavigationBar: Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        decoration: BoxDecoration(
          color: context.colors.cardBg,
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 12, offset: const Offset(0, -2))],
        ),
        child: AppButton(
          label: '${AppL10n.of(context).placeOrder}  •  \$${total.toStringAsFixed(2)}',
          isLoading: _placing,
          onPressed: cart.isEmpty ? null : _placeOrder,
        ),
      ),
    );
  }

  Widget _section(String title, IconData icon, {required List<Widget> children}) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: context.colors.cardBg,
      borderRadius: BorderRadius.circular(14),
      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)],
    ),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Icon(icon, size: 18, color: AppColors.primary),
        const SizedBox(width: 8),
        Text(title, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText)),
      ]),
      const SizedBox(height: 16),
      ...children,
    ]),
  );

  Widget _field(TextEditingController ctrl, String hint, IconData icon, {TextInputType? keyboardType}) => TextField(
    controller: ctrl,
    keyboardType: keyboardType,
    onChanged: (_) => setState(() {}),
    decoration: InputDecoration(
      hintText: hint,
      prefixIcon: Icon(icon, size: 18, color: AppColors.textGrey),
      filled: true, fillColor: context.colors.scaffoldBg,
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.primary, width: 2)),
    ),
  );


  Widget _row(String label, String value, {Color? valueColor}) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: const TextStyle(color: AppColors.textGrey, fontSize: 13)),
      Text(value, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: valueColor ?? context.colors.navyText)),
    ]),
  );
}

// ── Shared widgets ──────────────────────────────────────────────────────────

class _DistrictCard extends StatelessWidget {
  final String? districtName;
  final VoidCallback onChangeTap;
  const _DistrictCard({required this.districtName, required this.onChangeTap});

  @override
  Widget build(BuildContext context) {
    final hasDistrict = districtName != null;
    return GestureDetector(
      onTap: onChangeTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
        decoration: BoxDecoration(
          color: hasDistrict ? AppColors.primary.withValues(alpha: 0.05) : AppColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: hasDistrict ? AppColors.primary.withValues(alpha: 0.4) : AppColors.divider,
            width: hasDistrict ? 1.5 : 1,
          ),
        ),
        child: Row(children: [
          Container(
            width: 40, height: 40,
            decoration: BoxDecoration(
              color: hasDistrict ? AppColors.primary : AppColors.textGrey.withValues(alpha: 0.1),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(Icons.location_on_rounded, size: 20, color: hasDistrict ? Colors.white : AppColors.textGrey),
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(
              hasDistrict ? districtName! : AppL10n.of(context).selectDistrict2,
              style: TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 14,
                color: hasDistrict ? context.colors.navyText : AppColors.textGrey,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              hasDistrict ? AppL10n.of(context).tapToChangeDistrict : AppL10n.of(context).chooseWhereToDeliver,
              style: const TextStyle(fontSize: 11, color: AppColors.textGrey),
            ),
          ])),
          Icon(Icons.chevron_right_rounded, color: AppColors.textGrey.withValues(alpha: 0.6)),
        ]),
      ),
    );
  }
}

class _DistrictPickerSheet extends StatefulWidget {
  final List<DistrictModel> districts;
  final int? selectedId;
  final void Function(DistrictModel) onSelected;
  const _DistrictPickerSheet({required this.districts, required this.selectedId, required this.onSelected});

  @override
  State<_DistrictPickerSheet> createState() => _DistrictPickerSheetState();
}

class _DistrictPickerSheetState extends State<_DistrictPickerSheet> {
  String _search = '';

  @override
  Widget build(BuildContext context) {
    final filtered = widget.districts.where((d) => d.name.toLowerCase().contains(_search.toLowerCase())).toList();

    return Container(
      height: MediaQuery.of(context).size.height * 0.65,
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(children: [
        const SizedBox(height: 8),
        Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2))),
        const SizedBox(height: 16),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16),
          child: Row(children: [
            Expanded(child: Text(AppL10n.of(context).selectDistrictBtn, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17, color: context.colors.navyText))),
            IconButton(icon: const Icon(Icons.close_rounded), onPressed: () => Navigator.pop(context)),
          ]),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
          child: TextField(
            autofocus: true,
            onChanged: (v) => setState(() => _search = v),
            decoration: InputDecoration(
              hintText: AppL10n.of(context).searchDistrict,
              prefixIcon: const Icon(Icons.search_rounded, size: 18, color: AppColors.textGrey),
              filled: true, fillColor: AppColors.surface,
              contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
            ),
          ),
        ),
        Expanded(
          child: ListView.builder(
            itemCount: filtered.length,
            itemBuilder: (_, i) {
              final d = filtered[i];
              final selected = d.id == widget.selectedId;
              return ListTile(
                leading: Container(
                  width: 36, height: 36,
                  decoration: BoxDecoration(
                    color: selected ? AppColors.primary : AppColors.surface,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Icon(Icons.location_on_rounded, size: 18, color: selected ? Colors.white : AppColors.textGrey),
                ),
                title: Text(d.name, style: TextStyle(fontWeight: selected ? FontWeight.w700 : FontWeight.w500, color: selected ? AppColors.primary : context.colors.navyText)),
                trailing: selected ? const Icon(Icons.check_circle_rounded, color: AppColors.primary) : null,
                onTap: () => widget.onSelected(d),
              );
            },
          ),
        ),
      ]),
    );
  }
}
