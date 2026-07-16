import 'package:flutter/foundation.dart';

class PodcastCategory {
  final int id;
  final String name;
  final String slug;
  final String icon;
  final String color;
  final int podcastCount;

  const PodcastCategory({
    required this.id,
    required this.name,
    required this.slug,
    required this.icon,
    required this.color,
    required this.podcastCount,
  });

  factory PodcastCategory.fromJson(Map<String, dynamic> j) => PodcastCategory(
        id: j['id'],
        name: j['name'] ?? '',
        slug: j['slug'] ?? '',
        icon: j['icon'] ?? '🎙',
        color: j['color'] ?? '#6366F1',
        podcastCount: j['podcast_count'] ?? 0,
      );
}

class Podcast {
  final int id;
  final String title;
  final String slug;
  final String? coverImage;
  final String? description;
  final int totalEpisodes;
  final int totalFollowers;
  final double rating;
  final bool isVerified;
  final bool isFollowing;
  final PodcastCategory? category;

  const Podcast({
    required this.id,
    required this.title,
    required this.slug,
    this.coverImage,
    this.description,
    required this.totalEpisodes,
    required this.totalFollowers,
    required this.rating,
    required this.isVerified,
    required this.isFollowing,
    this.category,
  });

  factory Podcast.fromJson(Map<String, dynamic> j) => Podcast(
        id: j['id'],
        title: j['title'] ?? '',
        slug: j['slug'] ?? '',
        coverImage: j['cover_image'],
        description: j['description'],
        totalEpisodes: j['total_episodes'] ?? 0,
        totalFollowers: j['total_followers'] ?? 0,
        rating: (j['rating'] ?? 0.0).toDouble(),
        isVerified: j['is_verified'] == true,
        isFollowing: j['is_following'] == true,
        category: j['category'] != null ? PodcastCategory.fromJson(j['category']) : null,
      );
}

class PodcastEpisode {
  final int id;
  final String title;
  final String slug;
  final String? coverImage;
  final int duration;
  final String durationFmt;
  final int playCount;
  final int likeCount;
  final String? audioUrl;
  final bool isLiked;
  final bool isSaved;
  final int resumePosition;
  final String? publishedAt;
  final PodcastMini? podcast;

  const PodcastEpisode({
    required this.id,
    required this.title,
    required this.slug,
    this.coverImage,
    required this.duration,
    required this.durationFmt,
    required this.playCount,
    required this.likeCount,
    this.audioUrl,
    required this.isLiked,
    required this.isSaved,
    required this.resumePosition,
    this.publishedAt,
    this.podcast,
  });

  factory PodcastEpisode.fromJson(Map<String, dynamic> j) => PodcastEpisode(
        id: j['id'],
        title: j['title'] ?? '',
        slug: j['slug'] ?? '',
        coverImage: j['cover_image'],
        duration: j['duration'] ?? 0,
        durationFmt: j['duration_fmt'] ?? '0:00',
        playCount: j['play_count'] ?? 0,
        likeCount: j['like_count'] ?? 0,
        audioUrl: j['audio_url'],
        isLiked: j['is_liked'] == true,
        isSaved: j['is_saved'] == true,
        resumePosition: j['resume_position'] ?? 0,
        publishedAt: j['published_at'],
        podcast: j['podcast'] != null ? PodcastMini.fromJson(j['podcast']) : null,
      );

  double get progressRatio =>
      duration > 0 ? (resumePosition / duration).clamp(0.0, 1.0) : 0.0;
}

class PodcastMini {
  final int id;
  final String title;
  final String? coverImage;
  final bool isVerified;

  const PodcastMini({required this.id, required this.title, this.coverImage, required this.isVerified});

  factory PodcastMini.fromJson(Map<String, dynamic> j) => PodcastMini(
        id: j['id'],
        title: j['title'] ?? '',
        coverImage: j['cover_image'],
        isVerified: j['is_verified'] == true,
      );
}

class ContinueListeningItem {
  final PodcastEpisode episode;
  final int position;
  final int progress;

  const ContinueListeningItem({required this.episode, required this.position, required this.progress});

  factory ContinueListeningItem.fromJson(Map<String, dynamic> j) => ContinueListeningItem(
        episode: PodcastEpisode.fromJson(j['episode']),
        position: j['position'] ?? 0,
        progress: j['progress'] ?? 0,
      );
}

@immutable
class PodcastHomeData {
  final List<Podcast> featured;
  final List<PodcastCategory> categories;
  final List<PodcastEpisode> trendingEpisodes;
  final List<Podcast> newPodcasts;
  final List<ContinueListeningItem> continueListening;
  final List<PodcastEpisode> followingUpdates;

  const PodcastHomeData({
    required this.featured,
    required this.categories,
    required this.trendingEpisodes,
    required this.newPodcasts,
    required this.continueListening,
    required this.followingUpdates,
  });

  factory PodcastHomeData.fromJson(Map<String, dynamic> j) => PodcastHomeData(
        featured: (j['featured'] as List? ?? []).map((e) => Podcast.fromJson(e)).toList(),
        categories: (j['categories'] as List? ?? []).map((e) => PodcastCategory.fromJson(e)).toList(),
        trendingEpisodes: (j['trending_episodes'] as List? ?? []).map((e) => PodcastEpisode.fromJson(e)).toList(),
        newPodcasts: (j['new_podcasts'] as List? ?? []).map((e) => Podcast.fromJson(e)).toList(),
        continueListening: (j['continue_listening'] as List? ?? []).map((e) => ContinueListeningItem.fromJson(e)).toList(),
        followingUpdates: (j['following_updates'] as List? ?? []).map((e) => PodcastEpisode.fromJson(e)).toList(),
      );
}
