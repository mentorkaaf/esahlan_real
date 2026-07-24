// ── Marketing Broadcast ───────────────────────────────────────────────────────

class MarketingBroadcast {
  final String  uuid;
  final String  title;
  final String  body;
  final String? imageUrl;
  final String? videoUrl;
  final String? module;
  final String? ctaLabel;
  final String? ctaRoute;
  final bool    isRead;
  final DateTime? sentAt;

  const MarketingBroadcast({
    required this.uuid, required this.title, required this.body,
    this.imageUrl, this.videoUrl, this.module,
    this.ctaLabel, this.ctaRoute,
    required this.isRead, this.sentAt,
  });

  factory MarketingBroadcast.fromJson(Map<String, dynamic> j) => MarketingBroadcast(
    uuid:     j['uuid'],
    title:    j['title'],
    body:     j['body'],
    imageUrl: j['image_url'],
    videoUrl: j['video_url'],
    module:   j['module'],
    ctaLabel: j['cta_label'],
    ctaRoute: j['cta_route'],
    isRead:   j['is_read'] == true,
    sentAt:   j['sent_at'] != null ? DateTime.tryParse(j['sent_at']) : null,
  );
}

// ── Conversation ──────────────────────────────────────────────────────────────

class InboxConversation {
  final String    uuid;
  final String?   module;
  final String?   subject;
  final String    status;
  final String    priority;
  final String?   lastMessage;
  final DateTime? lastMessageAt;
  final int       unread;
  final Map<String, dynamic>? agent;
  final DateTime? createdAt;

  const InboxConversation({
    required this.uuid, this.module, this.subject,
    required this.status, required this.priority,
    this.lastMessage, this.lastMessageAt,
    required this.unread, this.agent, this.createdAt,
  });

  factory InboxConversation.fromJson(Map<String, dynamic> j) => InboxConversation(
    uuid:          j['uuid'],
    module:        j['module'],
    subject:       j['subject'],
    status:        j['status'] ?? 'open',
    priority:      j['priority'] ?? 'normal',
    lastMessage:   j['last_message'],
    lastMessageAt: j['last_message_at'] != null ? DateTime.tryParse(j['last_message_at']) : null,
    unread:        j['unread'] ?? 0,
    agent:         j['agent'] != null ? Map<String, dynamic>.from(j['agent']) : null,
    createdAt:     j['created_at'] != null ? DateTime.tryParse(j['created_at']) : null,
  );

  bool get isOpen     => status == 'open' || status == 'assigned';
  bool get isResolved => status == 'resolved' || status == 'closed';
}

// ── Message ───────────────────────────────────────────────────────────────────

enum MsgType { text, image, audio, video, file }
enum MsgStatus { sent, delivered, seen }
enum SenderType { user, agent, bot }

class InboxMessage {
  final String    uuid;
  final int       senderId;
  final SenderType senderType;
  final String?   senderName;
  final MsgType   type;
  final String?   content;
  final String?   mediaUrl;
  final int?      duration;
  final MsgStatus status;
  final bool      isDeleted;
  final DateTime  createdAt;

  const InboxMessage({
    required this.uuid, required this.senderId, required this.senderType,
    this.senderName, required this.type, this.content, this.mediaUrl,
    this.duration, required this.status, required this.isDeleted,
    required this.createdAt,
  });

  factory InboxMessage.fromJson(Map<String, dynamic> j) => InboxMessage(
    uuid:       j['uuid'],
    senderId:   j['sender_id'] ?? 0,
    senderType: _parseSenderType(j['sender_type']),
    senderName: j['sender_name'],
    type:       _parseMsgType(j['type']),
    content:    j['content'],
    mediaUrl:   j['media_url'],
    duration:   j['duration'],
    status:     _parseMsgStatus(j['status']),
    isDeleted:  j['is_deleted'] == true,
    createdAt:  DateTime.tryParse(j['created_at'] ?? '') ?? DateTime.now(),
  );

  bool get isFromUser  => senderType == SenderType.user;
  bool get isFromAgent => senderType == SenderType.agent || senderType == SenderType.bot;

  static SenderType _parseSenderType(String? s) {
    switch (s) {
      case 'agent': return SenderType.agent;
      case 'bot':   return SenderType.bot;
      default:      return SenderType.user;
    }
  }

  static MsgType _parseMsgType(String? s) {
    switch (s) {
      case 'image': return MsgType.image;
      case 'audio': return MsgType.audio;
      case 'video': return MsgType.video;
      case 'file':  return MsgType.file;
      default:      return MsgType.text;
    }
  }

  static MsgStatus _parseMsgStatus(String? s) {
    switch (s) {
      case 'delivered': return MsgStatus.delivered;
      case 'seen':      return MsgStatus.seen;
      default:          return MsgStatus.sent;
    }
  }
}

// ── Modules list for support ──────────────────────────────────────────────────

class SupportModule {
  final String id;
  final String label;
  final String icon;
  const SupportModule({required this.id, required this.label, required this.icon});
}

const kSupportModules = [
  SupportModule(id: 'general',    label: 'General',    icon: '💬'),
  SupportModule(id: 'efood',      label: 'eFood',      icon: '🍔'),
  SupportModule(id: 'egrocery',   label: 'eGrocery',   icon: '🛒'),
  SupportModule(id: 'eshop',      label: 'eShop',      icon: '🛍️'),
  SupportModule(id: 'epay',       label: 'Wallet',     icon: '💳'),
  SupportModule(id: 'elearning',  label: 'eLearning',  icon: '📚'),
  SupportModule(id: 'erent',      label: 'eRent',      icon: '🏠'),
  SupportModule(id: 'eparcel',    label: 'eParcel',    icon: '📦'),
  SupportModule(id: 'emoving',    label: 'eMoving',    icon: '🚛'),
  SupportModule(id: 'elaundry',   label: 'eLaundry',   icon: '👕'),
  SupportModule(id: 'eexchange',  label: 'eExchange',  icon: '💱'),
  SupportModule(id: 'account',    label: 'My Account', icon: '👤'),
];

// Route mapping for marketing CTA
const kCtaRouteMap = {
  'efood':     '/home',
  'egrocery':  '/home',
  'eshop':     '/home',
  'epay':      '/wallet',
  'crypto':    '/home',
  'community': '/community',
  'elearning': '/home',
};
