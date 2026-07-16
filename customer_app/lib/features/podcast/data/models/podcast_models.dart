import 'package:flutter/foundation.dart';

// ─── Category ─────────────────────────────────────────────────────────────────
class PodcastCategory {
  final int id;
  final String name;
  final String slug;
  final String icon;
  final String color;
  final int podcastCount;

  const PodcastCategory({required this.id, required this.name, required this.slug,
      required this.icon, required this.color, required this.podcastCount});

  factory PodcastCategory.fromJson(Map<String, dynamic> j) => PodcastCategory(
      id: j['id'], name: j['name'] ?? '', slug: j['slug'] ?? '',
      icon: j['icon'] ?? '🎙', color: j['color'] ?? '#FF8A00',
      podcastCount: j['podcast_count'] ?? 0);
}

// ─── Podcast ──────────────────────────────────────────────────────────────────
class Podcast {
  final int id;
  final String title;
  final String slug;
  final String? coverImage;
  final String? description;
  final int totalEpisodes;
  final int totalFollowers;
  final int totalPlays;
  final double rating;
  final bool isVerified;
  final bool isFollowing;
  final PodcastCategory? category;

  const Podcast({required this.id, required this.title, required this.slug,
      this.coverImage, this.description, required this.totalEpisodes,
      required this.totalFollowers, required this.totalPlays, required this.rating,
      required this.isVerified, required this.isFollowing, this.category});

  factory Podcast.fromJson(Map<String, dynamic> j) => Podcast(
      id: j['id'], title: j['title'] ?? '', slug: j['slug'] ?? '',
      coverImage: j['cover_image'], description: j['description'],
      totalEpisodes: j['total_episodes'] ?? 0,
      totalFollowers: j['total_followers'] ?? 0,
      totalPlays: j['total_plays'] ?? 0,
      rating: (j['rating'] ?? 0.0).toDouble(),
      isVerified: j['is_verified'] == true,
      isFollowing: j['is_following'] == true,
      category: j['category'] != null ? PodcastCategory.fromJson(j['category']) : null);
}

// ─── Episode ──────────────────────────────────────────────────────────────────
class PodcastEpisode {
  final int id;
  final String title;
  final String slug;
  final String? coverImage;
  final int duration;
  final String durationFmt;
  final int playCount;
  final int likeCount;
  final int commentCount;
  final String? audioUrl;
  final bool isLiked;
  final bool isSaved;
  final int resumePosition;
  final String? publishedAt;
  final String? description;
  final PodcastMini? podcast;

  const PodcastEpisode({required this.id, required this.title, required this.slug,
      this.coverImage, required this.duration, required this.durationFmt,
      required this.playCount, required this.likeCount, this.commentCount = 0,
      this.audioUrl, required this.isLiked, required this.isSaved,
      required this.resumePosition, this.publishedAt, this.description, this.podcast});

  factory PodcastEpisode.fromJson(Map<String, dynamic> j) => PodcastEpisode(
      id: j['id'], title: j['title'] ?? '', slug: j['slug'] ?? '',
      coverImage: j['cover_image'],
      duration: j['duration'] ?? 0,
      durationFmt: j['duration_fmt'] ?? j['duration_formatted'] ?? '0:00',
      playCount: j['play_count'] ?? 0,
      likeCount: j['like_count'] ?? 0,
      commentCount: j['comment_count'] ?? 0,
      audioUrl: j['audio_url'],
      isLiked: j['is_liked'] == true,
      isSaved: j['is_saved'] == true,
      resumePosition: j['resume_position'] ?? 0,
      publishedAt: j['published_at'],
      description: j['description'],
      podcast: j['podcast'] != null ? PodcastMini.fromJson(j['podcast']) : null);

  double get progressRatio =>
      duration > 0 ? (resumePosition / duration).clamp(0.0, 1.0) : 0.0;
}

// ─── Podcast Mini (inside episode) ────────────────────────────────────────────
class PodcastMini {
  final int id;
  final String title;
  final String? coverImage;
  final bool isVerified;

  const PodcastMini({required this.id, required this.title, this.coverImage, required this.isVerified});

  factory PodcastMini.fromJson(Map<String, dynamic> j) => PodcastMini(
      id: j['id'], title: j['title'] ?? '',
      coverImage: j['cover_image'], isVerified: j['is_verified'] == true);
}

// ─── Continue Listening Item ───────────────────────────────────────────────────
class ContinueItem {
  final PodcastEpisode episode;
  final int position;
  final int progress;

  const ContinueItem({required this.episode, required this.position, required this.progress});

  factory ContinueItem.fromJson(Map<String, dynamic> j) => ContinueItem(
      episode: PodcastEpisode.fromJson(j['episode']),
      position: j['position'] ?? 0,
      progress: j['progress'] ?? 0);
}

// ─── Home Stats ───────────────────────────────────────────────────────────────
class PodcastStats {
  final int totalPodcasts;
  final int totalCategories;
  final int totalEpisodes;
  final int newThisWeek;
  final int liveRooms;

  const PodcastStats({required this.totalPodcasts, required this.totalCategories,
      required this.totalEpisodes, required this.newThisWeek, required this.liveRooms});

  factory PodcastStats.fromJson(Map<String, dynamic> j) => PodcastStats(
      totalPodcasts: j['total_podcasts'] ?? 0,
      totalCategories: j['total_categories'] ?? 0,
      totalEpisodes: j['total_episodes'] ?? 0,
      newThisWeek: j['new_this_week'] ?? 0,
      liveRooms: j['live_rooms'] ?? 0);
}

// ─── Home Data ────────────────────────────────────────────────────────────────
@immutable
class PodcastHomeData {
  final PodcastStats stats;
  final List<Podcast> heroFeatured;
  final List<ContinueItem> continueListening;
  final List<Podcast> popularPodcasts;
  final List<PodcastEpisode> newReleases;
  final List<PodcastEpisode> trendingToday;
  final List<Podcast> recommended;
  final List<PodcastEpisode> topCharts;
  final List<PodcastCategory> categories;
  final List<dynamic> liveRooms;
  final List<dynamic> verifiedCreators;

  const PodcastHomeData({
    required this.stats, required this.heroFeatured, required this.continueListening,
    required this.popularPodcasts, required this.newReleases, required this.trendingToday,
    required this.recommended, required this.topCharts, required this.categories,
    required this.liveRooms, required this.verifiedCreators,
  });

  factory PodcastHomeData.fromJson(Map<String, dynamic> j) => PodcastHomeData(
      stats: PodcastStats.fromJson(j['stats'] ?? {}),
      heroFeatured: _list(j['hero_featured'], Podcast.fromJson),
      continueListening: _list(j['continue_listening'], ContinueItem.fromJson),
      popularPodcasts: _list(j['popular_podcasts'], Podcast.fromJson),
      newReleases: _list(j['new_releases'], PodcastEpisode.fromJson),
      trendingToday: _list(j['trending_today'], PodcastEpisode.fromJson),
      recommended: _list(j['recommended'], Podcast.fromJson),
      topCharts: _list(j['top_charts'], PodcastEpisode.fromJson),
      categories: _list(j['categories'], PodcastCategory.fromJson),
      liveRooms: (j['live_rooms'] as List? ?? []),
      verifiedCreators: (j['verified_creators'] as List? ?? []));

  static List<T> _list<T>(dynamic src, T Function(Map<String, dynamic>) fn) =>
      (src as List? ?? []).map((e) => fn(Map<String, dynamic>.from(e))).toList();
}

// ─── Comment ──────────────────────────────────────────────────────────────────
class PodcastComment {
  final int id;
  final String body;
  final int likes;
  final bool isPinned;
  final bool isLiked;
  final int replyCount;
  final String? createdAt;
  final int userId;
  final String userName;
  final String? userAvatar;

  const PodcastComment({required this.id, required this.body, required this.likes,
      required this.isPinned, required this.isLiked, required this.replyCount,
      this.createdAt, required this.userId, required this.userName, this.userAvatar});

  factory PodcastComment.fromJson(Map<String, dynamic> j) => PodcastComment(
      id: j['id'], body: j['body'] ?? '', likes: j['likes'] ?? 0,
      isPinned: j['is_pinned'] == true, isLiked: j['is_liked'] == true,
      replyCount: j['reply_count'] ?? 0, createdAt: j['created_at'],
      userId: j['user_id'], userName: j['user_name'] ?? '',
      userAvatar: j['user_avatar']);
}
