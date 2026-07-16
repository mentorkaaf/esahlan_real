import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';

class PodcastCover extends StatelessWidget {
  final String? url;
  final double width;
  final double height;
  final double radius;

  const PodcastCover({super.key, this.url, required this.width, required this.height, this.radius = 0});

  @override
  Widget build(BuildContext context) {
    if (url == null || url!.isEmpty) return _placeholder();
    return CachedNetworkImage(
      imageUrl: url!,
      width: width, height: height,
      fit: BoxFit.cover,
      placeholder: (_, __) => _placeholder(),
      errorWidget: (_, __, ___) => _placeholder(),
    );
  }

  Widget _placeholder() => Container(
    width: width, height: height,
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [Color(0xFF07003B), Color(0xFF140465)],
        begin: Alignment.topLeft, end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(radius),
    ),
    child: const Icon(Icons.podcasts_rounded, color: Color(0xFFFF8A00), size: 28),
  );
}
