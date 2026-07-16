import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../providers/podcast_provider.dart';
import '../../data/models/podcast_models.dart';
import 'all_podcasts_screen.dart';

const kNavy   = Color(0xFF07003B);
const kOrange = Color(0xFFFF8A00);

class PodcastCategoriesScreen extends ConsumerWidget {
  const PodcastCategoriesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final async = ref.watch(podcastCategoriesProvider);

    return Scaffold(
      backgroundColor: const Color(0xFFF0F2F5),
      appBar: AppBar(
        backgroundColor: kNavy,
        title: const Text('Categories', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
        iconTheme: const IconThemeData(color: Colors.white),
      ),
      body: async.when(
        loading: () => const Center(child: CircularProgressIndicator(color: kOrange)),
        error: (e, _) => Center(child: Text('$e')),
        data: (cats) => GridView.builder(
          padding: const EdgeInsets.all(16),
          gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: 2, mainAxisSpacing: 12, crossAxisSpacing: 12, childAspectRatio: 1.6),
          itemCount: cats.length,
          itemBuilder: (_, i) => _CatCard(cat: cats[i]),
        ),
      ),
    );
  }
}

class _CatCard extends StatelessWidget {
  final PodcastCategory cat;
  const _CatCard({required this.cat});

  Color _bg() {
    try { return Color(int.parse(cat.color.replaceFirst('#', '0xFF'))); }
    catch (_) { return kOrange; }
  }

  IconData _icon() {
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
    return map[cat.icon] ?? Icons.podcasts_rounded;
  }

  @override
  Widget build(BuildContext context) {
    final bg = _bg();
    return GestureDetector(
      onTap: () => Navigator.of(context).push(MaterialPageRoute(
          builder: (_) => const AllPodcastsScreen())),
      child: Container(
        decoration: BoxDecoration(
          gradient: LinearGradient(colors: [bg, bg.withAlpha(179)], begin: Alignment.topLeft, end: Alignment.bottomRight),
          borderRadius: BorderRadius.circular(14),
        ),
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
          Icon(_icon(), color: Colors.white, size: 30),
          Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(cat.name, style: const TextStyle(color: Colors.white, fontSize: 14, fontWeight: FontWeight.w800)),
            Text('${cat.podcastCount} podcasts', style: TextStyle(color: Colors.white.withAlpha(204), fontSize: 11)),
          ]),
        ]),
      ),
    );
  }
}
