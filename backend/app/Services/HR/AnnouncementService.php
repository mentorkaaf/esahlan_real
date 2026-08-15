<?php

namespace App\Services\HR;

use App\Models\HR\HrAnnouncement;
use App\Models\HR\HrEmployee;
use App\Services\FcmService;

class AnnouncementService
{
    /**
     * Publish an announcement and push FCM to linked app users.
     */
    public static function publish(HrAnnouncement $announcement): int
    {
        $announcement->update(['published_at' => now()]);

        AuditService::log('announcement.published', $announcement);

        // Find employees with linked user accounts
        $query = HrEmployee::whereNotNull('user_id')
            ->whereIn('status', ['active','probation'])
            ->with('user');

        if ($announcement->audience === 'department' && $announcement->department_id) {
            $query->where('department_id', $announcement->department_id);
        }

        $employees = $query->get();

        $sent = 0;
        foreach ($employees as $emp) {
            $token = $emp->user?->fcm_token ?? null;
            if (!$token) continue;

            try {
                FcmService::sendToToken(
                    fcmToken: $token,
                    title:    '📢 ' . $announcement->title,
                    body:     substr(strip_tags($announcement->body), 0, 120),
                    data:     ['type' => 'hr_announcement', 'id' => (string) $announcement->id],
                );
                $sent++;
            } catch (\Throwable) {
                // Non-fatal — log silently
            }
        }

        return $sent;
    }
}
