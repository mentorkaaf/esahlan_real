<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Module extends Model {
    use SoftDeletes;
    protected $fillable = ['name','name_so','name_ar','slug','icon','cover_image','description','color','is_active','is_available','sort_order','commission_type','commission_value','min_order_amount','settings'];
    protected $casts = ['is_active'=>'boolean','is_available'=>'boolean','settings'=>'array','commission_value'=>'float'];
    public function vendors() { return $this->hasMany(Vendor::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function districts() { return $this->belongsToMany(District::class,'module_district')->withPivot('is_active'); }
    public function categories() { return $this->hasMany(Category::class); }
    public function scopeActive($q) { return $q->where('is_active', true); }
    public function workforceAssignments() { return $this->hasMany(\App\Models\HR\WorkforceAssignment::class); }
}
