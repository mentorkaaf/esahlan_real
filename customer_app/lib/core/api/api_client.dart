import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';

/// Fires when the server returns 403 account_banned.
/// The router (or any listener) can watch this to force-logout UI.
final bannedNotifier = ValueNotifier<String?>(null);

/// Thrown when the server returns 403 with error=account_banned.
class BannedException implements Exception {
  final String message;
  const BannedException(this.message);
  @override
  String toString() => message;
}

/// Thrown when the API returns 403 with a restriction payload.
class RestrictionException implements Exception {
  final String message;
  final String restrictionType;
  final String? expiresAt;

  const RestrictionException({
    required this.message,
    required this.restrictionType,
    this.expiresAt,
  });

  @override
  String toString() => message;
}

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
        connectTimeout: AppConstants.apiConnectTimeout,
        receiveTimeout: AppConstants.apiReceiveTimeout,
        sendTimeout:    AppConstants.apiSendTimeout,
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
  // In-memory cache avoids a secure-storage disk read on every API request.
  // Cleared on 401 so the next request fetches the fresh token from storage.
  static String? _cachedToken;

  @override
  void onRequest(RequestOptions options, RequestInterceptorHandler handler) async {
    _cachedToken ??= await LocalStorage.getToken();
    if (_cachedToken != null) {
      options.headers['Authorization'] = 'Bearer $_cachedToken';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) async {
    if (err.response?.statusCode == 401) {
      _cachedToken = null; // force re-read from storage on next request
      await LocalStorage.deleteToken();
    }
    if (err.response?.statusCode == 403) {
      final data = err.response?.data;
      if (data is Map) {
        // Account banned — wipe token, force logout
        if (data['error'] == 'account_banned') {
          await LocalStorage.deleteToken();
          bannedNotifier.value = data['message'] ?? 'Your account has been suspended.';
          handler.reject(
            DioException(
              requestOptions: err.requestOptions,
              error: BannedException(data['message'] ?? 'Your account has been suspended.'),
              response: err.response,
              type: DioExceptionType.badResponse,
            ),
          );
          return;
        }
        if (data['restriction'] != null) {
          handler.reject(
            DioException(
              requestOptions: err.requestOptions,
              error: RestrictionException(
                message: data['message'] ?? 'Action not allowed.',
                restrictionType: data['restriction'] as String,
                expiresAt: data['expires_at'] as String?,
              ),
            ),
          );
          return;
        }
      }
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
