import 'dart:io';
import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:dio/dio.dart';

/// Checks whether the server requires a force update.
class AppUpdateChecker {
  static final _dio = Dio(BaseOptions(
    baseUrl: 'https://esahlan.com/api/v1',
    connectTimeout: const Duration(seconds: 10),
    receiveTimeout: const Duration(seconds: 10),
    headers: {'Accept': 'application/json'},
  ));

  static Future<void> check(BuildContext context, String appType) async {
    try {
      final info = await PackageInfo.fromPlatform();
      final currentVersion = info.version;

      final resp = await _dio.get(
        '/app/version-check',
        queryParameters: {'app': appType, 'version': currentVersion},
      );

      final body = resp.data as Map?;
      final data = body?['data'] as Map?;
      if (data == null) return;
      if (data['force_update'] != true) return;
      if (!context.mounted) return;

      final message  = (data['update_message'] as String?) ??
          'A new version is required. Please update to continue.';
      final androidUrl = data['android_url'] as String?;
      final iosUrl     = data['ios_url']     as String?;
      final storeUrl   = Platform.isIOS ? iosUrl : androidUrl;

      await showDialog(
        context: context,
        barrierDismissible: false,
        barrierColor: Colors.black.withOpacity(0.85),
        builder: (ctx) => PopScope(
          canPop: false,
          child: _ForceUpdateDialog(
            message:       message,
            storeUrl:      storeUrl,
            latestVersion: data['latest_version'] as String? ?? '',
          ),
        ),
      );
    } catch (e) {
      debugPrint('[AppUpdateChecker] check failed (non-fatal): $e');
    }
  }
}

class _ForceUpdateDialog extends StatelessWidget {
  final String message;
  final String? storeUrl;
  final String latestVersion;

  const _ForceUpdateDialog({
    required this.message,
    required this.storeUrl,
    required this.latestVersion,
  });

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(24)),
      insetPadding: const EdgeInsets.symmetric(horizontal: 28, vertical: 40),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(24, 28, 24, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 72, height: 72,
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF8B5CF6), Color(0xFF6D28D9)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(20),
              ),
              child: const Icon(Icons.system_update_rounded,
                  color: Colors.white, size: 36),
            ),
            const SizedBox(height: 20),
            const Text(
              'Update Required',
              style: TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
              textAlign: TextAlign.center,
            ),
            if (latestVersion.isNotEmpty) ...[
              const SizedBox(height: 6),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
                decoration: BoxDecoration(
                  color: const Color(0xFF8B5CF6).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  'Version $latestVersion available',
                  style: const TextStyle(
                    fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF8B5CF6)),
                ),
              ),
            ],
            const SizedBox(height: 16),
            Text(
              message,
              style: const TextStyle(fontSize: 14, color: Color(0xFF6B7280), height: 1.5),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 28),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: storeUrl != null
                    ? () async {
                        final uri = Uri.parse(storeUrl!);
                        if (await canLaunchUrl(uri)) {
                          await launchUrl(uri, mode: LaunchMode.externalApplication);
                        }
                      }
                    : null,
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF8B5CF6),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14)),
                  elevation: 0,
                ),
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.download_rounded, size: 20),
                    SizedBox(width: 8),
                    Text('Update Now',
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              Platform.isIOS ? 'Opens App Store' : 'Opens Google Play Store',
              style: const TextStyle(fontSize: 11, color: Color(0xFF9CA3AF)),
            ),
          ],
        ),
      ),
    );
  }
}
