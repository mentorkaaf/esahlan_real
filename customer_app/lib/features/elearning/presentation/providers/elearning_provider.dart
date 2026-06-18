import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../../core/theme/theme_x.dart';
import '../../data/models/elearning_models.dart';
import '../../data/services/elearning_api_service.dart';

// ── Service singleton ─────────────────────────────────────────────────────────
final _svc = ELearningApiService.create();

// ── Filters state ─────────────────────────────────────────────────────────────
class ELearningFilters {
  final int? categoryId;
  final String? search;
  final String? level;
  final bool? isFree;
  final String sort;

  const ELearningFilters({
    this.categoryId,
    this.search,
    this.level,
    this.isFree,
    this.sort = 'newest',
  });

  ELearningFilters copyWith({
    Object? categoryId = _sentinel,
    Object? search = _sentinel,
    Object? level = _sentinel,
    Object? isFree = _sentinel,
    String? sort,
  }) {
    return ELearningFilters(
      categoryId: categoryId == _sentinel ? this.categoryId : categoryId as int?,
      search:     search     == _sentinel ? this.search     : search     as String?,
      level:      level      == _sentinel ? this.level      : level      as String?,
      isFree:     isFree     == _sentinel ? this.isFree     : isFree     as bool?,
      sort:       sort ?? this.sort,
    );
  }
}

const _sentinel = Object();

// ── Providers ─────────────────────────────────────────────────────────────────

final elearningCategoriesProvider = FutureProvider<List<ELearningCategory>>((ref) async {
  return _svc.getCategories();
});

final elearningFiltersProvider = StateProvider<ELearningFilters>((ref) => const ELearningFilters());

final elearningCoursesProvider = FutureProvider<Map<String, dynamic>>((ref) async {
  final filters = ref.watch(elearningFiltersProvider);
  return _svc.getCourses(
    categoryId: filters.categoryId,
    search:     filters.search,
    level:      filters.level,
    isFree:     filters.isFree,
    sort:       filters.sort,
  );
});

final courseDetailProvider = FutureProvider.family<Map<String, dynamic>, String>((ref, slug) async {
  return _svc.getCourseDetailFull(slug);
});

final myLearningProvider = FutureProvider<List<ELearningEnrollment>>((ref) async {
  return _svc.getMyLearning();
});

final myCertificatesProvider = FutureProvider<List<ELearningCertificate>>((ref) async {
  return _svc.getMyCertificates();
});

final wishlistProvider = FutureProvider<List<dynamic>>((ref) async {
  return _svc.getWishlist();
});

final quizProvider = FutureProvider.family<QuizData, int>((ref, quizId) async {
  return _svc.getQuiz(quizId);
});

// ── Instructor ────────────────────────────────────────────────────────────────

final elearningServiceProvider = Provider<ELearningApiService>((ref) => _svc);

final instructorStatusProvider = FutureProvider<InstructorStatus>((ref) async {
  return _svc.getInstructorStatus();
});

final instructorDashboardProvider = FutureProvider<InstructorDashboard>((ref) async {
  return _svc.getInstructorDashboard();
});

final instructorCoursesProvider = FutureProvider<List<InstructorCourse>>((ref) async {
  return _svc.getInstructorCourses();
});

final courseStructureProvider = FutureProvider.family<Map<String, dynamic>, int>((ref, courseId) async {
  return _svc.getCourseStructure(courseId);
});
