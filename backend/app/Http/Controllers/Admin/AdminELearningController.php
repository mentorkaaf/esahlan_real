<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ELearningCategory;
use App\Models\ELearningCourse;
use App\Models\ELearningInstructor;
use App\Models\ELearningEnrollment;
use App\Models\ELearningCertificate;
use App\Models\ELearningWithdrawal;
use App\Models\ELearningReview;
use App\Models\ELearningInstructorEarning;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminELearningController extends Controller
{
    // ── Dashboard ─────────────────────────────────────────────────────────────
    public function dashboard()
    {
        $stats = [
            'total_instructors' => ELearningInstructor::where('verification_status', 'approved')->count(),
            'pending_instructors'=> ELearningInstructor::where('verification_status', 'pending')->count(),
            'total_courses'     => ELearningCourse::where('status', 'published')->count(),
            'pending_courses'   => ELearningCourse::where('status', 'pending')->count(),
            'total_students'    => ELearningEnrollment::distinct('user_id')->count('user_id'),
            'total_enrollments' => ELearningEnrollment::count(),
            'total_revenue'     => ELearningEnrollment::sum('amount_paid'),
            'total_certificates'=> ELearningCertificate::count(),
            'pending_withdrawals'=> ELearningWithdrawal::where('status', 'pending')->count(),
        ];

        $recentEnrollments = ELearningEnrollment::with(['user', 'course'])
            ->latest()
            ->limit(10)
            ->get();

        $topCourses = ELearningCourse::where('status', 'published')
            ->orderByDesc('total_students')
            ->limit(5)
            ->with('instructor.user')
            ->get();

        $monthlyRevenue = ELearningEnrollment::selectRaw('MONTH(created_at) as month, YEAR(created_at) as year, SUM(amount_paid) as total')
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at), MONTH(created_at)')
            ->get();

        return view('admin.elearning.dashboard', compact('stats', 'recentEnrollments', 'topCourses', 'monthlyRevenue'));
    }

    // ── Instructors ───────────────────────────────────────────────────────────
    public function instructors()
    {
        $filter = request('filter', 'all');
        $search = request('search');

        $query = ELearningInstructor::with('user')->latest();

        if ($filter !== 'all') $query->where('verification_status', $filter);
        if ($search) {
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%$search%")->orWhere('email', 'like', "%$search%"));
        }

        $instructors = $query->paginate(20)->withQueryString();

        return view('admin.elearning.instructors', compact('instructors', 'filter', 'search'));
    }

    public function instructorDetail(int $id)
    {
        $instructor = ELearningInstructor::with(['user', 'courses'])->findOrFail($id);
        return view('admin.elearning.instructors_show', compact('instructor'));
    }

    public function approveInstructor(int $id)
    {
        $instructor = ELearningInstructor::findOrFail($id);
        $instructor->update(['verification_status' => 'approved', 'is_active' => true]);
        return back()->with('success', 'Instructor approved successfully.');
    }

    public function rejectInstructor(int $id)
    {
        $instructor = ELearningInstructor::findOrFail($id);
        $instructor->update(['verification_status' => 'rejected', 'is_active' => false]);
        return back()->with('success', 'Instructor rejected.');
    }

    // ── Courses ───────────────────────────────────────────────────────────────
    public function courses()
    {
        $filter = request('filter', 'all');
        $search = request('search');

        $query = ELearningCourse::with(['instructor.user', 'category'])->latest();

        if ($filter !== 'all') $query->where('status', $filter);
        if ($search) $query->where('title', 'like', "%$search%");

        $courses = $query->paginate(20)->withQueryString();

        return view('admin.elearning.courses', compact('courses', 'filter', 'search'));
    }

    public function courseDetail(int $id)
    {
        $course = ELearningCourse::with(['instructor.user', 'category', 'sections.lessons'])->findOrFail($id);
        return view('admin.elearning.courses_show', compact('course'));
    }

    public function approveCourse(int $id)
    {
        $course = ELearningCourse::findOrFail($id);
        $course->update(['status' => 'published']);
        return back()->with('success', 'Course approved and published.');
    }

    public function rejectCourse(int $id)
    {
        $course = ELearningCourse::findOrFail($id);
        $course->update(['status' => 'rejected']);
        return back()->with('success', 'Course rejected.');
    }

    // ── Categories ────────────────────────────────────────────────────────────
    public function categories()
    {
        $categories = ELearningCategory::withCount('courses')->orderBy('sort_order')->get();
        return view('admin.elearning.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'icon'        => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'parent_id'   => 'nullable|integer|exists:el_categories,id',
            'sort_order'  => 'nullable|integer',
        ]);
        $data['slug'] = Str::slug($data['name']) . '-' . Str::random(4);
        ELearningCategory::create($data);
        return back()->with('success', 'Category created.');
    }

    public function updateCategory(Request $request, int $id)
    {
        $category = ELearningCategory::findOrFail($id);
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'icon'        => 'nullable|string|max:100',
            'description' => 'nullable|string|max:500',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'boolean',
        ]);
        $category->update($data);
        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(int $id)
    {
        $category = ELearningCategory::findOrFail($id);
        if ($category->courses()->count() > 0) {
            return back()->with('error', 'Cannot delete category with courses.');
        }
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }

    // ── Students ──────────────────────────────────────────────────────────────
    public function students()
    {
        $search = request('search');
        $query  = ELearningEnrollment::with(['user', 'course'])->latest();
        if ($search) {
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%$search%")->orWhere('email', 'like', "%$search%"));
        }
        $enrollments = $query->paginate(25)->withQueryString();
        return view('admin.elearning.students', compact('enrollments', 'search'));
    }

    // ── Certificates ──────────────────────────────────────────────────────────
    public function certificates()
    {
        $certs = ELearningCertificate::with(['user', 'course'])->latest()->paginate(25);
        return view('admin.elearning.certificates', compact('certs'));
    }

    public function revokeCertificate(int $id)
    {
        ELearningCertificate::findOrFail($id)->delete();
        return back()->with('success', 'Certificate revoked.');
    }

    // ── Withdrawals ───────────────────────────────────────────────────────────
    public function withdrawals()
    {
        $filter      = request('filter', 'all');
        $withdrawals = ELearningWithdrawal::with('instructor.user')
            ->when($filter !== 'all', fn($q) => $q->where('status', $filter))
            ->latest()
            ->paginate(25)
            ->withQueryString();
        return view('admin.elearning.withdrawals', compact('withdrawals', 'filter'));
    }

    public function approveWithdrawal(int $id)
    {
        $withdrawal = ELearningWithdrawal::findOrFail($id);
        $withdrawal->update(['status' => 'approved', 'processed_at' => now()]);
        return back()->with('success', 'Withdrawal approved.');
    }

    public function rejectWithdrawal(int $id)
    {
        $withdrawal = ELearningWithdrawal::findOrFail($id);
        $withdrawal->update(['status' => 'rejected', 'processed_at' => now()]);
        return back()->with('success', 'Withdrawal rejected.');
    }

    // ── Reviews ───────────────────────────────────────────────────────────────
    public function reviews()
    {
        $search  = request('search');
        $reviews = ELearningReview::with(['user', 'course'])
            ->when($search, fn($q) => $q->whereHas('course', fn($c) => $c->where('title', 'like', "%$search%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();
        return view('admin.elearning.reviews', compact('reviews', 'search'));
    }

    public function deleteReview(int $id)
    {
        ELearningReview::findOrFail($id)->delete();
        return back()->with('success', 'Review deleted.');
    }

    // ── Settings ──────────────────────────────────────────────────────────────
    public function settings()
    {
        $settings = [
            'commission_rate'       => \App\Helpers\AppSettings::get('el_commission_rate', 20),
            'certificate_enabled'   => \App\Helpers\AppSettings::get('el_certificate_enabled', true),
            'quiz_enabled'          => \App\Helpers\AppSettings::get('el_quiz_enabled', true),
            'max_instructor_payout' => \App\Helpers\AppSettings::get('el_max_instructor_payout', 1000),
        ];
        return view('admin.elearning.settings', compact('settings'));
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'commission_rate'       => 'required|numeric|min:0|max:100',
            'certificate_enabled'   => 'boolean',
            'quiz_enabled'          => 'boolean',
            'max_instructor_payout' => 'required|numeric|min:0',
        ]);

        \App\Helpers\AppSettings::set('el_commission_rate',       $request->commission_rate);
        \App\Helpers\AppSettings::set('el_certificate_enabled',   $request->boolean('certificate_enabled'));
        \App\Helpers\AppSettings::set('el_quiz_enabled',          $request->boolean('quiz_enabled'));
        \App\Helpers\AppSettings::set('el_max_instructor_payout', $request->max_instructor_payout);

        return back()->with('success', 'eLearning settings updated.');
    }

    // ── Reports ───────────────────────────────────────────────────────────────
    public function reports()
    {
        $monthlyEnrollments = ELearningEnrollment::selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, COUNT(*) as count, SUM(amount_paid) as revenue')
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupByRaw('YEAR(created_at), MONTH(created_at)')
            ->orderByRaw('YEAR(created_at), MONTH(created_at)')
            ->get();

        $topCourses = ELearningCourse::where('status', 'published')
            ->orderByDesc('total_students')
            ->limit(10)
            ->with(['instructor.user', 'category'])
            ->get();

        $topInstructors = ELearningInstructor::with('user')
            ->where('verification_status', 'approved')
            ->orderByDesc('total_students')
            ->limit(10)
            ->get();

        $categoryBreakdown = ELearningCategory::withCount(['courses' => fn($q) => $q->where('status', 'published')])
            ->having('courses_count', '>', 0)
            ->orderByDesc('courses_count')
            ->get();

        return view('admin.elearning.reports', compact('monthlyEnrollments', 'topCourses', 'topInstructors', 'categoryBreakdown'));
    }
}
