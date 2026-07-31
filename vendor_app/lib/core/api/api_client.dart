import 'package:dio/dio.dart';
import 'package:shared_preferences/shared_preferences.dart';

class ApiClient {
  static const String _base = 'https://esahlan.com/api/v1';

  final Dio _dio = Dio(BaseOptions(
    baseUrl: _base,
    connectTimeout: const Duration(seconds: 15),
    receiveTimeout: const Duration(seconds: 30),
    headers: {'Accept': 'application/json'},
  ));

  ApiClient() {
    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final prefs = await SharedPreferences.getInstance();
        final token = prefs.getString('vendor_token');
        if (token != null) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        handler.next(options);
      },
      onError: (e, handler) {
        handler.next(e);
      },
    ));
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? params, Map<String, dynamic>? queryParameters}) async {
    final res = await _dio.get(path, queryParameters: queryParameters ?? params);
    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> post(String path, {dynamic data, bool isMultipart = false}) async {
    final res = await _dio.post(path,
      data: isMultipart ? data : data,
      options: isMultipart ? Options(contentType: 'multipart/form-data') : null,
    );
    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> put(String path, {dynamic data}) async {
    final res = await _dio.put(path, data: data);
    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> patch(String path, {dynamic data}) async {
    final res = await _dio.patch(path, data: data);
    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> delete(String path) async {
    final res = await _dio.delete(path);
    return res.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> postForm(String path, FormData formData) async {
    final res = await _dio.post(path, data: formData,
      options: Options(contentType: 'multipart/form-data'));
    return res.data as Map<String, dynamic>;
  }

}
