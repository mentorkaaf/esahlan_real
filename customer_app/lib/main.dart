import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/constants/app_constants.dart';
import 'core/providers/theme_provider.dart';
import 'core/router/app_router.dart';
import 'core/services/firebase_service.dart';
import 'core/services/location_service.dart';
import 'core/theme/app_theme.dart';
import 'core/widgets/connectivity_wrapper.dart';
import 'firebase_options.dart';

// Cold-start deep link captured before runApp()
String? _coldStartDeepLink;

// ─────────────────────────────────────────────────────────────────────────────
// main() — keep lean: only native-level setup + runApp()
// flutter_local_notifications must NOT be initialized here (plugin not bound yet)
// ─────────────────────────────────────────────────────────────────────────────
void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  try {
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);

    // Native-level FCM setup: background handler + iOS foreground options
    await FirebaseService.setupBeforeRunApp();

    // Capture deep link from a cold-start notification tap (app was killed)
    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null) {
      _coldStartDeepLink = initial.data['deep_link'] as String?;
      debugPrint('[FCM] Cold-start deep link: $_coldStartDeepLink');
    }
  } catch (e) {
    debugPrint('[Firebase] Pre-runApp error: $e');
  }

  await LocationService.initBackground();

  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.dark,
  ));

  runApp(const ProviderScope(child: eSahlanApp()));
}

// ─────────────────────────────────────────────────────────────────────────────
class eSahlanApp extends ConsumerStatefulWidget {
  const eSahlanApp({super.key});
  @override
  ConsumerState<eSahlanApp> createState() => _eSahlanAppState();
}

class _eSahlanAppState extends ConsumerState<eSahlanApp>
    with WidgetsBindingObserver {

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    // Post-frame: plugin registry is fully bound after first frame
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      // 1. Initialize flutter_local_notifications + onMessage listener
      await FirebaseService().initialize();

      // 2. Wire up deep-link navigation (router is ready by now)
      _setupNotificationNavigation();

      // 3. Request notification permission (300ms after init so UI is stable)
      Future.delayed(const Duration(milliseconds: 300), () {
        if (mounted) FirebaseService().requestPermissionIfNeeded();
      });
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
      // Re-upload token in case it was rotated while app was backgrounded
      FirebaseService().refreshTokenIfNeeded();
    }
  }

  void _setupNotificationNavigation() {
    void navigate(String path) {
      try {
        ref.read(routerProvider).push(path);
        debugPrint('[Nav] Navigated to: $path');
      } catch (e) {
        debugPrint('[Nav] Error navigating to $path: $e');
      }
    }

    // Foreground local-notification tap
    FirebaseService().onDeepLink = navigate;

    // Background tap (app was minimised, user tapped the FCM banner)
    FirebaseMessaging.onMessageOpenedApp.listen((message) {
      final dl = message.data['deep_link'] as String?;
      debugPrint('[FCM] onMessageOpenedApp deep_link: $dl');
      if (dl != null && dl.isNotEmpty) navigate(dl);
    });

    // Cold-start tap (app was fully killed)
    if (_coldStartDeepLink != null) {
      final dl = _coldStartDeepLink!;
      _coldStartDeepLink = null;
      // Small delay so the router redirect has settled
      Future.delayed(const Duration(milliseconds: 1500), () {
        if (mounted) navigate(dl);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final themeMode = ref.watch(themeModeProvider);
    return ConnectivityWrapper(
      child: MaterialApp.router(
        title: AppConstants.appName,
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        darkTheme: AppTheme.dark,
        themeMode: themeMode,
        routerConfig: ref.watch(routerProvider),
      ),
    );
  }
}
