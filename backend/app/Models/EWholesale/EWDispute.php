<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWDispute extends Model
{
    protected $table = 'ewholesale_disputes';

    protected $fillable = [
        'order_id', 'opened_by', 'reason', 'description', 'attachments',
        'status', 'resolution_note', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'attachments'  => 'array',
        'resolved_at'  => 'datetime',
    ];

    public function order() { return $this->belongsTo(EWOrder::class, 'order_id'); }
}
