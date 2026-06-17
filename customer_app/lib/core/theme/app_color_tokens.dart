import 'package:flutter/material.dart';

/// Semantic color tokens consumed by every screen via `context.colors`.
/// Add new tokens here — never hardcode Color() inside widgets.
@immutable
class AppColorTokens extends ThemeExtension<AppColorTokens> {
  const AppColorTokens({
    required this.scaffoldBg,
    required this.cardBg,
    required this.surfaceBg,
    required this.elevatedBg,
    required this.navyText,
    required this.bodyText,
    required this.mutedText,
    required this.subtleText,
    required this.borderColor,
    required this.dividerColor,
    required this.inputFill,
    required this.searchBarBg,
    required this.shimmerBase,
    required this.shimmerHighlight,
    required this.statusPending,
    required this.statusPendingBg,
    required this.statusConfirmed,
    required this.statusConfirmedBg,
    required this.statusSuccess,
    required this.statusSuccessBg,
    required this.statusError,
    required this.statusErrorBg,
    required this.statusInfo,
    required this.statusInfoBg,
    required this.statusWarning,
    required this.statusWarningBg,
    required this.moduleHeaderBg,
    required this.overlayText,
    required this.iconOnLight,
    required this.chipBg,
    required this.chipSelected,
    required this.badgeBg,
  });

  // ── Backgrounds ────────────────────────────────────────────────────
  final Color scaffoldBg;       // page background
  final Color cardBg;           // card / surface container
  final Color surfaceBg;        // slightly elevated surface
  final Color elevatedBg;       // modals, bottom sheets, dialogs

  // ── Text ───────────────────────────────────────────────────────────
  final Color navyText;         // was AppColors.secondary (0xFF07003B) — adapts
  final Color bodyText;         // main body text
  final Color mutedText;        // secondary/muted text
  final Color subtleText;       // placeholder / hint

  // ── Borders ────────────────────────────────────────────────────────
  final Color borderColor;
  final Color dividerColor;

  // ── Inputs ─────────────────────────────────────────────────────────
  final Color inputFill;
  final Color searchBarBg;

  // ── Shimmer ────────────────────────────────────────────────────────
  final Color shimmerBase;
  final Color shimmerHighlight;

  // ── Status ─────────────────────────────────────────────────────────
  final Color statusPending;
  final Color statusPendingBg;
  final Color statusConfirmed;
  final Color statusConfirmedBg;
  final Color statusSuccess;
  final Color statusSuccessBg;
  final Color statusError;
  final Color statusErrorBg;
  final Color statusInfo;
  final Color statusInfoBg;
  final Color statusWarning;
  final Color statusWarningBg;

  // ── Module / Component ─────────────────────────────────────────────
  final Color moduleHeaderBg;   // navy header in modules (gradient start)
  final Color overlayText;      // text on dark gradient overlays (always white)
  final Color iconOnLight;      // icon color on colored backgrounds
  final Color chipBg;
  final Color chipSelected;
  final Color badgeBg;

  // ── Static instances ───────────────────────────────────────────────

  static const light = AppColorTokens(
    scaffoldBg:         Color(0xFFF4F5FA),
    cardBg:             Color(0xFFFFFFFF),
    surfaceBg:          Color(0xFFF8F9FE),
    elevatedBg:         Color(0xFFFFFFFF),
    navyText:           Color(0xFF07003B),
    bodyText:           Color(0xFF1A1A2E),
    mutedText:          Color(0xFF8A8A9A),
    subtleText:         Color(0xFFBBBBCC),
    borderColor:        Color(0xFFEEEEEE),
    dividerColor:       Color(0xFFEEEEEE),
    inputFill:          Color(0xFFFFFFFF),
    searchBarBg:        Color(0xFFF4F5FA),
    shimmerBase:        Color(0xFFE0E0E0),
    shimmerHighlight:   Color(0xFFF5F5F5),
    statusPending:      Color(0xFFF59E0B),
    statusPendingBg:    Color(0xFFFFF8E1),
    statusConfirmed:    Color(0xFF3B82F6),
    statusConfirmedBg:  Color(0xFFEFF6FF),
    statusSuccess:      Color(0xFF10B981),
    statusSuccessBg:    Color(0xFFECFDF5),
    statusError:        Color(0xFFEF4444),
    statusErrorBg:      Color(0xFFFEE2E2),
    statusInfo:         Color(0xFF3B82F6),
    statusInfoBg:       Color(0xFFEFF6FF),
    statusWarning:      Color(0xFFF59E0B),
    statusWarningBg:    Color(0xFFFFF8E1),
    moduleHeaderBg:     Color(0xFF07003B),
    overlayText:        Color(0xFFFFFFFF),
    iconOnLight:        Color(0xFFFFFFFF),
    chipBg:             Color(0xFFF8F9FE),
    chipSelected:       Color(0x26FF8A00),
    badgeBg:            Color(0xFFFF8A00),
  );

  static const dark = AppColorTokens(
    scaffoldBg:         Color(0xFF0F1120),
    cardBg:             Color(0xFF1F2038),
    surfaceBg:          Color(0xFF1A1B2E),
    elevatedBg:         Color(0xFF252640),
    navyText:           Color(0xFFE0E0FF),   // light lavender — readable on dark bg
    bodyText:           Color(0xFFDDDDF0),
    mutedText:          Color(0xFF9090B0),
    subtleText:         Color(0xFF5A5A7A),
    borderColor:        Color(0xFF2A2B48),
    dividerColor:       Color(0xFF2A2B48),
    inputFill:          Color(0xFF252640),
    searchBarBg:        Color(0xFF0F1120),
    shimmerBase:        Color(0xFF252640),
    shimmerHighlight:   Color(0xFF2E2F50),
    statusPending:      Color(0xFFFBBF24),
    statusPendingBg:    Color(0xFF2D2510),
    statusConfirmed:    Color(0xFF60A5FA),
    statusConfirmedBg:  Color(0xFF0D1B35),
    statusSuccess:      Color(0xFF34D399),
    statusSuccessBg:    Color(0xFF0D2520),
    statusError:        Color(0xFFF87171),
    statusErrorBg:      Color(0xFF2D1515),
    statusInfo:         Color(0xFF60A5FA),
    statusInfoBg:       Color(0xFF0D1B35),
    statusWarning:      Color(0xFFFBBF24),
    statusWarningBg:    Color(0xFF2D2510),
    moduleHeaderBg:     Color(0xFF1A1B2E),
    overlayText:        Color(0xFFFFFFFF),
    iconOnLight:        Color(0xFFFFFFFF),
    chipBg:             Color(0xFF252640),
    chipSelected:       Color(0x40FF8A00),
    badgeBg:            Color(0xFFFF8A00),
  );

  // ── ThemeExtension boilerplate ─────────────────────────────────────

  static AppColorTokens of(BuildContext context) =>
      Theme.of(context).extension<AppColorTokens>()!;

  @override
  AppColorTokens copyWith({
    Color? scaffoldBg, Color? cardBg, Color? surfaceBg, Color? elevatedBg,
    Color? navyText, Color? bodyText, Color? mutedText, Color? subtleText,
    Color? borderColor, Color? dividerColor, Color? inputFill, Color? searchBarBg,
    Color? shimmerBase, Color? shimmerHighlight,
    Color? statusPending, Color? statusPendingBg,
    Color? statusConfirmed, Color? statusConfirmedBg,
    Color? statusSuccess, Color? statusSuccessBg,
    Color? statusError, Color? statusErrorBg,
    Color? statusInfo, Color? statusInfoBg,
    Color? statusWarning, Color? statusWarningBg,
    Color? moduleHeaderBg, Color? overlayText, Color? iconOnLight,
    Color? chipBg, Color? chipSelected, Color? badgeBg,
  }) => AppColorTokens(
    scaffoldBg:        scaffoldBg        ?? this.scaffoldBg,
    cardBg:            cardBg            ?? this.cardBg,
    surfaceBg:         surfaceBg         ?? this.surfaceBg,
    elevatedBg:        elevatedBg        ?? this.elevatedBg,
    navyText:          navyText          ?? this.navyText,
    bodyText:          bodyText          ?? this.bodyText,
    mutedText:         mutedText         ?? this.mutedText,
    subtleText:        subtleText        ?? this.subtleText,
    borderColor:       borderColor       ?? this.borderColor,
    dividerColor:      dividerColor      ?? this.dividerColor,
    inputFill:         inputFill         ?? this.inputFill,
    searchBarBg:       searchBarBg       ?? this.searchBarBg,
    shimmerBase:       shimmerBase       ?? this.shimmerBase,
    shimmerHighlight:  shimmerHighlight  ?? this.shimmerHighlight,
    statusPending:     statusPending     ?? this.statusPending,
    statusPendingBg:   statusPendingBg   ?? this.statusPendingBg,
    statusConfirmed:   statusConfirmed   ?? this.statusConfirmed,
    statusConfirmedBg: statusConfirmedBg ?? this.statusConfirmedBg,
    statusSuccess:     statusSuccess     ?? this.statusSuccess,
    statusSuccessBg:   statusSuccessBg   ?? this.statusSuccessBg,
    statusError:       statusError       ?? this.statusError,
    statusErrorBg:     statusErrorBg     ?? this.statusErrorBg,
    statusInfo:        statusInfo        ?? this.statusInfo,
    statusInfoBg:      statusInfoBg      ?? this.statusInfoBg,
    statusWarning:     statusWarning     ?? this.statusWarning,
    statusWarningBg:   statusWarningBg   ?? this.statusWarningBg,
    moduleHeaderBg:    moduleHeaderBg    ?? this.moduleHeaderBg,
    overlayText:       overlayText       ?? this.overlayText,
    iconOnLight:       iconOnLight       ?? this.iconOnLight,
    chipBg:            chipBg            ?? this.chipBg,
    chipSelected:      chipSelected      ?? this.chipSelected,
    badgeBg:           badgeBg           ?? this.badgeBg,
  );

  @override
  AppColorTokens lerp(AppColorTokens? other, double t) {
    if (other is! AppColorTokens) return this;
    return AppColorTokens(
      scaffoldBg:        Color.lerp(scaffoldBg,        other.scaffoldBg,        t)!,
      cardBg:            Color.lerp(cardBg,            other.cardBg,            t)!,
      surfaceBg:         Color.lerp(surfaceBg,         other.surfaceBg,         t)!,
      elevatedBg:        Color.lerp(elevatedBg,        other.elevatedBg,        t)!,
      navyText:          Color.lerp(navyText,          other.navyText,          t)!,
      bodyText:          Color.lerp(bodyText,          other.bodyText,          t)!,
      mutedText:         Color.lerp(mutedText,         other.mutedText,         t)!,
      subtleText:        Color.lerp(subtleText,        other.subtleText,        t)!,
      borderColor:       Color.lerp(borderColor,       other.borderColor,       t)!,
      dividerColor:      Color.lerp(dividerColor,      other.dividerColor,      t)!,
      inputFill:         Color.lerp(inputFill,         other.inputFill,         t)!,
      searchBarBg:       Color.lerp(searchBarBg,       other.searchBarBg,       t)!,
      shimmerBase:       Color.lerp(shimmerBase,       other.shimmerBase,       t)!,
      shimmerHighlight:  Color.lerp(shimmerHighlight,  other.shimmerHighlight,  t)!,
      statusPending:     Color.lerp(statusPending,     other.statusPending,     t)!,
      statusPendingBg:   Color.lerp(statusPendingBg,   other.statusPendingBg,   t)!,
      statusConfirmed:   Color.lerp(statusConfirmed,   other.statusConfirmed,   t)!,
      statusConfirmedBg: Color.lerp(statusConfirmedBg, other.statusConfirmedBg, t)!,
      statusSuccess:     Color.lerp(statusSuccess,     other.statusSuccess,     t)!,
      statusSuccessBg:   Color.lerp(statusSuccessBg,   other.statusSuccessBg,   t)!,
      statusError:       Color.lerp(statusError,       other.statusError,       t)!,
      statusErrorBg:     Color.lerp(statusErrorBg,     other.statusErrorBg,     t)!,
      statusInfo:        Color.lerp(statusInfo,        other.statusInfo,        t)!,
      statusInfoBg:      Color.lerp(statusInfoBg,      other.statusInfoBg,      t)!,
      statusWarning:     Color.lerp(statusWarning,     other.statusWarning,     t)!,
      statusWarningBg:   Color.lerp(statusWarningBg,   other.statusWarningBg,   t)!,
      moduleHeaderBg:    Color.lerp(moduleHeaderBg,    other.moduleHeaderBg,    t)!,
      overlayText:       Color.lerp(overlayText,       other.overlayText,       t)!,
      iconOnLight:       Color.lerp(iconOnLight,       other.iconOnLight,       t)!,
      chipBg:            Color.lerp(chipBg,            other.chipBg,            t)!,
      chipSelected:      Color.lerp(chipSelected,      other.chipSelected,      t)!,
      badgeBg:           Color.lerp(badgeBg,           other.badgeBg,           t)!,
    );
  }
}
