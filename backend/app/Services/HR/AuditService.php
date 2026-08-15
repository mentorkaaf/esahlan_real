<?php

namespace App\Services\HR;

use App\Models\HR\HrAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an HR audit event.
     *
     * @param  string      $action      e.g. 'employee.created'
     * @param  Model|null  $subject     the model being acted on
     * @param  array|null  $before      state before change
     * @param  array|null  $after       state after change
     * @param  array       $extra       override actor info if needed
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        ?array $before  = null,
        ?array $after   = null,
        array  $extra   = []
    ): HrAuditLog {
        [$actorType, $actorId, $actorName] = static::resolveActor($extra);

        return HrAuditLog::create([
            'actor_type'   => $actorType,
            'actor_id'     => $actorId,
            'actor_name'   => $actorName,
            'action'       => $action,
            'subject_type' => $subject ? get_class($subject) : ($extra['subject_type'] ?? null),
            'subject_id'   => $subject ? $subject->getKey() : ($extra['subject_id']   ?? null),
            'before'       => $before,
            'after'        => $after,
            'ip'           => Request::ip(),
            'user_agent'   => substr(Request::userAgent() ?? '', 0, 500),
            'created_at'   => now(),
        ]);
    }

    /**
     * Resolve the current actor from auth guards.
     * Priority: hr guard → web (admin) guard → system
     */
    private static function resolveActor(array $extra): array
    {
        if (!empty($extra['actor_type'])) {
            return [$extra['actor_type'], $extra['actor_id'] ?? null, $extra['actor_name'] ?? 'System'];
        }

        if (Auth::guard('hr')->check()) {
            $staff = Auth::guard('hr')->user();
            return ['hr', $staff->id, $staff->name . ' (' . $staff->role_label . ')'];
        }

        if (Auth::guard('web')->check()) {
            $admin = Auth::guard('web')->user();
            return ['admin', $admin->id, $admin->name];
        }

        return ['system', null, 'System'];
    }

    /**
     * Log an override action taken by a web/admin user (super_admin).
     */
    public static function logAdmin(
        string $action,
        ?Model $subject = null,
        ?array $before  = null,
        ?array $after   = null,
    ): HrAuditLog {
        $admin = Auth::guard('web')->user();
        return static::log($action, $subject, $before, $after, [
            'actor_type' => 'admin',
            'actor_id'   => $admin?->id,
            'actor_name' => $admin ? $admin->name . ' [ADMIN OVERRIDE]' : 'Admin',
        ]);
    }

    /**
     * Build a before/after diff array from model dirty attributes.
     */
    public static function diffModel(Model $model): array
    {
        $before = [];
        $after  = [];

        foreach ($model->getDirty() as $key => $newVal) {
            // Skip sensitive internal keys
            if (in_array($key, ['updated_at', 'created_at', 'remember_token'])) continue;
            $before[$key] = $model->getOriginal($key);
            $after[$key]  = $newVal;
        }

        return [$before ?: null, $after ?: null];
    }
}
