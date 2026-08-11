import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../data/models/community_models.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart';
import 'community_chat_screen.dart';
import '../../../../core/l10n/app_strings.dart';

final _taCache = <String, String>{};
String _fmtTimeago(DateTime dt, {String locale = 'en'}) {
  final m = DateTime.now().millisecondsSinceEpoch ~/ 60000;
  final k = '${dt.millisecondsSinceEpoch}:$m:$locale';
  return _taCache.putIfAbsent(k, () => timeago.format(dt, locale: locale));
}

class CommunityChatListScreen extends ConsumerStatefulWidget {
  const CommunityChatListScreen({super.key});

  @override
  ConsumerState<CommunityChatListScreen> createState() => _CommunityChatListScreenState();
}

class _CommunityChatListScreenState extends ConsumerState<CommunityChatListScreen> {
  @override
  void initState() {
    super.initState();
    // Inbox real-time updates are handled by CommunityShell which is always
    // mounted — no need to subscribe here too.
  }

  @override
  void dispose() {
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final chatsAsync = ref.watch(communityChatsProvider);
    final l = AppL10n.of(context);

    return Scaffold(

      appBar: AppBar(

        elevation: 0,
        title: Text(l.chats,
            style: TextStyle(color: context.colors.bodyText, fontWeight: FontWeight.w800, fontSize: 22)),
        actions: [
          IconButton(
            icon: Icon(Icons.search_rounded, color: context.colors.bodyText),
            onPressed: () {},
          ),
          IconButton(
            icon: Icon(Icons.edit_rounded, color: context.colors.bodyText),
            onPressed: () {},
          ),
        ],
      ),
      body: Column(children: [
        // Search bar
        Padding(
          padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
          child: Container(
            height: 40,
            decoration: BoxDecoration(
              color: context.colors.searchBarBg,
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(children: [
              SizedBox(width: 12),
              Icon(Icons.search_rounded, color: context.colors.mutedText, size: 20),
              SizedBox(width: 8),
              Text(l.searchMessages,
                  style: TextStyle(color: context.colors.mutedText, fontSize: 14)),
            ]),
          ),
        ),

        Expanded(
          child: chatsAsync.when(
            loading: () => Center(child: CircularProgressIndicator(color: kOrange)),
            error: (e, _) => Center(child: Text('Error: $e',
                style: TextStyle(color: Colors.red))),
            data: (chats) {
              if (chats.isEmpty) {
                return Center(
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    Container(
                      width: 70, height: 70,
                      decoration: BoxDecoration(color: context.colors.surfaceBg, shape: BoxShape.circle),
                      child: Icon(Icons.chat_bubble_rounded, size: 35, color: context.colors.subtleText),
                    ),
                    SizedBox(height: 14),
                    Text(l.noMessages,
                        style: TextStyle(color: context.colors.bodyText, fontSize: 16, fontWeight: FontWeight.w700)),
                    SizedBox(height: 6),
                    Text(l.startChat,
                        style: TextStyle(color: context.colors.mutedText, fontSize: 13)),
                  ]),
                );
              }
              return RefreshIndicator(
                color: kOrange,
                onRefresh: () => ref.read(communityChatsProvider.notifier).load(),
                child: ListView.builder(
                  padding: EdgeInsets.only(bottom: 20),
                  itemCount: chats.length,
                  itemBuilder: (ctx, i) => _ChatTile(chat: chats[i]),
                ),
              );
            },
          ),
        ),
      ]),
    );
  }
}

class _ChatTile extends ConsumerWidget {
  final CommunityChat chat;
  const _ChatTile({required this.chat});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final other = chat.otherUser;
    final lastMsg = chat.lastMessage;
    final hasUnread = (chat.unreadCount ?? 0) > 0;
    final isTyping = ref.watch(chatTypingProvider(chat.id));
    final l = AppL10n.of(context);

    // Parse created_at — could be DateTime string or DateTime object
    DateTime? lastMsgTime;
    final rawTs = lastMsg?['created_at'];
    if (rawTs is String) lastMsgTime = DateTime.tryParse(rawTs);
    else if (rawTs is DateTime) lastMsgTime = rawTs;

    return ListTile(
      contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      leading: Stack(children: [
        CircleNetImage(url: other?.avatar, size: 52, fallbackText: other?.name),
        Positioned(
          right: 0, bottom: 0,
          child: Container(
            width: 12, height: 12,
            decoration: BoxDecoration(
              color: const Color(0xFF45BD62),
              shape: BoxShape.circle,
              border: Border.all(color: context.colors.cardBg, width: 2),
            ),
          ),
        ),
      ]),
      title: Row(children: [
        Expanded(
          child: Text(other?.name ?? 'Unknown',
              style: TextStyle(
                fontWeight: hasUnread ? FontWeight.w700 : FontWeight.w600,
                fontSize: 15,
                color: context.colors.bodyText,
              )),
        ),
        if (lastMsgTime != null)
          Text(
            _fmtTimeago(lastMsgTime, locale: 'en_short'),
            style: TextStyle(
              color: isTyping ? kOrange : (hasUnread ? kOrange : context.colors.mutedText),
              fontSize: 12,
              fontWeight: hasUnread ? FontWeight.w600 : FontWeight.normal,
            ),
          ),
      ]),
      subtitle: Row(children: [
        Expanded(
          child: isTyping
              ? Text('${l.typing}…',
                  maxLines: 1,
                  style: TextStyle(
                    color: kOrange,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    fontStyle: FontStyle.italic,
                  ))
              : Text(
                  lastMsg?['content'] as String? ?? '',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: hasUnread ? context.colors.bodyText : context.colors.mutedText,
                    fontSize: 13,
                    fontWeight: hasUnread ? FontWeight.w600 : FontWeight.normal,
                  ),
                ),
        ),
        if (hasUnread)
          Container(
            margin: EdgeInsets.only(left: 8),
            width: 20, height: 20,
            decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle),
            child: Center(
              child: Text('${chat.unreadCount}',
                  style: TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
            ),
          ),
      ]),
      onTap: () {
        if (other == null) return;
        Navigator.push(context, MaterialPageRoute(builder: (_) => CommunityChatScreen(chat: chat)));
      },
    );
  }
}
