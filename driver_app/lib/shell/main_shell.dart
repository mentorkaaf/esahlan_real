import 'dart:convert';
import 'package:flutter/material.dart';
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

class _MainShellState extends State<MainShell> {
  static const _tabs = ['/dashboard', '/orders', '/earnings', '/wallet', '/profile'];

  int _index(BuildContext context) {
    final loc = GoRouterState.of(context).matchedLocation;
    final i = _tabs.indexOf(loc);
    return i >= 0 ? i : 0;
  }

  @override
  void initState() {
    super.initState();

    // ── Wire up new-order handler (fires from foreground FCM or notification tap)
    FirebaseService().onNewOrder = (data) {
      if (!mounted) return;
      // Build order map from FCM data — may be flat data keys or nested
      final orderData = _parseOrderFromFcm(data);
      context.push('/incoming-order', extra: orderData);
    };

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) AppUpdateChecker.check(context, 'driver');
    });
  }

  @override
  void dispose() {
    FirebaseService().onNewOrder = null;
    super.dispose();
  }

  /// FCM data keys from backend: order_id, order_number, delivery_fee,
  /// pickup_district, pickup_address, pickup_lat, pickup_lng,
  /// delivery_district, delivery_address, delivery_lat, delivery_lng,
  /// distance_km, estimated_minutes, driver_to_pickup_km, module_slug
  Map<String, dynamic> _parseOrderFromFcm(Map<String, dynamic> data) {
    // If backend sent a nested JSON string under 'order', decode it
    if (data.containsKey('order')) {
      try {
        final raw = data['order'];
        final nested = raw is String ? jsonDecode(raw) : raw;
        if (nested is Map<String, dynamic>) return nested;
      } catch (_) {}
    }

    // Otherwise reconstruct from flat keys
    return {
      'id': int.tryParse('${data['order_id'] ?? 0}') ?? 0,
      'order_number': data['order_number'] ?? '',
      'module_slug': data['module_slug'] ?? 'order',
      'delivery_fee': data['delivery_fee'] ?? '0',
      'distance_km': data['distance_km'] ?? '0',
      'estimated_minutes': int.tryParse('${data['estimated_minutes'] ?? 0}') ?? 0,
      'driver_to_pickup_km': data['driver_to_pickup_km'] ?? '0',
      'pickup': {
        'district': data['pickup_district'] ?? '',
        'address': data['pickup_address'] ?? '',
        'lat': data['pickup_lat'] ?? '0',
        'lng': data['pickup_lng'] ?? '0',
      },
      'delivery': {
        'district': data['delivery_district'] ?? '',
        'address': data['delivery_address'] ?? '',
        'lat': data['delivery_lat'] ?? '0',
        'lng': data['delivery_lng'] ?? '0',
      },
    };
  }

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Scaffold(
      body: widget.child,
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: c.navyLight,
          border: Border(top: BorderSide(color: c.border, width: 0.5)),
        ),
        child: BottomNavigationBar(
          currentIndex: _index(context),
          onTap: (i) => context.go(_tabs[i]),
          backgroundColor: c.navyLight,
          selectedItemColor: DC.orange,
          unselectedItemColor: c.textMuted,
          type: BottomNavigationBarType.fixed,
          selectedFontSize: 11,
          unselectedFontSize: 11,
          items: const [
            BottomNavigationBarItem(icon: Icon(Icons.home_rounded), label: 'Home'),
            BottomNavigationBarItem(icon: Icon(Icons.delivery_dining_rounded), label: 'Orders'),
            BottomNavigationBarItem(icon: Icon(Icons.bar_chart_rounded), label: 'Earnings'),
            BottomNavigationBarItem(icon: Icon(Icons.account_balance_wallet_rounded), label: 'Wallet'),
            BottomNavigationBarItem(icon: Icon(Icons.person_rounded), label: 'Profile'),
          ],
        ),
      ),
    );
  }
}
