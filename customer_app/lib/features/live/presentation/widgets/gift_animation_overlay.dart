import 'dart:math' as math;
import 'dart:ui';
import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';

class GiftAnimationOverlay extends StatefulWidget {
  final GiftEvent event;
  const GiftAnimationOverlay({super.key, required this.event});

  @override
  State<GiftAnimationOverlay> createState() => _GiftAnimationOverlayState();
}

class _GiftAnimationOverlayState extends State<GiftAnimationOverlay>
    with TickerProviderStateMixin {
  late AnimationController _entryCtrl;
  late AnimationController _particleCtrl;
  late Animation<double> _scale;
  late Animation<double> _entryOpacity;

  GiftRarity get _rarity => widget.event.gift.rarity;
  bool get _isFullScreen =>
      _rarity == GiftRarity.epic || _rarity == GiftRarity.legendary;

  @override
  void initState() {
    super.initState();
    _entryCtrl = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 500),
    );
    _particleCtrl = AnimationController(
      vsync: this,
      duration: const Duration(seconds: 3),
    );

    _scale = CurvedAnimation(parent: _entryCtrl, curve: Curves.elasticOut)
        .drive(Tween(begin: 0.3, end: 1.0));
    _entryOpacity = CurvedAnimation(parent: _entryCtrl, curve: Curves.easeIn)
        .drive(Tween(begin: 0.0, end: 1.0));

    _entryCtrl.forward().then((_) => _particleCtrl.forward());
  }

  @override
  void dispose() {
    _entryCtrl.dispose();
    _particleCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_isFullScreen) {
      return _FullScreenGift(
        event: widget.event,
        scale: _scale,
        opacity: _entryOpacity,
        particles: _particleCtrl,
        rarity: _rarity,
      );
    }
    return _BannerGift(
      event: widget.event,
      scale: _scale,
      opacity: _entryOpacity,
    );
  }
}

// ── Banner (normal / rare) ────────────────────────────────────────────────────

class _BannerGift extends StatelessWidget {
  final GiftEvent event;
  final Animation<double> scale;
  final Animation<double> opacity;

  const _BannerGift({required this.event, required this.scale, required this.opacity});

  Color get _accent => event.gift.rarity == GiftRarity.rare
      ? const Color(0xFF4FC3F7)
      : Colors.orange;

  @override
  Widget build(BuildContext context) {
    return Positioned(
      left: 12,
      bottom: 180,
      child: FadeTransition(
        opacity: opacity,
        child: ScaleTransition(
          scale: scale,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: 0.78),
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: _accent.withValues(alpha: 0.6), width: 1),
            ),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(event.gift.emoji, style: const TextStyle(fontSize: 28)),
                const SizedBox(width: 10),
                Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(event.senderName,
                        style: TextStyle(
                            color: _accent, fontSize: 12, fontWeight: FontWeight.bold)),
                    Text(
                      'sent ${event.gift.name}${event.quantity > 1 ? ' ×${event.quantity}' : ''}',
                      style: const TextStyle(color: Colors.white70, fontSize: 11),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ── Full-screen (epic / legendary) ───────────────────────────────────────────

class _FullScreenGift extends StatelessWidget {
  final GiftEvent event;
  final Animation<double> scale;
  final Animation<double> opacity;
  final AnimationController particles;
  final GiftRarity rarity;

  const _FullScreenGift({
    required this.event,
    required this.scale,
    required this.opacity,
    required this.particles,
    required this.rarity,
  });

  Color get _glow => rarity == GiftRarity.legendary
      ? const Color(0xFFFFD700)
      : const Color(0xFFAA00FF);

  @override
  Widget build(BuildContext context) {
    return Positioned.fill(
      child: FadeTransition(
        opacity: opacity,
        child: Stack(
          children: [
            // Blur + dim
            BackdropFilter(
              filter: ImageFilter.blur(sigmaX: 5, sigmaY: 5),
              child: Container(color: Colors.black.withValues(alpha: 0.55)),
            ),
            // Particle burst
            AnimatedBuilder(
              animation: particles,
              builder: (_, __) => CustomPaint(
                painter: _ParticlePainter(
                  progress: particles.value,
                  primaryColor: _glow,
                  rarity: rarity,
                ),
                size: Size.infinite,
              ),
            ),
            // Gift + info
            Center(
              child: ScaleTransition(
                scale: scale,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Glow halo
                    Container(
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        boxShadow: [
                          BoxShadow(
                            color: _glow.withValues(alpha: 0.55),
                            blurRadius: 60,
                            spreadRadius: 20,
                          ),
                        ],
                      ),
                      child: Text(event.gift.emoji,
                          style: const TextStyle(fontSize: 110)),
                    ),
                    const SizedBox(height: 20),
                    // Rarity badge
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 7),
                      decoration: BoxDecoration(
                        gradient: LinearGradient(
                          colors: rarity == GiftRarity.legendary
                              ? [const Color(0xFFFFD700), const Color(0xFFFF8C00)]
                              : [const Color(0xFFAA00FF), const Color(0xFF6200EA)],
                        ),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        rarity == GiftRarity.legendary ? '✦ LEGENDARY ✦' : '◆ EPIC ◆',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                          fontSize: 14,
                          letterSpacing: 2.5,
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    Text(
                      event.gift.name,
                      style: TextStyle(
                          color: _glow,
                          fontSize: 30,
                          fontWeight: FontWeight.bold),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '${event.senderName} sent${event.quantity > 1 ? ' ×${event.quantity}' : ' a'} ${event.gift.name}',
                      style: const TextStyle(color: Colors.white70, fontSize: 14),
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

// ── Particle system ───────────────────────────────────────────────────────────

class _ParticlePainter extends CustomPainter {
  final double progress;
  final Color primaryColor;
  final GiftRarity rarity;

  _ParticlePainter({
    required this.progress,
    required this.primaryColor,
    required this.rarity,
  });

  static const _colors = [
    Color(0xFFFFD700),
    Color(0xFFFF6B6B),
    Color(0xFF4FC3F7),
    Color(0xFFCE93D8),
    Colors.white,
  ];

  @override
  void paint(Canvas canvas, Size size) {
    final count = rarity == GiftRarity.legendary ? 90 : 55;
    final paint = Paint()..style = PaintingStyle.fill;
    final cx = size.width / 2;
    final cy = size.height * 0.55;

    for (int i = 0; i < count; i++) {
      final angle = (i / count) * 2 * math.pi;
      final speed = 150 + (i % 5) * 60.0;
      final delay = (i % 4) * 0.08;
      final t = ((progress - delay) / (1 - delay)).clamp(0.0, 1.0);
      if (t <= 0) continue;

      final r = speed * t;
      final gravity = 180 * t * t;
      final x = cx + r * math.cos(angle);
      final y = cy + r * math.sin(angle) + gravity;
      final size_ = (1 - t) * (rarity == GiftRarity.legendary ? 7 : 5) + 2;
      final alpha = (1.0 - t * 0.8).clamp(0.0, 1.0);

      paint.color = _colors[i % _colors.length].withValues(alpha: alpha);

      if (i % 5 == 0) {
        _star(canvas, Offset(x, y), size_, paint);
      } else if (i % 5 == 1) {
        canvas.drawRect(
          Rect.fromCenter(center: Offset(x, y), width: size_, height: size_),
          paint,
        );
      } else {
        canvas.drawCircle(Offset(x, y), size_ / 2, paint);
      }
    }
  }

  void _star(Canvas canvas, Offset c, double r, Paint p) {
    final path = Path();
    for (int i = 0; i < 5; i++) {
      final a = (i * 4 * math.pi / 5) - math.pi / 2;
      final x = c.dx + r * math.cos(a);
      final y = c.dy + r * math.sin(a);
      if (i == 0) path.moveTo(x, y); else path.lineTo(x, y);
    }
    path.close();
    canvas.drawPath(path, p);
  }

  @override
  bool shouldRepaint(_ParticlePainter old) => old.progress != progress;
}
