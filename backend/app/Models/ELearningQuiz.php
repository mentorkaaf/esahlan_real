<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningQuiz extends Model
{
    protected $table = 'el_quizzes';
    protected $fillable = ['lesson_id','title','description','pass_score','time_limit_minutes','max_attempts'];

    public function lesson()    { return $this->belongsTo(ELearningLesson::class, 'lesson_id'); }
    public function questions() { return $this->hasMany(ELearningQuizQuestion::class, 'quiz_id')->orderBy('sort_order'); }
    public function attempts()  { return $this->hasMany(ELearningQuizAttempt::class, 'quiz_id'); }
}
