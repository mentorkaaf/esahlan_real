import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import '../api/api_client.dart';
import '../theme/app_theme.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Error type classification
// ─────────────────────────────────────────────────────────────────────────────
enum _ErrorType { network, auth, balance, validation, server, general }

class _ErrorInfo {
  final String title;
  final String message;
  final IconData icon;
  final Color color;
  final _ErrorType type;

  const _ErrorInfo({
    required this.title,
    required this.message,
    required this.icon,
    required this.color,
    required this.type,
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// AppErrorHandler
// ─────────────────────────────────────────────────────────────────────────────

class AppErrorHandler {
  /// Extract a clean, human-readable message from any error object.
  static String message(dynamic error) {
    if (error is ApiException) return error.message;
    if (error is DioException) return ApiException.fromDio(error).message;
    final s = error.toString();
    // Strip technical prefixes Dart adds
    if (s.startsWith('Exception: ')) return s.substring(11);
    if (s.startsWith('Error: '))     return s.substring(7);
    return s;
  }

  /// Show a beautiful, context-aware error bottom sheet.
  static void show(
    BuildContext context,
    dynamic error, {
    String? title,
    VoidCallback? onRetry,
    String? retryLabel,
  }) {
    if (!context.mounted) return;

    final info = _classify(error);
    final displayTitle   = title ?? info.title;
    final displayMessage = info.message;

    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      isScrollControlled: true,
      builder: (_) => _ErrorSheet(
        title:      displayTitle,
        message:    displayMessage,
        icon:       info.icon,
        color:      info.color,
        onRetry:    onRetry,
        retryLabel: retryLabel,
      ),
    );
  }

  /// Show a lightweight snackbar for minor/validation errors.
  static void snack(BuildContext context, dynamic error, {SnackBarAction? action}) {
    if (!context.mounted) return;
    final msg  = message(error);
    final info = _classify(error);

    ScaffoldMessenger.of(context).hideCurrentSnackBar();
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Row(children: [
          Icon(info.icon, color: Colors.white, size: 18),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              msg,
              style: const TextStyle(
                  color: Colors.white, fontSize: 14, fontWeight: FontWeight.w500),
            ),
          ),
        ]),
        backgroundColor: info.color,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        margin: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        duration: const Duration(seconds: 4),
        action: action,
      ),
    );
  }

  // ── Internal classifier ───────────────────────────────────────────────────
  static _ErrorInfo _classify(dynamic error) {
    final msg        = message(error);
    final statusCode = error is ApiException ? error.statusCode
                     : error is DioException ? error.response?.statusCode
                     : null;

    final msgLower = msg.toLowerCase();

    // Network / connectivity
    if (error is DioException &&
        (error.type == DioExceptionType.connectionError ||
         error.type == DioExceptionType.unknown)) {
      return _ErrorInfo(
        title: 'No Internet Connection',
        message: 'Please check your network and try again.',
        icon: Icons.wifi_off_rounded,
        color: AppColors.textDark,
        type: _ErrorType.network,
      );
    }

    // Timeout
    if (error is DioException &&
        (error.type == DioExceptionType.connectionTimeout ||
         error.type == DioExceptionType.receiveTimeout ||
         error.type == DioExceptionType.sendTimeout)) {
      return _ErrorInfo(
        title: 'Request Timed Out',
        message: 'The server took too long to respond. Please try again.',
        icon: Icons.timer_off_rounded,
        color: AppColors.textDark,
        type: _ErrorType.network,
      );
    }

    // Insufficient balance
    if (msgLower.contains('insufficient') ||
        msgLower.contains('balance') ||
        msgLower.contains('not enough')) {
      return _ErrorInfo(
        title: 'Insufficient Balance',
        message: msg,
        icon: Icons.account_balance_wallet_rounded,
        color: AppColors.error,
        type: _ErrorType.balance,
      );
    }

    // Auth errors
    if (statusCode == 401 ||
        msgLower.contains('unauthenticated') ||
        msgLower.contains('session expired') ||
        msgLower.contains('unauthorized')) {
      return _ErrorInfo(
        title: 'Session Expired',
        message: 'Please sign in again to continue.',
        icon: Icons.lock_outline_rounded,
        color: AppColors.secondary,
        type: _ErrorType.auth,
      );
    }

    // Permission
    if (statusCode == 403 || msgLower.contains('permission') || msgLower.contains('forbidden')) {
      return _ErrorInfo(
        title: 'Access Denied',
        message: msg,
        icon: Icons.block_rounded,
        color: AppColors.secondary,
        type: _ErrorType.auth,
      );
    }

    // Validation / bad request (422, 400)
    if (statusCode == 422 || statusCode == 400) {
      return _ErrorInfo(
        title: 'Invalid Request',
        message: msg,
        icon: Icons.info_outline_rounded,
        color: AppColors.warning,
        type: _ErrorType.validation,
      );
    }

    // Server errors (5xx)
    if (statusCode != null && statusCode >= 500) {
      return _ErrorInfo(
        title: 'Server Error',
        message: 'Something went wrong on our end. Please try again later.',
        icon: Icons.cloud_off_rounded,
        color: AppColors.textDark,
        type: _ErrorType.server,
      );
    }

    // Not found
    if (statusCode == 404) {
      return _ErrorInfo(
        title: 'Not Found',
        message: msg,
        icon: Icons.search_off_rounded,
        color: AppColors.textDark,
        type: _ErrorType.general,
      );
    }

    // Generic
    return _ErrorInfo(
      title: 'Something Went Wrong',
      message: msg,
      icon: Icons.error_outline_rounded,
      color: AppColors.error,
      type: _ErrorType.general,
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Error Bottom Sheet Widget
// ─────────────────────────────────────────────────────────────────────────────

class _ErrorSheet extends StatelessWidget {
  final String title;
  final String message;
  final IconData icon;
  final Color color;
  final VoidCallback? onRetry;
  final String? retryLabel;

  const _ErrorSheet({
    required this.title,
    required this.message,
    required this.icon,
    required this.color,
    this.onRetry,
    this.retryLabel,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(12, 0, 12, 24),
      padding: const EdgeInsets.fromLTRB(24, 28, 24, 20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(color: Colors.black.withOpacity(0.12), blurRadius: 24, offset: const Offset(0, -4)),
        ],
      ),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        // Icon circle
        Container(
          width: 64, height: 64,
          decoration: BoxDecoration(
            color: color.withOpacity(0.1),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: color, size: 32),
        ),

        const SizedBox(height: 16),

        // Title
        Text(
          title,
          style: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w800,
            color: AppColors.textDark,
            letterSpacing: -0.3,
          ),
          textAlign: TextAlign.center,
        ),

        const SizedBox(height: 8),

        // Message
        Text(
          message,
          style: const TextStyle(
            fontSize: 14,
            color: AppColors.textGrey,
            height: 1.5,
          ),
          textAlign: TextAlign.center,
        ),

        const SizedBox(height: 24),

        // Buttons
        if (onRetry != null) ...[
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton(
              onPressed: () {
                Navigator.pop(context);
                onRetry!();
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: color,
                foregroundColor: Colors.white,
                elevation: 0,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              ),
              child: Text(
                retryLabel ?? 'Try Again',
                style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
              ),
            ),
          ),
          const SizedBox(height: 10),
        ],

        SizedBox(
          width: double.infinity,
          height: 48,
          child: TextButton(
            onPressed: () => Navigator.pop(context),
            style: TextButton.styleFrom(
              foregroundColor: const Color(0xFF6B7280),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
              backgroundColor: AppColors.background,
            ),
            child: const Text('OK, Got It',
                style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600)),
          ),
        ),
      ]),
    );
  }
}
