import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

class VC {
  // ── Dark palette ─────────────────────────────────────────────────────────
  static const Color navy      = Color(0xFF0B1629);
  static const Color navyLight = Color(0xFF111E35);
  static const Color navyCard  = Color(0xFF16243D);
  static const Color border    = Color(0xFF1F3154);

  // ── Light palette ────────────────────────────────────────────────────────
  static const Color lightBg      = Color(0xFFF4F6FA);
  static const Color lightSurface = Color(0xFFFFFFFF);
  static const Color lightCard    = Color(0xFFF8F9FC);
  static const Color lightBorder  = Color(0xFFE2E8F0);

  // ── Brand / status (shared) ──────────────────────────────────────────────
  static const Color orange    = Color(0xFFFF6B35);
  static const Color orangeDim = Color(0x1AFF6B35);
  static const Color green     = Color(0xFF10B981);
  static const Color greenDim  = Color(0x1A10B981);
  static const Color blue      = Color(0xFF3B82F6);
  static const Color blueDim   = Color(0x1A3B82F6);
  static const Color amber     = Color(0xFFF59E0B);
  static const Color amberDim  = Color(0x1AF59E0B);
  static const Color red       = Color(0xFFEF4444);
  static const Color redDim    = Color(0x1AEF4444);
  static const Color purple    = Color(0xFF8B5CF6);

  // ── Text (dark mode, used as defaults) ──────────────────────────────────
  static const Color text      = Color(0xFFE8EDF5);
  static const Color textSec   = Color(0xFF8A9BB0);
  static const Color textMuted = Color(0xFF5A6B82);
  static const Color divider   = Color(0xFF1F3154);

  // ── ThemeData factory ────────────────────────────────────────────────────
  static ThemeData dark() => ThemeData(
    brightness: Brightness.dark,
    scaffoldBackgroundColor: navy,
    colorScheme: const ColorScheme.dark(primary: orange, surface: navyCard),
    appBarTheme: const AppBarTheme(
      backgroundColor: navyLight,
      foregroundColor: text,
      elevation: 0,
      titleTextStyle: TextStyle(color: text, fontSize: 17, fontWeight: FontWeight.w800),
      iconTheme: IconThemeData(color: textSec),
    ),
    cardColor: navyCard,
    dividerColor: border,
    switchTheme: SwitchThemeData(
      thumbColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? green : Colors.grey),
      trackColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? greenDim : Colors.grey.withValues(alpha: 0.2)),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true, fillColor: navy,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: orange, width: 1.5)),
      hintStyle: const TextStyle(color: textMuted),
      isDense: true, contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    ),
    textTheme: const TextTheme(bodyMedium: TextStyle(color: text), bodySmall: TextStyle(color: textSec)),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(backgroundColor: navyLight, selectedItemColor: orange, unselectedItemColor: textMuted, type: BottomNavigationBarType.fixed, elevation: 0),
    extensions: const [_VCColors(
      bg: navy, surface: navyLight, card: navyCard,
      borderColor: border, textPrimary: text, textSecondary: textSec, textHint: textMuted,
      inputFill: navy, isDark: true,
    )],
  );

  static ThemeData light() => ThemeData(
    brightness: Brightness.light,
    scaffoldBackgroundColor: lightBg,
    colorScheme: const ColorScheme.light(primary: orange, surface: lightSurface),
    appBarTheme: const AppBarTheme(
      backgroundColor: lightSurface,
      foregroundColor: Color(0xFF1A2340),
      elevation: 0,
      shadowColor: Color(0x0F000000),
      titleTextStyle: TextStyle(color: Color(0xFF1A2340), fontSize: 17, fontWeight: FontWeight.w800),
      iconTheme: IconThemeData(color: Color(0xFF5A6B82)),
    ),
    cardColor: lightSurface,
    dividerColor: lightBorder,
    switchTheme: SwitchThemeData(
      thumbColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? green : Colors.grey),
      trackColor: WidgetStateProperty.resolveWith((s) => s.contains(WidgetState.selected) ? greenDim : Colors.grey.withValues(alpha: 0.2)),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true, fillColor: lightCard,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: lightBorder)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: lightBorder)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: orange, width: 1.5)),
      hintStyle: const TextStyle(color: Color(0xFF94A3B8)),
      isDense: true, contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
    ),
    textTheme: const TextTheme(bodyMedium: TextStyle(color: Color(0xFF1A2340)), bodySmall: TextStyle(color: Color(0xFF5A6B82))),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(backgroundColor: lightSurface, selectedItemColor: orange, unselectedItemColor: Color(0xFF94A3B8), type: BottomNavigationBarType.fixed, elevation: 0),
    extensions: const [_VCColors(
      bg: lightBg, surface: lightSurface, card: lightCard,
      borderColor: lightBorder, textPrimary: Color(0xFF1A2340), textSecondary: Color(0xFF5A6B82), textHint: Color(0xFF94A3B8),
      inputFill: lightCard, isDark: false,
    )],
  );

  // Legacy alias
  static ThemeData theme() => dark();

  // Helper: get theme colors from context
  static _VCColors of(BuildContext context) =>
    Theme.of(context).extension<_VCColors>() ?? const _VCColors(
      bg: navy, surface: navyLight, card: navyCard,
      borderColor: border, textPrimary: text, textSecondary: textSec, textHint: textMuted,
      inputFill: navy, isDark: true,
    );
}

// ThemeExtension to carry context-aware colors
@immutable
class _VCColors extends ThemeExtension<_VCColors> {
  final Color bg, surface, card, borderColor, textPrimary, textSecondary, textHint, inputFill;
  final bool isDark;
  const _VCColors({required this.bg, required this.surface, required this.card, required this.borderColor,
    required this.textPrimary, required this.textSecondary, required this.textHint, required this.inputFill, required this.isDark});
  @override
  _VCColors copyWith({Color? bg, Color? surface, Color? card, Color? borderColor,
    Color? textPrimary, Color? textSecondary, Color? textHint, Color? inputFill, bool? isDark}) =>
    _VCColors(bg: bg ?? this.bg, surface: surface ?? this.surface, card: card ?? this.card,
      borderColor: borderColor ?? this.borderColor, textPrimary: textPrimary ?? this.textPrimary,
      textSecondary: textSecondary ?? this.textSecondary, textHint: textHint ?? this.textHint,
      inputFill: inputFill ?? this.inputFill, isDark: isDark ?? this.isDark);
  @override
  _VCColors lerp(_VCColors? other, double t) => this;
}

// BuildContext extension for easy theme-aware color access
extension VCContext on BuildContext {
  bool    get isDark     => Theme.of(this).brightness == Brightness.dark;
  Color   get vcBg       => Theme.of(this).scaffoldBackgroundColor;
  Color   get vcSurface  => Theme.of(this).appBarTheme.backgroundColor ?? (isDark ? VC.navyLight : VC.lightSurface);
  Color   get vcCard     => Theme.of(this).cardColor;
  Color   get vcBorder   => Theme.of(this).dividerColor;
  Color   get vcText     => isDark ? VC.text     : const Color(0xFF1A2340);
  Color   get vcTextSec  => isDark ? VC.textSec  : const Color(0xFF5A6B82);
  Color   get vcTextMute => isDark ? VC.textMuted: const Color(0xFF94A3B8);
  Color   get vcInputFill => isDark ? VC.navy    : VC.lightCard;
}

// Theme provider
final themeModeProvider = StateNotifierProvider<ThemeModeNotifier, ThemeMode>((ref) => ThemeModeNotifier());

class ThemeModeNotifier extends StateNotifier<ThemeMode> {
  ThemeModeNotifier() : super(ThemeMode.dark) { _load(); }

  Future<void> _load() async {
    final prefs = await SharedPreferences.getInstance();
    final val = prefs.getString('vendor_theme') ?? 'dark';
    state = val == 'light' ? ThemeMode.light : ThemeMode.dark;
  }

  Future<void> toggle() async {
    state = state == ThemeMode.dark ? ThemeMode.light : ThemeMode.dark;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('vendor_theme', state == ThemeMode.light ? 'light' : 'dark');
  }
}
