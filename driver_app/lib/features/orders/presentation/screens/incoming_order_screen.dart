import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:google_maps_flutter/google_maps_flutter.dart';
import 'package:audioplayers/audioplayers.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../../core/theme/driver_colors.dart';

// ──────────────────────────────────────────────────────────────────────────────
// Provider: the pending incoming order (set by FirebaseService on FCM arrival)
// ──────────────────────────────────────────────────────────────────────────────
class _IncomingOrderState {
  final Map<String, dynamic>? order;
  const _IncomingOrderState({this.order});
  _IncomingOrderState copyWith({Map<String, dynamic>? order}) =>
      _IncomingOrderState(order: order);
}

class IncomingOrderNotifier extends StateNotifier<_IncomingOrderState> {
  IncomingOrderNotifier() : super(const _IncomingOrderState());

  void setOrder(Map<String, dynamic> order) {
    state = _IncomingOrderState(order: order);
  }

  void clear() {
    state = const _IncomingOrderState();
  }
}

final incomingOrderProvider =
    StateNotifierProvider<IncomingOrderNotifier, _IncomingOrderState>(
        (ref) => IncomingOrderNotifier());

// ──────────────────────────────────────────────────────────────────────────────
// Incoming Order Screen — DoorDash Dasher style
// ──────────────────────────────────────────────────────────────────────────────
class IncomingOrderScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> order;
  const IncomingOrderScreen({super.key, required this.order});

  @override
  ConsumerState<IncomingOrderScreen> createState() =>
      _IncomingOrderScreenState();
}

class _IncomingOrderScreenState extends ConsumerState<IncomingOrderScreen>
    with TickerProviderStateMixin {
  static const _kCountdown = 45; // seconds to accept

  late AnimationController _pulseCtrl;
  late Animation<double> _pulseAnim;
  late AnimationController _slideCtrl;
  late Animation<Offset> _slideAnim;

  Timer? _countdownTimer;
  int _secondsLeft = _kCountdown;
  bool _accepting = false;
  bool _declining = false;

  final _alarm = AudioPlayer();
  GoogleMapController? _mapCtrl;

  @override
  void initState() {
    super.initState();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);

    _pulseCtrl =
        AnimationController(vsync: this, duration: const Duration(seconds: 1))
          ..repeat(reverse: true);
    _pulseAnim = Tween<double>(begin: 0.95, end: 1.05).animate(
        CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeInOut));

    _slideCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 600));
    _slideAnim = Tween<Offset>(
            begin: const Offset(0, 1), end: Offset.zero)
        .animate(
            CurvedAnimation(parent: _slideCtrl, curve: Curves.easeOutCubic));
    _slideCtrl.forward();

    _startAlarm();
    _startCountdown();
  }

  Future<void> _startAlarm() async {
    try {
      await _alarm.setReleaseMode(ReleaseMode.loop);
      await _alarm.setVolume(1.0);
      await _alarm.play(AssetSource('sounds/order_ring.wav'));
    } catch (_) {
      // Alarm sound optional — notification sound handles background
    }
  }

  void _startCountdown() {
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() => _secondsLeft--);
      if (_secondsLeft <= 0) {
        _decline(autoDecline: true);
      }
    });
  }

  Future<void> _accept() async {
    if (_accepting || _declining) return;
    setState(() => _accepting = true);
    _stopAlarm();
    try {
      final id = (widget.order['id'] as num).toInt();
      await ref.read(authRepoProvider).acceptOrder(id);
      if (mounted) {
        ref.read(incomingOrderProvider.notifier).clear();
        context.go('/orders');
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: const Text('✓ Order accepted — go pick it up!'),
            backgroundColor: DC.success,
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _accepting = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('$e'), backgroundColor: DC.error,
              behavior: SnackBarBehavior.floating),
        );
      }
    }
  }

  Future<void> _decline({bool autoDecline = false}) async {
    if (_accepting || _declining) return;
    setState(() => _declining = true);
    _stopAlarm();
    try {
      final id = (widget.order['id'] as num).toInt();
      await ref.read(authRepoProvider).rejectOrder(id);
    } catch (_) {}
    if (mounted) {
      ref.read(incomingOrderProvider.notifier).clear();
      context.go('/dashboard');
    }
  }

  void _stopAlarm() {
    _countdownTimer?.cancel();
    _alarm.stop();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  }

  @override
  void dispose() {
    _stopAlarm();
    _alarm.dispose();
    _pulseCtrl.dispose();
    _slideCtrl.dispose();
    super.dispose();
  }

  // ── helpers ─────────────────────────────────────────────────────────────────
  static const _moduleIcons = {
    'efood': Icons.restaurant_rounded,
    'egrocery': Icons.local_grocery_store_rounded,
    'eshop': Icons.shopping_bag_rounded,
    'eparcel': Icons.local_shipping_rounded,
    'emoving': Icons.move_to_inbox_rounded,
    'elaundry': Icons.local_laundry_service_rounded,
  };
  static const _moduleLabels = {
    'efood': 'eFood Delivery',
    'egrocery': 'eGrocery Delivery',
    'eshop': 'eShop Delivery',
    'eparcel': 'eParcel Delivery',
    'emoving': 'eMoving Service',
    'elaundry': 'eLaundry Pickup',
  };

  @override
  Widget build(BuildContext context) {
    final o = widget.order;
    final pickup = (o['pickup'] as Map<String, dynamic>?) ?? {};
    final delivery = (o['delivery'] as Map<String, dynamic>?) ?? {};
    final module = (o['module_slug'] ?? 'order').toString();
    final fee =
        double.tryParse('${o['delivery_fee'] ?? 0}') ?? 0;
    final distance = double.tryParse('${o['distance_km'] ?? 0}') ?? 0;
    final estMin = (o['estimated_minutes'] as num?)?.toInt() ?? 0;
    final driverToPickup =
        double.tryParse('${o['driver_to_pickup_km'] ?? 0}') ?? 0;

    final pickupLat = double.tryParse('${pickup['lat'] ?? 0}') ?? 0;
    final pickupLng = double.tryParse('${pickup['lng'] ?? 0}') ?? 0;
    final delivLat = double.tryParse('${delivery['lat'] ?? 0}') ?? 0;
    final delivLng = double.tryParse('${delivery['lng'] ?? 0}') ?? 0;
    final hasCoords = pickupLat != 0 && delivLat != 0;

    final progress = _secondsLeft / _kCountdown;
    final progressColor = _secondsLeft > 20
        ? DC.success
        : _secondsLeft > 10
            ? DC.orange
            : DC.error;

    return Scaffold(
      backgroundColor: const Color(0xFF0A0F1A),
      body: Stack(children: [
        // ── BACKGROUND MAP ──────────────────────────────────────────────────
        if (hasCoords)
          Positioned.fill(
            child: GoogleMap(
              initialCameraPosition: CameraPosition(
                target: LatLng(
                    (pickupLat + delivLat) / 2, (pickupLng + delivLng) / 2),
                zoom: 12.5,
              ),
              onMapCreated: (ctrl) => _mapCtrl = ctrl,
              myLocationEnabled: true,
              myLocationButtonEnabled: false,
              zoomControlsEnabled: false,
              rotateGesturesEnabled: false,
              tiltGesturesEnabled: false,
              markers: {
                Marker(
                  markerId: const MarkerId('pickup'),
                  position: LatLng(pickupLat, pickupLng),
                  icon: BitmapDescriptor.defaultMarkerWithHue(
                      BitmapDescriptor.hueOrange),
                  infoWindow: InfoWindow(
                      title: '📦 Pickup',
                      snippet: pickup['district'] ?? ''),
                ),
                Marker(
                  markerId: const MarkerId('delivery'),
                  position: LatLng(delivLat, delivLng),
                  icon: BitmapDescriptor.defaultMarkerWithHue(
                      BitmapDescriptor.hueGreen),
                  infoWindow: InfoWindow(
                      title: '🏠 Delivery',
                      snippet: delivery['district'] ?? ''),
                ),
              },
              polylines: {
                Polyline(
                  polylineId: const PolylineId('route'),
                  points: [
                    LatLng(pickupLat, pickupLng),
                    LatLng(delivLat, delivLng),
                  ],
                  color: DC.orange,
                  width: 4,
                  patterns: [
                    PatternItem.dash(20),
                    PatternItem.gap(12),
                  ],
                ),
              },
            ),
          ),

        // ── DARK GRADIENT OVERLAY ───────────────────────────────────────────
        Positioned.fill(
          child: Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  Color(0xCC0A0F1A),
                  Color(0x880A0F1A),
                  Color(0x330A0F1A),
                  Color(0x880A0F1A),
                  Color(0xFF0A0F1A),
                ],
                stops: [0, 0.15, 0.4, 0.65, 1],
              ),
            ),
          ),
        ),

        // ── TOP: PULSING ALERT BADGE ────────────────────────────────────────
        SafeArea(
          child: Column(children: [
            const SizedBox(height: 20),
            ScaleTransition(
              scale: _pulseAnim,
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [Color(0xFFFF6B00), Color(0xFFFF9A00)],
                  ),
                  borderRadius: BorderRadius.circular(40),
                  boxShadow: [
                    BoxShadow(
                        color: DC.orange.withValues(alpha: 0.6),
                        blurRadius: 20,
                        spreadRadius: 2),
                  ],
                ),
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  const Icon(Icons.notifications_active_rounded,
                      color: Colors.white, size: 20),
                  const SizedBox(width: 8),
                  Text(
                    'NEW ORDER REQUEST',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      fontSize: 14,
                      letterSpacing: 1.2,
                    ),
                  ),
                ]),
              ),
            ),
          ]),
        ),

        // ── BOTTOM PANEL ────────────────────────────────────────────────────
        Positioned(
          left: 0,
          right: 0,
          bottom: 0,
          child: SlideTransition(
            position: _slideAnim,
            child: Container(
              decoration: const BoxDecoration(
                color: Color(0xFF111827),
                borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
                boxShadow: [
                  BoxShadow(
                      color: Colors.black54, blurRadius: 30, spreadRadius: 5),
                ],
              ),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                // Handle
                Container(
                  margin: const EdgeInsets.only(top: 12, bottom: 8),
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.white24,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),

                // ── HEADER ROW: module chip + order number ─────────────────
                Padding(
                  padding: const EdgeInsets.fromLTRB(20, 4, 20, 12),
                  child: Row(children: [
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                      decoration: BoxDecoration(
                        color: DC.orangeDim,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(mainAxisSize: MainAxisSize.min, children: [
                        Icon(_moduleIcons[module] ?? Icons.delivery_dining_rounded,
                            color: DC.orange, size: 13),
                        const SizedBox(width: 5),
                        Text(_moduleLabels[module] ?? 'Delivery',
                            style: const TextStyle(color: DC.orange, fontSize: 11, fontWeight: FontWeight.w700)),
                      ]),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Order #${o['order_number'] ?? ''}',
                        style: const TextStyle(color: Colors.white54, fontSize: 12),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ]),
                ),

                // ── COUNTDOWN + EARNINGS ────────────────────────────────────
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Row(children: [
                    // Countdown circle
                    SizedBox(
                      width: 64,
                      height: 64,
                      child: Stack(alignment: Alignment.center, children: [
                        CircularProgressIndicator(
                          value: progress,
                          strokeWidth: 5,
                          backgroundColor: Colors.white12,
                          valueColor: AlwaysStoppedAnimation(progressColor),
                        ),
                        Text(
                          '$_secondsLeft',
                          style: TextStyle(
                            color: progressColor,
                            fontWeight: FontWeight.w900,
                            fontSize: 20,
                          ),
                        ),
                      ]),
                    ),
                    const SizedBox(width: 12),
                    const Text('sec left',
                        style: TextStyle(color: Colors.white38, fontSize: 11)),
                    const Spacer(),
                    Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                      const Text('You earn',
                          style: TextStyle(color: Colors.white38, fontSize: 11)),
                      Text(
                        '\$ ${fee.toStringAsFixed(0)}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          fontSize: 32,
                          height: 1,
                        ),
                      ),
                    ]),
                  ]),
                ),

                const SizedBox(height: 16),
                Container(height: 1, color: Colors.white10,
                    margin: const EdgeInsets.symmetric(horizontal: 20)),
                const SizedBox(height: 16),

                // ── ROUTE INFO ─────────────────────────────────────────────
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Row(children: [
                    // Pickup
                    Expanded(
                      child: _RoutePoint(
                        icon: Icons.circle_rounded,
                        iconColor: DC.orange,
                        label: 'PICKUP',
                        district: pickup['district'] ?? 'N/A',
                        address: pickup['address'] ?? '',
                        subLabel: driverToPickup > 0
                            ? '${driverToPickup.toStringAsFixed(1)} km from you'
                            : null,
                      ),
                    ),
                    Column(children: [
                      Container(width: 1, height: 40, color: Colors.white12),
                      const Icon(Icons.arrow_forward_rounded,
                          color: Colors.white24, size: 16),
                    ]),
                    Expanded(
                      child: _RoutePoint(
                        icon: Icons.location_on_rounded,
                        iconColor: const Color(0xFF22C55E),
                        label: 'DELIVERY',
                        district: delivery['district'] ?? 'N/A',
                        address: delivery['address'] ?? '',
                        subLabel: distance > 0
                            ? '${distance.toStringAsFixed(1)} km total'
                            : null,
                        alignRight: true,
                      ),
                    ),
                  ]),
                ),

                const SizedBox(height: 16),

                // ── STATS ROW ──────────────────────────────────────────────
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Row(children: [
                    _StatChip(
                      icon: Icons.straighten_rounded,
                      label: '${distance.toStringAsFixed(1)} km',
                      hint: 'Distance',
                    ),
                    const SizedBox(width: 8),
                    _StatChip(
                      icon: Icons.timer_rounded,
                      label: estMin > 0 ? '~$estMin min' : '—',
                      hint: 'Est. time',
                    ),
                    const SizedBox(width: 8),
                    _StatChip(
                      icon: Icons.directions_bike_rounded,
                      label: driverToPickup > 0
                          ? '${driverToPickup.toStringAsFixed(1)} km'
                          : '—',
                      hint: 'To pickup',
                    ),
                  ]),
                ),

                const SizedBox(height: 24),

                // ── ACCEPT / DECLINE ───────────────────────────────────────
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Row(children: [
                    // DECLINE
                    Expanded(
                      flex: 2,
                      child: GestureDetector(
                        onTap: _declining ? null : _decline,
                        child: Container(
                          height: 60,
                          decoration: BoxDecoration(
                            color: Colors.white.withValues(alpha: 0.08),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(color: Colors.white12),
                          ),
                          child: _declining
                              ? const Center(
                                  child: SizedBox.square(
                                    dimension: 22,
                                    child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: Colors.white54),
                                  ))
                              : const Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Icon(Icons.close_rounded,
                                        color: Colors.white54, size: 20),
                                    SizedBox(width: 6),
                                    Text('Decline',
                                        style: TextStyle(
                                            color: Colors.white54,
                                            fontWeight: FontWeight.w700,
                                            fontSize: 16)),
                                  ],
                                ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 12),
                    // ACCEPT
                    Expanded(
                      flex: 3,
                      child: GestureDetector(
                        onTap: _accepting ? null : _accept,
                        child: Container(
                          height: 60,
                          decoration: BoxDecoration(
                            gradient: const LinearGradient(
                              colors: [Color(0xFF16A34A), Color(0xFF22C55E)],
                            ),
                            borderRadius: BorderRadius.circular(16),
                            boxShadow: [
                              BoxShadow(
                                  color: const Color(0xFF22C55E)
                                      .withValues(alpha: 0.4),
                                  blurRadius: 16,
                                  offset: const Offset(0, 6)),
                            ],
                          ),
                          child: _accepting
                              ? const Center(
                                  child: SizedBox.square(
                                    dimension: 24,
                                    child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        color: Colors.white),
                                  ))
                              : const Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    Icon(Icons.check_circle_rounded,
                                        color: Colors.white, size: 22),
                                    SizedBox(width: 8),
                                    Text('Accept Order',
                                        style: TextStyle(
                                            color: Colors.white,
                                            fontWeight: FontWeight.w900,
                                            fontSize: 17)),
                                  ],
                                ),
                        ),
                      ),
                    ),
                  ]),
                ),

                // Safe area bottom
                SizedBox(
                    height: MediaQuery.of(context).padding.bottom + 16),
              ]),
            ),
          ),
        ),
      ]),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Sub-widgets
// ──────────────────────────────────────────────────────────────────────────────

class _RoutePoint extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String label;
  final String district;
  final String address;
  final String? subLabel;
  final bool alignRight;

  const _RoutePoint({
    required this.icon,
    required this.iconColor,
    required this.label,
    required this.district,
    required this.address,
    this.subLabel,
    this.alignRight = false,
  });

  @override
  Widget build(BuildContext context) {
    final align =
        alignRight ? CrossAxisAlignment.end : CrossAxisAlignment.start;
    return Column(crossAxisAlignment: align, children: [
      Row(
        mainAxisSize: MainAxisSize.min,
        children: alignRight
            ? [
                Text(label,
                    style: const TextStyle(
                        color: Colors.white38,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.0)),
                const SizedBox(width: 5),
                Icon(icon, color: iconColor, size: 10),
              ]
            : [
                Icon(icon, color: iconColor, size: 10),
                const SizedBox(width: 5),
                Text(label,
                    style: const TextStyle(
                        color: Colors.white38,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.0)),
              ],
      ),
      const SizedBox(height: 4),
      Text(
        district,
        textAlign: alignRight ? TextAlign.right : TextAlign.left,
        style: const TextStyle(
            color: Colors.white, fontWeight: FontWeight.w700, fontSize: 14),
      ),
      if (address.isNotEmpty)
        Text(
          address,
          textAlign: alignRight ? TextAlign.right : TextAlign.left,
          style: const TextStyle(color: Colors.white38, fontSize: 11),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
      if (subLabel != null)
        Text(
          subLabel!,
          style: TextStyle(color: iconColor, fontSize: 11, fontWeight: FontWeight.w600),
        ),
    ]);
  }
}

class _StatChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final String hint;
  const _StatChip(
      {required this.icon, required this.label, required this.hint});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 12),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.06),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: Colors.white10),
        ),
        child: Column(children: [
          Icon(icon, color: DC.orange, size: 18),
          const SizedBox(height: 4),
          Text(label,
              style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 13)),
          Text(hint,
              style: const TextStyle(color: Colors.white38, fontSize: 10)),
        ]),
      ),
    );
  }
}
