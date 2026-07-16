import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/podcast_models.dart';
import '../../data/repositories/podcast_repository.dart';
import '../widgets/podcast_cover.dart';
import 'podcast_detail_screen.dart';

const kNavy   = Color(0xFF07003B);
const kOrange = Color(0xFFFF8A00);
const kBg     = Color(0xFFF0F2F5);

final _repoProvider = Provider((_) => PodcastRepository());

final _allPodcastsProvider = FutureProvider.autoDispose.family<Map<String, dynamic>, String>((ref, sort) {
  return ref.watch(_repoProvider).getAllPodcasts(sort: sort);
});

class AllPodcastsScreen extends ConsumerStatefulWidget {
  const AllPodcastsScreen({super.key});
  @override
  ConsumerState<AllPodcastsScreen> createState() => _AllPodcastsScreenState();
}

class _AllPodcastsScreenState extends ConsumerState<AllPodcastsScreen> {
  String _sort = 'popular';
  final _sorts = ['popular', 'new', 'rating'];
  final _sortLabels = {'popular': 'Popular', 'new': 'Newest', 'rating': 'Top Rated'};

  @override
  Widget build(BuildContext context) {
    final async = ref.watch(_allPodcastsProvider(_sort));

    return Scaffold(
      backgroundColor: kBg,
      appBar: AppBar(
        backgroundColor: kNavy,
        title: const Text('All Podcasts', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
        iconTheme: const IconThemeData(color: Colors.white),
        actions: [
          PopupMenuButton<String>(
            icon: const Icon(Icons.sort_rounded, color: Colors.white),
            onSelected: (v) => setState(() => _sort = v),
            itemBuilder: (_) => _sorts.map((s) => PopupMenuItem(
              value: s,
              child: Text(_sortLabels[s]!, style: TextStyle(
                color: s == _sort ? kOrange : kNavy,
                fontWeight: s == _sort ? FontWeight.w700 : FontWeight.normal,
              )),
            )).toList(),
          ),
        ],
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
        error: (e, _) => Center(child: Text('Error: $e')),
        data: (data) {
          final items = (data['data']?['data'] as List? ?? [])
              .map((e) => Podcast.fromJson(Map<String, dynamic>.from(e))).toList();
          if (items.isEmpty) return const Center(child: Text('No podcasts found'));
          return ListView.builder(
            padding: const EdgeInsets.symmetric(vertical: 8),
            itemCount: items.length,
            itemBuilder: (_, i) => _PodcastListTile(podcast: items[i]),
          );
        },
      ),
    );
  }
}

class _PodcastListTile extends StatefulWidget {
  final Podcast podcast;
  const _PodcastListTile({required this.podcast});
  @override
  State<_PodcastListTile> createState() => _PodcastListTileState();
}

class _PodcastListTileState extends State<_PodcastListTile> {
  late bool _following;

  @override
  void initState() {
    super.initState();
    _following = widget.podcast.isFollowing;
  }

  Future<void> _toggle() async {
    setState(() => _following = !_following);
    try {
      final repo = PodcastRepository();
      final result = await repo.followPodcast(widget.podcast.id);
      if (mounted) setState(() => _following = result);
    } catch (_) {
      if (mounted) setState(() => _following = !_following);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = widget.podcast;
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12),
          boxShadow: [BoxShadow(color: Colors.black.withAlpha(8), blurRadius: 6)]),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        leading: ClipRRect(borderRadius: BorderRadius.circular(10),
            child: PodcastCover(url: p.coverImage, width: 56, height: 56)),
        title: Row(children: [
          Expanded(child: Text(p.title, style: const TextStyle(color: kNavy, fontWeight: FontWeight.w700, fontSize: 13))),
          if (p.isVerified) const Icon(Icons.verified_rounded, color: kOrange, size: 14),
        ]),
        subtitle: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(p.category?.name ?? '', style: const TextStyle(color: Colors.grey, fontSize: 11)),
          const SizedBox(height: 3),
          Row(children: [
            const Icon(Icons.headset_rounded, color: kOrange, size: 11),
            const SizedBox(width: 3),
            Text(_fmt(p.totalFollowers), style: const TextStyle(color: kOrange, fontSize: 10, fontWeight: FontWeight.w600)),
            const SizedBox(width: 10),
            const Icon(Icons.mic_rounded, size: 11, color: Colors.grey),
            const SizedBox(width: 2),
            Text('${p.totalEpisodes} ep', style: const TextStyle(color: Colors.grey, fontSize: 10)),
          ]),
        ]),
        trailing: Row(mainAxisSize: MainAxisSize.min, children: [
          GestureDetector(
            onTap: _toggle,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: _following ? kNavy : kOrange,
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(_following ? 'Following' : 'Follow',
                  style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w700)),
            ),
          ),
          const SizedBox(width: 4),
          PopupMenuButton<String>(
            icon: const Icon(Icons.more_vert_rounded, color: Colors.grey, size: 18),
            onSelected: (_) {},
            itemBuilder: (_) => [
              const PopupMenuItem(value: 'share', child: Text('Share')),
              const PopupMenuItem(value: 'report', child: Text('Report')),
            ],
          ),
        ]),
        onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => PodcastDetailScreen(slug: p.slug))),
      ),
    );
  }

  String _fmt(int n) => n >= 1000 ? '${(n / 1000).toStringAsFixed(1)}K' : '$n';
}
