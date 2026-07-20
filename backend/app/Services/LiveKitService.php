<?php
namespace App\Services;

class LiveKitService
{
    private string $apiKey;
    private string $apiSecret;
    private string $host;

    public function __construct()
    {
        $this->apiKey    = config('livekit.api_key');
        $this->apiSecret = config('livekit.api_secret');
        $this->host      = config('livekit.host');
    }

    /**
     * Generate a LiveKit access token.
     * Implements JWT manually (no extra package needed).
     */
    public function generateToken(string $roomName, string $participantIdentity, array $grants = []): string
    {
        $defaultGrants = [
            'roomJoin'   => true,
            'room'       => $roomName,
            'canPublish' => true,
            'canSubscribe' => true,
        ];

        $videoGrants = array_merge($defaultGrants, $grants);

        $header = $this->base64urlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));

        $payload = $this->base64urlEncode(json_encode([
            'iss'   => $this->apiKey,
            'sub'   => $participantIdentity,
            'iat'   => time(),
            'exp'   => time() + 3600 * 6, // 6 hours
            'nbf'   => time(),
            'jti'   => uniqid(),
            'video' => $videoGrants,
        ]));

        $signature = $this->base64urlEncode(
            hash_hmac('sha256', "$header.$payload", $this->apiSecret, true)
        );

        return "$header.$payload.$signature";
    }

    /** Generate unique room name */
    public function newRoomName(string $prefix = 'room'): string
    {
        return $prefix . '_' . uniqid() . '_' . time();
    }

    /** LiveKit server URL for clients to connect */
    public function serverUrl(): string
    {
        return $this->host;
    }

    private function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
