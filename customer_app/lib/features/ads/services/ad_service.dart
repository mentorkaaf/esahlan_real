import 'dart:async';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../models/ad_model.dart';
import '../../../core/api/api_client.dart';
import '../widgets/popup_ad_dialog.dart';

// ─── Keys ─────────────────────────────────────────────────────────────────────
const _kOpenCount    = 'ads_open_count';
const _kDontShowDate = 'ads_dont_show_date_'; // + adId
const _kShownDate    = 'ads_shown_date_';     // + adId

class AdService {
  AdService._();
  static final AdService instance = AdService._();

  final Dio _dio = ApiClient.instance;

  // ── Fetch ─────────────────────────────────────────────────────────────────

  Future<List<AdModel>> fetchAds({String? type, String? module}) async {
    try {
      final resp = await _dio.get(
        '/ads',
        queryParameters: {
          if (type   != null) 'type': type,
          if (module != null) 'module': module,
        },
      );
      final list = resp.data['data'] as List? ?? [];
      return list.map((e) => AdModel.fromJson(e as Map<String, dynamic>)).toList();
    } catch (_) {
      return [];
    }
  }

  // ── Tracking ──────────────────────────────────────────────────────────────

  Future<void> trackImpression(int adId) async {
    try {
      await _dio.post('/ads/$adId/track', data: {'action': 'impression'});
    } catch (_) {}
  }

  Future<void> trackClick(int adId) async {
    try {
      await _dio.post('/ads/$adId/track', data: {'action': 'click'});
    } catch (_) {}
  }

  // ── Frequency & "Don't show today" logic ─────────────────────────────────

  Future<bool> shouldShow(AdModel ad) async {
    final prefs = await SharedPreferences.getInstance();

    // "Don't show today" check
    final dontShowKey = '$_kDontShowDate${ad.id}';
    final dontShowDate = prefs.getString(dontShowKey);
    if (dontShowDate != null) {
      final storedDate = DateTime.tryParse(dontShowDate);
      if (storedDate != null) {
        final today = DateTime.now();
        if (storedDate.year == today.year &&
            storedDate.month == today.month &&
            storedDate.day == today.day) {
          return false;
        }
      }
    }

    // Frequency check: show every N app opens
    if (ad.displayFrequency > 1) {
      final openCount = prefs.getInt(_kOpenCount) ?? 0;
      if (openCount % ad.displayFrequency != 0) return false;
    }

    return true;
  }

  Future<void> markDontShowToday(int adId) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('$_kDontShowDate$adId', DateTime.now().toIso8601String());
  }

  Future<void> incrementOpenCount() async {
    final prefs = await SharedPreferences.getInstance();
    final count = prefs.getInt(_kOpenCount) ?? 0;
    await prefs.setInt(_kOpenCount, count + 1);
  }

  // ── App-open popup trigger ────────────────────────────────────────────────

  Future<void> triggerAppOpenPopups(BuildContext context, WidgetRef ref) async {
    await incrementOpenCount();

    final ads = await fetchAds(type: 'popup');
    final eligible = <AdModel>[];

    for (final ad in ads) {
      if (!ad.showOnAppOpen) continue;
      if (await shouldShow(ad)) eligible.add(ad);
    }

    if (eligible.isEmpty) return;

    // Delay before showing first popup
    final delay = eligible.first.displayDelaySeconds.clamp(0, 30);
    await Future.delayed(Duration(seconds: delay));
    if (!context.mounted) return;

    // Show all eligible popups sequentially with 5s gap between each
    for (int i = 0; i < eligible.length; i++) {
      if (!context.mounted) return;
      final ad = eligible[i];
      await trackImpression(ad.id);
      await showPopupAd(context, ad, adService: this);
      if (i < eligible.length - 1) {
        await Future.delayed(const Duration(seconds: 5));
      }
    }
  }
}
