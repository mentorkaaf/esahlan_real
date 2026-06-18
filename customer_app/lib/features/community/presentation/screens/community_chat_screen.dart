import 'package:cached_network_image/cached_network_image.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../data/models/community_models.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart';

class CommunityChatScreen extends ConsumerStatefulWidget {
  final CommunityChat chat;
  const CommunityChatScreen({super.key, required this.chat});

  @override
  ConsumerState<CommunityChatScreen> createState() => _CommunityChatScreenState();
}

class _CommunityChatScreenState extends ConsumerState<CommunityChatScreen> {
  final _msgCtrl = TextEditingController();
  final _scrollCtrl = ScrollController();
  bool _sending = false;

  CommunityChat get chat => widget.chat;
  CommunityUser? get other => chat.otherUser;

  @override
  void dispose() {
    _msgCtrl.dispose();
    _scrollCtrl.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty || _sending) return;
    _msgCtrl.clear();
    setState(() => _sending = true);
    try {
      final myProfile = ref.read(communityMyProfileProvider).valueOrNull;
      final myId = myProfile?.id ?? 0;
      await ref.read(communityRepoProvider).sendMessage(chat.id, myId, content: text);
      ref.read(communityMessagesProvider(chat.id).notifier).load();
      await Future.delayed(const Duration(milliseconds: 100));
      if (_scrollCtrl.hasClients) {
        _scrollCtrl.animateTo(
          _scrollCtrl.position.maxScrollExtent,
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
        );
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to send: $e'), backgroundColor: Colors.red));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final myProfile = ref.watch(communityMyProfileProvider);
    final myId = myProfile.valueOrNull?.id ?? 0;
    final msgsAsync = ref.watch(communityMessagesProvider(chat.id));

    return Scaffold(
      
      appBar: AppBar(
        
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded, color: Color(0xFF1A1B2E)),
          onPressed: () => Navigator.pop(context),
        ),
        title: Row(children: [
          CircleNetImage(url: other?.avatar, size: 36, fallbackText: other?.name),
          const SizedBox(width: 10),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Text(other?.name ?? 'Chat',
                  style: const TextStyle(color: Color(0xFF1A1B2E), fontWeight: FontWeight.w700, fontSize: 15)),
              if (other?.isVerified == true) ...[
                const SizedBox(width: 4),
                const Icon(Icons.verified_rounded, color: Color(0xFF1877F2), size: 14),
              ],
            ]),
            const Text('Online', style: TextStyle(color: Color(0xFF45BD62), fontSize: 12, fontWeight: FontWeight.w500)),
          ]),
        ]),
        actions: [
          IconButton(icon: const Icon(Icons.call_rounded, color: Color(0xFF1A1B2E)), onPressed: () {}),
          IconButton(icon: const Icon(Icons.videocam_rounded, color: Color(0xFF1A1B2E)), onPressed: () {}),
          IconButton(icon: const Icon(Icons.more_vert_rounded, color: Color(0xFF1A1B2E)), onPressed: () {}),
        ],
      ),
      body: Column(children: [
        Expanded(
          child: msgsAsync.when(
            loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
            error: (e, _) => Center(child: Text('$e', style: const TextStyle(color: Colors.red))),
            data: (msgs) {
              if (msgs.isEmpty) {
                return Center(
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    CircleNetImage(url: other?.avatar, size: 72, fallbackText: other?.name),
                    const SizedBox(height: 12),
                    Text(other?.name ?? '',
                        style: const TextStyle(color: Color(0xFF1A1B2E), fontWeight: FontWeight.w700, fontSize: 16)),
                    const SizedBox(height: 4),
                    const Text('Say hi!', style: TextStyle(color: Color(0xFF9CA3AF), fontSize: 13)),
                  ]),
                );
              }
              return ListView.builder(
                controller: _scrollCtrl,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                itemCount: msgs.length,
                itemBuilder: (ctx, i) => _MessageBubble(msg: msgs[i]),
              );
            },
          ),
        ),

        // Input bar
        Container(
          color: Colors.white,
          padding: EdgeInsets.only(
            left: 12, right: 12,
            top: 8,
            bottom: MediaQuery.of(context).padding.bottom + 8,
          ),
          child: Row(children: [
            IconButton(icon: const Icon(Icons.add_circle_rounded, color: kOrange, size: 28), onPressed: () {}),
            Expanded(
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: const Color(0xFFF0F2F5),
                  borderRadius: BorderRadius.circular(24),
                ),
                child: TextField(
                  controller: _msgCtrl,
                  maxLines: null,
                  textCapitalization: TextCapitalization.sentences,
                  style: const TextStyle(fontSize: 15, color: Color(0xFF1A1B2E)),
                  decoration: const InputDecoration.collapsed(
                    hintText: 'Type a message...',
                    hintStyle: TextStyle(color: Color(0xFF9CA3AF)),
                  ),
                  onSubmitted: (_) => _send(),
                ),
              ),
            ),
            const SizedBox(width: 8),
            IconButton(
              icon: const Icon(Icons.mic_rounded, color: Color(0xFF9CA3AF), size: 24),
              onPressed: () {},
            ),
            GestureDetector(
              onTap: _send,
              child: Container(
                width: 40, height: 40,
                decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
                child: _sending
                    ? const Center(child: SizedBox(width: 18, height: 18,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)))
                    : const Icon(Icons.send_rounded, color: Colors.white, size: 20),
              ),
            ),
          ]),
        ),
      ]),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  final CommunityMessage msg;
  const _MessageBubble({required this.msg});

  @override
  Widget build(BuildContext context) {
    final isMe = msg.isMe;
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        mainAxisAlignment: isMe ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isMe) ...[
            CircleNetImage(url: msg.user?.avatar, size: 28, fallbackText: msg.user?.name),
            const SizedBox(width: 8),
          ],
          Flexible(
            child: Column(
              crossAxisAlignment: isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: isMe ? kOrange : Colors.white,
                    borderRadius: BorderRadius.only(
                      topLeft: const Radius.circular(18),
                      topRight: const Radius.circular(18),
                      bottomLeft: Radius.circular(isMe ? 18 : 4),
                      bottomRight: Radius.circular(isMe ? 4 : 18),
                    ),
                    boxShadow: [
                      BoxShadow(color: Colors.black.withOpacity(0.06), blurRadius: 4, offset: const Offset(0, 2)),
                    ],
                  ),
                  child: Text(
                    msg.content ?? '',
                    style: TextStyle(
                      color: isMe ? Colors.white : const Color(0xFF1A1B2E),
                      fontSize: 15,
                    ),
                  ),
                ),
                const SizedBox(height: 3),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      timeago.format(msg.createdAt, locale: 'en_short'),
                      style: const TextStyle(color: Color(0xFF9CA3AF), fontSize: 11),
                    ),
                    if (isMe) ...[
                      const SizedBox(width: 4),
                      Icon(
                        msg.isRead ? Icons.done_all_rounded : Icons.done_rounded,
                        size: 14,
                        color: msg.isRead ? kOrange : const Color(0xFF9CA3AF),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
