<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VendorDocument extends Model {
    protected $fillable = ['vendor_id','type','file_path','status','reviewed_by','reviewed_at'];
    protected $casts = ['reviewed_at'=>'datetime'];
    public function vendor() { return $this->belongsTo(Vendor::class); }
}
