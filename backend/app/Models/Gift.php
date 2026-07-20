<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gift extends Model
{
    protected $fillable = ['name', 'emoji', 'animation', 'coins', 'sort', 'is_active'];
    protected $casts    = ['is_active' => 'boolean'];

    public function transactions()
    {
        return $this->hasMany(GiftTransaction::class);
    }
}
