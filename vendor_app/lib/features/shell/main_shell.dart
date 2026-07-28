import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../dashboard/dashboard_screen.dart';
import '../orders/orders_screen.dart';
import '../products/products_screen.dart';
import '../store/store_screen.dart';
import '../wallet/wallet_screen.dart';
import '../../core/services/auth_service.dart';
import '../../core/services/vendor_repository.dart';
import '../../core/theme/vc.dart';

final _pendingCountProvider = FutureProvider.autoDispose<int>((ref) async {
  try {
    final res = await VendorRepository.instance.orders(status: 'pending');
    return ((res['data'] as List?) ?? []).length;
  } catch (_) { return 0; }
});

class MainShell extends ConsumerStatefulWidget {
  final int initialIndex;
  const MainShell({super.key, this.initialIndex = 0});
  @override
  ConsumerState<MainShell> createState() => _MainShellState();
}

class _MainShellState extends ConsumerState<MainShell> {
  late int _index;
  String _moduleSlug = 'efood';

  static const _screens = [
    DashboardScreen(),
    OrdersScreen(),
    ProductsScreen(),
    StoreScreen(),
    WalletScreen(),
  ];

  @override
  void initState() {
    super.initState();
    _index = widget.initialIndex;
    _loadModule();
  }

  Future<void> _loadModule() async {
    final vendor = await AuthService.instance.getVendor();
    if (mounted && vendor != null) {
      setState(() => _moduleSlug = vendor['module_slug'] ?? 'efood');
    }
  }

  @override
  Widget build(BuildContext context) {
    final pending   = ref.watch(_pendingCountProvider);
    final pendingCount = pending.valueOrNull ?? 0;
    final isEshop   = _moduleSlug == 'eshop';
    final themeMode = ref.watch(themeModeProvider);
    final isDark    = themeMode == ThemeMode.dark;

    return Scaffold(
      body: IndexedStack(index: _index, children: _screens),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Theme.of(context).appBarTheme.backgroundColor,
          border: Border(top: BorderSide(color: Theme.of(context).dividerColor.withValues(alpha: 0.6))),
          boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: isDark ? 0.15 : 0.06), blurRadius: 16, offset: const Offset(0, -2))],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _NavItem(icon: Icons.dashboard_rounded, label: 'Dashboard', index: 0, selected: _index, onTap: () => setState(() => _index = 0)),
                _NavBadgeItem(icon: Icons.receipt_long_rounded, label: 'Orders', index: 1, selected: _index, badge: pendingCount, onTap: () => setState(() => _index = 1)),
                _NavItem(
                  icon: isEshop ? Icons.inventory_2_rounded : Icons.restaurant_menu_rounded,
                  label: isEshop ? 'Products' : 'Menu',
                  index: 2, selected: _index,
                  onTap: () => setState(() => _index = 2),
                ),
                _NavItem(icon: Icons.storefront_rounded, label: 'Store', index: 3, selected: _index, onTap: () => setState(() => _index = 3)),
                _NavItem(icon: Icons.account_balance_wallet_rounded, label: 'Wallet', index: 4, selected: _index, onTap: () => setState(() => _index = 4)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final int index, selected;
  final VoidCallback onTap;
  const _NavItem({required this.icon, required this.label, required this.index, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final active = index == selected;
    final unselColor = Theme.of(context).brightness == Brightness.dark ? VC.textMuted : const Color(0xFF94A3B8);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(color: active ? VC.orangeDim : Colors.transparent, borderRadius: BorderRadius.circular(12)),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, color: active ? VC.orange : unselColor, size: 22),
          const SizedBox(height: 2),
          Text(label, style: TextStyle(color: active ? VC.orange : unselColor, fontSize: 10, fontWeight: active ? FontWeight.w800 : FontWeight.w500)),
        ]),
      ),
    );
  }
}

class _NavBadgeItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final int index, selected, badge;
  final VoidCallback onTap;
  const _NavBadgeItem({required this.icon, required this.label, required this.index, required this.selected, required this.badge, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final active = index == selected;
    final unselColor = Theme.of(context).brightness == Brightness.dark ? VC.textMuted : const Color(0xFF94A3B8);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(color: active ? VC.orangeDim : Colors.transparent, borderRadius: BorderRadius.circular(12)),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Stack(clipBehavior: Clip.none, children: [
            Icon(icon, color: active ? VC.orange : unselColor, size: 22),
            if (badge > 0) Positioned(top: -4, right: -6, child: Container(
              width: 16, height: 16, decoration: const BoxDecoration(color: VC.red, shape: BoxShape.circle),
              child: Center(child: Text(badge > 9 ? '9+' : '$badge', style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w900))),
            )),
          ]),
          const SizedBox(height: 2),
          Text(label, style: TextStyle(color: active ? VC.orange : unselColor, fontSize: 10, fontWeight: active ? FontWeight.w800 : FontWeight.w500)),
        ]),
      ),
    );
  }
}
