import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/driver_colors.dart';
import '../../../auth/presentation/providers/auth_provider.dart';

final _profileProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.read(authRepoProvider).profile());

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final data = ref.watch(_profileProvider);

    return Scaffold(
      backgroundColor: DC.navy,
      appBar: AppBar(title: const Text('Profile')),
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
        error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: DC.error))),
        data: (d) {
          final user = d['user'] as Map<String, dynamic>? ?? {};
          return ListView(padding: const EdgeInsets.all(16), children: [
            // Avatar + name
            Center(child: Column(children: [
              Container(
                width: 80, height: 80,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(colors: [DC.orange, Color(0xFFFF6200)]),
                  shape: BoxShape.circle,
                ),
                child: Center(child: Text(
                  (user['name'] ?? 'D')[0].toUpperCase(),
                  style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w900),
                )),
              ),
              const SizedBox(height: 12),
              Text(user['name'] ?? '—', style: const TextStyle(color: DC.text, fontSize: 20, fontWeight: FontWeight.w800)),
              Text(user['phone'] ?? '', style: const TextStyle(color: DC.textSec, fontSize: 13)),
              const SizedBox(height: 8),
              Row(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.star_rounded, color: DC.busy, size: 18),
                const SizedBox(width: 4),
                Text('${d['rating'] ?? 5.0}', style: const TextStyle(color: DC.text, fontWeight: FontWeight.w700)),
                Text(' · ${d['total_deliveries'] ?? 0} deliveries', style: const TextStyle(color: DC.textMuted, fontSize: 12)),
              ]),
            ])),
            const SizedBox(height: 28),

            // Vehicle info
            _SectionCard(title: 'Vehicle', icon: Icons.directions_bike_rounded, children: [
              _InfoRow('Type', _vehicleLabel(d['vehicle_type'])),
              _InfoRow('Plate', d['vehicle_plate'] ?? '—'),
              _InfoRow('Model', d['vehicle_model'] ?? '—'),
            ]),
            const SizedBox(height: 12),

            // Status
            _SectionCard(title: 'Account', icon: Icons.shield_rounded, children: [
              _InfoRow('Status', (d['status'] ?? 'pending').toString().toUpperCase()),
              _InfoRow('Approved', (d['is_approved'] == true) ? 'Yes' : 'No'),
            ]),
            const SizedBox(height: 24),

            // Logout
            SizedBox(width: double.infinity, height: 50, child: OutlinedButton.icon(
              onPressed: () => ref.read(logoutProvider)(),
              icon: const Icon(Icons.logout_rounded, color: DC.error),
              label: const Text('Logout', style: TextStyle(color: DC.error, fontWeight: FontWeight.w700)),
              style: OutlinedButton.styleFrom(side: const BorderSide(color: DC.error)),
            )),
          ]);
        },
      ),
    );
  }

  String _vehicleLabel(String? t) => switch (t) {
    'motorcycle' => '🏍️ Motorcycle',
    'bajaj'      => '🛺 Bajaj',
    'car'        => '🚗 Car',
    'van'        => '🚐 Van',
    'truck'      => '🚛 Truck',
    'bicycle'    => '🚲 Bicycle',
    _ => t ?? '—',
  };
}

class _SectionCard extends StatelessWidget {
  final String title; final IconData icon; final List<Widget> children;
  const _SectionCard({required this.title, required this.icon, required this.children});
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(color: DC.card, borderRadius: BorderRadius.circular(16)),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Icon(icon, color: DC.orange, size: 18),
        const SizedBox(width: 8),
        Text(title, style: const TextStyle(color: DC.text, fontWeight: FontWeight.w700, fontSize: 14)),
      ]),
      const SizedBox(height: 12),
      ...children,
    ]),
  );
}

class _InfoRow extends StatelessWidget {
  final String label, value;
  const _InfoRow(this.label, this.value);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 8),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
      Text(label, style: const TextStyle(color: DC.textMuted, fontSize: 13)),
      Text(value, style: const TextStyle(color: DC.text, fontWeight: FontWeight.w600, fontSize: 13)),
    ]),
  );
}
