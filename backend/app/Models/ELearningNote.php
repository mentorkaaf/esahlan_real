<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningNote extends Model
{
    protected $table = 'el_notes';
    protected $fillable = ['user_id','lesson_id','note','timestamp_seconds'];

    public function user()   { return $this->belongsTo(User::class); }
    public function lesson() { return $this->belongsTo(ELearningLesson::class, 'lesson_id'); }
}
