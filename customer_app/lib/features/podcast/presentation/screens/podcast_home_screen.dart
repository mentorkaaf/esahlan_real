import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/podcast_models.dart';
import '../../data/repositories/podcast_repository.dart';
import '../providers/podcast_provider.dart';
import '../services/podcast_audio_service.dart';
import '../widgets/podcast_cover.dart';
import 'episode_player_screen.dart';
import 'all_podcasts_screen.dart';
import 'podcast_detail_screen.dart';
import 'podcast_categories_screen.dart';
import 'top_charts_screen.dart';
import 'podcast_search_screen.dart';
import 'creator_studio_screen.dart';
import 'podcast_library_screen.dart';
import 'live_audio_screen.dart';

const kNavy   = Color(0xFF07003B);
const kOrange = Color(0xFFFF8A00);
const kBg     = Color(0xFFF0F2F5);

IconData podcastCatIcon(String icon) {
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
    'stories': Icons.auto_stories_rounded,
    'motivation': Icons.emoji_events_rounded,
    'entertainment': Icons.theaters_rounded,
    'finance': Icons.trending_up_rounded,
    'lifestyle': Icons.spa_rounded,
    'health': Icons.health_and_safety_rounded,
    'technology': Icons.memory_rounded,
    'news': Icons.newspaper_rounded,
  };
  return map[icon] ?? Icons.podcasts_rounded;
}

class PodcastHomeScreen extends ConsumerWidget {
  const PodcastHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final homeAsync = ref.watch(podcastHomeProvider);

    return Scaffold(
      backgroundColor: kBg,
      body: homeAsync.when(
        loading: () => const _HomeLoading(),
        error: (e, st) => _HomeError(
          error: e.toString(),
          onRetry: () => ref.invalidate(podcastHomeProvider),
        ),
        data: (data) => _HomeContent(data: data),
      ),
    );
  }
}

// ─── Main content ──────────────────────────────────────────────────────────────

class _HomeContent extends StatelessWidget {
  final PodcastHomeData data;
  const _HomeContent({required this.data});

  @override
  Widget build(BuildContext context) {
    return CustomScrollView(
      physics: const BouncingScrollPhysics(),
      slivers: [
        // ── App Bar ────────────────────────────────────────────────────────
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
            _IBtn(icon: Icons.search_rounded, onTap: () => _push(context, const PodcastSearchScreen())),
            _IBtn(icon: Icons.notifications_outlined, onTap: () {}),
            _IBtn(icon: Icons.mic_external_on_rounded, color: kOrange,
                onTap: () => _push(context, const CreatorStudioScreen())),
          ]),
        ),

        // ── Hero Banner ────────────────────────────────────────────────────
        SliverToBoxAdapter(child: _HeroBanner(
          podcasts: data.heroFeatured,
          onExplore: () => _push(context, const AllPodcastsScreen()),
          onCreate: () => _push(context, const CreatorStudioScreen()),
        )),

        // ── Stats Bar ──────────────────────────────────────────────────────
        SliverToBoxAdapter(child: _StatsBar(
          stats: data.stats,
          onAllPodcasts: () => _push(context, const AllPodcastsScreen()),
          onCategories:  () => _push(context, const PodcastCategoriesScreen()),
          onTopCharts:   () => _push(context, const TopChartsScreen()),
          onLive:        () => _push(context, const LiveAudioScreen()),
        )),

        // ── Continue Listening ─────────────────────────────────────────────
        if (data.continueListening.isNotEmpty) ...[
          _sectionHeader(context, 'Sii Dhegeyso',
              onSeeAll: () => _push(context, const PodcastLibraryScreen())),
          SliverToBoxAdapter(child: _ContinueRow(items: data.continueListening)),
        ],

        // ── Popular Podcasts ───────────────────────────────────────────────
        if (data.popularPodcasts.isNotEmpty) ...[
          _sectionHeader(context, 'Popular Podcasts',
              onSeeAll: () => _push(context, const AllPodcastsScreen())),
          SliverToBoxAdapter(child: _PodcastRow(podcasts: data.popularPodcasts)),
        ],

        // ── Top Charts ─────────────────────────────────────────────────────
        if (data.topCharts.isNotEmpty) ...[
          _sectionHeader(context, 'Top Charts',
              onSeeAll: () => _push(context, const TopChartsScreen())),
          SliverToBoxAdapter(child: _TopChartsList(episodes: data.topCharts, limit: 5)),
        ],

        // ── New Releases ───────────────────────────────────────────────────
        if (data.newReleases.isNotEmpty) ...[
          _sectionHeader(context, 'New Releases', badge: 'NEW', onSeeAll: () {}),
          SliverToBoxAdapter(child: _EpisodeRow(episodes: data.newReleases)),
        ],

        // ── Trending ───────────────────────────────────────────────────────
        if (data.trendingToday.isNotEmpty) ...[
          _sectionHeader(context, 'Trending Maanta', onSeeAll: () {}),
          SliverToBoxAdapter(child: _EpisodeRow(episodes: data.trendingToday)),
        ],

        // ── Categories ─────────────────────────────────────────────────────
        if (data.categories.isNotEmpty) ...[
          _sectionHeader(context, 'Categories',
              onSeeAll: () => _push(context, const PodcastCategoriesScreen())),
          SliverToBoxAdapter(child: _CategoriesRow(categories: data.categories)),
        ],

        // ── Recommended ────────────────────────────────────────────────────
        if (data.recommended.isNotEmpty) ...[
          _sectionHeader(context, 'Kuu Taliya', onSeeAll: () {}),
          SliverToBoxAdapter(child: _PodcastRow(podcasts: data.recommended)),
        ],

        // ── Live Audio ─────────────────────────────────────────────────────
        if (data.liveRooms.isNotEmpty) ...[
          _sectionHeader(context, 'Live Audio',
              badge: 'LIVE', onSeeAll: () => _push(context, const LiveAudioScreen())),
          SliverToBoxAdapter(child: _LiveRow(rooms: data.liveRooms,
              onJoin: (r) => _push(context, const LiveAudioScreen()))),
        ],

        const SliverToBoxAdapter(child: SizedBox(height: 120)),
      ],
    );
  }

  SliverToBoxAdapter _sectionHeader(BuildContext ctx, String title,
      {String? badge, VoidCallback? onSeeAll}) {
    return SliverToBoxAdapter(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 10),
        child: Row(children: [
          Text(title,
              style: const TextStyle(color: kNavy, fontSize: 15, fontWeight: FontWeight.w800)),
          if (badge != null) ...[
            const SizedBox(width: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
              decoration: BoxDecoration(
                color: badge == 'LIVE' ? Colors.red : kOrange,
                borderRadius: BorderRadius.circular(4),
              ),
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

  void _push(BuildContext ctx, Widget screen) =>
      Navigator.of(ctx).push(MaterialPageRoute(builder: (_) => screen));
}

// ─── Hero Banner ──────────────────────────────────────────────────────────────

class _HeroBanner extends StatefulWidget {
  final List<Podcast> podcasts;
  final VoidCallback onExplore;
  final VoidCallback onCreate;
  const _HeroBanner({required this.podcasts, required this.onExplore, required this.onCreate});
  @override
  State<_HeroBanner> createState() => _HeroBannerState();
}

class _HeroBannerState extends State<_HeroBanner> {
  int _cur = 0;
  final _ctrl = PageController();

  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    // If no featured podcasts → show default hero
    if (widget.podcasts.isEmpty) {
      return _DefaultHero(onExplore: widget.onExplore, onCreate: widget.onCreate);
    }

    return SizedBox(
      height: 230,
      child: Stack(children: [
        PageView.builder(
          controller: _ctrl,
          itemCount: widget.podcasts.length,
          onPageChanged: (i) => setState(() => _cur = i),
          itemBuilder: (_, i) {
            final p = widget.podcasts[i];
            return _PodcastHeroSlide(podcast: p,
                onExplore: widget.onExplore, onCreate: widget.onCreate);
          },
        ),
        if (widget.podcasts.length > 1)
          Positioned(bottom: 14, left: 0, right: 0,
              child: Row(mainAxisAlignment: MainAxisAlignment.center,
                  children: List.generate(widget.podcasts.length, (i) =>
                    AnimatedContainer(
                      duration: const Duration(milliseconds: 200),
                      width: _cur == i ? 18 : 6, height: 6,
                      margin: const EdgeInsets.symmetric(horizontal: 2),
                      decoration: BoxDecoration(
                        color: _cur == i ? kOrange : Colors.white38,
                        borderRadius: BorderRadius.circular(3),
                      ),
                    )))),
      ]),
    );
  }
}

class _DefaultHero extends StatelessWidget {
  final VoidCallback onExplore;
  final VoidCallback onCreate;
  const _DefaultHero({required this.onExplore, required this.onCreate});

  @override
  Widget build(BuildContext context) => Container(
    height: 230,
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        colors: [Color(0xFF140465), Color(0xFF07003B), Color(0xFF1a0080)],
        begin: Alignment.topLeft, end: Alignment.bottomRight,
      ),
    ),
    child: Stack(children: [
      // Decorative circles
      Positioned(right: -30, top: -30,
          child: Container(width: 180, height: 180,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: kOrange.withAlpha(20),
              ))),
      Positioned(right: 20, top: 10,
          child: Container(width: 100, height: 100,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: kOrange.withAlpha(15),
              ))),
      // Waveform decoration
      Positioned(right: 16, top: 0, bottom: 0,
          child: _WaveDecoration()),
      // Mic icon
      Positioned(right: 24, top: 30,
          child: Icon(Icons.mic_rounded, size: 70, color: kOrange.withAlpha(180))),
      // Content
      Positioned(left: 20, right: 120, bottom: 30, top: 20,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
              decoration: BoxDecoration(
                color: kOrange.withAlpha(40),
                borderRadius: BorderRadius.circular(4),
              ),
              child: const Text('eSahlan Audio', style: TextStyle(color: kOrange, fontSize: 10, fontWeight: FontWeight.w700, letterSpacing: 0.5)),
            ),
            const SizedBox(height: 8),
            const Text('Welcome to\nAudio & Podcast',
                style: TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900, height: 1.15)),
            const SizedBox(height: 6),
            const Text('Discover, listen and share\namazing audio content.',
                style: TextStyle(color: Colors.white54, fontSize: 11)),
            const SizedBox(height: 16),
            Row(children: [
              _HeroBtn(label: 'Explore', filled: true, onTap: onExplore),
              const SizedBox(width: 10),
              _HeroBtn(label: '🎙 Create Audio', filled: false, onTap: onCreate),
            ]),
          ])),
    ]),
  );
}

class _WaveDecoration extends StatefulWidget {
  @override
  State<_WaveDecoration> createState() => _WaveDecorationState();
}

class _WaveDecorationState extends State<_WaveDecoration> with SingleTickerProviderStateMixin {
  late AnimationController _ctrl;
  @override
  void initState() {
    super.initState();
    _ctrl = AnimationController(vsync: this, duration: const Duration(seconds: 2))..repeat();
  }
  @override
  void dispose() { _ctrl.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
    animation: _ctrl,
    builder: (_, __) => CustomPaint(
      size: const Size(80, 230),
      painter: _WavePainter(tick: _ctrl.value),
    ),
  );
}

class _WavePainter extends CustomPainter {
  final double tick;
  final _rng = math.Random(7);
  _WavePainter({required this.tick});

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..style = PaintingStyle.fill;
    const bars = 18;
    final barW = size.width / (bars * 1.6);
    final gap = barW * 0.6;
    for (var i = 0; i < bars; i++) {
      final baseH = 20.0 + _rng.nextDouble() * 60;
      final wave = math.sin(tick * math.pi * 2 + i * 0.4);
      final h = baseH * (0.7 + 0.3 * wave.abs());
      final x = i * (barW + gap);
      final y = (size.height - h) / 2;
      paint.color = kOrange.withAlpha((60 + i * 5).clamp(40, 100));
      canvas.drawRRect(
        RRect.fromRectAndRadius(Rect.fromLTWH(x, y, barW, h), const Radius.circular(2)),
        paint,
      );
    }
  }
  @override
  bool shouldRepaint(_WavePainter old) => old.tick != tick;
}

class _PodcastHeroSlide extends StatelessWidget {
  final Podcast podcast;
  final VoidCallback onExplore;
  final VoidCallback onCreate;
  const _PodcastHeroSlide({required this.podcast, required this.onExplore, required this.onCreate});

  @override
  Widget build(BuildContext context) => Stack(fit: StackFit.expand, children: [
    PodcastCover(url: podcast.coverImage, width: double.infinity, height: 230, radius: 0),
    Container(decoration: const BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topCenter, end: Alignment.bottomCenter,
        colors: [Color(0x44000000), Color(0xEE07003B)],
      ),
    )),
    Positioned(left: 20, right: 20, bottom: 28,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(podcast.title,
              style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900)),
          if (podcast.category != null)
            Text(podcast.category!.name, style: const TextStyle(color: Colors.white60, fontSize: 12)),
          const SizedBox(height: 12),
          Row(children: [
            _HeroBtn(label: 'Explore', filled: true, onTap: onExplore),
            const SizedBox(width: 10),
            _HeroBtn(label: '🎙 Create Audio', filled: false, onTap: onCreate),
          ]),
        ])),
  ]);
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
        border: Border.all(color: filled ? kOrange : Colors.white38),
      ),
      child: Text(label, style: TextStyle(
        color: filled ? Colors.white : Colors.white70,
        fontSize: 12, fontWeight: FontWeight.w700,
      )),
    ),
  );
}

// ─── Stats Bar ────────────────────────────────────────────────────────────────

class _StatsBar extends StatelessWidget {
  final PodcastStats stats;
  final VoidCallback onAllPodcasts, onCategories, onTopCharts, onLive;
  const _StatsBar({required this.stats, required this.onAllPodcasts,
      required this.onCategories, required this.onTopCharts, required this.onLive});

  @override
  Widget build(BuildContext context) => Container(
    color: Colors.white,
    padding: const EdgeInsets.symmetric(vertical: 12),
    child: Row(mainAxisAlignment: MainAxisAlignment.spaceEvenly, children: [
      _StatItem(icon: Icons.podcasts_rounded, label: 'All Podcasts',
          value: _k(stats.totalPodcasts), onTap: onAllPodcasts),
      _StatItem(icon: Icons.category_rounded, label: 'Categories',
          value: '${stats.totalCategories}', onTap: onCategories),
      _StatItem(icon: Icons.bar_chart_rounded, label: 'Top Charts',
          value: _k(stats.totalEpisodes), onTap: onTopCharts),
      _StatItem(icon: Icons.new_releases_rounded, label: 'New Releases',
          value: _k(stats.newThisWeek), onTap: () {}),
      _StatItem(icon: Icons.sensors_rounded, label: 'Live Audio',
          value: '${stats.liveRooms}', onTap: onLive,
          dot: stats.liveRooms > 0),
    ]),
  );

  String _k(int n) => n >= 1000 ? '${(n/1000).toStringAsFixed(1)}K' : '$n';
}

class _StatItem extends StatelessWidget {
  final IconData icon;
  final String label, value;
  final VoidCallback onTap;
  final bool dot;
  const _StatItem({required this.icon, required this.label,
      required this.value, required this.onTap, this.dot = false});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Column(mainAxisSize: MainAxisSize.min, children: [
      Stack(children: [
        Icon(icon, color: kNavy, size: 26),
        if (dot) Positioned(right: 0, top: 0,
            child: Container(width: 7, height: 7,
                decoration: const BoxDecoration(color: Colors.red, shape: BoxShape.circle))),
      ]),
      const SizedBox(height: 3),
      Text(value, style: const TextStyle(color: kNavy, fontSize: 12, fontWeight: FontWeight.w800)),
      Text(label, style: const TextStyle(color: Colors.grey, fontSize: 9)),
    ]),
  );
}

// ─── Continue Listening ───────────────────────────────────────────────────────

class _ContinueRow extends StatelessWidget {
  final List<ContinueItem> items;
  const _ContinueRow({required this.items});

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

class _ContinueCard extends StatefulWidget {
  final ContinueItem item;
  const _ContinueCard({required this.item});
  @override
  State<_ContinueCard> createState() => _ContinueCardState();
}

class _ContinueCardState extends State<_ContinueCard> {
  bool _loading = false;
  final _repo = PodcastRepository();

  Future<void> _play() async {
    if (_loading) return;
    PodcastEpisode ep = widget.item.episode;
    if (ep.audioUrl == null || ep.audioUrl!.isEmpty) {
      setState(() => _loading = true);
      try { ep = await _repo.getEpisode(ep.slug); } catch (_) {}
      if (mounted) setState(() => _loading = false);
    }
    await PodcastAudioService.instance.play(ep);
    if (!mounted) return;
    Navigator.of(context).push(PageRouteBuilder(
      pageBuilder: (_, __, ___) => const EpisodePlayerScreen(),
      transitionsBuilder: (_, a, __, child) => SlideTransition(
        position: Tween<Offset>(begin: const Offset(0,1), end: Offset.zero)
            .animate(CurvedAnimation(parent: a, curve: Curves.easeOutCubic)),
        child: child),
    ));
  }

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: _play,
    child: Container(
      width: 290, margin: const EdgeInsets.only(right: 12),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12),
          boxShadow: [BoxShadow(color: Colors.black.withAlpha(13), blurRadius: 8)]),
      child: Row(children: [
        ClipRRect(borderRadius: const BorderRadius.horizontal(left: Radius.circular(12)),
            child: PodcastCover(url: widget.item.episode.coverImage, width: 90, height: 90)),
        const SizedBox(width: 10),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisAlignment: MainAxisAlignment.center, children: [
          Text(widget.item.episode.title, maxLines: 2, overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: kNavy, fontSize: 12, fontWeight: FontWeight.w700)),
          const SizedBox(height: 6),
          LinearProgressIndicator(
            value: widget.item.progress / 100,
            backgroundColor: const Color(0xFFE5E7EB),
            valueColor: const AlwaysStoppedAnimation<Color>(kOrange),
            minHeight: 3, borderRadius: BorderRadius.circular(2),
          ),
          const SizedBox(height: 4),
          Text('${widget.item.progress}% · ${widget.item.episode.durationFmt}',
              style: const TextStyle(color: Colors.grey, fontSize: 10)),
        ])),
        Container(margin: const EdgeInsets.only(right: 10), width: 32, height: 32,
            decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
            child: _loading
                ? const Padding(padding: EdgeInsets.all(8),
                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 18)),
      ]),
    ),
  );
}

// ─── Podcast Row ──────────────────────────────────────────────────────────────

class _PodcastRow extends StatelessWidget {
  final List<Podcast> podcasts;
  const _PodcastRow({required this.podcasts});

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 185,
    child: ListView.builder(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: podcasts.length,
      itemBuilder: (_, i) => _PodCard(podcast: podcasts[i]),
    ),
  );
}

class _PodCard extends StatelessWidget {
  final Podcast podcast;
  const _PodCard({required this.podcast});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => PodcastDetailScreen(slug: podcast.slug))),
    child: Container(
    width: 130, margin: const EdgeInsets.only(right: 12),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Stack(children: [
        ClipRRect(borderRadius: BorderRadius.circular(12),
            child: PodcastCover(url: podcast.coverImage, width: 130, height: 130)),
        if (podcast.isVerified) Positioned(top: 6, right: 6,
            child: Container(padding: const EdgeInsets.all(2),
                decoration: const BoxDecoration(color: Colors.white, shape: BoxShape.circle),
                child: const Icon(Icons.verified_rounded, color: kOrange, size: 13))),
      ]),
      const SizedBox(height: 5),
      Text(podcast.title, maxLines: 1, overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: kNavy, fontSize: 12, fontWeight: FontWeight.w700)),
      if (podcast.category != null)
        Text(podcast.category!.name, maxLines: 1, overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.grey, fontSize: 10)),
      const SizedBox(height: 2),
      Row(children: [
        const Icon(Icons.headset_rounded, color: kOrange, size: 11),
        const SizedBox(width: 2),
        Text(_k(podcast.totalFollowers),
            style: const TextStyle(color: kOrange, fontSize: 10, fontWeight: FontWeight.w600)),
        const SizedBox(width: 6),
        const Icon(Icons.mic_rounded, size: 11, color: Colors.grey),
        const SizedBox(width: 2),
        Text('${podcast.totalEpisodes}', style: const TextStyle(color: Colors.grey, fontSize: 10)),
      ]),
    ]),
  ));

  String _k(int n) => n >= 1000 ? '${(n/1000).toStringAsFixed(1)}K' : '$n';
}

// ─── Episode Row ──────────────────────────────────────────────────────────────

class _EpisodeRow extends StatelessWidget {
  final List<PodcastEpisode> episodes;
  const _EpisodeRow({required this.episodes});

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 185,
    child: ListView.builder(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: episodes.length,
      itemBuilder: (_, i) => _EpCard(episode: episodes[i]),
    ),
  );
}

class _EpCard extends StatefulWidget {
  final PodcastEpisode episode;
  const _EpCard({required this.episode});
  @override
  State<_EpCard> createState() => _EpCardState();
}

class _EpCardState extends State<_EpCard> {
  bool _loading = false;
  final _repo = PodcastRepository();

  Future<void> _play() async {
    if (_loading) return;
    PodcastEpisode ep = widget.episode;
    if (ep.audioUrl == null || ep.audioUrl!.isEmpty) {
      setState(() => _loading = true);
      try { ep = await _repo.getEpisode(ep.slug); } catch (_) {}
      if (mounted) setState(() => _loading = false);
    }
    await PodcastAudioService.instance.play(ep);
    if (!mounted) return;
    Navigator.of(context).push(PageRouteBuilder(
      pageBuilder: (_, __, ___) => const EpisodePlayerScreen(),
      transitionsBuilder: (_, a, __, child) => SlideTransition(
        position: Tween<Offset>(begin: const Offset(0,1), end: Offset.zero)
            .animate(CurvedAnimation(parent: a, curve: Curves.easeOutCubic)),
        child: child),
    ));
  }

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: _play,
    child: Container(
      width: 140, margin: const EdgeInsets.only(right: 12),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Stack(children: [
          ClipRRect(borderRadius: BorderRadius.circular(12),
              child: PodcastCover(url: widget.episode.coverImage, width: 140, height: 130)),
          Positioned(bottom: 6, right: 6,
              child: Container(
                padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 2),
                decoration: BoxDecoration(
                  color: Colors.black.withAlpha(160), borderRadius: BorderRadius.circular(5)),
                child: Text(widget.episode.durationFmt,
                    style: const TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w700)))),
          Positioned(bottom: 6, left: 6,
              child: Container(padding: const EdgeInsets.all(4),
                  decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
                  child: _loading
                      ? const SizedBox(width: 12, height: 12,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                      : const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 12))),
        ]),
        const SizedBox(height: 5),
        Text(widget.episode.title, maxLines: 2, overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: kNavy, fontSize: 11, fontWeight: FontWeight.w700, height: 1.3)),
        if (widget.episode.podcast != null)
          Text(widget.episode.podcast!.title, maxLines: 1, overflow: TextOverflow.ellipsis,
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
          color: Colors.white, borderRadius: BorderRadius.circular(14),
          boxShadow: [BoxShadow(color: Colors.black.withAlpha(10), blurRadius: 8)],
        ),
        child: Column(
          children: show.asMap().entries.map((e) =>
            _ChartRow(rank: e.key + 1, episode: e.value, isLast: e.key == show.length - 1)
          ).toList(),
        ),
      ),
    );
  }
}

class _ChartRow extends StatefulWidget {
  final int rank;
  final PodcastEpisode episode;
  final bool isLast;
  const _ChartRow({required this.rank, required this.episode, required this.isLast});
  @override
  State<_ChartRow> createState() => _ChartRowState();
}

class _ChartRowState extends State<_ChartRow> {
  bool _loading = false;
  final _repo = PodcastRepository();

  Future<void> _play() async {
    if (_loading) return;
    PodcastEpisode ep = widget.episode;
    if (ep.audioUrl == null || ep.audioUrl!.isEmpty) {
      setState(() => _loading = true);
      try { ep = await _repo.getEpisode(ep.slug); } catch (_) {}
      if (mounted) setState(() => _loading = false);
    }
    await PodcastAudioService.instance.play(ep);
    if (!mounted) return;
    Navigator.of(context).push(PageRouteBuilder(
      pageBuilder: (_, __, ___) => const EpisodePlayerScreen(),
      transitionsBuilder: (_, a, __, child) => SlideTransition(
        position: Tween<Offset>(begin: const Offset(0,1), end: Offset.zero)
            .animate(CurvedAnimation(parent: a, curve: Curves.easeOutCubic)),
        child: child),
    ));
  }

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: _play,
    child: Column(children: [
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        child: Row(children: [
          SizedBox(width: 26,
              child: Text('${widget.rank}', style: TextStyle(
                fontSize: 16, fontWeight: FontWeight.w900,
                color: widget.rank <= 3 ? kOrange : Colors.grey.shade400))),
          ClipRRect(borderRadius: BorderRadius.circular(8),
              child: PodcastCover(url: widget.episode.coverImage, width: 44, height: 44)),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(widget.episode.title, maxLines: 1, overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: kNavy, fontSize: 13, fontWeight: FontWeight.w600)),
            Text(widget.episode.podcast?.title ?? '',
                style: const TextStyle(color: Colors.grey, fontSize: 11)),
          ])),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            Text(widget.episode.durationFmt, style: const TextStyle(color: Colors.grey, fontSize: 11)),
            Text(_k(widget.episode.playCount),
                style: const TextStyle(color: kOrange, fontSize: 10, fontWeight: FontWeight.w700)),
          ]),
          const SizedBox(width: 8),
          _loading
              ? const SizedBox(width: 30, height: 30,
                  child: Padding(padding: EdgeInsets.all(6),
                      child: CircularProgressIndicator(color: kOrange, strokeWidth: 2)))
              : const Icon(Icons.play_circle_fill_rounded, color: kOrange, size: 30),
        ]),
      ),
      if (!widget.isLast) Divider(height: 1, indent: 54, endIndent: 14, color: Colors.grey.shade100),
    ]),
  );

  String _k(int n) => n >= 1000 ? '${(n/1000).toStringAsFixed(1)}K' : '$n';
}

// ─── Categories Row ───────────────────────────────────────────────────────────

class _CategoriesRow extends StatelessWidget {
  final List<PodcastCategory> categories;
  const _CategoriesRow({required this.categories});

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 82,
    child: ListView.builder(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: categories.length,
      itemBuilder: (_, i) => _CatChip(cat: categories[i]),
    ),
  );
}

class _CatChip extends StatelessWidget {
  final PodcastCategory cat;
  const _CatChip({required this.cat});

  Color _bg() {
    try { return Color(int.parse(cat.color.replaceFirst('#', '0xFF'))).withAlpha(30); }
    catch (_) { return kOrange.withAlpha(30); }
  }

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: () => Navigator.of(context).push(MaterialPageRoute(
        builder: (_) => const PodcastCategoriesScreen())),
    child: Container(
      width: 88, margin: const EdgeInsets.only(right: 10),
      decoration: BoxDecoration(color: _bg(), borderRadius: BorderRadius.circular(14)),
      child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
        Icon(podcastCatIcon(cat.icon), color: kNavy, size: 24),
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

// ─── Live Row ─────────────────────────────────────────────────────────────────

class _LiveRow extends StatelessWidget {
  final List<dynamic> rooms;
  final Function(dynamic) onJoin;
  const _LiveRow({required this.rooms, required this.onJoin});

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 110,
    child: ListView.builder(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 16),
      itemCount: rooms.length,
      itemBuilder: (_, i) {
        final r = rooms[i] as Map<String, dynamic>;
        return _LiveCard(room: r, onJoin: () => onJoin(r));
      },
    ),
  );
}

class _LiveCard extends StatelessWidget {
  final Map<String, dynamic> room;
  final VoidCallback onJoin;
  const _LiveCard({required this.room, required this.onJoin});

  @override
  Widget build(BuildContext context) => Container(
    width: 230, margin: const EdgeInsets.only(right: 12),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [Color(0xFF07003B), Color(0xFF1a0080)],
        begin: Alignment.topLeft, end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(14),
    ),
    padding: const EdgeInsets.all(12),
    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Row(children: [
        Container(padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
            decoration: BoxDecoration(color: Colors.red, borderRadius: BorderRadius.circular(4)),
            child: const Text('LIVE', style: TextStyle(color: Colors.white, fontSize: 9, fontWeight: FontWeight.w800))),
        const SizedBox(width: 6),
        const Icon(Icons.people_rounded, color: Colors.white54, size: 13),
        const SizedBox(width: 3),
        Text('${room['listener_count'] ?? 0}',
            style: const TextStyle(color: Colors.white54, fontSize: 11)),
      ]),
      const SizedBox(height: 6),
      Text(room['title'] ?? 'Live Session',
          maxLines: 1, overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: Colors.white, fontSize: 13, fontWeight: FontWeight.w700)),
      Text(room['host_name'] ?? '',
          style: const TextStyle(color: Colors.white54, fontSize: 11)),
      const Spacer(),
      GestureDetector(
        onTap: onJoin,
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
          decoration: BoxDecoration(color: kOrange, borderRadius: BorderRadius.circular(20)),
          child: const Text('Join', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12)),
        ),
      ),
    ]),
  );
}

// ─── Helper widgets ───────────────────────────────────────────────────────────

class _IBtn extends StatelessWidget {
  final IconData icon;
  final VoidCallback onTap;
  final Color color;
  const _IBtn({required this.icon, required this.onTap, this.color = Colors.white});

  @override
  Widget build(BuildContext context) => GestureDetector(
    onTap: onTap,
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Icon(icon, color: color, size: 22),
    ),
  );
}

// ─── Loading / Error ──────────────────────────────────────────────────────────

class _HomeLoading extends StatelessWidget {
  const _HomeLoading();

  @override
  Widget build(BuildContext context) => Column(children: [
    Container(height: 230, decoration: const BoxDecoration(
      gradient: LinearGradient(
        colors: [Color(0xFF140465), Color(0xFF07003B)],
        begin: Alignment.topLeft, end: Alignment.bottomRight,
      ),
    )),
    const SizedBox(height: 12),
    _shimmer(100, double.infinity, padding: const EdgeInsets.symmetric(horizontal: 16)),
    const SizedBox(height: 16),
    _shimmerRow(),
  ]);

  Widget _shimmerRow() => Padding(
    padding: const EdgeInsets.symmetric(horizontal: 16),
    child: Row(children: [
      _shimmer(130, 130), const SizedBox(width: 12),
      _shimmer(130, 130), const SizedBox(width: 12),
      _shimmer(130, 130),
    ]),
  );

  Widget _shimmer(double h, double w, {EdgeInsets? padding}) => Padding(
    padding: padding ?? EdgeInsets.zero,
    child: Container(height: h, width: w,
        decoration: BoxDecoration(color: Colors.grey.shade200,
            borderRadius: BorderRadius.circular(12))),
  );
}

class _HomeError extends StatelessWidget {
  final String error;
  final VoidCallback onRetry;
  const _HomeError({required this.error, required this.onRetry});

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.wifi_off_rounded, size: 56, color: Colors.grey),
        const SizedBox(height: 12),
        const Text('Xiriirka kuma guulaysanin',
            style: TextStyle(color: kNavy, fontSize: 15, fontWeight: FontWeight.w600)),
        const SizedBox(height: 6),
        Text(error, style: const TextStyle(color: Colors.grey, fontSize: 11),
            textAlign: TextAlign.center, maxLines: 3, overflow: TextOverflow.ellipsis),
        const SizedBox(height: 20),
        ElevatedButton(
          onPressed: onRetry,
          style: ElevatedButton.styleFrom(
            backgroundColor: kOrange, foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
            minimumSize: const Size(160, 44),
          ),
          child: const Text('Isku Day', style: TextStyle(fontWeight: FontWeight.w700)),
        ),
      ]),
    ),
  );
}
