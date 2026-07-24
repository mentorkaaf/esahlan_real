import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../../core/providers/app_settings_provider.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../features/auth/data/models/user_model.dart';
import '../../../../features/auth/presentation/providers/auth_provider.dart';

// ── Design tokens ─────────────────────────────────────────────────────────────
const _kNavy   = Color(0xFF07003B);
const _kOrange = Color(0xFFFF8A00);
const _kPurple = Color(0xFF6C63FF);

// ── Services quick-access data ────────────────────────────────────────────────
class _Service {
  final IconData icon;
  final String label;
  final String route;
  final Color color;
  const _Service(this.icon, this.label, this.route, this.color);
}

const _kServices = [
  _Service(Icons.receipt_long_rounded,   'My Orders',    '/orders',    Color(0xFF6C63FF)),
  _Service(Icons.account_balance_wallet_rounded, 'Wallet', '/wallet',  Color(0xFF16A34A)),
  _Service(Icons.school_rounded,         'eLearning',    '/elearning', Color(0xFFE63946)),
  _Service(Icons.people_rounded,         'Community',    '/community', Color(0xFF0EA5E9)),
  _Service(Icons.live_tv_rounded,        'Live',         '/live',      Color(0xFFEF4444)),
  _Service(Icons.currency_exchange_rounded, 'eExchange', '/eexchange', Color(0xFFF59E0B)),
  _Service(Icons.restaurant_rounded,     'eFood',        '/efood',     Color(0xFFFF6B35)),
  _Service(Icons.headset_mic_rounded,    'Support',      '/chat',      Color(0xFF8B5CF6)),
];

// ─────────────────────────────────────────────────────────────────────────────
class ProfileScreen extends ConsumerStatefulWidget {
  const ProfileScreen({super.key});

  @override
  ConsumerState<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends ConsumerState<ProfileScreen> {
  bool _uploadingAvatar = false;

  Future<void> _pickAvatar() async {
    final picked = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 80);
    if (picked == null || !mounted) return;
    setState(() => _uploadingAvatar = true);
    try {
      await ref.read(updateProfileProvider)({'avatar': picked.path});
    } catch (_) {} finally {
      if (mounted) setState(() => _uploadingAvatar = false);
    }
  }

  void _editField(String label, String currentValue, String fieldKey, {TextInputType keyboard = TextInputType.text}) {
    final ctrl = TextEditingController(text: currentValue);
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _EditSheet(
        label: label,
        controller: ctrl,
        keyboard: keyboard,
        onSave: () async {
          final v = ctrl.text.trim();
          if (v.isEmpty) return;
          await ref.read(updateProfileProvider)({fieldKey: v});
          if (ctx.mounted) Navigator.pop(ctx);
        },
      ),
    );
  }

  void _showLanguagePicker(AppSettings settings) {
    const langs = [
      ('en', '🇺🇸', 'English'),
      ('so', '🇸🇴', 'Somali'),
      ('ar', '🇸🇦', 'Arabic'),
      ('am', '🇪🇹', 'Amharic'),
      ('sw', '🌍', 'Swahili'),
      ('fr', '🇫🇷', 'French'),
    ];
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _BottomSheet(
        title: 'Language',
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: langs.map((l) {
            final (code, flag, name) = l;
            final selected = settings.language == code;
            return ListTile(
              leading: Text(flag, style: const TextStyle(fontSize: 22)),
              title: Text(name, style: TextStyle(
                fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
                color: selected ? _kNavy : null,
              )),
              trailing: selected ? Icon(Icons.check_circle_rounded, color: _kOrange) : null,
              onTap: () {
                ref.read(appSettingsProvider.notifier).setLanguage(code);
                Navigator.pop(ctx);
              },
            );
          }).toList(),
        ),
      ),
    );
  }

  void _showFontSizePicker(AppSettings settings) {
    const sizes = [
      ('small', 'Small', 0.88),
      ('medium', 'Medium', 1.0),
      ('large', 'Large', 1.15),
      ('xlarge', 'Extra Large', 1.30),
    ];
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _BottomSheet(
        title: 'Font Size',
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: sizes.map((s) {
            final (key, label, scale) = s;
            final selected = settings.fontSize == key;
            return ListTile(
              title: Text(label, style: TextStyle(
                fontSize: 14 * scale,
                fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
                color: selected ? _kNavy : null,
              )),
              trailing: selected ? Icon(Icons.check_circle_rounded, color: _kOrange) : null,
              onTap: () {
                ref.read(appSettingsProvider.notifier).setFontSize(key);
                Navigator.pop(ctx);
              },
            );
          }).toList(),
        ),
      ),
    );
  }

  void _confirmLogout() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: const Row(children: [
          Icon(Icons.logout_rounded, color: Colors.red, size: 22),
          SizedBox(width: 8),
          Text('Sign Out?', style: TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
        ]),
        content: const Text('Are you sure you want to sign out?'),
        actionsPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
        actions: [
          Row(children: [
            Expanded(child: OutlinedButton(
              onPressed: () => Navigator.pop(ctx),
              style: OutlinedButton.styleFrom(
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
              child: const Text('Cancel', style: TextStyle(fontWeight: FontWeight.w700)),
            )),
            const SizedBox(width: 10),
            Expanded(child: ElevatedButton(
              onPressed: () {
                Navigator.pop(ctx);
                WidgetsBinding.instance.addPostFrameCallback((_) async {
                  await ref.read(logoutProvider)();
                });
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.red,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                padding: const EdgeInsets.symmetric(vertical: 12),
              ),
              child: const Text('Sign Out', style: TextStyle(fontWeight: FontWeight.w700)),
            )),
          ]),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final userAsync = ref.watch(authStateProvider);
    final user      = userAsync.valueOrNull;
    final settings  = ref.watch(appSettingsProvider);
    final isDark    = context.isDark;
    final bottomPad = MediaQuery.of(context).padding.bottom + 86;

    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
      body: CustomScrollView(
        slivers: [
          _buildHeader(context, user, isDark),
          SliverToBoxAdapter(
            child: Column(children: [
              const SizedBox(height: 16),
              _buildPointsStrip(context, user, isDark),
              const SizedBox(height: 12),
              if (user?.referralCode != null) ...[
                _buildReferralCard(context, user!),
                const SizedBox(height: 12),
              ],
              _buildAccountSection(context, user, isDark),
              const SizedBox(height: 12),
              _buildPreferencesSection(context, settings, isDark),
              const SizedBox(height: 12),
              _buildSupportSection(context, isDark),
              const SizedBox(height: 12),
              _buildSignOut(context, isDark),
              Padding(
                padding: EdgeInsets.only(top: 20, bottom: bottomPad),
                child: const Text('eSahlan v1.0.0', style: TextStyle(color: AppColors.textLight, fontSize: 12)),
              ),
            ]),
          ),
        ],
      ),
    );
  }

  // ── Header ───────────────────────────────────────────────────────────────────

  Widget _buildHeader(BuildContext context, UserModel? user, bool isDark) {
    return SliverAppBar(
      expandedHeight: 220,
      pinned: true,
      backgroundColor: _kNavy,
      foregroundColor: Colors.white,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      title: const Text('Profile', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 17)),
      flexibleSpace: FlexibleSpaceBar(
        background: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              colors: [_kNavy, Color(0xFF1A0070), Color(0xFF3A1278)],
              stops: [0.0, 0.55, 1.0],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
          ),
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 56, 20, 16),
              child: Row(children: [
                // Avatar
                GestureDetector(
                  onTap: _pickAvatar,
                  child: Stack(
                    children: [
                      Container(
                        width: 76, height: 76,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          gradient: const LinearGradient(
                            colors: [_kOrange, Color(0xFFFF6B00)],
                            begin: Alignment.topLeft, end: Alignment.bottomRight,
                          ),
                          border: Border.all(color: Colors.white, width: 3),
                          boxShadow: [BoxShadow(color: _kOrange.withAlpha(80), blurRadius: 12, offset: const Offset(0, 4))],
                        ),
                        child: _uploadingAvatar
                            ? const Center(child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)))
                            : (user?.avatar != null
                                ? ClipOval(child: Image.network(user!.avatar!, fit: BoxFit.cover, width: 76, height: 76, errorBuilder: (_, __, ___) => _avatarInitials(user)))
                                : _avatarInitials(user)),
                      ),
                      Positioned(
                        bottom: 0, right: 0,
                        child: Container(
                          width: 22, height: 22,
                          decoration: BoxDecoration(color: Colors.white, shape: BoxShape.circle, border: Border.all(color: _kOrange, width: 2)),
                          child: const Icon(Icons.camera_alt_rounded, size: 11, color: _kOrange),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 16),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
                  // Name + edit
                  Row(children: [
                    Flexible(
                      child: Text(user?.name ?? '—',
                          style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900, height: 1.2)),
                    ),
                    const SizedBox(width: 8),
                    GestureDetector(
                      onTap: () => _editField('Full Name', user?.name ?? '', 'name'),
                      child: Container(
                        padding: const EdgeInsets.all(5),
                        decoration: BoxDecoration(color: Colors.white.withAlpha(30), borderRadius: BorderRadius.circular(8)),
                        child: const Icon(Icons.edit_rounded, color: Colors.white70, size: 14),
                      ),
                    ),
                  ]),
                  const SizedBox(height: 4),
                  Text(user?.phone ?? '', style: const TextStyle(color: Colors.white70, fontSize: 13)),
                  if (user?.email != null) ...[
                    const SizedBox(height: 2),
                    Text(user!.email!, style: const TextStyle(color: Colors.white54, fontSize: 12)),
                  ],
                  const SizedBox(height: 8),
                  // Status badge
                  Row(children: [
                    if (user?.roleName != null) ...[
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                        decoration: BoxDecoration(
                          color: Colors.white.withAlpha(25),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: Colors.white.withAlpha(50)),
                        ),
                        child: Row(mainAxisSize: MainAxisSize.min, children: [
                          const Icon(Icons.verified_rounded, color: _kOrange, size: 11),
                          const SizedBox(width: 4),
                          Text(user!.roleName!, style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700)),
                        ]),
                      ),
                      const SizedBox(width: 6),
                    ],
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: const Color(0xFF16A34A).withAlpha(60),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: const Row(mainAxisSize: MainAxisSize.min, children: [
                        Icon(Icons.circle, color: Color(0xFF4ADE80), size: 6),
                        SizedBox(width: 4),
                        Text('Active', style: TextStyle(color: Color(0xFF4ADE80), fontSize: 10, fontWeight: FontWeight.w700)),
                      ]),
                    ),
                  ]),
                ])),
              ]),
            ),
          ),
        ),
      ),
    );
  }

  Widget _avatarInitials(UserModel? user) => Center(
    child: Text(user?.initials ?? 'U',
        style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
  );

  // ── Points strip ─────────────────────────────────────────────────────────────

  Widget _buildPointsStrip(BuildContext context, UserModel? user, bool isDark) {
    final cardBg = isDark ? const Color(0xFF1A1A2E) : Colors.white;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: cardBg,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black.withAlpha(isDark ? 30 : 8), blurRadius: 12, offset: const Offset(0, 3))],
        ),
        child: Row(children: [
          Expanded(child: _StatChip(
            icon: Icons.stars_rounded,
            iconColor: const Color(0xFFF59E0B),
            bgColor: const Color(0xFFFEF3C7),
            label: '${user?.loyaltyPoints ?? 0}',
            sublabel: 'Points',
            isDark: isDark,
          )),
          _kVertDivider,
          Expanded(child: _StatChip(
            icon: Icons.location_city_rounded,
            iconColor: _kPurple,
            bgColor: _kPurple.withAlpha(25),
            label: user?.districtName ?? 'Not set',
            sublabel: 'District',
            isDark: isDark,
          )),
          _kVertDivider,
          Expanded(child: GestureDetector(
            onTap: () => context.push('/orders'),
            child: _StatChip(
              icon: Icons.shopping_bag_rounded,
              iconColor: _kOrange,
              bgColor: _kOrange.withAlpha(25),
              label: 'Orders',
              sublabel: 'View all',
              isDark: isDark,
              tappable: true,
            ),
          )),
        ]),
      ),
    );
  }

  // ── Referral card ─────────────────────────────────────────────────────────────

  Widget _buildReferralCard(BuildContext context, UserModel user) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            colors: [_kOrange, Color(0xFFFF6B00)],
            begin: Alignment.topLeft, end: Alignment.bottomRight,
          ),
          borderRadius: BorderRadius.circular(18),
          boxShadow: [BoxShadow(color: _kOrange.withAlpha(80), blurRadius: 16, offset: const Offset(0, 6))],
        ),
        child: Row(children: [
          Container(
            width: 44, height: 44,
            decoration: BoxDecoration(color: Colors.white.withAlpha(30), borderRadius: BorderRadius.circular(12)),
            child: const Icon(Icons.card_giftcard_rounded, color: Colors.white, size: 24),
          ),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Referral Code', style: TextStyle(color: Colors.white70, fontSize: 11, fontWeight: FontWeight.w600)),
            const SizedBox(height: 2),
            Text(user.referralCode!,
                style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900, letterSpacing: 3)),
            const SizedBox(height: 2),
            const Text('Share to earn rewards', style: TextStyle(color: Colors.white60, fontSize: 10)),
          ])),
          GestureDetector(
            onTap: () {
              Clipboard.setData(ClipboardData(text: user.referralCode!));
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Referral code copied!'), duration: Duration(seconds: 2)),
              );
            },
            child: Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(color: Colors.white.withAlpha(30), borderRadius: BorderRadius.circular(12)),
              child: const Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.copy_rounded, color: Colors.white, size: 18),
                SizedBox(height: 2),
                Text('Copy', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w700)),
              ]),
            ),
          ),
        ]),
      ),
    );
  }

  // ── Services grid ─────────────────────────────────────────────────────────────

  Widget _buildServicesGrid(BuildContext context, bool isDark) {
    final cardBg = isDark ? const Color(0xFF1A1A2E) : Colors.white;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _SectionHeader('My Services'),
        const SizedBox(height: 10),
        GridView.count(
          crossAxisCount: 4,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 0.9,
          children: _kServices.map((s) => GestureDetector(
            onTap: () => context.push(s.route),
            child: Container(
              decoration: BoxDecoration(
                color: cardBg,
                borderRadius: BorderRadius.circular(14),
                boxShadow: [BoxShadow(color: Colors.black.withAlpha(isDark ? 25 : 6), blurRadius: 8, offset: const Offset(0, 2))],
              ),
              child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                Container(
                  width: 42, height: 42,
                  decoration: BoxDecoration(color: s.color.withAlpha(20), borderRadius: BorderRadius.circular(12)),
                  child: Icon(s.icon, color: s.color, size: 22),
                ),
                const SizedBox(height: 6),
                Text(s.label,
                    textAlign: TextAlign.center,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(fontSize: 10, fontWeight: FontWeight.w700,
                        color: isDark ? Colors.white70 : _kNavy)),
              ]),
            ),
          )).toList(),
        ),
      ]),
    );
  }

  // ── Account section ──────────────────────────────────────────────────────────

  Widget _buildAccountSection(BuildContext context, UserModel? user, bool isDark) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: _Card(isDark: isDark, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _SectionHeaderPad('My Account'),
        _EditableRow(
          icon: Icons.person_outline_rounded,
          label: 'Full Name',
          value: user?.name ?? '—',
          isDark: isDark,
          onTap: () => _editField('Full Name', user?.name ?? '', 'name'),
        ),
        _kDivider,
        _EditableRow(
          icon: Icons.phone_outlined,
          label: 'Phone Number',
          value: user?.phone ?? '—',
          isDark: isDark,
          editable: false,
        ),
        _kDivider,
        _EditableRow(
          icon: Icons.email_outlined,
          label: 'Email Address',
          value: user?.email ?? 'Not added',
          isDark: isDark,
          onTap: () => _editField('Email Address', user?.email ?? '', 'email', keyboard: TextInputType.emailAddress),
        ),
        _kDivider,
        _EditableRow(
          icon: Icons.location_on_outlined,
          label: 'District',
          value: user?.districtName ?? 'Not set',
          isDark: isDark,
          editable: false,
        ),
        const SizedBox(height: 4),
      ])),
    );
  }

  // ── Preferences section ──────────────────────────────────────────────────────

  Widget _buildPreferencesSection(BuildContext context, AppSettings settings, bool isDark) {
    final themeLabel = switch (settings.themeMode) {
      ThemeMode.light  => 'Light',
      ThemeMode.dark   => 'Dark',
      _                => 'System',
    };
    final langLabels = {'en': 'English', 'so': 'Somali', 'ar': 'Arabic', 'am': 'Amharic', 'sw': 'Swahili', 'fr': 'French'};
    final fontLabels = {'small': 'Small', 'medium': 'Medium', 'large': 'Large', 'xlarge': 'Extra Large'};

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: _Card(isDark: isDark, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _SectionHeaderPad('Preferences'),

        // Theme
        _PrefRow(
          icon: Icons.palette_outlined,
          label: 'Appearance',
          value: themeLabel,
          isDark: isDark,
          onTap: () => _showThemePicker(settings),
        ),
        _kDivider,

        // Language
        _PrefRow(
          icon: Icons.language_rounded,
          label: 'Language',
          value: langLabels[settings.language] ?? 'English',
          isDark: isDark,
          onTap: () => _showLanguagePicker(settings),
        ),
        _kDivider,

        // Font size
        _PrefRow(
          icon: Icons.text_fields_rounded,
          label: 'Text Size',
          value: fontLabels[settings.fontSize] ?? 'Medium',
          isDark: isDark,
          onTap: () => _showFontSizePicker(settings),
        ),
        _kDivider,

        // Data Saver
        _ToggleRow(
          icon: Icons.data_saver_on_rounded,
          label: 'Data Saver',
          subtitle: 'Reduce media quality to save data',
          value: settings.dataSaverEnabled,
          isDark: isDark,
          onChanged: (v) => ref.read(appSettingsProvider.notifier).setDataSaver(v),
        ),
        const SizedBox(height: 4),
      ])),
    );
  }

  void _showThemePicker(AppSettings settings) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _BottomSheet(
        title: 'Appearance',
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          for (final (key, label, icon) in [
            ('system', 'System Default', Icons.brightness_auto_rounded),
            ('light',  'Light Mode',     Icons.light_mode_rounded),
            ('dark',   'Dark Mode',      Icons.dark_mode_rounded),
          ]) ListTile(
            leading: Container(
              width: 38, height: 38,
              decoration: BoxDecoration(color: _kNavy.withAlpha(12), borderRadius: BorderRadius.circular(10)),
              child: Icon(icon, color: _kNavy, size: 20),
            ),
            title: Text(label, style: TextStyle(
              fontWeight: (settings.themeMode.name == key || (key == 'system' && settings.themeMode == ThemeMode.system)) ? FontWeight.w800 : FontWeight.w500,
            )),
            trailing: (settings.themeMode == (key == 'light' ? ThemeMode.light : key == 'dark' ? ThemeMode.dark : ThemeMode.system))
                ? Icon(Icons.check_circle_rounded, color: _kOrange)
                : null,
            onTap: () {
              ref.read(appSettingsProvider.notifier).setTheme(key);
              Navigator.pop(ctx);
            },
          ),
        ]),
      ),
    );
  }

  void _showVideoAutoplayPicker(AppSettings settings) {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _BottomSheet(
        title: 'Video Autoplay',
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          for (final (key, label, sub) in [
            ('always',    'Always',      'Play videos anywhere'),
            ('wifi_only', 'Wi-Fi Only',  'Save mobile data'),
            ('never',     'Never',       'Manually start videos'),
          ]) ListTile(
            title: Text(label, style: TextStyle(
              fontWeight: settings.videoAutoplay == key ? FontWeight.w800 : FontWeight.w500,
              color: settings.videoAutoplay == key ? _kNavy : null,
            )),
            subtitle: Text(sub, style: const TextStyle(fontSize: 12)),
            trailing: settings.videoAutoplay == key ? Icon(Icons.check_circle_rounded, color: _kOrange) : null,
            onTap: () {
              ref.read(appSettingsProvider.notifier).setVideoAutoplay(key);
              Navigator.pop(ctx);
            },
          ),
        ]),
      ),
    );
  }

  // ── Support & Legal ──────────────────────────────────────────────────────────

  Widget _buildSupportSection(BuildContext context, bool isDark) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: _Card(isDark: isDark, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        _SectionHeaderPad('Support & Legal'),
        _NavRow(icon: Icons.headset_mic_rounded,   iconColor: _kPurple,            label: 'Help & Support',   isDark: isDark, onTap: () => context.push('/chat')),
        _kDivider,
        _NavRow(icon: Icons.privacy_tip_outlined,   iconColor: const Color(0xFF0EA5E9), label: 'Privacy Policy',   isDark: isDark, onTap: () {}),
        _kDivider,
        _NavRow(icon: Icons.description_outlined,   iconColor: const Color(0xFF6C63FF), label: 'Terms of Service', isDark: isDark, onTap: () {}),
        _kDivider,
        _NavRow(icon: Icons.star_rate_rounded,      iconColor: const Color(0xFFF59E0B), label: 'Rate eSahlan',     isDark: isDark, onTap: () {}),
        const SizedBox(height: 4),
      ])),
    );
  }

  // ── Sign out ─────────────────────────────────────────────────────────────────

  Widget _buildSignOut(BuildContext context, bool isDark) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: GestureDetector(
        onTap: _confirmLogout,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 16),
          decoration: BoxDecoration(
            color: isDark ? const Color(0xFF2D0A0A) : const Color(0xFFFFF5F5),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: Colors.red.withAlpha(60)),
          ),
          child: Row(children: [
            Container(
              width: 40, height: 40,
              decoration: BoxDecoration(color: Colors.red.withAlpha(20), borderRadius: BorderRadius.circular(11)),
              child: const Icon(Icons.logout_rounded, color: Colors.red, size: 20),
            ),
            const SizedBox(width: 14),
            const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Sign Out', style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800, color: Colors.red)),
              Text('You can always log back in', style: TextStyle(fontSize: 11, color: Colors.redAccent)),
            ])),
            const Icon(Icons.chevron_right_rounded, color: Colors.red, size: 20),
          ]),
        ),
      ),
    );
  }
}

// ── Shared Widgets ────────────────────────────────────────────────────────────

const _kDivider = Divider(height: 1, indent: 54, color: Color(0xFFF0F0F8));
const Widget _kVertDivider = SizedBox(
  height: 40,
  child: VerticalDivider(width: 1, color: Color(0xFFE8ECF4)),
);

class _SectionHeader extends StatelessWidget {
  final String text;
  const _SectionHeader(this.text);
  @override
  Widget build(BuildContext context) => Text(
    text.toUpperCase(),
    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800, color: AppColors.textGrey, letterSpacing: 0.8),
  );
}

class _SectionHeaderPad extends StatelessWidget {
  final String text;
  const _SectionHeaderPad(this.text);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
    child: Text(
      text.toUpperCase(),
      style: const TextStyle(fontSize: 10, fontWeight: FontWeight.w800, color: AppColors.textGrey, letterSpacing: 0.8),
    ),
  );
}

class _Card extends StatelessWidget {
  final bool isDark;
  final Widget child;
  const _Card({required this.isDark, required this.child});
  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: isDark ? const Color(0xFF1A1A2E) : Colors.white,
      borderRadius: BorderRadius.circular(18),
      boxShadow: [BoxShadow(color: Colors.black.withAlpha(isDark ? 30 : 8), blurRadius: 12, offset: const Offset(0, 3))],
    ),
    child: child,
  );
}

class _StatChip extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final Color bgColor;
  final String label;
  final String sublabel;
  final bool isDark;
  final bool tappable;
  const _StatChip({required this.icon, required this.iconColor, required this.bgColor,
      required this.label, required this.sublabel, required this.isDark, this.tappable = false});

  @override
  Widget build(BuildContext context) => Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Container(
          width: 38, height: 38,
          decoration: BoxDecoration(color: bgColor, borderRadius: BorderRadius.circular(10)),
          child: Icon(icon, color: iconColor, size: 20),
        ),
        const SizedBox(height: 6),
        Text(label, maxLines: 1, overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800,
                color: isDark ? Colors.white : _kNavy)),
        Row(mainAxisAlignment: MainAxisAlignment.center, mainAxisSize: MainAxisSize.min, children: [
          Text(sublabel, style: const TextStyle(fontSize: 10, color: AppColors.textGrey)),
          if (tappable) const Icon(Icons.arrow_forward_ios_rounded, size: 8, color: AppColors.textGrey),
        ]),
      ]),
  );
}

class _EditableRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final bool isDark;
  final VoidCallback? onTap;
  final bool editable;
  const _EditableRow({required this.icon, required this.label, required this.value,
      required this.isDark, this.onTap, this.editable = true});

  @override
  Widget build(BuildContext context) => ListTile(
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
    leading: Container(
      width: 38, height: 38,
      decoration: BoxDecoration(color: _kOrange.withAlpha(20), borderRadius: BorderRadius.circular(10)),
      child: Icon(icon, color: _kOrange, size: 20),
    ),
    title: Text(label, style: const TextStyle(fontSize: 11, color: AppColors.textGrey, fontWeight: FontWeight.w600)),
    subtitle: Text(value, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700,
        color: isDark ? Colors.white : _kNavy)),
    trailing: editable && onTap != null
        ? const Icon(Icons.edit_rounded, color: AppColors.textLight, size: 16)
        : editable ? null : const Icon(Icons.lock_outline_rounded, color: AppColors.textLight, size: 14),
    onTap: onTap,
  );
}

class _PrefRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final bool isDark;
  final VoidCallback onTap;
  const _PrefRow({required this.icon, required this.label, required this.value,
      required this.isDark, required this.onTap});

  @override
  Widget build(BuildContext context) => ListTile(
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
    leading: Container(
      width: 38, height: 38,
      decoration: BoxDecoration(color: _kNavy.withAlpha(12), borderRadius: BorderRadius.circular(10)),
      child: Icon(icon, color: _kNavy, size: 20),
    ),
    title: Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: isDark ? Colors.white : _kNavy)),
    trailing: Row(mainAxisSize: MainAxisSize.min, children: [
      Text(value, style: const TextStyle(fontSize: 13, color: AppColors.textGrey, fontWeight: FontWeight.w500)),
      const SizedBox(width: 4),
      const Icon(Icons.chevron_right_rounded, color: AppColors.textLight, size: 18),
    ]),
    onTap: onTap,
  );
}

class _ToggleRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String subtitle;
  final bool value;
  final bool isDark;
  final ValueChanged<bool> onChanged;
  const _ToggleRow({required this.icon, required this.label, required this.subtitle,
      required this.value, required this.isDark, required this.onChanged});

  @override
  Widget build(BuildContext context) => ListTile(
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
    leading: Container(
      width: 38, height: 38,
      decoration: BoxDecoration(color: _kNavy.withAlpha(12), borderRadius: BorderRadius.circular(10)),
      child: Icon(icon, color: _kNavy, size: 20),
    ),
    title: Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: isDark ? Colors.white : _kNavy)),
    subtitle: Text(subtitle, style: const TextStyle(fontSize: 11, color: AppColors.textGrey)),
    trailing: Switch.adaptive(
      value: value,
      onChanged: onChanged,
      activeThumbColor: _kOrange,
      activeTrackColor: _kOrange.withAlpha(100),
    ),
  );
}

class _NavRow extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String label;
  final bool isDark;
  final VoidCallback onTap;
  const _NavRow({required this.icon, required this.iconColor, required this.label,
      required this.isDark, required this.onTap});

  @override
  Widget build(BuildContext context) => ListTile(
    contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 2),
    leading: Container(
      width: 38, height: 38,
      decoration: BoxDecoration(color: iconColor.withAlpha(18), borderRadius: BorderRadius.circular(10)),
      child: Icon(icon, color: iconColor, size: 20),
    ),
    title: Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: isDark ? Colors.white : _kNavy)),
    trailing: const Icon(Icons.chevron_right_rounded, color: AppColors.textLight, size: 20),
    onTap: onTap,
  );
}

// ── Edit bottom sheet ─────────────────────────────────────────────────────────

class _EditSheet extends StatelessWidget {
  final String label;
  final TextEditingController controller;
  final TextInputType keyboard;
  final Future<void> Function() onSave;
  const _EditSheet({required this.label, required this.controller, required this.keyboard, required this.onSave});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
      child: _BottomSheet(
        title: 'Edit $label',
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            TextField(
              controller: controller,
              keyboardType: keyboard,
              autofocus: true,
              decoration: InputDecoration(
                labelText: label,
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: _kOrange, width: 2),
                ),
              ),
            ),
            const SizedBox(height: 16),
            Row(children: [
              Expanded(child: OutlinedButton(
                onPressed: () => Navigator.pop(context),
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                child: const Text('Cancel', style: TextStyle(fontWeight: FontWeight.w700)),
              )),
              const SizedBox(width: 10),
              Expanded(child: ElevatedButton(
                onPressed: onSave,
                style: ElevatedButton.styleFrom(
                  backgroundColor: _kOrange,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  elevation: 0,
                ),
                child: const Text('Save', style: TextStyle(fontWeight: FontWeight.w800)),
              )),
            ]),
          ]),
        ),
      ),
    );
  }
}

class _BottomSheet extends StatelessWidget {
  final String title;
  final Widget child;
  const _BottomSheet({required this.title, required this.child});

  @override
  Widget build(BuildContext context) {
    final isDark = context.isDark;
    return Container(
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1A1A2E) : Colors.white,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        const SizedBox(height: 10),
        Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey.withAlpha(80), borderRadius: BorderRadius.circular(2))),
        const SizedBox(height: 14),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Text(title, style: TextStyle(fontSize: 17, fontWeight: FontWeight.w900, color: isDark ? Colors.white : _kNavy)),
        ),
        const SizedBox(height: 8),
        child,
        SizedBox(height: MediaQuery.of(context).padding.bottom + 8),
      ]),
    );
  }
}
