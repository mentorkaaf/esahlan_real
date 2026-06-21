import 'dart:convert';
import 'dart:ui';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import '../api/api_client.dart';
import '../storage/local_storage.dart';

@pragma('vm:entry-point')
Future<void> _bgHandler(RemoteMessage message) async {
  debugPrint('[FCM:BG] ${message.messageId}');
}

class FirebaseService {
  static final FirebaseService _i = FirebaseService._();
  factory FirebaseService() => _i;
  FirebaseService._();

  final _fcm = FirebaseMessaging.instance;
  final _local = FlutterLocalNotificationsPlugin();
  bool _init = false;
  void Function(String path)? onDeepLink;

  static Future<void> setupBeforeRunApp() async {
    FirebaseMessaging.onBackgroundMessage(_bgHandler);
    await FirebaseMessaging.instance.setForegroundNotificationPresentationOptions(alert: true, badge: true, sound: true);
  }

  Future<void> initialize() async {
    if (_init) return;

    const channel = AndroidNotificationChannel('esahlan_driver_v1', 'eSahlan Driver', importance: Importance.high);
    await _local.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()?.createNotificationChannel(channel);

    await _local.initialize(
      const InitializationSettings(android: AndroidInitializationSettings('@mipmap/ic_launcher')),
      onDidReceiveNotificationResponse: (r) {
        if (r.payload != null) {
          try {
            final data = jsonDecode(r.payload!);
            final dl = data['deep_link'] as String?;
            if (dl != null) onDeepLink?.call(dl);
          } catch (_) {}
        }
      },
    );

    FirebaseMessaging.onMessage.listen(_handleForeground);

    await _registerToken();
    _fcm.onTokenRefresh.listen(_uploadToken);
    _init = true;
  }

  Future<void> _handleForeground(RemoteMessage msg) async {
    final title = msg.notification?.title ?? msg.data['title'] ?? 'eSahlan Driver';
    final body = msg.notification?.body ?? msg.data['body'] ?? '';

    await _local.show(
      msg.hashCode,
      title,
      body,
      NotificationDetails(android: AndroidNotificationDetails(
        'esahlan_driver_v1', 'eSahlan Driver',
        importance: Importance.high,
        priority: Priority.high,
        color: const Color(0xFFFF8A00),
        playSound: true,
      )),
      payload: jsonEncode(msg.data),
    );
  }

  Future<void> _registerToken() async {
    try {
      final token = await _fcm.getToken();
      if (token != null) await _uploadToken(token);
    } catch (e) {
      debugPrint('[FCM] Token error: $e');
    }
  }

  Future<void> _uploadToken(String token) async {
    try {
      final authToken = await LocalStorage.getToken();
      if (authToken == null) return;
      await ApiClient.instance.post('/delivery/fcm-token', data: {'token': token});
    } catch (_) {}
  }

  Future<void> refreshTokenIfNeeded() async => _registerToken();

  Future<void> deleteToken() async {
    try { await _fcm.deleteToken(); } catch (_) {}
  }
}
