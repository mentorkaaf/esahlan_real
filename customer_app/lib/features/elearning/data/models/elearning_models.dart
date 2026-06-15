// eLearning Data Models

class ELearningCategory {
  final int id;
  final String name;
  final String slug;
  final String? icon;
  final String? description;
  final List<ELearningCategory> children;

  const ELearningCategory({
    required this.id,
    required this.name,
    required this.slug,
    this.icon,
    this.description,
    this.children = const [],
  });

  factory ELearningCategory.fromJson(Map<String, dynamic> j) => ELearningCategory(
    id:          j['id'] as int,
    name:        j['name'] as String,
    slug:        j['slug'] as String,
    icon:        j['icon'] as String?,
    description: j['description'] as String?,
    children:    (j['children'] as List<dynamic>? ?? [])
                    .map((c) => ELearningCategory.fromJson(c as Map<String, dynamic>))
                    .toList(),
  );
}

class ELearningInstructor {
  final int id;
  final String name;
  final String? avatar;
  final String? profilePhoto;
  final String? expertise;
  final String? bio;
  final double rating;
  final int totalStudents;
  final int totalCourses;
  final int experienceYears;

  const ELearningInstructor({
    required this.id,
    required this.name,
    this.avatar,
    this.profilePhoto,
    this.expertise,
    this.bio,
    required this.rating,
    required this.totalStudents,
    required this.totalCourses,
    required this.experienceYears,
  });

  factory ELearningInstructor.fromJson(Map<String, dynamic> j) => ELearningInstructor(
    id:               j['id'] as int,
    name:             j['name'] as String,
    avatar:           j['avatar'] as String?,
    profilePhoto:     j['profile_photo'] as String?,
    expertise:        j['expertise'] as String?,
    bio:              j['bio'] as String?,
    rating:           (j['rating'] as num?)?.toDouble() ?? 0,
    totalStudents:    (j['total_students'] as num?)?.toInt() ?? 0,
    totalCourses:     (j['total_courses'] as num?)?.toInt() ?? 0,
    experienceYears:  (j['experience_years'] as num?)?.toInt() ?? 0,
  );

  String get displayPhoto => profilePhoto ?? avatar ?? '';
}

// ── Instructor (self) ─────────────────────────────────────────────────────────

class InstructorStatus {
  /// none | pending | approved | rejected
  final String applicationStatus;
  final int? id;
  final String? bio;
  final String? expertise;
  final String? qualifications;
  final int experienceYears;
  final String? profilePhoto;
  final double rating;
  final int totalCourses;
  final int totalStudents;

  const InstructorStatus({
    required this.applicationStatus,
    this.id,
    this.bio,
    this.expertise,
    this.qualifications,
    this.experienceYears = 0,
    this.profilePhoto,
    this.rating = 0,
    this.totalCourses = 0,
    this.totalStudents = 0,
  });

  bool get isApproved => applicationStatus == 'approved';
  bool get isPending  => applicationStatus == 'pending';
  bool get isRejected => applicationStatus == 'rejected';
  bool get hasApplied => applicationStatus != 'none';

  factory InstructorStatus.fromJson(Map<String, dynamic> j) {
    final inst = j['instructor'] as Map<String, dynamic>?;
    return InstructorStatus(
      applicationStatus: j['application_status'] as String? ?? 'none',
      id:               inst?['id'] as int?,
      bio:              inst?['bio'] as String?,
      expertise:        inst?['expertise'] as String?,
      qualifications:   inst?['qualifications'] as String?,
      experienceYears:  (inst?['experience_years'] as num?)?.toInt() ?? 0,
      profilePhoto:     inst?['profile_photo'] as String?,
      rating:           (inst?['rating'] as num?)?.toDouble() ?? 0,
      totalCourses:     (inst?['total_courses'] as num?)?.toInt() ?? 0,
      totalStudents:    (inst?['total_students'] as num?)?.toInt() ?? 0,
    );
  }
}

class InstructorDashboard {
  final int totalStudents;
  final int totalCourses;
  final double totalEarnings;
  final double pendingEarnings;
  final double rating;
  final List<Map<String, dynamic>> recentEnrollments;

  const InstructorDashboard({
    required this.totalStudents,
    required this.totalCourses,
    required this.totalEarnings,
    required this.pendingEarnings,
    required this.rating,
    required this.recentEnrollments,
  });

  factory InstructorDashboard.fromJson(Map<String, dynamic> j) => InstructorDashboard(
    totalStudents:   (j['total_students'] as num?)?.toInt() ?? 0,
    totalCourses:    (j['total_courses'] as num?)?.toInt() ?? 0,
    totalEarnings:   (j['total_earnings'] as num?)?.toDouble() ?? 0,
    pendingEarnings: (j['pending_earnings'] as num?)?.toDouble() ?? 0,
    rating:          (j['rating'] as num?)?.toDouble() ?? 0,
    recentEnrollments: (j['recent_enrollments'] as List<dynamic>? ?? [])
        .map((e) => e as Map<String, dynamic>).toList(),
  );
}

class InstructorCourse {
  final int id;
  final String title;
  final String slug;
  final String? thumbnail;
  final String status;
  final double price;
  final int totalStudents;
  final double rating;
  final int totalLessons;
  final String? category;
  final String createdAt;

  const InstructorCourse({
    required this.id,
    required this.title,
    required this.slug,
    this.thumbnail,
    required this.status,
    required this.price,
    required this.totalStudents,
    required this.rating,
    required this.totalLessons,
    this.category,
    required this.createdAt,
  });

  factory InstructorCourse.fromJson(Map<String, dynamic> j) => InstructorCourse(
    id:            j['id'] as int,
    title:         j['title'] as String,
    slug:          j['slug'] as String,
    thumbnail:     j['thumbnail'] as String?,
    status:        j['status'] as String? ?? 'draft',
    price:         (j['price'] as num?)?.toDouble() ?? 0,
    totalStudents: (j['total_students'] as num?)?.toInt() ?? 0,
    rating:        (j['rating'] as num?)?.toDouble() ?? 0,
    totalLessons:  (j['total_lessons'] as num?)?.toInt() ?? 0,
    category:      j['category'] as String?,
    createdAt:     j['created_at'] as String? ?? '',
  );
}

class ELearningCourse {
  final int id;
  final String title;
  final String slug;
  final String? subtitle;
  final String? description;
  final String? thumbnail;
  final String? trailerVideo;
  final String language;
  final String level;
  final double price;
  final double? discountPrice;
  final double effectivePrice;
  final bool isFree;
  final bool isFeatured;
  final double durationHours;
  final int totalLessons;
  final int totalSections;
  final int totalStudents;
  final double rating;
  final int totalReviews;
  final List<String> learningOutcomes;
  final List<String> requirements;
  final String? targetAudience;
  final List<String> tags;
  final ELearningInstructor? instructor;
  final ELearningCategory? category;
  bool isEnrolled;

  ELearningCourse({
    required this.id,
    required this.title,
    required this.slug,
    this.subtitle,
    this.description,
    this.thumbnail,
    this.trailerVideo,
    required this.language,
    required this.level,
    required this.price,
    this.discountPrice,
    required this.effectivePrice,
    required this.isFree,
    required this.isFeatured,
    required this.durationHours,
    required this.totalLessons,
    required this.totalSections,
    required this.totalStudents,
    required this.rating,
    required this.totalReviews,
    this.learningOutcomes = const [],
    this.requirements = const [],
    this.targetAudience,
    this.tags = const [],
    this.instructor,
    this.category,
    this.isEnrolled = false,
  });

  factory ELearningCourse.fromJson(Map<String, dynamic> j) {
    ELearningInstructor? instructor;
    if (j['instructor'] is Map) {
      instructor = ELearningInstructor.fromJson(j['instructor'] as Map<String, dynamic>);
    }
    ELearningCategory? category;
    if (j['category'] is Map) {
      category = ELearningCategory.fromJson(j['category'] as Map<String, dynamic>);
    }
    return ELearningCourse(
      id:               j['id'] as int,
      title:            j['title'] as String,
      slug:             j['slug'] as String,
      subtitle:         j['subtitle'] as String?,
      description:      j['description'] as String?,
      thumbnail:        j['thumbnail'] as String?,
      trailerVideo:     j['trailer_video'] as String?,
      language:         j['language'] as String? ?? 'so',
      level:            j['level'] as String? ?? 'all',
      price:            (j['price'] as num?)?.toDouble() ?? 0,
      discountPrice:    (j['discount_price'] as num?)?.toDouble(),
      effectivePrice:   (j['effective_price'] as num?)?.toDouble() ?? (j['price'] as num?)?.toDouble() ?? 0,
      isFree:           j['is_free'] == true,
      isFeatured:       j['is_featured'] == true,
      durationHours:    (j['duration_hours'] as num?)?.toDouble() ?? 0,
      totalLessons:     (j['total_lessons'] as num?)?.toInt() ?? 0,
      totalSections:    (j['total_sections'] as num?)?.toInt() ?? 0,
      totalStudents:    (j['total_students'] as num?)?.toInt() ?? 0,
      rating:           (j['rating'] as num?)?.toDouble() ?? 0,
      totalReviews:     (j['total_reviews'] as num?)?.toInt() ?? 0,
      learningOutcomes: (j['learning_outcomes'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
      requirements:     (j['requirements'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
      targetAudience:   j['target_audience'] as String?,
      tags:             (j['tags'] as List<dynamic>? ?? []).map((e) => e.toString()).toList(),
      instructor:       instructor,
      category:         category,
      isEnrolled:       j['is_enrolled'] == true,
    );
  }
}

class ELearningLesson {
  final int id;
  final String title;
  final String type;
  final int videoDurationSeconds;
  final bool isFreePreview;
  final bool isLocked;
  final String? videoUrl;

  const ELearningLesson({
    required this.id,
    required this.title,
    required this.type,
    required this.videoDurationSeconds,
    required this.isFreePreview,
    required this.isLocked,
    this.videoUrl,
  });

  factory ELearningLesson.fromJson(Map<String, dynamic> j) => ELearningLesson(
    id:                    j['id'] as int,
    title:                 j['title'] as String,
    type:                  j['type'] as String? ?? 'video',
    videoDurationSeconds:  (j['video_duration_seconds'] as num?)?.toInt() ?? 0,
    isFreePreview:         j['is_free_preview'] == true,
    isLocked:              j['is_locked'] == true,
    videoUrl:              j['video_url'] as String?,
  );

  String get formattedDuration {
    if (videoDurationSeconds == 0) return '';
    final m = videoDurationSeconds ~/ 60;
    final s = videoDurationSeconds % 60;
    return '${m.toString().padLeft(2, '0')}:${s.toString().padLeft(2, '0')}';
  }
}

class ELearningSection {
  final int id;
  final String title;
  final List<ELearningLesson> lessons;

  const ELearningSection({required this.id, required this.title, required this.lessons});

  factory ELearningSection.fromJson(Map<String, dynamic> j) => ELearningSection(
    id:      j['id'] as int,
    title:   j['title'] as String,
    lessons: (j['lessons'] as List<dynamic>? ?? [])
                .map((l) => ELearningLesson.fromJson(l as Map<String, dynamic>))
                .toList(),
  );
}

class ELearningReview {
  final int id;
  final int rating;
  final String? comment;
  final Map<String, dynamic> user;
  final String createdAt;

  const ELearningReview({
    required this.id,
    required this.rating,
    this.comment,
    required this.user,
    required this.createdAt,
  });

  factory ELearningReview.fromJson(Map<String, dynamic> j) => ELearningReview(
    id:        j['id'] as int,
    rating:    j['rating'] as int,
    comment:   j['comment'] as String?,
    user:      j['user'] as Map<String, dynamic>? ?? {},
    createdAt: j['created_at'] as String? ?? '',
  );
}

class ELearningEnrollment {
  final int enrollmentId;
  final String status;
  final String enrolledAt;
  final String? completedAt;
  final int progressPercent;
  final int completedLessons;
  final int totalLessons;
  final ELearningCourse course;

  const ELearningEnrollment({
    required this.enrollmentId,
    required this.status,
    required this.enrolledAt,
    this.completedAt,
    required this.progressPercent,
    required this.completedLessons,
    required this.totalLessons,
    required this.course,
  });

  factory ELearningEnrollment.fromJson(Map<String, dynamic> j) => ELearningEnrollment(
    enrollmentId:      j['enrollment_id'] as int,
    status:            j['status'] as String,
    enrolledAt:        j['enrolled_at'] as String? ?? '',
    completedAt:       j['completed_at'] as String?,
    progressPercent:   (j['progress_percent'] as num?)?.toInt() ?? 0,
    completedLessons:  (j['completed_lessons'] as num?)?.toInt() ?? 0,
    totalLessons:      (j['total_lessons'] as num?)?.toInt() ?? 0,
    course:            ELearningCourse.fromJson(j['course'] as Map<String, dynamic>),
  );
}

class ELearningCertificate {
  final int id;
  final String certificateNumber;
  final String issuedAt;
  final String? pdfPath;
  final ELearningCourse course;

  const ELearningCertificate({
    required this.id,
    required this.certificateNumber,
    required this.issuedAt,
    this.pdfPath,
    required this.course,
  });

  factory ELearningCertificate.fromJson(Map<String, dynamic> j) => ELearningCertificate(
    id:                j['id'] as int,
    certificateNumber: j['certificate_number'] as String,
    issuedAt:          j['issued_at'] as String? ?? '',
    pdfPath:           j['pdf_path'] as String?,
    course:            ELearningCourse.fromJson(j['course'] as Map<String, dynamic>),
  );
}

class ELearningNote {
  final int id;
  final String note;
  final int timestampSeconds;
  final String createdAt;

  const ELearningNote({
    required this.id,
    required this.note,
    required this.timestampSeconds,
    required this.createdAt,
  });

  factory ELearningNote.fromJson(Map<String, dynamic> j) => ELearningNote(
    id:               j['id'] as int,
    note:             j['note'] as String,
    timestampSeconds: (j['timestamp_seconds'] as num?)?.toInt() ?? 0,
    createdAt:        j['created_at'] as String? ?? '',
  );
}

class QuizQuestion {
  final int id;
  final String question;
  final String type;
  final List<String> options;

  const QuizQuestion({
    required this.id,
    required this.question,
    required this.type,
    required this.options,
  });

  factory QuizQuestion.fromJson(Map<String, dynamic> j) => QuizQuestion(
    id:       j['id'] as int,
    question: j['question'] as String,
    type:     j['type'] as String? ?? 'mcq',
    options:  (j['options'] as List<dynamic>? ?? []).map((o) => o.toString()).toList(),
  );
}

class QuizData {
  final int quizId;
  final String title;
  final String? description;
  final int passScore;
  final int? timeLimitMinutes;
  final int attemptNumber;
  final List<QuizQuestion> questions;

  const QuizData({
    required this.quizId,
    required this.title,
    this.description,
    required this.passScore,
    this.timeLimitMinutes,
    required this.attemptNumber,
    required this.questions,
  });

  factory QuizData.fromJson(Map<String, dynamic> j) => QuizData(
    quizId:           j['quiz_id'] as int,
    title:            j['title'] as String,
    description:      j['description'] as String?,
    passScore:        (j['pass_score'] as num?)?.toInt() ?? 70,
    timeLimitMinutes: (j['time_limit_minutes'] as num?)?.toInt(),
    attemptNumber:    (j['attempt_number'] as num?)?.toInt() ?? 1,
    questions:        (j['questions'] as List<dynamic>? ?? [])
                          .map((q) => QuizQuestion.fromJson(q as Map<String, dynamic>))
                          .toList(),
  );
}

class QuizResult {
  final double score;
  final bool passed;
  final int passScore;
  final int correctAnswers;
  final int totalQuestions;
  final int attemptsUsed;
  final int attemptsMax;
  final List<Map<String, dynamic>> answers;

  const QuizResult({
    required this.score,
    required this.passed,
    required this.passScore,
    required this.correctAnswers,
    required this.totalQuestions,
    required this.attemptsUsed,
    required this.attemptsMax,
    required this.answers,
  });

  factory QuizResult.fromJson(Map<String, dynamic> j) => QuizResult(
    score:           (j['score'] as num?)?.toDouble() ?? 0,
    passed:          j['passed'] == true,
    passScore:       (j['pass_score'] as num?)?.toInt() ?? 70,
    correctAnswers:  (j['correct_answers'] as num?)?.toInt() ?? 0,
    totalQuestions:  (j['total_questions'] as num?)?.toInt() ?? 0,
    attemptsUsed:    (j['attempts_used'] as num?)?.toInt() ?? 1,
    attemptsMax:     (j['attempts_max'] as num?)?.toInt() ?? 3,
    answers:         (j['answers'] as List<dynamic>? ?? [])
                         .map((a) => a as Map<String, dynamic>)
                         .toList(),
  );
}
