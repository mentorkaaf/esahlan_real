import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:printing/printing.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/utils/error_handler.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../providers/order_provider.dart';
import '../../data/models/order_model.dart';
import '../../services/picking_slip_pdf.dart';
import '../../../../core/l10n/app_strings.dart';

const _navy  = Color(0xFF07003B);
const _navyL = Color(0xFF1B0F6E);
const _amber = Color(0xFFFF8A00);

class OrderDetailScreen extends ConsumerWidget {
  final int orderId;
  const OrderDetailScreen({super.key, required this.orderId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final orderAsync = ref.watch(orderDetailProvider(orderId));

    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
      body: orderAsync.when(
        loading: () => const Scaffold(
          body: Center(child: CircularProgressIndicator(color: _amber)),
        ),
        error: (e, _) => Scaffold(
          appBar: AppBar(leading: BackButton(onPressed: () => context.pop())),
          body: Center(child: Text(AppErrorHandler.message(e))),
        ),
        data: (order) => _OrderDetailBody(order: order, orderId: orderId, ref: ref),
      ),
    );
  }
}

class _OrderDetailBody extends StatelessWidget {
  final OrderModel order;
  final int orderId;
  final WidgetRef ref;
  const _OrderDetailBody({required this.order, required this.orderId, required this.ref});

  bool get isParcel => order.moduleSlug == 'eparcel';

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return CustomScrollView(
      slivers: [
        // ── Hero status app bar ──────────────────────────────────────
        SliverAppBar(
          expandedHeight: 200,
          pinned: true,
          backgroundColor: order.statusColor,
          leading: IconButton(
            icon: const Icon(Icons.arrow_back_ios_new_rounded, color: Colors.white, size: 20),
            onPressed: () => context.pop(),
          ),
          actions: [
            // Picking Slip button
            IconButton(
              tooltip: 'Picking Slip',
              icon: const Icon(Icons.receipt_outlined, color: Colors.white, size: 22),
              onPressed: () => _openPickingSlip(context, order),
            ),
            if (order.isActive)
              TextButton.icon(
                onPressed: () => context.push('/orders/$orderId/tracking'),
                icon: const Icon(Icons.location_on_outlined, color: Colors.white, size: 16),
                label: Text(l.trackBtn, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
              ),
          ],
          flexibleSpace: FlexibleSpaceBar(
            collapseMode: CollapseMode.pin,
            background: Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [order.statusColor, order.statusColor.withOpacity(0.75)],
                  begin: Alignment.topLeft, end: Alignment.bottomRight,
                ),
              ),
              child: Stack(children: [
                // deco
                Positioned(right: -30, top: -20,
                  child: Container(width: 130, height: 130,
                    decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withOpacity(0.07)))),
                Positioned(left: -20, bottom: -30,
                  child: Container(width: 100, height: 100,
                    decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withOpacity(0.05)))),
                // content
                Positioned.fill(child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const SizedBox(height: 40),
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.15),
                        shape: BoxShape.circle,
                      ),
                      child: Icon(_statusIcon(order.status), color: Colors.white, size: 32),
                    ),
                    const SizedBox(height: 10),
                    Text(order.statusLabel,
                      style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(order.orderNumber,
                      style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 13)),
                    const SizedBox(height: 4),
                    Text('\$${order.totalAmount.toStringAsFixed(2)}',
                      style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800)),
                  ],
                )),
              ]),
            ),
          ),
        ),

        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

              // ── Parcel route card ──────────────────────────────────
              if (isParcel && order.parcelDetails != null) ...[
                _ParcelRouteCard(p: order.parcelDetails!),
                const SizedBox(height: 14),
              ],

              // ── Regular items (non-parcel) ─────────────────────────
              if (!isParcel && order.items.isNotEmpty) ...[
                _DetailCard(
                  title: l.orderItems,
                  icon: Icons.shopping_bag_outlined,
                  child: Column(children: [
                    ...order.items.map((item) => Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Container(
                          width: 38, height: 38,
                          decoration: BoxDecoration(
                            color: context.colors.surfaceBg,
                            borderRadius: BorderRadius.circular(10)),
                          child: item.imageUrl != null
                              ? ClipRRect(borderRadius: BorderRadius.circular(10),
                                  child: NetImage(url: item.imageUrl, fit: BoxFit.cover,
                                    errorWidget: const Icon(Icons.shopping_bag_outlined, size: 18, color: AppColors.textGrey)))
                              : const Icon(Icons.shopping_bag_outlined, size: 18, color: AppColors.textGrey),
                        ),
                        const SizedBox(width: 10),
                        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(item.productName,
                            style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: context.colors.navyText)),
                          // Variant badge
                          if (item.variantDisplay != null)
                            Container(
                              margin: const EdgeInsets.only(top: 4),
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF0F4FF),
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(color: const Color(0xFFBFD0FF)),
                              ),
                              child: Text(item.variantDisplay!,
                                style: const TextStyle(fontSize: 11, color: Color(0xFF3B5BDB), fontWeight: FontWeight.w600)),
                            ),
                          // Unit price × qty
                          Padding(padding: const EdgeInsets.only(top: 4),
                            child: Text('\$${item.price.toStringAsFixed(2)} × ${item.quantity}',
                              style: const TextStyle(fontSize: 11, color: AppColors.textGrey))),
                        ])),
                        const SizedBox(width: 8),
                        Text('\$${item.total.toStringAsFixed(2)}',
                          style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 14, color: _amber)),
                      ]),
                    )),
                  ]),
                ),
                const SizedBox(height: 14),
              ],

              // ── Payment summary ────────────────────────────────────
              _DetailCard(
                title: l.paymentSummary,
                icon: Icons.receipt_long_outlined,
                child: Column(children: [
                  if (!isParcel) ...[
                    _SumRow(l.subtotal, '\$${(order.totalAmount - (order.deliveryFee ?? 0) + (order.discount ?? 0)).toStringAsFixed(2)}'),
                    if ((order.deliveryFee ?? 0) > 0)
                      _SumRow(l.deliveryFee, '\$${order.deliveryFee!.toStringAsFixed(2)}'),
                    if ((order.discount ?? 0) > 0)
                      _SumRow(l.discount, '-\$${order.discount!.toStringAsFixed(2)}', color: Colors.green),
                    const Divider(height: 16, color: AppColors.divider),
                  ],
                  _SumRow(l.total, '\$${order.totalAmount.toStringAsFixed(2)}', bold: true),
                ]),
              ),
              const SizedBox(height: 14),

              // ── Payment method ─────────────────────────────────────
              _DetailCard(
                title: l.payment,
                icon: Icons.payment_outlined,
                child: Row(children: [
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: context.colors.surfaceBg,
                      borderRadius: BorderRadius.circular(10)),
                    child: Icon(
                      order.paymentMethod?.toLowerCase() == 'wallet'
                          ? Icons.account_balance_wallet_outlined
                          : Icons.payments_outlined,
                      color: _amber, size: 22),
                  ),
                  SizedBox(width: 12),
                  Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(
                        (order.paymentMethod ?? 'cash')
                            .split('_')
                            .map((w) => w.isEmpty ? '' : '${w[0].toUpperCase()}${w.substring(1)}')
                            .join(' '),
                      style: TextStyle(fontWeight: FontWeight.w700, fontSize: 15, color: context.colors.navyText)),
                    const SizedBox(height: 2),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      decoration: BoxDecoration(
                        color: order.paymentStatus == 'paid'
                            ? Colors.green.shade50 : Colors.orange.shade50,
                        borderRadius: BorderRadius.circular(6)),
                      child: Text(
                        (order.paymentStatus ?? 'pending').toUpperCase(),
                        style: TextStyle(
                          fontSize: 11, fontWeight: FontWeight.w700,
                          color: order.paymentStatus == 'paid' ? Colors.green : Colors.orange)),
                    ),
                  ]),
                ]),
              ),
              const SizedBox(height: 14),

              // ── Status timeline ────────────────────────────────────
              if (order.history != null && (order.history as List).isNotEmpty) ...[
                _DetailCard(
                  title: l.statusTimeline,
                  icon: Icons.timeline_outlined,
                  child: Column(children: [
                    ...(order.history as List).asMap().entries.map((e) {
                      final h = e.value as Map;
                      final isLast = e.key == (order.history as List).length - 1;
                      return _TimelineItem(
                        status: h['status'] ?? '',
                        note: h['note'],
                        time: h['created_at'],
                        isLast: isLast,
                        isCurrent: isLast,
                      );
                    }),
                  ]),
                ),
                const SizedBox(height: 14),
              ],

              // ── Picking Slip button ────────────────────────────────
              GestureDetector(
                onTap: () => _openPickingSlip(context, order),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      colors: [Color(0xFF07003B), Color(0xFF1B0F6E)],
                    ),
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: [BoxShadow(color: const Color(0xFF07003B).withOpacity(0.3),
                      blurRadius: 10, offset: const Offset(0, 4))],
                  ),
                  child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                    const Icon(Icons.receipt_outlined, color: Color(0xFFFF8A00), size: 20),
                    const SizedBox(width: 8),
                    const Text('View Picking Slip',
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800,
                        fontSize: 14, letterSpacing: 0.5)),
                  ]),
                ),
              ),
              const SizedBox(height: 12),

              // ── Cancel button ──────────────────────────────────────
              if (order.status == 'pending')
                _CancelButton(orderId: orderId, ref: ref),

              const SizedBox(height: 40),
            ]),
          ),
        ),
      ],
    );
  }

  IconData _statusIcon(String status) {
    switch (status) {
      case 'delivered':      return Icons.check_circle_outline_rounded;
      case 'cancelled':      return Icons.cancel_outlined;
      case 'preparing':      return Icons.restaurant_outlined;
      case 'out_for_delivery': return Icons.delivery_dining_rounded;
      default:               return Icons.receipt_long_outlined;
    }
  }

  Future<void> _openPickingSlip(BuildContext context, OrderModel order) async {
    try {
      // Show loading snackbar
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Generating picking slip...'),
          duration: Duration(seconds: 1)),
      );
      final pdfBytes = await PickingSlipPdf.generate(order);
      await Printing.layoutPdf(
        onLayout: (_) async => pdfBytes,
        name: 'PickingSlip-${order.orderNumber}.pdf',
      );
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: ${e.toString()}'),
            backgroundColor: Colors.red),
        );
      }
    }
  }
}

// ══════════════════════════════════════════════════════════════════════════════
// PARCEL ROUTE CARD
// ══════════════════════════════════════════════════════════════════════════════

class _ParcelRouteCard extends StatelessWidget {
  final Map<String, dynamic> p;
  const _ParcelRouteCard({required this.p});

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return Container(
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [_navy, _navyL],
          begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [BoxShadow(color: context.colors.navyText.withOpacity(0.3), blurRadius: 16, offset: const Offset(0, 6))],
      ),
      child: Stack(children: [
        Positioned(right: -20, top: -20,
          child: Container(width: 100, height: 100,
            decoration: BoxDecoration(shape: BoxShape.circle, color: Colors.white.withOpacity(0.04)))),
        Padding(
          padding: const EdgeInsets.all(20),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [

            // label
            Row(children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: _amber.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: _amber.withOpacity(0.3))),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.local_shipping_rounded, color: _amber, size: 14),
                  const SizedBox(width: 6),
                  Text(l.parcelDelivery, style: const TextStyle(color: _amber, fontSize: 12, fontWeight: FontWeight.w700)),
                ]),
              ),
            ]),
            const SizedBox(height: 16),

            // route
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Expanded(child: _RouteStop(
                icon: Icons.my_location_rounded,
                iconColor: const Color(0xFF22C55E),
                label: l.pickup,
                district: p['pickup_district'] ?? '—',
                name: p['sender_name'] ?? '—',
                phone: p['sender_phone'] ?? '—',
              )),
              Column(children: [
                const SizedBox(height: 20),
                Container(width: 28, height: 28,
                  decoration: BoxDecoration(
                    color: _amber.withOpacity(0.1),
                    shape: BoxShape.circle,
                    border: Border.all(color: _amber.withOpacity(0.3))),
                  child: const Icon(Icons.east_rounded, color: _amber, size: 14)),
              ]),
              Expanded(child: _RouteStop(
                icon: Icons.location_on_rounded,
                iconColor: Colors.redAccent,
                label: l.delivery,
                district: p['delivery_district'] ?? '—',
                name: p['recipient_name'] ?? '—',
                phone: p['recipient_phone'] ?? '—',
                align: CrossAxisAlignment.end,
              )),
            ]),

            // parcel type
            if ((p['parcel_type'] ?? '').toString().isNotEmpty) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: const Color(0xFFFF8A00).withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(color: const Color(0xFFFF8A00).withValues(alpha: 0.4)),
                ),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.inventory_2_rounded, color: Color(0xFFFF8A00), size: 13),
                  const SizedBox(width: 5),
                  Text('${p['parcel_type']}',
                    style: const TextStyle(color: Color(0xFFFF8A00), fontSize: 12, fontWeight: FontWeight.w700)),
                ]),
              ),
            ],

            // description
            if ((p['description'] ?? '').toString().isNotEmpty) ...[
              const SizedBox(height: 16),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.07),
                  borderRadius: BorderRadius.circular(10)),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(l.packageContents, style: const TextStyle(color: Colors.white54, fontSize: 10, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 4),
                  Text('${p['description']}', style: const TextStyle(color: Colors.white, fontSize: 13)),
                ]),
              ),
            ],
          ]),
        ),
      ]),
    );
  }
}

class _RouteStop extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String label, district, name, phone;
  final CrossAxisAlignment align;
  const _RouteStop({
    required this.icon, required this.iconColor,
    required this.label, required this.district,
    required this.name, required this.phone,
    this.align = CrossAxisAlignment.start,
  });

  @override
  Widget build(BuildContext context) => Column(crossAxisAlignment: align, children: [
    Row(
      mainAxisSize: MainAxisSize.min,
      children: align == CrossAxisAlignment.end
          ? [Text(label, style: const TextStyle(color: Colors.white54, fontSize: 11)),
             const SizedBox(width: 4),
             Icon(icon, color: iconColor, size: 14)]
          : [Icon(icon, color: iconColor, size: 14),
             const SizedBox(width: 4),
             Text(label, style: const TextStyle(color: Colors.white54, fontSize: 11))],
    ),
    const SizedBox(height: 6),
    Text(district, textAlign: align == CrossAxisAlignment.end ? TextAlign.right : TextAlign.left,
      style: const TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.w800)),
    const SizedBox(height: 6),
    Text(name, textAlign: align == CrossAxisAlignment.end ? TextAlign.right : TextAlign.left,
      style: const TextStyle(color: Colors.white70, fontSize: 12, fontWeight: FontWeight.w600)),
    Text(phone, textAlign: align == CrossAxisAlignment.end ? TextAlign.right : TextAlign.left,
      style: const TextStyle(color: Colors.white38, fontSize: 11)),
  ]);
}

// ══════════════════════════════════════════════════════════════════════════════
// GENERIC COMPONENTS
// ══════════════════════════════════════════════════════════════════════════════

class _DetailCard extends StatelessWidget {
  final String title;
  final IconData icon;
  final Widget child;
  const _DetailCard({required this.title, required this.icon, required this.child});

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(color: context.colors.cardBg,
      borderRadius: BorderRadius.circular(18),
      boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 10, offset: const Offset(0, 3))],
    ),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(16, 14, 16, 0),
        child: Row(children: [
          Container(
            padding: const EdgeInsets.all(6),
            decoration: BoxDecoration(color: _amber.withOpacity(0.1), borderRadius: BorderRadius.circular(8)),
            child: Icon(icon, size: 16, color: _amber),
          ),
          SizedBox(width: 8),
          Text(title, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800, color: context.colors.navyText)),
        ]),
      ),
      Padding(padding: const EdgeInsets.all(16), child: child),
    ]),
  );
}

class _SumRow extends StatelessWidget {
  final String label, value;
  final bool bold;
  final Color? color;
  const _SumRow(this.label, this.value, {this.bold = false, this.color});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 4),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(
        color: bold ? context.colors.navyText : AppColors.textGrey,
        fontWeight: bold ? FontWeight.w700 : FontWeight.w400,
        fontSize: bold ? 15 : 13)),
      Text(value, style: TextStyle(
        color: color ?? (bold ? context.colors.navyText : context.colors.navyText),
        fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
        fontSize: bold ? 15 : 13)),
    ]),
  );
}

class _TimelineItem extends StatelessWidget {
  final String status;
  final dynamic note, time;
  final bool isLast, isCurrent;
  const _TimelineItem({
    required this.status, this.note, this.time,
    this.isLast = false, this.isCurrent = false,
  });

  @override
  Widget build(BuildContext context) => Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Column(children: [
      Container(
        width: 12, height: 12,
        decoration: BoxDecoration(
          color: isCurrent ? _amber : AppColors.textLight,
          shape: BoxShape.circle,
          border: Border.all(color: isCurrent ? _amber : AppColors.divider, width: 2)),
      ),
      if (!isLast) Container(width: 2, height: 36, color: AppColors.divider),
    ]),
    const SizedBox(width: 12),
    Expanded(child: Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(status.replaceAll('_', ' ').toUpperCase(),
          style: TextStyle(
            fontSize: 12, fontWeight: FontWeight.w700,
            color: isCurrent ? _amber : context.colors.navyText)),
        if (note != null && note.toString().isNotEmpty)
          Text('$note', style: const TextStyle(fontSize: 12, color: AppColors.textGrey)),
        if (time != null)
          Text('$time', style: const TextStyle(fontSize: 11, color: AppColors.textLight)),
      ]),
    )),
  ]);
}

class _CancelButton extends StatelessWidget {
  final int orderId;
  final WidgetRef ref;
  const _CancelButton({required this.orderId, required this.ref});

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    return SizedBox(
    width: double.infinity,
    child: OutlinedButton.icon(
      onPressed: () => _confirm(context),
      icon: const Icon(Icons.cancel_outlined, size: 18),
      label: Text(l.cancelOrder, style: const TextStyle(fontWeight: FontWeight.w700)),
      style: OutlinedButton.styleFrom(
        foregroundColor: Colors.red,
        side: const BorderSide(color: Colors.red),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        padding: const EdgeInsets.symmetric(vertical: 14),
      ),
    ),
  );
  }

  void _confirm(BuildContext context) {
    final l = AppL10n.of(context);
    showDialog(
    context: context,
    builder: (_) => AlertDialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      title: Text(l.cancelOrderQ, style: const TextStyle(fontWeight: FontWeight.w800)),
      content: Text(l.cancelOrderConfirm),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context), child: Text(l.no)),
        ElevatedButton(
          onPressed: () async {
            Navigator.pop(context);
            await ref.read(orderRepositoryProvider).cancelOrder(orderId);
            // ignore: unused_result
            ref.refresh(orderDetailProvider(orderId));
          },
          style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
          child: Text(l.yesCancel),
        ),
      ],
    ),
  );
  }
}
