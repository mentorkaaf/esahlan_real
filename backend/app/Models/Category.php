<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Category extends Model {
    use SoftDeletes;
    protected $fillable = ['module_id','vendor_id','parent_id','name','name_so','name_ar','slug','image','sort_order','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function module() { return $this->belongsTo(Module::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function parent() { return $this->belongsTo(Category::class,'parent_id'); }
    public function children() { return $this->hasMany(Category::class,'parent_id'); }
    public function products() { return $this->hasMany(Product::class); }
}
