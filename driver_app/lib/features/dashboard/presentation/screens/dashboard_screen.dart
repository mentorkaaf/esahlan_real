import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/driver_colors.dart';
import '../../../../core/services/location_service.dart';
import '../../../auth/presentation/providers/auth_provider.dart';

final _dashProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) {
  return ref.read(authRepoProvider).dashboard();
});

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dash = ref.watch(_dashProvider);

    return Scaffold(
      backgroundColor: DC.navy,
      appBar: AppBar(
        title: Row(mainAxisSize: MainAxisSize.min, children: [
          Container(width: 32, height: 32, decoration: BoxDecoration(color: DC.orange, borderRadius: BorderRadius.circular(8)),
            child: const Icon(Icons.delivery_dining, color: Colors.white, size: 18)),
          const SizedBox(width: 10),
          const Text('eSahlan Driver'),
        ]),
        actions: [
          IconButton(icon: const Icon(Icons.notifications_outlined), onPressed: () {}),
        ],
      ),
      body: dash.when(
        loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
        error: (e, _) => Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
          Text('$e', style: const TextStyle(color: DC.error)),
          const SizedBox(height: 12),
          ElevatedButton(onPressed: () => ref.invalidate(_dashProvider), child: const Text('Retry')),
        ])),
        data: (d) => RefreshIndicator(
          color: DC.orange,
          onRefresh: () async => ref.invalidate(_dashProvider),
          child: ListView(padding: const EdgeInsets.all(16), children: [
            // Online/Offline Toggle
            _OnlineToggle(isOnline: d['is_online'] == true, onToggle: () async {
              await ref.read(authRepoProvider).toggleStatus();
              ref.invalidate(_dashProvider);
              final goingOnline = d['is_online'] != true;
              if (goingOnline) {
                DriverLocationService.startTracking();
              } else {
                DriverLocationService.stopTracking();
              }
            }),
            const SizedBox(height: 20),

            // Stats
            Row(children: [
              Expanded(child: _StatCard(icon: Icons.shopping_bag_rounded, label: 'Today', value: '${d['today_orders'] ?? 0}', sub: 'orders', color: DC.orange)),
              const SizedBox(width: 12),
              Expanded(child: _StatCard(icon: Icons.attach_money_rounded, label: 'Earnings', value: '\$${(d['today_earnings'] ?? 0).toStringAsFixed(2)}', sub: 'today', color: DC.success)),
              const SizedBox(width: 12),
              Expanded(child: _StatCard(icon: Icons.star_rounded, label: 'Rating', value: '${d['rating'] ?? 5.0}', sub: '${d['total_deliveries'] ?? 0} trips', color: DC.busy)),
            ]),
            const SizedBox(height: 20),

            // Wallet balance
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFF1A2A4A), Color(0xFF243355)]), borderRadius: BorderRadius.circular(16)),
              child: Row(children: [
                Container(width: 44, height: 44, decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(12)),
                  child: const Icon(Icons.account_balance_wallet_rounded, color: DC.orange, size: 22)),
                const SizedBox(width: 14),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('Wallet Balance', style: TextStyle(color: DC.textSec, fontSize: 12)),
                  Text('\$${(d['wallet_balance'] ?? 0).toStringAsFixed(2)}', style: const TextStyle(color: DC.text, fontSize: 22, fontWeight: FontWeight.w900)),
                ])),
              ]),
            ),
            const SizedBox(height: 20),

            // Weekly earnings
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(16)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('This Week', style: TextStyle(color: DC.textSec, fontSize: 12, fontWeight: FontWeight.w600)),
                const SizedBox(height: 6),
                Text('\$${(d['weekly_earnings'] ?? 0).toStringAsFixed(2)}', style: const TextStyle(color: DC.success, fontSize: 28, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text('${d['completed_today'] ?? 0} deliveries completed today', style: const TextStyle(color: DC.textMuted, fontSize: 12)),
              ]),
            ),
            const SizedBox(height: 20),

            // Active order
            if (d['active_order'] != null) ...[
              const Text('Active Delivery', style: TextStyle(color: DC.text, fontSize: 16, fontWeight: FontWeight.w800)),
              const SizedBox(height: 10),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(16), border: Border.all(color: DC.orange.withValues(alpha: 0.3))),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: DC.orangeDim, borderRadius: BorderRadius.circular(6)),
                      child: Text(d['active_order']['module_slug'] ?? 'order', style: const TextStyle(color: DC.orange, fontSize: 11, fontWeight: FontWeight.w700))),
                    const SizedBox(width: 8),
                    Text('#${d['active_order']['order_number'] ?? ''}', style: const TextStyle(color: DC.text, fontWeight: FontWeight.w700)),
                  ]),
                  const SizedBox(height: 10),
                  if (d['active_order']['vendor'] != null) Text(d['active_order']['vendor']['name'] ?? '', style: const TextStyle(color: DC.textSec, fontSize: 13)),
                  const SizedBox(height: 8),
                  SizedBox(width: double.infinity, child: ElevatedButton(onPressed: () => context.go('/orders'), child: const Text('View Details'))),
                ]),
              ),
            ],
          ]),
        ),
      ),
    );
  }
}

class _OnlineToggle extends StatelessWidget {
  final bool isOnline;
  final VoidCallback onToggle;
  const _OnlineToggle({required this.isOnline, required this.onToggle});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onToggle,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 300),
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          gradient: LinearGradient(
            colors: isOnline ? [const Color(0xFF064E3B), const Color(0xFF065F46)] : [DC.card, DC.cardLight],
          ),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: isOnline ? DC.success.withValues(alpha: 0.4) : DC.border),
        ),
        child: Row(children: [
          Container(
            width: 56, height: 56,
            decoration: BoxDecoration(
              color: isOnline ? DC.success.withValues(alpha: 0.2) : DC.textMuted.withValues(alpha: 0.15),
              shape: BoxShape.circle,
            ),
            child: Icon(isOnline ? Icons.power_settings_new_rounded : Icons.power_off_rounded,
                color: isOnline ? DC.success : DC.textMuted, size: 28),
          ),
          const SizedBox(width: 16),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(isOnline ? 'You are Online' : 'You are Offline',
                style: TextStyle(color: isOnline ? DC.success : DC.textMuted, fontSize: 18, fontWeight: FontWeight.w800)),
            Text(isOnline ? 'Receiving delivery requests' : 'Tap to go online',
                style: TextStyle(color: isOnline ? DC.success.withValues(alpha: 0.7) : DC.textMuted, fontSize: 12)),
          ])),
          Container(
            width: 52, height: 30,
            decoration: BoxDecoration(
              color: isOnline ? DC.success : DC.textMuted,
              borderRadius: BorderRadius.circular(15),
            ),
            child: AnimatedAlign(
              duration: const Duration(milliseconds: 200),
              alignment: isOnline ? Alignment.centerRight : Alignment.centerLeft,
              child: Container(width: 26, height: 26, margin: const EdgeInsets.all(2),
                decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle)),
            ),
          ),
        ]),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final IconData icon;
  final String label, value, sub;
  final Color color;
  const _StatCard({required this.icon, required this.label, required this.value, required this.sub, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(14)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, color: color, size: 22),
        const SizedBox(height: 10),
        Text(value, style: TextStyle(color: DC.text, fontSize: 18, fontWeight: FontWeight.w900)),
        Text(sub, style: const TextStyle(color: DC.textMuted, fontSize: 11)),
      ]),
    );
  }
}
