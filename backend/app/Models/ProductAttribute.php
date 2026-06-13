<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductAttribute extends Model {
    public $timestamps = false;
    protected $fillable = ['name', 'type', 'is_active', 'sort_order'];
    protected $casts    = ['is_active' => 'boolean'];

    public function values() {
        return $this->hasMany(ProductAttributeValue::class, 'attribute_id')->orderBy('sort_order');
    }
}
