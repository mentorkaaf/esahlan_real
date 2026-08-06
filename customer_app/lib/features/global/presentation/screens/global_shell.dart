import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';

/// Bottom-nav shell that wraps all global store tab screens.
/// Used via [ShellRoute] in the router.
class GlobalShell extends ConsumerWidget {
  final Widget child;
  const GlobalShell({super.key, required this.child});

  static const _tabs = [
    _Tab(path: '/global',          icon: Icons.home_outlined,              activeIcon: Icons.home_rounded,              label: 'Home'),
    _Tab(path: '/global/products', icon: Icons.grid_view_outlined,         activeIcon: Icons.grid_view_rounded,         label: 'Shop'),
    _Tab(path: '/global/cart',     icon: Icons.shopping_bag_outlined,      activeIcon: Icons.shopping_bag_rounded,      label: 'Cart'),
    _Tab(path: '/global/orders',   icon: Icons.receipt_long_outlined,      activeIcon: Icons.receipt_long_rounded,      label: 'Orders'),
    _Tab(path: '/global/profile',  icon: Icons.person_outline_rounded,     activeIcon: Icons.person_rounded,            label: 'Profile'),
  ];

  int _currentIndex(String location) {
    // exact match for /global home
    if (location == '/global' || location == '/global/') return 0;
    for (int i = _tabs.length - 1; i >= 0; i--) {
      if (location.startsWith(_tabs[i].path) && _tabs[i].path != '/global') {
        return i;
      }
    }
    return 0;
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final location = GoRouterState.of(context).uri.path;
    final idx      = _currentIndex(location);
    final cart     = ref.watch(globalCartProvider).valueOrNull;
    final cartCount = cart?.count ?? 0;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        systemNavigationBarColor: Colors.white,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: const Color(0xFFF0F2F5),
        extendBody: true,
        body: child,
        bottomNavigationBar: _GlobalNavBar(
          tabs: _tabs,
          selectedIndex: idx,
          cartCount: cartCount,
          onTap: (i) => context.go(_tabs[i].path),
        ),
      ),
    );
  }
}

// ── Floating nav bar ──────────────────────────────────────────────────────────

class _GlobalNavBar extends StatelessWidget {
  final List<_Tab> tabs;
  final int selectedIndex;
  final int cartCount;
  final ValueChanged<int> onTap;

  const _GlobalNavBar({
    required this.tabs,
    required this.selectedIndex,
    required this.cartCount,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).padding.bottom;

    return Container(
      margin: EdgeInsets.fromLTRB(12, 0, 12, bottom + 8),
      height: 64,
      decoration: BoxDecoration(
        color: const Color(0xFF1A1A2E),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: const Color(0xFF1A1A2E).withValues(alpha: 0.5),
            blurRadius: 24,
            offset: const Offset(0, 8),
          ),
          BoxShadow(
            color: const Color(0xFFF59E0B).withValues(alpha: 0.12),
            blurRadius: 40,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(24),
        child: Row(
          children: List.generate(tabs.length, (i) {
            final tab    = tabs[i];
            final active = selectedIndex == i;
            final isCart = tab.path == '/global/cart';

            return Expanded(
              child: _NavItem(
                icon:       tab.icon,
                activeIcon: tab.activeIcon,
                label:      tab.label,
                active:     active,
                badge:      isCart && cartCount > 0 ? cartCount : null,
                onTap:      () => onTap(i),
              ),
            );
          }),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  final IconData icon;
  final IconData activeIcon;
  final String label;
  final bool active;
  final int? badge;
  final VoidCallback onTap;

  const _NavItem({
    required this.icon,
    required this.activeIcon,
    required this.label,
    required this.active,
    required this.onTap,
    this.badge,
  });

  static const _kOrange = Color(0xFFF59E0B);

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: SizedBox(
        height: 64,
        child: Stack(
          alignment: Alignment.center,
          children: [
            // Active pill highlight
            if (active)
              Positioned(
                top: 8,
                child: Container(
                  width: 48,
                  height: 32,
                  decoration: BoxDecoration(
                    color: _kOrange.withValues(alpha: 0.18),
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
              ),

            Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                // Icon + badge
                SizedBox(
                  width: 28,
                  height: 28,
                  child: Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Center(
                        child: AnimatedSwitcher(
                          duration: const Duration(milliseconds: 200),
                          transitionBuilder: (child, anim) =>
                              ScaleTransition(scale: anim, child: child),
                          child: Icon(
                            active ? activeIcon : icon,
                            key: ValueKey(active),
                            color: active
                                ? _kOrange
                                : Colors.white.withValues(alpha: 0.45),
                            size: active ? 24 : 22,
                          ),
                        ),
                      ),
                      if (badge != null && badge! > 0)
                        Positioned(
                          right: -4,
                          top: -4,
                          child: Container(
                            padding: const EdgeInsets.all(3),
                            decoration: const BoxDecoration(
                              color: Color(0xFFEF4444),
                              shape: BoxShape.circle,
                            ),
                            child: Text(
                              badge! > 9 ? '9+' : '$badge',
                              style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 9,
                                  fontWeight: FontWeight.w800),
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 3),
                AnimatedDefaultTextStyle(
                  duration: const Duration(milliseconds: 200),
                  style: TextStyle(
                    fontSize: 9.5,
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

            // Bottom dot for active tab
            if (active)
              Positioned(
                bottom: 7,
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

class _Tab {
  final String path;
  final IconData icon;
  final IconData activeIcon;
  final String label;
  const _Tab({
    required this.path,
    required this.icon,
    required this.activeIcon,
    required this.label,
  });
}
