import 'package:flutter/material.dart';
import 'driver_colors.dart';

class DriverTheme {
  static ThemeData get dark => ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    scaffoldBackgroundColor: DC.navy,
    colorScheme: const ColorScheme.dark(
      primary: DC.orange,
      secondary: DC.orangeLight,
      surface: DC.surface,
      error: DC.error,
    ),
    appBarTheme: const AppBarTheme(
      backgroundColor: DC.navyLight,
      foregroundColor: DC.text,
      elevation: 0,
      centerTitle: true,
      titleTextStyle: TextStyle(color: DC.text, fontSize: 17, fontWeight: FontWeight.w800),
    ),
    cardTheme: CardThemeData(
      color: DC.card,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      elevation: 0,
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: DC.orange,
        foregroundColor: Colors.white,
        minimumSize: const Size(double.infinity, 52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: DC.orange,
        side: const BorderSide(color: DC.orange),
        minimumSize: const Size(double.infinity, 48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: DC.inputFill,
      hintStyle: const TextStyle(color: DC.textMuted, fontSize: 14),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: DC.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: DC.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: DC.orange, width: 2)),
    ),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: DC.navyLight,
      selectedItemColor: DC.orange,
      unselectedItemColor: DC.textMuted,
      type: BottomNavigationBarType.fixed,
      selectedLabelStyle: TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
      unselectedLabelStyle: TextStyle(fontSize: 11),
    ),
    dividerColor: DC.divider,
    textTheme: const TextTheme(
      headlineLarge: TextStyle(color: DC.text, fontWeight: FontWeight.w900),
      titleLarge: TextStyle(color: DC.text, fontWeight: FontWeight.w800),
      bodyLarge: TextStyle(color: DC.text),
      bodyMedium: TextStyle(color: DC.textSec),
      labelSmall: TextStyle(color: DC.textMuted),
    ),
  );
}
