import 'dart:ui';
import 'package:flutter/material.dart';

enum BeautyFilter { none, beauty, warm, cool, vivid, vintage }

extension BeautyFilterExt on BeautyFilter {
  String get label {
    switch (this) {
      case BeautyFilter.none:    return 'Normal';
      case BeautyFilter.beauty:  return 'Beauty';
      case BeautyFilter.warm:    return 'Warm';
      case BeautyFilter.cool:    return 'Cool';
      case BeautyFilter.vivid:   return 'Vivid';
      case BeautyFilter.vintage: return 'Vintage';
    }
  }

  String get emoji {
    switch (this) {
      case BeautyFilter.none:    return '⬜';
      case BeautyFilter.beauty:  return '✨';
      case BeautyFilter.warm:    return '🌅';
      case BeautyFilter.cool:    return '❄️';
      case BeautyFilter.vivid:   return '🌈';
      case BeautyFilter.vintage: return '🎞️';
    }
  }

  ColorFilter? get colorFilter {
    switch (this) {
      case BeautyFilter.none:
        return null;
      case BeautyFilter.beauty:
        // Slight brightness boost + low contrast for smooth skin look
        return const ColorFilter.matrix([
          1.1, 0,   0,   0, 10,
          0,   1.1, 0,   0, 10,
          0,   0,   1.1, 0, 10,
          0,   0,   0,   1, 0,
        ]);
      case BeautyFilter.warm:
        // Boost red, reduce blue
        return const ColorFilter.matrix([
          1.2, 0,   0,   0, 15,
          0,   1.05, 0,  0, 5,
          0,   0,   0.85, 0, -10,
          0,   0,   0,   1, 0,
        ]);
      case BeautyFilter.cool:
        // Boost blue, reduce red
        return const ColorFilter.matrix([
          0.85, 0,   0,   0, -10,
          0,    1.0, 0,   0, 5,
          0,    0,   1.2, 0, 15,
          0,    0,   0,   1, 0,
        ]);
      case BeautyFilter.vivid:
        // High saturation
        return const ColorFilter.matrix([
          1.3, -0.1, -0.1, 0, 0,
          -0.1, 1.3, -0.1, 0, 0,
          -0.1, -0.1, 1.3, 0, 0,
          0,    0,    0,   1, 0,
        ]);
      case BeautyFilter.vintage:
        // Sepia-like warm faded look
        return const ColorFilter.matrix([
          0.9,  0.1,  0.05, 0, 10,
          0.05, 0.85, 0.05, 0, 8,
          0.02, 0.02, 0.75, 0, 5,
          0,    0,    0,    1, 0,
        ]);
    }
  }

  double get blurSigma {
    // Beauty filter applies slight blur for skin smoothing
    return this == BeautyFilter.beauty ? 0.6 : 0.0;
  }
}

/// Wraps any child widget with the selected beauty filter
class BeautyFilterWidget extends StatelessWidget {
  final BeautyFilter filter;
  final Widget child;

  const BeautyFilterWidget({
    super.key,
    required this.filter,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    Widget result = child;

    // Apply blur for beauty filter
    if (filter.blurSigma > 0) {
      result = ImageFiltered(
        imageFilter: ImageFilter.blur(
          sigmaX: filter.blurSigma,
          sigmaY: filter.blurSigma,
          tileMode: TileMode.clamp,
        ),
        child: result,
      );
    }

    // Apply color matrix
    if (filter.colorFilter != null) {
      result = ColorFiltered(colorFilter: filter.colorFilter!, child: result);
    }

    return result;
  }
}

// ── Filter Selector Strip ─────────────────────────────────────────────────────

class FilterSelectorStrip extends StatelessWidget {
  final BeautyFilter selected;
  final void Function(BeautyFilter) onSelect;

  const FilterSelectorStrip({
    super.key,
    required this.selected,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 72,
      color: Colors.black54,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        children: BeautyFilter.values.map((f) {
          final isSelected = f == selected;
          return GestureDetector(
            onTap: () => onSelect(f),
            child: Container(
              width: 56,
              margin: const EdgeInsets.symmetric(horizontal: 4),
              decoration: BoxDecoration(
                color: isSelected
                    ? Colors.orange.withValues(alpha: 0.3)
                    : Colors.white.withValues(alpha: 0.08),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                  color: isSelected ? Colors.orange : Colors.white12,
                ),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(f.emoji, style: const TextStyle(fontSize: 16)),
                  const SizedBox(height: 2),
                  Text(
                    f.label,
                    style: TextStyle(
                      color: isSelected ? Colors.orange : Colors.white54,
                      fontSize: 9,
                      fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                ],
              ),
            ),
          );
        }).toList(),
      ),
    );
  }
}
