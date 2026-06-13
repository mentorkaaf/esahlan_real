<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
class Product extends Model {
    use SoftDeletes;
    protected $fillable = ['uuid','vendor_id','module_id','category_id','name','name_so','name_ar','slug','description','short_description','sku','barcode','price','compare_price','cost_price','sale_price','is_taxable','tax_percentage','track_inventory','stock_quantity','low_stock_alert','is_active','is_available','is_featured','sort_order','rating','review_count','total_reviews','unit','unit_id','brand','tags','weight','min_qty','max_qty','moq','meta','image','thumbnail','available_from','available_until'];
    protected $casts = ['is_taxable'=>'boolean','track_inventory'=>'boolean','is_active'=>'boolean','is_featured'=>'boolean','price'=>'float','compare_price'=>'float','meta'=>'array'];
    protected static function boot() { parent::boot(); static::creating(fn($p) => $p->uuid = (string)Str::uuid()); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function category() { return $this->belongsTo(Category::class); }
    public function images() { return $this->hasMany(ProductImage::class); }
    public function primaryImage() { return $this->hasOne(ProductImage::class)->where('is_primary',true); }
    public function variants() { return $this->hasMany(ProductVariant::class); }
    public function addons() { return $this->belongsToMany(Addon::class,'product_addons')->withPivot('is_required'); }
    public function reviews() { return $this->morphMany(Review::class,'reviewable'); }
    public function scopeActive($q) { return $q->where('is_active',true); }
    public function getDiscountPercentageAttribute(): int {
        if(!$this->compare_price || $this->compare_price <= $this->price) return 0;
        return (int)(($this->compare_price - $this->price) / $this->compare_price * 100);
    }
}
