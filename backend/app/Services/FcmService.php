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
        array   $data         = [],
        ?string $imageUrl     = null,
        string  $channelId    = 'esahlan_high_v3',
        ?int    $pushNotifId  = null,   // for open-rate tracking
        ?int    $userId       = null,
        string  $userType     = 'customer',
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
                        'channel_id' => $channelId,
                        'sound'      => 'default',
                        'color'      => '#FF8A00',
                        'image'      => $imageUrl,
                    ]),
                ],
                'apns' => [
                    'headers' => ['apns-priority' => '10'],
                    'payload' => ['aps' => ['alert' => ['title' => $title, 'body' => $body], 'sound' => 'default', 'badge' => 1]],
                ],
            ],
        ];

        // Inject log ID into data payload so Flutter can report opens
        $logId = null;
        if ($pushNotifId) {
            try {
                $log = \App\Models\NotificationLog::create([
                    'push_notification_id' => $pushNotifId,
                    'user_id'   => $userId,
                    'user_type' => $userType,
                    'fcm_token' => $fcmToken,
                    'status'    => 'sent',
                    'sent_at'   => now(),
                ]);
                $logId = $log->id;
                $payload['message']['data']['notification_log_id'] = (string) $logId;
            } catch (\Throwable $e) {
                Log::warning('[FCM] Could not create NotificationLog: ' . $e->getMessage());
            }
        }

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
            Log::error('[FCM] sendToToken failed', [
                'code'  => $code,
                'token' => '...' . substr($fcmToken, -20),
                'resp'  => $resp,
            ]);

            if ($logId) {
                \App\Models\NotificationLog::where('id', $logId)->update(['status' => 'failed']);
            }

            // Auto-clear stale/mismatched tokens (check both user and vendor tables)
            // 404 = UNREGISTERED (app uninstalled/reinstalled)
            // 403 = SenderId mismatch (token from old Firebase project / old app version)
            if ($code === 404 || $code === 403) {
                $parsed  = json_decode($resp, true);
                $errCode = $parsed['error']['details'][0]['errorCode']
                    ?? $parsed['error']['status']    // 403 uses 'status' field
                    ?? '';
                $clearable = in_array($errCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH', 'PERMISSION_DENIED'])
                    || str_contains(strtolower($parsed['error']['message'] ?? ''), 'senderid mismatch');
                if ($clearable) {
                    \App\Models\User::where('fcm_token', $fcmToken)->update(['fcm_token' => null]);
                    \App\Models\Vendor::where('vendor_fcm_token', $fcmToken)->update(['vendor_fcm_token' => null]);
                    \App\Models\Global\GlobalUser::where('fcm_token', $fcmToken)->update(['fcm_token' => null]);
                    Log::info('[FCM] Cleared stale/mismatched token', ['code' => $code, 'errorCode' => $errCode, 'token' => '...' . substr($fcmToken, -20)]);
                }
            }
            return false;
        }

        $parsed = json_decode($resp, true);
        Log::info('[FCM] sendToToken OK', [
            'code'   => $code,
            'msg_id' => $parsed['name'] ?? 'unknown',
            'token'  => '...' . substr($fcmToken, -20),
        ]);
        return true;
    }

    /**
     * Send a silent data-only ping to validate a token.
     * No notification is shown to the user.
     * Returns true if the token is valid, false if invalid (auto-cleared).
     */
    public static function sendSilentPing(string $fcmToken): bool
    {
        if (empty($fcmToken)) return false;

        $sa = self::loadServiceAccount();
        if (!$sa) return false;

        $accessToken = self::getAccessToken($sa);
        if (!$accessToken) return false;

        $payload = [
            'message' => [
                'token' => $fcmToken,
                // data-only: no 'notification' block → no visible notification
                'data'  => ['type' => 'ping', 'ts' => (string) time()],
                'android' => [
                    'priority' => 'normal', // normal priority — won't wake screen
                    'direct_boot_ok' => true,
                ],
            ],
        ];

        $url = 'https://fcm.googleapis.com/v1/projects/' . $sa['project_id'] . '/messages:send';
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
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
            // Re-use existing auto-clear logic
            if ($code === 404 || $code === 403) {
                $parsed  = json_decode($resp, true);
                $errCode = $parsed['error']['details'][0]['errorCode']
                    ?? $parsed['error']['status'] ?? '';
                $clearable = in_array($errCode, ['UNREGISTERED', 'SENDER_ID_MISMATCH', 'PERMISSION_DENIED'])
                    || str_contains(strtolower($parsed['error']['message'] ?? ''), 'senderid mismatch');
                if ($clearable) {
                    \App\Models\User::where('fcm_token', $fcmToken)->update(['fcm_token' => null]);
                    \App\Models\Vendor::where('vendor_fcm_token', $fcmToken)->update(['vendor_fcm_token' => null]);
                    \App\Models\Global\GlobalUser::where('fcm_token', $fcmToken)->update(['fcm_token' => null]);
                    Log::info('[FCM] Ping cleared stale token', ['code' => $code, 'token' => '...' . substr($fcmToken, -20)]);
                }
            }
            return false;
        }

        return true;
    }

    // ── Send to multiple tokens ───────────────────────────────────────────────
    public static function sendToTokens(
        array   $fcmTokens,
        string  $title,
        string  $body,
        array   $data     = [],
        ?string $imageUrl = null,
        string  $channelId = 'esahlan_high_v3',
    ): int {
        $sent = 0;
        foreach (array_filter($fcmTokens) as $token) {
            if (self::sendToToken($token, $title, $body, $data, $imageUrl, $channelId)) {
                $sent++;
            }
        }
        return $sent;
    }

    // ── Driver assigned to customer ───────────────────────────────────────────
    // Sent when admin dispatches or driver self-accepts an order.
    public static function sendDriverAssigned(
        string  $fcmToken,
        string  $orderNumber,
        int     $orderId,
        ?string $moduleSlug,
        string  $driverName,
        string  $driverPhone,
    ): bool {
        $moduleLabel = match ($moduleSlug) {
            'efood'    => 'your food',
            'egrocery' => 'your groceries',
            'eparcel'  => 'your parcel',
            'eshop'    => 'your package',
            'emoving'  => 'your move',
            'elaundry' => 'your laundry',
            default    => 'your order',
        };

        return self::sendToToken($fcmToken,
            '🚴 Driver On the Way!',
            "{$driverName} is delivering {$moduleLabel} (#$orderNumber). Call: {$driverPhone}",
            [
                'type'         => 'driver_assigned',
                'order_id'     => (string) $orderId,
                'order_number' => $orderNumber,
                'status'       => 'out_for_delivery',
                'module'       => (string) ($moduleSlug ?? ''),
                'driver_name'  => $driverName,
                'driver_phone' => $driverPhone,
                'deep_link'    => '/orders/' . $orderId,
            ]
        );
    }

    // ── Order status notification (uses DB templates) ─────────────────────────
    public static function sendOrderUpdate(
        string  $fcmToken,
        string  $orderNumber,
        string  $status,
        int     $orderId,
        ?string $moduleSlug = null,
    ): bool {
        $tpl   = \App\Models\OrderNotificationTemplate::resolve($status, $moduleSlug, 'customer');
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


    /**
     * Send a full-screen ringing alarm when admin assigns an order to a driver.
     * The Flutter app's _bgHandler uses type=new_order to show a full-screen intent.
     */
    public static function sendNewOrderRing(
        string $fcmToken,
        array  $order,
    ): bool {
        $pickup   = $order['pickup_address']   ?? [];
        $delivery = $order['delivery_address'] ?? [];
        $fee      = $order['delivery_fee']     ?? 0;
        $slug     = $order['module_slug']      ?? 'order';

        $data = [
            'type'              => 'new_order',
            'order_id'          => (string) $order['id'],
            'order_number'      => (string) ($order['order_number'] ?? ''),
            'module_slug'       => (string) $slug,
            'delivery_fee'      => (string) $fee,
            'distance_km'       => (string) ($order['distance'] ?? 0),
            'estimated_minutes' => (string) ($order['estimated_minutes'] ?? 0),
            'driver_to_pickup_km' => (string) ($order['driver_to_pickup_km'] ?? 0),
            'pickup_district'   => (string) ($pickup['district'] ?? ''),
            'pickup_address'    => (string) ($pickup['address'] ?? ''),
            'pickup_lat'        => (string) ($pickup['lat'] ?? 0),
            'pickup_lng'        => (string) ($pickup['lng'] ?? 0),
            'delivery_district' => (string) ($delivery['district'] ?? ''),
            'delivery_address'  => (string) ($delivery['address'] ?? ''),
            'delivery_lat'      => (string) ($delivery['lat'] ?? 0),
            'delivery_lng'      => (string) ($delivery['lng'] ?? 0),
        ];

        // DATA-ONLY FCM — no 'notification' block.
        // CRITICAL: A notification block causes Android to handle the message directly
        // (shows system tray notification) and Flutter _bgHandler is NEVER called
        // when the app is killed or in background.
        // Without notification block → _bgHandler is ALWAYS called regardless of app state.
        // _bgHandler then shows the fullScreenIntent alarm with order_ring.wav itself.
        return self::sendDataOnly($fcmToken, $data);
    }

    /**
     * Data-only FCM — no notification block.
     * Flutter _bgHandler is called regardless of app state (bg/killed/foreground).
     */
    public static function sendDataOnly(string $fcmToken, array $data): bool
    {
        if (empty($fcmToken)) return false;
        $sa = self::loadServiceAccount();
        if (!$sa) return false;
        $accessToken = self::getAccessToken($sa);
        if (!$accessToken) return false;

        $payload = [
            'message' => [
                'token' => $fcmToken,
                'data'  => array_map('strval', $data),
                'android' => [
                    'priority'       => 'high',
                    'direct_boot_ok' => true,
                ],
                'apns' => [
                    'headers' => ['apns-priority' => '10', 'apns-push-type' => 'background'],
                    'payload' => ['aps' => ['content-available' => 1]],
                ],
            ],
        ];

        $projectId = $sa['project_id'];
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
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
            Log::warning('[FCM] sendDataOnly failed', ['code' => $code, 'resp' => $resp, 'token' => '...'.substr($fcmToken,-20)]);
            return false;
        }
        $parsed = json_decode($resp, true);
        Log::info('[FCM] sendDataOnly OK', ['msg_id' => $parsed['name'] ?? 'unknown', 'token' => '...'.substr($fcmToken,-20)]);
        return true;
    }

    public static function sendDriverOrderUpdate(
        string  $fcmToken,
        string  $orderNumber,
        string  $status,
        int     $orderId,
        ?string $moduleSlug = null,
    ): bool {
        $tpl   = \App\Models\OrderNotificationTemplate::resolve($status, $moduleSlug, 'driver');
        $title = $tpl['title'];
        $body  = str_replace('{order_number}', $orderNumber, $tpl['body']);

        return self::sendToToken($fcmToken, $title, $body, [
            'type'         => 'driver_order_update',
            'order_id'     => (string) $orderId,
            'order_number' => $orderNumber,
            'status'       => $status,
            'module'       => (string) ($moduleSlug ?? ''),
            'deep_link'    => '/orders',
        ], null, 'esahlan_driver_v1');
    }

    // ── Wallet / withdrawal helpers ───────────────────────────────────────────
    public static function sendWalletCredit(string $t, float $a, float $b): bool
    {
        return self::sendToToken($t, 'ePay Topped Up', '$' . number_format($a, 2) . ' added. Balance: $' . number_format($b, 2), ['type' => 'wallet_credit', 'amount' => (string) $a, 'balance' => (string) $b, 'deep_link' => '/wallet']);
    }

    public static function sendWalletEvent(string $t, string $type, float $a, float $b, string $note = ''): bool
    {
        if ($type === 'credit') {
            $title = '💰 ePay Credit';
            $body  = '+$' . number_format($a, 2) . ' received. Balance: $' . number_format($b, 2);
            if ($note) $body .= ' · ' . $note;
        } else {
            $title = '📤 ePay Debit';
            $body  = '-$' . number_format($a, 2) . ' sent. Balance: $' . number_format($b, 2);
            if ($note) $body .= ' · ' . $note;
        }
        return self::sendToToken($t, $title, $body, [
            'type'      => 'wallet_' . $type,
            'amount'    => (string) $a,
            'balance'   => (string) $b,
            'note'      => $note,
            'deep_link' => '/wallet',
        ]);
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

    // ── Notify vendor of new order (call from every module createOrder) ──────
    // Pass $vendorId (int) + the created Order model + module slug.
    // Looks up vendor_fcm_token itself — no need to fetch it in the controller.
    public static function notifyVendorNewOrder(int $vendorId, \App\Models\Order $order, string $module): void
    {
        try {
            $token = \Illuminate\Support\Facades\DB::table('vendors')
                ->where('id', $vendorId)
                ->value('vendor_fcm_token');

            Log::info("[FCM] notifyVendorNewOrder", [
                'module'    => $module,
                'vendor_id' => $vendorId,
                'has_token' => !empty($token),
            ]);

            if (!$token) return;

            $itemCount    = $order->items()->count();
            $customerName = $order->user?->name ?? 'Customer';

            self::sendToToken(
                $token,
                '🛎 New Order #' . $order->order_number,
                $customerName . ' · ' . $itemCount . ' item' . ($itemCount > 1 ? 's' : '') . ' · $' . number_format($order->total_amount, 2),
                [
                    'type'         => 'vendor_new_order',
                    'order_id'     => (string) $order->id,
                    'order_number' => $order->order_number,
                    'total'        => (string) $order->total_amount,
                    'module'       => $module,
                    'deep_link'    => '/orders',
                ]
            );
        } catch (\Throwable $e) {
            Log::error('[FCM] notifyVendorNewOrder failed: ' . $e->getMessage(), [
                'vendor_id' => $vendorId,
                'module'    => $module,
            ]);
        }
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

    /**
     * High-priority silent push that wakes the driver app (even if killed by OS)
     * and tells it to post its current GPS location immediately.
     */
    public static function sendLocationRequest(string $fcmToken): bool
    {
        if (empty($fcmToken)) return false;
        $sa = self::loadServiceAccount();
        if (!$sa) return false;
        $accessToken = self::getAccessToken($sa);
        if (!$accessToken) return false;

        $payload = [
            'message' => [
                'token' => $fcmToken,
                'data'  => [
                    'type' => 'request_location',
                    'ts'   => (string) time(),
                ],
                'android' => [
                    'priority'        => 'high',   // HIGH: wakes app even when killed
                    'direct_boot_ok'  => true,
                    'ttl'             => '30s',    // expires fast — stale ping useless
                ],
                'apns' => [
                    'headers' => ['apns-priority' => '5'],
                    'payload' => ['aps' => ['content-available' => 1]],
                ],
            ],
        ];

        // Reuse the same HTTP call pattern as sendToToken
        $url  = 'https://fcm.googleapis.com/v1/projects/' . $sa['project_id'] . '/messages:send';
        $resp = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->post($url, $payload);
        return $resp->successful();
    }

}