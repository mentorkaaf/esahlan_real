import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import 'package:go_router/go_router.dart';
import 'community_shell.dart';
import 'community_chat_screen.dart';

final _taCache = <String, String>{};
String _fmtTimeago(DateTime dt) {
  final m = DateTime.now().millisecondsSinceEpoch ~/ 60000;
  final k = '${dt.millisecondsSinceEpoch}:$m';
  return _taCache.putIfAbsent(k, () => timeago.format(dt));
}

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
    _tab = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tab.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final notifsAsync = ref.watch(communityNotifProvider);

    return Scaffold(
      
      appBar: AppBar(
        
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back_rounded, color: context.colors.bodyText),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text('Notifications',
            style: TextStyle(color: context.colors.bodyText, fontWeight: FontWeight.w800, fontSize: 20)),
        actions: [
          GestureDetector(
            onTap: () => ref.read(communityNotifProvider.notifier).markAllRead(),
            child: Container(
              margin: EdgeInsets.only(right: 12),
              padding: EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: kOrange.withOpacity(0.1),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text('Mark all read',
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
          labelPadding: EdgeInsets.symmetric(horizontal: 0),
          tabs: const [
            Tab(child: Center(child: Text('All', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)))),
            Tab(child: Center(child: Text('Mentions', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)))),
            Tab(child: Center(child: Text('Requests', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)))),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tab,
        children: [
          _NotifList(notifsAsync: notifsAsync, filter: null),
          _NotifList(notifsAsync: notifsAsync, filter: 'mention'),
          const _FollowRequestsList(),
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
      loading: () => Center(child: CircularProgressIndicator(color: kOrange)),
      error: (e, _) => Center(child: Text('Error: $e', style: TextStyle(color: Colors.red))),
      data: (all) {
        final notifs = filter == null ? all : all.where((n) => n.type == filter).toList();

        if (notifs.isEmpty) {
          return Center(
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Container(
                width: 70, height: 70,
                decoration: BoxDecoration(color: context.colors.surfaceBg, shape: BoxShape.circle),
                child: Icon(Icons.notifications_rounded, size: 35, color: context.colors.subtleText),
              ),
              SizedBox(height: 14),
              Text('No notifications yet',
                  style: TextStyle(color: context.colors.bodyText, fontSize: 16, fontWeight: FontWeight.w700)),
              SizedBox(height: 6),
              Text('When people interact with you, it will show here',
                  style: TextStyle(color: context.colors.mutedText, fontSize: 13), textAlign: TextAlign.center),
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
            padding: EdgeInsets.only(bottom: 20),
            children: [
              if (todayN.isNotEmpty) ...[
                _section('New', context),
                ...todayN.map((n) => _NotifTile(notif: n,
                    onTap: () { ref.read(communityNotifProvider.notifier).markRead(n.id); _navigateNotif(context, n); })),
              ],
              if (weekN.isNotEmpty) ...[
                _section('This Week', context),
                ...weekN.map((n) => _NotifTile(notif: n,
                    onTap: () { ref.read(communityNotifProvider.notifier).markRead(n.id); _navigateNotif(context, n); })),
              ],
              if (olderN.isNotEmpty) ...[
                _section('Earlier', context),
                ...olderN.map((n) => _NotifTile(notif: n,
                    onTap: () { ref.read(communityNotifProvider.notifier).markRead(n.id); _navigateNotif(context, n); })),
              ],
            ],
          ),
        );
      },
    );
  }

  void _navigateNotif(BuildContext context, CommunityNotification n) {
    switch (n.type) {
      case 'like': case 'comment': case 'share': case 'mention':
        if (n.actor != null) context.push('/community/profile/${n.actor!.id}');
        break;
      case 'follow':
        if (n.actor != null) context.push('/community/profile/${n.actor!.id}');
        break;
      case 'message':
        if (n.notifiableId != null && n.actor != null) {
          Navigator.push(context, MaterialPageRoute(builder: (_) => CommunityChatScreen(
            chat: CommunityChat(id: n.notifiableId!, type: 'direct', otherUser: n.actor))));
        }
        break;
      default:
        if (n.actor != null) context.push('/community/profile/${n.actor!.id}');
    }
  }

  bool _isToday(DateTime dt, DateTime now) => dt.year == now.year && dt.month == now.month && dt.day == now.day;
  bool _isThisWeek(DateTime dt, DateTime now) => now.difference(dt).inDays <= 7;

  Widget _section(String title, BuildContext context) => Padding(
    padding: EdgeInsets.fromLTRB(16, 14, 16, 6),
    child: Text(title, style: TextStyle(color: context.colors.bodyText, fontWeight: FontWeight.w800, fontSize: 17)),
  );
}

class _NotifTile extends ConsumerStatefulWidget {
  final CommunityNotification notif;
  final VoidCallback onTap;
  const _NotifTile({required this.notif, required this.onTap});

  @override
  ConsumerState<_NotifTile> createState() => _NotifTileState();
}

class _NotifTileState extends ConsumerState<_NotifTile> {
  late bool _following;
  bool _loading = false;
  bool _requestHandled = false;

  @override
  void initState() {
    super.initState();
    _following = widget.notif.actor?.isFollowing ?? false;
  }

  Future<void> _toggleFollow() async {
    final actorId = widget.notif.actor?.id;
    if (actorId == null || _loading) return;
    setState(() { _loading = true; _following = !_following; });
    try {
      await ref.read(communityRepoProvider).toggleFollow(actorId);
    } catch (_) {
      if (mounted) setState(() => _following = !_following);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _acceptRequest() async {
    final notifId = widget.notif.id;
    if (_loading || _requestHandled) return;
    setState(() => _loading = true);
    try {
      await ref.read(communityRepoProvider).acceptFollowRequest(notifId);
      if (mounted) setState(() { _requestHandled = true; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _rejectRequest() async {
    final notifId = widget.notif.id;
    if (_loading || _requestHandled) return;
    setState(() => _loading = true);
    try {
      await ref.read(communityRepoProvider).rejectFollowRequest(notifId);
      if (mounted) setState(() { _requestHandled = true; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final notif = widget.notif;
    return InkWell(
      onTap: widget.onTap,
      child: Container(
        color: notif.isRead ? context.colors.cardBg : kOrange.withOpacity(0.08),
        padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        child: Row(crossAxisAlignment: CrossAxisAlignment.center, children: [
          Stack(clipBehavior: Clip.none, children: [
            CircleNetImage(url: notif.actor?.avatar, size: 48, fallbackText: notif.actor?.name),
            Positioned(
              bottom: -3, right: -3,
              child: Container(
                padding: EdgeInsets.all(4),
                decoration: BoxDecoration(
                  color: _color(notif.type),
                  shape: BoxShape.circle,
                  border: Border.all(color: context.colors.cardBg, width: 2),
                ),
                child: Icon(_icon(notif.type), size: 10, color: Colors.white),
              ),
            ),
          ]),
          SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              RichText(
                text: TextSpan(
                  style: TextStyle(color: context.colors.bodyText, fontSize: 14, height: 1.35),
                  children: [
                    TextSpan(text: notif.actor?.name ?? 'Someone',
                        style: TextStyle(fontWeight: FontWeight.w700)),
                    TextSpan(text: ' ${_text(notif.type)}'),
                  ],
                ),
              ),
              SizedBox(height: 3),
              Text(_fmtTimeago(notif.createdAt),
                  style: TextStyle(
                    color: notif.isRead ? const Color(0xFF9CA3AF) : kOrange,
                    fontSize: 12,
                    fontWeight: notif.isRead ? FontWeight.normal : FontWeight.w600,
                  )),
            ]),
          ),
          SizedBox(width: 8),
          if (notif.type == 'follow_request' && notif.actor != null)
            _requestHandled
                ? Text('Done', style: TextStyle(color: Colors.grey, fontSize: 12))
                : Row(mainAxisSize: MainAxisSize.min, children: [
                    SizedBox(
                      height: 32,
                      child: ElevatedButton(
                        onPressed: _loading ? null : _acceptRequest,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: kOrange, foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                          padding: EdgeInsets.symmetric(horizontal: 12),
                          minimumSize: Size.zero, tapTargetSize: MaterialTapTargetSize.shrinkWrap, elevation: 0,
                        ),
                        child: _loading
                            ? SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                            : Text('Accept', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                      ),
                    ),
                    SizedBox(width: 6),
                    SizedBox(
                      height: 32,
                      child: OutlinedButton(
                        onPressed: _loading ? null : _rejectRequest,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: context.colors.bodyText,
                          side: BorderSide(color: context.colors.borderColor),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                          padding: EdgeInsets.symmetric(horizontal: 12),
                          minimumSize: Size.zero, tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
                        child: Text('Reject', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
                      ),
                    ),
                  ])
          else if (notif.type == 'follow' && notif.actor != null)
            SizedBox(
              height: 32,
              child: ElevatedButton(
                onPressed: _loading ? null : _toggleFollow,
                style: ElevatedButton.styleFrom(
                  backgroundColor: _following ? context.colors.surfaceBg : kOrange,
                  foregroundColor: _following ? context.colors.bodyText : Colors.white,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(6)),
                  padding: EdgeInsets.symmetric(horizontal: 12),
                  minimumSize: Size.zero,
                  tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  elevation: 0,
                ),
                child: _loading
                    ? SizedBox(width: 14, height: 14, child: CircularProgressIndicator(strokeWidth: 2, color: _following ? Colors.black54 : Colors.white))
                    : Text(_following ? 'Following' : 'Follow Back',
                        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
              ),
            )
          else if (!notif.isRead)
            Container(
              width: 10, height: 10,
              decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle),
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
      case 'follow_request': return 'wants to follow you.';
      case 'follow_request_accepted': return 'accepted your follow request.';
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
      case 'follow_request': return Icons.person_add_rounded;
      case 'follow_request_accepted': return Icons.check_circle_rounded;
      case 'mention': return Icons.alternate_email_rounded;
      default: return Icons.notifications_rounded;
    }
  }

  Color _color(String t) {
    switch (t) {
      case 'like': return const Color(0xFFE41E3F);
      case 'comment': return const Color(0xFF1877F2);
      case 'follow': return kOrange;
      case 'follow_request': return const Color(0xFF8B5CF6);
      case 'follow_request_accepted': return const Color(0xFF10B981);
      case 'mention': return const Color(0xFF8B5CF6);
      default: return const Color(0xFF9CA3AF);
    }
  }
}

class _FollowRequestsList extends ConsumerStatefulWidget {
  const _FollowRequestsList();

  @override
  ConsumerState<_FollowRequestsList> createState() => _FollowRequestsListState();
}

class _FollowRequestsListState extends ConsumerState<_FollowRequestsList> {
  List<dynamic> _requests = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await ref.read(communityRepoProvider).getFollowRequests();
      if (mounted) setState(() { _requests = data; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_requests.isEmpty) {
      return Center(
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(Icons.person_add_rounded, size: 56, color: context.colors.subtleText),
          SizedBox(height: 12),
          Text('No follow requests', style: TextStyle(color: context.colors.bodyText, fontSize: 16, fontWeight: FontWeight.w600)),
          SizedBox(height: 4),
          Text('When someone requests to follow you,\nit will appear here.', textAlign: TextAlign.center,
              style: TextStyle(color: context.colors.mutedText, fontSize: 13)),
        ]),
      );
    }
    return ListView.separated(
      itemCount: _requests.length,
      separatorBuilder: (_, __) => Divider(height: 1, color: context.colors.dividerColor),
      itemBuilder: (context, i) {
        final req = _requests[i];
        return _FollowRequestTile(
          request: req,
          onHandled: () => setState(() => _requests.removeAt(i)),
        );
      },
    );
  }
}

class _FollowRequestTile extends ConsumerStatefulWidget {
  final dynamic request;
  final VoidCallback onHandled;
  const _FollowRequestTile({required this.request, required this.onHandled});

  @override
  ConsumerState<_FollowRequestTile> createState() => _FollowRequestTileState();
}

class _FollowRequestTileState extends ConsumerState<_FollowRequestTile> {
  bool _loading = false;

  Future<void> _accept() async {
    if (_loading) return;
    setState(() => _loading = true);
    try {
      await ref.read(communityRepoProvider).acceptFollowRequest(widget.request['id'] as int);
      widget.onHandled();
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _reject() async {
    if (_loading) return;
    setState(() => _loading = true);
    try {
      await ref.read(communityRepoProvider).rejectFollowRequest(widget.request['id'] as int);
      widget.onHandled();
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final req = widget.request;
    final user = req['user'] as Map<String, dynamic>?;
    final name = user?['name'] as String? ?? 'Unknown';
    final avatar = user?['avatar'] as String?;
    final username = user?['username'] as String? ?? '';
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      child: Row(children: [
        CircleNetImage(url: avatar, size: 48, fallbackText: name),
        SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(name, style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14, color: context.colors.bodyText)),
            if (username.isNotEmpty)
              Text('@$username', style: TextStyle(fontSize: 12, color: context.colors.mutedText)),
          ]),
        ),
        SizedBox(width: 8),
        if (_loading)
          SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2, color: kOrange))
        else ...[
          SizedBox(
            height: 34,
            child: ElevatedButton(
              onPressed: _accept,
              style: ElevatedButton.styleFrom(
                backgroundColor: kOrange, foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                padding: EdgeInsets.symmetric(horizontal: 14),
                minimumSize: Size.zero, tapTargetSize: MaterialTapTargetSize.shrinkWrap, elevation: 0,
              ),
              child: Text('Accept', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
            ),
          ),
          SizedBox(width: 8),
          SizedBox(
            height: 34,
            child: OutlinedButton(
              onPressed: _reject,
              style: OutlinedButton.styleFrom(
                foregroundColor: context.colors.bodyText,
                side: BorderSide(color: context.colors.borderColor),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                padding: EdgeInsets.symmetric(horizontal: 14),
                minimumSize: Size.zero, tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              ),
              child: Text('Reject', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ]),
    );
  }
}
