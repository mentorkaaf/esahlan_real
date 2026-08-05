import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../data/models/community_models.dart';
import '../providers/community_provider.dart';

class ESpaceAdCard extends ConsumerStatefulWidget {
  final ESpaceAd ad;
  const ESpaceAdCard({super.key, required this.ad});

  @override
  ConsumerState<ESpaceAdCard> createState() => _ESpaceAdCardState();
}

class _ESpaceAdCardState extends ConsumerState<ESpaceAdCard> {
  bool _dismissed = false;
  bool _pressed   = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) ref.read(communityRepoProvider).recordESpaceAdImpression(widget.ad.id);
    });
  }

  Color _hexColor(String hex) {
    final h = hex.replaceAll('#', '');
    return Color(int.parse('FF$h', radix: 16));
  }

  void _onTap() {
    ref.read(communityRepoProvider).recordESpaceAdClick(widget.ad.id);
    context.go(widget.ad.deepLink);
  }

  @override
  Widget build(BuildContext context) {
    if (_dismissed) return const SizedBox.shrink();

    final ad = widget.ad;
    final baseColor = _hexColor(ad.moduleColor);
    final darkColor = Color.lerp(baseColor, Colors.black, 0.25)!;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      child: AnimatedScale(
        scale: _pressed ? 0.97 : 1.0,
        duration: const Duration(milliseconds: 80),
        curve: Curves.easeOut,
        child: GestureDetector(
          onTapDown: (_) => setState(() => _pressed = true),
          onTapUp: (_) { setState(() => _pressed = false); _onTap(); },
          onTapCancel: () => setState(() => _pressed = false),
          child: ClipRRect(
              borderRadius: BorderRadius.circular(20),
              child: Stack(
                children: [
                  Container(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [baseColor, darkColor],
                      ),
                    ),
                    padding: const EdgeInsets.fromLTRB(20, 20, 20, 18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Container(
                              width: 36,
                              height: 36,
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.2),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: Center(
                                child: Text(ad.moduleEmoji, style: const TextStyle(fontSize: 18)),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'eSahlan Services',
                                  style: TextStyle(
                                    color: Colors.white.withValues(alpha: 0.65),
                                    fontSize: 9,
                                    fontWeight: FontWeight.w700,
                                    letterSpacing: 0.5,
                                  ),
                                ),
                                Text(
                                  ad.moduleLabel,
                                  style: const TextStyle(
                                    color: Colors.white,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ],
                            ),
                            const Spacer(),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.15),
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: const Text(
                                'Sponsored',
                                style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w600),
                              ),
                            ),
                            const SizedBox(width: 8),
                            GestureDetector(
                              onTap: () => setState(() => _dismissed = true),
                              child: Icon(Icons.close_rounded, color: Colors.white.withValues(alpha: 0.7), size: 18),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),
                        Text(
                          ad.title,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 20,
                            fontWeight: FontWeight.w900,
                            height: 1.2,
                          ),
                        ),
                        if (ad.subtitle != null && ad.subtitle!.isNotEmpty) ...[
                          const SizedBox(height: 6),
                          Text(
                            ad.subtitle!,
                            style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.8),
                              fontSize: 13,
                              height: 1.4,
                            ),
                          ),
                        ],
                        const SizedBox(height: 18),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(30),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Text(
                                ad.ctaText,
                                style: TextStyle(
                                  color: baseColor,
                                  fontSize: 13,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(width: 4),
                              Icon(Icons.arrow_forward_rounded, color: baseColor, size: 14),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                  // Decorative emoji watermark
                  Positioned(
                    right: -8,
                    top: 10,
                    child: Text(
                      ad.moduleEmoji,
                      style: TextStyle(fontSize: 90, color: Colors.white.withValues(alpha: 0.07)),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
    );
  }
}
