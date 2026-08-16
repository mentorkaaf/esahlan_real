<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    protected $fillable = ['name','slug','module','group'];

    public function roles() {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
    public function users() {
        return $this->belongsToMany(User::class, 'user_permissions')->withPivot('granted');
    }
    public function moduleRoles() {
        return $this->belongsToMany(ModuleRole::class, 'module_role_permissions', 'permission_id', 'module_role_id');
    }
}
