import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/providers/community_feature_provider.dart';
import '../../../../core/services/realtime_client.dart';
import '../providers/home_provider.dart';

// ─── Design tokens ────────────────────────────────────────────────────────────
const _kNavy   = Color(0xFF07003B);
const _kOrange = Color(0xFFFF8A00);
const _kBg     = Color(0xFFF5F6FA);

// Desktop sidebar width
const _kSidebarW = 230.0;
// Breakpoint: > this → desktop layout
const _kDesktopBreak = 900.0;

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
  _Dest(path: '/chat',      icon: Icons.chat_bubble_outline,              activeIcon: Icons.chat_bubble,                     label: 'Inbox'),
  _Dest(path: '/profile',   icon: Icons.person_outline_rounded,           activeIcon: Icons.person_rounded,                  label: 'Profile'),
];

// ─── Main shell ───────────────────────────────────────────────────────────────

class MainShell extends ConsumerStatefulWidget {
  final Widget child;
  const MainShell({super.key, required this.child});

  @override
  ConsumerState<MainShell> createState() => _MainShellState();
}

class _MainShellState extends ConsumerState<MainShell> {
  int _selectedIndex(String path, List<_Dest> dests) {
    for (var i = 0; i < dests.length; i++) {
      if (path.startsWith(dests[i].path)) return i;
    }
    return -1;
  }

  static const _kNavRoots = {'/home', '/orders', '/wallet', '/chat', '/profile'};
  bool _showNav(String path) => _kNavRoots.any((r) => path.startsWith(r));

  @override
  void initState() {
    super.initState();
    RealtimeClient.instance.listen('modules', 'modules.updated', _onModulesUpdated);
  }

  @override
  void dispose() {
    RealtimeClient.instance.removeListener('modules', 'modules.updated', _onModulesUpdated);
    super.dispose();
  }

  void _onModulesUpdated(dynamic _) {
    if (!mounted) return;
    ref.invalidate(modulesProvider);
    Future.delayed(const Duration(milliseconds: 300), () {
      if (!mounted) return;
      final location = GoRouterState.of(context).uri.path;
      ref.read(modulesProvider.future).then((modules) {
        if (!mounted) return;
        if (modules.length == 1) {
          context.go('/${modules.first.slug}');
        } else if (modules.length > 1 && !location.startsWith('/home') &&
            !location.startsWith('/orders') && !location.startsWith('/wallet') &&
            !location.startsWith('/chat') && !location.startsWith('/profile') &&
            !location.startsWith('/community')) {
          context.go('/home');
        }
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    final communityEnabled = ref.watch(communityFeatureProvider);
    final location = GoRouterState.of(context).uri.path;

    final dests = communityEnabled
        ? _kAllDestinations
        : _kAllDestinations.where((d) => d.path != '/community').toList();

    if (!communityEnabled && location.startsWith('/community')) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        context.go('/home');
      });
    }

    final idx     = _selectedIndex(location, dests);
    final showBar = _showNav(location);
    final width   = MediaQuery.sizeOf(context).width;
    final isDesktop = kIsWeb && width >= _kDesktopBreak;

    // ── Desktop layout ────────────────────────────────────────────────────────
    if (isDesktop) {
      return _DesktopShell(
        destinations: dests,
        selectedIndex: idx,
        child: widget.child,
      );
    }

    // ── Mobile layout (unchanged) ─────────────────────────────────────────────
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        systemNavigationBarColor: showBar ? Colors.white : Colors.transparent,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
      child: Scaffold(
        backgroundColor: _kBg,
        extendBody: showBar,
        body: widget.child,
        bottomNavigationBar: showBar
            ? _FloatingNavBar(selectedIndex: idx, location: location, destinations: dests)
            : null,
      ),
    );
  }
}

// ─── Desktop shell ────────────────────────────────────────────────────────────

class _DesktopShell extends StatelessWidget {
  final Widget child;
  final List<_Dest> destinations;
  final int selectedIndex;

  const _DesktopShell({
    required this.child,
    required this.destinations,
    required this.selectedIndex,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _kBg,
      body: Row(
        children: [
          _DesktopSidebar(
            destinations: destinations,
            selectedIndex: selectedIndex,
          ),
          // Content area with max-width clamp
          Expanded(
            child: Container(
              color: _kBg,
              alignment: Alignment.topCenter,
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 1280),
                child: child,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Desktop sidebar ──────────────────────────────────────────────────────────

class _DesktopSidebar extends StatelessWidget {
  final List<_Dest> destinations;
  final int selectedIndex;

  const _DesktopSidebar({
    required this.destinations,
    required this.selectedIndex,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: _kSidebarW,
      decoration: const BoxDecoration(
        color: _kNavy,
        boxShadow: [
          BoxShadow(
            color: Color(0x33000000),
            blurRadius: 20,
            offset: Offset(4, 0),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ── Logo ────────────────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 36, 24, 20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                RichText(
                  text: const TextSpan(children: [
                    TextSpan(
                      text: 'e-',
                      style: TextStyle(
                        color: _kOrange, fontSize: 28,
                        fontWeight: FontWeight.w900, fontFamily: 'Cairo',
                      ),
                    ),
                    TextSpan(
                      text: 'Sahlan',
                      style: TextStyle(
                        color: Colors.white, fontSize: 28,
                        fontWeight: FontWeight.w900, fontFamily: 'Cairo',
                      ),
                    ),
                  ]),
                ),
                const SizedBox(height: 4),
                Text(
                  'Everything You Need',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.35),
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
                    letterSpacing: 0.3,
                  ),
                ),
              ],
            ),
          ),

          Divider(color: Colors.white.withValues(alpha: 0.08), height: 1, indent: 16, endIndent: 16),
          const SizedBox(height: 10),

          // ── Nav items ───────────────────────────────────────────────────────
          ...List.generate(destinations.length, (i) => _SidebarNavItem(
            dest: destinations[i],
            active: selectedIndex == i,
          )),

          const Spacer(),

          // ── Bottom branding ─────────────────────────────────────────────────
          Divider(color: Colors.white.withValues(alpha: 0.06), height: 1, indent: 16, endIndent: 16),
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 14, 24, 28),
            child: Row(
              children: [
                Container(
                  width: 28, height: 28,
                  decoration: BoxDecoration(
                    color: _kOrange.withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Center(
                    child: Text('🛵', style: TextStyle(fontSize: 14)),
                  ),
                ),
                const SizedBox(width: 10),
                Text(
                  'eSahlan © 2025',
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.25),
                    fontSize: 11,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Sidebar nav item ─────────────────────────────────────────────────────────

class _SidebarNavItem extends StatefulWidget {
  final _Dest dest;
  final bool active;
  const _SidebarNavItem({required this.dest, required this.active});

  @override
  State<_SidebarNavItem> createState() => _SidebarNavItemState();
}

class _SidebarNavItemState extends State<_SidebarNavItem> {
  bool _hover = false;

  @override
  Widget build(BuildContext context) {
    final active = widget.active;
    return MouseRegion(
      onEnter: (_) => setState(() => _hover = true),
      onExit: (_) => setState(() => _hover = false),
      cursor: SystemMouseCursors.click,
      child: GestureDetector(
        onTap: () => context.go(widget.dest.path),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          margin: const EdgeInsets.symmetric(horizontal: 12, vertical: 2),
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 11),
          decoration: BoxDecoration(
            color: active
                ? _kOrange.withValues(alpha: 0.14)
                : _hover
                    ? Colors.white.withValues(alpha: 0.05)
                    : Colors.transparent,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              // Active indicator bar
              AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                width: 3,
                height: 22,
                decoration: BoxDecoration(
                  color: active ? _kOrange : Colors.transparent,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              const SizedBox(width: 10),
              Icon(
                active ? widget.dest.activeIcon : widget.dest.icon,
                color: active
                    ? _kOrange
                    : _hover
                        ? Colors.white.withValues(alpha: 0.7)
                        : Colors.white.withValues(alpha: 0.40),
                size: 20,
              ),
              const SizedBox(width: 12),
              Text(
                widget.dest.label,
                style: TextStyle(
                  color: active
                      ? _kOrange
                      : _hover
                          ? Colors.white.withValues(alpha: 0.85)
                          : Colors.white.withValues(alpha: 0.55),
                  fontSize: 14,
                  fontWeight: active ? FontWeight.w700 : FontWeight.w500,
                  letterSpacing: active ? 0.2 : 0,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Floating nav bar (mobile only) ──────────────────────────────────────────

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

// ─── Single nav pill (mobile) ─────────────────────────────────────────────────

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
                    color: active ? _kOrange : Colors.white.withValues(alpha: 0.45),
                    letterSpacing: active ? 0.3 : 0,
                  ),
                  child: Text(label),
                ),
              ],
            ),
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
