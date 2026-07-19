import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/providers/app_settings_provider.dart';
import '../../data/repositories/community_repository.dart';
import 'transparency_center_screen.dart';
import 'edit_profile_screen.dart';
import 'ad_analytics_screen.dart';
import '../../data/models/community_models.dart';

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
    _SM('wallet',        'ePay',               Icons.account_balance_wallet_outlined,  Color(0xFFFF8A00), 'Manage your ePay and transactions'),
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
      'account'       => const AccountSettingsScreen(),
      'privacy'       => PrivacySettingsScreen(initial: _section(settings, 'privacy')),
      'notifications' => NotificationSettingsScreen(initial: _section(settings, 'notifications')),
      'security'      => const SecuritySettingsScreen(),
      'content'       => ContentPreferencesScreen(initial: _section(settings, 'content')),
      'language'      => LanguageSettingsScreen(initial: _section(settings, 'language')),
      'data_saver'    => DataSaverSettingsScreen(initial: _section(settings, 'data_saver')),
      'appearance'    => AppearanceSettingsScreen(initial: _section(settings, 'appearance')),
      'video'         => VideoSettingsScreen(initial: _section(settings, 'video')),
      'messages'      => MessageSettingsScreen(initial: _section(settings, 'messages')),
      'wallet'        => const WalletSettingsScreen(),
      'creator_studio'=> const CreatorStudioSettingsScreen(),
      'business'      => const BusinessSettingsScreen(),
      'accessibility' => AccessibilitySettingsScreen(initial: _section(settings, 'accessibility')),
      'ai'            => AiSettingsScreen(initial: _section(settings, 'ai_features')),
      'safety'        => CommunitySafetySettingsScreen(initial: _section(settings, 'safety')),
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
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(16),
                child: Material(
                  color: c.cardBg,
                  child: Column(children: [
                    for (int i = 0; i < filtered.length; i++) ...[
                      InkWell(
                        onTap: () => _open(filtered[i].key, settings),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
                          child: Row(children: [
                            Container(width: 42, height: 42, decoration: BoxDecoration(color: filtered[i].color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(11)), child: Icon(filtered[i].icon, color: filtered[i].color, size: 21)),
                            const SizedBox(width: 13),
                            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(filtered[i].label, style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14, color: c.bodyText)),
                              const SizedBox(height: 2),
                              Text(filtered[i].subtitle, style: TextStyle(fontSize: 11, color: c.subtleText)),
                            ])),
                            Icon(Icons.chevron_right_rounded, color: c.subtleText, size: 20),
                          ]),
                        ),
                      ),
                      if (i < filtered.length - 1) Divider(height: 1, indent: 71, color: c.dividerColor),
                    ],
                  ]),
                ),
              ),
            ],
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
  final String? trailing; final String? subtitle; final VoidCallback? onTap; final Widget? trailingWidget;
  const _Nav({required this.icon, required this.iconColor, required this.label, this.trailing, this.subtitle, this.onTap, this.trailingWidget});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return ListTile(
      leading: Container(width: 36, height: 36, decoration: BoxDecoration(color: iconColor.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)), child: Icon(icon, color: iconColor, size: 18)),
      title: Text(label, style: TextStyle(fontSize: 14, fontWeight: FontWeight.w500, color: c.bodyText)),
      subtitle: subtitle != null ? Text(subtitle!, style: TextStyle(fontSize: 11, color: c.subtleText)) : null,
      trailing: trailingWidget ?? Row(mainAxisSize: MainAxisSize.min, children: [
        if (trailing != null) Text(trailing!, style: TextStyle(fontSize: 12, color: c.subtleText)),
        if (onTap != null) ...[const SizedBox(width: 4), Icon(Icons.chevron_right_rounded, color: c.subtleText, size: 18)],
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
          _Toggle(icon: Icons.bookmark_border_rounded, iconColor: const Color(0xFF6B7280), label: 'Hide Saved Posts',    value: _b('hide_saved'),                onChanged: (v) => _save('hide_saved', v)),
        ]),
        _group(title: 'ADVANCED', context: context, children: [
          _Nav(icon: Icons.block_rounded,      iconColor: const Color(0xFFEF4444), label: 'Blocked Users',    trailing: counts != null ? '${counts['blocked']}' : null,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const _UserListScreen(type: 'blocked')))),
          _Nav(icon: Icons.volume_off_rounded, iconColor: const Color(0xFFF59E0B), label: 'Muted Users',      trailing: counts != null ? '${counts['muted']}' : null,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const _UserListScreen(type: 'muted')))),
          _Nav(icon: Icons.person_off_outlined,iconColor: const Color(0xFF8B5CF6), label: 'Restricted Users', trailing: counts != null ? '${counts['restricted']}' : null,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const _UserListScreen(type: 'restricted')))),
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
              Text(info != null ? (info['last_login'] as String) : 'Checking...', style: const TextStyle(fontSize: 12, color: Color(0xFF6B7280))),
            ])),
          ]),
        ),
        _group(title: 'AUTHENTICATION', context: context, children: [
          _Toggle(icon: Icons.security_rounded, iconColor: const Color(0xFF10B981), label: 'Two Factor Authentication', subtitle: 'Enabled', value: true, onChanged: (_) {}),
          _Nav(icon: Icons.history_rounded, iconColor: const Color(0xFF3B82F6), label: 'Login Activity',
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const _LoginActivityScreen()))),
        ]),
        _group(title: 'DEVICES', context: context, children: [
          _Nav(icon: Icons.devices_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Active Devices',
            trailing: info != null ? '${info['active_devices']}' : null,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const _ActiveDevicesScreen()))),
        ]),
        _group(title: 'ACCOUNT', context: context, children: [
          _Toggle(icon: Icons.notifications_active_outlined, iconColor: const Color(0xFF06B6D4), label: 'Security Alerts',
            subtitle: 'Get notified about suspicious activity',
            value: info?['security_alerts'] as bool? ?? true,
            onChanged: (v) => CommunityRepository().updateSettings('security', {'security_alerts': v}).catchError((_) {})),
        ]),
        const SizedBox(height: 16),
        Material(color: const Color(0xFFEF4444).withValues(alpha: 0.08), borderRadius: BorderRadius.circular(16), child: ListTile(
          leading: Container(width: 36, height: 36, decoration: BoxDecoration(color: const Color(0xFFEF4444).withValues(alpha: 0.15), borderRadius: BorderRadius.circular(10)), child: const Icon(Icons.logout_rounded, color: Color(0xFFEF4444), size: 18)),
          title: const Text('Logout All Devices', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFFEF4444))),
          onTap: () async {
            final ok = await showDialog<bool>(context: context, builder: (dlgCtx) => AlertDialog(
              title: const Text('Logout All Devices?'),
              content: const Text('You will be signed out from all devices.'),
              actions: [
                TextButton(onPressed: () => Navigator.pop(dlgCtx, false), child: const Text('Cancel')),
                TextButton(onPressed: () => Navigator.pop(dlgCtx, true), child: const Text('Logout All', style: TextStyle(color: Colors.red))),
              ],
            ));
            if (ok == true) {
              await CommunityRepository().logoutAllDevices();
              if (context.mounted) Navigator.of(context).popUntil((r) => r.isFirst);
            }
          },
        )),
      ]),
    );
  }
}

final _securityInfoProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  try {
    final sessions = await CommunityRepository().getSessions();
    final current = sessions.firstWhere((s) => s['is_current'] == true, orElse: () => sessions.isNotEmpty ? sessions.first : {});
    final lastUsed = current['last_used'] as String? ?? current['created_at'] as String? ?? '';
    String lastLoginLabel = 'Today';
    if (lastUsed.length >= 10) {
      final d = DateTime.tryParse(lastUsed);
      if (d != null) {
        final diff = DateTime.now().difference(d);
        if (diff.inMinutes < 60) lastLoginLabel = '${diff.inMinutes}m ago';
        else if (diff.inHours < 24) lastLoginLabel = '${diff.inHours}h ago';
        else lastLoginLabel = '${diff.inDays}d ago';
      }
    }
    return {
      'last_login': 'Last login: $lastLoginLabel',
      'active_devices': sessions.length,
    };
  } catch (_) {
    return {'last_login': 'Last login: Today', 'active_devices': 1};
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

  Future<void> _save(String k, dynamic v) async {
    setState(() => _s[k] = v);
    final n = ref.read(appSettingsProvider.notifier);
    switch (k) {
      case 'enabled':           n.setDataSaver(v as bool); break;
      case 'disable_autoplay':  n.setDisableAutoplay(v as bool); break;
      case 'wifi_only_download':n.setWifiOnly(v as bool); break;
      case 'preload_wifi_only': n.setPreloadWifiOnly(v as bool); break;
      default: CommunityRepository().updateSettings('data_saver', {k: v}).catchError((_) {});
    }
  }

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

  Future<void> _save(String k, dynamic v) async {
    setState(() => _s[k] = v);
    final n = ref.read(appSettingsProvider.notifier);
    switch (k) {
      case 'theme':         n.setTheme(v as String); break;
      case 'font_size':     n.setFontSize(v as String); break;
      case 'reduce_motion': n.setReduceMotion(v as bool); break;
      default: CommunityRepository().updateSettings('appearance', {k: v}).catchError((_) {});
    }
  }

  String _str(String k, [String d = '']) => _s[k] as String? ?? d;
  bool _b(String k) => _s[k] as bool? ?? false;

  @override
  Widget build(BuildContext context) {
    final appS = ref.watch(appSettingsProvider);
    final themeStr = switch (appS.themeMode) { ThemeMode.dark => 'dark', ThemeMode.light => 'light', _ => 'system' };
    return Scaffold(
      backgroundColor: context.colors.scaffoldBg,
      appBar: _bar(context, 'Appearance'),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _group(title: 'THEME', context: context, children: [_Chips(options: const ['Light', 'Dark', 'System'], values: const ['light', 'dark', 'system'], current: themeStr, onChanged: (v) => _save('theme', v))]),
        _group(title: 'FONT SIZE', context: context, children: [_Chips(options: const ['Small', 'Medium', 'Large', 'X-Large'], values: const ['small', 'medium', 'large', 'xlarge'], current: appS.fontSize, onChanged: (v) => _save('font_size', v))]),
        _group(title: 'MOTION', context: context, children: [_Toggle(icon: Icons.animation_rounded, iconColor: const Color(0xFF8B5CF6), label: 'Reduce Motion', subtitle: 'Minimize animations throughout the app', value: appS.reduceMotion, onChanged: (v) => _save('reduce_motion', v))]),
      ]),
    );
  }
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

  Future<void> _save(String k, dynamic v) async {
    setState(() => _s[k] = v);
    final n = ref.read(appSettingsProvider.notifier);
    switch (k) {
      case 'autoplay':    n.setVideoAutoplay(v as String); break;
      case 'loop':        n.setVideoLoop(v as bool); break;
      case 'pip_enabled': n.setVideoPip(v as bool); break;
      default: CommunityRepository().updateSettings('video', {k: v}).catchError((_) {});
    }
  }

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
// WALLET — real data from backend
// ══════════════════════════════════════════════════════════════════════════
final _walletProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  try { return await CommunityRepository().getWalletInfo(); } catch (_) { return {}; }
});

final _walletTxProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  try { return await CommunityRepository().getWalletTransactions(); } catch (_) { return []; }
});

class WalletSettingsScreen extends ConsumerStatefulWidget {
  const WalletSettingsScreen({super.key});
  @override ConsumerState<WalletSettingsScreen> createState() => _WalletState();
}
class _WalletState extends ConsumerState<WalletSettingsScreen> {
  void _deposit() => showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: context.colors.cardBg,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
    builder: (ctx) => _DepositSheet(onDone: () { ref.invalidate(_walletProvider); }));

  void _withdraw() => showModalBottomSheet(context: context, isScrollControlled: true, backgroundColor: context.colors.cardBg,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
    builder: (ctx) => _WithdrawSheet(onDone: () { ref.invalidate(_walletProvider); }));

  void _history() => Navigator.push(context, MaterialPageRoute(builder: (_) => const _WalletHistoryScreen()));

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final walletAsync = ref.watch(_walletProvider);
    final txAsync = ref.watch(_walletTxProvider);
    final wallet = walletAsync.valueOrNull ?? {};
    final txs = txAsync.valueOrNull ?? [];

    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'ePay'),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Container(
          padding: const EdgeInsets.all(20),
          decoration: const BoxDecoration(gradient: LinearGradient(colors: [Color(0xFF0F1F3D), Color(0xFF1E3A6E)], begin: Alignment.topLeft, end: Alignment.bottomRight), borderRadius: BorderRadius.all(Radius.circular(20))),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Total Balance', style: TextStyle(color: Colors.white60, fontSize: 13)),
            const SizedBox(height: 6),
            walletAsync.isLoading
                ? const SizedBox(height: 40, child: Center(child: CircularProgressIndicator(color: Colors.white54, strokeWidth: 2)))
                : Text(wallet['balance_formatted'] ?? '${wallet['currency'] ?? 'USD'} 0.00',
                    style: const TextStyle(color: Colors.white, fontSize: 32, fontWeight: FontWeight.w800)),
            if (wallet['loyalty_points'] != null)
              Text('${wallet['loyalty_points']} loyalty points', style: const TextStyle(color: Colors.white60, fontSize: 11)),
            const SizedBox(height: 20),
            Row(children: [
              _WBtn(label: 'Deposit', icon: Icons.add_rounded, onTap: _deposit),
              const SizedBox(width: 8),
              _WBtn(label: 'Withdraw', icon: Icons.arrow_upward_rounded, outlined: true, onTap: _withdraw),
              const SizedBox(width: 8),
              _WBtn(label: 'History', icon: Icons.history_rounded, outlined: true, onTap: _history),
            ]),
          ]),
        ),
        if (txs.isNotEmpty) ...[
          Padding(padding: const EdgeInsets.fromLTRB(4, 20, 4, 8), child: Text('RECENT TRANSACTIONS', style: TextStyle(fontSize: 11, fontWeight: FontWeight.w700, color: c.subtleText, letterSpacing: .6))),
          Material(color: c.cardBg, borderRadius: BorderRadius.circular(16), child: Column(children: [
            for (int i = 0; i < txs.take(5).length; i++) ...[
              _TxTile(tx: txs[i]),
              if (i < txs.take(5).length - 1) Divider(height: 1, indent: 56, color: c.dividerColor),
            ],
          ])),
          const SizedBox(height: 8),
          TextButton(onPressed: _history, child: const Text('View all transactions', style: TextStyle(color: _kOrange))),
        ],
        _group(title: 'QUICK ACCESS', context: context, children: [
          _Nav(icon: Icons.add_circle_outline_rounded, iconColor: const Color(0xFF10B981), label: 'Deposit', onTap: _deposit),
          _Nav(icon: Icons.arrow_circle_up_outlined,   iconColor: const Color(0xFFEF4444), label: 'Withdraw', onTap: _withdraw),
          _Nav(icon: Icons.receipt_long_outlined,      iconColor: const Color(0xFFF59E0B), label: 'All Transactions', onTap: _history),
          _Nav(icon: Icons.loyalty_outlined,           iconColor: const Color(0xFF8B5CF6), label: 'Loyalty Points',
            trailing: wallet['loyalty_points']?.toString(), onTap: null),
        ]),
      ]),
    );
  }
}

class _WBtn extends StatelessWidget {
  final String label; final IconData icon; final bool outlined; final VoidCallback? onTap;
  const _WBtn({required this.label, required this.icon, this.outlined = false, this.onTap});
  @override
  Widget build(BuildContext context) => Expanded(child: GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.symmetric(vertical: 10),
      decoration: BoxDecoration(color: outlined ? Colors.transparent : _kOrange, border: outlined ? Border.all(color: Colors.white38) : null, borderRadius: BorderRadius.circular(12)),
      child: Column(mainAxisSize: MainAxisSize.min, children: [Icon(icon, color: Colors.white, size: 18), const SizedBox(height: 4), Text(label, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600))]),
    ),
  ));
}

class _TxTile extends StatelessWidget {
  final Map<String, dynamic> tx;
  const _TxTile({required this.tx});
  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final isCredit = (tx['type'] ?? '') == 'credit';
    final amount = (tx['amount'] as num?)?.toStringAsFixed(2) ?? '0.00';
    final note = tx['note'] as String? ?? tx['description'] as String? ?? '';
    final date = tx['created_at'] as String? ?? '';
    return ListTile(
      leading: Container(width: 40, height: 40,
        decoration: BoxDecoration(color: (isCredit ? const Color(0xFF10B981) : const Color(0xFFEF4444)).withValues(alpha: 0.12), borderRadius: BorderRadius.circular(10)),
        child: Icon(isCredit ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded, color: isCredit ? const Color(0xFF10B981) : const Color(0xFFEF4444), size: 18)),
      title: Text(note.isNotEmpty ? note : (isCredit ? 'Credit' : 'Debit'), style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: c.bodyText), maxLines: 1, overflow: TextOverflow.ellipsis),
      subtitle: Text(date.length > 10 ? date.substring(0, 10) : date, style: TextStyle(fontSize: 11, color: c.subtleText)),
      trailing: Text(isCredit ? '+\$$amount' : '-\$$amount', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w700, color: isCredit ? const Color(0xFF10B981) : const Color(0xFFEF4444))),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
    );
  }
}

// Deposit sheet
class _DepositSheet extends ConsumerStatefulWidget {
  final VoidCallback onDone;
  const _DepositSheet({required this.onDone});
  @override ConsumerState<_DepositSheet> createState() => _DepositSheetState();
}
class _DepositSheetState extends ConsumerState<_DepositSheet> {
  final _phone = TextEditingController();
  final _amount = TextEditingController();
  bool _loading = false;
  String? _error;

  @override void dispose() { _phone.dispose(); _amount.dispose(); super.dispose(); }

  Future<void> _submit() async {
    final phone = _phone.text.trim();
    final amount = double.tryParse(_amount.text.trim());
    if (phone.isEmpty || amount == null || amount <= 0) { setState(() => _error = 'Enter valid phone and amount'); return; }
    setState(() { _loading = true; _error = null; });
    try {
      final res = await CommunityRepository().depositWallet(phone, amount);
      if (!mounted) return;
      Navigator.pop(context);
      widget.onDone();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res['message'] ?? 'Deposit initiated'), backgroundColor: const Color(0xFF10B981)));
    } catch (e) {
      setState(() { _loading = false; _error = e.toString(); });
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    final bottom = MediaQuery.of(context).viewInsets.bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, bottom + 24),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Deposit to ePay', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18, color: c.bodyText)),
        const SizedBox(height: 20),
        TextField(controller: _phone, decoration: InputDecoration(labelText: 'Phone Number', prefixIcon: const Icon(Icons.phone_outlined), hintText: '61XXXXXXX'),
          keyboardType: TextInputType.phone, style: TextStyle(color: c.bodyText)),
        const SizedBox(height: 12),
        TextField(controller: _amount, decoration: const InputDecoration(labelText: 'Amount (USD)', prefixIcon: Icon(Icons.attach_money_rounded)),
          keyboardType: const TextInputType.numberWithOptions(decimal: true), style: TextStyle(color: c.bodyText)),
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_error!, style: const TextStyle(color: Color(0xFFEF4444), fontSize: 12))),
        const SizedBox(height: 20),
        SizedBox(width: double.infinity, child: ElevatedButton(
          onPressed: _loading ? null : _submit,
          child: _loading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Text('Deposit via Waafi Pay'),
        )),
      ]),
    );
  }
}

// Withdraw sheet
class _WithdrawSheet extends ConsumerStatefulWidget {
  final VoidCallback onDone;
  const _WithdrawSheet({required this.onDone});
  @override ConsumerState<_WithdrawSheet> createState() => _WithdrawSheetState();
}
class _WithdrawSheetState extends ConsumerState<_WithdrawSheet> {
  final _amount = TextEditingController();
  bool _loading = false;
  String? _error;
  @override void dispose() { _amount.dispose(); super.dispose(); }

  Future<void> _submit() async {
    final amount = double.tryParse(_amount.text.trim());
    if (amount == null || amount <= 0) { setState(() => _error = 'Enter valid amount'); return; }
    setState(() { _loading = true; _error = null; });
    try {
      final res = await CommunityRepository().withdrawWallet(amount);
      if (!mounted) return;
      Navigator.pop(context);
      widget.onDone();
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(res['message'] ?? 'Withdrawal requested'), backgroundColor: const Color(0xFF10B981)));
    } catch (e) {
      setState(() { _loading = false; _error = e.toString(); });
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(context).viewInsets.bottom + 24),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Withdraw from ePay', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 18, color: c.bodyText)),
        const SizedBox(height: 20),
        TextField(controller: _amount, decoration: const InputDecoration(labelText: 'Amount (USD)', prefixIcon: Icon(Icons.attach_money_rounded)),
          keyboardType: const TextInputType.numberWithOptions(decimal: true), style: TextStyle(color: c.bodyText)),
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_error!, style: const TextStyle(color: Color(0xFFEF4444), fontSize: 12))),
        const SizedBox(height: 20),
        SizedBox(width: double.infinity, child: ElevatedButton(
          onPressed: _loading ? null : _submit,
          child: _loading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Text('Request Withdrawal'),
        )),
      ]),
    );
  }
}

// Wallet history full screen
class _WalletHistoryScreen extends ConsumerStatefulWidget {
  const _WalletHistoryScreen();
  @override ConsumerState<_WalletHistoryScreen> createState() => _WalletHistoryState();
}
class _WalletHistoryState extends ConsumerState<_WalletHistoryScreen> {
  late Future<List<Map<String, dynamic>>> _future;
  @override void initState() { super.initState(); _future = CommunityRepository().getWalletTransactions(); }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'Transaction History'),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (ctx, snap) {
          if (snap.connectionState == ConnectionState.waiting) return const Center(child: CircularProgressIndicator(color: _kOrange));
          final txs = snap.data ?? [];
          if (txs.isEmpty) return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.receipt_long_outlined, size: 56, color: c.subtleText),
            const SizedBox(height: 12),
            Text('No transactions yet', style: TextStyle(color: c.subtleText)),
          ]));
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: txs.length,
            separatorBuilder: (_, __) => const SizedBox(height: 4),
            itemBuilder: (_, i) => Material(color: c.cardBg, borderRadius: BorderRadius.circular(12), child: _TxTile(tx: txs[i])),
          );
        },
      ),
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
          _Nav(icon: Icons.analytics_outlined,      iconColor: const Color(0xFF8B5CF6), label: 'Ad Analytics',
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdAnalyticsScreen()))),
          _Nav(icon: Icons.attach_money_rounded,    iconColor: const Color(0xFF10B981), label: 'Earnings',       trailing: 'Coming Soon', onTap: null),
          _Nav(icon: Icons.monetization_on_outlined,iconColor: const Color(0xFFF59E0B), label: 'Monetization',   trailing: 'Coming Soon', onTap: null),
          _Nav(icon: Icons.group_outlined,          iconColor: const Color(0xFF06B6D4), label: 'Subscribers',
            trailing: stats != null ? '${stats['followers_count']}' : null, onTap: null),
          _Nav(icon: Icons.verified_outlined,       iconColor: _kOrange,               label: 'Verification',
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
// COMMUNITY SAFETY — full implementation
// ══════════════════════════════════════════════════════════════════════════
class CommunitySafetySettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const CommunitySafetySettingsScreen({super.key, required this.initial});
  @override ConsumerState<CommunitySafetySettingsScreen> createState() => _SafetyState();
}
class _SafetyState extends ConsumerState<CommunitySafetySettingsScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  Future<void> _save(String k, dynamic v) async {
    setState(() => _s[k] = v);
    try { await CommunityRepository().updateSettings('safety', {k: v}); } catch (_) {}
  }
  bool _b(String k) => _s[k] == true || _s[k] == 1;
  String _str(String k, [String d = '']) => _s[k] as String? ?? d;
  List<String> _words() => (_s['hidden_words'] as List?)?.cast<String>() ?? [];

  void _editSensitive() => showModalBottomSheet(
    context: context, backgroundColor: context.colors.cardBg,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    builder: (ctx) {
      final c = context.colors;
      return Padding(padding: EdgeInsets.fromLTRB(20, 20, 20, MediaQuery.of(context).padding.bottom + 20), child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Sensitive Content', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 16, color: c.bodyText)),
        const SizedBox(height: 8),
        Text('Control what kind of sensitive content appears in your feed.', style: TextStyle(fontSize: 12, color: c.subtleText)),
        const SizedBox(height: 16),
        for (final opt in [('Off', 'off', 'No sensitive content filtering'), ('Standard', 'standard', 'Filter most sensitive content'), ('Strict', 'strict', 'Strictly filter sensitive content')])
          ListTile(
            title: Text(opt.$1, style: TextStyle(color: c.bodyText, fontWeight: FontWeight.w600)),
            subtitle: Text(opt.$3, style: TextStyle(color: c.subtleText, fontSize: 11)),
            trailing: _str('sensitive_content', 'standard') == opt.$2 ? const Icon(Icons.check_circle_rounded, color: _kOrange) : null,
            onTap: () { Navigator.pop(ctx); _save('sensitive_content', opt.$2); },
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
          ),
      ]));
    });

  @override
  Widget build(BuildContext context) {
    final words = _words();
    final sensitive = _str('sensitive_content', 'standard');
    final sensitiveLabel = switch (sensitive) { 'off' => 'Off', 'strict' => 'Strict', _ => 'Standard' };

    return Scaffold(
      backgroundColor: context.colors.scaffoldBg, appBar: _bar(context, 'Community Safety'),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _group(context: context, children: [
          _Toggle(icon: Icons.comment_outlined, iconColor: const Color(0xFF3B82F6), label: 'Comment Filter',
            subtitle: 'Filter offensive comments on your posts', value: _b('comment_filter'), onChanged: (v) => _save('comment_filter', v)),
          _Nav(icon: Icons.warning_amber_rounded, iconColor: const Color(0xFFF59E0B), label: 'Sensitive Content',
            trailing: sensitiveLabel, onTap: _editSensitive),
          _Nav(icon: Icons.filter_alt_outlined, iconColor: const Color(0xFF10B981), label: 'Hidden Words',
            trailing: words.isEmpty ? null : '${words.length}',
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _HiddenWordsScreen(words: words, onSave: (w) => _save('hidden_words', w))))),
          _Nav(icon: Icons.security_rounded, iconColor: const Color(0xFF10B981), label: 'Safety Center',
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TransparencyCenter()))),
        ]),
      ]),
    );
  }
}

// Hidden words screen
class _HiddenWordsScreen extends StatefulWidget {
  final List<String> words;
  final ValueChanged<List<String>> onSave;
  const _HiddenWordsScreen({required this.words, required this.onSave});
  @override State<_HiddenWordsScreen> createState() => _HiddenWordsState();
}
class _HiddenWordsState extends State<_HiddenWordsScreen> {
  late List<String> _words;
  final _ctrl = TextEditingController();
  @override void initState() { super.initState(); _words = List.from(widget.words); }
  @override void dispose() { _ctrl.dispose(); super.dispose(); }

  void _add() {
    final w = _ctrl.text.trim().toLowerCase();
    if (w.isEmpty || _words.contains(w)) return;
    setState(() => _words.add(w));
    _ctrl.clear();
    widget.onSave(_words);
  }

  void _remove(String w) {
    setState(() => _words.remove(w));
    widget.onSave(_words);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg, appBar: _bar(context, 'Hidden Words'),
      body: Column(children: [
        Padding(padding: const EdgeInsets.all(16), child: Row(children: [
          Expanded(child: TextField(controller: _ctrl, decoration: const InputDecoration(hintText: 'Add a word or phrase...', prefixIcon: Icon(Icons.add_rounded)),
            onSubmitted: (_) => _add(), style: TextStyle(color: c.bodyText))),
          const SizedBox(width: 10),
          ElevatedButton(onPressed: _add, style: ElevatedButton.styleFrom(minimumSize: const Size(60, 48)), child: const Text('Add')),
        ])),
        Expanded(child: _words.isEmpty
          ? Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.filter_alt_outlined, size: 48, color: c.subtleText),
              const SizedBox(height: 12),
              Text('No hidden words yet', style: TextStyle(color: c.subtleText)),
            ]))
          : ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              itemCount: _words.length,
              separatorBuilder: (_, __) => const SizedBox(height: 4),
              itemBuilder: (_, i) => Material(color: c.cardBg, borderRadius: BorderRadius.circular(10), child: ListTile(
                title: Text(_words[i], style: TextStyle(color: c.bodyText)),
                trailing: IconButton(icon: const Icon(Icons.close_rounded, color: Color(0xFFEF4444)), onPressed: () => _remove(_words[i])),
              )),
            )),
      ]),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// HELP & SUPPORT
// ══════════════════════════════════════════════════════════════════════════
class HelpSettingsScreen extends StatelessWidget {
  const HelpSettingsScreen({super.key});

  void _email(BuildContext ctx, String subject) async {
    final uri = Uri(scheme: 'mailto', path: 'support@esahlan.com', query: 'subject=${Uri.encodeComponent(subject)}');
    if (!await launchUrl(uri)) {
      if (ctx.mounted) ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Cannot open email app')));
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg, appBar: _bar(context, 'Help & Support'),
    body: ListView(padding: const EdgeInsets.all(16), children: [
      _group(context: context, children: [
        _Nav(icon: Icons.support_agent_rounded,    iconColor: const Color(0xFF10B981), label: 'Contact Support',
          subtitle: 'support@esahlan.com', onTap: () => _email(context, 'Support Request')),
        _Nav(icon: Icons.bug_report_outlined,      iconColor: const Color(0xFFEF4444), label: 'Report a Bug',
          onTap: () => _email(context, 'Bug Report')),
        _Nav(icon: Icons.lightbulb_outline_rounded,iconColor: const Color(0xFF8B5CF6), label: 'Feature Request',
          onTap: () => _email(context, 'Feature Request')),
        _Nav(icon: Icons.rate_review_outlined,     iconColor: const Color(0xFF06B6D4), label: 'Send Feedback',
          onTap: () => _email(context, 'App Feedback')),
      ]),
      _group(title: 'CONTACT', context: context, children: [
        _Nav(icon: Icons.email_outlined, iconColor: const Color(0xFF3B82F6), label: 'Email',
          trailing: 'support@esahlan.com',
          trailingWidget: GestureDetector(
            onTap: () { Clipboard.setData(const ClipboardData(text: 'support@esahlan.com')); ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Copied to clipboard'))); },
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Text('support@esahlan.com', style: TextStyle(fontSize: 11, color: context.colors.subtleText)),
              const SizedBox(width: 4),
              Icon(Icons.copy_rounded, size: 14, color: context.colors.subtleText),
            ]),
          ), onTap: () => _email(context, 'Support')),
      ]),
    ]),
  );
}

// ══════════════════════════════════════════════════════════════════════════
// ABOUT
// ══════════════════════════════════════════════════════════════════════════
class AboutSettingsScreen extends StatelessWidget {
  const AboutSettingsScreen({super.key});

  Future<void> _open(BuildContext ctx, String url) async {
    final uri = Uri.parse(url);
    if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (ctx.mounted) ScaffoldMessenger.of(ctx).showSnackBar(const SnackBar(content: Text('Cannot open link')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    const baseUrl = 'https://esahlan.com';
    return Scaffold(
      backgroundColor: c.scaffoldBg, appBar: _bar(context, 'About'),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _group(context: context, children: [
          _Nav(icon: Icons.menu_book_outlined,  iconColor: const Color(0xFF3B82F6), label: 'Community Guidelines',
            onTap: () => _open(context, '$baseUrl/community-guidelines')),
          _Nav(icon: Icons.privacy_tip_outlined,iconColor: const Color(0xFF8B5CF6), label: 'Privacy Policy',
            onTap: () => _open(context, '$baseUrl/privacy-policy')),
          _Nav(icon: Icons.gavel_rounded,       iconColor: const Color(0xFF10B981), label: 'Terms of Service',
            onTap: () => _open(context, '$baseUrl/terms-of-service')),
        ]),
        _group(title: 'APP INFO', context: context, children: [
          _Nav(icon: Icons.info_outline_rounded, iconColor: _kOrange, label: 'App Version',
            trailingWidget: const Text('1.0.0 (1)', style: TextStyle(fontSize: 12, color: Color(0xFF6B7280)))),
          _Nav(icon: Icons.cloud_done_outlined, iconColor: const Color(0xFF10B981), label: 'System Status',
            trailing: 'Operational', onTap: () => _open(context, '$baseUrl/status')),
          _Nav(icon: Icons.web_rounded, iconColor: const Color(0xFF3B82F6), label: 'Website',
            trailing: 'esahlan.com', onTap: () => _open(context, baseUrl)),
        ]),
      ]),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// ACCOUNT SETTINGS — routes to EditProfileScreen
// ══════════════════════════════════════════════════════════════════════════
class AccountSettingsScreen extends ConsumerWidget {
  const AccountSettingsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final profileAsync = ref.watch(_myProfileForSettingsProvider);
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'Account'),
      body: profileAsync.when(
        loading: () => const Center(child: CircularProgressIndicator(color: _kOrange)),
        error: (_, __) => const Center(child: Text('Failed to load')),
        data: (user) => ListView(padding: const EdgeInsets.all(16), children: [
          _group(context: context, children: [
            _Nav(icon: Icons.edit_rounded, iconColor: _kOrange, label: 'Edit Profile',
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EditProfileScreen(user: user)))),
            _Nav(icon: Icons.alternate_email_rounded, iconColor: const Color(0xFF3B82F6), label: 'Username',
              trailing: user.username != null ? '@${user.username}' : 'Not set',
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => EditProfileScreen(user: user)))),
            _Nav(icon: Icons.phone_outlined, iconColor: const Color(0xFF10B981), label: 'Phone Number',
              trailing: user.phone != null ? user.phone!.replaceRange(3, user.phone!.length - 2, '****') : 'Not set',
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _PhoneChangeScreen(current: user.phone)))),
            _Nav(icon: Icons.email_outlined, iconColor: const Color(0xFF8B5CF6), label: 'Email Address',
              trailing: user.email != null && user.email!.isNotEmpty ? user.email!.split('@').first.replaceRange(2, null, '***@${user.email!.split('@').last}') : 'Not set',
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => _EmailChangeScreen(current: user.email)))),
          ]),
          _group(title: 'ACCOUNT CONTROL', context: context, children: [
            _Nav(icon: Icons.download_outlined, iconColor: const Color(0xFF06B6D4), label: 'Download Your Data', onTap: () {}),
            _Nav(icon: Icons.pause_circle_outline_rounded, iconColor: const Color(0xFFF59E0B), label: 'Deactivate Account',
              onTap: () async {
                final ok = await showDialog<bool>(context: context, builder: (dlgCtx) => AlertDialog(
                  title: const Text('Deactivate Account?'),
                  content: const Text('Your profile will be hidden. You can reactivate by logging in again.'),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(dlgCtx, false), child: const Text('Cancel')),
                    TextButton(onPressed: () => Navigator.pop(dlgCtx, true), child: const Text('Deactivate', style: TextStyle(color: Colors.orange))),
                  ],
                ));
                if (ok == true) {
                  await CommunityRepository().deactivateAccount();
                  if (context.mounted) Navigator.of(context).popUntil((r) => r.isFirst);
                }
              }),
            _Nav(icon: Icons.delete_outline_rounded, iconColor: const Color(0xFFEF4444), label: 'Delete Account',
              onTap: () async {
                final ok = await showDialog<bool>(context: context, builder: (dlgCtx) => AlertDialog(
                  title: const Text('Delete Account?'),
                  content: const Text('This action is permanent and cannot be undone. All your data will be removed.'),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(dlgCtx, false), child: const Text('Cancel')),
                    TextButton(onPressed: () => Navigator.pop(dlgCtx, true), child: const Text('Delete Permanently', style: TextStyle(color: Colors.red))),
                  ],
                ));
                if (ok == true) {
                  await CommunityRepository().deleteAccount();
                  if (context.mounted) Navigator.of(context).popUntil((r) => r.isFirst);
                }
              }),
          ]),
        ]),
      ),
    );
  }
}

final _myProfileForSettingsProvider = FutureProvider.autoDispose((ref) =>
    CommunityRepository().getMyProfile());

// ══════════════════════════════════════════════════════════════════════════
// CONTENT PREFERENCES
// ══════════════════════════════════════════════════════════════════════════
class ContentPreferencesScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const ContentPreferencesScreen({super.key, required this.initial});
  @override ConsumerState<ContentPreferencesScreen> createState() => _ContentPrefState();
}
class _ContentPrefState extends ConsumerState<ContentPreferencesScreen> {
  late Map<String, dynamic> _s;
  @override void initState() { super.initState(); _s = Map.from(widget.initial); }
  bool _b(String k) => _s[k] == true || _s[k] == 1;
  void _save(String k, dynamic v) { setState(() => _s[k] = v); CommunityRepository().updateSettings('content', {k: v}); }
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg, appBar: _bar(context, 'Content Preferences'),
    body: ListView(padding: const EdgeInsets.all(16), children: [_group(context: context, children: [
      _Toggle(icon: Icons.translate_rounded, iconColor: const Color(0xFF06B6D4), label: 'Show Posts in Other Languages', subtitle: 'Auto-translate posts', value: _b('auto_translate'), onChanged: (v) => _save('auto_translate', v)),
      _Toggle(icon: Icons.explore_outlined, iconColor: _kOrange, label: 'Suggest Reels', subtitle: 'Show suggested reels in feed', value: _b('suggest_reels'), onChanged: (v) => _save('suggest_reels', v)),
      _Toggle(icon: Icons.recommend_outlined, iconColor: const Color(0xFF8B5CF6), label: 'Personalized Ads', subtitle: 'Use your activity to personalize ads', value: _b('personalized_ads'), onChanged: (v) => _save('personalized_ads', v)),
    ])]),
  );
}

// ══════════════════════════════════════════════════════════════════════════
// LANGUAGE
// ══════════════════════════════════════════════════════════════════════════
class LanguageSettingsScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic> initial;
  const LanguageSettingsScreen({super.key, required this.initial});
  @override ConsumerState<LanguageSettingsScreen> createState() => _LangState();
}
class _LangState extends ConsumerState<LanguageSettingsScreen> {
  late String _code;
  @override void initState() {
    super.initState();
    // Use current app setting (SharedPreferences-backed) not just backend initial
    _code = AppSettingsNotifier.current.language;
    if (_code == 'en' && widget.initial['code'] != null) {
      _code = widget.initial['code'] as String? ?? 'en';
    }
  }

  static const _langs = [
    ('English',  'en'), ('Somali', 'so'), ('Arabic', 'ar'),
    ('Amharic',  'am'), ('Swahili', 'sw'), ('French', 'fr'),
  ];

  Future<void> _select(String code) async {
    setState(() => _code = code);
    ref.read(appSettingsProvider.notifier).setLanguage(code);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg, appBar: _bar(context, 'Language'),
      body: ListView.separated(
        padding: const EdgeInsets.all(16),
        itemCount: _langs.length,
        separatorBuilder: (_, __) => const SizedBox(height: 4),
        itemBuilder: (_, i) {
          final (label, code) = _langs[i];
          final isSelected = code == _code;
          return ListTile(
            tileColor: c.cardBg,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            title: Text(label, style: TextStyle(fontWeight: isSelected ? FontWeight.w700 : FontWeight.normal, color: c.bodyText)),
            trailing: isSelected ? const Icon(Icons.check_rounded, color: _kOrange) : null,
            onTap: () => _select(code),
          );
        },
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// BLOCKED / MUTED / RESTRICTED USER LIST
// ══════════════════════════════════════════════════════════════════════════
class _UserListScreen extends ConsumerStatefulWidget {
  final String type; // 'blocked' | 'muted' | 'restricted'
  const _UserListScreen({required this.type});
  @override ConsumerState<_UserListScreen> createState() => _UserListState();
}
class _UserListState extends ConsumerState<_UserListScreen> {
  late Future<List<dynamic>> _future;
  final Set<int> _removing = {};

  String get _title => switch (widget.type) { 'blocked' => 'Blocked Users', 'muted' => 'Muted Users', _ => 'Restricted Users' };

  @override
  void initState() { super.initState(); _load(); }
  void _load() => _future = switch (widget.type) {
    'blocked'    => CommunityRepository().getBlockedUsers(),
    'muted'      => CommunityRepository().getMutedUsers(),
    _            => CommunityRepository().getRestrictedUsers(),
  };

  Future<void> _remove(int id) async {
    setState(() => _removing.add(id));
    try {
      if (widget.type == 'blocked') {
        await CommunityRepository().unblockUser(id);
      } else if (widget.type == 'muted') {
        await CommunityRepository().unmuteUser(id);
      }
      setState(() { _removing.remove(id); _load(); });
    } catch (_) {
      setState(() => _removing.remove(id));
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, _title),
      body: FutureBuilder<List<dynamic>>(
        future: _future,
        builder: (ctx, snap) {
          if (snap.connectionState == ConnectionState.waiting) return const Center(child: CircularProgressIndicator(color: _kOrange));
          if (snap.hasError) return Center(child: Text('Error: ${snap.error}', style: const TextStyle(color: Colors.red)));
          final users = snap.data ?? [];
          if (users.isEmpty) return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.people_outline_rounded, size: 56, color: c.subtleText),
            const SizedBox(height: 12),
            Text('No ${widget.type} users', style: TextStyle(color: c.subtleText, fontSize: 15)),
          ]));
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: users.length,
            separatorBuilder: (_, __) => const SizedBox(height: 4),
            itemBuilder: (_, i) {
              final u = Map<String, dynamic>.from(users[i] as Map);
              final id = u['id'] as int;
              final isRemoving = _removing.contains(id);
              return Material(color: c.cardBg, borderRadius: BorderRadius.circular(12), child: ListTile(
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                leading: CircleAvatar(
                  backgroundImage: u['avatar'] != null ? NetworkImage(u['avatar'] as String) : null,
                  backgroundColor: _kOrange.withValues(alpha: 0.2),
                  child: u['avatar'] == null ? Text((u['name'] as String? ?? '?')[0].toUpperCase(), style: const TextStyle(color: _kOrange)) : null,
                ),
                title: Text(u['name'] as String? ?? 'Unknown', style: TextStyle(color: c.bodyText, fontWeight: FontWeight.w600, fontSize: 14)),
                trailing: widget.type == 'restricted'
                    ? null
                    : isRemoving
                        ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: _kOrange))
                        : TextButton(
                            onPressed: () => _remove(id),
                            child: Text(widget.type == 'blocked' ? 'Unblock' : 'Unmute',
                              style: const TextStyle(color: _kOrange, fontWeight: FontWeight.w600, fontSize: 13)),
                          ),
              ));
            },
          );
        },
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// LOGIN ACTIVITY
// ══════════════════════════════════════════════════════════════════════════
class _LoginActivityScreen extends StatefulWidget {
  const _LoginActivityScreen();
  @override State<_LoginActivityScreen> createState() => _LoginActivityState();
}
class _LoginActivityState extends State<_LoginActivityScreen> {
  late Future<List<Map<String, dynamic>>> _future;
  @override void initState() { super.initState(); _future = CommunityRepository().getSessions(); }

  IconData _icon(String type) => switch (type) {
    'android' => Icons.android_rounded,
    'ios'     => Icons.phone_iphone_rounded,
    'windows' => Icons.computer_rounded,
    'mac'     => Icons.laptop_mac_rounded,
    _         => Icons.phone_android_rounded,
  };

  String _timeAgo(String? raw) {
    if (raw == null) return 'Unknown';
    final d = DateTime.tryParse(raw);
    if (d == null) return raw.length > 10 ? raw.substring(0, 10) : raw;
    final diff = DateTime.now().difference(d);
    if (diff.inMinutes < 1)  return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24)   return '${diff.inHours}h ago';
    if (diff.inDays < 30)    return '${diff.inDays}d ago';
    return raw.substring(0, 10);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'Login Activity'),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (ctx, snap) {
          if (snap.connectionState == ConnectionState.waiting)
            return const Center(child: CircularProgressIndicator(color: _kOrange));
          final sessions = snap.data ?? [];
          if (sessions.isEmpty) return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.history_rounded, size: 56, color: c.subtleText),
            const SizedBox(height: 12),
            Text('No login history', style: TextStyle(color: c.subtleText)),
          ]));
          return ListView.separated(
            padding: const EdgeInsets.all(16),
            itemCount: sessions.length,
            separatorBuilder: (_, __) => const SizedBox(height: 4),
            itemBuilder: (_, i) {
              final s = sessions[i];
              final isCurrent = s['is_current'] == true;
              final deviceType = s['device_type'] as String? ?? 'mobile';
              final lastUsed = s['last_used'] as String? ?? s['created_at'] as String?;
              return Material(
                color: isCurrent ? _kOrange.withValues(alpha: 0.06) : c.cardBg,
                borderRadius: BorderRadius.circular(12),
                child: ListTile(
                  contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  leading: Container(
                    width: 44, height: 44,
                    decoration: BoxDecoration(
                      color: (isCurrent ? _kOrange : const Color(0xFF3B82F6)).withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(_icon(deviceType), color: isCurrent ? _kOrange : const Color(0xFF3B82F6), size: 22),
                  ),
                  title: Row(children: [
                    Text(s['name'] as String? ?? 'Device', style: TextStyle(fontWeight: FontWeight.w600, color: c.bodyText, fontSize: 14)),
                    if (isCurrent) ...[
                      const SizedBox(width: 8),
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                        decoration: BoxDecoration(color: _kOrange, borderRadius: BorderRadius.circular(6)),
                        child: const Text('This device', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700)),
                      ),
                    ],
                  ]),
                  subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    if (s['ip_address'] != null) Text(s['ip_address'] as String, style: TextStyle(fontSize: 11, color: c.subtleText)),
                    Text('Last active: ${_timeAgo(lastUsed)}', style: TextStyle(fontSize: 11, color: c.subtleText)),
                  ]),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// ACTIVE DEVICES
// ══════════════════════════════════════════════════════════════════════════
class _ActiveDevicesScreen extends StatefulWidget {
  const _ActiveDevicesScreen();
  @override State<_ActiveDevicesScreen> createState() => _ActiveDevicesState();
}
class _ActiveDevicesState extends State<_ActiveDevicesScreen> {
  late Future<List<Map<String, dynamic>>> _future;
  final Set<int> _revoking = {};
  @override void initState() { super.initState(); _load(); }
  void _load() => setState(() { _future = CommunityRepository().getSessions(); });

  IconData _icon(String type) => switch (type) {
    'android' => Icons.android_rounded,
    'ios'     => Icons.phone_iphone_rounded,
    'windows' => Icons.computer_rounded,
    'mac'     => Icons.laptop_mac_rounded,
    _         => Icons.phone_android_rounded,
  };

  Future<void> _revoke(int id) async {
    final ok = await showDialog<bool>(context: context, builder: (dlg) => AlertDialog(
      title: const Text('Sign out device?'),
      content: const Text('This device will be signed out immediately.'),
      actions: [
        TextButton(onPressed: () => Navigator.pop(dlg, false), child: const Text('Cancel')),
        TextButton(onPressed: () => Navigator.pop(dlg, true), child: const Text('Sign Out', style: TextStyle(color: Color(0xFFEF4444)))),
      ],
    ));
    if (ok != true) return;
    setState(() => _revoking.add(id));
    try {
      await CommunityRepository().revokeSession(id);
      _load();
    } finally {
      setState(() => _revoking.remove(id));
    }
  }

  String _timeAgo(String? raw) {
    if (raw == null) return '';
    final d = DateTime.tryParse(raw);
    if (d == null) return '';
    final diff = DateTime.now().difference(d);
    if (diff.inMinutes < 1)  return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24)   return '${diff.inHours}h ago';
    return '${diff.inDays}d ago';
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'Active Devices'),
      body: FutureBuilder<List<Map<String, dynamic>>>(
        future: _future,
        builder: (ctx, snap) {
          if (snap.connectionState == ConnectionState.waiting)
            return const Center(child: CircularProgressIndicator(color: _kOrange));
          final sessions = snap.data ?? [];
          if (sessions.isEmpty) return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(Icons.devices_rounded, size: 56, color: c.subtleText),
            const SizedBox(height: 12),
            Text('No active devices', style: TextStyle(color: c.subtleText)),
          ]));
          return ListView(padding: const EdgeInsets.all(16), children: [
            ...sessions.asMap().entries.map((entry) {
              final i = entry.key; final s = entry.value;
              final id = s['id'] as int;
              final isCurrent = s['is_current'] == true;
              final deviceType = s['device_type'] as String? ?? 'mobile';
              final lastUsed = s['last_used'] as String? ?? s['created_at'] as String?;
              return Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Material(
                  color: isCurrent ? _kOrange.withValues(alpha: 0.06) : c.cardBg,
                  borderRadius: BorderRadius.circular(14),
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Row(children: [
                      Container(
                        width: 48, height: 48,
                        decoration: BoxDecoration(
                          color: (isCurrent ? _kOrange : const Color(0xFF6B7280)).withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Icon(_icon(deviceType), color: isCurrent ? _kOrange : const Color(0xFF6B7280), size: 24),
                      ),
                      const SizedBox(width: 14),
                      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Row(children: [
                          Text(s['name'] as String? ?? 'Device', style: TextStyle(fontWeight: FontWeight.w700, color: c.bodyText, fontSize: 14)),
                          if (isCurrent) ...[
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                              decoration: BoxDecoration(color: _kOrange, borderRadius: BorderRadius.circular(6)),
                              child: const Text('Current', style: TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700)),
                            ),
                          ],
                        ]),
                        if (s['ip_address'] != null) ...[
                          const SizedBox(height: 3),
                          Text(s['ip_address'] as String, style: TextStyle(fontSize: 12, color: c.subtleText)),
                        ],
                        const SizedBox(height: 2),
                        Text('Active ${_timeAgo(lastUsed)}', style: TextStyle(fontSize: 11, color: c.subtleText)),
                      ])),
                      if (!isCurrent)
                        _revoking.contains(id)
                          ? const SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2, color: Color(0xFFEF4444)))
                          : TextButton(
                              onPressed: () => _revoke(id),
                              style: TextButton.styleFrom(foregroundColor: const Color(0xFFEF4444)),
                              child: const Text('Sign Out', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                            ),
                    ]),
                  ),
                ),
              );
            }),
            const SizedBox(height: 8),
            Material(
              color: const Color(0xFFEF4444).withValues(alpha: 0.08),
              borderRadius: BorderRadius.circular(12),
              child: ListTile(
                leading: const Icon(Icons.logout_rounded, color: Color(0xFFEF4444)),
                title: const Text('Sign Out All Other Devices', style: TextStyle(color: Color(0xFFEF4444), fontWeight: FontWeight.w600, fontSize: 14)),
                onTap: () async {
                  final ok = await showDialog<bool>(context: context, builder: (dlg) => AlertDialog(
                    title: const Text('Sign out all devices?'),
                    content: const Text('All other devices will be signed out. You will remain signed in on this device.'),
                    actions: [
                      TextButton(onPressed: () => Navigator.pop(dlg, false), child: const Text('Cancel')),
                      TextButton(onPressed: () => Navigator.pop(dlg, true), child: const Text('Sign Out All', style: TextStyle(color: Colors.red))),
                    ],
                  ));
                  if (ok == true) {
                    await CommunityRepository().logoutAllDevices();
                    if (context.mounted) Navigator.of(context).popUntil((r) => r.isFirst);
                  }
                },
              ),
            ),
          ]);
        },
      ),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// ACCOUNT — PHONE NUMBER CHANGE
// ══════════════════════════════════════════════════════════════════════════
class _PhoneChangeScreen extends StatefulWidget {
  final String? current;
  const _PhoneChangeScreen({this.current});
  @override State<_PhoneChangeScreen> createState() => _PhoneChangeState();
}
class _PhoneChangeState extends State<_PhoneChangeScreen> {
  final _ctrl = TextEditingController();
  bool _loading = false;
  String? _error;

  @override void dispose() { _ctrl.dispose(); super.dispose(); }

  Future<void> _submit() async {
    final phone = _ctrl.text.trim();
    if (phone.isEmpty || phone.length < 7) {
      setState(() => _error = 'Enter a valid phone number');
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      await CommunityRepository().updateAccountInfo(phone: phone);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Phone number updated'), backgroundColor: Color(0xFF10B981)));
      Navigator.pop(context);
    } catch (e) {
      setState(() { _loading = false; _error = 'Failed to update phone number'; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'Phone Number'),
      body: Padding(padding: const EdgeInsets.all(20), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Current: ${widget.current ?? 'Not set'}', style: TextStyle(color: c.subtleText, fontSize: 13)),
        const SizedBox(height: 20),
        TextField(
          controller: _ctrl,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(
            labelText: 'New Phone Number',
            prefixIcon: const Icon(Icons.phone_outlined),
            hintText: '61XXXXXXX',
          ),
          style: TextStyle(color: c.bodyText),
        ),
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_error!, style: const TextStyle(color: Color(0xFFEF4444), fontSize: 12))),
        const SizedBox(height: 24),
        SizedBox(width: double.infinity, child: ElevatedButton(
          onPressed: _loading ? null : _submit,
          child: _loading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Text('Save Phone Number'),
        )),
      ])),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// ACCOUNT — EMAIL CHANGE
// ══════════════════════════════════════════════════════════════════════════
class _EmailChangeScreen extends StatefulWidget {
  final String? current;
  const _EmailChangeScreen({this.current});
  @override State<_EmailChangeScreen> createState() => _EmailChangeState();
}
class _EmailChangeState extends State<_EmailChangeScreen> {
  final _ctrl = TextEditingController();
  bool _loading = false;
  String? _error;

  @override void dispose() { _ctrl.dispose(); super.dispose(); }

  Future<void> _submit() async {
    final email = _ctrl.text.trim();
    if (email.isEmpty || !email.contains('@')) {
      setState(() => _error = 'Enter a valid email address');
      return;
    }
    setState(() { _loading = true; _error = null; });
    try {
      await CommunityRepository().updateAccountInfo(email: email);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Email updated'), backgroundColor: Color(0xFF10B981)));
      Navigator.pop(context);
    } catch (e) {
      setState(() { _loading = false; _error = 'Failed to update email'; });
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.colors;
    return Scaffold(
      backgroundColor: c.scaffoldBg,
      appBar: _bar(context, 'Email Address'),
      body: Padding(padding: const EdgeInsets.all(20), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text('Current: ${widget.current ?? 'Not set'}', style: TextStyle(color: c.subtleText, fontSize: 13)),
        const SizedBox(height: 20),
        TextField(
          controller: _ctrl,
          keyboardType: TextInputType.emailAddress,
          decoration: const InputDecoration(
            labelText: 'New Email Address',
            prefixIcon: Icon(Icons.email_outlined),
            hintText: 'example@email.com',
          ),
          style: TextStyle(color: c.bodyText),
        ),
        if (_error != null) Padding(padding: const EdgeInsets.only(top: 8), child: Text(_error!, style: const TextStyle(color: Color(0xFFEF4444), fontSize: 12))),
        const SizedBox(height: 24),
        SizedBox(width: double.infinity, child: ElevatedButton(
          onPressed: _loading ? null : _submit,
          child: _loading ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)) : const Text('Save Email'),
        )),
      ])),
    );
  }
}

// ══════════════════════════════════════════════════════════════════════════
// BUSINESS SETTINGS
// ══════════════════════════════════════════════════════════════════════════
class BusinessSettingsScreen extends StatelessWidget {
  const BusinessSettingsScreen({super.key});
  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: context.colors.scaffoldBg, appBar: _bar(context, 'Business Center'),
    body: ListView(padding: const EdgeInsets.all(16), children: [_group(context: context, children: [
      _Nav(icon: Icons.store_outlined, iconColor: _kOrange, label: 'My Business Page', onTap: () {}),
      _Nav(icon: Icons.bar_chart_rounded, iconColor: const Color(0xFF3B82F6), label: 'Business Analytics', onTap: () {}),
      _Nav(icon: Icons.campaign_outlined, iconColor: const Color(0xFFF59E0B), label: 'Advertise', onTap: () {}),
      _Nav(icon: Icons.shopping_bag_outlined, iconColor: const Color(0xFF10B981), label: 'Shop Settings', onTap: () {}),
    ])]),
  );
}
