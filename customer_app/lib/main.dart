import 'dart:async';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_callkit_incoming/flutter_callkit_incoming.dart';
import 'package:flutter_callkit_incoming/entities/entities.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:media_kit/media_kit.dart';

import 'core/constants/app_constants.dart';
import 'core/l10n/app_strings.dart';
import 'core/providers/app_settings_provider.dart';
import 'core/providers/community_feature_provider.dart';
import 'core/providers/theme_provider.dart';
import 'core/router/app_router.dart';
import 'core/services/cold_start.dart';
import 'core/services/firebase_service.dart';
import 'core/services/location_service.dart';
import 'core/theme/app_theme.dart';
import 'core/widgets/connectivity_wrapper.dart';
import 'firebase_options.dart';
import 'features/calls/data/repositories/call_repository.dart';
import 'features/podcast/presentation/services/podcast_audio_service.dart';

// Callkit accept captured before app is mounted
String? _pendingCallkitCallId;

// ─────────────────────────────────────────────────────────────────────────────
void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  MediaKit.ensureInitialized();
  PodcastAudioService.instance.init();

  // Firebase init — web + native
  try {
    await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
    if (!kIsWeb) {
      await FirebaseService.setupBeforeRunApp();
      // Cold-start: app was killed, user tapped a regular notification
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (initial != null && initial.data['type'] != 'incoming_call') {
        pendingColdStartDeepLink = initial.data['deep_link'] as String?;
        debugPrint('[FCM] Cold-start deep link: $pendingColdStartDeepLink');
      }
      // Capture callkit accept for cold-start
      FlutterCallkitIncoming.onEvent.listen((CallEvent? event) {
        if (event?.event == Event.actionCallAccept && _pendingCallkitCallId == null) {
          _pendingCallkitCallId = event?.body?['extra']?['call_id']?.toString();
          debugPrint('[CallKit:pre] Cold-start capture: $_pendingCallkitCallId');
        }
      });
    }
  } catch (e) {
    debugPrint('[Firebase] Pre-runApp error: $e');
  }

  if (!kIsWeb) await LocationService.initBackground();

  if (!kIsWeb) SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  if (!kIsWeb) SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.transparent,
    statusBarIconBrightness: Brightness.dark,
  ));

  // Limit image cache so old phones don't OOM and GC-stutter during scroll.
  PaintingBinding.instance.imageCache.maximumSize = 150;
  PaintingBinding.instance.imageCache.maximumSizeBytes = 80 << 20; // 80 MB

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

  StreamSubscription? _callkitSub;
  AppLifecycleState _lifecycle = AppLifecycleState.resumed;
  // Holds a callkit accept that arrived while app was not yet resumed
  String? _pendingAcceptId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    if (!kIsWeb) {
      _callkitSub = FlutterCallkitIncoming.onEvent.listen(_onCallkitEvent);
    }

    // Initialize early so the community tab hide/show is ready before first render
    ref.read(communityFeatureProvider);

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await FirebaseService().initialize();
      await FirebaseService().setupOpenedHandlers();
      _setupNotificationNavigation();
      Future.delayed(const Duration(milliseconds: 300), () {
        if (mounted) FirebaseService().requestPermissionIfNeeded();
      });

      // Handle cold-start callkit accept captured before runApp
      if (!kIsWeb && _pendingCallkitCallId != null) {
        final id = _pendingCallkitCallId!;
        _pendingCallkitCallId = null;
        Future.delayed(const Duration(milliseconds: 1000), () {
          if (mounted) _acceptCall(id);
        });
      }
    });
  }

  @override
  void dispose() {
    _callkitSub?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  // ── CallKit event handler (background + foreground) ──────────────────────
  void _onCallkitEvent(CallEvent? event) async {
    if (event == null || !mounted) return;
    debugPrint('[CallKit] Event: ${event.event} lifecycle: $_lifecycle');
    switch (event.event) {
      case Event.actionCallAccept:
        final callId = event.body?['extra']?['call_id']?.toString() ?? '';
        if (callId.isEmpty) break;
        if (_lifecycle == AppLifecycleState.resumed) {
          // App already in foreground — navigate immediately
          await _acceptCall(callId);
        } else {
          // App is backgrounded — defer until resumed lifecycle fires
          _pendingAcceptId = callId;
        }
        break;
      case Event.actionCallDecline:
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
  }

  Future<void> _acceptCall(String callIdStr) async {
    final callId = int.tryParse(callIdStr) ?? 0;
    if (callId <= 0) return;
    try {
      final router = ref.read(routerProvider);
      final session = await CallRepository().acceptCall(callId);
      if (mounted) router.push('/calls/active', extra: session);
    } catch (e) {
      debugPrint('[CallKit] Accept error: $e');
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _lifecycle = state;
    if (state == AppLifecycleState.resumed) {
      FirebaseService().refreshTokenIfNeeded();
      // If a callkit accept arrived while we were backgrounded, handle it now
      if (!kIsWeb && _pendingAcceptId != null) {
        final id = _pendingAcceptId!;
        _pendingAcceptId = null;
        Future.delayed(const Duration(milliseconds: 400), () {
          if (mounted) _acceptCall(id);
        });
      }
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

    // Cold-start deep link is handled by SplashScreen via pendingColdStartDeepLink.
    // SplashScreen reads it in _destinationForCode() and navigates directly,
    // avoiding any timing conflict with the 3-second minimum splash delay.

    // CallKit events are handled by _onCallkitEvent (set up in initState)
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
        // Only 'en' and 'ar' here — flutter_localizations doesn't support 'so'.
        // Somali UI strings are handled by AppL10n, not Flutter's localization system.
        supportedLocales: const [
          Locale('en'),
          Locale('ar'),
        ],
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,    // RTL for Arabic
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        routerConfig: ref.watch(routerProvider),
        builder: (ctx, child) => AppLangScope(
          language: settings.language,
          child: MediaQuery(
            data: MediaQuery.of(ctx).copyWith(
              textScaler: TextScaler.linear(settings.textScaleFactor),
            ),
            child: child!,
          ),
        ),
      ),
    );
  }
}
