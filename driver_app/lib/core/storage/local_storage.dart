import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class LocalStorage {
  static const _secure = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static Future<void> saveToken(String token) => _secure.write(key: 'auth_token', value: token);
  static Future<String?> getToken() => _secure.read(key: 'auth_token');
  static Future<void> deleteToken() => _secure.delete(key: 'auth_token');

  static Future<SharedPreferences> get _prefs => SharedPreferences.getInstance();
  static Future<void> saveString(String key, String value) async => (await _prefs).setString(key, value);
  static Future<String?> getString(String key) async => (await _prefs).getString(key);
  static Future<void> saveBool(String key, bool value) async => (await _prefs).setBool(key, value);
  static Future<bool> getBool(String key, {bool def = false}) async => (await _prefs).getBool(key) ?? def;

  static Future<void> clear() async {
    await _secure.deleteAll();
    (await _prefs).clear();
  }
}
