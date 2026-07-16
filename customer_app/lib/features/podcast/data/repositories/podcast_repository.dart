import '../../../../core/api/api_client.dart';
import '../models/podcast_models.dart';

class PodcastRepository {
  final _dio = ApiClient.instance;

  Future<PodcastHomeData> getHome() async {
    final r = await _dio.get('/podcast/home');
    return PodcastHomeData.fromJson(r.data['data']);
  }

  Future<List<PodcastCategory>> getCategories() async {
    final r = await _dio.get('/podcast/categories');
    return (r.data['data'] as List).map((e) => PodcastCategory.fromJson(e)).toList();
  }

  Future<Map<String, dynamic>> getCategoryPodcasts(String slug, {int page = 1}) async {
    final r = await _dio.get('/podcast/categories/$slug', queryParameters: {'page': page});
    return r.data;
  }

  Future<Map<String, dynamic>> getPodcast(String slug) async {
    final r = await _dio.get('/podcast/shows/$slug');
    return r.data;
  }

  Future<bool> followPodcast(int id) async {
    final r = await _dio.post('/podcast/shows/$id/follow');
    return r.data['following'] == true;
  }

  Future<PodcastEpisode> getEpisode(String slug) async {
    final r = await _dio.get('/podcast/episodes/$slug');
    return PodcastEpisode.fromJson(r.data['episode']);
  }

  Future<void> recordPlay(int episodeId, int position, {bool completed = false}) async {
    await _dio.post('/podcast/episodes/$episodeId/play', data: {
      'position': position,
      'completed': completed,
    });
  }

  Future<bool> likeEpisode(int id) async {
    final r = await _dio.post('/podcast/episodes/$id/like');
    return r.data['liked'] == true;
  }

  Future<bool> saveEpisode(int id) async {
    final r = await _dio.post('/podcast/episodes/$id/save');
    return r.data['saved'] == true;
  }

  Future<Map<String, dynamic>> search(String q, {String type = 'all', int page = 1}) async {
    final r = await _dio.get('/podcast/search', queryParameters: {'q': q, 'type': type, 'page': page});
    return r.data;
  }

  Future<Map<String, dynamic>> getRecommendations() async {
    final r = await _dio.get('/podcast/recommendations');
    return r.data['data'];
  }

  Future<Map<String, dynamic>> createShow(Map<String, dynamic> data) async {
    final r = await _dio.post('/podcast/shows', data: data);
    return r.data;
  }

  Future<Map<String, dynamic>> getRssPreview(String url) async {
    final r = await _dio.post('/podcast/rss-import', data: {'url': url});
    return r.data;
  }

  Future<List<dynamic>> getMyShows() async {
    final r = await _dio.get('/podcast/my-shows');
    return r.data['data'] as List;
  }
}
