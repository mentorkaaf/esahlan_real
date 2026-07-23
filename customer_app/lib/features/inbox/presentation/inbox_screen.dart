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

final unreadCountProvider = FutureProvider.autoDispose<int>((ref) async {
  final convs   = await ref.watch(inboxConvsProvider.future);
  final broads  = await ref.watch(inboxBroadcastsProvider.future);
  final convUnread = convs.fold<int>(0, (s, c) => s + c.unread);
  final brdUnread  = broads.where((b) => !b.isRead).length;
  return convUnread + brdUnread;
});

// ── Main Screen ───────────────────────────────────────────────────────────────

class InboxScreen extends ConsumerStatefulWidget {
  const InboxScreen({super.key});

  @override
  ConsumerState<InboxScreen> createState() => _InboxScreenState();
}

class _InboxScreenState extends ConsumerState<InboxScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tab;

  @override
  void initState() {
    super.initState();
    _tab = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final bg     = isDark ? const Color(0xFF0F0F1A) : const Color(0xFFF5F6FA);
    const accent = Color(0xFF6C63FF);

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: isDark ? const Color(0xFF1A1A2E) : Colors.white,
        elevation: 0,
        title: Text('Messages', style: TextStyle(
          color: isDark ? Colors.white : const Color(0xFF07003B),
          fontWeight: FontWeight.w700, fontSize: 18,
        )),
        actions: [
          IconButton(
            icon: const Icon(Icons.headset_mic_outlined, color: accent),
            tooltip: 'New Support Request',
            onPressed: () => _openNewSupport(context),
          ),
        ],
        bottom: TabBar(
          controller: _tab,
          indicatorColor: accent,
          labelColor: accent,
          unselectedLabelColor: Colors.grey,
          tabs: [
            Tab(child: _TabLabel('Marketing', inboxBroadcastsProvider, (b) => b.where((x) => !x.isRead).length)),
            Tab(child: _TabLabel('Support', inboxConvsProvider, (c) => c.fold<int>(0, (s, x) => s + x.unread))),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tab,
        children: [
          _MarketingTab(repo: ref.read(_inboxRepoProvider)),
          _SupportTab(repo: ref.read(_inboxRepoProvider)),
        ],
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

// ── Tab label with badge ──────────────────────────────────────────────────────

class _TabLabel<T> extends ConsumerWidget {
  const _TabLabel(this.label, this.provider, this.countFn);
  final String label;
  final ProviderBase<AsyncValue<T>> provider;
  final int Function(T) countFn;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(provider);
    final count = async.whenOrNull(data: countFn) ?? 0;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(label),
        if (count > 0) ...[
          const SizedBox(width: 6),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
            decoration: BoxDecoration(
              color: const Color(0xFF6C63FF),
              borderRadius: BorderRadius.circular(8),
            ),
            child: Text('$count', style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700)),
          ),
        ],
      ],
    );
  }
}

// ── Marketing Tab ─────────────────────────────────────────────────────────────

class _MarketingTab extends ConsumerWidget {
  const _MarketingTab({required this.repo});
  final InboxRepository repo;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(inboxBroadcastsProvider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: Color(0xFF6C63FF))),
      error: (e, _) => Center(child: Text('Error: $e')),
      data: (list) {
        if (list.isEmpty) return _EmptyState('No marketing messages yet', Icons.campaign_outlined);
        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(inboxBroadcastsProvider),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: list.length,
            itemBuilder: (ctx, i) => _BroadcastCard(
              b: list[i], repo: repo,
              onTap: () {
                Navigator.push(ctx, MaterialPageRoute(
                  builder: (_) => InboxMarketingScreen(broadcast: list[i], repo: repo),
                )).then((_) => ref.invalidate(inboxBroadcastsProvider));
              },
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
    final card   = isDark ? const Color(0xFF1A1A2E) : Colors.white;

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(
          color: card,
          borderRadius: BorderRadius.circular(14),
          border: b.isRead ? null : Border.all(color: const Color(0xFF6C63FF).withAlpha(120), width: 1.5),
          boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 8, offset: const Offset(0,2))],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (b.imageUrl != null)
              ClipRRect(
                borderRadius: const BorderRadius.vertical(top: Radius.circular(14)),
                child: Image.network(b.imageUrl!, height: 150, width: double.infinity, fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const SizedBox.shrink()),
              ),
            Padding(
              padding: const EdgeInsets.all(14),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(children: [
                    if (!b.isRead) Container(
                      width: 8, height: 8,
                      margin: const EdgeInsets.only(right: 6),
                      decoration: const BoxDecoration(color: Color(0xFF6C63FF), shape: BoxShape.circle),
                    ),
                    Expanded(child: Text(b.title,
                        style: TextStyle(fontWeight: b.isRead ? FontWeight.w500 : FontWeight.w700, fontSize: 14))),
                    if (b.sentAt != null)
                      Text(_timeAgo(b.sentAt!), style: TextStyle(color: Colors.grey[500], fontSize: 11)),
                  ]),
                  const SizedBox(height: 6),
                  Text(b.body, maxLines: 2, overflow: TextOverflow.ellipsis,
                      style: TextStyle(color: Colors.grey[600], fontSize: 12)),
                  if (b.ctaLabel != null) ...[
                    const SizedBox(height: 10),
                    SizedBox(
                      width: double.infinity,
                      height: 36,
                      child: ElevatedButton(
                        onPressed: onTap,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFF6C63FF),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        child: Text(b.ctaLabel!, style: const TextStyle(color: Colors.white, fontSize: 12)),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _timeAgo(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 60) return '${diff.inMinutes}m';
    if (diff.inHours < 24)   return '${diff.inHours}h';
    return '${diff.inDays}d';
  }
}

// ── Support Tab ───────────────────────────────────────────────────────────────

class _SupportTab extends ConsumerWidget {
  const _SupportTab({required this.repo});
  final InboxRepository repo;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(inboxConvsProvider);
    return async.when(
      loading: () => const Center(child: CircularProgressIndicator(color: Color(0xFF6C63FF))),
      error: (e, _) => Center(child: Text('Error: $e')),
      data: (list) {
        if (list.isEmpty) return _EmptyState('No support conversations\nTap the headset icon to start', Icons.support_agent_outlined);
        return RefreshIndicator(
          onRefresh: () async => ref.invalidate(inboxConvsProvider),
          child: ListView.builder(
            padding: const EdgeInsets.all(16),
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
    final card   = isDark ? const Color(0xFF1A1A2E) : Colors.white;
    final mod    = kSupportModules.firstWhere((m) => m.id == conv.module, orElse: () => kSupportModules.first);

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: card,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 8, offset: const Offset(0,2))],
        ),
        child: Row(
          children: [
            Container(
              width: 46, height: 46,
              decoration: BoxDecoration(
                color: _statusColor(conv.status).withAlpha(30),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Center(child: Text(mod.icon, style: const TextStyle(fontSize: 22))),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(child: Text(conv.subject ?? mod.label,
                          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13),
                          maxLines: 1, overflow: TextOverflow.ellipsis)),
                      if (conv.lastMessageAt != null)
                        Text(_timeAgo(conv.lastMessageAt!),
                            style: TextStyle(color: Colors.grey[500], fontSize: 11)),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      Expanded(
                        child: Text(conv.lastMessage ?? '...',
                            maxLines: 1, overflow: TextOverflow.ellipsis,
                            style: TextStyle(color: Colors.grey[600], fontSize: 12)),
                      ),
                      if (conv.unread > 0) Container(
                        padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                        decoration: BoxDecoration(
                          color: const Color(0xFF6C63FF),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Text('${conv.unread}',
                            style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w700)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  _StatusChip(conv.status),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Color _statusColor(String s) {
    switch (s) {
      case 'open':     return const Color(0xFF6C63FF);
      case 'assigned': return const Color(0xFF00BFA5);
      case 'resolved': return Colors.green;
      default:         return Colors.grey;
    }
  }

  String _timeAgo(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 60) return '${diff.inMinutes}m';
    if (diff.inHours < 24)   return '${diff.inHours}h';
    return '${diff.inDays}d';
  }
}

class _StatusChip extends StatelessWidget {
  const _StatusChip(this.status);
  final String status;

  @override
  Widget build(BuildContext context) {
    final colors = {
      'open':     (const Color(0xFF6C63FF), 'Open'),
      'assigned': (const Color(0xFF00BFA5), 'In Progress'),
      'resolved': (Colors.green,             'Resolved'),
      'closed':   (Colors.grey,              'Closed'),
    };
    final (color, label) = colors[status] ?? (Colors.grey, status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: color.withAlpha(25),
        borderRadius: BorderRadius.circular(6),
      ),
      child: Text(label, style: TextStyle(color: color, fontSize: 10, fontWeight: FontWeight.w600)),
    );
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
  SupportModule? _module;
  final _subjectCtrl = TextEditingController();
  final _msgCtrl     = TextEditingController();
  bool _loading = false;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return Container(
      height: MediaQuery.of(context).size.height * 0.85,
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1A1A2E) : Colors.white,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        children: [
          const SizedBox(height: 8),
          Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.grey[300], borderRadius: BorderRadius.circular(2))),
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Text('New Support Request', style: TextStyle(
              fontSize: 16, fontWeight: FontWeight.w700,
              color: isDark ? Colors.white : const Color(0xFF07003B),
            )),
          ),
          const SizedBox(height: 16),
          Expanded(
            child: SingleChildScrollView(
              padding: EdgeInsets.fromLTRB(20, 0, 20, MediaQuery.of(context).viewInsets.bottom + 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _Label('Select Module'),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8, runSpacing: 8,
                    children: kSupportModules.map((m) => GestureDetector(
                      onTap: () => setState(() => _module = m),
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: _module?.id == m.id
                              ? const Color(0xFF6C63FF)
                              : (isDark ? const Color(0xFF0F0F1A) : const Color(0xFFF5F6FA)),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: _module?.id == m.id
                                ? const Color(0xFF6C63FF)
                                : (isDark ? const Color(0xFF2A2A3E) : Colors.grey.shade300),
                          ),
                        ),
                        child: Row(mainAxisSize: MainAxisSize.min, children: [
                          Text(m.icon, style: const TextStyle(fontSize: 14)),
                          const SizedBox(width: 6),
                          Text(m.label, style: TextStyle(
                            fontSize: 12, fontWeight: FontWeight.w500,
                            color: _module?.id == m.id ? Colors.white : null,
                          )),
                        ]),
                      ),
                    )).toList(),
                  ),
                  const SizedBox(height: 16),
                  _Label('Subject'),
                  const SizedBox(height: 8),
                  _Field(controller: _subjectCtrl, hint: 'Brief description of your issue'),
                  const SizedBox(height: 12),
                  _Label('Describe your issue'),
                  const SizedBox(height: 8),
                  _Field(controller: _msgCtrl, hint: 'Please provide details...', maxLines: 5),
                  const SizedBox(height: 24),
                  SizedBox(
                    width: double.infinity, height: 48,
                    child: ElevatedButton(
                      onPressed: _loading ? null : _submit,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: const Color(0xFF6C63FF),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                      child: _loading
                          ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                          : const Text('Submit Request', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700)),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _submit() async {
    if (_module == null) { _toast('Please select a module'); return; }
    if (_subjectCtrl.text.trim().isEmpty) { _toast('Please enter a subject'); return; }
    if (_msgCtrl.text.trim().isEmpty) { _toast('Please describe your issue'); return; }

    setState(() => _loading = true);
    try {
      await widget.repo.createConversation(
        module: _module!.id,
        subject: _subjectCtrl.text.trim(),
        message: _msgCtrl.text.trim(),
      );
      widget.onCreated();
      if (mounted) Navigator.of(context).pop();
      _toast('Support request submitted!');
    } catch (e) {
      _toast('Failed to submit. Please try again.');
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _toast(String msg) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(msg)));
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

class _EmptyState extends StatelessWidget {
  const _EmptyState(this.msg, this.icon);
  final String msg; final IconData icon;

  @override
  Widget build(BuildContext context) => Center(
    child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
      Icon(icon, size: 64, color: Colors.grey[400]),
      const SizedBox(height: 12),
      Text(msg, textAlign: TextAlign.center, style: TextStyle(color: Colors.grey[500], fontSize: 14)),
    ]),
  );
}

class _Label extends StatelessWidget {
  const _Label(this.text);
  final String text;
  @override
  Widget build(BuildContext context) => Text(text,
      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: Color(0xFF6C63FF)));
}

class _Field extends StatelessWidget {
  const _Field({required this.controller, required this.hint, this.maxLines = 1});
  final TextEditingController controller;
  final String hint;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return TextField(
      controller: controller,
      maxLines: maxLines,
      style: TextStyle(color: isDark ? Colors.white : const Color(0xFF07003B), fontSize: 13),
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: TextStyle(color: Colors.grey[500], fontSize: 13),
        filled: true,
        fillColor: isDark ? const Color(0xFF0F0F1A) : const Color(0xFFF5F6FA),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide.none,
        ),
        contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      ),
    );
  }
}
