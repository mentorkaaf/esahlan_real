import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../data/models/podcast_models.dart';
import '../../data/repositories/podcast_repository.dart';

final _repo = PodcastRepository();

final podcastHomeProvider = FutureProvider.autoDispose<PodcastHomeData>((ref) {
  return _repo.getHome();
});

final podcastCategoriesProvider = FutureProvider.autoDispose<List<PodcastCategory>>((ref) {
  return _repo.getCategories();
});
