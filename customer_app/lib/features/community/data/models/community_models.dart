import '../../../../core/constants/app_constants.dart';

String _fixUrl(String? url) {
  if (url == null || url.isEmpty) return '';
  final canonical  = 'https://${AppConstants.baseDomain}';
  final api        = 'https://${AppConstants.apiDomain}';
  final mediaProxy = '$canonical/api/v1/media?f=';
  return url
      .replaceAll('$api/', '$canonical/')
      .replaceAll('http://${AppConstants.apiDomain}/', '$canonical/')
      .replaceAll('$canonical/storage/', mediaProxy)
      .replaceAll('http://${AppConstants.baseDomain}/storage/', mediaProxy);
}

String? _fixUrlNullable(String? url) {
  if (url == null || url.isEmpty) return null;
  return _fixUrl(url);
}

class CommunityUser {
  final int id;
  final String name;
  final String? username;
  final String? avatar;
  final String? coverPhoto;
  final String? bio;
  final String? location;
  final String? website;
  final bool isVerified;
  final bool isBusiness;
  final int followersCount;
  final int followingCount;
  final int postsCount;
  bool isFollowing;
  final bool isMe;

  CommunityUser({
    required this.id,
    required this.name,
    this.username,
    this.avatar,
    this.coverPhoto,
    this.bio,
    this.location,
    this.website,
    this.isVerified = false,
    this.isBusiness = false,
    this.followersCount = 0,
    this.followingCount = 0,
    this.postsCount = 0,
    this.isFollowing = false,
    this.isMe = false,
  });

  factory CommunityUser.fromJson(Map<String, dynamic> j) => CommunityUser(
        id: j['id'] as int,
        name: j['name'] as String,
        username: j['username'] as String?,
        avatar: _fixUrlNullable(j['avatar'] as String?),
        coverPhoto: _fixUrlNullable(j['cover_photo'] as String?),
        bio: j['bio'] as String?,
        location: j['location'] as String?,
        website: j['website'] as String?,
        isVerified: j['is_verified'] as bool? ?? false,
        isBusiness: j['is_business'] as bool? ?? false,
        followersCount: j['followers_count'] as int? ?? 0,
        followingCount: j['following_count'] as int? ?? 0,
        postsCount: j['posts_count'] as int? ?? 0,
        isFollowing: j['is_following'] as bool? ?? false,
        isMe: j['is_me'] as bool? ?? false,
      );
}

class CommunityPostMedia {
  final int id;
  final String type; // image | video
  final String url;
  final String? hlsUrl;
  final String? thumbnail;
  final int? duration;
  final int? width;
  final int? height;
  // Transcoding pipeline state — set by backend, polled by Flutter
  final String transcodingStatus; // none | pending | processing | ready | failed
  final int transcodingProgress;  // 0-100

  const CommunityPostMedia({
    required this.id,
    required this.type,
    required this.url,
    this.hlsUrl,
    this.thumbnail,
    this.duration,
    this.width,
    this.height,
    this.transcodingStatus = 'none',
    this.transcodingProgress = 0,
  });

  bool get isTranscoding => transcodingStatus == 'pending' || transcodingStatus == 'processing';
  bool get transcodingFailed => transcodingStatus == 'failed';

  double? get aspectRatio => width != null && height != null && height! > 0 ? width! / height! : null;

  /// Direct nginx URL for the optimized MP4, derived from the HLS URL.
  ///
  /// HLS:  https://esahlan.com/hls/{dir}/hls/master.m3u8
  /// MP4:  https://esahlan.com/hls/{dir}/optimized.mp4
  ///
  /// Uses the /hls/ nginx location directly (no PHP proxy, sendfile, Cloudflare-
  /// cacheable, Range-request support built-in). MP4+faststart needs only 1 network
  /// round-trip to start playing vs HLS's 3 round-trips (master → quality → segment).
  String get mp4DirectUrl {
    final hls = hlsUrl;
    if (hls != null && hls.contains('/hls/master.m3u8')) {
      return hls.replaceFirst(RegExp(r'/hls/master\.m3u8$'), '/optimized.mp4');
    }
    return url; // fallback to stored url (PHP proxy, still works)
  }

  factory CommunityPostMedia.fromJson(Map<String, dynamic> j) =>
      CommunityPostMedia(
        id: j['id'] as int,
        type: j['type'] as String,
        url: _fixUrl(j['url'] as String?),
        hlsUrl: _fixUrlNullable(j['hls_url'] as String?),
        thumbnail: _fixUrlNullable(j['thumbnail'] as String?),
        duration: j['duration'] as int?,
        width: j['width'] as int?,
        height: j['height'] as int?,
        transcodingStatus: j['transcoding_status'] as String? ?? 'none',
        transcodingProgress: j['transcoding_progress'] as int? ?? 0,
      );
}

class PollOption {
  final String text;
  int votes;

  PollOption({required this.text, this.votes = 0});

  factory PollOption.fromJson(Map<String, dynamic> j) =>
      PollOption(text: j['text'] as String, votes: j['votes'] as int? ?? 0);
}

class CommunityPost {
  final int id;
  final String type; // text|image|video|reel|poll|share|service
  final String? content;
  final String? location;
  final String? feeling;
  final String privacy;
  final bool isPinned;
  final bool commentsDisabled;
  int viewsCount;
  int likesCount;
  int commentsCount;
  int sharesCount;
  int savesCount;
  final List<PollOption> pollOptions;
  final DateTime createdAt;
  final List<CommunityPostMedia> media;
  final CommunityUser user;
  String? userReaction;
  bool isSaved;

  // Shared post
  final Map<String, dynamic>? sharedPost;
  // Page & Ad fields
  final int? pageId;
  final Map<String, dynamic>? page;
  final bool isAd;
  final String? adTitle;
  final String? adMediaUrl;
  final String? adThumbnailUrl;
  final String? adCtaText;
  final String? adCtaUrl;
  final String? adType; // image|video
  final Map<String, dynamic>? adPage;

  CommunityPost({
    required this.id,
    required this.type,
    this.content,
    this.location,
    this.feeling,
    this.privacy = 'public',
    this.isPinned = false,
    this.commentsDisabled = false,
    this.viewsCount = 0,
    this.likesCount = 0,
    this.commentsCount = 0,
    this.sharesCount = 0,
    this.savesCount = 0,
    this.pollOptions = const [],
    required this.createdAt,
    this.media = const [],
    required this.user,
    this.userReaction,
    this.isSaved = false,
    this.sharedPost,
    this.pageId,
    this.page,
    this.isAd = false,
    this.adTitle,
    this.adMediaUrl,
    this.adThumbnailUrl,
    this.adCtaText,
    this.adCtaUrl,
    this.adType,
    this.adPage,
  });

  factory CommunityPost.ad(Map<String, dynamic> j) => CommunityPost(
    id: j['id'] as int? ?? 0,
    type: 'ad',
    content: j['description'] as String?,
    createdAt: DateTime.now(),
    user: CommunityUser(id: 0, name: j['page']?['name'] ?? 'Sponsored'),
    isAd: true,
    adTitle: j['title'] as String?,
    adMediaUrl: j['media_url'] as String?,
    adThumbnailUrl: j['thumbnail_url'] as String?,
    adCtaText: j['cta_text'] as String?,
    adCtaUrl: j['cta_url'] as String?,
    adType: j['ad_type'] as String?,
    adPage: j['page'] as Map<String, dynamic>?,
  );

  factory CommunityPost.fromJson(Map<String, dynamic> j) => CommunityPost(
        id: j['id'] as int,
        type: j['type'] as String,
        content: j['content'] as String?,
        location: j['location'] as String?,
        feeling: j['feeling'] as String?,
        privacy: j['privacy'] as String? ?? 'public',
        isPinned: j['is_pinned'] as bool? ?? false,
        commentsDisabled: j['comments_disabled'] as bool? ?? false,
        viewsCount: j['views_count'] as int? ?? 0,
        likesCount: j['likes_count'] as int? ?? 0,
        commentsCount: j['comments_count'] as int? ?? 0,
        sharesCount: j['shares_count'] as int? ?? 0,
        savesCount: j['saves_count'] as int? ?? 0,
        pollOptions: (j['poll_options'] as List?)
                ?.map((e) => PollOption.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [],
        createdAt: DateTime.tryParse(j['created_at'] as String? ?? '') ?? DateTime.now(),
        media: (j['media'] as List?)
                ?.map((e) => CommunityPostMedia.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [],
        user: CommunityUser.fromJson(j['user'] as Map<String, dynamic>),
        userReaction: j['user_reaction'] as String?,
        isSaved: j['is_saved'] as bool? ?? false,
        sharedPost: j['shared_post'] as Map<String, dynamic>?,
        pageId: j['page_id'] as int?,
        page: j['page'] as Map<String, dynamic>?,
      );

  bool get isLiked => userReaction != null;
  bool get hasMedia => media.isNotEmpty;
  bool get isVideo => type == 'video' || type == 'reel';
  CommunityPostMedia? get firstMedia => media.isNotEmpty ? media.first : null;
}

class CommunityComment {
  final int id;
  final int postId;
  final CommunityUser user;
  final String content;
  final String? mediaUrl;
  final String? mediaType;
  int likesCount;
  int repliesCount;
  final bool isPinned;
  final int? parentId;
  final DateTime createdAt;
  bool isLiked;

  CommunityComment({
    required this.id,
    required this.postId,
    required this.user,
    required this.content,
    this.mediaUrl,
    this.mediaType,
    this.likesCount = 0,
    this.repliesCount = 0,
    this.isPinned = false,
    this.parentId,
    required this.createdAt,
    this.isLiked = false,
  });

  factory CommunityComment.fromJson(Map<String, dynamic> j) => CommunityComment(
        id: j['id'] as int,
        postId: j['post_id'] as int,
        user: CommunityUser.fromJson(j['user'] as Map<String, dynamic>),
        content: j['content'] as String? ?? '',
        mediaUrl: j['media_url'] as String?,
        mediaType: j['media_type'] as String?,
        likesCount: j['likes_count'] as int? ?? 0,
        repliesCount: j['replies_count'] as int? ?? 0,
        isPinned: j['is_pinned'] as bool? ?? false,
        parentId: j['parent_id'] as int?,
        createdAt: DateTime.tryParse(j['created_at'] as String? ?? '') ?? DateTime.now(),
      );

  CommunityComment copyWith({String? content}) => CommunityComment(
    id: id, postId: postId, user: user, content: content ?? this.content,
    mediaUrl: mediaUrl, mediaType: mediaType, likesCount: likesCount,
    repliesCount: repliesCount, isPinned: isPinned, parentId: parentId,
    createdAt: createdAt, isLiked: isLiked,
  );
}

class CommunityStory {
  final int id;
  final CommunityUser user;
  final String type; // image|video|text
  final String? mediaUrl;
  final String? thumbnail;
  final String? textContent;
  final String? bgColor;
  final String? location;
  int viewsCount;
  final DateTime expiresAt;
  final DateTime createdAt;
  bool isViewed;

  CommunityStory({
    required this.id,
    required this.user,
    required this.type,
    this.mediaUrl,
    this.thumbnail,
    this.textContent,
    this.bgColor,
    this.location,
    this.viewsCount = 0,
    required this.expiresAt,
    DateTime? createdAt,
    this.isViewed = false,
  }) : createdAt = createdAt ?? DateTime.now();

  factory CommunityStory.fromJson(Map<String, dynamic> j) => CommunityStory(
        id: j['id'] as int,
        user: j['user'] != null
            ? CommunityUser.fromJson(j['user'] as Map<String, dynamic>)
            : CommunityUser(id: 0, name: ''),
        type: j['type'] as String,
        mediaUrl: _fixUrlNullable(j['media_url'] as String?),
        thumbnail: _fixUrlNullable(j['thumbnail'] as String?),
        textContent: j['text_content'] as String?,
        bgColor: j['bg_color'] as String?,
        location: j['location'] as String?,
        viewsCount: j['views_count'] as int? ?? 0,
        expiresAt: DateTime.tryParse(j['expires_at'] as String? ?? '') ?? DateTime.now().add(const Duration(hours: 24)),
        createdAt: DateTime.tryParse(j['created_at'] as String? ?? ''),
        isViewed: j['is_viewed'] as bool? ?? false,
      );
}

class StoryGroup {
  final CommunityUser user;
  final List<CommunityStory> stories;
  final bool allViewed;

  const StoryGroup({
    required this.user,
    required this.stories,
    this.allViewed = false,
  });

  factory StoryGroup.fromJson(Map<String, dynamic> j) => StoryGroup(
        user: CommunityUser.fromJson(j['user'] as Map<String, dynamic>),
        stories: (j['stories'] as List)
            .map((e) => CommunityStory.fromJson(e as Map<String, dynamic>))
            .toList(),
        allViewed: j['all_viewed'] as bool? ?? false,
      );
}

class CommunityGroup {
  final int id;
  final String name;
  final String slug;
  final String? description;
  final String? coverPhoto;
  final String? avatar;
  final String privacy;
  final String category;
  final String? location;
  int membersCount;
  int postsCount;
  bool isMember;
  final String? membershipStatus;
  final bool isOwner;

  CommunityGroup({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
    this.coverPhoto,
    this.avatar,
    this.privacy = 'public',
    this.category = 'general',
    this.location,
    this.membersCount = 0,
    this.postsCount = 0,
    this.isMember = false,
    this.membershipStatus,
    this.isOwner = false,
  });

  factory CommunityGroup.fromJson(Map<String, dynamic> j) => CommunityGroup(
        id: j['id'] as int,
        name: j['name'] as String,
        slug: j['slug'] as String,
        description: j['description'] as String?,
        coverPhoto: j['cover_photo'] as String?,
        avatar: j['avatar'] as String?,
        privacy: j['privacy'] as String? ?? 'public',
        category: j['category'] as String? ?? 'general',
        location: j['location'] as String?,
        membersCount: j['members_count'] as int? ?? 0,
        postsCount: j['posts_count'] as int? ?? 0,
        isMember: j['is_member'] as bool? ?? false,
        membershipStatus: j['membership_status'] as String?,
        isOwner: j['is_owner'] as bool? ?? false,
      );
}

class CommunityMessage {
  final int id;
  final int chatId;
  final CommunityUser user;
  final String type; // text|image|video|audio
  final String? content;
  final String? mediaUrl;
  final int? duration;
  final bool isDeleted;
  final bool isMe;
  final bool isRead;
  final DateTime createdAt;

  const CommunityMessage({
    required this.id,
    required this.chatId,
    required this.user,
    required this.type,
    this.content,
    this.mediaUrl,
    this.duration,
    this.isDeleted = false,
    this.isMe = false,
    this.isRead = false,
    required this.createdAt,
  });

  factory CommunityMessage.fromJson(Map<String, dynamic> j, int myId) =>
      CommunityMessage(
        id: j['id'] as int,
        chatId: j['chat_id'] as int,
        user: CommunityUser.fromJson(j['user'] as Map<String, dynamic>),
        type: j['type'] as String? ?? 'text',
        content: j['is_deleted'] == true ? 'Message deleted' : j['content'] as String?,
        mediaUrl: j['media_url'] as String?,
        duration: j['duration'] as int?,
        isDeleted: j['is_deleted'] as bool? ?? false,
        isMe: (j['user_id'] as int?) == myId,
        isRead: j['is_read'] == true || j['read_at'] != null,
        createdAt: DateTime.tryParse(j['created_at'] as String? ?? '') ?? DateTime.now(),
      );
}

class CommunityChat {
  final int id;
  final String type;
  final String? name;
  final String? avatar;
  final CommunityUser? otherUser;
  final Map<String, dynamic>? lastMessage;
  int unreadCount;

  CommunityChat({
    required this.id,
    required this.type,
    this.name,
    this.avatar,
    this.otherUser,
    this.lastMessage,
    this.unreadCount = 0,
  });

  factory CommunityChat.fromJson(Map<String, dynamic> j) => CommunityChat(
        id: j['id'] as int,
        type: j['type'] as String,
        name: j['name'] as String?,
        avatar: j['avatar'] as String?,
        otherUser: j['other_user'] != null
            ? CommunityUser.fromJson(j['other_user'] as Map<String, dynamic>)
            : null,
        lastMessage: j['last_message'] as Map<String, dynamic>?,
        unreadCount: j['unread_count'] as int? ?? 0,
      );
}

class CommunityNotification {
  final int id;
  final CommunityUser? actor;
  final String type;
  final String? notifiableType;
  final int? notifiableId;
  final bool isRead;
  final DateTime createdAt;

  const CommunityNotification({
    required this.id,
    this.actor,
    required this.type,
    this.notifiableType,
    this.notifiableId,
    this.isRead = false,
    required this.createdAt,
  });

  factory CommunityNotification.fromJson(Map<String, dynamic> j) =>
      CommunityNotification(
        id: j['id'] as int,
        actor: j['actor'] != null
            ? CommunityUser.fromJson(j['actor'] as Map<String, dynamic>)
            : null,
        type: j['type'] as String? ?? 'unknown',
        notifiableType: j['notifiable_type'] as String?,
        notifiableId: j['notifiable_id'] as int?,
        isRead: j['is_read'] as bool? ?? false,
        createdAt: DateTime.tryParse(j['created_at'] as String? ?? '') ?? DateTime.now(),
      );
}
