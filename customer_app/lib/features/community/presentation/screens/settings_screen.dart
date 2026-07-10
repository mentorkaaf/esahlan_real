import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/theme_x.dart';
import '../../data/repositories/community_repository.dart';
import 'transparency_center_screen.dart';

const _kOrange = Color(0xFFFF8A00);

// â”€â”€ Providers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
final settingsProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  return CommunityRepository().getSettings();
});

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// MAIN SETTINGS SCREEN
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});

  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  final _search = TextEditingController();
  String _query = '';

  static const _sections = [
    _SectionMeta('account',          'Account',           Icons.manage_accounts_rounded,    Color(0xFF3B82F6), 'Manage your personal information'),
    _SectionMeta('privacy',          'Privacy',           Icons.lock_outline_rounded,        Color(0xFF8B5CF6), 'Control who can see and contact you'),
    _SectionMeta('notifications',    'Notifications',     Icons.notifications_outlined,      Color(0xFFF59E0B), 'Manage your notification preferences'),
    _SectionMeta('security',         'Security',          Icons.shield_outlined,             Color(0xFF10B981), 'Secure your account and devices'),
    _SectionMeta('content',          'Content Preferences',Icons.tune_rounded,               Color(0xFFEC4899), 'Customize your content experience'),
    _SectionMeta('language',         'Language',          Icons.language_rounded,            Color(0xFF06B6D4), 'Choose your preferred language'),
    _SectionMeta('appearance',       'Appearance',        Icons.palette_outlined,            Color(0xFF8B5CF6), 'Customize app appearance'),
    _SectionMeta('data_saver',       'Data Saver',        Icons.data_saver_on_outlined,     Color(0xFF10B981), 'Save data and control media quality'),
    _SectionMeta('video',            'Video Settings',    Icons.play_circle_outline_rounded, Color(0xFFEF4444), 'Manage video playback preferences'),
    _SectionMeta('audio',            'Audio',             Icons.headphones_rounded,          Color(0xFF8B5CF6), 'Audio playback preferences'),
    _SectionMeta('messages',         'Messages',          Icons.chat_bubble_outline_rounded, Color(0xFF3B82F6), 'Manage your messaging preferences'),
    _SectionMeta('storage',          'Storage',           Icons.storage_rounded,             Color(0xFF6B7280), 'Manage cache and downloads'),
    _SectionMeta('wallet',           'Wallet',            Icons.account_balance_wallet_outlined, Color(0xFFFF8A00), 'Manage your wallet and transactions'),
    _SectionMeta('creator_studio',   'Creator Studio',    Icons.auto_awesome_rounded,        Color(0xFFF59E0B), 'Analytics, earnings and creator tools'),
    _SectionMeta('business',         'Business Center',   Icons.business_center_outlined,    Color(0xFF3B82F6), 'Manage your business and shop'),
    _SectionMeta('safety',           'Community Safety',  Icons.security_rounded,            Color(0xFF10B981), 'Hidden words, content filters'),
    _SectionMeta('ai',               'AI Features',       Icons.auto_fix_high_rounded,       Color(0xFF8B5CF6), 'AI-powered experience settings'),
    _SectionMeta('accessibility',    'Accessibility',     Icons.accessibility_new_rounded,   Color(0xFF06B6D4), 'Large text, captions, voice'),
    _SectionMeta('help',             'Help & Support',    Icons.help_outline_rounded,        Color(0xFF6B7280), 'Get help and contact support'),
    _SectionMeta('about',            'About',             Icons.info_outline_rounded,        Color(0xFF6B7280), 'Terms, privacy policy and more'),
  ];

  void _openSection(String key) {
    final widget = _screenFor(key);
    if (widget != null) {
      Navigator.push(context, MaterialPageRoute(builder: (_) => widget));
    }
  }

  Widget? _screenFor(String key) {
    final settingsAsync = ref.read(settingsProvider);
    final settings = settingsAsync.valueOrNull ?? {};
    return switch (key) {
      'privacy'       => PrivacySettingsScreen(initial: Map<String,dynamic>.from(settings['privacy'] ?? {})),
      'notifications' => NotificationSettingsScreen(initial: Map<String,dynamic>.from(settings['notifications'] ?? {})),
      'security'      => const SecuritySettingsScreen(),
      'data_saver'    => DataSaverSettingsScreen(initial: Map<String,dynamic>.from(settings['data_saver'] ?? {})),
      'appearance'    => AppearanceSettingsScreen(initial: Map<String,dynamic>.from(settings['appearance'] ?? {})),
      'video'         => VideoSettingsScreen(initial: Map<String,dynamic>.from(settings['video'] ?? {})),
      'messages'      => MessageSettingsScreen(initial: Map<String,dynamic>.from(settings['messages'] ?? {})),
      'wallet'        => const WalletSettingsScreen(),
      'creator_studio'=> const CreatorStudioSettingsScreen(),
      'accessibility' => AccessibilitySettingsScreen(initial: Map<String,dynamic>.from(settings['accessibility'] ?? {})),
      'ai'            => AiSettingsScreen(initial: Map<String,dynamic>.from(settings['ai_features'] ?? {})),
      'safety'        => const CommunitySafetySettingsScreen(),
      'help'          => const HelpSettingsScreen(),
      'about'         => const AboutSettingsScreen(),
      'account'       => const AccountSettingsScreen(),
      _               => null,
    };
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final filtered = _query.isEmpty
        ? _sections
        : _sections.where((s) =>
            s.label.toLowerCase().contains(_query) ||
            s.subtitle.toLowerCase().contains(_query)).toList();

    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: AppBar(
        title: const Text('Settings', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18)),
        centerTitle: true,
        elevation: 0,
        backgroundColor: c.cardBg,
        surfaceTintColor: Colors.transparent,
        leading: BackButton(color: c.bodyText),
      ),
      body: Column(children: [
        // Search bar
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: TextField(
            controller: _search,
            onChanged: (v) => setState(() => _query = v.toLowerCase()),
            decoration: InputDecoration(
              hintText: 'Search settings...',
              prefixIcon: Icon(Icons.search_rounded, color: c.subtleText, size: 20),
              suffixIcon: _query.isNotEmpty
                  ? IconButton(icon: Icon(Icons.close_rounded, size: 18, color: c.subtleText), onPressed: () { _search.clear(); setState(() => _query = ''); })
                  : null,
              filled: true,
              fillColor: c.inputFill,
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: BorderSide.none),
              contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              hintStyle: TextStyle(color: c.subtleText, fontSize: 14),
            ),
            style: TextStyle(color: c.bodyText, fontSize: 14),
          ),
        ),
        Expanded(
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
            itemCount: filtered.length,
            separatorBuilder: (_, __) => const SizedBox(height: 8),
            itemBuilder: (_, i) {
              final s = filtered[i];
              return _SettingsTile(
                meta: s,
                onTap: () => _openSection(s.key),
              );
            },
          ),
        ),
      ]),
    );
  }
}

class _SectionMeta {
  final String key, label, subtitle;
  final IconData icon;
  final Color color;
  const _SectionMeta(this.key, this.label, this.icon, this.color, this.subtitle);
}

class _SettingsTile extends StatelessWidget {
  final _SectionMeta meta;
  final VoidCallback onTap;
  const _SettingsTile({required this.meta, required this.onTap});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Material(
      color: c.cardBg,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(children: [
            Container(
              width: 44, height: 44,
              decoration: BoxDecoration(
                color: meta.color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(meta.icon, color: meta.color, size: 22),
            ),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(meta.label, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: c.bodyText)),
              const SizedBox(height: 2),
              Text(meta.subtitle, style: TextStyle(fontSize: 11, color: c.subtleText)),
            ])),
            Icon(Icons.chevron_right_rounded, color: c.subtleText, size: 20),
          ]),
        ),
      ),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// SHARED WIDGETS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class _SettingsGroup extends StatelessWidget {
  final String? title;
  final List<Widget> children;
  const _SettingsGroup({this.title, required this.children});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      if (title != null) ...[
        Padding(padding: const EdgeInsets.fromLTRB(4, 16, 4, 8),
          child: Text(title!, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: c.subtleText, letterSpacing: .6))),
      ],
      Material(
        color: c.cardBg,
        borderRadius: BorderRadius.circular(16),
        child: Column(children: [
          for (int i = 0; i < children.length; i++) ...[
            children[i],
            if (i < children.length - 1)
              Divider(height: 1, indent: 56, color: c.dividerColor),
          ],
        ]),
      ),
    ]);
  }
}

class _ToggleTile extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String label;
  final String? subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;
  const _ToggleTile({required this.icon, required this.iconColor, required this.label, this.subtitle, required this.value, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return ListTile(
      leading: Container(width: 36, height: 36, decoration: BoxDecoration(color: iconColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
        child: Icon(icon, color: iconColor, size: 18)),
      title: Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: c.bodyText)),
      subtitle: subtitle != null ? Text(subtitle!, style: TextStyle(fontSize: 11, color: c.subtleText)) : null,
      trailing: Switch.adaptive(value: value, onChanged: onChanged, activeThumbColor: _kOrange, activeTrackColor: _kOrange.withValues(alpha: 0.4)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
    );
  }
}

class _NavTile extends StatelessWidget {
  final IconData icon;
  final Color iconColor;
  final String label;
  final String? trailing;
  final VoidCallback? onTap;
  final Widget? trailingWidget;
  const _NavTile({required this.icon, required this.iconColor, required this.label, this.trailing, this.onTap, this.trailingWidget});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return ListTile(
      leading: Container(width: 36, height: 36, decoration: BoxDecoration(color: iconColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
        child: Icon(icon, color: iconColor, size: 18)),
      title: Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: c.bodyText)),
      trailing: trailingWidget ?? Row(mainAxisSize: MainAxisSize.min, children: [
        if (trailing != null) Text(trailing!, style: TextStyle(fontSize: 12, color: c.subtleText)),
        const SizedBox(width: 4),
        Icon(Icons.chevron_right_rounded, color: c.subtleText, size: 18),
      ]),
      onTap: onTap,
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
    );
  }
}

Widget _settingsAppBar(BuildContext context, String title) => AppBar(
  title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18)),
  centerTitle: true, elevation: 0,
  backgroundColor: context.colors.cardBg, surfaceTintColor: Colors.transparent,
  leading: BackButton(color: context.colors.bodyText),
);

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ACCOUNT SETTINGS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class AccountSettingsScreen extends StatelessWidget {
  const AccountSettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Account') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(title: 'PROFILE', children: [
          _NavTile(icon: Icons.person_outline_rounded, iconColor: const Color(0xFF3B82F6), label: 'Edit Profile', onTap: () {}),
          _NavTile(icon: Icons.alternate_email_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Username', onTap: () {}),
        ]),
        _SettingsGroup(title: 'CONTACT', children: [
          _NavTile(icon: Icons.email_outlined, iconColor: const Color(0xFF10B981), label: 'Email Address', onTap: () {}),
          _NavTile(icon: Icons.phone_outlined, iconColor: const Color(0xFF10B981), label: 'Phone Number', onTap: () {}),
        ]),
        _SettingsGroup(title: 'SECURITY', children: [
          _NavTile(icon: Icons.lock_outline_rounded, iconColor: const Color(0xFFF59E0B), label: 'Change Password', onTap: () {}),
          _NavTile(icon: Icons.face_outlined, iconColor: const Color(0xFF6B7280), label: 'Face ID / Biometrics', onTap: () {}),
          _NavTile(icon: Icons.fingerprint_rounded, iconColor: const Color(0xFF6B7280), label: 'Fingerprint', onTap: () {}),
        ]),
        _SettingsGroup(title: 'DATA', children: [
          _NavTile(icon: Icons.download_outlined, iconColor: const Color(0xFF3B82F6), label: 'Download My Data', onTap: () {}),
        ]),
        const SizedBox(height: 16),
        Material(
          color: const Color(0xFFEF4444).withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(16),
          child: ListTile(
            leading: Container(width: 36, height: 36, decoration: BoxDecoration(color: const Color(0xFFEF4444).withValues(alpha: 0.15), borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.delete_outline_rounded, color: Color(0xFFEF4444), size: 18)),
            title: const Text('Delete Account', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFFEF4444))),
            trailing: const Icon(Icons.chevron_right_rounded, color: Color(0xFFEF4444), size: 18),
            onTap: () {},
          ),
        ),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// PRIVACY SETTINGS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class PrivacySettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const PrivacySettingsScreen({super.key, required this.initial});

  @override
  ConsumerState<PrivacySettingsScreen> createState() => _PrivacySettingsState();
}

class _PrivacySettingsState extends ConsumerState<PrivacySettingsScreen> {
  late Map<String, dynamic> _s;

  @override
  void initState() {
    super.initState();
    _s = Map.from(widget.initial);
  }

  Future<void> _save(String key, dynamic val) async {
    setState(() => _s[key] = val);
    try {
      await CommunityRepository().updateSettings('privacy', {key: val});
    } catch (_) {}
  }

  bool _bool(String k, [bool def = false]) => _s[k] as bool? ?? def;
  String _str(String k, [String def = 'everyone']) => _s[k] as String? ?? def;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Privacy') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(title: 'ACCOUNT PRIVACY', children: [
          _ToggleTile(icon: Icons.lock_outline_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Private Account',
            subtitle: 'Only approved followers can see your content and profile.',
            value: _bool('private_account'), onChanged: (v) => _save('private_account', v)),
        ]),
        _SettingsGroup(title: 'WHO CAN...', children: [
          _WhoCanTile(label: 'Follow me',         value: _str('who_can_follow'),   onChanged: (v) => _save('who_can_follow', v)),
          _WhoCanTile(label: 'Message me',        value: _str('who_can_message'), onChanged: (v) => _save('who_can_message', v)),
          _WhoCanTile(label: 'Comment on my posts', value: _str('who_can_comment'), onChanged: (v) => _save('who_can_comment', v)),
          _WhoCanTile(label: 'Mention me',        value: _str('who_can_mention'),  onChanged: (v) => _save('who_can_mention', v)),
          _WhoCanTile(label: 'Tag me',            value: _str('who_can_tag'),      onChanged: (v) => _save('who_can_tag', v),    opts: ['followers', 'nobody']),
          _WhoCanTile(label: 'Remix my content',  value: _str('who_can_remix'),    onChanged: (v) => _save('who_can_remix', v)),
        ]),
        _SettingsGroup(title: 'VISIBILITY', children: [
          _ToggleTile(icon: Icons.visibility_off_rounded, iconColor: const Color(0xFF6B7280), label: 'Hide Online Status', value: _bool('hide_online_status', true), onChanged: (v) => _save('hide_online_status', v)),
          _ToggleTile(icon: Icons.people_outline_rounded, iconColor: const Color(0xFF6B7280), label: 'Hide Followers',     value: _bool('hide_followers'),          onChanged: (v) => _save('hide_followers', v)),
          _ToggleTile(icon: Icons.person_outline_rounded, iconColor: const Color(0xFF6B7280), label: 'Hide Following',     value: _bool('hide_following'),          onChanged: (v) => _save('hide_following', v)),
          _ToggleTile(icon: Icons.favorite_border_rounded,iconColor: const Color(0xFF6B7280), label: 'Hide Likes',         value: _bool('hide_likes'),              onChanged: (v) => _save('hide_likes', v)),
        ]),
        _SettingsGroup(title: 'ADVANCED', children: [
          _NavTile(icon: Icons.block_rounded, iconColor: const Color(0xFFEF4444), label: 'Blocked Users', trailing: '12', onTap: () {}),
          _NavTile(icon: Icons.volume_off_rounded, iconColor: const Color(0xFFF59E0B), label: 'Muted Users', trailing: '25', onTap: () {}),
          _NavTile(icon: Icons.person_off_outlined, iconColor: const Color(0xFF8B5CF6), label: 'Restricted Users', trailing: '8', onTap: () {}),
        ]),
      ]),
    );
  }
}

class _WhoCanTile extends StatelessWidget {
  final String label;
  final String value;
  final ValueChanged<String> onChanged;
  final List<String> opts;
  const _WhoCanTile({required this.label, required this.value, required this.onChanged, this.opts = const ['everyone', 'followers', 'nobody']});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      title: Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: c.bodyText)),
      trailing: GestureDetector(
        onTap: () => showModalBottomSheet(context: context, backgroundColor: c.cardBg, shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))), builder: (_) => _WhoCanSheet(label: label, current: value, opts: opts, onSelect: onChanged)),
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          Text(_label(value), style: TextStyle(fontSize: 12, color: c.subtleText)),
          Icon(Icons.chevron_right_rounded, color: c.subtleText, size: 18),
        ]),
      ),
    );
  }

  String _label(String v) => switch(v) { 'everyone' => 'Everyone', 'followers' => 'Followers', 'nobody' => 'Nobody', _ => v };
}

class _WhoCanSheet extends StatelessWidget {
  final String label, current;
  final List<String> opts;
  final ValueChanged<String> onSelect;
  const _WhoCanSheet({required this.label, required this.current, required this.opts, required this.onSelect});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(context).padding.bottom + 20),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: c.bodyText)),
        const SizedBox(height: 16),
        ...opts.map((o) => ListTile(
          title: Text(_label(o), style: TextStyle(color: c.bodyText, fontWeight: FontWeight.w500)),
          trailing: o == current ? Icon(Icons.check_circle_rounded, color: _kOrange) : null,
          onTap: () { Navigator.pop(context); onSelect(o); },
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        )),
      ]),
    );
  }
  String _label(String v) => switch(v) { 'everyone' => 'Everyone', 'followers' => 'Followers', 'nobody' => 'Nobody', _ => v };
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// NOTIFICATIONS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class NotificationSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const NotificationSettingsScreen({super.key, required this.initial});

  @override
  ConsumerState<NotificationSettingsScreen> createState() => _NotifState();
}

class _NotifState extends ConsumerState<NotificationSettingsScreen> {
  late Map<String, dynamic> _s;

  @override
  void initState() { super.initState(); _s = Map.from(widget.initial); }

  Future<void> _save(String k, bool v) async {
    setState(() => _s[k] = v);
    await CommunityRepository().updateSettings('notifications', {k: v});
  }

  bool _b(String k, [bool d = true]) => _s[k] as bool? ?? d;

  static const _items = [
    (Icons.favorite_border_rounded,     Color(0xFFEF4444), 'Likes',            'likes',           true),
    (Icons.chat_bubble_outline_rounded, Color(0xFF3B82F6), 'Comments',         'comments',        true),
    (Icons.reply_rounded,               Color(0xFF8B5CF6), 'Replies',          'replies',         true),
    (Icons.alternate_email_rounded,     Color(0xFFF59E0B), 'Mentions',         'mentions',        true),
    (Icons.mail_outline_rounded,        Color(0xFF10B981), 'Messages',         'messages',        true),
    (Icons.person_add_outlined,         Color(0xFF06B6D4), 'Follows',          'follows',         true),
    (Icons.upload_outlined,             Color(0xFF8B5CF6), 'Creator Uploads',  'creator_uploads', true),
    (Icons.shopping_bag_outlined,       Color(0xFFFF8A00), 'Business Orders',  'business_orders', true),
    (Icons.live_tv_rounded,             Color(0xFFEF4444), 'Live Notifications','live_streams',   true),
    (Icons.campaign_outlined,           Color(0xFF6B7280), 'Promotions',       'promotions',      false),
    (Icons.email_outlined,              Color(0xFF3B82F6), 'Email Notifications','email',         true),
    (Icons.sms_outlined,                Color(0xFF6B7280), 'SMS Notifications', 'sms',            false),
  ];

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Notifications') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(children: _items.map((item) =>
          _ToggleTile(icon: item.$1, iconColor: item.$2, label: item.$3, value: _b(item.$4, item.$5), onChanged: (v) => _save(item.$4, v))
        ).toList()),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// SECURITY
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class SecuritySettingsScreen extends StatelessWidget {
  const SecuritySettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Security') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(color: const Color(0xFF10B981).withValues(alpha: 0.08), borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFF10B981).withValues(alpha: 0.2))),
          child: Row(children: [
            Container(width: 56, height: 56, decoration: BoxDecoration(color: const Color(0xFF10B981).withValues(alpha: 0.15), shape: BoxShape.circle),
              child: const Icon(Icons.verified_user_rounded, color: Color(0xFF10B981), size: 28)),
            const SizedBox(width: 16),
            const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Your account is secure', style: TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF10B981), fontSize: 15)),
              SizedBox(height: 4),
              Text('Last login: Today, 09:30 AM\nMogadishu, Somalia', style: TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
            ])),
          ]),
        ),
        _SettingsGroup(title: 'AUTHENTICATION', children: [
          _ToggleTile(icon: Icons.security_rounded, iconColor: const Color(0xFF10B981), label: 'Two Factor Authentication', subtitle: 'Enabled', value: true, onChanged: (_) {}),
          _NavTile(icon: Icons.history_rounded, iconColor: const Color(0xFF3B82F6), label: 'Login Activity', onTap: () {}),
        ]),
        _SettingsGroup(title: 'DEVICES', children: [
          _NavTile(icon: Icons.devices_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Active Devices', trailing: '3', onTap: () {}),
          _NavTile(icon: Icons.verified_outlined, iconColor: const Color(0xFF10B981), label: 'Trusted Devices', trailing: '2', onTap: () {}),
        ]),
        _SettingsGroup(title: 'ACCOUNT', children: [
          _NavTile(icon: Icons.vpn_key_outlined, iconColor: const Color(0xFFF59E0B), label: 'Recovery Codes', onTap: () {}),
          _ToggleTile(icon: Icons.notifications_active_outlined, iconColor: const Color(0xFF06B6D4), label: 'Security Alerts', value: true, onChanged: (_) {}),
        ]),
        const SizedBox(height: 16),
        Material(
          color: const Color(0xFFEF4444).withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(16),
          child: ListTile(
            leading: Container(width: 36, height: 36, decoration: BoxDecoration(color: const Color(0xFFEF4444).withValues(alpha: 0.15), borderRadius: BorderRadius.circular(10)),
              child: const Icon(Icons.logout_rounded, color: Color(0xFFEF4444), size: 18)),
            title: const Text('Logout All Devices', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFFEF4444))),
            onTap: () {},
          ),
        ),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// DATA SAVER
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class DataSaverSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const DataSaverSettingsScreen({super.key, required this.initial});

  @override
  ConsumerState<DataSaverSettingsScreen> createState() => _DataSaverState();
}

class _DataSaverState extends ConsumerState<DataSaverSettingsScreen> {
  late Map<String, dynamic> _s;

  @override
  void initState() { super.initState(); _s = Map.from(widget.initial); }

  Future<void> _save(String k, dynamic v) async {
    setState(() => _s[k] = v);
    await CommunityRepository().updateSettings('data_saver', {k: v});
  }

  bool _b(String k, [bool d = false]) => _s[k] as bool? ?? d;
  String _str(String k, [String d = '']) => _s[k] as String? ?? d;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Data Saver') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(children: [
          _ToggleTile(icon: Icons.data_saver_on_outlined, iconColor: const Color(0xFF10B981), label: 'Data Saver', subtitle: 'Reduce data usage across the app.', value: _b('enabled'), onChanged: (v) => _save('enabled', v)),
        ]),
        _SettingsGroup(title: 'IMAGE QUALITY', children: [
          _RadioGroup(
            options: const ['High', 'Medium', 'Low'],
            values: const ['high', 'medium', 'low'],
            current: _str('image_quality', 'high'),
            onChanged: (v) => _save('image_quality', v),
          ),
        ]),
        _SettingsGroup(title: 'VIDEO QUALITY', children: [
          _RadioGroup(
            options: const ['Auto', '144p', '240p', '360p', '480p', '720p', '1080p'],
            values: const ['auto', '144p', '240p', '360p', '480p', '720p', '1080p'],
            current: _str('video_quality', 'auto'),
            onChanged: (v) => _save('video_quality', v),
          ),
        ]),
        _SettingsGroup(title: 'DOWNLOADS', children: [
          _ToggleTile(icon: Icons.play_disabled_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Disable Auto Play', value: _b('disable_autoplay'), onChanged: (v) => _save('disable_autoplay', v)),
          _ToggleTile(icon: Icons.wifi_outlined, iconColor: const Color(0xFF3B82F6), label: 'Wi-Fi Only Downloads', value: _b('wifi_only_download'), onChanged: (v) => _save('wifi_only_download', v)),
          _ToggleTile(icon: Icons.download_outlined, iconColor: const Color(0xFF10B981), label: 'Preload Only on Wi-Fi', value: _b('preload_wifi_only'), onChanged: (v) => _save('preload_wifi_only', v)),
        ]),
      ]),
    );
  }
}

class _RadioGroup extends StatelessWidget {
  final List<String> options, values;
  final String current;
  final ValueChanged<String> onChanged;
  const _RadioGroup({required this.options, required this.values, required this.current, required this.onChanged});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Wrap(spacing: 8, runSpacing: 8, children: [
        for (int i = 0; i < options.length; i++)
          GestureDetector(
            onTap: () => onChanged(values[i]),
            child: AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              decoration: BoxDecoration(
                color: current == values[i] ? _kOrange : c.inputFill,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: current == values[i] ? _kOrange : c.dividerColor),
              ),
              child: Text(options[i], style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: current == values[i] ? Colors.white : c.bodyText)),
            ),
          ),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// APPEARANCE
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class AppearanceSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const AppearanceSettingsScreen({super.key, required this.initial});

  @override
  ConsumerState<AppearanceSettingsScreen> createState() => _AppearanceState();
}

class _AppearanceState extends ConsumerState<AppearanceSettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, dynamic v) async { setState(() => _s[k] = v); await CommunityRepository().updateSettings('appearance', {k: v}); }
  String _str(String k, [String d = '']) => _s[k] as String? ?? d;
  bool _b(String k) => _s[k] as bool? ?? false;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Appearance') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(title: 'THEME', children: [
          _RadioGroup(options: const ['Light', 'Dark', 'System'], values: const ['light', 'dark', 'system'], current: _str('theme', 'system'), onChanged: (v) => _save('theme', v)),
        ]),
        _SettingsGroup(title: 'FONT SIZE', children: [
          _RadioGroup(options: const ['Small', 'Medium', 'Large', 'X-Large'], values: const ['small', 'medium', 'large', 'xlarge'], current: _str('font_size', 'medium'), onChanged: (v) => _save('font_size', v)),
        ]),
        _SettingsGroup(title: 'MOTION', children: [
          _ToggleTile(icon: Icons.animation_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Reduce Motion', subtitle: 'Minimize animations throughout the app', value: _b('reduce_motion'), onChanged: (v) => _save('reduce_motion', v)),
        ]),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// VIDEO SETTINGS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class VideoSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const VideoSettingsScreen({super.key, required this.initial});
  @override ConsumerState<VideoSettingsScreen> createState() => _VideoSettingsState();
}

class _VideoSettingsState extends ConsumerState<VideoSettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, dynamic v) async { setState(() => _s[k] = v); await CommunityRepository().updateSettings('video', {k: v}); }
  String _str(String k, [String d = '']) => _s[k] as String? ?? d;
  bool _b(String k, [bool d = false]) => _s[k] as bool? ?? d;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Video Settings') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(title: 'AUTO PLAY', children: [
          _RadioGroup(options: const ['Always', 'Wi-Fi Only', 'Never'], values: const ['always', 'wifi_only', 'never'], current: _str('autoplay', 'wifi_only'), onChanged: (v) => _save('autoplay', v)),
        ]),
        _SettingsGroup(title: 'PREFERRED RESOLUTION', children: [
          _RadioGroup(options: const ['Auto', '480p', '720p', '1080p'], values: const ['auto', '480p', '720p', '1080p'], current: _str('resolution', 'auto'), onChanged: (v) => _save('resolution', v)),
        ]),
        _SettingsGroup(title: 'OTHER', children: [
          _ToggleTile(icon: Icons.picture_in_picture_rounded, iconColor: const Color(0xFF3B82F6), label: 'Picture in Picture', value: _b('pip_enabled', true), onChanged: (v) => _save('pip_enabled', v)),
          _ToggleTile(icon: Icons.loop_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Loop Videos', value: _b('loop'), onChanged: (v) => _save('loop', v)),
        ]),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// MESSAGES SETTINGS
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class MessageSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const MessageSettingsScreen({super.key, required this.initial});
  @override ConsumerState<MessageSettingsScreen> createState() => _MsgSettingsState();
}

class _MsgSettingsState extends ConsumerState<MessageSettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, dynamic v) async { setState(() => _s[k] = v); await CommunityRepository().updateSettings('messages', {k: v}); }
  bool _b(String k, [bool d = true]) => _s[k] as bool? ?? d;
  String _str(String k, [String d = '']) => _s[k] as String? ?? d;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Messages') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(children: [
          _ToggleTile(icon: Icons.done_all_rounded, iconColor: const Color(0xFF3B82F6), label: 'Read Receipts', subtitle: 'Show when you have read messages', value: _b('read_receipts'), onChanged: (v) => _save('read_receipts', v)),
          _ToggleTile(icon: Icons.more_horiz_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Typing Indicator', subtitle: 'Show when you are typing', value: _b('typing_indicator'), onChanged: (v) => _save('typing_indicator', v)),
          _ToggleTile(icon: Icons.mark_email_unread_outlined, iconColor: const Color(0xFFF59E0B), label: 'Message Requests', subtitle: 'Allow messages from non-followers', value: _b('message_requests'), onChanged: (v) => _save('message_requests', v)),
        ]),
        _SettingsGroup(title: 'WHO CAN MESSAGE ME', children: [
          _RadioGroup(options: const ['Everyone', 'Followers', 'Nobody'], values: const ['everyone', 'followers', 'nobody'], current: _str('who_can_message', 'everyone'), onChanged: (v) => _save('who_can_message', v)),
        ]),
        _SettingsGroup(title: 'DISAPPEARING MESSAGES', children: [
          _RadioGroup(options: const ['Off', '24 Hours', '7 Days', '30 Days'], values: const ['off', '24h', '7d', '30d'], current: _str('disappearing', 'off'), onChanged: (v) => _save('disappearing', v)),
        ]),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// WALLET (read-only display, real wallet is separate)
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class WalletSettingsScreen extends StatelessWidget {
  const WalletSettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Wallet') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFF0F1F3D), Color(0xFF1E3A6E)], begin: Alignment.topLeft, end: Alignment.bottomRight), borderRadius: BorderRadius.circular(20)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Total Balance', style: TextStyle(color: Colors.white60, fontSize: 13)),
            const SizedBox(height: 6),
            const Text('\$245.60', style: TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w800)),
            const Text('USD', style: TextStyle(color: Colors.white60, fontSize: 12)),
            const SizedBox(height: 20),
            Row(children: [
              _WalletBtn(label: 'Deposit', icon: Icons.add_rounded),
              const SizedBox(width: 8),
              _WalletBtn(label: 'Withdraw', icon: Icons.arrow_upward_rounded, outlined: true),
              const SizedBox(width: 8),
              _WalletBtn(label: 'History', icon: Icons.history_rounded, outlined: true),
            ]),
          ]),
        ),
        _SettingsGroup(title: 'QUICK ACCESS', children: [
          _NavTile(icon: Icons.credit_card_rounded, iconColor: const Color(0xFF3B82F6), label: 'Cards', onTap: () {}),
          _NavTile(icon: Icons.account_balance_outlined, iconColor: const Color(0xFF10B981), label: 'Bank Accounts', onTap: () {}),
          _NavTile(icon: Icons.receipt_long_outlined, iconColor: const Color(0xFFF59E0B), label: 'Transactions', onTap: () {}),
          _NavTile(icon: Icons.payment_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Payment Methods', onTap: () {}),
        ]),
      ]),
    );
  }
}

class _WalletBtn extends StatelessWidget {
  final String label; final IconData icon; final bool outlined;
  const _WalletBtn({required this.label, required this.icon, this.outlined = false});
  @override
  Widget build(BuildContext context) => Expanded(
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(
        color: outlined ? Colors.transparent : _kOrange,
        border: outlined ? Border.all(color: Colors.white38) : null,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, color: Colors.white, size: 18),
        const SizedBox(height: 4),
        Text(label, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600)),
      ]),
    ),
  );
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// CREATOR STUDIO
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class CreatorStudioSettingsScreen extends StatelessWidget {
  const CreatorStudioSettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Creator Studio') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(color: c.cardBg, borderRadius: BorderRadius.circular(20), border: Border.all(color: c.dividerColor)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('This Month Overview', style: TextStyle(fontWeight: FontWeight.w700, color: c.bodyText)),
              Text('View all analytics â€º', style: TextStyle(fontSize: 12, color: _kOrange)),
            ]),
            const SizedBox(height: 16),
            Row(children: [
              _StatChip(label: 'Views',       value: '2.1M'),
              _StatChip(label: 'Followers',   value: '24.3K'),
              _StatChip(label: 'Engagement',  value: '12.5K'),
            ]),
            const SizedBox(height: 16),
            _MiniBarChart(),
          ]),
        ),
        _SettingsGroup(title: 'TOOLS', children: [
          _NavTile(icon: Icons.dashboard_outlined, iconColor: const Color(0xFF3B82F6), label: 'Dashboard', onTap: () {}),
          _NavTile(icon: Icons.analytics_outlined, iconColor: const Color(0xFF8B5CF6), label: 'Analytics', onTap: () {}),
          _NavTile(icon: Icons.attach_money_rounded, iconColor: const Color(0xFF10B981), label: 'Earnings', onTap: () {}),
          _NavTile(icon: Icons.monetization_on_outlined, iconColor: const Color(0xFFF59E0B), label: 'Monetization', onTap: () {}),
          _NavTile(icon: Icons.group_outlined, iconColor: const Color(0xFF06B6D4), label: 'Subscribers', onTap: () {}),
          _NavTile(icon: Icons.build_outlined, iconColor: const Color(0xFF6B7280), label: 'Creator Tools', onTap: () {}),
          _NavTile(icon: Icons.school_outlined, iconColor: const Color(0xFF8B5CF6), label: 'Creator Academy', onTap: () {}),
          _NavTile(icon: Icons.verified_outlined, iconColor: _kOrange, label: 'Verification', trailingWidget: Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: _kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8), border: Border.all(color: _kOrange)), child: const Text('Verified', style: TextStyle(color: _kOrange, fontSize: 11, fontWeight: FontWeight.w700)))),
        ]),
      ]),
    );
  }
}

class _StatChip extends StatelessWidget {
  final String label, value;
  const _StatChip({required this.label, required this.value});
  @override
  Widget build(BuildContext context) => Expanded(child: Column(children: [
    Text(value, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: _kOrange)),
    Text(label, style: TextStyle(fontSize: 11, color: context.colors.subtleText)),
  ]));
}

class _MiniBarChart extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    const vals = [0.4, 0.6, 0.5, 0.8, 0.7, 0.9, 0.65];
    return SizedBox(height: 48, child: Row(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: vals.map((v) => Expanded(
        child: Padding(padding: const EdgeInsets.symmetric(horizontal: 2),
          child: Container(height: 48 * v, decoration: BoxDecoration(color: _kOrange.withValues(alpha: 0.7 + v * 0.3), borderRadius: BorderRadius.circular(4)))),
      )).toList(),
    ));
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ACCESSIBILITY
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class AccessibilitySettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const AccessibilitySettingsScreen({super.key, required this.initial});
  @override ConsumerState<AccessibilitySettingsScreen> createState() => _AccessibilityState();
}

class _AccessibilityState extends ConsumerState<AccessibilitySettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, bool v) async { setState(() => _s[k] = v); await CommunityRepository().updateSettings('accessibility', {k: v}); }
  bool _b(String k) => _s[k] as bool? ?? false;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Accessibility') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(children: [
          _ToggleTile(icon: Icons.text_increase_rounded, iconColor: const Color(0xFF06B6D4), label: 'Large Text', value: _b('large_text'), onChanged: (v) => _save('large_text', v)),
          _ToggleTile(icon: Icons.record_voice_over_outlined, iconColor: const Color(0xFF8B5CF6), label: 'Screen Reader', value: _b('screen_reader'), onChanged: (v) => _save('screen_reader', v)),
          _ToggleTile(icon: Icons.closed_caption_outlined, iconColor: const Color(0xFF3B82F6), label: 'Captions', value: _b('captions'), onChanged: (v) => _save('captions', v)),
          _ToggleTile(icon: Icons.contrast_rounded, iconColor: const Color(0xFF10B981), label: 'High Contrast', value: _b('high_contrast'), onChanged: (v) => _save('high_contrast', v)),
          _ToggleTile(icon: Icons.mic_outlined, iconColor: const Color(0xFFF59E0B), label: 'Voice Commands', value: _b('voice_commands'), onChanged: (v) => _save('voice_commands', v)),
        ]),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// AI FEATURES
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class AiSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const AiSettingsScreen({super.key, required this.initial});
  @override ConsumerState<AiSettingsScreen> createState() => _AiSettingsState();
}

class _AiSettingsState extends ConsumerState<AiSettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, bool v) async { setState(() => _s[k] = v); await CommunityRepository().updateSettings('ai_features', {k: v}); }
  bool _b(String k) => _s[k] as bool? ?? true;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'AI Features') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(children: [
          _ToggleTile(icon: Icons.recommend_outlined, iconColor: const Color(0xFF8B5CF6), label: 'AI Recommendations', subtitle: 'Personalized content based on your interests', value: _b('recommendations'), onChanged: (v) => _save('recommendations', v)),
          _ToggleTile(icon: Icons.translate_rounded, iconColor: const Color(0xFF3B82F6), label: 'AI Translation', subtitle: 'Auto-translate posts to your language', value: _b('translation'), onChanged: (v) => _save('translation', v)),
          _ToggleTile(icon: Icons.closed_caption_off_outlined, iconColor: const Color(0xFF10B981), label: 'AI Captions', subtitle: 'Auto-generate captions for videos', value: _b('captions'), onChanged: (v) => _save('captions', v)),
          _ToggleTile(icon: Icons.smart_toy_outlined, iconColor: const Color(0xFFF59E0B), label: 'AI Assistant', subtitle: 'Get help with writing and creating content', value: _b('assistant'), onChanged: (v) => _save('assistant', v)),
          _ToggleTile(icon: Icons.summarize_outlined, iconColor: const Color(0xFF06B6D4), label: 'AI Summary', subtitle: 'Summarize long posts and articles', value: _b('summary'), onChanged: (v) => _save('summary', v)),
        ]),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// COMMUNITY SAFETY
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class CommunitySafetySettingsScreen extends StatelessWidget {
  const CommunitySafetySettingsScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Community Safety') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(children: [
          _NavTile(icon: Icons.filter_alt_outlined, iconColor: const Color(0xFF10B981), label: 'Hidden Words', onTap: () {}),
          _NavTile(icon: Icons.comment_outlined, iconColor: const Color(0xFF3B82F6), label: 'Comment Filter', onTap: () {}),
          _NavTile(icon: Icons.warning_amber_rounded, iconColor: const Color(0xFFF59E0B), label: 'Sensitive Content', trailing: 'Standard', onTap: () {}),
          _NavTile(icon: Icons.history_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Report History', onTap: () {}),
          _NavTile(icon: Icons.security_rounded, iconColor: const Color(0xFF10B981), label: 'Safety Center', onTap: () { Navigator.push(context, MaterialPageRoute(builder: (_) => const TransparencyCenter())); }),
          _NavTile(icon: Icons.family_restroom_rounded, iconColor: const Color(0xFF06B6D4), label: 'Parental Controls', onTap: () {}),
        ]),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// HELP & SUPPORT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class HelpSettingsScreen extends StatelessWidget {
  const HelpSettingsScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'Help & Support') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(children: [
          _NavTile(icon: Icons.help_outline_rounded, iconColor: const Color(0xFF3B82F6), label: 'Help Center', onTap: () {}),
          _NavTile(icon: Icons.support_agent_rounded, iconColor: const Color(0xFF10B981), label: 'Contact Support', onTap: () {}),
          _NavTile(icon: Icons.chat_outlined, iconColor: const Color(0xFFF59E0B), label: 'Live Chat', onTap: () {}),
          _NavTile(icon: Icons.bug_report_outlined, iconColor: const Color(0xFFEF4444), label: 'Report a Bug', onTap: () {}),
          _NavTile(icon: Icons.lightbulb_outline_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Feature Request', onTap: () {}),
          _NavTile(icon: Icons.rate_review_outlined, iconColor: const Color(0xFF06B6D4), label: 'Send Feedback', onTap: () {}),
        ]),
      ]),
    );
  }
}

// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
// ABOUT
// â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
class AboutSettingsScreen extends StatelessWidget {
  const AboutSettingsScreen({super.key});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _settingsAppBar(context, 'About') as PreferredSizeWidget,
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _SettingsGroup(children: [
          _NavTile(icon: Icons.menu_book_outlined, iconColor: const Color(0xFF3B82F6), label: 'Community Guidelines', onTap: () {}),
          _NavTile(icon: Icons.privacy_tip_outlined, iconColor: const Color(0xFF8B5CF6), label: 'Privacy Policy', onTap: () {}),
          _NavTile(icon: Icons.gavel_rounded, iconColor: const Color(0xFF10B981), label: 'Terms of Service', onTap: () {}),
          _NavTile(icon: Icons.article_outlined, iconColor: const Color(0xFF6B7280), label: 'Licenses', onTap: () {}),
        ]),
        _SettingsGroup(title: 'APP INFO', children: [
          _NavTile(icon: Icons.info_outline_rounded, iconColor: _kOrange, label: 'App Version', trailing: '2.4.1', trailingWidget: const Text('2.4.1 (241)', style: TextStyle(fontSize: 12, color: Color(0xFF6B7280)))),
          _NavTile(icon: Icons.build_circle_outlined, iconColor: const Color(0xFF6B7280), label: 'Build Number', trailing: '241'),
          _NavTile(icon: Icons.cloud_done_outlined, iconColor: const Color(0xFF10B981), label: 'System Status', trailing: 'All systems operational', onTap: () {}),
        ]),
      ]),
    );
  }
}


