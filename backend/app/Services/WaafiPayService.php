<?php

namespace App\Services;

use App\Helpers\AppSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WaafiPayService
{
    private string $apiUrl;
    private string $merchantUid;
    private string $apiUserId;
    private string $apiKey;
    private string $description;

    public function __construct()
    {
        // Credentials from settings table (admin-configurable), fallback to env
        $this->apiUrl      = (string)(AppSettings::get('waafi_api_url', config('services.waafi_pay.api_url', 'https://api.waafipay.net/asm')) ?? 'https://api.waafipay.net/asm');
        $this->merchantUid = (string)(AppSettings::get('waafi_merchant_uid', config('services.waafi_pay.merchant_uid', '')) ?? '');
        $this->apiUserId   = (string)(AppSettings::get('waafi_api_user_id', config('services.waafi_pay.api_user_id', '')) ?? '');
        $this->apiKey      = (string)(AppSettings::get('waafi_api_key', config('services.waafi_pay.api_key', '')) ?? '');
        $this->description = (string)(AppSettings::get('waafi_description', config('services.waafi_pay.description', 'eSahlan Payment')) ?? 'eSahlan Payment');
    }

    public function isConfigured(): bool
    {
        return !empty($this->merchantUid) && !empty($this->apiUserId) && !empty($this->apiKey);
    }

    /**
     * Initiate a payment request — sends confirmation prompt to customer's phone.
     * Returns ['success', 'reference', 'gateway_reference', 'response_code', 'message', 'raw']
     */
    public function initiatePayment(string $phone, float $amount, string $reference, string $description = ''): array
    {
        $phone = $this->normalizePhone($phone);

        $payload = [
            'schemaVersion' => '1.0',
            'requestId'     => 'REQ-' . strtoupper(Str::random(10)),
            'timestamp'     => now()->format('Y-m-d H:i:s'),
            'channelName'   => 'WEB',
            'serviceName'   => 'API_PURCHASE',
            'serviceParams' => [
                'merchantUid'     => $this->merchantUid,
                'apiUserId'       => $this->apiUserId,
                'apiKey'          => $this->apiKey,
                'paymentMethod'   => 'MWALLET_ACCOUNT',
                'payerInfo'       => ['accountNo' => $phone],
                'transactionInfo' => [
                    'referenceId' => $reference,
                    'invoiceId'   => $reference,
                    'amount'      => number_format($amount, 2, '.', ''),
                    'currency'    => 'USD',
                    'description' => $description ?: $this->description,
                ],
            ],
        ];

        try {
            $response = Http::timeout(30)->post($this->apiUrl, $payload);
            $data     = $response->json();

            Log::info('WaafiPay initiatePayment', ['ref' => $reference, 'code' => $data['responseCode'] ?? null]);

            $code = $data['responseCode'] ?? '';

            // 2001 = approved immediately, 5310 = pending customer confirmation
            $success = in_array($code, ['2001', '5310']);

            return [
                'success'           => $success,
                'reference'         => $reference,
                'gateway_reference' => $data['params']['transactionId'] ?? ($data['params']['referenceId'] ?? null),
                'response_code'     => $code,
                'message'           => $data['responseMsg'] ?? ($success ? 'Payment initiated' : 'Payment failed'),
                'raw'               => $data,
                'status'            => $code === '2001' ? 'success' : ($success ? 'pending' : 'failed'),
            ];
        } catch (\Throwable $e) {
            Log::error('WaafiPay initiatePayment error', ['error' => $e->getMessage(), 'ref' => $reference]);
            return [
                'success'           => false,
                'reference'         => $reference,
                'gateway_reference' => null,
                'response_code'     => 'ERR',
                'message'           => 'Payment gateway error: ' . $e->getMessage(),
                'raw'               => [],
                'status'            => 'failed',
            ];
        }
    }

    /**
     * Check status of a pending payment.
     */
    public function checkStatus(string $reference): array
    {
        $payload = [
            'schemaVersion' => '1.0',
            'requestId'     => 'STAT-' . strtoupper(Str::random(10)),
            'timestamp'     => now()->format('Y-m-d H:i:s'),
            'channelName'   => 'WEB',
            'serviceName'   => 'API_ENQUIRY',
            'serviceParams' => [
                'merchantUid' => $this->merchantUid,
                'apiUserId'   => $this->apiUserId,
                'apiKey'      => $this->apiKey,
                'transactionInfo' => [
                    'referenceId' => $reference,
                ],
            ],
        ];

        try {
            $response = Http::timeout(15)->post($this->apiUrl, $payload);
            $data     = $response->json();
            $code     = $data['responseCode'] ?? '';

            return [
                'success'       => $code === '2001',
                'response_code' => $code,
                'message'       => $data['responseMsg'] ?? 'Unknown status',
                'status'        => $code === '2001' ? 'success' : ($code === '5310' ? 'pending' : 'failed'),
                'raw'           => $data,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'response_code' => 'ERR', 'message' => $e->getMessage(), 'status' => 'failed', 'raw' => []];
        }
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);
        // Somalia numbers: 61xxxxxxx or 252xxxxxxx → keep as-is
        return $phone;
    }
}
