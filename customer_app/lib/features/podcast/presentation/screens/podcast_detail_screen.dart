import 'package:flutter/material.dart';
import '../../data/models/podcast_models.dart';
import '../../data/repositories/podcast_repository.dart';
import '../widgets/podcast_cover.dart';
import '../services/podcast_audio_service.dart';
import 'episode_player_screen.dart';

const _kNavy   = Color(0xFF07003B);
const _kOrange = Color(0xFFFF8A00);

class PodcastDetailScreen extends StatefulWidget {
  final String slug;
  const PodcastDetailScreen({super.key, required this.slug});
  @override
  State<PodcastDetailScreen> createState() => _PodcastDetailScreenState();
}

class _PodcastDetailScreenState extends State<PodcastDetailScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabs;
  final _repo = PodcastRepository();
  Map<String, dynamic>? _data;
  bool _loading = true;
  String? _error;
  bool _following = false;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
    _load();
  }

  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  Future<void> _load() async {
    try {
      final d = await _repo.getPodcast(widget.slug);
      if (mounted) setState(() {
        _data = d;
        _following = d['podcast']?['is_following'] == true;
        _loading = false;
      });
    } catch (e) {
      if (mounted) setState(() { _error = '$e'; _loading = false; });
    }
  }

  Future<void> _toggleFollow() async {
    final id = _data?['podcast']?['id'];
    if (id == null) return;
    setState(() => _following = !_following);
    try {
      final res = await _repo.followPodcast(id);
      if (mounted) setState(() => _following = res);
    } catch (_) {
      if (mounted) setState(() => _following = !_following);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Scaffold(
      backgroundColor: _kNavy,
      body: Center(child: CircularProgressIndicator(color: _kOrange)),
    );
    if (_error != null) return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      body: Center(child: Text(_error!)),
    );

    final p       = _data!['podcast'] as Map<String, dynamic>;
    final episodes = ((_data!['episodes']?['data']) as List? ?? [])
        .map((e) => PodcastEpisode.fromJson(Map<String, dynamic>.from(e))).toList();
    final cover    = p['cover_image'] as String?;
    final title    = p['title'] as String? ?? '';
    final desc     = p['description'] as String? ?? '';
    final follows  = p['total_followers'] ?? 0;
    final totalEp  = p['total_episodes'] ?? 0;
    final verified = p['is_verified'] == true;
    final creator  = p['user'] as Map<String, dynamic>?;
    final creatorName   = creator?['name'] as String? ?? '';
    final creatorAvatar = creator?['avatar'] as String?;
    final handle = '@${creatorName.toLowerCase().replaceAll(' ', '')}';

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      body: NestedScrollView(
        headerSliverBuilder: (_, __) => [
          SliverAppBar(
            pinned: true,
            expandedHeight: 310,
            backgroundColor: _kNavy,
            iconTheme: const IconThemeData(color: Colors.white),
            flexibleSpace: FlexibleSpaceBar(
              background: Stack(fit: StackFit.expand, children: [
                // Background: podcast cover or gradient
                cover != null
                    ? Image.network(cover, fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => _gradientBg())
                    : _gradientBg(),
                // Dark gradient overlay
                Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: [
                        _kNavy.withAlpha(120),
                        _kNavy.withAlpha(220),
                      ],
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                    ),
                  ),
                ),
                // Creator info at bottom of header
                Positioned(
                  left: 20, right: 20, bottom: 20,
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      // Creator avatar
                      Container(
                        width: 58, height: 58,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          border: Border.all(color: _kOrange, width: 2),
                        ),
                        child: ClipOval(
                          child: creatorAvatar != null
                              ? Image.network(creatorAvatar, fit: BoxFit.cover,
                                  errorBuilder: (_, __, ___) => _avatarPlaceholder(creatorName))
                              : _avatarPlaceholder(creatorName),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Row(children: [
                            Flexible(
                              child: Text(creatorName,
                                  style: const TextStyle(color: Colors.white, fontSize: 16,
                                      fontWeight: FontWeight.w800),
                                  maxLines: 1, overflow: TextOverflow.ellipsis),
                            ),
                            if (verified) ...[
                              const SizedBox(width: 4),
                              const Icon(Icons.verified_rounded, color: _kOrange, size: 16),
                            ],
                          ]),
                          Text(handle,
                              style: const TextStyle(color: Colors.white60, fontSize: 12)),
                        ]),
                      ),
                    ]),
                    const SizedBox(height: 12),
                    Text(title,
                        style: const TextStyle(color: Colors.white70, fontSize: 13,
                            fontWeight: FontWeight.w600)),
                    const SizedBox(height: 12),
                    // Stats row
                    Row(children: [
                      _StatChip(value: '$totalEp', label: 'Podcasts'),
                      const SizedBox(width: 24),
                      _StatChip(value: _fmt(follows), label: 'Followers'),
                      const SizedBox(width: 24),
                      const _StatChip(value: '—', label: 'Following'),
                    ]),
                  ]),
                ),
              ]),
            ),
          ),

          // Follow + Message buttons + bio
          SliverToBoxAdapter(
            child: Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 12),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                if (desc.isNotEmpty) ...[
                  Text(desc,
                      style: const TextStyle(color: Color(0xFF4B5563), fontSize: 13, height: 1.6),
                      maxLines: 3, overflow: TextOverflow.ellipsis),
                  const SizedBox(height: 14),
                ],
                Row(children: [
                  Expanded(
                    child: GestureDetector(
                      onTap: _toggleFollow,
                      child: Container(
                        height: 42,
                        decoration: BoxDecoration(
                          color: _following ? Colors.transparent : _kOrange,
                          border: Border.all(
                            color: _following ? Colors.grey.shade300 : _kOrange,
                          ),
                          borderRadius: BorderRadius.circular(10),
                        ),
                        child: Center(
                          child: Text(
                            _following ? 'Following' : 'Follow',
                            style: TextStyle(
                              color: _following ? Colors.grey.shade700 : Colors.white,
                              fontWeight: FontWeight.w800,
                              fontSize: 14,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Container(
                    width: 42, height: 42,
                    decoration: BoxDecoration(
                      border: Border.all(color: Colors.grey.shade300),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(Icons.mail_outline_rounded, color: _kNavy, size: 20),
                  ),
                ]),
              ]),
            ),
          ),

          // Tabs
          SliverToBoxAdapter(
            child: Container(
              color: Colors.white,
              child: TabBar(
                controller: _tabs,
                indicatorColor: _kOrange,
                indicatorWeight: 3,
                labelColor: _kOrange,
                unselectedLabelColor: Colors.grey,
                labelStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                tabs: const [Tab(text: 'Episodes'), Tab(text: 'About'), Tab(text: 'Playlists')],
              ),
            ),
          ),
        ],
        body: TabBarView(controller: _tabs, children: [
          // Episodes tab
          RefreshIndicator(
            onRefresh: _load, color: _kOrange,
            child: ListView.builder(
              padding: const EdgeInsets.symmetric(vertical: 8),
              itemCount: episodes.length,
              itemBuilder: (_, i) => _EpisodeTile(
                episode: episodes[i],
                fallbackCover: cover,
              ),
            ),
          ),

          // About tab
          SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('About',
                  style: TextStyle(color: _kNavy, fontSize: 15, fontWeight: FontWeight.w800)),
              const SizedBox(height: 10),
              Text(desc.isEmpty ? 'No description available.' : desc,
                  style: const TextStyle(color: Color(0xFF4B5563), fontSize: 14, height: 1.7)),
            ]),
          ),

          // Playlists tab
          const Center(
            child: Text('Playlists coming soon',
                style: TextStyle(color: Colors.grey, fontSize: 14)),
          ),
        ]),
      ),
    );
  }

  Widget _gradientBg() => Container(
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        colors: [Color(0xFF1E0050), _kNavy],
        begin: Alignment.topLeft, end: Alignment.bottomRight,
      ),
    ),
  );

  Widget _avatarPlaceholder(String name) => Container(
    color: _kOrange,
    child: Center(
      child: Text(
        name.isNotEmpty ? name[0].toUpperCase() : 'P',
        style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w900),
      ),
    ),
  );

  String _fmt(dynamic n) {
    final v = (n is num ? n : 0).toInt();
    if (v >= 1000000) return '${(v / 1000000).toStringAsFixed(1)}M';
    if (v >= 1000) return '${(v / 1000).toStringAsFixed(1)}K';
    return '$v';
  }
}

// ─── Stat chip ────────────────────────────────────────────────────────────────

class _StatChip extends StatelessWidget {
  final String value;
  final String label;
  const _StatChip({required this.value, required this.label});
  @override
  Widget build(BuildContext context) => Column(children: [
    Text(value, style: const TextStyle(
        color: Colors.white, fontSize: 16, fontWeight: FontWeight.w900)),
    Text(label, style: const TextStyle(color: Colors.white60, fontSize: 11)),
  ]);
}

// ─── Episode tile ─────────────────────────────────────────────────────────────

class _EpisodeTile extends StatefulWidget {
  final PodcastEpisode episode;
  final String? fallbackCover;
  const _EpisodeTile({required this.episode, this.fallbackCover});
  @override
  State<_EpisodeTile> createState() => _EpisodeTileState();
}

class _EpisodeTileState extends State<_EpisodeTile> {
  bool _loading = false;
  final _repo = PodcastRepository();

  Future<void> _play() async {
    if (_loading) return;
    PodcastEpisode ep = widget.episode;

    // Fetch full episode if audioUrl is missing
    if (ep.audioUrl == null || ep.audioUrl!.isEmpty) {
      setState(() => _loading = true);
      try {
        ep = await _repo.getEpisode(widget.episode.slug);
      } catch (_) {
        if (mounted) setState(() => _loading = false);
        return;
      }
      if (mounted) setState(() => _loading = false);
    }

    await PodcastAudioService.instance.play(ep);
    if (!mounted) return;
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
    final ep = widget.episode;
    final coverUrl = ep.coverImage ?? widget.fallbackCover;

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 5),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 6, offset: const Offset(0, 2))],
      ),
      child: Row(children: [
        // Episode artwork
        ClipRRect(
          borderRadius: const BorderRadius.only(
              topLeft: Radius.circular(14), bottomLeft: Radius.circular(14)),
          child: PodcastCover(url: coverUrl, width: 80, height: 80),
        ),
        // Content
        Expanded(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              // Episode number
              if (ep.episodeNumber != null)
                Text('EP. ${ep.episodeNumber}',
                    style: const TextStyle(color: _kOrange, fontSize: 10, fontWeight: FontWeight.w700)),
              Text(ep.title,
                  style: const TextStyle(color: _kNavy, fontWeight: FontWeight.w700, fontSize: 13),
                  maxLines: 2, overflow: TextOverflow.ellipsis),
              const SizedBox(height: 4),
              Row(children: [
                const Icon(Icons.access_time_rounded, size: 12, color: Colors.grey),
                const SizedBox(width: 3),
                Text(ep.durationFmt, style: const TextStyle(color: Colors.grey, fontSize: 11)),
                const SizedBox(width: 10),
                const Icon(Icons.headset_rounded, size: 12, color: Colors.grey),
                const SizedBox(width: 3),
                Text(_fmt(ep.playCount), style: const TextStyle(color: Colors.grey, fontSize: 11)),
              ]),
              // Progress bar if resumePosition > 0
              if (ep.resumePosition > 0 && ep.duration > 0) ...[
                const SizedBox(height: 6),
                ClipRRect(
                  borderRadius: BorderRadius.circular(2),
                  child: LinearProgressIndicator(
                    value: (ep.resumePosition / ep.duration).clamp(0.0, 1.0),
                    backgroundColor: Colors.grey.shade200,
                    valueColor: const AlwaysStoppedAnimation<Color>(_kOrange),
                    minHeight: 3,
                  ),
                ),
              ],
            ]),
          ),
        ),
        // Play button
        GestureDetector(
          onTap: _play,
          child: Container(
            margin: const EdgeInsets.only(right: 14),
            width: 38, height: 38,
            decoration: const BoxDecoration(color: _kOrange, shape: BoxShape.circle),
            child: _loading
                ? const Padding(
                    padding: EdgeInsets.all(10),
                    child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                : const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 22),
          ),
        ),
      ]),
    );
  }

  String _fmt(int n) => n >= 1000 ? '${(n / 1000).toStringAsFixed(1)}K' : '$n';
}
