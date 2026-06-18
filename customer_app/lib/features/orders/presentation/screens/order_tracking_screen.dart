import 'dart:async';
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/utils/error_handler.dart';
import '../providers/order_provider.dart';

class OrderTrackingScreen extends ConsumerStatefulWidget {
  final int orderId;
  const OrderTrackingScreen({super.key, required this.orderId});

  @override
  ConsumerState<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends ConsumerState<OrderTrackingScreen> {
  GoogleMapController? _mapController;
  Timer? _refreshTimer;
  Set<Marker> _markers = {};

  static const LatLng _defaultCenter = LatLng(
    AppConstants.defaultLat,
    AppConstants.defaultLng,
  );

  @override
  void initState() {
    super.initState();
    // Auto-refresh order every 30 seconds
    _refreshTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      ref.invalidate(orderDetailProvider(widget.orderId));
    });
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _mapController?.dispose();
    super.dispose();
  }

  void _setupMarkers(dynamic order) {
    final markers = <Marker>{};

    // Vendor marker
    final vendorLat = _toDouble(order.vendorLatitude ?? order.vendor?['latitude']);
    final vendorLng = _toDouble(order.vendorLongitude ?? order.vendor?['longitude']);
    if (vendorLat != null && vendorLng != null) {
      markers.add(Marker(
        markerId: const MarkerId('vendor'),
        position: LatLng(vendorLat, vendorLng),
        infoWindow: InfoWindow(title: order.vendorName ?? 'Restaurant'),
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueOrange),
      ));
    }

    // Delivery address marker
    final delivLat = _toDouble(order.deliveryLatitude ?? order.deliveryAddress?['latitude']);
    final delivLng = _toDouble(order.deliveryLongitude ?? order.deliveryAddress?['longitude']);
    if (delivLat != null && delivLng != null) {
      markers.add(Marker(
        markerId: const MarkerId('delivery'),
        position: LatLng(delivLat, delivLng),
        infoWindow: const InfoWindow(title: 'Delivery Address'),
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueViolet),
      ));

      // Move camera to delivery location
      _mapController?.animateCamera(
        CameraUpdate.newLatLngZoom(LatLng(delivLat, delivLng), 14),
      );
    }

    // Deliveryman marker (if dispatched)
    final driverLat = _toDouble(order.driverLatitude);
    final driverLng = _toDouble(order.driverLongitude);
    if (driverLat != null && driverLng != null) {
      markers.add(Marker(
        markerId: const MarkerId('driver'),
        position: LatLng(driverLat, driverLng),
        infoWindow: const InfoWindow(title: 'Delivery Driver'),
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueGreen),
      ));
    }

    setState(() => _markers = markers);
  }

  double? _toDouble(dynamic v) {
    if (v == null) return null;
    if (v is double) return v;
    if (v is int) return v.toDouble();
    if (v is String) return double.tryParse(v);
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final orderAsync = ref.watch(orderDetailProvider(widget.orderId));

    return Scaffold(
            appBar: AppBar(
        title: const Text('Track Order',
            style: TextStyle(fontWeight: FontWeight.w800)),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textDark,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(orderDetailProvider(widget.orderId)),
          ),
        ],
      ),
      body: orderAsync.when(
        loading: () => const Center(
          child: CircularProgressIndicator(color: AppColors.primary),
        ),
        error: (e, _) => Center(child: Text(AppErrorHandler.message(e))),
        data: (order) {
          // Setup markers after first data load
          WidgetsBinding.instance.addPostFrameCallback((_) => _setupMarkers(order));

          return Column(
            children: [
              // ── Map ──────────────────────────────────────────────────────
              Expanded(
                flex: 3,
                child: Stack(
                  children: [
                    // Google Maps — mobile only (not supported on web)
                    if (kIsWeb)
                      _WebMapPlaceholder(orderNumber: order.orderNumber)
                    else
                      GoogleMap(
                        initialCameraPosition: const CameraPosition(
                          target: _defaultCenter,
                          zoom: 13,
                        ),
                        onMapCreated: (c) {
                          _mapController = c;
                          _setupMarkers(order);
                        },
                        markers: _markers,
                        myLocationEnabled: true,
                        myLocationButtonEnabled: false,
                        zoomControlsEnabled: false,
                        mapToolbarEnabled: false,
                        compassEnabled: true,
                      ),

                    // Order number badge
                    Positioned(
                      top: 16, left: 16,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(color: context.colors.cardBg,
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withOpacity(0.12),
                              blurRadius: 8,
                            ),
                          ],
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 8, height: 8,
                              decoration: BoxDecoration(
                                color: order.statusColor,
                                shape: BoxShape.circle,
                              ),
                            ),
                            const SizedBox(width: 6),
                            Text(
                              order.orderNumber,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 13,
                                color: AppColors.textDark,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),

                    // Legend
                    Positioned(
                      top: 16, right: 16,
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(color: context.colors.cardBg,
                          borderRadius: BorderRadius.circular(12),
                          boxShadow: [BoxShadow(color: Colors.black.withOpacity(0.08), blurRadius: 6)],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            _LegendItem(color: Colors.orange, label: 'Restaurant'),
                            const SizedBox(height: 4),
                            _LegendItem(color: Colors.purple, label: 'Delivery'),
                            if (_markers.any((m) => m.markerId.value == 'driver')) ...[
                              const SizedBox(height: 4),
                              _LegendItem(color: Colors.green, label: 'Driver'),
                            ],
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              // ── Status panel ─────────────────────────────────────────────
              Expanded(
                flex: 2,
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(color: context.colors.cardBg,
                    borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Container(
                            width: 10, height: 10,
                            decoration: BoxDecoration(
                              color: order.statusColor,
                              shape: BoxShape.circle,
                            ),
                          ),
                          const SizedBox(width: 8),
                          Text(
                            order.statusLabel,
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
                              color: order.statusColor,
                            ),
                          ),
                          const Spacer(),
                          // Auto-refresh indicator
                          Row(
                            children: [
                              const Icon(Icons.sync_rounded, size: 12, color: AppColors.textGrey),
                              const SizedBox(width: 4),
                              Text('Auto-refresh',
                                  style: TextStyle(fontSize: 11, color: AppColors.textGrey)),
                            ],
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        _statusMessage(order.status),
                        style: const TextStyle(color: AppColors.textGrey, fontSize: 14),
                      ),
                      const SizedBox(height: 16),

                      // Steps
                      Expanded(
                        child: SingleChildScrollView(
                          child: Column(
                            children: [
                              _TrackStep(
                                icon: Icons.check_circle_rounded,
                                label: 'Order Placed',
                                done: true,
                              ),
                              _TrackStep(
                                icon: Icons.restaurant_rounded,
                                label: 'Confirmed',
                                done: _isDone(order.status, 'confirmed'),
                              ),
                              _TrackStep(
                                icon: Icons.lunch_dining_rounded,
                                label: 'Preparing',
                                done: _isDone(order.status, 'preparing'),
                              ),
                              _TrackStep(
                                icon: Icons.delivery_dining_rounded,
                                label: 'On the way',
                                done: _isDone(order.status, 'picked_up'),
                              ),
                              _TrackStep(
                                icon: Icons.home_rounded,
                                label: 'Delivered',
                                done: order.status == 'delivered',
                                last: true,
                              ),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  String _statusMessage(String status) {
    switch (status) {
      case 'confirmed':  return 'Your order has been confirmed by the vendor.';
      case 'preparing':  return 'The vendor is preparing your order right now.';
      case 'ready':      return 'Your order is ready and waiting for pickup.';
      case 'picked_up':  return 'A driver has picked up your order. On the way!';
      case 'delivered':  return 'Your order has been delivered. Enjoy! 🎉';
      default:           return 'Your order is being processed.';
    }
  }

  bool _isDone(String current, String step) {
    const order = ['pending','confirmed','preparing','ready','picked_up','delivered'];
    final ci = order.indexOf(current);
    final si = order.indexOf(step);
    return ci >= si;
  }
}

class _LegendItem extends StatelessWidget {
  final Color color;
  final String label;
  const _LegendItem({required this.color, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Container(width: 10, height: 10, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
        const SizedBox(width: 5),
        Text(label, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w500, color: AppColors.textDark)),
      ],
    );
  }
}

class _TrackStep extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool done;
  final bool last;
  const _TrackStep({required this.icon, required this.label, required this.done, this.last = false});

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Column(
          children: [
            Icon(icon, color: done ? AppColors.primary : AppColors.divider, size: 20),
            if (!last)
              Container(
                width: 2, height: 18,
                color: done ? AppColors.primary.withOpacity(0.3) : AppColors.divider,
              ),
          ],
        ),
        const SizedBox(width: 10),
        Padding(
          padding: const EdgeInsets.only(top: 2),
          child: Text(label, style: TextStyle(
            fontWeight: done ? FontWeight.w700 : FontWeight.w400,
            color: done ? AppColors.textDark : AppColors.textLight,
            fontSize: 13,
          )),
        ),
      ],
    );
  }
}

// ── Web placeholder (Google Maps not supported on web) ────────────────────
class _WebMapPlaceholder extends StatelessWidget {
  final String orderNumber;
  const _WebMapPlaceholder({required this.orderNumber});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      height: double.infinity,
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF1a0a6e), Color(0xFF140465)],
        ),
      ),
      child: Stack(
        children: [
          CustomPaint(painter: _GridPainter(), size: Size.infinite),
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 72, height: 72,
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.12),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.delivery_dining_rounded, color: Colors.white, size: 38),
                ),
                const SizedBox(height: 16),
                const Text('Live Tracking',
                    style: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800)),
                const SizedBox(height: 6),
                Text('Available on the mobile app',
                    style: TextStyle(color: Colors.white.withOpacity(0.6), fontSize: 13)),
                const SizedBox(height: 20),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  decoration: BoxDecoration(
                    color: Colors.white.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: Colors.white.withOpacity(0.3)),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 8, height: 8,
                        decoration: const BoxDecoration(color: Color(0xFF4ADE80), shape: BoxShape.circle),
                      ),
                      const SizedBox(width: 8),
                      Text(orderNumber,
                          style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _GridPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = Colors.white.withOpacity(0.05)
      ..strokeWidth = 1;
    const spacing = 40.0;
    for (double x = 0; x < size.width; x += spacing) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), paint);
    }
    for (double y = 0; y < size.height; y += spacing) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), paint);
    }
  }
  @override
  bool shouldRepaint(_) => false;
}
