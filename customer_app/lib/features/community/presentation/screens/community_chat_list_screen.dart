import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart';
import 'community_chat_screen.dart';

class CommunityChatListScreen extends ConsumerWidget {
  const CommunityChatListScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final chatsAsync = ref.watch(communityChatsProvider);

    return Scaffold(
      
      appBar: AppBar(
        
        elevation: 0,
        title: Text('Chats',
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
              color: const Color(0xFFF0F2F5),
              borderRadius: BorderRadius.circular(20),
            ),
            child: Row(children: [
              SizedBox(width: 12),
              Icon(Icons.search_rounded, color: context.colors.mutedText, size: 20),
              SizedBox(width: 8),
              Text('Search messages or users',
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
                      decoration: BoxDecoration(color: Color(0xFFF0F2F5), shape: BoxShape.circle),
                      child: Icon(Icons.chat_bubble_rounded, size: 35, color: Color(0xFFD1D5DB)),
                    ),
                    SizedBox(height: 14),
                    Text('No messages yet',
                        style: TextStyle(color: context.colors.bodyText, fontSize: 16, fontWeight: FontWeight.w700)),
                    SizedBox(height: 6),
                    Text('Start a conversation with someone',
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

class _ChatTile extends StatelessWidget {
  final CommunityChat chat;
  const _ChatTile({required this.chat});

  @override
  Widget build(BuildContext context) {
    final other = chat.otherUser;
    final lastMsg = chat.lastMessage;
    final hasUnread = (chat.unreadCount ?? 0) > 0;

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
              border: Border.all(color: Colors.white, width: 2),
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
                color: const Color(0xFF1A1B2E),
              )),
        ),
        if (lastMsg != null)
          Text(
            timeago.format(
              DateTime.tryParse(lastMsg['created_at'] as String? ?? '') ?? DateTime.now(),
              locale: 'en_short',
            ),
            style: TextStyle(
              color: hasUnread ? kOrange : const Color(0xFF9CA3AF),
              fontSize: 12,
              fontWeight: hasUnread ? FontWeight.w600 : FontWeight.normal,
            ),
          ),
      ]),
      subtitle: Row(children: [
        Expanded(
          child: Text(
            lastMsg?['content'] as String? ?? 'Voice note',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: hasUnread ? const Color(0xFF1A1B2E) : const Color(0xFF9CA3AF),
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
