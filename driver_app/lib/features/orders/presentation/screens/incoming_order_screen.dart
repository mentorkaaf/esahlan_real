import 'dart:async';
import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:audioplayers/audioplayers.dart';
import '../../../auth/presentation/providers/auth_provider.dart';
import '../../../../core/theme/driver_colors.dart';

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Provider
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
class _IncomingOrderState {
  final Map<String, dynamic>? order;
  const _IncomingOrderState({this.order});
  _IncomingOrderState copyWith({Map<String, dynamic>? order}) =>
      _IncomingOrderState(order: order);
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

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Incoming Order Screen â€” Full-screen DoorDash-style alarm
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
class IncomingOrderScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> order;
  const IncomingOrderScreen({super.key, required this.order});

  @override
  ConsumerState<IncomingOrderScreen> createState() =>
      _IncomingOrderScreenState();
}

class _IncomingOrderScreenState extends ConsumerState<IncomingOrderScreen>
    with TickerProviderStateMixin {
  static const _kCountdown = 45;

  // Animations
  late AnimationController _pulseCtrl;
  late AnimationController _rippleCtrl;
  late AnimationController _slideCtrl;
  late AnimationController _rotateCtrl;
  late Animation<double> _pulseAnim;
  late Animation<double> _rippleAnim;
  late Animation<Offset> _slideAnim;
  late Animation<double> _rotateAnim;

  Timer? _countdownTimer;
  int _secondsLeft = _kCountdown;
  bool _accepting = false;
  bool _declining = false;

  final _alarm = AudioPlayer();

  static const _moduleIcons = {
    'efood':    Icons.restaurant_rounded,
    'egrocery': Icons.local_grocery_store_rounded,
    'eshop':    Icons.shopping_bag_rounded,
    'eparcel':  Icons.local_shipping_rounded,
    'emoving':  Icons.move_to_inbox_rounded,
    'elaundry': Icons.local_laundry_service_rounded,
    'erent':    Icons.home_rounded,
  };
  static const _moduleLabels = {
    'efood':    'eFood Delivery',
    'egrocery': 'eGrocery Delivery',
    'eshop':    'eShop Delivery',
    'eparcel':  'eParcel Delivery',
    'emoving':  'eMoving Service',
    'elaundry': 'eLaundry Pickup',
    'erent':    'eRent Service',
  };
  static const _moduleColors = {
    'efood':    Color(0xFFEF4444),
    'egrocery': Color(0xFF22C55E),
    'eshop':    Color(0xFF8B5CF6),
    'eparcel':  Color(0xFFFF8A00),
    'emoving':  Color(0xFF3B82F6),
    'elaundry': Color(0xFF06B6D4),
    'erent':    Color(0xFFF59E0B),
  };

  @override
  void initState() {
    super.initState();
    // Full immersive â€” hide status bar + nav bar
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.immersiveSticky);

    // Pulse animation â€” icon breathing
    _pulseCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 900))
      ..repeat(reverse: true);
    _pulseAnim = Tween<double>(begin: 0.92, end: 1.08)
        .animate(CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeInOut));

    // Ripple animation â€” expanding rings
    _rippleCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 1500))
      ..repeat();
    _rippleAnim = Tween<double>(begin: 0, end: 1)
        .animate(CurvedAnimation(parent: _rippleCtrl, curve: Curves.easeOut));

    // Slide up animation
    _slideCtrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 500));
    _slideAnim = Tween<Offset>(begin: const Offset(0, 1), end: Offset.zero)
        .animate(CurvedAnimation(parent: _slideCtrl, curve: Curves.easeOutCubic));
    _slideCtrl.forward();

    // Rotate animation for the ring
    _rotateCtrl = AnimationController(
        vsync: this, duration: const Duration(seconds: 3))
      ..repeat();
    _rotateAnim = Tween<double>(begin: 0, end: 1).animate(_rotateCtrl);

    _startAlarm();
    _startCountdown();
  }

  Future<void> _startAlarm() async {
    try {
      await _alarm.setReleaseMode(ReleaseMode.loop);
      await _alarm.setVolume(1.0);
      await _alarm.play(AssetSource('sounds/order_ring.wav'));
    } catch (_) {}
  }

  void _startCountdown() {
    _countdownTimer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() => _secondsLeft--);
      if (_secondsLeft <= 0) _decline(autoDecline: true);
    });
  }

  Future<void> _accept() async {
    if (_accepting || _declining) return;
    HapticFeedback.heavyImpact();
    setState(() => _accepting = true);
    _stopAll();
    try {
      final id = (widget.order['id'] as num).toInt();
      await ref.read(authRepoProvider).acceptOrder(id);
      if (mounted) {
        ref.read(incomingOrderProvider.notifier).clear();
        context.go('/orders');
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(
          content: const Text('âœ“ Order accepted â€” go pick it up!'),
          backgroundColor: DC.success,
          behavior: SnackBarBehavior.floating,
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

  Future<void> _decline({bool autoDecline = false}) async {
    if (_accepting || _declining) return;
    if (!autoDecline) HapticFeedback.mediumImpact();
    setState(() => _declining = true);
    _stopAll();
    try {
      final id = (widget.order['id'] as num).toInt();
      await ref.read(authRepoProvider).rejectOrder(id);
    } catch (_) {}
    if (mounted) {
      ref.read(incomingOrderProvider.notifier).clear();
      context.go('/dashboard');
    }
  }

  void _stopAll() {
    _countdownTimer?.cancel();
    _alarm.stop();
    SystemChrome.setEnabledSystemUIMode(SystemUiMode.edgeToEdge);
  }

  @override
  void dispose() {
    _stopAll();
    _alarm.dispose();
    _pulseCtrl.dispose();
    _rippleCtrl.dispose();
    _slideCtrl.dispose();
    _rotateCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final o        = widget.order;
    final pickup   = (o['pickup']   as Map<String, dynamic>?) ?? {};
    final delivery = (o['delivery'] as Map<String, dynamic>?) ?? {};
    final module   = (o['module_slug'] ?? 'order').toString();
    final fee      = double.tryParse('${o['delivery_fee']  ?? 0}') ?? 0;
    final distance = double.tryParse('${o['distance_km']   ?? 0}') ?? 0;
    final estMin   = (o['estimated_minutes'] as num?)?.toInt() ?? 0;
    final toPickup = double.tryParse('${o['driver_to_pickup_km'] ?? 0}') ?? 0;

    final moduleColor = _moduleColors[module] ?? DC.orange;
    final progress    = _secondsLeft / _kCountdown;
    final timerColor  = _secondsLeft > 20
        ? const Color(0xFF22C55E)
        : _secondsLeft > 10 ? DC.orange : DC.error;

    final size = MediaQuery.of(context).size;

    return Scaffold(
      backgroundColor: const Color(0xFF060B14),
      body: Stack(children: [
        // â”€â”€ ANIMATED BACKGROUND â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        _AnimatedBackground(
          pulseAnim: _pulseAnim,
          rippleAnim: _rippleAnim,
          rotateAnim: _rotateAnim,
          moduleColor: moduleColor,
          size: size,
        ),

        // â”€â”€ MAIN CONTENT â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
        SafeArea(
          child: Column(children: [
            // â”€â”€ TOP SECTION â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            Expanded(
              flex: 5,
              child: _TopSection(
                pulseAnim: _pulseAnim,
                moduleColor: moduleColor,
                moduleIcon: _moduleIcons[module] ?? Icons.delivery_dining_rounded,
                moduleLabel: _moduleLabels[module] ?? 'Delivery',
                orderNumber: o['order_number']?.toString() ?? '',
              ),
            ),

            // â”€â”€ SLIDE-UP CARD â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
            Expanded(
              flex: 7,
              child: SlideTransition(
                position: _slideAnim,
                child: _OrderCard(
                  fee: fee,
                  distance: distance,
                  estMin: estMin,
                  toPickup: toPickup,
                  pickup: pickup,
                  delivery: delivery,
                  secondsLeft: _secondsLeft,
                  countdown: _kCountdown,
                  progress: progress,
                  timerColor: timerColor,
                  moduleColor: moduleColor,
                  accepting: _accepting,
                  declining: _declining,
                  onAccept: _accept,
                  onDecline: _decline,
                ),
              ),
            ),
          ]),
        ),
      ]),
    );
  }
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Animated Background â€” ripple rings + gradient glow
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
class _AnimatedBackground extends StatelessWidget {
  final Animation<double> pulseAnim;
  final Animation<double> rippleAnim;
  final Animation<double> rotateAnim;
  final Color moduleColor;
  final Size size;

  const _AnimatedBackground({
    required this.pulseAnim,
    required this.rippleAnim,
    required this.rotateAnim,
    required this.moduleColor,
    required this.size,
  });

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: Listenable.merge([pulseAnim, rippleAnim]),
      builder: (_, __) {
        return CustomPaint(
          size: size,
          painter: _BgPainter(
            ripple: rippleAnim.value,
            pulse: pulseAnim.value,
            color: moduleColor,
          ),
        );
      },
    );
  }
}

class _BgPainter extends CustomPainter {
  final double ripple;
  final double pulse;
  final Color color;
  _BgPainter({required this.ripple, required this.pulse, required this.color});

  @override
  void paint(Canvas canvas, Size size) {
    final cx = size.width / 2;
    final cy = size.height * 0.35;

    // Dark base gradient
    final bgPaint = Paint();
    final bgRect = Rect.fromLTWH(0, 0, size.width, size.height);
    bgPaint.shader = const LinearGradient(
      begin: Alignment.topCenter,
      end: Alignment.bottomCenter,
      colors: [Color(0xFF060B14), Color(0xFF0D1628), Color(0xFF060B14)],
    ).createShader(bgRect);
    canvas.drawRect(bgRect, bgPaint);

    // Glow behind icon
    final glowPaint = Paint()
      ..color = color.withValues(alpha: 0.12 * pulse)
      ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 80);
    canvas.drawCircle(Offset(cx, cy), 140 * pulse, glowPaint);

    // Ripple rings
    for (int i = 0; i < 3; i++) {
      final t = (ripple + i / 3) % 1.0;
      final radius = 80.0 + t * 160;
      final opacity = (1 - t) * 0.3;
      if (opacity <= 0) continue;
      final paint = Paint()
        ..color = color.withValues(alpha: opacity)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.5;
      canvas.drawCircle(Offset(cx, cy), radius, paint);
    }
  }

  @override
  bool shouldRepaint(_BgPainter old) =>
      old.ripple != ripple || old.pulse != pulse;
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Top Section â€” icon + label
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
class _TopSection extends StatelessWidget {
  final Animation<double> pulseAnim;
  final Color moduleColor;
  final IconData moduleIcon;
  final String moduleLabel;
  final String orderNumber;

  const _TopSection({
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
        // Alert badge
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: Colors.white.withValues(alpha: 0.15)),
          ),
          child: const Row(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.notifications_active_rounded,
                color: Colors.white70, size: 14),
            SizedBox(width: 6),
            Text('NEW ORDER REQUEST',
                style: TextStyle(
                    color: Colors.white70,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 1.5)),
          ]),
        ),

        const SizedBox(height: 28),

        // Module icon â€” pulsing
        ScaleTransition(
          scale: pulseAnim,
          child: Container(
            width: 100,
            height: 100,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: RadialGradient(colors: [
                moduleColor.withValues(alpha: 0.25),
                moduleColor.withValues(alpha: 0.05),
              ]),
              border: Border.all(color: moduleColor.withValues(alpha: 0.5), width: 2),
            ),
            child: Icon(moduleIcon, color: moduleColor, size: 46),
          ),
        ),

        const SizedBox(height: 16),

        // Module label
        Text(moduleLabel,
            style: TextStyle(
                color: moduleColor,
                fontSize: 16,
                fontWeight: FontWeight.w800,
                letterSpacing: 0.5)),
        const SizedBox(height: 4),
        if (orderNumber.isNotEmpty)
          Text('Order #$orderNumber',
              style: const TextStyle(
                  color: Colors.white38, fontSize: 13)),
      ],
    );
  }
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Order Card â€” slide-up panel with all details
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
class _OrderCard extends StatelessWidget {
  final double fee;
  final double distance;
  final int estMin;
  final double toPickup;
  final Map<String, dynamic> pickup;
  final Map<String, dynamic> delivery;
  final int secondsLeft;
  final int countdown;
  final double progress;
  final Color timerColor;
  final Color moduleColor;
  final bool accepting;
  final bool declining;
  final VoidCallback onAccept;
  final void Function({bool autoDecline}) onDecline;

  const _OrderCard({
    required this.fee,
    required this.distance,
    required this.estMin,
    required this.toPickup,
    required this.pickup,
    required this.delivery,
    required this.secondsLeft,
    required this.countdown,
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
    final bottomPad = MediaQuery.of(context).padding.bottom;

    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        color: Color(0xFF111827),
        borderRadius: BorderRadius.vertical(top: Radius.circular(32)),
        boxShadow: [
          BoxShadow(color: Colors.black54, blurRadius: 40, spreadRadius: 4),
        ],
      ),
      child: Column(children: [
        // Drag handle
        Container(
          margin: const EdgeInsets.only(top: 10, bottom: 4),
          width: 36,
          height: 4,
          decoration: BoxDecoration(
            color: Colors.white12,
            borderRadius: BorderRadius.circular(2),
          ),
        ),

        Expanded(
          child: SingleChildScrollView(
            padding: EdgeInsets.fromLTRB(20, 8, 20, bottomPad + 8),
            child: Column(children: [
              // â”€â”€ EARNINGS + TIMER â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
              Row(children: [
                // Timer ring
                SizedBox(
                  width: 72,
                  height: 72,
                  child: Stack(alignment: Alignment.center, children: [
                    SizedBox.expand(
                      child: CircularProgressIndicator(
                        value: progress,
                        strokeWidth: 6,
                        backgroundColor: Colors.white10,
                        valueColor: AlwaysStoppedAnimation(timerColor),
                        strokeCap: StrokeCap.round,
                      ),
                    ),
                    Column(mainAxisSize: MainAxisSize.min, children: [
                      Text('$secondsLeft',
                          style: TextStyle(
                              color: timerColor,
                              fontSize: 22,
                              fontWeight: FontWeight.w900,
                              height: 1)),
                      Text('sec',
                          style: const TextStyle(
                              color: Colors.white38,
                              fontSize: 10,
                              fontWeight: FontWeight.w600)),
                    ]),
                  ]),
                ),
                const SizedBox(width: 16),
                // Earnings
                Expanded(
                  child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                    const Text('You earn',
                        style:
                            TextStyle(color: Colors.white38, fontSize: 12)),
                    const SizedBox(height: 2),
                    Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                      Text(
                        '\$${fee.toStringAsFixed(fee == fee.floorToDouble() ? 0 : 2)}',
                        style: const TextStyle(
                            color: Colors.white,
                            fontSize: 36,
                            fontWeight: FontWeight.w900,
                            height: 1),
                      ),
                      const SizedBox(width: 4),
                      const Padding(
                        padding: EdgeInsets.only(bottom: 4),
                        child: Text('delivery fee',
                            style: TextStyle(
                                color: Colors.white38, fontSize: 11)),
                      ),
                    ]),
                  ]),
                ),
              ]),

              const SizedBox(height: 16),
              _divider(),
              const SizedBox(height: 16),

              // â”€â”€ ROUTE â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
              _RouteSection(pickup: pickup, delivery: delivery),

              const SizedBox(height: 16),
              _divider(),
              const SizedBox(height: 14),

              // â”€â”€ STATS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
              Row(children: [
                _Stat(
                    icon: Icons.straighten_rounded,
                    value: distance > 0
                        ? '${distance.toStringAsFixed(1)} km'
                        : 'â€”',
                    label: 'Distance',
                    color: moduleColor),
                _gap(),
                _Stat(
                    icon: Icons.timer_rounded,
                    value: estMin > 0 ? '~$estMin min' : 'â€”',
                    label: 'Est. time',
                    color: moduleColor),
                _gap(),
                _Stat(
                    icon: Icons.directions_bike_rounded,
                    value: toPickup > 0
                        ? '${toPickup.toStringAsFixed(1)} km'
                        : 'â€”',
                    label: 'To pickup',
                    color: moduleColor),
              ]),

              const SizedBox(height: 20),

              // â”€â”€ BUTTONS â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
              Row(children: [
                // DECLINE
                Expanded(
                  flex: 2,
                  child: _Button(
                    onTap: declining ? null : () => onDecline(),
                    loading: declining,
                    label: 'Decline',
                    icon: Icons.close_rounded,
                    color: Colors.white,
                    backgroundColor: Colors.white.withValues(alpha: 0.07),
                    borderColor: Colors.white.withValues(alpha: 0.12),
                    textColor: Colors.white60,
                  ),
                ),
                const SizedBox(width: 12),
                // ACCEPT
                Expanded(
                  flex: 3,
                  child: _Button(
                    onTap: accepting ? null : onAccept,
                    loading: accepting,
                    label: 'Accept Order',
                    icon: Icons.check_circle_rounded,
                    color: Colors.white,
                    backgroundColor: const Color(0xFF16A34A),
                    shadowColor: const Color(0xFF22C55E),
                    textColor: Colors.white,
                    bold: true,
                  ),
                ),
              ]),
            ]),
          ),
        ),
      ]),
    );
  }

  Widget _divider() => Container(
      height: 1, color: Colors.white.withValues(alpha: 0.06));
  Widget _gap() => const SizedBox(width: 8);
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Route Section
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
class _RouteSection extends StatelessWidget {
  final Map<String, dynamic> pickup;
  final Map<String, dynamic> delivery;
  const _RouteSection({required this.pickup, required this.delivery});

  @override
  Widget build(BuildContext context) {
    final pickupDistrict = pickup['district']?.toString() ?? '';
    final pickupAddress  = pickup['address']?.toString()  ?? '';
    final delivDistrict  = delivery['district']?.toString() ?? '';
    final delivAddress   = delivery['address']?.toString()  ?? '';

    return Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      // Left column: icons + line
      Column(children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: DC.orange.withValues(alpha: 0.15),
            shape: BoxShape.circle,
            border: Border.all(color: DC.orange.withValues(alpha: 0.4)),
          ),
          child: const Icon(Icons.circle_rounded, color: DC.orange, size: 12),
        ),
        Container(
            width: 2, height: 32,
            color: Colors.white.withValues(alpha: 0.1)),
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: const Color(0xFF22C55E).withValues(alpha: 0.15),
            shape: BoxShape.circle,
            border: Border.all(
                color: const Color(0xFF22C55E).withValues(alpha: 0.4)),
          ),
          child: const Icon(Icons.location_on_rounded,
              color: Color(0xFF22C55E), size: 14),
        ),
      ]),
      const SizedBox(width: 12),

      // Right column: text
      Expanded(
        child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
          // Pickup
          const Text('PICKUP',
              style: TextStyle(
                  color: Colors.white38,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.2)),
          const SizedBox(height: 2),
          Text(
            pickupDistrict.isNotEmpty ? pickupDistrict : 'Pickup location',
            style: const TextStyle(
                color: Colors.white,
                fontSize: 15,
                fontWeight: FontWeight.w700),
          ),
          if (pickupAddress.isNotEmpty)
            Text(pickupAddress,
                style: const TextStyle(
                    color: Colors.white38, fontSize: 11),
                maxLines: 1,
                overflow: TextOverflow.ellipsis),

          const SizedBox(height: 20),

          // Delivery
          const Text('DELIVERY',
              style: TextStyle(
                  color: Colors.white38,
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.2)),
          const SizedBox(height: 2),
          Text(
            delivDistrict.isNotEmpty ? delivDistrict : 'Delivery location',
            style: const TextStyle(
                color: Colors.white,
                fontSize: 15,
                fontWeight: FontWeight.w700),
          ),
          if (delivAddress.isNotEmpty)
            Text(delivAddress,
                style: const TextStyle(
                    color: Colors.white38, fontSize: 11),
                maxLines: 1,
                overflow: TextOverflow.ellipsis),
        ]),
      ),
    ]);
  }
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Stat chip
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
class _Stat extends StatelessWidget {
  final IconData icon;
  final String value;
  final String label;
  final Color color;
  const _Stat(
      {required this.icon,
      required this.value,
      required this.label,
      required this.color});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding:
            const EdgeInsets.symmetric(vertical: 12, horizontal: 8),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.04),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Colors.white.withValues(alpha: 0.07)),
        ),
        child: Column(children: [
          Icon(icon, color: color, size: 18),
          const SizedBox(height: 5),
          Text(value,
              style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 13)),
          Text(label,
              style: const TextStyle(
                  color: Colors.white38, fontSize: 9)),
        ]),
      ),
    );
  }
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Button
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
class _Button extends StatelessWidget {
  final VoidCallback? onTap;
  final bool loading;
  final String label;
  final IconData icon;
  final Color color;
  final Color backgroundColor;
  final Color? borderColor;
  final Color? shadowColor;
  final Color textColor;
  final bool bold;

  const _Button({
    required this.onTap,
    required this.loading,
    required this.label,
    required this.icon,
    required this.color,
    required this.backgroundColor,
    this.borderColor,
    this.shadowColor,
    required this.textColor,
    this.bold = false,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        height: 62,
        decoration: BoxDecoration(
          color: backgroundColor,
          borderRadius: BorderRadius.circular(18),
          border: borderColor != null
              ? Border.all(color: borderColor!)
              : null,
          boxShadow: shadowColor != null
              ? [
                  BoxShadow(
                      color: shadowColor!.withValues(alpha: 0.35),
                      blurRadius: 20,
                      offset: const Offset(0, 8))
                ]
              : null,
        ),
        child: loading
            ? Center(
                child: SizedBox.square(
                  dimension: 24,
                  child: CircularProgressIndicator(
                      strokeWidth: 2.5, color: textColor),
                ))
            : Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                Icon(icon, color: textColor, size: 22),
                const SizedBox(width: 8),
                Text(label,
                    style: TextStyle(
                        color: textColor,
                        fontWeight: bold
                            ? FontWeight.w900
                            : FontWeight.w600,
                        fontSize: bold ? 17 : 15)),
              ]),
      ),
    );
  }
}

