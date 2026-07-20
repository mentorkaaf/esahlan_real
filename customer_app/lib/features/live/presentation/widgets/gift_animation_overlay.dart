import 'package:confetti/confetti.dart';
import 'package:flutter/material.dart';
import '../../data/models/live_models.dart';

class GiftAnimationOverlay extends StatefulWidget {
  final GiftEvent event;

  const GiftAnimationOverlay({super.key, required this.event});

  @override
  State<GiftAnimationOverlay> createState() => _GiftAnimationOverlayState();
}

class _GiftAnimationOverlayState extends State<GiftAnimationOverlay>
    with SingleTickerProviderStateMixin {
  late AnimationController _slide;
  late Animation<Offset> _offsetAnim;
  late ConfettiController _confetti;

  @override
  void initState() {
    super.initState();

    _confetti = ConfettiController(duration: const Duration(seconds: 2))..play();

    _slide = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 400),
    );
    _offsetAnim = Tween<Offset>(
      begin: const Offset(-1, 0),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _slide, curve: Curves.easeOut));

    _slide.forward();
  }

  @override
  void dispose() {
    _slide.dispose();
    _confetti.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Positioned(
      left: 12,
      bottom: 120,
      child: SlideTransition(
        position: _offsetAnim,
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Confetti from top-left
            ConfettiWidget(
              confettiController: _confetti,
              blastDirectionality: BlastDirectionality.explosive,
              numberOfParticles: 12,
              maxBlastForce: 20,
              minBlastForce: 8,
              emissionFrequency: 0.05,
              colors: const [
                Colors.orange,
                Colors.red,
                Colors.yellow,
                Colors.pink,
                Colors.purple,
              ],
            ),
            // Gift card
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [Colors.black.withOpacity(0.8), Colors.orange.withOpacity(0.3)],
                ),
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: Colors.orange.withOpacity(0.5)),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    widget.event.gift.emoji,
                    style: const TextStyle(fontSize: 28),
                  ),
                  const SizedBox(width: 8),
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        widget.event.senderName,
                        style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                            fontSize: 13),
                      ),
                      Text(
                        'sent ${widget.event.quantity > 1 ? '×${widget.event.quantity} ' : ''}${widget.event.gift.name}',
                        style: const TextStyle(color: Colors.orange, fontSize: 11),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
