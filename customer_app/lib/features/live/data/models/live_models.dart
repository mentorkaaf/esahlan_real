class LiveHost {
  final int id;
  final String name;
  final String username;
  final String avatar;

  const LiveHost({
    required this.id,
    required this.name,
    required this.username,
    required this.avatar,
  });

  factory LiveHost.fromJson(Map<String, dynamic> j) => LiveHost(
        id: j['id'] ?? 0,
        name: j['name'] ?? '',
        username: j['username'] ?? '',
        avatar: j['avatar'] ?? '',
      );
}

class LiveRoom {
  final int id;
  final String title;
  final String roomName;
  final String? thumbnail;
  final String status;
  final int viewerCount;
  final int peakViewers;
  final LiveHost host;
  final DateTime createdAt;

  const LiveRoom({
    required this.id,
    required this.title,
    required this.roomName,
    this.thumbnail,
    required this.status,
    required this.viewerCount,
    required this.peakViewers,
    required this.host,
    required this.createdAt,
  });

  factory LiveRoom.fromJson(Map<String, dynamic> j) => LiveRoom(
        id: j['id'] ?? 0,
        title: j['title'] ?? '',
        roomName: j['room_name'] ?? '',
        thumbnail: j['thumbnail'],
        status: j['status'] ?? 'live',
        viewerCount: j['viewer_count'] ?? 0,
        peakViewers: j['peak_viewers'] ?? 0,
        host: LiveHost.fromJson(j['host'] ?? {}),
        createdAt: DateTime.tryParse(j['created_at'] ?? '') ?? DateTime.now(),
      );
}

class LiveSession {
  final LiveRoom room;
  final String token;
  final String livekitUrl;

  const LiveSession({
    required this.room,
    required this.token,
    required this.livekitUrl,
  });

  factory LiveSession.fromJson(Map<String, dynamic> j) => LiveSession(
        room: LiveRoom.fromJson(j['room'] ?? {}),
        token: j['token'] ?? '',
        livekitUrl: j['livekit_url'] ?? '',
      );
}

class GiftModel {
  final int id;
  final String name;
  final String emoji;
  final String animation;
  final int coins;

  const GiftModel({
    required this.id,
    required this.name,
    required this.emoji,
    required this.animation,
    required this.coins,
  });

  factory GiftModel.fromJson(Map<String, dynamic> j) => GiftModel(
        id: j['id'] ?? 0,
        name: j['name'] ?? '',
        emoji: j['emoji'] ?? '🎁',
        animation: j['animation'] ?? 'confetti',
        coins: j['coins'] ?? 0,
      );
}

class GiftEvent {
  final GiftModel gift;
  final int quantity;
  final String senderName;
  final String senderAvatar;

  const GiftEvent({
    required this.gift,
    required this.quantity,
    required this.senderName,
    required this.senderAvatar,
  });
}
