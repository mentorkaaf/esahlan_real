<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ELearningCategory extends Model
{
    protected $table = 'el_categories';
    protected $fillable = ['name','slug','icon','description','parent_id','sort_order','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function parent() { return $this->belongsTo(ELearningCategory::class, 'parent_id'); }
    public function children() { return $this->hasMany(ELearningCategory::class, 'parent_id'); }
    public function courses() { return $this->hasMany(ELearningCourse::class, 'category_id'); }
}
