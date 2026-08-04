import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
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
        accuracy: LocationAccuracy.high,
        timeLimit: Duration(seconds: 20),
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

  /// Returns true if location is enabled and permission granted.
  /// If not, requests permission and shows dialog to enable GPS.
  static Future<bool> ensureLocationEnabled(BuildContext context) async {
    bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      await _showLocationRequiredDialog(context);
      serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) return false;
    }

    LocationPermission permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }
    if (permission == LocationPermission.denied) {
      await _showLocationRequiredDialog(context);
      return false;
    }
    if (permission == LocationPermission.deniedForever) {
      await _showOpenSettingsDialog(context);
      return false;
    }
    return true;
  }

  static Future<void> _showLocationRequiredDialog(BuildContext context) async {
    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(children: [
          Icon(Icons.location_off_rounded, color: Colors.red, size: 24),
          SizedBox(width: 10),
          Text('Location Required', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
        ]),
        content: Text(
          kIsWeb
              ? 'eSahlan needs your location to deliver orders. Please allow location access in your browser and try again.'
              : 'eSahlan needs your location to deliver orders to you. Please enable location services to continue.',
          style: const TextStyle(fontSize: 13, height: 1.5),
        ),
        actions: [
          TextButton(
            onPressed: () async {
              if (!kIsWeb) await Geolocator.openLocationSettings();
              if (ctx.mounted) Navigator.pop(ctx);
            },
            child: Text(kIsWeb ? 'OK' : 'Open Settings',
                style: const TextStyle(fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    );
  }

  static Future<void> _showOpenSettingsDialog(BuildContext context) async {
    await showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Row(children: [
          Icon(Icons.location_disabled_rounded, color: Colors.red, size: 24),
          SizedBox(width: 10),
          Text('Permission Denied', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
        ]),
        content: Text(
          kIsWeb
              ? 'Location access is blocked in your browser. On Safari: go to Settings → Safari → Location → Allow. Then reload the page.'
              : 'Location permission was permanently denied. Please go to app settings and enable location access for eSahlan.',
          style: const TextStyle(fontSize: 13, height: 1.5),
        ),
        actions: [
          if (!kIsWeb)
            TextButton(
              onPressed: () async {
                await Geolocator.openAppSettings();
                if (ctx.mounted) Navigator.pop(ctx);
              },
              child: const Text('Open App Settings',
                  style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('OK', style: TextStyle(fontWeight: FontWeight.w700)),
          ),
        ],
      ),
    );
  }

  static void startTracking() {
    if (_running) return;
    _running = true;

    _postLocation();
    _timer = Timer.periodic(const Duration(minutes: 5), (_) => _postLocation());

    if (!kIsWeb) {
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
  }

  static void stopTracking() {
    _running = false;
    _timer?.cancel();
    _timer = null;
    if (!kIsWeb) Workmanager().cancelByTag(_bgTaskTag);
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
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high),
      );

      await LocalStorage.saveDouble('saved_lat', pos.latitude);
      await LocalStorage.saveDouble('saved_lng', pos.longitude);
      await _repo.updateLocation(pos.latitude, pos.longitude);
    } catch (_) {}
  }
}
