import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

class HttpCacheInterceptor extends Interceptor {
  static final Map<String, dynamic> _mem = {};
  static final Map<String, int> _memTs = {};
  static SharedPreferences? _prefs;
  static const _p = 'hc_';
  static const _memTtl = 600000; // 10 min in ms
  static const _diskTtl = 3600000; // 1 hour in ms

  static Future<void> init() async {
    _prefs = await SharedPreferences.getInstance();
  }

  static void invalidatePattern(String pattern) {
    _mem.removeWhere((k, _) => k.contains(pattern));
    _memTs.removeWhere((k, _) => k.contains(pattern));
    final keys = _prefs?.getKeys().where((k) => k.startsWith(_p) && k.contains(pattern)).toList() ?? [];
    for (final k in keys) _prefs?.remove(k);
  }

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    if (options.method != 'GET' || options.extra['noCache'] == true) {
      return handler.next(options);
    }

    final key = options.uri.toString();
    final now = DateTime.now().millisecondsSinceEpoch;

    // 1. Memory cache (fastest)
    final memData = _mem[key];
    final memAge = now - (_memTs[key] ?? 0);
    if (memData != null && memAge < _memTtl) {
      return handler.resolve(Response(requestOptions: options, data: memData, statusCode: 200));
    }

    // 2. Disk cache
    if (_prefs != null) {
      final raw = _prefs!.getString('$_p$key');
      final ts = _prefs!.getInt('${_p}t$key') ?? 0;
      if (raw != null && now - ts < _diskTtl) {
        try {
          final decoded = jsonDecode(raw);
          _mem[key] = decoded;
          _memTs[key] = ts;
          return handler.resolve(Response(requestOptions: options, data: decoded, statusCode: 200));
        } catch (_) {}
      }
    }

    handler.next(options);
  }

  @override
  void onResponse(Response response, ResponseInterceptorHandler handler) {
    if (response.requestOptions.method == 'GET' && response.statusCode == 200) {
      final key = response.requestOptions.uri.toString();
      final now = DateTime.now().millisecondsSinceEpoch;
      _mem[key] = response.data;
      _memTs[key] = now;
      _persistAsync(key, response.data, now);
    }
    handler.next(response);
  }

  static void _persistAsync(String key, dynamic data, int ts) {
    Future.microtask(() async {
      try {
        final json = jsonEncode(data);
        if (json.length < 300000) {
          await _prefs?.setString('$_p$key', json);
          await _prefs?.setInt('${_p}t$key', ts);
        }
      } catch (_) {}
    });
  }
}
