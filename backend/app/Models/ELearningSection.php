<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningSection extends Model
{
    protected $table = 'el_sections';
    protected $fillable = ['course_id','title','sort_order'];

    public function course()  { return $this->belongsTo(ELearningCourse::class, 'course_id'); }
    public function lessons() { return $this->hasMany(ELearningLesson::class, 'section_id')->orderBy('sort_order'); }
}
