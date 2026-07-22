import 'package:flutter/material.dart';
import 'crypto_theme.dart';
import 'crypto_home_screen.dart';
import 'crypto_markets_screen.dart';
import 'crypto_buy_sell_screen.dart';
import 'crypto_p2p_screen.dart';
import 'crypto_wallet_screen.dart';

/// Main shell — bottom nav with 5 tabs.
class CryptoExchangeScreen extends StatefulWidget {
  const CryptoExchangeScreen({super.key});

  @override
  State<CryptoExchangeScreen> createState() => _CryptoExchangeScreenState();
}

class _CryptoExchangeScreenState extends State<CryptoExchangeScreen> {
  int _index = 0;

  static const _screens = [
    CryptoHomeScreen(),
    CryptoMarketsScreen(),
    CryptoBuySellScreen(),
    CryptoP2PScreen(),
    CryptoWalletScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: cryptoTheme(),
      child: Scaffold(
        backgroundColor: kCryptoBg,
        body: IndexedStack(index: _index, children: _screens),
        bottomNavigationBar: _BottomNav(
          current: _index,
          onTap: (i) => setState(() => _index = i),
        ),
      ),
    );
  }
}

class _BottomNav extends StatelessWidget {
  const _BottomNav({required this.current, required this.onTap});
  final int current;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: kCryptoCard,
        border: Border(top: BorderSide(color: kCryptoBorder)),
      ),
      child: SafeArea(
        child: SizedBox(
          height: 60,
          child: Row(
            children: [
              _NavItem(icon: Icons.home_outlined,       activeIcon: Icons.home,                  label: 'Home',     index: 0, current: current, onTap: onTap),
              _NavItem(icon: Icons.bar_chart_outlined,  activeIcon: Icons.bar_chart,             label: 'Markets',  index: 1, current: current, onTap: onTap),
              _NavItem(icon: Icons.swap_horiz_outlined, activeIcon: Icons.swap_horiz,            label: 'Buy/Sell', index: 2, current: current, onTap: onTap),
              _NavItem(icon: Icons.people_outline,      activeIcon: Icons.people,                label: 'P2P',      index: 3, current: current, onTap: onTap),
              _NavItem(icon: Icons.account_balance_wallet_outlined, activeIcon: Icons.account_balance_wallet, label: 'Wallet', index: 4, current: current, onTap: onTap),
            ],
          ),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.icon, required this.activeIcon, required this.label,
    required this.index, required this.current, required this.onTap,
  });
  final IconData icon, activeIcon;
  final String label;
  final int index, current;
  final ValueChanged<int> onTap;

  @override
  Widget build(BuildContext context) {
    final active = index == current;
    return Expanded(
      child: GestureDetector(
        behavior: HitTestBehavior.opaque,
        onTap: () => onTap(index),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(active ? activeIcon : icon,
                size: 22, color: active ? kCryptoPrimary : kCryptoMuted),
            const SizedBox(height: 3),
            Text(label,
                style: TextStyle(
                    fontSize: 10,
                    fontWeight: active ? FontWeight.w700 : FontWeight.normal,
                    color: active ? kCryptoPrimary : kCryptoMuted)),
          ],
        ),
      ),
    );
  }
}
