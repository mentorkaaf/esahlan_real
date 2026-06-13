<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class District extends Model {
    protected $fillable = ['name','name_so','name_ar','slug','city','country','latitude','longitude','boundary_polygon','status','sort_order'];
    protected $casts = ['latitude'=>'float','longitude'=>'float','boundary_polygon'=>'array'];
    public function modules() { return $this->belongsToMany(Module::class,'module_district')->withPivot('is_active'); }
    public function vendors() { return $this->hasMany(Vendor::class); }
    public function users() { return $this->hasMany(User::class); }
    public function deliverymen() { return $this->hasMany(Deliveryman::class); }
    public function scopeActive($q) { return $q->where('status','active'); }
}
