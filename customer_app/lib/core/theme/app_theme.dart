import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'app_color_tokens.dart';

// ── Brand constants (never change between themes) ─────────────────────────────
class AppColors {
  static const primary   = Color(0xFFFF8A00);
  static const secondary = Color(0xFF07003B); // use context.colors.navyText for theme-aware
  static const surface   = Color(0xFFF8F9FE);
  static const card      = Color(0xFFFFFFFF);
  static const error     = Color(0xFFE53935);
  static const success   = Color(0xFF2E7D32);
  static const warning   = Color(0xFFF57C00);
  static const textDark  = Color(0xFF1A1A2E);
  static const textGrey  = Color(0xFF8A8A9A);
  static const divider   = Color(0xFFEEEEEE);
  static const shimmer   = Color(0xFFE0E0E0);
  static const textLight = Color(0xFFBBBBCC);
  static const background= Color(0xFFF4F5FA);
}

// ─────────────────────────────────────────────────────────────────────────────

class AppTheme {
  // ── Light ──────────────────────────────────────────────────────────────────
  static ThemeData get light => _build(
    cs: ColorScheme.light(
      primary:         AppColors.primary,
      secondary:       AppColors.secondary,
      surface:         AppColors.surface,
      error:           AppColors.error,
      onPrimary:       Colors.white,
      onSecondary:     Colors.white,
      onSurface:       AppColors.textDark,
      onError:         Colors.white,
      surfaceContainerHighest: AppColors.background,
    ),
    scaffoldBg:  AppColors.background,
    appBarBg:    Colors.white,
    appBarFg:    AppColors.secondary,
    cardBg:      Colors.white,
    dialogBg:    Colors.white,
    sheetBg:     Colors.white,
    navBg:       Colors.white,
    inputFill:   Colors.white,
    divColor:    AppColors.divider,
    tokens:      AppColorTokens.light,
    brightness:  Brightness.dark,   // status bar icon brightness (dark icons on light bg)
    statusBrightness: Brightness.light,
  );

  // ── Dark ───────────────────────────────────────────────────────────────────
  static ThemeData get dark => _build(
    cs: const ColorScheme.dark(
      primary:         Color(0xFFFF8A00),
      secondary:       Color(0xFFE0E0FF),
      surface:         Color(0xFF1A1B2E),
      error:           Color(0xFFF87171),
      onPrimary:       Colors.white,
      onSecondary:     Color(0xFF0F1120),
      onSurface:       Color(0xFFDDDDF0),
      onError:         Colors.white,
      surfaceContainerHighest: Color(0xFF252640),
    ),
    scaffoldBg:  const Color(0xFF0F1120),
    appBarBg:    const Color(0xFF1A1B2E),
    appBarFg:    Colors.white,
    cardBg:      const Color(0xFF1F2038),
    dialogBg:    const Color(0xFF252640),
    sheetBg:     const Color(0xFF1F2038),
    navBg:       const Color(0xFF1A1B2E),
    inputFill:   const Color(0xFF252640),
    divColor:    const Color(0xFF2A2B48),
    tokens:      AppColorTokens.dark,
    brightness:  Brightness.light,   // light icons on dark bg
    statusBrightness: Brightness.dark,
  );

  // ── Builder ────────────────────────────────────────────────────────────────
  static ThemeData _build({
    required ColorScheme cs,
    required Color scaffoldBg,
    required Color appBarBg,
    required Color appBarFg,
    required Color cardBg,
    required Color dialogBg,
    required Color sheetBg,
    required Color navBg,
    required Color inputFill,
    required Color divColor,
    required AppColorTokens tokens,
    required Brightness brightness,        // status bar icon brightness
    required Brightness statusBrightness,  // status bar bg brightness (iOS)
  }) {
    final onSurface = cs.onSurface;
    const primary   = Color(0xFFFF8A00);
    const r14       = BorderRadius.all(Radius.circular(14));
    const r12       = BorderRadius.all(Radius.circular(12));
    const r20       = BorderRadius.all(Radius.circular(20));
    const r16       = BorderRadius.all(Radius.circular(16));

    return ThemeData(
      useMaterial3: true,
      colorScheme: cs,
      brightness: cs.brightness,
      scaffoldBackgroundColor: scaffoldBg,
      fontFamily: 'Cairo',
      extensions: [tokens],

      // ── AppBar ────────────────────────────────────────────────────
      appBarTheme: AppBarTheme(
        backgroundColor: appBarBg,
        foregroundColor: appBarFg,
        elevation: 0,
        scrolledUnderElevation: 1,
        centerTitle: false,
        systemOverlayStyle: SystemUiOverlayStyle(
          statusBarColor: Colors.transparent,
          statusBarIconBrightness: brightness,
          statusBarBrightness: statusBrightness,
        ),
        titleTextStyle: TextStyle(
          color: appBarFg,
          fontSize: 18,
          fontWeight: FontWeight.w700,
          fontFamily: 'Cairo',
        ),
        iconTheme: IconThemeData(color: appBarFg),
        actionsIconTheme: IconThemeData(color: appBarFg),
      ),

      // ── Buttons ───────────────────────────────────────────────────
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          minimumSize: const Size(double.infinity, 52),
          shape: const RoundedRectangleBorder(borderRadius: r14),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700, fontFamily: 'Cairo'),
          elevation: 0,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: primary,
          side: const BorderSide(color: primary),
          minimumSize: const Size(double.infinity, 52),
          shape: const RoundedRectangleBorder(borderRadius: r14),
          textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, fontFamily: 'Cairo'),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: primary,
          textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, fontFamily: 'Cairo'),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: Colors.white,
          minimumSize: const Size(double.infinity, 52),
          shape: const RoundedRectangleBorder(borderRadius: r14),
        ),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: primary,
        foregroundColor: Colors.white,
        elevation: 4,
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(foregroundColor: onSurface),
      ),

      // ── Input ─────────────────────────────────────────────────────
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: inputFill,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        border:         OutlineInputBorder(borderRadius: r12, borderSide: BorderSide(color: divColor)),
        enabledBorder:  OutlineInputBorder(borderRadius: r12, borderSide: BorderSide(color: divColor)),
        focusedBorder:  const OutlineInputBorder(borderRadius: r12, borderSide: BorderSide(color: primary, width: 2)),
        errorBorder:    OutlineInputBorder(borderRadius: r12, borderSide: BorderSide(color: cs.error)),
        focusedErrorBorder: OutlineInputBorder(borderRadius: r12, borderSide: BorderSide(color: cs.error, width: 2)),
        hintStyle: TextStyle(color: tokens.subtleText, fontSize: 14, fontFamily: 'Cairo'),
        labelStyle: TextStyle(color: tokens.mutedText, fontFamily: 'Cairo'),
        prefixIconColor: tokens.mutedText,
        suffixIconColor: tokens.mutedText,
      ),

      // ── Card ──────────────────────────────────────────────────────
      cardTheme: CardThemeData(
        color: cardBg,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: r16,
          side: BorderSide(color: divColor, width: 0.5),
        ),
        margin: EdgeInsets.zero,
        surfaceTintColor: Colors.transparent,
      ),

      // ── Dialog ────────────────────────────────────────────────────
      dialogTheme: DialogThemeData(
        backgroundColor: dialogBg,
        elevation: 8,
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(20))),
        titleTextStyle: TextStyle(
          color: onSurface,
          fontSize: 18,
          fontWeight: FontWeight.w700,
          fontFamily: 'Cairo',
        ),
        contentTextStyle: TextStyle(color: onSurface, fontSize: 14, fontFamily: 'Cairo'),
      ),

      // ── Bottom Sheet ──────────────────────────────────────────────
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: sheetBg,
        modalBackgroundColor: sheetBg,
        elevation: 8,
        modalElevation: 16,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
        ),
        showDragHandle: true,
        dragHandleColor: divColor,
      ),

      // ── Navigation ────────────────────────────────────────────────
      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        backgroundColor: navBg,
        selectedItemColor: primary,
        unselectedItemColor: tokens.mutedText,
        type: BottomNavigationBarType.fixed,
        showSelectedLabels: true,
        showUnselectedLabels: true,
        selectedLabelStyle: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, fontFamily: 'Cairo'),
        unselectedLabelStyle: const TextStyle(fontSize: 11, fontFamily: 'Cairo'),
        elevation: 8,
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: navBg,
        indicatorColor: primary.withOpacity(0.15),
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return TextStyle(
            color: selected ? primary : tokens.mutedText,
            fontSize: 11,
            fontWeight: selected ? FontWeight.w600 : FontWeight.normal,
            fontFamily: 'Cairo',
          );
        }),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          return IconThemeData(
            color: states.contains(WidgetState.selected) ? primary : tokens.mutedText,
          );
        }),
      ),
      drawerTheme: DrawerThemeData(
        backgroundColor: sheetBg,
        elevation: 8,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.horizontal(right: Radius.circular(24)),
        ),
      ),

      // ── Selection & Form ──────────────────────────────────────────
      switchTheme: SwitchThemeData(
        thumbColor: WidgetStateProperty.resolveWith((s) =>
            s.contains(WidgetState.selected) ? primary : tokens.mutedText),
        trackColor: WidgetStateProperty.resolveWith((s) =>
            s.contains(WidgetState.selected) ? primary.withOpacity(0.3) : divColor),
      ),
      checkboxTheme: CheckboxThemeData(
        fillColor: WidgetStateProperty.resolveWith((s) =>
            s.contains(WidgetState.selected) ? primary : Colors.transparent),
        checkColor: WidgetStateProperty.all(Colors.white),
        side: BorderSide(color: tokens.mutedText),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
      ),
      radioTheme: RadioThemeData(
        fillColor: WidgetStateProperty.resolveWith((s) =>
            s.contains(WidgetState.selected) ? primary : tokens.mutedText),
      ),

      // ── Chip ──────────────────────────────────────────────────────
      chipTheme: ChipThemeData(
        backgroundColor: tokens.chipBg,
        selectedColor: tokens.chipSelected,
        labelStyle: TextStyle(fontSize: 12, fontFamily: 'Cairo', color: onSurface),
        shape: const RoundedRectangleBorder(borderRadius: r20),
        side: BorderSide(color: divColor),
        checkmarkColor: primary,
        deleteIconColor: tokens.mutedText,
      ),

      // ── Tab ───────────────────────────────────────────────────────
      tabBarTheme: TabBarThemeData(
        labelColor: primary,
        unselectedLabelColor: tokens.mutedText,
        labelStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, fontFamily: 'Cairo'),
        unselectedLabelStyle: const TextStyle(fontSize: 14, fontFamily: 'Cairo'),
        indicator: const UnderlineTabIndicator(
          borderSide: BorderSide(color: primary, width: 2),
        ),
        dividerColor: Colors.transparent,
        indicatorColor: primary,
      ),

      // ── List ──────────────────────────────────────────────────────
      listTileTheme: ListTileThemeData(
        tileColor: cardBg,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        iconColor: tokens.mutedText,
        textColor: onSurface,
        subtitleTextStyle: TextStyle(color: tokens.mutedText, fontSize: 13, fontFamily: 'Cairo'),
        titleTextStyle: TextStyle(color: onSurface, fontSize: 15, fontWeight: FontWeight.w500, fontFamily: 'Cairo'),
      ),
      expansionTileTheme: ExpansionTileThemeData(
        backgroundColor: cardBg,
        collapsedBackgroundColor: cardBg,
        textColor: onSurface,
        collapsedTextColor: onSurface,
        iconColor: tokens.mutedText,
        collapsedIconColor: tokens.mutedText,
        shape: RoundedRectangleBorder(borderRadius: r12, side: BorderSide(color: divColor, width: 0.5)),
        collapsedShape: RoundedRectangleBorder(borderRadius: r12, side: BorderSide(color: divColor, width: 0.5)),
      ),

      // ── SnackBar ──────────────────────────────────────────────────
      snackBarTheme: SnackBarThemeData(
        backgroundColor: cs.brightness == Brightness.dark
            ? const Color(0xFF2A2B48)
            : const Color(0xFF1A1A2E),
        contentTextStyle: const TextStyle(color: Colors.white, fontFamily: 'Cairo'),
        shape: const RoundedRectangleBorder(borderRadius: BorderRadius.all(Radius.circular(12))),
        behavior: SnackBarBehavior.floating,
        actionTextColor: primary,
      ),

      // ── Progress ──────────────────────────────────────────────────
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: primary,
        linearTrackColor: Color(0x26FF8A00),
        circularTrackColor: Color(0x26FF8A00),
      ),

      // ── Popup Menu ────────────────────────────────────────────────
      popupMenuTheme: PopupMenuThemeData(
        color: dialogBg,
        elevation: 8,
        shape: const RoundedRectangleBorder(borderRadius: r12),
        textStyle: TextStyle(color: onSurface, fontFamily: 'Cairo'),
      ),

      // ── Tooltip ───────────────────────────────────────────────────
      tooltipTheme: TooltipThemeData(
        decoration: BoxDecoration(
          color: cs.brightness == Brightness.dark
              ? const Color(0xFF2A2B48)
              : const Color(0xFF07003B),
          borderRadius: r12,
        ),
        textStyle: const TextStyle(color: Colors.white, fontSize: 12, fontFamily: 'Cairo'),
      ),

      // ── Divider & misc ────────────────────────────────────────────
      dividerTheme: DividerThemeData(color: divColor, thickness: 0.5),
      iconTheme: IconThemeData(color: tokens.mutedText),
      primaryIconTheme: const IconThemeData(color: primary),

      // ── Typography ────────────────────────────────────────────────
      textTheme: TextTheme(
        displayLarge:   TextStyle(fontSize: 32, fontWeight: FontWeight.w800, color: onSurface),
        headlineLarge:  TextStyle(fontSize: 24, fontWeight: FontWeight.w700, color: onSurface),
        headlineMedium: TextStyle(fontSize: 20, fontWeight: FontWeight.w700, color: onSurface),
        titleLarge:     TextStyle(fontSize: 18, fontWeight: FontWeight.w600, color: onSurface),
        titleMedium:    TextStyle(fontSize: 16, fontWeight: FontWeight.w600, color: onSurface),
        titleSmall:     TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: onSurface),
        bodyLarge:      TextStyle(fontSize: 15, fontWeight: FontWeight.w400, color: onSurface),
        bodyMedium:     TextStyle(fontSize: 14, fontWeight: FontWeight.w400, color: onSurface),
        bodySmall:      TextStyle(fontSize: 12, fontWeight: FontWeight.w400, color: tokens.mutedText),
        labelLarge:     TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: onSurface),
        labelMedium:    TextStyle(fontSize: 12, fontWeight: FontWeight.w500, color: tokens.mutedText),
        labelSmall:     TextStyle(fontSize: 11, fontWeight: FontWeight.w400, color: tokens.mutedText),
      ).apply(fontFamily: 'Cairo'),
    );
  }
}
