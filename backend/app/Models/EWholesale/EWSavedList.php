<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWSavedList extends Model
{
    protected $table = 'ewholesale_saved_lists';
    protected $fillable = ['buyer_id','name'];

    public function buyer() { return $this->belongsTo(EWBuyer::class,'buyer_id'); }
    public function items() { return $this->hasMany(EWSavedListItem::class,'list_id'); }
}
