import 'package:flutter/material.dart';

/// eGrocery design tokens — extends the master AppColors palette.
class EGTheme {
  // Brand
  static const orange  = Color(0xFFFF8A00);
  static const navy    = Color(0xFF0C0148);
  static const green   = Color(0xFF22C55E);
  static const red     = Color(0xFFEF4444);
  static const purple  = Color(0xFF8B5CF6);

  // Surface
  static const bg      = Color(0xFFF4F5FA);
  static const card    = Color(0xFFFFFFFF);
  static const shimmer = Color(0xFFE8EDF5);

  // Text
  static const textDark = Color(0xFF1A1A2E);
  static const textGrey = Color(0xFF8A8A9A);

  // Radii
  static const rCard   = 16.0;
  static const rChip   = 24.0;
  static const rInput  = 12.0;
  static const rBtn    = 14.0;

  // Text styles
  static const sectionTitle = TextStyle(fontSize: 17, fontWeight: FontWeight.w800, color: textDark, letterSpacing: -0.3);
  static const productName  = TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: textDark, height: 1.3);
  static const priceMain    = TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: textDark);
  static const priceStruck  = TextStyle(fontSize: 12, fontWeight: FontWeight.w400, color: textGrey, decoration: TextDecoration.lineThrough);
  static const discountChip = TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: Colors.white);
  static const variantLabel = TextStyle(fontSize: 12, fontWeight: FontWeight.w600);
  static const caption      = TextStyle(fontSize: 11, fontWeight: FontWeight.w400, color: textGrey);
}
