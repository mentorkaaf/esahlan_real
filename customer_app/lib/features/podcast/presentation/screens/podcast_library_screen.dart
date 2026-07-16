import 'package:flutter/material.dart';
import '../../data/models/podcast_models.dart';
import '../../data/repositories/podcast_repository.dart';
import '../widgets/podcast_cover.dart';
import '../services/podcast_audio_service.dart';
import 'episode_player_screen.dart';

const kNavy   = Color(0xFF07003B);
const kOrange = Color(0xFFFF8A00);

class PodcastLibraryScreen extends StatefulWidget {
  const PodcastLibraryScreen({super.key});
  @override
  State<PodcastLibraryScreen> createState() => _PodcastLibraryScreenState();
}

class _PodcastLibraryScreenState extends State<PodcastLibraryScreen> with SingleTickerProviderStateMixin {
  late TabController _tabs;
  final _repo = PodcastRepository();
  Map<String, dynamic>? _libData;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 4, vsync: this);
    _load();
  }

  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  Future<void> _load() async {
    try {
      final d = await _repo.getLibrary();
      if (mounted) setState(() { _libData = d; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  List<PodcastEpisode> _episodes(String key) {
    return ((_libData?[key] as List?) ?? [])
        .map((e) => PodcastEpisode.fromJson(Map<String, dynamic>.from(
            e is Map ? e : (e['episode'] ?? e))))
        .toList();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF0F2F5),
    appBar: AppBar(
      backgroundColor: kNavy,
      title: const Text('My Library', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
      iconTheme: const IconThemeData(color: Colors.white),
      bottom: TabBar(
        controller: _tabs,
        indicatorColor: kOrange,
        labelColor: Colors.white,
        unselectedLabelColor: Colors.white54,
        isScrollable: true,
        tabs: const [Tab(text: 'History'), Tab(text: 'Saved'), Tab(text: 'Liked'), Tab(text: 'Playlists')],
      ),
    ),
    body: _loading
        ? const Center(child: CircularProgressIndicator(color: kOrange))
        : TabBarView(controller: _tabs, children: [
            _EpisodeList(episodes: _episodes('history')),
            _EpisodeList(episodes: _episodes('saved')),
            _EpisodeList(episodes: _episodes('liked')),
            _PlaylistsTab(playlists: (_libData?['playlists'] as List? ?? [])),
          ]),
  );
}

class _EpisodeList extends StatelessWidget {
  final List<PodcastEpisode> episodes;
  const _EpisodeList({required this.episodes});

  @override
  Widget build(BuildContext context) {
    if (episodes.isEmpty) return const Center(
      child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(Icons.library_music_rounded, size: 56, color: Colors.grey),
        SizedBox(height: 12),
        Text('Empty', style: TextStyle(color: Colors.grey, fontSize: 15)),
      ]),
    );
    return ListView.builder(
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: episodes.length,
      itemBuilder: (_, i) => _LibEpisodeTile(episode: episodes[i]),
    );
  }
}

class _LibEpisodeTile extends StatelessWidget {
  final PodcastEpisode episode;
  const _LibEpisodeTile({required this.episode});

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
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 6)]),
    child: ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
      leading: ClipRRect(borderRadius: BorderRadius.circular(8),
          child: PodcastCover(url: episode.coverImage, width: 50, height: 50)),
      title: Text(episode.title, maxLines: 1, overflow: TextOverflow.ellipsis,
          style: const TextStyle(color: kNavy, fontWeight: FontWeight.w700, fontSize: 13)),
      subtitle: Text(episode.durationFmt, style: const TextStyle(color: Colors.grey, fontSize: 11)),
      trailing: GestureDetector(
        onTap: () => _play(context),
        child: Container(
          width: 34, height: 34,
          decoration: const BoxDecoration(color: kOrange, shape: BoxShape.circle),
          child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 18),
        ),
      ),
      onTap: () => _play(context),
    ),
  );
}

class _PlaylistsTab extends StatelessWidget {
  final List playlists;
  const _PlaylistsTab({required this.playlists});

  @override
  Widget build(BuildContext context) {
    if (playlists.isEmpty) return const Center(
      child: Text('No playlists yet', style: TextStyle(color: Colors.grey, fontSize: 15)),
    );
    return ListView.builder(
      padding: const EdgeInsets.symmetric(vertical: 8),
      itemCount: playlists.length,
      itemBuilder: (_, i) {
        final pl = playlists[i] as Map<String, dynamic>;
        return Container(
          margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
          child: ListTile(
            leading: Container(width: 50, height: 50,
                decoration: BoxDecoration(color: kNavy.withAlpha(26), borderRadius: BorderRadius.circular(8)),
                child: const Icon(Icons.playlist_play_rounded, color: kNavy)),
            title: Text(pl['title'] ?? 'Playlist', style: const TextStyle(color: kNavy, fontWeight: FontWeight.w700)),
            subtitle: Text('${pl['episode_count'] ?? 0} episodes', style: const TextStyle(color: Colors.grey, fontSize: 11)),
            trailing: const Icon(Icons.chevron_right_rounded, color: Colors.grey),
          ),
        );
      },
    );
  }
}
