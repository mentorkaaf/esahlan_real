import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/podcast_models.dart';
import '../../data/repositories/podcast_repository.dart';
import '../providers/podcast_provider.dart';
import '../services/podcast_audio_service.dart';
import '../widgets/podcast_cover.dart';
import 'episode_player_screen.dart';
import 'all_podcasts_screen.dart';
import 'podcast_categories_screen.dart';
import 'top_charts_screen.dart';
import 'podcast_search_screen.dart';
import 'create_podcast_screen.dart';
import 'podcast_library_screen.dart';

// Brand colors (eSahlan)
const kNavy   = Color(0xFF07003B);
const kOrange = Color(0xFFFF8A00);
const kBg     = Color(0xFFF0F2F5);

class PodcastHomeScreen extends ConsumerStatefulWidget {
  const PodcastHomeScreen({super.key});
  @override
  ConsumerState<PodcastHomeScreen> createState() => _PodcastHomeScreenState();
}

class _PodcastHomeScreenState extends ConsumerState<PodcastHomeScreen> {
  int _heroCurrent = 0;
  final _heroCtrl = PageController(viewportFraction: 1.0);

  @override
  void dispose() {
    _heroCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final homeAsync = ref.watch(podcastHomeProvider);

    return Scaffold(
      backgroundColor: kBg,
      body: homeAsync.when(
        loading: () => const _HomeLoading(),
        error: (e, _) => _HomeError(onRetry: () => ref.invalidate(podcastHomeProvider)),
        data: (data) => _buildContent(data),
      ),
    );
  }

  Widget _buildContent(PodcastHomeData data) {
    return CustomScrollView(
      physics: const ClampingScrollPhysics(),
      slivers: [
        // ── AppBar ──────────────────────────────────────────────────────────
        SliverAppBar(
          backgroundColor: kNavy,
          pinned: true,
          expandedHeight: 0,
          titleSpacing: 16,
          title: Row(children: [
            const Icon(Icons.podcasts_rounded, color: kOrange, size: 22),
            const SizedBox(width: 8),
            const Text('Audio & Podcast',
                style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
            const Spacer(),
            _AppBarBtn(icon: Icons.search_rounded, onTap: () => _push(const PodcastSearchScreen())),
            _AppBarBtn(icon: Icons.notifications_outlined, onTap: () {}),
            _AppBarBtn(icon: Icons.account_circle_outlined, onTap: () {}),
          ]),
        ),

        // ── Hero Banner ─────────────────────────────────────────────────────
        if (data.heroFeatured.isNotEmpty)
          SliverToBoxAdapter(child: _HeroBanner(
            podcasts: data.heroFeatured,
            current: _heroCurrent,
            ctrl: _heroCtrl,
            onPageChanged: (i) => setState(() => _heroCurrent = i),
            onExplore: () => _push(const AllPodcastsScreen()),
            onCreate: () => _push(const CreatePodcastScreen()),
          )),

        // ── Stats Bar ────────────────────────────────────────────────────────
        SliverToBoxAdapter(child: _StatsBar(
          stats: data.stats,
          onAllPodcasts: () => _push(const AllPodcastsScreen()),
          onCategories:  () => _push(const PodcastCategoriesScreen()),
          onTopCharts:   () => _push(const TopChartsScreen()),
          onLive:        () {},
        )),

        // ── Continue Listening ───────────────────────────────────────────────
        if (data.continueListening.isNotEmpty) ...[
          _header('Sii Dhegeyso', onSeeAll: () => _push(const PodcastLibraryScreen())),
          SliverToBoxAdapter(child: _ContinueListeningRow(items: data.continueListening)),
        ],

        // ── Popular Podcasts ─────────────────────────────────────────────────
        if (data.popularPodcasts.isNotEmpty) ...[
          _header('Popular Podcasts', onSeeAll: () => _push(const AllPodcastsScreen())),
          SliverToBoxAdapter(child: _PodcastCardRow(podcasts: data.popularPodcasts)),
        ],

        // ── Top Charts ───────────────────────────────────────────────────────
        if (data.topCharts.isNotEmpty) ...[
          _header('Top Charts', onSeeAll: () => _push(const TopChartsScreen())),
          SliverToBoxAdapter(child: _TopChartsList(episodes: data.topCharts, limit: 5)),
        ],

        // ── New Releases ─────────────────────────────────────────────────────
        if (data.newReleases.isNotEmpty) ...[
          _header('New Releases', badge: 'NEW', onSeeAll: () {}),
          SliverToBoxAdapter(child: _EpisodeCardRow(episodes: data.newReleases)),
        ],

        // ── Trending Today ───────────────────────────────────────────────────
        if (data.trendingToday.isNotEmpty) ...[
          _header('Trending Maanta', onSeeAll: () {}),
          SliverToBoxAdapter(child: _EpisodeCardRow(episodes: data.trendingToday)),
        ],

        // ── Categories ──────────────────────────────────────────────────────
        if (data.categories.isNotEmpty) ...[
          _header('Categories', onSeeAll: () => _push(const PodcastCategoriesScreen())),
          SliverToBoxAdapter(child: _CategoriesRow(categories: data.categories)),
        ],

        // ── Recommended ──────────────────────────────────────────────────────
        if (data.recommended.isNotEmpty) ...[
          _header('Kuu Taliya', onSeeAll: () {}),
          SliverToBoxAdapter(child: _PodcastCardRow(podcasts: data.recommended)),
        ],

        // ── Verified Creators ─────────────────────────────────────────────────
        if (data.verifiedCreators.isNotEmpty) ...[
          _header('Verified Creators', onSeeAll: () {}),
          SliverToBoxAdapter(child: _VerifiedCreatorsRow(creators: data.verifiedCreators)),
        ],

        const SliverToBoxAdapter(child: SizedBox(height: 100)),
      ],
    );
  }

  Widget _header(String title, {String? badge, VoidCallback? onSeeAll}) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 10),
        child: Row(children: [
          Text(title,
              style: const TextStyle(color: kNavy, fontSize: 16, fontWeight: FontWeight.w800)),
          if (badge != null) ...[
            const SizedBox(width: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(4)),
              child: Text(badge, style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800)),
            ),
          ],
          const Spacer(),
          if (onSeeAll != null)
            GestureDetector(
              onTap: onSeeAll,
              child: const Text('See all', style: TextStyle(color: kOrange, fontSize: 13, fontWeight: FontWeight.w600)),
            ),
        ]),
      ),
    );
  }

  void _push(Widget screen) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
  }
}

// ─── Hero Banner ──────────────────────────────────────────────────────────────

class _HeroBanner extends StatelessWidget {
  final List<Podcast> podcasts;
  final int current;
  final PageController ctrl;
  final ValueChanged<int> onPageChanged;
  final VoidCallback onExplore;
  final VoidCallback onCreate;

  const _HeroBanner({required this.podcasts, required this.current,
      required this.ctrl, required this.onPageChanged,
      required this.onExplore, required this.onCreate});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 220,
      child: Stack(
        children: [
          PageView.builder(
            controller: ctrl,
            onPageChanged: onPageChanged,
            itemCount: podcasts.length,
            itemBuilder: (_, i) => _HeroSlide(podcast: podcasts[i],
                onExplore: onExplore, onCreate: onCreate),
          ),
          Positioned(
            bottom: 12, left: 0, right: 0,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List.generate(podcasts.length, (i) => AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                width: current == i ? 20 : 6, height: 6,
                margin: const EdgeInsets.symmetric(horizontal: 2),
                decoration: BoxDecoration(
                  color: current == i ? kOrange : Colors.white54,
                  borderRadius: BorderRadius.circular(3),
                ),
              )),
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroSlide extends StatelessWidget {
  final Podcast podcast;
  final VoidCallback onExplore;
  final VoidCallback onCreate;

  const _HeroSlide({required this.podcast, required this.onExplore, required this.onCreate});

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        PodcastCover(url: podcast.coverImage, width: double.infinity, height: 220, radius: 0),
        Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter, end: Alignment.bottomCenter,
              colors: [Color(0x88000000), Color(0xEE07003B)],
              stops: [0.0, 1.0],
            ),
          ),
        ),
        Positioned(
          left: 20, right: 20, bottom: 32,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('Welcome to', style: TextStyle(color: Colors.white70, fontSize: 12)),
            const Text('Audio & Podcast',
                style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900, height: 1.1)),
            const SizedBox(height: 4),
            const Text('Discover, listen and share amazing audio content.',
                style: TextStyle(color: Colors.white60, fontSize: 11)),
            const SizedBox(height: 12),
            Row(children: [
              _HeroBtn(label: 'Explore', filled: true, onTap: onExplore),
              const SizedBox(width: 10),
              _HeroBtn(label: '🎙 Create Audio', filled: false, onTap: onCreate),
            ]),
          ]),
        ),
      ],
    );
  }
}

class _HeroBtn extends StatelessWidget {
  final String label;
  final bool filled;
  final VoidCallback onTap;
  const _HeroBtn({required this.label, required this.filled, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: BoxDecoration(
        color: filled ? kOrange : Colors.transparent,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: filled ? kOrange : Colors.white54),
      ),
      child: Text(label,
          style: TextStyle(color: filled ? Colors.white : Colors.white70,
              fontSize: 12, fontWeight: FontWeight.w700)),
    ),
  );
}

// ─── Stats Bar ────────────────────────────────────────────────────────────────

class _StatsBar extends StatelessWidget {
  final PodcastStats stats;
  final VoidCallback onAllPodcasts;
  final VoidCallback onCategories;
  final VoidCallback onTopCharts;
  final VoidCallback onLive;

  const _StatsBar({required this.stats, required this.onAllPodcasts,
      required this.onCategories, required this.onTopCharts, required this.onLive});

  @override
  Widget build(BuildContext context) {
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: [
          _StatChip(icon: Icons.podcasts_rounded, label: 'All Podcasts',
              value: _fmt(stats.totalPodcasts), onTap: onAllPodcasts),
          _StatChip(icon: Icons.category_outlined, label: 'Categories',
              value: '${stats.totalCategories}', onTap: onCategories),
          _StatChip(icon: Icons.bar_chart_rounded, label: 'Top Charts',
              value: _fmt(stats.totalEpisodes), onTap: onTopCharts),
          _StatChip(icon: Icons.new_releases_outlined, label: 'New Releases',
              value: _fmt(stats.newThisWeek), onTap: () {}),
          _StatChip(icon: Icons.sensors_rounded, label: 'Live Audio',
              value: '${stats.liveRooms}', onTap: onLive, live: stats.liveRooms > 0),
        ],
      ),
    );
  }

  String _fmt(int n) {
    if (n >= 1000) return '${(n / 1000).toStringAsFixed(1)}K';
    return '$n';
  }
}

class _StatChip extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final VoidCallback onTap;
  final bool live;

  const _StatChip({required this.icon, required this.label,
      required this.value, required this.onTap, this.live = false});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      Stack(children: [
        Icon(icon, color: kNavy, size: 26),
        if (live) Positioned(right: 0, top: 0,
            child: Container(width: 7, height: 7,
                decoration: const BoxDecoration(color: Colors.red, shape: BoxShape.circle))),
      ]),
      const SizedBox(height: 3),
      Text(value, style: const TextStyle(color: kNavy, fontSize: 12, fontWeight: FontWeight.w800)),
      Text(label, style: const TextStyle(color: Colors.grey, fontSize: 9)),
    ]),
  );
}

// ─── Continue Listening ──────────────────────────────────────────────────────

class _ContinueListeningRow extends StatelessWidget {
  final List<ContinueItem> items;
  const _ContinueListeningRow({required this.items});

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 90,
    child: ListView.builder(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: items.length,
      itemBuilder: (_, i) => _ContinueCard(item: items[i]),
    ),
  );
}

class _ContinueCard extends StatelessWidget {
  final ContinueItem item;
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
  Widget build(BuildContext context) => GestureDetector(
    onTap: () => _play(context),
    child: Container(
      width: 290,
      margin: const EdgeInsets.only(right: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withAlpha(13), blurRadius: 8, offset: const Offset(0,2))],
      ),
      child: Row(children: [
        ClipRRect(
          borderRadius: const BorderRadius.horizontal(left: Radius.circular(12)),
          child: PodcastCover(url: item.episode.coverImage, width: 90, height: 90),
        ),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
          Text(item.episode.title, maxLines: 2, overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: kNavy, fontSize: 12, fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          LinearProgressIndicator(
            value: item.progress / 100,
            backgroundColor: const Color(0xFFE5E7EB),
            valueColor: const AlwaysStoppedAnimation<Color>(kOrange),
            minHeight: 3,
            borderRadius: BorderRadius.circular(2),
          ),
          const SizedBox(height: 4),
          Text('${item.progress}% · ${item.episode.durationFmt}',
              style: const TextStyle(color: Colors.grey, fontSize: 10)),
        ])),
        Container(
          margin: const EdgeInsets.only(right: 10),
          width: 34, height: 34,
          decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
          child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 20),
        ),
      ]),
    ),
  );
}

// ─── Podcast Card Row ─────────────────────────────────────────────────────────

class _PodcastCardRow extends StatelessWidget {
  final List<Podcast> podcasts;
  const _PodcastCardRow({required this.podcasts});

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 195,
    child: ListView.builder(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: podcasts.length,
      itemBuilder: (_, i) => _PodcastCard(podcast: podcasts[i]),
    ),
  );
}

class _PodcastCard extends StatelessWidget {
  final Podcast podcast;
  const _PodcastCard({required this.podcast});

  @override
  Widget build(BuildContext context) => Container(
    width: 130,
    margin: const EdgeInsets.only(right: 12),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Stack(children: [
        ClipRRect(
          borderRadius: BorderRadius.circular(12),
          child: PodcastCover(url: podcast.coverImage, width: 130, height: 130),
        ),
        if (podcast.isVerified) Positioned(top: 6, right: 6,
          child: Container(
            padding: const EdgeInsets.all(2),
            decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
            child: const Icon(Icons.verified_rounded, color: kOrange, size: 14),
          )),
      ]),
      const SizedBox(height: 6),
      Text(podcast.title, maxLines: 1, overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: kNavy, fontSize: 12, fontWeight: FontWeight.w700)),
      Text(podcast.category?.name ?? '', maxLines: 1, overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: Colors.grey, fontSize: 10)),
      const SizedBox(height: 2),
      Row(children: [
        const Icon(Icons.headset_rounded, color: kOrange, size: 11),
        const SizedBox(width: 3),
        Text(_fmt(podcast.totalFollowers),
            style: const TextStyle(color: kOrange, fontSize: 10, fontWeight: FontWeight.w600)),
        const SizedBox(width: 6),
        const Icon(Icons.mic_rounded, size: 11, color: Colors.grey),
        const SizedBox(width: 2),
        Text('${podcast.totalEpisodes}', style: const TextStyle(color: Colors.grey, fontSize: 10)),
      ]),
    ]),
  );

  String _fmt(int n) => n >= 1000 ? '${(n / 1000).toStringAsFixed(1)}K' : '$n';
}

// ─── Episode Card Row ─────────────────────────────────────────────────────────

class _EpisodeCardRow extends StatelessWidget {
  final List<PodcastEpisode> episodes;
  const _EpisodeCardRow({required this.episodes});

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 190,
    child: ListView.builder(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: episodes.length,
      itemBuilder: (_, i) => _EpisodeCard(episode: episodes[i]),
    ),
  );
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
  Widget build(BuildContext context) => GestureDetector(
    onTap: () => _play(context),
    child: Container(
      width: 140,
      margin: const EdgeInsets.only(right: 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Stack(children: [
          ClipRRect(borderRadius: BorderRadius.circular(12),
              child: PodcastCover(url: episode.coverImage, width: 140, height: 130)),
          Positioned(bottom: 6, right: 6,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
              decoration: BoxDecoration(
                color: Colors.black.withAlpha(179), borderRadius: BorderRadius.circular(6)),
              child: Text(episode.durationFmt,
                  style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w700)),
            )),
          Positioned(bottom: 6, left: 6,
            child: Container(
              padding: const EdgeInsets.all(5),
              decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
              child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 13),
            )),
        ]),
        const SizedBox(height: 6),
        Text(episode.title, maxLines: 2, overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: kNavy, fontSize: 11, fontWeight: FontWeight.w700, height: 1.3)),
        if (episode.podcast != null)
          Text(episode.podcast!.title, maxLines: 1, overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: Colors.grey, fontSize: 10)),
      ]),
    ),
  );
}

// ─── Top Charts List ──────────────────────────────────────────────────────────

class _TopChartsList extends StatelessWidget {
  final List<PodcastEpisode> episodes;
  final int limit;
  const _TopChartsList({required this.episodes, this.limit = 5});

  @override
  Widget build(BuildContext context) {
    final show = episodes.take(limit).toList();
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withAlpha(10), blurRadius: 8)],
        ),
        child: Column(
          children: show.asMap().entries.map((entry) {
            final rank = entry.key + 1;
            final ep   = entry.value;
            return _ChartTile(rank: rank, episode: ep, isLast: rank == show.length);
          }).toList(),
        ),
      ),
    );
  }
}

class _ChartTile extends StatelessWidget {
  final int rank;
  final PodcastEpisode episode;
  final bool isLast;
  const _ChartTile({required this.rank, required this.episode, required this.isLast});

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
  Widget build(BuildContext context) => GestureDetector(
    onTap: () => _play(context),
    child: Column(children: [
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        child: Row(children: [
          SizedBox(width: 28,
            child: Text('$rank', style: TextStyle(
              fontSize: 16, fontWeight: FontWeight.w900,
              color: rank <= 3 ? kOrange : Colors.grey.shade400,
            ))),
          ClipRRect(borderRadius: BorderRadius.circular(8),
              child: PodcastCover(url: episode.coverImage, width: 44, height: 44)),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(episode.title, maxLines: 1, overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: kNavy, fontSize: 13, fontWeight: FontWeight.w600)),
            Text(episode.podcast?.title ?? '',
                style: const TextStyle(color: Colors.grey, fontSize: 11)),
          ])),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text(episode.durationFmt,
                style: const TextStyle(color: Colors.grey, fontSize: 11)),
            Text(_fmt(episode.playCount),
                style: const TextStyle(color: kOrange, fontSize: 10, fontWeight: FontWeight.w700)),
          ]),
          const SizedBox(width: 8),
          const Icon(Icons.play_circle_fill_rounded, color: kOrange, size: 30),
        ]),
      ),
      if (!isLast) Divider(height: 1, indent: 56, endIndent: 14, color: Colors.grey.shade100),
    ]),
  );

  String _fmt(int n) => n >= 1000 ? '${(n/1000).toStringAsFixed(1)}K' : '$n';
}

// ─── Categories Row ───────────────────────────────────────────────────────────

class _CategoriesRow extends StatelessWidget {
  final List<PodcastCategory> categories;
  const _CategoriesRow({required this.categories});

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 80,
    child: ListView.builder(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: categories.length,
      itemBuilder: (_, i) => _CatChip(cat: categories[i]),
    ),
  );
}

IconData _catIcon(String icon) {
  const map = {
    'business_center': Icons.business_center_rounded,
    'school': Icons.school_rounded,
    'mosque': Icons.mosque_rounded,
    'sports_soccer': Icons.sports_soccer_rounded,
    'music_note': Icons.music_note_rounded,
    'movie': Icons.movie_rounded,
    'science': Icons.science_rounded,
    'favorite': Icons.favorite_rounded,
    'code': Icons.code_rounded,
    'attach_money': Icons.attach_money_rounded,
    'local_hospital': Icons.local_hospital_rounded,
    'restaurant': Icons.restaurant_rounded,
    'travel_explore': Icons.travel_explore_rounded,
    'palette': Icons.palette_rounded,
    'gavel': Icons.gavel_rounded,
    'nature': Icons.nature_rounded,
    'child_care': Icons.child_care_rounded,
    'psychology': Icons.psychology_rounded,
  };
  return map[icon] ?? Icons.podcasts_rounded;
}

class _CatChip extends StatelessWidget {
  final PodcastCategory cat;
  const _CatChip({required this.cat});

  @override
  Widget build(BuildContext context) {
    Color bg;
    try { bg = Color(int.parse(cat.color.replaceFirst('#', '0xFF'))).withAlpha(26); }
    catch (_) { bg = kOrange.withAlpha(26); }

    return GestureDetector(
      onTap: () {},
      child: Container(
        width: 90, margin: const EdgeInsets.only(right: 10),
        decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(14)),
        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
          Icon(_catIcon(cat.icon), color: kNavy, size: 26),
          const SizedBox(height: 4),
          Text(cat.name, maxLines: 1, overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: const TextStyle(color: kNavy, fontSize: 10, fontWeight: FontWeight.w700)),
          Text('${cat.podcastCount}',
              style: const TextStyle(color: Colors.grey, fontSize: 9)),
        ]),
      ),
    );
  }
}

// ─── Verified Creators ───────────────────────────────────────────────────────

class _VerifiedCreatorsRow extends StatelessWidget {
  final List<dynamic> creators;
  const _VerifiedCreatorsRow({required this.creators});

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 100,
    child: ListView.builder(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: creators.length,
      itemBuilder: (_, i) {
        final c = creators[i] as Map<String, dynamic>;
        return _CreatorChip(creator: c);
      },
    ),
  );
}

class _CreatorChip extends StatelessWidget {
  final Map<String, dynamic> creator;
  const _CreatorChip({required this.creator});

  @override
  Widget build(BuildContext context) => Container(
    width: 80, margin: const EdgeInsets.only(right: 14),
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      Stack(children: [
        ClipRRect(borderRadius: BorderRadius.circular(30),
            child: PodcastCover(url: creator['cover_image'], width: 56, height: 56)),
        const Positioned(right: 0, bottom: 0,
          child: Icon(Icons.verified_rounded, color: kOrange, size: 16)),
      ]),
      const SizedBox(height: 4),
      Text(creator['title'] ?? '', maxLines: 1, overflow: TextOverflow.ellipsis,
          textAlign: TextAlign.center,
          style: const TextStyle(color: kNavy, fontSize: 10, fontWeight: FontWeight.w700)),
      Text(_fmt(creator['followers'] ?? 0),
          style: const TextStyle(color: Colors.grey, fontSize: 9)),
    ]),
  );

  String _fmt(dynamic n) {
    final v = n is int ? n : (n as num).toInt();
    return v >= 1000 ? '${(v/1000).toStringAsFixed(1)}K' : '$v';
  }
}

// ─── AppBar Button ────────────────────────────────────────────────────────────

class _AppBarBtn extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  const _AppBarBtn({required this.icon, required this.onTap});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Icon(icon, color: Colors.white, size: 22),
    ),
  );
}

// ─── Shared Loading/Error ─────────────────────────────────────────────────────

class _HomeLoading extends StatelessWidget {
  const _HomeLoading();

  @override
  Widget build(BuildContext context) => Container(
    color: kBg,
    child: Column(children: [
      Container(height: 220, color: kNavy.withAlpha(200)),
      const SizedBox(height: 12),
      _shimmer(100, double.infinity),
      const SizedBox(height: 16),
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        child: Row(children: [
          _shimmer(130, 130), const SizedBox(width: 12),
          _shimmer(130, 130), const SizedBox(width: 12),
          _shimmer(130, 130),
        ]),
      ),
    ]),
  );

  Widget _shimmer(double h, double w) => Container(
    height: h, width: w,
    decoration: BoxDecoration(
      color: Colors.grey.shade200,
      borderRadius: BorderRadius.circular(12),
    ),
  );
}

class _HomeError extends StatelessWidget {
  final VoidCallback onRetry;
  const _HomeError({required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      const Icon(Icons.wifi_off_rounded, size: 56, color: Colors.grey),
      const SizedBox(height: 12),
      const Text('Xiriirka kuma guulaysanin', style: TextStyle(color: kNavy, fontSize: 15, fontWeight: FontWeight.w600)),
      const SizedBox(height: 16),
      ElevatedButton(
        onPressed: onRetry,
        style: ElevatedButton.styleFrom(backgroundColor: kOrange, foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10))),
        child: const Text('Isku Day'),
      ),
    ]),
  );
}
