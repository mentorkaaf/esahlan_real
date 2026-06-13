<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UserAddress extends Model {
    protected $fillable = ['user_id','label','address','latitude','longitude','district_id','is_default'];
    protected $casts = ['latitude'=>'float','longitude'=>'float','is_default'=>'boolean'];
    public function user() { return $this->belongsTo(User::class); }
    public function district() { return $this->belongsTo(District::class); }
}
