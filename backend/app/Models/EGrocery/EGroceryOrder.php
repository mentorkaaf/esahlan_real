<?php
namespace App\Models\EGrocery;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class EGroceryOrder extends Model
{
    protected $table = 'egrocery_orders';

    protected $fillable = [
        'order_no','user_id','address_id','status','payment_method','payment_status',
        'subtotal','discount','delivery_fee','total','coupon_code',
        'delivery_slot_id','scheduled_date','substitution_pref',
        'customer_note','cancelled_reason','driver_id','confirmed_at','delivered_at',
    ];

    protected $casts = [
        'subtotal'       => 'decimal:2',
        'discount'       => 'decimal:2',
        'delivery_fee'   => 'decimal:2',
        'total'          => 'decimal:2',
        'scheduled_date' => 'date',
        'confirmed_at'   => 'datetime',
        'delivered_at'   => 'datetime',
    ];

    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function items(): HasMany         { return $this->hasMany(EGroceryOrderItem::class, 'order_id'); }
    public function deliverySlot(): BelongsTo{ return $this->belongsTo(EGroceryDeliverySlot::class, 'delivery_slot_id'); }

    public function scopeForUser($q, int $userId) { return $q->where('user_id', $userId); }

    /** Generate order number like EGR-2026-00001 */
    public static function generateOrderNo(): string
    {
        $year  = now()->year;
        $count = self::whereYear('created_at', $year)->count() + 1;
        return 'EGR-' . $year . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
    }
}
