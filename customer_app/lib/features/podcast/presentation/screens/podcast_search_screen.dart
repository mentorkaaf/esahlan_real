import 'dart:async';
import 'package:flutter/material.dart';
import '../../data/models/podcast_models.dart';
import '../../data/repositories/podcast_repository.dart';
import '../services/podcast_audio_service.dart';
import 'episode_player_screen.dart';

class PodcastSearchScreen extends StatefulWidget {
  const PodcastSearchScreen({super.key});

  @override
  State<PodcastSearchScreen> createState() => _PodcastSearchScreenState();
}

class _PodcastSearchScreenState extends State<PodcastSearchScreen> {
  final _ctrl   = TextEditingController();
  final _repo   = PodcastRepository();
  Timer?        _debounce;
  bool          _loading  = false;
  String        _tab      = 'all';
  List<Podcast> _podcasts = [];
  List<PodcastEpisode> _episodes = [];
  String        _lastQ   = '';

  @override
  void dispose() {
    _ctrl.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  void _onChanged(String v) {
    _debounce?.cancel();
    if (v.trim().length < 2) {
      setState(() { _podcasts = []; _episodes = []; });
      return;
    }
    _debounce = Timer(const Duration(milliseconds: 400), () => _search(v.trim()));
  }

  Future<void> _search(String q) async {
    if (q == _lastQ) return;
    _lastQ = q;
    setState(() => _loading = true);
    try {
      final r = await _repo.search(q, type: _tab);
      if (!mounted) return;
      setState(() {
        _podcasts = (r['podcasts']?['data'] as List? ?? []).map((e) => Podcast.fromJson(e)).toList();
        _episodes = (r['episodes']?['data'] as List? ?? []).map((e) => PodcastEpisode.fromJson(e)).toList();
      });
    } catch (_) {} finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0A0A0F),
      appBar: AppBar(
        backgroundColor: const Color(0xFF0A0A0F),
        titleSpacing: 0,
        leading: const BackButton(color: Colors.white70),
        title: TextField(
          controller: _ctrl,
          autofocus: true,
          onChanged: _onChanged,
          style: const TextStyle(color: Colors.white, fontSize: 15),
          decoration: InputDecoration(
            hintText: 'Podcast, episode, subject...',
            hintStyle: const TextStyle(color: Color(0xFF6B6B80)),
            border: InputBorder.none,
            suffixIcon: _ctrl.text.isNotEmpty
                ? IconButton(
                    icon: const Icon(Icons.close_rounded, color: Colors.white54, size: 18),
                    onPressed: () { _ctrl.clear(); setState(() { _podcasts = []; _episodes = []; _lastQ = ''; }); })
                : null,
          ),
        ),
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(40),
          child: Row(
            children: [
              const SizedBox(width: 16),
              for (final t in [('all','Waxkasta'), ('podcast','Podcast'), ('episode','Episode')])
                GestureDetector(
                  onTap: () { setState(() { _tab = t.$1; _lastQ = ''; }); _search(_ctrl.text.trim()); },
                  child: Container(
                    margin: const EdgeInsets.only(right: 8),
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                    decoration: BoxDecoration(
                      color: _tab == t.$1 ? const Color(0xFF7C3AED) : const Color(0xFF1C1C28),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: Text(t.$2, style: TextStyle(
                      color: _tab == t.$1 ? Colors.white : Colors.white60,
                      fontSize: 12, fontWeight: FontWeight.w600,
                    )),
                  ),
                ),
            ],
          ),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF7C3AED), strokeWidth: 2))
          : _buildResults(),
    );
  }

  Widget _buildResults() {
    if (_ctrl.text.trim().length < 2) {
      return const Center(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Icon(Icons.search_rounded, color: Color(0xFF3A3A4A), size: 56),
          SizedBox(height: 12),
          Text('Wax raadi...', style: TextStyle(color: Color(0xFF6B6B80), fontSize: 15)),
        ]),
      );
    }
    if (_podcasts.isEmpty && _episodes.isEmpty) {
      return Center(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.podcasts_rounded, color: Color(0xFF3A3A4A), size: 56),
          const SizedBox(height: 12),
          Text('"${_ctrl.text}" lama helin', style: const TextStyle(color: Color(0xFF6B6B80), fontSize: 15)),
        ]),
      );
    }

    return ListView(
      padding: const EdgeInsets.symmetric(vertical: 8),
      children: [
        if (_podcasts.isNotEmpty) ...[
          const _SectionLabel('Podcast-yada'),
          ..._podcasts.map((p) => _PodcastTile(podcast: p)),
        ],
        if (_episodes.isNotEmpty) ...[
          const _SectionLabel('Episodes'),
          ..._episodes.map((e) => _EpisodeTile(episode: e)),
        ],
      ],
    );
  }
}

class _SectionLabel extends StatelessWidget {
  final String text;
  const _SectionLabel(this.text);
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 16, 16, 6),
    child: Text(text, style: const TextStyle(color: Color(0xFF6B6B80), fontSize: 12, fontWeight: FontWeight.w700, letterSpacing: 0.5)),
  );
}

class _PodcastTile extends StatelessWidget {
  final Podcast podcast;
  const _PodcastTile({required this.podcast});

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      leading: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: podcast.coverImage != null
            ? Image.network(podcast.coverImage!, width: 52, height: 52, fit: BoxFit.cover, errorBuilder: (_,__,___) => _Cover())
            : _Cover(),
      ),
      title: Row(children: [
        Expanded(child: Text(podcast.title, style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600), maxLines: 1, overflow: TextOverflow.ellipsis)),
        if (podcast.isVerified) const Icon(Icons.verified_rounded, color: Color(0xFF7C3AED), size: 14),
      ]),
      subtitle: Text('${podcast.totalEpisodes} episodes · ⭐ ${podcast.rating}',
          style: const TextStyle(color: Color(0xFF6B6B80), fontSize: 11)),
      trailing: const Icon(Icons.chevron_right_rounded, color: Colors.white38),
      onTap: () {},
    );
  }
}

class _EpisodeTile extends StatelessWidget {
  final PodcastEpisode episode;
  const _EpisodeTile({required this.episode});

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
    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      leading: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: episode.coverImage != null
            ? Image.network(episode.coverImage!, width: 52, height: 52, fit: BoxFit.cover, errorBuilder: (_,__,___) => _Cover())
            : _Cover(),
      ),
      title: Text(episode.title, style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w600), maxLines: 2, overflow: TextOverflow.ellipsis),
      subtitle: Text(
        '${episode.podcast?.title ?? ''} · ${episode.durationFmt}',
        style: const TextStyle(color: Color(0xFF6B6B80), fontSize: 11),
      ),
      trailing: GestureDetector(
        onTap: () => _play(context),
        child: Container(
          width: 36, height: 36,
          decoration: const BoxDecoration(color: Color(0xFF7C3AED), shape: BoxShape.circle),
          child: const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 20),
        ),
      ),
      onTap: () => _play(context),
    );
  }
}

class _Cover extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    width: 52, height: 52,
    color: const Color(0xFF1C1C28),
    child: const Icon(Icons.podcasts_rounded, color: Color(0xFF3A3A4A), size: 24),
  );
}
