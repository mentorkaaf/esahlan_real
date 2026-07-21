String _fixLiveUrl(String? url) {
  if (url == null || url.isEmpty) return '';
  const canonical  = 'https://esahlan.com';
  const api        = 'https://api.esahlan.com';
  const mediaProxy = '$canonical/api/v1/media?f=';
  var u = url
      .replaceAll('$api/', '$canonical/')
      .replaceAll('api.esahlan.com', 'esahlan.com');
  if (u.contains('/storage/') && !u.contains(mediaProxy)) {
    u = '$mediaProxy${Uri.encodeComponent(u)}';
  }
  return u;
}

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
        avatar: _fixLiveUrl(j['avatar'] as String?),
      );
}

class LiveRoom {
  final int id;
  final String title;
  final String roomName;
  final String? thumbnail;
  final String category;
  final List<String> tags;
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
    required this.category,
    required this.tags,
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
        category: j['category'] ?? 'general',
        tags: (j['tags'] as List? ?? []).cast<String>(),
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

class LiveCategory {
  final String key;
  final String label;
  final String emoji;
  final int count;

  const LiveCategory({
    required this.key,
    required this.label,
    required this.emoji,
    required this.count,
  });

  factory LiveCategory.fromJson(Map<String, dynamic> j) => LiveCategory(
        key:   j['key']   ?? 'general',
        label: j['label'] ?? '',
        emoji: j['emoji'] ?? '🌐',
        count: j['count'] ?? 0,
      );
}

class LiveDiscovery {
  final LiveRoom? featured;
  final List<LiveRoom> following;
  final List<LiveRoom> trending;
  final List<LiveRoom> all;

  const LiveDiscovery({
    this.featured,
    required this.following,
    required this.trending,
    required this.all,
  });

  factory LiveDiscovery.fromJson(Map<String, dynamic> j) => LiveDiscovery(
        featured:  j['featured'] != null
            ? LiveRoom.fromJson(Map<String, dynamic>.from(j['featured'] as Map))
            : null,
        following: _parseRooms(j['following']),
        trending:  _parseRooms(j['trending']),
        all:       _parseRooms(j['all']),
      );

  static List<LiveRoom> _parseRooms(dynamic v) =>
      (v as List? ?? []).map((e) => LiveRoom.fromJson(Map<String, dynamic>.from(e as Map))).toList();
}

class LiveSession {
  final int userId;
  final LiveRoom room;
  final String token;
  final String livekitUrl;

  const LiveSession({
    required this.userId,
    required this.room,
    required this.token,
    required this.livekitUrl,
  });

  factory LiveSession.fromJson(Map<String, dynamic> j) => LiveSession(
        userId: j['user_id'] ?? 0,
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

class LiveStats {
  final int viewerCount;
  final int peakViewers;
  final int coinsEarned;
  final int giftsReceived;
  final int likes;
  final int messages;
  final int newFollowers;
  final int durationSeconds;
  final LiveRoomSettings settings;

  const LiveStats({
    required this.viewerCount,
    required this.peakViewers,
    required this.coinsEarned,
    required this.giftsReceived,
    required this.likes,
    required this.messages,
    required this.newFollowers,
    required this.durationSeconds,
    required this.settings,
  });

  factory LiveStats.fromJson(Map<String, dynamic> j) => LiveStats(
        viewerCount:     j['viewer_count']     ?? 0,
        peakViewers:     j['peak_viewers']     ?? 0,
        coinsEarned:     j['coins_earned']     ?? 0,
        giftsReceived:   j['gifts_received']   ?? 0,
        likes:           j['likes']            ?? 0,
        messages:        j['messages']         ?? 0,
        newFollowers:    j['new_followers']    ?? 0,
        durationSeconds: j['duration_seconds'] ?? 0,
        settings: LiveRoomSettings.fromJson(
            Map<String, dynamic>.from(j['settings'] as Map? ?? {})),
      );
}

class LiveRoomSettings {
  final bool slowMode;
  final int slowModeSeconds;
  final bool followersOnly;
  final bool commentsDisabled;
  final List<String> blockedWords;

  const LiveRoomSettings({
    required this.slowMode,
    required this.slowModeSeconds,
    required this.followersOnly,
    required this.commentsDisabled,
    required this.blockedWords,
  });

  factory LiveRoomSettings.fromJson(Map<String, dynamic> j) => LiveRoomSettings(
        slowMode:         j['slow_mode'] == true,
        slowModeSeconds:  j['slow_mode_seconds'] ?? 30,
        followersOnly:    j['followers_only'] == true,
        commentsDisabled: j['comments_disabled'] == true,
        blockedWords: (j['blocked_words'] as List? ?? []).cast<String>(),
      );
}

class LeaderboardEntry {
  final int rank;
  final int userId;
  final String name;
  final String username;
  final String avatar;
  final int totalCoins;
  final int totalGifts;

  const LeaderboardEntry({
    required this.rank,
    required this.userId,
    required this.name,
    required this.username,
    required this.avatar,
    required this.totalCoins,
    required this.totalGifts,
  });

  factory LeaderboardEntry.fromJson(Map<String, dynamic> j) => LeaderboardEntry(
        rank:       j['rank']         ?? 0,
        userId:     j['user_id']      ?? 0,
        name:       j['name']         ?? '',
        username:   j['username']     ?? '',
        avatar:     j['avatar']       ?? '',
        totalCoins: j['total_coins']  ?? j['earned_coins'] ?? 0,
        totalGifts: j['total_gifts']  ?? 0,
      );
}

// ── PK Battle ────────────────────────────────────────────────────────────────

class BattleParticipant {
  final int hostId;
  final int liveRoomId;
  final String name;
  final String username;
  final String avatar;
  final int score;
  final int rank; // 1/2/3/4

  const BattleParticipant({
    required this.hostId,
    required this.liveRoomId,
    required this.name,
    required this.username,
    required this.avatar,
    required this.score,
    required this.rank,
  });

  factory BattleParticipant.fromJson(Map<String, dynamic> j) => BattleParticipant(
        hostId:    j['host_id']      ?? 0,
        liveRoomId: j['live_room_id'] ?? 0,
        name:      j['name']         ?? '',
        username:  j['username']     ?? '',
        avatar:    j['avatar']       ?? '',
        score:     j['score']        ?? 0,
        rank:      j['rank']         ?? 0,
      );

  BattleParticipant copyWith({int? score, int? rank}) => BattleParticipant(
        hostId: hostId, liveRoomId: liveRoomId, name: name,
        username: username, avatar: avatar,
        score: score ?? this.score,
        rank: rank ?? this.rank,
      );
}

class LiveBattle {
  final int id;
  final String status; // active/ended
  final int durationSeconds;
  final DateTime? endsAt;
  final int? winnerHostId;
  final List<BattleParticipant> participants;

  const LiveBattle({
    required this.id,
    required this.status,
    required this.durationSeconds,
    this.endsAt,
    this.winnerHostId,
    required this.participants,
  });

  factory LiveBattle.fromJson(Map<String, dynamic> j) => LiveBattle(
        id:              j['id']               ?? 0,
        status:          j['status']           ?? 'active',
        durationSeconds: j['duration_seconds'] ?? 180,
        endsAt:          j['ends_at'] != null ? DateTime.tryParse(j['ends_at']) : null,
        winnerHostId:    j['winner_host_id']   as int?,
        participants: (j['participants'] as List? ?? [])
            .map((e) => BattleParticipant.fromJson(Map<String, dynamic>.from(e as Map)))
            .toList(),
      );

  LiveBattle copyWithScores(List<Map<String, dynamic>> scores) {
    final updated = participants.map((p) {
      final s = scores.firstWhere((s) => s['host_id'] == p.hostId, orElse: () => {});
      if (s.isEmpty) return p;
      return p.copyWith(score: s['score'] as int? ?? p.score, rank: s['rank'] as int? ?? p.rank);
    }).toList();
    return LiveBattle(id: id, status: status, durationSeconds: durationSeconds,
        endsAt: endsAt, winnerHostId: winnerHostId, participants: updated);
  }

  int get totalScore => participants.fold(0, (s, p) => s + p.score);
}

class BattleInvite {
  final int inviteId;
  final int fromRoomId;
  final int fromHostId;
  final String fromName;
  final String fromAvatar;
  final String fromUsername;
  final int expiresIn;

  const BattleInvite({
    required this.inviteId,
    required this.fromRoomId,
    required this.fromHostId,
    required this.fromName,
    required this.fromAvatar,
    required this.fromUsername,
    required this.expiresIn,
  });

  factory BattleInvite.fromJson(Map<String, dynamic> j) => BattleInvite(
        inviteId:    j['invite_id']    ?? 0,
        fromRoomId:  j['from_room_id'] ?? 0,
        fromHostId:  j['from_host_id'] ?? 0,
        fromName:    j['from_name']    ?? '',
        fromAvatar:  j['from_avatar']  ?? '',
        fromUsername: j['from_username'] ?? '',
        expiresIn:   j['expires_in']   ?? 30,
      );
}

// ── Subscription ─────────────────────────────────────────────────────────────

class LiveSubscriptionTier {
  final String tier;
  final double priceUsd;
  final String badgeEmoji;
  final String badgeLabel;
  final List<String> perks;

  const LiveSubscriptionTier({
    required this.tier,
    required this.priceUsd,
    required this.badgeEmoji,
    required this.badgeLabel,
    required this.perks,
  });

  factory LiveSubscriptionTier.fromJson(Map<String, dynamic> j) =>
      LiveSubscriptionTier(
        tier:       j['tier']        ?? 'basic',
        priceUsd:   (j['price_usd'] as num?)?.toDouble() ?? 4.99,
        badgeEmoji: j['badge_emoji'] ?? '⭐',
        badgeLabel: j['badge_label'] ?? 'Subscriber',
        perks: (j['perks'] is List)
            ? (j['perks'] as List).cast<String>()
            : [],
      );
}

class LiveSubscription {
  final String tier;
  final DateTime expiresAt;

  const LiveSubscription({required this.tier, required this.expiresAt});

  factory LiveSubscription.fromJson(Map<String, dynamic> j) => LiveSubscription(
        tier:      j['tier']       ?? 'basic',
        expiresAt: DateTime.tryParse(j['expires_at'] ?? '') ?? DateTime.now(),
      );

  bool get isActive => expiresAt.isAfter(DateTime.now());

  String get emoji {
    switch (tier) {
      case 'supporter': return '💎';
      case 'superfan':  return '👑';
      default:          return '⭐';
    }
  }
}

// ── Live Goal ─────────────────────────────────────────────────────────────────

class LiveGoal {
  final int id;
  final String type;  // coins / gifts / likes / followers
  final String title;
  final int target;
  final int current;
  final int percent;
  final String status; // active / completed / cancelled

  const LiveGoal({
    required this.id,
    required this.type,
    required this.title,
    required this.target,
    required this.current,
    required this.percent,
    required this.status,
  });

  factory LiveGoal.fromJson(Map<String, dynamic> j) => LiveGoal(
        id:      j['id']      ?? 0,
        type:    j['type']    ?? 'coins',
        title:   j['title']   ?? '',
        target:  j['target']  ?? 0,
        current: j['current'] ?? 0,
        percent: j['percent'] ?? 0,
        status:  j['status']  ?? 'active',
      );

  String get typeEmoji {
    switch (type) {
      case 'coins':     return '🪙';
      case 'gifts':     return '🎁';
      case 'likes':     return '❤️';
      case 'followers': return '👥';
      default:          return '🎯';
    }
  }
}

// ── Q&A Question ──────────────────────────────────────────────────────────────

class LiveQuestion {
  final int id;
  final String username;
  final String avatar;
  final String question;
  final String status;

  const LiveQuestion({
    required this.id,
    required this.username,
    required this.avatar,
    required this.question,
    required this.status,
  });

  factory LiveQuestion.fromJson(Map<String, dynamic> j) => LiveQuestion(
        id:       j['id']       ?? 0,
        username: j['username'] ?? '',
        avatar:   j['avatar']   ?? '',
        question: j['question'] ?? '',
        status:   j['status']   ?? 'pending',
      );
}

// ── VOD Recording ─────────────────────────────────────────────────────────────

class LiveRecording {
  final int id;
  final int roomId;
  final String title;
  final String? recordingUrl;
  final String? thumbnailUrl;
  final int durationSeconds;
  final int viewCount;
  final String hostName;
  final String hostAvatar;
  final DateTime? createdAt;

  const LiveRecording({
    required this.id,
    required this.roomId,
    required this.title,
    this.recordingUrl,
    this.thumbnailUrl,
    required this.durationSeconds,
    required this.viewCount,
    required this.hostName,
    required this.hostAvatar,
    this.createdAt,
  });

  factory LiveRecording.fromJson(Map<String, dynamic> j) => LiveRecording(
        id:              j['id']               ?? 0,
        roomId:          j['room_id']          ?? 0,
        title:           j['title']            ?? '',
        recordingUrl:    j['recording_url']    as String?,
        thumbnailUrl:    j['thumbnail_url']    as String?,
        durationSeconds: j['duration_seconds'] ?? 0,
        viewCount:       j['view_count']       ?? 0,
        hostName:        j['host_name']        ?? '',
        hostAvatar:      j['host_avatar']      ?? '',
        createdAt: j['created_at'] != null
            ? DateTime.tryParse(j['created_at'])
            : null,
      );

  String get durationStr {
    final m = (durationSeconds ~/ 60).toString().padLeft(2, '0');
    final s = (durationSeconds % 60).toString().padLeft(2, '0');
    return '$m:$s';
  }
}

// ── Raid ──────────────────────────────────────────────────────────────────────

class RaidTarget {
  final int roomId;
  final String title;
  final String hostName;
  final String hostAvatar;
  final int viewerCount;

  const RaidTarget({
    required this.roomId,
    required this.title,
    required this.hostName,
    required this.hostAvatar,
    required this.viewerCount,
  });

  factory RaidTarget.fromJson(Map<String, dynamic> j) => RaidTarget(
        roomId:      j['room_id']      ?? 0,
        title:       j['title']        ?? '',
        hostName:    j['host_name']    ?? '',
        hostAvatar:  j['host_avatar']  ?? '',
        viewerCount: j['viewer_count'] ?? 0,
      );
}

class PinnedMessage {
  final int? messageId;
  final String message;
  final String username;

  const PinnedMessage({this.messageId, required this.message, required this.username});

  factory PinnedMessage.fromJson(Map<String, dynamic> j) => PinnedMessage(
        messageId: j['message_id'] as int?,
        message:   j['message']   ?? '',
        username:  j['username']  ?? '',
      );
}
