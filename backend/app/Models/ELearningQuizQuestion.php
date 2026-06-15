<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningQuizQuestion extends Model
{
    protected $table = 'el_quiz_questions';
    protected $fillable = ['quiz_id','question','type','options','correct_answer','explanation','sort_order'];
    protected $casts = ['options' => 'array'];

    public function quiz() { return $this->belongsTo(ELearningQuiz::class, 'quiz_id'); }
}
