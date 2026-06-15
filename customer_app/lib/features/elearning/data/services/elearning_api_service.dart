import 'package:dio/dio.dart';
import '../../../../core/api/api_client.dart';
import '../models/elearning_models.dart';

class ELearningApiService {
  final Dio _dio;

  ELearningApiService(this._dio);
  static ELearningApiService create() => ELearningApiService(ApiClient.instance);

  static const String _base = '/elearning';

  // ── Helpers ──────────────────────────────────────────────────────────────────
  Future<dynamic> _get(String path, {Map<String, dynamic>? params}) async {
    final r = await _dio.get(path, queryParameters: params);
    return r.data;
  }

  Future<dynamic> _post(String path, Map<String, dynamic> data) async {
    final r = await _dio.post(path, data: data);
    return r.data;
  }

  // ── Public ───────────────────────────────────────────────────────────────────

  Future<List<ELearningCategory>> getCategories() async {
    final res = await _get('$_base/categories');
    final list = res['data'] as List<dynamic>? ?? [];
    return list.map((c) => ELearningCategory.fromJson(c as Map<String, dynamic>)).toList();
  }

  Future<Map<String, dynamic>> getCourses({
    int? categoryId,
    String? search,
    String? level,
    bool? isFree,
    String sort = 'newest',
    int page = 1,
  }) async {
    final res = await _get('$_base/courses', params: {
      if (categoryId != null) 'category_id': categoryId,
      if (search != null && search.isNotEmpty) 'search': search,
      if (level != null) 'level': level,
      if (isFree != null) 'is_free': isFree ? 1 : 0,
      'sort': sort,
      'page': page,
    });
    final list = res['data'] as List<dynamic>? ?? [];
    return {
      'courses': list.map((c) => ELearningCourse.fromJson(c as Map<String, dynamic>)).toList(),
      'meta':    res['meta'] as Map<String, dynamic>? ?? {},
    };
  }

  Future<ELearningCourse> getCourseDetail(String slug) async {
    final res = await _get('$_base/courses/$slug');
    final data = res['data'] as Map<String, dynamic>;
    final course = ELearningCourse.fromJson(data);

    // Parse sections
    if (data['sections'] != null) {
      // Sections are stored separately on the course object via the detail screen
    }
    return course;
  }

  Future<Map<String, dynamic>> getCourseDetailFull(String slug) async {
    final res = await _get('$_base/courses/$slug');
    return res['data'] as Map<String, dynamic>;
  }

  Future<List<ELearningInstructor>> getInstructors() async {
    final res = await _get('$_base/instructors');
    final list = res['data'] as List<dynamic>? ?? [];
    return list.map((i) => ELearningInstructor.fromJson(i as Map<String, dynamic>)).toList();
  }

  // ── Student (Authenticated) ───────────────────────────────────────────────────

  Future<List<ELearningEnrollment>> getMyLearning() async {
    final res = await _get('$_base/my-learning');
    final list = res['data'] as List<dynamic>? ?? [];
    return list.map((e) => ELearningEnrollment.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<Map<String, dynamic>> enrollFree(int courseId) async {
    return await _post('$_base/enroll', {'course_id': courseId});
  }

  Future<Map<String, dynamic>> updateProgress(int lessonId, int watchSeconds, int lastPosition) async {
    return await _post('$_base/lesson-progress', {
      'lesson_id':             lessonId,
      'watch_seconds':         watchSeconds,
      'last_position_seconds': lastPosition,
    });
  }

  Future<Map<String, dynamic>> completeLesson(int lessonId) async {
    return await _post('$_base/complete-lesson', {'lesson_id': lessonId});
  }

  Future<List<ELearningCertificate>> getMyCertificates() async {
    final res = await _get('$_base/certificates');
    final list = res['data'] as List<dynamic>? ?? [];
    return list.map((c) => ELearningCertificate.fromJson(c as Map<String, dynamic>)).toList();
  }

  Future<List<dynamic>> getWishlist() async {
    final res = await _get('$_base/wishlist');
    return res['data'] as List<dynamic>? ?? [];
  }

  Future<bool> toggleWishlist(int courseId) async {
    final res = await _post('$_base/wishlist/toggle', {'course_id': courseId});
    return res['in_wishlist'] == true;
  }

  Future<Map<String, dynamic>> submitReview(int courseId, int rating, String? comment) async {
    return await _post('$_base/reviews', {
      'course_id': courseId,
      'rating':    rating,
      if (comment != null && comment.isNotEmpty) 'comment': comment,
    });
  }

  Future<List<ELearningNote>> getNotes(int lessonId) async {
    final res = await _get('$_base/notes', params: {'lesson_id': lessonId});
    final list = res['data'] as List<dynamic>? ?? [];
    return list.map((n) => ELearningNote.fromJson(n as Map<String, dynamic>)).toList();
  }

  Future<ELearningNote> saveNote(int lessonId, String note, int timestampSeconds) async {
    final res = await _post('$_base/notes', {
      'lesson_id':         lessonId,
      'note':              note,
      'timestamp_seconds': timestampSeconds,
    });
    return ELearningNote.fromJson(res['data'] as Map<String, dynamic>);
  }

  Future<QuizData> getQuiz(int quizId) async {
    final res = await _get('$_base/quiz/$quizId');
    return QuizData.fromJson(res['data'] as Map<String, dynamic>);
  }

  Future<QuizResult> submitQuiz(int quizId, Map<int, String> answers, int timeTaken) async {
    final Map<String, dynamic> answersMap = answers.map((k, v) => MapEntry(k.toString(), v));
    final res = await _post('$_base/quiz/submit', {
      'quiz_id':            quizId,
      'answers':            answersMap,
      'time_taken_seconds': timeTaken,
    });
    return QuizResult.fromJson(res['data'] as Map<String, dynamic>);
  }

  // ── Payment ──────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> initiatePurchase(int courseId) async {
    return await _post('$_base/purchase', {'course_id': courseId});
  }

  Future<Map<String, dynamic>> verifyPurchase(int courseId) async {
    return await _post('$_base/purchase/verify', {'course_id': courseId});
  }
}
