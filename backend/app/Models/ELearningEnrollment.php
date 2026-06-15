<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningEnrollment extends Model
{
    protected $table = 'el_enrollments';
    protected $fillable = [
        'user_id','course_id','payment_transaction_id','amount_paid','status','completed_at','expires_at',
    ];
    protected $casts = [
        'completed_at' => 'datetime',
        'expires_at'   => 'datetime',
        'amount_paid'  => 'float',
    ];

    public function user()   { return $this->belongsTo(User::class); }
    public function course() { return $this->belongsTo(ELearningCourse::class, 'course_id'); }
    public function lessonProgress() { return $this->hasMany(ELearningLessonProgress::class, 'enrollment_id'); }
}
