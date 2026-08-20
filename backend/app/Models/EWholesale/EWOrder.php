<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EWOrder extends Model
{
    use SoftDeletes;

    protected $table = 'ewholesale_orders';

    protected $fillable = [
        'order_no', 'buyer_id', 'supplier_id', 'source', 'quote_id', 'status',
        'payment_plan', 'payment_method', 'deposit_percent', 'subtotal', 'delivery_fee', 'platform_fee',
        'total', 'paid_total', 'fulfillment', 'address_id', 'expected_at',
        'buyer_note', 'cancelled_reason', 'confirmed_at', 'completed_at',
    ];

    protected $casts = [
        'deposit_percent' => 'float',
        'subtotal'        => 'float',
        'delivery_fee'    => 'float',
        'platform_fee'    => 'float',
        'total'           => 'float',
        'paid_total'      => 'float',
        'expected_at'     => 'date',
        'confirmed_at'    => 'datetime',
        'completed_at'    => 'datetime',
    ];

    public function buyer()       { return $this->belongsTo(EWBuyer::class, 'buyer_id'); }
    public function supplier()    { return $this->belongsTo(EWSupplier::class, 'supplier_id'); }
    public function items()       { return $this->hasMany(EWOrderItem::class, 'order_id'); }
    public function payments()    { return $this->hasMany(EWOrderPayment::class, 'order_id'); }
    public function shipments()   { return $this->hasMany(EWShipment::class, 'order_id'); }
    public function disputes()    { return $this->hasMany(EWDispute::class, 'order_id'); }

    public function balanceDue(): float
    {
        return max(0, $this->total - $this->paid_total);
    }

    public static function generateOrderNo(): string
    {
        return 'EWO-' . strtoupper(substr(md5(uniqid('ew', true)), 0, 8));
    }

    public static function statusColor(string $status): string
    {
        return match($status) {
            'pending_confirmation' => 'yellow',
            'confirmed'            => 'blue',
            'awaiting_payment'     => 'orange',
            'processing'           => 'indigo',
            'ready'                => 'cyan',
            'partially_shipped'    => 'teal',
            'shipped'              => 'sky',
            'delivered'            => 'green',
            'completed'            => 'emerald',
            'cancelled'            => 'red',
            'disputed'             => 'rose',
            default                => 'gray',
        };
    }
}
