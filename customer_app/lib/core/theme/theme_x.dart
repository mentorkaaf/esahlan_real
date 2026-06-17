import 'package:flutter/material.dart';
import 'app_color_tokens.dart';

/// BuildContext extensions — use these everywhere instead of hardcoded Color().
///
///   context.colors.cardBg       — theme-aware card background
///   context.colors.navyText     — adapts dark navy → light lavender in dark mode
///   context.cs.primary          — orange primary (always)
///   context.isDark              — true when in dark mode
extension AppThemeX on BuildContext {
  /// All custom semantic color tokens for this theme.
  AppColorTokens get colors => AppColorTokens.of(this);

  /// Material ColorScheme shortcut.
  ColorScheme get cs => Theme.of(this).colorScheme;

  /// TextTheme shortcut.
  TextTheme get tt => Theme.of(this).textTheme;

  /// True when the active theme is dark.
  bool get isDark => Theme.of(this).brightness == Brightness.dark;
}
