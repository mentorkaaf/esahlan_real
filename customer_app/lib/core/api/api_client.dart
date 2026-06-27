import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
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
        connectTimeout: const Duration(minutes: 2),
        receiveTimeout: const Duration(minutes: 30),
        sendTimeout: const Duration(minutes: 30),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    dio.interceptors.add(_AuthInterceptor());
    if (kDebugMode) {
      dio.interceptors.add(LogInterceptor(
        requestBody: false,
        responseBody: false,
        error: true,
        logPrint: (o) => debugPrint('🌐 $o'),
      ));
    }

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
    final data   = e.response?.data;
    final status = e.response?.statusCode;
    String msg   = 'Something went wrong. Please try again.';

    // Extract backend message first (most specific)
    if (data is Map) {
      // Try common message keys
      final backendMsg = data['message']?.toString()
          ?? data['error']?.toString()
          ?? data['msg']?.toString();

      if (backendMsg != null && backendMsg.isNotEmpty) {
        msg = backendMsg;
      } else if (data['errors'] is Map) {
        // Validation errors — take the first one
        final errs = data['errors'] as Map;
        final first = errs.values.firstOrNull;
        if (first is List && first.isNotEmpty) {
          msg = first.first.toString();
        } else if (first is String) {
          msg = first;
        }
      }
    }

    // Override with friendly messages for specific conditions
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout ||
        e.type == DioExceptionType.sendTimeout) {
      msg = 'Request timed out. Please check your connection and try again.';
    } else if (e.type == DioExceptionType.connectionError ||
               e.type == DioExceptionType.unknown) {
      msg = 'No internet connection. Please check your network and try again.';
    } else if (status == 401) {
      msg = 'Your session has expired. Please sign in again.';
    } else if (status == 403) {
      msg = 'You don\'t have permission to perform this action.';
    } else if (status == 404) {
      msg = data is Map ? (data['message']?.toString() ?? 'The requested item was not found.') : 'Not found.';
    } else if (status == 429) {
      msg = 'Too many requests. Please wait a moment and try again.';
    } else if (status != null && status >= 500) {
      // Keep backend message if server provided one, else generic
      if (data is! Map || (data['message'] == null && data['error'] == null)) {
        msg = 'A server error occurred. Please try again later.';
      }
    }

    return ApiException(
      msg,
      statusCode: status,
      errors: data is Map ? data['errors'] as Map<String, dynamic>? : null,
    );
  }

  @override
  String toString() => message;
}
