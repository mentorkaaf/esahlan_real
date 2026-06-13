<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductAttributeValue extends Model {
    public $timestamps = false;
    protected $fillable = ['attribute_id', 'value', 'color_code', 'sort_order'];

    public function attribute() {
        return $this->belongsTo(ProductAttribute::class, 'attribute_id');
    }
}
