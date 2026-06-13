<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name','slug','guard_name','is_system'];
    protected $casts = ['is_system'=>'boolean'];

    public function users() { return $this->hasMany(User::class); }
    public function permissions() {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }
}
