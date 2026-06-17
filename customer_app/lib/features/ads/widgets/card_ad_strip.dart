import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../models/ad_model.dart';
import '../providers/ads_provider.dart';
import '../services/ad_service.dart';
import '../../../core/widgets/network_image_widget.dart';

/// Horizontal scrollable row of promotional card ads.
/// Pass [module] to show ads targeted to that module + global.
class CardAdStrip extends ConsumerWidget {
  final String? module;
  final EdgeInsets margin;

  const CardAdStrip({
    super.key,
    this.module,
    this.margin = const EdgeInsets.fromLTRB(0, 0, 0, 16),
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final adsAsync = ref.watch(cardAdsProvider(module));

    return adsAsync.when(
      loading: () => const SizedBox.shrink(),
      error: (_, __) => const SizedBox.shrink(),
      data: (ads) {
        if (ads.isEmpty) return const SizedBox.shrink();
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
              child: Row(children: [
                Container(
                  width: 3, height: 16,
                  decoration: BoxDecoration(
                    color: const Color(0xFFFF8A00),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
                const SizedBox(width: 8),
                const Text('Promotions',
                  style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Color(0xFF07003B))),
              ]),
            ),
            SizedBox(
              height: 160,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: ads.length,
                itemBuilder: (_, i) => Padding(
                  padding: EdgeInsets.only(right: i < ads.length - 1 ? 12 : 0),
                  child: _PromoCard(ad: ads[i]),
                ),
              ),
            ),
            margin.bottom > 0 ? SizedBox(height: margin.bottom) : const SizedBox.shrink(),
          ],
        );
      },
    );
  }
}

class _PromoCard extends StatelessWidget {
  final AdModel ad;
  const _PromoCard({required this.ad});

  void _onTap(BuildContext context) {
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
      default:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: ad.actionType != 'none' ? () => _onTap(context) : null,
      child: Container(
        width: 200,
        height: 160,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(18),
          color: const Color(0xFF07003B),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.12), blurRadius: 12, offset: const Offset(0, 4)),
          ],
        ),
        clipBehavior: Clip.hardEdge,
        child: Stack(fit: StackFit.expand, children: [
          // Background image
          if (ad.imageUrl != null)
            NetImage(url: ad.imageUrl!, fit: BoxFit.cover, width: double.infinity, height: double.infinity)
          else
            Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: [const Color(0xFF07003B), ad.buttonColor.withOpacity(0.9)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
            ),

          // Dark gradient overlay
          Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                stops: [0.25, 1.0],
                colors: [Colors.transparent, Colors.black87],
              ),
            ),
          ),

          // "AD" chip top-right
          Positioned(
            top: 8, right: 8,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: Colors.black.withOpacity(0.5),
                borderRadius: BorderRadius.circular(4),
              ),
              child: const Text('AD',
                style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800, letterSpacing: 0.5)),
            ),
          ),

          // Content bottom
          Positioned(
            left: 12, right: 12, bottom: 12,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  ad.title,
                  style: const TextStyle(
                    color: Colors.white, fontSize: 13, fontWeight: FontWeight.w900,
                    shadows: [Shadow(blurRadius: 4, color: Colors.black38)],
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                if (ad.description != null && ad.description!.isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(ad.description!,
                    style: const TextStyle(color: Colors.white70, fontSize: 10),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis),
                ],
                if (ad.actionType != 'none') ...[
                  const SizedBox(height: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(
                      color: ad.buttonColor,
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(
                      ad.buttonText,
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800),
                    ),
                  ),
                ],
              ],
            ),
          ),
        ]),
      ),
    );
  }
}
