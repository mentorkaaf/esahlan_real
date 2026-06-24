import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../../../shared/widgets/app_button.dart';
import '../../../shared/widgets/wallet_pin_dialog.dart';
import '../../wallet/presentation/providers/wallet_provider.dart';
import '../../auth/data/models/district_model.dart';
import '../../auth/data/repositories/district_repository.dart';
import '../../auth/presentation/providers/auth_provider.dart';
import '../../../core/theme/theme_x.dart';

final _svc = ModuleApiService.create();
final _catsProvider = FutureProvider((_) => _svc.getGroceryCategories());
final _prodsProvider = FutureProvider.family<dynamic, String>((_, key) {
  final parts = key.split('|');
  final catId = parts[0].isEmpty ? null : int.tryParse(parts[0]);
  final search = parts.length > 1 && parts[1].isNotEmpty ? parts[1] : null;
  return _svc.getGroceryProducts(categoryId: catId, search: search);
});
final _districtsProvider = FutureProvider<List<DistrictModel>>((_) => DistrictRepository().getDistricts());

// ══════════════════════════════════════════════════════════════════
// CART STATE
// ══════════════════════════════════════════════════════════════════

double _toDouble(dynamic v) => v == null ? 0 : (v is num ? v.toDouble() : double.tryParse('$v') ?? 0);
double _effectivePrice(Map<String, dynamic> p) { final s = _toDouble(p['sale_price']); return s > 0 ? s : _toDouble(p['price']); }

class _CartItem {
  final Map<String, dynamic> product;
  int qty;
  _CartItem(this.product, [this.qty = 1]);
  double get lineTotal => _effectivePrice(product) * qty;
  double get unitPrice => _effectivePrice(product);
}

class _CartNotifier extends StateNotifier<List<_CartItem>> {
  _CartNotifier() : super([]);
  void add(Map<String, dynamic> product) {
    final i = state.indexWhere((e) => e.product['id'] == product['id']);
    if (i >= 0) { state[i].qty++; state = [...state]; } else { state = [...state, _CartItem(product)]; }
  }
  void increment(int productId) { final i = state.indexWhere((e) => e.product['id'] == productId); if (i >= 0) { state[i].qty++; state = [...state]; } }
  void decrement(int productId) {
    final i = state.indexWhere((e) => e.product['id'] == productId);
    if (i >= 0) { if (state[i].qty > 1) { state[i].qty--; state = [...state]; } else { state = [...state]..removeAt(i); } }
  }
  void remove(int productId) { state = state.where((e) => e.product['id'] != productId).toList(); }
  void clear() => state = [];
  int get totalItems => state.fold(0, (s, e) => s + e.qty);
  double get subtotal => state.fold(0, (s, e) => s + e.lineTotal);
  int qtyOf(int productId) => state.where((e) => e.product['id'] == productId).fold(0, (s, e) => s + e.qty);
}

final _cartProvider = StateNotifierProvider<_CartNotifier, List<_CartItem>>((_) => _CartNotifier());

// ══════════════════════════════════════════════════════════════════
// MAIN SCREEN
// ══════════════════════════════════════════════════════════════════

class EGroceryScreen extends ConsumerStatefulWidget {
  const EGroceryScreen({super.key});
  @override
  ConsumerState<EGroceryScreen> createState() => _EGroceryScreenState();
}

class _EGroceryScreenState extends ConsumerState<EGroceryScreen> {
  int? _categoryId;
  String _search = '';
  final _searchCtrl = TextEditingController();

  @override
  void dispose() { _searchCtrl.dispose(); super.dispose(); }

  String get _key => '${_categoryId ?? ''}|$_search|';

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(_cartProvider);
    final cartCount = ref.read(_cartProvider.notifier).totalItems;
    final catsAsync = ref.watch(_catsProvider);
    final prodsAsync = ref.watch(_prodsProvider(_key));

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(icon: Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: context.colors.navyText), onPressed: () => context.pop()),
        title: Text('eGrocery', style: TextStyle(fontWeight: FontWeight.w800, color: context.colors.navyText, fontFamily: 'Cairo')),
        actions: [
          Stack(children: [
            IconButton(icon: Icon(Icons.shopping_cart_outlined, color: context.colors.navyText),
              onPressed: cartCount > 0 ? () => Navigator.push(context, MaterialPageRoute(builder: (_) => const _CheckoutPage())) : null),
            if (cartCount > 0) Positioned(right: 6, top: 6, child: Container(
              width: 18, height: 18, decoration: const BoxDecoration(color: AppColors.primary, shape: BoxShape.circle),
              child: Center(child: Text('$cartCount', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w900))),
            )),
          ]),
        ],
      ),
      body: Column(children: [
        // Search
        Container(color: context.colors.cardBg, padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
          child: TextField(
            controller: _searchCtrl, onChanged: (v) => setState(() => _search = v),
            decoration: InputDecoration(
              hintText: 'Search groceries...', hintStyle: const TextStyle(color: AppColors.textGrey, fontSize: 13),
              prefixIcon: const Icon(Icons.search_rounded, color: AppColors.textGrey, size: 20),
              suffixIcon: _search.isNotEmpty ? IconButton(icon: const Icon(Icons.close, size: 16), onPressed: () { _searchCtrl.clear(); setState(() => _search = ''); }) : null,
              filled: true, fillColor: context.colors.scaffoldBg,
              contentPadding: const EdgeInsets.symmetric(vertical: 10),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
            ),
          ),
        ),

        // Categories
        catsAsync.when(
          loading: () => const SizedBox(height: 80),
          error: (_, __) => const SizedBox(),
          data: (res) {
            final cats = res['data'] as List? ?? [];
            return SizedBox(height: 84, child: ListView.builder(
              scrollDirection: Axis.horizontal, padding: const EdgeInsets.fromLTRB(16, 6, 16, 6),
              itemCount: cats.length + 1,
              itemBuilder: (_, i) {
                if (i == 0) return _CatChip(label: 'All', icon: Icons.grid_view_rounded, selected: _categoryId == null, onTap: () => setState(() => _categoryId = null));
                final c = cats[i - 1];
                return _CatChip(label: c['name'] ?? '', imageUrl: c['image'], selected: _categoryId == c['id'], onTap: () => setState(() => _categoryId = c['id']));
              },
            ));
          },
        ),

        // Products
        Expanded(child: prodsAsync.when(
          loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
          error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: AppColors.error))),
          data: (res) {
            final products = res['data'] as List? ?? [];
            if (products.isEmpty) return const Center(child: Text('No products found', style: TextStyle(color: AppColors.textGrey)));
            return GridView.builder(
              padding: const EdgeInsets.all(14),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: 2, childAspectRatio: 0.72, crossAxisSpacing: 12, mainAxisSpacing: 12),
              itemCount: products.length,
              itemBuilder: (_, i) => _ProductCard(product: products[i]),
            );
          },
        )),
      ]),

      // Bottom cart bar
      bottomNavigationBar: cartCount > 0 ? Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: context.colors.cardBg, boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10, offset: const Offset(0, -2))]),
        child: AppButton(
          label: 'Checkout ($cartCount items) • \$${ref.read(_cartProvider.notifier).subtotal.toStringAsFixed(2)}',
          onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const _CheckoutPage())),
        ),
      ) : null,
    );
  }
}

// ── Category Chip ─────────────────────────────────────────────────

class _CatChip extends StatelessWidget {
  final String label; final IconData? icon; final String? imageUrl; final bool selected; final VoidCallback onTap;
  const _CatChip({required this.label, this.icon, this.imageUrl, required this.selected, required this.onTap});
  @override
  Widget build(BuildContext context) => GestureDetector(onTap: onTap, child: AnimatedContainer(
    duration: const Duration(milliseconds: 200), margin: const EdgeInsets.only(right: 10),
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
    decoration: BoxDecoration(color: selected ? AppColors.primary : context.colors.cardBg, borderRadius: BorderRadius.circular(12),
      border: Border.all(color: selected ? AppColors.primary : AppColors.divider)),
    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      if (imageUrl != null && imageUrl!.isNotEmpty)
        ClipRRect(borderRadius: BorderRadius.circular(8), child: NetImage(url: imageUrl, width: 28, height: 28, fit: BoxFit.cover))
      else Icon(icon ?? Icons.local_grocery_store_outlined, color: selected ? Colors.white : AppColors.primary, size: 22),
      const SizedBox(height: 4),
      Text(label, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: selected ? Colors.white : context.colors.navyText), maxLines: 1, overflow: TextOverflow.ellipsis),
    ]),
  ));
}

// ── Product Card ──────────────────────────────────────────────────

class _ProductCard extends ConsumerWidget {
  final Map<String, dynamic> product;
  const _ProductCard({required this.product});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = product;
    final price = _toDouble(p['price']);
    final salePrice = _toDouble(p['sale_price']);
    final effectivePrice = salePrice > 0 ? salePrice : price;
    final hasDiscount = salePrice > 0 && salePrice < price;
    final qty = ref.watch(_cartProvider.notifier).qtyOf(p['id']);
    final imgUrl = p['thumbnail'] ?? p['image'];

    return Container(
      decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(16),
        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Image
        Expanded(child: Stack(children: [
          ClipRRect(borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
            child: imgUrl != null
                ? NetImage(url: imgUrl, fit: BoxFit.cover, width: double.infinity)
                : Container(color: AppColors.surface, child: const Center(child: Icon(Icons.local_grocery_store_outlined, size: 36, color: AppColors.divider)))),
          if (hasDiscount) Positioned(top: 8, left: 8, child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            decoration: BoxDecoration(color: AppColors.error, borderRadius: BorderRadius.circular(6)),
            child: Text('-${((1 - salePrice / price) * 100).toInt()}%', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800)),
          )),
          if (p['is_featured'] == true || p['is_featured'] == 1) Positioned(top: 8, right: 8, child: Container(
            padding: const EdgeInsets.all(4), decoration: BoxDecoration(color: Colors.amber.withValues(alpha: 0.9), shape: BoxShape.circle),
            child: const Icon(Icons.star_rounded, size: 12, color: Colors.white),
          )),
        ])),

        // Info
        Padding(padding: const EdgeInsets.fromLTRB(10, 8, 10, 10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(p['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: context.colors.navyText), maxLines: 2, overflow: TextOverflow.ellipsis),
          if (p['unit'] != null) Text(p['unit'], style: const TextStyle(fontSize: 10, color: AppColors.textGrey)),
          const SizedBox(height: 6),
          Row(children: [
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('\$${effectivePrice.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: AppColors.primary)),
              if (hasDiscount) Text('\$${price.toStringAsFixed(2)}', style: const TextStyle(fontSize: 10, color: AppColors.textGrey, decoration: TextDecoration.lineThrough)),
            ]),
            const Spacer(),
            qty == 0
                ? GestureDetector(onTap: () => ref.read(_cartProvider.notifier).add(p),
                    child: Container(width: 30, height: 30, decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(8)),
                      child: const Icon(Icons.add, color: Colors.white, size: 18)))
                : Row(mainAxisSize: MainAxisSize.min, children: [
                    GestureDetector(onTap: () => ref.read(_cartProvider.notifier).decrement(p['id']),
                      child: Container(width: 26, height: 26, decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                        child: const Icon(Icons.remove, color: AppColors.primary, size: 14))),
                    SizedBox(width: 28, child: Text('$qty', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: context.colors.navyText))),
                    GestureDetector(onTap: () => ref.read(_cartProvider.notifier).increment(p['id']),
                      child: Container(width: 26, height: 26, decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(6)),
                        child: const Icon(Icons.add, color: Colors.white, size: 14))),
                  ]),
          ]),
        ])),
      ]),
    );
  }
}

// ══════════════════════════════════════════════════════════════════
// CHECKOUT PAGE
// ══════════════════════════════════════════════════════════════════

class _CheckoutPage extends ConsumerStatefulWidget {
  const _CheckoutPage();
  @override
  ConsumerState<_CheckoutPage> createState() => _CheckoutPageState();
}

class _CheckoutPageState extends ConsumerState<_CheckoutPage> {
  int? _districtId;
  String? _districtName;
  String _payment = 'wallet';
  bool _ordering = false;
  bool _districtInit = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _initDistrict());
  }

  void _initDistrict() {
    if (_districtInit) return;
    final user = ref.read(authStateProvider).valueOrNull;
    if (user != null && user.districtId != null) {
      setState(() { _districtId = user.districtId; _districtName = user.districtName; _districtInit = true; });
    }
  }

  Future<void> _pickDistrict() async {
    final districts = await ref.read(_districtsProvider.future);
    if (!mounted) return;
    showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: Colors.transparent,
      builder: (_) => _DistrictSheet(districts: districts, selectedId: _districtId, onSelected: (d) {
        setState(() { _districtId = d.id; _districtName = d.name; });
        Navigator.pop(context);
      }),
    );
  }

  Future<void> _placeOrder() async {
    if (_districtId == null) { _snack('Select delivery district'); return; }
    final cart = ref.read(_cartProvider);
    if (cart.isEmpty) return;

    if (_payment == 'wallet') {
      final pinOk = await showWalletPinDialog(context);
      if (!pinOk) return;
    }

    setState(() => _ordering = true);
    try {
      final items = cart.map((e) => {'product_id': e.product['id'], 'quantity': e.qty}).toList();
      await _svc.placeGroceryOrder({
        'items': items,
        'district_id': _districtId,
        'delivery_address': {'district': _districtName, 'city': _districtName},
        'payment_method': _payment,
      });
      ref.read(_cartProvider.notifier).clear();
      if (_payment == 'wallet') ref.invalidate(walletProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Grocery order placed! 🛒'), backgroundColor: AppColors.success));
        Navigator.pop(context);
      }
    } catch (e) {
      _snack('$e');
    } finally { if (mounted) setState(() => _ordering = false); }
  }

  void _snack(String msg) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: AppColors.error, behavior: SnackBarBehavior.floating));

  @override
  Widget build(BuildContext context) {
    ref.listen(authStateProvider, (_, __) { if (!_districtInit) _initDistrict(); });
    final cart = ref.watch(_cartProvider);
    final subtotal = ref.read(_cartProvider.notifier).subtotal;
    const deliveryFee = 1.50; // Will be calculated by backend with zone pricing
    final total = subtotal + deliveryFee;

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20), onPressed: () => Navigator.pop(context)),
        title: const Text('Checkout', style: TextStyle(fontWeight: FontWeight.w800, fontFamily: 'Cairo')),
      ),
      body: ListView(padding: const EdgeInsets.fromLTRB(16, 16, 16, 100), children: [
        // Delivery District
        _section('Delivery Address', Icons.location_on_outlined, children: [
          GestureDetector(
            onTap: _pickDistrict,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
              decoration: BoxDecoration(
                color: _districtId != null ? AppColors.primary.withValues(alpha: 0.05) : AppColors.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _districtId != null ? AppColors.primary.withValues(alpha: 0.4) : AppColors.divider),
              ),
              child: Row(children: [
                Icon(Icons.location_on_rounded, color: _districtId != null ? AppColors.primary : AppColors.textGrey, size: 20),
                const SizedBox(width: 12),
                Expanded(child: Text(_districtName ?? 'Select delivery district',
                  style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: _districtId != null ? context.colors.navyText : AppColors.textGrey))),
                const Icon(Icons.chevron_right_rounded, color: AppColors.textGrey),
              ]),
            ),
          ),
        ]),
        const SizedBox(height: 16),

        // Order Items
        _section('Order Summary', Icons.receipt_long_outlined, children: [
          ...cart.map((item) => Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Row(children: [
              ClipRRect(borderRadius: BorderRadius.circular(8),
                child: item.product['thumbnail'] != null
                    ? NetImage(url: item.product['thumbnail'], width: 48, height: 48, fit: BoxFit.cover)
                    : Container(width: 48, height: 48, color: AppColors.surface)),
              const SizedBox(width: 10),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(item.product['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText), maxLines: 1, overflow: TextOverflow.ellipsis),
                Text('${item.product['unit'] ?? 'piece'} × ${item.qty}', style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
              ])),
              Text('\$${item.lineTotal.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: AppColors.primary)),
            ]),
          )),
          const Divider(height: 8),
          const SizedBox(height: 8),
          _priceRow('Subtotal', '\$${subtotal.toStringAsFixed(2)}'),
          _priceRow('Delivery Fee', '\$${deliveryFee.toStringAsFixed(2)}'),
          const Divider(height: 16),
          Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text('Total', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: context.colors.navyText)),
            Text('\$${total.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: AppColors.primary)),
          ]),
        ]),
        const SizedBox(height: 16),

        // Payment
        _section('Payment Method', Icons.payment_outlined, children: [
          _paymentOption('wallet', 'Wallet', Icons.account_balance_wallet_outlined, 'Pay from wallet'),
          const SizedBox(height: 10),
          _paymentOption('waafi_pay', 'Waafi Pay', Icons.phone_android_rounded, 'EVC / eDahab'),
        ]),
      ]),
      bottomNavigationBar: Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
        decoration: BoxDecoration(color: context.colors.cardBg, boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 12, offset: const Offset(0, -2))]),
        child: AppButton(
          label: 'Place Order • \$${total.toStringAsFixed(2)}',
          isLoading: _ordering,
          onPressed: cart.isEmpty ? null : _placeOrder,
        ),
      ),
    );
  }

  Widget _section(String title, IconData icon, {required List<Widget> children}) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(14),
      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [Icon(icon, size: 18, color: AppColors.primary), const SizedBox(width: 8),
        Text(title, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15, color: context.colors.navyText))]),
      const SizedBox(height: 16), ...children,
    ]),
  );

  Widget _priceRow(String label, String value) => Padding(padding: const EdgeInsets.only(bottom: 8),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: const TextStyle(color: AppColors.textGrey, fontSize: 13)),
      Text(value, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText)),
    ]));

  Widget _paymentOption(String value, String label, IconData icon, String subtitle) {
    final selected = _payment == value;
    return GestureDetector(onTap: () => setState(() => _payment = value), child: AnimatedContainer(
      duration: const Duration(milliseconds: 200), padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: selected ? AppColors.primary.withValues(alpha: 0.05) : AppColors.surface, borderRadius: BorderRadius.circular(12),
        border: Border.all(color: selected ? AppColors.primary : AppColors.divider, width: selected ? 2 : 1)),
      child: Row(children: [
        Container(width: 44, height: 44, decoration: BoxDecoration(color: selected ? AppColors.primary : context.colors.cardBg, borderRadius: BorderRadius.circular(12)),
          child: Icon(icon, size: 22, color: selected ? Colors.white : AppColors.textGrey)),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: selected ? AppColors.primary : context.colors.navyText)),
          Text(subtitle, style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
        ])),
        if (selected) const Icon(Icons.check_circle_rounded, color: AppColors.primary),
      ]),
    ));
  }
}

// ── District Picker Sheet ─────────────────────────────────────────

class _DistrictSheet extends StatefulWidget {
  final List<DistrictModel> districts; final int? selectedId; final void Function(DistrictModel) onSelected;
  const _DistrictSheet({required this.districts, required this.selectedId, required this.onSelected});
  @override State<_DistrictSheet> createState() => _DistrictSheetState();
}

class _DistrictSheetState extends State<_DistrictSheet> {
  String _search = '';
  @override
  Widget build(BuildContext context) {
    final filtered = widget.districts.where((d) => d.name.toLowerCase().contains(_search.toLowerCase())).toList();
    return Container(
      height: MediaQuery.of(context).size.height * 0.65,
      decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: const BorderRadius.vertical(top: Radius.circular(20))),
      child: Column(children: [
        const SizedBox(height: 8),
        Container(width: 40, height: 4, decoration: BoxDecoration(color: AppColors.divider, borderRadius: BorderRadius.circular(2))),
        Padding(padding: const EdgeInsets.all(16), child: TextField(
          autofocus: true, onChanged: (v) => setState(() => _search = v),
          decoration: InputDecoration(hintText: 'Search district...', prefixIcon: const Icon(Icons.search, size: 18, color: AppColors.textGrey),
            filled: true, fillColor: AppColors.surface, contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none)),
        )),
        Expanded(child: ListView.builder(itemCount: filtered.length, itemBuilder: (_, i) {
          final d = filtered[i]; final sel = d.id == widget.selectedId;
          return ListTile(
            leading: Container(width: 36, height: 36, decoration: BoxDecoration(color: sel ? AppColors.primary : AppColors.surface, borderRadius: BorderRadius.circular(8)),
              child: Icon(Icons.location_on_rounded, size: 18, color: sel ? Colors.white : AppColors.textGrey)),
            title: Text(d.name, style: TextStyle(fontWeight: sel ? FontWeight.w700 : FontWeight.w500, color: sel ? AppColors.primary : context.colors.navyText)),
            trailing: sel ? const Icon(Icons.check_circle_rounded, color: AppColors.primary) : null,
            onTap: () => widget.onSelected(d),
          );
        })),
      ]),
    );
  }
}
