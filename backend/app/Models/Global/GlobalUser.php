<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthContract;
use Laravel\Sanctum\HasApiTokens;

class GlobalUser extends Model implements AuthContract
{
    use SoftDeletes, Authenticatable, HasApiTokens;

    protected $fillable = [
        'name','email','password','phone','avatar','country_code','country','fcm_token',
        'email_verified','email_verify_token','reset_token','reset_token_expires_at',
        'stripe_customer_id','paypal_customer_id','is_active','marketing_emails','last_login_at',
        'is_banned','banned_at','ban_reason',
    ];

    protected $hidden = ['password','remember_token','email_verify_token','reset_token'];

    protected $casts = [
        'email_verified'         => 'boolean',
        'is_active'              => 'boolean',
        'is_banned'              => 'boolean',
        'marketing_emails'       => 'boolean',
        'last_login_at'          => 'datetime',
        'banned_at'              => 'datetime',
        'reset_token_expires_at' => 'datetime',
    ];

    public function addresses() { return $this->hasMany(GlobalAddress::class); }
    public function orders()    { return $this->hasMany(GlobalOrder::class); }
    public function wishlists() { return $this->hasMany(GlobalWishlist::class); }
    public function defaultAddress() { return $this->hasOne(GlobalAddress::class)->where('is_default', true); }
}
