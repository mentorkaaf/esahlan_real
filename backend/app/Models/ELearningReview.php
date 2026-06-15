<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningReview extends Model
{
    protected $table = 'el_reviews';
    protected $fillable = ['user_id','course_id','rating','comment','instructor_reply'];

    public function user()   { return $this->belongsTo(User::class); }
    public function course() { return $this->belongsTo(ELearningCourse::class, 'course_id'); }
}
