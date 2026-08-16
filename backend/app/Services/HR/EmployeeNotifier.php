<?php

namespace App\Services\HR;

use App\Events\RealtimeEvent;
use App\Models\HR\EmployeeNotification;
use App\Models\HR\HrEmployee;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Creates a persisted EmployeeNotification record and immediately
 * broadcasts it to the employee's private Reverb channel
 * (private-employee.{id}).
 */
class EmployeeNotifier
{
    /**
     * Send a notification to a single employee.
     */
    public static function send(
        HrEmployee $employee,
        string     $type,
        string     $title,
        string     $body  = '',
        array      $data  = [],
        ?string    $url   = null,
    ): EmployeeNotification {
        $notif = EmployeeNotification::create([
            'employee_id' => $employee->id,
            'type'        => $type,
            'title'       => $title,
            'body'        => $body,
            'data'        => $data,
            'icon'        => EmployeeNotification::iconFor($type),
            'url'         => $url,
        ]);

        static::broadcast($employee->id, $notif);

        return $notif;
    }

    /**
     * Send the same notification to many employees (e.g. an announcement).
     */
    public static function sendToAll(
        iterable $employees,
        string   $type,
        string   $title,
        string   $body  = '',
        array    $data  = [],
        ?string  $url   = null,
    ): void {
        foreach ($employees as $emp) {
            static::send($emp, $type, $title, $body, $data, $url);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    private static function broadcast(int $employeeId, EmployeeNotification $notif): void
    {
        try {
            broadcast(new RealtimeEvent(
                [['channel' => "employee.{$employeeId}", 'type' => 'private']],
                'employee.notification',
                [
                    'id'         => $notif->id,
                    'type'       => $notif->type,
                    'title'      => $notif->title,
                    'body'       => $notif->body,
                    'icon'       => $notif->icon,
                    'color'      => EmployeeNotification::colorFor($notif->type),
                    'url'        => $notif->url,
                    'created_at' => $notif->created_at->toISOString(),
                ],
            ));
        } catch (\Throwable) {
            // Broadcasting failure must never break the HTTP response
        }
    }
}
