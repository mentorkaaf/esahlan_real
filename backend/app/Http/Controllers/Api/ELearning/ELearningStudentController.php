<?php
namespace App\Http\Controllers\Api\ELearning;

use App\Http\Controllers\Controller;
use App\Models\ELearningCourse;
use App\Models\ELearningEnrollment;
use App\Models\ELearningLesson;
use App\Models\ELearningLessonProgress;
use App\Models\ELearningCertificate;
use App\Models\ELearningWishlist;
use App\Models\ELearningReview;
use App\Models\ELearningNote;
use App\Models\ELearningQuiz;
use App\Models\ELearningQuizAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ELearningStudentController extends Controller
{
    public function myLearning()
    {
        $userId = auth()->id();
        $enrollments = ELearningEnrollment::with(['course.instructor.user', 'course.category'])
            ->where('user_id', $userId)
            ->whereIn('status', ['active', 'completed'])
            ->latest()
            ->get();

        $data = $enrollments->map(function ($e) use ($userId) {
            $course = $e->course;
            $totalLessons = $course->total_lessons;
            $completedLessons = ELearningLessonProgress::where('user_id', $userId)
                ->where('enrollment_id', $e->id)
                ->where('is_completed', true)
                ->count();
            $progress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;

            return [
                'enrollment_id'    => $e->id,
                'status'           => $e->status,
                'enrolled_at'      => $e->created_at->toDateString(),
                'completed_at'     => $e->completed_at?->toDateString(),
                'progress_percent' => $progress,
                'completed_lessons'=> $completedLessons,
                'total_lessons'    => $totalLessons,
                'course'           => [
                    'id'         => $course->id,
                    'title'      => $course->title,
                    'slug'       => $course->slug,
                    'thumbnail'  => $course->thumbnail_url,
                    'instructor' => $course->instructor?->user->name,
                    'level'      => $course->level,
                    'duration_hours' => $course->duration_hours,
                ],
            ];
        });

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function enroll(Request $request)
    {
        $request->validate(['course_id' => 'required|integer|exists:el_courses,id']);
        $userId = auth()->id();
        $course = ELearningCourse::where('id', $request->course_id)
            ->where('status', 'published')
            ->firstOrFail();

        $existing = ELearningEnrollment::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->first();
        if ($existing) {
            return response()->json(['status' => 'error', 'message' => 'Already enrolled in this course'], 422);
        }

        if (!$course->is_free) {
            return response()->json(['status' => 'error', 'message' => 'Please purchase this course first'], 422);
        }

        $enrollment = ELearningEnrollment::create([
            'user_id'    => $userId,
            'course_id'  => $course->id,
            'amount_paid'=> 0,
            'status'     => 'active',
        ]);

        $course->increment('total_students');

        return response()->json(['status' => 'success', 'message' => 'Enrolled successfully', 'data' => ['enrollment_id' => $enrollment->id]]);
    }

    public function lessonProgress(Request $request)
    {
        $request->validate([
            'lesson_id'            => 'required|integer|exists:el_lessons,id',
            'watch_seconds'        => 'required|integer|min:0',
            'last_position_seconds'=> 'required|integer|min:0',
        ]);

        $userId = auth()->id();
        $lesson = ELearningLesson::findOrFail($request->lesson_id);

        $enrollment = ELearningEnrollment::where('user_id', $userId)
            ->where('course_id', $lesson->course_id)
            ->where('status', 'active')
            ->first();

        if (!$enrollment && !$lesson->is_free_preview) {
            return response()->json(['status' => 'error', 'message' => 'Not enrolled in this course'], 403);
        }

        ELearningLessonProgress::updateOrCreate(
            ['user_id' => $userId, 'lesson_id' => $lesson->id],
            [
                'enrollment_id'         => $enrollment?->id,
                'watch_seconds'         => $request->watch_seconds,
                'last_position_seconds' => $request->last_position_seconds,
            ]
        );

        return response()->json(['status' => 'success', 'message' => 'Progress saved']);
    }

    public function completeLesson(Request $request)
    {
        $request->validate(['lesson_id' => 'required|integer|exists:el_lessons,id']);
        $userId = auth()->id();
        $lesson = ELearningLesson::findOrFail($request->lesson_id);

        $enrollment = ELearningEnrollment::where('user_id', $userId)
            ->where('course_id', $lesson->course_id)
            ->where('status', 'active')
            ->firstOrFail();

        ELearningLessonProgress::updateOrCreate(
            ['user_id' => $userId, 'lesson_id' => $lesson->id],
            [
                'enrollment_id' => $enrollment->id,
                'is_completed'  => true,
                'completed_at'  => now(),
            ]
        );

        // Check if course is completed
        $totalLessons = $lesson->course->total_lessons;
        $completedLessons = ELearningLessonProgress::where('user_id', $userId)
            ->where('enrollment_id', $enrollment->id)
            ->where('is_completed', true)
            ->count();

        $certificate = null;
        if ($totalLessons > 0 && $completedLessons >= $totalLessons) {
            $enrollment->update(['status' => 'completed', 'completed_at' => now()]);

            // Generate certificate
            $cert = ELearningCertificate::firstOrCreate(
                ['user_id' => $userId, 'course_id' => $lesson->course_id],
                [
                    'enrollment_id'       => $enrollment->id,
                    'certificate_number'  => 'ES-EL-' . date('Y') . '-' . str_pad(rand(1, 9999999), 7, '0', STR_PAD_LEFT),
                    'issued_at'           => now(),
                ]
            );
            $certificate = $cert->certificate_number;
        }

        return response()->json([
            'status'      => 'success',
            'message'     => 'Lesson completed',
            'course_done' => $certificate !== null,
            'certificate_number' => $certificate,
            'progress_percent' => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0,
        ]);
    }

    public function myCertificates()
    {
        $certs = ELearningCertificate::with('course')
            ->where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(fn($c) => [
                'id'                 => $c->id,
                'certificate_number' => $c->certificate_number,
                'issued_at'          => $c->issued_at->toDateString(),
                'pdf_path'           => $c->pdf_path ? asset('storage/' . $c->pdf_path) : null,
                'course'             => [
                    'id'        => $c->course->id,
                    'title'     => $c->course->title,
                    'thumbnail' => $c->course->thumbnail_url,
                ],
            ]);

        return response()->json(['status' => 'success', 'data' => $certs]);
    }

    public function wishlist()
    {
        $items = ELearningWishlist::with(['course.instructor.user'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(fn($w) => [
                'id'     => $w->id,
                'course' => [
                    'id'             => $w->course->id,
                    'title'          => $w->course->title,
                    'slug'           => $w->course->slug,
                    'thumbnail'      => $w->course->thumbnail_url,
                    'price'          => $w->course->price,
                    'discount_price' => $w->course->discount_price,
                    'rating'         => $w->course->rating,
                    'instructor'     => $w->course->instructor?->user->name,
                ],
            ]);

        return response()->json(['status' => 'success', 'data' => $items]);
    }

    public function toggleWishlist(Request $request)
    {
        $request->validate(['course_id' => 'required|integer|exists:el_courses,id']);
        $userId = auth()->id();

        $existing = ELearningWishlist::where('user_id', $userId)
            ->where('course_id', $request->course_id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['status' => 'success', 'in_wishlist' => false]);
        }

        ELearningWishlist::create(['user_id' => $userId, 'course_id' => $request->course_id]);
        return response()->json(['status' => 'success', 'in_wishlist' => true]);
    }

    public function submitReview(Request $request)
    {
        $request->validate([
            'course_id' => 'required|integer|exists:el_courses,id',
            'rating'    => 'required|integer|min:1|max:5',
            'comment'   => 'nullable|string|max:1000',
        ]);

        $userId = auth()->id();

        // Must be enrolled and completed
        $enrollment = ELearningEnrollment::where('user_id', $userId)
            ->where('course_id', $request->course_id)
            ->where('status', 'completed')
            ->first();

        if (!$enrollment) {
            return response()->json(['status' => 'error', 'message' => 'You must complete the course before reviewing'], 422);
        }

        $review = ELearningReview::updateOrCreate(
            ['user_id' => $userId, 'course_id' => $request->course_id],
            ['rating' => $request->rating, 'comment' => $request->comment]
        );

        // Recalculate course rating
        $course = ELearningCourse::find($request->course_id);
        $avg = ELearningReview::where('course_id', $course->id)->avg('rating');
        $count = ELearningReview::where('course_id', $course->id)->count();
        $course->update(['rating' => round($avg, 2), 'total_reviews' => $count]);

        return response()->json(['status' => 'success', 'message' => 'Review submitted', 'data' => $review]);
    }

    public function notes(Request $request)
    {
        $request->validate(['lesson_id' => 'required|integer|exists:el_lessons,id']);
        $notes = ELearningNote::where('user_id', auth()->id())
            ->where('lesson_id', $request->lesson_id)
            ->orderBy('timestamp_seconds')
            ->get();

        return response()->json(['status' => 'success', 'data' => $notes]);
    }

    public function saveNote(Request $request)
    {
        $request->validate([
            'lesson_id'         => 'required|integer|exists:el_lessons,id',
            'note'              => 'required|string|max:2000',
            'timestamp_seconds' => 'required|integer|min:0',
        ]);

        $note = ELearningNote::create([
            'user_id'           => auth()->id(),
            'lesson_id'         => $request->lesson_id,
            'note'              => $request->note,
            'timestamp_seconds' => $request->timestamp_seconds,
        ]);

        return response()->json(['status' => 'success', 'data' => $note]);
    }

    public function quizStart(int $quizId)
    {
        $quiz = ELearningQuiz::with('questions')->findOrFail($quizId);
        $userId = auth()->id();

        // Check enrollment
        $enrollment = ELearningEnrollment::where('user_id', $userId)
            ->where('course_id', $quiz->lesson->course_id)
            ->where('status', 'active')
            ->first();
        if (!$enrollment) {
            return response()->json(['status' => 'error', 'message' => 'Not enrolled'], 403);
        }

        $attemptCount = ELearningQuizAttempt::where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->count();

        if ($attemptCount >= $quiz->max_attempts) {
            return response()->json(['status' => 'error', 'message' => 'Maximum attempts reached'], 422);
        }

        // Return questions without answers
        $questions = $quiz->questions->map(fn($q) => [
            'id'      => $q->id,
            'question'=> $q->question,
            'type'    => $q->type,
            'options' => $q->options,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'quiz_id'            => $quiz->id,
                'title'              => $quiz->title,
                'description'        => $quiz->description,
                'pass_score'         => $quiz->pass_score,
                'time_limit_minutes' => $quiz->time_limit_minutes,
                'attempt_number'     => $attemptCount + 1,
                'questions'          => $questions,
            ],
        ]);
    }

    public function quizSubmit(Request $request)
    {
        $request->validate([
            'quiz_id'           => 'required|integer|exists:el_quizzes,id',
            'answers'           => 'required|array',
            'time_taken_seconds'=> 'required|integer|min:0',
        ]);

        $userId = auth()->id();
        $quiz = ELearningQuiz::with('questions')->findOrFail($request->quiz_id);

        $attemptCount = ELearningQuizAttempt::where('user_id', $userId)
            ->where('quiz_id', $quiz->id)
            ->count();

        if ($attemptCount >= $quiz->max_attempts) {
            return response()->json(['status' => 'error', 'message' => 'Maximum attempts reached'], 422);
        }

        // Calculate score
        $total = $quiz->questions->count();
        $correct = 0;
        $answersWithFeedback = [];

        foreach ($quiz->questions as $question) {
            $userAnswer = $request->answers[$question->id] ?? null;
            $isCorrect = strtolower(trim($userAnswer ?? '')) === strtolower(trim($question->correct_answer));
            if ($isCorrect) $correct++;
            $answersWithFeedback[] = [
                'question_id'    => $question->id,
                'user_answer'    => $userAnswer,
                'correct_answer' => $question->correct_answer,
                'is_correct'     => $isCorrect,
                'explanation'    => $question->explanation,
            ];
        }

        $score  = $total > 0 ? round(($correct / $total) * 100, 2) : 0;
        $passed = $score >= $quiz->pass_score;

        ELearningQuizAttempt::create([
            'user_id'            => $userId,
            'quiz_id'            => $quiz->id,
            'answers'            => $request->answers,
            'score'              => $score,
            'passed'             => $passed,
            'time_taken_seconds' => $request->time_taken_seconds,
            'attempt_number'     => $attemptCount + 1,
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'score'           => $score,
                'passed'          => $passed,
                'pass_score'      => $quiz->pass_score,
                'correct_answers' => $correct,
                'total_questions' => $total,
                'answers'         => $answersWithFeedback,
                'attempts_used'   => $attemptCount + 1,
                'attempts_max'    => $quiz->max_attempts,
            ],
        ]);
    }
}
