import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/constants/app_assets.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/services/country_detection_service.dart';
import 'country_selection_screen.dart';
import '../../../../core/services/realtime_client.dart';
import '../../../../core/services/cold_start.dart';
import '../../../../core/storage/local_storage.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../community/presentation/providers/community_provider.dart';

class SplashScreen extends ConsumerStatefulWidget {
  const SplashScreen({super.key});
  @override
  ConsumerState<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends ConsumerState<SplashScreen>
    with TickerProviderStateMixin {

  late final AnimationController _logoCtrl;
  late final AnimationController _textCtrl;
  late final AnimationController _pulseCtrl;

  // Logo: scale + fade in with elastic bounce
  late final Animation<double> _logoScale;
  late final Animation<double> _logoFade;

  // Text: slide up + fade in after logo
  late final Animation<double> _textFade;
  late final Animation<Offset> _textSlide;

  // Subtle pulse glow behind logo
  late final Animation<double> _pulse;

  @override
  void initState() {
    super.initState();

    _logoCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
    _textCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 600));
    _pulseCtrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 1800))
      ..repeat(reverse: true);

    _logoScale = Tween<double>(begin: 0.0, end: 1.0).animate(
        CurvedAnimation(parent: _logoCtrl, curve: Curves.elasticOut));
    _logoFade = Tween<double>(begin: 0.0, end: 1.0).animate(
        CurvedAnimation(parent: _logoCtrl, curve: const Interval(0.0, 0.4, curve: Curves.easeIn)));

    _textFade = Tween<double>(begin: 0.0, end: 1.0).animate(
        CurvedAnimation(parent: _textCtrl, curve: Curves.easeOut));
    _textSlide = Tween<Offset>(begin: const Offset(0, 0.4), end: Offset.zero).animate(
        CurvedAnimation(parent: _textCtrl, curve: Curves.easeOut));

    _pulse = Tween<double>(begin: 0.85, end: 1.15).animate(
        CurvedAnimation(parent: _pulseCtrl, curve: Curves.easeInOut));

    _startSequence();
    _navigate();
  }

  Future<void> _startSequence() async {
    await _logoCtrl.forward();
    await Future.delayed(const Duration(milliseconds: 100));
    _textCtrl.forward();
  }

  Future<void> _navigate() async {
    // Run country check + minimum splash delay concurrently.
    final results = await Future.wait([
      _resolveDestination(),
      Future.delayed(const Duration(milliseconds: 3000)),
    ]);
    if (!mounted) return;
    final destination = results[0] as String;

    // Capture router + deep link BEFORE context.go() which will dispose
    // this widget — after go(), `mounted` becomes false.
    final dl     = pendingColdStartDeepLink;
    final router = GoRouter.of(context); // router lives independently of widget

    context.go(destination);

    // Cold-start deep link: push the screen on top of home once it settles.
    if (destination == '/home' && dl != null && dl.isNotEmpty) {
      pendingColdStartDeepLink = null;
      debugPrint('[Splash] Cold-start deep link → $dl');
      // addPostFrameCallback gives home one frame to render before pushing.
      // We use the router reference (not context) so the disposed widget is fine.
      WidgetsBinding.instance.addPostFrameCallback((_) {
        router.push(dl);
      });
    }
  }

  /// Determines where to send the user after splash.
  ///
  /// Priority order:
  ///   1. User previously chose a country manually → honour it (permanent cache).
  ///   2. IP detection confident → auto-route, skip selector (good UX).
  ///   3. IP detection uncertain (offline / failed) → show country selector.
  Future<String> _resolveDestination() async {
    // ── 1. Returning user — they already picked a country before ─────────────
    final saved = await getSavedCountrySelection();
    if (saved != null) {
      return _destinationForCode(saved);
    }

    // ── 2. First launch — try IP detection to skip the selector ──────────────
    // Run IP detection with a hard 6s cap. If it returns a confident answer
    // we auto-route the user without showing the selector (less friction).
    // If it times out or fails we fall through to the selector.
    try {
      final isInternational = await CountryDetectionService.isInternationalUser()
          .timeout(const Duration(seconds: 6));

      // IP detection succeeded → save result and route automatically
      final code = isInternational ? 'OTHER' : 'SO';
      await saveCountrySelection(code);
      return _destinationForCode(code);
    } catch (_) {
      // Detection failed / timed out → show selector so user can choose manually
      return '/country-select';
    }
  }

  /// Maps a saved country code to the correct initial route.
  Future<String> _destinationForCode(String code) async {
    if (code == 'SO') {
      // Somalia local flow — check auth
      final token          = await LocalStorage.getToken();
      final onboardingDone = await LocalStorage.getBool('onboarding_done');
      if (!onboardingDone) return '/onboarding';
      if (token != null) {
        RealtimeClient.instance.connect();
        ref.read(communityMyProfileProvider.future).then(
          (me) => MessagesNotifier.setMyId(me.id),
          onError: (_) {},
        );
        return '/home';
      }
      return '/auth/login';
    }
    // Any other code (US, GB, OTHER…) → global store
    return '/global';
  }

  @override
  void dispose() {
    _logoCtrl.dispose();
    _textCtrl.dispose();
    _pulseCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF07003B),
      body: Stack(
        children: [
          // Decorative blobs
          Positioned(top: -100, right: -80,
            child: _GlowBlob(size: 320, color: AppColors.primary.withOpacity(0.06))),
          Positioned(bottom: -80, left: -60,
            child: _GlowBlob(size: 260, color: Colors.white.withOpacity(0.03))),

          // Pulse glow
          Center(
            child: AnimatedBuilder(
              animation: _pulse,
              builder: (_, __) => Container(
                width: 140 * _pulse.value,
                height: 140 * _pulse.value,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: AppColors.primary.withOpacity(0.08),
                ),
              ),
            ),
          ),

          // Main content
          Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Logo image with scale + fade
                AnimatedBuilder(
                  animation: _logoCtrl,
                  builder: (_, __) => FadeTransition(
                    opacity: _logoFade,
                    child: ScaleTransition(
                      scale: _logoScale,
                      child: Image.asset(
                        AppAssets.splashLogo,
                        width: 140,
                        height: 140,
                        fit: BoxFit.contain,
                      ),
                    ),
                  ),
                ),

                const SizedBox(height: 28),

                // Text slide up
                AnimatedBuilder(
                  animation: _textCtrl,
                  builder: (_, __) => FadeTransition(
                    opacity: _textFade,
                    child: SlideTransition(
                      position: _textSlide,
                      child: Column(
                        children: [
                          const Text('eSahlan',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 34,
                              fontWeight: FontWeight.w900,
                              letterSpacing: 0.5,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text('Everything You Need, Simplified',
                            style: TextStyle(
                              color: Colors.white.withOpacity(0.55),
                              fontSize: 14,
                              fontWeight: FontWeight.w500,
                              letterSpacing: 0.2,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),

          // Bottom loading dots
          Positioned(
            bottom: 60, left: 0, right: 0,
            child: AnimatedBuilder(
              animation: _textCtrl,
              builder: (_, __) => FadeTransition(
                opacity: _textFade,
                child: _LoadingDots(),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _GlowBlob extends StatelessWidget {
  final double size;
  final Color color;
  const _GlowBlob({required this.size, required this.color});
  @override
  Widget build(BuildContext context) => Container(
    width: size, height: size,
    decoration: BoxDecoration(shape: BoxShape.circle, color: color),
  );
}

class _LoadingDots extends StatefulWidget {
  @override
  State<_LoadingDots> createState() => _LoadingDotsState();
}

class _LoadingDotsState extends State<_LoadingDots> with SingleTickerProviderStateMixin {
  late final AnimationController _ctrl;

  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(milliseconds: 900))
      ..repeat();
  }

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _ctrl,
      builder: (_, __) {
        return Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(3, (i) {
            final delay = i / 3;
            final t = ((_ctrl.value - delay) % 1.0 + 1.0) % 1.0;
            final scale = 0.6 + 0.4 * (t < 0.5 ? 2 * t : 2 * (1 - t));
            return Container(
              width: 6, height: 6,
              margin: const EdgeInsets.symmetric(horizontal: 4),
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.white.withOpacity(0.3 + 0.5 * scale),
              ),
              transform: Matrix4.identity()..scale(scale),
              transformAlignment: Alignment.center,
            );
          }),
        );
      },
    );
  }
}
