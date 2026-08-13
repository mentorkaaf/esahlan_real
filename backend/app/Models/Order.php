<?php

namespace App\Models;

use App\Services\AdminAlertService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid','order_number','user_id','vendor_id','module_id','module_slug','deliveryman_id',
        'status','payment_status','payment_method','subtotal','delivery_fee',
        'tax_amount','discount_amount','coupon_discount','total_amount','commission','wallet_used',
        'notes','note','scheduled_at','placed_at','confirmed_at','ready_at','dispatched_at',
        'picked_up_at','delivered_at','cancelled_at','cancellation_reason',
        'refund_amount','coupon_id','delivery_address','meta',
    ];

    protected $casts = [
        'delivery_address'=>'array','meta'=>'array',
        'scheduled_at'=>'datetime','placed_at'=>'datetime','confirmed_at'=>'datetime',
        'ready_at'=>'datetime','dispatched_at'=>'datetime',
        'picked_up_at'=>'datetime','delivered_at'=>'datetime','cancelled_at'=>'datetime',
        'subtotal'=>'float','delivery_fee'=>'float','tax_amount'=>'float',
        'discount_amount'=>'float','coupon_discount'=>'float','total_amount'=>'float',
        'wallet_used'=>'float','refund_amount'=>'float',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($order) {
            $order->uuid         = (string) Str::uuid();
            $order->order_number = 'ESH-' . strtoupper(Str::random(8));
        });

        // Admin email alert — fires for ANY order from ANY controller/module
        static::created(function (Order $order) {
            try {
                $module = strtoupper($order->module_slug ?? $order->module_id ?? 'N/A');
                AdminAlertService::send('new_order', "New Order {$order->order_number}", [
                    'Order #'   => $order->order_number,
                    'Module'    => $module,
                    'Total'     => '$' . number_format((float) $order->total_amount, 2),
                    'Payment'   => strtoupper($order->payment_method ?? 'N/A'),
                    'Status'    => ucfirst($order->status ?? 'pending'),
                    'Placed At' => now()->format('d M Y H:i') . ' UTC',
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('[AdminAlert][Order::created] ' . $e->getMessage());
            }
        });
    }

    public function user() { return $this->belongsTo(User::class); }
    public function vendor() { return $this->belongsTo(Vendor::class); }
    public function module() { return $this->belongsTo(Module::class); }
    public function deliveryman() { return $this->belongsTo(Deliveryman::class); }
    public function items() { return $this->hasMany(OrderItem::class); }
    public function statusHistory() { return $this->hasMany(OrderStatusHistory::class); }
    public function tracking() { return $this->hasMany(OrderTracking::class); }
    public function latestTracking() { return $this->hasOne(OrderTracking::class)->latestOfMany(); }
    public function coupon() { return $this->belongsTo(Coupon::class); }
    public function commission() { return $this->hasOne(Commission::class); }
    public function review() { return $this->hasOne(Review::class); }

    // Alias: many places use ->total, migration uses total_amount
    public function getTotalAttribute(): float { return (float)$this->total_amount; }
    public function getDeliveryLatitudeAttribute(): ?float {
        return is_array($this->delivery_address) ? ($this->delivery_address['latitude'] ?? null) : null;
    }
    public function getDeliveryLongitudeAttribute(): ?float {
        return is_array($this->delivery_address) ? ($this->delivery_address['longitude'] ?? null) : null;
    }

    public function isPending(): bool { return $this->status === 'pending'; }
    public function isDelivered(): bool { return $this->status === 'delivered'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }
    public function canBeCancelled(): bool { return in_array($this->status, ['pending','confirmed']); }

    public function updateStatus(string $status, ?string $note = null, ?int $changedBy = null): void
    {
        $this->update(['status' => $status]);
        $this->statusHistory()->create(['status'=>$status,'note'=>$note,'changed_by'=>$changedBy]);
    }
}
