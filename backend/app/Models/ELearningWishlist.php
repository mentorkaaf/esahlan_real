<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningWishlist extends Model
{
    protected $table = 'el_wishlists';
    protected $fillable = ['user_id','course_id'];

    public function user()   { return $this->belongsTo(User::class); }
    public function course() { return $this->belongsTo(ELearningCourse::class, 'course_id'); }
}
