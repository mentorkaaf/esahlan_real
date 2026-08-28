import 'dart:async';
import 'dart:convert';
import 'dart:math' as math;
import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:flutter_polyline_points/flutter_polyline_points.dart';
import 'package:http/http.dart' as http;
import '../../../../core/constants/app_constants.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/utils/error_handler.dart';
import '../../../../core/services/realtime_client.dart';
import '../providers/order_provider.dart';
import '../../../../core/l10n/app_strings.dart';

const _kMapsKey = AppConstants.googleMapsApiKey;

class OrderTrackingScreen extends ConsumerStatefulWidget {
  final int orderId;
  const OrderTrackingScreen({super.key, required this.orderId});

  @override
  ConsumerState<OrderTrackingScreen> createState() => _OrderTrackingScreenState();
}

class _OrderTrackingScreenState extends ConsumerState<OrderTrackingScreen> {
  GoogleMapController? _mapController;
  Timer? _refreshTimer;
  Timer? _etaTimer;

  Set<Marker>   _markers   = {};
  Set<Polyline> _polylines = {};

  LatLng? _driverPos;
  LatLng? _pickupPos;
  LatLng? _deliveryPos;
  bool _routeLoaded = false;
  String _eta       = '';
  String _distKm    = '';

  static const LatLng _defaultCenter = LatLng(
    AppConstants.defaultLat,
    AppConstants.defaultLng,
  );

  // ── WebSocket — real-time driver position ──────────────────────────────────
  void _onDriverLocation(dynamic payload) {
    if (!mounted) return;
    final data = payload is Map ? payload : <String, dynamic>{};
    final lat  = double.tryParse('${data['lat'] ?? ''}');
    final lng  = double.tryParse('${data['lng'] ?? ''}');
    if (lat == null || lng == null) return;
    final pos = LatLng(lat, lng);
    setState(() => _driverPos = pos);
    _rebuildMarkers();
    _rebuildPolylines();
    _loadEta();              // recalculate ETA with new driver position
    _mapController?.animateCamera(CameraUpdate.newLatLng(pos));
  }

  @override
  void initState() {
    super.initState();
    final channel = 'private-order-chat.${widget.orderId}';
    RealtimeClient.instance.listen(channel, 'driver_location', _onDriverLocation);
    _refreshTimer = Timer.periodic(const Duration(seconds: 60), (_) {
      ref.invalidate(orderDetailProvider(widget.orderId));
    });
  }

  @override
  void dispose() {
    final channel = 'private-order-chat.${widget.orderId}';
    RealtimeClient.instance.removeListener(channel, 'driver_location', _onDriverLocation);
    _refreshTimer?.cancel();
    _etaTimer?.cancel();
    _mapController?.dispose();
    super.dispose();
  }

  // ── Seed positions from order data ─────────────────────────────────────────
  void _seedFromOrder(dynamic order) {
    // Pickup (vendor)
    if (order.pickupLat != null && order.pickupLng != null) {
      _pickupPos = LatLng(order.pickupLat!, order.pickupLng!);
    }
    // Delivery (customer address)
    if (order.deliveryLat != null && order.deliveryLng != null) {
      _deliveryPos = LatLng(order.deliveryLat!, order.deliveryLng!);
    }
    // Driver initial position (if not already set by WebSocket)
    if (_driverPos == null && order.driver?.lat != null && order.driver?.lng != null) {
      _driverPos = LatLng(order.driver!.lat!, order.driver!.lng!);
    }

    _rebuildMarkers();
    _rebuildPolylines();

    if (!_routeLoaded && _pickupPos != null && _deliveryPos != null) {
      _loadRoute();
    }
    _fitAllMarkers();
  }

  // ── Directions API — pickup → delivery road route ─────────────────────────
  Future<void> _loadRoute() async {
    if (_pickupPos == null || _deliveryPos == null) return;
    try {
      final result = await PolylinePoints().getRouteBetweenCoordinates(
        googleApiKey: _kMapsKey,
        request: PolylineRequest(
          origin:      PointLatLng(_pickupPos!.latitude, _pickupPos!.longitude),
          destination: PointLatLng(_deliveryPos!.latitude, _deliveryPos!.longitude),
          mode:        TravelMode.driving,
        ),
      );
      if (result.points.isNotEmpty && mounted) {
        final pts = result.points.map((p) => LatLng(p.latitude, p.longitude)).toList();
        setState(() {
          _routeLoaded = true;
          _polylines = _buildPolylines(routePoints: pts);
        });
      }
    } catch (e) {
      debugPrint('[Tracking] route error: $e');
    }
    _loadEta();
  }

  // ── Distance Matrix API — ETA from driver → delivery ─────────────────────
  Future<void> _loadEta() async {
    final from = _driverPos ?? _pickupPos;
    if (from == null || _deliveryPos == null) return;
    _etaTimer?.cancel();
    try {
      final url = Uri.parse(
        'https://maps.googleapis.com/maps/api/distancematrix/json'
        '?origins=${from.latitude},${from.longitude}'
        '&destinations=${_deliveryPos!.latitude},${_deliveryPos!.longitude}'
        '&mode=driving&key=$_kMapsKey',
      );
      final res  = await http.get(url).timeout(const Duration(seconds: 8));
      final body = jsonDecode(res.body) as Map<String, dynamic>;
      final elem = ((body['rows'] as List?)?.first?['elements'] as List?)?.first as Map?;
      if (elem != null && elem['status'] == 'OK' && mounted) {
        setState(() {
          _eta    = elem['duration']?['text']  as String? ?? '';
          _distKm = elem['distance']?['text']  as String? ?? '';
        });
      }
    } catch (_) {}
    _etaTimer = Timer(const Duration(minutes: 2), _loadEta);
  }

  // ── Markers ────────────────────────────────────────────────────────────────
  void _rebuildMarkers() {
    if (!mounted) return;
    final m = <Marker>{};
    if (_pickupPos != null) {
      m.add(Marker(
        markerId: const MarkerId('pickup'),
        position: _pickupPos!,
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueOrange),
        infoWindow: const InfoWindow(title: 'Store / Pickup'),
      ));
    }
    if (_deliveryPos != null) {
      m.add(Marker(
        markerId: const MarkerId('delivery'),
        position: _deliveryPos!,
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueViolet),
        infoWindow: const InfoWindow(title: 'Delivery Address'),
      ));
    }
    if (_driverPos != null) {
      m.add(Marker(
        markerId: const MarkerId('driver'),
        position: _driverPos!,
        icon: BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueAzure),
        infoWindow: const InfoWindow(title: '🚴 Driver (Live)'),
        zIndex: 3,
      ));
    }
    setState(() => _markers = m);
  }

  // ── Polylines ──────────────────────────────────────────────────────────────
  Set<Polyline> _buildPolylines({List<LatLng>? routePoints}) {
    final lines = <Polyline>{};
    // Road route: pickup → delivery (orange solid)
    if (routePoints != null && routePoints.isNotEmpty) {
      lines.add(Polyline(
        polylineId: const PolylineId('route'),
        points:    routePoints,
        color:     AppColors.primary,
        width:     5,
        startCap:  Cap.roundCap,
        endCap:    Cap.roundCap,
        jointType: JointType.round,
      ));
    } else if (_pickupPos != null && _deliveryPos != null) {
      // Fallback straight line while route loads
      lines.add(Polyline(
        polylineId: const PolylineId('straight'),
        points:  [_pickupPos!, _deliveryPos!],
        color:   AppColors.primary.withValues(alpha: 0.4),
        width:   3,
        patterns: [PatternItem.dash(15), PatternItem.gap(10)],
      ));
    }
    // Driver → pickup dashed blue line
    if (_driverPos != null && _pickupPos != null) {
      lines.add(Polyline(
        polylineId: const PolylineId('driver_to_pickup'),
        points:   [_driverPos!, _pickupPos!],
        color:    Colors.blue,
        width:    3,
        patterns: [PatternItem.dash(12), PatternItem.gap(8)],
      ));
    }
    return lines;
  }

  void _rebuildPolylines() {
    if (!mounted) return;
    // Preserve existing route points if already loaded
    final existingRoute = _polylines
        .where((p) => p.polylineId.value == 'route')
        .firstOrNull;
    setState(() => _polylines = _buildPolylines(
      routePoints: existingRoute?.points,
    ));
  }

  // ── Camera — fit all visible markers ──────────────────────────────────────
  void _fitAllMarkers() {
    final ctrl = _mapController;
    if (ctrl == null) return;
    final pts = <LatLng>[
      if (_driverPos   != null) _driverPos!,
      if (_pickupPos   != null) _pickupPos!,
      if (_deliveryPos != null) _deliveryPos!,
    ];
    if (pts.isEmpty) return;
    if (pts.length == 1) {
      ctrl.animateCamera(CameraUpdate.newLatLngZoom(pts.first, 15));
      return;
    }
    var minLat = pts.first.latitude,  maxLat = pts.first.latitude;
    var minLng = pts.first.longitude, maxLng = pts.first.longitude;
    for (final p in pts) {
      minLat = math.min(minLat, p.latitude);
      maxLat = math.max(maxLat, p.latitude);
      minLng = math.min(minLng, p.longitude);
      maxLng = math.max(maxLng, p.longitude);
    }
    ctrl.animateCamera(CameraUpdate.newLatLngBounds(
      LatLngBounds(
        southwest: LatLng(minLat, minLng),
        northeast: LatLng(maxLat, maxLng),
      ),
      80,
    ));
  }

  @override
  Widget build(BuildContext context) {
    final l = AppL10n.of(context);
    final orderAsync = ref.watch(orderDetailProvider(widget.orderId));

    return Scaffold(
      appBar: AppBar(
        title: Text(l.orderTracking,
            style: const TextStyle(fontWeight: FontWeight.w800)),
        foregroundColor: AppColors.textDark,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
          onPressed: () => context.pop(),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.my_location_rounded),
            onPressed: _fitAllMarkers,
            tooltip: 'Fit map',
          ),
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            onPressed: () => ref.invalidate(orderDetailProvider(widget.orderId)),
          ),
        ],
      ),
      body: orderAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: AppColors.primary)),
        error:   (e, _) => Center(child: Text(AppErrorHandler.message(e))),
        data: (order) {
          WidgetsBinding.instance.addPostFrameCallback((_) => _seedFromOrder(order));

          return Column(
            children: [
              // ── Map ──────────────────────────────────────────────────────
              Expanded(
                flex: 3,
                child: Stack(
                  children: [
                    if (kIsWeb)
                      _WebMapPlaceholder(orderNumber: order.orderNumber)
                    else
                      GoogleMap(
                        initialCameraPosition: const CameraPosition(target: _defaultCenter, zoom: 13),
                        onMapCreated: (c) {
                          _mapController = c;
                          Future.delayed(const Duration(milliseconds: 300), () => _seedFromOrder(order));
                        },
                        markers:   _markers,
                        polylines: _polylines,
                        myLocationEnabled:     true,
                        myLocationButtonEnabled: false,
                        zoomControlsEnabled:   false,
                        mapToolbarEnabled:     false,
                        compassEnabled:        true,
                      ),

                    // ── ETA chip ────────────────────────────────────────────
                    if (_eta.isNotEmpty)
                      Positioned(
                        top: 12, left: 12,
                        child: _EtaChip(eta: _eta, dist: _distKm),
                      ),

                    // ── Order number badge ──────────────────────────────────
                    Positioned(
                      top: 12, right: 12,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: context.colors.cardBg,
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.12), blurRadius: 8)],
                        ),
                        child: Row(mainAxisSize: MainAxisSize.min, children: [
                          Container(
                            width: 8, height: 8,
                            decoration: BoxDecoration(color: order.statusColor, shape: BoxShape.circle),
                          ),
                          const SizedBox(width: 6),
                          Text(order.orderNumber,
                            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13, color: AppColors.textDark)),
                        ]),
                      ),
                    ),

                    // ── Legend ──────────────────────────────────────────────
                    Positioned(
                      bottom: 12, left: 12,
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: context.colors.cardBg.withValues(alpha: 0.9),
                          borderRadius: BorderRadius.circular(12),
                          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.08), blurRadius: 6)],
                        ),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          _LegendItem(color: Colors.orange,          label: l.restaurant),
                          const SizedBox(height: 4),
                          _LegendItem(color: Colors.purple,          label: l.delivery),
                          if (_driverPos != null) ...[
                            const SizedBox(height: 4),
                            _LegendItem(color: Colors.blue,          label: l.driver),
                          ],
                        ]),
                      ),
                    ),

                    // ── Route loading indicator ──────────────────────────────
                    if (!_routeLoaded && _pickupPos != null)
                      Positioned(
                        bottom: 12, right: 12,
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(color: Colors.black54, borderRadius: BorderRadius.circular(20)),
                          child: const Row(mainAxisSize: MainAxisSize.min, children: [
                            SizedBox(width: 12, height: 12,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)),
                            SizedBox(width: 6),
                            Text('Loading route…', style: TextStyle(color: Colors.white, fontSize: 11)),
                          ]),
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
                  decoration: BoxDecoration(
                    color: context.colors.cardBg,
                    borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
                  ),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Container(
                        width: 10, height: 10,
                        decoration: BoxDecoration(color: order.statusColor, shape: BoxShape.circle),
                      ),
                      const SizedBox(width: 8),
                      Text(order.statusLabel, style: TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 16, color: order.statusColor)),
                      const Spacer(),
                      Row(children: [
                        const Icon(Icons.sync_rounded, size: 12, color: AppColors.textGrey),
                        const SizedBox(width: 4),
                        Text(l.autoRefresh, style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
                      ]),
                    ]),
                    const SizedBox(height: 6),
                    Text(_statusMessage(order.status),
                        style: const TextStyle(color: AppColors.textGrey, fontSize: 14)),
                    const SizedBox(height: 16),
                    Expanded(
                      child: SingleChildScrollView(
                        child: Column(children: [
                          _TrackStep(icon: Icons.check_circle_rounded, label: l.orderPlacedStep, done: true),
                          _TrackStep(icon: Icons.restaurant_rounded,   label: l.confirmed,        done: _isDone(order.status, 'confirmed')),
                          _TrackStep(icon: Icons.lunch_dining_rounded,  label: l.preparing,        done: _isDone(order.status, 'preparing')),
                          _TrackStep(icon: Icons.delivery_dining_rounded, label: l.onTheWay,       done: _isDone(order.status, 'picked_up')),
                          _TrackStep(icon: Icons.home_rounded,          label: l.tabDelivered,     done: order.status == 'delivered', last: true),
                        ]),
                      ),
                    ),
                  ]),
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
    const order = ['pending', 'confirmed', 'preparing', 'ready', 'picked_up', 'delivered'];
    return order.indexOf(current) >= order.indexOf(step);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ETA chip
// ─────────────────────────────────────────────────────────────────────────────
class _EtaChip extends StatelessWidget {
  final String eta;
  final String dist;
  const _EtaChip({required this.eta, required this.dist});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFF1E293B).withValues(alpha: 0.92),
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black26, blurRadius: 8, offset: const Offset(0, 2))],
      ),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.schedule_rounded, color: Colors.white, size: 14),
        const SizedBox(width: 6),
        Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          Text(eta, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
          if (dist.isNotEmpty)
            Text(dist, style: const TextStyle(color: Colors.white70, fontSize: 10)),
        ]),
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Legend item
// ─────────────────────────────────────────────────────────────────────────────
class _LegendItem extends StatelessWidget {
  final Color color;
  final String label;
  const _LegendItem({required this.color, required this.label});

  @override
  Widget build(BuildContext context) {
    return Row(mainAxisSize: MainAxisSize.min, children: [
      Container(width: 10, height: 10, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
      const SizedBox(width: 5),
      Text(label, style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w500, color: AppColors.textDark)),
    ]);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Track step
// ─────────────────────────────────────────────────────────────────────────────
class _TrackStep extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool done;
  final bool last;
  const _TrackStep({required this.icon, required this.label, required this.done, this.last = false});

  @override
  Widget build(BuildContext context) {
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Column(children: [
        Icon(icon, color: done ? AppColors.primary : AppColors.divider, size: 20),
        if (!last)
          Container(width: 2, height: 18, color: done ? AppColors.primary.withValues(alpha: 0.3) : AppColors.divider),
      ]),
      const SizedBox(width: 10),
      Padding(
        padding: const EdgeInsets.only(top: 2),
        child: Text(label, style: TextStyle(
          fontWeight: done ? FontWeight.w700 : FontWeight.w400,
          color:      done ? AppColors.textDark : AppColors.textLight,
          fontSize:   13,
        )),
      ),
    ]);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Web placeholder
// ─────────────────────────────────────────────────────────────────────────────
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
          begin: Alignment.topLeft, end: Alignment.bottomRight,
          colors: [Color(0xFF1a0a6e), Color(0xFF140465)],
        ),
      ),
      child: Center(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Container(
            width: 72, height: 72,
            decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.12), shape: BoxShape.circle),
            child: const Icon(Icons.delivery_dining_rounded, color: Colors.white, size: 38),
          ),
          const SizedBox(height: 16),
          Text(AppL10n.current.liveTracking,
              style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w800)),
          const SizedBox(height: 6),
          Text(AppL10n.current.mobileOnly,
              style: TextStyle(color: Colors.white.withValues(alpha: 0.6), fontSize: 13)),
          const SizedBox(height: 20),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withValues(alpha: 0.3)),
            ),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Container(width: 8, height: 8,
                decoration: const BoxDecoration(color: Color(0xFF4ADE80), shape: BoxShape.circle)),
              const SizedBox(width: 8),
              Text(orderNumber, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14)),
            ]),
          ),
        ]),
      ),
    );
  }
}
