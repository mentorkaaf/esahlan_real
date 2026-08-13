<?php
namespace App\Models;
use App\Services\AdminAlertService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
class Product extends Model {
    use SoftDeletes;
    protected $fillable = ['uuid','vendor_id','module_id','category_id','name','name_so','name_ar','slug','description','short_description','sku','barcode','price','compare_price','cost_price','sale_price','is_taxable','tax_percentage','track_inventory','stock_quantity','low_stock_alert','is_active','is_available','is_featured','sort_order','rating','review_count','total_reviews','unit','unit_id','brand','tags','weight','min_qty','max_qty','moq','meta','image','thumbnail','available_from','available_until'];
    protected $casts = ['is_taxable'=>'boolean','track_inventory'=>'boolean','is_active'=>'boolean','is_featured'=>'boolean','price'=>'float','compare_price'=>'float','meta'=>'array'];
    protected static function boot() {
        parent::boot();
        static::creating(fn($p) => $p->uuid = (string)Str::uuid());

        // Admin alert: new product added (vendor web panel OR vendor app)
        static::created(function (Product $p) {
            try {
                $vendorName = \DB::table('vendors')->where('id', $p->vendor_id)->value('name') ?? 'Vendor #' . $p->vendor_id;
                AdminAlertService::send('new_product', "📦 New Product: {$p->name}", [
                    'Product'  => $p->name,
                    'Price'    => '$' . number_format($p->price, 2),
                    'Vendor'   => $vendorName,
                    'Stock'    => $p->stock_quantity ?? 0,
                    'Added At' => now()->format('d M Y H:i') . ' UTC',
                ], 'new_product_vendor_' . $p->vendor_id, 60);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('[AdminAlert][new_product] ' . $e->getMessage());
            }
        });

        // Admin alert: low stock when product updated and stock drops ≤ 5
        static::updated(function (Product $p) {
            try {
                $stock = (int)$p->stock_quantity;
                $wasHigher = (int)($p->getOriginal('stock_quantity') ?? 99) > 5;
                if ($wasHigher && $stock <= 5) {
                    AdminAlertService::send('low_stock', "⚠️ Low Stock: {$p->name} ({$stock} left)", [
                        'Product'    => $p->name,
                        'Stock Left' => $stock . ($stock === 0 ? ' — OUT OF STOCK' : ''),
                        'Threshold'  => '5 units',
                        'Vendor'     => \DB::table('vendors')->where('id', $p->vendor_id)->value('name') ?? '#' . $p->vendor_id,
                    ], 'low_stock_product_' . $p->id, 3600);
                }
            } catch (\Throwable) {}
        });
    }
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
