<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ELearningCourse extends Model
{
    protected $table = 'el_courses';
    protected $fillable = [
        'instructor_id','category_id','title','slug','subtitle','description',
        'thumbnail','trailer_video','language','level','price','discount_price',
        'duration_hours','total_lessons','total_sections','total_students',
        'rating','total_reviews','tags','learning_outcomes','requirements',
        'target_audience','status','is_featured','is_free','commission_rate',
    ];
    protected $casts = [
        'tags'              => 'array',
        'learning_outcomes' => 'array',
        'requirements'      => 'array',
        'is_featured'       => 'boolean',
        'is_free'           => 'boolean',
        'price'             => 'float',
        'discount_price'    => 'float',
        'rating'            => 'float',
        'duration_hours'    => 'float',
        'commission_rate'   => 'float',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($course) {
            if (!$course->slug) {
                $course->slug = Str::slug($course->title) . '-' . Str::random(6);
            }
        });
    }

    public function instructor() { return $this->belongsTo(ELearningInstructor::class, 'instructor_id'); }
    public function category()   { return $this->belongsTo(ELearningCategory::class, 'category_id'); }
    public function sections()   { return $this->hasMany(ELearningSection::class, 'course_id')->orderBy('sort_order'); }
    public function lessons()    { return $this->hasMany(ELearningLesson::class, 'course_id'); }
    public function enrollments(){ return $this->hasMany(ELearningEnrollment::class, 'course_id'); }
    public function reviews()    { return $this->hasMany(ELearningReview::class, 'course_id'); }
    public function wishlists()  { return $this->hasMany(ELearningWishlist::class, 'course_id'); }

    public function getEffectivePriceAttribute(): float
    {
        return $this->discount_price ?? $this->price;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail) return null;
        $url = str_starts_with($this->thumbnail, 'http')
            ? $this->thumbnail
            : asset('storage/' . $this->thumbnail);
        // Ensure HTTPS so Android doesn't block cleartext HTTP
        return str_replace('http://', 'https://', $url);
    }
}
