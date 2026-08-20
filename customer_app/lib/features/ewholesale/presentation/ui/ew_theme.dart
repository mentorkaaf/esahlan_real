import 'package:flutter/material.dart';

/// eWholesale Design System tokens
class EwTheme {
  EwTheme._();

  // ── Brand Palette ──────────────────────────────────────────────────────────
  static const navy   = Color(0xFF1B1444);
  static const orange = Color(0xFFF7941D);
  static const green  = Color(0xFF16A34A);
  static const red    = Color(0xFFDC2626);
  static const amber  = Color(0xFFD97706);

  // ── Verification badge colours ─────────────────────────────────────────────
  static const badgeGold        = Color(0xFFB45309);
  static const badgeBlue        = Color(0xFF1D4ED8);
  static const badgeGoldSurface = Color(0xFFFEF3C7);
  static const badgeBlueSurface = Color(0xFFDBEAFE);
  static const badgeGraySurface = Color(0xFFF3F4F6);
  static const badgeGray        = Color(0xFF6B7280);

  // ── Surface ────────────────────────────────────────────────────────────────
  static const bg            = Color(0xFFF9FAFB);
  static const surface       = Colors.white;
  static const border        = Color(0xFFE5E7EB);
  static const textPrimary   = Color(0xFF111827);
  static const textSecondary = Color(0xFF6B7280);
  static const textMuted     = Color(0xFF9CA3AF);

  // ── Typography ──────────────────────────────────────────────────────────────
  static const TextStyle heading1 = TextStyle(
    fontSize: 22, fontWeight: FontWeight.w700, color: navy, height: 1.25,
  );
  static const TextStyle heading2 = TextStyle(
    fontSize: 17, fontWeight: FontWeight.w700, color: navy, height: 1.3,
  );
  static const TextStyle heading3 = TextStyle(
    fontSize: 14, fontWeight: FontWeight.w600, color: navy, height: 1.4,
  );
  static const TextStyle body = TextStyle(
    fontSize: 14, fontWeight: FontWeight.w400, color: textPrimary, height: 1.5,
  );
  static const TextStyle bodySmall = TextStyle(
    fontSize: 12, fontWeight: FontWeight.w400, color: textSecondary, height: 1.4,
  );
  static const TextStyle label = TextStyle(
    fontSize: 11, fontWeight: FontWeight.w600, color: textSecondary,
    letterSpacing: 0.4, height: 1.2,
  );
  static const TextStyle price = TextStyle(
    fontSize: 16, fontWeight: FontWeight.w700, color: navy,
    fontFeatures: [FontFeature.tabularFigures()],
  );
  static const TextStyle priceSmall = TextStyle(
    fontSize: 13, fontWeight: FontWeight.w600, color: navy,
    fontFeatures: [FontFeature.tabularFigures()],
  );

  // ── Spacing ────────────────────────────────────────────────────────────────
  static const double spacing4  = 4;
  static const double spacing8  = 8;
  static const double spacing12 = 12;
  static const double spacing16 = 16;
  static const double spacing20 = 20;
  static const double spacing24 = 24;

  // ── Radius ─────────────────────────────────────────────────────────────────
  static const radius4  = BorderRadius.all(Radius.circular(4));
  static const radius8  = BorderRadius.all(Radius.circular(8));
  static const radius12 = BorderRadius.all(Radius.circular(12));
  static const radius16 = BorderRadius.all(Radius.circular(16));

  // ── Shadows ────────────────────────────────────────────────────────────────
  static final cardShadow = [
    BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 8, offset: const Offset(0, 2)),
  ];

  // ── Buttons ────────────────────────────────────────────────────────────────
  static final primaryButton = ElevatedButton.styleFrom(
    backgroundColor:   orange,
    foregroundColor:   Colors.white,
    elevation:         0,
    minimumSize:       const Size(double.infinity, 48),
    shape:             const RoundedRectangleBorder(borderRadius: radius8),
    textStyle:         const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
  );

  static final secondaryButton = OutlinedButton.styleFrom(
    foregroundColor:   navy,
    side:              const BorderSide(color: navy, width: 1.5),
    minimumSize:       const Size(double.infinity, 48),
    shape:             const RoundedRectangleBorder(borderRadius: radius8),
    textStyle:         const TextStyle(fontSize: 15, fontWeight: FontWeight.w600),
  );

  // ── Format helpers ──────────────────────────────────────────────────────────
  static String formatPrice(double v) => '\$${v.toStringAsFixed(2)}';
  static String formatQty(double q, String unit) {
    final s = q == q.truncate() ? q.toInt().toString() : q.toStringAsFixed(1);
    return '$s $unit';
  }
}
