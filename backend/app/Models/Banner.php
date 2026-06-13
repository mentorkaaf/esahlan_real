<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Banner extends Model {
    protected $fillable = ['title','image','link_type','link_value','module_id','district_id','position','sort_order','is_active','starts_at','ends_at'];
    protected $casts = ['is_active'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime'];
    public function module() { return $this->belongsTo(Module::class); }
    public function district() { return $this->belongsTo(District::class); }
    public function getImageUrlAttribute(): ?string { return $this->image ? asset('storage/'.$this->image) : null; }
}
