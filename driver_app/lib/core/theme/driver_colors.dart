import 'package:flutter/material.dart';

/// Accent colors — identical in both themes, safe to use as const
class DC {
  DC._();
  static const orange      = Color(0xFFFF8A00);
  static const orangeLight = Color(0xFFFFAB40);
  static const orangeDim   = Color(0x33FF8A00);
  static const error       = Color(0xFFEF4444);
  static const success     = Color(0xFF10B981);
  static const online      = Color(0xFF10B981);
  static const offline     = Color(0xFF6B7280);
  static const busy        = Color(0xFFF59E0B);
}

/// Theme-aware surface and text colors — always access via context.dc
@immutable
class AppColors extends ThemeExtension<AppColors> {
  final Color navy;
  final Color navyLight;
  final Color surface;
  final Color card;
  final Color cardLight;
  final Color text;
  final Color textSec;
  final Color textMuted;
  final Color border;
  final Color divider;
  final Color inputFill;

  const AppColors({
    required this.navy,
    required this.navyLight,
    required this.surface,
    required this.card,
    required this.cardLight,
    required this.text,
    required this.textSec,
    required this.textMuted,
    required this.border,
    required this.divider,
    required this.inputFill,
  });

  static const dark = AppColors(
    navy:      Color(0xFF0A1628),
    navyLight: Color(0xFF12233D),
    surface:   Color(0xFF162038),
    card:      Color(0xFF1C2B4A),
    cardLight: Color(0xFF243355),
    text:      Color(0xFFFFFFFF),
    textSec:   Color(0xFF8B9CC7),
    textMuted: Color(0xFF5B6B8A),
    border:    Color(0xFF2A3A5C),
    divider:   Color(0xFF1E2F4D),
    inputFill: Color(0xFF162038),
  );

  static const light = AppColors(
    navy:      Color(0xFFF0F4F8),
    navyLight: Color(0xFFFFFFFF),
    surface:   Color(0xFFFFFFFF),
    card:      Color(0xFFFFFFFF),
    cardLight: Color(0xFFF8FAFC),
    text:      Color(0xFF0D1B2E),
    textSec:   Color(0xFF4B5B72),
    textMuted: Color(0xFF8A97A8),
    border:    Color(0xFFDDE3ED),
    divider:   Color(0xFFEEF1F6),
    inputFill: Color(0xFFF5F7FA),
  );

  @override
  AppColors copyWith({
    Color? navy, Color? navyLight, Color? surface, Color? card, Color? cardLight,
    Color? text, Color? textSec, Color? textMuted, Color? border, Color? divider,
    Color? inputFill,
  }) => AppColors(
    navy:      navy      ?? this.navy,
    navyLight: navyLight ?? this.navyLight,
    surface:   surface   ?? this.surface,
    card:      card      ?? this.card,
    cardLight: cardLight ?? this.cardLight,
    text:      text      ?? this.text,
    textSec:   textSec   ?? this.textSec,
    textMuted: textMuted ?? this.textMuted,
    border:    border    ?? this.border,
    divider:   divider   ?? this.divider,
    inputFill: inputFill ?? this.inputFill,
  );

  @override
  AppColors lerp(AppColors? other, double t) {
    if (other == null) return this;
    return AppColors(
      navy:      Color.lerp(navy,      other.navy,      t)!,
      navyLight: Color.lerp(navyLight, other.navyLight, t)!,
      surface:   Color.lerp(surface,   other.surface,   t)!,
      card:      Color.lerp(card,      other.card,      t)!,
      cardLight: Color.lerp(cardLight, other.cardLight, t)!,
      text:      Color.lerp(text,      other.text,      t)!,
      textSec:   Color.lerp(textSec,   other.textSec,   t)!,
      textMuted: Color.lerp(textMuted, other.textMuted, t)!,
      border:    Color.lerp(border,    other.border,    t)!,
      divider:   Color.lerp(divider,   other.divider,   t)!,
      inputFill: Color.lerp(inputFill, other.inputFill, t)!,
    );
  }
}

/// Quick accessor: context.dc.navy, context.dc.card, etc.
extension AppColorsExt on BuildContext {
  AppColors get dc => Theme.of(this).extension<AppColors>() ?? AppColors.dark;
}
