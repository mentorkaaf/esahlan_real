<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VendorSchedule extends Model {
    public $timestamps = false;
    protected $fillable = ['vendor_id','day','is_open','open_time','close_time'];
    protected $casts = ['is_open'=>'boolean'];
    public function vendor() { return $this->belongsTo(Vendor::class); }
}
