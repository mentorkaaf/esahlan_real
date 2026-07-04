import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:path_provider/path_provider.dart';

import '../api/api_client.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';
import '../theme/app_theme.dart';

// ─────────────────────────────────────────────────────────────────────────────
// BACKGROUND HANDLER — must be top-level, called in native isolate
// Registered via FirebaseMessaging.onBackgroundMessage() in main() before runApp()
// ─────────────────────────────────────────────────────────────────────────────
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  // Notifications with a 'notification' object are displayed by Android system.
  // Data-only messages land here too — no manual display needed unless desired.
  debugPrint('[FCM:BG] Received: ${message.messageId}');
}

// ─────────────────────────────────────────────────────────────────────────────
// FirebaseService — singleton
// ─────────────────────────────────────────────────────────────────────────────
class FirebaseService {
  static final FirebaseService _instance = FirebaseService._internal();
  factory FirebaseService() => _instance;
  FirebaseService._internal();

  final FirebaseMessaging _fcm = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotif =
      FlutterLocalNotificationsPlugin();

  bool _initialized = false;
  StreamSubscription<RemoteMessage>? _fgSubscription;

  /// Called by main() BEFORE runApp() to register the native-level handlers.
  /// Must NOT touch flutter_local_notifications here — plugin is not bound yet.
  static Future<void> setupBeforeRunApp() async {
    // Background handler registration is native — safe before runApp()
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

    // iOS foreground presentation — FCM config, not a plugin method call
    await FirebaseMessaging.instance.setForegroundNotificationPresentationOptions(
      alert: true,
      badge: true,
      sound: true,
    );
  }

  /// Called from widget initState() AFTER runApp() — plugin registry is ready.
  Future<void> initialize() async {
    if (_initialized) return;

    try {
      // 1. Create Android notification channel (plugin bound after runApp)
      await _localNotif
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.createNotificationChannel(_notifChannel);

      // 2. Initialize flutter_local_notifications
      await _localNotif.initialize(
        const InitializationSettings(
          android: AndroidInitializationSettings('ic_notification'),
          iOS: DarwinInitializationSettings(
            requestAlertPermission: false,
            requestBadgePermission: false,
            requestSoundPermission: false,
          ),
        ),
        onDidReceiveNotificationResponse: _onNotificationTap,
      );

      // 3. Subscribe to foreground FCM messages
      await _fgSubscription?.cancel();
      _fgSubscription = FirebaseMessaging.onMessage.listen(
        _handleForegroundMessage,
        onError: (e) => debugPrint('[FCM] onMessage error: $e'),
      );

      // 4. Register FCM token
      await _registerToken();
      _fcm.onTokenRefresh.listen(_uploadToken);

      _initialized = true;
      debugPrint('[FCM] Initialized ✓');
    } catch (e, st) {
      debugPrint('[FCM] Init error: $e\n$st');
    }
  }

  // ── Android notification channel ──────────────────────────────────────────
  static const AndroidNotificationChannel _notifChannel =
      AndroidNotificationChannel(
    AppConstants.fcmChannelId,
    AppConstants.fcmChannelName,
    description: AppConstants.fcmChannelDesc,
    importance: Importance.max,
    enableVibration: true,
    playSound: true,
    showBadge: true,
  );

  // ── Notification tap callback (foreground local notifications) ─────────────
  void Function(String deepLink)? onDeepLink;

  void _onNotificationTap(NotificationResponse response) {
    if (response.payload == null || response.payload!.isEmpty) return;
    try {
      final data = jsonDecode(response.payload!) as Map<String, dynamic>;
      final dl = data['deep_link'] as String?;
      if (dl != null && dl.isNotEmpty) {
        debugPrint('[FCM] Notification tapped → $dl');
        onDeepLink?.call(dl);
      }
    } catch (e) {
      debugPrint('[FCM] Tap parse error: $e');
    }
  }

  // ── Foreground message handler ────────────────────────────────────────────
  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    debugPrint('[FCM:FG] Received: ${message.messageId}');
    debugPrint('[FCM:FG] Title: ${message.notification?.title} | Body: ${message.notification?.body}');
    debugPrint('[FCM:FG] Data: ${message.data}');

    final title = message.notification?.title
        ?? message.data['title'] as String?
        ?? AppConstants.appName;
    final body = message.notification?.body
        ?? message.data['body'] as String?
        ?? '';

    if (title.isEmpty && body.isEmpty) {
      debugPrint('[FCM:FG] Empty notification — skipping');
      return;
    }

    // Resolve image URL from multiple possible sources
    final imageUrl = message.notification?.android?.imageUrl
        ?? message.notification?.apple?.imageUrl
        ?? message.data['image'] as String?
        ?? message.data['image_url'] as String?;

    // Download image if present
    final imageFile = imageUrl != null && imageUrl.isNotEmpty
        ? await _downloadImage(imageUrl)
        : null;

    try {
      AndroidNotificationDetails androidDetails;

      if (imageFile != null) {
        // Rich notification with BigPicture
        androidDetails = AndroidNotificationDetails(
          _notifChannel.id,
          _notifChannel.name,
          channelDescription: _notifChannel.description,
          importance: Importance.max,
          priority: Priority.high,
          icon: 'ic_notification',
          color: AppColors.secondary,
          enableVibration: true,
          playSound: true,
          largeIcon: FilePathAndroidBitmap(imageFile.path),
          styleInformation: BigPictureStyleInformation(
            FilePathAndroidBitmap(imageFile.path),
            contentTitle: title,
            summaryText: body,
            htmlFormatContentTitle: false,
            htmlFormatSummaryText: false,
            hideExpandedLargeIcon: false,
          ),
        );
        debugPrint('[FCM:FG] Showing with image: ${imageFile.path}');
      } else {
        // Text-only notification
        androidDetails = AndroidNotificationDetails(
          _notifChannel.id,
          _notifChannel.name,
          channelDescription: _notifChannel.description,
          importance: Importance.max,
          priority: Priority.high,
          icon: 'ic_notification',
          color: AppColors.secondary,
          enableVibration: true,
          playSound: true,
        );
      }

      await _localNotif.show(
        DateTime.now().millisecondsSinceEpoch % 2147483647,
        title,
        body,
        NotificationDetails(
          android: androidDetails,
          iOS: const DarwinNotificationDetails(
            presentAlert: true,
            presentBadge: true,
            presentSound: true,
            attachments: [],
          ),
        ),
        payload: jsonEncode(message.data),
      );
      debugPrint('[FCM:FG] Notification shown ✓');
    } catch (e, st) {
      debugPrint('[FCM:FG] show() error: $e\n$st');
    }
  }

  // ── Download image to temp dir ────────────────────────────────────────────
  Future<File?> _downloadImage(String url) async {
    try {
      final dir = await getTemporaryDirectory();
      // Use URL hash as filename to reuse cached downloads
      final filename = 'fcm_img_${url.hashCode.abs()}.jpg';
      final file = File('${dir.path}/$filename');
      if (await file.exists()) {
        debugPrint('[FCM] Using cached image: $filename');
        return file;
      }
      final dio = Dio();
      final response = await dio.get<List<int>>(
        url,
        options: Options(responseType: ResponseType.bytes, sendTimeout: const Duration(seconds: 10), receiveTimeout: const Duration(seconds: 10)),
      );
      await file.writeAsBytes(response.data!);
      debugPrint('[FCM] Image downloaded: $filename (${response.data!.length} bytes)');
      return file;
    } catch (e) {
      debugPrint('[FCM] Image download failed: $e');
      return null;
    }
  }

  // ── Permission ────────────────────────────────────────────────────────────
  /// Call from a post-frame callback (after UI is ready) to show system dialog.
  /// On Android 13+  the system dialog is shown by requestNotificationsPermission().
  /// On Android <13  there is no runtime permission — notifications are always allowed.
  /// On iOS          Firebase shows its own dialog.
  Future<bool> requestPermissionIfNeeded() async {
    // iOS / macOS — Firebase handles the dialog
    await _fcm.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    // Android 13+ (API 33+) — flutter_local_notifications shows the system dialog.
    // Safe to call every launch: if permission is already granted the OS does nothing.
    try {
      await _localNotif
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.requestNotificationsPermission();
    } catch (e) {
      debugPrint('[FCM] Android permission request error: $e');
    }

    final settings = await _fcm.getNotificationSettings();
    final granted = settings.authorizationStatus == AuthorizationStatus.authorized
        || settings.authorizationStatus == AuthorizationStatus.provisional;
    debugPrint('[FCM] Permission status: ${settings.authorizationStatus}');
    return granted;
  }

  // ── Token management ──────────────────────────────────────────────────────
  Future<void> _registerToken() async {
    try {
      final token = await _fcm.getToken();
      if (token == null) {
        debugPrint('[FCM] getToken() returned null');
        return;
      }
      debugPrint('[FCM] Token: ${token.substring(0, 20)}...');
      await _uploadToken(token);
    } catch (e) {
      debugPrint('[FCM] Token registration error: $e');
    }
  }

  Future<void> _uploadToken(String token) async {
    final stored = await LocalStorage.getString(AppConstants.fcmTokenKey);
    if (stored == token) {
      debugPrint('[FCM] Token unchanged — skipping upload');
      return;
    }
    try {
      await ApiClient.instance.post('/auth/fcm-token', data: {'fcm_token': token});
      await LocalStorage.saveString(AppConstants.fcmTokenKey, token);
      debugPrint('[FCM] Token uploaded ✓');
    } catch (e) {
      // Clear cached token so next launch retries
      await LocalStorage.remove(AppConstants.fcmTokenKey);
      debugPrint('[FCM] Token upload failed: $e');
    }
  }

  /// Force-refresh and re-upload token (called on app resume).
  Future<void> refreshTokenIfNeeded() async {
    if (!_initialized) return;
    try {
      final token = await _fcm.getToken();
      if (token == null) return;
      final stored = await LocalStorage.getString(AppConstants.fcmTokenKey);
      if (stored != token) {
        debugPrint('[FCM] Token changed — re-uploading');
        await _uploadToken(token);
      }
    } catch (e) {
      debugPrint('[FCM] Refresh error: $e');
    }
  }

  /// Call after login so the current user's token is registered.
  Future<void> registerTokenAfterLogin() async {
    await LocalStorage.remove(AppConstants.fcmTokenKey);
    await _registerToken();
  }

  Future<String?> getToken() => _fcm.getToken();

  Future<void> deleteToken() async {
    try {
      await _fcm.deleteToken();
    } catch (_) {}
    await LocalStorage.remove(AppConstants.fcmTokenKey);
  }
}
