import 'dart:async';
import 'dart:convert';
import 'dart:io' show File, Directory;

import 'package:dio/dio.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_callkit_incoming/flutter_callkit_incoming.dart';
import 'package:flutter_callkit_incoming/entities/entities.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:path_provider/path_provider.dart';

import '../api/api_client.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';
import '../theme/app_theme.dart';

// ─────────────────────────────────────────────────────────────────────────────
// BACKGROUND HANDLER — must be top-level, called in native isolate
// ─────────────────────────────────────────────────────────────────────────────
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  if (message.data['type'] == 'incoming_call') {
    await _showCallkitIncoming(message.data);
  }
}

Future<void> _showCallkitIncoming(Map<String, dynamic> data) async {
  final callType = data['call_type'] ?? 'audio';
  await FlutterCallkitIncoming.showCallkitIncoming(CallKitParams(
    id: data['call_id'] ?? '',
    nameCaller: data['caller_name'] ?? '',
    appName: 'eSahlan',
    avatar: data['caller_avatar'],
    handle: data['caller_name'] ?? '',
    type: callType == 'video' ? 1 : 0,
    duration: 30000,
    textAccept: 'Accept',
    textDecline: 'Decline',
    extra: <String, dynamic>{
      'call_id': data['call_id'] ?? '',
      'call_type': callType,
      'caller_name': data['caller_name'] ?? '',
      'caller_avatar': data['caller_avatar'] ?? '',
      'livekit_url': data['livekit_url'] ?? '',
    },
    android: const AndroidParams(
      isCustomNotification: true,
      isShowLogo: false,
      ringtonePath: 'system_ringtone_default',
      backgroundColor: '#1A0A2E',
      actionColor: '#FF6600',
      isShowFullLockedScreen: true,
    ),
  ));
}

// ─────────────────────────────────────────────────────────────────────────────
// FirebaseService — singleton
// ─────────────────────────────────────────────────────────────────────────────
class FirebaseService {
  static final FirebaseService _instance = FirebaseService._internal();
  factory FirebaseService() => _instance;
  FirebaseService._internal();

  final FirebaseMessaging _fcm = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotif =
      FlutterLocalNotificationsPlugin();

  bool _initialized = false;
  StreamSubscription<RemoteMessage>? _fgSubscription;

  /// Called by main() BEFORE runApp() to register the native-level handlers.
  /// Must NOT touch flutter_local_notifications here — plugin is not bound yet.
  static Future<void> setupBeforeRunApp() async {
    // Background handler registration is native — safe before runApp()
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

    // iOS foreground presentation — FCM config, not a plugin method call
    await FirebaseMessaging.instance.setForegroundNotificationPresentationOptions(
      alert: true,
      badge: true,
      sound: true,
    );
  }

  /// Called from widget initState() AFTER runApp() — plugin registry is ready.
  Future<void> initialize() async {
    if (_initialized) return;

    try {
      if (kIsWeb) {
        await _initWeb();
        return;
      }

      // ── Native (Android / iOS) ────────────────────────────────────────────
      // 1. Create Android notification channel (plugin bound after runApp)
      await _localNotif
          .resolvePlatformSpecificImplementation<
              AndroidFlutterLocalNotificationsPlugin>()
          ?.createNotificationChannel(_notifChannel);

      // 2. Initialize flutter_local_notifications
      await _localNotif.initialize(
        const InitializationSettings(
          android: AndroidInitializationSettings('ic_notification'),
          iOS: DarwinInitializationSettings(
            requestAlertPermission: false,
            requestBadgePermission: false,
            requestSoundPermission: false,
          ),
        ),
        onDidReceiveNotificationResponse: _onNotificationTap,
      );

      // 3. Subscribe to foreground FCM messages
      await _fgSubscription?.cancel();
      _fgSubscription = FirebaseMessaging.onMessage.listen(
        _handleForegroundMessage,
        onError: (e) => debugPrint('[FCM] onMessage error: $e'),
      );

      // 4. Register FCM token
      await _registerToken();
      _fcm.onTokenRefresh.listen(_uploadToken);

      _initialized = true;
      debugPrint('[FCM] Initialized ✓');
    } catch (e, st) {
      debugPrint('[FCM] Init error: $e\n$st');
    }
  }

  Future<void> _initWeb() async {
    try {
      // 1. Subscribe to foreground messages (shown as browser notification)
      await _fgSubscription?.cancel();
      _fgSubscription = FirebaseMessaging.onMessage.listen(
        _handleWebForegroundMessage,
        onError: (e) => debugPrint('[FCM:Web] onMessage error: $e'),
      );

      // 2. Register FCM token (needs VAPID key for web push)
      await _registerToken();
      _fcm.onTokenRefresh.listen(_uploadToken);

      _initialized = true;
      debugPrint('[FCM:Web] Initialized ✓');
    } catch (e, st) {
      debugPrint('[FCM:Web] Init error: $e\n$st');
    }
  }

  Future<void> _handleWebForegroundMessage(RemoteMessage message) async {
    final title = message.notification?.title ?? message.data['title'] as String? ?? AppConstants.appName;
    final body  = message.notification?.body  ?? message.data['body']  as String? ?? '';
    debugPrint('[FCM:Web:FG] $title — $body');
    if (title.isEmpty && body.isEmpty) return;

    // Show browser notification if permission is granted
    try {
      await _fcm.requestPermission(alert: true, badge: true, sound: true);
    } catch (_) {}
  }

  // ── Android notification channel ──────────────────────────────────────────
  static const AndroidNotificationChannel _notifChannel =
      AndroidNotificationChannel(
    AppConstants.fcmChannelId,
    AppConstants.fcmChannelName,
    description: AppConstants.fcmChannelDesc,
    importance: Importance.max,
    enableVibration: true,
    playSound: true,
    showBadge: true,
  );

  // ── Notification tap callback (foreground local notifications) ─────────────
  void Function(String deepLink)? onDeepLink;

  void _onNotificationTap(NotificationResponse response) {
    if (response.payload == null || response.payload!.isEmpty) return;
    try {
      final data = jsonDecode(response.payload!) as Map<String, dynamic>;
      _reportOpened(data);
      final dl = data['deep_link'] as String?;
      if (dl != null && dl.isNotEmpty) {
        debugPrint('[FCM] Notification tapped → $dl');
        onDeepLink?.call(dl);
      }
    } catch (e) {
      debugPrint('[FCM] Tap parse error: $e');
    }
  }

  // ── Report notification open to backend ───────────────────────────────────
  static void _reportOpened(Map<String, dynamic> data) {
    final logId = data['notification_log_id'] as String?;
    if (logId == null || logId.isEmpty) return;
    ApiClient.instance.post('/notifications/opened/$logId').catchError((_) {});
    debugPrint('[FCM] Reported open for log #$logId');
  }

  /// Call this from main() after initialize() to handle background/killed taps
  Future<void> setupOpenedHandlers() async {
    // Background tap (app was in background, NOT killed, when user tapped).
    // onDeepLink is set by _setupNotificationNavigation() right after this call.
    FirebaseMessaging.onMessageOpenedApp.listen((msg) {
      _reportOpened(msg.data);
      final dl = msg.data['deep_link'] as String?;
      if (dl != null && dl.isNotEmpty) onDeepLink?.call(dl);
    });

    // Killed-app tap: deep_link is already stored in pendingColdStartDeepLink
    // by main.dart (before runApp). SplashScreen reads it and pushes after /home.
    // We only call _reportOpened here — no navigation (onDeepLink is null at this point).
    final initial = await _fcm.getInitialMessage();
    if (initial != null) {
      _reportOpened(initial.data);
      // Do NOT call onDeepLink here — it hasn't been set yet.
      // SplashScreen handles the navigation via pendingColdStartDeepLink.
    }
  }

  // ── Foreground message handler (native only) ─────────────────────────────
  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    if (kIsWeb) return; // web uses _handleWebForegroundMessage
    debugPrint('[FCM:FG] Received: ${message.messageId}');
    debugPrint('[FCM:FG] Title: ${message.notification?.title} | Body: ${message.notification?.body}');
    debugPrint('[FCM:FG] Data: ${message.data}');

    // Handle incoming call — show native callkit overlay
    if (message.data['type'] == 'incoming_call') {
      await _showCallkitIncoming(Map<String, dynamic>.from(message.data));
      return;
    }

    final title = message.notification?.title
        ?? message.data['title'] as String?
        ?? AppConstants.appName;
    final body = message.notification?.body
        ?? message.data['body'] as String?
        ?? '';

    if (title.isEmpty && body.isEmpty) {
      debugPrint('[FCM:FG] Empty notification — skipping');
      return;
    }

    // Resolve image URL from multiple possible sources
    final imageUrl = message.notification?.android?.imageUrl
        ?? message.notification?.apple?.imageUrl
        ?? message.data['image'] as String?
        ?? message.data['image_url'] as String?;

    // Download image if present
    final imageFile = imageUrl != null && imageUrl.isNotEmpty
        ? await _downloadImage(imageUrl)
        : null;

    try {
      AndroidNotificationDetails androidDetails;

      if (imageFile != null) {
        // Rich notification with BigPicture
        androidDetails = AndroidNotificationDetails(
          _notifChannel.id,
          _notifChannel.name,
          channelDescription: _notifChannel.description,
          importance: Importance.max,
          priority: Priority.high,
          icon: 'ic_notification',
          color: AppColors.secondary,
          enableVibration: true,
          playSound: true,
          largeIcon: FilePathAndroidBitmap(imageFile.path),
          styleInformation: BigPictureStyleInformation(
            FilePathAndroidBitmap(imageFile.path),
            contentTitle: title,
            summaryText: body,
            htmlFormatContentTitle: false,
            htmlFormatSummaryText: false,
            hideExpandedLargeIcon: false,
          ),
        );
        debugPrint('[FCM:FG] Showing with image: ${imageFile.path}');
      } else {
        // Text-only notification
        androidDetails = AndroidNotificationDetails(
          _notifChannel.id,
          _notifChannel.name,
          channelDescription: _notifChannel.description,
          importance: Importance.max,
          priority: Priority.high,
          icon: 'ic_notification',
          color: AppColors.secondary,
          enableVibration: true,
          playSound: true,
        );
      }

      await _localNotif.show(
        DateTime.now().millisecondsSinceEpoch % 2147483647,
        title,
        body,
        NotificationDetails(
          android: androidDetails,
          iOS: const DarwinNotificationDetails(
            presentAlert: true,
            presentBadge: true,
            presentSound: true,
            attachments: [],
          ),
        ),
        payload: jsonEncode(message.data),
      );
      debugPrint('[FCM:FG] Notification shown ✓');
    } catch (e, st) {
      debugPrint('[FCM:FG] show() error: $e\n$st');
    }
  }

  // ── Download image to temp dir (native only) ─────────────────────────────
  Future<File?> _downloadImage(String url) async {
    if (kIsWeb) return null;
    try {
      final dir = await getTemporaryDirectory();
      final filename = 'fcm_img_${url.hashCode.abs()}.jpg';
      final file = File('${dir.path}/$filename');
      if (await file.exists()) {
        debugPrint('[FCM] Using cached image: $filename');
        return file;
      }
      final dio = Dio();
      final response = await dio.get<List<int>>(
        url,
        options: Options(responseType: ResponseType.bytes, sendTimeout: const Duration(seconds: 10), receiveTimeout: const Duration(seconds: 10)),
      );
      await file.writeAsBytes(response.data!);
      debugPrint('[FCM] Image downloaded: $filename (${response.data!.length} bytes)');
      return file;
    } catch (e) {
      debugPrint('[FCM] Image download failed: $e');
      return null;
    }
  }

  // ── Permission ────────────────────────────────────────────────────────────
  /// Call from a post-frame callback (after UI is ready) to show system dialog.
  /// On Android 13+  the system dialog is shown by requestNotificationsPermission().
  /// On Android <13  there is no runtime permission — notifications are always allowed.
  /// On iOS          Firebase shows its own dialog.
  Future<bool> requestPermissionIfNeeded() async {
    // Request permission — works on iOS, Android 13+, and web (browser dialog)
    await _fcm.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    if (!kIsWeb) {
      // Android 13+ system notification permission dialog
      try {
        await _localNotif
            .resolvePlatformSpecificImplementation<
                AndroidFlutterLocalNotificationsPlugin>()
            ?.requestNotificationsPermission();
      } catch (e) {
        debugPrint('[FCM] Android permission request error: $e');
      }
    }

    final settings = await _fcm.getNotificationSettings();
    final granted = settings.authorizationStatus == AuthorizationStatus.authorized
        || settings.authorizationStatus == AuthorizationStatus.provisional;
    debugPrint('[FCM] Permission status: ${settings.authorizationStatus}');
    return granted;
  }

  // ── Token management ──────────────────────────────────────────────────────
  Future<void> _registerToken() async {
    try {
      String? token;
      if (kIsWeb) {
        if (AppConstants.webVapidKey.isEmpty) {
          debugPrint('[FCM:Web] VAPID key not set — skipping web token registration');
          return;
        }
        token = await _fcm.getToken(vapidKey: AppConstants.webVapidKey);
      } else {
        token = await _fcm.getToken();
      }
      if (token == null) {
        debugPrint('[FCM] getToken() returned null');
        return;
      }
      debugPrint('[FCM] Token: ${token.substring(0, 20)}...');
      await _uploadToken(token);
    } catch (e) {
      debugPrint('[FCM] Token registration error: $e');
    }
  }

  // ── Token upload timestamp key ───────────────────────────────────────────
  static const _kTokenUploadedAt = 'fcm_token_uploaded_at';
  static const _kForceUploadIntervalH = 12; // re-upload every 12 hours minimum

  /// Upload token to server.
  /// [force] = always upload even if token matches local cache.
  ///   Used on app-resume so a server-side token clear gets repaired.
  Future<void> _uploadToken(String token, {bool force = false}) async {
    final stored = await LocalStorage.getString(AppConstants.fcmTokenKey);
    if (!force && stored == token) {
      debugPrint('[FCM] Token unchanged — skipping upload');
      return;
    }
    try {
      await ApiClient.instance.post('/auth/fcm-token', data: {'fcm_token': token});
      await LocalStorage.saveString(AppConstants.fcmTokenKey, token);
      // Record last upload time
      await LocalStorage.saveString(_kTokenUploadedAt, DateTime.now().toIso8601String());
      debugPrint('[FCM] Token uploaded ✓ (force=$force)');
    } catch (e) {
      // Clear cached token so next launch retries
      await LocalStorage.remove(AppConstants.fcmTokenKey);
      debugPrint('[FCM] Token upload failed: $e');
    }
  }

  /// Called on every app resume (AppLifecycleState.resumed).
  ///
  /// Always re-uploads if the token hasn't been confirmed to the server in the
  /// last [_kForceUploadIntervalH] hours — this repairs server-side token clears
  /// (403 SenderId mismatch / 404 UNREGISTERED auto-clear) where the local
  /// cache still holds the old token so the "unchanged" check would incorrectly
  /// skip the upload and leave the DB token null.
  Future<void> refreshTokenIfNeeded() async {
    if (!_initialized) return;
    try {
      final token = await _fcm.getToken();
      if (token == null) return;

      final stored    = await LocalStorage.getString(AppConstants.fcmTokenKey);
      final lastStr   = await LocalStorage.getString(_kTokenUploadedAt);
      final lastAt    = lastStr != null ? DateTime.tryParse(lastStr) : null;
      final stale     = lastAt == null ||
          DateTime.now().difference(lastAt).inHours >= _kForceUploadIntervalH;

      if (stored != token || stale) {
        // Token changed OR 12h since last server confirm — force upload.
        // This repairs any server-side clear without waiting for onTokenRefresh.
        debugPrint('[FCM] Resume upload — changed:${stored != token} stale:$stale');
        await _uploadToken(token, force: true);
      }
    } catch (e) {
      debugPrint('[FCM] Refresh error: $e');
    }
  }

  /// Call after login so the current user's token is registered.
  Future<void> registerTokenAfterLogin() async {
    await LocalStorage.remove(AppConstants.fcmTokenKey);
    await LocalStorage.remove(_kTokenUploadedAt);
    await _registerToken();
  }

  Future<String?> getToken() => _fcm.getToken();

  Future<void> deleteToken() async {
    try {
      await _fcm.deleteToken();
    } catch (_) {}
    await LocalStorage.remove(AppConstants.fcmTokenKey);
  }
}
