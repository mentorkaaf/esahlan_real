<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class QrCode extends Model
{
    protected $fillable = [
        'token','title','type','headline','description',
        'logo_url','fields','cta_label','cta_url','color',
        'scan_count','is_active','created_by',
    ];

    protected $casts = [
        'fields'    => 'array',
        'is_active' => 'boolean',
    ];

    // Auto-generate token before creating
    protected static function booted(): void
    {
        static::creating(function (self $qr) {
            $qr->token ??= self::uniqueToken();
        });
    }

    public static function uniqueToken(): string
    {
        do {
            $token = strtoupper(Str::random(8));
        } while (self::where('token', $token)->exists());
        return $token;
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publicUrl(): string
    {
        return url('/qr/' . $this->token);
    }

    public static function types(): array
    {
        return [
            'vendor'  => ['label' => 'Vendor',    'icon' => '🏪', 'color' => '#FF8A00'],
            'driver'  => ['label' => 'Driver',     'icon' => '🚗', 'color' => '#10B981'],
            'order'   => ['label' => 'Order',      'icon' => '📦', 'color' => '#8B5CF6'],
            'event'   => ['label' => 'Event',      'icon' => '🎉', 'color' => '#EF4444'],
            'promo'   => ['label' => 'Promo',      'icon' => '🏷️', 'color' => '#3B82F6'],
            'custom'  => ['label' => 'Custom',     'icon' => '⚙️', 'color' => '#6B7280'],
        ];
    }
}
