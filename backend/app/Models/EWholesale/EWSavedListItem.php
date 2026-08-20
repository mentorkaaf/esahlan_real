<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWSavedListItem extends Model
{
    protected $table = 'ewholesale_saved_list_items';
    protected $fillable = ['list_id','product_id','variant_id','qty'];
    protected $casts = ['qty' => 'float'];

    public function list()    { return $this->belongsTo(EWSavedList::class,'list_id'); }
    public function product() { return $this->belongsTo(EWProduct::class,'product_id')->withTrashed(); }
    public function variant() { return $this->belongsTo(EWProductVariant::class,'variant_id'); }
}
