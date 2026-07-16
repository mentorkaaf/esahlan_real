import 'dart:async';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../data/models/podcast_models.dart';
import '../../data/repositories/podcast_repository.dart';
import '../services/podcast_audio_service.dart';
import 'episode_player_screen.dart';

const _kBg     = Color(0xFFF5F6FA);
const _kNavy   = Color(0xFF07003B);
const _kOrange = Color(0xFFFF8A00);
const _kPurple = Color(0xFF7C3AED);
const _prefKey = 'podcast_recent_searches';

class PodcastSearchScreen extends StatefulWidget {
  const PodcastSearchScreen({super.key});
  @override
  State<PodcastSearchScreen> createState() => _PodcastSearchScreenState();
}

class _PodcastSearchScreenState extends State<PodcastSearchScreen> {
  final _ctrl     = TextEditingController();
  final _repo     = PodcastRepository();
  final _focus    = FocusNode();
  Timer?          _debounce;
  bool            _loading  = false;
  String          _tab      = 'all';
  List<Podcast>   _podcasts = [];
  List<PodcastEpisode> _episodes = [];
  String          _lastQ   = '';
  List<String>    _recent  = [];

  List<String> _trending = [];

  @override
  void initState() {
    super.initState();
    _loadRecent();
    _loadTrending();
    _ctrl.addListener(() => setState(() {}));
  }

  Future<void> _loadTrending() async {
    try {
      final cats = await _repo.getCategories();
      if (mounted) {
        setState(() => _trending = cats.take(6).map((c) => c.name).toList());
      }
    } catch (_) {
      // silent — trending stays empty
    }
  }

  @override
  void dispose() {
    _ctrl.dispose();
    _focus.dispose();
    _debounce?.cancel();
    super.dispose();
  }

  Future<void> _loadRecent() async {
    final prefs = await SharedPreferences.getInstance();
    setState(() => _recent = prefs.getStringList(_prefKey) ?? []);
  }

  Future<void> _saveRecent(String q) async {
    final prefs = await SharedPreferences.getInstance();
    _recent.remove(q);
    _recent.insert(0, q);
    if (_recent.length > 8) _recent = _recent.sublist(0, 8);
    await prefs.setStringList(_prefKey, _recent);
    setState(() {});
  }

  Future<void> _clearRecent() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_prefKey);
    setState(() => _recent = []);
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
    if (q.isEmpty) return;
    if (q == _lastQ) return;
    _lastQ = q;
    setState(() => _loading = true);
    await _saveRecent(q);
    try {
      final r = await _repo.search(q, type: _tab);
      if (!mounted) return;
      setState(() {
        _podcasts = (r['podcasts']?['data'] as List? ?? [])
            .map((e) => Podcast.fromJson(e)).toList();
        _episodes = (r['episodes']?['data'] as List? ?? [])
            .map((e) => PodcastEpisode.fromJson(e)).toList();
      });
    } catch (_) {} finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _submitSearch(String q) {
    q = q.trim();
    if (q.length < 2) return;
    _search(q);
    _focus.unfocus();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: _kBg,
    appBar: AppBar(
      backgroundColor: Colors.white,
      elevation: 0,
      leading: IconButton(
        icon: const Icon(Icons.arrow_back_ios_new_rounded, color: _kNavy, size: 20),
        onPressed: () => Navigator.pop(context),
      ),
      titleSpacing: 0,
      title: Container(
        height: 42,
        margin: const EdgeInsets.only(right: 16),
        decoration: BoxDecoration(
          color: const Color(0xFFF0F2F5),
          borderRadius: BorderRadius.circular(22),
          border: Border.all(color: _kOrange.withAlpha(80), width: 1.5),
        ),
        child: Row(children: [
          const SizedBox(width: 12),
          const Icon(Icons.search_rounded, color: _kOrange, size: 20),
          const SizedBox(width: 8),
          Expanded(
            child: TextField(
              controller: _ctrl,
              focusNode: _focus,
              autofocus: true,
              onChanged: _onChanged,
              onSubmitted: _submitSearch,
              textInputAction: TextInputAction.search,
              style: const TextStyle(color: _kNavy, fontSize: 14),
              decoration: const InputDecoration(
                hintText: 'Search podcasts, episodes, creators...',
                hintStyle: TextStyle(color: Colors.grey, fontSize: 13),
                border: InputBorder.none,
                isDense: true,
              ),
            ),
          ),
          if (_ctrl.text.isNotEmpty)
            GestureDetector(
              onTap: () {
                _ctrl.clear();
                setState(() { _podcasts = []; _episodes = []; _lastQ = ''; });
              },
              child: const Padding(
                padding: EdgeInsets.symmetric(horizontal: 10),
                child: Icon(Icons.close_rounded, color: Colors.grey, size: 18),
              ),
            ),
        ]),
      ),
    ),
    body: Column(children: [
      // Filter chips
      Container(
        color: Colors.white,
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
        child: Row(children: [
          for (final t in [('all', 'Waxkasta'), ('podcast', 'Podcast'), ('episode', 'Episode')])
            GestureDetector(
              onTap: () {
                setState(() { _tab = t.$1; _lastQ = ''; });
                if (_ctrl.text.trim().length >= 2) _search(_ctrl.text.trim());
              },
              child: Container(
                margin: const EdgeInsets.only(right: 8),
                padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 7),
                decoration: BoxDecoration(
                  color: _tab == t.$1 ? _kPurple : Colors.transparent,
                  borderRadius: BorderRadius.circular(20),
                  border: Border.all(
                    color: _tab == t.$1 ? _kPurple : Colors.grey.shade300,
                  ),
                ),
                child: Text(t.$2, style: TextStyle(
                  color: _tab == t.$1 ? Colors.white : Colors.grey.shade600,
                  fontSize: 13, fontWeight: FontWeight.w600,
                )),
              ),
            ),
        ]),
      ),
      const Divider(height: 1, color: Color(0xFFEEEEEE)),
      Expanded(
        child: _loading
            ? const Center(child: CircularProgressIndicator(color: _kOrange, strokeWidth: 2))
            : _ctrl.text.trim().length < 2
                ? _buildEmptyState()
                : _buildResults(),
      ),
    ]),
  );

  Widget _buildEmptyState() => ListView(
    padding: const EdgeInsets.all(20),
    children: [
      // Recent Searches
      if (_recent.isNotEmpty) ...[
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          const Text('Recent Searches',
              style: TextStyle(color: _kNavy, fontSize: 15, fontWeight: FontWeight.w800)),
          GestureDetector(
            onTap: _clearRecent,
            child: const Text('Clear all',
                style: TextStyle(color: _kOrange, fontSize: 13, fontWeight: FontWeight.w600)),
          ),
        ]),
        const SizedBox(height: 12),
        Wrap(
          spacing: 8, runSpacing: 8,
          children: _recent.map((q) => GestureDetector(
            onTap: () { _ctrl.text = q; _onChanged(q); },
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: Colors.grey.shade200),
                boxShadow: [BoxShadow(color: Colors.black.withAlpha(6), blurRadius: 4)],
              ),
              child: Text(q, style: const TextStyle(color: _kNavy, fontSize: 13, fontWeight: FontWeight.w600)),
            ),
          )).toList(),
        ),
        const SizedBox(height: 28),
      ],

      // Trending Searches
      const Text('Trending Searches',
          style: TextStyle(color: _kNavy, fontSize: 15, fontWeight: FontWeight.w800)),
      const SizedBox(height: 12),
      ..._trending.map((q) => GestureDetector(
        onTap: () { _ctrl.text = q; _onChanged(q); },
        child: Container(
          margin: const EdgeInsets.only(bottom: 4),
          padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
          decoration: const BoxDecoration(
            border: Border(bottom: BorderSide(color: Color(0xFFF0F0F0))),
          ),
          child: Row(children: [
            const Icon(Icons.search_rounded, color: Colors.grey, size: 18),
            const SizedBox(width: 12),
            Expanded(child: Text(q,
                style: const TextStyle(color: _kNavy, fontSize: 14, fontWeight: FontWeight.w500))),
            const Icon(Icons.north_west_rounded, color: Colors.grey, size: 14),
          ]),
        ),
      )),
    ],
  );

  Widget _buildResults() {
    if (_podcasts.isEmpty && _episodes.isEmpty) {
      return Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
        const Icon(Icons.podcasts_rounded, color: Colors.grey, size: 56),
        const SizedBox(height: 12),
        Text('"${_ctrl.text}" lama helin',
            style: const TextStyle(color: Colors.grey, fontSize: 15)),
      ]));
    }
    return ListView(
      padding: const EdgeInsets.symmetric(vertical: 8),
      children: [
        if (_podcasts.isNotEmpty) ...[
          _sectionLabel('Podcasts'),
          ..._podcasts.map((p) => _PodcastTile(podcast: p)),
        ],
        if (_episodes.isNotEmpty) ...[
          _sectionLabel('Episodes'),
          ..._episodes.map((e) => _EpisodeTile(episode: e)),
        ],
      ],
    );
  }

  Widget _sectionLabel(String text) => Padding(
    padding: const EdgeInsets.fromLTRB(16, 14, 16, 6),
    child: Text(text, style: const TextStyle(
        color: _kNavy, fontSize: 13, fontWeight: FontWeight.w800, letterSpacing: 0.3)),
  );
}

// ─── Tiles ───────────────────────────────────────────────────────────────────

class _PodcastTile extends StatelessWidget {
  final Podcast podcast;
  const _PodcastTile({required this.podcast});
  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
    decoration: BoxDecoration(
        color: Colors.white, borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withAlpha(6), blurRadius: 4)]),
    child: ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      leading: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: podcast.coverImage != null
            ? Image.network(podcast.coverImage!, width: 52, height: 52, fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => _CoverPlaceholder())
            : _CoverPlaceholder(),
      ),
      title: Row(children: [
        Expanded(child: Text(podcast.title,
            style: const TextStyle(color: _kNavy, fontSize: 14, fontWeight: FontWeight.w700),
            maxLines: 1, overflow: TextOverflow.ellipsis)),
        if (podcast.isVerified)
          const Icon(Icons.verified_rounded, color: _kPurple, size: 14),
      ]),
      subtitle: Text('${podcast.totalEpisodes} episodes · ⭐ ${podcast.rating}',
          style: const TextStyle(color: Colors.grey, fontSize: 11)),
      trailing: const Icon(Icons.chevron_right_rounded, color: Colors.grey),
    ),
  );
}

class _EpisodeTile extends StatefulWidget {
  final PodcastEpisode episode;
  const _EpisodeTile({required this.episode});
  @override
  State<_EpisodeTile> createState() => _EpisodeTileState();
}

class _EpisodeTileState extends State<_EpisodeTile> {
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
    decoration: BoxDecoration(
        color: Colors.white, borderRadius: BorderRadius.circular(12),
        boxShadow: [BoxShadow(color: Colors.black.withAlpha(6), blurRadius: 4)]),
    child: ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      leading: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: widget.episode.coverImage != null
            ? Image.network(widget.episode.coverImage!, width: 52, height: 52, fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => _CoverPlaceholder())
            : _CoverPlaceholder(),
      ),
      title: Text(widget.episode.title,
          style: const TextStyle(color: _kNavy, fontSize: 14, fontWeight: FontWeight.w700),
          maxLines: 2, overflow: TextOverflow.ellipsis),
      subtitle: Text('${widget.episode.podcast?.title ?? ''} · ${widget.episode.durationFmt}',
          style: const TextStyle(color: Colors.grey, fontSize: 11)),
      trailing: GestureDetector(
        onTap: _play,
        child: Container(
          width: 36, height: 36,
          decoration: const BoxDecoration(color: _kOrange, shape: BoxShape.circle),
          child: _loading
              ? const Padding(padding: EdgeInsets.all(10),
                  child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
              : const Icon(Icons.play_arrow_rounded, color: Colors.white, size: 20),
        ),
      ),
      onTap: _play,
    ),
  );
}

class _CoverPlaceholder extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
    width: 52, height: 52,
    decoration: BoxDecoration(color: _kNavy.withAlpha(15), borderRadius: BorderRadius.circular(8)),
    child: const Icon(Icons.podcasts_rounded, color: _kNavy, size: 24),
  );
}
