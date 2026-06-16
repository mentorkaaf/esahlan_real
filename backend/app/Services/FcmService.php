<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FcmService
{
    private const SCOPE    = 'https://www.googleapis.com/auth/firebase.messaging';
    private const SA_PATH  = 'app/firebase-service-account.json';
    private const TOKEN_CACHE_KEY = 'fcm_v1_token';

    // ── Send to a single FCM token ────────────────────────────────────────────
    public static function sendToToken(
        string  $fcmToken,
        string  $title,
        string  $body,
        array   $data     = [],
        ?string $imageUrl = null,
    ): bool {
        if (empty($fcmToken)) return false;

        $sa = self::loadServiceAccount();
        if (!$sa) return false;

        $accessToken = self::getAccessToken($sa);
        if (!$accessToken) return false;

        $projectId = $sa['project_id'];

        $payload = [
            'message' => [
                'token'        => $fcmToken,
                'notification' => array_filter([
                    'title' => $title,
                    'body'  => $body,
                    'image' => $imageUrl,
                ]),
                'data'    => array_map('strval', $data ?: []),
                'android' => [
                    'priority'     => 'high',
                    'notification' => array_filter([
                        'channel_id' => 'esahlan_high_v3',
                        'sound'      => 'default',
                        'color'      => '#140465',
                        'image'      => $imageUrl,
                    ]),
                ],
                'apns' => [
                    'headers' => ['apns-priority' => '10'],
                    'payload' => ['aps' => ['alert' => ['title' => $title, 'body' => $body], 'sound' => 'default', 'badge' => 1]],
                ],
            ],
        ];

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            Log::error('[FCM] sendToToken failed', ['code' => $code, 'resp' => $resp]);

            // Auto-clear stale tokens so we don't retry dead tokens
            if ($code === 404) {
                $parsed  = json_decode($resp, true);
                $errCode = $parsed['error']['details'][0]['errorCode'] ?? '';
                if (in_array($errCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH'])) {
                    \App\Models\User::where('fcm_token', $fcmToken)->update(['fcm_token' => null]);
                    Log::info('[FCM] Cleared stale token', ['errorCode' => $errCode]);
                }
            }
            return false;
        }

        Log::info('[FCM] sendToToken OK', ['code' => $code]);
        return true;
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

    // ── Order status notification (uses DB templates) ─────────────────────────
    public static function sendOrderUpdate(
        string  $fcmToken,
        string  $orderNumber,
        string  $status,
        int     $orderId,
        ?string $moduleSlug = null,
    ): bool {
        $tpl   = \App\Models\OrderNotificationTemplate::resolve($status, $moduleSlug);
        $title = $tpl['title'];
        $body  = str_replace('{order_number}', $orderNumber, $tpl['body']);

        return self::sendToToken($fcmToken, $title, $body, [
            'type'         => 'order_update',
            'order_id'     => (string) $orderId,
            'order_number' => $orderNumber,
            'status'       => $status,
            'module'       => (string) ($moduleSlug ?? ''),
            'deep_link'    => '/orders/' . $orderId,
        ]);
    }

    // ── Wallet / withdrawal helpers ───────────────────────────────────────────
    public static function sendWalletCredit(string $t, float $a, float $b): bool
    {
        return self::sendToToken($t, 'Wallet Topped Up', '$' . number_format($a, 2) . ' added. Balance: $' . number_format($b, 2), ['type' => 'wallet_credit', 'amount' => (string) $a, 'balance' => (string) $b, 'deep_link' => '/wallet']);
    }

    public static function sendWithdrawalApproved(string $t, float $a): bool
    {
        return self::sendToToken($t, 'Withdrawal Approved', 'Your withdrawal of $' . number_format($a, 2) . ' is approved.', ['type' => 'withdrawal_approved', 'amount' => (string) $a, 'deep_link' => '/wallet']);
    }

    public static function sendWithdrawalRejected(string $t, float $a): bool
    {
        return self::sendToToken($t, 'Withdrawal Rejected', 'Your withdrawal of $' . number_format($a, 2) . ' was rejected and refunded.', ['type' => 'withdrawal_rejected', 'amount' => (string) $a, 'deep_link' => '/wallet']);
    }

    public static function sendBookingUpdate(string $t, string $mod, string $st, int $id): bool
    {
        $msgs = [
            'pending'     => ['Booking Received', "Your {$mod} booking is under review."],
            'confirmed'   => ['Booking Confirmed', "Your {$mod} booking is confirmed."],
            'in_progress' => ['In Progress', "Your {$mod} booking is in progress."],
            'completed'   => ['Completed!', "Your {$mod} booking is complete."],
            'cancelled'   => ['Cancelled', "Your {$mod} booking was cancelled."],
            'rejected'    => ['Rejected', "Your {$mod} booking was rejected."],
        ];
        [$ti, $bo] = $msgs[$st] ?? ['Booking Update', "Status: {$st}"];
        return self::sendToToken($t, $ti, $bo, ['type' => 'booking_update', 'module' => $mod, 'booking_id' => (string) $id, 'status' => $st, 'deep_link' => '/orders']);
    }

    // ── Internal: load service account JSON ──────────────────────────────────
    private static function loadServiceAccount(): ?array
    {
        $path = storage_path(self::SA_PATH);
        if (!file_exists($path)) {
            Log::error('[FCM] Service account file not found: ' . $path);
            return null;
        }
        $sa = json_decode(file_get_contents($path), true);
        if (empty($sa['private_key']) || empty($sa['client_email']) || empty($sa['project_id'])) {
            Log::error('[FCM] Service account JSON is missing required fields');
            return null;
        }
        return $sa;
    }

    // ── Internal: get OAuth2 access token via JWT (curl, not Guzzle) ─────────
    private static function getAccessToken(array $sa): ?string
    {
        // Try cache first
        $cached = \Illuminate\Support\Facades\Cache::get(self::TOKEN_CACHE_KEY);
        if ($cached) return $cached;

        $key = openssl_pkey_get_private($sa['private_key']);
        if (!$key) {
            Log::error('[FCM] Failed to load private key: ' . openssl_error_string());
            return null;
        }

        $b64 = fn($d) => rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
        $now = time();
        $h   = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $c   = $b64(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => self::SCOPE,
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));
        openssl_sign("{$h}.{$c}", $sig, $key, 'SHA256');
        $jwt = "{$h}.{$c}." . $b64($sig);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            Log::error('[FCM] OAuth2 token request failed', ['code' => $code, 'resp' => $res]);
            return null;
        }

        $token = json_decode($res, true)['access_token'] ?? null;
        if ($token) {
            \Illuminate\Support\Facades\Cache::put(self::TOKEN_CACHE_KEY, $token, 3300);
        }
        return $token;
    }
}
