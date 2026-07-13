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
        'uuid','name','email','phone','password','wallet_pin','avatar',
        'referral_code','referred_by','status','fcm_token',
        'preferred_language','dark_mode','role_id','district_id',
        'phone_verified_at','email_verified_at',
        'latitude','longitude','location_updated_at',
    ];

    protected $hidden = ['password','wallet_pin','remember_token'];

    protected $casts = [
        'phone_verified_at'   => 'datetime',
        'email_verified_at'   => 'datetime',
        'location_updated_at' => 'datetime',
        'dark_mode'           => 'boolean',
        'latitude'            => 'float',
        'longitude'           => 'float',
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
    public function elInstructor() { return $this->hasOne(\App\Models\ELearningInstructor::class); }
    public function elEnrollments() { return $this->hasMany(\App\Models\ELearningEnrollment::class); }
    public function stories() { return $this->hasMany(\App\Models\CommunityStory::class); }
    public function permissions() {
        return $this->belongsToMany(Permission::class, 'user_permissions')->withPivot('granted');
    }

    public function hasPermission(string $slug): bool
    {
        // Cache the full permission set per user for the lifetime of this request.
        // Avoids N+1 when multiple Gate checks fire on a single request.
        $perms = $this->_loadPermissionCache();

        // Explicit user-level grant/revoke takes priority over role
        if (array_key_exists($slug, $perms['user'])) {
            return (bool) $perms['user'][$slug];
        }

        return in_array($slug, $perms['role'], true);
    }

    /** @return array{user: array<string,bool>, role: string[]} */
    private function _loadPermissionCache(): array
    {
        static $cache = [];
        $key = $this->id;

        if (!isset($cache[$key])) {
            // User-level overrides: slug → granted bool
            $userPerms = $this->permissions()
                ->get(['slug', 'granted' /* pivot */])
                ->mapWithKeys(fn($p) => [$p->slug => (bool) $p->pivot->granted])
                ->all();

            // Role-level permissions: array of slugs
            $rolePerms = $this->role
                ? $this->role->permissions()->pluck('slug')->all()
                : [];

            $cache[$key] = ['user' => $userPerms, 'role' => $rolePerms];
        }

        return $cache[$key];
    }

    /** Flush the per-request permission cache for this user (call after role/perm changes). */
    public function flushPermissionCache(): void
    {
        static $cache = [];
        unset($cache[$this->id]);
    }

    public function hasRole(string $slug): bool { return $this->role?->slug === $slug; }
    public function isAdmin(): bool { return in_array($this->role?->slug, ['super_admin','admin']); }

    /** Modules this employee is assigned to manage. */
    public function managedModules()
    {
        return $this->belongsToMany(Module::class, 'user_modules');
    }

    public function isEmployee(): bool { return $this->role?->slug === 'employee'; }

    /** Roles that may sign in to the admin panel and manage everything. */
    public function isFullAdmin(): bool
    {
        return in_array($this->role?->slug, [
            'super_admin', 'admin', 'operations_manager',
            'finance_manager', 'marketing_manager', 'customer_support',
        ]);
    }

    /** Slugs of modules this user can manage in the admin panel. */
    public function manageableModuleSlugs(): array
    {
        if ($this->isFullAdmin()) {
            return Module::pluck('slug')->all();
        }
        if ($this->isEmployee()) {
            return $this->managedModules()->pluck('slug')->all();
        }
        return [];
    }

    /** Can this user manage the given module's admin pages? */
    public function canManageModule(string $slug): bool
    {
        if ($this->isFullAdmin()) return true;
        if ($this->isEmployee()) {
            return $this->managedModules()->where('slug', $slug)->exists();
        }
        return false;
    }

    /** May this user reach the admin panel at all? */
    public function canAccessAdminPanel(): bool
    {
        return $this->isFullAdmin()
            || ($this->isEmployee() && $this->managedModules()->exists());
    }

    public function getLoyaltyPointsBalance(): int
    {
        return (int) $this->loyaltyPoints()
            ->selectRaw('SUM(CASE WHEN type IN ("earned","bonus") THEN points ELSE -points END) as total')
            ->value('total');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return cdn_url($this->attributes['avatar'] ?? null);
    }

    // Serve avatars through the CORS-safe proxy so they load on Flutter Web.
    // Idempotent: proxied/external URLs pass through unchanged.
    public function getAvatarAttribute(): ?string
    {
        return cdn_url($this->attributes['avatar'] ?? null);
    }
}
