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

// ── Background isolate handler ────────────────────────────────────────────────
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

class FirebaseService {
  static final FirebaseService _instance = FirebaseService._internal();
  factory FirebaseService() => _instance;
  FirebaseService._internal();

  final FirebaseMessaging _fcm = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotif =
      FlutterLocalNotificationsPlugin();

  /// Called when a LOCAL notification (shown while app is open) is tapped.
  /// Set this from main.dart so navigation can use Riverpod.
  void Function(String deepLink)? onDeepLink;

  static const AndroidNotificationChannel _channel = AndroidNotificationChannel(
    AppConstants.fcmChannelId,
    AppConstants.fcmChannelName,
    description: AppConstants.fcmChannelDesc,
    importance: Importance.max,
    enableVibration: true,
    playSound: true,
    showBadge: true,
  );

  // ── Initialize (called once in main before runApp) ─────────────────────────
  Future<void> initialize() async {
    try {
      FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

      await _fcm.setForegroundNotificationPresentationOptions(
        alert: true, badge: true, sound: true,
      );

      await _setupLocalNotifications();

      // Show notification while app is open
      FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

      await _registerToken();
      _fcm.onTokenRefresh.listen(_uploadToken);
    } catch (e) {
      debugPrint('[FCM] Init error: $e');
    }
  }

  // ── Foreground: show via flutter_local_notifications ──────────────────────
  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    final notif = message.notification;
    if (notif == null) return;
    debugPrint('[FCM] Foreground message: ${notif.title} data:${message.data}');

    // image_url is always in data (added by backend) — most reliable source
    final imageUrl = (message.data['image_url'] as String?)?.isNotEmpty == true
        ? message.data['image_url'] as String
        : (notif.android?.imageUrl ?? notif.apple?.imageUrl);

    AndroidNotificationDetails android;
    if (imageUrl != null && imageUrl.isNotEmpty) {
      final localPath = await _downloadImage(imageUrl);
      if (localPath != null) {
        android = AndroidNotificationDetails(
          _channel.id, _channel.name,
          channelDescription: _channel.description,
          importance: Importance.max,
          priority: Priority.high,
          icon: '@drawable/ic_notification',
          color: const Color(0xFF140465),
          styleInformation: BigPictureStyleInformation(
            FilePathAndroidBitmap(localPath),
            largeIcon: FilePathAndroidBitmap(localPath),
            contentTitle: notif.title,
            summaryText: notif.body,
            hideExpandedLargeIcon: false,
          ),
        );
      } else {
        android = _simpleAndroid();
      }
    } else {
      android = _simpleAndroid();
    }

    try {
      await _localNotif.show(
        notif.hashCode,
        notif.title,
        notif.body,
        NotificationDetails(
          android: android,
          iOS: const DarwinNotificationDetails(
            presentAlert: true, presentBadge: true, presentSound: true,
          ),
        ),
        payload: jsonEncode(message.data),
      );
      debugPrint('[FCM] Local notification shown');
    } catch (e) {
      debugPrint('[FCM] Show error: $e');
    }
  }

  AndroidNotificationDetails _simpleAndroid() => AndroidNotificationDetails(
        _channel.id, _channel.name,
        channelDescription: _channel.description,
        importance: Importance.max,
        priority: Priority.high,
        icon: '@drawable/ic_notification',
        color: const Color(0xFF140465),
      );

  // ── Download image to temp file ────────────────────────────────────────────
  Future<String?> _downloadImage(String url) async {
    try {
      final dir = await getTemporaryDirectory();
      final file = File('${dir.path}/fcm_${url.hashCode}.jpg');
      if (await file.exists()) return file.path;
      await Dio().download(url, file.path,
          options: Options(receiveTimeout: const Duration(seconds: 10)));
      return file.path;
    } catch (e) {
      debugPrint('[FCM] Image download failed: $e');
      return null;
    }
  }

  // ── Local notification tap handler ─────────────────────────────────────────
  void _setupLocalNotificationTapHandler() {
    // already set up in initialize via _setupLocalNotifications
  }

  // ── Setup flutter_local_notifications ─────────────────────────────────────
  Future<void> _setupLocalNotifications() async {
    // Create channel FIRST (must exist before any notification is shown)
    await _localNotif
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_channel);

    await _localNotif.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
        iOS: DarwinInitializationSettings(
          requestAlertPermission: false,
          requestBadgePermission: false,
          requestSoundPermission: false,
        ),
      ),
      onDidReceiveNotificationResponse: (details) {
        debugPrint('[FCM] Local notif tapped payload: ${details.payload}');
        if (details.payload == null || details.payload!.isEmpty) return;
        try {
          final data = jsonDecode(details.payload!) as Map<String, dynamic>;
          final dl = data['deep_link'] as String?;
          debugPrint('[FCM] Deep link from local notif: $dl');
          if (dl != null && dl.isNotEmpty) {
            onDeepLink?.call(dl);
          }
        } catch (e) {
          debugPrint('[FCM] Payload parse error: $e');
        }
      },
    );
  }

  // ── Permission ─────────────────────────────────────────────────────────────
  Future<bool> requestPermissionIfNeeded() async {
    // Use v3 key so previous denials are re-asked after app update
    const permKey = 'notif_permission_asked_v3';
    final already = await LocalStorage.getBool(permKey);
    if (already) {
      final s = await _fcm.getNotificationSettings();
      return s.authorizationStatus == AuthorizationStatus.authorized;
    }

    final settings = await _fcm.requestPermission(
      alert: true, badge: true, sound: true, provisional: false,
    );
    await LocalStorage.saveBool(permKey, true);

    // Android 13+
    final android = _localNotif
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>();
    await android?.requestNotificationsPermission();

    debugPrint('[FCM] Permission status: ${settings.authorizationStatus}');
    return settings.authorizationStatus == AuthorizationStatus.authorized;
  }

  // ── Token ──────────────────────────────────────────────────────────────────
  Future<void> _registerToken() async {
    try {
      final token = await _fcm.getToken();
      debugPrint('[FCM] Token: $token');
      if (token != null) await _uploadToken(token);
    } catch (e) {
      debugPrint('[FCM] Token error: $e');
    }
  }

  Future<void> _uploadToken(String token, {bool force = false}) async {
    if (!force) {
      final stored = await LocalStorage.getString(AppConstants.fcmTokenKey);
      if (stored == token) return;
    }
    try {
      await ApiClient.instance.post('/auth/fcm-token', data: {'fcm_token': token});
      await LocalStorage.saveString(AppConstants.fcmTokenKey, token);
      debugPrint('[FCM] Token uploaded');
    } catch (e) {
      // Upload failed (e.g. not logged in yet) — clear local cache so next
      // authenticated upload attempt will retry
      await LocalStorage.remove(AppConstants.fcmTokenKey);
      debugPrint('[FCM] Token upload failed: $e');
    }
  }

  /// Call this after login to force-upload the current FCM token.
  Future<void> registerTokenAfterLogin() async {
    await LocalStorage.remove(AppConstants.fcmTokenKey);
    await _registerToken();
  }

  /// Call this when the app resumes from background to keep the token fresh.
  Future<void> refreshTokenIfNeeded() async {
    try {
      final token = await _fcm.getToken();
      if (token != null) await _uploadToken(token, force: true);
    } catch (e) {
      debugPrint('[FCM] Token refresh error: $e');
    }
  }

  Future<String?> getToken() => _fcm.getToken();

  Future<void> deleteToken() async {
    await _fcm.deleteToken();
    await LocalStorage.remove(AppConstants.fcmTokenKey);
  }
}
