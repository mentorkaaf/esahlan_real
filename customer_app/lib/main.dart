import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/api/http_cache_interceptor.dart';
import 'core/router/app_router.dart';
import 'core/services/firebase_service.dart';
import 'core/theme/app_theme.dart';
import 'core/widgets/connectivity_wrapper.dart';
import 'firebase_options.dart';

/// Deep link from cold-start (app was killed when notification was tapped).
String? _coldStartDeepLink;

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // ── Firebase ────────────────────────────────────────────────────────────
  try {
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);

    // Grab initial message BEFORE runApp so it's available during first build
    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null) {
      _coldStartDeepLink = initial.data['deep_link'] as String?;
      debugPrint('[FCM] Cold-start deep link: $_coldStartDeepLink');
    }

    await FirebaseService().initialize();
  } catch (e) {
    debugPrint('[Firebase] Init error (non-fatal): $e');
  }

  // ── HTTP Cache ──────────────────────────────────────────────────────────
  await HttpCacheInterceptor.init();

  // ── UI ───────────────────────────────────────────────────────────────────
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.dark,
  ));

  runApp(const ProviderScope(child: eSahlanApp()));
}

class eSahlanApp extends ConsumerStatefulWidget {
  const eSahlanApp({super.key});
  @override
  ConsumerState<eSahlanApp> createState() => _eSahlanAppState();
}

class _eSahlanAppState extends ConsumerState<eSahlanApp> {
  @override
  void initState() {
    super.initState();
    _setupNotifications();
  }

  void _setupNotifications() {
    // ── Helper: navigate safely ────────────────────────────────────────────
    void navigate(String path) {
      try {
        debugPrint('[FCM] Navigating to: $path');
        ref.read(routerProvider).go(path);
      } catch (e) {
        debugPrint('[FCM] Navigation error: $e');
      }
    }

    // ── 1. Foreground local-notification tap ───────────────────────────────
    // Fires when user taps the heads-up notification shown by flutter_local_notifications
    FirebaseService().onDeepLink = navigate;

    // ── 2. Background tap (app was open but in background) ─────────────────
    // Fires when user taps the FCM notification and app comes to foreground
    FirebaseMessaging.onMessageOpenedApp.listen((message) {
      debugPrint('[FCM] onMessageOpenedApp data: ${message.data}');
      final dl = message.data['deep_link'] as String?;
      if (dl != null && dl.isNotEmpty) navigate(dl);
    });

    // ── 3. Cold-start (app was killed when notification was tapped) ─────────
    if (_coldStartDeepLink != null) {
      final dl = _coldStartDeepLink!;
      _coldStartDeepLink = null;
      // Delay so splash/auth guards finish before we push the target route
      Future.delayed(const Duration(milliseconds: 2000), () {
        if (mounted) navigate(dl);
      });
    }

    // ── 4. Request permission after first frame (Android 13+) ──────────────
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Future.delayed(const Duration(milliseconds: 1500), () {
        if (mounted) FirebaseService().requestPermissionIfNeeded();
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    final router = ref.watch(routerProvider);
    return ConnectivityWrapper(
      child: MaterialApp.router(
        title: 'eSahlan',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        routerConfig: router,
      ),
    );
  }
}
