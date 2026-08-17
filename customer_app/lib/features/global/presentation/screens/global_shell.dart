import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../providers/global_provider.dart';
import 'global_privacy_screen.dart';

/// Responsive shell for the global store.
/// Desktop (≥ 1024 px): persistent sidebar + top navbar.
/// Mobile: floating bottom nav (unchanged).
class GlobalShell extends ConsumerStatefulWidget {
  final Widget child;
  const GlobalShell({super.key, required this.child});

  @override
  ConsumerState<GlobalShell> createState() => _GlobalShellState();
}

class _GlobalShellState extends ConsumerState<GlobalShell> {
  bool _showGdpr = false;

  static const _kGdprKey = 'global_gdpr_accepted';

  @override
  void initState() {
    super.initState();
    _checkGdpr();
  }

  Future<void> _checkGdpr() async {
    final prefs = await SharedPreferences.getInstance();
    final accepted = prefs.getBool(_kGdprKey) ?? false;
    if (!accepted && mounted) setState(() => _showGdpr = true);
  }

  Future<void> _acceptGdpr() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_kGdprKey, true);
    if (mounted) setState(() => _showGdpr = false);
  }

  static const _tabs = [
    _Tab(path: '/global',          icon: Icons.home_outlined,         activeIcon: Icons.home_rounded,         label: 'Home'),
    _Tab(path: '/global/products', icon: Icons.grid_view_outlined,    activeIcon: Icons.grid_view_rounded,    label: 'Shop'),
    _Tab(path: '/global/cart',     icon: Icons.shopping_bag_outlined, activeIcon: Icons.shopping_bag_rounded, label: 'Cart'),
    _Tab(path: '/global/orders',   icon: Icons.receipt_long_outlined, activeIcon: Icons.receipt_long_rounded, label: 'Orders'),
    _Tab(path: '/global/profile',  icon: Icons.person_outline_rounded,activeIcon: Icons.person_rounded,       label: 'Profile'),
  ];

  int _currentIndex(String location) {
    if (location == '/global' || location == '/global/') return 0;
    for (int i = _tabs.length - 1; i >= 0; i--) {
      if (location.startsWith(_tabs[i].path) && _tabs[i].path != '/global') {
        return i;
      }
    }
    return 0;
  }

  @override
  Widget build(BuildContext context) {
    // ── Real-time store status check ─────────────────────────────────────────
    final storeEnabled = ref.watch(globalStoreEnabledProvider);
    if (!storeEnabled) {
      return const _StoreClosedScreen();
    }

    final location  = GoRouterState.of(context).uri.path;
    final idx       = _currentIndex(location);
    final cart      = ref.watch(globalCartProvider).valueOrNull;
    final cartCount = cart?.count ?? 0;
    final width     = MediaQuery.of(context).size.width;
    final isDesktop = kIsWeb && width >= 1024;

    if (isDesktop) {
      return _DesktopShell(
        tabs: _tabs,
        selectedIndex: idx,
        cartCount: cartCount,
        showGdpr: _showGdpr,
        onGdprAccept: _acceptGdpr,
        onGdprPolicy: () { _acceptGdpr(); context.push('/global/privacy'); },
        child: widget.child,
      );
    }

    // ── Mobile layout ────────────────────────────────────────────────────────
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
        body: Stack(
          children: [
            widget.child,
            if (_showGdpr)
              Positioned(
                bottom: 80, left: 0, right: 0,
                child: GlobalGdprBanner(
                  onAccept: _acceptGdpr,
                  onViewPolicy: () { _acceptGdpr(); context.push('/global/privacy'); },
                ),
              ),
          ],
        ),
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

// ── Desktop Shell ─────────────────────────────────────────────────────────────

class _DesktopShell extends ConsumerWidget {
  final List<_Tab> tabs;
  final int selectedIndex;
  final int cartCount;
  final bool showGdpr;
  final VoidCallback onGdprAccept;
  final VoidCallback onGdprPolicy;
  final Widget child;

  const _DesktopShell({
    required this.tabs,
    required this.selectedIndex,
    required this.cartCount,
    required this.showGdpr,
    required this.onGdprAccept,
    required this.onGdprPolicy,
    required this.child,
  });

  static const _kNavy  = Color(0xFF1A1A2E);
  static const _kOrange = Color(0xFFF59E0B);
  static const _kBg    = Color(0xFFF0F2F5);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(globalAuthProvider);

    return Scaffold(
      backgroundColor: _kBg,
      body: Column(
        children: [
          // ── Top Navbar ───────────────────────────────────────────────────
          _DesktopTopBar(
            cartCount: cartCount,
            auth: auth,
            selectedIndex: selectedIndex,
            tabs: tabs,
          ),
          // ── Body ─────────────────────────────────────────────────────────
          Expanded(
            child: Stack(
              children: [
                child,
                if (showGdpr)
                  Positioned(
                    bottom: 20, left: 0, right: 0,
                    child: Center(
                      child: SizedBox(
                        width: 700,
                        child: GlobalGdprBanner(
                          onAccept: onGdprAccept,
                          onViewPolicy: onGdprPolicy,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          // ── Desktop Footer — rendered by each page inside its scroll ────
        ],
      ),
    );
  }
}

// ── Desktop Top Navigation Bar ────────────────────────────────────────────────

class _DesktopTopBar extends ConsumerWidget {
  final int cartCount;
  final AsyncValue<dynamic> auth;
  final int selectedIndex;
  final List<_Tab> tabs;

  const _DesktopTopBar({
    required this.cartCount,
    required this.auth,
    required this.selectedIndex,
    required this.tabs,
  });

  static const _kNavy   = Color(0xFF1A1A2E);
  static const _kOrange = Color(0xFFF59E0B);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Container(
      height: 64,
      color: _kNavy,
      padding: const EdgeInsets.symmetric(horizontal: 40),
      child: Row(
        children: [
          // Logo
          GestureDetector(
            onTap: () => context.go('/global'),
            child: Row(
              children: [
                Container(
                  width: 32, height: 32,
                  decoration: BoxDecoration(
                    color: _kOrange,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Center(
                    child: Text('e', style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      fontSize: 18,
                    )),
                  ),
                ),
                const SizedBox(width: 10),
                const Text('eSahlan', style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 18,
                  letterSpacing: -0.3,
                )),
                const SizedBox(width: 4),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                  decoration: BoxDecoration(
                    color: _kOrange,
                    borderRadius: BorderRadius.circular(4),
                  ),
                  child: const Text('Global', style: TextStyle(
                    color: Color(0xFF1A1A2E),
                    fontWeight: FontWeight.w800,
                    fontSize: 10,
                    letterSpacing: 0.5,
                  )),
                ),
              ],
            ),
          ),

          const SizedBox(width: 32),

          // Search bar
          Expanded(
            child: GestureDetector(
              onTap: () => context.push('/global/search'),
              child: Container(
                height: 40,
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Row(
                  children: [
                    const SizedBox(width: 14),
                    Icon(Icons.search_rounded, color: Colors.grey.shade400, size: 20),
                    const SizedBox(width: 8),
                    Text('Search products, brands, categories...',
                      style: TextStyle(color: Colors.grey.shade400, fontSize: 14)),
                    const Spacer(),
                    Container(
                      width: 40,
                      height: 40,
                      decoration: const BoxDecoration(
                        color: _kOrange,
                        borderRadius: BorderRadius.only(
                          topRight: Radius.circular(8),
                          bottomRight: Radius.circular(8),
                        ),
                      ),
                      child: const Icon(Icons.search_rounded, color: Colors.white, size: 20),
                    ),
                  ],
                ),
              ),
            ),
          ),

          const SizedBox(width: 24),

          // Nav links
          _NavLink(label: 'Home',   path: '/global',          active: selectedIndex == 0),
          _NavLink(label: 'Shop',   path: '/global/products', active: selectedIndex == 1),
          _NavLink(label: 'Orders', path: '/global/orders',   active: selectedIndex == 3),

          const SizedBox(width: 16),

          // Cart
          _CartButton(count: cartCount),

          const SizedBox(width: 12),

          // Auth
          auth.when(
            data: (u) => u != null
              ? _UserAvatar(name: u.name)
              : _SignInButton(),
            loading: () => const SizedBox(
              width: 20, height: 20,
              child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)),
            error: (_, __) => _SignInButton(),
          ),
        ],
      ),
    );
  }
}

class _NavLink extends StatefulWidget {
  final String label;
  final String path;
  final bool active;
  const _NavLink({required this.label, required this.path, required this.active});

  @override
  State<_NavLink> createState() => _NavLinkState();
}

class _NavLinkState extends State<_NavLink> {
  bool _hover = false;

  @override
  Widget build(BuildContext context) {
    return MouseRegion(
      onEnter: (_) => setState(() => _hover = true),
      onExit: (_)  => setState(() => _hover = false),
      child: GestureDetector(
        onTap: () => context.go(widget.path),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 4),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(widget.label, style: TextStyle(
                color: widget.active || _hover ? const Color(0xFFF59E0B) : Colors.white.withValues(alpha: 0.75),
                fontWeight: widget.active ? FontWeight.w700 : FontWeight.w500,
                fontSize: 14,
              )),
              const SizedBox(height: 2),
              AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                height: 2,
                width: widget.active ? 24 : 0,
                decoration: BoxDecoration(
                  color: const Color(0xFFF59E0B),
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CartButton extends StatefulWidget {
  final int count;
  const _CartButton({required this.count});

  @override
  State<_CartButton> createState() => _CartButtonState();
}

class _CartButtonState extends State<_CartButton> {
  bool _hover = false;

  @override
  Widget build(BuildContext context) {
    return MouseRegion(
      onEnter: (_) => setState(() => _hover = true),
      onExit: (_)  => setState(() => _hover = false),
      child: GestureDetector(
        onTap: () => context.go('/global/cart'),
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          decoration: BoxDecoration(
            color: _hover ? const Color(0xFFF59E0B) : const Color(0xFFF59E0B).withValues(alpha: 0.15),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: const Color(0xFFF59E0B).withValues(alpha: 0.5),
              width: 1,
            ),
          ),
          child: Row(
            children: [
              Icon(Icons.shopping_cart_outlined,
                color: _hover ? const Color(0xFF1A1A2E) : const Color(0xFFF59E0B),
                size: 18),
              if (widget.count > 0) ...[
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                  decoration: BoxDecoration(
                    color: _hover ? const Color(0xFF1A1A2E) : const Color(0xFFF59E0B),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text('${widget.count}', style: TextStyle(
                    color: _hover ? Colors.white : const Color(0xFF1A1A2E),
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  )),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _UserAvatar extends StatefulWidget {
  final String name;
  const _UserAvatar({required this.name});

  @override
  State<_UserAvatar> createState() => _UserAvatarState();
}

class _UserAvatarState extends State<_UserAvatar> {
  bool _hover = false;

  @override
  Widget build(BuildContext context) {
    return MouseRegion(
      onEnter: (_) => setState(() => _hover = true),
      onExit: (_)  => setState(() => _hover = false),
      child: GestureDetector(
        onTap: () => context.go('/global/profile'),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          decoration: BoxDecoration(
            color: _hover ? const Color(0xFFF59E0B).withValues(alpha: 0.15) : Colors.transparent,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Row(
            children: [
              CircleAvatar(
                radius: 15,
                backgroundColor: const Color(0xFFF59E0B),
                child: Text(widget.name[0].toUpperCase(), style: const TextStyle(
                  fontSize: 12, fontWeight: FontWeight.w800, color: Color(0xFF1A1A2E),
                )),
              ),
              const SizedBox(width: 8),
              Text(widget.name.split(' ')[0], style: TextStyle(
                color: _hover ? const Color(0xFFF59E0B) : Colors.white,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              )),
            ],
          ),
        ),
      ),
    );
  }
}

class _SignInButton extends StatefulWidget {
  const _SignInButton();

  @override
  State<_SignInButton> createState() => _SignInButtonState();
}

class _SignInButtonState extends State<_SignInButton> {
  bool _hover = false;

  @override
  Widget build(BuildContext context) {
    return MouseRegion(
      onEnter: (_) => setState(() => _hover = true),
      onExit: (_)  => setState(() => _hover = false),
      child: GestureDetector(
        onTap: () => context.push('/global/auth'),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 150),
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          decoration: BoxDecoration(
            color: _hover ? const Color(0xFFF59E0B) : Colors.transparent,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: Colors.white.withValues(alpha: 0.4), width: 1),
          ),
          child: Text('Sign In', style: TextStyle(
            color: _hover ? const Color(0xFF1A1A2E) : Colors.white,
            fontSize: 13,
            fontWeight: FontWeight.w600,
          )),
        ),
      ),
    );
  }
}

// ── Desktop Footer ────────────────────────────────────────────────────────────

/// Public footer widget — used by desktop pages inside their ScrollView.
class GlobalDesktopFooter extends StatelessWidget {
  const GlobalDesktopFooter({super.key});
  @override
  Widget build(BuildContext context) => _DesktopFooter();
}

class _DesktopFooter extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      color: const Color(0xFF1A1A2E),
      padding: const EdgeInsets.symmetric(horizontal: 80, vertical: 32),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Brand column
              Expanded(
                flex: 2,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(children: [
                      Container(
                        width: 28, height: 28,
                        decoration: BoxDecoration(
                          color: const Color(0xFFF59E0B),
                          borderRadius: BorderRadius.circular(6),
                        ),
                        child: const Center(child: Text('e', style: TextStyle(
                          color: Colors.white, fontWeight: FontWeight.w900, fontSize: 16,
                        ))),
                      ),
                      const SizedBox(width: 8),
                      const Text('eSahlan Global', style: TextStyle(
                        color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16,
                      )),
                    ]),
                    const SizedBox(height: 12),
                    Text('Your global marketplace for quality\nproducts shipped worldwide.',
                      style: TextStyle(color: Colors.white.withValues(alpha: 0.5), fontSize: 13, height: 1.6)),
                  ],
                ),
              ),
              // Shop links
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Shop', style: TextStyle(
                      color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
                    const SizedBox(height: 12),
                    ...[
                      ('New Arrivals', '/global/products'),
                      ('Best Sellers', '/global/products'),
                      ('All Products', '/global/products'),
                    ].map((item) => Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: GestureDetector(
                        onTap: () => context.go(item.$2),
                        child: Text(item.$1, style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.5), fontSize: 13,
                        )),
                      ),
                    )),
                  ],
                ),
              ),
              // Account links
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Account', style: TextStyle(
                      color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
                    const SizedBox(height: 12),
                    ...[
                      ('My Orders', '/global/orders'),
                      ('Profile', '/global/profile'),
                      ('Sign In', '/global/auth'),
                    ].map((item) => Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: GestureDetector(
                        onTap: () => context.go(item.$2),
                        child: Text(item.$1, style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.5), fontSize: 13,
                        )),
                      ),
                    )),
                  ],
                ),
              ),
              // Legal links
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Legal', style: TextStyle(
                      color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
                    const SizedBox(height: 12),
                    ...[
                      ('Privacy Policy', '/global/privacy'),
                    ].map((item) => Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: GestureDetector(
                        onTap: () => context.go(item.$2),
                        child: Text(item.$1, style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.5), fontSize: 13,
                        )),
                      ),
                    )),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 24),
          Divider(color: Colors.white.withValues(alpha: 0.1)),
          const SizedBox(height: 16),
          Row(
            children: [
              Text('© ${DateTime.now().year} eSahlan Global. All rights reserved.',
                style: TextStyle(color: Colors.white.withValues(alpha: 0.35), fontSize: 12)),
              const Spacer(),
              Text('Secure payments · Fast shipping · Global reach',
                style: TextStyle(color: Colors.white.withValues(alpha: 0.35), fontSize: 12)),
            ],
          ),
        ],
      ),
    );
  }
}

// ── Mobile Bottom Nav ─────────────────────────────────────────────────────────

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
                icon: tab.icon, activeIcon: tab.activeIcon,
                label: tab.label, active: active,
                badge: isCart && cartCount > 0 ? cartCount : null,
                onTap: () => onTap(i),
              ),
            );
          }),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  final IconData icon, activeIcon;
  final String label;
  final bool active;
  final int? badge;
  final VoidCallback onTap;

  const _NavItem({
    required this.icon, required this.activeIcon,
    required this.label, required this.active,
    required this.onTap, this.badge,
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
            if (active)
              Positioned(
                top: 8,
                child: Container(
                  width: 48, height: 32,
                  decoration: BoxDecoration(
                    color: _kOrange.withValues(alpha: 0.18),
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
              ),
            Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                SizedBox(
                  width: 28, height: 28,
                  child: Stack(
                    clipBehavior: Clip.none,
                    children: [
                      Center(
                        child: AnimatedSwitcher(
                          duration: const Duration(milliseconds: 200),
                          transitionBuilder: (child, anim) => ScaleTransition(scale: anim, child: child),
                          child: Icon(
                            active ? activeIcon : icon,
                            key: ValueKey(active),
                            color: active ? _kOrange : Colors.white.withValues(alpha: 0.45),
                            size: active ? 24 : 22,
                          ),
                        ),
                      ),
                      if (badge != null && badge! > 0)
                        Positioned(
                          right: -4, top: -4,
                          child: Container(
                            padding: const EdgeInsets.all(3),
                            decoration: const BoxDecoration(color: Color(0xFFEF4444), shape: BoxShape.circle),
                            child: Text(badge! > 9 ? '9+' : '$badge',
                              style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800)),
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
                    color: active ? _kOrange : Colors.white.withValues(alpha: 0.45),
                    letterSpacing: active ? 0.3 : 0,
                  ),
                  child: Text(label),
                ),
              ],
            ),
            if (active)
              Positioned(
                bottom: 7,
                child: Container(
                  width: 4, height: 4,
                  decoration: BoxDecoration(
                    color: _kOrange,
                    shape: BoxShape.circle,
                    boxShadow: [BoxShadow(color: _kOrange.withValues(alpha: 0.6), blurRadius: 6)],
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
  final IconData icon, activeIcon;
  final String label;
  const _Tab({
    required this.path, required this.icon,
    required this.activeIcon, required this.label,
  });
}

// ── Store Closed Screen ───────────────────────────────────────────────────────
// Marka admin-ku Global Store disable gareeyo, screen-kan ayaa soo baxda
// si toos ah (real-time) — user-ku ma baahan refresh.

class _StoreClosedScreen extends StatelessWidget {
  const _StoreClosedScreen();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF5F7FA),
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(40),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Icon
                Container(
                  width: 100, height: 100,
                  decoration: BoxDecoration(
                    color: const Color(0xFFFF8A00).withValues(alpha: 0.1),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.store_mall_directory_rounded,
                    size: 52, color: Color(0xFFFF8A00),
                  ),
                ),
                const SizedBox(height: 28),

                // Title
                const Text(
                  'eSahlan Global Store',
                  style: TextStyle(
                    fontSize: 22, fontWeight: FontWeight.w900,
                    color: Color(0xFF07003B),
                  ),
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 12),

                // Subtitle
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  decoration: BoxDecoration(
                    color: Colors.orange.shade50,
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.orange.shade200),
                  ),
                  child: const Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(Icons.access_time_rounded, size: 16, color: Colors.orange),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Store-ku hadda wuu xidhan yahay.\nWaxaan dib u furmeynaa si dhakhso ah.',
                          style: TextStyle(fontSize: 13, color: Colors.orange, height: 1.5),
                          textAlign: TextAlign.center,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 32),

                // Info badges
                Wrap(
                  spacing: 8, runSpacing: 8,
                  alignment: WrapAlignment.center,
                  children: [
                    _InfoChip(icon: Icons.security_rounded,   label: 'Secure & Safe'),
                    _InfoChip(icon: Icons.flash_on_rounded,   label: 'Coming Back Soon'),
                    _InfoChip(icon: Icons.support_agent_rounded, label: 'Support Available'),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final IconData icon;
  final String label;
  const _InfoChip({required this.icon, required this.label});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
    decoration: BoxDecoration(
      color: Colors.white,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: const Color(0xFFE5E7EB)),
    ),
    child: Row(mainAxisSize: MainAxisSize.min, children: [
      Icon(icon, size: 13, color: const Color(0xFFFF8A00)),
      const SizedBox(width: 5),
      Text(label, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: Color(0xFF374151))),
    ]),
  );
}
