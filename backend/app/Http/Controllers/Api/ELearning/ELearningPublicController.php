<?php
namespace App\Http\Controllers\Api\ELearning;

use App\Http\Controllers\Controller;
use App\Models\ELearningCategory;
use App\Models\ELearningCourse;
use App\Models\ELearningInstructor;
use Illuminate\Http\Request;

class ELearningPublicController extends Controller
{
    public function categories()
    {
        $categories = ELearningCategory::where('is_active', true)
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['status' => 'success', 'data' => $categories]);
    }

    public function courses(Request $request)
    {
        $query = ELearningCourse::with(['instructor.user', 'category'])
            ->where('status', 'published');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('title', 'like', "%$s%")
                  ->orWhere('subtitle', 'like', "%$s%")
                  ->orWhere('description', 'like', "%$s%");
            });
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('is_free')) {
            $query->where('is_free', (bool) $request->is_free);
        }

        switch ($request->get('sort', 'newest')) {
            case 'popular':  $query->orderByDesc('total_students'); break;
            case 'rating':   $query->orderByDesc('rating'); break;
            case 'price_asc': $query->orderBy('price'); break;
            case 'price_desc': $query->orderByDesc('price'); break;
            default:          $query->latest(); break;
        }

        $courses = $query->paginate(15);

        return response()->json([
            'status' => 'success',
            'data'   => $courses->map(fn($c) => $this->transformCourse($c)),
            'meta'   => [
                'current_page' => $courses->currentPage(),
                'last_page'    => $courses->lastPage(),
                'total'        => $courses->total(),
            ],
        ]);
    }

    public function courseDetail(string $slug)
    {
        $course = ELearningCourse::with([
            'instructor.user',
            'category',
            'sections.lessons',
            'reviews.user',
        ])->where('slug', $slug)->where('status', 'published')->firstOrFail();

        $isEnrolled = false;
        if (auth('sanctum')->check()) {
            $isEnrolled = $course->enrollments()
                ->where('user_id', auth('sanctum')->id())
                ->where('status', 'active')
                ->exists();
        }

        $data = $this->transformCourse($course, true);
        $data['is_enrolled'] = $isEnrolled;
        $data['sections'] = $course->sections->map(function ($section) use ($isEnrolled) {
            return [
                'id'       => $section->id,
                'title'    => $section->title,
                'lessons'  => $section->lessons->map(fn($l) => [
                    'id'                   => $l->id,
                    'title'                => $l->title,
                    'type'                 => $l->type,
                    'video_duration_seconds'=> $l->video_duration_seconds,
                    'is_free_preview'      => $l->is_free_preview,
                    'is_locked'            => !$isEnrolled && !$l->is_free_preview,
                    'video_url'            => ($isEnrolled || $l->is_free_preview) ? $l->video_url : null,
                ]),
            ];
        });
        $data['reviews'] = $course->reviews->map(fn($r) => [
            'id'      => $r->id,
            'rating'  => $r->rating,
            'comment' => $r->comment,
            'user'    => ['name' => $r->user->name, 'avatar' => $r->user->avatar_url],
            'created_at' => $r->created_at->diffForHumans(),
        ]);

        return response()->json(['status' => 'success', 'data' => $data]);
    }

    public function instructors()
    {
        $instructors = ELearningInstructor::with('user')
            ->where('verification_status', 'approved')
            ->where('is_active', true)
            ->orderByDesc('rating')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'data'   => $instructors->map(fn($i) => [
                'id'               => $i->id,
                'name'             => $i->user->name,
                'avatar'           => $i->user->avatar_url,
                'profile_photo'    => cdn_url($i->profile_photo),
                'expertise'        => $i->expertise,
                'bio'              => $i->bio,
                'rating'           => $i->rating,
                'total_students'   => $i->total_students,
                'total_courses'    => $i->total_courses,
                'experience_years' => $i->experience_years,
            ]),
        ]);
    }

    private function transformCourse(ELearningCourse $c, bool $full = false): array
    {
        $data = [
            'id'              => $c->id,
            'title'           => $c->title,
            'slug'            => $c->slug,
            'subtitle'        => $c->subtitle,
            'thumbnail'       => $c->thumbnail_url,
            'trailer_video'   => $c->trailer_video_url,
            'language'        => $c->language,
            'level'           => $c->level,
            'price'           => $c->price,
            'discount_price'  => $c->discount_price,
            'effective_price' => $c->effective_price,
            'is_free'         => $c->is_free,
            'is_featured'     => $c->is_featured,
            'duration_hours'  => $c->duration_hours,
            'total_lessons'   => $c->total_lessons,
            'total_students'  => $c->total_students,
            'rating'          => $c->rating,
            'total_reviews'   => $c->total_reviews,
            'category'        => $c->category ? ['id' => $c->category->id, 'name' => $c->category->name] : null,
            'instructor'      => $c->instructor ? [
                'id'        => $c->instructor->id,
                'name'      => $c->instructor->user->name,
                'avatar'    => $c->instructor->user->avatar_url,
                'expertise' => $c->instructor->expertise,
                'rating'    => $c->instructor->rating,
            ] : null,
        ];

        if ($full) {
            $data['description']        = $c->description;
            $data['learning_outcomes']  = $c->learning_outcomes;
            $data['requirements']       = $c->requirements;
            $data['target_audience']    = $c->target_audience;
            $data['tags']               = $c->tags;
            $data['total_sections']     = $c->total_sections;
        }

        return $data;
    }
}
