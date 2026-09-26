import 'dart:convert';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../api/api_client.dart';

const _kVendorBaseUrl = 'https://esahlan.com/api/v1';
const _kVendorFcmCacheKey = 'vendor_fcm_token'; // already used by _tryUploadToken

// Top-level — runs inside the background isolate.
Future<void> _bgCheckVendorToken() async {
  try {
    final token = await FirebaseMessaging.instance.getToken();
    if (token == null) return;
    final prefs = await SharedPreferences.getInstance();
    if (prefs.getString(_kVendorFcmCacheKey) == token) return;
    // Read auth token from SharedPreferences (vendor_app stores it under 'vendor_token')
    final auth = prefs.getString('vendor_token');
    if (auth == null) return;
    final dio = Dio();
    await dio.post(
      '$_kVendorBaseUrl/vendor/fcm-token',
      data: {'fcm_token': token},
      options: Options(
        headers: {'Authorization': 'Bearer $auth', 'Accept': 'application/json'},
        sendTimeout: const Duration(seconds: 10),
        receiveTimeout: const Duration(seconds: 10),
      ),
    );
    await prefs.setString(_kVendorFcmCacheKey, token);
    debugPrint('[FCM:BG] Vendor token refreshed ✓');
  } catch (e) {
    debugPrint('[FCM:BG] Vendor token check skipped: $e');
  }
}

// ── Background isolate handler ──────────────────────────────────────────────
@pragma('vm:entry-point')
Future<void> _firebaseBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
  // Re-upload token on every background wake — repairs server-side clears.
  await _bgCheckVendorToken();
  // Notification payload → OS auto-shows in system tray for other types.
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
    FirebaseMessaging.onMessageOpenedApp.listen((msg) {
      _reportOpened(msg.data);
      _onTap(msg);
    });

    // Killed tap: app was killed when user tapped notification
    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null) {
      _reportOpened(initial.data);
      _routeFromData(initial.data);
    }

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

  static const _kLastUploadKey = 'vendor_fcm_uploaded_at';
  static const _kForceIntervalH = 12;

  // ── Internal: upload token — skips only when token matches AND < 12h since last upload ──
  static Future<void> _tryUploadToken([String? token]) async {
    try {
      token ??= await FirebaseMessaging.instance.getToken();
      if (token == null) return;
      final prefs = await SharedPreferences.getInstance();
      final cached   = prefs.getString(_kVendorFcmCacheKey);
      final lastStr  = prefs.getString(_kLastUploadKey);
      final lastAt   = lastStr != null ? DateTime.tryParse(lastStr) : null;
      final stale    = lastAt == null ||
          DateTime.now().difference(lastAt).inHours >= _kForceIntervalH;
      if (cached == token && !stale) return;
      await ApiClient().post('/vendor/fcm-token', data: {'fcm_token': token});
      await prefs.setString(_kVendorFcmCacheKey, token);
      await prefs.setString(_kLastUploadKey, DateTime.now().toIso8601String());
    } catch (_) {}
  }

  // ── Called on app resume to refresh token if stale ────────────────────────
  static Future<void> refreshTokenIfNeeded() => _tryUploadToken();

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

  // ── Report open to backend ─────────────────────────────────────────────────
  static void _reportOpened(Map<String, dynamic> data) {
    final logId = data['notification_log_id'] as String?;
    if (logId == null || logId.isEmpty) return;
    ApiClient().post('/notifications/opened/$logId').catchError((_) {});
  }

  // ── Notification tap handlers ──────────────────────────────────────────────
  static void _onTap(RemoteMessage message) => _routeFromData(message.data);

  static void _onLocalTap(NotificationResponse response) {
    try {
      final data = jsonDecode(response.payload ?? '{}') as Map<String, dynamic>;
      _reportOpened(data);
      _routeFromData(data);
    } catch (_) {}
  }

  static void _routeFromData(Map<String, dynamic> data) {
    final type = data['type'] as String? ?? '';
    final requestId = data['request_id'] as String? ?? data['id'] as String?;
    final deepLink = data['deep_link'] as String? ?? '';
    final tab = Uri.tryParse(deepLink)?.queryParameters['tab'];

    final route = switch (type) {
      'vendor_new_order'    => '/orders',
      'order_confirmed'     => '/orders',
      'order_rejected'      => '/orders',
      'wallet_credit'       => '/wallet',
      'withdrawal_approved' => '/wallet',
      'withdrawal_rejected' => '/wallet',
      // House request notifications → agent requests tab
      'house_request'             => '/agent/requests',
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
    if (requestId != null) {
      _pendingRequestId = requestId;
      _pendingRequestTab = tab;
    }

    // For agent shell tab routes, just store — shell will pick it up on resume.
    // For stack routes (wallet, orders), push normally.
    if (!route.startsWith('/agent/')) {
      navigatorKey?.currentState?.pushNamed(route);
    }
  }

  static String? _pendingRoute;
  static String? _pendingRequestId;
  static String? _pendingRequestTab;

  static String? consumePendingRoute() {
    final r = _pendingRoute;
    _pendingRoute = null;
    return r;
  }

  static ({String id, String? tab})? consumePendingRequest() {
    final id = _pendingRequestId;
    final tab = _pendingRequestTab;
    _pendingRequestId = null;
    _pendingRequestTab = null;
    if (id == null) return null;
    return (id: id, tab: tab);
  }
}
