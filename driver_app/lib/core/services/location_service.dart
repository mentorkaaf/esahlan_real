import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:workmanager/workmanager.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Config constants
// ─────────────────────────────────────────────────────────────────────────────
const _kServiceId       = 1001;
const _kChannelId       = 'esahlan_driver_location';
const _kBgTaskName      = 'driver_location_bg';
const _kIntervalMs      = 5000;   // 5 s — real-time foreground interval
// Fix M-10: Android WorkManager enforces a minimum of 15 minutes for periodic
// tasks regardless of what value you pass. 5 was silently rounded up to 15.
const _kBgIntervalMin   = 15;     // WorkManager minimum (Android OS enforced)

// ─────────────────────────────────────────────────────────────────────────────
// Isolated HTTP helper — safe to call from Isolate / background entry points
// ─────────────────────────────────────────────────────────────────────────────
Future<void> _postLocationHttp({int? orderId}) async {
  try {
    final perm = await Geolocator.checkPermission();
    if (perm == LocationPermission.denied ||
        perm == LocationPermission.deniedForever) {
      return;
    }

    final pos = await Geolocator.getCurrentPosition(
      locationSettings: AndroidSettings(
        accuracy: LocationAccuracy.bestForNavigation,
        distanceFilter: 0,
        forceLocationManager: false,
        intervalDuration: const Duration(seconds: 5),
      ),
    );

    final token = await LocalStorage.getToken();
    if (token == null) return;

    final client = HttpClient();
    try {
      final uri = Uri.parse('${AppConstants.baseUrl}/delivery/location');
      final req  = await client.postUrl(uri);
      req.headers.set('Authorization', 'Bearer $token');
      req.headers.set('Content-Type', 'application/json');
      req.headers.set('Accept', 'application/json');
      req.write(jsonEncode({
        'latitude':  pos.latitude,
        'longitude': pos.longitude,
        'accuracy':  pos.accuracy,
        'speed':     pos.speed,
        'heading':   pos.heading,
        'timestamp': pos.timestamp.toIso8601String(),
        if (orderId != null) 'order_id': orderId,
      }));
      final res = await req.close();
      await res.drain();
      debugPrint('[GPS] ✓ ${pos.latitude.toStringAsFixed(5)},${pos.longitude.toStringAsFixed(5)}');
    } finally {
      client.close();
    }
  } catch (e) {
    debugPrint('[GPS] post failed: $e');
  }
}

/// Public wrapper so firebase_service.dart (and WorkManager) can call the
/// HTTP post without importing private internals.
Future<void> postLocationForFcm({int? orderId}) => _postLocationHttp(orderId: orderId);

// ─────────────────────────────────────────────────────────────────────────────
// WorkManager dispatcher — runs in a separate Isolate
// ─────────────────────────────────────────────────────────────────────────────
@pragma('vm:entry-point')
void locationCallbackDispatcher() {
  Workmanager().executeTask((taskName, inputData) async {
    if (taskName == _kBgTaskName) {
      final orderId = inputData?['order_id'] as int?;
      await _postLocationHttp(orderId: orderId);
    }
    return true;
  });
}

// ─────────────────────────────────────────────────────────────────────────────
// Foreground Task Handler — lives inside the persistent foreground service
// ─────────────────────────────────────────────────────────────────────────────
@pragma('vm:entry-point')
class _LocationTaskHandler extends TaskHandler {
  int?   _orderId;
  Timer? _timer;
  Timer? _watchdog; // restarts timer if it ever dies

  // ── boot entry: called in the foreground service isolate ──────────────────
  @override
  Future<void> onStart(DateTime timestamp, TaskStarter starter) async {
    debugPrint('[FGTask] started (starter: $starter)');
    // Post one location immediately on start; subsequent posts come from
    // onRepeatEvent which is fired every _kIntervalMs by the FG task engine.
    _postLocationHttp(orderId: _orderId);
  }

  @override
  void onReceiveData(Object data) {
    if (data is Map) {
      final oid = data['order_id'];
      _orderId = oid is int ? oid : (oid != null ? int.tryParse('$oid') : null);
      debugPrint('[FGTask] order_id → $_orderId');
    }
  }

  @override
  void onRepeatEvent(DateTime timestamp) {
    // Fix H-1: onRepeatEvent is the ONLY source of periodic posts.
    // The internal _timer has been removed to avoid double-posting every 5 s.
    _postLocationHttp(orderId: _orderId);
  }

  @override
  Future<void> onDestroy(DateTime timestamp) async {
    debugPrint('[FGTask] destroyed');
  }

  // Fix H-1: removed _startTimer / _startWatchdog — onRepeatEvent (fired by
  // flutter_foreground_task every _kIntervalMs) is the sole periodic source.
  // Having both a Timer AND onRepeatEvent caused two HTTP posts per tick.
}

// entry point registered with flutter_foreground_task
@pragma('vm:entry-point')
void _fgTaskCallback() {
  FlutterForegroundTask.setTaskHandler(_LocationTaskHandler());
}

// ─────────────────────────────────────────────────────────────────────────────
// Public API
// ─────────────────────────────────────────────────────────────────────────────
// Fix C-3 persistence key
const _kWasTrackingKey = 'driver_was_tracking';

class DriverLocationService {
  DriverLocationService._();

  static bool _running = false;
  // Fix C-2: guard flag to prevent concurrent startTracking calls
  static bool _starting = false;
  static int? _activeOrderId;

  // ── Call once from main() before runApp ──────────────────────────────────
  static Future<void> initBackground() async {
    // WorkManager fallback (keeps firing every 15 min even if foreground dies)
    await Workmanager().initialize(locationCallbackDispatcher);

    // Foreground task configuration
    FlutterForegroundTask.init(
      androidNotificationOptions: AndroidNotificationOptions(
        channelId: _kChannelId,
        channelName: 'eSahlan Driver — Location',
        channelDescription: 'Keeps your location active for real-time delivery tracking.',
        channelImportance: NotificationChannelImportance.LOW,
        priority: NotificationPriority.LOW,
        onlyAlertOnce: true,
        playSound: false,
        enableVibration: false,
      ),
      iosNotificationOptions: const IOSNotificationOptions(
        showNotification: true,
        playSound: false,
      ),
      foregroundTaskOptions: ForegroundTaskOptions(
        // onRepeatEvent fires every 5 s as a backup to our internal timer
        eventAction: ForegroundTaskEventAction.repeat(_kIntervalMs),
        autoRunOnBoot: true,
        autoRunOnMyPackageReplaced: true,
        allowWakeLock: true,
        allowWifiLock: true,
      ),
    );

    // Auto-resume if driver was tracking before the device restarted / app updated
    final wasTracking = await _getWasTracking();
    final token       = await LocalStorage.getToken();
    if (token != null && wasTracking) {
      debugPrint('[GPS] auto-resume after boot/reinstall');
      await startTracking();
    }
  }

  // ── Request all needed permissions (call from UI after login) ─────────────
  static Future<void> requestPermissions() async {
    // Basic location
    LocationPermission perm = await Geolocator.checkPermission();
    if (perm == LocationPermission.denied) {
      perm = await Geolocator.requestPermission();
    }
    if (perm == LocationPermission.deniedForever) {
      await Geolocator.openAppSettings();
      return;
    }

    if (Platform.isAndroid) {
      // Background location (Android 10+) — needed for tracking when app is not foreground
      final bgPerm = await Permission.locationAlways.status;
      if (bgPerm.isDenied) {
        await Permission.locationAlways.request();
      }

      // Battery optimisation exclusion — essential for keeping service alive on HONOR/Huawei
      if (!await FlutterForegroundTask.isIgnoringBatteryOptimizations) {
        await FlutterForegroundTask.requestIgnoreBatteryOptimization();
      }

      // Overlay permission (SYSTEM_ALERT_WINDOW) — needed for fullScreenIntent on HONOR
      final overlayPerm = await Permission.systemAlertWindow.status;
      if (overlayPerm.isDenied) {
        await Permission.systemAlertWindow.request();
      }

      // Notification permission (Android 13+)
      final notifPerm = await Permission.notification.status;
      if (notifPerm.isDenied) {
        await Permission.notification.request();
      }
    }
  }

  // ── Request overlay permission separately (call on ring order first launch) ─
  static Future<void> requestOverlayPermission(BuildContext? context) async {
    if (!Platform.isAndroid) return;
    final status = await Permission.systemAlertWindow.status;
    if (!status.isGranted) {
      await Permission.systemAlertWindow.request();
    }
  }

  // ── Start tracking ────────────────────────────────────────────────────────
  static Future<void> startTracking({int? orderId}) async {
    if (_running) {
      // Just update the active order if already running
      if (orderId != null) {
        _activeOrderId = orderId;
        FlutterForegroundTask.sendDataToTask({'order_id': orderId});
      }
      return;
    }
    // Fix C-2: prevent concurrent startTracking calls (race condition)
    if (_starting) return;
    _starting = true;

    _running       = true;
    _activeOrderId = orderId;
    await _setWasTracking(true);

    // Ensure foreground task is launched
    if (await FlutterForegroundTask.isRunningService) {
      await FlutterForegroundTask.restartService();
    } else {
      await FlutterForegroundTask.startService(
        serviceId:        _kServiceId,
        notificationTitle: 'eSahlan Driver — Active',
        notificationText:  'Location tracking on · Real-time',
        callback:          _fgTaskCallback,
      );
    }

    // WorkManager fallback — fires every 15 min if the foreground service is killed
    await Workmanager().registerPeriodicTask(
      _kBgTaskName, _kBgTaskName,
      tag:                _kBgTaskName,
      frequency:          const Duration(minutes: _kBgIntervalMin),
      existingWorkPolicy: ExistingPeriodicWorkPolicy.keep,
      inputData:          orderId != null ? {'order_id': orderId} : null,
      // No network constraint — fires regardless of connectivity.
      // _postLocationHttp has try/catch so it silently retries next tick.
    );

    _starting = false; // Fix C-2: release concurrent-start guard
    debugPrint('[GPS] tracking started (orderId: $orderId)');
  }

  // ── Stop tracking ─────────────────────────────────────────────────────────
  static Future<void> stopTracking() async {
    _running       = false;
    _starting      = false;
    _activeOrderId = null;
    await _setWasTracking(false);
    await FlutterForegroundTask.stopService();
    await Workmanager().cancelByTag(_kBgTaskName);
    debugPrint('[GPS] tracking stopped');
  }

  // ── Update active order without restarting service ────────────────────────
  static void setActiveOrder(int? orderId) {
    _activeOrderId = orderId;
    FlutterForegroundTask.sendDataToTask({'order_id': orderId});
    // Also update WorkManager task with new order_id
    if (orderId != null && _running) {
      Workmanager().registerPeriodicTask(
        _kBgTaskName, _kBgTaskName,
        tag:                _kBgTaskName,
        frequency:          const Duration(minutes: _kBgIntervalMin),
        existingWorkPolicy: ExistingPeriodicWorkPolicy.replace,
        inputData:          {'order_id': orderId},
      );
    }
  }

  // ── Post a single location update immediately (call on order accept) ──────
  static Future<void> pingNow({int? orderId}) async {
    await _postLocationHttp(orderId: orderId ?? _activeOrderId);
  }

  static bool get isRunning => _running;

  // ── Persistence helpers ────────────────────────────────────────────────────
  // Fix C-3: persist the actual tracking state rather than inferring it from
  // the login token. Previously, every logged-in driver had GPS auto-resumed
  // after a reboot even if they had manually gone offline.
  static Future<bool> _getWasTracking() async {
    try {
      final token = await LocalStorage.getToken();
      if (token == null) return false; // not logged in
      return await LocalStorage.getBool(_kWasTrackingKey);
    } catch (_) {
      return false;
    }
  }

  static Future<void> _setWasTracking(bool value) async {
    try {
      await LocalStorage.saveBool(_kWasTrackingKey, value);
      debugPrint('[GPS] persist wasTracking=$value');
    } catch (_) {}
  }
}
