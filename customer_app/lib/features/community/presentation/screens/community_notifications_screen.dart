import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../data/models/community_models.dart';
import '../providers/community_provider.dart';
import 'package:go_router/go_router.dart';
import 'community_shell.dart';
import 'community_chat_screen.dart';

class CommunityNotificationsScreen extends ConsumerStatefulWidget {
  const CommunityNotificationsScreen({super.key});

  @override
  ConsumerState<CommunityNotificationsScreen> createState() => _State();
}

class _State extends ConsumerState<CommunityNotificationsScreen>
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

  void _navigateNotif(BuildContext context, CommunityNotification n) {
    switch (n.type) {
      case 'like':
      case 'comment':
      case 'share':
      case 'mention':
        if (n.notifiableId != null) context.push('/community/post/${n.notifiableId}');
        break;
      case 'follow':
        if (n.actor != null) context.push('/community/profile/${n.actor!.id}');
        break;
      case 'message':
        if (n.notifiableId != null) {
          final chatId = n.notifiableId!;
          final chat = CommunityChat(id: chatId, type: 'direct', otherUser: n.actor);
          Navigator.push(context, MaterialPageRoute(builder: (_) => CommunityChatScreen(chat: chat)));
        }
        break;
      default:
        if (n.actor != null) context.push('/community/profile/${n.actor!.id}');
    }
  }

  @override
  Widget build(BuildContext context) {
    final notifsAsync = ref.watch(communityNotifProvider);

    return Scaffold(
      
      appBar: AppBar(
        
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF1A1B2E)),
          onPressed: () => Navigator.pop(context),
        ),
        title: const Text('Notifications',
            style: TextStyle(color: Color(0xFF1A1B2E), fontWeight: FontWeight.w800, fontSize: 20)),
        actions: [
          GestureDetector(
            onTap: () => ref.read(communityNotifProvider.notifier).markAllRead(),
            child: Container(
              margin: const EdgeInsets.only(right: 12),
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: kOrange.withOpacity(0.1),
                borderRadius: BorderRadius.circular(20),
              ),
              child: const Text('Mark all read',
                  style: TextStyle(color: kOrange, fontWeight: FontWeight.w600, fontSize: 12)),
            ),
          ),
        ],
        bottom: TabBar(
          controller: _tab,
          indicatorColor: kOrange,
          indicatorWeight: 3,
          labelColor: Colors.white,
          unselectedLabelColor: const Color(0xFF6B7280),
          indicator: BoxDecoration(
            color: kOrange,
            borderRadius: BorderRadius.circular(20),
          ),
          labelPadding: const EdgeInsets.symmetric(horizontal: 0),
          tabs: const [
            Tab(child: Center(child: Text('All', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)))),
            Tab(child: Center(child: Text('Mentions', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)))),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tab,
        children: [
          _NotifList(notifsAsync: notifsAsync, filter: null),
          _NotifList(notifsAsync: notifsAsync, filter: 'mention'),
        ],
      ),
    );
  }
}

class _NotifList extends ConsumerWidget {
  final AsyncValue<List<CommunityNotification>> notifsAsync;
  final String? filter;
  const _NotifList({required this.notifsAsync, required this.filter});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return notifsAsync.when(
      loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
      error: (e, _) => Center(child: Text('Error: $e', style: const TextStyle(color: Colors.red))),
      data: (all) {
        final notifs = filter == null ? all : all.where((n) => n.type == filter).toList();

        if (notifs.isEmpty) {
          return Center(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Container(
                width: 70, height: 70,
                decoration: const BoxDecoration(color: Color(0xFFF0F2F5), shape: BoxShape.circle),
                child: const Icon(Icons.notifications_rounded, size: 35, color: Color(0xFFD1D5DB)),
              ),
              const SizedBox(height: 14),
              const Text('No notifications yet',
                  style: TextStyle(color: Color(0xFF1A1B2E), fontSize: 16, fontWeight: FontWeight.w700)),
              const SizedBox(height: 6),
              const Text('When people interact with you, it will show here',
                  style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 13), textAlign: TextAlign.center),
            ]),
          );
        }

        final today = DateTime.now();
        final todayN = notifs.where((n) => _isToday(n.createdAt, today)).toList();
        final weekN = notifs.where((n) => !_isToday(n.createdAt, today) && _isThisWeek(n.createdAt, today)).toList();
        final olderN = notifs.where((n) => !_isThisWeek(n.createdAt, today)).toList();

        return RefreshIndicator(
          color: kOrange,
          onRefresh: () => ref.read(communityNotifProvider.notifier).load(),
          child: ListView(
            padding: const EdgeInsets.only(bottom: 20),
            children: [
              if (todayN.isNotEmpty) ...[
                _section('New'),
                ...todayN.map((n) => _NotifTile(notif: n,
                    onTap: () { ref.read(communityNotifProvider.notifier).markRead(n.id); _navigateNotif(context, n); })),
              ],
              if (weekN.isNotEmpty) ...[
                _section('This Week'),
                ...weekN.map((n) => _NotifTile(notif: n,
                    onTap: () { ref.read(communityNotifProvider.notifier).markRead(n.id); _navigateNotif(context, n); })),
              ],
              if (olderN.isNotEmpty) ...[
                _section('Earlier'),
                ...olderN.map((n) => _NotifTile(notif: n,
                    onTap: () { ref.read(communityNotifProvider.notifier).markRead(n.id); _navigateNotif(context, n); })),
              ],
            ],
          ),
        );
      },
    );
  }

  bool _isToday(DateTime dt, DateTime now) => dt.year == now.year && dt.month == now.month && dt.day == now.day;
  bool _isThisWeek(DateTime dt, DateTime now) => now.difference(dt).inDays <= 7;

  Widget _section(String title) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 14, 16, 6),
    child: Text(title, style: const TextStyle(color: Color(0xFF1A1B2E), fontWeight: FontWeight.w800, fontSize: 17)),
  );
}

class _NotifTile extends StatelessWidget {
  final CommunityNotification notif;
  final VoidCallback onTap;
  const _NotifTile({required this.notif, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        color: notif.isRead ? Colors.white : kOrange.withOpacity(0.06),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
          Stack(clipBehavior: Clip.none, children: [
            CircleNetImage(url: notif.actor?.avatar, size: 48, fallbackText: notif.actor?.name),
            Positioned(
              bottom: -3, right: -3,
              child: Container(
                padding: const EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: _color(notif.type),
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 2),
                ),
                child: Icon(_icon(notif.type), size: 10, color: Colors.white),
              ),
            ),
          ]),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              RichText(
                text: TextSpan(
                  style: const TextStyle(color: Color(0xFF1A1B2E), fontSize: 14, height: 1.35),
                  children: [
                    TextSpan(text: notif.actor?.name ?? 'Someone',
                        style: const TextStyle(fontWeight: FontWeight.w700)),
                    TextSpan(text: ' ${_text(notif.type)}'),
                  ],
                ),
              ),
              const SizedBox(height: 3),
              Text(timeago.format(notif.createdAt),
                  style: TextStyle(
                    color: notif.isRead ? const Color(0xFF9CA3AF) : kOrange,
                    fontSize: 12,
                    fontWeight: notif.isRead ? FontWeight.normal : FontWeight.w600,
                  )),
            ]),
          ),
          const SizedBox(width: 8),
          // Follow button for follow notifications
          if (notif.type == 'follow')
            ElevatedButton(
              onPressed: () {},
              style: ElevatedButton.styleFrom(
                backgroundColor: kOrange,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: const Text('Follow', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
            )
          else if (!notif.isRead)
            Container(
              width: 10, height: 10,
              decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
            ),
        ]),
      ),
    );
  }

  String _text(String t) {
    switch (t) {
      case 'like': return 'liked your post.';
      case 'comment': return 'commented on your photo.';
      case 'follow': return 'started following you.';
      case 'mention': return 'mentioned you in a comment.';
      case 'share': return 'shared your post.';
      case 'reply': return 'replied to your comment.';
      case 'story_view': return 'has 1K new views.';
      default: return 'interacted with you.';
    }
  }

  IconData _icon(String t) {
    switch (t) {
      case 'like': return Icons.favorite_rounded;
      case 'comment': return Icons.chat_bubble_rounded;
      case 'follow': return Icons.person_add_rounded;
      case 'mention': return Icons.alternate_email_rounded;
      default: return Icons.notifications_rounded;
    }
  }

  Color _color(String t) {
    switch (t) {
      case 'like': return const Color(0xFFE41E3F);
      case 'comment': return const Color(0xFF1877F2);
      case 'follow': return kOrange;
      case 'mention': return const Color(0xFF8B5CF6);
      default: return const Color(0xFF9CA3AF);
    }
  }
}
