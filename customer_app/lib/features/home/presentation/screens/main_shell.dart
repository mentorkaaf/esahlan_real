import 'dart:math' as math;

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/api/api_client.dart';
import '../../../../core/l10n/app_strings.dart';
import '../../../../core/providers/community_feature_provider.dart';
import '../../../../core/services/realtime_client.dart';
import '../../../../core/services/app_update_checker.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/phone_input_field.dart';
import '../../../../core/widgets/smart_location_banner.dart';
import '../providers/home_provider.dart';
import '../../../auth/data/models/district_model.dart';
import '../../../auth/data/repositories/auth_repository.dart';
import '../../../auth/data/repositories/district_repository.dart';
import '../../../auth/presentation/providers/auth_provider.dart';

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

class _MainShellState extends ConsumerState<MainShell> with WidgetsBindingObserver {
  bool _maintenance      = false;
  String _maintMessage   = 'The app is currently under maintenance. Please try again later.';

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
    WidgetsBinding.instance.addObserver(this);
    RealtimeClient.instance.listen('modules', 'modules.updated', _onModulesUpdated);
    // Maintenance mode — public channel
    RealtimeClient.instance.listen('maintenance', 'maintenance.status', _onMaintenance);
    // Banned user — private channel — subscribed after first frame (need user id)
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      AppUpdateChecker.check(context, 'customer');
      _subscribeBanned();
      _checkMaintenanceStatus();
      _checkProfileCompletion();
    });
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _checkMaintenanceStatus();
    }
  }

  Future<void> _checkMaintenanceStatus() async {
    try {
      final res = await ApiClient.instance.get('/app/maintenance');
      final data = res.data['data'];
      if (!mounted) return;
      setState(() {
        _maintenance  = data['enabled'] == true;
        _maintMessage = data['message']?.toString() ?? _maintMessage;
      });
    } catch (_) {
      // network error — keep existing state
    }
  }

  void _checkProfileCompletion() {
    final user = ref.read(authStateProvider).valueOrNull;
    if (user == null) return;
    final needsCompletion = user.phone.isEmpty || user.districtId == null;
    if (!needsCompletion) return;
    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (_) => const _ProfileCompletionDialog(),
    );
  }

  void _subscribeBanned() {
    final user = ref.read(authStateProvider).valueOrNull;
    if (user == null) return;
    RealtimeClient.instance.listen(
      'private-user.${user.id}', 'user.banned', _onBanned);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    RealtimeClient.instance.removeListener('modules', 'modules.updated', _onModulesUpdated);
    RealtimeClient.instance.removeListener('maintenance', 'maintenance.status', _onMaintenance);
    final user = ref.read(authStateProvider).valueOrNull;
    if (user != null) {
      RealtimeClient.instance.removeListener('private-user.${user.id}', 'user.banned', _onBanned);
    }
    super.dispose();
  }

  void _onMaintenance(dynamic data) {
    if (!mounted) return;
    final enabled = data['enabled'] == true;
    final message = data['message']?.toString() ?? _maintMessage;
    setState(() { _maintenance = enabled; _maintMessage = message; });
  }

  void _onBanned(dynamic data) {
    if (!mounted) return;
    // Force logout
    ref.read(authRepositoryProvider).logout().catchError((_) {});
    ref.invalidate(authStateProvider);
    // Navigate to login with banned message
    context.go('/auth/login');
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (_) => AlertDialog(
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
          title: const Row(children: [
            Icon(Icons.block_rounded, color: Colors.red),
            SizedBox(width: 10),
            Text('Account Suspended', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
          ]),
          content: Text(data['message']?.toString() ?? 'Your account has been suspended. Please contact support.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(_), child: const Text('OK')),
          ],
        ),
      );
    });
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

    // ── Mobile layout ─────────────────────────────────────────────────────────
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.dark,
        systemNavigationBarColor: showBar ? Colors.white : Colors.transparent,
        systemNavigationBarIconBrightness: Brightness.dark,
      ),
      child: Stack(
        children: [
          Scaffold(
            backgroundColor: _kBg,
            extendBody: showBar,
            body: Stack(
              children: [
                widget.child,
                const SmartLocationBanner(isLocalApp: true),
              ],
            ),
            bottomNavigationBar: showBar
                ? _FloatingNavBar(selectedIndex: idx, location: location, destinations: dests)
                : null,
          ),
          // Maintenance overlay — covers everything real-time
          if (_maintenance)
            _MaintenanceOverlay(message: _maintMessage),
        ],
      ),
    );
  }
}

// ─── Profile completion dialog ────────────────────────────────────────────────
class _ProfileCompletionDialog extends ConsumerStatefulWidget {
  const _ProfileCompletionDialog();
  @override
  ConsumerState<_ProfileCompletionDialog> createState() => _ProfileCompletionDialogState();
}

class _ProfileCompletionDialogState extends ConsumerState<_ProfileCompletionDialog> {
  final _phoneCtrl = TextEditingController();
  CountryCode _country = const CountryCode(name: 'Somalia', dialCode: '+252', flag: '🇸🇴', iso: 'SO');
  DistrictModel? _district;
  List<DistrictModel> _districts = [];
  bool _loadingDistricts = true;
  bool _submitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadDistricts();
  }

  @override
  void dispose() {
    _phoneCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadDistricts() async {
    try {
      final list = await DistrictRepository().getDistricts();
      if (mounted) setState(() { _districts = list; _loadingDistricts = false; });
    } catch (_) {
      if (mounted) setState(() => _loadingDistricts = false);
    }
  }

  Future<void> _submit() async {
    final phone = _phoneCtrl.text.trim();
    if (phone.isEmpty) {
      setState(() => _error = 'Please enter your phone number');
      return;
    }
    if (_district == null) {
      setState(() => _error = 'Please select your district');
      return;
    }
    setState(() { _submitting = true; _error = null; });
    try {
      await AuthRepository().completeProfile(
        phone: '${_country.dialCode}$phone',
        districtId: _district!.id,
      );
      if (!mounted) return;
      // Invalidate in-memory auth state so updated user (with phone+district) is loaded
      ref.invalidate(authStateProvider);
      Navigator.of(context).pop();
    } catch (e) {
      if (mounted) setState(() {
        _error = e.toString().replaceFirst('Exception: ', '');
        _submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return PopScope(
      canPop: false,
      child: Dialog(
        backgroundColor: c.scaffoldBg,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        insetPadding: const EdgeInsets.symmetric(horizontal: 24, vertical: 40),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Center(
              child: Container(
                width: 64, height: 64,
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.1),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.person_add_rounded, color: AppColors.primary, size: 32),
              ),
            ),
            const SizedBox(height: 14),
            Center(
              child: Text('Complete Your Profile',
                style: TextStyle(fontSize: 18, fontWeight: FontWeight.w900,
                    color: c.navyText, decoration: TextDecoration.none)),
            ),
            const SizedBox(height: 6),
            Center(
              child: Text('Please add your phone number and district\nto continue using eSahlan.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 12, color: c.mutedText, height: 1.5,
                    decoration: TextDecoration.none)),
            ),
            const SizedBox(height: 24),

            Text('Phone Number',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700,
                  color: c.navyText, decoration: TextDecoration.none)),
            const SizedBox(height: 8),
            PhoneInputField(
              controller: _phoneCtrl,
              initialCountry: _country,
              onCountryChanged: (v) => setState(() => _country = v),
            ),
            const SizedBox(height: 16),

            Text('Your District',
              style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700,
                  color: c.navyText, decoration: TextDecoration.none)),
            const SizedBox(height: 8),
            Container(
              height: 48,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              decoration: BoxDecoration(
                color: c.inputFill,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: c.borderColor),
              ),
              child: _loadingDistricts
                  ? const Center(child: SizedBox(width: 18, height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2)))
                  : DropdownButtonHideUnderline(
                      child: DropdownButton<DistrictModel>(
                        value: _district,
                        isExpanded: true,
                        hint: Text('Select district',
                          style: TextStyle(color: c.mutedText, fontSize: 13)),
                        dropdownColor: c.scaffoldBg,
                        items: _districts.map((d) => DropdownMenuItem(
                          value: d,
                          child: Text(d.name,
                            style: TextStyle(color: c.navyText, fontSize: 14)),
                        )).toList(),
                        onChanged: (v) => setState(() => _district = v),
                      ),
                    ),
            ),

            if (_error != null) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.error.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(_error!,
                  style: const TextStyle(color: AppColors.error, fontSize: 12,
                      decoration: TextDecoration.none)),
              ),
            ],

            const SizedBox(height: 24),

            SizedBox(
              width: double.infinity,
              height: 48,
              child: ElevatedButton(
                onPressed: _submitting ? null : _submit,
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                ),
                child: _submitting
                    ? const SizedBox(width: 20, height: 20,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                    : const Text('Continue',
                        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800,
                            color: Colors.white, decoration: TextDecoration.none)),
              ),
            ),
          ]),
        ),
      ),
    );
  }
}

// ─── Maintenance overlay ─────────────────────────────────────────────────────
class _MaintenanceOverlay extends StatefulWidget {
  final String message;
  const _MaintenanceOverlay({required this.message});

  @override
  State<_MaintenanceOverlay> createState() => _MaintenanceOverlayState();
}

class _MaintenanceOverlayState extends State<_MaintenanceOverlay>
    with TickerProviderStateMixin {
  late final AnimationController _pulseCtrl;
  late final AnimationController _gearCtrl;

  @override
  void initState() {
    super.initState();
    _pulseCtrl = AnimationController(
      vsync: this, duration: const Duration(milliseconds: 2400))..repeat();
    _gearCtrl = AnimationController(
      vsync: this, duration: const Duration(seconds: 10))..repeat();
  }

  @override
  void dispose() {
    _pulseCtrl.dispose();
    _gearCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF07003B), Color(0xFF110060), Color(0xFF07003B)],
        ),
      ),
      child: SafeArea(
        child: Column(children: [
          const Spacer(),
          // ── Animated icon area ────────────────────────────────────────────
          SizedBox(
            width: 180,
            height: 180,
            child: Stack(
              alignment: Alignment.center,
              children: [
                AnimatedBuilder(
                  animation: _pulseCtrl,
                  builder: (_, __) => Stack(
                    alignment: Alignment.center,
                    children: [
                      _PulseRing(progress: (_pulseCtrl.value + 0.0) % 1.0, size: 170),
                      _PulseRing(progress: (_pulseCtrl.value + 0.33) % 1.0, size: 170),
                      _PulseRing(progress: (_pulseCtrl.value + 0.66) % 1.0, size: 170),
                    ],
                  ),
                ),
                Container(
                  width: 96, height: 96,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    gradient: const LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [Color(0xFF1A0070), Color(0xFF2A00A8)],
                    ),
                    border: Border.all(
                      color: _kOrange.withValues(alpha: 0.5), width: 1.5),
                    boxShadow: [
                      BoxShadow(
                        color: _kOrange.withValues(alpha: 0.35),
                        blurRadius: 32,
                        spreadRadius: 4,
                      ),
                    ],
                  ),
                  child: AnimatedBuilder(
                    animation: _gearCtrl,
                    builder: (_, child) => Transform.rotate(
                      angle: _gearCtrl.value * 2 * math.pi,
                      child: child,
                    ),
                    child: const Icon(Icons.settings_rounded,
                        color: _kOrange, size: 46),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 36),

          // ── Title ─────────────────────────────────────────────────────────
          const Text(
            'Under Maintenance',
            style: TextStyle(
              fontSize: 26,
              fontWeight: FontWeight.w900,
              color: Colors.white,
              letterSpacing: -0.5,
              decoration: TextDecoration.none,
            ),
          ),
          const SizedBox(height: 10),
          Container(
            width: 44, height: 3,
            decoration: BoxDecoration(
              color: _kOrange,
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(height: 20),

          // ── Message ───────────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 48),
            child: Text(
              widget.message,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 14,
                color: Colors.white.withValues(alpha: 0.55),
                height: 1.65,
                fontWeight: FontWeight.w400,
                decoration: TextDecoration.none,
              ),
            ),
          ),
          const SizedBox(height: 44),

          // ── Bouncing dots ─────────────────────────────────────────────────
          _BouncingDots(ctrl: _pulseCtrl),
          const SizedBox(height: 14),
          Text(
            'We\'ll be back soon',
            style: TextStyle(
              fontSize: 12,
              color: Colors.white.withValues(alpha: 0.35),
              letterSpacing: 0.6,
              fontWeight: FontWeight.w500,
              decoration: TextDecoration.none,
            ),
          ),

          const Spacer(),

          // ── Brand footer ──────────────────────────────────────────────────
          Padding(
            padding: const EdgeInsets.only(bottom: 28),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              RichText(text: const TextSpan(children: [
                TextSpan(text: 'e-',
                  style: TextStyle(color: _kOrange, fontSize: 15,
                    fontWeight: FontWeight.w900, decoration: TextDecoration.none)),
                TextSpan(text: 'Sahlan',
                  style: TextStyle(color: Colors.white, fontSize: 15,
                    fontWeight: FontWeight.w900, decoration: TextDecoration.none)),
              ])),
              const SizedBox(width: 8),
              Text('· Everything You Need',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.25),
                  fontSize: 12,
                  fontWeight: FontWeight.w400,
                  decoration: TextDecoration.none,
                )),
            ]),
          ),
        ]),
      ),
    );
  }
}

class _PulseRing extends StatelessWidget {
  final double progress;
  final double size;
  const _PulseRing({required this.progress, required this.size});

  @override
  Widget build(BuildContext context) {
    final t = Curves.easeOut.transform(progress);
    final opacity = (1 - t) * 0.35;
    final scale   = 0.5 + t * 0.5;
    return Transform.scale(
      scale: scale,
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(
            color: _kOrange.withValues(alpha: opacity), width: 1.5),
        ),
      ),
    );
  }
}

class _BouncingDots extends StatelessWidget {
  final AnimationController ctrl;
  const _BouncingDots({required this.ctrl});

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: ctrl,
      builder: (_, __) => Row(
        mainAxisSize: MainAxisSize.min,
        children: List.generate(3, (i) {
          final phase = (ctrl.value - i * 0.22).clamp(0.0, 1.0);
          final bounce = math.sin(phase * math.pi).clamp(0.0, 1.0);
          return Container(
            margin: const EdgeInsets.symmetric(horizontal: 4),
            transform: Matrix4.translationValues(0, -10 * bounce, 0),
            child: Container(
              width: 8,
              height: 8,
              decoration: BoxDecoration(
                color: _kOrange.withValues(alpha: 0.35 + 0.65 * bounce),
                shape: BoxShape.circle,
              ),
            ),
          );
        }),
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
              Builder(builder: (ctx) {
                final l = AppL10n.of(ctx);
                final lbl = switch (widget.dest.path) {
                  '/home'      => l.home,
                  '/orders'    => l.orders,
                  '/wallet'    => 'ePay',
                  '/community' => 'eSpace',
                  '/chat'      => l.messages,
                  '/profile'   => l.profile,
                  _            => widget.dest.label,
                };
                return Text(lbl, style: TextStyle(
                  color: active ? _kOrange : _hover ? Colors.white.withValues(alpha: 0.85) : Colors.white.withValues(alpha: 0.55),
                  fontSize: 14,
                  fontWeight: active ? FontWeight.w700 : FontWeight.w500,
                  letterSpacing: active ? 0.2 : 0,
                ));
              }),
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
            final l = AppL10n.of(context);
            final translatedLabel = switch (dest.path) {
              '/home'      => l.home,
              '/orders'    => l.orders,
              '/wallet'    => 'ePay',
              '/community' => 'eSpace',
              '/chat'      => l.messages,
              '/profile'   => l.profile,
              _            => dest.label,
            };

            // eSpace — special elevated pill that stands out from other nav items
            if (dest.path == '/community') {
              return _ESpaceNavItem(active: active, onTap: () => context.go(dest.path));
            }

            return Expanded(
              child: _NavPill(
                icon:       dest.icon,
                activeIcon: dest.activeIcon,
                label:      translatedLabel,
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

// ─── eSpace special nav item ──────────────────────────────────────────────────

class _ESpaceNavItem extends StatelessWidget {
  final bool active;
  final VoidCallback onTap;
  const _ESpaceNavItem({required this.active, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: GestureDetector(
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
                      active ? Icons.people : Icons.people_outline,
                      key: ValueKey(active),
                      color: _kOrange,
                      size: active ? 28 : 26,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'eSpace',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      color: _kOrange,
                      letterSpacing: 0.2,
                    ),
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
                        BoxShadow(color: _kOrange.withValues(alpha: 0.6), blurRadius: 6),
                      ],
                    ),
                  ),
                ),
            ],
          ),
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
