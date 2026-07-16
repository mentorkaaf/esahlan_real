import 'package:dio/dio.dart' as dio_pkg;
import '../../../../core/api/api_client.dart';
import '../models/podcast_models.dart';

class PodcastRepository {
  final _dio = ApiClient.instance;

  // ── Home ────────────────────────────────────────────────────────────────────
  Future<PodcastHomeData> getHome() async {
    final r = await _dio.get('/podcast/home');
    return PodcastHomeData.fromJson(r.data['data']);
  }

  Future<Map<String, dynamic>> getTopCharts({String type = 'popular', int page = 1}) async {
    final r = await _dio.get('/podcast/top-charts', queryParameters: {'type': type, 'page': page});
    return r.data;
  }

  Future<List<dynamic>> getLiveRooms() async {
    final r = await _dio.get('/podcast/live-rooms');
    return r.data['data'];
  }

  // ── Categories ──────────────────────────────────────────────────────────────
  Future<List<PodcastCategory>> getCategories() async {
    final r = await _dio.get('/podcast/categories');
    return (r.data['data'] as List).map((e) => PodcastCategory.fromJson(e)).toList();
  }

  Future<Map<String, dynamic>> getCategoryDetail(String slug, {int page = 1}) async {
    final r = await _dio.get('/podcast/categories/$slug', queryParameters: {'page': page});
    return r.data;
  }

  // ── Podcasts ─────────────────────────────────────────────────────────────────
  Future<Map<String, dynamic>> getAllPodcasts({String? category, String sort = 'popular', int page = 1}) async {
    final r = await _dio.get('/podcast/shows', queryParameters: {
      if (category != null) 'category': category,
      'sort': sort, 'page': page,
    });
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

  // ── Episodes ─────────────────────────────────────────────────────────────────
  Future<PodcastEpisode> getEpisode(String slug) async {
    final r = await _dio.get('/podcast/episodes/$slug');
    return PodcastEpisode.fromJson(r.data['episode']);
  }

  Future<void> recordPlay(int episodeId, int position, {bool completed = false}) async {
    await _dio.post('/podcast/episodes/$episodeId/play',
        data: {'position': position, 'completed': completed});
  }

  Future<bool> likeEpisode(int id) async {
    final r = await _dio.post('/podcast/episodes/$id/like');
    return r.data['liked'] == true;
  }

  Future<bool> saveEpisode(int id) async {
    final r = await _dio.post('/podcast/episodes/$id/save');
    return r.data['saved'] == true;
  }

  // ── Comments ─────────────────────────────────────────────────────────────────
  Future<Map<String, dynamic>> getComments(int episodeId, {int page = 1}) async {
    final r = await _dio.get('/podcast/episodes/$episodeId/comments', queryParameters: {'page': page});
    return r.data;
  }

  Future<Map<String, dynamic>> postComment(int episodeId, String body, {int? parentId}) async {
    final r = await _dio.post('/podcast/episodes/$episodeId/comments',
        data: {'body': body, if (parentId != null) 'parent_id': parentId});
    return r.data;
  }

  Future<bool> likeComment(int id) async {
    final r = await _dio.post('/podcast/comments/$id/like');
    return r.data['liked'] == true;
  }

  Future<List<dynamic>> getReplies(int commentId) async {
    final r = await _dio.get('/podcast/comments/$commentId/replies');
    return r.data['data'];
  }

  // ── Library ──────────────────────────────────────────────────────────────────
  Future<Map<String, dynamic>> getLibrary() async {
    final r = await _dio.get('/podcast/library');
    return r.data['data'];
  }

  Future<void> addToQueue(int episodeId) async {
    await _dio.post('/podcast/library/queue/$episodeId');
  }

  Future<void> removeFromQueue(int episodeId) async {
    await _dio.delete('/podcast/library/queue/$episodeId');
  }

  Future<List<dynamic>> getPlaylists() async {
    final r = await _dio.get('/podcast/library/playlists');
    return r.data['data'];
  }

  Future<void> createPlaylist(String title, {String privacy = 'private'}) async {
    await _dio.post('/podcast/library/playlists', data: {'title': title, 'privacy': privacy});
  }

  // ── Search ───────────────────────────────────────────────────────────────────
  Future<Map<String, dynamic>> search(String q, {String type = 'all', int page = 1}) async {
    final r = await _dio.get('/podcast/search', queryParameters: {'q': q, 'type': type, 'page': page});
    return r.data;
  }

  Future<Map<String, dynamic>> getRecommendations() async {
    final r = await _dio.get('/podcast/recommendations');
    return r.data['data'];
  }

  // ── Creator ──────────────────────────────────────────────────────────────────
  Future<List<dynamic>> getMyShows() async {
    final r = await _dio.get('/podcast/my-shows');
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

  Future<Map<String, dynamic>> uploadEpisode({
    required dynamic audioFile,
    dynamic coverFile,
    required String title,
    required String description,
    required String category,
    required String privacy,
  }) async {
    final formData = dio_pkg.FormData.fromMap({
      'title': title,
      'description': description,
      'category': category,
      'privacy': privacy,
      'audio': await dio_pkg.MultipartFile.fromFile(
          audioFile.path, filename: audioFile.path.split('/').last),
      if (coverFile != null)
        'cover_image': await dio_pkg.MultipartFile.fromFile(
            coverFile.path, filename: coverFile.path.split('/').last),
    });
    final r = await _dio.post('/podcast/episodes', data: formData);
    return r.data;
  }
}
