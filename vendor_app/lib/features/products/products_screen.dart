import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';
import '../../core/services/vendor_repository.dart';
import '../../core/theme/vc.dart';

final _catsProvider  = FutureProvider.autoDispose<List<dynamic>>((ref) => VendorRepository.instance.categories());
final _prodsProvider = FutureProvider.autoDispose.family<Map<String, dynamic>, int?>((ref, catId) =>
  VendorRepository.instance.products(categoryId: catId));

class ProductsScreen extends ConsumerStatefulWidget {
  const ProductsScreen({super.key});
  @override
  ConsumerState<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends ConsumerState<ProductsScreen> {
  int? _selectedCat;

  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);

  @override
  Widget build(BuildContext context) {
    final cats  = ref.watch(_catsProvider);
    final prods = ref.watch(_prodsProvider(_selectedCat));

    return Scaffold(
      appBar: AppBar(
        title: const Text('Menu / Products'),
        actions: [
          IconButton(icon: const Icon(Icons.add_circle_rounded, color: VC.orange, size: 28),
            onPressed: () => _showProductForm(context)),
        ],
      ),
      body: Column(children: [
        // Category filter chips
        cats.maybeWhen(
          data: (list) => list.isEmpty ? const SizedBox() : SizedBox(height: 52,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              itemCount: list.length + 1,
              itemBuilder: (_, i) {
                if (i == 0) return _catChip(null, 'All');
                final c = list[i - 1] as Map<String, dynamic>;
                return _catChip(c['id'] as int?, c['name'] ?? '');
              },
            )),
          orElse: () => const SizedBox(),
        ),

        // Products
        Expanded(child: prods.when(
          loading: () => const Center(child: CircularProgressIndicator(color: VC.orange)),
          error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: VC.red))),
          data: (res) {
            final list = res['data'] as List? ?? [];
            if (list.isEmpty) return _empty();
            return RefreshIndicator(
              color: VC.orange,
              onRefresh: () async { ref.invalidate(_prodsProvider(_selectedCat)); },
              child: ListView.builder(
                padding: const EdgeInsets.all(14),
                itemCount: list.length,
                itemBuilder: (_, i) {
                  final p = list[i] as Map<String, dynamic>;
                  return _ProductTile(
                    product: p,
                    onEdit: () => _showProductForm(context, product: p),
                    onToggle: () => _toggle(p['id']),
                    onDelete: () => _delete(context, p['id'], p['name'] ?? ''),
                  );
                },
              ),
            );
          },
        )),
      ]),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: VC.orange,
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('Add Product', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
        onPressed: () => _showProductForm(context),
      ),
    );
  }

  Widget _catChip(int? id, String label) => GestureDetector(
    onTap: () => setState(() => _selectedCat = id),
    child: Container(
      margin: const EdgeInsets.only(right: 8),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
      decoration: BoxDecoration(
        color: _selectedCat == id ? VC.orange : context.vcCard,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: _selectedCat == id ? VC.orange : context.vcBorder),
      ),
      child: Text(label, style: TextStyle(
        color: _selectedCat == id ? Colors.white : context.vcTextSec,
        fontSize: 12, fontWeight: FontWeight.w700)),
    ),
  );

  Widget _empty() => Center(child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
    Icon(Icons.restaurant_menu_rounded, color: context.vcTextMute, size: 56),
    const SizedBox(height: 12),
    Text('No products yet', style: TextStyle(color: context.vcTextMute, fontSize: 15, fontWeight: FontWeight.w600)),
    const SizedBox(height: 8),
    ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: VC.orange),
      onPressed: () => _showProductForm(context),
      child: const Text('Add First Product', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700))),
  ]));

  Future<void> _toggle(int id) async {
    try {
      await VendorRepository.instance.toggleProduct(id);
      ref.invalidate(_prodsProvider(_selectedCat));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: VC.red));
    }
  }

  Future<void> _delete(BuildContext ctx, int id, String name) async {
    final ok = await showDialog<bool>(context: ctx, builder: (c) => AlertDialog(
      backgroundColor: c.vcCard,
      title: Text('Delete Product?', style: TextStyle(color: c.vcText, fontWeight: FontWeight.w800)),
      content: Text('Remove "$name" from your menu?', style: TextStyle(color: c.vcTextSec)),
      actions: [
        TextButton(onPressed: () => Navigator.pop(c, false), child: Text('Cancel', style: TextStyle(color: c.vcTextSec))),
        ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: VC.red), onPressed: () => Navigator.pop(c, true), child: const Text('Delete', style: TextStyle(color: Colors.white))),
      ],
    ));
    if (ok != true) return;
    try {
      await VendorRepository.instance.deleteProduct(id);
      ref.invalidate(_prodsProvider(_selectedCat));
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Product deleted'), backgroundColor: VC.amber));
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: VC.red));
    }
  }

  void _showProductForm(BuildContext ctx, {Map<String, dynamic>? product}) {
    showModalBottomSheet(
      context: ctx,
      isScrollControlled: true,
      backgroundColor: ctx.vcSurface,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (_) => _ProductFormSheet(
        product: product,
        onSaved: () { ref.invalidate(_prodsProvider(_selectedCat)); ref.invalidate(_catsProvider); },
      ),
    );
  }
}

class _ProductTile extends StatelessWidget {
  final Map<String, dynamic> product;
  final VoidCallback onEdit, onToggle, onDelete;
  const _ProductTile({required this.product, required this.onEdit, required this.onToggle, required this.onDelete});

  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);

  @override
  Widget build(BuildContext context) {
    final p = product;
    final isAvail = p['is_available'] == true || p['is_available'] == 1;
    final thumb = p['thumbnail'] as String?;
    final price = double.tryParse('${p['price'] ?? 0}') ?? 0;
    final salePrice = double.tryParse('${p['sale_price'] ?? 0}') ?? 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: context.vcCard, borderRadius: BorderRadius.circular(14),
        border: Border.all(color: isAvail ? context.vcBorder.withValues(alpha: 0.5) : VC.red.withValues(alpha: 0.2)),
      ),
      child: Row(children: [
        // Thumbnail
        ClipRRect(
          borderRadius: BorderRadius.circular(10),
          child: thumb != null
            ? CachedNetworkImage(imageUrl: thumb.startsWith('http') ? thumb : 'https://esahlan.com/storage/$thumb',
                width: 60, height: 60, fit: BoxFit.cover,
                placeholder: (_, __) => Container(width: 60, height: 60, color: context.vcSurface),
                errorWidget: (_, __, ___) => _placeholder(context))
            : _placeholder(context),
        ),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(p['name'] ?? '', style: TextStyle(color: context.vcText, fontWeight: FontWeight.w800, fontSize: 14), maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 3),
          Row(children: [
            if (salePrice > 0) ...[
              Text('\$${_fmt(salePrice)}', style: const TextStyle(color: VC.green, fontWeight: FontWeight.w800, fontSize: 13)),
              const SizedBox(width: 5),
              Text('\$${_fmt(price)}', style: TextStyle(color: context.vcTextMute, fontSize: 11, decoration: TextDecoration.lineThrough)),
            ] else
              Text('\$${_fmt(price)}', style: const TextStyle(color: VC.green, fontWeight: FontWeight.w800, fontSize: 13)),
          ]),
          const SizedBox(height: 2),
          if (p['category'] != null)
            Text(p['category']['name'] ?? '', style: TextStyle(color: context.vcTextMute, fontSize: 11)),
        ])),
        Column(children: [
          Switch(value: isAvail, onChanged: (_) => onToggle(), activeColor: VC.green, inactiveThumbColor: VC.red, materialTapTargetSize: MaterialTapTargetSize.shrinkWrap),
          Row(mainAxisSize: MainAxisSize.min, children: [
            GestureDetector(onTap: onEdit, child: const Icon(Icons.edit_rounded, color: VC.blue, size: 20)),
            const SizedBox(width: 10),
            GestureDetector(onTap: onDelete, child: const Icon(Icons.delete_rounded, color: VC.red, size: 20)),
          ]),
        ]),
      ]),
    );
  }

  Widget _placeholder(BuildContext context) => Container(width: 60, height: 60, decoration: BoxDecoration(color: context.vcSurface, borderRadius: BorderRadius.circular(10)),
    child: Icon(Icons.fastfood_rounded, color: context.vcTextMute, size: 28));
}

class _ProductFormSheet extends ConsumerStatefulWidget {
  final Map<String, dynamic>? product;
  final VoidCallback onSaved;
  const _ProductFormSheet({this.product, required this.onSaved});
  @override
  ConsumerState<_ProductFormSheet> createState() => _ProductFormSheetState();
}

class _ProductFormSheetState extends ConsumerState<_ProductFormSheet> {
  final _nameCtrl  = TextEditingController();
  final _priceCtrl = TextEditingController();
  final _descCtrl  = TextEditingController();
  int? _catId;
  XFile? _image;
  bool _loading = false;
  bool _isEdit = false;

  @override
  void initState() {
    super.initState();
    final p = widget.product;
    if (p != null) {
      _isEdit = true;
      _nameCtrl.text  = p['name'] ?? '';
      _priceCtrl.text = p['price']?.toString() ?? '';
      _descCtrl.text  = p['description'] ?? '';
      _catId = p['category_id'] as int?;
    }
  }

  Future<void> _submit() async {
    if (_nameCtrl.text.trim().isEmpty || _priceCtrl.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Name and price are required'), backgroundColor: VC.red));
      return;
    }
    setState(() => _loading = true);
    try {
      if (_isEdit) {
        await VendorRepository.instance.updateProduct(widget.product!['id'], {
          'name': _nameCtrl.text.trim(),
          'price': double.tryParse(_priceCtrl.text.trim()) ?? 0,
          'description': _descCtrl.text.trim(),
          if (_catId != null) 'category_id': _catId,
        });
      } else {
        final formData = FormData.fromMap({
          'name': _nameCtrl.text.trim(),
          'price': double.tryParse(_priceCtrl.text.trim()) ?? 0,
          'description': _descCtrl.text.trim(),
          if (_catId != null) 'category_id': _catId,
          if (_image != null) 'thumbnail': await MultipartFile.fromFile(_image!.path, filename: _image!.name),
        });
        await VendorRepository.instance.createProduct(formData);
      }
      if (mounted) {
        Navigator.pop(context);
        widget.onSaved();
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(_isEdit ? 'Product updated!' : 'Product added!'), backgroundColor: VC.green));
      }
    } catch (e) {
      setState(() => _loading = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$e'), backgroundColor: VC.red));
    }
  }

  @override
  Widget build(BuildContext context) {
    final cats = ref.watch(_catsProvider);
    return Padding(
      padding: EdgeInsets.only(left: 20, right: 20, top: 24, bottom: MediaQuery.of(context).viewInsets.bottom + 24),
      child: SingleChildScrollView(child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(_isEdit ? 'Edit Product' : 'Add Product', style: TextStyle(color: context.vcText, fontSize: 18, fontWeight: FontWeight.w900)),
        const SizedBox(height: 20),

        // Image picker (new only)
        if (!_isEdit) GestureDetector(
          onTap: () async {
            final img = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
            if (img != null) setState(() => _image = img);
          },
          child: Container(
            width: double.infinity, height: 100,
            decoration: BoxDecoration(color: context.vcCard, borderRadius: BorderRadius.circular(12), border: Border.all(color: context.vcBorder, style: BorderStyle.solid)),
            child: _image != null
              ? Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                  const Icon(Icons.check_circle_rounded, color: VC.green, size: 22),
                  const SizedBox(width: 8),
                  Text(_image!.name, style: const TextStyle(color: VC.green, fontWeight: FontWeight.w600)),
                ])
              : Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                  Icon(Icons.add_photo_alternate_rounded, color: context.vcTextMute, size: 32),
                  const SizedBox(height: 6),
                  Text('Add Photo', style: TextStyle(color: context.vcTextMute, fontSize: 13)),
                ]),
          ),
        ),
        if (!_isEdit) const SizedBox(height: 14),

        _label('Product Name *'),
        _field(_nameCtrl, 'e.g. Chicken Burger'),
        const SizedBox(height: 12),
        _label('Price (USD) *'),
        _field(_priceCtrl, '0.00', type: TextInputType.numberWithOptions(decimal: true)),
        const SizedBox(height: 12),
        _label('Description'),
        TextField(controller: _descCtrl, maxLines: 2, style: TextStyle(color: context.vcText), decoration: _dec('Optional description')),
        const SizedBox(height: 12),
        _label('Category'),
        cats.maybeWhen(
          data: (list) => DropdownButtonFormField<int>(
            value: _catId,
            decoration: _dec('Select category'),
            dropdownColor: context.vcCard,
            style: TextStyle(color: context.vcText),
            items: list.map<DropdownMenuItem<int>>((c) => DropdownMenuItem(value: c['id'] as int, child: Text(c['name'] ?? ''))).toList(),
            onChanged: (v) => setState(() => _catId = v),
          ),
          orElse: () => const LinearProgressIndicator(color: VC.orange),
        ),
        const SizedBox(height: 24),
        SizedBox(width: double.infinity, height: 52,
          child: ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: VC.orange, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
            onPressed: _loading ? null : _submit,
            child: _loading
              ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : Text(_isEdit ? 'Update Product' : 'Add Product', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
          ),
        ),
      ])),
    );
  }

  Widget _label(String t) => Padding(padding: const EdgeInsets.only(bottom: 6), child: Text(t, style: TextStyle(color: context.vcTextSec, fontSize: 12, fontWeight: FontWeight.w600)));
  Widget _field(TextEditingController c, String hint, {TextInputType? type}) => TextField(controller: c, keyboardType: type, style: TextStyle(color: context.vcText), decoration: _dec(hint));
  InputDecoration _dec(String hint) => InputDecoration(
    hintText: hint, hintStyle: TextStyle(color: context.vcTextMute),
    filled: true, fillColor: context.vcInputFill,
    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: context.vcBorder)),
    enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: context.vcBorder)),
    focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: VC.orange, width: 1.5)),
  );
}
