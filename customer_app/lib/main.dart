import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_callkit_incoming/flutter_callkit_incoming.dart';
import 'package:flutter_callkit_incoming/entities/entities.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:media_kit/media_kit.dart';

import 'core/constants/app_constants.dart';
import 'core/providers/app_settings_provider.dart';
import 'core/providers/theme_provider.dart';
import 'core/router/app_router.dart';
import 'core/services/firebase_service.dart';
import 'core/services/location_service.dart';
import 'core/theme/app_theme.dart';
import 'core/widgets/connectivity_wrapper.dart';
import 'firebase_options.dart';
import 'features/calls/data/repositories/call_repository.dart';
import 'features/podcast/presentation/services/podcast_audio_service.dart';

// Cold-start notification data captured before runApp()
String? _coldStartDeepLink;
// Callkit accept captured before app is mounted
String? _pendingCallkitCallId;

// ─────────────────────────────────────────────────────────────────────────────
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  MediaKit.ensureInitialized();
  PodcastAudioService.instance.init();

  try {
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
    await FirebaseService.setupBeforeRunApp();

    // Cold-start: app was killed, user tapped a regular notification
    final initial = await FirebaseMessaging.instance.getInitialMessage();
    if (initial != null && initial.data['type'] != 'incoming_call') {
      _coldStartDeepLink = initial.data['deep_link'] as String?;
      debugPrint('[FCM] Cold-start deep link: $_coldStartDeepLink');
    }
    // Capture callkit accept BEFORE runApp so we don't miss the event
    FlutterCallkitIncoming.onEvent.listen((CallEvent? event) {
      if (event?.event == Event.actionCallAccept) {
        _pendingCallkitCallId =
            event?.body?['extra']?['call_id']?.toString();
        debugPrint('[CallKit:pre] Accept captured: $_pendingCallkitCallId');
      }
    });
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

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await FirebaseService().initialize();
      _setupNotificationNavigation();
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
      FirebaseService().refreshTokenIfNeeded();
    }
  }

  void _setupNotificationNavigation() {
    final router = ref.read(routerProvider);

    void navigate(String path) {
      try {
        router.push(path);
      } catch (e) {
        debugPrint('[Nav] Error: $path — $e');
      }
    }

    // Regular deep-link from foreground notification tap
    FirebaseService().onDeepLink = navigate;

    // Background tap — regular notification (includes live_started deep link)
    FirebaseMessaging.onMessageOpenedApp.listen((message) {
      final dl = message.data['deep_link'] as String?;
      if (dl != null && dl.isNotEmpty) navigate(dl);
    });

    // Cold-start deep link
    if (_coldStartDeepLink != null) {
      final dl = _coldStartDeepLink!;
      _coldStartDeepLink = null;
      Future.delayed(const Duration(milliseconds: 1500), () {
        if (mounted) navigate(dl);
      });
    }

    // ── flutter_callkit_incoming events ────────────────────────────────────
    Future<void> handleCallkitAccept(String callIdStr) async {
      final callId = int.tryParse(callIdStr) ?? 0;
      if (callId <= 0) return;
      try {
        final session = await CallRepository().acceptCall(callId);
        if (mounted) router.push('/calls/active', extra: session);
      } catch (e) {
        debugPrint('[CallKit] Accept error: $e');
      }
    }

    // Handle cold-start callkit accept (captured before runApp)
    if (_pendingCallkitCallId != null) {
      final id = _pendingCallkitCallId!;
      _pendingCallkitCallId = null;
      Future.delayed(const Duration(milliseconds: 800), () {
        if (mounted) handleCallkitAccept(id);
      });
    }

    FlutterCallkitIncoming.onEvent.listen((CallEvent? event) async {
      if (event == null || !mounted) return;
      debugPrint('[CallKit] Event: ${event.event}');

      switch (event.event) {
        case Event.actionCallAccept:
          await handleCallkitAccept(
              event.body?['extra']?['call_id']?.toString() ?? '');
          break;

        case Event.actionCallDecline:
          final callId = int.tryParse(
              event.body?['extra']?['call_id']?.toString() ?? '0') ?? 0;
          if (callId > 0) {
            try { await CallRepository().rejectCall(callId); } catch (_) {}
          }
          break;

        case Event.actionCallTimeout:
          final callId = int.tryParse(
              event.body?['extra']?['call_id']?.toString() ?? '0') ?? 0;
          if (callId > 0) {
            try { await CallRepository().rejectCall(callId); } catch (_) {}
          }
          break;

        default:
          break;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final settings = ref.watch(appSettingsProvider);
    return ConnectivityWrapper(
      child: MaterialApp.router(
        title: AppConstants.appName,
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        darkTheme: AppTheme.dark,
        themeMode: settings.themeMode,
        locale: settings.locale,
        supportedLocales: const [
          Locale('en'), Locale('so'), Locale('ar'),
          Locale('am'), Locale('sw'), Locale('fr'),
        ],
        routerConfig: ref.watch(routerProvider),
        builder: (ctx, child) => MediaQuery(
          data: MediaQuery.of(ctx).copyWith(
            textScaler: TextScaler.linear(settings.textScaleFactor),
          ),
          child: child!,
        ),
      ),
    );
  }
}
