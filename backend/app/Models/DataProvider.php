<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DataProvider extends Model {
    protected $fillable = ['name','logo','color','sort_order','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function packages() { return $this->hasMany(DataPackage::class, 'provider_id'); }
    public function getLogoUrlAttribute(): ?string { return cdn_url($this->logo); }
}
