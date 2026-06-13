<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class DataPackage extends Model {
    protected $fillable = ['provider_id','name','category','data_amount','speed','validity_days','price','description','image','is_active','is_featured','sort_order'];
    protected $casts = ['is_active'=>'boolean','price'=>'float'];
    public function provider() { return $this->belongsTo(DataProvider::class,'provider_id'); }
}
