<?php

namespace App\Services\Notification;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    private string $fcmUrl    = 'https://fcm.googleapis.com/fcm/send';
    private string $serverKey = '';

    public function __construct()
    {
        $this->serverKey = config('services.firebase.server_key') ?? '';
    }

    /**
     * Send push notification to specific FCM tokens.
     */
    public function sendPush(array $tokens, string $title, string $body, array $data = []): bool
    {
        if (empty($tokens) || empty($this->serverKey) || str_contains($this->serverKey, 'your_fcm')) {
            Log::info('FCM not configured — skipping push', compact('title', 'body'));
            return false;
        }

        try {
            $payload = [
                'registration_ids' => $tokens,
                'notification'     => ['title' => $title, 'body' => $body, 'sound' => 'default'],
                'data'             => $data,
                'priority'         => 'high',
            ];

            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type'  => 'application/json',
            ])->post($this->fcmUrl, $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('FCM send failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Broadcast to all users with FCM tokens.
     */
    public function sendBroadcast(string $title, string $body, array $data = []): bool
    {
        $tokens = User::whereNotNull('fcm_token')
            ->where('status', 'active')
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) {
            Log::info('No FCM tokens available for broadcast');
            return false;
        }

        // Send in batches of 500 (FCM limit)
        foreach (array_chunk($tokens, 500) as $chunk) {
            $this->sendPush($chunk, $title, $body, $data);
        }

        return true;
    }

    /**
     * Send to users by role slug.
     */
    public function sendToRole(string $roleSlug, string $title, string $body, array $data = []): bool
    {
        $tokens = User::whereHas('role', fn($q) => $q->where('slug', $roleSlug))
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();

        return $this->sendPush($tokens, $title, $body, $data);
    }
}
