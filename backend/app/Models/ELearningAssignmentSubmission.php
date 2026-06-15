<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningAssignmentSubmission extends Model
{
    protected $table = 'el_assignment_submissions';
    protected $fillable = [
        'user_id','assignment_id','file_path','text_answer','grade',
        'feedback','status','submitted_at','graded_at',
    ];
    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at'    => 'datetime',
    ];

    public function user()       { return $this->belongsTo(User::class); }
    public function assignment() { return $this->belongsTo(ELearningAssignment::class, 'assignment_id'); }
}
