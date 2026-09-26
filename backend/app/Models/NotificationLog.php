<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'push_notification_id',
        'user_id',
        'user_type',
        'fcm_token',
        'status',
        'sent_at',
        'opened_at',
    ];

    protected $casts = [
        'sent_at'   => 'datetime',
        'opened_at' => 'datetime',
    ];

    public function pushNotification(): BelongsTo
    {
        return $this->belongsTo(PushNotification::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function deliveryman(): BelongsTo
    {
        return $this->belongsTo(Deliveryman::class);
    }

    /** Returns the display name+phone regardless of user_type */
    public function getActorAttribute(): ?array
    {
        if ($this->user_type === 'vendor') {
            $v = $this->vendor;
            return $v ? ['name' => $v->name, 'sub' => $v->phone ?? $v->email] : null;
        }
        if ($this->user_type === 'driver') {
            $d = $this->deliveryman;
            if ($d) {
                $u = $d->user ?? null;
                return ['name' => $u?->name ?? 'Driver #'.$d->id, 'sub' => $u?->phone ?? $u?->email ?? ''];
            }
            return null;
        }
        $u = $this->user;
        return $u ? ['name' => $u->name, 'sub' => $u->phone ?? $u->email] : null;
    }

    // ── Stats helpers ─────────────────────────────────────────────────────────
    public static function statsFor(int $pushNotificationId): array
    {
        $rows = static::where('push_notification_id', $pushNotificationId)->get();
        $sent   = $rows->count();
        $opened = $rows->where('status', 'opened')->count();
        return [
            'sent'        => $sent,
            'opened'      => $opened,
            'unopened'    => $sent - $opened,
            'open_rate'   => $sent > 0 ? round($opened / $sent * 100, 1) : 0,
        ];
    }

    public static function unopenedTokensFor(int $pushNotificationId): array
    {
        return static::where('push_notification_id', $pushNotificationId)
            ->where('status', 'sent')
            ->pluck('fcm_token')
            ->toArray();
    }
}
