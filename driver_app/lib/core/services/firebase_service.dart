import 'dart:convert';
import 'dart:typed_data';
import 'dart:ui';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import '../api/api_client.dart';
import '../storage/local_storage.dart';
import 'location_service.dart';

// ──────────────────────────────────────────────────────────────────────────────
// Global background handler — runs in its own isolate even when app is killed
// REQUIRED: WidgetsFlutterBinding.ensureInitialized() + Firebase.initializeApp()
// must be called first or all Flutter plugins silently fail in background isolate
// ──────────────────────────────────────────────────────────────────────────────
@pragma('vm:entry-point')
Future<void> _bgHandler(RemoteMessage message) async {
  // CRITICAL: must be first line — initializes Flutter engine in background isolate
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();

  final type = message.data['type'] ?? '';
  debugPrint('[FCM:BG] type=$type data=${message.data}');

  if (type == 'request_location') {
    await postLocationForFcm();
    return;
  }

  if (type == 'new_order') {
    final local = FlutterLocalNotificationsPlugin();
    await local.initialize(
      const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher')),
    );
    await local
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(
          const AndroidNotificationChannel(
            'esahlan_order_ring_v2',
            'New Order Alert',
            description: 'Rings when a new delivery order is assigned to you.',
            importance: Importance.max,
            playSound: true,
            sound: RawResourceAndroidNotificationSound('order_ring'),
            enableVibration: true,
            enableLights: true,
            ledColor: Color(0xFFFF8A00),
          ),
        );

    final orderNum = message.data['order_number'] ?? '';
    final fee = message.data['delivery_fee'] ?? '';

    await local.show(
      99901,
      '🚨 New Order! Tap to accept',
      'Order #$orderNum • \$$fee delivery fee',
      NotificationDetails(
        android: AndroidNotificationDetails(
          'esahlan_order_ring_v2',
          'New Order Alert',
          importance: Importance.max,
          priority: Priority.max,
          color: const Color(0xFFFF8A00),
          // fullScreenIntent removed — blocked on HONOR/Huawei without special permission
          // Instead: loud sound + vibration + tap to open
          category: AndroidNotificationCategory.alarm,
          visibility: NotificationVisibility.public,
          playSound: true,
          sound: const RawResourceAndroidNotificationSound('order_ring'),
          enableVibration: true,
          vibrationPattern: Int64List.fromList([0, 400, 200, 400, 200, 400, 200, 400]),
          ongoing: false,
          autoCancel: true,
          ticker: 'New delivery order',
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// FirebaseService — singleton, initialised after runApp
// ──────────────────────────────────────────────────────────────────────────────
class FirebaseService {
  static final FirebaseService _i = FirebaseService._();
  factory FirebaseService() => _i;
  FirebaseService._();

  final _fcm = FirebaseMessaging.instance;
  final _local = FlutterLocalNotificationsPlugin();
  bool _init = false;

  /// Called when a new-order notification is tapped (foreground or background)
  void Function(Map<String, dynamic> orderData)? onNewOrder;

  // ── One-time setup BEFORE runApp ─────────────────────────────────────────
  static Future<void> setupBeforeRunApp() async {
    FirebaseMessaging.onBackgroundMessage(_bgHandler);
    await FirebaseMessaging.instance.setForegroundNotificationPresentationOptions(
        alert: true, badge: true, sound: false);
  }

  // ── Main init (call from shell after runApp) ──────────────────────────────
  Future<void> initialize() async {
    if (_init) return;

    // ── Notification channels ───────────────────────────────────────────────
    final androidPlugin =
        _local.resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>();

    // 1. General driver notifications
    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        'esahlan_driver_v1',
        'eSahlan Driver',
        importance: Importance.high,
      ),
    );

    // 2. Order alarm — max importance + custom sound (v2 = new channel to force fresh Android cache)
    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        'esahlan_order_ring_v2',
        'New Order Alert',
        description:
            'Rings when a new delivery order is assigned to you.',
        importance: Importance.max,
        playSound: true,
        sound: RawResourceAndroidNotificationSound('order_ring'),
        enableVibration: true,
        enableLights: true,
        ledColor: Color(0xFFFF8A00),
      ),
    );

    await _local.initialize(
      const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher')),
      onDidReceiveNotificationResponse: (r) {
        if (r.payload != null) {
          try {
            final data = jsonDecode(r.payload!) as Map<String, dynamic>;
            if (data['type'] == 'new_order') {
              onNewOrder?.call(data);
            }
          } catch (_) {}
        }
      },
    );

    FirebaseMessaging.onMessage.listen(_handleForeground);

    // Handle notification tap when app was in background (not killed)
    FirebaseMessaging.onMessageOpenedApp.listen((msg) {
      if (msg.data['type'] == 'new_order') {
        onNewOrder?.call(msg.data);
      }
    });

    // Handle notification tap when app was killed and restarted by the tap
    final initial = await _fcm.getInitialMessage();
    if (initial != null && initial.data['type'] == 'new_order') {
      // Delay to let the app finish booting
      Future.delayed(const Duration(milliseconds: 800), () {
        onNewOrder?.call(initial.data);
      });
    }

    await _registerToken();
    _fcm.onTokenRefresh.listen(_uploadToken);
    _init = true;
  }

  // ── Foreground handler ────────────────────────────────────────────────────
  Future<void> _handleForeground(RemoteMessage msg) async {
    final type = msg.data['type'] ?? '';

    if (type == 'request_location') {
      await postLocationForFcm();
      return;
    }

    if (type == 'new_order') {
      // Cancel any existing order notification
      await _local.cancel(99901);

      // Trigger the in-app incoming order screen (if onNewOrder is set)
      onNewOrder?.call(msg.data);

      // Also show a local notification (handles if app is backgrounded mid-flow)
      final orderNum = msg.data['order_number'] ?? '';
      final fee = msg.data['delivery_fee'] ?? '';
      await _local.show(
        99901,
        '🚀 New Order — Accept now!',
        'Order #$orderNum • Earn SOS $fee',
        NotificationDetails(
          android: AndroidNotificationDetails(
            'esahlan_order_ring_v2',
            'New Order Alert',
            importance: Importance.max,
            priority: Priority.max,
            color: const Color(0xFFFF8A00),
            fullScreenIntent: true,
            category: AndroidNotificationCategory.call,
            visibility: NotificationVisibility.public,
            playSound: true,
            sound: const RawResourceAndroidNotificationSound('order_ring'),
            enableVibration: true,
            vibrationPattern:
                Int64List.fromList([0, 500, 300, 500, 300, 500]),
            ongoing: true,
            autoCancel: false,
            timeoutAfter: 50000,
          ),
        ),
        payload: jsonEncode(msg.data),
      );
      return;
    }

    // Generic notification
    final title =
        msg.notification?.title ?? msg.data['title'] ?? 'eSahlan Driver';
    final body = msg.notification?.body ?? msg.data['body'] ?? '';
    await _local.show(
      msg.hashCode,
      title,
      body,
      NotificationDetails(
          android: AndroidNotificationDetails(
        'esahlan_driver_v1',
        'eSahlan Driver',
        importance: Importance.high,
        priority: Priority.high,
        color: const Color(0xFFFF8A00),
        playSound: true,
      )),
      payload: jsonEncode(msg.data),
    );
  }

  // ── Cancel the ongoing order notification (call after accept/decline) ─────
  Future<void> cancelOrderNotification() async {
    await _local.cancel(99901);
  }

  // ── Token management ──────────────────────────────────────────────────────
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
      await ApiClient.instance
          .post('/delivery/fcm-token', data: {'token': token});
    } catch (_) {}
  }

  Future<void> refreshTokenIfNeeded() async => _registerToken();

  Future<void> deleteToken() async {
    try {
      await _fcm.deleteToken();
    } catch (_) {}
  }
}
