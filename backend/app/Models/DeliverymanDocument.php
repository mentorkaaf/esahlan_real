<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class DeliverymanDocument extends Model {
    protected $fillable = ['deliveryman_id','type','file_path','status','reviewed_by','reviewed_at'];
    protected $casts = ['reviewed_at' => 'datetime'];

    public function deliveryman() { return $this->belongsTo(Deliveryman::class); }
}
