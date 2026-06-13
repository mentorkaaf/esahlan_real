<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProductImage extends Model {
    public $timestamps = false;
    protected $fillable = ['product_id','image','sort_order','is_primary'];
    protected $casts = ['is_primary'=>'boolean'];
    public function product() { return $this->belongsTo(Product::class); }
    public function getUrlAttribute(): string { return asset('storage/'.$this->image); }
}
