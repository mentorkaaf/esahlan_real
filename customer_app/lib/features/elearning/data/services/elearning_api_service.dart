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

  Future<Map<String, dynamic>> verifyPurchase(int courseId,
      {String paymentMethod = 'wallet', String? paymentReference}) async {
    return await _post('$_base/purchase/verify', {
      'course_id': courseId,
      'payment_method': paymentMethod,
      if (paymentReference != null) 'payment_reference': paymentReference,
    });
  }

  Future<Map<String, dynamic>> initiateWaafiPayment(
      {required double amount, required String phone}) async {
    return await _post('/payment/initiate', {
      'amount': amount,
      'phone': phone,
      'type': 'custom',
      'description': 'eLearning course purchase',
    });
  }

  // ── Instructor (self) ──────────────────────────────────────────────────────────

  Future<InstructorStatus> getInstructorStatus() async {
    final res = await _get('$_base/instructor/status');
    return InstructorStatus.fromJson(res['data'] as Map<String, dynamic>);
  }

  Future<Map<String, dynamic>> applyInstructor({
    required String bio,
    required String expertise,
    String? qualifications,
    int? experienceYears,
    MultipartFile? profilePhoto,
  }) async {
    final form = FormData.fromMap({
      'bio':       bio,
      'expertise': expertise,
      if (qualifications != null && qualifications.isNotEmpty) 'qualifications': qualifications,
      if (experienceYears != null) 'experience_years': experienceYears,
      if (profilePhoto != null) 'profile_photo': profilePhoto,
    });
    final r = await _dio.post('$_base/instructor/apply', data: form);
    return r.data as Map<String, dynamic>;
  }

  Future<InstructorDashboard> getInstructorDashboard() async {
    final res = await _get('$_base/instructor/dashboard');
    return InstructorDashboard.fromJson(res['data'] as Map<String, dynamic>);
  }

  Future<List<InstructorCourse>> getInstructorCourses() async {
    final res = await _get('$_base/instructor/courses');
    final list = res['data'] as List<dynamic>? ?? [];
    return list.map((c) => InstructorCourse.fromJson(c as Map<String, dynamic>)).toList();
  }

  /// Course structure (sections + lessons) for the in-app builder. Works for drafts.
  Future<Map<String, dynamic>> getCourseStructure(int courseId) async {
    final res = await _get('$_base/instructor/courses/$courseId/structure');
    return res['data'] as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> createCourse({
    required String title,
    String? subtitle,
    required String description,
    required int categoryId,
    required String level,
    required String language,
    required double price,
    double? discountPrice,
    bool isFree = false,
    List<String>? learningOutcomes,
    List<String>? requirements,
    String? targetAudience,
    MultipartFile? thumbnail,
  }) async {
    final form = FormData.fromMap({
      'title':       title,
      if (subtitle != null && subtitle.isNotEmpty) 'subtitle': subtitle,
      'description': description,
      'category_id': categoryId,
      'level':       level,
      'language':    language,
      'price':       price,
      if (discountPrice != null) 'discount_price': discountPrice,
      'is_free':     isFree ? 1 : 0,
      if (learningOutcomes != null)
        for (var i = 0; i < learningOutcomes.length; i++) 'learning_outcomes[$i]': learningOutcomes[i],
      if (requirements != null)
        for (var i = 0; i < requirements.length; i++) 'requirements[$i]': requirements[i],
      if (targetAudience != null && targetAudience.isNotEmpty) 'target_audience': targetAudience,
      if (thumbnail != null) 'thumbnail': thumbnail,
    });
    final r = await _dio.post('$_base/instructor/courses', data: form);
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> addSection({
    required int courseId,
    required String title,
    int? sortOrder,
  }) async {
    return await _post('$_base/instructor/sections', {
      'course_id': courseId,
      'title':     title,
      if (sortOrder != null) 'sort_order': sortOrder,
    });
  }

  Future<Map<String, dynamic>> addLesson({
    required int sectionId,
    required String title,
    required String type,
    String? videoUrl,
    int? videoDurationSeconds,
    String? content,
    bool isFreePreview = false,
    int? sortOrder,
    MultipartFile? videoFile,
  }) async {
    final data = FormData.fromMap({
      'section_id':    sectionId,
      'title':         title,
      'type':          type,
      if (videoUrl != null && videoUrl.isNotEmpty) 'video_url': videoUrl,
      if (videoDurationSeconds != null) 'video_duration_seconds': videoDurationSeconds,
      if (content != null && content.isNotEmpty) 'content': content,
      'is_free_preview': isFreePreview ? 1 : 0,
      if (sortOrder != null) 'sort_order': sortOrder,
      if (videoFile != null) 'video_file': videoFile,
    });
    final r = await _dio.post('$_base/instructor/lessons', data: data);
    return r.data as Map<String, dynamic>;
  }

  /// Submit a draft course for admin review (status -> pending).
  Future<Map<String, dynamic>> submitCourseForReview(int courseId) async {
    final r = await _dio.put('$_base/instructor/courses/$courseId', data: {'status': 'pending'});
    return r.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> updateCourse(int courseId, {
    String? title,
    String? subtitle,
    String? description,
    int? categoryId,
    String? level,
    String? language,
    double? price,
    bool? isFree,
    List<String>? learningOutcomes,
    List<String>? requirements,
    MultipartFile? thumbnail,
  }) async {
    final data = FormData.fromMap({
      if (title != null) 'title': title,
      if (subtitle != null) 'subtitle': subtitle,
      if (description != null) 'description': description,
      if (categoryId != null) 'category_id': categoryId,
      if (level != null) 'level': level,
      if (language != null) 'language': language,
      if (price != null) 'price': price,
      if (isFree != null) 'is_free': isFree ? 1 : 0,
      if (learningOutcomes != null) 'learning_outcomes': learningOutcomes,
      if (requirements != null) 'requirements': requirements,
      if (thumbnail != null) 'thumbnail': thumbnail,
      '_method': 'PUT',
    });
    final r = await _dio.post('$_base/instructor/courses/$courseId', data: data);
    return r.data as Map<String, dynamic>;
  }

  Future<void> deleteCourse(int courseId) async {
    await _dio.delete('$_base/instructor/courses/$courseId');
  }
}
