import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../models/ad_model.dart';
import '../services/ad_service.dart';

// ── Popup ads (app-open, login) ──────────────────────────────────────────────
final popupAdsProvider = FutureProvider<List<AdModel>>((ref) {
  return AdService.instance.fetchAds(type: 'popup');
});

// ── Banner ads (optionally filtered by module) ───────────────────────────────
final bannerAdsProvider = FutureProvider.family<List<AdModel>, String?>((ref, module) {
  return AdService.instance.fetchAds(type: 'banner', module: module);
});

// ── Card ads ─────────────────────────────────────────────────────────────────
final cardAdsProvider = FutureProvider.family<List<AdModel>, String?>((ref, module) {
  return AdService.instance.fetchAds(type: 'card', module: module);
});
