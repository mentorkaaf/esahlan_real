<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Ad extends Model
{
    protected $fillable = [
        'title', 'description', 'image', 'image_url', 'video_url',
        'ad_type', 'target_module', 'target_district_id',
        'status', 'start_date', 'end_date',
        'action_type', 'action_value', 'button_text', 'button_color',
        'display_frequency', 'display_delay_seconds',
        'show_on_app_open', 'show_after_login', 'allow_dont_show_today',
        'sort_order', 'impressions', 'clicks',
    ];

    protected $casts = [
        'start_date'           => 'datetime',
        'end_date'             => 'datetime',
        'show_on_app_open'     => 'boolean',
        'show_after_login'     => 'boolean',
        'allow_dont_show_today'=> 'boolean',
        'impressions'          => 'integer',
        'clicks'               => 'integer',
        'display_frequency'    => 'integer',
        'display_delay_seconds'=> 'integer',
    ];

    // ── Relations ─────────────────────────────────────────────────────────────

    public function district()
    {
        return $this->belongsTo(District::class, 'target_district_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        $now = now();
        return $query->where('status', 'active')
            ->where(function ($q) use ($now) {
                $q->whereNull('start_date')->orWhere('start_date', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', $now);
            });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getEffectiveImageUrl(): ?string
    {
        if ($this->image) {
            return cdn_url($this->image);
        }
        return $this->image_url ?: null;
    }

    public function isCurrentlyActive(): bool
    {
        if ($this->status !== 'active') return false;
        $now = Carbon::now();
        if ($this->start_date && $now->lt($this->start_date)) return false;
        if ($this->end_date   && $now->gt($this->end_date))   return false;
        return true;
    }

    // ── API serialization ─────────────────────────────────────────────────────

    public function toApiArray(): array
    {
        return [
            'id'                     => $this->id,
            'title'                  => $this->title,
            'description'            => $this->description,
            'image_url'              => $this->getEffectiveImageUrl(),
            'video_url'              => $this->video_url,
            'ad_type'                => $this->ad_type,
            'target_module'          => $this->target_module,
            'action_type'            => $this->action_type,
            'action_value'           => $this->action_value,
            'button_text'            => $this->button_text ?: 'Learn More',
            'button_color'           => $this->button_color ?: '#FF8A00',
            'display_frequency'      => $this->display_frequency,
            'display_delay_seconds'  => $this->display_delay_seconds,
            'show_on_app_open'       => (bool) $this->show_on_app_open,
            'show_after_login'       => (bool) $this->show_after_login,
            'allow_dont_show_today'  => (bool) $this->allow_dont_show_today,
        ];
    }
}
