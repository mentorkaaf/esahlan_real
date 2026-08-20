<?php

namespace App\Models\EWholesale;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class EWSupplier extends Model
{
    protected $table = 'ewholesale_suppliers';

    protected $fillable = [
        'vendor_id','display_name','logo','banner','about',
        'warehouse_address','district_id',
        'verification','verified_at',
        'response_rate','response_time_avg','rating','total_orders','is_active',
    ];

    protected $casts = [
        'verified_at'    => 'datetime',
        'response_rate'  => 'decimal:2',
        'rating'         => 'decimal:2',
        'is_active'      => 'boolean',
    ];

    public function vendor(): BelongsTo        { return $this->belongsTo(Vendor::class); }
    public function products(): HasMany        { return $this->hasMany(EWProduct::class, 'supplier_id'); }
    public function shippingRules(): HasMany   { return $this->hasMany(EWShippingRule::class, 'supplier_id'); }
    public function orders(): HasMany          { return $this->hasMany(EWOrder::class, 'supplier_id'); }

    public function scopeActive($q)            { return $q->where('is_active', true); }
    public function scopeVerified($q)          { return $q->whereIn('verification', ['verified','gold']); }

    public function isVerified(): bool         { return in_array($this->verification, ['verified','gold']); }
    public function isGold(): bool             { return $this->verification === 'gold'; }
}
