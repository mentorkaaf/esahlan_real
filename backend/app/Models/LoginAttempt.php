<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = ['identifier', 'ip_address', 'succeeded', 'attempted_at'];

    protected $casts = ['attempted_at' => 'datetime', 'succeeded' => 'boolean'];
}
