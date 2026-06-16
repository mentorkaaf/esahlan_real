import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import 'core/router/app_router.dart';
import 'core/services/firebase_service.dart';
import 'core/theme/app_theme.dart';
import 'core/widgets/connectivity_wrapper.dart';
import 'firebase_options.dart';

String? _coldStartDeepLink;

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  try {
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);

    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null) {
      _coldStartDeepLink = initial.data['deep_link'] as String?;
    }

    await FirebaseService().initialize();
  } catch (e) {
    debugPrint('[Firebase] Init error: $e');
  }

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

class _eSahlanAppState extends ConsumerState<eSahlanApp>
    with WidgetsBindingObserver {

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _setupNotifications();
    // Ask for notification permission after first frame
    // (must run after runApp so Android dialog can appear)
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Future.delayed(const Duration(milliseconds: 1500), () {
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
      FirebaseService().refreshTokenIfNeeded();
    }
  }

  void _setupNotifications() {
    void navigate(String path) {
      try {
        ref.read(routerProvider).go(path);
      } catch (e) {
        debugPrint('[Nav] Error: $e');
      }
    }

    // Foreground local-notification tap → deep link
    FirebaseService().onDeepLink = navigate;

    // Background tap (app minimized, user taps FCM notification)
    FirebaseMessaging.onMessageOpenedApp.listen((message) {
      final dl = message.data['deep_link'] as String?;
      if (dl != null && dl.isNotEmpty) navigate(dl);
    });

    // Cold-start tap (app was killed)
    if (_coldStartDeepLink != null) {
      final dl = _coldStartDeepLink!;
      _coldStartDeepLink = null;
      Future.delayed(const Duration(milliseconds: 2000), () {
        if (mounted) navigate(dl);
      });
    }
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
