<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningInstructor extends Model
{
    protected $table = 'el_instructors';
    protected $fillable = [
        'user_id','bio','expertise','qualifications','experience_years',
        'profile_photo','cover_photo','social_links','verification_status',
        'is_active','total_students','total_courses','total_earnings','rating',
    ];
    protected $casts = [
        'social_links' => 'array',
        'is_active'    => 'boolean',
        'total_earnings' => 'float',
        'rating'         => 'float',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function courses() { return $this->hasMany(ELearningCourse::class, 'instructor_id'); }
    public function earnings() { return $this->hasMany(ELearningInstructorEarning::class, 'instructor_id'); }
    public function withdrawals() { return $this->hasMany(ELearningWithdrawal::class, 'instructor_id'); }
}
