import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/services/auth_service.dart';
import '../../../core/theme/vc.dart';

const _kTeal = Color(0xFF0EA5E9);

class AgentProfileScreen extends ConsumerStatefulWidget {
  const AgentProfileScreen({super.key});
  @override
  ConsumerState<AgentProfileScreen> createState() => _AgentProfileScreenState();
}

class _AgentProfileScreenState extends ConsumerState<AgentProfileScreen> {
  Map<String, dynamic>? _user;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final u = await AuthService.instance.getUser();
    if (mounted) setState(() => _user = u);
  }

  Future<void> _logout() async {
    final ok = await showDialog<bool>(context: context, builder: (_) => AlertDialog(
      title: const Text('Logout'),
      content: const Text('Are you sure you want to logout?'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
        TextButton(
          style: TextButton.styleFrom(foregroundColor: VC.red),
          onPressed: () => Navigator.pop(context, true),
          child: const Text('Logout'),
        ),
      ],
    ));
    if (ok == true) {
      await AuthService.instance.logout();
      if (mounted) Navigator.of(context).pushNamedAndRemoveUntil('/', (_) => false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg   = isDark ? VC.navy     : VC.lightBg;
    final card = isDark ? VC.navyCard : VC.lightSurface;
    final txt  = isDark ? VC.text     : const Color(0xFF1A2340);
    final sec  = isDark ? VC.textSec  : const Color(0xFF5A6B82);
    final themeMode = ref.watch(themeModeProvider);

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(backgroundColor: isDark ? VC.navyLight : VC.lightSurface, title: const Text('Profile')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          // Avatar card
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: [const Color(0xFF0369A1), _kTeal],
                begin: Alignment.topLeft, end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(children: [
              Container(
                width: 60, height: 60,
                decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), shape: BoxShape.circle),
                child: const Icon(Icons.real_estate_agent_rounded, color: Colors.white, size: 32),
              ),
              const SizedBox(width: 16),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(_user?['name'] ?? 'Agent', style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900)),
                Text(_user?['email'] ?? _user?['phone'] ?? '', style: const TextStyle(color: Colors.white70, fontSize: 13)),
                const SizedBox(height: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                  decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.2), borderRadius: BorderRadius.circular(20)),
                  child: const Text('Rent Agent', style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                ),
              ])),
            ]),
          ),

          const SizedBox(height: 20),

          // Settings
          _SectionCard(card: card, children: [
            _SettingTile(
              icon: Icons.dark_mode_rounded,
              label: 'Dark Mode',
              color: _kTeal,
              txt: txt, sec: sec,
              trailing: Switch(
                value: themeMode == ThemeMode.dark,
                onChanged: (_) => ref.read(themeModeProvider.notifier).toggle(),
                activeColor: _kTeal,
              ),
            ),
          ]),

          const SizedBox(height: 12),

          _SectionCard(card: card, children: [
            _SettingTile(icon: Icons.help_outline_rounded, label: 'Help & Support', color: VC.blue, txt: txt, sec: sec),
            _SettingTile(icon: Icons.privacy_tip_outlined, label: 'Privacy Policy', color: VC.purple, txt: txt, sec: sec),
          ]),

          const SizedBox(height: 12),

          _SectionCard(card: card, children: [
            _SettingTile(
              icon: Icons.logout_rounded,
              label: 'Logout',
              color: VC.red,
              txt: VC.red, sec: sec,
              onTap: _logout,
            ),
          ]),

          const SizedBox(height: 24),
          Center(child: Text('eSahlan Agent v1.0', style: TextStyle(color: sec, fontSize: 12))),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final Color card;
  final List<Widget> children;
  const _SectionCard({required this.card, required this.children});
  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: card,
      borderRadius: BorderRadius.circular(16),
      border: Border.all(color: Colors.grey.withValues(alpha: 0.08)),
    ),
    child: Column(children: children),
  );
}

class _SettingTile extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color, txt, sec;
  final Widget? trailing;
  final VoidCallback? onTap;
  const _SettingTile({required this.icon, required this.label, required this.color,
    required this.txt, required this.sec, this.trailing, this.onTap});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      onTap: onTap,
      leading: Container(
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(10)),
        child: Icon(icon, color: color, size: 18),
      ),
      title: Text(label, style: TextStyle(color: txt, fontWeight: FontWeight.w600, fontSize: 14)),
      trailing: trailing ?? (onTap != null ? Icon(Icons.chevron_right_rounded, color: sec, size: 18) : null),
    );
  }
}
