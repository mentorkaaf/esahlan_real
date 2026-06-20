import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';
import 'package:workmanager/workmanager.dart';
import '../constants/app_constants.dart';
import '../storage/local_storage.dart';
import '../../features/auth/data/repositories/auth_repository.dart';

const _bgTaskName = 'esahlan_location_update';
const _bgTaskTag  = 'location';

@pragma('vm:entry-point')
void callbackDispatcher() {
  Workmanager().executeTask((taskName, inputData) async {
    if (taskName == _bgTaskName) {
      await _backgroundPostLocation();
    }
    return true;
  });
}

Future<void> _backgroundPostLocation() async {
  try {
    final permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) return;

    final pos = await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.medium,
        timeLimit: Duration(seconds: 15),
      ),
    );

    final token = await LocalStorage.getToken();
    if (token == null) return;

    final client = HttpClient();
    try {
      final uri = Uri.parse('${AppConstants.baseUrl}/auth/location');
      final request = await client.postUrl(uri);
      request.headers.set('Authorization', 'Bearer $token');
      request.headers.set('Content-Type', 'application/json');
      request.headers.set('Accept', 'application/json');
      request.write(jsonEncode({
        'latitude': pos.latitude,
        'longitude': pos.longitude,
      }));
      final response = await request.close();
      await response.drain();
    } finally {
      client.close();
    }
  } catch (e) {
    debugPrint('[LocationBG] Error: $e');
  }
}

class LocationService {
  LocationService._();

  static final _repo = AuthRepository();
  static Timer? _timer;
  static bool _running = false;

  static Future<void> initBackground() async {
    await Workmanager().initialize(callbackDispatcher, isInDebugMode: false);
  }

  static void startTracking() {
    if (_running) return;
    _running = true;

    _postLocation();
    _timer = Timer.periodic(const Duration(minutes: 5), (_) => _postLocation());

    Workmanager().registerPeriodicTask(
      _bgTaskName,
      _bgTaskName,
      tag: _bgTaskTag,
      frequency: const Duration(minutes: 15),
      existingWorkPolicy: ExistingPeriodicWorkPolicy.keep,
      constraints: Constraints(networkType: NetworkType.connected),
      backoffPolicy: BackoffPolicy.linear,
      backoffPolicyDelay: const Duration(minutes: 5),
    );
  }

  static void stopTracking() {
    _running = false;
    _timer?.cancel();
    _timer = null;
    Workmanager().cancelByTag(_bgTaskTag);
  }

  static void onResume() => _postLocation();

  static Future<void> _postLocation() async {
    try {
      final token = await LocalStorage.getToken();
      if (token == null) return;

      final permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) return;

      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.medium),
      );

      await LocalStorage.saveDouble('saved_lat', pos.latitude);
      await LocalStorage.saveDouble('saved_lng', pos.longitude);
      await _repo.updateLocation(pos.latitude, pos.longitude);
    } catch (_) {}
  }
}
