import 'dart:math' as math;
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import '../../core/theme/theme_x.dart';

/// Normalises a stored image URL.
/// - Full https:// URLs are kept as-is (with legacy /api/img/ fix).
/// - Relative storage paths (no scheme) are wrapped in the /api/v1/media proxy.
const _kBase = 'https://esahlan.com';

String fixImgUrl(String? url) {
  if (url == null || url.isEmpty) return '';
  // Already a full URL
  if (url.startsWith('http://') || url.startsWith('https://')) {
    return url.replaceFirst('/api/img/', '/api/v1/img/');
  }
  // Relative storage path → media proxy
  final path = url.startsWith('/') ? url.substring(1) : url;
  return '$_kBase/api/v1/media?f=${Uri.encodeComponent(path)}';
}

/// Append a width hint (?w=) so the backend returns a downscaled variant instead
/// of the full-resolution original. Only applied to our own /media proxy URLs.
String _withWidth(String url, int? widthPx) {
  if (widthPx == null || widthPx <= 0) return url;
  if (!url.contains('/api/v1/media')) return url;
  // Bucket to the nearest 100px so the CDN/disk cache is reused across slightly
  // different layout sizes.
  final bucket = (widthPx / 100).ceil() * 100;
  final sep = url.contains('?') ? '&' : '?';
  return '$url${sep}w=$bucket';
}

/// Cross-platform network image with skeleton placeholder, disk/HTTP caching and
/// automatic server-side downscaling sized to the widget's render box.
class NetImage extends StatelessWidget {
  final String? url;
  final BoxFit fit;
  final double? width;
  final double? height;
  final Widget? placeholder;
  final Widget? errorWidget;
  final BorderRadius? borderRadius;

  /// Explicit target width (logical px) to request from the server. When null the
  /// widget measures its own render box via LayoutBuilder.
  final int? targetWidth;

  const NetImage({
    super.key,
    required this.url,
    this.fit = BoxFit.cover,
    this.width,
    this.height,
    this.placeholder,
    this.errorWidget,
    this.borderRadius,
    this.targetWidth,
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

    final base = fixImgUrl(url!);
    final dpr = MediaQuery.maybeOf(context)?.devicePixelRatio ?? 2.0;

    return _wrap(
      LayoutBuilder(
        builder: (context, constraints) {
          // Decide the pixel width to request: explicit > finite layout width >
          // explicit `width` prop. Capped so a full-bleed banner never asks for
          // thousands of px.
          double logical = targetWidth?.toDouble() ?? 0;
          if (logical <= 0 && constraints.maxWidth.isFinite) {
            logical = constraints.maxWidth;
          }
          if (logical <= 0 && width != null && width!.isFinite) {
            logical = width!;
          }
          final px = logical > 0
              ? math.min(1600, (logical * dpr).ceil())
              : null;

          final resolved = _withWidth(base, px);
          final cacheW = px;

          return _buildImage(resolved, cacheW, fallback);
        },
      ),
    );
  }

  Widget _buildImage(String resolved, int? cacheW, Widget fallback) {
    if (kIsWeb) {
      return Image.network(
        resolved,
        fit: fit,
        width: width,
        height: height,
        cacheWidth: cacheW,
        gaplessPlayback: true,
        loadingBuilder: (_, child, progress) =>
            progress == null ? child : (placeholder ?? const _Shimmer()),
        errorBuilder: (_, __, ___) => fallback,
      );
    }
    return CachedNetworkImage(
      imageUrl: resolved,
      fit: fit,
      width: width,
      height: height,
      memCacheWidth: cacheW,
      fadeInDuration: const Duration(milliseconds: 200),
      placeholder: (_, __) => RepaintBoundary(child: placeholder ?? const _Shimmer()),
      errorWidget: (_, __, ___) => fallback,
    );
  }

  Widget _wrap(Widget child) {
    if (borderRadius != null) {
      return ClipRRect(borderRadius: borderRadius!, child: child);
    }
    return child;
  }
}

/// Cached circular avatar — uses the fast proxy/resize pipeline with a graceful
/// initial/icon fallback. Use everywhere instead of `NetworkImage` for avatars.
class CircleNetImage extends StatelessWidget {
  final String? url;
  final double size;
  final String? fallbackText;
  const CircleNetImage({
    super.key,
    required this.url,
    this.size = 40,
    this.fallbackText,
  });

  @override
  Widget build(BuildContext context) {
    final hasUrl = url != null && url!.isNotEmpty;
    return ClipOval(
      child: SizedBox(
        width: size,
        height: size,
        child: hasUrl
            ? NetImage(
                url: url,
                width: size,
                height: size,
                fit: BoxFit.cover,
                targetWidth: (size * 2).round(),
              )
            : Container(
                color: const Color(0xFFE4E6EB),
                alignment: Alignment.center,
                child: (fallbackText != null && fallbackText!.trim().isNotEmpty)
                    ? Text(
                        fallbackText!.trim()[0].toUpperCase(),
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          color: const Color(0xFF65676B),
                          fontSize: size * 0.4,
                        ),
                      )
                    : Icon(Icons.person,
                        color: const Color(0xFF9CA3AF), size: size * 0.55),
              ),
      ),
    );
  }
}

/// Lightweight animated shimmer placeholder.
class _Shimmer extends StatefulWidget {
  const _Shimmer();

  @override
  State<_Shimmer> createState() => _ShimmerState();
}

class _ShimmerState extends State<_Shimmer> with SingleTickerProviderStateMixin {
  late final AnimationController _c =
      AnimationController(vsync: this, duration: const Duration(milliseconds: 1100))
        ..repeat();

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _c,
      builder: (_, __) {
        final t = _c.value;
        return DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment(-1 - 2 * t, 0),
              end: Alignment(1 - 2 * t, 0),
              colors: const [
                Color(0xFFEDEDF2),
                Color(0xFFF6F6FA),
                Color(0xFFEDEDF2),
              ],
              stops: const [0.1, 0.5, 0.9],
            ),
          ),
        );
      },
    );
  }
}
