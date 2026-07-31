import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../api/api_client.dart';

// ── Background isolate handler ──────────────────────────────────────────────
// Called when app is killed or in background. Must be top-level.
@pragma('vm:entry-point')
Future<void> _firebaseBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
  // Notification payload → OS auto-shows in system tray.
  // We do nothing here — OS handles display.
}

// ── Channel constant (must match backend FcmService channel_id) ──────────────
const _kChannelId   = 'esahlan_high_v3';
const _kChannelName = 'Vendor Notifications';
const _kChannelDesc = 'New orders and important alerts';

class VendorFcmService {
  static final _fln = FlutterLocalNotificationsPlugin();
  static GlobalKey<NavigatorState>? navigatorKey;

  // Notification permission status — set in init(), read by UI
  static bool notificationsEnabled = true;

  static Future<void> init() async {
    await Firebase.initializeApp();

    // Background handler — MUST be registered before runApp
    FirebaseMessaging.onBackgroundMessage(_firebaseBackgroundHandler);

    // Create high-importance Android channel
    final androidPlugin = _fln
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();

    await androidPlugin?.createNotificationChannel(const AndroidNotificationChannel(
      _kChannelId,
      _kChannelName,
      description: _kChannelDesc,
      importance: Importance.max,
      playSound: true,
      enableVibration: true,
      showBadge: true,
    ));

    // Init flutter_local_notifications
    await _fln.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      ),
      onDidReceiveNotificationResponse: _onLocalTap,
    );

    // Request FCM permission (Android 13+ + iOS)
    final settings = await FirebaseMessaging.instance.requestPermission(
      alert: true, badge: true, sound: true, provisional: false,
    );
    notificationsEnabled =
        settings.authorizationStatus == AuthorizationStatus.authorized ||
        settings.authorizationStatus == AuthorizationStatus.provisional;

    // Also explicitly request POST_NOTIFICATIONS on Android 13+
    if (!notificationsEnabled) {
      final granted = await androidPlugin?.requestNotificationsPermission();
      notificationsEnabled = granted ?? notificationsEnabled;
    }

    // Foreground: Firebase does NOT auto-show → we show via flutter_local_notifications
    FirebaseMessaging.onMessage.listen(_onForeground);

    // Background tap: app was backgrounded when user tapped notification
    FirebaseMessaging.onMessageOpenedApp.listen(_onTap);

    // Killed tap: app was killed when user tapped notification
    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null) _routeFromData(initial.data);

    // Register FCM token (best-effort; will 401 if not logged in yet)
    await _tryUploadToken();
    FirebaseMessaging.instance.onTokenRefresh.listen(_tryUploadToken);
  }

  // ── Called after successful login ──────────────────────────────────────────
  static Future<void> forceRegisterToken() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null) return;
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('vendor_fcm_token'); // clear cache → force re-upload
      await prefs.setString('vendor_fcm_token', token);
      await ApiClient().post('/vendor/fcm-token', data: {'fcm_token': token});
    } catch (_) {}
  }

  // ── Called on logout ───────────────────────────────────────────────────────
  static Future<void> clearToken() async {
    try {
      await ApiClient().post('/vendor/fcm-token', data: {'fcm_token': null});
    } catch (_) {}
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove('vendor_fcm_token');
      await FirebaseMessaging.instance.deleteToken();
    } catch (_) {}
  }

  // ── Send test notification (calls backend endpoint) ────────────────────────
  static Future<bool> sendTestNotification() async {
    try {
      final res = await ApiClient().post('/vendor/test-notification');
      return res['data']?['sent'] == true;
    } catch (_) {
      return false;
    }
  }

  // ── Internal: upload token (skips if same as cached) ──────────────────────
  static Future<void> _tryUploadToken([String? token]) async {
    try {
      token ??= await FirebaseMessaging.instance.getToken();
      if (token == null) return;
      final prefs = await SharedPreferences.getInstance();
      if (prefs.getString('vendor_fcm_token') == token) return;
      await prefs.setString('vendor_fcm_token', token);
      await ApiClient().post('/vendor/fcm-token', data: {'fcm_token': token});
    } catch (_) {}
  }

  // ── Show notification in foreground ───────────────────────────────────────
  static void _onForeground(RemoteMessage message) {
    final n = message.notification;
    final title = n?.title ?? message.data['title'] as String? ?? 'New notification';
    final body  = n?.body  ?? message.data['body']  as String? ?? '';

    _fln.show(
      message.hashCode,
      title,
      body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          _kChannelId,
          _kChannelName,
          channelDescription: _kChannelDesc,
          importance: Importance.max,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
          color: Color(0xFFFF6B35),
          playSound: true,
          enableVibration: true,
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }

  // ── Notification tap handlers ──────────────────────────────────────────────
  static void _onTap(RemoteMessage message) => _routeFromData(message.data);

  static void _onLocalTap(NotificationResponse response) {
    try {
      final data = jsonDecode(response.payload ?? '{}') as Map<String, dynamic>;
      _routeFromData(data);
    } catch (_) {}
  }

  static void _routeFromData(Map<String, dynamic> data) {
    final type = data['type'] as String? ?? '';
    final route = switch (type) {
      'vendor_new_order'    => '/orders',
      'order_confirmed'     => '/orders',
      'order_rejected'      => '/orders',
      'wallet_credit'       => '/wallet',
      'withdrawal_approved' => '/wallet',
      'withdrawal_rejected' => '/wallet',
      // House request notifications → agent requests tab
      'house_request_assigned'    => '/agent/requests',
      'offer_accepted'            => '/agent/requests',
      'offer_rejected'            => '/agent/requests',
      'offer_countered'           => '/agent/requests',
      'viewing_confirmed'         => '/agent/requests',
      'viewing_cancelled'         => '/agent/requests',
      'request_message_agent'     => '/agent/requests',
      _ => data['deep_link'] as String? ?? '/orders',
    };
    _pendingRoute = route;
    // For agent shell tab routes, just store — shell will pick it up on resume.
    // For stack routes (wallet, orders), push normally.
    if (!route.startsWith('/agent/')) {
      navigatorKey?.currentState?.pushNamed(route);
    }
  }

  static String? _pendingRoute;
  static String? consumePendingRoute() {
    final r = _pendingRoute;
    _pendingRoute = null;
    return r;
  }
}
