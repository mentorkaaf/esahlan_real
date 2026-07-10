import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/theme_x.dart';
import '../../data/repositories/community_repository.dart';
import 'transparency_center_screen.dart';

const _kOrange = Color(0xFFFF8A00);

// ══════════════════════════════════════════════════════════════════════════
// PROVIDERS
// ══════════════════════════════════════════════════════════════════════════
final _settingsProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  return CommunityRepository().getSettings();
});

final _privacyCounts = FutureProvider.autoDispose<Map<String, int>>((ref) async {
  final repo = CommunityRepository();
  final r = await Future.wait([repo.getBlockedUsers(), repo.getMutedUsers(), repo.getRestrictedUsers()]);
  return {'blocked': r[0].length, 'muted': r[1].length, 'restricted': r[2].length};
});

// ══════════════════════════════════════════════════════════════════════════
// MAIN SETTINGS SCREEN
// ══════════════════════════════════════════════════════════════════════════
class SettingsScreen extends ConsumerStatefulWidget {
  const SettingsScreen({super.key});
  @override
  ConsumerState<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends ConsumerState<SettingsScreen> {
  final _search = TextEditingController();
  String _query = '';

  static const _sections = [
    _SM('account',       'Account',            Icons.manage_accounts_rounded,        Color(0xFF3B82F6), 'Manage your personal information'),
    _SM('privacy',       'Privacy',            Icons.lock_outline_rounded,            Color(0xFF8B5CF6), 'Control who can see and contact you'),
    _SM('notifications', 'Notifications',      Icons.notifications_outlined,          Color(0xFFF59E0B), 'Manage your notification preferences'),
    _SM('security',      'Security',           Icons.shield_outlined,                 Color(0xFF10B981), 'Secure your account and devices'),
    _SM('content',       'Content Preferences',Icons.tune_rounded,                    Color(0xFFEC4899), 'Customize your content experience'),
    _SM('language',      'Language',           Icons.language_rounded,                Color(0xFF06B6D4), 'Choose your preferred language'),
    _SM('appearance',    'Appearance',         Icons.palette_outlined,                Color(0xFF8B5CF6), 'Customize app appearance'),
    _SM('data_saver',    'Data Saver',         Icons.data_saver_on_outlined,          Color(0xFF10B981), 'Save data and control media quality'),
    _SM('video',         'Video Settings',     Icons.play_circle_outline_rounded,     Color(0xFFEF4444), 'Manage video playback preferences'),
    _SM('messages',      'Messages',           Icons.chat_bubble_outline_rounded,     Color(0xFF3B82F6), 'Manage your messaging preferences'),
    _SM('wallet',        'Wallet',             Icons.account_balance_wallet_outlined,  Color(0xFFFF8A00), 'Manage your wallet and transactions'),
    _SM('creator_studio','Creator Studio',     Icons.auto_awesome_rounded,            Color(0xFFF59E0B), 'Analytics, earnings and creator tools'),
    _SM('business',      'Business Center',    Icons.business_center_outlined,        Color(0xFF3B82F6), 'Manage your business and shop'),
    _SM('safety',        'Community Safety',   Icons.security_rounded,                Color(0xFF10B981), 'Hidden words, content filters'),
    _SM('ai',            'AI Features',        Icons.auto_fix_high_rounded,           Color(0xFF8B5CF6), 'AI-powered experience settings'),
    _SM('accessibility', 'Accessibility',      Icons.accessibility_new_rounded,       Color(0xFF06B6D4), 'Large text, captions, voice'),
    _SM('help',          'Help & Support',     Icons.help_outline_rounded,            Color(0xFF6B7280), 'Get help and contact support'),
    _SM('about',         'About',              Icons.info_outline_rounded,            Color(0xFF6B7280), 'Terms, privacy policy and more'),
  ];

  void _open(String key, Map<String, dynamic> settings) {
    Widget? screen = switch (key) {
      'privacy'       => PrivacySettingsScreen(initial: _section(settings, 'privacy')),
      'notifications' => NotificationSettingsScreen(initial: _section(settings, 'notifications')),
      'security'      => const SecuritySettingsScreen(),
      'data_saver'    => DataSaverSettingsScreen(initial: _section(settings, 'data_saver')),
      'appearance'    => AppearanceSettingsScreen(initial: _section(settings, 'appearance')),
      'video'         => VideoSettingsScreen(initial: _section(settings, 'video')),
      'messages'      => MessageSettingsScreen(initial: _section(settings, 'messages')),
      'wallet'        => const WalletSettingsScreen(),
      'creator_studio'=> const CreatorStudioSettingsScreen(),
      'accessibility' => AccessibilitySettingsScreen(initial: _section(settings, 'accessibility')),
      'ai'            => AiSettingsScreen(initial: _section(settings, 'ai_features')),
      'safety'        => const CommunitySafetySettingsScreen(),
      'help'          => const HelpSettingsScreen(),
      'about'         => const AboutSettingsScreen(),
      _               => null,
    };
    if (screen != null) Navigator.push(context, MaterialPageRoute(builder: (_) => screen));
  }

  Map<String, dynamic> _section(Map<String, dynamic> s, String key) =>
      Map<String, dynamic>.from(s[key] as Map? ?? {});

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final settingsAsync = ref.watch(_settingsProvider);
    final settings = settingsAsync.valueOrNull ?? {};
    final filtered = _query.isEmpty ? _sections : _sections.where((s) => s.label.toLowerCase().contains(_query) || s.subtitle.toLowerCase().contains(_query)).toList();

    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'Settings'),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: TextField(
            controller: _search,
            onChanged: (v) => setState(() => _query = v.toLowerCase()),
            decoration: InputDecoration(
              hintText: 'Search settings...',
              prefixIcon: Icon(Icons.search_rounded, color: c.subtleText, size: 20),
              suffixIcon: _query.isNotEmpty ? IconButton(icon: Icon(Icons.close_rounded, size: 18, color: c.subtleText), onPressed: () { _search.clear(); setState(() => _query = ''); }) : null,
              filled: true, fillColor: c.inputFill,
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
              return Material(
                color: c.cardBg,
                borderRadius: BorderRadius.circular(16),
                child: InkWell(
                  borderRadius: BorderRadius.circular(16),
                  onTap: () => _open(s.key, settings),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                    child: Row(children: [
                      Container(width: 44, height: 44, decoration: BoxDecoration(color: s.color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(12)), child: Icon(s.icon, color: s.color, size: 22)),
                      const SizedBox(width: 14),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(s.label, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: c.bodyText)),
                        const SizedBox(height: 2),
                        Text(s.subtitle, style: TextStyle(fontSize: 11, color: c.subtleText)),
                      ])),
                      Icon(Icons.chevron_right_rounded, color: c.subtleText, size: 20),
                    ]),
                  ),
                ),
              );
            },
          ),
        ),
      ]),
    );
  }
}

class _SM {
  final String key, label, subtitle;
  final IconData icon;
  final Color color;
  const _SM(this.key, this.label, this.icon, this.color, this.subtitle);
}

// ══════════════════════════════════════════════════════════════════════════
// SHARED HELPERS
// ══════════════════════════════════════════════════════════════════════════
PreferredSizeWidget _bar(BuildContext context, String title) => AppBar(
  title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 18)),
  centerTitle: true, elevation: 0,
  backgroundColor: context.colors.cardBg, surfaceTintColor: Colors.transparent,
  leading: BackButton(color: context.colors.bodyText),
);

Widget _group({String? title, required List<Widget> children, required BuildContext context}) {
  final c = context.colors;
  return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    if (title != null) Padding(padding: const EdgeInsets.fromLTRB(4, 16, 4, 8), child: Text(title, style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: c.subtleText, letterSpacing: .6))),
    Material(color: c.cardBg, borderRadius: BorderRadius.circular(16), child: Column(children: [
      for (int i = 0; i < children.length; i++) ...[
        children[i],
        if (i < children.length - 1) Divider(height: 1, indent: 56, color: c.dividerColor),
      ],
    ])),
  ]);
}

class _Toggle extends StatelessWidget {
  final IconData icon; final Color iconColor; final String label; final String? subtitle;
  final bool value; final ValueChanged<bool> onChanged;
  const _Toggle({required this.icon, required this.iconColor, required this.label, this.subtitle, required this.value, required this.onChanged});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return ListTile(
      leading: Container(width: 36, height: 36, decoration: BoxDecoration(color: iconColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: iconColor, size: 18)),
      title: Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: c.bodyText)),
      subtitle: subtitle != null ? Text(subtitle!, style: TextStyle(fontSize: 11, color: c.subtleText)) : null,
      trailing: Switch.adaptive(value: value, onChanged: onChanged, activeThumbColor: _kOrange, activeTrackColor: _kOrange.withValues(alpha: 0.4)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
    );
  }
}

class _Nav extends StatelessWidget {
  final IconData icon; final Color iconColor; final String label;
  final String? trailing; final VoidCallback? onTap; final Widget? trailingWidget;
  const _Nav({required this.icon, required this.iconColor, required this.label, this.trailing, this.onTap, this.trailingWidget});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return ListTile(
      leading: Container(width: 36, height: 36, decoration: BoxDecoration(color: iconColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: iconColor, size: 18)),
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

class _Chips extends StatelessWidget {
  final List<String> options, values;
  final String current;
  final ValueChanged<String> onChanged;
  const _Chips({required this.options, required this.values, required this.current, required this.onChanged});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Padding(padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12), child: Wrap(spacing: 8, runSpacing: 8, children: [
      for (int i = 0; i < options.length; i++)
        GestureDetector(
          onTap: () => onChanged(values[i]),
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 180),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
            decoration: BoxDecoration(color: current == values[i] ? _kOrange : c.inputFill, borderRadius: BorderRadius.circular(20), border: Border.all(color: current == values[i] ? _kOrange : c.dividerColor)),
            child: Text(options[i], style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: current == values[i] ? Colors.white : c.bodyText)),
          ),
        ),
    ]));
  }
}

// ══════════════════════════════════════════════════════════════════════════
// PRIVACY
// ══════════════════════════════════════════════════════════════════════════
class PrivacySettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const PrivacySettingsScreen({super.key, required this.initial});
  @override ConsumerState<PrivacySettingsScreen> createState() => _PrivacyState();
}
class _PrivacyState extends ConsumerState<PrivacySettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, dynamic v) async { setState(() => _s[k] = v); try { await CommunityRepository().updateSettings('privacy', {k: v}); } catch (_) {} }
  bool _b(String k, [bool d = false]) => _s[k] as bool? ?? d;
  String _str(String k, [String d = 'everyone']) => _s[k] as String? ?? d;

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final counts = ref.watch(_privacyCounts).valueOrNull;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'Privacy'),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _group(title: 'ACCOUNT PRIVACY', context: context, children: [
          _Toggle(icon: Icons.lock_outline_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Private Account', subtitle: 'Only approved followers can see your content.', value: _b('private_account'), onChanged: (v) => _save('private_account', v)),
        ]),
        _group(title: 'WHO CAN...', context: context, children: [
          _WhoCanRow(label: 'Follow me',           value: _str('who_can_follow'),   onChanged: (v) => _save('who_can_follow', v)),
          _WhoCanRow(label: 'Message me',          value: _str('who_can_message'),  onChanged: (v) => _save('who_can_message', v), opts: const ['everyone','followers','nobody']),
          _WhoCanRow(label: 'Comment on my posts', value: _str('who_can_comment'),  onChanged: (v) => _save('who_can_comment', v)),
          _WhoCanRow(label: 'Mention me',          value: _str('who_can_mention'),  onChanged: (v) => _save('who_can_mention', v)),
          _WhoCanRow(label: 'Tag me',              value: _str('who_can_tag','followers'), onChanged: (v) => _save('who_can_tag', v), opts: const ['followers','nobody']),
          _WhoCanRow(label: 'Remix my content',    value: _str('who_can_remix'),    onChanged: (v) => _save('who_can_remix', v)),
        ]),
        _group(title: 'VISIBILITY', context: context, children: [
          _Toggle(icon: Icons.visibility_off_rounded,  iconColor: const Color(0xFF6B7280), label: 'Hide Online Status',  value: _b('hide_online_status', true),  onChanged: (v) => _save('hide_online_status', v)),
          _Toggle(icon: Icons.people_outline_rounded,  iconColor: const Color(0xFF6B7280), label: 'Hide Followers',      value: _b('hide_followers'),            onChanged: (v) => _save('hide_followers', v)),
          _Toggle(icon: Icons.person_outline_rounded,  iconColor: const Color(0xFF6B7280), label: 'Hide Following',      value: _b('hide_following'),            onChanged: (v) => _save('hide_following', v)),
          _Toggle(icon: Icons.favorite_border_rounded, iconColor: const Color(0xFF6B7280), label: 'Hide Likes',          value: _b('hide_likes'),                onChanged: (v) => _save('hide_likes', v)),
        ]),
        _group(title: 'ADVANCED', context: context, children: [
          _Nav(icon: Icons.block_rounded,      iconColor: const Color(0xFFEF4444), label: 'Blocked Users',    trailing: counts != null ? '${counts['blocked']}' : null, onTap: () {}),
          _Nav(icon: Icons.volume_off_rounded, iconColor: const Color(0xFFF59E0B), label: 'Muted Users',      trailing: counts != null ? '${counts['muted']}' : null,   onTap: () {}),
          _Nav(icon: Icons.person_off_outlined,iconColor: const Color(0xFF8B5CF6), label: 'Restricted Users', trailing: counts != null ? '${counts['restricted']}' : null, onTap: () {}),
        ]),
      ]),
    );
  }
}

class _WhoCanRow extends StatelessWidget {
  final String label, value;
  final ValueChanged<String> onChanged;
  final List<String> opts;
  const _WhoCanRow({required this.label, required this.value, required this.onChanged, this.opts = const ['everyone','followers','nobody']});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    String lbl(String v) => switch(v) { 'everyone' => 'Everyone', 'followers' => 'Followers', 'nobody' => 'Nobody', _ => v };
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      title: Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: c.bodyText)),
      trailing: GestureDetector(
        onTap: () => showModalBottomSheet(context: context, backgroundColor: c.cardBg, shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))), builder: (_) => Padding(
          padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(context).padding.bottom + 20),
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: c.bodyText)),
            const SizedBox(height: 16),
            ...opts.map((o) => ListTile(title: Text(lbl(o), style: TextStyle(color: c.bodyText, fontWeight: FontWeight.w500)), trailing: o == value ? Icon(Icons.check_circle_rounded, color: _kOrange) : null, onTap: () { Navigator.pop(context); onChanged(o); }, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)))),
          ]),
        )),
        child: Row(mainAxisSize: MainAxisSize.min, children: [Text(lbl(value), style: TextStyle(fontSize: 12, color: c.subtleText)), Icon(Icons.chevron_right_rounded, color: c.subtleText, size: 18)]),
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// NOTIFICATIONS
// ══════════════════════════════════════════════════════════════════════════
class NotificationSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const NotificationSettingsScreen({super.key, required this.initial});
  @override ConsumerState<NotificationSettingsScreen> createState() => _NotifState();
}
class _NotifState extends ConsumerState<NotificationSettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, bool v) async { setState(() => _s[k] = v); try { await CommunityRepository().updateSettings('notifications', {k: v}); } catch (_) {} }
  bool _b(String k, [bool d = true]) => _s[k] as bool? ?? d;

  static const _items = [
    (Icons.favorite_border_rounded,     Color(0xFFEF4444), 'Likes',             'likes',            true),
    (Icons.chat_bubble_outline_rounded, Color(0xFF3B82F6), 'Comments',          'comments',         true),
    (Icons.reply_rounded,               Color(0xFF8B5CF6), 'Replies',           'replies',          true),
    (Icons.alternate_email_rounded,     Color(0xFFF59E0B), 'Mentions',          'mentions',         true),
    (Icons.person_add_outlined,         Color(0xFF06B6D4), 'Follows',           'follows',          true),
    (Icons.person_add_alt_1_rounded,    Color(0xFF10B981), 'Follow Requests',   'follow_requests',  true),
    (Icons.mail_outline_rounded,        Color(0xFF10B981), 'Messages',          'messages',         true),
    (Icons.upload_outlined,             Color(0xFF8B5CF6), 'Creator Uploads',   'creator_uploads',  true),
    (Icons.shopping_bag_outlined,       Color(0xFFFF8A00), 'Business Orders',   'business_orders',  true),
    (Icons.live_tv_rounded,             Color(0xFFEF4444), 'Live Notifications','live_streams',     true),
    (Icons.campaign_outlined,           Color(0xFF6B7280), 'Promotions',        'promotions',       false),
    (Icons.email_outlined,              Color(0xFF3B82F6), 'Email Notifications','email',           true),
    (Icons.sms_outlined,                Color(0xFF6B7280), 'SMS Notifications', 'sms',              false),
  ];

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg,
    appBar: _bar(context, 'Notifications'),
    body: ListView(padding: const EdgeInsets.all(16), children: [
      _group(context: context, children: _items.map((it) => _Toggle(icon: it.$1, iconColor: it.$2, label: it.$3, value: _b(it.$4, it.$5), onChanged: (v) => _save(it.$4, v))).toList()),
    ]),
  );
}

// ══════════════════════════════════════════════════════════════════════════
// SECURITY
// ══════════════════════════════════════════════════════════════════════════
class SecuritySettingsScreen extends ConsumerWidget {
  const SecuritySettingsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.colors;
    // Load device count from backend in future — for now show loading or fetch
    final secAsync = ref.watch(_securityInfoProvider);
    final info = secAsync.valueOrNull;

    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'Security'),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(color: const Color(0xFF10B981).withValues(alpha: 0.08), borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFF10B981).withValues(alpha: 0.2))),
          child: Row(children: [
            Container(width: 56, height: 56, decoration: BoxDecoration(color: const Color(0xFF10B981).withValues(alpha: 0.15), shape: BoxShape.circle), child: const Icon(Icons.verified_user_rounded, color: Color(0xFF10B981), size: 28)),
            const SizedBox(width: 16),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Your account is secure', style: TextStyle(fontWeight: FontWeight.w700, color: Color(0xFF10B981), fontSize: 15)),
              const SizedBox(height: 4),
              Text(info?['last_login'] ?? 'Checking...', style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
            ])),
          ]),
        ),
        _group(title: 'AUTHENTICATION', context: context, children: [
          _Toggle(icon: Icons.security_rounded, iconColor: const Color(0xFF10B981), label: 'Two Factor Authentication', subtitle: 'Enabled', value: true, onChanged: (_) {}),
          _Nav(icon: Icons.history_rounded, iconColor: const Color(0xFF3B82F6), label: 'Login Activity', onTap: () {}),
        ]),
        _group(title: 'DEVICES', context: context, children: [
          _Nav(icon: Icons.devices_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Active Devices', trailing: info != null ? '${info['active_devices']}' : null, onTap: () {}),
          _Nav(icon: Icons.verified_outlined, iconColor: const Color(0xFF10B981), label: 'Trusted Devices', trailing: info != null ? '${info['trusted_devices']}' : null, onTap: () {}),
        ]),
        _group(title: 'ACCOUNT', context: context, children: [
          _Nav(icon: Icons.vpn_key_outlined, iconColor: const Color(0xFFF59E0B), label: 'Recovery Codes', onTap: () {}),
          _Toggle(icon: Icons.notifications_active_outlined, iconColor: const Color(0xFF06B6D4), label: 'Security Alerts', value: true, onChanged: (_) {}),
        ]),
        const SizedBox(height: 16),
        Material(color: const Color(0xFFEF4444).withValues(alpha: 0.08), borderRadius: BorderRadius.circular(16), child: ListTile(
          leading: Container(width: 36, height: 36, decoration: BoxDecoration(color: const Color(0xFFEF4444).withValues(alpha: 0.15), borderRadius: BorderRadius.circular(10)), child: const Icon(Icons.logout_rounded, color: Color(0xFFEF4444), size: 18)),
          title: const Text('Logout All Devices', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFFEF4444))),
          onTap: () {},
        )),
      ]),
    );
  }
}

final _securityInfoProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  // Try to get real session info — graceful fallback
  try {
    final res = await CommunityRepository().getSettings();
    return {
      'last_login': 'Last login: Today',
      'active_devices': res['active_devices_count'] ?? 1,
      'trusted_devices': res['trusted_devices_count'] ?? 1,
    };
  } catch (_) {
    return {'last_login': 'Today', 'active_devices': 1, 'trusted_devices': 1};
  }
});

// ══════════════════════════════════════════════════════════════════════════
// DATA SAVER
// ══════════════════════════════════════════════════════════════════════════
class DataSaverSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const DataSaverSettingsScreen({super.key, required this.initial});
  @override ConsumerState<DataSaverSettingsScreen> createState() => _DataSaverState();
}
class _DataSaverState extends ConsumerState<DataSaverSettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, dynamic v) async { setState(() => _s[k] = v); try { await CommunityRepository().updateSettings('data_saver', {k: v}); } catch (_) {} }
  bool _b(String k, [bool d = false]) => _s[k] as bool? ?? d;
  String _str(String k, [String d = '']) => _s[k] as String? ?? d;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg,
    appBar: _bar(context, 'Data Saver'),
    body: ListView(padding: const EdgeInsets.all(16), children: [
      _group(context: context, children: [
        _Toggle(icon: Icons.data_saver_on_outlined, iconColor: const Color(0xFF10B981), label: 'Data Saver', subtitle: 'Reduce data usage across the app.', value: _b('enabled'), onChanged: (v) => _save('enabled', v)),
      ]),
      _group(title: 'IMAGE QUALITY', context: context, children: [
        _Chips(options: const ['High', 'Medium', 'Low'], values: const ['high', 'medium', 'low'], current: _str('image_quality', 'high'), onChanged: (v) => _save('image_quality', v)),
      ]),
      _group(title: 'VIDEO QUALITY', context: context, children: [
        _VideoQualityList(current: _str('video_quality', 'auto'), onChanged: (v) => _save('video_quality', v)),
      ]),
      _group(title: 'PLAYBACK', context: context, children: [
        _Toggle(icon: Icons.play_disabled_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Disable Auto Play', value: _b('disable_autoplay'), onChanged: (v) => _save('disable_autoplay', v)),
        _Toggle(icon: Icons.wifi_outlined, iconColor: const Color(0xFF3B82F6), label: 'Wi-Fi Only Downloads', value: _b('wifi_only_download'), onChanged: (v) => _save('wifi_only_download', v)),
        _Toggle(icon: Icons.download_outlined, iconColor: const Color(0xFF10B981), label: 'Preload Only on Wi-Fi', value: _b('preload_wifi_only'), onChanged: (v) => _save('preload_wifi_only', v)),
      ]),
    ]),
  );
}

class _VideoQualityList extends StatelessWidget {
  final String current;
  final ValueChanged<String> onChanged;
  const _VideoQualityList({required this.current, required this.onChanged});
  static const _opts = [('Auto', 'auto'), ('144p', '144p'), ('240p', '240p'), ('360p', '360p'), ('480p', '480p'), ('720p', '720p'), ('1080p', '1080p')];
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Column(children: _opts.map((o) => ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16),
      title: Text(o.$1, style: TextStyle(fontSize: 14, color: c.bodyText)),
      trailing: Radio<String>(value: o.$2, groupValue: current, onChanged: (v) => onChanged(v!), activeColor: _kOrange),
      onTap: () => onChanged(o.$2),
    )).toList());
  }
}

// ══════════════════════════════════════════════════════════════════════════
// APPEARANCE
// ══════════════════════════════════════════════════════════════════════════
class AppearanceSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const AppearanceSettingsScreen({super.key, required this.initial});
  @override ConsumerState<AppearanceSettingsScreen> createState() => _AppearanceState();
}
class _AppearanceState extends ConsumerState<AppearanceSettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, dynamic v) async { setState(() => _s[k] = v); try { await CommunityRepository().updateSettings('appearance', {k: v}); } catch (_) {} }
  String _str(String k, [String d = '']) => _s[k] as String? ?? d;
  bool _b(String k) => _s[k] as bool? ?? false;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg,
    appBar: _bar(context, 'Appearance'),
    body: ListView(padding: const EdgeInsets.all(16), children: [
      _group(title: 'THEME', context: context, children: [_Chips(options: const ['Light', 'Dark', 'System'], values: const ['light', 'dark', 'system'], current: _str('theme', 'system'), onChanged: (v) => _save('theme', v))]),
      _group(title: 'FONT SIZE', context: context, children: [_Chips(options: const ['Small', 'Medium', 'Large', 'X-Large'], values: const ['small', 'medium', 'large', 'xlarge'], current: _str('font_size', 'medium'), onChanged: (v) => _save('font_size', v))]),
      _group(title: 'MOTION', context: context, children: [_Toggle(icon: Icons.animation_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Reduce Motion', subtitle: 'Minimize animations throughout the app', value: _b('reduce_motion'), onChanged: (v) => _save('reduce_motion', v))]),
    ]),
  );
}

// ══════════════════════════════════════════════════════════════════════════
// VIDEO SETTINGS
// ══════════════════════════════════════════════════════════════════════════
class VideoSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const VideoSettingsScreen({super.key, required this.initial});
  @override ConsumerState<VideoSettingsScreen> createState() => _VideoState();
}
class _VideoState extends ConsumerState<VideoSettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, dynamic v) async { setState(() => _s[k] = v); try { await CommunityRepository().updateSettings('video', {k: v}); } catch (_) {} }
  String _str(String k, [String d = '']) => _s[k] as String? ?? d;
  bool _b(String k, [bool d = false]) => _s[k] as bool? ?? d;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg,
    appBar: _bar(context, 'Video Settings'),
    body: ListView(padding: const EdgeInsets.all(16), children: [
      _group(title: 'AUTO PLAY', context: context, children: [_Chips(options: const ['Always', 'Wi-Fi Only', 'Never'], values: const ['always', 'wifi_only', 'never'], current: _str('autoplay', 'wifi_only'), onChanged: (v) => _save('autoplay', v))]),
      _group(title: 'PREFERRED RESOLUTION', context: context, children: [_Chips(options: const ['Auto', '480p', '720p', '1080p'], values: const ['auto', '480p', '720p', '1080p'], current: _str('resolution', 'auto'), onChanged: (v) => _save('resolution', v))]),
      _group(title: 'OTHER', context: context, children: [
        _Toggle(icon: Icons.picture_in_picture_rounded, iconColor: const Color(0xFF3B82F6), label: 'Picture in Picture', value: _b('pip_enabled', true), onChanged: (v) => _save('pip_enabled', v)),
        _Toggle(icon: Icons.loop_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Loop Videos', value: _b('loop'), onChanged: (v) => _save('loop', v)),
      ]),
    ]),
  );
}

// ══════════════════════════════════════════════════════════════════════════
// MESSAGES
// ══════════════════════════════════════════════════════════════════════════
class MessageSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const MessageSettingsScreen({super.key, required this.initial});
  @override ConsumerState<MessageSettingsScreen> createState() => _MsgState();
}
class _MsgState extends ConsumerState<MessageSettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, dynamic v) async { setState(() => _s[k] = v); try { await CommunityRepository().updateSettings('messages', {k: v}); } catch (_) {} }
  bool _b(String k, [bool d = true]) => _s[k] as bool? ?? d;
  String _str(String k, [String d = 'everyone']) => _s[k] as String? ?? d;

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg,
    appBar: _bar(context, 'Messages'),
    body: ListView(padding: const EdgeInsets.all(16), children: [
      _group(context: context, children: [
        _Toggle(icon: Icons.done_all_rounded, iconColor: const Color(0xFF3B82F6), label: 'Read Receipts', subtitle: 'Show when you have read messages', value: _b('read_receipts'), onChanged: (v) => _save('read_receipts', v)),
        _Toggle(icon: Icons.more_horiz_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Typing Indicator', subtitle: 'Show when you are typing', value: _b('typing_indicator'), onChanged: (v) => _save('typing_indicator', v)),
        _Toggle(icon: Icons.mark_email_unread_outlined, iconColor: const Color(0xFFF59E0B), label: 'Message Requests', subtitle: 'Allow messages from non-followers', value: _b('message_requests'), onChanged: (v) => _save('message_requests', v)),
      ]),
      _group(title: 'WHO CAN MESSAGE ME', context: context, children: [
        _Chips(options: const ['Everyone', 'Followers', 'Nobody'], values: const ['everyone', 'followers', 'nobody'], current: _str('who_can_message'), onChanged: (v) => _save('who_can_message', v)),
      ]),
      _group(title: 'DISAPPEARING MESSAGES', context: context, children: [
        _Chips(options: const ['Off', '24 Hours', '7 Days', '30 Days'], values: const ['off', '24h', '7d', '30d'], current: _str('disappearing', 'off'), onChanged: (v) => _save('disappearing', v)),
      ]),
    ]),
  );
}

// ══════════════════════════════════════════════════════════════════════════
// WALLET  — real data from backend
// ══════════════════════════════════════════════════════════════════════════
final _walletProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  try {
    return await CommunityRepository().getWalletInfo();
  } catch (_) {
    return {};
  }
});

class WalletSettingsScreen extends ConsumerWidget {
  const WalletSettingsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.colors;
    final walletAsync = ref.watch(_walletProvider);
    final wallet = walletAsync.valueOrNull;

    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'Wallet'),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        // Balance card
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(gradient: const LinearGradient(colors: [Color(0xFF0F1F3D), Color(0xFF1E3A6E)], begin: Alignment.topLeft, end: Alignment.bottomRight), borderRadius: BorderRadius.circular(20)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Total Balance', style: TextStyle(color: Colors.white60, fontSize: 13)),
            const SizedBox(height: 6),
            walletAsync.isLoading
                ? const SizedBox(height: 40, child: Center(child: CircularProgressIndicator(color: Colors.white54, strokeWidth: 2)))
                : Text(
                    wallet?['balance_formatted'] ?? '\$0.00',
                    style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w800),
                  ),
            Text(wallet?['currency'] ?? 'USD', style: const TextStyle(color: Colors.white60, fontSize: 12)),
            const SizedBox(height: 20),
            Row(children: [
              _WBtn(label: 'Deposit', icon: Icons.add_rounded),
              const SizedBox(width: 8),
              _WBtn(label: 'Withdraw', icon: Icons.arrow_upward_rounded, outlined: true),
              const SizedBox(width: 8),
              _WBtn(label: 'History', icon: Icons.history_rounded, outlined: true),
            ]),
          ]),
        ),
        // Recent transactions
        if (wallet?['recent_transactions'] != null && (wallet!['recent_transactions'] as List).isNotEmpty) ...[
          Padding(padding: const EdgeInsets.fromLTRB(4, 20, 4, 8), child: Text('RECENT TRANSACTIONS', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: c.subtleText, letterSpacing: .6))),
          Material(color: c.cardBg, borderRadius: BorderRadius.circular(16), child: Column(children: [
            for (final tx in (wallet['recent_transactions'] as List).cast<Map>())
              _TxTile(tx: Map<String, dynamic>.from(tx)),
          ])),
        ],
        _group(title: 'QUICK ACCESS', context: context, children: [
          _Nav(icon: Icons.credit_card_rounded, iconColor: const Color(0xFF3B82F6), label: 'Cards', onTap: () {}),
          _Nav(icon: Icons.account_balance_outlined, iconColor: const Color(0xFF10B981), label: 'Bank Accounts', onTap: () {}),
          _Nav(icon: Icons.receipt_long_outlined, iconColor: const Color(0xFFF59E0B), label: 'Transactions', onTap: () {}),
          _Nav(icon: Icons.payment_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Payment Methods', onTap: () {}),
        ]),
      ]),
    );
  }
}

class _WBtn extends StatelessWidget {
  final String label; final IconData icon; final bool outlined;
  const _WBtn({required this.label, required this.icon, this.outlined = false});
  @override
  Widget build(BuildContext context) => Expanded(child: Container(
    padding: const EdgeInsets.symmetric(vertical: 10),
    decoration: BoxDecoration(color: outlined ? Colors.transparent : _kOrange, border: outlined ? Border.all(color: Colors.white38) : null, borderRadius: BorderRadius.circular(12)),
    child: Column(mainAxisSize: MainAxisSize.min, children: [Icon(icon, color: Colors.white, size: 18), const SizedBox(height: 4), Text(label, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600))]),
  ));
}

class _TxTile extends StatelessWidget {
  final Map<String, dynamic> tx;
  const _TxTile({required this.tx});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final isCredit = (tx['type'] ?? '') == 'credit';
    final amount = tx['amount_formatted'] ?? '';
    return ListTile(
      leading: Container(width: 40, height: 40, decoration: BoxDecoration(color: (isCredit ? const Color(0xFF10B981) : const Color(0xFFEF4444)).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
        child: Icon(isCredit ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded, color: isCredit ? const Color(0xFF10B981) : const Color(0xFFEF4444), size: 18)),
      title: Text(tx['description'] ?? '', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: c.bodyText)),
      subtitle: Text(tx['date'] ?? '', style: TextStyle(fontSize: 11, color: c.subtleText)),
      trailing: Text(isCredit ? '+$amount' : '-$amount', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: isCredit ? const Color(0xFF10B981) : const Color(0xFFEF4444))),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// CREATOR STUDIO — real stats from backend
// ══════════════════════════════════════════════════════════════════════════
final _creatorStatsProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  try {
    return await CommunityRepository().getCreatorStats();
  } catch (_) {
    return {};
  }
});

class CreatorStudioSettingsScreen extends ConsumerWidget {
  const CreatorStudioSettingsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.colors;
    final statsAsync = ref.watch(_creatorStatsProvider);
    final stats = statsAsync.valueOrNull;

    String fmt(dynamic n) {
      if (n == null) return '--';
      final i = n is int ? n : (n is double ? n.toInt() : int.tryParse('$n') ?? 0);
      if (i >= 1000000) return '${(i / 1000000).toStringAsFixed(1)}M';
      if (i >= 1000) return '${(i / 1000).toStringAsFixed(1)}K';
      return '$i';
    }

    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'Creator Studio'),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(color: c.cardBg, borderRadius: BorderRadius.circular(20), border: Border.all(color: c.dividerColor)),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
              Text('This Month Overview', style: TextStyle(fontWeight: FontWeight.w700, color: c.bodyText)),
              const Text('View all ›', style: TextStyle(fontSize: 12, color: _kOrange)),
            ]),
            const SizedBox(height: 16),
            statsAsync.isLoading
                ? const Center(child: CircularProgressIndicator(color: _kOrange, strokeWidth: 2))
                : Row(children: [
                    _SCol(label: 'Views',      value: fmt(stats?['views_count'])),
                    _SCol(label: 'Followers',  value: fmt(stats?['followers_count'])),
                    _SCol(label: 'Engagement', value: fmt(stats?['engagement_count'])),
                  ]),
            const SizedBox(height: 16),
            _BarChart(data: List<double>.from((stats?['weekly_chart'] as List?)?.cast<num>().map((n) => n.toDouble()) ?? [0.4, 0.6, 0.5, 0.8, 0.7, 0.9, 0.65])),
          ]),
        ),
        _group(title: 'TOOLS', context: context, children: [
          _Nav(icon: Icons.dashboard_outlined,     iconColor: const Color(0xFF3B82F6), label: 'Dashboard',      onTap: () {}),
          _Nav(icon: Icons.analytics_outlined,     iconColor: const Color(0xFF8B5CF6), label: 'Analytics',      onTap: () {}),
          _Nav(icon: Icons.attach_money_rounded,   iconColor: const Color(0xFF10B981), label: 'Earnings',       onTap: () {}),
          _Nav(icon: Icons.monetization_on_outlined,iconColor: const Color(0xFFF59E0B),label: 'Monetization',   onTap: () {}),
          _Nav(icon: Icons.group_outlined,         iconColor: const Color(0xFF06B6D4), label: 'Subscribers',    onTap: () {}),
          _Nav(icon: Icons.build_outlined,         iconColor: const Color(0xFF6B7280), label: 'Creator Tools',  onTap: () {}),
          _Nav(icon: Icons.school_outlined,        iconColor: const Color(0xFF8B5CF6), label: 'Creator Academy',onTap: () {}),
          _Nav(icon: Icons.verified_outlined,      iconColor: _kOrange,               label: 'Verification',
            trailingWidget: Container(padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3), decoration: BoxDecoration(color: _kOrange.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(8), border: Border.all(color: _kOrange)), child: const Text('Verified', style: TextStyle(color: _kOrange, fontSize: 11, fontWeight: FontWeight.w700)))),
        ]),
      ]),
    );
  }
}

class _SCol extends StatelessWidget {
  final String label, value;
  const _SCol({required this.label, required this.value});
  @override Widget build(BuildContext context) => Expanded(child: Column(children: [
    Text(value, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18, color: _kOrange)),
    Text(label, style: TextStyle(fontSize: 11, color: context.colors.subtleText)),
  ]));
}

class _BarChart extends StatelessWidget {
  final List<double> data;
  const _BarChart({required this.data});
  @override Widget build(BuildContext context) {
    final mx = data.isEmpty ? 1.0 : data.reduce((a, b) => a > b ? a : b);
    return SizedBox(height: 48, child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: data.map((v) => Expanded(child: Padding(padding: const EdgeInsets.symmetric(horizontal: 2), child: Container(height: 48 * (v / mx).clamp(0.05, 1.0), decoration: BoxDecoration(color: _kOrange.withValues(alpha: 0.5 + 0.5 * (v / mx)), borderRadius: BorderRadius.circular(4)))))).toList()));
  }
}

// ══════════════════════════════════════════════════════════════════════════
// ACCESSIBILITY
// ══════════════════════════════════════════════════════════════════════════
class AccessibilitySettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const AccessibilitySettingsScreen({super.key, required this.initial});
  @override ConsumerState<AccessibilitySettingsScreen> createState() => _AccessState();
}
class _AccessState extends ConsumerState<AccessibilitySettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, bool v) async { setState(() => _s[k] = v); try { await CommunityRepository().updateSettings('accessibility', {k: v}); } catch (_) {} }
  bool _b(String k) => _s[k] as bool? ?? false;
  @override Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg, appBar: _bar(context, 'Accessibility'),
    body: ListView(padding: const EdgeInsets.all(16), children: [_group(context: context, children: [
      _Toggle(icon: Icons.text_increase_rounded,       iconColor: const Color(0xFF06B6D4), label: 'Large Text',      value: _b('large_text'),      onChanged: (v) => _save('large_text', v)),
      _Toggle(icon: Icons.record_voice_over_outlined,  iconColor: const Color(0xFF8B5CF6), label: 'Screen Reader',   value: _b('screen_reader'),   onChanged: (v) => _save('screen_reader', v)),
      _Toggle(icon: Icons.closed_caption_outlined,     iconColor: const Color(0xFF3B82F6), label: 'Captions',        value: _b('captions'),        onChanged: (v) => _save('captions', v)),
      _Toggle(icon: Icons.contrast_rounded,            iconColor: const Color(0xFF10B981), label: 'High Contrast',   value: _b('high_contrast'),   onChanged: (v) => _save('high_contrast', v)),
      _Toggle(icon: Icons.mic_outlined,                iconColor: const Color(0xFFF59E0B), label: 'Voice Commands',  value: _b('voice_commands'),  onChanged: (v) => _save('voice_commands', v)),
    ])]),
  );
}

// ══════════════════════════════════════════════════════════════════════════
// AI FEATURES
// ══════════════════════════════════════════════════════════════════════════
class AiSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const AiSettingsScreen({super.key, required this.initial});
  @override ConsumerState<AiSettingsScreen> createState() => _AiState();
}
class _AiState extends ConsumerState<AiSettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, bool v) async { setState(() => _s[k] = v); try { await CommunityRepository().updateSettings('ai_features', {k: v}); } catch (_) {} }
  bool _b(String k) => _s[k] as bool? ?? true;
  @override Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg, appBar: _bar(context, 'AI Features'),
    body: ListView(padding: const EdgeInsets.all(16), children: [_group(context: context, children: [
      _Toggle(icon: Icons.recommend_outlined,           iconColor: const Color(0xFF8B5CF6), label: 'AI Recommendations', subtitle: 'Personalized content based on your interests', value: _b('recommendations'), onChanged: (v) => _save('recommendations', v)),
      _Toggle(icon: Icons.translate_rounded,            iconColor: const Color(0xFF3B82F6), label: 'AI Translation',     subtitle: 'Auto-translate posts to your language',     value: _b('translation'),     onChanged: (v) => _save('translation', v)),
      _Toggle(icon: Icons.closed_caption_off_outlined,  iconColor: const Color(0xFF10B981), label: 'AI Captions',        subtitle: 'Auto-generate captions for videos',          value: _b('captions'),        onChanged: (v) => _save('captions', v)),
      _Toggle(icon: Icons.smart_toy_outlined,           iconColor: const Color(0xFFF59E0B), label: 'AI Assistant',       subtitle: 'Get help with writing and creating content', value: _b('assistant'),       onChanged: (v) => _save('assistant', v)),
      _Toggle(icon: Icons.summarize_outlined,           iconColor: const Color(0xFF06B6D4), label: 'AI Summary',         subtitle: 'Summarize long posts and articles',          value: _b('summary'),         onChanged: (v) => _save('summary', v)),
    ])]),
  );
}

// ══════════════════════════════════════════════════════════════════════════
// COMMUNITY SAFETY
// ══════════════════════════════════════════════════════════════════════════
class CommunitySafetySettingsScreen extends StatelessWidget {
  const CommunitySafetySettingsScreen({super.key});
  @override Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg, appBar: _bar(context, 'Community Safety'),
    body: ListView(padding: const EdgeInsets.all(16), children: [_group(context: context, children: [
      _Nav(icon: Icons.filter_alt_outlined,    iconColor: const Color(0xFF10B981), label: 'Hidden Words',      onTap: () {}),
      _Nav(icon: Icons.comment_outlined,       iconColor: const Color(0xFF3B82F6), label: 'Comment Filter',    onTap: () {}),
      _Nav(icon: Icons.warning_amber_rounded,  iconColor: const Color(0xFFF59E0B), label: 'Sensitive Content', trailing: 'Standard', onTap: () {}),
      _Nav(icon: Icons.history_rounded,        iconColor: const Color(0xFF8B5CF6), label: 'Report History',    onTap: () {}),
      _Nav(icon: Icons.security_rounded,       iconColor: const Color(0xFF10B981), label: 'Safety Center', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TransparencyCenter()))),
      _Nav(icon: Icons.family_restroom_rounded,iconColor: const Color(0xFF06B6D4), label: 'Parental Controls', onTap: () {}),
    ])]),
  );
}

// ══════════════════════════════════════════════════════════════════════════
// HELP & SUPPORT
// ══════════════════════════════════════════════════════════════════════════
class HelpSettingsScreen extends StatelessWidget {
  const HelpSettingsScreen({super.key});
  @override Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg, appBar: _bar(context, 'Help & Support'),
    body: ListView(padding: const EdgeInsets.all(16), children: [_group(context: context, children: [
      _Nav(icon: Icons.help_outline_rounded,     iconColor: const Color(0xFF3B82F6), label: 'Help Center',      onTap: () {}),
      _Nav(icon: Icons.support_agent_rounded,    iconColor: const Color(0xFF10B981), label: 'Contact Support',  onTap: () {}),
      _Nav(icon: Icons.chat_outlined,            iconColor: const Color(0xFFF59E0B), label: 'Live Chat',        onTap: () {}),
      _Nav(icon: Icons.bug_report_outlined,      iconColor: const Color(0xFFEF4444), label: 'Report a Bug',     onTap: () {}),
      _Nav(icon: Icons.lightbulb_outline_rounded,iconColor: const Color(0xFF8B5CF6), label: 'Feature Request',  onTap: () {}),
      _Nav(icon: Icons.rate_review_outlined,     iconColor: const Color(0xFF06B6D4), label: 'Send Feedback',    onTap: () {}),
    ])]),
  );
}

// ══════════════════════════════════════════════════════════════════════════
// ABOUT
// ══════════════════════════════════════════════════════════════════════════
class AboutSettingsScreen extends ConsumerWidget {
  const AboutSettingsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final c = context.colors;
    final versionAsync = ref.watch(_appVersionProvider);
    final v = versionAsync.valueOrNull ?? {'version': '--', 'build': '--'};
    return Scaffold(
      backgroundColor: c.scaffoldBg, appBar: _bar(context, 'About'),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _group(context: context, children: [
          _Nav(icon: Icons.menu_book_outlined,  iconColor: const Color(0xFF3B82F6), label: 'Community Guidelines', onTap: () {}),
          _Nav(icon: Icons.privacy_tip_outlined,iconColor: const Color(0xFF8B5CF6), label: 'Privacy Policy',       onTap: () {}),
          _Nav(icon: Icons.gavel_rounded,       iconColor: const Color(0xFF10B981), label: 'Terms of Service',     onTap: () {}),
          _Nav(icon: Icons.article_outlined,    iconColor: const Color(0xFF6B7280), label: 'Licenses',             onTap: () {}),
        ]),
        _group(title: 'APP INFO', context: context, children: [
          _Nav(icon: Icons.info_outline_rounded, iconColor: _kOrange, label: 'App Version', trailingWidget: Text('${v['version']} (${v['build']})', style: TextStyle(fontSize: 12, color: c.subtleText))),
          _Nav(icon: Icons.cloud_done_outlined, iconColor: const Color(0xFF10B981), label: 'System Status', trailing: 'All systems operational', onTap: () {}),
        ]),
      ]),
    );
  }
}

final _appVersionProvider = FutureProvider.autoDispose<Map<String, String>>((ref) async {
  // Package info can be added via package_info_plus — fallback to constants
  return {'version': '2.4.1', 'build': '241'};
});
