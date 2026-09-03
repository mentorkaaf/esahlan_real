import 'dart:async';
import 'package:audioplayers/audioplayers.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../../core/theme/driver_colors.dart';
import '../../../../core/services/firebase_service.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Provider — kept for compatibility with any code that checks incoming state
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
const _kModuleIcons = {
  'efood':    Icons.restaurant_rounded,
  'egrocery': Icons.local_grocery_store_rounded,
  'eshop':    Icons.shopping_bag_rounded,
  'eparcel':  Icons.local_shipping_rounded,
  'emoving':  Icons.move_to_inbox_rounded,
  'elaundry': Icons.local_laundry_service_rounded,
  'erent':    Icons.home_rounded,
};
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

// ─────────────────────────────────────────────────────────────────────────────
// IncomingOrderScreen — full-screen DoorDash-style incoming order alarm
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
  int   _secondsLeft = _kTimeout;
  Timer? _timer;

  // ── button state ──────────────────────────────────────────────────────────
  bool _accepting = false;
  bool _declining = false;

  // ── audio ─────────────────────────────────────────────────────────────────
  final _player = AudioPlayer();

  // ── animations ────────────────────────────────────────────────────────────
  late final AnimationController _pulseCtrl;
  late final AnimationController _rippleCtrl;
  late final AnimationController _slideCtrl;
  late final Animation<double>   _pulseAnim;
  late final Animation<double>   _rippleAnim;
  late final Animation<Offset>   _slideAnim;

  @override
  void initState() {
    super.initState();

    // Full immersive — hide status bar + nav bar
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);

    // Pulse: icon breathes
    _pulseCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 850))
      ..repeat(reverse: true);
    _pulseAnim = Tween<double>(begin: 0.90, end: 1.10)
        .animate(CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeInOut));

    // Ripple: expanding rings behind icon
    _rippleCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 1400))
      ..repeat();
    _rippleAnim = Tween<double>(begin: 0.0, end: 1.0)
        .animate(CurvedAnimation(parent: _rippleCtrl, curve: Curves.easeOut));

    // Slide: bottom card slides up
    _slideCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 450));
    _slideAnim = Tween<Offset>(begin: const Offset(0, 1), end: Offset.zero)
        .animate(CurvedAnimation(parent: _slideCtrl, curve: Curves.easeOutCubic));
    _slideCtrl.forward();

    _startAudio();
    _startCountdown();
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
      // Fix H-6: re-check mounted after setState before calling _decline,
      // which itself calls setState. The widget could be disposed between the
      // two operations.
      if (_secondsLeft <= 0 && mounted) _decline(auto: true);
    });
  }

  // ── stop everything ───────────────────────────────────────────────────────
  // Fix L-4: made async so _player.stop() is properly awaited, preventing
  // the ring sound from persisting briefly into the next screen.
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
      final id = _orderId;
      await ref.read(authRepoProvider).acceptOrder(id);
      if (mounted) {
        ref.read(incomingOrderProvider.notifier).clear();
        context.go('/orders');
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: const Text('Order accepted — go pick it up!'),
          backgroundColor: DC.success,
          behavior: SnackBarBehavior.floating,
          shape:
              RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        ));
      }
    } catch (e) {
      if (mounted) {
        setState(() => _accepting = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: Text('$e'),
          backgroundColor: DC.error,
          behavior: SnackBarBehavior.floating,
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
    try {
      await ref.read(authRepoProvider).rejectOrder(_orderId);
    } catch (_) {}
    if (mounted) {
      ref.read(incomingOrderProvider.notifier).clear();
      context.go('/dashboard');
    }
  }

  @override
  void dispose() {
    // _stopAll is async but dispose must be sync; fire-and-forget is acceptable
    // here since the timer is cancelled synchronously at the start of _stopAll.
    _timer?.cancel();
    _player.stop().catchError((_) {}).then((_) => _player.dispose());
    FirebaseService().cancelOrderNotification();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
    _pulseCtrl.dispose();
    _rippleCtrl.dispose();
    _slideCtrl.dispose();
    super.dispose();
  }

  // ── helpers ───────────────────────────────────────────────────────────────
  int get _orderId =>
      (widget.order['id'] is num)
          ? (widget.order['id'] as num).toInt()
          : int.tryParse('${widget.order['id'] ?? 0}') ?? 0;

  @override
  Widget build(BuildContext context) {
    final o        = widget.order;
    final pickup   = (o['pickup']   as Map?)?.cast<String, dynamic>() ?? {};
    final delivery = (o['delivery'] as Map?)?.cast<String, dynamic>() ?? {};
    final module   = (o['module_slug'] ?? 'order').toString();

    final fee      = double.tryParse('${o['delivery_fee']       ?? 0}') ?? 0;
    final distance = double.tryParse('${o['distance_km']        ?? 0}') ?? 0;
    final estMin   = int.tryParse   ('${o['estimated_minutes']  ?? 0}') ?? 0;
    final toPickup = double.tryParse('${o['driver_to_pickup_km']?? 0}') ?? 0;
    final orderNum = o['order_number']?.toString() ?? '';

    final moduleColor = _kModuleColors[module] ?? DC.orange;
    final moduleIcon  = _kModuleIcons[module]  ?? Icons.delivery_dining_rounded;
    final moduleLabel = _kModuleLabels[module] ?? 'Delivery';

    final progress   = _secondsLeft / _kTimeout;
    final timerColor = _secondsLeft > 20
        ? const Color(0xFF22C55E)
        : _secondsLeft > 10 ? DC.orange : DC.error;

    final size = MediaQuery.of(context).size;

    // Fix L-7: wrap with PopScope so the system back button calls _stopAll
    // (cancels timer, stops audio, restores system UI) before navigating away.
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        await _decline();
      },
      child: Scaffold(
      backgroundColor: const Color(0xFF060B14),
      body: Stack(children: [
        // ── Animated background ─────────────────────────────────────────────
        _RippleBackground(
          rippleAnim: _rippleAnim,
          moduleColor: moduleColor,
          size: size,
        ),

        // ── Content ─────────────────────────────────────────────────────────
        SafeArea(
          child: Column(children: [
            // Top: pulsing icon + module label + order number
            Expanded(
              flex: 4,
              child: _TopHero(
                pulseAnim:   _pulseAnim,
                moduleColor: moduleColor,
                moduleIcon:  moduleIcon,
                moduleLabel: moduleLabel,
                orderNumber: orderNum,
              ),
            ),

            // Bottom: slide-up card with all order info + buttons
            Expanded(
              flex: 6,
              child: SlideTransition(
                position: _slideAnim,
                child: _OrderCard(
                  fee:          fee,
                  distance:     distance,
                  estMin:       estMin,
                  toPickup:     toPickup,
                  pickup:       pickup,
                  delivery:     delivery,
                  secondsLeft:  _secondsLeft,
                  progress:     progress,
                  timerColor:   timerColor,
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
      ]),
    ), // Scaffold
    ); // PopScope
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Ripple Background — animated dark gradient + expanding rings
// ─────────────────────────────────────────────────────────────────────────────
class _RippleBackground extends StatelessWidget {
  final Animation<double> rippleAnim;
  final Color moduleColor;
  final Size  size;
  const _RippleBackground({
    required this.rippleAnim,
    required this.moduleColor,
    required this.size,
  });

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: rippleAnim,
      builder: (_, __) => CustomPaint(
        size: size,
        painter: _BgPainter(
          ripple:      rippleAnim.value,
          moduleColor: moduleColor,
        ),
      ),
    );
  }
}

class _BgPainter extends CustomPainter {
  final double ripple;
  final Color  moduleColor;
  _BgPainter({required this.ripple, required this.moduleColor});

  @override
  void paint(Canvas canvas, Size size) {
    // Dark gradient background
    final bgPaint = Paint()
      ..shader = RadialGradient(
        center: const Alignment(0, -0.3),
        radius: 1.4,
        colors: [
          moduleColor.withValues(alpha: 0.18),
          const Color(0xFF0A0F1E),
          const Color(0xFF060B14),
        ],
        stops: const [0.0, 0.55, 1.0],
      ).createShader(Rect.fromLTWH(0, 0, size.width, size.height));
    canvas.drawRect(Rect.fromLTWH(0, 0, size.width, size.height), bgPaint);

    // Three staggered ripple rings
    final cx = size.width  / 2;
    final cy = size.height * 0.38;
    for (int i = 0; i < 3; i++) {
      final t = (ripple + i / 3.0) % 1.0;
      final radius = size.width * 0.28 + t * size.width * 0.42;
      final opacity = (1.0 - t) * 0.22;
      canvas.drawCircle(
        Offset(cx, cy),
        radius,
        Paint()
          ..color   = moduleColor.withValues(alpha: opacity)
          ..style   = PaintingStyle.stroke
          ..strokeWidth = 2.5,
      );
    }

    // Glow behind icon
    canvas.drawCircle(
      Offset(cx, cy),
      size.width * 0.20,
      Paint()
        ..color  = moduleColor.withValues(alpha: 0.07)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 40),
    );
  }

  @override
  bool shouldRepaint(_BgPainter old) =>
      old.ripple != ripple || old.moduleColor != moduleColor;
}

// ─────────────────────────────────────────────────────────────────────────────
// Top Hero — pulsing icon, module label, order number
// ─────────────────────────────────────────────────────────────────────────────
class _TopHero extends StatelessWidget {
  final Animation<double> pulseAnim;
  final Color             moduleColor;
  final IconData          moduleIcon;
  final String            moduleLabel;
  final String            orderNumber;
  const _TopHero({
    required this.pulseAnim,
    required this.moduleColor,
    required this.moduleIcon,
    required this.moduleLabel,
    required this.orderNumber,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        // Alert chip
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
          decoration: BoxDecoration(
            color:        moduleColor.withValues(alpha: 0.15),
            borderRadius: BorderRadius.circular(20),
            border:       Border.all(color: moduleColor.withValues(alpha: 0.4)),
          ),
          child: Text(
            'NEW ORDER',
            style: TextStyle(
              color:      moduleColor,
              fontSize:   11,
              fontWeight: FontWeight.w800,
              letterSpacing: 2.0,
            ),
          ),
        ),

        const SizedBox(height: 20),

        // Pulsing icon
        ScaleTransition(
          scale: pulseAnim,
          child: Container(
            width: 96, height: 96,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: RadialGradient(
                colors: [
                  moduleColor.withValues(alpha: 0.30),
                  moduleColor.withValues(alpha: 0.08),
                ],
              ),
              border: Border.all(
                  color: moduleColor.withValues(alpha: 0.55), width: 2),
            ),
            child: Icon(moduleIcon, size: 44, color: moduleColor),
          ),
        ),

        const SizedBox(height: 18),

        // Module label
        Text(
          moduleLabel,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 22,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.3,
          ),
        ),

        const SizedBox(height: 6),

        // Order number
        if (orderNumber.isNotEmpty)
          Text(
            'Order #$orderNumber',
            style: TextStyle(
              color:      Colors.white.withValues(alpha: 0.45),
              fontSize:   13,
              fontWeight: FontWeight.w500,
            ),
          ),
      ],
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Order Card — earnings, route, countdown, accept / decline
// ─────────────────────────────────────────────────────────────────────────────
class _OrderCard extends StatelessWidget {
  final double   fee, distance, toPickup;
  final int      estMin, secondsLeft;
  final double   progress;
  final Color    timerColor, moduleColor;
  final Map<String, dynamic> pickup, delivery;
  final bool     accepting, declining;
  final VoidCallback onAccept, onDecline;

  const _OrderCard({
    required this.fee,
    required this.distance,
    required this.estMin,
    required this.toPickup,
    required this.pickup,
    required this.delivery,
    required this.secondsLeft,
    required this.progress,
    required this.timerColor,
    required this.moduleColor,
    required this.accepting,
    required this.declining,
    required this.onAccept,
    required this.onDecline,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      decoration: BoxDecoration(
        color:        const Color(0xFF0E1520),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(
            color: Colors.white.withValues(alpha: 0.07)),
        boxShadow: [
          BoxShadow(
            color:       Colors.black.withValues(alpha: 0.55),
            blurRadius:  30,
            offset:      const Offset(0, -8),
          ),
        ],
      ),
      child: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Earnings row ─────────────────────────────────────────────
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

            const SizedBox(height: 16),

            // ── Route ────────────────────────────────────────────────────
            _RouteSection(
              pickup:      pickup,
              delivery:    delivery,
              toPickup:    toPickup,
              moduleColor: moduleColor,
            ),

            const SizedBox(height: 16),

            // ── Countdown ────────────────────────────────────────────────
            Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                SizedBox(
                  width: 48, height: 48,
                  child: Stack(alignment: Alignment.center, children: [
                    CircularProgressIndicator(
                      value:       progress,
                      strokeWidth: 3.5,
                      backgroundColor:
                          Colors.white.withValues(alpha: 0.08),
                      valueColor:
                          AlwaysStoppedAnimation<Color>(timerColor),
                    ),
                    Text(
                      '$secondsLeft',
                      style: TextStyle(
                        color:      timerColor,
                        fontSize:   15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ]),
                ),
                const SizedBox(width: 10),
                Text(
                  'Respond before time runs out',
                  style: TextStyle(
                    color:    Colors.white.withValues(alpha: 0.40),
                    fontSize: 12,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 16),

            // ── Buttons ──────────────────────────────────────────────────
            Row(children: [
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
          ],
        ),
      ),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Route section — pickup → delivery with real addresses
// ─────────────────────────────────────────────────────────────────────────────
class _RouteSection extends StatelessWidget {
  final Map<String, dynamic> pickup, delivery;
  final double toPickup;
  final Color  moduleColor;
  const _RouteSection({
    required this.pickup,
    required this.delivery,
    required this.toPickup,
    required this.moduleColor,
  });

  @override
  Widget build(BuildContext context) {
    final pickupDistrict  = pickup['district']  as String? ?? '';
    final pickupAddress   = pickup['address']   as String? ?? '';
    final delivDistrict   = delivery['district']as String? ?? '';
    final delivAddress    = delivery['address'] as String? ?? '';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color:        Colors.white.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(16),
        border:       Border.all(
            color: Colors.white.withValues(alpha: 0.06)),
      ),
      child: Column(children: [
        // Pickup
        _RouteRow(
          dotColor: moduleColor,
          label:    'PICKUP',
          district: pickupDistrict,
          address:  pickupAddress,
          badge:    toPickup > 0
              ? '${toPickup.toStringAsFixed(1)} km away'
              : null,
        ),
        // Connector line
        Padding(
          padding: const EdgeInsets.only(left: 10),
          child: Row(children: [
            Container(
              width: 2, height: 20,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [
                    moduleColor.withValues(alpha: 0.6),
                    const Color(0xFF22C55E).withValues(alpha: 0.6),
                  ],
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                ),
              ),
            ),
          ]),
        ),
        // Delivery
        _RouteRow(
          dotColor: const Color(0xFF22C55E),
          label:    'DELIVER',
          district: delivDistrict,
          address:  delivAddress,
        ),
      ]),
    );
  }
}

class _RouteRow extends StatelessWidget {
  final Color   dotColor;
  final String  label, district, address;
  final String? badge;
  const _RouteRow({
    required this.dotColor,
    required this.label,
    required this.district,
    required this.address,
    this.badge,
  });

  @override
  Widget build(BuildContext context) {
    final hasAddress = district.isNotEmpty || address.isNotEmpty;
    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Column(children: [
        Container(
          width: 22, height: 22,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: dotColor.withValues(alpha: 0.15),
            border: Border.all(color: dotColor, width: 2),
          ),
          child: Center(
            child: Container(
              width: 7, height: 7,
              decoration: BoxDecoration(
                  shape: BoxShape.circle, color: dotColor),
            ),
          ),
        ),
      ]),
      const SizedBox(width: 10),
      Expanded(
        child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
          Row(children: [
            Text(
              label,
              style: TextStyle(
                color:         dotColor,
                fontSize:      10,
                fontWeight:    FontWeight.w700,
                letterSpacing: 1.4,
              ),
            ),
            if (badge != null) ...[
              const SizedBox(width: 6),
              Container(
                padding: const EdgeInsets.symmetric(
                    horizontal: 7, vertical: 2),
                decoration: BoxDecoration(
                  color:        dotColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  badge!,
                  style: TextStyle(
                      color: dotColor, fontSize: 9, fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ]),
          const SizedBox(height: 2),
          if (hasAddress) ...[
            if (district.isNotEmpty)
              Text(
                district,
                style: const TextStyle(
                    color:      Colors.white,
                    fontSize:   13,
                    fontWeight: FontWeight.w600),
              ),
            if (address.isNotEmpty)
              Text(
                address,
                style: TextStyle(
                    color:    Colors.white.withValues(alpha: 0.40),
                    fontSize: 11),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
          ] else
            Text(
              'Address not available',
              style: TextStyle(
                  color:    Colors.white.withValues(alpha: 0.25),
                  fontSize: 12),
            ),
          const SizedBox(height: 6),
        ]),
      ),
    ]);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Stat tile
// ─────────────────────────────────────────────────────────────────────────────
class _StatTile extends StatelessWidget {
  final String label, value;
  final Color  color;
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
        Text(
          value,
          style: TextStyle(
              color: color, fontSize: 16, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 2),
        Text(
          label,
          style: TextStyle(
              color:    Colors.white.withValues(alpha: 0.35),
              fontSize: 10,
              fontWeight: FontWeight.w500),
        ),
      ]),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Action button
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
              ? [
                  SizedBox(
                    width: 20, height: 20,
                    child: CircularProgressIndicator(
                        strokeWidth: 2, color: textColor),
                  ),
                ]
              : [
                  Icon(icon, color: textColor, size: 20),
                  const SizedBox(width: 7),
                  Text(
                    label,
                    style: TextStyle(
                        color:      textColor,
                        fontSize:   15,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 0.5),
                  ),
                ],
        ),
      ),
    );
  }
}
