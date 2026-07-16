import 'package:flutter/material.dart';
import '../../data/models/podcast_models.dart';
import '../../data/repositories/podcast_repository.dart';
import '../widgets/podcast_cover.dart';
import '../services/podcast_audio_service.dart';
import 'episode_player_screen.dart';

const kNavy   = Color(0xFF07003B);
const kOrange = Color(0xFFFF8A00);

class TopChartsScreen extends StatefulWidget {
  const TopChartsScreen({super.key});
  @override
  State<TopChartsScreen> createState() => _TopChartsScreenState();
}

class _TopChartsScreenState extends State<TopChartsScreen> with SingleTickerProviderStateMixin {
  late TabController _tabs;
  final _types = ['popular', 'new', 'trending'];
  final _labels = ['Popular', 'New', 'Trending'];
  final _repo = PodcastRepository();
  final _cache = <String, List<PodcastEpisode>>{};
  bool _loading = false;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
    _tabs.addListener(() { if (!_tabs.indexIsChanging) _load(_types[_tabs.index]); });
    _load('popular');
  }

  @override
  void dispose() { _tabs.dispose(); super.dispose(); }

  Future<void> _load(String type) async {
    if (_cache.containsKey(type)) return;
    if (mounted) setState(() => _loading = true);
    try {
      final d = await _repo.getTopCharts(type: type);
      // paginator: d['data']['data'], or flat list: d['data']
      final raw = d['data'];
      final items = raw is Map ? (raw['data'] as List? ?? []) : (raw as List? ?? []);
      final list = items
          .map((e) => PodcastEpisode.fromJson(Map<String, dynamic>.from(e))).toList();
      if (mounted) setState(() { _cache[type] = list; _loading = false; });
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xFFF0F2F5),
    appBar: AppBar(
      backgroundColor: kNavy,
      title: const Text('Top Charts', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
      iconTheme: const IconThemeData(color: Colors.white),
      bottom: TabBar(
        controller: _tabs,
        indicatorColor: kOrange,
        labelColor: Colors.white,
        unselectedLabelColor: Colors.white54,
        tabs: _labels.map((l) => Tab(text: l)).toList(),
      ),
    ),
    body: _loading
        ? const Center(child: CircularProgressIndicator(color: kOrange))
        : TabBarView(
            controller: _tabs,
            children: _types.asMap().entries.map((entry) {
              final type = entry.value;
              final list = _cache[type] ?? [];
              return list.isEmpty
                  ? const Center(child: Text('No data yet'))
                  : ListView.builder(
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      itemCount: list.length,
                      itemBuilder: (_, i) => _ChartTile(rank: i + 1, episode: list[i]),
                    );
            }).toList(),
          ),
  );
}

class _ChartTile extends StatefulWidget {
  final int rank;
  final PodcastEpisode episode;
  const _ChartTile({required this.rank, required this.episode});
  @override
  State<_ChartTile> createState() => _ChartTileState();
}

class _ChartTileState extends State<_ChartTile> {
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
  Widget build(BuildContext context) => GestureDetector(
    onTap: _play,
    child: Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12),
          boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 6)]),
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(children: [
        SizedBox(width: 32, child: Text('${widget.rank}', style: TextStyle(
          fontSize: 18, fontWeight: FontWeight.w900,
          color: widget.rank <= 3 ? kOrange : Colors.grey.shade400,
        ))),
        ClipRRect(borderRadius: BorderRadius.circular(10),
            child: PodcastCover(url: widget.episode.coverImage, width: 52, height: 52)),
        const SizedBox(width: 12),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(widget.episode.title, maxLines: 1, overflow: TextOverflow.ellipsis,
              style: const TextStyle(color: kNavy, fontSize: 13, fontWeight: FontWeight.w700)),
          if (widget.episode.podcast != null)
            Text(widget.episode.podcast!.title, maxLines: 1, overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: Colors.grey, fontSize: 11)),
          const SizedBox(height: 2),
          Row(children: [
            const Icon(Icons.headset_rounded, size: 11, color: kOrange),
            const SizedBox(width: 3),
            Text(_fmt(widget.episode.playCount), style: const TextStyle(color: kOrange, fontSize: 10, fontWeight: FontWeight.w600)),
            const SizedBox(width: 10),
            const Icon(Icons.access_time_rounded, size: 11, color: Colors.grey),
            const SizedBox(width: 3),
            Text(widget.episode.durationFmt, style: const TextStyle(color: Colors.grey, fontSize: 10)),
          ]),
        ])),
        _loading
            ? const SizedBox(width: 34, height: 34,
                child: Padding(padding: EdgeInsets.all(8),
                    child: CircularProgressIndicator(color: kOrange, strokeWidth: 2)))
            : const Icon(Icons.play_circle_fill_rounded, color: kOrange, size: 34),
      ]),
    ),
  );

  String _fmt(int n) => n >= 1000 ? '${(n/1000).toStringAsFixed(1)}K' : '$n';
}
