import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../models/ad_model.dart';
import '../providers/ads_provider.dart';
import '../services/ad_service.dart';
import '../../../core/widgets/network_image_widget.dart';

/// Horizontal banner ad slider for home or module pages.
/// Pass [module] to show ads targeted to that module (plus global ads).
/// Pass null for the home screen (global ads only).
class BannerAdStrip extends ConsumerStatefulWidget {
  final String? module;
  final double height;
  final EdgeInsets margin;

  const BannerAdStrip({
    super.key,
    this.module,
    this.height = 140.0,
    this.margin = const EdgeInsets.fromLTRB(16, 0, 16, 12),
  });

  @override
  ConsumerState<BannerAdStrip> createState() => _BannerAdStripState();
}

class _BannerAdStripState extends ConsumerState<BannerAdStrip> {
  final _ctrl = PageController();
  Timer? _timer;
  int _page = 0;

  @override
  void dispose() {
    _timer?.cancel();
    _ctrl.dispose();
    super.dispose();
  }

  void _startAutoScroll(int count) {
    if (count <= 1) return;
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 5), (_) {
      if (!mounted) return;
      _page = (_page + 1) % count;
      _ctrl.animateToPage(_page,
          duration: const Duration(milliseconds: 400), curve: Curves.easeInOut);
    });
  }

  void _onAdTap(AdModel ad) {
    AdService.instance.trackClick(ad.id);
    final val = ad.actionValue ?? '';
    switch (ad.actionType) {
      case 'module':
        if (val.isNotEmpty) context.push('/$val');
        break;
      case 'vendor':
        final id = int.tryParse(val);
        if (id != null) context.push('/vendor/$id');
        break;
      case 'product':
        final id = int.tryParse(val);
        if (id != null) context.push('/eshop/products/$id');
        break;
      case 'url':
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    final adsAsync = ref.watch(bannerAdsProvider(widget.module));

    return adsAsync.when(
      loading: () => const SizedBox.shrink(),
      error: (_, __) => const SizedBox.shrink(),
      data: (ads) {
        if (ads.isEmpty) return const SizedBox.shrink();
        _startAutoScroll(ads.length);

        return Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              margin: widget.margin,
              height: widget.height,
              child: PageView.builder(
                controller: _ctrl,
                itemCount: ads.length,
                onPageChanged: (p) {
                  setState(() => _page = p);
                  AdService.instance.trackImpression(ads[p].id);
                },
                itemBuilder: (_, i) => _BannerAdCard(
                  ad: ads[i],
                  onTap: () => _onAdTap(ads[i]),
                ),
              ),
            ),
            if (ads.length > 1) ...[
              const SizedBox(height: 8),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List.generate(ads.length, (i) => AnimatedContainer(
                  duration: const Duration(milliseconds: 300),
                  width: _page == i ? 18 : 6,
                  height: 6,
                  margin: const EdgeInsets.symmetric(horizontal: 2),
                  decoration: BoxDecoration(
                    color: _page == i ? const Color(0xFFFF8A00) : const Color(0xFFD1D5DB),
                    borderRadius: BorderRadius.circular(3),
                  ),
                )),
              ),
            ],
          ],
        );
      },
    );
  }
}

class _BannerAdCard extends StatelessWidget {
  final AdModel ad;
  final VoidCallback onTap;

  const _BannerAdCard({required this.ad, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: ad.actionType != 'none' ? onTap : null,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          color: const Color(0xFFF1F5F9),
        ),
        clipBehavior: Clip.hardEdge,
        child: Stack(fit: StackFit.expand, children: [
          // Background image
          if (ad.imageUrl != null)
            NetImage(
              url: ad.imageUrl!,
              fit: BoxFit.cover,
              width: double.infinity,
              height: double.infinity,
            )
          else
            Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [const Color(0xFF07003B), ad.buttonColor.withOpacity(0.8)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
            ),

          // Gradient overlay
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                stops: [0.3, 1.0],
                colors: [Colors.transparent, Colors.black54],
              ),
            ),
          ),

          // Content
          Positioned(
            left: 16, bottom: 14, right: 60,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  ad.title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    shadows: [Shadow(blurRadius: 6, color: Colors.black38)],
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
                if (ad.description != null && ad.description!.isNotEmpty)
                  Text(
                    ad.description!,
                    style: const TextStyle(color: Colors.white70, fontSize: 11),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
              ],
            ),
          ),

          // CTA badge
          if (ad.actionType != 'none')
            Positioned(
              right: 12, bottom: 12,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                decoration: BoxDecoration(
                  color: ad.buttonColor,
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: [BoxShadow(color: ad.buttonColor.withOpacity(0.4), blurRadius: 8, offset: const Offset(0, 3))],
                ),
                child: Text(
                  ad.buttonText,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),

          // "AD" label
          Positioned(
            top: 8, right: 8,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: Colors.black.withOpacity(0.45),
                borderRadius: BorderRadius.circular(4),
              ),
              child: const Text('AD', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: 0.5)),
            ),
          ),
        ]),
      ),
    );
  }
}
