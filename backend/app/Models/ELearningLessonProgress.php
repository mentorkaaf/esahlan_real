<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningLessonProgress extends Model
{
    protected $table = 'el_lesson_progress';
    protected $fillable = [
        'user_id','lesson_id','enrollment_id','is_completed','watch_seconds','last_position_seconds','completed_at',
    ];
    protected $casts = [
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];
}
