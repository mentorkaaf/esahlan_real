import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter_foreground_task/flutter_foreground_task.dart';
import 'package:geolocator/geolocator.dart';
import 'package:workmanager/workmanager.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';

// ─── Workmanager fallback (keeps alive if foreground service killed) ───────────
const _bgTask = 'driver_location_update';

@pragma('vm:entry-point')
void locationCallbackDispatcher() {
  Workmanager().executeTask((taskName, inputData) async {
    if (taskName == _bgTask) await _postLocationHttp();
    return true;
  });
}

// ─── Core HTTP post (used by both foreground service + workmanager) ────────────
Future<void> _postLocationHttp({int? orderId}) async {
  try {
    final perm = await Geolocator.checkPermission();
    if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) return;
    final pos = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 15),
      ),
    );
    final token = await LocalStorage.getToken();
    if (token == null) return;
    final client = HttpClient();
    try {
      final uri = Uri.parse('${AppConstants.baseUrl}/delivery/location');
      final req = await client.postUrl(uri);
      req.headers.set('Authorization', 'Bearer $token');
      req.headers.set('Content-Type', 'application/json');
      req.headers.set('Accept', 'application/json');
      req.write(jsonEncode({
        'latitude': pos.latitude,
        'longitude': pos.longitude,
        if (orderId != null) 'order_id': orderId,
      }));
      final res = await req.close();
      await res.drain();
    } finally {
      client.close();
    }
  } catch (e) {
    debugPrint('[LocBG] $e');
  }
}

// ─── Foreground Task Handler ───────────────────────────────────────────────────
@pragma('vm:entry-point')
class _LocationTaskHandler extends TaskHandler {
  int? _orderId;
  Timer? _timer;

  @override
  Future<void> onStart(DateTime timestamp, TaskStarter starter) async {
    debugPrint('[FGTask] Started');
    _orderId = null;
    // Post immediately on start
    await _postLocationHttp(orderId: _orderId);
    // Then every 10 seconds
    _timer = Timer.periodic(const Duration(seconds: 10), (_) async {
      await _postLocationHttp(orderId: _orderId);
    });
  }

  @override
  void onReceiveData(Object data) {
    if (data is Map && data['order_id'] != null) {
      _orderId = data['order_id'] as int?;
    }
  }

  @override
  Future<void> onDestroy(DateTime timestamp) async {
    _timer?.cancel();
    debugPrint('[FGTask] Destroyed');
  }

  @override
  void onRepeatEvent(DateTime timestamp) {
    // Handled by internal timer
  }
}

// ─── Public API ───────────────────────────────────────────────────────────────
class DriverLocationService {
  DriverLocationService._();
  static bool _running = false;
  // ignore: unused_field
  static int? _activeOrderId;

  /// Call once in main() before runApp
  static Future<void> initBackground() async {
    // Init workmanager fallback
    await Workmanager().initialize(locationCallbackDispatcher);

    // Init foreground task
    FlutterForegroundTask.init(
      androidNotificationOptions: AndroidNotificationOptions(
        channelId: 'esahlan_driver_location',
        channelName: 'Driver Location',
        channelDescription: 'eSahlan is tracking your location for deliveries',
        onlyAlertOnce: true,
        playSound: false,
        enableVibration: false,
      ),
      iosNotificationOptions: const IOSNotificationOptions(
        showNotification: true,
        playSound: false,
      ),
      foregroundTaskOptions: ForegroundTaskOptions(
        eventAction: ForegroundTaskEventAction.repeat(10000), // 10s
        autoRunOnBoot: true,
        autoRunOnMyPackageReplaced: true,
        allowWakeLock: true,
        allowWifiLock: true,
      ),
    );

    // Auto-start if driver was previously logged in (boot/reinstall recovery)
    final token = await LocalStorage.getToken();
    if (token != null) {
      await startTracking();
    }
  }

  static Future<void> startTracking({int? orderId}) async {
    if (_running) {
      if (orderId != null) {
        _activeOrderId = orderId;
        FlutterForegroundTask.sendDataToTask({'order_id': orderId});
      }
      return;
    }
    _running = true;
    _activeOrderId = orderId;

    // Request permissions if needed
    final perm = await Geolocator.checkPermission();
    if (perm == LocationPermission.denied) {
      await Geolocator.requestPermission();
    }

    // Start foreground service
    if (await FlutterForegroundTask.isRunningService) {
      FlutterForegroundTask.restartService();
    } else {
      await FlutterForegroundTask.startService(
        serviceId: 1001,
        notificationTitle: 'eSahlan Driver',
        notificationText: 'Location tracking active',
        callback: _startCallback,
      );
    }

    // Workmanager as fallback (every 15min if foreground service is killed)
    Workmanager().registerPeriodicTask(
      _bgTask, _bgTask,
      tag: 'driver_loc',
      frequency: const Duration(minutes: 15),
      existingWorkPolicy: ExistingPeriodicWorkPolicy.keep,
      constraints: Constraints(networkType: NetworkType.connected),
    );
  }

  static Future<void> stopTracking() async {
    _running = false;
    _activeOrderId = null;
    await FlutterForegroundTask.stopService();
    Workmanager().cancelByTag('driver_loc');
  }

  static void setActiveOrder(int? orderId) {
    _activeOrderId = orderId;
    if (orderId != null) {
      FlutterForegroundTask.sendDataToTask({'order_id': orderId});
    }
  }

  static bool get isRunning => _running;
}

@pragma('vm:entry-point')
void _startCallback() {
  FlutterForegroundTask.setTaskHandler(_LocationTaskHandler());
}
