<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExchangeRate extends Model {
    public $timestamps = false;
    const UPDATED_AT = 'updated_at';
    protected $fillable = ['from_wallet','to_wallet','rate','fee_type','fee_value','min_amount','max_amount','is_active'];
    protected $casts = ['rate'=>'float','fee_value'=>'float','min_amount'=>'float','max_amount'=>'float','is_active'=>'boolean'];
}
