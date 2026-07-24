import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../data/inbox_models.dart';
import '../data/inbox_repository.dart';
import 'inbox_support_screen.dart';
import 'inbox_marketing_screen.dart';

// ── Providers ─────────────────────────────────────────────────────────────────

final _inboxRepoProvider = Provider<InboxRepository>((_) => InboxRepository.create());

final inboxConvsProvider = FutureProvider.autoDispose<List<InboxConversation>>((ref) =>
    ref.read(_inboxRepoProvider).getConversations());

final inboxBroadcastsProvider = FutureProvider.autoDispose<List<MarketingBroadcast>>((ref) =>
    ref.read(_inboxRepoProvider).getBroadcasts());

// ── Design tokens ─────────────────────────────────────────────────────────────

const _kPrimary   = Color(0xFF07003B);
const _kAccent    = Color(0xFF6C63FF);
const _kAccentOr  = Color(0xFFFF8A00);
const _kBgLight   = Color(0xFFF4F6FB);
const _kCardLight = Colors.white;
const _kBgDark    = Color(0xFF0D0D1A);
const _kCardDark  = Color(0xFF181830);

// ── Main Screen ───────────────────────────────────────────────────────────────

class InboxScreen extends ConsumerStatefulWidget {
  const InboxScreen({super.key});

  @override
  ConsumerState<InboxScreen> createState() => _InboxScreenState();
}

class _InboxScreenState extends ConsumerState<InboxScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tab;
  int _selectedTab = 1; // 0=Marketing, 1=Support

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 2, vsync: this, initialIndex: 1);
    _tab.addListener(() { if (mounted) setState(() => _selectedTab = _tab.index); });
  }

  @override
  void dispose() { _tab.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg = isDark ? _kBgDark : _kBgLight;

    return Scaffold(
      backgroundColor: bg,
      body: NestedScrollView(
        headerSliverBuilder: (ctx, _) => [_buildSliverHeader(ctx, isDark)],
        body: TabBarView(
          controller: _tab,
          children: [
            _MarketingTab(repo: ref.read(_inboxRepoProvider)),
            _SupportTab(repo: ref.read(_inboxRepoProvider), onNewRequest: () => _openNewSupport(context)),
          ],
        ),
      ),
      floatingActionButton: _selectedTab == 1
          ? FloatingActionButton.extended(
              onPressed: () => _openNewSupport(context),
              backgroundColor: _kAccent,
              elevation: 3,
              icon: const Icon(Icons.add, color: Colors.white),
              label: const Text('New Request', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
            )
          : null,
    );
  }

  Widget _buildSliverHeader(BuildContext context, bool isDark) {
    final card = isDark ? _kCardDark : _kCardLight;
    return SliverAppBar(
      pinned: true,
      expandedHeight: 130,
      backgroundColor: card,
      elevation: 0,
      shadowColor: Colors.black.withAlpha(15),
      flexibleSpace: FlexibleSpaceBar(
        collapseMode: CollapseMode.pin,
        background: Container(
          decoration: BoxDecoration(
            color: card,
            boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 12, offset: const Offset(0, 2))],
          ),
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    Container(
                      width: 36, height: 36,
                      decoration: BoxDecoration(
                        gradient: const LinearGradient(colors: [_kPrimary, Color(0xFF1a0070)]),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Icon(Icons.inbox_rounded, color: Colors.white, size: 18),
                    ),
                    const SizedBox(width: 10),
                    Text('Inbox', style: TextStyle(
                      fontSize: 22, fontWeight: FontWeight.w900,
                      color: isDark ? Colors.white : _kPrimary,
                      letterSpacing: -.5,
                    )),
                    const Spacer(),
                    _UnreadBadge(),
                  ]),
                  const SizedBox(height: 6),
                  Text('Your messages & notifications',
                      style: TextStyle(fontSize: 12, color: Colors.grey[500])),
                ],
              ),
            ),
          ),
        ),
      ),
      bottom: PreferredSize(
        preferredSize: const Size.fromHeight(44),
        child: Container(
          color: card,
          child: _CustomTabBar(controller: _tab),
        ),
      ),
    );
  }

  void _openNewSupport(BuildContext context) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (_) => _NewSupportSheet(repo: ref.read(_inboxRepoProvider), onCreated: () {
        ref.invalidate(inboxConvsProvider);
      }),
    );
  }
}

// ── Custom tab bar ────────────────────────────────────────────────────────────

class _CustomTabBar extends ConsumerWidget {
  const _CustomTabBar({required this.controller});
  final TabController controller;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final broads  = ref.watch(inboxBroadcastsProvider).whenOrNull(data: (l) => l.where((b) => !b.isRead).length) ?? 0;
    final convs   = ref.watch(inboxConvsProvider).whenOrNull(data: (l) => l.fold<int>(0, (s, c) => s + c.unread)) ?? 0;

    return TabBar(
      controller: controller,
      indicatorColor: _kAccent,
      indicatorWeight: 3,
      labelColor: _kAccent,
      unselectedLabelColor: Colors.grey[500],
      labelStyle: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
      unselectedLabelStyle: const TextStyle(fontWeight: FontWeight.w500, fontSize: 13),
      dividerColor: isDark ? const Color(0xFF252540) : const Color(0xFFEEF0F8),
      tabs: [
        Tab(child: _TabChip('Marketing', broads, Icons.campaign_outlined)),
        Tab(child: _TabChip('Support', convs, Icons.headset_mic_outlined)),
      ],
    );
  }
}

class _TabChip extends StatelessWidget {
  const _TabChip(this.label, this.count, this.icon);
  final String label;
  final int count;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      Icon(icon, size: 15),
      const SizedBox(width: 5),
      Text(label),
      if (count > 0) ...[
        const SizedBox(width: 5),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
          decoration: BoxDecoration(color: _kAccent, borderRadius: BorderRadius.circular(10)),
          child: Text('$count', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800)),
        ),
      ],
    ],
  );
}

class _UnreadBadge extends ConsumerWidget {
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final convs  = ref.watch(inboxConvsProvider).whenOrNull(data: (l) => l.fold<int>(0, (s, c) => s + c.unread)) ?? 0;
    final broads = ref.watch(inboxBroadcastsProvider).whenOrNull(data: (l) => l.where((b) => !b.isRead).length) ?? 0;
    final total  = convs + broads;
    if (total == 0) return const SizedBox.shrink();
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(12)),
      child: Text('$total unread', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800)),
    );
  }
}

// ── Marketing Tab ─────────────────────────────────────────────────────────────

class _MarketingTab extends ConsumerWidget {
  const _MarketingTab({required this.repo});
  final InboxRepository repo;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final async  = ref.watch(inboxBroadcastsProvider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: _kAccent, strokeWidth: 2)),
      error:   (e, _) => _ErrorState('$e', () => ref.invalidate(inboxBroadcastsProvider)),
      data:    (list) {
        if (list.isEmpty) return _EmptyState('No campaigns yet', 'Marketing messages from eSahlan\nwill appear here', Icons.campaign_outlined);
        return RefreshIndicator(
          color: _kAccent,
          onRefresh: () async => ref.invalidate(inboxBroadcastsProvider),
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
            itemCount: list.length,
            itemBuilder: (ctx, i) => _BroadcastCard(
              b: list[i], repo: repo,
              onTap: () => Navigator.push(ctx, MaterialPageRoute(
                builder: (_) => InboxMarketingScreen(broadcast: list[i], repo: repo),
              )).then((_) => ref.invalidate(inboxBroadcastsProvider)),
            ),
          ),
        );
      },
    );
  }
}

class _BroadcastCard extends StatelessWidget {
  const _BroadcastCard({required this.b, required this.repo, required this.onTap});
  final MarketingBroadcast b;
  final InboxRepository repo;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final card   = isDark ? _kCardDark : _kCardLight;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        height: 112,
        decoration: BoxDecoration(
          color: card,
          borderRadius: BorderRadius.circular(14),
          border: b.isRead ? null : Border.all(color: _kAccentOr.withAlpha(100), width: 1.5),
          boxShadow: [BoxShadow(color: Colors.black.withAlpha(isDark ? 20 : 6), blurRadius: 8, offset: const Offset(0, 2))],
        ),
        child: Row(
          children: [
            // Image on the LEFT
            if (b.imageUrl != null)
              ClipRRect(
                borderRadius: const BorderRadius.only(topLeft: Radius.circular(14), bottomLeft: Radius.circular(14)),
                child: Image.network(b.imageUrl!, width: 96, height: 112, fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const SizedBox.shrink()),
              ),
            // Content on the RIGHT
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Row(children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
                        decoration: BoxDecoration(
                          color: _kAccentOr.withAlpha(20),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Row(mainAxisSize: MainAxisSize.min, children: [
                          Icon(Icons.campaign_outlined, size: 10, color: _kAccentOr),
                          const SizedBox(width: 3),
                          Text('Campaign', style: TextStyle(color: _kAccentOr, fontSize: 9, fontWeight: FontWeight.w700)),
                        ]),
                      ),
                      const Spacer(),
                      if (!b.isRead)
                        Container(width: 7, height: 7, decoration: const BoxDecoration(color: _kAccentOr, shape: BoxShape.circle)),
                      if (b.sentAt != null) ...[
                        const SizedBox(width: 5),
                        Text(_timeAgo(b.sentAt!), style: TextStyle(color: Colors.grey[500], fontSize: 10)),
                      ],
                    ]),
                    Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(b.title, maxLines: 1, overflow: TextOverflow.ellipsis,
                          style: TextStyle(fontWeight: b.isRead ? FontWeight.w600 : FontWeight.w800, fontSize: 13,
                              color: isDark ? Colors.white : _kPrimary)),
                      const SizedBox(height: 2),
                      Text(b.body, maxLines: 1, overflow: TextOverflow.ellipsis,
                          style: TextStyle(color: isDark ? Colors.grey[400] : Colors.grey[600], fontSize: 11, height: 1.3)),
                    ]),
                    if (b.ctaLabel != null)
                      Align(
                        alignment: Alignment.center,
                        child: SizedBox(
                          height: 28,
                          child: ElevatedButton(
                            onPressed: onTap,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: _kAccentOr,
                              elevation: 0,
                              padding: const EdgeInsets.symmetric(horizontal: 20),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                            ),
                            child: Text(b.ctaLabel!, style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
                          ),
                        ),
                      )
                    else
                      const SizedBox.shrink(),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _timeAgo(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24)   return '${diff.inHours}h ago';
    return '${diff.inDays}d ago';
  }
}

// ── Support Tab ───────────────────────────────────────────────────────────────

class _SupportTab extends ConsumerWidget {
  const _SupportTab({required this.repo, required this.onNewRequest});
  final InboxRepository repo;
  final VoidCallback onNewRequest;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(inboxConvsProvider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: _kAccent, strokeWidth: 2)),
      error:   (e, _) => _ErrorState('$e', () => ref.invalidate(inboxConvsProvider)),
      data:    (list) {
        if (list.isEmpty) return _EmptyState(
          'No support tickets yet',
          'Start a conversation with our support team',
          Icons.support_agent_outlined,
          action: onNewRequest,
          actionLabel: 'New Request',
        );
        return RefreshIndicator(
          color: _kAccent,
          onRefresh: () async => ref.invalidate(inboxConvsProvider),
          child: ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 100),
            itemCount: list.length,
            itemBuilder: (ctx, i) => _ConvCard(
              conv: list[i],
              onTap: () => Navigator.push(ctx, MaterialPageRoute(
                builder: (_) => InboxSupportScreen(conversation: list[i], repo: repo),
              )).then((_) => ref.invalidate(inboxConvsProvider)),
            ),
          ),
        );
      },
    );
  }
}

class _ConvCard extends StatelessWidget {
  const _ConvCard({required this.conv, required this.onTap});
  final InboxConversation conv;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final card   = isDark ? _kCardDark : _kCardLight;
    final mod    = kSupportModules.firstWhere((m) => m.id == conv.module, orElse: () => kSupportModules.first);
    final (statusColor, statusLabel) = _statusInfo(conv.status);

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(
          color: card,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [BoxShadow(color: Colors.black.withAlpha(isDark ? 18 : 5), blurRadius: 10, offset: const Offset(0, 2))],
        ),
        child: Column(children: [
          // Status color bar
          Container(
            height: 3,
            decoration: BoxDecoration(
              color: statusColor,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Row(children: [
              // Module icon
              Container(
                width: 50, height: 50,
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: [statusColor.withAlpha(50), statusColor.withAlpha(20)],
                    begin: Alignment.topLeft, end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Center(child: Text(mod.icon, style: const TextStyle(fontSize: 22))),
              ),
              const SizedBox(width: 12),
              Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Expanded(child: Text(
                    conv.subject ?? mod.label,
                    style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: isDark ? Colors.white : _kPrimary),
                    maxLines: 1, overflow: TextOverflow.ellipsis,
                  )),
                  if (conv.lastMessageAt != null)
                    Text(_timeAgo(conv.lastMessageAt!), style: TextStyle(color: Colors.grey[500], fontSize: 11)),
                ]),
                const SizedBox(height: 4),
                Text(
                  conv.lastMessage ?? 'Tap to open conversation',
                  maxLines: 1, overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: isDark ? Colors.grey[400] : Colors.grey[600],
                    fontSize: 12,
                    fontWeight: conv.unread > 0 ? FontWeight.w600 : FontWeight.normal,
                  ),
                ),
                const SizedBox(height: 8),
                Row(children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                    decoration: BoxDecoration(
                      color: statusColor.withAlpha(20),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(statusLabel, style: TextStyle(color: statusColor, fontSize: 10, fontWeight: FontWeight.w700)),
                  ),
                  const Spacer(),
                  if (conv.unread > 0)
                    Container(
                      width: 22, height: 22,
                      decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(11)),
                      child: Center(child: Text('${conv.unread}',
                          style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w800))),
                    ),
                ]),
              ])),
            ]),
          ),
        ]),
      ),
    );
  }

  (Color, String) _statusInfo(String s) {
    switch (s) {
      case 'open':     return (const Color(0xFF6C63FF), 'Open');
      case 'assigned': return (const Color(0xFF00BFA5), 'In Progress');
      case 'resolved': return (const Color(0xFF16a34a), 'Resolved');
      default:         return (Colors.grey, 'Closed');
    }
  }

  String _timeAgo(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 1)  return 'Just now';
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24)   return '${diff.inHours}h ago';
    return '${diff.inDays}d ago';
  }
}

// ── New Support Sheet ─────────────────────────────────────────────────────────

class _NewSupportSheet extends StatefulWidget {
  const _NewSupportSheet({required this.repo, required this.onCreated});
  final InboxRepository repo;
  final VoidCallback onCreated;

  @override
  State<_NewSupportSheet> createState() => _NewSupportSheetState();
}

class _NewSupportSheetState extends State<_NewSupportSheet> {
  int _step = 0; // 0 = pick module, 1 = fill form
  SupportModule? _module;
  final _subjectCtrl = TextEditingController();
  final _msgCtrl     = TextEditingController();
  bool _loading = false;

  @override
  void dispose() { _subjectCtrl.dispose(); _msgCtrl.dispose(); super.dispose(); }

  Widget _buildHeader(BuildContext context, bool isDark) {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 16),
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: isDark ? const Color(0xFF252540) : const Color(0xFFEEF0F8))),
      ),
      child: Row(children: [
        if (_step == 1)
          GestureDetector(
            onTap: () => setState(() => _step = 0),
            child: Container(
              width: 30, height: 30, margin: const EdgeInsets.only(right: 10),
              decoration: BoxDecoration(
                color: isDark ? const Color(0xFF252540) : _kBgLight,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Icon(Icons.arrow_back_ios_rounded, size: 14, color: Colors.grey[600]),
            ),
          )
        else ...[
          Container(
            width: 36, height: 36,
            decoration: BoxDecoration(
              gradient: const LinearGradient(colors: [_kPrimary, Color(0xFF1a0070)]),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.headset_mic_outlined, color: Colors.white, size: 18),
          ),
          const SizedBox(width: 10),
        ],
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text('New Support Request', style: TextStyle(
            fontSize: 16, fontWeight: FontWeight.w800,
            color: isDark ? Colors.white : _kPrimary,
          )),
          Text(_step == 0 ? 'Step 1 of 2 — Choose a module' : 'Step 2 of 2 — Describe your issue',
            style: TextStyle(fontSize: 11, color: Colors.grey[500])),
        ])),
        GestureDetector(
          onTap: () => Navigator.pop(context),
          child: Container(
            width: 30, height: 30,
            decoration: BoxDecoration(
              color: isDark ? const Color(0xFF252540) : _kBgLight,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(Icons.close, size: 16, color: Colors.grey[600]),
          ),
        ),
      ]),
    );
  }

  Widget _buildStepIndicator() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 0),
      child: Row(children: [
        Expanded(child: Container(height: 3, decoration: BoxDecoration(
          color: _kAccent, borderRadius: BorderRadius.circular(2)))),
        const SizedBox(width: 6),
        Expanded(child: Container(height: 3, decoration: BoxDecoration(
          color: _step == 1 ? _kAccent : _kAccent.withAlpha(40),
          borderRadius: BorderRadius.circular(2)))),
      ]),
    );
  }

  Widget _buildModuleStep(BuildContext context, bool isDark) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Expanded(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
          child: GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 4, crossAxisSpacing: 8, mainAxisSpacing: 8, childAspectRatio: 0.95,
            ),
            itemCount: kSupportModules.length,
            itemBuilder: (_, i) {
              final m = kSupportModules[i];
              final sel = _module?.id == m.id;
              return GestureDetector(
                onTap: () => setState(() => _module = m),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 180),
                  decoration: BoxDecoration(
                    color: sel ? _kAccent : (isDark ? const Color(0xFF252540) : _kBgLight),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: sel ? _kAccent : (isDark ? const Color(0xFF333355) : const Color(0xFFDDE2F0)),
                      width: sel ? 2 : 1,
                    ),
                    boxShadow: sel ? [BoxShadow(color: _kAccent.withAlpha(60), blurRadius: 6, offset: const Offset(0, 2))] : [],
                  ),
                  child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                    Text(m.icon, style: const TextStyle(fontSize: 20)),
                    const SizedBox(height: 4),
                    Text(m.label, textAlign: TextAlign.center, style: TextStyle(
                      fontSize: 9, fontWeight: FontWeight.w700,
                      color: sel ? Colors.white : (isDark ? Colors.grey[300] : const Color(0xFF374151)),
                    ), maxLines: 2, overflow: TextOverflow.ellipsis),
                  ]),
                ),
              );
            },
          ),
        ),
      ),
      // Next button
      Padding(
        padding: EdgeInsets.fromLTRB(16, 8, 16, MediaQuery.of(context).padding.bottom + 20),
        child: SizedBox(
          width: double.infinity, height: 52,
          child: ElevatedButton(
            onPressed: _module == null ? null : () => setState(() => _step = 1),
            style: ElevatedButton.styleFrom(
              backgroundColor: _kAccent,
              disabledBackgroundColor: _kAccent.withAlpha(60),
              elevation: 0,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
            ),
            child: Row(mainAxisAlignment: MainAxisAlignment.center, children: [
              Text(
                _module == null ? 'Select a module to continue' : 'Continue with ${_module!.label}',
                style: TextStyle(
                  color: _module == null ? Colors.white54 : Colors.white,
                  fontWeight: FontWeight.w800, fontSize: 14,
                ),
              ),
              if (_module != null) ...[
                const SizedBox(width: 6),
                const Icon(Icons.arrow_forward_rounded, color: Colors.white, size: 18),
              ],
            ]),
          ),
        ),
      ),
    ]);
  }

  Widget _buildFormStep(BuildContext context, bool isDark) {
    return Expanded(
      child: Column(children: [
        // Selected module chip
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 9),
            decoration: BoxDecoration(
              color: _kAccent.withAlpha(15),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _kAccent.withAlpha(60)),
            ),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Text(_module!.icon, style: const TextStyle(fontSize: 16)),
              const SizedBox(width: 8),
              Text(_module!.label, style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13, color: _kAccent)),
              const Spacer(),
              GestureDetector(
                onTap: () => setState(() => _step = 0),
                child: const Text('Change', style: TextStyle(fontSize: 11, color: _kAccent, fontWeight: FontWeight.w600)),
              ),
            ]),
          ),
        ),
        Expanded(
          child: SingleChildScrollView(
            padding: EdgeInsets.fromLTRB(16, 16, 16, MediaQuery.of(context).viewInsets.bottom + 16),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              _SectionTitle('Subject', Icons.title_rounded),
              const SizedBox(height: 8),
              _StyledField(controller: _subjectCtrl, hint: 'Brief description of your issue', isDark: isDark),
              const SizedBox(height: 16),
              _SectionTitle('Describe your issue', Icons.chat_bubble_outline_rounded),
              const SizedBox(height: 8),
              _StyledField(controller: _msgCtrl, hint: 'Please provide as much detail as possible…', maxLines: 5, isDark: isDark),
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity, height: 52,
                child: ElevatedButton(
                  onPressed: _loading ? null : _submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: _kAccent,
                    disabledBackgroundColor: _kAccent.withAlpha(100),
                    elevation: 0,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  child: _loading
                      ? const SizedBox(width: 22, height: 22,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2.5))
                      : const Row(mainAxisAlignment: MainAxisAlignment.center, children: [
                          Icon(Icons.send_rounded, color: Colors.white, size: 18),
                          SizedBox(width: 8),
                          Text('Submit Request', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15)),
                        ]),
                ),
              ),
            ]),
          ),
        ),
      ]),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final bg     = isDark ? _kCardDark : Colors.white;

    return Container(
      height: MediaQuery.of(context).size.height * 0.92,
      decoration: BoxDecoration(
        color: bg,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(children: [
        const SizedBox(height: 10),
        Container(width: 36, height: 4, decoration: BoxDecoration(
          color: Colors.grey[300], borderRadius: BorderRadius.circular(2))),
        _buildHeader(context, isDark),
        _buildStepIndicator(),
        const SizedBox(height: 4),
        if (_step == 0) Expanded(child: _buildModuleStep(context, isDark))
        else _buildFormStep(context, isDark),
      ]),
    );
  }

  Future<void> _submit() async {
    if (_module == null) { _toast('Please select a module'); return; }
    if (_subjectCtrl.text.trim().isEmpty) { _toast('Please enter a subject'); return; }
    if (_msgCtrl.text.trim().isEmpty) { _toast('Please describe your issue'); return; }

    setState(() => _loading = true);
    try {
      await widget.repo.createConversation(
        module:  _module!.id,
        subject: _subjectCtrl.text.trim(),
        message: _msgCtrl.text.trim(),
      );
      widget.onCreated();
      if (mounted) Navigator.of(context).pop();
      _toast('Support request submitted!');
    } catch (_) {
      _toast('Failed to submit. Please try again.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _toast(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(
      content: Text(msg),
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
    ));
  }
}

// ── Helper widgets ────────────────────────────────────────────────────────────

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text, this.icon);
  final String text; final IconData icon;

  @override
  Widget build(BuildContext context) => Row(children: [
    Icon(icon, size: 15, color: _kAccent),
    const SizedBox(width: 6),
    Text(text, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800, color: _kAccent, letterSpacing: .3)),
  ]);
}

class _StyledField extends StatelessWidget {
  const _StyledField({required this.controller, required this.hint, this.maxLines = 1, required this.isDark});
  final TextEditingController controller;
  final String hint;
  final int maxLines;
  final bool isDark;

  @override
  Widget build(BuildContext context) => TextField(
    controller: controller,
    maxLines: maxLines,
    style: TextStyle(color: isDark ? Colors.white : _kPrimary, fontSize: 14),
    decoration: InputDecoration(
      hintText: hint,
      hintStyle: TextStyle(color: Colors.grey[500], fontSize: 13),
      filled: true,
      fillColor: isDark ? const Color(0xFF252540) : _kBgLight,
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: isDark ? const Color(0xFF333355) : const Color(0xFFDDE2F0)),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: isDark ? const Color(0xFF333355) : const Color(0xFFDDE2F0)),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: _kAccent, width: 2),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState(this.title, this.subtitle, this.icon, {this.action, this.actionLabel});
  final String title; final String subtitle; final IconData icon;
  final VoidCallback? action;
  final String? actionLabel;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Container(
            width: 80, height: 80,
            decoration: BoxDecoration(
              color: _kAccent.withAlpha(20),
              borderRadius: BorderRadius.circular(24),
            ),
            child: Icon(icon, size: 36, color: _kAccent),
          ),
          const SizedBox(height: 20),
          Text(title, style: TextStyle(
            fontSize: 16, fontWeight: FontWeight.w800,
            color: isDark ? Colors.white : _kPrimary,
          )),
          const SizedBox(height: 8),
          Text(subtitle, textAlign: TextAlign.center,
              style: TextStyle(fontSize: 13, color: Colors.grey[500], height: 1.5)),
          if (action != null) ...[
            const SizedBox(height: 24),
            ElevatedButton.icon(
              onPressed: action,
              icon: const Icon(Icons.add, size: 18),
              label: Text(actionLabel ?? 'New Request', style: const TextStyle(fontWeight: FontWeight.w700)),
              style: ElevatedButton.styleFrom(
                backgroundColor: _kAccent,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 14),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                elevation: 0,
              ),
            ),
          ],
        ]),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  const _ErrorState(this.msg, this.onRetry);
  final String msg; final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      const Icon(Icons.error_outline, size: 48, color: Colors.red),
      const SizedBox(height: 12),
      Text(msg, textAlign: TextAlign.center, style: const TextStyle(color: Colors.grey, fontSize: 13)),
      const SizedBox(height: 16),
      TextButton(onPressed: onRetry, child: const Text('Retry', style: TextStyle(color: _kAccent, fontWeight: FontWeight.w700))),
    ]),
  );
}
