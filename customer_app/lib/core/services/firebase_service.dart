import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../api/api_client.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';

// Top-level — required by firebase_messaging for background isolate
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

  // ── Initialize ─────────────────────────────────────────────────────────────
  Future<void> initialize() async {
    try {
      // Must be registered before runApp()
      FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);

      // iOS foreground banners
      await _fcm.setForegroundNotificationPresentationOptions(
        alert: true, badge: true, sound: true,
      );

      // Setup local notifications (channel + plugin init)
      await _setupLocalNotifications();

      // Listen for foreground messages
      FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

      // Register token
      await _registerToken();
      _fcm.onTokenRefresh.listen(_uploadToken);

    } catch (e) {
      debugPrint('[FCM] Init error: $e');
    }
  }

  // ── Setup flutter_local_notifications ─────────────────────────────────────
  Future<void> _setupLocalNotifications() async {
    // 1. Create Android channel first
    await _localNotif
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_channel);

    // 2. Initialize plugin
    // 'ic_notification' = raw drawable name, no @drawable/ prefix
    // DO NOT request permission here — this runs before runApp()
    // Permission is requested later via requestPermissionIfNeeded() post-frame
    await _localNotif.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('ic_notification'),
        iOS: DarwinInitializationSettings(
          requestAlertPermission: false,
          requestBadgePermission: false,
          requestSoundPermission: false,
        ),
      ),
      onDidReceiveNotificationResponse: (details) {
        if (details.payload == null || details.payload!.isEmpty) return;
        try {
          final data = jsonDecode(details.payload!) as Map<String, dynamic>;
          final dl = data['deep_link'] as String?;
          if (dl != null && dl.isNotEmpty) onDeepLink?.call(dl);
        } catch (_) {}
      },
    );
  }

  // ── Foreground notification handler ────────────────────────────────────────
  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    try {
      final title = message.notification?.title
          ?? message.data['title'] as String?
          ?? 'eSahlan';
      final body = message.notification?.body
          ?? message.data['body'] as String?
          ?? '';

      debugPrint('[FCM] Foreground: "$title" | "$body"');

      await _localNotif.show(
        DateTime.now().millisecondsSinceEpoch % 100000,
        title,
        body,
        NotificationDetails(
          android: AndroidNotificationDetails(
            _channel.id,
            _channel.name,
            channelDescription: _channel.description,
            importance: Importance.max,
            priority: Priority.high,
            icon: 'ic_notification',
            color: const Color(0xFF140465),
            enableVibration: true,
            playSound: true,
          ),
          iOS: const DarwinNotificationDetails(
            presentAlert: true,
            presentBadge: true,
            presentSound: true,
          ),
        ),
        payload: jsonEncode(message.data),
      );

      debugPrint('[FCM] Foreground notification shown ✓');
    } catch (e, st) {
      debugPrint('[FCM] Foreground error: $e\n$st');
    }
  }

  // ── Permission ─────────────────────────────────────────────────────────────
  Future<bool> requestPermissionIfNeeded() async {
    const key = 'notif_permission_v4';
    final asked = await LocalStorage.getBool(key);

    if (!asked) {
      final s = await _fcm.requestPermission(
        alert: true, badge: true, sound: true,
      );
      await LocalStorage.saveBool(key, true);

      // Android 13+
      await _localNotif
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.requestNotificationsPermission();

      debugPrint('[FCM] Permission: ${s.authorizationStatus}');
      return s.authorizationStatus == AuthorizationStatus.authorized;
    }

    final s = await _fcm.getNotificationSettings();
    return s.authorizationStatus == AuthorizationStatus.authorized;
  }

  // ── Token ──────────────────────────────────────────────────────────────────
  Future<void> _registerToken() async {
    try {
      final token = await _fcm.getToken();
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
      debugPrint('[FCM] Token uploaded ✓');
    } catch (e) {
      await LocalStorage.remove(AppConstants.fcmTokenKey);
      debugPrint('[FCM] Token upload failed: $e');
    }
  }

  Future<void> registerTokenAfterLogin() async {
    await LocalStorage.remove(AppConstants.fcmTokenKey);
    await _registerToken();
  }

  Future<void> refreshTokenIfNeeded() async {
    try {
      final token = await _fcm.getToken();
      if (token != null) await _uploadToken(token, force: true);
    } catch (e) {
      debugPrint('[FCM] Refresh error: $e');
    }
  }

  Future<String?> getToken() => _fcm.getToken();

  Future<void> deleteToken() async {
    await _fcm.deleteToken();
    await LocalStorage.remove(AppConstants.fcmTokenKey);
  }
}
