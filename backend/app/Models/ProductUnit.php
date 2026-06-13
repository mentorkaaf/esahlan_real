<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model {
    public $timestamps = false;
    protected $fillable = ['name', 'symbol', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];
}
