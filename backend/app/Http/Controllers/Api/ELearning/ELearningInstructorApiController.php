<?php
namespace App\Http\Controllers\Api\ELearning;

use App\Http\Controllers\Controller;
use App\Models\ELearningCourse;
use App\Models\ELearningSection;
use App\Models\ELearningLesson;
use App\Models\ELearningEnrollment;
use App\Models\ELearningInstructor;
use App\Models\ELearningInstructorEarning;
use App\Models\ELearningWithdrawal;
use App\Models\ELearningQuiz;
use App\Models\ELearningQuizQuestion;
use App\Models\ELearningAssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ELearningInstructorApiController extends Controller
{
    private function getInstructor(): ?ELearningInstructor
    {
        return auth()->user()->elInstructor;
    }

    private function requireInstructor(): ELearningInstructor
    {
        $instructor = $this->getInstructor();
        if (!$instructor || $instructor->verification_status !== 'approved') {
            abort(403, 'You must be an approved instructor to access this.');
        }
        return $instructor;
    }

    /**
     * Return the current user's instructor status.
     * none = never applied, pending/approved/rejected = application state.
     */
    public function status()
    {
        $instructor = $this->getInstructor();

        if (!$instructor) {
            return response()->json([
                'status' => 'success',
                'data'   => ['application_status' => 'none', 'instructor' => null],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'application_status' => $instructor->verification_status,
                'instructor' => [
                    'id'               => $instructor->id,
                    'bio'              => $instructor->bio,
                    'expertise'        => $instructor->expertise,
                    'qualifications'   => $instructor->qualifications,
                    'experience_years' => $instructor->experience_years,
                    'profile_photo'    => $instructor->profile_photo ? asset('storage/' . $instructor->profile_photo) : null,
                    'rating'           => $instructor->rating,
                    'total_courses'    => $instructor->total_courses,
                    'total_students'   => $instructor->total_students,
                    'is_active'        => $instructor->is_active,
                ],
            ],
        ]);
    }

    /**
     * Apply to become an instructor. Creates a pending record for admin review.
     */
    public function apply(Request $request)
    {
        $existing = $this->getInstructor();
        if ($existing) {
            if ($existing->verification_status === 'rejected') {
                // allow re-apply: reset to pending with new details
            } else {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'You already have an instructor application ('
                        . $existing->verification_status . ').',
                ], 422);
            }
        }

        $data = $request->validate([
            'bio'              => 'required|string|max:2000',
            'expertise'        => 'required|string|max:255',
            'qualifications'   => 'nullable|string|max:2000',
            'experience_years' => 'nullable|integer|min:0|max:80',
            'social_links'     => 'nullable|array',
            'profile_photo'    => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $request->file('profile_photo')->store('elearning/instructors', 'public');
        }

        $data['verification_status'] = 'pending';
        $data['is_active']           = false;

        if ($existing) {
            $existing->update($data);
            $instructor = $existing;
        } else {
            $data['user_id'] = auth()->id();
            $instructor = ELearningInstructor::create($data);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Application submitted! Admin will review within 24 hours.',
            'data'    => ['application_status' => 'pending', 'id' => $instructor->id],
        ], 201);
    }

    public function dashboard()
    {
        $instructor = $this->requireInstructor();

        $totalStudents  = ELearningEnrollment::whereHas('course', fn($q) => $q->where('instructor_id', $instructor->id))->count();
        $totalCourses   = ELearningCourse::where('instructor_id', $instructor->id)->count();
        $totalEarnings  = ELearningInstructorEarning::where('instructor_id', $instructor->id)->sum('net_amount');
        $pendingEarnings= ELearningInstructorEarning::where('instructor_id', $instructor->id)->where('status', 'pending')->sum('net_amount');

        $recentEnrollments = ELearningEnrollment::with(['user', 'course'])
            ->whereHas('course', fn($q) => $q->where('instructor_id', $instructor->id))
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn($e) => [
                'user'       => $e->user->name,
                'course'     => $e->course->title,
                'amount'     => $e->amount_paid,
                'enrolled_at'=> $e->created_at->toDateString(),
            ]);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'total_students'  => $totalStudents,
                'total_courses'   => $totalCourses,
                'total_earnings'  => $totalEarnings,
                'pending_earnings'=> $pendingEarnings,
                'rating'          => $instructor->rating,
                'recent_enrollments' => $recentEnrollments,
            ],
        ]);
    }

    public function myCourses()
    {
        $instructor = $this->requireInstructor();
        $courses = ELearningCourse::with('category')
            ->where('instructor_id', $instructor->id)
            ->latest()
            ->get()
            ->map(fn($c) => [
                'id'             => $c->id,
                'title'          => $c->title,
                'slug'           => $c->slug,
                'thumbnail'      => $c->thumbnail_url,
                'status'         => $c->status,
                'price'          => $c->price,
                'total_students' => $c->total_students,
                'rating'         => $c->rating,
                'total_lessons'  => $c->total_lessons,
                'category'       => $c->category?->name,
                'created_at'     => $c->created_at->toDateString(),
            ]);

        return response()->json(['status' => 'success', 'data' => $courses]);
    }

    /**
     * Return a single course owned by the instructor with its sections + lessons,
     * for the in-app course builder (works for draft/pending courses too).
     */
    public function courseStructure(int $id)
    {
        $instructor = $this->requireInstructor();
        $course = ELearningCourse::with(['sections.lessons' => fn($q) => $q->orderBy('sort_order')])
            ->where('id', $id)
            ->where('instructor_id', $instructor->id)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id'            => $course->id,
                'title'         => $course->title,
                'slug'          => $course->slug,
                'status'        => $course->status,
                'thumbnail'     => $course->thumbnail_url,
                'price'         => $course->price,
                'is_free'       => $course->is_free,
                'total_lessons' => $course->total_lessons,
                'sections'      => $course->sections->sortBy('sort_order')->values()->map(fn($s) => [
                    'id'      => $s->id,
                    'title'   => $s->title,
                    'lessons' => $s->lessons->map(fn($l) => [
                        'id'                     => $l->id,
                        'title'                  => $l->title,
                        'type'                   => $l->type,
                        'video_duration_seconds' => $l->video_duration_seconds,
                        'is_free_preview'        => $l->is_free_preview,
                    ]),
                ]),
            ],
        ]);
    }

    public function createCourse(Request $request)
    {
        $instructor = $this->requireInstructor();

        $data = $request->validate([
            'title'             => 'required|string|max:255',
            'subtitle'          => 'nullable|string|max:500',
            'description'       => 'required|string',
            'category_id'       => 'required|integer|exists:el_categories,id',
            'level'             => 'required|in:beginner,intermediate,advanced,all',
            'language'          => 'required|string|max:10',
            'price'             => 'required|numeric|min:0',
            'discount_price'    => 'nullable|numeric|min:0',
            'is_free'           => 'boolean',
            'learning_outcomes' => 'nullable|array',
            'requirements'      => 'nullable|array',
            'target_audience'   => 'nullable|string',
            'tags'              => 'nullable|array',
        ]);

        $data['instructor_id'] = $instructor->id;
        $data['slug']          = Str::slug($data['title']) . '-' . Str::random(6);
        $data['status']        = 'draft';

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('elearning/thumbnails', 'public');
        }

        $course = ELearningCourse::create($data);
        $instructor->increment('total_courses');

        return response()->json(['status' => 'success', 'message' => 'Course created', 'data' => ['id' => $course->id, 'slug' => $course->slug]], 201);
    }

    public function updateCourse(Request $request, int $id)
    {
        $instructor = $this->requireInstructor();
        $course = ELearningCourse::where('id', $id)->where('instructor_id', $instructor->id)->firstOrFail();

        $data = $request->validate([
            'title'             => 'sometimes|string|max:255',
            'subtitle'          => 'nullable|string|max:500',
            'description'       => 'sometimes|string',
            'category_id'       => 'sometimes|integer|exists:el_categories,id',
            'level'             => 'sometimes|in:beginner,intermediate,advanced,all',
            'price'             => 'sometimes|numeric|min:0',
            'discount_price'    => 'nullable|numeric|min:0',
            'is_free'           => 'boolean',
            'status'            => 'sometimes|in:draft,pending',
            'learning_outcomes' => 'nullable|array',
            'requirements'      => 'nullable|array',
            'target_audience'   => 'nullable|string',
            'tags'              => 'nullable|array',
        ]);

        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail
            if ($course->thumbnail) {
                $oldPath = str_replace(asset('storage') . '/', '', $course->thumbnail);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('thumbnail')->store('elearning/thumbnails', 'public');
            $data['thumbnail'] = asset('storage/' . $path);
        }

        // Recalculate effective_price
        if (isset($data['is_free']) && $data['is_free']) {
            $data['price'] = 0;
            $data['discount_price'] = null;
        }

        $course->update($data);

        return response()->json(['status' => 'success', 'message' => 'Course updated']);
    }

    public function deleteCourse(int $id)
    {
        $instructor = $this->requireInstructor();
        $course = ELearningCourse::where('id', $id)->where('instructor_id', $instructor->id)->firstOrFail();

        if ($course->status === 'published') {
            return response()->json(['status' => 'error', 'message' => 'Published courses cannot be deleted. Archive it first.'], 422);
        }

        // Delete thumbnail
        if ($course->thumbnail) {
            $path = str_replace(asset('storage') . '/', '', $course->thumbnail);
            \Storage::disk('public')->delete($path);
        }

        $course->delete();
        return response()->json(['status' => 'success', 'message' => 'Course deleted']);
    }

    public function addSection(Request $request)
    {
        $instructor = $this->requireInstructor();
        $request->validate([
            'course_id'  => 'required|integer|exists:el_courses,id',
            'title'      => 'required|string|max:255',
            'sort_order' => 'nullable|integer',
        ]);

        $course = ELearningCourse::where('id', $request->course_id)
            ->where('instructor_id', $instructor->id)
            ->firstOrFail();

        $section = ELearningSection::create([
            'course_id'  => $course->id,
            'title'      => $request->title,
            'sort_order' => $request->get('sort_order', 0),
        ]);
        $course->increment('total_sections');

        return response()->json(['status' => 'success', 'data' => $section], 201);
    }

    public function addLesson(Request $request)
    {
        $instructor = $this->requireInstructor();
        $request->validate([
            'section_id'              => 'required|integer|exists:el_sections,id',
            'title'                   => 'required|string|max:255',
            'type'                    => 'required|in:video,pdf,document,quiz,assignment,live',
            'video_url'               => 'nullable|string',
            'video_file'              => 'nullable|file|mimes:mp4,mov,avi,webm|max:512000',
            'video_duration_seconds'  => 'nullable|integer|min:0',
            'content'                 => 'nullable|string',
            'is_free_preview'         => 'boolean',
            'sort_order'              => 'nullable|integer',
        ]);

        $section = ELearningSection::findOrFail($request->section_id);
        $course  = ELearningCourse::where('id', $section->course_id)
            ->where('instructor_id', $instructor->id)
            ->firstOrFail();

        $lessonData = $request->only(['section_id','title','type','video_url','video_duration_seconds','content','is_free_preview','sort_order']);
        $lessonData['course_id'] = $course->id;

        if ($request->hasFile('video_file')) {
            $path = $request->file('video_file')->store('elearning/videos', 'public');
            $lessonData['video_url'] = asset('storage/' . $path);
        } elseif ($request->hasFile('file')) {
            $lessonData['file_path'] = $request->file('file')->store('elearning/lessons', 'public');
        }

        $lesson = ELearningLesson::create($lessonData);
        $course->increment('total_lessons');

        // Recalculate duration
        $totalSeconds = ELearningLesson::where('course_id', $course->id)->sum('video_duration_seconds');
        $course->update(['duration_hours' => round($totalSeconds / 3600, 2)]);

        return response()->json(['status' => 'success', 'data' => $lesson], 201);
    }

    public function students()
    {
        $instructor = $this->requireInstructor();
        $enrollments = ELearningEnrollment::with(['user', 'course'])
            ->whereHas('course', fn($q) => $q->where('instructor_id', $instructor->id))
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $enrollments->map(fn($e) => [
                'id'          => $e->id,
                'user'        => ['id' => $e->user->id, 'name' => $e->user->name, 'email' => $e->user->email],
                'course'      => ['id' => $e->course->id, 'title' => $e->course->title],
                'amount_paid' => $e->amount_paid,
                'status'      => $e->status,
                'enrolled_at' => $e->created_at->toDateString(),
            ]),
            'meta' => ['current_page' => $enrollments->currentPage(), 'last_page' => $enrollments->lastPage(), 'total' => $enrollments->total()],
        ]);
    }

    public function earnings()
    {
        $instructor = $this->requireInstructor();
        $earnings = ELearningInstructorEarning::with(['enrollment.course'])
            ->where('instructor_id', $instructor->id)
            ->latest()
            ->paginate(20);

        $totalNet     = ELearningInstructorEarning::where('instructor_id', $instructor->id)->sum('net_amount');
        $pendingNet   = ELearningInstructorEarning::where('instructor_id', $instructor->id)->where('status', 'pending')->sum('net_amount');
        $releasedNet  = ELearningInstructorEarning::where('instructor_id', $instructor->id)->where('status', 'released')->sum('net_amount');

        return response()->json([
            'status' => 'success',
            'summary'=> ['total' => $totalNet, 'pending' => $pendingNet, 'released' => $releasedNet],
            'data'   => $earnings->map(fn($e) => [
                'id'                => $e->id,
                'course'            => $e->enrollment?->course?->title,
                'amount'            => $e->amount,
                'commission_amount' => $e->commission_amount,
                'net_amount'        => $e->net_amount,
                'status'            => $e->status,
                'released_at'       => $e->released_at?->toDateString(),
                'created_at'        => $e->created_at->toDateString(),
            ]),
            'meta' => ['current_page' => $earnings->currentPage(), 'last_page' => $earnings->lastPage()],
        ]);
    }

    public function requestWithdrawal(Request $request)
    {
        $instructor = $this->requireInstructor();
        $request->validate(['amount' => 'required|numeric|min:10']);

        $available = ELearningInstructorEarning::where('instructor_id', $instructor->id)
            ->where('status', 'released')
            ->sum('net_amount');

        $pendingWithdrawals = ELearningWithdrawal::where('instructor_id', $instructor->id)
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount');

        $withdrawable = $available - $pendingWithdrawals;

        if ($request->amount > $withdrawable) {
            return response()->json(['status' => 'error', 'message' => "Only $withdrawable available for withdrawal"], 422);
        }

        $withdrawal = ELearningWithdrawal::create([
            'instructor_id' => $instructor->id,
            'amount'        => $request->amount,
            'status'        => 'pending',
        ]);

        return response()->json(['status' => 'success', 'message' => 'Withdrawal request submitted', 'data' => $withdrawal], 201);
    }

    public function createQuiz(Request $request)
    {
        $instructor = $this->requireInstructor();
        $request->validate([
            'lesson_id'          => 'required|integer|exists:el_lessons,id',
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'pass_score'         => 'required|integer|min:1|max:100',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'max_attempts'       => 'required|integer|min:1',
        ]);

        $lesson = ELearningLesson::with('course')->findOrFail($request->lesson_id);
        if ($lesson->course->instructor_id !== $instructor->id) abort(403);

        $quiz = ELearningQuiz::create($request->only(['lesson_id','title','description','pass_score','time_limit_minutes','max_attempts']));

        return response()->json(['status' => 'success', 'data' => $quiz], 201);
    }

    public function addQuestion(Request $request)
    {
        $instructor = $this->requireInstructor();
        $request->validate([
            'quiz_id'        => 'required|integer|exists:el_quizzes,id',
            'question'       => 'required|string',
            'type'           => 'required|in:mcq,true_false,short_answer',
            'options'        => 'nullable|array',
            'correct_answer' => 'required|string',
            'explanation'    => 'nullable|string',
            'sort_order'     => 'nullable|integer',
        ]);

        $quiz = ELearningQuiz::with('lesson.course')->findOrFail($request->quiz_id);
        if ($quiz->lesson->course->instructor_id !== $instructor->id) abort(403);

        $question = ELearningQuizQuestion::create($request->only(['quiz_id','question','type','options','correct_answer','explanation','sort_order']));

        return response()->json(['status' => 'success', 'data' => $question], 201);
    }

    public function reviewSubmissions()
    {
        $instructor = $this->requireInstructor();
        $submissions = ELearningAssignmentSubmission::with(['user', 'assignment.lesson.course'])
            ->whereHas('assignment.lesson.course', fn($q) => $q->where('instructor_id', $instructor->id))
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $submissions->map(fn($s) => [
                'id'           => $s->id,
                'user'         => $s->user->name,
                'assignment'   => $s->assignment->title,
                'course'       => $s->assignment->lesson->course->title,
                'status'       => $s->status,
                'grade'        => $s->grade,
                'submitted_at' => $s->submitted_at->toDateString(),
                'text_answer'  => $s->text_answer,
                'file_path'    => $s->file_path ? asset('storage/' . $s->file_path) : null,
            ]),
            'meta' => ['current_page' => $submissions->currentPage(), 'last_page' => $submissions->lastPage()],
        ]);
    }

    public function gradeSubmission(Request $request, int $id)
    {
        $instructor = $this->requireInstructor();
        $submission = ELearningAssignmentSubmission::with('assignment.lesson.course')->findOrFail($id);

        if ($submission->assignment->lesson->course->instructor_id !== $instructor->id) abort(403);

        $request->validate([
            'grade'    => 'required|integer|min:0|max:' . $submission->assignment->max_score,
            'feedback' => 'nullable|string|max:1000',
        ]);

        $submission->update([
            'grade'      => $request->grade,
            'feedback'   => $request->feedback,
            'status'     => 'graded',
            'graded_at'  => now(),
        ]);

        return response()->json(['status' => 'success', 'message' => 'Submission graded']);
    }
}
