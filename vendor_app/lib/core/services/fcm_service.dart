import 'dart:convert';
import 'package:flutter/material.dart' show Color;
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../api/api_client.dart';

// Background message handler — must be top-level function
@pragma('vm:entry-point')
Future<void> _onBackgroundMessage(RemoteMessage message) async {
  await Firebase.initializeApp();
}

class VendorFcmService {
  static final _fln = FlutterLocalNotificationsPlugin();
  static const _channel = AndroidNotificationChannel(
    'vendor_high', 'Vendor Notifications',
    description: 'New orders and store activity',
    importance: Importance.max,
    playSound: true,
    enableVibration: true,
  );

  static Future<void> init() async {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(_onBackgroundMessage);

    // Local notifications setup
    await _fln.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(_channel);

    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    await _fln.initialize(
      const InitializationSettings(android: androidInit),
      onDidReceiveNotificationResponse: _onNotificationTap,
    );

    // Request permission
    await FirebaseMessaging.instance.requestPermission(alert: true, badge: true, sound: true);

    // Foreground: show heads-up notification
    FirebaseMessaging.onMessage.listen(_onForegroundMessage);

    // Background tap (app was in background)
    FirebaseMessaging.onMessageOpenedApp.listen(_onMessageTapped);

    // Register token
    await _registerToken();

    // Listen for token refresh
    FirebaseMessaging.instance.onTokenRefresh.listen((token) => _saveAndRegisterToken(token));
  }

  static Future<void> _registerToken() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _saveAndRegisterToken(token);
    } catch (_) {}
  }

  /// Call this after login — always sends to server even if token unchanged
  static Future<void> forceRegisterToken() async {
    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token == null) return;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('vendor_fcm_token', token);
      await ApiClient().post('/auth/fcm-token', data: {'fcm_token': token});
    } catch (_) {}
  }

  static Future<void> _saveAndRegisterToken(String token) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final old = prefs.getString('vendor_fcm_token');
      if (old == token) return;
      await prefs.setString('vendor_fcm_token', token);
      await ApiClient().post('/auth/fcm-token', data: {'fcm_token': token});
    } catch (_) {}
  }

  static void _onForegroundMessage(RemoteMessage message) {
    final n = message.notification;
    if (n == null) return;
    _fln.show(
      message.hashCode,
      n.title,
      n.body,
      NotificationDetails(
        android: AndroidNotificationDetails(
          _channel.id, _channel.name,
          channelDescription: _channel.description,
          importance: Importance.max,
          priority: Priority.high,
          icon: '@mipmap/ic_launcher',
          color: const Color(0xFFFF6B35),
          playSound: true,
          enableVibration: true,
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }

  static void _onMessageTapped(RemoteMessage message) {
    // Navigation handled by app via deep_link in data
    _handleData(message.data);
  }

  static void _onNotificationTap(NotificationResponse response) {
    if (response.payload == null) return;
    try {
      final data = jsonDecode(response.payload!) as Map<String, dynamic>;
      _handleData(data);
    } catch (_) {}
  }

  static void _handleData(Map<String, dynamic> data) {
    // App will handle navigation via navigatorKey or route observation
    // Store last notification data for the app to pick up
    _pendingRoute = data['deep_link'] as String?;
  }

  static String? _pendingRoute;
  static String? consumePendingRoute() {
    final r = _pendingRoute;
    _pendingRoute = null;
    return r;
  }
}

