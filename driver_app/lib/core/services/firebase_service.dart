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

// ─────────────────────────────────────────────────────────────────────────────
// Background isolate handler — called when app is KILLED or BACKGROUND
// for DATA-ONLY FCM messages (no 'notification' block in FCM payload).
//
// CRITICAL: WidgetsFlutterBinding + Firebase.initializeApp() MUST be first.
// DATA-ONLY FCM is used so this handler is ALWAYS called regardless of app state.
// ─────────────────────────────────────────────────────────────────────────────
@pragma('vm:entry-point')
Future<void> _bgHandler(RemoteMessage message) async {
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

    // Initialize local notifications plugin in this isolate
    await local.initialize(
      const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher')),
    );

    // Create the alarm channel with correct sound FIRST
    await local
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(
          const AndroidNotificationChannel(
            'esahlan_order_ring_v2',
            'New Order Alert',
            description: 'Rings loudly when a new delivery order is assigned.',
            importance: Importance.max,
            playSound: true,
            sound: RawResourceAndroidNotificationSound('order_ring'),
            enableVibration: true,
            enableLights: true,
            ledColor: Color(0xFFFF8A00),
          ),
        );

    final orderNum = message.data['order_number'] ?? '';
    final fee      = message.data['delivery_fee'] ?? '';

    // Show full-screen intent notification — pops over lock screen like incoming call
    await local.show(
      99901,
      '🚨 New Order! Tap to accept',
      'Order #$orderNum • \$$fee delivery fee',
      NotificationDetails(
        android: AndroidNotificationDetails(
          'esahlan_order_ring_v2',
          'New Order Alert',
          importance:     Importance.max,
          priority:       Priority.max,
          color:          const Color(0xFFFF8A00),
          // fullScreenIntent — auto-pops over lock screen (like incoming call)
          fullScreenIntent: true,
          category:       AndroidNotificationCategory.alarm,
          visibility:     NotificationVisibility.public,
          playSound:      true,
          sound:          const RawResourceAndroidNotificationSound('order_ring'),
          enableVibration: true,
          vibrationPattern: Int64List.fromList([0, 400, 200, 400, 200, 400, 200, 400]),
          ongoing:        true,   // stays until driver acts
          autoCancel:     false,
          ticker:         'New delivery order',
          // Show over lock screen
          styleInformation: const DefaultStyleInformation(true, true),
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// FirebaseService — singleton, initialised once after runApp
// ─────────────────────────────────────────────────────────────────────────────
class FirebaseService {
  static final FirebaseService _i = FirebaseService._();
  factory FirebaseService() => _i;
  FirebaseService._();

  final _fcm   = FirebaseMessaging.instance;
  final _local = FlutterLocalNotificationsPlugin();
  bool _init   = false;

  /// Called with order data whenever a new order notification arrives
  /// (foreground, background tap, or killed-app launch)
  void Function(Map<String, dynamic> orderData)? onNewOrder;

  // ── Register background handler BEFORE runApp ─────────────────────────────
  static Future<void> setupBeforeRunApp() async {
    FirebaseMessaging.onBackgroundMessage(_bgHandler);
    // For foreground: don't show system notification (we handle it ourselves)
    await FirebaseMessaging.instance
        .setForegroundNotificationPresentationOptions(
            alert: false, badge: true, sound: false);
  }

  // ── Main init — call from shell after runApp ──────────────────────────────
  Future<void> initialize() async {
    if (_init) return;

    final androidPlugin = _local.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();

    // Channel 1: General driver notifications
    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        'esahlan_driver_v1',
        'eSahlan Driver',
        importance: Importance.high,
      ),
    );

    // Channel 2: Order alarm — max importance + custom sound
    await androidPlugin?.createNotificationChannel(
      const AndroidNotificationChannel(
        'esahlan_order_ring_v2',
        'New Order Alert',
        description: 'Rings loudly when a new delivery order is assigned.',
        importance:     Importance.max,
        playSound:      true,
        sound:          RawResourceAndroidNotificationSound('order_ring'),
        enableVibration: true,
        enableLights:   true,
        ledColor:       Color(0xFFFF8A00),
      ),
    );

    await _local.initialize(
      const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher')),
      onDidReceiveNotificationResponse: (r) {
        // Driver tapped the local notification (foreground or bg-tap)
        if (r.payload != null) {
          try {
            final data = jsonDecode(r.payload!) as Map<String, dynamic>;
            if (data['type'] == 'new_order') {
              onNewOrder?.call(data);
              _cancelOrderNotification();
            }
          } catch (_) {}
        }
      },
      // Called when app was KILLED and launched via notification tap
      onDidReceiveBackgroundNotificationResponse: _onBgNotifTap,
    );

    // ── Handle launch from notification when app was KILLED ──────────────────
    final launchDetails = await _local.getNotificationAppLaunchDetails();
    if (launchDetails?.didNotificationLaunchApp == true) {
      final payload = launchDetails?.notificationResponse?.payload;
      if (payload != null) {
        try {
          final data = jsonDecode(payload) as Map<String, dynamic>;
          if (data['type'] == 'new_order') {
            // Delay slightly so the router/shell is ready
            Future.delayed(const Duration(milliseconds: 600), () {
              onNewOrder?.call(data);
              _cancelOrderNotification();
            });
          }
        } catch (_) {}
      }
    }

    // ── Foreground FCM handler ───────────────────────────────────────────────
    FirebaseMessaging.onMessage.listen(_handleForeground);

    // ── Background tap (app in background, driver taps notification) ─────────
    // For notification-type FCM taps (if any slip through)
    FirebaseMessaging.onMessageOpenedApp.listen((msg) {
      if (msg.data['type'] == 'new_order') {
        onNewOrder?.call(msg.data);
        _cancelOrderNotification();
      }
    });

    // ── Killed-app FCM tap (for notification-type messages) ──────────────────
    final initial = await _fcm.getInitialMessage();
    if (initial != null && initial.data['type'] == 'new_order') {
      Future.delayed(const Duration(milliseconds: 600), () {
        onNewOrder?.call(initial.data);
        _cancelOrderNotification();
      });
    }

    await _registerToken();
    _fcm.onTokenRefresh.listen(_uploadToken);
    _init = true;
  }

  // ── Foreground: show full-screen local notification + open screen directly ─
  Future<void> _handleForeground(RemoteMessage msg) async {
    final type = msg.data['type'] ?? '';

    if (type == 'request_location') {
      await postLocationForFcm();
      return;
    }

    if (type == 'new_order') {
      await _cancelOrderNotification();

      // 1. Open IncomingOrderScreen immediately (app is in foreground)
      onNewOrder?.call(msg.data);

      // 2. Also show an ongoing notification so driver can tap if they switch apps
      final orderNum = msg.data['order_number'] ?? '';
      final fee      = msg.data['delivery_fee'] ?? '';
      await _local.show(
        99901,
        '🚨 New Order! Tap to accept',
        'Order #$orderNum • \$$fee delivery fee',
        NotificationDetails(
          android: AndroidNotificationDetails(
            'esahlan_order_ring_v2',
            'New Order Alert',
            importance:      Importance.max,
            priority:        Priority.max,
            color:           const Color(0xFFFF8A00),
            fullScreenIntent: true,
            category:        AndroidNotificationCategory.alarm,
            visibility:      NotificationVisibility.public,
            playSound:       true,
            sound:           const RawResourceAndroidNotificationSound('order_ring'),
            enableVibration: true,
            vibrationPattern: Int64List.fromList([0, 500, 300, 500, 300, 500]),
            ongoing:         true,
            autoCancel:      false,
            timeoutAfter:    50000,
          ),
        ),
        payload: jsonEncode(msg.data),
      );
      return;
    }

    // General notification
    final title = msg.notification?.title ?? msg.data['title'] ?? 'eSahlan Driver';
    final body  = msg.notification?.body  ?? msg.data['body']  ?? '';
    await _local.show(
      msg.hashCode,
      title,
      body,
      NotificationDetails(
          android: AndroidNotificationDetails(
        'esahlan_driver_v1',
        'eSahlan Driver',
        importance: Importance.high,
        priority:   Priority.high,
        color:      const Color(0xFFFF8A00),
        playSound:  true,
      )),
      payload: jsonEncode(msg.data),
    );
  }

  // ── Cancel the ongoing order notification (call after accept/decline) ──────
  Future<void> cancelOrderNotification() => _cancelOrderNotification();
  Future<void> _cancelOrderNotification() => _local.cancel(99901);

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

// ── Background notification tap handler (static — required by flutter_local_notifications)
@pragma('vm:entry-point')
void _onBgNotifTap(NotificationResponse response) {
  // This runs in a separate isolate when app is killed and notification is tapped.
  // The main isolate will handle it via getNotificationAppLaunchDetails() on next startup.
  debugPrint('[FCM:BG_TAP] notification tapped, payload: ${response.payload}');
}
