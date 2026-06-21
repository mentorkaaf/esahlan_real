import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';
import 'package:workmanager/workmanager.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';

const _bgTask = 'driver_location_update';

@pragma('vm:entry-point')
void locationCallbackDispatcher() {
  Workmanager().executeTask((taskName, inputData) async {
    if (taskName == _bgTask) await _bgPostLocation();
    return true;
  });
}

Future<void> _bgPostLocation() async {
  try {
    final perm = await Geolocator.checkPermission();
    if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) return;
    final pos = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 15)),
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
      req.write(jsonEncode({'latitude': pos.latitude, 'longitude': pos.longitude}));
      final res = await req.close();
      await res.drain();
    } finally {
      client.close();
    }
  } catch (e) {
    debugPrint('[LocBG] $e');
  }
}

class DriverLocationService {
  DriverLocationService._();
  static Timer? _timer;
  static bool _running = false;
  static int? _activeOrderId;

  static Future<void> initBackground() async {
    await Workmanager().initialize(locationCallbackDispatcher);
  }

  static void startTracking({int? orderId}) {
    if (_running) return;
    _running = true;
    _activeOrderId = orderId;
    _postLocation();
    _timer = Timer.periodic(const Duration(seconds: 10), (_) => _postLocation());
    Workmanager().registerPeriodicTask(_bgTask, _bgTask,
      tag: 'driver_loc',
      frequency: const Duration(minutes: 15),
      existingWorkPolicy: ExistingPeriodicWorkPolicy.keep,
      constraints: Constraints(networkType: NetworkType.connected),
    );
  }

  static void stopTracking() {
    _running = false;
    _activeOrderId = null;
    _timer?.cancel();
    _timer = null;
    Workmanager().cancelByTag('driver_loc');
  }

  static void setActiveOrder(int? orderId) => _activeOrderId = orderId;

  static Future<void> _postLocation() async {
    try {
      final token = await LocalStorage.getToken();
      if (token == null) return;
      final perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied || perm == LocationPermission.deniedForever) return;
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      );
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
          if (_activeOrderId != null) 'order_id': _activeOrderId,
        }));
        final res = await req.close();
        await res.drain();
      } finally {
        client.close();
      }
    } catch (_) {}
  }
}
