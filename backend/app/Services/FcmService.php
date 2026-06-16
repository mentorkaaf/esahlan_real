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
        string  $fcmToken,
        string  $orderNumber,
        string  $status,
        int     $orderId,
        ?string $moduleSlug = null,
    ): bool {
        // Resolve title/body from DB templates (module-specific → global → hardcoded)
        $tpl  = \App\Models\OrderNotificationTemplate::resolve($status, $moduleSlug);
        $title = $tpl['title'];
        $body  = str_replace('{order_number}', $orderNumber, $tpl['body']);

        return self::sendToToken($fcmToken, $title, $body, [
            'type'         => 'order_update',
            'order_id'     => (string) $orderId,
            'order_number' => $orderNumber,
            'status'       => $status,
            'module'       => (string) ($moduleSlug ?? ''),
        ]);
    }

    // ── Get OAuth2 access token from service account ──────────────────────────

    public static function sendWalletCredit(string $t,float $a,float $b):bool{return self::sendToToken($t,'Wallet Topped Up','$'.number_format($a,2).' added. Balance: $'.number_format($b,2),['type'=>'wallet_credit','amount'=>(string)$a,'balance'=>(string)$b]);}
    public static function sendWithdrawalApproved(string $t,float $a):bool{return self::sendToToken($t,'Withdrawal Approved','Your withdrawal of $'.number_format($a,2).' is approved.',['type'=>'withdrawal_approved','amount'=>(string)$a]);}
    public static function sendWithdrawalRejected(string $t,float $a):bool{return self::sendToToken($t,'Withdrawal Rejected','Your withdrawal of $'.number_format($a,2).' was rejected and refunded.',['type'=>'withdrawal_rejected','amount'=>(string)$a]);}
    public static function sendBookingUpdate(string $t,string $mod,string $st,int $id):bool{$msgs=['pending'=>['Booking Received','Your $mod booking is under review.'],'confirmed'=>['Booking Confirmed','Your $mod booking is confirmed.'],'in_progress'=>['In Progress','Your $mod booking is in progress.'],'completed'=>['Completed!','Your $mod booking is complete.'],'cancelled'=>['Cancelled','Your $mod booking was cancelled.'],'rejected'=>['Rejected','Your $mod booking was rejected.']];[$ti,$bo]=$msgs[$st]??['Booking Update','Status: '.$st];return self::sendToToken($t,$ti,$bo,['type'=>'booking_update','module'=>$mod,'booking_id'=>(string)$id,'status'=>$st]);}
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
