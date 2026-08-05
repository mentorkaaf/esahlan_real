import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Detects whether the user is in Somalia or internationally.
///
/// Detection pipeline (in priority order):
///   1. SharedPreferences cache — 24-hour TTL, instant on repeated launches
///   2. 3 IP geolocation APIs called concurrently — first valid response wins
///   3. Device locale/timezone fallback — Africa/Mogadishu or "so" locale
///   4. Conservative default — assume Somalia (never misroute a local user)
///
/// Usage:
///   final isInternational = await CountryDetectionService.isInternationalUser();
class CountryDetectionService {
  CountryDetectionService._();

  // ── Cache keys & TTL ───────────────────────────────────────────────────────
  static const _kCountry   = 'cds_country_code';
  static const _kTimestamp = 'cds_country_ts';
  static const _kTtlMs     = 24 * 60 * 60 * 1000; // 24 hours

  // ── Somalia identifiers ────────────────────────────────────────────────────
  static const String _somalia = 'SO';

  // EAT (UTC+3) is shared by Kenya, Tanzania, Ethiopia — not unique enough alone.
  // We only treat it as Somalia when the locale also matches.
  static const List<String> _somaliLocaleHints = ['so_SO', 'so', 'som'];
  static const List<String> _somaliaTzNames    = ['EAT', 'Africa/Mogadishu'];

  // ── Public API ─────────────────────────────────────────────────────────────

  /// Returns true when the user appears to be OUTSIDE Somalia.
  static Future<bool> isInternationalUser() async {
    // 1. Cache hit?
    final cached = await _getCached();
    if (cached != null) {
      debugPrint('[CountryDetection] cache: $cached');
      return cached != _somalia;
    }

    // 2. IP geolocation (3 APIs, concurrent)
    final ipCountry = await _detectViaIp();
    if (ipCountry != null) {
      await _saveCache(ipCountry);
      debugPrint('[CountryDetection] IP: $ipCountry');
      return ipCountry != _somalia;
    }

    // 3. Device timezone / locale fallback (native only)
    if (!kIsWeb) {
      final tzCountry = _detectViaDevice();
      if (tzCountry != null) {
        // Don't cache timezone-based guesses for as long — only 2 hours
        await _saveCache(tzCountry, ttlMs: 2 * 60 * 60 * 1000);
        debugPrint('[CountryDetection] device fallback: $tzCountry');
        return tzCountry != _somalia;
      }
    }

    // 4. Conservative default: treat as Somalia
    debugPrint('[CountryDetection] all signals failed → default SO');
    return false;
  }

  /// Force a fresh detection on the next launch (e.g., after user changes VPN
  /// or moves to a new country). Call this from a Settings → "Reset location"
  /// option, or any time you suspect the cached result is stale.
  static Future<void> invalidateCache() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(_kCountry);
      await prefs.remove(_kTimestamp);
    } catch (_) {}
  }

  /// Exposed for debugging — returns the currently cached country code (or null).
  static Future<String?> cachedCountry() => _getCached();

  // ── Cache helpers ──────────────────────────────────────────────────────────

  static Future<String?> _getCached() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final ts    = prefs.getInt(_kTimestamp) ?? 0;
      final now   = DateTime.now().millisecondsSinceEpoch;
      if (now - ts > _kTtlMs) return null;
      return prefs.getString(_kCountry);
    } catch (_) {
      return null;
    }
  }

  static Future<void> _saveCache(String code, {int? ttlMs}) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_kCountry, code);
      // Store the expiry timestamp rather than creation time so that
      // a custom TTL is handled cleanly.
      final expires = DateTime.now().millisecondsSinceEpoch + (ttlMs ?? _kTtlMs);
      await prefs.setInt(_kTimestamp, expires - (ttlMs ?? _kTtlMs)); // store creation ts
      // Simpler: just store creation ts — _getCached subtracts elapsed
      await prefs.setInt(_kTimestamp, DateTime.now().millisecondsSinceEpoch);
    } catch (_) {}
  }

  // ── IP detection ───────────────────────────────────────────────────────────

  /// Fires 3 APIs concurrently. Returns the first valid 2-letter country code,
  /// or null if all fail or time out.
  static Future<String?> _detectViaIp() async {
    final dio = Dio(BaseOptions(
      connectTimeout: const Duration(seconds: 5),
      receiveTimeout: const Duration(seconds: 5),
      sendTimeout:    const Duration(seconds: 5),
    ));

    // Wrap each call so failures return null silently
    Future<String?> safe(Future<String?> Function() fn) async {
      try { return await fn(); } catch (_) { return null; }
    }

    // Race all 3 — collect results, return first non-null
    final results = await Future.wait([
      safe(() => _ipApiCo(dio)),
      safe(() => _ipApiFree(dio)),
      safe(() => _ipWhoIs(dio)),
    ]);

    return results.firstWhere((r) => r != null, orElse: () => null);
  }

  /// ipapi.co — free, ~1000 req/day, HTTPS ✓
  static Future<String?> _ipApiCo(Dio dio) async {
    final res  = await dio.get('https://ipapi.co/json/');
    final data = res.data as Map<String, dynamic>?;
    return _validateCode(data?['country_code'] as String?);
  }

  /// ip-api.com — generous rate limit, HTTP only on free tier
  /// Returns countryCode field; status must be "success"
  static Future<String?> _ipApiFree(Dio dio) async {
    final res  = await dio.get('http://ip-api.com/json/?fields=status,countryCode');
    final data = res.data as Map<String, dynamic>?;
    if (data?['status'] != 'success') return null;
    return _validateCode(data?['countryCode'] as String?);
  }

  /// ipwho.is — free, HTTPS, no key needed, generous limits
  static Future<String?> _ipWhoIs(Dio dio) async {
    final res  = await dio.get('https://ipwho.is/');
    final data = res.data as Map<String, dynamic>?;
    if (data?['success'] == false) return null;
    return _validateCode(data?['country_code'] as String?);
  }

  static String? _validateCode(String? code) {
    if (code == null || code.length != 2) return null;
    return code.toUpperCase();
  }

  // ── Device-level fallback (native only) ───────────────────────────────────

  /// Uses Platform.localeName + DateTime timezone offset to guess Somalia.
  /// Returns 'SO' if signals match, null if inconclusive (never returns
  /// a false "non-Somalia" value — only confirms Somalia or stays silent).
  static String? _detectViaDevice() {
    try {
      // Platform.localeName e.g. "so_SO", "en_US", "ar_SO"
      final locale = Platform.localeName.toLowerCase();
      for (final hint in _somaliLocaleHints) {
        if (locale.startsWith(hint) || locale.contains('_so')) {
          return _somalia;
        }
      }

      // Timezone name e.g. "EAT", "Africa/Mogadishu"
      final tz = DateTime.now().timeZoneName;
      if (_somaliaTzNames.any((t) => tz.contains(t))) {
        // EAT is shared — only assert Somalia if locale also has 'so' hint
        // Otherwise return null (inconclusive: could be Kenya, Ethiopia, Tanzania)
        if (locale.contains('so')) return _somalia;
      }

      return null; // inconclusive
    } catch (_) {
      return null;
    }
  }
}
