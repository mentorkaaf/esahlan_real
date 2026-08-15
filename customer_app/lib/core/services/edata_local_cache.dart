// ignore_for_file: avoid_print
import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

/// Caches eData providers + bundles locally so the offline purchase flow
/// can show real data without a network connection.
class EdataLocalCache {
  static const _kProviders = 'edata_providers_v1';
  static const _kPackages  = 'edata_packages_v1_'; // + providerId
  static const _kBundles   = 'edata_bundles_v1_';  // + providerId
  static const _kPhones    = 'edata_phones_v1_';   // + providerId
  static const _kUpdatedAt = 'edata_cache_ts_v1';

  // ── Providers ─────────────────────────────────────────────────────────────

  static Future<void> saveProviders(List<dynamic> providers) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kProviders, jsonEncode(providers));
    await prefs.setInt(_kUpdatedAt, DateTime.now().millisecondsSinceEpoch);
  }

  static Future<List<dynamic>> loadProviders() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_kProviders);
    if (raw == null) return [];
    try { return (jsonDecode(raw) as List<dynamic>); } catch (_) { return []; }
  }

  // ── Packages ───────────────────────────────────────────────────────────────

  static Future<void> savePackages(int providerId, List<dynamic> packages) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kPackages + providerId.toString(), jsonEncode(packages));
  }

  static Future<List<dynamic>> loadPackages(int providerId) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_kPackages + providerId.toString());
    if (raw == null) return [];
    try { return (jsonDecode(raw) as List<dynamic>); } catch (_) { return []; }
  }

  // ── Bundles ────────────────────────────────────────────────────────────────

  static Future<void> saveBundles(int providerId, List<dynamic> bundles) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_kBundles + providerId.toString(), jsonEncode(bundles));
  }

  static Future<List<dynamic>> loadBundles(int providerId) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_kBundles + providerId.toString());
    if (raw == null) return [];
    try { return (jsonDecode(raw) as List<dynamic>); } catch (_) { return []; }
  }

  // ── Phones (local mirror of server data) ──────────────────────────────────

  static Future<void> savePhones(
      int providerId, String paymentPhone, String dataPhone) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      _kPhones + providerId.toString(),
      jsonEncode({'payment_phone': paymentPhone, 'data_phone': dataPhone}),
    );
  }

  static Future<Map<String, String>?> loadPhones(int providerId) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_kPhones + providerId.toString());
    if (raw == null) return null;
    try {
      final m = jsonDecode(raw) as Map<String, dynamic>;
      return {
        'payment_phone': m['payment_phone']?.toString() ?? '',
        'data_phone':    m['data_phone']?.toString()    ?? '',
      };
    } catch (_) { return null; }
  }

  // ── Cache metadata ─────────────────────────────────────────────────────────

  /// Returns true if cache is older than [maxHours] or has never been populated.
  static Future<bool> isStale({int maxHours = 24}) async {
    final prefs = await SharedPreferences.getInstance();
    final ts = prefs.getInt(_kUpdatedAt);
    if (ts == null) return true;
    final ageMs = DateTime.now().millisecondsSinceEpoch - ts;
    return ageMs > maxHours * 3600 * 1000;
  }

  static Future<bool> hasProviders() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_kProviders);
    if (raw == null) return false;
    try { return (jsonDecode(raw) as List).isNotEmpty; } catch (_) { return false; }
  }

  // ── Pending offline orders (sync when back online) ─────────────────────────

  static const _kPendingOrders = 'edata_pending_orders_v1';

  /// Save an order that failed due to no internet — will be synced later.
  static Future<void> savePendingOrder(Map<String, dynamic> order) async {
    final prefs  = await SharedPreferences.getInstance();
    final orders = await loadPendingOrders();
    // add timestamp so admin/backend knows when the offline attempt happened
    orders.add({...order, '_queued_at': DateTime.now().toIso8601String()});
    await prefs.setString(_kPendingOrders, jsonEncode(orders));
  }

  static Future<List<Map<String, dynamic>>> loadPendingOrders() async {
    final prefs = await SharedPreferences.getInstance();
    final raw   = prefs.getString(_kPendingOrders);
    if (raw == null) return [];
    try {
      return (jsonDecode(raw) as List)
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList();
    } catch (_) { return []; }
  }

  static Future<void> clearPendingOrders() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_kPendingOrders);
  }

  /// Remove one order by index after successful sync.
  static Future<void> removePendingOrderAt(int index) async {
    final prefs  = await SharedPreferences.getInstance();
    final orders = await loadPendingOrders();
    if (index >= 0 && index < orders.length) orders.removeAt(index);
    await prefs.setString(_kPendingOrders, jsonEncode(orders));
  }
}
