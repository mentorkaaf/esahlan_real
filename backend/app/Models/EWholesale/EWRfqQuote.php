<?php

namespace App\Models\EWholesale;

use Illuminate\Database\Eloquent\Model;

class EWRfqQuote extends Model
{
    protected $table = 'ewholesale_rfq_quotes';

    protected $fillable = [
        'rfq_id', 'supplier_id', 'unit_price', 'qty_offered', 'lead_time_days',
        'valid_until', 'note', 'status',
    ];

    protected $casts = [
        'unit_price'   => 'float',
        'qty_offered'  => 'float',
        'valid_until'  => 'date',
    ];

    public function rfq()      { return $this->belongsTo(EWRfq::class, 'rfq_id'); }
    public function supplier() { return $this->belongsTo(EWSupplier::class, 'supplier_id'); }
}
