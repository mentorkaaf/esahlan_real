<?php

namespace App\Traits\HR;

use App\Services\HR\AuditService;

/**
 * Auto-log created / updated / deleted events for any HR model.
 * Use: add `use HrAuditable;` to any HR model class.
 */
trait HrAuditable
{
    public static function bootHrAuditable(): void
    {
        static::created(function ($model) {
            AuditService::log(
                static::auditActionPrefix() . '.created',
                $model,
                null,
                static::auditPayload($model)
            );
        });

        static::updated(function ($model) {
            [$before, $after] = AuditService::diffModel($model);
            if ($before || $after) {
                AuditService::log(
                    static::auditActionPrefix() . '.updated',
                    $model,
                    $before,
                    $after
                );
            }
        });

        static::deleted(function ($model) {
            AuditService::log(
                static::auditActionPrefix() . '.deleted',
                $model,
                static::auditPayload($model),
                null
            );
        });
    }

    /**
     * Override in model to customize the action prefix.
     * Default: derives from table name e.g. hr_employees → employee
     */
    protected static function auditActionPrefix(): string
    {
        $table = (new static())->getTable();
        // hr_employees → employee, hr_departments → department, etc.
        return str_replace('hr_', '', $table);
        // strip trailing 's' for cleaner names: employees → employee
    }

    /**
     * Override in model to limit which fields are included in audit payloads.
     * Default: all fillable attributes (excluding sensitive ones for display).
     */
    protected static function auditPayload($model): array
    {
        $data = $model->toArray();
        // Don't log password-like fields
        unset($data['password'], $data['remember_token']);
        return $data;
    }
}
