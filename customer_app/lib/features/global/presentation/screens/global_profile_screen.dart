import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../providers/global_provider.dart';
import '../../data/models/global_models.dart';
import '../../../../features/auth/presentation/screens/country_selection_screen.dart';

class GlobalProfileScreen extends ConsumerWidget {
  const GlobalProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final authAsync = ref.watch(globalAuthProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      body: authAsync.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (_, __) => _GuestView(),
        data: (user) => user == null ? _GuestView() : _ProfileView(user: user),
      ),
    );
  }
}

// ── Logged-in profile ─────────────────────────────────────────────────────────

class _ProfileView extends ConsumerWidget {
  final GlobalUser user;
  const _ProfileView({required this.user});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return CustomScrollView(
      slivers: [
        // ── Header ──────────────────────────────────────────────────────────
        SliverToBoxAdapter(
          child: Container(
            decoration: const BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [Color(0xFF1A1A2E), Color(0xFF16213E)],
              ),
            ),
            padding: EdgeInsets.fromLTRB(
                20, MediaQuery.of(context).padding.top + 20, 20, 30),
            child: Row(
              children: [
                // Avatar
                Container(
                  width: 70,
                  height: 70,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: const Color(0xFFF59E0B), width: 2.5),
                  ),
                  child: user.avatar != null
                      ? ClipOval(
                          child: Image.network(user.avatar!,
                              fit: BoxFit.cover,
                              errorBuilder: (_, __, ___) =>
                                  _AvatarInitials(user.name)),
                        )
                      : _AvatarInitials(user.name),
                ),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(user.name,
                          style: const TextStyle(
                              color: Colors.white,
                              fontSize: 19,
                              fontWeight: FontWeight.w800)),
                      const SizedBox(height: 3),
                      Text(user.email,
                          style: TextStyle(
                              color: Colors.white.withValues(alpha: 0.6),
                              fontSize: 13)),
                      if (user.country != null) ...[
                        const SizedBox(height: 4),
                        Row(children: [
                          const Icon(Icons.location_on,
                              size: 12, color: Color(0xFFF59E0B)),
                          const SizedBox(width: 3),
                          Text(user.country!,
                              style: const TextStyle(
                                  color: Color(0xFFF59E0B),
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600)),
                        ]),
                      ],
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),

        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // ── Quick stats ──────────────────────────────────────────────
                _StatsRow(user: user),
                const SizedBox(height: 20),

                // ── Orders section ───────────────────────────────────────────
                _SectionTitle('My Orders'),
                _MenuCard(items: [
                  _MenuItem(
                    icon: Icons.receipt_long_outlined,
                    iconBg: const Color(0xFFEDE9FE),
                    iconColor: const Color(0xFF5B21B6),
                    label: 'All Orders',
                    sub: 'View your order history',
                    onTap: () => context.push('/global/orders'),
                  ),
                  _MenuItem(
                    icon: Icons.local_shipping_outlined,
                    iconBg: const Color(0xFFECFDF5),
                    iconColor: const Color(0xFF065F46),
                    label: 'Track Shipment',
                    sub: 'Real-time tracking',
                    onTap: () => context.push('/global/orders'),
                  ),
                ]),
                const SizedBox(height: 16),

                // ── Addresses ────────────────────────────────────────────────
                _SectionTitle('Addresses'),
                _MenuCard(items: [
                  _MenuItem(
                    icon: Icons.location_on_outlined,
                    iconBg: const Color(0xFFFFF7ED),
                    iconColor: const Color(0xFFB45309),
                    label: 'Saved Addresses',
                    sub: user.addresses.isEmpty
                        ? 'No addresses saved'
                        : '${user.addresses.length} address${user.addresses.length > 1 ? 'es' : ''} saved',
                    onTap: () => _showAddresses(context, user.addresses),
                  ),
                ]),
                const SizedBox(height: 16),

                // ── Settings ─────────────────────────────────────────────────
                _SectionTitle('Settings'),
                _MenuCard(items: [
                  _MenuItem(
                    icon: Icons.language_outlined,
                    iconBg: const Color(0xFFEFF6FF),
                    iconColor: const Color(0xFF1D4ED8),
                    label: 'Change Region',
                    sub: 'Switch between Global & Somalia',
                    onTap: () async {
                      await saveCountrySelection(null);
                      if (context.mounted) context.go('/country-select');
                    },
                  ),
                  _MenuItem(
                    icon: Icons.notifications_outlined,
                    iconBg: const Color(0xFFFFF1F2),
                    iconColor: const Color(0xFFBE123C),
                    label: 'Notifications',
                    sub: 'Manage push notifications',
                    onTap: () {},
                  ),
                  _MenuItem(
                    icon: Icons.help_outline,
                    iconBg: const Color(0xFFF0FDF4),
                    iconColor: const Color(0xFF166534),
                    label: 'Help & Support',
                    sub: 'FAQs, contact us',
                    onTap: () {},
                  ),
                ]),
                const SizedBox(height: 16),

                // ── Sign out ─────────────────────────────────────────────────
                _SignOutButton(onTap: () async {
                  await ref.read(globalAuthProvider.notifier).logout();
                  if (context.mounted) context.go('/global');
                }),

                const SizedBox(height: 90),
              ],
            ),
          ),
        ),
      ],
    );
  }

  void _showAddresses(BuildContext context, List<GlobalAddress> addresses) {
    if (addresses.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('No addresses saved yet.')),
      );
      return;
    }
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(
          borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (_) => Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Saved Addresses',
                style:
                    TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
            const SizedBox(height: 14),
            ...addresses.map((a) => Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: a.isDefault
                        ? const Color(0xFFF0F4FF)
                        : const Color(0xFFF8F9FA),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                        color: a.isDefault
                            ? const Color(0xFF6366F1)
                            : Colors.grey.shade200),
                  ),
                  child: Row(children: [
                    Icon(Icons.location_on,
                        size: 18,
                        color: a.isDefault
                            ? const Color(0xFF6366F1)
                            : Colors.grey.shade400),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                        Text(a.name,
                            style: const TextStyle(
                                fontWeight: FontWeight.w700, fontSize: 13)),
                        Text(a.fullAddress,
                            style: TextStyle(
                                color: Colors.grey.shade600,
                                fontSize: 11)),
                      ]),
                    ),
                    if (a.isDefault)
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 8, vertical: 3),
                        decoration: BoxDecoration(
                          color: const Color(0xFF6366F1),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Text('Default',
                            style: TextStyle(
                                color: Colors.white,
                                fontSize: 10,
                                fontWeight: FontWeight.w700)),
                      ),
                  ]),
                )),
          ],
        ),
      ),
    );
  }
}

// ── Guest view ────────────────────────────────────────────────────────────────

class _GuestView extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 90,
              height: 90,
              decoration: BoxDecoration(
                color: const Color(0xFFEDE9FE),
                borderRadius: BorderRadius.circular(24),
              ),
              child: const Icon(Icons.person_outline,
                  size: 48, color: Color(0xFF5B21B6)),
            ),
            const SizedBox(height: 20),
            const Text('Sign in to your account',
                style:
                    TextStyle(fontSize: 19, fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            Text('View orders, addresses and manage your global shopping preferences.',
                textAlign: TextAlign.center,
                style:
                    TextStyle(color: Colors.grey.shade600, fontSize: 13)),
            const SizedBox(height: 28),
            SizedBox(
              width: double.infinity,
              height: 50,
              child: ElevatedButton(
                onPressed: () => context.push('/global/auth'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF1A1A2E),
                  foregroundColor: Colors.white,
                  shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Sign In',
                    style: TextStyle(
                        fontWeight: FontWeight.w800, fontSize: 15)),
              ),
            ),
            const SizedBox(height: 12),
            TextButton(
              onPressed: () =>
                  context.push('/global/auth?mode=register'),
              child: const Text('Create an Account',
                  style: TextStyle(
                      color: Color(0xFFF59E0B),
                      fontWeight: FontWeight.w700)),
            ),
          ],
        ),
      ),
    );
  }
}

// ── Sub-widgets ───────────────────────────────────────────────────────────────

class _AvatarInitials extends StatelessWidget {
  final String name;
  const _AvatarInitials(this.name);

  @override
  Widget build(BuildContext context) {
    final initials = name.isNotEmpty
        ? name.trim().split(' ').map((w) => w[0]).take(2).join().toUpperCase()
        : '?';
    return Container(
      decoration: const BoxDecoration(
        color: Color(0xFFF59E0B),
        shape: BoxShape.circle,
      ),
      child: Center(
        child: Text(initials,
            style: const TextStyle(
                color: Color(0xFF1A1A2E),
                fontSize: 22,
                fontWeight: FontWeight.w900)),
      ),
    );
  }
}

class _StatsRow extends StatelessWidget {
  final GlobalUser user;
  const _StatsRow({required this.user});

  @override
  Widget build(BuildContext context) {
    return Row(children: [
      _StatCard(label: 'Orders', value: '—', icon: Icons.receipt_long),
      const SizedBox(width: 10),
      _StatCard(label: 'Addresses', value: '${user.addresses.length}', icon: Icons.location_on),
      const SizedBox(width: 10),
      _StatCard(label: 'Region', value: 'Global 🌍', icon: Icons.language),
    ]);
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;
  final IconData icon;
  const _StatCard({required this.label, required this.value, required this.icon});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
                color: Colors.black.withValues(alpha: 0.04),
                blurRadius: 8,
                offset: const Offset(0, 2))
          ],
        ),
        child: Column(children: [
          Icon(icon, size: 20, color: const Color(0xFFF59E0B)),
          const SizedBox(height: 6),
          Text(value,
              style: const TextStyle(
                  fontWeight: FontWeight.w800, fontSize: 16)),
          Text(label,
              style: TextStyle(
                  color: Colors.grey.shade500,
                  fontSize: 10,
                  fontWeight: FontWeight.w500)),
        ]),
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  final String title;
  const _SectionTitle(this.title);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 2, bottom: 8),
      child: Text(title,
          style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Colors.grey.shade500,
              letterSpacing: 0.5)),
    );
  }
}

class _MenuCard extends StatelessWidget {
  final List<_MenuItem> items;
  const _MenuCard({required this.items});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 2))
        ],
      ),
      child: Column(
        children: List.generate(items.length, (i) {
          final item = items[i];
          return Column(
            children: [
              InkWell(
                onTap: item.onTap,
                borderRadius: BorderRadius.vertical(
                  top: i == 0 ? const Radius.circular(14) : Radius.zero,
                  bottom: i == items.length - 1
                      ? const Radius.circular(14)
                      : Radius.zero,
                ),
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                      horizontal: 16, vertical: 14),
                  child: Row(children: [
                    Container(
                      width: 38,
                      height: 38,
                      decoration: BoxDecoration(
                        color: item.iconBg,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(item.icon,
                          size: 19, color: item.iconColor),
                    ),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                        Text(item.label,
                            style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 14)),
                        Text(item.sub,
                            style: TextStyle(
                                color: Colors.grey.shade500,
                                fontSize: 11)),
                      ]),
                    ),
                    Icon(Icons.chevron_right,
                        color: Colors.grey.shade300, size: 20),
                  ]),
                ),
              ),
              if (i < items.length - 1)
                Divider(
                    height: 1,
                    color: Colors.grey.shade100,
                    indent: 68),
            ],
          );
        }),
      ),
    );
  }
}

class _MenuItem {
  final IconData icon;
  final Color iconBg;
  final Color iconColor;
  final String label;
  final String sub;
  final VoidCallback onTap;

  const _MenuItem({
    required this.icon,
    required this.iconBg,
    required this.iconColor,
    required this.label,
    required this.sub,
    required this.onTap,
  });
}

class _SignOutButton extends StatelessWidget {
  final VoidCallback onTap;
  const _SignOutButton({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: Colors.red.shade50,
          borderRadius: BorderRadius.circular(14),
          border:
              Border.all(color: Colors.red.shade100),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.logout, color: Colors.red.shade600, size: 18),
            const SizedBox(width: 8),
            Text('Sign Out',
                style: TextStyle(
                    color: Colors.red.shade600,
                    fontWeight: FontWeight.w700,
                    fontSize: 14)),
          ],
        ),
      ),
    );
  }
}
