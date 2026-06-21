import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import 'package:permission_handler/permission_handler.dart';
import 'core/router/app_router.dart';
import 'core/services/firebase_service.dart';
import 'core/services/location_service.dart';
import 'core/theme/driver_theme.dart';
import 'core/constants/app_constants.dart';
import 'firebase_options.dart';

String? _coldDeepLink;

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  try {
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
    await FirebaseService.setupBeforeRunApp();
    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null) _coldDeepLink = initial.data['deep_link'];
  } catch (e) {
    debugPrint('[Firebase] Init error: $e');
  }

  await DriverLocationService.initBackground();

  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.light,
    systemNavigationBarColor: Color(0xFF12233D),
  ));

  runApp(const ProviderScope(child: DriverApp()));
}

class DriverApp extends ConsumerStatefulWidget {
  const DriverApp({super.key});
  @override
  ConsumerState<DriverApp> createState() => _DriverAppState();
}

class _DriverAppState extends ConsumerState<DriverApp> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await FirebaseService().initialize();
      _setupNotifications();
      _requestPermissions();
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      FirebaseService().refreshTokenIfNeeded();
    }
  }

  Future<void> _requestPermissions() async {
    // Location
    LocationPermission locPerm = await Geolocator.checkPermission();
    if (locPerm == LocationPermission.denied) {
      locPerm = await Geolocator.requestPermission();
    }
    if (locPerm == LocationPermission.deniedForever) {
      await Geolocator.openAppSettings();
    }

    // Notifications
    await Permission.notification.request();
  }

  void _setupNotifications() {
    void navigate(String path) {
      try { ref.read(routerProvider).go(path); } catch (_) {}
    }

    FirebaseService().onDeepLink = navigate;

    FirebaseMessaging.onMessageOpenedApp.listen((msg) {
      final dl = msg.data['deep_link'] as String?;
      if (dl != null) navigate(dl);
    });

    if (_coldDeepLink != null) {
      final dl = _coldDeepLink!;
      _coldDeepLink = null;
      Future.delayed(const Duration(milliseconds: 1500), () {
        if (mounted) navigate(dl);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp.router(
      title: AppConstants.appName,
      debugShowCheckedModeBanner: false,
      theme: DriverTheme.dark,
      routerConfig: ref.watch(routerProvider),
    );
  }
}
