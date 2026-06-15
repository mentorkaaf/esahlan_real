<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningQuizAttempt extends Model
{
    protected $table = 'el_quiz_attempts';
    protected $fillable = ['user_id','quiz_id','answers','score','passed','time_taken_seconds','attempt_number'];
    protected $casts = ['answers' => 'array', 'passed' => 'boolean', 'score' => 'float'];

    public function user() { return $this->belongsTo(User::class); }
    public function quiz() { return $this->belongsTo(ELearningQuiz::class, 'quiz_id'); }
}
