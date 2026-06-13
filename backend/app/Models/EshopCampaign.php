<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EshopCampaign extends Model {
    protected $table    = 'eshop_campaigns';
    protected $fillable = ['title','subtitle','banner','badge','discount_type','discount_value','starts_at','ends_at','is_active','sort_order'];
    protected $casts    = ['is_active' => 'boolean', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'discount_value' => 'float'];

    public function products() {
        return $this->belongsToMany(Product::class, 'eshop_campaign_products', 'campaign_id', 'product_id');
    }
}
