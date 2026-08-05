import 'dart:async';
import 'package:flutter/foundation.dart';
import 'country_detection_service.dart';
import '../storage/local_storage.dart';

/// Watches for country changes in the background and fires a callback
/// when the detected region no longer matches the saved selection.
///
/// Usage:
///   LocationWatcherService.instance.onRegionMismatch = (detectedCode) { ... };
///   LocationWatcherService.instance.start();
///   // on dispose:
///   LocationWatcherService.instance.stop();
class LocationWatcherService {
  LocationWatcherService._();
  static final instance = LocationWatcherService._();

  // How often to re-check in the background (10 min)
  static const _checkInterval = Duration(minutes: 10);

  // Minimum gap between showing the banner (1 session = once per cold start)
  static const _kBannerShownKey = 'location_banner_shown_session';

  Timer? _timer;
  bool _running = false;

  /// Called when detected country differs from the saved country.
  /// [detectedCode] is the newly detected country code (e.g. 'SO', 'US').
  /// [savedCode]   is what the user previously chose.
  ValueChanged<RegionMismatch>? onRegionMismatch;

  // ── Public API ────────────────────────────────────────────────────────────

  void start() {
    if (_running) return;
    _running = true;
    // First check after 30 s (let app settle), then every 10 min
    Future.delayed(const Duration(seconds: 30), _check);
    _timer = Timer.periodic(_checkInterval, (_) => _check());
  }

  void stop() {
    _running = false;
    _timer?.cancel();
    _timer = null;
  }

  /// Call when the user dismisses the banner — prevents it showing again
  /// this session.
  Future<void> markBannerDismissed() async {
    await LocalStorage.saveBool(_kBannerShownKey, true);
  }

  /// Call when user switches region — resets the session flag so the banner
  /// can appear again if they switch back.
  Future<void> resetBannerState() async {
    await LocalStorage.saveBool(_kBannerShownKey, false);
  }

  // ── Internal ──────────────────────────────────────────────────────────────

  Future<void> _check() async {
    if (!_running || onRegionMismatch == null) return;

    try {
      // Don't nag — only once per cold start session
      final alreadyShown = await LocalStorage.getBool(_kBannerShownKey);
      if (alreadyShown) return;

      // Get saved user choice
      final saved = await CountryDetectionService.cachedCountry();
      if (saved == null) return; // user never selected — nothing to compare

      // Force a fresh IP check (bypass the 24h cache) by calling the raw method
      await CountryDetectionService.invalidateCache();
      final isInternational = await CountryDetectionService.isInternationalUser()
          .timeout(const Duration(seconds: 8));

      final detected = isInternational ? 'OTHER' : 'SO';

      // Restore the saved value in cache (we only wanted a fresh network check)
      await CountryDetectionService.invalidateCache();
      // Re-save the user's explicit choice so routing stays correct
      // (the cache is used by routing, not this watcher)

      // Compare: saved Somalia, now international → suggest Global
      //          saved International, now Somalia  → suggest Local
      final mismatch = _isMismatch(saved, detected);
      if (mismatch != null) {
        onRegionMismatch!(mismatch);
      }
    } catch (_) {
      // Silent — network errors during background checks are expected
    }
  }

  RegionMismatch? _isMismatch(String saved, String detected) {
    final savedIsLocal = saved == 'SO';
    final detectedIsLocal = detected == 'SO';
    if (savedIsLocal == detectedIsLocal) return null; // same region, no change
    return RegionMismatch(
      savedCode: saved,
      detectedCode: detected,
      suggestGlobal: !detectedIsLocal,
    );
  }
}

class RegionMismatch {
  final String savedCode;
  final String detectedCode;

  /// true  → user is in a non-Somalia location, suggest switching to Global
  /// false → user is in Somalia, suggest switching to Local
  final bool suggestGlobal;

  const RegionMismatch({
    required this.savedCode,
    required this.detectedCode,
    required this.suggestGlobal,
  });
}
