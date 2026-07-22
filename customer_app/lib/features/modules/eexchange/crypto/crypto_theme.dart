import 'package:flutter/material.dart';

// ── Accent colors — always the same regardless of theme ──────────────────────
const kCryptoPrimary = Color(0xFF6C63FF);
const kCryptoGreen   = Color(0xFF00C97A);
const kCryptoRed     = Color(0xFFFF4B55);
const kCryptoGold    = Color(0xFFFFB300);

// ── Context-aware structural colors (follow app light/dark theme) ─────────────
Color cBg(BuildContext c)   => Theme.of(c).scaffoldBackgroundColor;
Color cCard(BuildContext c) => Theme.of(c).cardColor;
Color cBd(BuildContext c)   => Theme.of(c).dividerColor;
Color cTx(BuildContext c)   => Theme.of(c).colorScheme.onSurface;
Color cMt(BuildContext c)   => Theme.of(c).colorScheme.onSurface.withAlpha(140);

// ── Deprecated const colors — kept for CustomPainter / const-context use ─────
// DO NOT use in new widget build() methods — use cBg/cCard/cBd/cTx/cMt above.
const kCryptoBg     = Color(0xFF0A0D1A);
const kCryptoCard   = Color(0xFF131928);
const kCryptoBorder = Color(0xFF1C2640);
const kCryptoText   = Color(0xFFFFFFFF);
const kCryptoMuted  = Color(0xFF8899BB);

Color cryptoChangeColor(double v) => v >= 0 ? kCryptoGreen : kCryptoRed;

String cryptoPct(double v) =>
    '${v >= 0 ? '+' : ''}${v.toStringAsFixed(2)}%';

String cryptoFmtUsd(double v, {int d = 2}) {
  if (v >= 1e9) return '\$${(v / 1e9).toStringAsFixed(2)}B';
  if (v >= 1e6) return '\$${(v / 1e6).toStringAsFixed(2)}M';
  if (v >= 1e3) return '\$${(v / 1e3).toStringAsFixed(2)}K';
  return '\$${v.toStringAsFixed(d)}';
}

String cryptoCoinPrice(double p) {
  if (p >= 1000) return '\$${p.toStringAsFixed(2)}';
  if (p >= 1)    return '\$${p.toStringAsFixed(4)}';
  return '\$${p.toStringAsFixed(6)}';
}

ThemeData cryptoTheme() => ThemeData.dark().copyWith(
  scaffoldBackgroundColor: kCryptoBg,
  colorScheme: const ColorScheme.dark(
    primary: kCryptoPrimary,
    surface: kCryptoCard,
  ),
  appBarTheme: const AppBarTheme(
    backgroundColor: kCryptoBg,
    elevation: 0,
    iconTheme: IconThemeData(color: kCryptoText),
    titleTextStyle: TextStyle(
      color: kCryptoText, fontSize: 16, fontWeight: FontWeight.w700),
  ),
  tabBarTheme: const TabBarThemeData(
    labelColor: kCryptoPrimary,
    unselectedLabelColor: kCryptoMuted,
    indicatorColor: kCryptoPrimary,
    indicatorSize: TabBarIndicatorSize.label,
  ),
);
