import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../api/api_client.dart';

// Must be top-level — called when app is killed/background
@pragma('vm:entry-point')
Future<void> _firebaseBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  // FCM shows the notification automatically (notification payload present).
  // No need to show local notification here — system handles it.
}

class VendorFcmService {
  static final _fln = FlutterLocalNotificationsPlugin();

  // Channel ID must match backend FcmService 'channel_id' => 'esahlan_high_v3'
  static const _channelId   = 'esahlan_high_v3';
  static const _channelName = 'Vendor Notifications';
  static const _channelDesc = 'New orders and important alerts';

  static final _channel = AndroidNotificationChannel(
    _channelId,
    _channelName,
    description: _channelDesc,
    importance: Importance.max,
    playSound: true,
    enableVibration: true,
    showBadge: true,
  );

  // Navigator key — set from main.dart, used for notification tap routing
  static GlobalKey<NavigatorState>? navigatorKey;

  static Future<void> init() async {
    await Firebase.initializeApp();

    // Register background handler
    FirebaseMessaging.onBackgroundMessage(_firebaseBackgroundHandler);

    // Create notification channel on Android
    final androidPlugin = _fln
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    await androidPlugin?.createNotificationChannel(_channel);

    // Request Android 13+ POST_NOTIFICATIONS permission via the plugin
    await androidPlugin?.requestNotificationsPermission();

    // Init flutter_local_notifications
    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    await _fln.initialize(
      const InitializationSettings(android: androidSettings),
      onDidReceiveNotificationResponse: _onLocalNotificationTap,
    );

    // Request FCM permission (iOS + Android 13)
    await FirebaseMessaging.instance.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );

    // Foreground message — show heads-up via flutter_local_notifications
    FirebaseMessaging.onMessage.listen(_onForegroundMessage);

    // App opened from background via notification tap
    FirebaseMessaging.onMessageOpenedApp.listen(_onMessageOpenedApp);

    // App was killed — check if launched via notification tap
    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null) _routeFromData(initial.data);

    // Register FCM token (best-effort; no auth yet — will retry after login)
    await _registerToken();

    // Re-register on token refresh
    FirebaseMessaging.instance.onTokenRefresh.listen(_uploadToken);
  }

  /// Call after successful login — forces re-upload even if token unchanged.
  static Future<void> forceRegisterToken() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null) return;
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('vendor_fcm_token'); // clear cache so upload always runs
      await prefs.setString('vendor_fcm_token', token);
      await ApiClient().post('/auth/fcm-token', data: {'fcm_token': token});
    } catch (_) {}
  }

  static Future<void> _registerToken() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _uploadToken(token);
    } catch (_) {}
  }

  static Future<void> _uploadToken(String token) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final cached = prefs.getString('vendor_fcm_token');
      if (cached == token) return; // unchanged
      await prefs.setString('vendor_fcm_token', token);
      await ApiClient().post('/auth/fcm-token', data: {'fcm_token': token});
    } catch (_) {}
  }

  // ── Foreground: show heads-up notification ──────────────────────────────────
  static void _onForegroundMessage(RemoteMessage message) {
    final n = message.notification;
    if (n == null) return;

    _fln.show(
      message.hashCode,
      n.title,
      n.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          _channelId,
          _channelName,
          channelDescription: _channelDesc,
          importance: Importance.max,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
          color: const Color(0xFFFF6B35),
          playSound: true,
          enableVibration: true,
          styleInformation: BigTextStyleInformation(n.body ?? ''),
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }

  // ── Background tap — app was in background ──────────────────────────────────
  static void _onMessageOpenedApp(RemoteMessage message) {
    _routeFromData(message.data);
  }

  // ── Foreground local notification tap ───────────────────────────────────────
  static void _onLocalNotificationTap(NotificationResponse response) {
    try {
      final data = jsonDecode(response.payload ?? '{}') as Map<String, dynamic>;
      _routeFromData(data);
    } catch (_) {}
  }

  // ── Route to the correct screen based on notification data ─────────────────
  static void _routeFromData(Map<String, dynamic> data) {
    final type = data['type'] as String? ?? '';
    final route = switch (type) {
      'vendor_new_order'  => '/orders',
      'order_confirmed'   => '/orders',
      'order_rejected'    => '/orders',
      'wallet_credit'     => '/wallet',
      'withdrawal_approved' => '/wallet',
      'withdrawal_rejected' => '/wallet',
      _ => data['deep_link'] as String? ?? '/orders',
    };
    _pendingRoute = route;
    // If navigator is available, navigate immediately
    navigatorKey?.currentState?.pushNamed(route);
  }

  static String? _pendingRoute;
  static String? consumePendingRoute() {
    final r = _pendingRoute;
    _pendingRoute = null;
    return r;
  }

  /// Clear token on logout so next login re-registers cleanly
  static Future<void> clearToken() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('vendor_fcm_token');
      await FirebaseMessaging.instance.deleteToken();
    } catch (_) {}
  }
}
