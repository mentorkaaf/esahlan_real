import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/widgets/network_image_widget.dart';
import '../data/models/egrocery_models.dart';
import '../presentation/providers/egrocery_providers.dart';
import 'eg_theme.dart';

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ShimmerBox
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class EGShimmerBox extends StatefulWidget {
  final double width;
  final double height;
  final double radius;
  const EGShimmerBox({super.key, required this.width, required this.height, this.radius = 10});

  @override
  State<EGShimmerBox> createState() => _EGShimmerBoxState();
}

class _EGShimmerBoxState extends State<EGShimmerBox> with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl;
  late final Animation<double> _anim;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))..repeat();
    _anim = Tween<double>(begin: -2, end: 2).animate(CurvedAnimation(parent: _ctrl, curve: Curves.linear));
  }

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _anim,
      builder: (_, __) => Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(widget.radius),
          gradient: LinearGradient(
            begin: Alignment(_anim.value - 1, 0),
            end: Alignment(_anim.value, 0),
            colors: const [Color(0xFFE8EDF5), Color(0xFFD0D8E8), Color(0xFFE8EDF5)],
          ),
        ),
      ),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// SectionHeader
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class EGSectionHeader extends StatelessWidget {
  final String title;
  final VoidCallback? onSeeAll;
  const EGSectionHeader({super.key, required this.title, this.onSeeAll});

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 10),
        child: Row(children: [
          Expanded(child: Text(title, style: EGTheme.sectionTitle)),
          if (onSeeAll != null)
            GestureDetector(
              onTap: onSeeAll,
              child: const Text('See all', style: TextStyle(fontSize: 13, color: EGTheme.orange, fontWeight: FontWeight.w700)),
            ),
        ]),
      );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// PricePair
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class EGPricePair extends StatelessWidget {
  final EGVariant variant;
  const EGPricePair({super.key, required this.variant});

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.baseline,
        textBaseline: TextBaseline.alphabetic,
        children: [
          Text('\$${variant.effectivePrice.toStringAsFixed(2)}', style: EGTheme.priceMain),
          if (variant.originalPrice != null) ...[
            const SizedBox(width: 4),
            Text('\$${variant.originalPrice!.toStringAsFixed(2)}', style: EGTheme.priceStruck),
          ],
        ],
      );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// QtyStepper
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class EGQtyStepper extends StatelessWidget {
  final double qty;
  final bool isWeightBased;
  final ValueChanged<double> onChanged;
  final double step;

  const EGQtyStepper({
    super.key,
    required this.qty,
    required this.onChanged,
    this.isWeightBased = false,
    this.step = 1,
  });

  String get _label {
    if (isWeightBased) return '${qty.toStringAsFixed(qty < 1 ? 2 : 1)} kg';
    return qty.toInt().toString();
  }

  @override
  Widget build(BuildContext context) => Container(
        height: 34,
        decoration: BoxDecoration(
          color: EGTheme.orange,
          borderRadius: BorderRadius.circular(EGTheme.rChip),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          _btn(Icons.remove, () => onChanged(qty - step > 0 ? qty - step : 0)),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 10),
            child: Text(_label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 14)),
          ),
          _btn(Icons.add, () => onChanged(qty + step)),
        ]),
      );

  Widget _btn(IconData icon, VoidCallback onTap) => GestureDetector(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          child: Icon(icon, size: 16, color: Colors.white),
        ),
      );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ProductCard â€” animated add â†’ stepper morph
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class EGProductCard extends ConsumerWidget {
  final EGProduct product;
  final VoidCallback? onTap;
  final double width;

  const EGProductCard({super.key, required this.product, this.onTap, this.width = 160});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final cart = ref.watch(egCartProvider);
    final variant = product.defaultVariant;
    if (variant == null) return const SizedBox();

    final cartLine = cart.firstWhere((l) => l.variantId == variant.id, orElse: () => EGCartLine(variantId: -1, qty: 0));
    final inCart = cartLine.variantId != -1;
    final hasDiscount = variant.discountPct > 0;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: width,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.07), blurRadius: 16, offset: const Offset(0, 4)),
            BoxShadow(color: Colors.black.withOpacity(0.03), blurRadius: 4, offset: const Offset(0, 1)),
          ],
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // ── Image area ───────────────────────────────────────────────
          Stack(children: [
            ClipRRect(
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
              child: Container(
                height: 130,
                width: width,
                color: const Color(0xFFF7F7F7),
                child: NetImage(
                  url: product.image ?? '',
                  height: 130,
                  width: width,
                  fit: BoxFit.cover,
                  errorWidget: Container(
                    color: const Color(0xFFF2F2F2),
                    child: const Center(child: Icon(Icons.image_outlined, size: 36, color: Color(0xFFCCCCCC))),
                  ),
                ),
              ),
            ),
            // Discount badge
            if (hasDiscount)
              Positioned(
                top: 8, left: 8,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: EGTheme.red,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text('-${variant.discountPct}%',
                    style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: 0.3)),
                ),
              ),
            // Flash deal badge
            if (variant.isFlashDeal)
              Positioned(
                top: 8, right: 8,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFF6B00),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Text('⚡ Flash', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800)),
                ),
              ),
          ]),

          // ── Info area ─────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(10, 10, 10, 10),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              // Product name
              Text(
                product.name,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF1A1A1A),
                  height: 1.3,
                ),
              ),
              const SizedBox(height: 3),
              // Variant label (unit)
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                  color: const Color(0xFFF5F5F5),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  variant.label,
                  style: const TextStyle(fontSize: 10, color: Color(0xFF888888), fontWeight: FontWeight.w500),
                ),
              ),
              const SizedBox(height: 8),
              // Price + Add button row
              Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
                // Price column
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(
                    '\$${variant.effectivePrice.toStringAsFixed(2)}',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: hasDiscount ? EGTheme.red : EGTheme.textDark,
                    ),
                  ),
                  if (hasDiscount && variant.originalPrice != null)
                    Text(
                      '\$${variant.originalPrice!.toStringAsFixed(2)}',
                      style: const TextStyle(
                        fontSize: 11,
                        color: Color(0xFFAAAAAA),
                        decoration: TextDecoration.lineThrough,
                        decorationColor: Color(0xFFAAAAAA),
                      ),
                    ),
                ]),
                const Spacer(),
                // Add / Stepper
                AnimatedSwitcher(
                  duration: const Duration(milliseconds: 200),
                  transitionBuilder: (child, anim) => ScaleTransition(scale: anim, child: child),
                  child: inCart
                      ? EGQtyStepper(
                          key: ValueKey('stepper-${variant.id}'),
                          qty: cartLine.qty,
                          isWeightBased: product.isWeightBased,
                          step: product.isWeightBased ? 0.25 : 1,
                          onChanged: (q) => ref.read(egCartProvider.notifier).setQty(variant.id, q),
                        )
                      : _AddBtn(
                          key: ValueKey('add-${variant.id}'),
                          onTap: () => ref.read(egCartProvider.notifier).addOrIncrement(variant, product),
                        ),
                ),
              ]),
            ]),
          ),
        ]),
      ),
    );
  }
}

class _AddBtn extends StatelessWidget {
  final VoidCallback onTap;
  const _AddBtn({super.key, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
        onTap: onTap,
        child: Container(
          width: 34,
          height: 34,
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [Color(0xFFFF8C00), Color(0xFFFF5F00)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(11),
            boxShadow: [BoxShadow(color: EGTheme.orange.withOpacity(0.35), blurRadius: 8, offset: const Offset(0, 3))],
          ),
          child: const Icon(Icons.add_rounded, color: Colors.white, size: 20),
        ),
      );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// BannerSlider
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class EGBannerSlider extends StatefulWidget {
  final List<EGBanner> banners;
  final double height;
  const EGBannerSlider({super.key, required this.banners, this.height = 180});

  @override
  State<EGBannerSlider> createState() => _EGBannerSliderState();
}

class _EGBannerSliderState extends State<EGBannerSlider> {
  late final PageController _ctrl;
  int _page = 0;
  Timer? _timer;

  @override
  void initState() {
    super.initState();
    _ctrl = PageController(viewportFraction: 0.92);
    if (widget.banners.length > 1) {
      _timer = Timer.periodic(const Duration(seconds: 4), (_) {
        if (!mounted) return;
        final next = (_page + 1) % widget.banners.length;
        _ctrl.animateToPage(next, duration: const Duration(milliseconds: 400), curve: Curves.easeInOut);
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.banners.isEmpty) return const SizedBox();
    return Column(children: [
      SizedBox(
        height: widget.height,
        child: PageView.builder(
          controller: _ctrl,
          onPageChanged: (p) => setState(() => _page = p),
          itemCount: widget.banners.length,
          itemBuilder: (_, i) {
            final b = widget.banners[i];
            return Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: ClipRRect(
                borderRadius: BorderRadius.circular(EGTheme.rCard),
                child: NetImage(url: b.image, height: widget.height, fit: BoxFit.cover),
              ),
            );
          },
        ),
      ),
      if (widget.banners.length > 1) ...[
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(
            widget.banners.length,
            (i) => AnimatedContainer(
              duration: const Duration(milliseconds: 250),
              margin: const EdgeInsets.symmetric(horizontal: 3),
              width: i == _page ? 18 : 6,
              height: 6,
              decoration: BoxDecoration(
                color: i == _page ? EGTheme.orange : EGTheme.shimmer,
                borderRadius: BorderRadius.circular(3),
              ),
            ),
          ),
        ),
      ],
    ]);
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// CategoryTile
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class EGCategoryTile extends StatelessWidget {
  final EGCategory category;
  final bool selected;
  final VoidCallback onTap;
  const EGCategoryTile({super.key, required this.category, required this.onTap, this.selected = false});

  @override
  Widget build(BuildContext context) => GestureDetector(
        onTap: onTap,
        child: Container(
          width: 72,
          margin: const EdgeInsets.symmetric(horizontal: 4),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: selected ? EGTheme.orange.withOpacity(0.12) : EGTheme.shimmer.withOpacity(0.5),
                borderRadius: BorderRadius.circular(16),
                border: selected ? Border.all(color: EGTheme.orange, width: 1.5) : null,
              ),
              child: category.icon != null
                  ? Center(child: Text(category.icon!, style: const TextStyle(fontSize: 26)))
                  : (category.image != null
                      ? ClipRRect(
                          borderRadius: BorderRadius.circular(14),
                          child: NetImage(url: category.image!, height: 56, width: 56, fit: BoxFit.cover))
                      : Icon(Icons.category, color: selected ? EGTheme.orange : EGTheme.textGrey, size: 26)),
            ),
            const SizedBox(height: 6),
            Text(
              category.name,
              style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: selected ? EGTheme.orange : EGTheme.textDark),
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ]),
        ),
      );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// FlashDealCard (with countdown chip + sold-progress bar)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class EGFlashDealCard extends ConsumerStatefulWidget {
  final EGProduct product;
  const EGFlashDealCard({super.key, required this.product});

  @override
  ConsumerState<EGFlashDealCard> createState() => _EGFlashDealCardState();
}

class _EGFlashDealCardState extends ConsumerState<EGFlashDealCard> {
  Timer? _timer;
  Duration _remaining = Duration.zero;
  late EGVariant _variant;

  @override
  void initState() {
    super.initState();
    _variant = widget.product.defaultVariant ?? widget.product.variants.first;
    _parseRemaining();
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() {
        if (_remaining.inSeconds > 0) _remaining -= const Duration(seconds: 1);
      });
    });
  }

  void _parseRemaining() {
    if (_variant.endsAt == null) return;
    try {
      final ends = DateTime.parse(_variant.endsAt!).toLocal();
      _remaining = ends.difference(DateTime.now());
    } catch (_) {}
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  String get _countdownLabel {
    if (_remaining.isNegative) return 'Ended';
    final h = _remaining.inHours;
    final m = _remaining.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = _remaining.inSeconds.remainder(60).toString().padLeft(2, '0');
    return h > 0 ? '${h}h ${m}m' : '$m:$s';
  }

  @override
  Widget build(BuildContext context) {
    final cart = ref.watch(egCartProvider);
    final cartLine = cart.firstWhere((l) => l.variantId == _variant.id, orElse: () => EGCartLine(variantId: -1, qty: 0));
    final inCart = cartLine.variantId != -1;

    final qtyLeft  = _variant.qtyLeft;
    final qtyLimit = _variant.qtyLimit;
    final progress = (qtyLeft != null && qtyLimit != null && qtyLimit > 0)
        ? ((qtyLimit - qtyLeft) / qtyLimit).clamp(0.0, 1.0)
        : null;

    return Container(
      width: 180,
      margin: const EdgeInsets.only(right: 12),
      decoration: BoxDecoration(
        color: EGTheme.card,
        borderRadius: BorderRadius.circular(EGTheme.rCard),
        border: Border.all(color: EGTheme.orange.withOpacity(0.3)),
        boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 10, offset: const Offset(0, 3))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        // Image + countdown chip
        Stack(children: [
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(EGTheme.rCard)),
            child: NetImage(url: widget.product.image ?? '', height: 110, width: 180, fit: BoxFit.cover),
          ),
          Positioned(
            top: 8,
            left: 8,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: EGTheme.orange,
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                const Text('âš¡', style: TextStyle(fontSize: 10)),
                const SizedBox(width: 3),
                Text(_countdownLabel, style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800)),
              ]),
            ),
          ),
        ]),
        Padding(
          padding: const EdgeInsets.all(10),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(widget.product.name, style: EGTheme.productName, maxLines: 1, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 4),
            EGPricePair(variant: _variant),
            // Sold progress
            if (progress != null) ...[
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: LinearProgressIndicator(
                  value: progress,
                  backgroundColor: EGTheme.shimmer,
                  color: EGTheme.orange,
                  minHeight: 5,
                ),
              ),
              const SizedBox(height: 4),
              Text(qtyLeft! > 0 ? '$qtyLeft left' : 'Sold out!', style: EGTheme.caption),
            ],
            const SizedBox(height: 8),
            // Add / Stepper
            SizedBox(
              width: double.infinity,
              child: inCart
                  ? EGQtyStepper(
                      qty: cartLine.qty,
                      onChanged: (q) => ref.read(egCartProvider.notifier).setQty(_variant.id, q),
                    )
                  : ElevatedButton(
                      onPressed: () => ref.read(egCartProvider.notifier).addOrIncrement(_variant, widget.product),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: EGTheme.orange,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(EGTheme.rBtn)),
                      ),
                      child: const Text('Add', style: TextStyle(fontWeight: FontWeight.w800)),
                    ),
            ),
          ]),
        ),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// EmptyState
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class EGEmptyState extends StatelessWidget {
  final String emoji;
  final String title;
  final String? subtitle;
  final VoidCallback? onRetry;
  const EGEmptyState({super.key, required this.emoji, required this.title, this.subtitle, this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(emoji, style: const TextStyle(fontSize: 56)),
            const SizedBox(height: 16),
            Text(title, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700, color: EGTheme.textDark)),
            if (subtitle != null) ...[
              const SizedBox(height: 8),
              Text(subtitle!, style: const TextStyle(fontSize: 14, color: EGTheme.textGrey), textAlign: TextAlign.center),
            ],
            if (onRetry != null) ...[
              const SizedBox(height: 20),
              ElevatedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh),
                label: const Text('Try Again'),
                style: ElevatedButton.styleFrom(backgroundColor: EGTheme.orange, foregroundColor: Colors.white),
              ),
            ],
          ]),
        ),
      );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// Cart Badge (for AppBar)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

class EGCartBadge extends ConsumerWidget {
  final VoidCallback? onTap;
  const EGCartBadge({super.key, this.onTap});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final count = ref.watch(egCartProvider).length;
    return GestureDetector(
      onTap: onTap,
      child: Stack(children: [
        const Icon(Icons.shopping_cart_outlined, color: EGTheme.textDark),
        if (count > 0)
          Positioned(
            right: 0,
            top: 0,
            child: Container(
              padding: const EdgeInsets.all(3),
              decoration: const BoxDecoration(color: EGTheme.orange, shape: BoxShape.circle),
              child: Text('$count', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800)),
            ),
          ),
      ]),
    );
  }
}


