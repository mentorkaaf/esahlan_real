import 'dart:ui';
import 'package:flutter/material.dart';

enum BeautyFilter { none, beauty, warm, cool, vivid, vintage, rose, drama }

extension BeautyFilterExt on BeautyFilter {
  String get label {
    switch (this) {
      case BeautyFilter.none:    return 'Normal';
      case BeautyFilter.beauty:  return 'Beauty';
      case BeautyFilter.warm:    return 'Warm';
      case BeautyFilter.cool:    return 'Cool';
      case BeautyFilter.vivid:   return 'Vivid';
      case BeautyFilter.vintage: return 'Vintage';
      case BeautyFilter.rose:    return 'Rose';
      case BeautyFilter.drama:   return 'Drama';
    }
  }

  // Gradient colors shown in the filter thumbnail
  List<Color> get previewColors {
    switch (this) {
      case BeautyFilter.none:    return [const Color(0xFF888888), const Color(0xFF444444)];
      case BeautyFilter.beauty:  return [const Color(0xFFFFD6E0), const Color(0xFFFFB6C1)];
      case BeautyFilter.warm:    return [const Color(0xFFFF9500), const Color(0xFFFF6B00)];
      case BeautyFilter.cool:    return [const Color(0xFF4FC3F7), const Color(0xFF0288D1)];
      case BeautyFilter.vivid:   return [const Color(0xFFFF6EC7), const Color(0xFF7B2FF7)];
      case BeautyFilter.vintage: return [const Color(0xFFD4A96A), const Color(0xFF8B5E3C)];
      case BeautyFilter.rose:    return [const Color(0xFFFF80AB), const Color(0xFFE91E8C)];
      case BeautyFilter.drama:   return [const Color(0xFF424242), const Color(0xFF000000)];
    }
  }

  ColorFilter? get colorFilter {
    switch (this) {
      case BeautyFilter.none:
        return null;
      case BeautyFilter.beauty:
        // Soft brightness + reduce saturation slightly for smooth skin look
        return const ColorFilter.matrix([
          1.05, 0,    0,    0, 18,
          0,    1.05, 0,    0, 14,
          0,    0,    1.05, 0, 12,
          0,    0,    0,    1, 0,
        ]);
      case BeautyFilter.warm:
        // Boost red + warm golden tone
        return const ColorFilter.matrix([
          1.25, 0,    0,    0, 20,
          0,    1.08, 0,    0, 8,
          0,    0,    0.80, 0, -15,
          0,    0,    0,    1, 0,
        ]);
      case BeautyFilter.cool:
        // Boost blue, cyan, reduce warm
        return const ColorFilter.matrix([
          0.82, 0,    0,    0, -12,
          0,    1.02, 0,    0, 8,
          0,    0,    1.25, 0, 20,
          0,    0,    0,    1, 0,
        ]);
      case BeautyFilter.vivid:
        // High saturation punch
        return const ColorFilter.matrix([
          1.4,  -0.15, -0.15, 0, 5,
          -0.1,  1.4,  -0.1,  0, 5,
          -0.15,-0.15,  1.4,  0, 5,
          0,     0,     0,    1, 0,
        ]);
      case BeautyFilter.vintage:
        // Faded sepia warm tone
        return const ColorFilter.matrix([
          0.85, 0.15, 0.08, 0, 12,
          0.06, 0.82, 0.06, 0, 10,
          0.02, 0.04, 0.70, 0, 6,
          0,    0,    0,    1, 0,
        ]);
      case BeautyFilter.rose:
        // Pink/rose tint
        return const ColorFilter.matrix([
          1.15, 0.05, 0.05, 0, 18,
          0,    0.92, 0.08, 0, 8,
          0,    0,    0.88, 0, -5,
          0,    0,    0,    1, 0,
        ]);
      case BeautyFilter.drama:
        // High contrast dark moody
        return const ColorFilter.matrix([
          1.3,  -0.1, -0.1, 0, -20,
          -0.1,  1.2, -0.1, 0, -15,
          -0.1, -0.1,  1.2, 0, -15,
          0,     0,    0,   1, 0,
        ]);
    }
  }

  double get blurSigma => this == BeautyFilter.beauty ? 0.5 : 0.0;
}

class BeautyFilterWidget extends StatelessWidget {
  final BeautyFilter filter;
  final Widget child;

  const BeautyFilterWidget({super.key, required this.filter, required this.child});

  @override
  Widget build(BuildContext context) {
    Widget result = child;
    if (filter.blurSigma > 0) {
      result = ImageFiltered(
        imageFilter: ImageFilter.blur(sigmaX: filter.blurSigma, sigmaY: filter.blurSigma, tileMode: TileMode.clamp),
        child: result,
      );
    }
    if (filter.colorFilter != null) {
      result = ColorFiltered(colorFilter: filter.colorFilter!, child: result);
    }
    return result;
  }
}

// ── TikTok-style Filter Selector Strip ───────────────────────────────────────

class FilterSelectorStrip extends StatelessWidget {
  final BeautyFilter selected;
  final void Function(BeautyFilter) onSelect;
  final VoidCallback? onClose;

  const FilterSelectorStrip({
    super.key,
    required this.selected,
    required this.onSelect,
    this.onClose,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 100,
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.bottomCenter,
          end: Alignment.topCenter,
          colors: [Colors.black.withValues(alpha: 0.85), Colors.black.withValues(alpha: 0.0)],
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const SizedBox(height: 8),
          SizedBox(
            height: 88,
            child: ListView.builder(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              itemCount: BeautyFilter.values.length,
              itemBuilder: (_, i) {
                final f = BeautyFilter.values[i];
                final isSelected = f == selected;
                return GestureDetector(
                  onTap: () => onSelect(f),
                  child: Container(
                    width: 62,
                    margin: const EdgeInsets.only(right: 10),
                    child: Column(
                      children: [
                        AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          width: isSelected ? 54 : 50,
                          height: isSelected ? 54 : 50,
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              colors: f.previewColors,
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                            ),
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: isSelected ? Colors.white : Colors.white.withValues(alpha: 0.20),
                              width: isSelected ? 2.5 : 1.0,
                            ),
                            boxShadow: isSelected
                                ? [BoxShadow(color: f.previewColors.first.withValues(alpha: 0.5), blurRadius: 8, spreadRadius: 1)]
                                : null,
                          ),
                          child: Center(
                            child: Text(
                              f == BeautyFilter.none ? '⬜' : '',
                              style: const TextStyle(fontSize: 16),
                            ),
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(
                          f.label,
                          style: TextStyle(
                            color: isSelected ? Colors.white : Colors.white.withValues(alpha: 0.65),
                            fontSize: 10,
                            fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
