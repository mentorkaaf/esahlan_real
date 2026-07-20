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
  final DateTime? endedAt;
  final int? durationSeconds;
  final int? totalGifts;

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
    this.endedAt,
    this.durationSeconds,
    this.totalGifts,
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
        endedAt: j['ended_at'] != null ? DateTime.tryParse(j['ended_at']) : null,
        durationSeconds: j['duration_seconds'] as int?,
        totalGifts: j['total_gifts'] as int?,
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

enum GiftRarity { normal, rare, epic, legendary }

class GiftModel {
  final int id;
  final String name;
  final String emoji;
  final String animation;
  final String? animationUrl;
  final String? iconUrl;
  final int coins;
  final int hostCoins;
  final String category;
  final GiftRarity rarity;
  final String? soundEffect;
  final bool isFeatured;

  const GiftModel({
    required this.id,
    required this.name,
    required this.emoji,
    required this.animation,
    this.animationUrl,
    this.iconUrl,
    required this.coins,
    required this.hostCoins,
    required this.category,
    required this.rarity,
    this.soundEffect,
    required this.isFeatured,
  });

  factory GiftModel.fromJson(Map<String, dynamic> j) => GiftModel(
        id: j['id'] ?? 0,
        name: j['name'] ?? '',
        emoji: j['emoji'] ?? '🎁',
        animation: j['animation'] ?? 'confetti',
        animationUrl: j['animation_url'] as String?,
        iconUrl: j['icon_url'] as String?,
        coins: j['coins'] ?? 0,
        hostCoins: j['host_coins'] ?? 0,
        category: j['category'] ?? 'normal',
        rarity: _parseRarity(j['rarity'] as String? ?? 'normal'),
        soundEffect: j['sound_effect'] as String?,
        isFeatured: j['is_featured'] == true,
      );

  static GiftRarity _parseRarity(String v) {
    switch (v) {
      case 'rare':      return GiftRarity.rare;
      case 'epic':      return GiftRarity.epic;
      case 'legendary': return GiftRarity.legendary;
      default:          return GiftRarity.normal;
    }
  }
}

class CoinPackage {
  final int id;
  final String name;
  final int coins;
  final int bonusCoins;
  final int totalCoins;
  final double price;
  final String currency;
  final String? badgeLabel;
  final bool isFeatured;

  const CoinPackage({
    required this.id,
    required this.name,
    required this.coins,
    required this.bonusCoins,
    required this.totalCoins,
    required this.price,
    required this.currency,
    this.badgeLabel,
    required this.isFeatured,
  });

  factory CoinPackage.fromJson(Map<String, dynamic> j) => CoinPackage(
        id: j['id'] ?? 0,
        name: j['name'] ?? '',
        coins: j['coins'] ?? 0,
        bonusCoins: j['bonus_coins'] ?? 0,
        totalCoins: j['total_coins'] ?? j['coins'] ?? 0,
        price: (j['price'] as num?)?.toDouble() ?? 0.0,
        currency: j['currency'] ?? 'USD',
        badgeLabel: j['badge_label'] as String?,
        isFeatured: j['is_featured'] == true,
      );
}

class LiveGuest {
  final int userId;
  final String name;
  final String username;
  final String avatar;
  final bool isMuted;
  final bool cameraDisabled;

  const LiveGuest({
    required this.userId,
    required this.name,
    required this.username,
    required this.avatar,
    required this.isMuted,
    required this.cameraDisabled,
  });

  factory LiveGuest.fromJson(Map<String, dynamic> j) => LiveGuest(
        userId: j['user_id'] ?? 0,
        name: j['name'] ?? '',
        username: j['username'] ?? '',
        avatar: j['avatar'] ?? '',
        isMuted: j['is_muted'] == true,
        cameraDisabled: j['camera_disabled'] == true,
      );
}

class GuestRequest {
  final int requestId;
  final int userId;
  final String name;
  final String username;
  final String avatar;

  const GuestRequest({
    required this.requestId,
    required this.userId,
    required this.name,
    required this.username,
    required this.avatar,
  });

  factory GuestRequest.fromJson(Map<String, dynamic> j) => GuestRequest(
        requestId: j['request_id'] ?? 0,
        userId: j['user_id'] ?? 0,
        name: j['name'] ?? '',
        username: j['username'] ?? '',
        avatar: j['avatar'] ?? '',
      );
}

class LiveChatMessage {
  final int id;
  final int userId;
  final String username;
  final String avatar;
  final String message;
  final bool isHost;
  final DateTime createdAt;

  const LiveChatMessage({
    required this.id,
    required this.userId,
    required this.username,
    required this.avatar,
    required this.message,
    required this.isHost,
    required this.createdAt,
  });

  factory LiveChatMessage.fromJson(Map<String, dynamic> j) => LiveChatMessage(
        id: j['id'] ?? 0,
        userId: j['user_id'] ?? 0,
        username: j['username'] ?? '',
        avatar: j['avatar'] ?? '',
        message: j['message'] ?? '',
        isHost: j['is_host'] == true,
        createdAt: j['created_at'] != null
            ? DateTime.tryParse(j['created_at']) ?? DateTime.now()
            : DateTime.now(),
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
