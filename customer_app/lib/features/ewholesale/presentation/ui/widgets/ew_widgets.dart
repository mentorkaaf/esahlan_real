import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import '../../ui/ew_theme.dart';
import '../../../data/models/ew_models.dart';

// ─────────────────────────────────────────────────────────────────────────────
// ShimmerBox
// ─────────────────────────────────────────────────────────────────────────────

class ShimmerBox extends StatefulWidget {
  final double width;
  final double height;
  final BorderRadius? borderRadius;

  const ShimmerBox({super.key, required this.width, required this.height, this.borderRadius});

  const ShimmerBox.fill({super.key, required this.height, this.borderRadius})
    : width = double.infinity;

  @override
  State<ShimmerBox> createState() => _ShimmerBoxState();
}

class _ShimmerBoxState extends State<ShimmerBox> with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  late Animation<double> _anim;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 1200))..repeat();
    _anim = Tween(begin: -1.5, end: 2.5).animate(CurvedAnimation(parent: _ctrl, curve: Curves.easeInOut));
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _anim,
      builder: (_, __) => Container(
        width: widget.width,
        height: widget.height,
        decoration: BoxDecoration(
          borderRadius: widget.borderRadius ?? EwTheme.radius8,
          gradient: LinearGradient(
            begin: Alignment(_anim.value - 1, 0),
            end:   Alignment(_anim.value + 1, 0),
            colors: const [Color(0xFFE5E7EB), Color(0xFFF3F4F6), Color(0xFFE5E7EB)],
          ),
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// SupplierBadge
// ─────────────────────────────────────────────────────────────────────────────

class SupplierBadge extends StatelessWidget {
  final String verification;
  final bool compact;

  const SupplierBadge({super.key, required this.verification, this.compact = false});

  @override
  Widget build(BuildContext context) {
    final (icon, label, fg, bg) = switch (verification) {
      'gold'     => ('⭐', 'Gold Supplier', EwTheme.badgeGold, EwTheme.badgeGoldSurface),
      'verified' => ('✓', 'Verified',      EwTheme.badgeBlue, EwTheme.badgeBlueSurface),
      _          => ('', compact ? '' : 'Unverified', EwTheme.badgeGray, EwTheme.badgeGraySurface),
    };
    if (verification == 'unverified' && compact) return const SizedBox.shrink();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(color: bg, borderRadius: EwTheme.radius4),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        if (icon.isNotEmpty) ...[
          Text(icon, style: TextStyle(fontSize: 10, color: fg)),
          const SizedBox(width: 3),
        ],
        Text(label, style: TextStyle(fontSize: 10, fontWeight: FontWeight.w600, color: fg)),
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// WholesaleProductCard
// ─────────────────────────────────────────────────────────────────────────────

class WholesaleProductCard extends StatelessWidget {
  final EwProduct product;
  final VoidCallback? onTap;

  const WholesaleProductCard({super.key, required this.product, this.onTap});

  @override
  Widget build(BuildContext context) {
    final img = product.images.isNotEmpty ? product.images.first : null;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          color: EwTheme.surface,
          borderRadius: EwTheme.radius12,
          boxShadow: EwTheme.cardShadow,
          border: Border.all(color: EwTheme.border),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          // Image
          ClipRRect(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
            child: AspectRatio(
              aspectRatio: 1,
              child: img != null
                ? Image.network(img, fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => _placeholder())
                : _placeholder(),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(10),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              // Product name
              Text(product.name,
                style: EwTheme.heading3.copyWith(fontSize: 13),
                maxLines: 2, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 4),
              // Price
              Text(
                product.tierSummaryLabel ?? 'From ${EwTheme.formatPrice(product.minPrice)} / ${product.unit}',
                style: EwTheme.priceSmall,
              ),
              const SizedBox(height: 6),
              // MOQ chip + supplier badge row
              Row(children: [
                _moqChip(),
                const SizedBox(width: 6),
                if (product.supplier != null)
                  SupplierBadge(verification: product.supplier!.verification, compact: true),
              ]),
            ]),
          ),
        ]),
      ),
    );
  }

  Widget _placeholder() => Container(
    color: EwTheme.bg,
    child: const Center(child: Icon(Icons.inventory_2_outlined, color: EwTheme.textMuted, size: 32)),
  );

  Widget _moqChip() => Container(
    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
    decoration: BoxDecoration(
      color: EwTheme.navy.withOpacity(0.07),
      borderRadius: EwTheme.radius4,
    ),
    child: Text(
      'MOQ: ${EwTheme.formatQty(product.moq, product.unit)}',
      style: EwTheme.bodySmall.copyWith(fontSize: 10, color: EwTheme.navy, fontWeight: FontWeight.w600),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// PriceTierTable
// ─────────────────────────────────────────────────────────────────────────────

class PriceTierTable extends StatelessWidget {
  final List<EwPriceTier> tiers;
  final double currentQty;
  final String unit;

  const PriceTierTable({super.key, required this.tiers, required this.currentQty, required this.unit});

  @override
  Widget build(BuildContext context) {
    if (tiers.isEmpty) return const SizedBox.shrink();
    return Container(
      decoration: BoxDecoration(
        border: Border.all(color: EwTheme.border),
        borderRadius: EwTheme.radius8,
      ),
      child: Column(
        children: tiers.asMap().entries.map((e) {
          final i = e.key;
          final tier = e.value;
          final isActive = tier.containsQty(currentQty);
          final isLast = i == tiers.length - 1;

          final qtyLabel = tier.maxQty == null
            ? '${tier.minQty.toInt()}+ ${unit}s'
            : '${tier.minQty.toInt()}–${tier.maxQty!.toInt()} ${unit}s';

          // Saving vs the highest tier
          final highestPrice = tiers.first.unitPrice;
          final savingPct = highestPrice > 0
            ? ((highestPrice - tier.unitPrice) / highestPrice * 100).round()
            : 0;

          return AnimatedContainer(
            duration: const Duration(milliseconds: 200),
            decoration: BoxDecoration(
              color: isActive ? EwTheme.navy.withOpacity(0.05) : null,
              border: isLast ? null : const Border(bottom: BorderSide(color: EwTheme.border)),
              borderRadius: isActive
                ? (i == 0 ? const BorderRadius.vertical(top: Radius.circular(8))
                  : isLast ? const BorderRadius.vertical(bottom: Radius.circular(8))
                  : BorderRadius.zero)
                : BorderRadius.zero,
            ),
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Row(children: [
              Expanded(child: Row(children: [
                if (isActive) Container(
                  width: 4, height: 16,
                  decoration: const BoxDecoration(color: EwTheme.orange, borderRadius: EwTheme.radius4),
                  margin: const EdgeInsets.only(right: 8),
                ),
                Text(qtyLabel, style: EwTheme.body.copyWith(
                  fontWeight: isActive ? FontWeight.w600 : FontWeight.w400,
                  color: isActive ? EwTheme.navy : EwTheme.textSecondary,
                )),
              ])),
              if (savingPct > 0 && i > 0)
                SavingsChip(percent: savingPct),
              const SizedBox(width: 8),
              Text(
                EwTheme.formatPrice(tier.unitPrice),
                style: EwTheme.priceSmall.copyWith(
                  color: isActive ? EwTheme.orange : EwTheme.navy,
                  fontWeight: isActive ? FontWeight.w700 : FontWeight.w600,
                ),
              ),
              Text(' /${unit}', style: EwTheme.bodySmall),
            ]),
          );
        }).toList(),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// MoqStepper
// ─────────────────────────────────────────────────────────────────────────────

class MoqStepper extends StatefulWidget {
  final double moq;
  final double step; // usually 1 or unitsPerPack
  final double value;
  final double? max;
  final String unit;
  final ValueChanged<double> onChanged;

  const MoqStepper({super.key, required this.moq, this.step = 1,
    required this.value, this.max, required this.unit, required this.onChanged});

  @override
  State<MoqStepper> createState() => _MoqStepperState();
}

class _MoqStepperState extends State<MoqStepper> with SingleTickerProviderStateMixin {
  late AnimationController _shake;
  late Animation<double> _shakeAnim;

  @override
  void initState() {
    super.initState();
    _shake = AnimationController(vsync: this, duration: const Duration(milliseconds: 400));
    _shakeAnim = TweenSequence([
      TweenSequenceItem(tween: Tween(begin: 0.0, end: -8.0), weight: 1),
      TweenSequenceItem(tween: Tween(begin: -8.0, end: 8.0), weight: 2),
      TweenSequenceItem(tween: Tween(begin: 8.0, end: -8.0), weight: 2),
      TweenSequenceItem(tween: Tween(begin: -8.0, end: 0.0), weight: 1),
    ]).animate(CurvedAnimation(parent: _shake, curve: Curves.easeInOut));
  }

  @override
  void dispose() { _shake.dispose(); super.dispose(); }

  void _dec() {
    final next = widget.value - widget.step;
    if (next < widget.moq) {
      HapticFeedback.lightImpact();
      _shake.forward(from: 0);
    } else {
      widget.onChanged(next);
    }
  }

  void _inc() {
    final next = widget.value + widget.step;
    if (widget.max != null && next > widget.max!) return;
    widget.onChanged(next);
  }

  @override
  Widget build(BuildContext context) {
    final belowMoq = widget.value < widget.moq;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      AnimatedBuilder(
        animation: _shakeAnim,
        builder: (_, child) => Transform.translate(
          offset: Offset(_shakeAnim.value, 0), child: child),
        child: Container(
          decoration: BoxDecoration(
            border: Border.all(color: belowMoq ? EwTheme.red : EwTheme.border, width: 1.5),
            borderRadius: EwTheme.radius8,
            color: EwTheme.surface,
          ),
          child: Row(children: [
            _stepBtn(Icons.remove, _dec),
            Expanded(child: Center(
              child: Text(
                EwTheme.formatQty(widget.value, widget.unit),
                style: EwTheme.heading3,
              ),
            )),
            _stepBtn(Icons.add, _inc),
          ]),
        ),
      ),
      if (belowMoq)
        Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Text(
            'Minimum order: ${EwTheme.formatQty(widget.moq, widget.unit)}',
            style: EwTheme.bodySmall.copyWith(color: EwTheme.red),
          ),
        ),
    ]);
  }

  Widget _stepBtn(IconData icon, VoidCallback onTap) => Material(
    color: Colors.transparent,
    child: InkWell(
      onTap: onTap,
      borderRadius: EwTheme.radius8,
      child: SizedBox(
        width: 44, height: 44,
        child: Icon(icon, size: 18, color: EwTheme.navy),
      ),
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// SavingsChip
// ─────────────────────────────────────────────────────────────────────────────

class SavingsChip extends StatelessWidget {
  final int percent;
  final String? label;

  const SavingsChip({super.key, required this.percent, this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
      decoration: BoxDecoration(
        color: EwTheme.green.withOpacity(0.1),
        borderRadius: EwTheme.radius4,
        border: Border.all(color: EwTheme.green.withOpacity(0.3)),
      ),
      child: Text(
        label ?? 'Save $percent%',
        style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700, color: EwTheme.green),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// QuoteStatusChip
// ─────────────────────────────────────────────────────────────────────────────

class QuoteStatusChip extends StatelessWidget {
  final String status;

  const QuoteStatusChip({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    final (label, fg, bg) = switch (status) {
      'pending'   => ('Pending', EwTheme.textSecondary, EwTheme.badgeGraySurface),
      'sent'      => ('Quote Received', EwTheme.badgeBlue, EwTheme.badgeBlueSurface),
      'countered' => ('Countered', EwTheme.amber, const Color(0xFFFEF3C7)),
      'accepted'  => ('Accepted', EwTheme.green, const Color(0xFFDCFCE7)),
      'declined'  => ('Declined', EwTheme.red, const Color(0xFFFEE2E2)),
      'expired'   => ('Expired', EwTheme.textMuted, EwTheme.badgeGraySurface),
      _           => (status, EwTheme.textSecondary, EwTheme.badgeGraySurface),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: bg, borderRadius: EwTheme.radius4),
      child: Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: fg)),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// OrderStatusChip
// ─────────────────────────────────────────────────────────────────────────────

class OrderStatusChip extends StatelessWidget {
  final String status;

  const OrderStatusChip({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    final (label, fg, bg) = switch (status) {
      'pending_confirmation' => ('Pending', EwTheme.amber, const Color(0xFFFEF3C7)),
      'confirmed'            => ('Confirmed', EwTheme.badgeBlue, EwTheme.badgeBlueSurface),
      'awaiting_payment'     => ('Awaiting Payment', EwTheme.red, const Color(0xFFFEE2E2)),
      'processing'           => ('Processing', EwTheme.badgeBlue, EwTheme.badgeBlueSurface),
      'ready'                => ('Ready', EwTheme.green, const Color(0xFFDCFCE7)),
      'partially_shipped'    => ('Part. Shipped', EwTheme.amber, const Color(0xFFFEF3C7)),
      'shipped'              => ('Shipped', EwTheme.badgeBlue, EwTheme.badgeBlueSurface),
      'delivered'            => ('Delivered', EwTheme.green, const Color(0xFFDCFCE7)),
      'completed'            => ('Completed', EwTheme.green, const Color(0xFFDCFCE7)),
      'cancelled'            => ('Cancelled', EwTheme.textMuted, EwTheme.badgeGraySurface),
      'disputed'             => ('Disputed', EwTheme.red, const Color(0xFFFEE2E2)),
      _                      => (status, EwTheme.textSecondary, EwTheme.badgeGraySurface),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: EwTheme.radius4),
      child: Text(label, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: fg)),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// PaymentPlanSelector
// ─────────────────────────────────────────────────────────────────────────────

class PaymentPlanSelector extends StatelessWidget {
  final List<String> plans;
  final String selected;
  final double groupTotal;
  final int depositPercent;
  final double? creditAvailable;
  final ValueChanged<String> onChanged;

  const PaymentPlanSelector({super.key,
    required this.plans, required this.selected, required this.groupTotal,
    required this.depositPercent, this.creditAvailable, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    if (plans.length <= 1 && plans.contains('prepaid')) {
      return const SizedBox.shrink();
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Payment Plan', style: EwTheme.label),
        const SizedBox(height: 8),
        ...plans.map((plan) => _planTile(plan)),
      ],
    );
  }

  Widget _planTile(String plan) {
    final isSelected = plan == selected;
    final depositAmt = groupTotal * depositPercent / 100;
    final balanceAmt = groupTotal - depositAmt;

    final (title, subtitle) = switch (plan) {
      'prepaid' => ('Pay Now', 'Full ${EwTheme.formatPrice(groupTotal)} upfront'),
      'deposit' => ('Deposit ${depositPercent}%',
          '${EwTheme.formatPrice(depositAmt)} now · ${EwTheme.formatPrice(balanceAmt)} on delivery'),
      'credit'  => ('Net Terms (Credit)',
          creditAvailable != null ? 'Available: ${EwTheme.formatPrice(creditAvailable!)}' : 'Pay within net terms'),
      _         => (plan, ''),
    };

    return GestureDetector(
      onTap: () => onChanged(plan),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: isSelected ? EwTheme.navy.withOpacity(0.04) : EwTheme.surface,
          border: Border.all(
            color: isSelected ? EwTheme.navy : EwTheme.border,
            width: isSelected ? 2 : 1,
          ),
          borderRadius: EwTheme.radius8,
        ),
        child: Row(children: [
          AnimatedContainer(
            duration: const Duration(milliseconds: 150),
            width: 18, height: 18,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(color: isSelected ? EwTheme.navy : EwTheme.border, width: 2),
            ),
            child: isSelected
              ? const Center(child: CircleAvatar(radius: 4, backgroundColor: EwTheme.navy))
              : null,
          ),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: EwTheme.heading3.copyWith(fontSize: 13)),
            if (subtitle.isNotEmpty)
              Text(subtitle, style: EwTheme.bodySmall),
          ])),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// EmptyState
// ─────────────────────────────────────────────────────────────────────────────

class EwEmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String? subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;

  const EwEmptyState({super.key, required this.icon, required this.title,
    this.subtitle, this.actionLabel, this.onAction});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, size: 56, color: EwTheme.textMuted),
          const SizedBox(height: 16),
          Text(title, style: EwTheme.heading2, textAlign: TextAlign.center),
          if (subtitle != null) ...[
            const SizedBox(height: 8),
            Text(subtitle!, style: EwTheme.bodySmall, textAlign: TextAlign.center),
          ],
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: 20),
            ElevatedButton(
              onPressed: onAction,
              style: EwTheme.primaryButton,
              child: Text(actionLabel!),
            ),
          ],
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// EwSectionHeader
// ─────────────────────────────────────────────────────────────────────────────

class EwSectionHeader extends StatelessWidget {
  final String title;
  final String? actionLabel;
  final VoidCallback? onAction;

  const EwSectionHeader({super.key, required this.title, this.actionLabel, this.onAction});

  @override
  Widget build(BuildContext context) {
    return Row(children: [
      Container(width: 3, height: 18, color: EwTheme.orange,
        margin: const EdgeInsets.only(right: 8)),
      Expanded(child: Text(title, style: EwTheme.heading2)),
      if (actionLabel != null)
        TextButton(
          onPressed: onAction,
          style: TextButton.styleFrom(foregroundColor: EwTheme.orange),
          child: Text(actionLabel!, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w600)),
        ),
    ]);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// EwErrorRetry
// ─────────────────────────────────────────────────────────────────────────────

class EwErrorRetry extends StatelessWidget {
  final Object error;
  final VoidCallback onRetry;

  const EwErrorRetry({super.key, required this.error, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.wifi_off_rounded, size: 48, color: EwTheme.textMuted),
          const SizedBox(height: 12),
          Text('Something went wrong', style: EwTheme.heading3),
          const SizedBox(height: 4),
          Text(error.toString(), style: EwTheme.bodySmall, textAlign: TextAlign.center, maxLines: 3),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh, size: 16),
            label: const Text('Retry'),
            style: EwTheme.secondaryButton.copyWith(
              minimumSize: WidgetStatePropertyAll(const Size(140, 40)),
            ),
          ),
        ]),
      ),
    );
  }
}
