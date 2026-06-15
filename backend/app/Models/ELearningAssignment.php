<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningAssignment extends Model
{
    protected $table = 'el_assignments';
    protected $fillable = ['lesson_id','title','description','due_days','max_score'];

    public function lesson()      { return $this->belongsTo(ELearningLesson::class, 'lesson_id'); }
    public function submissions() { return $this->hasMany(ELearningAssignmentSubmission::class, 'assignment_id'); }
}
