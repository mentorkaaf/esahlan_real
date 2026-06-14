import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

// On Flutter Web the CDN (hcdn) caches /api/img/* as static files and strips
// CORS headers. /api/v1/* is treated as DYNAMIC so CORS headers pass through.
// Rewrite image URLs on web to use the v1 path and bypass CDN caching.
String fixImgUrl(String? url) {
  if (url == null || url.isEmpty) return '';
  if (!kIsWeb) return url;
  return url.replaceFirst('/api/img/', '/api/v1/img/');
}

String _fixUrl(String url) => fixImgUrl(url);

/// Cross-platform network image:
/// - Web: uses Image.network (<img> tag, no CORS restriction)
/// - Native: uses CachedNetworkImage (disk-cached, efficient)
class NetImage extends StatelessWidget {
  final String? url;
  final BoxFit fit;
  final double? width;
  final double? height;
  final Widget? placeholder;
  final Widget? errorWidget;
  final BorderRadius? borderRadius;

  const NetImage({
    super.key,
    required this.url,
    this.fit = BoxFit.cover,
    this.width,
    this.height,
    this.placeholder,
    this.errorWidget,
    this.borderRadius,
  });

  @override
  Widget build(BuildContext context) {
    final fallback = errorWidget ??
        Container(
          color: const Color(0xFFF0EEE8),
          child: const Icon(Icons.broken_image_outlined,
              color: Color(0xFFBBBBAA), size: 32),
        );

    if (url == null || url!.isEmpty) return _wrap(fallback);

    final resolvedUrl = _fixUrl(url!);

    Widget img;
    if (kIsWeb) {
      img = Image.network(
        resolvedUrl,
        fit: fit,
        width: width,
        height: height,
        loadingBuilder: (_, child, progress) =>
            progress == null ? child : (placeholder ?? _shimmer()),
        errorBuilder: (_, __, ___) => fallback,
      );
    } else {
      img = CachedNetworkImage(
        imageUrl: resolvedUrl,
        fit: fit,
        width: width,
        height: height,
        placeholder: (_, __) => placeholder ?? _shimmer(),
        errorWidget: (_, __, ___) => fallback,
      );
    }

    return _wrap(img);
  }

  Widget _wrap(Widget child) {
    if (borderRadius != null) {
      return ClipRRect(borderRadius: borderRadius!, child: child);
    }
    return child;
  }

  Widget _shimmer() => Container(color: const Color(0xFFEEEEEE));
}
