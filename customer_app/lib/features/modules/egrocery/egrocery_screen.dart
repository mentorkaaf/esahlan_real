import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../../../shared/widgets/app_button.dart';
import '../../ads/services/ad_service.dart';
import '../../../../core/theme/theme_x.dart';

final _svc = ModuleApiService.create();
final _groceryCategoriesProvider = FutureProvider((_) => _svc.getGroceryCategories());
final _groceryProductsProvider   = FutureProvider.family<dynamic, Map?>((_, p) =>
    _svc.getGroceryProducts(categoryId: p?['category_id'], search: p?['search']));

class EGroceryScreen extends ConsumerStatefulWidget {
  const EGroceryScreen({super.key});
  @override
  ConsumerState<EGroceryScreen> createState() => _EGroceryScreenState();
}

class _EGroceryScreenState extends ConsumerState<EGroceryScreen> {
  int?   _categoryId;
  String _search = '';
  final  _searchCtrl = TextEditingController();
  final  Map<int, int> _cart = {}; // productId → qty

  int get _cartCount => _cart.values.fold(0, (s, q) => s + q);

  // We need products list to compute total — stored when built
  List _products = [];

  double get _cartTotal => _products.isEmpty ? 0 : _cart.entries.fold(0.0, (s, e) {
    final p = _products.firstWhere((p) => p['id'] == e.key, orElse: () => {'price': 0});
    return s + (p['price'] as num).toDouble() * e.value;
  });

  @override
  void dispose() { _searchCtrl.dispose(); super.dispose(); }

  static const _catIcons = {
    'Vegetables': Icons.eco_outlined,
    'Fruits':     Icons.local_florist_outlined,
    'Meat':       Icons.restaurant_outlined,
    'Dairy':      Icons.water_drop_outlined,
    'Bakery':     Icons.bakery_dining_outlined,
    'Beverages':  Icons.local_drink_outlined,
    'Snacks':     Icons.fastfood_outlined,
    'Frozen':     Icons.ac_unit_outlined,
  };

  @override
  Widget build(BuildContext context) {
    final catsAsync     = ref.watch(_groceryCategoriesProvider);
    final productsAsync = ref.watch(_groceryProductsProvider(_categoryId != null || _search.isNotEmpty
        ? {'category_id': _categoryId, 'search': _search.isEmpty ? null : _search} : null));

    return Scaffold(
            appBar: AppBar(
                leading: IconButton(icon: Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: context.colors.navyText), onPressed: () => context.pop()),
        title: Text('Grocery', style: TextStyle(fontWeight: FontWeight.w800, color: context.colors.navyText, fontFamily: 'Cairo')),
        actions: [
          Stack(children: [
            IconButton(icon: Icon(Icons.shopping_cart_outlined, color: context.colors.navyText), onPressed: _cartCount > 0 ? () => _showCart(context) : null),
            if (_cartCount > 0) Positioned(right: 6, top: 6, child: Container(
              width: 16, height: 16, decoration: const BoxDecoration(color: AppColors.primary, shape: BoxShape.circle),
              child: Center(child: Text('$_cartCount', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w900))),
            )),
          ]),
        ],
      ),
      body: Column(children: [
        // Search
        Container(color: context.colors.cardBg, padding: const EdgeInsets.fromLTRB(16, 4, 16, 10),
          child: TextField(
            controller: _searchCtrl,
            onChanged: (v) => setState(() => _search = v),
            decoration: InputDecoration(
              hintText: 'Search groceries...', hintStyle: TextStyle(color: AppColors.textGrey, fontSize: 13),
              prefixIcon: Icon(Icons.search_rounded, color: AppColors.textGrey, size: 20),
              suffixIcon: _search.isNotEmpty ? IconButton(icon: Icon(Icons.close, size: 16), onPressed: () { _searchCtrl.clear(); setState(() => _search = ''); }) : null,
              filled: true, fillColor: context.colors.cardBg,
              contentPadding: const EdgeInsets.symmetric(vertical: 10),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
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
                if (i == 0) {
                  final sel = _categoryId == null;
                  return GestureDetector(onTap: () => setState(() => _categoryId = null), child: AnimatedContainer(
                    duration: const Duration(milliseconds: 200), margin: const EdgeInsets.only(right: 10),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                    decoration: BoxDecoration(color: sel ? AppColors.primary : Colors.white, borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: sel ? AppColors.primary : AppColors.divider)),
                    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                      Icon(Icons.grid_view_rounded, color: sel ? Colors.white : AppColors.textGrey, size: 22),
                      const SizedBox(height: 4),
                      Text('All', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: sel ? Colors.white : AppColors.secondary)),
                    ]),
                  ));
                }
                final cat = cats[i - 1];
                final sel = _categoryId == cat['id'];
                final icon = _catIcons[cat['name']] ?? Icons.local_grocery_store_outlined;
                return GestureDetector(onTap: () => setState(() => _categoryId = cat['id']), child: AnimatedContainer(
                  duration: const Duration(milliseconds: 200), margin: const EdgeInsets.only(right: 10),
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
                  decoration: BoxDecoration(color: sel ? AppColors.primary : Colors.white, borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: sel ? AppColors.primary : AppColors.divider)),
                  child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Icon(icon, color: sel ? Colors.white : AppColors.primary, size: 22),
                    const SizedBox(height: 4),
                    Text(cat['name'] ?? '', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: sel ? Colors.white : AppColors.secondary)),
                  ]),
                ));
              },
            ));
          },
        ),

        // Products
        Expanded(child: productsAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(child: Text('Error: $e')),
          data: (res) {
            _products = res['data'] as List? ?? [];
            if (_products.isEmpty) return const Center(child: Text('No products found', style: TextStyle(color: AppColors.textGrey)));
            return GridView.builder(
              padding: const EdgeInsets.all(16),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2, childAspectRatio: 0.78, crossAxisSpacing: 12, mainAxisSpacing: 12),
              itemCount: _products.length,
              itemBuilder: (_, i) {
                final p   = _products[i];
                final qty = _cart[p['id']] ?? 0;
                return Container(
                  decoration: BoxDecoration(color: context.colors.cardBg, borderRadius: BorderRadius.circular(14),
                      boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Expanded(child: ClipRRect(borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
                      child: p['image'] != null
                          ? Image.network(fixImgUrl(p['image']), fit: BoxFit.cover, width: double.infinity, height: double.infinity,
                              errorBuilder: (_, __, ___) => Container(color: AppColors.surface, child: Icon(Icons.local_grocery_store_outlined, size: 40, color: AppColors.divider)))
                          : Container(color: AppColors.surface, child: Icon(Icons.local_grocery_store_outlined, size: 40, color: AppColors.divider)),
                    )),
                    Padding(padding: const EdgeInsets.all(10), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(p['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: context.colors.navyText), maxLines: 2, overflow: TextOverflow.ellipsis),
                      if (p['unit'] != null) Text(p['unit'], style: const TextStyle(fontSize: 10, color: AppColors.textGrey)),
                      const SizedBox(height: 6),
                      Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
                        Text('\$${(p['price'] as num).toStringAsFixed(2)}',
                            style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: AppColors.primary)),
                        qty == 0
                            ? GestureDetector(onTap: () => setState(() => _cart[p['id']] = 1),
                                child: Container(width: 28, height: 28, decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(8)),
                                    child: Icon(Icons.add, color: Colors.white, size: 16)))
                            : Row(mainAxisSize: MainAxisSize.min, children: [
                                GestureDetector(onTap: () => setState(() { if (qty > 1) _cart[p['id']] = qty - 1; else _cart.remove(p['id']); }),
                                    child: Container(width: 24, height: 24, decoration: BoxDecoration(color: AppColors.primary.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                                        child: Icon(Icons.remove, color: AppColors.primary, size: 14))),
                                Padding(padding: const EdgeInsets.symmetric(horizontal: 6),
                                    child: Text('$qty', style: TextStyle(fontWeight: FontWeight.w900, fontSize: 14, color: context.colors.navyText))),
                                GestureDetector(onTap: () => setState(() => _cart[p['id']] = qty + 1),
                                    child: Container(width: 24, height: 24, decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(6)),
                                        child: const Icon(Icons.add, color: Colors.white, size: 14))),
                              ]),
                      ]),
                    ])),
                  ]),
                );
              },
            );
          },
        )),
      ]),
      bottomNavigationBar: _cartCount > 0 ? Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: context.colors.cardBg, boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.06), blurRadius: 10, offset: const Offset(0, -2))]),
        child: AppButton(label: 'Checkout ($_cartCount items) • \$${_cartTotal.toStringAsFixed(2)}', onPressed: () => _showCart(context)),
      ) : null,
    );
  }

  void _showCart(BuildContext context) {
    showModalBottomSheet(context: context, isScrollControlled: true, 
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
        builder: (_) => _GroceryCartSheet(cart: _cart, products: _products, onUpdate: () => setState(() {})));
  }
}

class _GroceryCartSheet extends StatefulWidget {
  final Map<int, int> cart;
  final List products;
  final VoidCallback onUpdate;
  const _GroceryCartSheet({required this.cart, required this.products, required this.onUpdate});
  @override State<_GroceryCartSheet> createState() => _GroceryCartSheetState();
}

class _GroceryCartSheetState extends State<_GroceryCartSheet> {
  bool _ordering = false;
  final _addrCtrl = TextEditingController();

  @override
  void dispose() { _addrCtrl.dispose(); super.dispose(); }

  double get _subtotal => widget.cart.entries.fold(0.0, (s, e) {
    final p = widget.products.firstWhere((p) => p['id'] == e.key, orElse: () => {'price': 0});
    return s + (p['price'] as num).toDouble() * e.value;
  });

  @override
  Widget build(BuildContext context) {
    const deliveryFee = 1.50;
    final total = _subtotal + deliveryFee;
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Padding(padding: const EdgeInsets.all(16), child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text('Your Order', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: context.colors.navyText)),
          IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
        ])),
        Flexible(child: ListView(shrinkWrap: true, padding: const EdgeInsets.symmetric(horizontal: 16), children: [
          ...widget.cart.entries.map((e) {
            final p = widget.products.firstWhere((p) => p['id'] == e.key, orElse: () => null);
            if (p == null) return const SizedBox();
            return Padding(padding: const EdgeInsets.only(bottom: 10), child: Row(children: [
              ClipRRect(borderRadius: BorderRadius.circular(8), child: p['image'] != null
                  ? Image.network(p['image'], width: 48, height: 48, fit: BoxFit.cover, errorBuilder: (_, __, ___) => Container(width: 48, height: 48, color: AppColors.surface))
                  : Container(width: 48, height: 48, color: AppColors.surface)),
              SizedBox(width: 10),
              Expanded(child: Text(p['name'] ?? '', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText))),
              Text('\$${((p['price'] as num).toDouble() * e.value).toStringAsFixed(2)}',
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.primary)),
              const SizedBox(width: 4),
              Text('x${e.value}', style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
            ]));
          }),
          const Divider(),
          _row('Subtotal',     '\$${_subtotal.toStringAsFixed(2)}'),
          _row('Delivery Fee', '\$${deliveryFee.toStringAsFixed(2)}'),
          const SizedBox(height: 4),
          _row('Total', '\$${total.toStringAsFixed(2)}', bold: true),
          SizedBox(height: 14),
          TextField(controller: _addrCtrl, onChanged: (_) => setState(() {}), decoration: InputDecoration(
              labelText: 'Delivery Address', prefixIcon: Icon(Icons.location_on_outlined, color: AppColors.textGrey, size: 20),
              filled: true, fillColor: context.colors.cardBg,
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)))),
          const SizedBox(height: 14),
          AppButton(label: _ordering ? 'Placing Order...' : 'Place Grocery Order', isLoading: _ordering,
              onPressed: _addrCtrl.text.trim().isEmpty ? null : _order),
          const SizedBox(height: 16),
        ])),
      ]),
    );
  }

  Widget _row(String label, String value, {bool bold = false}) => Padding(padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(label, style: TextStyle(color: AppColors.textGrey, fontSize: 13, fontWeight: bold ? FontWeight.w800 : FontWeight.w400)),
        Text(value, style: TextStyle(fontWeight: FontWeight.w800, fontSize: bold ? 16 : 13,
            color: bold ? AppColors.primary : AppColors.secondary)),
      ]));

  Future<void> _order() async {
    setState(() => _ordering = true);
    try {
      final items = widget.cart.entries.map((e) => {'product_id': e.key, 'qty': e.value}).toList();
      await _svc.placeGroceryOrder({'items': items, 'delivery_address': _addrCtrl.text.trim(), 'payment_method': 'cod'});
      if (mounted) {
        widget.cart.clear(); widget.onUpdate();
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Grocery order placed!'), backgroundColor: AppColors.success));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.error));
    } finally { setState(() => _ordering = false); }
  }
}
