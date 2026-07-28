import 'package:flutter/material.dart';
import '../../core/services/vendor_repository.dart';
import '../../core/theme/vc.dart';

void showOrderDetail(BuildContext context, int orderId) {
  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: context.vcSurface,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
    builder: (_) => _OrderDetailSheet(orderId: orderId),
  );
}

class _OrderDetailSheet extends StatefulWidget {
  final int orderId;
  const _OrderDetailSheet({required this.orderId});
  @override
  State<_OrderDetailSheet> createState() => _OrderDetailSheetState();
}

class _OrderDetailSheetState extends State<_OrderDetailSheet> {
  Map<String, dynamic>? _order;
  bool _loading = true;
  bool _acting = false;
  String? _error;

  static String _fmt(dynamic v) => (double.tryParse('${v ?? 0}') ?? 0).toStringAsFixed(2);

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final o = await VendorRepository.instance.orderDetail(widget.orderId);
      if (mounted) setState(() { _order = o; _loading = false; });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  Future<void> _accept() async {
    setState(() => _acting = true);
    try {
      await VendorRepository.instance.acceptOrder(widget.orderId);
      if (mounted) { Navigator.pop(context); _showSnack('Order accepted!', VC.green); }
    } catch (e) {
      _showSnack('$e', VC.red);
      setState(() => _acting = false);
    }
  }

  Future<void> _reject() async {
    final reason = await _rejectDialog();
    if (reason == null || reason.isEmpty) return;
    setState(() => _acting = true);
    try {
      await VendorRepository.instance.rejectOrder(widget.orderId, reason);
      if (mounted) { Navigator.pop(context); _showSnack('Order rejected', VC.amber); }
    } catch (e) {
      _showSnack('$e', VC.red);
      setState(() => _acting = false);
    }
  }

  Future<void> _markReady() async {
    setState(() => _acting = true);
    try {
      await VendorRepository.instance.markReady(widget.orderId);
      if (mounted) { Navigator.pop(context); _showSnack('Order marked ready for pickup!', VC.green); }
    } catch (e) {
      _showSnack('$e', VC.red);
      setState(() => _acting = false);
    }
  }

  void _showSnack(String msg, Color color) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg), backgroundColor: color));
  }

  Future<String?> _rejectDialog() async {
    final ctrl = TextEditingController();
    return showDialog<String>(context: context, builder: (ctx) => AlertDialog(
      backgroundColor: ctx.vcCard,
      title: Text('Reject Order', style: TextStyle(color: ctx.vcText, fontWeight: FontWeight.w800)),
      content: TextField(
        controller: ctrl, autofocus: true,
        style: TextStyle(color: ctx.vcText),
        decoration: InputDecoration(hintText: 'Reason for rejection', hintStyle: TextStyle(color: ctx.vcTextMute)),
      ),
      actions: [
        TextButton(onPressed: () => Navigator.pop(ctx), child: Text('Cancel', style: TextStyle(color: ctx.vcTextSec))),
        ElevatedButton(
          style: ElevatedButton.styleFrom(backgroundColor: VC.red),
          onPressed: () => Navigator.pop(ctx, ctrl.text.trim()),
          child: const Text('Reject', style: TextStyle(color: Colors.white)),
        ),
      ],
    ));
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.85, maxChildSize: 0.95, minChildSize: 0.5,
      expand: false,
      builder: (_, sc) => _loading
        ? const Center(child: CircularProgressIndicator(color: VC.orange))
        : _error != null
          ? Center(child: Text(_error!, style: const TextStyle(color: VC.red)))
          : _buildContent(sc),
    );
  }

  Widget _buildContent(ScrollController sc) {
    final o = _order!;
    final status = o['status'] as String? ?? 'pending';
    final items  = o['items'] as List? ?? [];
    final statusColor = {'pending': VC.amber, 'confirmed': VC.blue, 'ready_for_pickup': VC.green, 'delivered': VC.green, 'cancelled': VC.red}[status] ?? context.vcTextSec;

    return Column(children: [
      // Handle
      Container(width: 36, height: 4, margin: const EdgeInsets.only(top: 12, bottom: 16),
        decoration: BoxDecoration(color: context.vcBorder, borderRadius: BorderRadius.circular(2))),

      Expanded(child: ListView(controller: sc, padding: const EdgeInsets.symmetric(horizontal: 20), children: [
        // Header
        Row(children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('#${o['order_number'] ?? ''}', style: TextStyle(color: context.vcText, fontSize: 20, fontWeight: FontWeight.w900)),
            const SizedBox(height: 4),
            Row(children: [
              Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(color: statusColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(8)),
                child: Text(status.replaceAll('_', ' ').toUpperCase(), style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.w800))),
              const SizedBox(width: 8),
              Text(o['module_slug'] ?? '', style: TextStyle(color: context.vcTextSec, fontSize: 11)),
            ]),
          ])),
          Text('\$${_fmt(o['total_amount'])}', style: const TextStyle(color: VC.green, fontSize: 22, fontWeight: FontWeight.w900)),
        ]),
        const SizedBox(height: 16),
        Divider(color: context.vcBorder),
        const SizedBox(height: 12),

        // Customer
        _sectionTitle('Customer'),
        _infoRow(Icons.person_rounded, o['user']?['name'] ?? 'Customer'),
        _infoRow(Icons.phone_rounded, o['user']?['phone'] ?? ''),
        if (o['delivery_address'] != null)
          _infoRow(Icons.location_on_rounded, o['delivery_address'].toString()),
        if (o['note'] != null && o['note'].toString().isNotEmpty)
          _infoRow(Icons.note_rounded, o['note'].toString()),
        const SizedBox(height: 12),
        Divider(color: context.vcBorder),
        const SizedBox(height: 12),

        // Items
        _sectionTitle('Order Items (${items.length})'),
        ...items.map((item) => Padding(
          padding: const EdgeInsets.only(bottom: 8),
          child: Row(children: [
            Container(width: 36, height: 36, decoration: BoxDecoration(color: VC.orangeDim, borderRadius: BorderRadius.circular(8)),
              child: const Icon(Icons.fastfood_rounded, color: VC.orange, size: 18)),
            const SizedBox(width: 10),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(item['product_name'] ?? item['name'] ?? '', style: TextStyle(color: context.vcText, fontWeight: FontWeight.w700, fontSize: 13)),
              if (item['variant_name'] != null)
                Text(item['variant_name'], style: TextStyle(color: context.vcTextSec, fontSize: 11)),
            ])),
            Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
              Text('×${item['quantity'] ?? 1}', style: TextStyle(color: context.vcTextSec, fontSize: 12)),
              Text('\$${_fmt(item['total_price'] ?? item['price'])}', style: TextStyle(color: context.vcText, fontWeight: FontWeight.w700, fontSize: 13)),
            ]),
          ]),
        )),
        Divider(color: context.vcBorder),
        const SizedBox(height: 8),

        // Totals
        _total('Items Subtotal', o['subtotal']),
        _total('Delivery Fee', o['delivery_fee']),
        if ((double.tryParse('${o['discount'] ?? 0}') ?? 0) > 0)
          _total('Discount', '-\$${_fmt(o['discount'])}', color: VC.green),
        const SizedBox(height: 4),
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Text('Order Total', style: TextStyle(color: context.vcText, fontWeight: FontWeight.w900, fontSize: 15)),
          Text('\$${_fmt(o['total_amount'])}', style: const TextStyle(color: VC.green, fontWeight: FontWeight.w900, fontSize: 16)),
        ]),
        const SizedBox(height: 12),
        Divider(color: context.vcBorder),
        const SizedBox(height: 8),
        // Vendor earnings breakdown
        _earningsRow('Platform Commission', '-\$${_fmt(o['commission'])}', VC.red),
        const SizedBox(height: 6),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(color: VC.greenDim, borderRadius: BorderRadius.circular(10)),
          child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Row(children: [
              const Icon(Icons.account_balance_wallet_rounded, color: VC.green, size: 16),
              const SizedBox(width: 6),
              Text('Your Earning', style: TextStyle(color: VC.green, fontWeight: FontWeight.w900, fontSize: 14)),
            ]),
            Text('\$${_fmt((double.tryParse('${o['total_amount'] ?? 0}') ?? 0) - (double.tryParse('${o['commission'] ?? 0}') ?? 0))}',
              style: const TextStyle(color: VC.green, fontWeight: FontWeight.w900, fontSize: 16)),
          ]),
        ),
        const SizedBox(height: 20),

        // Payment
        _infoRow(Icons.payment_rounded, 'Payment: ${(o['payment_method'] ?? 'cash').toString().replaceAll('_', ' ')}'),
        const SizedBox(height: 20),
      ])),

      // Action buttons
      if (!_acting) _actionButtons(status),
      if (_acting) const Padding(padding: EdgeInsets.all(20), child: CircularProgressIndicator(color: VC.orange)),
      const SizedBox(height: 16),
    ]);
  }

  Widget _actionButtons(String status) {
    if (status == 'pending') {
      return Padding(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8), child: Row(children: [
        Expanded(child: OutlinedButton(
          style: OutlinedButton.styleFrom(side: const BorderSide(color: VC.red), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)), padding: const EdgeInsets.symmetric(vertical: 14)),
          onPressed: _reject,
          child: const Text('Reject', style: TextStyle(color: VC.red, fontWeight: FontWeight.w800)),
        )),
        const SizedBox(width: 12),
        Expanded(flex: 2, child: ElevatedButton(
          style: ElevatedButton.styleFrom(backgroundColor: VC.green, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)), padding: const EdgeInsets.symmetric(vertical: 14)),
          onPressed: _accept,
          child: const Text('Accept Order', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
        )),
      ]));
    }
    if (status == 'confirmed') {
      return Padding(padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8), child: SizedBox(width: double.infinity,
        child: ElevatedButton(
          style: ElevatedButton.styleFrom(backgroundColor: VC.blue, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)), padding: const EdgeInsets.symmetric(vertical: 14)),
          onPressed: _markReady,
          child: const Text('Mark Ready for Pickup', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
        )));
    }
    return const SizedBox();
  }

  Widget _sectionTitle(String t) => Padding(padding: const EdgeInsets.only(bottom: 10),
    child: Text(t, style: TextStyle(color: context.vcTextSec, fontSize: 11, fontWeight: FontWeight.w800, letterSpacing: 1)));

  Widget _infoRow(IconData icon, String text) => Padding(padding: const EdgeInsets.only(bottom: 8),
    child: Row(children: [
      Icon(icon, color: context.vcTextMute, size: 16),
      const SizedBox(width: 8),
      Expanded(child: Text(text, style: TextStyle(color: context.vcText, fontSize: 13))),
    ]));

  Widget _total(String label, dynamic val, {Color? color}) => Padding(padding: const EdgeInsets.only(bottom: 6),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(color: context.vcTextSec, fontSize: 13)),
      Text(color != null ? val.toString() : '\$${_fmt(val)}', style: TextStyle(color: color ?? context.vcText, fontSize: 13, fontWeight: FontWeight.w600)),
    ]));

  Widget _earningsRow(String label, String val, Color color) => Padding(padding: const EdgeInsets.only(bottom: 4),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: TextStyle(color: context.vcTextSec, fontSize: 12)),
      Text(val, style: TextStyle(color: color, fontSize: 13, fontWeight: FontWeight.w700)),
    ]));
}
