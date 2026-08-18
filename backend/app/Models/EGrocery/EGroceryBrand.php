<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EGroceryBrand extends Model
{
    protected $table = 'egrocery_brands';
    protected $fillable = ['name','logo','is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function products(): HasMany
    {
        return $this->hasMany(EGroceryProduct::class, 'brand_id');
    }
}
