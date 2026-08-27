import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import '../core/theme/driver_colors.dart';
import '../core/services/app_update_checker.dart';
import '../core/services/firebase_service.dart';

class MainShell extends StatefulWidget {
  final Widget child;
  const MainShell({super.key, required this.child});

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> with WidgetsBindingObserver {
  static const _tabs = ['/dashboard', '/orders', '/earnings', '/wallet', '/profile'];
  bool _initialized = false;

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

    WidgetsBinding.instance.addPostFrameCallback((_) async {
      // Initialise FCM channels + listeners (safe to call multiple times)
      await FirebaseService().initialize();
      _initialized = true;

      // Check for order that arrived while app was KILLED or BACKGROUND
      // _bgHandler saved it to SharedPreferences; we read + consume it now.
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

  // ── App lifecycle — check pending order when app comes to foreground ─────
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _initialized) {
      _checkPendingOrder();
    }
  }

  // ── Read pending order from SharedPreferences ────────────────────────────
  // Called on startup AND every time the app resumes.
  // This handles BOTH killed-app taps AND background-app taps.
  Future<void> _checkPendingOrder() async {
    final data = await FirebaseService.checkPendingOrder();
    if (data != null && mounted) {
      // Small delay ensures GoRouter is settled before push
      await Future.delayed(const Duration(milliseconds: 200));
      if (mounted) _handleIncomingOrder(data);
    }
  }

  // ── Navigate to IncomingOrderScreen ──────────────────────────────────────
  void _handleIncomingOrder(Map<String, dynamic> data) {
    if (!mounted) return;
    // Cancel alarm notification (app is now showing the full-screen UI)
    FirebaseService().cancelOrderNotification();
    final orderData = _parseOrderFromFcm(data);
    context.push('/incoming-order', extra: orderData);
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
    final c = context.dc;
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
