<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalCategory extends Model
{
    protected $fillable = ['parent_id','name','slug','image','icon','sort_order','is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function parent()   { return $this->belongsTo(GlobalCategory::class, 'parent_id'); }
    public function children() { return $this->hasMany(GlobalCategory::class, 'parent_id'); }
    public function products() { return $this->hasMany(GlobalProduct::class, 'category_id'); }
}
