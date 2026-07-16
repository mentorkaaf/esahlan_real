import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/podcast_models.dart';
import '../providers/podcast_provider.dart';
import '../services/podcast_audio_service.dart';
import 'episode_player_screen.dart';
import 'podcast_search_screen.dart';
import 'create_podcast_screen.dart';

class PodcastHomeScreen extends ConsumerWidget {
  const PodcastHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final homeAsync = ref.watch(podcastHomeProvider);

    return Scaffold(
      backgroundColor: const Color(0xFF0A0A0F),
      body: homeAsync.when(
        loading: () => const _PodcastSkeleton(),
        error: (e, _) => _ErrorView(onRetry: () => ref.invalidate(podcastHomeProvider)),
        data: (data) => _HomeContent(data: data),
      ),
    );
  }
}

class _HomeContent extends StatelessWidget {
  final PodcastHomeData data;
  const _HomeContent({required this.data});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const ClampingScrollPhysics(),
      slivers: [
        _PodcastAppBar(),
        SliverToBoxAdapter(child: _SearchBar()),
        if (data.continueListening.isNotEmpty) ...[
          _SectionHeader(title: 'Sii Dhegeyso', icon: '▶'),
          SliverToBoxAdapter(child: _ContinueListeningRow(items: data.continueListening)),
        ],
        if (data.featured.isNotEmpty) ...[
          _SectionHeader(title: 'Muuqashada', icon: '⭐'),
          SliverToBoxAdapter(child: _FeaturedBanner(podcasts: data.featured)),
        ],
        _SectionHeader(title: 'Qaybaha', icon: '📚'),
        SliverToBoxAdapter(child: _CategoriesGrid(categories: data.categories)),
        if (data.trendingEpisodes.isNotEmpty) ...[
          _SectionHeader(title: 'Trending Maanta', icon: '🔥'),
          SliverToBoxAdapter(child: _EpisodeRow(episodes: data.trendingEpisodes)),
        ],
        if (data.followingUpdates.isNotEmpty) ...[
          _SectionHeader(title: 'Ku Raacday', icon: '🎙'),
          SliverToBoxAdapter(child: _EpisodeRow(episodes: data.followingUpdates)),
        ],
        if (data.newPodcasts.isNotEmpty) ...[
          _SectionHeader(title: 'Cusub', icon: '✨'),
          SliverToBoxAdapter(child: _PodcastRow(podcasts: data.newPodcasts)),
        ],
        const SliverToBoxAdapter(child: SizedBox(height: 80)),
      ],
    );
  }
}

// ─── AppBar ──────────────────────────────────────────────────────────────────

class _PodcastAppBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return SliverAppBar(
      backgroundColor: const Color(0xFF0A0A0F),
      pinned: true,
      expandedHeight: 120,
      flexibleSpace: FlexibleSpaceBar(
        titlePadding: const EdgeInsets.only(left: 20, bottom: 14),
        title: const Text(
          '🎙 Podcast',
          style: TextStyle(
            color: Colors.white,
            fontSize: 22,
            fontWeight: FontWeight.w800,
            letterSpacing: -0.5,
          ),
        ),
        background: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [Color(0xFF1A0A2E), Color(0xFF0A0A0F)],
            ),
          ),
        ),
      ),
      actions: [
        IconButton(
          icon: const Icon(Icons.search_rounded, color: Colors.white70),
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PodcastSearchScreen())),
        ),
        IconButton(
          icon: const Icon(Icons.add_circle_outline_rounded, color: Colors.white70),
          onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const CreatePodcastScreen())),
        ),
      ],
    );
  }
}

// ─── Search Bar ──────────────────────────────────────────────────────────────

class _SearchBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: GestureDetector(
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PodcastSearchScreen())),
        child: Container(
          height: 44,
          decoration: BoxDecoration(
            color: const Color(0xFF1C1C28),
            borderRadius: BorderRadius.circular(12),
          ),
          child: const Row(
            children: [
              SizedBox(width: 12),
              Icon(Icons.search_rounded, color: Color(0xFF6B6B80), size: 20),
              SizedBox(width: 8),
              Text('Podcast ama episode raadi...', style: TextStyle(color: Color(0xFF6B6B80), fontSize: 14)),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Section Header ──────────────────────────────────────────────────────────

class _SectionHeader extends StatelessWidget {
  final String title;
  final String icon;
  const _SectionHeader({required this.title, required this.icon});

  @override
  Widget build(BuildContext context) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 10),
        child: Row(
          children: [
            Text(icon, style: const TextStyle(fontSize: 16)),
            const SizedBox(width: 6),
            Text(
              title,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 17,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Continue Listening ──────────────────────────────────────────────────────

class _ContinueListeningRow extends StatelessWidget {
  final List<ContinueListeningItem> items;
  const _ContinueListeningRow({required this.items});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 90,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: items.length,
        itemBuilder: (_, i) {
          final item = items[i];
          return _ContinueCard(item: item);
        },
      ),
    );
  }
}

class _ContinueCard extends StatelessWidget {
  final ContinueListeningItem item;
  const _ContinueCard({required this.item});

  void _play(BuildContext context) {
    PodcastAudioService.instance.play(item.episode);
    Navigator.of(context).push(PageRouteBuilder(
      pageBuilder: (_, __, ___) => const EpisodePlayerScreen(),
      transitionsBuilder: (_, anim, __, child) => SlideTransition(
        position: Tween<Offset>(begin: const Offset(0, 1), end: Offset.zero)
            .animate(CurvedAnimation(parent: anim, curve: Curves.easeOutCubic)),
        child: child,
      ),
    ));
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _play(context),
      child: Container(
      width: 280,
      margin: const EdgeInsets.only(right: 12),
      decoration: BoxDecoration(
        color: const Color(0xFF1C1C28),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: const BorderRadius.horizontal(left: Radius.circular(12)),
            child: _CoverImage(url: item.episode.coverImage, size: 90),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text(
                  item.episode.title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 4),
                LinearProgressIndicator(
                  value: item.progress / 100,
                  backgroundColor: const Color(0xFF2A2A3A),
                  valueColor: const AlwaysStoppedAnimation<Color>(Color(0xFF7C3AED)),
                  minHeight: 3,
                ),
                const SizedBox(height: 4),
                Text(
                  '${item.progress}% dhegaysatay',
                  style: const TextStyle(color: Color(0xFF6B6B80), fontSize: 10),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Container(
            margin: const EdgeInsets.only(right: 10),
            decoration: const BoxDecoration(
              color: Color(0xFF7C3AED),
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 22),
          ),
        ],
      ),
      ),
    );
  }
}

// ─── Featured Banner ─────────────────────────────────────────────────────────

class _FeaturedBanner extends StatefulWidget {
  final List<Podcast> podcasts;
  const _FeaturedBanner({required this.podcasts});

  @override
  State<_FeaturedBanner> createState() => _FeaturedBannerState();
}

class _FeaturedBannerState extends State<_FeaturedBanner> {
  final _ctrl = PageController(viewportFraction: 0.88);
  int _current = 0;

  @override
  void dispose() {
    _ctrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          height: 200,
          child: PageView.builder(
            controller: _ctrl,
            onPageChanged: (i) => setState(() => _current = i),
            itemCount: widget.podcasts.length,
            itemBuilder: (_, i) {
              final p = widget.podcasts[i];
              return _FeaturedCard(podcast: p);
            },
          ),
        ),
        const SizedBox(height: 10),
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: List.generate(widget.podcasts.length, (i) {
            return AnimatedContainer(
              duration: const Duration(milliseconds: 200),
              width: _current == i ? 20 : 6,
              height: 6,
              margin: const EdgeInsets.symmetric(horizontal: 2),
              decoration: BoxDecoration(
                color: _current == i ? const Color(0xFF7C3AED) : const Color(0xFF2A2A3A),
                borderRadius: BorderRadius.circular(3),
              ),
            );
          }),
        ),
      ],
    );
  }
}

class _FeaturedCard extends StatelessWidget {
  final Podcast podcast;
  const _FeaturedCard({required this.podcast});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 6),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        color: const Color(0xFF1C1C28),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: Stack(
          fit: StackFit.expand,
          children: [
            _CoverImage(url: podcast.coverImage, size: 200, fit: BoxFit.cover),
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Colors.transparent, Color(0xCC000000)],
                  stops: [0.4, 1.0],
                ),
              ),
            ),
            Positioned(
              left: 14,
              right: 14,
              bottom: 14,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (podcast.category != null)
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: _parseColor(podcast.category!.color).withOpacity(0.85),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        '${podcast.category!.icon} ${podcast.category!.name}',
                        style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600),
                      ),
                    ),
                  const SizedBox(height: 6),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          podcast.title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(color: Colors.white, fontSize: 16, fontWeight: FontWeight.w700),
                        ),
                      ),
                      if (podcast.isVerified)
                        const Padding(
                          padding: EdgeInsets.only(left: 4),
                          child: Icon(Icons.verified_rounded, color: Color(0xFF7C3AED), size: 16),
                        ),
                    ],
                  ),
                  Text(
                    '${podcast.totalEpisodes} episodes · ⭐ ${podcast.rating}',
                    style: const TextStyle(color: Colors.white70, fontSize: 11),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Color _parseColor(String hex) {
    try {
      return Color(int.parse(hex.replaceFirst('#', '0xFF')));
    } catch (_) {
      return const Color(0xFF7C3AED);
    }
  }
}

// ─── Categories Grid ─────────────────────────────────────────────────────────

class _CategoriesGrid extends StatelessWidget {
  final List<PodcastCategory> categories;
  const _CategoriesGrid({required this.categories});

  @override
  Widget build(BuildContext context) {
    final show = categories.take(12).toList();
    return GridView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.symmetric(horizontal: 16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        childAspectRatio: 2.4,
        crossAxisSpacing: 8,
        mainAxisSpacing: 8,
      ),
      itemCount: show.length,
      itemBuilder: (_, i) => _CategoryChip(cat: show[i]),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  final PodcastCategory cat;
  const _CategoryChip({required this.cat});

  @override
  Widget build(BuildContext context) {
    Color bg;
    try {
      bg = Color(int.parse(cat.color.replaceFirst('#', '0xFF'))).withOpacity(0.15);
    } catch (_) {
      bg = const Color(0xFF7C3AED).withOpacity(0.15);
    }
    return GestureDetector(
      onTap: () {},
      child: Container(
        decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(10)),
        child: Center(
          child: Text(
            '${cat.icon} ${cat.name}',
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w600),
          ),
        ),
      ),
    );
  }
}

// ─── Episode Row ─────────────────────────────────────────────────────────────

class _EpisodeRow extends StatelessWidget {
  final List<PodcastEpisode> episodes;
  const _EpisodeRow({required this.episodes});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 200,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: episodes.length,
        itemBuilder: (_, i) => _EpisodeCard(episode: episodes[i]),
      ),
    );
  }
}

class _EpisodeCard extends StatelessWidget {
  final PodcastEpisode episode;
  const _EpisodeCard({required this.episode});

  void _play(BuildContext context) {
    PodcastAudioService.instance.play(episode);
    Navigator.of(context).push(PageRouteBuilder(
      pageBuilder: (_, __, ___) => const EpisodePlayerScreen(),
      transitionsBuilder: (_, anim, __, child) => SlideTransition(
        position: Tween<Offset>(begin: const Offset(0, 1), end: Offset.zero)
            .animate(CurvedAnimation(parent: anim, curve: Curves.easeOutCubic)),
        child: child,
      ),
    ));
  }

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => _play(context),
      child: Container(
      width: 140,
      margin: const EdgeInsets.only(right: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: Stack(
              children: [
                _CoverImage(url: episode.coverImage, size: 140),
                Positioned(
                  bottom: 6,
                  right: 6,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                    decoration: BoxDecoration(
                      color: Colors.black.withOpacity(0.75),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      episode.durationFmt,
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600),
                    ),
                  ),
                ),
                Positioned(
                  bottom: 6,
                  left: 6,
                  child: Container(
                    padding: const EdgeInsets.all(5),
                    decoration: const BoxDecoration(color: Color(0xFF7C3AED), shape: BoxShape.circle),
                    child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 14),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 6),
          Text(
            episode.title,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600, height: 1.3),
          ),
          if (episode.podcast != null)
            Text(
              episode.podcast!.title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Color(0xFF6B6B80), fontSize: 10),
            ),
        ],
      ),
      ),
    );
  }
}

// ─── Podcast Row ─────────────────────────────────────────────────────────────

class _PodcastRow extends StatelessWidget {
  final List<Podcast> podcasts;
  const _PodcastRow({required this.podcasts});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 170,
      child: ListView.builder(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        itemCount: podcasts.length,
        itemBuilder: (_, i) => _PodcastCard(podcast: podcasts[i]),
      ),
    );
  }
}

class _PodcastCard extends StatelessWidget {
  final Podcast podcast;
  const _PodcastCard({required this.podcast});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 120,
      margin: const EdgeInsets.only(right: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(12),
            child: _CoverImage(url: podcast.coverImage, size: 120),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              Expanded(
                child: Text(
                  podcast.title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w600, height: 1.3),
                ),
              ),
              if (podcast.isVerified)
                const Icon(Icons.verified_rounded, color: Color(0xFF7C3AED), size: 12),
            ],
          ),
          Text(
            '${podcast.totalEpisodes} ep',
            style: const TextStyle(color: Color(0xFF6B6B80), fontSize: 10),
          ),
        ],
      ),
    );
  }
}

// ─── Shared Widgets ──────────────────────────────────────────────────────────

class _CoverImage extends StatelessWidget {
  final String? url;
  final double size;
  final BoxFit fit;
  const _CoverImage({this.url, required this.size, this.fit = BoxFit.cover});

  @override
  Widget build(BuildContext context) {
    if (url == null) {
      return _Placeholder(size: size);
    }
    return Image.network(
      url!,
      width: size,
      height: size,
      fit: fit,
      errorBuilder: (_, __, ___) => _Placeholder(size: size),
      loadingBuilder: (_, child, progress) =>
          progress == null ? child : _Placeholder(size: size),
    );
  }
}

class _Placeholder extends StatelessWidget {
  final double size;
  const _Placeholder({required this.size});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      color: const Color(0xFF1C1C28),
      child: const Icon(Icons.podcasts_rounded, color: Color(0xFF3A3A4A), size: 32),
    );
  }
}

class _PodcastSkeleton extends StatelessWidget {
  const _PodcastSkeleton();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.podcasts_rounded, color: Color(0xFF3A3A4A), size: 48),
          SizedBox(height: 16),
          Text('Podcast-yada la soo rarinayaa...', style: TextStyle(color: Color(0xFF6B6B80), fontSize: 14)),
        ],
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final VoidCallback onRetry;
  const _ErrorView({required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.wifi_off_rounded, color: Color(0xFF6B6B80), size: 48),
          const SizedBox(height: 12),
          const Text('Xiriirka kuma guulaysanin', style: TextStyle(color: Colors.white70, fontSize: 15)),
          const SizedBox(height: 16),
          TextButton(
            onPressed: onRetry,
            child: const Text('Isku Day', style: TextStyle(color: Color(0xFF7C3AED))),
          ),
        ],
      ),
    );
  }
}
