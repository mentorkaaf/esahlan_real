import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import '../api/api_client.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';

// ═══════════════════════════════════════════════════════════════════════════════
// TOP-LEVEL functions — flutter_local_notifications REQUIRES these to be
// top-level (not class methods) for background isolate execution.
// ═══════════════════════════════════════════════════════════════════════════════

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

@pragma('vm:entry-point')
void localNotifBackgroundTapped(NotificationResponse details) {
  // Background tap navigation is handled by onMessageOpenedApp in main.dart
}

// ═══════════════════════════════════════════════════════════════════════════════

final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
    FlutterLocalNotificationsPlugin();

class FirebaseService {
  static final FirebaseService _instance = FirebaseService._internal();
  factory FirebaseService() => _instance;
  FirebaseService._internal();

  final FirebaseMessaging _messaging = FirebaseMessaging.instance;

  /// Called when a foreground notification is tapped — set from main.dart.
  void Function(String deepLink)? onDeepLink;

  static const _channelId   = AppConstants.fcmChannelId;   // 'esahlan_high_v3'
  static const _channelName = AppConstants.fcmChannelName;
  static const _channelDesc = AppConstants.fcmChannelDesc;

  // ── Initialize ─────────────────────────────────────────────────────────────
  Future<void> initialize() async {
    try {
      // 1. Register background handler (top-level function)
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

      // 2. iOS foreground presentation
      await _messaging.setForegroundNotificationPresentationOptions(
        alert: true, badge: true, sound: true,
      );

      // 3. Request notification permission (Android 13+ and iOS)
      await _messaging.requestPermission(
        alert: true, badge: true, sound: true,
      );

      // 4. Setup flutter_local_notifications
      await _initLocalNotifications();

      // 5. Listen for foreground messages
      FirebaseMessaging.onMessage.listen(_showForegroundNotification);

      // 6. Register FCM token
      await _registerToken();
      _messaging.onTokenRefresh.listen(_uploadToken);

    } catch (e) {
      debugPrint('[FCM] initialize error: $e');
    }
  }

  // ── Setup flutter_local_notifications ─────────────────────────────────────
  Future<void> _initLocalNotifications() async {
    // Create Android notification channel
    const channel = AndroidNotificationChannel(
      _channelId,
      _channelName,
      description: _channelDesc,
      importance: Importance.max,
      enableVibration: true,
      playSound: true,
    );

    await flutterLocalNotificationsPlugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    // Also request Android 13+ notification permission via local_notifications
    await flutterLocalNotificationsPlugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.requestNotificationsPermission();

    // Initialize — 'ic_notification' is the raw drawable name (no @drawable/ prefix)
    const initSettings = InitializationSettings(
      android: AndroidInitializationSettings('ic_notification'),
      iOS: DarwinInitializationSettings(
        requestAlertPermission: false,
        requestBadgePermission: false,
        requestSoundPermission: false,
      ),
    );

    await flutterLocalNotificationsPlugin.initialize(
      initSettings,
      onDidReceiveNotificationResponse: _onForegroundNotifTapped,
      // MUST be top-level function — class method breaks plugin initialization
      onDidReceiveBackgroundNotificationResponse: localNotifBackgroundTapped,
    );
  }

  // ── Show notification when app is in foreground ───────────────────────────
  Future<void> _showForegroundNotification(RemoteMessage message) async {
    try {
      final title = message.notification?.title
          ?? message.data['title'] as String?
          ?? 'eSahlan';
      final body = message.notification?.body
          ?? message.data['body'] as String?
          ?? '';

      debugPrint('[FCM] onMessage → showing foreground notif: $title | $body');

      const androidDetails = AndroidNotificationDetails(
        _channelId,
        _channelName,
        channelDescription: _channelDesc,
        importance: Importance.max,
        priority: Priority.high,
        icon: 'ic_notification',         // raw drawable name, no prefix
        color: Color(0xFF140465),
        enableVibration: true,
        playSound: true,
      );

      const notifDetails = NotificationDetails(
        android: androidDetails,
        iOS: DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
        ),
      );

      // Unique ID per message
      final id = DateTime.now().millisecondsSinceEpoch % 100000;

      await flutterLocalNotificationsPlugin.show(
        id,
        title,
        body,
        notifDetails,
        payload: jsonEncode(message.data),
      );

      debugPrint('[FCM] Foreground notification shown ✓ id=$id');
    } catch (e, st) {
      debugPrint('[FCM] Foreground show error: $e\n$st');
    }
  }

  // ── Notification tap ───────────────────────────────────────────────────────
  void _onForegroundNotifTapped(NotificationResponse details) {
    debugPrint('[FCM] Notification tapped payload: ${details.payload}');
    if (details.payload == null || details.payload!.isEmpty) return;
    try {
      final data = jsonDecode(details.payload!) as Map<String, dynamic>;
      final dl = data['deep_link'] as String?;
      if (dl != null && dl.isNotEmpty) onDeepLink?.call(dl);
    } catch (e) {
      debugPrint('[FCM] Payload parse error: $e');
    }
  }

  // ── Permission (called from UI after first frame) ─────────────────────────
  Future<bool> requestPermissionIfNeeded() async {
    final s = await _messaging.getNotificationSettings();
    return s.authorizationStatus == AuthorizationStatus.authorized;
  }

  // ── Token management ───────────────────────────────────────────────────────
  Future<void> _registerToken() async {
    try {
      final token = await _messaging.getToken();
      if (token != null) {
        debugPrint('[FCM] Token: ${token.substring(0, 20)}...');
        await _uploadToken(token);
      }
    } catch (e) {
      debugPrint('[FCM] Token get error: $e');
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
      final token = await _messaging.getToken();
      if (token != null) await _uploadToken(token, force: true);
    } catch (e) {
      debugPrint('[FCM] Token refresh error: $e');
    }
  }

  Future<String?> getToken() => _messaging.getToken();

  Future<void> deleteToken() async {
    await _messaging.deleteToken();
    await LocalStorage.remove(AppConstants.fcmTokenKey);
  }
}
