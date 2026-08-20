import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/ew_provider.dart';
import '../ui/ew_theme.dart';
import '../ui/widgets/ew_widgets.dart';
import '../../data/models/ew_models.dart';

class EwProductDetailScreen extends ConsumerStatefulWidget {
  final String slug;
  const EwProductDetailScreen({super.key, required this.slug});

  @override
  ConsumerState<EwProductDetailScreen> createState() => _EwProductDetailScreenState();
}

class _EwProductDetailScreenState extends ConsumerState<EwProductDetailScreen> {
  int _imageIndex = 0;
  int? _selectedVariantId;
  double _qty = 0; // set once product loads
  bool _qtyInit = false;

  @override
  Widget build(BuildContext context) {
    final productAsync = ref.watch(ewProductDetailProvider(widget.slug));

    return Scaffold(
      backgroundColor: EwTheme.bg,
      body: productAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: EwTheme.orange)),
        error: (e, _) => Scaffold(
          appBar: AppBar(backgroundColor: EwTheme.navy, foregroundColor: Colors.white),
          body: EwErrorRetry(error: e, onRetry: () => ref.refresh(ewProductDetailProvider(widget.slug))),
        ),
        data: (product) {
          if (!_qtyInit) {
            _qty = product.moq;
            _selectedVariantId = product.variants.where((v) => v.isDefault).firstOrNull?.id
              ?? product.variants.firstOrNull?.id;
            _qtyInit = true;
          }
          return _buildScaffold(context, product);
        },
      ),
    );
  }

  Widget _buildScaffold(BuildContext context, EwProduct product) {
    final unitPrice  = product.priceForQty(_qty);
    final lineTotal  = unitPrice * _qty;
    final maxStock   = product.variants
        .where((v) => v.id == _selectedVariantId)
        .firstOrNull?.availableQty;

    return Scaffold(
      backgroundColor: EwTheme.bg,
      body: Stack(children: [
        CustomScrollView(slivers: [
          // ── Image gallery ────────────────────────────────────────────────
          SliverToBoxAdapter(child: _buildGallery(product)),
          // ── Content ──────────────────────────────────────────────────────
          SliverToBoxAdapter(child: Column(children: [
            _buildHeader(context, product, unitPrice),
            const SizedBox(height: 1),
            _buildPriceTiers(product),
            _buildVariants(product),
            _buildSpecsAndInfo(product),
            _buildSupplierCard(context, product.supplier),
            _buildRelated(context, product),
            const SizedBox(height: 100),
          ])),
        ]),
        // ── Sticky bottom bar ─────────────────────────────────────────────
        Positioned(
          bottom: 0, left: 0, right: 0,
          child: _buildStickyBar(context, product, unitPrice, lineTotal, maxStock),
        ),
      ]),
    );
  }

  Widget _buildGallery(EwProduct product) {
    final images = product.images;
    return Stack(children: [
      AspectRatio(
        aspectRatio: 1.2,
        child: images.isEmpty
          ? Container(color: EwTheme.bg, child: const Icon(Icons.inventory_2_outlined, size: 64, color: EwTheme.textMuted))
          : PageView.builder(
              itemCount: images.length,
              onPageChanged: (i) => setState(() => _imageIndex = i),
              itemBuilder: (_, i) => Image.network(images[i], fit: BoxFit.contain,
                errorBuilder: (_, __, ___) => Container(color: EwTheme.bg,
                  child: const Icon(Icons.broken_image_outlined, color: EwTheme.textMuted, size: 48))),
            ),
      ),
      Positioned(top: 0, left: 0, right: 0,
        child: SafeArea(child: Row(children: [
          IconButton(
            icon: const CircleAvatar(backgroundColor: Colors.black26,
              child: Icon(Icons.arrow_back, color: Colors.white, size: 18)),
            onPressed: () => context.pop(),
          ),
        ])),
      ),
      if (images.length > 1)
        Positioned(bottom: 12, left: 0, right: 0,
          child: Row(mainAxisAlignment: MainAxisAlignment.center, children: images.asMap().entries.map((e) =>
            AnimatedContainer(duration: const Duration(milliseconds: 200),
              margin: const EdgeInsets.symmetric(horizontal: 3),
              width: _imageIndex == e.key ? 20 : 6, height: 6,
              decoration: BoxDecoration(
                color: _imageIndex == e.key ? EwTheme.orange : Colors.black26,
                borderRadius: EwTheme.radius4,
              ),
            ),
          ).toList()),
        ),
    ]);
  }

  Widget _buildHeader(BuildContext context, EwProduct product, double unitPrice) {
    return Container(
      color: EwTheme.surface,
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Name + supplier badge
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(child: Text(product.name, style: EwTheme.heading1)),
          if (product.supplier != null)
            SupplierBadge(verification: product.supplier!.verification),
        ]),
        const SizedBox(height: 8),
        // Price display
        Row(children: [
          Text(EwTheme.formatPrice(unitPrice), style: EwTheme.price.copyWith(fontSize: 22, color: EwTheme.orange)),
          Text(' / ${product.unit}', style: EwTheme.bodySmall),
          const Spacer(),
          if (product.priceTiers.length > 1)
            SavingsChip(
              percent: _savingsVsHighest(product, unitPrice),
              label: _savingsVsHighest(product, unitPrice) > 0 ? 'Save ${_savingsVsHighest(product, unitPrice)}%' : null,
            ),
        ]),
        const SizedBox(height: 4),
        Text('Total: ${EwTheme.formatPrice(unitPrice * _qty)}',
          style: EwTheme.heading3.copyWith(color: EwTheme.navy.withOpacity(0.6))),
        const SizedBox(height: 10),
        // Meta chips
        Wrap(spacing: 8, runSpacing: 6, children: [
          _metaChip(Icons.access_time_outlined, '${product.leadTimeDays}d lead time'),
          if (product.unitsPerPack != null)
            _metaChip(Icons.inventory_outlined, '1 ${product.unit} = ${product.unitsPerPack} pcs'),
          if (product.brand != null) _metaChip(Icons.label_outline, product.brand!),
          if (product.originCountry != null) _metaChip(Icons.flag_outlined, product.originCountry!),
        ]),
      ]),
    );
  }

  Widget _buildPriceTiers(EwProduct product) {
    if (product.priceTiers.isEmpty) return const SizedBox.shrink();
    return Container(
      color: EwTheme.surface,
      margin: const EdgeInsets.only(top: 1),
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const EwSectionHeader(title: 'Price Tiers'),
        const SizedBox(height: 12),
        PriceTierTable(tiers: product.priceTiers, currentQty: _qty, unit: product.unit),
        const SizedBox(height: 8),
        Text('Order more to unlock better pricing', style: EwTheme.bodySmall.copyWith(color: EwTheme.green)),
      ]),
    );
  }

  Widget _buildVariants(EwProduct product) {
    if (product.variants.length <= 1) return const SizedBox.shrink();

    // Get all attribute keys
    final keys = <String>{};
    for (final v in product.variants) keys.addAll(v.attributes.keys);

    return Container(
      color: EwTheme.surface,
      margin: const EdgeInsets.only(top: 1),
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Options', style: EwTheme.heading3),
        const SizedBox(height: 10),
        ...keys.map((key) {
          final values = product.variants
            .where((v) => v.attributes.containsKey(key))
            .map((v) => v.attributes[key]!)
            .toSet()
            .toList();
          return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(key, style: EwTheme.label),
            const SizedBox(height: 6),
            Wrap(spacing: 8, runSpacing: 6, children: values.map((val) {
              final variant = product.variants.where((v) => v.attributes[key] == val).firstOrNull;
              final isSelected = variant?.id == _selectedVariantId;
              final isOos = variant != null && variant.availableQty <= 0;
              return GestureDetector(
                onTap: isOos ? null : () => setState(() => _selectedVariantId = variant?.id),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 150),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                  decoration: BoxDecoration(
                    color: isSelected ? EwTheme.navy : EwTheme.surface,
                    border: Border.all(
                      color: isSelected ? EwTheme.navy : (isOos ? EwTheme.border : EwTheme.border),
                      width: isSelected ? 2 : 1,
                    ),
                    borderRadius: EwTheme.radius8,
                  ),
                  child: Text(val, style: TextStyle(
                    fontSize: 13, fontWeight: FontWeight.w500,
                    color: isOos ? EwTheme.textMuted : (isSelected ? Colors.white : EwTheme.textPrimary),
                    decoration: isOos ? TextDecoration.lineThrough : null,
                  )),
                ),
              );
            }).toList()),
            const SizedBox(height: 12),
          ]);
        }),
      ]),
    );
  }

  Widget _buildSpecsAndInfo(EwProduct product) {
    if (product.specs.isEmpty && product.description == null) return const SizedBox.shrink();
    return Container(
      color: EwTheme.surface,
      margin: const EdgeInsets.only(top: 1),
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        if (product.description != null) ...[
          Text('Description', style: EwTheme.heading3),
          const SizedBox(height: 8),
          Text(product.description!, style: EwTheme.body),
          const SizedBox(height: 16),
        ],
        if (product.specs.isNotEmpty) ...[
          Text('Specifications', style: EwTheme.heading3),
          const SizedBox(height: 8),
          ...product.specs.asMap().entries.map((e) => Container(
            padding: const EdgeInsets.symmetric(vertical: 8, horizontal: 12),
            decoration: BoxDecoration(
              color: e.key.isEven ? EwTheme.bg : EwTheme.surface,
              border: const Border(bottom: BorderSide(color: EwTheme.border)),
            ),
            child: Row(children: [
              Expanded(flex: 2, child: Text(e.value['key'] ?? '', style: EwTheme.bodySmall.copyWith(fontWeight: FontWeight.w600))),
              Expanded(flex: 3, child: Text(e.value['value'] ?? '', style: EwTheme.body)),
            ]),
          )),
        ],
      ]),
    );
  }

  Widget _buildSupplierCard(BuildContext context, EwSupplierCard? supplier) {
    if (supplier == null) return const SizedBox.shrink();
    return GestureDetector(
      onTap: () => context.push('/ewholesale/supplier/${supplier.id}'),
      child: Container(
        color: EwTheme.surface,
        margin: const EdgeInsets.only(top: 1),
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Supplier', style: EwTheme.heading3),
          const SizedBox(height: 12),
          Row(children: [
            CircleAvatar(
              radius: 24,
              backgroundColor: EwTheme.navy.withOpacity(0.08),
              backgroundImage: supplier.logo != null ? NetworkImage(supplier.logo!) : null,
              child: supplier.logo == null
                ? Text(supplier.displayName.isNotEmpty ? supplier.displayName[0] : '?',
                    style: const TextStyle(color: EwTheme.navy, fontWeight: FontWeight.w700, fontSize: 18))
                : null,
            ),
            const SizedBox(width: 12),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text(supplier.displayName, style: EwTheme.heading3, maxLines: 1, overflow: TextOverflow.ellipsis)),
                SupplierBadge(verification: supplier.verification),
              ]),
              const SizedBox(height: 4),
              Row(children: [
                _metricDot('${supplier.responseRate.toInt()}%', 'Response'),
                _metricDot('${supplier.onTimeDeliveryRate.toInt()}%', 'On-time'),
                _metricDot('⭐ ${supplier.rating.toStringAsFixed(1)}', 'Rating'),
              ]),
            ])),
            const Icon(Icons.chevron_right, color: EwTheme.textMuted),
          ]),
        ]),
      ),
    );
  }

  Widget _buildRelated(BuildContext context, EwProduct product) {
    // Related products based on same category — loaded on demand
    return const SizedBox.shrink();
  }

  Widget _buildStickyBar(BuildContext context, EwProduct product, double unitPrice, double lineTotal, double? maxStock) {
    return Container(
      decoration: BoxDecoration(
        color: EwTheme.surface,
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.1), blurRadius: 12, offset: const Offset(0, -3))],
      ),
      padding: EdgeInsets.fromLTRB(16, 12, 16, MediaQuery.of(context).padding.bottom + 12),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        // Qty stepper
        MoqStepper(
          moq:  product.moq,
          step: product.unitsPerPack?.toDouble() ?? 1,
          value: _qty,
          max:  maxStock,
          unit: product.unit,
          onChanged: (q) => setState(() => _qty = q),
        ),
        const SizedBox(height: 10),
        // Actions
        Row(children: [
          Expanded(child: OutlinedButton.icon(
            onPressed: _qty >= product.moq ? () => _showInquirySheet(context, product) : null,
            icon: const Icon(Icons.chat_bubble_outline, size: 16),
            label: const Text('Inquiry'),
            style: EwTheme.secondaryButton.copyWith(minimumSize: const WidgetStatePropertyAll(Size(0, 44))),
          )),
          const SizedBox(width: 10),
          Expanded(flex: 2, child: ElevatedButton.icon(
            onPressed: _qty >= product.moq ? () => _addToCart(context, product, unitPrice) : null,
            icon: const Icon(Icons.shopping_cart_outlined, size: 16),
            label: Text('Add · ${EwTheme.formatPrice(lineTotal)}'),
            style: EwTheme.primaryButton.copyWith(minimumSize: const WidgetStatePropertyAll(Size(0, 44))),
          )),
        ]),
      ]),
    );
  }

  void _addToCart(BuildContext context, EwProduct product, double unitPrice) {
    if (product.supplier == null) return;
    ref.read(ewCartProvider.notifier).add(EwCartLine(
      productId:         product.id,
      productName:       product.name,
      productImage:      product.images.firstOrNull,
      variantId:         _selectedVariantId,
      variantAttributes: {},
      qty:               _qty,
      unit:              product.unit,
      unitPrice:         unitPrice,
      moq:               product.moq,
      supplierId:        product.supplier!.id,
    ));
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text('Added ${EwTheme.formatQty(_qty, product.unit)} to cart'),
      backgroundColor: EwTheme.green,
      behavior: SnackBarBehavior.floating,
      action: SnackBarAction(label: 'View Cart', textColor: Colors.white,
        onPressed: () => context.push('/ewholesale/cart')),
    ));
  }

  void _showInquirySheet(BuildContext context, EwProduct product) {
    final msgCtrl = TextEditingController();
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: EwTheme.surface,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(16))),
      builder: (ctx) => Padding(
        padding: EdgeInsets.fromLTRB(16, 20, 16, MediaQuery.of(ctx).viewInsets.bottom + 20),
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('Send Inquiry', style: EwTheme.heading2),
          const SizedBox(height: 4),
          Text('Qty: ${EwTheme.formatQty(_qty, product.unit)}', style: EwTheme.bodySmall),
          const SizedBox(height: 12),
          TextField(
            controller: msgCtrl,
            maxLines: 4,
            decoration: InputDecoration(
              hintText: 'Describe your requirements, delivery address, preferred price…',
              border: OutlineInputBorder(borderRadius: EwTheme.radius8),
              focusedBorder: OutlineInputBorder(borderRadius: EwTheme.radius8, borderSide: const BorderSide(color: EwTheme.navy, width: 1.5)),
            ),
          ),
          const SizedBox(height: 12),
          ElevatedButton(
            style: EwTheme.primaryButton,
            onPressed: () async {
              if (msgCtrl.text.trim().isEmpty) return;
              final repo = ref.read(ewRepoProvider);
              await repo.createInquiry(productId: product.id, qty: _qty, message: msgCtrl.text.trim());
              if (ctx.mounted) { Navigator.pop(ctx); }
              if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Inquiry sent!'), backgroundColor: EwTheme.green));
            },
            child: const Text('Send Inquiry'),
          ),
        ]),
      ),
    );
  }

  Widget _metaChip(IconData icon, String label) => Container(
    margin: const EdgeInsets.only(bottom: 2),
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    decoration: BoxDecoration(color: EwTheme.bg, borderRadius: EwTheme.radius4, border: Border.all(color: EwTheme.border)),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 12, color: EwTheme.textSecondary),
      const SizedBox(width: 4),
      Text(label, style: EwTheme.bodySmall.copyWith(fontSize: 11)),
    ]),
  );

  Widget _metricDot(String value, String label) => Expanded(child: Column(children: [
    Text(value, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: EwTheme.navy)),
    Text(label, style: EwTheme.bodySmall.copyWith(fontSize: 10)),
  ]));

  int _savingsVsHighest(EwProduct product, double currentPrice) {
    if (product.priceTiers.isEmpty) return 0;
    final highest = product.priceTiers.first.unitPrice;
    if (highest <= 0 || currentPrice >= highest) return 0;
    return ((highest - currentPrice) / highest * 100).round();
  }
}
