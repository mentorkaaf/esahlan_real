<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Vendor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid','user_id','module_id','module_slug','district_id','business_license','name','slug',
        'description','logo','cover_image','phone','email','address',
        'latitude','longitude','vendor_type','status','is_open','is_active','is_approved',
        'is_featured','is_verified','temporarily_closed','minimum_order','delivery_fee',
        'delivery_time','tax_percentage','commission_type','commission_value',
        'rating','review_count','meta','working_hours',
    ];

    protected $casts = [
        'is_open'=>'boolean','is_active'=>'boolean','is_approved'=>'boolean',
        'is_featured'=>'boolean','is_verified'=>'boolean',
        'temporarily_closed'=>'boolean','meta'=>'array',
        'latitude'=>'float','longitude'=>'float','rating'=>'float',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($vendor) {
            $vendor->uuid = (string) Str::uuid();
            if (!$vendor->slug) {
                $vendor->slug = Str::slug($vendor->name) . '-' . Str::random(6);
            }
        });
    }

    public function scopeActive($q) { return $q->where('status', 'active'); }
    public function scopeFeatured($q) { return $q->where('is_featured', true); }
    public function scopeOpen($q) { return $q->where('is_open', true)->where('temporarily_closed', false); }

    public function user() { return $this->belongsTo(User::class); }
    public function module() { return $this->belongsTo(Module::class); }
    public function district() { return $this->belongsTo(District::class); }
    public function schedules() { return $this->hasMany(VendorSchedule::class); }
    public function documents() { return $this->hasMany(VendorDocument::class); }
    public function employees() { return $this->hasMany(VendorEmployee::class); }
    public function categories() { return $this->hasMany(Category::class); }
    public function products() { return $this->hasMany(Product::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function reviews() { return $this->morphMany(Review::class, 'reviewable'); }
    public function wallet() { return $this->hasOne(Wallet::class, 'owner_id')->where('owner_type', 'vendor'); }
    public function commissions() { return $this->hasMany(Commission::class); }
    public function coupons() { return $this->hasMany(Coupon::class); }

    public function getEffectiveCommission(): array
    {
        if ($this->commission_type !== 'inherit') {
            return ['type' => $this->commission_type, 'value' => $this->commission_value];
        }
        return ['type' => $this->module->commission_type, 'value' => $this->module->commission_value];
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo) return null;
        return str_starts_with($this->logo, 'http') ? $this->logo : asset('storage/' . $this->logo);
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (!$this->cover_image) return null;
        return str_starts_with($this->cover_image, 'http') ? $this->cover_image : asset('storage/' . $this->cover_image);
    }

    public function isCurrentlyOpen(): bool
    {
        if ($this->temporarily_closed || !$this->is_open) return false;
        // If no schedules configured at all, or no active days, vendor is open 24/7
        $hasActiveSchedule = $this->schedules()->where('is_open', true)->exists();
        if (!$hasActiveSchedule) return true;
        $schedule = $this->schedules()->where('day', now()->dayOfWeek)->first();
        if (!$schedule || !$schedule->is_open) return false;
        $now = now()->format('H:i:s');
        return $now >= $schedule->open_time && $now <= $schedule->close_time;
    }
}
