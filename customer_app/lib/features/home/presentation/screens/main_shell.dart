import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/providers/community_feature_provider.dart';

// ─── Design tokens ────────────────────────────────────────────────────────────
const _kNavy   = Color(0xFF07003B);
const _kOrange = Color(0xFFFF8A00);
const _kBg     = Color(0xFFF5F6FA);

// ─── Nav destinations ─────────────────────────────────────────────────────────
class _Dest {
  final String path;
  final IconData icon;
  final IconData activeIcon;
  final String label;
  const _Dest({required this.path, required this.icon, required this.activeIcon, required this.label});
}

const _kAllDestinations = [
  _Dest(path: '/home',      icon: Icons.home_outlined,                    activeIcon: Icons.home_rounded,                    label: 'Home'),
  _Dest(path: '/orders',    icon: Icons.receipt_long_outlined,            activeIcon: Icons.receipt_long_rounded,            label: 'Orders'),
  _Dest(path: '/wallet',    icon: Icons.account_balance_wallet_outlined,  activeIcon: Icons.account_balance_wallet_rounded,  label: 'ePay'),
  _Dest(path: '/community', icon: Icons.people_outline,                   activeIcon: Icons.people,                          label: 'eSpace'),
  _Dest(path: '/profile',   icon: Icons.person_outline_rounded,           activeIcon: Icons.person_rounded,                  label: 'Profile'),
];

// ─── Main shell ───────────────────────────────────────────────────────────────

class MainShell extends ConsumerWidget {
  final Widget child;
  const MainShell({super.key, required this.child});

  // Returns -1 when on a module screen (no tab is active)
  int _selectedIndex(String path, List<_Dest> dests) {
    for (var i = 0; i < dests.length; i++) {
      if (path.startsWith(dests[i].path)) return i;
    }
    return -1;
  }

  // Show nav bar on main tabs — community has its own nav bar
  bool _showNav(String path) {
    return path.startsWith('/home') ||
           path.startsWith('/orders') ||
           path.startsWith('/wallet') ||
           path.startsWith('/profile');
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final communityEnabled = ref.watch(communityFeatureProvider);
    final location = GoRouterState.of(context).uri.path;

    final dests = communityEnabled
        ? _kAllDestinations
        : _kAllDestinations.where((d) => d.path != '/community').toList();

    // If community is disabled and the user is on /community, redirect to /home
    if (!communityEnabled && location.startsWith('/community')) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        context.go('/home');
      });
    }

    final idx = _selectedIndex(location, dests);
    final showBar = _showNav(location);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        systemNavigationBarColor: showBar ? Colors.white : Colors.transparent,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: _kBg,
        extendBody: true,
        body: child,
        bottomNavigationBar: showBar
            ? _FloatingNavBar(selectedIndex: idx, location: location, destinations: dests)
            : null,
      ),
    );
  }
}

// ─── Floating nav bar ─────────────────────────────────────────────────────────

class _FloatingNavBar extends StatelessWidget {
  final int selectedIndex;
  final String location;
  final List<_Dest> destinations;
  const _FloatingNavBar({required this.selectedIndex, required this.location, required this.destinations});

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).padding.bottom;

    return Container(
      margin: EdgeInsets.fromLTRB(16, 0, 16, bottom + 10),
      height: 66,
      decoration: BoxDecoration(
        color: _kNavy,
        borderRadius: BorderRadius.circular(28),
        boxShadow: [
          BoxShadow(
            color: _kNavy.withValues(alpha: 0.40),
            blurRadius: 24,
            offset: const Offset(0, 8),
          ),
          BoxShadow(
            color: _kOrange.withValues(alpha: 0.12),
            blurRadius: 40,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(28),
        child: Row(
          children: List.generate(destinations.length, (i) {
            final dest   = destinations[i];
            final active = selectedIndex == i;
            return Expanded(
              child: _NavPill(
                icon:       dest.icon,
                activeIcon: dest.activeIcon,
                label:      dest.label,
                active:     active,
                onTap: () => context.go(dest.path),
              ),
            );
          }),
        ),
      ),
    );
  }
}

// ─── Single nav pill ──────────────────────────────────────────────────────────

class _NavPill extends StatelessWidget {
  final IconData icon;
  final IconData activeIcon;
  final String label;
  final bool active;
  final VoidCallback onTap;

  const _NavPill({
    required this.icon,
    required this.activeIcon,
    required this.label,
    required this.active,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: SizedBox(
        height: 66,
        child: Stack(
          alignment: Alignment.center,
          children: [
            // Active glow blob behind the icon
            AnimatedOpacity(
              opacity: active ? 1 : 0,
              duration: const Duration(milliseconds: 250),
              child: Container(
                width: 52,
                height: 36,
                decoration: BoxDecoration(
                  color: _kOrange.withValues(alpha: 0.18),
                  borderRadius: BorderRadius.circular(18),
                ),
              ),
            ),
            // Icon + label column
            Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                AnimatedSwitcher(
                  duration: const Duration(milliseconds: 200),
                  transitionBuilder: (child, anim) =>
                      ScaleTransition(scale: anim, child: child),
                  child: Icon(
                    active ? activeIcon : icon,
                    key: ValueKey(active),
                    color: active ? _kOrange : Colors.white.withValues(alpha: 0.45),
                    size: active ? 24 : 22,
                  ),
                ),
                const SizedBox(height: 3),
                AnimatedDefaultTextStyle(
                  duration: const Duration(milliseconds: 200),
                  style: TextStyle(
                    fontSize: 10,
                    fontWeight: active ? FontWeight.w700 : FontWeight.w400,
                    color: active
                        ? _kOrange
                        : Colors.white.withValues(alpha: 0.45),
                    letterSpacing: active ? 0.3 : 0,
                  ),
                  child: Text(label),
                ),
              ],
            ),
            // Active top indicator dot
            if (active)
              Positioned(
                bottom: 6,
                child: Container(
                  width: 4,
                  height: 4,
                  decoration: BoxDecoration(
                    color: _kOrange,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: _kOrange.withValues(alpha: 0.6),
                        blurRadius: 6,
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
