import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:geolocator/geolocator.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../core/theme/driver_colors.dart';
import '../core/services/app_update_checker.dart';
import '../core/services/firebase_service.dart';
import '../core/services/location_service.dart';
import '../core/storage/local_storage.dart';
import '../features/auth/presentation/providers/auth_provider.dart';

class MainShell extends ConsumerStatefulWidget {
  final Widget child;
  const MainShell({super.key, required this.child});

  @override
  ConsumerState<MainShell> createState() => _MainShellState();
}

class _MainShellState extends ConsumerState<MainShell> with WidgetsBindingObserver {
  static const _tabs        = ['/dashboard', '/orders', '/earnings', '/wallet', '/profile'];
  static const _intentCh    = MethodChannel('esahlan_intent');
  bool _initialized        = false;
  bool _locationOk         = true;
  bool _showingOrderScreen = false;

  int _index(BuildContext context) {
    final loc = GoRouterState.of(context).matchedLocation;
    final i   = _tabs.indexOf(loc);
    return i >= 0 ? i : 0;
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);

    // ── Wire up the in-app new-order navigation ──────────────────────────
    FirebaseService().onNewOrder = _handleIncomingOrder;

    // Listen for new intents from OrderCallActivity → MainActivity.
    // OrderCallActivity sends order_action="accept"/"decline" as an intent extra.
    // For background state: onNewIntent fires with the action → _handleOrderAction runs.
    // For killed state: action is read from SharedPrefs in _checkNativeOrderAction().
    _intentCh.setMethodCallHandler((call) async {
      if (call.method == 'onNewIntent') {
        final action = call.arguments as String?;
        debugPrint('[Shell] onNewIntent order_action=$action');
        if (action != null && action.isNotEmpty) {
          await _handleOrderAction(action);
        } else {
          // Ring notification tapped with no specific action → show ring screen
          await _checkPendingOrder();
        }
      }
    });

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      // Initialise FCM channels + listeners (safe to call multiple times)
      await FirebaseService().initialize();
      _initialized = true;

      // Check location on startup
      await _checkLocation();

      // Check if OrderCallActivity set an action (accept/decline) before app launched
      await _checkNativeOrderAction();

      // Check for order that arrived while app was KILLED or BACKGROUND
      await _checkPendingOrder();

      if (mounted) AppUpdateChecker.check(context, 'driver');
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    FirebaseService().onNewOrder = null;
    super.dispose();
  }

  // ── App lifecycle — check location + pending order when app resumes ──────
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _initialized) {
      _checkLocation();
      _checkPendingOrder();
      // Ensure foreground service is still running if driver was online.
      // The OS may have killed it while app was in background.
      _ensureTrackingAlive();
    }
  }

  // ── Re-start foreground service if it was killed by the OS ───────────────
  Future<void> _ensureTrackingAlive() async {
    final token = await LocalStorage.getToken();
    if (token == null) return; // not logged in
    final wasTracking = await LocalStorage.getBool('driver_was_tracking');
    if (!wasTracking) return; // driver chose to be offline
    if (!DriverLocationService.isRunning) {
      // Service died — restart it silently
      await DriverLocationService.startTracking();
      debugPrint('[Shell] Foreground service restarted after OS kill');
    }
  }

  // ── Location gate: device GPS + permission must both be ON ───────────────
  Future<void> _checkLocation() async {
    final serviceEnabled = await Geolocator.isLocationServiceEnabled();
    final permission     = await Geolocator.checkPermission();
    final permOk = permission == LocationPermission.always ||
                   permission == LocationPermission.whileInUse;
    final ok = serviceEnabled && permOk;
    if (mounted && ok != _locationOk) {
      setState(() => _locationOk = ok);
    } else if (!mounted) {
      _locationOk = ok;
    }
  }

  // ── Check native order action (no-op now) ────────────────────────────────────
  // OrderCallActivity is now a notification-only alert (SEE ORDER button).
  // It does NOT write accept/decline — those happen in IncomingOrderScreen.
  // This method kept for safety (clears any leftover key from old installs).
  Future<void> _checkNativeOrderAction() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final saved = prefs.getString('pending_ring_action');
      if (saved != null && saved.isNotEmpty) {
        await prefs.remove('pending_ring_action');
        debugPrint('[Shell] cleared legacy ring_action=$saved');
      }
    } catch (_) {}
  }

  /// Process an accept/decline action that came from the native OrderCallActivity.
  /// Reads the pending order from SharedPreferences for the order ID.
  Future<void> _handleOrderAction(String? action) async {
    if (action == null || action.isEmpty) return;
    final data = await FirebaseService.checkPendingOrder();
    if (data == null) return;

    final orderId = int.tryParse('${data['order_id'] ?? 0}') ?? 0;
    if (orderId == 0) return;

    if (!mounted) return;
    await Future.delayed(const Duration(milliseconds: 300));

    if (action == 'accept') {
      try {
        await ref.read(authRepoProvider).acceptOrder(orderId);
        if (mounted) {
          context.go('/orders');
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(
            content: const Text('Order accepted — go pick it up! 🚴'),
            backgroundColor: const Color(0xFF22C55E),
            behavior: SnackBarBehavior.floating,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ));
        }
      } catch (e) {
        debugPrint('[Shell] auto-accept failed: $e');
        // If accept API fails, show ring screen so driver can retry
        if (mounted) _handleIncomingOrder(data);
      }
    } else if (action == 'decline') {
      try {
        await ref.read(authRepoProvider).rejectOrder(orderId);
        debugPrint('[Shell] order $orderId auto-declined from native screen');
      } catch (_) {}
    }
  }

  // ── Read pending order from SharedPreferences ────────────────────────────
  // Called on startup AND on resume. OrderCallActivity shows a simple alert
  // and when driver taps SEE ORDER → MainActivity opens → this reads the order
  // and shows IncomingOrderScreen (the real ring screen with full details).
  Future<void> _checkPendingOrder() async {
    final data = await FirebaseService.checkPendingOrder();
    if (data != null && mounted) {
      await Future.delayed(const Duration(milliseconds: 200));
      if (mounted) _handleIncomingOrder(data);
    }
  }

  // ── Navigate to IncomingOrderScreen ──────────────────────────────────────
  void _handleIncomingOrder(Map<String, dynamic> data) {
    if (!mounted) return;
    // Fix H-8: prevent pushing a second /incoming-order while one is showing
    if (_showingOrderScreen) return;
    _showingOrderScreen = true;
    FirebaseService().cancelOrderNotification();
    final orderData = _parseOrderFromFcm(data);
    context.push('/incoming-order', extra: orderData).whenComplete(() {
      _showingOrderScreen = false;
    });
  }

  /// Map flat FCM data keys → nested order structure for IncomingOrderScreen.
  Map<String, dynamic> _parseOrderFromFcm(Map<String, dynamic> data) {
    // Backend sends a nested JSON string under 'order' key in some flows
    if (data.containsKey('order')) {
      try {
        final raw    = data['order'];
        final nested = raw is String ? jsonDecode(raw) : raw;
        if (nested is Map<String, dynamic>) return nested;
      } catch (_) {}
    }

    // Standard flat keys from sendNewOrderRing / sendDataOnly
    return {
      'id':           int.tryParse('${data['order_id'] ?? 0}') ?? 0,
      'order_number': data['order_number'] ?? '',
      'module_slug':  data['module_slug']  ?? 'order',
      'delivery_fee': data['delivery_fee'] ?? '0',
      'distance_km':  data['distance_km']  ?? '0',
      'estimated_minutes':    int.tryParse('${data['estimated_minutes'] ?? 0}') ?? 0,
      'driver_to_pickup_km':  data['driver_to_pickup_km'] ?? '0',
      'pickup': {
        'district': data['pickup_district'] ?? '',
        'address':  data['pickup_address']  ?? '',
        'lat':      data['pickup_lat']      ?? '0',
        'lng':      data['pickup_lng']      ?? '0',
      },
      'delivery': {
        'district': data['delivery_district'] ?? '',
        'address':  data['delivery_address']  ?? '',
        'lat':      data['delivery_lat']      ?? '0',
        'lng':      data['delivery_lng']      ?? '0',
      },
    };
  }

  @override
  Widget build(BuildContext context) {
    if (!_locationOk) {
      return _LocationGateScreen(onRetry: _checkLocation);
    }
    return Scaffold(
      body: widget.child,
      bottomNavigationBar: _PremiumNavBar(
        currentIndex: _index(context),
        onTap: (i) => context.go(_tabs[i]),
      ),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Location Gate Screen — blocks app until GPS + permission are enabled
// ──────────────────────────────────────────────────────────────────────────────
class _LocationGateScreen extends StatelessWidget {
  final Future<void> Function() onRetry;
  const _LocationGateScreen({required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF080D18),
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 100, height: 100,
                  decoration: BoxDecoration(
                    color: DC.orange.withValues(alpha: 0.12),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.location_off_rounded,
                      size: 48, color: DC.orange),
                ),
                const SizedBox(height: 28),
                const Text(
                  'Location Required',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 12),
                Text(
                  'eSahlan Driver requires your device location to be enabled in order to receive and deliver orders.\n\nPlease turn on Location Services to continue.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.6),
                    fontSize: 14,
                    height: 1.6,
                  ),
                ),
                const SizedBox(height: 36),
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    icon: const Icon(Icons.settings_rounded),
                    label: const Text('Open Location Settings'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: DC.orange,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14)),
                      textStyle: const TextStyle(
                          fontSize: 15, fontWeight: FontWeight.w700),
                    ),
                    onPressed: () async {
                      await Geolocator.openLocationSettings();
                    },
                  ),
                ),
                const SizedBox(height: 12),
                TextButton(
                  onPressed: onRetry,
                  child: Text(
                    'I\'ve enabled it — Continue',
                    style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.5),
                        fontSize: 13),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

// ──────────────────────────────────────────────────────────────────────────────
// Premium Bottom Navigation Bar
// ──────────────────────────────────────────────────────────────────────────────
class _PremiumNavBar extends StatelessWidget {
  final int currentIndex;
  final ValueChanged<int> onTap;
  const _PremiumNavBar({required this.currentIndex, required this.onTap});

  static const _items = [
    (Icons.home_rounded,                    Icons.home_outlined,                   'Home'),
    (Icons.delivery_dining_rounded,         Icons.delivery_dining_outlined,        'Orders'),
    (Icons.bar_chart_rounded,               Icons.bar_chart_outlined,              'Earnings'),
    (Icons.account_balance_wallet_rounded,  Icons.account_balance_wallet_outlined, 'Wallet'),
    (Icons.person_rounded,                  Icons.person_outline_rounded,          'Profile'),
  ];

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.of(context).padding.bottom;
    return Container(
      height: 60 + bottom,
      padding: EdgeInsets.only(bottom: bottom),
      decoration: BoxDecoration(
        color: const Color(0xFF0D1420),
        border: Border(
            top: BorderSide(
                color: Colors.white.withValues(alpha: 0.06), width: 0.5)),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withValues(alpha: 0.3),
              blurRadius: 20,
              offset: const Offset(0, -4)),
        ],
      ),
      child: Row(
        children: List.generate(_items.length, (i) {
          final (activeIcon, inactiveIcon, label) = _items[i];
          final isActive = i == currentIndex;
          return Expanded(
            child: GestureDetector(
              behavior: HitTestBehavior.opaque,
              onTap: () {
                HapticFeedback.selectionClick();
                onTap(i);
              },
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 200),
                    padding: const EdgeInsets.symmetric(
                        horizontal: 14, vertical: 6),
                    decoration: BoxDecoration(
                      color: isActive
                          ? DC.orange.withValues(alpha: 0.15)
                          : Colors.transparent,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      isActive ? activeIcon : inactiveIcon,
                      color: isActive ? DC.orange : Colors.white30,
                      size: 22,
                    ),
                  ),
                  const SizedBox(height: 2),
                  AnimatedDefaultTextStyle(
                    duration: const Duration(milliseconds: 200),
                    style: TextStyle(
                      color: isActive ? DC.orange : Colors.white30,
                      fontSize: 10,
                      fontWeight:
                          isActive ? FontWeight.w700 : FontWeight.w500,
                    ),
                    child: Text(label),
                  ),
                ],
              ),
            ),
          );
        }),
      ),
    );
  }
}
