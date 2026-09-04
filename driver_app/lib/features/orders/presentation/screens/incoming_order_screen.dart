import 'dart:async';
import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../../core/theme/driver_colors.dart';
import '../../../../core/services/firebase_service.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Provider — kept for compatibility
// ─────────────────────────────────────────────────────────────────────────────
class _IncomingOrderState {
  final Map<String, dynamic>? order;
  const _IncomingOrderState({this.order});
}

class IncomingOrderNotifier extends StateNotifier<_IncomingOrderState> {
  IncomingOrderNotifier() : super(const _IncomingOrderState());
  void setOrder(Map<String, dynamic> order) =>
      state = _IncomingOrderState(order: order);
  void clear() => state = const _IncomingOrderState();
}

final incomingOrderProvider =
    StateNotifierProvider<IncomingOrderNotifier, _IncomingOrderState>(
        (ref) => IncomingOrderNotifier());

// ─────────────────────────────────────────────────────────────────────────────
// Module config
// ─────────────────────────────────────────────────────────────────────────────
const _kModuleLabels = {
  'efood':    'eFood Delivery',
  'egrocery': 'eGrocery Delivery',
  'eshop':    'eShop Delivery',
  'eparcel':  'eParcel Delivery',
  'emoving':  'eMoving Service',
  'elaundry': 'eLaundry Pickup',
  'erent':    'eRent Service',
};
const _kModuleColors = {
  'efood':    Color(0xFFEF4444),
  'egrocery': Color(0xFF22C55E),
  'eshop':    Color(0xFF8B5CF6),
  'eparcel':  Color(0xFFFF8A00),
  'emoving':  Color(0xFF3B82F6),
  'elaundry': Color(0xFF06B6D4),
  'erent':    Color(0xFFF59E0B),
};
const _kModuleIcons = {
  'efood':    Icons.restaurant_rounded,
  'egrocery': Icons.local_grocery_store_rounded,
  'eshop':    Icons.shopping_bag_rounded,
  'eparcel':  Icons.local_shipping_rounded,
  'emoving':  Icons.move_to_inbox_rounded,
  'elaundry': Icons.local_laundry_service_rounded,
  'erent':    Icons.home_rounded,
};

// ─────────────────────────────────────────────────────────────────────────────
// IncomingOrderScreen — DoorDash-style full-screen ring alarm
// ─────────────────────────────────────────────────────────────────────────────
class IncomingOrderScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> order;
  const IncomingOrderScreen({super.key, required this.order});

  @override
  ConsumerState<IncomingOrderScreen> createState() =>
      _IncomingOrderScreenState();
}

class _IncomingOrderScreenState extends ConsumerState<IncomingOrderScreen>
    with TickerProviderStateMixin {

  // ── countdown ─────────────────────────────────────────────────────────────
  static const _kTimeout = 45;
  int    _secondsLeft = _kTimeout;
  Timer? _timer;

  // ── button state ──────────────────────────────────────────────────────────
  bool _accepting = false;
  bool _declining = false;

  // ── audio ─────────────────────────────────────────────────────────────────
  final _player = AudioPlayer();

  // ── map ───────────────────────────────────────────────────────────────────
  GoogleMapController? _mapCtrl;

  // ── rich details (loaded async from API) ──────────────────────────────────
  Map<String, dynamic>? _richOrder;
  bool _richLoading = true;

  // ── sheet animation ───────────────────────────────────────────────────────
  late final AnimationController _sheetCtrl;
  late final Animation<double>   _sheetAnim;

  @override
  void initState() {
    super.initState();

    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);

    _sheetCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 520));
    _sheetAnim = CurvedAnimation(parent: _sheetCtrl, curve: Curves.easeOutCubic);
    _sheetCtrl.forward();

    _startAudio();
    _startCountdown();
    _loadRichDetails();
  }

  // ── audio ─────────────────────────────────────────────────────────────────
  Future<void> _startAudio() async {
    try {
      await _player.setReleaseMode(ReleaseMode.loop);
      await _player.setVolume(1.0);
      await _player.play(AssetSource('sounds/order_ring.wav'));
    } catch (e) {
      debugPrint('[Ring] audio error: $e');
    }
  }

  // ── countdown ─────────────────────────────────────────────────────────────
  void _startCountdown() {
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() => _secondsLeft--);
      if (_secondsLeft <= 0 && mounted) _decline(auto: true);
    });
  }

  // ── load rich order details from API ──────────────────────────────────────
  Future<void> _loadRichDetails() async {
    try {
      final id = _orderId;
      if (id <= 0) { if (mounted) setState(() => _richLoading = false); return; }
      final data = await ref.read(authRepoProvider).ringOrderDetails(id);
      if (!mounted) return;
      setState(() {
        _richOrder   = data;
        _richLoading = false;
      });
      _fitMapBounds();
    } catch (e) {
      debugPrint('[Ring] details error: $e');
      if (mounted) setState(() => _richLoading = false);
    }
  }

  // ── stop everything ───────────────────────────────────────────────────────
  Future<void> _stopAll() async {
    _timer?.cancel();
    try { await _player.stop(); } catch (_) {}
    FirebaseService().cancelOrderNotification();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  }

  // ── accept ────────────────────────────────────────────────────────────────
  Future<void> _accept() async {
    if (_accepting || _declining) return;
    HapticFeedback.heavyImpact();
    setState(() => _accepting = true);
    await _stopAll();
    try {
      await ref.read(authRepoProvider).acceptOrder(_orderId);
      if (mounted) {
        ref.read(incomingOrderProvider.notifier).clear();
        context.go('/orders');
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: const Text('Order accepted — go pick it up! 🚀'),
          backgroundColor: DC.success,
          behavior:         SnackBarBehavior.floating,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ));
      }
    } catch (e) {
      if (mounted) {
        setState(() => _accepting = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content:         Text('$e'),
          backgroundColor: DC.error,
          behavior:        SnackBarBehavior.floating,
        ));
      }
    }
  }

  // ── decline ───────────────────────────────────────────────────────────────
  Future<void> _decline({bool auto = false}) async {
    if (_accepting || _declining) return;
    if (!auto) HapticFeedback.mediumImpact();
    if (mounted) setState(() => _declining = true);
    await _stopAll();
    try { await ref.read(authRepoProvider).rejectOrder(_orderId); } catch (_) {}
    if (mounted) {
      ref.read(incomingOrderProvider.notifier).clear();
      context.go('/dashboard');
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    _player.stop().catchError((_) {}).then((_) => _player.dispose());
    FirebaseService().cancelOrderNotification();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    _sheetCtrl.dispose();
    _mapCtrl?.dispose();
    super.dispose();
  }

  // ── helpers ───────────────────────────────────────────────────────────────
  int get _orderId {
    final o = widget.order;
    return (o['id'] is num)
        ? (o['id'] as num).toInt()
        : int.tryParse('${o['id'] ?? 0}') ?? 0;
  }

  // Coords — prefer rich order, fallback to FCM data
  double get _pickupLat {
    final r = _richOrder;
    if (r != null) return (r['pickup']?['lat'] as num?)?.toDouble() ?? 0;
    return double.tryParse('${widget.order['pickup_lat'] ?? 0}') ?? 0;
  }
  double get _pickupLng {
    final r = _richOrder;
    if (r != null) return (r['pickup']?['lng'] as num?)?.toDouble() ?? 0;
    return double.tryParse('${widget.order['pickup_lng'] ?? 0}') ?? 0;
  }
  double get _delivLat {
    final r = _richOrder;
    if (r != null) return (r['delivery']?['lat'] as num?)?.toDouble() ?? 0;
    return double.tryParse('${widget.order['delivery_lat'] ?? 0}') ?? 0;
  }
  double get _delivLng {
    final r = _richOrder;
    if (r != null) return (r['delivery']?['lng'] as num?)?.toDouble() ?? 0;
    return double.tryParse('${widget.order['delivery_lng'] ?? 0}') ?? 0;
  }

  void _fitMapBounds() {
    if (_mapCtrl == null) return;
    final pLat = _pickupLat; final pLng = _pickupLng;
    final dLat = _delivLat;  final dLng = _delivLng;
    if (pLat == 0 && pLng == 0) return;
    if (dLat == 0 && dLng == 0) {
      _mapCtrl!.animateCamera(CameraUpdate.newLatLngZoom(LatLng(pLat, pLng), 14));
      return;
    }
    final sw = LatLng(pLat < dLat ? pLat : dLat, pLng < dLng ? pLng : dLng);
    final ne = LatLng(pLat > dLat ? pLat : dLat, pLng > dLng ? pLng : dLng);
    _mapCtrl!.animateCamera(CameraUpdate.newLatLngBounds(
      LatLngBounds(southwest: sw, northeast: ne), 80));
  }

  Set<Marker> get _markers {
    final markers = <Marker>{};
    final pLat = _pickupLat; final pLng = _pickupLng;
    final dLat = _delivLat;  final dLng = _delivLng;
    if (pLat != 0 || pLng != 0) {
      markers.add(Marker(
        markerId: const MarkerId('pickup'),
        position: LatLng(pLat, pLng),
        icon:     BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueOrange),
        infoWindow: const InfoWindow(title: 'Pickup'),
      ));
    }
    if (dLat != 0 || dLng != 0) {
      markers.add(Marker(
        markerId: const MarkerId('delivery'),
        position: LatLng(dLat, dLng),
        icon:     BitmapDescriptor.defaultMarkerWithHue(BitmapDescriptor.hueGreen),
        infoWindow: const InfoWindow(title: 'Delivery'),
      ));
    }
    return markers;
  }

  @override
  Widget build(BuildContext context) {
    final o          = widget.order;
    final module     = (o['module_slug'] ?? 'order').toString();
    final moduleColor = _kModuleColors[module] ?? DC.orange;
    final moduleLabel = _kModuleLabels[module] ?? 'Delivery';
    final moduleIcon  = _kModuleIcons[module]  ?? Icons.delivery_dining_rounded;
    final orderNum   = (_richOrder?['order_number'] ?? o['order_number'] ?? '').toString();

    // Earnings / distance / time — prefer rich data
    final fee      = ((_richOrder?['delivery_fee'] ?? o['delivery_fee'] ?? 0) as num).toDouble();
    final distance = ((_richOrder?['distance_km']  ?? o['distance_km']  ?? 0) as num).toDouble();
    final estMin   = ((_richOrder?['estimated_minutes'] ?? o['estimated_minutes'] ?? 0) as num).toInt();
    final toPickup = double.tryParse('${o['driver_to_pickup_km'] ?? 0}') ?? 0;

    final progress    = _secondsLeft / _kTimeout;
    final timerColor  = _secondsLeft > 20
        ? const Color(0xFF22C55E)
        : _secondsLeft > 10 ? DC.orange : DC.error;

    final hasMap = (_pickupLat != 0 || _pickupLng != 0);
    final initialCamera = hasMap
        ? CameraPosition(target: LatLng(_pickupLat, _pickupLng), zoom: 13)
        : const CameraPosition(target: LatLng(2.0469, 45.3182), zoom: 12); // Mogadishu default

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        await _decline();
      },
      child: Scaffold(
        backgroundColor: const Color(0xFF0A0E1A),
        body: Stack(children: [

          // ── Google Maps (full screen behind sheet) ─────────────────────────
          Positioned.fill(
            child: GoogleMap(
              initialCameraPosition: initialCamera,
              markers:               _markers,
              mapType:               MapType.normal,
              myLocationButtonEnabled: false,
              zoomControlsEnabled:  false,
              compassEnabled:       false,
              onMapCreated: (ctrl) {
                _mapCtrl = ctrl;
                _fitMapBounds();
              },
            ),
          ),

          // ── Dark gradient overlay at bottom (for sheet readability) ────────
          Positioned(
            left: 0, right: 0, bottom: 0,
            height: MediaQuery.of(context).size.height * 0.62,
            child: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.transparent,
                    const Color(0xFF0A0E1A).withValues(alpha: 0.85),
                    const Color(0xFF0A0E1A),
                  ],
                  stops: const [0.0, 0.30, 0.55],
                ),
              ),
            ),
          ),

          // ── Top bar: NEW ORDER chip + timer ───────────────────────────────
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  // Module chip
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    decoration: BoxDecoration(
                      color:        const Color(0xFF0A0E1A).withValues(alpha: 0.85),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: moduleColor.withValues(alpha: 0.5)),
                    ),
                    child: Row(mainAxisSize: MainAxisSize.min, children: [
                      Icon(moduleIcon, color: moduleColor, size: 14),
                      const SizedBox(width: 5),
                      Text(
                        moduleLabel.toUpperCase(),
                        style: TextStyle(
                          color: moduleColor,
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 1.2,
                        ),
                      ),
                    ]),
                  ),
                  // Countdown circle
                  SizedBox(
                    width: 50, height: 50,
                    child: Stack(alignment: Alignment.center, children: [
                      CircularProgressIndicator(
                        value:           progress,
                        strokeWidth:     3.5,
                        backgroundColor: Colors.white.withValues(alpha: 0.12),
                        valueColor:      AlwaysStoppedAnimation<Color>(timerColor),
                      ),
                      Text(
                        '$_secondsLeft',
                        style: TextStyle(
                          color:      timerColor,
                          fontSize:   15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ]),
                  ),
                ],
              ),
            ),
          ),

          // ── Slide-up bottom sheet ──────────────────────────────────────────
          Positioned(
            left: 0, right: 0, bottom: 0,
            child: AnimatedBuilder(
              animation: _sheetAnim,
              builder: (_, child) => FractionalTranslation(
                translation: Offset(0, 1 - _sheetAnim.value),
                child: child,
              ),
              child: _BottomSheet(
                order:        widget.order,
                richOrder:    _richOrder,
                richLoading:  _richLoading,
                fee:          fee,
                distance:     distance,
                estMin:       estMin,
                toPickup:     toPickup,
                orderNum:     orderNum,
                moduleColor:  moduleColor,
                accepting:    _accepting,
                declining:    _declining,
                onAccept:     _accept,
                onDecline:    () => _decline(),
              ),
            ),
          ),
        ]),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Bottom Sheet — full order details
// ─────────────────────────────────────────────────────────────────────────────
class _BottomSheet extends StatelessWidget {
  final Map<String, dynamic>  order;
  final Map<String, dynamic>? richOrder;
  final bool   richLoading;
  final double fee, distance, toPickup;
  final int    estMin;
  final String orderNum;
  final Color  moduleColor;
  final bool   accepting, declining;
  final VoidCallback onAccept, onDecline;

  const _BottomSheet({
    required this.order,
    required this.richOrder,
    required this.richLoading,
    required this.fee,
    required this.distance,
    required this.estMin,
    required this.toPickup,
    required this.orderNum,
    required this.moduleColor,
    required this.accepting,
    required this.declining,
    required this.onAccept,
    required this.onDecline,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color:        Color(0xFF0E1520),
        borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag handle
          Padding(
            padding: const EdgeInsets.only(top: 10, bottom: 6),
            child: Container(
              width: 40, height: 4,
              decoration: BoxDecoration(
                color:        Colors.white.withValues(alpha: 0.18),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),

          // Scrollable content
          ConstrainedBox(
            constraints: BoxConstraints(
              maxHeight: MediaQuery.of(context).size.height * 0.58,
            ),
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 4, 16, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // ── Earnings row ───────────────────────────────────────────
                  Row(children: [
                    Expanded(child: _StatTile(
                      label: 'Earnings',
                      value: '\$${fee.toStringAsFixed(2)}',
                      color: const Color(0xFF22C55E),
                      icon:  Icons.attach_money_rounded,
                    )),
                    if (distance > 0) ...[
                      const SizedBox(width: 10),
                      Expanded(child: _StatTile(
                        label: 'Distance',
                        value: '${distance.toStringAsFixed(1)} km',
                        color: const Color(0xFF60A5FA),
                        icon:  Icons.route_rounded,
                      )),
                    ],
                    if (estMin > 0) ...[
                      const SizedBox(width: 10),
                      Expanded(child: _StatTile(
                        label: 'Est. Time',
                        value: '${estMin}m',
                        color: DC.orange,
                        icon:  Icons.access_time_rounded,
                      )),
                    ],
                  ]),

                  const SizedBox(height: 14),

                  // ── Details (loading skeleton or cards) ────────────────────
                  if (richLoading)
                    _LoadingSkeleton()
                  else
                    _DetailCards(
                      fcmOrder:    order,
                      richOrder:   richOrder,
                      moduleColor: moduleColor,
                      toPickup:    toPickup,
                      orderNum:    orderNum,
                    ),

                  const SizedBox(height: 14),
                ],
              ),
            ),
          ),

          // ── Sticky buttons at bottom ───────────────────────────────────────
          Container(
            padding: EdgeInsets.fromLTRB(
                16, 12, 16, MediaQuery.of(context).padding.bottom + 16),
            decoration: BoxDecoration(
              color: const Color(0xFF0E1520),
              border: Border(
                top: BorderSide(
                    color: Colors.white.withValues(alpha: 0.06), width: 1)),
            ),
            child: Row(children: [
              // Decline
              Expanded(
                flex: 2,
                child: _ActionButton(
                  label:     'Decline',
                  icon:      Icons.close_rounded,
                  bgColor:   const Color(0xFF1C2234),
                  textColor: Colors.white54,
                  loading:   declining,
                  onTap:     onDecline,
                ),
              ),
              const SizedBox(width: 12),
              // Accept
              Expanded(
                flex: 3,
                child: _ActionButton(
                  label:     'ACCEPT',
                  icon:      Icons.check_rounded,
                  bgColor:   const Color(0xFF22C55E),
                  textColor: Colors.white,
                  loading:   accepting,
                  onTap:     onAccept,
                ),
              ),
            ]),
          ),
        ],
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Detail Cards — customer, vendor, route, parcel/items
// ─────────────────────────────────────────────────────────────────────────────
class _DetailCards extends StatelessWidget {
  final Map<String, dynamic>  fcmOrder;
  final Map<String, dynamic>? richOrder;
  final Color  moduleColor;
  final double toPickup;
  final String orderNum;

  const _DetailCards({
    required this.fcmOrder,
    required this.richOrder,
    required this.moduleColor,
    required this.toPickup,
    required this.orderNum,
  });

  @override
  Widget build(BuildContext context) {
    final r      = richOrder;
    final module = (r?['module_slug'] ?? fcmOrder['module_slug'] ?? '').toString();

    // Pickup / delivery from rich order or FCM
    final pickup = (r?['pickup'] as Map?)?.cast<String, dynamic>() ?? {
      'district': fcmOrder['pickup_district'] ?? '',
      'address':  fcmOrder['pickup_address']  ?? '',
    };
    final delivery = (r?['delivery'] as Map?)?.cast<String, dynamic>() ?? {
      'district': fcmOrder['delivery_district'] ?? '',
      'address':  fcmOrder['delivery_address']  ?? '',
    };

    // Customer (person receiving)
    final custName  = (r?['delivery']?['name']  ?? '').toString();
    final custPhone = (r?['delivery']?['phone'] ?? '').toString();

    // Vendor / sender
    final vendorName  = (r?['pickup']?['name']    ?? '').toString();
    final vendorPhone = (r?['pickup']?['phone']   ?? '').toString();
    final vendorAddr  = (pickup['address']        ?? '').toString();
    final vendorDistr = (pickup['district']       ?? '').toString();

    // Parcel
    final parcel = (r?['parcel'] as Map?)?.cast<String, dynamic>();

    // Items (for food/grocery)
    final items      = r?['items'] as List?;
    final itemsCount = (r?['items_count'] as num?)?.toInt() ??
                       (items?.length ?? 0);

    final orderNumDisplay = orderNum.isNotEmpty ? orderNum
        : (r?['order_number'] ?? '').toString();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // ── Route card ─────────────────────────────────────────────────────
        _InfoCard(
          child: Column(children: [
            _RouteRow(
              dotColor: moduleColor,
              label:    'PICKUP',
              title:    pickup['district']?.toString() ?? '',
              subtitle: pickup['address']?.toString()  ?? '',
              trailing: toPickup > 0 ? '${toPickup.toStringAsFixed(1)} km away' : null,
            ),
            Padding(
              padding: const EdgeInsets.only(left: 11),
              child: Container(
                width: 2, height: 16,
                color: Colors.white.withValues(alpha: 0.10),
              ),
            ),
            _RouteRow(
              dotColor: const Color(0xFF22C55E),
              label:    'DELIVER',
              title:    delivery['district']?.toString() ?? '',
              subtitle: delivery['address']?.toString()  ?? '',
            ),
          ]),
        ),

        const SizedBox(height: 10),

        // ── Customer card ──────────────────────────────────────────────────
        if (custName.isNotEmpty || custPhone.isNotEmpty)
          _PeopleCard(
            icon:   Icons.person_rounded,
            label:  'Customer',
            color:  const Color(0xFF60A5FA),
            name:   custName,
            phone:  custPhone,
          ),

        if (custName.isNotEmpty || custPhone.isNotEmpty)
          const SizedBox(height: 10),

        // ── Vendor / Sender card ───────────────────────────────────────────
        if (vendorName.isNotEmpty || vendorPhone.isNotEmpty)
          _PeopleCard(
            icon:    module == 'eparcel' ? Icons.inbox_rounded : Icons.storefront_rounded,
            label:   module == 'eparcel' ? 'Sender' : 'Restaurant / Store',
            color:   moduleColor,
            name:    vendorName,
            phone:   vendorPhone,
            address: vendorDistr.isNotEmpty ? vendorDistr : vendorAddr,
          ),

        if (vendorName.isNotEmpty || vendorPhone.isNotEmpty)
          const SizedBox(height: 10),

        // ── Parcel info ────────────────────────────────────────────────────
        if (parcel != null) _ParcelCard(parcel: parcel, moduleColor: moduleColor),
        if (parcel != null) const SizedBox(height: 10),

        // ── Items summary (food/grocery) ────────────────────────────────────
        if (itemsCount > 0 && parcel == null)
          _InfoCard(
            child: Row(children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color:        moduleColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(Icons.receipt_long_rounded, color: moduleColor, size: 18),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '$itemsCount item${itemsCount == 1 ? '' : 's'}',
                    style: const TextStyle(
                        color: Colors.white, fontSize: 14, fontWeight: FontWeight.w700),
                  ),
                  if (items != null && items.isNotEmpty)
                    Text(
                      items.take(3).map((it) {
                        final name = it['name'] ?? it['product_name'] ?? '';
                        final qty  = it['quantity'] ?? 1;
                        return '${qty}x $name';
                      }).join('  •  '),
                      style: TextStyle(
                          color:    Colors.white.withValues(alpha: 0.40),
                          fontSize: 11),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                ],
              )),
            ]),
          ),

        if (itemsCount > 0 && parcel == null) const SizedBox(height: 10),

        // ── Order number ───────────────────────────────────────────────────
        if (orderNumDisplay.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: 2),
            child: Text(
              'Order #$orderNumDisplay',
              style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.25), fontSize: 11),
            ),
          ),
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Loading skeleton — shown while API call is in flight
// ─────────────────────────────────────────────────────────────────────────────
class _LoadingSkeleton extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Column(children: [
      _SkeletonBox(height: 88),
      const SizedBox(height: 10),
      _SkeletonBox(height: 64),
      const SizedBox(height: 10),
      _SkeletonBox(height: 64),
    ]);
  }
}

class _SkeletonBox extends StatelessWidget {
  final double height;
  const _SkeletonBox({required this.height});
  @override
  Widget build(BuildContext context) {
    return Container(
      height:      height,
      decoration: BoxDecoration(
        color:        Colors.white.withValues(alpha: 0.06),
        borderRadius: BorderRadius.circular(14),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// People Card — customer or vendor/sender
// ─────────────────────────────────────────────────────────────────────────────
class _PeopleCard extends StatelessWidget {
  final IconData icon;
  final String   label, name, phone;
  final String?  address;
  final Color    color;

  const _PeopleCard({
    required this.icon,
    required this.label,
    required this.color,
    required this.name,
    required this.phone,
    this.address,
  });

  @override
  Widget build(BuildContext context) {
    return _InfoCard(
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(
          padding: const EdgeInsets.all(9),
          decoration: BoxDecoration(
            color:        color.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, color: color, size: 18),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(
              label.toUpperCase(),
              style: TextStyle(
                  color: color, fontSize: 9,
                  fontWeight: FontWeight.w800, letterSpacing: 1.2),
            ),
            const SizedBox(height: 3),
            if (name.isNotEmpty)
              Text(name,
                  style: const TextStyle(
                      color: Colors.white, fontSize: 14,
                      fontWeight: FontWeight.w700)),
            if (phone.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                phone,
                style: TextStyle(
                    color:    Colors.white.withValues(alpha: 0.45),
                    fontSize: 12),
              ),
            ],
            if (address != null && address!.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                address!,
                style: TextStyle(
                    color:    Colors.white.withValues(alpha: 0.35),
                    fontSize: 11),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ]),
        ),
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Parcel Card
// ─────────────────────────────────────────────────────────────────────────────
class _ParcelCard extends StatelessWidget {
  final Map<String, dynamic> parcel;
  final Color moduleColor;
  const _ParcelCard({required this.parcel, required this.moduleColor});

  @override
  Widget build(BuildContext context) {
    final weight = parcel['weight']?.toString();
    final type   = parcel['package_type']?.toString();
    final desc   = parcel['description']?.toString();

    return _InfoCard(
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(
          padding: const EdgeInsets.all(9),
          decoration: BoxDecoration(
            color:        moduleColor.withValues(alpha: 0.12),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(Icons.inventory_2_rounded, color: moduleColor, size: 18),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(
              'PARCEL INFO',
              style: TextStyle(
                  color: moduleColor, fontSize: 9,
                  fontWeight: FontWeight.w800, letterSpacing: 1.2),
            ),
            const SizedBox(height: 4),
            if (type != null && type.isNotEmpty)
              Text(
                type,
                style: const TextStyle(
                    color: Colors.white, fontSize: 13,
                    fontWeight: FontWeight.w700),
              ),
            if (weight != null && weight.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                'Weight: $weight',
                style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.45), fontSize: 12),
              ),
            ],
            if (desc != null && desc.isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                desc,
                style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.35), fontSize: 11),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ],
          ]),
        ),
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Info Card container
// ─────────────────────────────────────────────────────────────────────────────
class _InfoCard extends StatelessWidget {
  final Widget child;
  const _InfoCard({required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color:        Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withValues(alpha: 0.07)),
      ),
      child: child,
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Route Row
// ─────────────────────────────────────────────────────────────────────────────
class _RouteRow extends StatelessWidget {
  final Color   dotColor;
  final String  label, title, subtitle;
  final String? trailing;
  const _RouteRow({
    required this.dotColor,
    required this.label,
    required this.title,
    required this.subtitle,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(
        width: 22, height: 22,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          color: dotColor.withValues(alpha: 0.15),
          border: Border.all(color: dotColor, width: 2),
        ),
        child: Center(child: Container(
          width: 7, height: 7,
          decoration: BoxDecoration(shape: BoxShape.circle, color: dotColor),
        )),
      ),
      const SizedBox(width: 10),
      Expanded(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            Text(label, style: TextStyle(
                color: dotColor, fontSize: 9,
                fontWeight: FontWeight.w800, letterSpacing: 1.2)),
            if (trailing != null) ...[
              const SizedBox(width: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                decoration: BoxDecoration(
                    color: dotColor.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(6)),
                child: Text(trailing!, style: TextStyle(
                    color: dotColor, fontSize: 9, fontWeight: FontWeight.w700)),
              ),
            ],
          ]),
          const SizedBox(height: 2),
          if (title.isNotEmpty)
            Text(title, style: const TextStyle(
                color: Colors.white, fontSize: 13, fontWeight: FontWeight.w600)),
          if (subtitle.isNotEmpty)
            Text(subtitle, style: TextStyle(
                color: Colors.white.withValues(alpha: 0.38), fontSize: 11),
                maxLines: 1, overflow: TextOverflow.ellipsis),
          const SizedBox(height: 4),
        ]),
      ),
    ]);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Stat Tile
// ─────────────────────────────────────────────────────────────────────────────
class _StatTile extends StatelessWidget {
  final String   label, value;
  final Color    color;
  final IconData icon;
  const _StatTile({
    required this.label,
    required this.value,
    required this.color,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 10),
      decoration: BoxDecoration(
        color:        color.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.22)),
      ),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, color: color, size: 18),
        const SizedBox(height: 5),
        Text(value, style: TextStyle(
            color: color, fontSize: 16, fontWeight: FontWeight.w800)),
        const SizedBox(height: 2),
        Text(label, style: TextStyle(
            color: Colors.white.withValues(alpha: 0.35),
            fontSize: 10, fontWeight: FontWeight.w500)),
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Action Button
// ─────────────────────────────────────────────────────────────────────────────
class _ActionButton extends StatelessWidget {
  final String   label;
  final IconData icon;
  final Color    bgColor, textColor;
  final bool     loading;
  final VoidCallback onTap;

  const _ActionButton({
    required this.label,
    required this.icon,
    required this.bgColor,
    required this.textColor,
    required this.loading,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: loading ? null : onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        height:   52,
        decoration: BoxDecoration(
          color:        loading ? bgColor.withValues(alpha: 0.5) : bgColor,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            if (!loading)
              BoxShadow(
                color:      bgColor.withValues(alpha: 0.35),
                blurRadius: 16,
                offset:     const Offset(0, 6),
              ),
          ],
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: loading
              ? [SizedBox(
                  width: 20, height: 20,
                  child: CircularProgressIndicator(strokeWidth: 2, color: textColor))]
              : [
                  Icon(icon, color: textColor, size: 20),
                  const SizedBox(width: 7),
                  Text(label, style: TextStyle(
                      color:         textColor,
                      fontSize:      15,
                      fontWeight:    FontWeight.w800,
                      letterSpacing: 0.5)),
                ],
        ),
      ),
    );
  }
}
