import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

class AppColors {
  static const primary   = Color(0xFFFF8A00);
  static const secondary = Color(0xFF07003B);
  static const surface   = Color(0xFFF8F9FE);
  static const card      = Color(0xFFFFFFFF);
  static const error     = Color(0xFFE53935);
  static const success   = Color(0xFF2E7D32);
  static const warning   = Color(0xFFF57C00);
  static const textDark  = Color(0xFF1A1A2E);
  static const textGrey  = Color(0xFF8A8A9A);
  static const divider   = Color(0xFFEEEEEE);
  static const shimmer    = Color(0xFFE0E0E0);
  static const textLight  = Color(0xFFBBBBCC);
  static const background = Color(0xFFF4F5FA);
}

class AppTheme {
  static ThemeData get light => ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.light(
      primary:    AppColors.primary,
      secondary:  AppColors.secondary,
      surface:    AppColors.surface,
      error:      AppColors.error,
    ),
    scaffoldBackgroundColor: AppColors.surface,
    fontFamily: 'Cairo',
    appBarTheme: const AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: AppColors.secondary,
      elevation: 0,
      scrolledUnderElevation: 1,
      systemOverlayStyle: SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        statusBarBrightness: Brightness.light,
      ),
      titleTextStyle: TextStyle(
        color: AppColors.secondary,
        fontSize: 18,
        fontWeight: FontWeight.w700,
        fontFamily: 'Cairo',
      ),
      iconTheme: IconThemeData(color: AppColors.secondary),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        minimumSize: const Size(double.infinity, 52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w700,
          fontFamily: 'Cairo',
        ),
        elevation: 0,
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: AppColors.primary,
        side: const BorderSide(color: AppColors.primary),
        minimumSize: const Size(double.infinity, 52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.divider),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.divider),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.primary, width: 2),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: AppColors.error),
      ),
      hintStyle: const TextStyle(color: AppColors.textGrey, fontSize: 14),
      labelStyle: const TextStyle(color: AppColors.textGrey),
    ),
    cardTheme: CardThemeData(
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: AppColors.divider, width: 0.5),
      ),
      margin: EdgeInsets.zero,
    ),
    chipTheme: ChipThemeData(
      backgroundColor: AppColors.surface,
      selectedColor: AppColors.primary.withOpacity(0.15),
      labelStyle: const TextStyle(fontSize: 12, fontFamily: 'Cairo'),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      side: const BorderSide(color: AppColors.divider),
    ),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: Colors.white,
      selectedItemColor: AppColors.primary,
      unselectedItemColor: AppColors.textGrey,
      type: BottomNavigationBarType.fixed,
      showSelectedLabels: true,
      showUnselectedLabels: true,
      selectedLabelStyle: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, fontFamily: 'Cairo'),
      unselectedLabelStyle: TextStyle(fontSize: 11, fontFamily: 'Cairo'),
      elevation: 8,
    ),
    dividerTheme: const DividerThemeData(color: AppColors.divider, thickness: 0.5),
    textTheme: const TextTheme(
      displayLarge:  TextStyle(fontSize: 32, fontWeight: FontWeight.w800, color: AppColors.textDark),
      headlineLarge: TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: AppColors.textDark),
      headlineMedium:TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: AppColors.textDark),
      titleLarge:    TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: AppColors.textDark),
      titleMedium:   TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: AppColors.textDark),
      bodyLarge:     TextStyle(fontSize: 15, fontWeight: FontWeight.w400, color: AppColors.textDark),
      bodyMedium:    TextStyle(fontSize: 14, fontWeight: FontWeight.w400, color: AppColors.textDark),
      bodySmall:     TextStyle(fontSize: 12, fontWeight: FontWeight.w400, color: AppColors.textGrey),
      labelLarge:    TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: AppColors.textDark),
    ),
  );

  static ThemeData get dark => ThemeData(
    useMaterial3: true,
    colorScheme: const ColorScheme.dark(
      primary:     Color(0xFFFF8A00),
      secondary:   Color(0xFFFFFFFF),
      surface:     Color(0xFF1A1B2E),
      error:       Color(0xFFEF5350),
      onPrimary:   Colors.white,
      onSecondary: Color(0xFF0F1120),
      onSurface:   Colors.white,
      onError:     Colors.white,
    ),
    scaffoldBackgroundColor: const Color(0xFF0F1120),
    fontFamily: 'Cairo',
    appBarTheme: const AppBarTheme(
      backgroundColor: Color(0xFF1A1B2E),
      foregroundColor: Colors.white,
      elevation: 0,
      scrolledUnderElevation: 1,
      systemOverlayStyle: SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      titleTextStyle: TextStyle(
        color: Colors.white,
        fontSize: 18,
        fontWeight: FontWeight.w700,
        fontFamily: 'Cairo',
      ),
      iconTheme: IconThemeData(color: Colors.white),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: Color(0xFFFF8A00),
        foregroundColor: Colors.white,
        minimumSize: const Size(double.infinity, 52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        textStyle: const TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w700,
          fontFamily: 'Cairo',
        ),
        elevation: 0,
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: const Color(0xFFFF8A00),
        side: const BorderSide(color: Color(0xFFFF8A00)),
        minimumSize: const Size(double.infinity, 52),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: const Color(0xFF252640),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFF3A3B58)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFF3A3B58)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFFF8A00), width: 2),
      ),
      errorBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: Color(0xFFEF5350)),
      ),
      hintStyle: const TextStyle(color: Color(0xFF6A6A8A), fontSize: 14),
      labelStyle: const TextStyle(color: Color(0xFF6A6A8A)),
    ),
    cardTheme: CardThemeData(
      color: const Color(0xFF1F2038),
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: Color(0xFF2A2B48), width: 0.5),
      ),
      margin: EdgeInsets.zero,
    ),
    chipTheme: ChipThemeData(
      backgroundColor: const Color(0xFF252640),
      selectedColor: const Color(0xFFFF8A00).withOpacity(0.25),
      labelStyle: const TextStyle(fontSize: 12, fontFamily: 'Cairo', color: Colors.white),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      side: const BorderSide(color: Color(0xFF3A3B58)),
    ),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      backgroundColor: Color(0xFF1A1B2E),
      selectedItemColor: Color(0xFFFF8A00),
      unselectedItemColor: Color(0xFF6A6A8A),
      type: BottomNavigationBarType.fixed,
      showSelectedLabels: true,
      showUnselectedLabels: true,
      selectedLabelStyle: TextStyle(fontSize: 11, fontWeight: FontWeight.w600, fontFamily: 'Cairo'),
      unselectedLabelStyle: TextStyle(fontSize: 11, fontFamily: 'Cairo'),
      elevation: 8,
    ),
    dividerTheme: const DividerThemeData(color: Color(0xFF2A2B48), thickness: 0.5),
    textTheme: const TextTheme(
      displayLarge:   TextStyle(fontSize: 32, fontWeight: FontWeight.w800, color: Colors.white),
      headlineLarge:  TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: Colors.white),
      headlineMedium: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: Colors.white),
      titleLarge:     TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: Colors.white),
      titleMedium:    TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: Colors.white),
      bodyLarge:      TextStyle(fontSize: 15, fontWeight: FontWeight.w400, color: Color(0xFFDDDDF0)),
      bodyMedium:     TextStyle(fontSize: 14, fontWeight: FontWeight.w400, color: Color(0xFFDDDDF0)),
      bodySmall:      TextStyle(fontSize: 12, fontWeight: FontWeight.w400, color: Color(0xFF6A6A8A)),
      labelLarge:     TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Colors.white),
    ),
  );
}
