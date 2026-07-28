import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';

/// Persists cart items to SharedPreferences so they survive app kills.
/// Each module has its own key. Data is a JSON-encoded List<Map>.
class CartPersistenceService {
  CartPersistenceService._();
  static final instance = CartPersistenceService._();

  static const String _prefix = 'cart_v1_';

  Future<void> save(String module, List<Map<String, dynamic>> items) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefix + module, jsonEncode(items));
  }

  Future<List<Map<String, dynamic>>> load(String module) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_prefix + module);
    if (raw == null || raw.isEmpty) return [];
    try {
      final list = jsonDecode(raw) as List<dynamic>;
      return list.cast<Map<String, dynamic>>();
    } catch (_) {
      return [];
    }
  }

  Future<void> clear(String module) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_prefix + module);
  }
}
