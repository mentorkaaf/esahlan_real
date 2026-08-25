import 'dart:async';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:path_provider/path_provider.dart';
import 'package:pdf/pdf.dart';
import 'package:printing/printing.dart';
import 'package:share_plus/share_plus.dart';
import 'package:record/record.dart';
import 'package:audioplayers/audioplayers.dart';
import 'package:dio/dio.dart' show FormData, MultipartFile;
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/utils/error_handler.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../providers/order_provider.dart';
import '../../data/models/order_model.dart';
import '../../services/picking_slip_pdf.dart';
import '../../../../core/l10n/app_strings.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/services/realtime_client.dart';
import 'package:geolocator/geolocator.dart';

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

// In-memory cache: orderId → generated image file path
final _slipCache = <int, String>{};

class _OrderDetailBody extends StatefulWidget {
  final OrderModel order;
  final int orderId;
  final WidgetRef ref;
  const _OrderDetailBody({required this.order, required this.orderId, required this.ref});
  @override
  State<_OrderDetailBody> createState() => _OrderDetailBodyState();
}

class _OrderDetailBodyState extends State<_OrderDetailBody> {
  OrderModel get order => widget.order;
  int get orderId => widget.orderId;
  WidgetRef get ref => widget.ref;
  bool get isParcel => order.moduleSlug == 'eparcel';

  @override
  void initState() {
    super.initState();
    // Pre-generate in background so "View Picking Slip" is instant
    if (!_slipCache.containsKey(orderId)) {
      _generateSlipInBackground();
    }
  }

  Future<void> _generateSlipInBackground() async {
    try {
      final path = await _buildSlipImage(order);
      if (mounted) _slipCache[orderId] = path;
    } catch (_) {/* silent — will retry on tap */}
  }

  // ── Generate picking slip image (shared helper) ───────────────────────────
  static Future<String> _buildSlipImage(OrderModel order) async {
    final pdfBytes = await PickingSlipPdf.generate(order);
    final pages = await Printing.raster(pdfBytes, dpi: 300, pages: [0]).toList();
    if (pages.isEmpty) throw Exception('Raster failed');
    final page = pages.first;
    final srcImage = await page.toImage();
    final recorder = ui.PictureRecorder();
    final canvas = Canvas(recorder);
    canvas.drawRect(
      Rect.fromLTWH(0, 0, page.width.toDouble(), page.height.toDouble()),
      Paint()..color = Colors.white,
    );
    canvas.drawImage(srcImage, Offset.zero, Paint());
    final composited = await recorder.endRecording().toImage(page.width, page.height);
    final pngBytes = await composited.toByteData(format: ui.ImageByteFormat.png);
    final tmpDir = await getTemporaryDirectory();
    final file = File('${tmpDir.path}/PickingSlip-${order.orderNumber}.png');
    await file.writeAsBytes(pngBytes!.buffer.asUint8List());
    return file.path;
  }

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

              // ── Driver card (shown when driver is assigned) ────────
              if (order.driver != null) ...[
                _DriverCard(driver: order.driver!, orderId: orderId),
                const SizedBox(height: 14),
              ],

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
    // Cached → show instantly, no spinner
    if (_slipCache.containsKey(order.id)) {
      await showModalBottomSheet(
        context: context,
        isScrollControlled: true,
        backgroundColor: Colors.transparent,
        builder: (_) => _PickingSlipPreview(
          imagePath: _slipCache[order.id]!,
          orderNumber: order.orderNumber,
        ),
      );
      return;
    }

    // Not cached yet — show brief spinner
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const Center(
        child: Card(
          child: Padding(
            padding: EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              CircularProgressIndicator(color: AppColors.primary),
              SizedBox(height: 14),
              Text('Preparing...', style: TextStyle(fontWeight: FontWeight.w600)),
            ]),
          ),
        ),
      ),
    );

    try {
      final path = await _buildSlipImage(order);
      _slipCache[order.id] = path;
      if (context.mounted) Navigator.of(context, rootNavigator: true).pop();
      if (context.mounted) {
        await showModalBottomSheet(
          context: context,
          isScrollControlled: true,
          backgroundColor: Colors.transparent,
          builder: (_) => _PickingSlipPreview(
            imagePath: path,
            orderNumber: order.orderNumber,
          ),
        );
      }
    } catch (e) {
      if (context.mounted) {
        Navigator.of(context, rootNavigator: true).pop();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Error: ${e.toString()}'), backgroundColor: Colors.red),
        );
      }
    }
  }
}

// ══════════════════════════════════════════════════════════════════════════════
// PICKING SLIP PREVIEW BOTTOM SHEET
// ══════════════════════════════════════════════════════════════════════════════

class _PickingSlipPreview extends StatelessWidget {
  final String imagePath;
  final String orderNumber;
  const _PickingSlipPreview({required this.imagePath, required this.orderNumber});

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.92,
      minChildSize: 0.5,
      maxChildSize: 0.97,
      builder: (_, scrollCtrl) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          children: [
            // ── Handle ───────────────────────────────────────────────────────
            Container(
              margin: const EdgeInsets.symmetric(vertical: 10),
              width: 40, height: 4,
              decoration: BoxDecoration(color: Colors.grey.shade300, borderRadius: BorderRadius.circular(2)),
            ),
            // ── Header row: title + close ─────────────────────────────────────
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 8, 0),
              child: Row(
                children: [
                  const Text('Picking Slip',
                      style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: Color(0xFF07003B))),
                  const Spacer(),
                  IconButton(
                    icon: const Icon(Icons.close_rounded, color: Color(0xFF07003B)),
                    onPressed: () => Navigator.of(context).pop(),
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            // ── Image (scrollable + zoomable) ─────────────────────────────────
            Expanded(
              child: InteractiveViewer(
                minScale: 0.5,
                maxScale: 4.0,
                child: SingleChildScrollView(
                  controller: scrollCtrl,
                  padding: const EdgeInsets.fromLTRB(12, 12, 12, 0),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.file(File(imagePath), fit: BoxFit.fitWidth),
                  ),
                ),
              ),
            ),
            // ── Share button — full width, always visible ─────────────────────
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                child: SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton.icon(
                    onPressed: () async {
                      await Share.shareXFiles(
                        [XFile(imagePath, mimeType: 'image/png')],
                        subject: 'Picking Slip — $orderNumber',
                        text: 'eSahlan Picking Slip\nOrder: $orderNumber',
                      );
                    },
                    icon: const Icon(Icons.share_rounded, size: 20),
                    label: const Text('Share Picking Slip',
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800)),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF07003B),
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                      elevation: 0,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════════
// DRIVER CARD
// ══════════════════════════════════════════════════════════════════════════════

class _DriverCard extends StatelessWidget {
  final OrderDriverModel driver;
  final int orderId;
  const _DriverCard({required this.driver, required this.orderId});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: context.colors.cardBg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFF22C55E).withOpacity(0.3)),
        boxShadow: [BoxShadow(color: const Color(0xFF22C55E).withOpacity(0.06), blurRadius: 12)],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Container(width: 8, height: 8, decoration: const BoxDecoration(color: Color(0xFF22C55E), shape: BoxShape.circle,
            boxShadow: [BoxShadow(color: Color(0x6622C55E), blurRadius: 6)])),
          const SizedBox(width: 8),
          const Text('YOUR DRIVER', style: TextStyle(color: Color(0xFF22C55E), fontSize: 10, fontWeight: FontWeight.w800, letterSpacing: 1)),
          const Spacer(),
          // Star rating
          Row(children: [
            const Icon(Icons.star_rounded, color: Color(0xFFFFB800), size: 14),
            const SizedBox(width: 3),
            Text(driver.rating.toStringAsFixed(1), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 12, color: Color(0xFFFFB800))),
          ]),
        ]),
        const SizedBox(height: 14),
        Row(children: [
          // Avatar
          Container(
            width: 52, height: 52,
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [Color(0xFF07003B), Color(0xFF1B0F6E)]),
              shape: BoxShape.circle,
            ),
            child: Center(child: Text(
              driver.name.isNotEmpty ? driver.name[0].toUpperCase() : 'D',
              style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 22),
            )),
          ),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(driver.name, style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16, color: context.colors.navyText)),
            if (driver.vehicleType != null)
              Text(driver.vehicleType!.replaceAll('_', ' ').toUpperCase(),
                style: const TextStyle(color: AppColors.textGrey, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.5)),
          ])),
        ]),
        if (driver.phone != null) ...[
          const SizedBox(height: 14),
          Row(children: [
            Expanded(child: _DriverActionBtn(
              icon: Icons.phone_rounded,
              label: 'Call Driver',
              color: const Color(0xFF22C55E),
              onTap: () async {
                final uri = Uri.parse('tel:${driver.phone}');
                // ignore: deprecated_member_use
                if (await canLaunchUrl(uri)) launchUrl(uri);
              },
            )),
            const SizedBox(width: 10),
            Expanded(child: _DriverActionBtn(
              icon: Icons.chat_bubble_rounded,
              label: 'Chat',
              color: const Color(0xFF3B82F6),
              onTap: () => _openDriverChat(context, driver, orderId),
            )),
          ]),
        ],
      ]),
    );
  }

  void _openDriverChat(BuildContext context, OrderDriverModel driver, int orderId) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _DriverChatSheet(driver: driver, orderId: orderId),
    );
  }
}

class _DriverActionBtn extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;
  const _DriverActionBtn({required this.icon, required this.label, required this.color, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: color.withOpacity(0.08),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withOpacity(0.25)),
        ),
        child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(icon, color: color, size: 16),
          const SizedBox(width: 6),
          Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w700, fontSize: 13)),
        ]),
      ),
    );
  }
}

class _DriverChatSheet extends StatefulWidget {
  final OrderDriverModel driver;
  final int orderId;
  const _DriverChatSheet({required this.driver, required this.orderId});
  @override
  State<_DriverChatSheet> createState() => _DriverChatSheetState();
}

class _DriverChatSheetState extends State<_DriverChatSheet> {
  static const _mapsKey = 'AIzaSyA9J4TSypPZv3cr8Zlabn0BSDICD_Ibp-A';

  final _ctrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  final List<_ChatMsg> _msgs = [];
  bool _loading = true;
  bool _sending = false;
  bool _sharingLocation = false;

  // Voice recording
  final _recorder = AudioRecorder();
  bool _recording = false;
  Timer? _recordTimer;
  int _recordSeconds = 0;
  // Pending preview before send
  String? _pendingVoicePath;

  String get _channel => 'private-order-chat.${widget.orderId}';

  @override
  void initState() {
    super.initState();
    _loadHistory();
    _subscribeRealtime();
  }

  Future<void> _loadHistory() async {
    try {
      final resp = await ApiClient.instance.get('/orders/${widget.orderId}/chat');
      final data = resp.data;
      final List msgs = (data is Map ? data['data'] : null) ?? [];
      if (mounted) {
        setState(() {
          _msgs.clear();
          for (final m in msgs) {
            if (m is Map) {
              _msgs.add(_ChatMsg.fromMap(m, myType: 'customer'));
            }
          }
          _loading = false;
        });
        _scrollToBottom();
      }
    } catch (e) {
      debugPrint('[ChatHistory] load failed: $e');
      if (mounted) setState(() => _loading = false);
    }
  }

  void _subscribeRealtime() {
    RealtimeClient.instance.listen(_channel, 'new_message', _onRealtime);
  }

  void _onRealtime(dynamic data) {
    if (!mounted) return;
    final m = data is Map ? data : <String, dynamic>{};
    final msg = _ChatMsg.fromMap(m, myType: 'customer');
    if (msg.type == _ChatMsgType.locationRequest) {
      // Driver requested my location — show inline request
      setState(() => _msgs.add(msg));
      _scrollToBottom();
    } else {
      setState(() => _msgs.add(msg));
      _scrollToBottom();
    }
  }

  void _scrollToBottom() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(_scrollCtrl.position.maxScrollExtent,
            duration: const Duration(milliseconds: 250), curve: Curves.easeOut);
      }
    });
  }

  Future<void> _send() async {
    final text = _ctrl.text.trim();
    if (text.isEmpty || _sending) return;
    _ctrl.clear();
    final optimistic = _ChatMsg(text: text, isMe: true, type: _ChatMsgType.text);
    setState(() { _msgs.add(optimistic); _sending = true; });
    _scrollToBottom();
    try {
      await ApiClient.instance.post('/orders/${widget.orderId}/chat',
          data: {'message': text, 'message_type': 'text'});
    } catch (_) {}
    if (mounted) setState(() => _sending = false);
  }

  // ── Voice recording ────────────────────────────────────────────────────────

  Future<void> _startRecording() async {
    if (_recording) return;
    final hasPermission = await _recorder.hasPermission();
    if (!hasPermission) { _showSnack('Microphone permission required'); return; }
    final dir = await getTemporaryDirectory();
    final path = '${dir.path}/voice_${DateTime.now().millisecondsSinceEpoch}.m4a';
    await _recorder.start(const RecordConfig(encoder: AudioEncoder.aacLc), path: path);
    setState(() { _recording = true; _recordSeconds = 0; });
    _recordTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) setState(() => _recordSeconds++);
    });
  }

  Future<void> _stopRecording({bool cancel = false}) async {
    _recordTimer?.cancel();
    final path = await _recorder.stop();
    if (!mounted) return;
    if (cancel || path == null) {
      setState(() { _recording = false; _pendingVoicePath = null; });
      return;
    }
    setState(() { _recording = false; _pendingVoicePath = path; });
  }

  Future<void> _sendVoice() async {
    final path = _pendingVoicePath;
    if (path == null || _sending) return;
    setState(() { _pendingVoicePath = null; _sending = true; });
    // Optimistic
    final optimistic = _ChatMsg(text: '🎵 Voice message', isMe: true,
        type: _ChatMsgType.voice, voiceUrl: path);
    setState(() => _msgs.add(optimistic));
    _scrollToBottom();
    try {
      final form = FormData.fromMap({
        'message_type': 'voice',
        'voice': await MultipartFile.fromFile(path, filename: 'voice.m4a'),
      });
      await ApiClient.instance.post('/orders/${widget.orderId}/chat', data: form);
    } catch (_) {}
    if (mounted) setState(() => _sending = false);
  }

  // ── Location ───────────────────────────────────────────────────────────────

  Future<void> _shareLocation() async {
    if (_sharingLocation) return;
    setState(() => _sharingLocation = true);
    try {
      final serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        if (mounted) _showSnack('Please enable GPS in settings'); return;
      }
      LocationPermission perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) perm = await Geolocator.requestPermission();
      if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) {
        if (mounted) _showSnack('Location permission required'); return;
      }
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium),
      ).timeout(const Duration(seconds: 30), onTimeout: () {
        throw Exception('GPS timeout');
      });
      await ApiClient.instance.post(
        '/orders/${widget.orderId}/chat/location',
        data: {'lat': pos.latitude, 'lng': pos.longitude},
      );
      if (mounted) _showSnack('📍 Location shared with driver');
    } catch (e) {
      if (mounted) _showSnack(e.toString().contains('GPS') ? 'GPS timeout — try again outdoors' : 'Could not share location');
    } finally {
      if (mounted) setState(() => _sharingLocation = false);
    }
  }

  void _showSnack(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(msg), duration: const Duration(seconds: 2)));
  }

  @override
  void dispose() {
    _recordTimer?.cancel();
    _recorder.dispose();
    RealtimeClient.instance.removeListener(_channel, 'new_message', _onRealtime);
    _ctrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.7,
      maxChildSize: 0.95,
      minChildSize: 0.4,
      builder: (_, sc) => Container(
        decoration: BoxDecoration(
          color: context.colors.scaffoldBg,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        ),
        child: Column(children: [
          // Handle
          Container(margin: const EdgeInsets.only(top: 10), width: 36, height: 4,
            decoration: BoxDecoration(color: context.colors.borderColor,
                borderRadius: BorderRadius.circular(2))),
          // Header
          Padding(padding: const EdgeInsets.fromLTRB(16, 12, 16, 0), child: Row(children: [
            Container(width: 36, height: 36,
              decoration: const BoxDecoration(color: Color(0xFF07003B), shape: BoxShape.circle),
              child: Center(child: Text(widget.driver.name[0].toUpperCase(),
                style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16)))),
            const SizedBox(width: 10),
            Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(widget.driver.name, style: TextStyle(
                  fontWeight: FontWeight.w800, color: context.colors.navyText)),
              const Text('Driver', style: TextStyle(color: AppColors.textGrey, fontSize: 11)),
            ]),
            const Spacer(),
            if (widget.driver.phone != null)
              IconButton(
                icon: const Icon(Icons.phone_rounded, color: Color(0xFF22C55E)),
                onPressed: () async {
                  final uri = Uri.parse('tel:${widget.driver.phone}');
                  // ignore: deprecated_member_use
                  if (await canLaunchUrl(uri)) launchUrl(uri);
                },
              ),
          ])),
          const Divider(),
          // Messages
          Expanded(child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _msgs.isEmpty
            ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.chat_bubble_outline_rounded, size: 48,
                    color: context.colors.borderColor),
                const SizedBox(height: 8),
                Text('Send a message to your driver',
                    style: TextStyle(color: context.colors.borderColor)),
              ]))
            : ListView.builder(
                controller: _scrollCtrl,
                padding: const EdgeInsets.all(16),
                itemCount: _msgs.length,
                itemBuilder: (_, i) => _buildMessage(_msgs[i], context),
              )),
          // Voice pending preview
          if (_pendingVoicePath != null)
            _VoicePendingBar(
              path: _pendingVoicePath!,
              onSend: _sendVoice,
              onCancel: () => setState(() => _pendingVoicePath = null),
            ),
          // Input row
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
              child: _recording
                ? _RecordingBar(
                    seconds: _recordSeconds,
                    onStop: () => _stopRecording(),
                    onCancel: () => _stopRecording(cancel: true),
                  )
                : Row(children: [
                    // Location
                    GestureDetector(
                      onTap: _shareLocation,
                      child: Container(
                        width: 44, height: 44,
                        decoration: BoxDecoration(
                          color: const Color(0xFF22C55E).withOpacity(0.12),
                          shape: BoxShape.circle,
                          border: Border.all(color: const Color(0xFF22C55E).withOpacity(0.3)),
                        ),
                        child: _sharingLocation
                          ? const Padding(padding: EdgeInsets.all(12),
                              child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFF22C55E)))
                          : const Icon(Icons.location_on_rounded, color: Color(0xFF22C55E), size: 20),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(child: TextField(
                      controller: _ctrl,
                      decoration: InputDecoration(
                        hintText: 'Message driver...',
                        filled: true,
                        fillColor: context.colors.cardBg,
                        border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(24),
                            borderSide: BorderSide.none),
                        contentPadding: const EdgeInsets.symmetric(
                            horizontal: 16, vertical: 10),
                      ),
                    )),
                    const SizedBox(width: 8),
                    // Mic (hold to record) or Send
                    ValueListenableBuilder(
                      valueListenable: _ctrl,
                      builder: (_, v, __) => v.text.isEmpty
                        ? GestureDetector(
                            onLongPressStart: (_) => _startRecording(),
                            onLongPressEnd: (_) => _stopRecording(),
                            child: Container(width: 44, height: 44,
                              decoration: const BoxDecoration(
                                  color: _amber, shape: BoxShape.circle),
                              child: const Icon(Icons.mic_rounded,
                                  color: Colors.white, size: 22)),
                          )
                        : GestureDetector(
                            onTap: _send,
                            child: Container(width: 44, height: 44,
                              decoration: const BoxDecoration(
                                  color: Color(0xFF07003B), shape: BoxShape.circle),
                              child: const Icon(Icons.send_rounded,
                                  color: Colors.white, size: 20)),
                          ),
                    ),
                  ]),
            ),
          ),
        ]),
      ),
    );
  }

  Widget _buildMessage(_ChatMsg m, BuildContext context) {
    switch (m.type) {
      case _ChatMsgType.voice:
        return Align(
          alignment: m.isMe ? Alignment.centerRight : Alignment.centerLeft,
          child: _VoiceBubble(
            url: m.voiceUrl!,
            isMe: m.isMe,
            isLocal: !m.voiceUrl!.startsWith('http'),
          ),
        );
      case _ChatMsgType.location:
        return Align(
          alignment: m.isMe ? Alignment.centerRight : Alignment.centerLeft,
          child: _LocationBubble(lat: m.lat!, lng: m.lng!, isMe: m.isMe, mapsKey: _mapsKey),
        );
      case _ChatMsgType.locationRequest:
        return _LocationRequestBubble(
          isMe: m.isMe,
          onShare: m.isMe ? null : _shareLocation,
        );
      case _ChatMsgType.text:
        return Align(
          alignment: m.isMe ? Alignment.centerRight : Alignment.centerLeft,
          child: Container(
            margin: const EdgeInsets.only(bottom: 8),
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * 0.72),
            decoration: BoxDecoration(
              color: m.isMe ? const Color(0xFF07003B) : context.colors.cardBg,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Text(m.text, style: TextStyle(
                color: m.isMe ? Colors.white : context.colors.navyText,
                fontSize: 14)),
          ),
        );
    }
  }
}

// ── Message model ─────────────────────────────────────────────────────────────

enum _ChatMsgType { text, voice, location, locationRequest }

class _ChatMsg {
  final String text;
  final bool isMe;
  final _ChatMsgType type;
  final String? voiceUrl;
  final double? lat;
  final double? lng;

  const _ChatMsg({
    required this.text,
    required this.isMe,
    this.type = _ChatMsgType.text,
    this.voiceUrl,
    this.lat,
    this.lng,
  });

  factory _ChatMsg.fromMap(Map m, {required String myType}) {
    final senderType = (m['sender_type'] ?? '') as String;
    final rawType = (m['message_type'] ?? 'text') as String;
    _ChatMsgType type;
    switch (rawType) {
      case 'voice':           type = _ChatMsgType.voice; break;
      case 'location':        type = _ChatMsgType.location; break;
      case 'location_request':type = _ChatMsgType.locationRequest; break;
      default:                type = _ChatMsgType.text;
    }
    return _ChatMsg(
      text:     (m['message'] ?? '').toString(),
      isMe:     senderType == myType,
      type:     type,
      voiceUrl: m['voice_url'] as String?,
      lat:      m['lat'] != null ? double.tryParse('${m['lat']}') : null,
      lng:      m['lng'] != null ? double.tryParse('${m['lng']}') : null,
    );
  }
}

// ── Voice bubble (playback) ────────────────────────────────────────────────────

class _VoiceBubble extends StatefulWidget {
  final String url;
  final bool isMe;
  final bool isLocal; // local file path vs remote URL
  const _VoiceBubble({required this.url, required this.isMe, required this.isLocal});
  @override
  State<_VoiceBubble> createState() => _VoiceBubbleState();
}

class _VoiceBubbleState extends State<_VoiceBubble> {
  final _player = AudioPlayer();
  bool _playing = false;
  Duration _position = Duration.zero;
  Duration _duration = Duration.zero;

  @override
  void initState() {
    super.initState();
    _player.onPlayerStateChanged.listen((s) {
      if (mounted) setState(() => _playing = s == PlayerState.playing);
    });
    _player.onPositionChanged.listen((p) {
      if (mounted) setState(() => _position = p);
    });
    _player.onDurationChanged.listen((d) {
      if (mounted) setState(() => _duration = d);
    });
    _player.onPlayerComplete.listen((_) {
      if (mounted) setState(() { _playing = false; _position = Duration.zero; });
    });
  }

  String _fmt(Duration d) {
    final m = d.inMinutes.remainder(60).toString().padLeft(2, '0');
    final s = d.inSeconds.remainder(60).toString().padLeft(2, '0');
    return '$m:$s';
  }

  Future<void> _toggle() async {
    if (_playing) {
      await _player.pause();
    } else {
      if (widget.isLocal) {
        await _player.play(DeviceFileSource(widget.url));
      } else {
        await _player.play(UrlSource(widget.url));
      }
    }
  }

  @override
  void dispose() { _player.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final color = widget.isMe ? const Color(0xFF07003B) : context.colors.cardBg;
    final textColor = widget.isMe ? Colors.white : context.colors.navyText;
    final accent = widget.isMe ? Colors.white70 : const Color(0xFFFF8A00);
    final total = _duration.inMilliseconds > 0 ? _duration.inMilliseconds.toDouble() : 1.0;
    final pos = _position.inMilliseconds.toDouble().clamp(0.0, total);

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(16)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        GestureDetector(
          onTap: _toggle,
          child: Icon(_playing ? Icons.pause_circle_filled : Icons.play_circle_filled,
              color: accent, size: 36),
        ),
        const SizedBox(width: 8),
        SizedBox(
          width: 120,
          child: SliderTheme(
            data: SliderThemeData(
              trackHeight: 2,
              thumbShape: const RoundSliderThumbShape(enabledThumbRadius: 5),
              overlayShape: SliderComponentShape.noOverlay,
              activeTrackColor: accent,
              inactiveTrackColor: accent.withOpacity(0.25),
              thumbColor: accent,
            ),
            child: Slider(
              value: pos,
              min: 0,
              max: total,
              onChanged: (v) => _player.seek(Duration(milliseconds: v.toInt())),
            ),
          ),
        ),
        const SizedBox(width: 4),
        Text(_fmt(_playing ? _position : _duration),
            style: TextStyle(color: textColor, fontSize: 11)),
      ]),
    );
  }
}

// ── Voice pending preview bar ──────────────────────────────────────────────────

class _VoicePendingBar extends StatelessWidget {
  final String path;
  final VoidCallback onSend;
  final VoidCallback onCancel;
  const _VoicePendingBar({required this.path, required this.onSend, required this.onCancel});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      color: const Color(0xFFFF8A00).withOpacity(0.08),
      child: Row(children: [
        const Icon(Icons.mic_rounded, color: _amber, size: 20),
        const SizedBox(width: 8),
        Expanded(child: _VoiceBubble(url: path, isMe: true, isLocal: true)),
        const SizedBox(width: 8),
        GestureDetector(onTap: onCancel,
            child: const Icon(Icons.close, color: Colors.red, size: 22)),
        const SizedBox(width: 12),
        GestureDetector(
          onTap: onSend,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
            decoration: BoxDecoration(
                color: const Color(0xFF07003B), borderRadius: BorderRadius.circular(20)),
            child: const Text('Send', style: TextStyle(color: Colors.white,
                fontWeight: FontWeight.w700, fontSize: 13)),
          ),
        ),
      ]),
    );
  }
}

// ── Recording bar ─────────────────────────────────────────────────────────────

class _RecordingBar extends StatelessWidget {
  final int seconds;
  final VoidCallback onStop;
  final VoidCallback onCancel;
  const _RecordingBar({required this.seconds, required this.onStop, required this.onCancel});

  @override
  Widget build(BuildContext context) {
    final m = (seconds ~/ 60).toString().padLeft(2, '0');
    final s = (seconds % 60).toString().padLeft(2, '0');
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.red.withOpacity(0.08),
        borderRadius: BorderRadius.circular(30),
      ),
      child: Row(children: [
        GestureDetector(onTap: onCancel,
            child: const Icon(Icons.delete_outline_rounded, color: Colors.red, size: 22)),
        const SizedBox(width: 8),
        const Icon(Icons.fiber_manual_record, color: Colors.red, size: 14),
        const SizedBox(width: 6),
        Text('$m:$s', style: const TextStyle(
            color: Colors.red, fontWeight: FontWeight.w700, fontSize: 15)),
        const Spacer(),
        const Text('Release to send', style: TextStyle(color: Colors.grey, fontSize: 12)),
        const SizedBox(width: 8),
        GestureDetector(
          onTap: onStop,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
            decoration: BoxDecoration(
                color: Colors.red, borderRadius: BorderRadius.circular(20)),
            child: const Text('Stop & Preview',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
          ),
        ),
      ]),
    );
  }
}

// ── Location bubble ───────────────────────────────────────────────────────────

class _LocationBubble extends StatelessWidget {
  final double lat, lng;
  final bool isMe;
  final String mapsKey;
  const _LocationBubble({required this.lat, required this.lng,
      required this.isMe, required this.mapsKey});

  @override
  Widget build(BuildContext context) {
    final mapUrl = 'https://maps.googleapis.com/maps/api/staticmap'
        '?center=$lat,$lng&zoom=15&size=280x140'
        '&markers=color:red|$lat,$lng&key=$mapsKey';
    final color = isMe ? const Color(0xFF07003B) : context.colors.cardBg;

    return GestureDetector(
      onTap: () async {
        final uri = Uri.parse('https://maps.google.com/?q=$lat,$lng');
        // ignore: deprecated_member_use
        if (await canLaunchUrl(uri)) launchUrl(uri, mode: LaunchMode.externalApplication);
      },
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        decoration: BoxDecoration(color: color, borderRadius: BorderRadius.circular(16)),
        clipBehavior: Clip.hardEdge,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Image.network(mapUrl, width: 220, height: 120, fit: BoxFit.cover,
              errorBuilder: (_, __, ___) => Container(
                  width: 220, height: 120, color: Colors.grey.shade200,
                  child: const Icon(Icons.map_outlined, size: 40, color: Colors.grey))),
          Padding(
            padding: const EdgeInsets.fromLTRB(10, 6, 10, 8),
            child: Row(children: [
              const Icon(Icons.location_on_rounded, size: 14, color: Color(0xFF22C55E)),
              const SizedBox(width: 4),
              Text(isMe ? 'My location' : 'Customer location',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.w600,
                      color: isMe ? Colors.white70 : context.colors.navyText)),
              const SizedBox(width: 4),
              Text('• Tap to open', style: TextStyle(fontSize: 11,
                  color: isMe ? Colors.white38 : Colors.grey)),
            ]),
          ),
        ]),
      ),
    );
  }
}

// ── Location request bubble ────────────────────────────────────────────────────

class _LocationRequestBubble extends StatelessWidget {
  final bool isMe;
  final VoidCallback? onShare; // null = I sent the request
  const _LocationRequestBubble({required this.isMe, this.onShare});

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: isMe ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: const Color(0xFF22C55E).withOpacity(0.1),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFF22C55E).withOpacity(0.35)),
        ),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.location_searching_rounded, color: Color(0xFF22C55E), size: 18),
          const SizedBox(width: 8),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(isMe ? '📍 Location requested' : '📍 Driver needs your location',
                style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13,
                    color: Color(0xFF22C55E))),
            if (!isMe && onShare != null)
              GestureDetector(
                onTap: onShare,
                child: Container(
                  margin: const EdgeInsets.only(top: 6),
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
                  decoration: BoxDecoration(
                    color: const Color(0xFF22C55E),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Text('Share Location',
                      style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700,
                          fontSize: 12)),
                ),
              ),
          ]),
        ]),
      ),
    );
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
