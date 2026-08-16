<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeNotification extends Model
{
    protected $table = 'employee_notifications';

    protected $fillable = [
        'employee_id', 'type', 'title', 'body',
        'data', 'icon', 'url', 'read_at',
    ];

    protected $casts = [
        'data'    => 'array',
        'read_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrEmployee::class, 'employee_id');
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /** Default icon per notification type */
    public static function iconFor(string $type): string
    {
        return match($type) {
            'leave_approved'  => 'fas fa-check-circle',
            'leave_rejected'  => 'fas fa-times-circle',
            'payslip_paid'    => 'fas fa-file-invoice-dollar',
            'announcement'    => 'fas fa-bullhorn',
            'order_status'    => 'fas fa-box',
            'commission'      => 'fas fa-coins',
            default           => 'fas fa-bell',
        };
    }

    /** Color per type */
    public static function colorFor(string $type): string
    {
        return match($type) {
            'leave_approved' => '#059669',
            'leave_rejected' => '#dc2626',
            'payslip_paid'   => '#2563eb',
            'announcement'   => '#d97706',
            'order_status'   => '#7c3aed',
            'commission'     => '#0891b2',
            default          => '#6b7280',
        };
    }
}
