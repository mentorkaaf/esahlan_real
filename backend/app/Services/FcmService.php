<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging — HTTP v1 API
 *
 * Setup:
 * 1. In Firebase Console → Project Settings → Service Accounts → Generate new private key
 * 2. Save the JSON as storage/app/firebase-service-account.json
 * 3. Add FIREBASE_PROJECT_ID=your-project-id to .env
 */
class FcmService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    // ── Send to a single FCM token ────────────────────────────────────────────
    public static function sendToToken(
        string  $fcmToken,
        string  $title,
        string  $body,
        array   $data  = [],
        ?string $imageUrl = null,
    ): bool {
        $projectId = config('services.firebase.project_id');
        if (!$projectId || $fcmToken === '') return false;

        try {
            $accessToken = self::getAccessToken();
            if (!$accessToken) return false;

            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    'data' => array_map('strval', $data),
                    'android' => [
                        'notification' => [
                            'channel_id' => 'esahlan_orders',
                            'priority'   => 'high',
                            'color'      => '#140465',
                            ...$imageUrl ? ['image' => $imageUrl] : [],
                        ],
                        'priority' => 'high',
                    ],
                    'apns' => [
                        'payload' => [
                            'aps' => [
                                'alert' => ['title' => $title, 'body' => $body],
                                'sound' => 'default',
                                'badge' => 1,
                            ],
                        ],
                        ...$imageUrl ? ['fcm_options' => ['image' => $imageUrl]] : [],
                    ],
                ],
            ];

            $response = Http::withToken($accessToken)
                ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", $payload);

            if ($response->failed()) {
                Log::warning('[FCM] Send failed', ['status' => $response->status(), 'body' => $response->body()]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('[FCM] Exception: ' . $e->getMessage());
            return false;
        }
    }

    // ── Send to multiple tokens ───────────────────────────────────────────────
    public static function sendToTokens(
        array   $fcmTokens,
        string  $title,
        string  $body,
        array   $data     = [],
        ?string $imageUrl = null,
    ): int {
        $sent = 0;
        foreach (array_filter($fcmTokens) as $token) {
            if (self::sendToToken($token, $title, $body, $data, $imageUrl)) {
                $sent++;
            }
        }
        return $sent;
    }

    // ── Order status notification ─────────────────────────────────────────────
    public static function sendOrderUpdate(
        string $fcmToken,
        string $orderNumber,
        string $status,
        int    $orderId,
    ): bool {
        $messages = [
            'pending'    => ['Order Received! 🎉',   "Your order #{$orderNumber} has been placed."],
            'confirmed'  => ['Order Confirmed ✅',   "Vendor confirmed your order #{$orderNumber}."],
            'preparing'  => ['Being Prepared 👨‍🍳',   "Your order #{$orderNumber} is being prepared."],
            'ready'      => ['Order Ready 📦',       "Your order #{$orderNumber} is ready for pickup."],
            'picked_up'  => ['On the Way! 🛵',       "Driver is heading to you with #{$orderNumber}."],
            'delivered'  => ['Delivered! 🏠',        "Your order #{$orderNumber} has arrived. Enjoy!"],
            'cancelled'  => ['Order Cancelled ❌',   "Your order #{$orderNumber} was cancelled."],
        ];

        [$title, $body] = $messages[$status] ?? ["Order Update", "Order #{$orderNumber} status: {$status}"];

        return self::sendToToken($fcmToken, $title, $body, [
            'type'         => 'order_update',
            'order_id'     => (string) $orderId,
            'order_number' => $orderNumber,
            'status'       => $status,
        ]);
    }

    // ── Get OAuth2 access token from service account ──────────────────────────
    private static function getAccessToken(): ?string
    {
        $serviceAccountPath = storage_path('app/firebase-service-account.json');

        if (!file_exists($serviceAccountPath)) {
            Log::warning('[FCM] Service account file not found: ' . $serviceAccountPath);
            return null;
        }

        try {
            $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);

            $now = time();
            $header    = self::base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claimSet  = self::base64UrlEncode(json_encode([
                'iss'   => $serviceAccount['client_email'],
                'scope' => self::SCOPE,
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            $signInput = "{$header}.{$claimSet}";
            $privateKey = openssl_pkey_get_private($serviceAccount['private_key']);
            openssl_sign($signInput, $signature, $privateKey, 'SHA256');
            $jwt = $signInput . '.' . self::base64UrlEncode($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]);

            return $response->json('access_token');
        } catch (\Throwable $e) {
            Log::error('[FCM] OAuth error: ' . $e->getMessage());
            return null;
        }
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
