<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningLesson extends Model
{
    protected $table = 'el_lessons';
    protected $fillable = [
        'section_id','course_id','title','type','video_url','video_duration_seconds',
        'content','file_path','is_preview','sort_order','is_free_preview',
    ];
    protected $casts = [
        'is_preview'      => 'boolean',
        'is_free_preview' => 'boolean',
    ];

    public function getVideoUrlAttribute(): ?string
    {
        return cdn_url($this->attributes['video_url'] ?? null);
    }

    public function section()    { return $this->belongsTo(ELearningSection::class, 'section_id'); }
    public function course()     { return $this->belongsTo(ELearningCourse::class, 'course_id'); }
    public function progress()   { return $this->hasMany(ELearningLessonProgress::class, 'lesson_id'); }
    public function quiz()       { return $this->hasOne(ELearningQuiz::class, 'lesson_id'); }
    public function assignment() { return $this->hasOne(ELearningAssignment::class, 'lesson_id'); }
}
