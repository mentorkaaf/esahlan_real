import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import '../api/api_client.dart';

class AuthService {
  AuthService._();
  static final instance = AuthService._();
  final _api = ApiClient();

  Future<Map<String, dynamic>> login(String login, String password) async {
    final field = login.contains('@') ? 'email' : 'phone';
    final res = await _api.post('/auth/login', data: {field: login, 'password': password});
    if (res['success'] == true) {
      final data = res['data'] as Map<String, dynamic>;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('vendor_token', data['token']);
      await prefs.setString('vendor_user', jsonEncode(data['user']));
    }
    return res;
  }

  Future<void> logout() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove('vendor_token');
    await prefs.remove('vendor_user');
  }

  Future<bool> isLoggedIn() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('vendor_token') != null;
  }

  Future<Map<String, dynamic>?> getUser() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString('vendor_user');
    if (raw == null) return null;
    return jsonDecode(raw) as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>?> getVendor() async {
    final user = await getUser();
    return user?['vendor'] as Map<String, dynamic>?;
  }
}
