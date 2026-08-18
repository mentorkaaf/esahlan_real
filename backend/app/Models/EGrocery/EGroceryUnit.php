<?php
namespace App\Models\EGrocery;

use Illuminate\Database\Eloquent\Model;

class EGroceryUnit extends Model
{
    protected $table = 'egrocery_units';
    protected $fillable = ['name','abbreviation','step'];
    protected $casts = ['step' => 'decimal:4'];
}
