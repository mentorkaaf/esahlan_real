import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

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

  /// Called when a foreground notification's deep link should trigger navigation.
  void Function(String deepLink)? onDeepLink;

  // Notification channel — must match AndroidManifest default_notification_channel_id
  static const AndroidNotificationChannel _channel = AndroidNotificationChannel(
    AppConstants.fcmChannelId,   // 'esahlan_high_v3'
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

      // iOS: show notification banner even when app is in foreground
      await _fcm.setForegroundNotificationPresentationOptions(
        alert: true, badge: true, sound: true,
      );

      // Create Android notification channel first, then init plugin
      await _createChannelAndInit();

      // Foreground message listener
      FirebaseMessaging.onMessage.listen(_handleForegroundMessage);

      await _registerToken();
      _fcm.onTokenRefresh.listen(_uploadToken);
    } catch (e) {
      debugPrint('[FCM] Init error: $e');
    }
  }

  // ── Create Android channel and init flutter_local_notifications ──────────
  Future<void> _createChannelAndInit() async {
    // Step 1: Create the channel (must exist before any show() call)
    final android = _localNotif
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>();

    await android?.createNotificationChannel(_channel);

    // Step 2: Initialize plugin
    await _localNotif.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@drawable/ic_notification'),
        iOS: DarwinInitializationSettings(
          requestAlertPermission: false,
          requestBadgePermission: false,
          requestSoundPermission: false,
        ),
      ),
      onDidReceiveNotificationResponse: _onNotificationTapped,
      onDidReceiveBackgroundNotificationResponse: _onNotificationTappedBackground,
    );
  }

  // ── Foreground message → show heads-up notification ───────────────────────
  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    try {
      // Get title/body — prefer notification field, fall back to data
      final title = message.notification?.title
          ?? message.data['title'] as String?
          ?? 'eSahlan';
      final body = message.notification?.body
          ?? message.data['body'] as String?
          ?? '';

      debugPrint('[FCM] Foreground: title=$title body=$body data=${message.data}');

      final notifDetails = NotificationDetails(
        android: AndroidNotificationDetails(
          _channel.id,
          _channel.name,
          channelDescription: _channel.description,
          importance: Importance.max,
          priority: Priority.high,
          icon: '@drawable/ic_notification',
          color: const Color(0xFF140465),
          enableVibration: true,
          playSound: true,
          // Show as heads-up (peek) notification
          fullScreenIntent: false,
          styleInformation: BigTextStyleInformation(
            body,
            contentTitle: title,
            summaryText: 'eSahlan',
          ),
        ),
        iOS: const DarwinNotificationDetails(
          presentAlert: true,
          presentBadge: true,
          presentSound: true,
        ),
      );

      // Use message hash as unique notification id
      final id = (message.messageId ?? message.data['order_id'] ?? '0')
          .hashCode
          .abs() % 100000;

      await _localNotif.show(
        id,
        title,
        body,
        notifDetails,
        payload: jsonEncode(message.data),
      );

      debugPrint('[FCM] Foreground notification shown (id=$id)');
    } catch (e, st) {
      debugPrint('[FCM] Foreground show error: $e\n$st');
    }
  }

  // ── Notification tap handlers ─────────────────────────────────────────────
  void _onNotificationTapped(NotificationResponse details) {
    _handlePayload(details.payload);
  }

  @pragma('vm:entry-point')
  static void _onNotificationTappedBackground(NotificationResponse details) {
    // Background tap is handled by onMessageOpenedApp in main.dart
  }

  void _handlePayload(String? payload) {
    if (payload == null || payload.isEmpty) return;
    try {
      final data = jsonDecode(payload) as Map<String, dynamic>;
      final dl = data['deep_link'] as String?;
      if (dl != null && dl.isNotEmpty) {
        debugPrint('[FCM] Navigating to deep link: $dl');
        onDeepLink?.call(dl);
      }
    } catch (e) {
      debugPrint('[FCM] Payload parse error: $e');
    }
  }

  // ── Permission ─────────────────────────────────────────────────────────────
  Future<bool> requestPermissionIfNeeded() async {
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

    // Android 13+: also request via local notifications plugin
    final android = _localNotif
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>();
    await android?.requestNotificationsPermission();

    debugPrint('[FCM] Permission status: ${settings.authorizationStatus}');
    return settings.authorizationStatus == AuthorizationStatus.authorized;
  }

  // ── Token management ───────────────────────────────────────────────────────
  Future<void> _registerToken() async {
    try {
      final token = await _fcm.getToken();
      debugPrint('[FCM] Device token: $token');
      if (token != null) await _uploadToken(token);
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
      debugPrint('[FCM] Token uploaded to server');
    } catch (e) {
      // Clear local copy so next auth attempt retries
      await LocalStorage.remove(AppConstants.fcmTokenKey);
      debugPrint('[FCM] Token upload failed: $e');
    }
  }

  /// Call after login — forces a fresh token upload.
  Future<void> registerTokenAfterLogin() async {
    await LocalStorage.remove(AppConstants.fcmTokenKey);
    await _registerToken();
  }

  /// Call on app resume — keeps server token fresh.
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
