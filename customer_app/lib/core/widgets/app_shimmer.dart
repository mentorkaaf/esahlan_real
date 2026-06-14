import 'package:flutter/material.dart';
import 'package:shimmer/shimmer.dart';

class AppShimmer extends StatelessWidget {
  final Widget child;
  const AppShimmer({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return Shimmer.fromColors(
      baseColor: const Color(0xFFE5E7EB),
      highlightColor: const Color(0xFFF9FAFB),
      period: const Duration(milliseconds: 1200),
      child: child,
    );
  }
}

// ── Reusable shimmer shapes ────────────────────────────────────────────────────

class ShimmerBox extends StatelessWidget {
  final double width;
  final double height;
  final double radius;
  const ShimmerBox({super.key, required this.width, required this.height, this.radius = 8});

  @override
  Widget build(BuildContext context) => Container(
        width: width,
        height: height,
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(radius),
        ),
      );
}

class ShimmerCircle extends StatelessWidget {
  final double size;
  const ShimmerCircle({super.key, required this.size});

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
      );
}

// ── Card shimmer (for vendor/restaurant list items) ───────────────────────────

class ShimmerCard extends StatelessWidget {
  const ShimmerCard({super.key});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Row(children: [
          const ShimmerBox(width: 70, height: 70, radius: 10),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const ShimmerBox(width: double.infinity, height: 14),
              const SizedBox(height: 8),
              const ShimmerBox(width: 120, height: 11),
              const SizedBox(height: 8),
              Row(children: const [
                ShimmerBox(width: 60, height: 11),
                SizedBox(width: 8),
                ShimmerBox(width: 60, height: 11),
              ]),
            ]),
          ),
        ]),
      ),
    );
  }
}

// ── Grid shimmer (for service/module cards) ────────────────────────────────────

class ShimmerGrid extends StatelessWidget {
  final int count;
  final int crossAxisCount;
  const ShimmerGrid({super.key, this.count = 8, this.crossAxisCount = 4});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: GridView.builder(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
          crossAxisCount: crossAxisCount,
          childAspectRatio: 0.85,
          crossAxisSpacing: 8,
          mainAxisSpacing: 8,
        ),
        itemCount: count,
        itemBuilder: (_, __) => Column(
          mainAxisSize: MainAxisSize.min,
          children: const [
            ShimmerBox(width: 60, height: 60, radius: 16),
            SizedBox(height: 6),
            ShimmerBox(width: 44, height: 10),
          ],
        ),
      ),
    );
  }
}

// ── List shimmer (for feed/posts) ──────────────────────────────────────────────

class ShimmerPostList extends StatelessWidget {
  final int count;
  const ShimmerPostList({super.key, this.count = 3});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: Column(
        children: List.generate(count, (_) => _PostShimmer()),
      ),
    );
  }
}

class _PostShimmer extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      color: Colors.white,
      padding: const EdgeInsets.all(16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          const ShimmerCircle(size: 40),
          const SizedBox(width: 10),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: const [
            ShimmerBox(width: 120, height: 12),
            SizedBox(height: 6),
            ShimmerBox(width: 80, height: 10),
          ]),
        ]),
        const SizedBox(height: 12),
        const ShimmerBox(width: double.infinity, height: 12),
        const SizedBox(height: 6),
        const ShimmerBox(width: 200, height: 12),
        const SizedBox(height: 12),
        const ShimmerBox(width: double.infinity, height: 200, radius: 12),
      ]),
    );
  }
}

// ── Banner shimmer ─────────────────────────────────────────────────────────────

class ShimmerBanner extends StatelessWidget {
  const ShimmerBanner({super.key});

  @override
  Widget build(BuildContext context) {
    return AppShimmer(
      child: Container(
        height: 160,
        margin: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
        ),
      ),
    );
  }
}
