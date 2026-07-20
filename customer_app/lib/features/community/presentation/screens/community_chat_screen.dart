import 'dart:async';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:go_router/go_router.dart';
import '../../../../core/theme/theme_x.dart';
import '../../../../core/widgets/network_image_widget.dart';
import '../../../../core/services/realtime_client.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timeago/timeago.dart' as timeago;
import '../../data/models/community_models.dart';
import '../../data/repositories/community_repository.dart';
import '../providers/community_provider.dart';
import 'community_shell.dart';
import '../../../../core/widgets/restriction_dialog.dart';
import '../../../calls/data/repositories/call_repository.dart';
import '../../../calls/presentation/screens/active_call_screen.dart';

// Minute-bucketed timeago cache — same pattern as feed screen.
// Eliminates repeated string formatting for unchanged timestamps on every rebuild.
final _taCache = <String, String>{};
String _fmtTimeago(DateTime dt, {String locale = 'en'}) {
  final m = DateTime.now().millisecondsSinceEpoch ~/ 60000;
  final k = '${dt.millisecondsSinceEpoch}:$m:$locale';
  return _taCache.putIfAbsent(k, () => timeago.format(dt, locale: locale));
}

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
  bool _otherTyping = false;
  bool _otherOnline = false;
  Timer? _typingDebounce;
  Timer? _typingClearTimer;
  Timer? _onlineTimer;
  bool _amTyping = false;
  bool _callingAudio = false;
  bool _callingVideo = false;
  final _callRepo = CallRepository();

  CommunityChat get chat => widget.chat;
  CommunityUser? get other => chat.otherUser;
  String get _chatChannel => 'private-chat.${chat.id}';
  String get _typingChannel => 'presence-chat-presence.${chat.id}';

  void Function(dynamic)? _onMessageSent;
  void Function(dynamic)? _onSeen;
  void Function(dynamic)? _onTyping;

  @override
  void initState() {
    super.initState();
    _subscribeRealtime();
    CommunityRepository().markChatRead(chat.id);
  }

  Future<void> _subscribeRealtime() async {
    // Must be the resolved id, not a possibly-still-loading synchronous
    // read — capturing 0 here would make every message (including the
    // sender's own) look like it belongs to someone else: wrong bubble
    // side, wrong color, "seen"/"typing" filters never matching.
    final me = await ref.read(communityMyProfileProvider.future);
    final myId = me.id;
    final hadStaleId = MessagesNotifier.myId != myId;
    MessagesNotifier.setMyId(myId);
    if (!mounted) return;
    // The provider's initial REST fetch may have already run with a stale
    // (possibly 0) id before this resolved — reload so isMe is correct on
    // already-displayed messages, not just future ones.
    if (hadStaleId) ref.read(communityMessagesProvider(chat.id).notifier).load();

    _onMessageSent = (data) {
      final msgJson = Map<String, dynamic>.from(data['message'] as Map);
      msgJson['chat_id'] = data['chat_id'];
      msgJson['user_id'] = (msgJson['user'] as Map)['id'];
      final msg = CommunityMessage.fromJson(msgJson, myId);
      ref.read(communityMessagesProvider(chat.id).notifier).appendIncoming(msg);
      if (!msg.isMe) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (_scrollCtrl.hasClients) {
            _scrollCtrl.animateTo(_scrollCtrl.position.maxScrollExtent,
                duration: const Duration(milliseconds: 200), curve: Curves.easeOut);
          }
        });
      }
    };
    RealtimeClient.instance.listen(_chatChannel, 'chat.message_sent', _onMessageSent!);

    _onSeen = (data) {
      if ((data['seen_by'] as int?) != myId) {
        ref.read(communityMessagesProvider(chat.id).notifier).markAllReadLocally();
      }
    };
    RealtimeClient.instance.listen(_chatChannel, 'chat.seen', _onSeen!);

    _onTyping = (data) {
      if ((data['user_id'] as int?) == myId) return;
      if (mounted) {
        setState(() {
          _otherTyping = data['is_typing'] == true;
          _otherOnline = true; // typing means online
        });
        _typingClearTimer?.cancel();
        _onlineTimer?.cancel();
        if (_otherTyping) {
          _typingClearTimer = Timer(const Duration(seconds: 5), () {
            if (mounted) setState(() => _otherTyping = false);
          });
        }
        // Stay "online" for 2 min after last typing event
        _onlineTimer = Timer(const Duration(minutes: 2), () {
          if (mounted) setState(() => _otherOnline = false);
        });
      }
    };
    RealtimeClient.instance.listen(_typingChannel, 'chat.typing', _onTyping!);
  }

  void _onTextChanged(String _) {
    if (!_amTyping) {
      _amTyping = true;
      CommunityRepository().sendTyping(chat.id, true);
    }
    _typingDebounce?.cancel();
    _typingDebounce = Timer(const Duration(seconds: 2), () {
      _amTyping = false;
      CommunityRepository().sendTyping(chat.id, false);
    });
  }

  @override
  void dispose() {
    _msgCtrl.dispose();
    _scrollCtrl.dispose();
    _typingDebounce?.cancel();
    _typingClearTimer?.cancel();
    _onlineTimer?.cancel();
    if (_onMessageSent != null) RealtimeClient.instance.removeListener(_chatChannel, 'chat.message_sent', _onMessageSent!);
    if (_onSeen != null) RealtimeClient.instance.removeListener(_chatChannel, 'chat.seen', _onSeen!);
    if (_onTyping != null) RealtimeClient.instance.removeListener(_typingChannel, 'chat.typing', _onTyping!);
    super.dispose();
  }

  Future<void> _send() async {
    final text = _msgCtrl.text.trim();
    if (text.isEmpty || _sending) return;
    _msgCtrl.clear();
    _typingDebounce?.cancel();
    if (_amTyping) {
      _amTyping = false;
      CommunityRepository().sendTyping(chat.id, false);
    }
    setState(() => _sending = true);
    try {
      await ref.read(communityMessagesProvider(chat.id).notifier).send(content: text);
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_scrollCtrl.hasClients) {
          _scrollCtrl.animateTo(_scrollCtrl.position.maxScrollExtent,
              duration: const Duration(milliseconds: 200), curve: Curves.easeOut);
        }
      });
    } catch (e) {
      if (mounted && !RestrictionDialog.handle(context, e)) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to send: $e'), backgroundColor: Colors.red));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _startCall(String type) async {
    final otherId = other?.id;
    if (otherId == null) return;
    final isVideo = type == 'video';
    if (isVideo) {
      if (_callingVideo) return;
      setState(() => _callingVideo = true);
    } else {
      if (_callingAudio) return;
      setState(() => _callingAudio = true);
    }
    try {
      final session = await _callRepo.initiateCall(receiverId: otherId, type: type);
      if (mounted) {
        Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => ActiveCallScreen(session: session)),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Call failed: $e'), backgroundColor: Colors.red),
        );
      }
    } finally {
      if (mounted) setState(() { _callingAudio = false; _callingVideo = false; });
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
          icon: Icon(Icons.arrow_back_rounded, color: context.colors.bodyText),
          onPressed: () => Navigator.pop(context),
        ),
        title: Row(children: [
          CircleNetImage(url: other?.avatar, size: 36, fallbackText: other?.name),
          SizedBox(width: 10),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [
              Text(other?.name ?? 'Chat',
                  style: TextStyle(color: context.colors.bodyText, fontWeight: FontWeight.w700, fontSize: 15)),
              if (other?.isVerified == true) ...[
                SizedBox(width: 4),
                Icon(Icons.verified_rounded, color: Color(0xFF1877F2), size: 14),
              ],
            ]),
            Text(
              _otherTyping ? 'typing…' : (_otherOnline ? 'Online' : 'last seen recently'),
              style: TextStyle(
                color: _otherTyping ? kOrange : (_otherOnline ? const Color(0xFF45BD62) : Colors.grey),
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ]),
        ]),
        actions: [
          _callingAudio
              ? const Padding(
                  padding: EdgeInsets.all(12),
                  child: SizedBox(width: 20, height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2)))
              : IconButton(
                  icon: Icon(Icons.call_rounded, color: context.colors.bodyText),
                  onPressed: () => _startCall('audio')),
          _callingVideo
              ? const Padding(
                  padding: EdgeInsets.all(12),
                  child: SizedBox(width: 20, height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2)))
              : IconButton(
                  icon: Icon(Icons.videocam_rounded, color: context.colors.bodyText),
                  onPressed: () => _startCall('video')),
          IconButton(icon: Icon(Icons.more_vert_rounded, color: context.colors.bodyText), onPressed: () {}),
        ],
      ),
      body: Column(children: [
        Expanded(
          child: msgsAsync.when(
            loading: () => Center(child: CircularProgressIndicator(color: kOrange)),
            error: (e, _) => Center(child: Text('$e', style: TextStyle(color: Colors.red))),
            data: (msgs) {
              if (msgs.isEmpty) {
                return Center(
                  child: Column(mainAxisSize: MainAxisSize.min, children: [
                    CircleNetImage(url: other?.avatar, size: 72, fallbackText: other?.name),
                    SizedBox(height: 12),
                    Text(other?.name ?? '',
                        style: TextStyle(color: context.colors.bodyText, fontWeight: FontWeight.w700, fontSize: 16)),
                    SizedBox(height: 4),
                    Text('Say hi!', style: TextStyle(color: context.colors.mutedText, fontSize: 13)),
                  ]),
                );
              }
              return ListView.builder(
                controller: _scrollCtrl,
                padding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                itemCount: msgs.length,
                itemBuilder: (ctx, i) => _MessageBubble(msg: msgs[i]),
              );
            },
          ),
        ),

        // Input bar
        Container(
          color: context.colors.cardBg,
          padding: EdgeInsets.only(
            left: 12, right: 12,
            top: 8,
            bottom: MediaQuery.of(context).padding.bottom + 8,
          ),
          child: Row(children: [
            IconButton(icon: Icon(Icons.add_circle_rounded, color: kOrange, size: 28), onPressed: () {}),
            Expanded(
              child: Container(
                padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                decoration: BoxDecoration(
                  color: context.colors.inputFill,
                  borderRadius: BorderRadius.circular(24),
                ),
                child: TextField(
                  controller: _msgCtrl,
                  maxLines: null,
                  textCapitalization: TextCapitalization.sentences,
                  style: TextStyle(fontSize: 15, color: context.colors.bodyText),
                  decoration: InputDecoration.collapsed(
                    hintText: 'Type a message...',
                    hintStyle: TextStyle(color: context.colors.mutedText),
                  ),
                  onChanged: _onTextChanged,
                  onSubmitted: (_) => _send(),
                ),
              ),
            ),
            SizedBox(width: 8),
            IconButton(
              icon: Icon(Icons.mic_rounded, color: context.colors.mutedText, size: 24),
              onPressed: () {},
            ),
            GestureDetector(
              onTap: _send,
              child: Container(
                width: 40, height: 40,
                decoration: BoxDecoration(color: kOrange, shape: BoxShape.circle),
                child: _sending
                    ? Center(child: SizedBox(width: 18, height: 18,
                        child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2)))
                    : Icon(Icons.send_rounded, color: Colors.white, size: 20),
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
      padding: EdgeInsets.only(bottom: 6),
      child: Row(
        mainAxisAlignment: isMe ? MainAxisAlignment.end : MainAxisAlignment.start,
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          if (!isMe) ...[
            CircleNetImage(url: msg.user?.avatar, size: 28, fallbackText: msg.user?.name),
            SizedBox(width: 8),
          ],
          Flexible(
            child: Column(
              crossAxisAlignment: isMe ? CrossAxisAlignment.end : CrossAxisAlignment.start,
              children: [
                Container(
                  padding: EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  decoration: BoxDecoration(
                    color: isMe ? kOrange : context.colors.inputFill,
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
                      color: isMe ? Colors.white : context.colors.bodyText,
                      fontSize: 15,
                    ),
                  ),
                ),
                SizedBox(height: 3),
                Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      _fmtTimeago(msg.createdAt, locale: 'en_short'),
                      style: TextStyle(color: context.colors.mutedText, fontSize: 11),
                    ),
                    if (isMe) ...[
                      SizedBox(width: 4),
                      Icon(
                        msg.isRead ? Icons.done_all_rounded : Icons.done_rounded,
                        size: 14,
                        color: msg.isRead ? kOrange : const Color(0xFF9CA3AF),
                      ),
                      SizedBox(width: 2),
                      Text(
                        msg.isRead ? 'Seen' : 'Delivered',
                        style: TextStyle(
                          color: msg.isRead ? kOrange : const Color(0xFF9CA3AF),
                          fontSize: 10,
                          fontWeight: FontWeight.w500,
                        ),
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
