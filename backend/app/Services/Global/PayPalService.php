<?php

namespace App\Services\Global;

use App\Models\Global\GlobalSetting;
use App\Models\Global\GlobalOrder;
use App\Models\Global\GlobalPayment;
use Illuminate\Support\Facades\Http;
use Exception;

class PayPalService
{
    private array  $config;
    private string $baseUrl;

    public function __construct()
    {
        $this->config  = GlobalSetting::paypal();
        $this->baseUrl = $this->config['test_mode']
            ? 'https://api-m.sandbox.paypal.com'
            : 'https://api-m.paypal.com';
    }

    public function isEnabled(): bool
    {
        return $this->config['enabled'] && !empty($this->config['client_id']);
    }

    /**
     * Get OAuth2 access token
     */
    private function getAccessToken(): string
    {
        $response = Http::withBasicAuth($this->config['client_id'], $this->config['secret'])
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

        if (!$response->successful()) {
            throw new Exception('PayPal authentication failed: ' . $response->body());
        }

        return $response->json('access_token');
    }

    /**
     * Create a PayPal order
     * Returns ['paypal_order_id' => '...', 'approve_url' => '...']
     */
    public function createOrder(GlobalOrder $order, string $returnUrl, string $cancelUrl): array
    {
        if (!$this->isEnabled()) {
            throw new Exception('PayPal payments are not enabled.');
        }

        $token    = $this->getAccessToken();
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/v2/checkout/orders", [
                'intent'          => 'CAPTURE',
                'purchase_units'  => [[
                    'reference_id'  => $order->order_number,
                    'description'   => "eSahlan Global Order #{$order->order_number}",
                    'amount'        => [
                        'currency_code' => $order->currency,
                        'value'         => number_format($order->total, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                    'brand_name' => 'eSahlan Global',
                    'user_action' => 'PAY_NOW',
                ],
            ]);

        if (!$response->successful()) {
            throw new Exception('PayPal order creation failed: ' . $response->body());
        }

        $data       = $response->json();
        $approveUrl = collect($data['links'])->firstWhere('rel', 'approve')['href'] ?? '';

        $order->update(['paypal_order_id' => $data['id']]);

        return [
            'paypal_order_id' => $data['id'],
            'approve_url'     => $approveUrl,
        ];
    }

    /**
     * Capture a PayPal order after user approves
     */
    public function captureOrder(GlobalOrder $order): GlobalPayment
    {
        $token    = $this->getAccessToken();
        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/v2/checkout/orders/{$order->paypal_order_id}/capture");

        if (!$response->successful()) {
            throw new Exception('PayPal capture failed: ' . $response->body());
        }

        $data     = $response->json();
        $capture  = $data['purchase_units'][0]['payments']['captures'][0] ?? [];
        $txId     = $capture['id'] ?? $data['id'];

        $payment = GlobalPayment::create([
            'global_order_id'  => $order->id,
            'global_user_id'   => $order->global_user_id,
            'method'           => 'paypal',
            'transaction_id'   => $txId,
            'amount'           => $order->total,
            'currency'         => $order->currency,
            'status'           => 'completed',
            'gateway_response' => $data,
        ]);

        $order->update([
            'payment_status' => 'paid',
            'status'         => 'processing',
        ]);

        return $payment;
    }

    /**
     * Refund a PayPal capture
     */
    public function refund(GlobalPayment $payment, float $amount = null): void
    {
        $token       = $this->getAccessToken();
        $refundAmount = $amount ?? $payment->amount;

        $response = Http::withToken($token)
            ->post("{$this->baseUrl}/v2/payments/captures/{$payment->transaction_id}/refund", [
                'amount' => [
                    'currency_code' => $payment->currency,
                    'value'         => number_format($refundAmount, 2, '.', ''),
                ],
            ]);

        if (!$response->successful()) {
            throw new Exception('PayPal refund failed: ' . $response->body());
        }

        $refundId = $response->json('id');

        $payment->update([
            'status'          => $amount && $amount < $payment->amount ? 'partially_refunded' : 'refunded',
            'refunded_amount' => $payment->refunded_amount + $refundAmount,
            'refund_id'       => $refundId,
        ]);

        $payment->order->update([
            'payment_status' => 'refunded',
            'status'         => 'refunded',
        ]);
    }
}
