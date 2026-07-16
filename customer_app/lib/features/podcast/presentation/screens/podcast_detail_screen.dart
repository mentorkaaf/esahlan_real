import 'package:flutter/material.dart';
import '../../data/models/podcast_models.dart';
import '../../data/repositories/podcast_repository.dart';
import '../widgets/podcast_cover.dart';
import '../services/podcast_audio_service.dart';
import 'episode_player_screen.dart';

const kNavy   = Color(0xFF07003B);
const kOrange = Color(0xFFFF8A00);

class PodcastDetailScreen extends StatefulWidget {
  final String slug;
  const PodcastDetailScreen({super.key, required this.slug});
  @override
  State<PodcastDetailScreen> createState() => _PodcastDetailScreenState();
}

class _PodcastDetailScreenState extends State<PodcastDetailScreen> with SingleTickerProviderStateMixin {
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
      if (mounted) setState(() { _data = d; _following = d['podcast']?['is_following'] == true; _loading = false; });
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
      backgroundColor: kNavy,
      body: Center(child: CircularProgressIndicator(color: kOrange)),
    );
    if (_error != null) return Scaffold(
      backgroundColor: Color(0xFFF0F2F5),
      body: Center(child: Text(_error!)),
    );

    final p  = _data!['podcast'] as Map<String, dynamic>;
    final ep = ((_data!['episodes']?['data']) as List? ?? [])
        .map((e) => PodcastEpisode.fromJson(Map<String, dynamic>.from(e))).toList();
    final cover   = p['cover_image'] as String?;
    final title   = p['title'] ?? '';
    final desc    = p['description'] ?? '';
    final follows = p['total_followers'] ?? 0;
    final rating  = (p['rating'] ?? 0.0).toDouble();
    final total   = p['total_episodes'] ?? 0;
    final verified = p['is_verified'] == true;

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      body: NestedScrollView(
        headerSliverBuilder: (_, __) => [
          SliverAppBar(
            pinned: true, expandedHeight: 280,
            backgroundColor: kNavy,
            iconTheme: const IconThemeData(color: Colors.white),
            flexibleSpace: FlexibleSpaceBar(
              background: Stack(fit: StackFit.expand, children: [
                PodcastCover(url: cover, width: double.infinity, height: 280),
                Container(decoration: BoxDecoration(gradient: LinearGradient(
                  colors: [Colors.transparent, kNavy.withAlpha(230)],
                  begin: Alignment.topCenter, end: Alignment.bottomCenter,
                ))),
                Positioned(left: 16, right: 16, bottom: 16, child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Text(title, style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900)),
                      if (verified) ...[const SizedBox(width: 6), const Icon(Icons.verified_rounded, color: kOrange, size: 18)],
                    ]),
                    Row(children: [
                      _StatPill(icon: Icons.star_rounded, value: rating.toStringAsFixed(1), color: Colors.amber),
                      const SizedBox(width: 10),
                      _StatPill(icon: Icons.headset_rounded, value: _fmt(follows), color: kOrange),
                      const SizedBox(width: 10),
                      _StatPill(icon: Icons.mic_rounded, value: '$total ep', color: Colors.white70),
                    ]),
                  ],
                )),
              ]),
            ),
            actions: [
              Padding(
                padding: const EdgeInsets.only(right: 16),
                child: GestureDetector(
                  onTap: _toggleFollow,
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
                    decoration: BoxDecoration(
                      color: _following ? Colors.white.withAlpha(51) : kOrange,
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: _following ? Colors.white54 : kOrange),
                    ),
                    child: Text(_following ? 'Following' : 'Follow',
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 13)),
                  ),
                ),
              ),
            ],
          ),
          SliverToBoxAdapter(child: Container(
            color: Colors.white,
            child: TabBar(
              controller: _tabs,
              indicatorColor: kOrange,
              labelColor: kOrange,
              unselectedLabelColor: Colors.grey,
              tabs: const [Tab(text: 'Episodes'), Tab(text: 'About'), Tab(text: 'Reviews')],
            ),
          )),
        ],
        body: TabBarView(controller: _tabs, children: [
          // Episodes
          ListView.builder(
            padding: const EdgeInsets.symmetric(vertical: 8),
            itemCount: ep.length,
            itemBuilder: (_, i) => _EpisodeTile(episode: ep[i]),
          ),
          // About
          SingleChildScrollView(padding: const EdgeInsets.all(20), child: Text(desc,
              style: const TextStyle(color: kNavy, fontSize: 14, height: 1.7))),
          // Reviews
          const Center(child: Text('Reviews coming soon', style: TextStyle(color: Colors.grey))),
        ]),
      ),
    );
  }

  String _fmt(dynamic n) {
    final v = n is int ? n : (n as num).toInt();
    return v >= 1000 ? '${(v/1000).toStringAsFixed(1)}K' : '$v';
  }
}

class _StatPill extends StatelessWidget {
  final IconData icon;
  final String value;
  final Color color;
  const _StatPill({required this.icon, required this.value, required this.color});

  @override
  Widget build(BuildContext context) => Row(children: [
    Icon(icon, color: color, size: 13),
    const SizedBox(width: 3),
    Text(value, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600)),
  ]);
}

class _EpisodeTile extends StatelessWidget {
  final PodcastEpisode episode;
  const _EpisodeTile({required this.episode});

  void _play(BuildContext context) {
    PodcastAudioService.instance.play(episode);
    Navigator.of(context).push(PageRouteBuilder(
      pageBuilder: (_, __, ___) => const EpisodePlayerScreen(),
      transitionsBuilder: (_, anim, __, child) => SlideTransition(
        position: Tween<Offset>(begin: const Offset(0,1), end: Offset.zero)
            .animate(CurvedAnimation(parent: anim, curve: Curves.easeOutCubic)),
        child: child,
      ),
    ));
  }

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 6)]),
    child: ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      leading: ClipRRect(borderRadius: BorderRadius.circular(8),
          child: PodcastCover(url: episode.coverImage, width: 50, height: 50)),
      title: Text(episode.title, maxLines: 2, overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: kNavy, fontWeight: FontWeight.w600, fontSize: 13)),
      subtitle: Row(children: [
        Text(episode.durationFmt, style: const TextStyle(color: Colors.grey, fontSize: 11)),
        const SizedBox(width: 8),
        const Icon(Icons.headset_rounded, size: 11, color: Colors.grey),
        const SizedBox(width: 2),
        Text(_fmt(episode.playCount), style: const TextStyle(color: Colors.grey, fontSize: 11)),
      ]),
      trailing: GestureDetector(
        onTap: () => _play(context),
        child: Container(
          width: 36, height: 36,
          decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
          child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 20),
        ),
      ),
      onTap: () => _play(context),
    ),
  );

  String _fmt(int n) => n >= 1000 ? '${(n/1000).toStringAsFixed(1)}K' : '$n';
}
