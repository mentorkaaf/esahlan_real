import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../core/theme/driver_colors.dart';
import '../core/services/app_update_checker.dart';

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
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) AppUpdateChecker.check(context, 'driver');
    });
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
