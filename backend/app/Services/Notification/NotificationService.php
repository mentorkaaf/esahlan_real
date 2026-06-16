<?php
namespace App\Services\Notification;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    private string $fcmV1Url = 'https://fcm.googleapis.com/v1/projects/{project_id}/messages:send';
    private string $credFile;
    private string $projectId;

    public function __construct()
    {
        $this->credFile  = storage_path('app/firebase-service-account.json');
        $saPath = storage_path('app/firebase-service-account.json');
        $saData = file_exists($saPath) ? json_decode(file_get_contents($saPath), true) : [];
        $this->projectId = $saData['project_id'] ?? config('services.firebase.project_id') ?? '';
    }

    public function sendPush(array $tokens, string $title, string $body, array $data = [], ?string $imageUrl = null): bool
    {
        if (empty($tokens)) return false;

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            Log::warning('FCM: no access token — Firebase not configured');
            return false;
        }

        if (!$this->projectId) {
            Log::warning('FCM: project_id not set');
            return false;
        }

        $url     = str_replace('{project_id}', $this->projectId, $this->fcmV1Url);
        $success = true;

        foreach ($tokens as $token) {
            $payload = [
                'message' => [
                    'token'        => $token,
                    'notification' => array_filter(['title' => $title, 'body' => $body, 'image' => $imageUrl]),
                    'data'         => array_map('strval', array_merge($data ?: [], $imageUrl ? ['image_url' => $imageUrl] : [])),
                    'android'      => ['priority' => 'high', 'notification' => array_filter(['sound' => 'default', 'image' => $imageUrl])],
                    'apns'         => ['payload' => ['aps' => ['sound' => 'default']]],
                ],
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($code !== 200) {
                Log::error('FCM send failed', ['code' => $code, 'resp' => $resp, 'project' => $this->projectId]);
                $success = false;

                // Auto-clear stale tokens
                if ($code === 404) {
                    $data2 = json_decode($resp, true);
                    $errCode = $data2['error']['details'][0]['errorCode'] ?? '';
                    if (in_array($errCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH'])) {
                        User::where('fcm_token', $token)->update(['fcm_token' => null]);
                        Log::info('FCM cleared stale token', ['errorCode' => $errCode]);
                    }
                }
            } else {
                Log::info('FCM send OK', ['code' => $code]);
            }
        }

        return $success;
    }

    public function sendBroadcast(string $title, string $body, array $data = []): bool
    {
        $tokens = User::whereNotNull('fcm_token')
            ->where('status', 'active')
            ->pluck('fcm_token')
            ->toArray();

        if (empty($tokens)) return false;

        foreach (array_chunk($tokens, 100) as $chunk) {
            $this->sendPush($chunk, $title, $body, $data);
        }
        return true;
    }

    public function sendToRole(string $roleSlug, string $title, string $body, array $data = []): bool
    {
        $tokens = User::whereHas('role', fn($q) => $q->where('slug', $roleSlug))
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->toArray();
        return $this->sendPush($tokens, $title, $body, $data);
    }

    private function getAccessToken(): ?string
    {
        return Cache::remember('fcm_v1_access_token', 3300, function () {
            if (!file_exists($this->credFile)) {
                Log::warning('FCM: service account file not found: ' . $this->credFile);
                return null;
            }

            $creds = json_decode(file_get_contents($this->credFile), true);
            if (empty($creds['private_key']) || empty($creds['client_email'])) {
                Log::warning('FCM: service account missing private_key or client_email');
                return null;
            }

            $now      = time();
            $header   = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims   = $this->b64url(json_encode([
                'iss'   => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud'   => 'https://oauth2.googleapis.com/token',
                'iat'   => $now,
                'exp'   => $now + 3600,
            ]));

            $sigInput = $header . '.' . $claims;
            $key = openssl_pkey_get_private($creds['private_key']);
            if (!$key) {
                Log::error('FCM: failed to load private key');
                return null;
            }
            openssl_sign($sigInput, $sig, $key, 'SHA256');
            $jwt = $sigInput . '.' . $this->b64url($sig);

            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion'  => $jwt,
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 15,
            ]);
            $resp = json_decode(curl_exec($ch), true);
            curl_close($ch);

            return $resp['access_token'] ?? null;
        });
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
