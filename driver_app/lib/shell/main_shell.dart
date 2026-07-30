import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import '../core/theme/driver_colors.dart';

class MainShell extends StatelessWidget {
  final Widget child;
  const MainShell({super.key, required this.child});

  static const _tabs = ['/dashboard', '/orders', '/earnings', '/wallet', '/profile'];

  int _index(BuildContext context) {
    final loc = GoRouterState.of(context).matchedLocation;
    final i = _tabs.indexOf(loc);
    return i >= 0 ? i : 0;
  }

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Scaffold(
      body: child,
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
