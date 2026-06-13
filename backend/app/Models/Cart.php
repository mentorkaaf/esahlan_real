<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Cart extends Model {
    protected $fillable = ['user_id','vendor_id','module_id'];
    public function user() { return $this->belongsTo(User::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function module() { return $this->belongsTo(Module::class); }
    public function items() { return $this->hasMany(CartItem::class); }

    public function getTotalAttribute(): float {
        return $this->items->sum(fn($i) => $i->price * $i->quantity);
    }
}
