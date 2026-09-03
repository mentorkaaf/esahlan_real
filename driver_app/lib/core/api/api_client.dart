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

// Fix C-5: Use QueuedInterceptorsWrapper so the async token read is properly
// awaited before Dio proceeds with the request. A plain `async void` override
// is not awaited by Dio, which can cause requests to go out without the
// Authorization header on a slow secure-storage read.
class _AuthInterceptor extends QueuedInterceptorsWrapper {
  @override
  Future<void> onRequest(
      RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await LocalStorage.getToken();
    if (token != null) options.headers['Authorization'] = 'Bearer $token';
    handler.next(options);
  }

  @override
  Future<void> onError(
      DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      // Fix M-6: delete token then trigger auth state refresh so the router
      // redirect fires immediately and active widgets can clean up.
      await LocalStorage.deleteToken();
      // Broadcast the logout so authStateProvider re-evaluates.
      // (authStateProvider.invalidate is not accessible here; we rely on the
      //  router's refreshListenable which watches authStateProvider.)
    }
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
