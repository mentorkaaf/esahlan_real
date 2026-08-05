import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../services/location_watcher_service.dart';
import '../../features/auth/presentation/screens/country_selection_screen.dart';

export '../services/location_watcher_service.dart' show RegionMismatch;

/// A slide-down banner that appears when the user's detected location no longer
/// matches the app region they're currently using.
///
/// Somalia user → now detected abroad  → "You appear to be outside Somalia. Switch to Global Store?"
/// Global user  → now detected in SO   → "You appear to be in Somalia. Switch to local services?"
///
/// Usage — put inside a Stack as the top-most child:
///
///   Stack(children: [
///     child,
///     const SmartLocationBanner(),
///   ])
class SmartLocationBanner extends StatefulWidget {
  /// Whether the user is currently in the LOCAL (Somalia) app.
  final bool isLocalApp;

  const SmartLocationBanner({super.key, required this.isLocalApp});

  @override
  State<SmartLocationBanner> createState() => _SmartLocationBannerState();
}

class _SmartLocationBannerState extends State<SmartLocationBanner>
    with SingleTickerProviderStateMixin {
  bool _visible = false;
  bool _suggestGlobal = true;

  late final AnimationController _ctrl;
  late final Animation<Offset> _slide;
  late final Animation<double> _fade;

  @override
  void initState() {
    super.initState();

    _ctrl = AnimationController(
        vsync: this, duration: const Duration(milliseconds: 380));
    _slide = Tween<Offset>(
      begin: const Offset(0, -1),
      end: Offset.zero,
    ).animate(CurvedAnimation(parent: _ctrl, curve: Curves.easeOutCubic));
    _fade = CurvedAnimation(parent: _ctrl, curve: Curves.easeIn);

    // Register callback with the watcher
    LocationWatcherService.instance.onRegionMismatch = _onMismatch;
    LocationWatcherService.instance.start();
  }

  @override
  void dispose() {
    LocationWatcherService.instance.onRegionMismatch = null;
    _ctrl.dispose();
    super.dispose();
  }

  void _onMismatch(RegionMismatch mismatch) {
    if (!mounted) return;
    setState(() {
      _suggestGlobal = mismatch.suggestGlobal;
      _visible = true;
    });
    _ctrl.forward();
  }

  Future<void> _dismiss() async {
    await _ctrl.reverse();
    if (mounted) setState(() => _visible = false);
    await LocationWatcherService.instance.markBannerDismissed();
  }

  Future<void> _switch() async {
    await _ctrl.reverse();
    if (mounted) setState(() => _visible = false);
    await LocationWatcherService.instance.resetBannerState();
    // Clear the saved country so the selector resets
    await saveCountrySelection(null);
    if (mounted) context.go('/country-select');
  }

  @override
  Widget build(BuildContext context) {
    if (!_visible) return const SizedBox.shrink();

    final isGlobal = _suggestGlobal;
    final icon     = isGlobal ? '🌍' : '🇸🇴';
    final message  = isGlobal
        ? 'You appear to be outside Somalia'
        : "You appear to be in Somalia";
    final action   = isGlobal ? 'Global Store' : 'Local Services';
    final accent   = isGlobal
        ? const Color(0xFF3B82F6)   // blue for global
        : const Color(0xFFFF6B35);  // orange for local

    return Positioned(
      top: 0, left: 0, right: 0,
      child: SlideTransition(
        position: _slide,
        child: FadeTransition(
          opacity: _fade,
          child: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
              child: Material(
                color: Colors.transparent,
                child: Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  decoration: BoxDecoration(
                    color: const Color(0xFF1A1A2E),
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: accent.withValues(alpha: 0.4)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.35),
                        blurRadius: 16,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      // Icon
                      Text(icon, style: const TextStyle(fontSize: 22)),
                      const SizedBox(width: 10),

                      // Message
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              message,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            Text(
                              'Switch to $action?',
                              style: TextStyle(
                                color: accent,
                                fontSize: 12,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      ),

                      // Switch button
                      GestureDetector(
                        onTap: _switch,
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 12, vertical: 7),
                          decoration: BoxDecoration(
                            color: accent,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: const Text(
                            'Switch',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),

                      // Dismiss
                      GestureDetector(
                        onTap: _dismiss,
                        child: Icon(
                          Icons.close_rounded,
                          color: Colors.white.withValues(alpha: 0.4),
                          size: 18,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
