import 'package:flutter/material.dart';
import 'driver_colors.dart';

class DriverTheme {
  static ThemeData get dark => ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    scaffoldBackgroundColor: AppColors.dark.navy,
    extensions: const [AppColors.dark],
    colorScheme: ColorScheme.dark(
      primary: DC.orange,
      secondary: DC.orangeLight,
      surface: AppColors.dark.surface,
      error: DC.error,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: AppColors.dark.navyLight,
      foregroundColor: AppColors.dark.text,
      elevation: 0,
      centerTitle: true,
      titleTextStyle: TextStyle(color: AppColors.dark.text, fontSize: 17, fontWeight: FontWeight.w800),
    ),
    cardTheme: CardThemeData(
      color: AppColors.dark.card,
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
      fillColor: AppColors.dark.inputFill,
      hintStyle: TextStyle(color: AppColors.dark.textMuted, fontSize: 14),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: AppColors.dark.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: AppColors.dark.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: DC.orange, width: 2)),
    ),
    bottomNavigationBarTheme: BottomNavigationBarThemeData(
      backgroundColor: AppColors.dark.navyLight,
      selectedItemColor: DC.orange,
      unselectedItemColor: AppColors.dark.textMuted,
      type: BottomNavigationBarType.fixed,
      selectedLabelStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
      unselectedLabelStyle: const TextStyle(fontSize: 11),
    ),
    dividerColor: AppColors.dark.divider,
    textTheme: TextTheme(
      headlineLarge: TextStyle(color: AppColors.dark.text, fontWeight: FontWeight.w900),
      titleLarge: TextStyle(color: AppColors.dark.text, fontWeight: FontWeight.w800),
      bodyLarge: TextStyle(color: AppColors.dark.text),
      bodyMedium: TextStyle(color: AppColors.dark.textSec),
      labelSmall: TextStyle(color: AppColors.dark.textMuted),
    ),
  );

  static ThemeData get light => ThemeData(
    useMaterial3: true,
    brightness: Brightness.light,
    scaffoldBackgroundColor: AppColors.light.navy,
    extensions: const [AppColors.light],
    colorScheme: ColorScheme.light(
      primary: DC.orange,
      secondary: DC.orangeLight,
      surface: AppColors.light.surface,
      error: DC.error,
    ),
    appBarTheme: AppBarTheme(
      backgroundColor: AppColors.light.navyLight,
      foregroundColor: AppColors.light.text,
      elevation: 0,
      centerTitle: true,
      titleTextStyle: TextStyle(color: AppColors.light.text, fontSize: 17, fontWeight: FontWeight.w800),
    ),
    cardTheme: CardThemeData(
      color: AppColors.light.card,
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
      fillColor: AppColors.light.inputFill,
      hintStyle: TextStyle(color: AppColors.light.textMuted, fontSize: 14),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: AppColors.light.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide(color: AppColors.light.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: DC.orange, width: 2)),
    ),
    bottomNavigationBarTheme: BottomNavigationBarThemeData(
      backgroundColor: AppColors.light.navyLight,
      selectedItemColor: DC.orange,
      unselectedItemColor: AppColors.light.textMuted,
      type: BottomNavigationBarType.fixed,
      selectedLabelStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700),
      unselectedLabelStyle: const TextStyle(fontSize: 11),
    ),
    dividerColor: AppColors.light.divider,
    textTheme: TextTheme(
      headlineLarge: TextStyle(color: AppColors.light.text, fontWeight: FontWeight.w900),
      titleLarge: TextStyle(color: AppColors.light.text, fontWeight: FontWeight.w800),
      bodyLarge: TextStyle(color: AppColors.light.text),
      bodyMedium: TextStyle(color: AppColors.light.textSec),
      labelSmall: TextStyle(color: AppColors.light.textMuted),
    ),
  );
}
