<?php

namespace App\Services\Global;

use App\Models\Global\GlobalSetting;
use App\Models\Global\GlobalOrder;
use App\Models\Global\GlobalPayment;
use Exception;

class StripeService
{
    private array $config;

    public function __construct()
    {
        $this->config = GlobalSetting::stripe();
    }

    public function isEnabled(): bool
    {
        return $this->config['enabled'] && !empty($this->config['secret_key']);
    }

    public function getPublicKey(): string
    {
        return $this->config['public_key'] ?? '';
    }

    /**
     * Create a Stripe PaymentIntent
     * Returns ['client_secret' => '...', 'payment_intent_id' => '...']
     */
    public function createPaymentIntent(GlobalOrder $order): array
    {
        if (!$this->isEnabled()) {
            throw new Exception('Stripe payments are not enabled.');
        }

        $this->setApiKey();

        $intent = \Stripe\PaymentIntent::create([
            'amount'               => (int) round($order->total * 100), // cents
            'currency'             => strtolower($order->currency),
            'payment_method_types' => ['card'],   // Card only — no Link, Bank, CashApp, AmazonPay
            'metadata'             => [
                'order_number' => $order->order_number,
                'order_id'     => (string) $order->id,
                'user_id'      => (string) $order->global_user_id,
            ],
            'description' => "eSahlan Global Order #{$order->order_number}",
        ]);

        return [
            'client_secret'     => $intent->client_secret,
            'payment_intent_id' => $intent->id,
        ];
    }

    /**
     * Verify a completed payment and record it
     */
    public function confirmPayment(GlobalOrder $order, string $paymentIntentId): GlobalPayment
    {
        $this->setApiKey();

        $intent = \Stripe\PaymentIntent::retrieve($paymentIntentId);

        if ($intent->status !== 'succeeded') {
            throw new Exception("Payment not completed. Status: {$intent->status}");
        }

        $payment = GlobalPayment::create([
            'global_order_id'  => $order->id,
            'global_user_id'   => $order->global_user_id,
            'method'           => 'stripe',
            'transaction_id'   => $paymentIntentId,
            'amount'           => $order->total,
            'currency'         => $order->currency,
            'status'           => 'completed',
            'gateway_response' => $intent->toArray(),
        ]);

        $order->update([
            'payment_status'     => 'paid',
            'payment_intent_id'  => $paymentIntentId,
            'status'             => 'processing',
        ]);

        return $payment;
    }

    /**
     * Issue a refund
     */
    public function refund(GlobalPayment $payment, float $amount = null): void
    {
        $this->setApiKey();

        $refundAmount = $amount ?? $payment->amount;

        $refund = \Stripe\Refund::create([
            'payment_intent' => $payment->transaction_id,
            'amount'         => (int) round($refundAmount * 100),
        ]);

        $payment->update([
            'status'           => $amount && $amount < $payment->amount ? 'partially_refunded' : 'refunded',
            'refunded_amount'  => $payment->refunded_amount + $refundAmount,
            'refund_id'        => $refund->id,
        ]);

        $payment->order->update([
            'payment_status' => 'refunded',
            'status'         => 'refunded',
        ]);
    }

    /**
     * Create a Stripe Checkout Session (web redirect flow)
     * Returns ['session_id' => '...', 'session_url' => '...']
     */
    public function createCheckoutSession(GlobalOrder $order): array
    {
        if (!$this->isEnabled()) {
            throw new Exception('Stripe payments are not enabled.');
        }

        $this->setApiKey();

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency'     => strtolower($order->currency),
                    'product_data' => ['name' => "eSahlan Global Order #{$order->order_number}"],
                    'unit_amount'  => (int) round($order->total * 100),
                ],
                'quantity' => 1,
            ]],
            'mode'        => 'payment',
            'success_url' => 'https://global.esahlan.com/#/global/orders?payment=success&order_id=' . $order->id,
            'cancel_url'  => 'https://global.esahlan.com/#/global/cart',
            'metadata'    => [
                'order_id'     => (string) $order->id,
                'order_number' => $order->order_number,
                'user_id'      => (string) $order->global_user_id,
            ],
        ]);

        return [
            'session_id'  => $session->id,
            'session_url' => $session->url,
        ];
    }

    /**
     * Handle Stripe webhook
     */
    public function handleWebhook(string $payload, string $signature): array
    {
        $webhookSecret = $this->config['webhook'];
        $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);

        switch ($event->type) {
            case 'payment_intent.succeeded':
                $pi = $event->data->object;
                $order = GlobalOrder::where('payment_intent_id', $pi->id)->first();
                if ($order && $order->payment_status !== 'paid') {
                    $order->update(['payment_status' => 'paid', 'status' => 'processing']);
                }
                break;

            case 'payment_intent.payment_failed':
                $pi = $event->data->object;
                $order = GlobalOrder::where('payment_intent_id', $pi->id)->first();
                if ($order) {
                    $order->update(['payment_status' => 'failed']);
                }
                break;

            case 'checkout.session.completed':
                $session = $event->data->object;
                $orderId = $session->metadata->order_id ?? null;
                if ($orderId) {
                    $order = GlobalOrder::find($orderId);
                    if ($order && $order->payment_status !== 'paid') {
                        $order->update([
                            'payment_status'    => 'paid',
                            'status'            => 'processing',
                            'payment_intent_id' => $session->payment_intent,
                        ]);
                    }
                }
                break;
        }

        return ['received' => true, 'type' => $event->type];
    }

    private function setApiKey(): void
    {
        if (!class_exists('\Stripe\Stripe')) {
            throw new Exception('Stripe PHP SDK not installed. Run: composer require stripe/stripe-php');
        }
        \Stripe\Stripe::setApiKey($this->config['secret_key']);
    }
}
