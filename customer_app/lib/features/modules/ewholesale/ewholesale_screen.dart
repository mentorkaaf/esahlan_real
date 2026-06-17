import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../core/api/module_api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../../../shared/widgets/app_button.dart';
import '../../ads/services/ad_service.dart';

final _svc = ModuleApiService.create();
final _wholesaleCategoriesProvider = FutureProvider((_) => _svc.getWholesaleCategories());
final _wholesaleProductsProvider   = FutureProvider.family<dynamic, Map>((_, p) =>
    _svc.getWholesaleProducts(categoryId: p['category_id'], search: p['search']));

class EWholesaleScreen extends ConsumerStatefulWidget {
  const EWholesaleScreen({super.key});
  @override
  ConsumerState<EWholesaleScreen> createState() => _EWholesaleScreenState();
}

class _EWholesaleScreenState extends ConsumerState<EWholesaleScreen> {
  int?   _categoryId;
  String _search = '';
  final  _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    AdService.instance.triggerModulePopups(context, 'ewholesale');
  }

  @override
  void dispose() { _searchCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final catsAsync     = ref.watch(_wholesaleCategoriesProvider);
    final productsAsync = ref.watch(_wholesaleProductsProvider({'category_id': _categoryId, 'search': _search.isEmpty ? null : _search}));

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white, elevation: 0,
        leading: IconButton(icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20, color: AppColors.secondary), onPressed: () => context.pop()),
        title: const Text('Wholesale', style: TextStyle(fontWeight: FontWeight.w800, color: AppColors.secondary, fontFamily: 'Cairo')),
        bottom: PreferredSize(preferredSize: const Size.fromHeight(60), child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
          child: TextField(
            controller: _searchCtrl,
            onChanged: (v) => setState(() => _search = v),
            decoration: InputDecoration(
              hintText: 'Search bulk products...', hintStyle: const TextStyle(color: AppColors.textGrey, fontSize: 13),
              prefixIcon: const Icon(Icons.search_rounded, color: AppColors.textGrey, size: 20),
              suffixIcon: _search.isNotEmpty ? IconButton(icon: const Icon(Icons.close, size: 16), onPressed: () { _searchCtrl.clear(); setState(() => _search = ''); }) : null,
              filled: true, fillColor: AppColors.surface,
              contentPadding: const EdgeInsets.symmetric(vertical: 10),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
            ),
          ),
        )),
      ),
      body: Column(children: [
        // Categories
        catsAsync.when(
          loading: () => const SizedBox(height: 50, child: Center(child: CircularProgressIndicator())),
          error: (_, __) => const SizedBox(),
          data: (res) {
            final cats = res['data'] as List? ?? [];
            return SizedBox(height: 46, child: ListView.builder(
              scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
              itemCount: cats.length + 1,
              itemBuilder: (_, i) {
                if (i == 0) return _cat(null, 'All', _categoryId == null);
                final c = cats[i - 1];
                return _cat(c['id'], c['name'] ?? '', _categoryId == c['id']);
              },
            ));
          },
        ),

        // Products
        Expanded(child: productsAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(child: Text('Error: $e')),
          data: (res) {
            final products = res['data'] as List? ?? [];
            if (products.isEmpty) return const Center(child: Text('No products found', style: TextStyle(color: AppColors.textGrey)));
            return ListView.separated(
              padding: const EdgeInsets.all(16),
              itemCount: products.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (_, i) {
                final p = products[i];
                return GestureDetector(
                  onTap: () => _showInquiry(context, p),
                  child: Container(
                    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14),
                        boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.05), blurRadius: 8)]),
                    child: Row(children: [
                      // Image
                      ClipRRect(borderRadius: const BorderRadius.horizontal(left: Radius.circular(14)),
                        child: p['image'] != null
                            ? Image.network(fixImgUrl(p['image']), width: 110, height: 110, fit: BoxFit.cover,
                                errorBuilder: (_, __, ___) => Container(width: 110, height: 110, color: AppColors.surface, child: const Icon(Icons.inventory_2_outlined, size: 40, color: AppColors.divider)))
                            : Container(width: 110, height: 110, color: AppColors.surface, child: const Icon(Icons.inventory_2_outlined, size: 40, color: AppColors.divider)),
                      ),
                      Expanded(child: Padding(padding: const EdgeInsets.all(12), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(p['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: AppColors.secondary), maxLines: 2, overflow: TextOverflow.ellipsis),
                        if (p['description'] != null)
                          Text(p['description'], style: const TextStyle(fontSize: 11, color: AppColors.textGrey), maxLines: 1, overflow: TextOverflow.ellipsis),
                        const SizedBox(height: 6),
                        Row(children: [
                          Text('\$${(p['price'] as num).toStringAsFixed(2)}/unit',
                              style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 16, color: AppColors.primary)),
                          if (p['original_price'] != null)
                            Padding(padding: const EdgeInsets.only(left: 6), child: Text('\$${(p['original_price'] as num).toStringAsFixed(2)}',
                                style: const TextStyle(decoration: TextDecoration.lineThrough, color: AppColors.textGrey, fontSize: 12))),
                        ]),
                        const SizedBox(height: 6),
                        if (p['min_qty'] != null)
                          Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(color: const Color(0xFFF39C12).withValues(alpha: 0.1), borderRadius: BorderRadius.circular(6)),
                            child: Text('Min Order: ${p['min_qty']} units',
                                style: const TextStyle(fontSize: 11, color: Color(0xFFF39C12), fontWeight: FontWeight.w700))),
                        const SizedBox(height: 8),
                        Container(padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(8)),
                          child: const Text('Request Quote', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 11))),
                      ]))),
                    ]),
                  ),
                );
              },
            );
          },
        )),
      ]),
    );
  }

  Widget _cat(int? id, String name, bool sel) => GestureDetector(
    onTap: () => setState(() => _categoryId = id),
    child: AnimatedContainer(duration: const Duration(milliseconds: 200),
      margin: const EdgeInsets.only(right: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
      decoration: BoxDecoration(color: sel ? AppColors.primary : Colors.white, borderRadius: BorderRadius.circular(20),
          border: Border.all(color: sel ? AppColors.primary : AppColors.divider)),
      child: Text(name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: sel ? Colors.white : AppColors.secondary)),
    ),
  );

  void _showInquiry(BuildContext context, dynamic p) {
    showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: Colors.white,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
        builder: (_) => _InquirySheet(product: p));
  }
}

class _InquirySheet extends StatefulWidget {
  final dynamic product;
  const _InquirySheet({required this.product});
  @override State<_InquirySheet> createState() => _InquirySheetState();
}

class _InquirySheetState extends State<_InquirySheet> {
  final _nameCtrl    = TextEditingController();
  final _phoneCtrl   = TextEditingController();
  final _messageCtrl = TextEditingController();
  int   _qty = 1;
  bool  _sending = false;

  int get _minQty => (widget.product['min_qty'] as num?)?.toInt() ?? 1;

  @override
  void initState() { super.initState(); _qty = _minQty; }

  @override
  void dispose() { _nameCtrl.dispose(); _phoneCtrl.dispose(); _messageCtrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final price = (widget.product['price'] as num).toDouble();
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: SingleChildScrollView(padding: const EdgeInsets.all(20), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Expanded(child: Text(widget.product['name'] ?? '', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: AppColors.secondary))),
          IconButton(icon: const Icon(Icons.close), onPressed: () => Navigator.pop(context)),
        ]),
        Text('\$${price.toStringAsFixed(2)}/unit', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: AppColors.primary)),
        const SizedBox(height: 16),
        const Text('Quantity', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.secondary)),
        const SizedBox(height: 8),
        Row(children: [
          GestureDetector(onTap: () { if (_qty > _minQty) setState(() => _qty--); },
              child: Container(width: 36, height: 36, decoration: BoxDecoration(color: _qty > _minQty ? AppColors.primary : AppColors.surface, borderRadius: BorderRadius.circular(8)),
                  child: Icon(Icons.remove, color: _qty > _minQty ? Colors.white : AppColors.textGrey, size: 18))),
          SizedBox(width: 60, child: Text('$_qty', textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 20, color: AppColors.secondary))),
          GestureDetector(onTap: () => setState(() => _qty++),
              child: Container(width: 36, height: 36, decoration: BoxDecoration(color: AppColors.primary, borderRadius: BorderRadius.circular(8)),
                  child: const Icon(Icons.add, color: Colors.white, size: 18))),
          const SizedBox(width: 12),
          Text('Est. Total: \$${(price * _qty).toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.secondary)),
        ]),
        if (_minQty > 1)
          Padding(padding: const EdgeInsets.only(top: 4),
              child: Text('Minimum order: $_minQty units', style: const TextStyle(fontSize: 11, color: Color(0xFFF39C12)))),
        const SizedBox(height: 14),
        _field(_nameCtrl, 'Your Name', Icons.person_outlined),
        const SizedBox(height: 10),
        _field(_phoneCtrl, 'Phone / WhatsApp', Icons.phone_outlined, isPhone: true),
        const SizedBox(height: 10),
        TextField(controller: _messageCtrl, maxLines: 3, decoration: InputDecoration(
            hintText: 'Additional requirements or notes...', hintStyle: const TextStyle(fontSize: 12, color: AppColors.textGrey),
            filled: true, fillColor: AppColors.surface,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none))),
        const SizedBox(height: 16),
        AppButton(label: _sending ? 'Sending...' : 'Send Inquiry', isLoading: _sending,
          onPressed: (_nameCtrl.text.trim().isEmpty || _phoneCtrl.text.trim().isEmpty) ? null : _send),
        const SizedBox(height: 8),
      ])),
    );
  }

  Widget _field(TextEditingController ctrl, String hint, IconData icon, {bool isPhone = false}) => TextField(
    controller: ctrl, keyboardType: isPhone ? TextInputType.phone : TextInputType.text,
    onChanged: (_) => setState(() {}),
    decoration: InputDecoration(hintText: hint, hintStyle: const TextStyle(color: AppColors.textGrey, fontSize: 13),
        prefixIcon: Icon(icon, color: AppColors.textGrey, size: 20),
        filled: true, fillColor: AppColors.surface,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider)),
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: const BorderSide(color: AppColors.divider))),
  );

  Future<void> _send() async {
    setState(() => _sending = true);
    try {
      await _svc.inquireWholesale({'product_id': widget.product['id'], 'quantity': _qty,
          'name': _nameCtrl.text.trim(), 'phone': _phoneCtrl.text.trim(), 'message': _messageCtrl.text.trim()});
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Inquiry sent! We\'ll contact you shortly.'), backgroundColor: AppColors.success));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Error: $e'), backgroundColor: AppColors.error));
    } finally { setState(() => _sending = false); }
  }
}
