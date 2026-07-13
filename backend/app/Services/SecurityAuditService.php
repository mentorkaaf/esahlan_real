<?php

namespace App\Services;

use App\Models\SecurityAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SecurityAuditService
{
    /**
     * Log a security event. Never throws — auth flows must not be disrupted by logging failure.
     *
     * @param string $event    dot-notation event name: login.failed, token.revoked, role.changed …
     * @param string $severity crit | warn | info | ok
     * @param array  $context  Keys: user_id, identifier, ip, ua, + any extra metadata
     */
    public static function log(string $event, string $severity = 'info', array $context = []): void
    {
        try {
            SecurityAuditLog::create([
                'event'           => $event,
                'severity'        => $severity,
                'user_id'         => $context['user_id'] ?? null,
                'user_identifier' => $context['identifier'] ?? null,
                'ip_address'      => $context['ip'] ?? null,
                'user_agent'      => $context['ua'] ?? null,
                'metadata'        => array_diff_key($context, array_flip(['user_id', 'identifier', 'ip', 'ua'])),
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[SecurityAudit] Failed to write log: ' . $e->getMessage());
        }
    }

    /**
     * Convenience: extract common context fields from a request.
     */
    public static function fromRequest(Request $request): array
    {
        return [
            'ip' => $request->ip(),
            'ua' => substr($request->userAgent() ?? '', 0, 255),
        ];
    }
}
