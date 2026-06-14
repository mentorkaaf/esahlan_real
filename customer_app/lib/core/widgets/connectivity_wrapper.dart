import 'dart:async';
import 'dart:math' as math;
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../features/home/presentation/providers/home_provider.dart';

class ConnectivityWrapper extends ConsumerStatefulWidget {
  final Widget child;
  const ConnectivityWrapper({super.key, required this.child});

  @override
  ConsumerState<ConnectivityWrapper> createState() => _ConnectivityWrapperState();
}

class _ConnectivityWrapperState extends ConsumerState<ConnectivityWrapper> {
  bool _isOnline = true;
  late StreamSubscription<List<ConnectivityResult>> _sub;

  @override
  void initState() {
    super.initState();
    Connectivity().checkConnectivity().then(_updateStatus);
    _sub = Connectivity().onConnectivityChanged.listen(_updateStatus);
  }

  void _updateStatus(List<ConnectivityResult> results) {
    final online = results.any((r) => r != ConnectivityResult.none);
    if (online == _isOnline) return;
    setState(() => _isOnline = online);
    if (online) _refreshProviders();
  }

  void _refreshProviders() {
    ref.invalidate(modulesProvider);
    ref.invalidate(homeDataProvider);
    ref.invalidate(homeBannersProvider);
  }

  @override
  void dispose() {
    _sub.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        widget.child,
        if (!_isOnline)
          Directionality(
            textDirection: TextDirection.ltr,
            child: const _NoInternetScreen(),
          ),
      ],
    );
  }
}

class _NoInternetScreen extends StatefulWidget {
  const _NoInternetScreen();
  @override
  State<_NoInternetScreen> createState() => _NoInternetScreenState();
}

class _NoInternetScreenState extends State<_NoInternetScreen>
    with TickerProviderStateMixin {
  late AnimationController _fadeCtrl;
  late AnimationController _floatCtrl;
  late Animation<double> _fadeAnim;
  late Animation<double> _floatAnim;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    _fadeCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 600));
    _floatCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 2400))
      ..repeat(reverse: true);
    _fadeAnim = CurvedAnimation(parent: _fadeCtrl, curve: Curves.easeOut);
    _floatAnim = Tween<double>(begin: -8, end: 8)
        .animate(CurvedAnimation(parent: _floatCtrl, curve: Curves.easeInOut));
    _fadeCtrl.forward();
  }

  @override
  void dispose() {
    _fadeCtrl.dispose();
    _floatCtrl.dispose();
    super.dispose();
  }

  Future<void> _retry() async {
    setState(() => _checking = true);
    await Future.delayed(const Duration(milliseconds: 900));
    await Connectivity().checkConnectivity();
    if (mounted) setState(() => _checking = false);
  }

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.of(context).size;
    return Scaffold(
      body: FadeTransition(
        opacity: _fadeAnim,
        child: Stack(
          children: [
            // Orange background
            Container(color: const Color(0xFFF97316)),

            // Illustration area
            Positioned(
              top: 0,
              left: 0,
              right: 0,
              height: size.height * 0.58,
              child: AnimatedBuilder(
                animation: _floatAnim,
                builder: (context, child) => Transform.translate(
                  offset: Offset(0, _floatAnim.value),
                  child: child,
                ),
                child: CustomPaint(
                  painter: _SparklesPainter(),
                  child: Center(
                    child: SizedBox(
                      width: 220,
                      height: 220,
                      child: CustomPaint(painter: _PlugIllustrationPainter()),
                    ),
                  ),
                ),
              ),
            ),

            // White bottom sheet
            Positioned(
              bottom: 0,
              left: 0,
              right: 0,
              child: Container(
                decoration: const BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.vertical(top: Radius.circular(36)),
                ),
                padding: EdgeInsets.fromLTRB(
                  32, 36, 32, MediaQuery.of(context).padding.bottom + 36,
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Text(
                      'No internet Connection',
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF1A1B2E),
                        letterSpacing: -0.4,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 12),
                    const Text(
                      'You are not connected to the internet.\nMake sure Wi-Fi is on, Airplane Mode is\noff and try again.',
                      style: TextStyle(
                        fontSize: 14,
                        color: Color(0xFF9CA3AF),
                        height: 1.65,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 32),
                    SizedBox(
                      width: double.infinity,
                      child: OutlinedButton(
                        onPressed: _checking ? null : _retry,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: const Color(0xFFF97316),
                          side: const BorderSide(color: Color(0xFFF97316), width: 1.5),
                          padding: const EdgeInsets.symmetric(vertical: 15),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(30),
                          ),
                        ),
                        child: _checking
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  color: Color(0xFFF97316),
                                  strokeWidth: 2.5,
                                ),
                              )
                            : const Text(
                                'Retry',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w600,
                                  letterSpacing: 0.2,
                                ),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// Draws sparkle dots around the illustration like in the reference
class _SparklesPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = Colors.white.withOpacity(0.35);
    final cx = size.width / 2;
    final cy = size.height / 2;
    final positions = [
      Offset(cx - 90, cy - 70),
      Offset(cx + 95, cy - 60),
      Offset(cx - 110, cy + 20),
      Offset(cx + 105, cy + 30),
      Offset(cx - 50, cy - 100),
      Offset(cx + 55, cy - 95),
      Offset(cx - 80, cy + 80),
      Offset(cx + 85, cy + 75),
    ];
    final sizes = [5.0, 4.0, 6.0, 4.5, 3.5, 5.5, 4.0, 3.0];
    for (int i = 0; i < positions.length; i++) {
      canvas.drawCircle(positions[i], sizes[i], paint);
      // Draw small diamond sparkle
      final sp = Paint()
        ..color = Colors.white.withOpacity(0.5)
        ..style = PaintingStyle.fill;
      final path = Path();
      final s = sizes[i] * 0.7;
      path.moveTo(positions[i].dx, positions[i].dy - s * 2);
      path.lineTo(positions[i].dx + s, positions[i].dy);
      path.lineTo(positions[i].dx, positions[i].dy + s * 2);
      path.lineTo(positions[i].dx - s, positions[i].dy);
      path.close();
      canvas.drawPath(path, sp);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

// Draws a disconnected plug illustration
class _PlugIllustrationPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final stroke = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeWidth = 5
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final fill = Paint()
      ..color = Colors.white.withOpacity(0.2)
      ..style = PaintingStyle.fill;

    // Left plug body
    final leftPlug = RRect.fromRectAndRadius(
      Rect.fromLTWH(w * 0.08, h * 0.35, w * 0.3, h * 0.28),
      const Radius.circular(16),
    );
    canvas.drawRRect(leftPlug, fill);
    canvas.drawRRect(leftPlug, stroke);

    // Left plug pins
    canvas.drawLine(Offset(w * 0.19, h * 0.35), Offset(w * 0.19, h * 0.26), stroke);
    canvas.drawLine(Offset(w * 0.27, h * 0.35), Offset(w * 0.27, h * 0.26), stroke);

    // Left cable going left
    final leftCable = Path()
      ..moveTo(w * 0.08, h * 0.49)
      ..cubicTo(w * 0.0, h * 0.49, w * 0.0, h * 0.72, w * 0.08, h * 0.72)
      ..lineTo(w * 0.0, h * 0.72);
    canvas.drawPath(leftCable, stroke);

    // Right plug body
    final rightPlug = RRect.fromRectAndRadius(
      Rect.fromLTWH(w * 0.62, h * 0.35, w * 0.3, h * 0.28),
      const Radius.circular(16),
    );
    canvas.drawRRect(rightPlug, fill);
    canvas.drawRRect(rightPlug, stroke);

    // Right plug pins (holes)
    final holePaint = Paint()
      ..color = Colors.white
      ..style = PaintingStyle.stroke
      ..strokeWidth = 4;
    canvas.drawOval(Rect.fromCenter(center: Offset(w * 0.73, h * 0.46), width: 10, height: 14), holePaint);
    canvas.drawOval(Rect.fromCenter(center: Offset(w * 0.81, h * 0.46), width: 10, height: 14), holePaint);

    // Right cable going right
    final rightCable = Path()
      ..moveTo(w * 0.92, h * 0.49)
      ..cubicTo(w, h * 0.49, w, h * 0.72, w * 0.92, h * 0.72)
      ..lineTo(w, h * 0.72);
    canvas.drawPath(rightCable, stroke);

    // Gap between the two plugs (disconnected)
    // Small lightning bolt / break indicator in the middle
    final boltPaint = Paint()
      ..color = Colors.white.withOpacity(0.9)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 3.5
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final bolt = Path()
      ..moveTo(w * 0.48, h * 0.38)
      ..lineTo(w * 0.44, h * 0.50)
      ..lineTo(w * 0.50, h * 0.50)
      ..lineTo(w * 0.46, h * 0.62);
    canvas.drawPath(bolt, boltPaint);

    // Dots showing gap
    final dotPaint = Paint()..color = Colors.white.withOpacity(0.6);
    canvas.drawCircle(Offset(w * 0.39, h * 0.49), 3, dotPaint);
    canvas.drawCircle(Offset(w * 0.61, h * 0.49), 3, dotPaint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
