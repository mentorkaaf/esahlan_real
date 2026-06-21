<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Deliveryman extends Model {
    use SoftDeletes;

    protected $fillable = [
        'uuid','user_id','district_id','national_id',
        'vehicle_type','vehicle_plate','vehicle_model','license_number',
        'status','driver_type','is_approved','is_online','is_available',
        'latitude','longitude','last_location_at',
        'rating','total_deliveries','cash_in_hand','fcm_token','meta',
    ];

    protected $casts = [
        'is_approved'  => 'boolean',
        'is_online'    => 'boolean',
        'is_available' => 'boolean',
        'latitude'     => 'float',
        'longitude'    => 'float',
        'rating'       => 'float',
        'cash_in_hand' => 'float',
        'meta'         => 'array',
    ];

    protected static function boot() {
        parent::boot();
        static::creating(fn($d) => $d->uuid = (string)Str::uuid());
    }

    public function user() { return $this->belongsTo(User::class); }
    public function district() { return $this->belongsTo(District::class); }
    public function orders() { return $this->hasMany(Order::class); }
    public function earnings() { return $this->hasMany(DeliverymanEarning::class); }
    public function documents() { return $this->hasMany(DeliverymanDocument::class); }
    public function wallet() { return $this->hasOne(Wallet::class, 'owner_id')->where('owner_type', 'deliveryman'); }
    public function reviews() { return $this->morphMany(Review::class, 'reviewable'); }

    public function scopeOnlineAvailable($q) {
        return $q->where('is_online', true)->where('is_available', true)->where('status', 'available');
    }
}
