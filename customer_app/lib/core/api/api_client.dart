import 'package:dio/dio.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';

class ApiClient {
  static Dio? _instance;

  static Dio get instance {
    _instance ??= _createDio();
    return _instance!;
  }

  static Dio _createDio() {
    final dio = Dio(
      BaseOptions(
        baseUrl: AppConstants.baseUrl,
        connectTimeout: const Duration(milliseconds: 30000),
        receiveTimeout: const Duration(milliseconds: 30000),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    dio.interceptors.add(_AuthInterceptor());
    dio.interceptors.add(LogInterceptor(
      requestBody: true,
      responseBody: true,
      error: true,
      logPrint: (o) => print('🌐 $o'),
    ));

    return dio;
  }
}

class _AuthInterceptor extends Interceptor {
  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    final token = await LocalStorage.getToken();
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      await LocalStorage.deleteToken();
      // Navigation handled by router redirect
    }
    handler.next(err);
  }
}

class ApiException implements Exception {
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  ApiException(this.message, {this.statusCode, this.errors});

  factory ApiException.fromDio(DioException e) {
    final data = e.response?.data;
    String msg = 'Something went wrong. Please try again.';

    if (data is Map) {
      msg = data['message'] ?? msg;
    }

    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout) {
      msg = 'Connection timed out. Check backend is running.';
    } else if (e.type == DioExceptionType.connectionError) {
      msg = 'Cannot reach server. Is Laravel Herd running on port 8000?';
    } else if (e.type == DioExceptionType.unknown) {
      // Flutter Web CORS errors or network issues show up here
      msg = 'Network error. Check CORS config or backend is running.';
    }

    return ApiException(
      msg,
      statusCode: e.response?.statusCode,
      errors: data is Map ? data['errors'] as Map<String, dynamic>? : null,
    );
  }

  @override
  String toString() => message;
}
