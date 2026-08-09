<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DbCleanLog extends Model
{
    protected $fillable = [
        'admin_id', 'tables_cleaned', 'rows_deleted', 'status', 'notes',
    ];

    protected $casts = [
        'tables_cleaned' => 'array',
        'rows_deleted'   => 'array',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
