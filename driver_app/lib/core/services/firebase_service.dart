import 'dart:convert';
import 'dart:typed_data';
import 'dart:ui';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../api/api_client.dart';
import '../storage/local_storage.dart';
import 'location_service.dart';
import '../../firebase_options.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Shared constants
// ─────────────────────────────────────────────────────────────────────────────
const _kPendingOrderKey = 'pending_ring_order';
// 'pending_ring_action' key is written by OrderCallActivity.kt (native),
// read by MainShell._checkNativeOrderAction(). Not used in Dart directly.
const _kRingChannelId   = 'esahlan_order_ring_v3';
const _kRingNotifId      = 99901;
const _kNativeCallCh     = 'esahlan_call';        // MethodChannel → CallPlugin → OrderCallActivity

// ─────────────────────────────────────────────────────────────────────────────
// Background handler — runs in its own Dart isolate
//
// RULE: DATA-ONLY FCM (no 'notification' block) → _bgHandler is ALWAYS called
// by the Android FCM library regardless of app state (killed / background).
//
// What we do here:
//   1. Save order to SharedPreferences  ← app reads this on launch / resume
//   2. Show fullScreenIntent alarm      ← pops over lock screen, rings
// ─────────────────────────────────────────────────────────────────────────────
@pragma('vm:entry-point')
Future<void> _bgHandler(RemoteMessage message) async {
  WidgetsFlutterBinding.ensureInitialized();
  try {
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
  } catch (_) {}

  final type = message.data['type'] ?? '';
  debugPrint('[FCM:BG] type=$type');

  if (type == 'request_location') {
    await postLocationForFcm();
    return;
  }

  if (type == 'force_online_reminder') {
    // Admin forced this driver online. Save flag so MainShell picks it up on resume.
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setBool('pending_force_online', true);
      debugPrint('[FCM:BG] force_online saved to prefs');
    } catch (e) {
      debugPrint('[FCM:BG] force_online prefs error: $e');
    }
    return;
  }

  if (type == 'new_order') {
    // ── 1. Persist order so the app reads it after launch / resume ───────────
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_kPendingOrderKey, jsonEncode(message.data));
      debugPrint('[FCM:BG] order saved to prefs');
    } catch (e) {
      debugPrint('[FCM:BG] prefs save error: $e');
    }

    // ── 2. Try native call screen via MethodChannel → CallPlugin → OrderCallActivity
    //
    // CallPlugin is registered by MainActivity.configureFlutterEngine().
    // When the app is BACKGROUND/LOCKED (engine running), this always works.
    // When the app is KILLED, BackgroundIsolateBinaryMessenger bridges the
    // background isolate to the platform if RootIsolateToken is available.
    bool nativeShown = false;
    try {
      final token = RootIsolateToken.instance;
      if (token != null) {
        BackgroundIsolateBinaryMessenger.ensureInitialized(token);
      }
      await const MethodChannel(_kNativeCallCh).invokeMethod(
        'showCallScreen',
        Map<String, String>.from(message.data),
      );
      nativeShown = true;
      debugPrint('[FCM:BG] native OrderCallActivity shown');
    } catch (e) {
      debugPrint('[FCM:BG] native call screen unavailable ($e) — using local notification fallback');
    }

    if (nativeShown) return; // OrderCallActivity handles everything

    // ── 3. Fallback: flutter_local_notifications with fullScreenIntent ────────
    //      Used when app is killed and CallPlugin is not yet registered on the
    //      background engine. The notification fires the fullScreenIntent which
    //      launches MainActivity → Flutter starts → checkPendingOrder() fires →
    //      ring screen appears. With USE_FULL_SCREEN_INTENT permission granted
    //      (see location_service.dart), Android shows this automatically on the
    //      lock screen without the user needing to tap.
    final local = FlutterLocalNotificationsPlugin();
    await local.initialize(
      const InitializationSettings(
        android: AndroidInitializationSettings('@mipmap/ic_launcher'),
      ),
      onDidReceiveBackgroundNotificationResponse: _onBgNotifTap,
    );

    await local
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(const AndroidNotificationChannel(
          _kRingChannelId, 'New Order Alert',
          description:     'Rings loudly when a new delivery order is assigned.',
          importance:      Importance.max,
          playSound:       true,
          sound:           RawResourceAndroidNotificationSound('order_ring'),
          enableVibration: true,
          enableLights:    true,
          ledColor:        Color(0xFFFF8A00),
        ));

    final orderNum = message.data['order_number'] ?? '';
    final fee      = message.data['delivery_fee'] ?? '';

    await local.show(
      _kRingNotifId,
      '🚴 New Order — Tap to accept',
      'Order #$orderNum  •  \$$fee delivery fee',
      NotificationDetails(
        android: AndroidNotificationDetails(
          _kRingChannelId, 'New Order Alert',
          importance:       Importance.max,
          priority:         Priority.max,
          color:            const Color(0xFFFF8A00),
          fullScreenIntent: true,                            // auto-shows on lock screen
          category:         AndroidNotificationCategory.call,// call = highest OS priority
          visibility:       NotificationVisibility.public,
          playSound:        true,
          sound:            const RawResourceAndroidNotificationSound('order_ring'),
          enableVibration:  true,
          vibrationPattern: Int64List.fromList([0, 500, 200, 500, 200, 500, 200, 500]),
          ongoing:          true,
          autoCancel:       false,
          ticker:           'New delivery order',
          timeoutAfter:     55000,
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// Background notification-tap handler (static — required by flutter_local_notifications)
// App was KILLED: user taps notification → app launches cold.
//                 main_shell.dart reads pending_ring_order via checkPendingOrder().
// ─────────────────────────────────────────────────────────────────────────────
@pragma('vm:entry-point')
void _onBgNotifTap(NotificationResponse response) {
  // Nothing to do here — main_shell.dart calls checkPendingOrder() on start.
  debugPrint('[FCM:BG_TAP] tapped, payload=${response.payload}');
}

// ─────────────────────────────────────────────────────────────────────────────
// FirebaseService — singleton, initialised once by main_shell
// ─────────────────────────────────────────────────────────────────────────────
class FirebaseService {
  static final FirebaseService _i = FirebaseService._();
  factory FirebaseService() => _i;
  FirebaseService._();

  final _fcm   = FirebaseMessaging.instance;
  final _local = FlutterLocalNotificationsPlugin();
  bool _init   = false;

  /// Called by MainShell when an order notification arrives while app is open.
  void Function(Map<String, dynamic> data)? onNewOrder;

  /// Called by MainShell when admin forces this driver online (foreground only).
  VoidCallback? onForceOnline;

  // ── Register _bgHandler BEFORE runApp ─────────────────────────────────────
  static Future<void> setupBeforeRunApp() async {
    FirebaseMessaging.onBackgroundMessage(_bgHandler);
    // Suppress system notification in foreground — we handle it ourselves.
    await FirebaseMessaging.instance
        .setForegroundNotificationPresentationOptions(
            alert: false, badge: true, sound: false);
  }

  // ── Killed-state: notification tap launched the app ─────────────────────
  // Called from main() before runApp(). If the app was launched by tapping
  // the system ring notification, save the order to SharedPreferences so
  // MainShell.checkPendingOrder() navigates to the ring screen.
  static Future<void> saveInitialMessageIfOrder() async {
    try {
      final msg = await FirebaseMessaging.instance.getInitialMessage();
      if (msg == null) return;
      final type = msg.data['type'] ?? '';
      debugPrint('[FCM] getInitialMessage type=$type');
      if (type == 'new_order') {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_kPendingOrderKey, jsonEncode(msg.data));
        debugPrint('[FCM] Initial order saved to prefs: ${msg.data['order_number']}');
      }
    } catch (e) {
      debugPrint('[FCM] saveInitialMessageIfOrder error: $e');
    }
  }

  // ── Full initialisation — call from MainShell.initState ───────────────────
  Future<void> initialize() async {
    // Always re-register token on every launch so server has the latest.
    // Token changes when app reinstalled / cleared / Firebase rotates it.
    // Only skip the heavy setup (channels, listeners) if already done.
    if (_init) {
      await _registerToken(); // re-upload token silently on every resume
      return;
    }
    _init = true;

    await _fcm.requestPermission(alert: true, sound: true, badge: true);

    // Create channels
    final android = _local.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    await android?.createNotificationChannel(
      const AndroidNotificationChannel(
        'esahlan_driver_v1', 'eSahlan Driver',
        importance: Importance.high,
      ),
    );
    await android?.createNotificationChannel(
      const AndroidNotificationChannel(
        _kRingChannelId, 'New Order Alert',
        description:     'Rings loudly when a new delivery order is assigned.',
        importance:      Importance.max,
        playSound:       true,
        sound:           RawResourceAndroidNotificationSound('order_ring'),
        enableVibration: true,
        enableLights:    true,
        ledColor:        Color(0xFFFF8A00),
      ),
    );

    await _local.initialize(
      const InitializationSettings(
          android: AndroidInitializationSettings('@mipmap/ic_launcher')),
      onDidReceiveNotificationResponse:           _onFgNotifTap,
      onDidReceiveBackgroundNotificationResponse: _onBgNotifTap,
    );

    // Foreground FCM (app open, message received)
    FirebaseMessaging.onMessage.listen(_handleForeground);

    // Background-state notification tap → driver taps tray notification while
    // app is in background. Save to prefs so MainShell.didChangeAppLifecycleState
    // picks it up on resume.
    FirebaseMessaging.onMessageOpenedApp.listen((msg) async {
      final type = msg.data['type'] ?? '';
      debugPrint('[FCM:TAP] onMessageOpenedApp type=$type');
      if (type == 'new_order') {
        try {
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString(_kPendingOrderKey, jsonEncode(msg.data));
          debugPrint('[FCM:TAP] saved to prefs, shell will pick up on resume');
          // Also call directly if shell is ready
          onNewOrder?.call(msg.data);
        } catch (_) {}
      }
    });

    // Register FCM token
    await _registerToken();
    _fcm.onTokenRefresh.listen(_uploadToken);
  }

  // ── Read + clear pending order from SharedPreferences ────────────────────
  // Call this on MainShell.initState AND on AppLifecycleState.resumed.
  // Returns the order data if one is waiting, null otherwise.
  static Future<Map<String, dynamic>?> checkPendingOrder() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw   = prefs.getString(_kPendingOrderKey);
      if (raw == null) return null;
      await prefs.remove(_kPendingOrderKey);            // consume once
      final data = jsonDecode(raw) as Map<String, dynamic>;
      debugPrint('[FCM] pending order found: ${data['order_number']}');
      return data;
    } catch (e) {
      debugPrint('[FCM] checkPendingOrder error: $e');
      return null;
    }
  }

  // ── Cancel the ongoing alarm notification (both flutter + native) ──────────
  Future<void> cancelOrderNotification() async {
    try { await _local.cancel(_kRingNotifId); } catch (_) {}
    try {
      await const MethodChannel(_kNativeCallCh).invokeMethod('cancelCallScreen');
    } catch (_) {}
  }

  // ── Foreground FCM handler ────────────────────────────────────────────────
  Future<void> _handleForeground(RemoteMessage msg) async {
    final type = msg.data['type'] ?? '';

    if (type == 'request_location') {
      await postLocationForFcm();
      return;
    }

    if (type == 'force_online_reminder') {
      debugPrint('[FCM:FG] force_online_reminder received');
      onForceOnline?.call();
      return;
    }

    if (type == 'new_order') {
      debugPrint('[FCM:FG] new_order received');

      // Save to prefs — if driver taps from another app, shell will pick it up on resume
      try {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_kPendingOrderKey, jsonEncode(msg.data));
      } catch (_) {}

      // Navigate immediately if shell is ready
      onNewOrder?.call(msg.data);

      // Show ongoing notification (visible if driver switches to another app)
      await cancelOrderNotification();
      final orderNum = msg.data['order_number'] ?? '';
      final fee      = msg.data['delivery_fee'] ?? '';
      await _local.show(
        _kRingNotifId,
        'New Order — Tap to accept',
        'Order #$orderNum  •  \$$fee delivery fee',
        NotificationDetails(
          android: AndroidNotificationDetails(
            _kRingChannelId, 'New Order Alert',
            importance:       Importance.max,
            priority:         Priority.max,
            color:            const Color(0xFFFF8A00),
            fullScreenIntent: true,
            category:         AndroidNotificationCategory.alarm,
            visibility:       NotificationVisibility.public,
            playSound:        true,
            sound:            const RawResourceAndroidNotificationSound('order_ring'),
            enableVibration:  true,
            vibrationPattern: Int64List.fromList([0, 500, 200, 500]),
            ongoing:          true,
            autoCancel:       false,
            timeoutAfter:     55000,
          ),
        ),
        payload: jsonEncode(msg.data),
      );
      return;
    }

    // General notifications
    final title = msg.notification?.title ?? msg.data['title'] ?? 'eSahlan Driver';
    final body  = msg.notification?.body  ?? msg.data['body']  ?? '';
    await _local.show(
      msg.hashCode,
      title, body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          'esahlan_driver_v1', 'eSahlan Driver',
          importance: Importance.high,
          priority:   Priority.high,
        ),
      ),
      payload: jsonEncode(msg.data),
    );
  }

  // ── Foreground notification-tap handler ──────────────────────────────────
  void _onFgNotifTap(NotificationResponse r) {
    if (r.payload == null) return;
    try {
      final data = jsonDecode(r.payload!) as Map<String, dynamic>;
      if (data['type'] == 'new_order') {
        onNewOrder?.call(data);
        cancelOrderNotification();
      }
    } catch (_) {}
  }

  // ── Token management ──────────────────────────────────────────────────────
  Future<void> _registerToken() async {
    try {
      final token = await _fcm.getToken();
      if (token != null) await _uploadToken(token);
    } catch (e) {
      debugPrint('[FCM] token error: $e');
    }
  }

  Future<void> _uploadToken(String token) async {
    try {
      final auth = await LocalStorage.getToken();
      if (auth == null) return;
      await ApiClient.instance.post('/delivery/fcm-token', data: {'token': token});
    } catch (_) {}
  }

  Future<void> refreshTokenIfNeeded() => _registerToken();

  Future<void> deleteToken() async {
    try { await _fcm.deleteToken(); } catch (_) {}
  }
}
