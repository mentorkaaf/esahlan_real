import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/services/fcm_service.dart';
import '../../core/theme/vc.dart';
import 'dashboard/agent_dashboard_screen.dart';
import 'properties/agent_properties_screen.dart';
import 'properties/add_property_screen.dart';
import 'wallet/agent_wallet_screen.dart';
import 'profile/agent_profile_screen.dart';
import 'requests/house_requests_screen.dart';

class AgentShell extends ConsumerStatefulWidget {
  const AgentShell({super.key});
  @override
  ConsumerState<AgentShell> createState() => _AgentShellState();
}

class _AgentShellState extends ConsumerState<AgentShell> with WidgetsBindingObserver {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _checkPendingRoute());
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _checkPendingRoute();
  }

  void _checkPendingRoute() {
    final route = VendorFcmService.consumePendingRoute();
    if (route == '/agent/requests' && mounted) {
      setState(() => _index = 2);
      // After switching tab, open specific request if pending
      WidgetsBinding.instance.addPostFrameCallback((_) {
        houseRequestsScreenKey.currentState?.openPendingRequest();
      });
    }
  }

  static final _screens = [
    const AgentDashboardScreen(),
    const AgentPropertiesScreen(),
    HouseRequestsScreen(key: houseRequestsScreenKey),
    const AgentWalletScreen(),
    const AgentProfileScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    final themeMode = ref.watch(themeModeProvider);
    final isDark = themeMode == ThemeMode.dark;

    return Scaffold(
      body: IndexedStack(index: _index, children: _screens),
      floatingActionButton: _index == 1
          ? FloatingActionButton.extended(
              onPressed: () async {
                final added = await Navigator.push<bool>(
                  context,
                  MaterialPageRoute(builder: (_) => const AddPropertyScreen()),
                );
                if (added == true) setState(() {});
              },
              backgroundColor: _kTeal,
              foregroundColor: Colors.white,
              icon: const Icon(Icons.add_rounded),
              label: const Text('List Property', style: TextStyle(fontWeight: FontWeight.w800)),
            )
          : null,
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: isDark ? VC.navyLight : VC.lightSurface,
          border: Border(top: BorderSide(color: isDark ? VC.border : VC.lightBorder)),
          boxShadow: [BoxShadow(
            color: Colors.black.withValues(alpha: isDark ? 0.15 : 0.06),
            blurRadius: 16, offset: const Offset(0, -2),
          )],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _AgentNavItem(icon: Icons.dashboard_rounded,              label: 'Dashboard',  index: 0, selected: _index, onTap: () => setState(() => _index = 0)),
                _AgentNavItem(icon: Icons.apartment_rounded,              label: 'Properties', index: 1, selected: _index, onTap: () => setState(() => _index = 1)),
                _AgentNavItem(icon: Icons.inbox_rounded,                  label: 'Requests',   index: 2, selected: _index, onTap: () => setState(() => _index = 2)),
                _AgentNavItem(icon: Icons.account_balance_wallet_rounded, label: 'Wallet',     index: 3, selected: _index, onTap: () => setState(() => _index = 3)),
                _AgentNavItem(icon: Icons.person_rounded,                 label: 'Profile',    index: 4, selected: _index, onTap: () => setState(() => _index = 4)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

const _kTeal = Color(0xFF0EA5E9);

class _AgentNavItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final int index, selected;
  final VoidCallback onTap;
  const _AgentNavItem({required this.icon, required this.label, required this.index, required this.selected, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final active = index == selected;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final unsel = isDark ? VC.textMuted : const Color(0xFF94A3B8);
    return GestureDetector(
      onTap: onTap,
      behavior: HitTestBehavior.opaque,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        decoration: BoxDecoration(
          color: active ? _kTeal.withValues(alpha: 0.12) : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(icon, color: active ? _kTeal : unsel, size: 22),
          const SizedBox(height: 2),
          Text(label, style: TextStyle(
            color: active ? _kTeal : unsel,
            fontSize: 10,
            fontWeight: active ? FontWeight.w800 : FontWeight.w500,
          )),
        ]),
      ),
    );
  }
}
