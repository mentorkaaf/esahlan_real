<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;

class GlobalAddress extends Model
{
    protected $fillable = [
        'global_user_id','label','first_name','last_name','phone',
        'address_line1','address_line2','city','state','zip_code',
        'country_code','country_name','is_default',
    ];
    protected $casts = ['is_default' => 'boolean'];
    public function user() { return $this->belongsTo(GlobalUser::class, 'global_user_id'); }
}
