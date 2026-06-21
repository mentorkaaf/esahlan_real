import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';

class ApiClient {
  static Dio? _instance;

  static Dio get instance {
    _instance ??= _create();
    return _instance!;
  }

  static Dio _create() {
    final dio = Dio(BaseOptions(
      baseUrl: AppConstants.baseUrl,
      connectTimeout: const Duration(seconds: 30),
      receiveTimeout: const Duration(seconds: 30),
      headers: {'Accept': 'application/json', 'Content-Type': 'application/json'},
    ));
    dio.interceptors.add(_AuthInterceptor());
    if (kDebugMode) {
      dio.interceptors.add(LogInterceptor(requestBody: false, responseBody: false, error: true,
          logPrint: (o) => debugPrint('🌐 $o')));
    }
    return dio;
  }
}

class _AuthInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await LocalStorage.getToken();
    if (token != null) options.headers['Authorization'] = 'Bearer $token';
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) await LocalStorage.deleteToken();
    handler.next(err);
  }
}

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  ApiException(this.message, {this.statusCode});

  factory ApiException.fromDio(DioException e) {
    String msg = 'Something went wrong';
    try {
      final data = e.response?.data;
      if (data is Map) msg = data['message'] ?? data['error'] ?? msg;
    } catch (_) {}
    if (e.type == DioExceptionType.connectionTimeout) msg = 'Connection timed out';
    if (e.response?.statusCode == 401) msg = 'Session expired. Please login again.';
    return ApiException(msg, statusCode: e.response?.statusCode);
  }

  @override
  String toString() => message;
}
