import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/driver_colors.dart';
import '../../../../core/providers/theme_provider.dart';
import '../../../auth/presentation/providers/auth_provider.dart';

final _profileProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.read(authRepoProvider).profile());

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.dc;
    final themeMode = ref.watch(themeModeProvider);
    final data = ref.watch(_profileProvider);

    return Scaffold(
      backgroundColor: c.navy,
      body: data.when(
        loading: () => const Center(child: CircularProgressIndicator(color: DC.orange)),
        error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: DC.error))),
        data: (d) {
          final user = d['user'] as Map<String, dynamic>? ?? {};
          final name = user['name'] ?? 'Driver';
          final rating = double.tryParse('${d['rating'] ?? 5.0}') ?? 5.0;

          return RefreshIndicator(
            color: DC.orange,
            onRefresh: () async => ref.invalidate(_profileProvider),
            child: CustomScrollView(slivers: [
              // Profile header — intentional dark gradient (brand identity)
              SliverToBoxAdapter(child: Container(
                padding: const EdgeInsets.fromLTRB(24, 60, 24, 28),
                decoration: const BoxDecoration(
                  gradient: LinearGradient(colors: [Color(0xFF0F1B30), Color(0xFF1A2A4A)], begin: Alignment.topCenter, end: Alignment.bottomCenter),
                ),
                child: Column(children: [
                  // Avatar
                  Container(width: 90, height: 90,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(colors: [Color(0xFFFF8A00), Color(0xFFFF6B00)]),
                      shape: BoxShape.circle,
                      boxShadow: [BoxShadow(color: DC.orange.withValues(alpha: 0.35), blurRadius: 20)],
                    ),
                    child: Center(child: Text(name[0].toUpperCase(), style: const TextStyle(color: Colors.white, fontSize: 36, fontWeight: FontWeight.w900)))),
                  const SizedBox(height: 14),
                  Text(name, style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w800)),
                  const SizedBox(height: 4),
                  Text(user['phone'] ?? '', style: const TextStyle(color: Color(0xFF8B9CC7), fontSize: 14)),
                  const SizedBox(height: 12),

                  // Stats row
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
                    decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.06), borderRadius: BorderRadius.circular(16)),
                    child: Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
                      _StatCol('⭐ ${rating.toStringAsFixed(1)}', '${d['total_reviews'] ?? 0} Reviews'),
                      Container(width: 1, height: 30, color: Colors.white.withValues(alpha: 0.15)),
                      _StatCol('${d['total_deliveries'] ?? 0}', 'Completed'),
                      Container(width: 1, height: 30, color: Colors.white.withValues(alpha: 0.15)),
                      _StatCol('0', 'Cancelled'),
                    ]),
                  ),
                ]),
              )),

              SliverToBoxAdapter(child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(children: [
                  // Vehicle section
                  _Section(title: 'Vehicle', icon: Icons.two_wheeler_rounded, children: [
                    _InfoTile(Icons.category_rounded, 'Type', _vehicleLabel(d['vehicle_type'])),
                    _InfoTile(Icons.pin_rounded, 'Plate', d['vehicle_plate'] ?? '—'),
                    _InfoTile(Icons.directions_car_rounded, 'Model', d['vehicle_model'] ?? '—'),
                  ]),
                  const SizedBox(height: 12),

                  // Documents
                  _Section(title: 'Documents', icon: Icons.folder_rounded, children: [
                    _DocTile('National ID', d['documents']),
                    _DocTile('Driver License', d['documents']),
                    _DocTile('Vehicle Registration', d['documents']),
                  ]),
                  const SizedBox(height: 12),

                  // Settings
                  _Section(title: 'Settings', icon: Icons.settings_rounded, children: [
                    _SettingsTile(Icons.notifications_outlined, 'Notifications', onTap: () {}),
                    _SettingsTile(Icons.language_rounded, 'Language', trailing: 'English', onTap: () {}),
                    _ThemeToggleTile(themeMode: themeMode, onToggle: () => ref.read(themeModeProvider.notifier).toggle(context)),
                    _SettingsTile(Icons.privacy_tip_outlined, 'Privacy Policy', onTap: () {}),
                  ]),
                  const SizedBox(height: 20),

                  // Logout
                  GestureDetector(
                    onTap: () => ref.read(logoutProvider)(),
                    child: Container(
                      width: double.infinity, height: 52,
                      decoration: BoxDecoration(border: Border.all(color: DC.error.withValues(alpha: 0.5)), borderRadius: BorderRadius.circular(14)),
                      child: const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                        Icon(Icons.logout_rounded, color: DC.error, size: 20),
                        SizedBox(width: 8),
                        Text('Logout', style: TextStyle(color: DC.error, fontWeight: FontWeight.w700, fontSize: 15)),
                      ]),
                    ),
                  ),
                  const SizedBox(height: 30),
                ]),
              )),
            ]),
          );
        },
      ),
    );
  }

  static String _vehicleLabel(dynamic t) => switch (t?.toString()) {
    'motorcycle' => '🏍️ Motorcycle', 'bajaj' => '🛺 Bajaj', 'car' => '🚗 Car',
    'van' => '🚐 Van', 'truck' => '🚛 Truck', 'bicycle' => '🚲 Bicycle', _ => t?.toString() ?? '—',
  };
}

class _StatCol extends StatelessWidget {
  final String value, label;
  const _StatCol(this.value, this.label);
  @override
  Widget build(BuildContext context) => Column(children: [
    Text(value, style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w800)),
    const SizedBox(height: 2),
    Text(label, style: const TextStyle(color: Color(0xFF8B9CC7), fontSize: 10)),
  ]);
}

class _Section extends StatelessWidget {
  final String title; final IconData icon; final List<Widget> children;
  const _Section({required this.title, required this.icon, required this.children});
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: c.card, borderRadius: BorderRadius.circular(18), border: Border.all(color: c.border.withValues(alpha: 0.3))),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Icon(icon, color: DC.orange, size: 18), const SizedBox(width: 8),
          Text(title, style: TextStyle(color: c.text, fontWeight: FontWeight.w700, fontSize: 15)),
        ]),
        const SizedBox(height: 12),
        ...children,
      ]),
    );
  }
}

class _InfoTile extends StatelessWidget {
  final IconData icon; final String label, value;
  const _InfoTile(this.icon, this.label, this.value);
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(children: [
        Icon(icon, color: c.textMuted, size: 16), const SizedBox(width: 10),
        Text(label, style: TextStyle(color: c.textMuted, fontSize: 13)),
        const Spacer(),
        Text(value, style: TextStyle(color: c.text, fontWeight: FontWeight.w600, fontSize: 13)),
      ]),
    );
  }
}

class _DocTile extends StatelessWidget {
  final String type; final dynamic docs;
  const _DocTile(this.type, this.docs);
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    final docList = docs is List ? docs as List : [];
    final slug = type.toLowerCase().replaceAll(' ', '_');
    final doc = docList.cast<Map<String, dynamic>?>().where((d) => d?['type'] == slug).firstOrNull;
    final status = doc?['status'] ?? 'not_uploaded';
    final color = status == 'approved' ? DC.success : (status == 'pending' ? DC.busy : c.textMuted);
    final label = status == 'approved' ? 'Verified' : (status == 'pending' ? 'Pending' : 'Upload');

    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(children: [
        Icon(Icons.description_outlined, color: c.textMuted, size: 16),
        const SizedBox(width: 10),
        Expanded(child: Text(type, style: TextStyle(color: c.textSec, fontSize: 13))),
        Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
          decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(6)),
          child: Text(label, style: TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w700))),
      ]),
    );
  }
}

class _SettingsTile extends StatelessWidget {
  final IconData icon; final String label; final String? trailing; final VoidCallback onTap;
  const _SettingsTile(this.icon, this.label, {this.trailing, required this.onTap});
  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    return GestureDetector(
      onTap: onTap,
      child: Padding(padding: const EdgeInsets.only(bottom: 12), child: Row(children: [
        Icon(icon, color: c.textMuted, size: 18), const SizedBox(width: 10),
        Expanded(child: Text(label, style: TextStyle(color: c.textSec, fontSize: 13))),
        if (trailing != null) Text(trailing!, style: TextStyle(color: c.textMuted, fontSize: 12)),
        const SizedBox(width: 4),
        Icon(Icons.chevron_right_rounded, color: c.textMuted, size: 18),
      ])),
    );
  }
}

class _ThemeToggleTile extends StatelessWidget {
  final ThemeMode themeMode;
  final VoidCallback onToggle;
  const _ThemeToggleTile({required this.themeMode, required this.onToggle});

  @override
  Widget build(BuildContext context) {
    final c = context.dc;
    final isDark = switch (themeMode) {
      ThemeMode.dark   => true,
      ThemeMode.light  => false,
      ThemeMode.system => Theme.of(context).brightness == Brightness.dark,
    };
    final label = switch (themeMode) {
      ThemeMode.system => 'Auto (System)',
      ThemeMode.dark   => 'Dark',
      ThemeMode.light  => 'Light',
    };
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(children: [
        Icon(isDark ? Icons.dark_mode_rounded : Icons.light_mode_rounded, color: c.textMuted, size: 18),
        const SizedBox(width: 10),
        Expanded(child: Text('Theme', style: TextStyle(color: c.textSec, fontSize: 13))),
        Text(label, style: TextStyle(color: c.textMuted, fontSize: 12)),
        const SizedBox(width: 8),
        GestureDetector(
          onTap: onToggle,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            width: 46, height: 26,
            decoration: BoxDecoration(
              color: isDark ? DC.orange : c.border,
              borderRadius: BorderRadius.circular(13),
            ),
            padding: const EdgeInsets.all(3),
            child: AnimatedAlign(
              duration: const Duration(milliseconds: 250),
              curve: Curves.easeInOut,
              alignment: isDark ? Alignment.centerRight : Alignment.centerLeft,
              child: Container(width: 20, height: 20,
                decoration: BoxDecoration(color: Colors.white, shape: BoxShape.circle,
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.15), blurRadius: 4)])),
            ),
          ),
        ),
      ]),
    );
  }
}
