<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid','name','email','phone','password','avatar',
        'referral_code','referred_by','status','fcm_token',
        'preferred_language','dark_mode','role_id','district_id',
        'phone_verified_at','email_verified_at',
    ];

    protected $hidden = ['password','remember_token'];

    protected $casts = [
        'phone_verified_at' => 'datetime',
        'email_verified_at' => 'datetime',
        'dark_mode' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($user) {
            $user->uuid = (string) Str::uuid();
            if (!$user->referral_code) {
                $user->referral_code = strtoupper(Str::random(8));
            }
        });
    }

    public function role() { return $this->belongsTo(Role::class); }
    public function district() { return $this->belongsTo(District::class); }
    public function referrer() { return $this->belongsTo(User::class, 'referred_by'); }
    public function referrals() { return $this->hasMany(Referral::class, 'referrer_id'); }
    public function wallet() { return $this->hasOne(Wallet::class, 'owner_id')->where('owner_type', 'App\\Models\\User'); }
    public function addresses() { return $this->hasMany(UserAddress::class); }
    public function defaultAddress() { return $this->hasOne(UserAddress::class)->where('is_default', true); }
    public function orders() { return $this->hasMany(Order::class); }
    public function loyaltyPoints() { return $this->hasMany(LoyaltyPoint::class); }
    public function wishlist() { return $this->hasMany(Wishlist::class); }
    public function reviews() { return $this->hasMany(Review::class); }
    public function vendor() { return $this->hasOne(Vendor::class); }
    public function deliveryman() { return $this->hasOne(Deliveryman::class); }
    public function communityProfile() { return $this->hasOne(\App\Models\CommunityProfile::class); }
    public function stories() { return $this->hasMany(\App\Models\CommunityStory::class); }
    public function permissions() {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withPivot('granted');
    }

    public function hasPermission(string $slug): bool
    {
        $direct = $this->permissions()->where('slug', $slug)->first();
        if ($direct) return (bool) $direct->pivot->granted;
        return $this->role?->permissions()->where('slug', $slug)->exists() ?? false;
    }

    public function hasRole(string $slug): bool { return $this->role?->slug === $slug; }
    public function isAdmin(): bool { return in_array($this->role?->slug, ['super_admin','admin']); }

    public function getLoyaltyPointsBalance(): int
    {
        return (int) $this->loyaltyPoints()
            ->selectRaw('SUM(CASE WHEN type IN ("earned","bonus") THEN points ELSE -points END) as total')
            ->value('total');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar) return null;
        if (str_starts_with($this->avatar, 'http')) return $this->avatar;
        return asset('storage/' . $this->avatar);
    }
}
