<?php

namespace App\Http\Controllers\Api\Global;

use App\Http\Controllers\Controller;
use App\Mail\Global\GlobalOrderConfirmationMail;
use App\Models\Global\GlobalOrder;
use App\Models\Global\GlobalOrderItem;
use App\Models\Global\GlobalShippingZone;
use App\Models\Global\GlobalSetting;
use App\Services\Global\StripeService;
use App\Services\Global\PayPalService;
use App\Services\AdminAlertService;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class GlobalCheckoutController extends Controller
{
    public function __construct(
        private StripeService $stripe,
        private PayPalService $paypal,
    ) {}

    /** GET /checkout/summary */
    public function summary(Request $request)
    {
        $userId = $request->user('global_users')->id;
        $items  = DB::table('global_cart_items as c')
            ->join('global_products as p', 'p.id', '=', 'c.global_product_id')
            ->where('c.global_user_id', $userId)
            ->select('c.*', 'p.name', 'p.thumbnail', 'p.weight')
            ->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }

        $subtotal = $items->sum(fn($i) => $i->price_snapshot * $i->quantity);
        $country  = $request->query('country', $request->user('global_users')->country ?? 'US');
        $shipping = $this->calcShipping($country, $subtotal);

        return response()->json([
            'subtotal'       => round($subtotal, 2),
            'shipping'       => $shipping['amount'],
            'shipping_label' => $shipping['label'],
            'tax'            => 0,
            'total'          => round($subtotal + $shipping['amount'], 2),
            'items_count'    => $items->sum('quantity'),
        ]);
    }

    /** POST /checkout/stripe */
    public function stripe(Request $request)
    {
        $data = $request->validate([
            'address_id'    => 'nullable|integer',
            'first_name'    => 'required_without:address_id|nullable|string|max:100',
            'last_name'     => 'nullable|string|max:100',
            'address_line1' => 'required_without:address_id|nullable|string|max:255',
            'city'          => 'required_without:address_id|nullable|string|max:100',
            'zip'           => 'nullable|string|max:20',
            'country_code'  => 'nullable|string|max:50',  // Accept full name or code; normalized in createPendingOrder
            'country_name'  => 'nullable|string|max:100',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $user  = $request->user('global_users');

        // Cancel any stale pending stripe orders to avoid duplicates
        // payment_status ENUM: pending|paid|failed|refunded (no 'cancelled')
        GlobalOrder::where('global_user_id', $user->id)
            ->where('status', 'pending')
            ->where('payment_method', 'stripe')
            ->where('payment_status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->update(['status' => 'cancelled', 'payment_status' => 'failed']);

        $order = $this->createPendingOrder($user, $data, 'stripe');

        $stripeData = $this->stripe->createPaymentIntent($order);

        $order->update(['payment_intent_id' => $stripeData['payment_intent_id']]);

        return response()->json([
            'order_id'      => $order->id,
            'order_number'  => $order->order_number,
            'total'         => $order->total,
            'client_secret' => $stripeData['client_secret'],
            'public_key'    => $this->stripe->getPublicKey(),
        ]);
    }

    /** POST /checkout/paypal */
    public function paypal(Request $request)
    {
        $data = $request->validate([
            'address_id'    => 'nullable|integer',
            'first_name'    => 'required_without:address_id|nullable|string|max:100',
            'last_name'     => 'nullable|string|max:100',
            'address_line1' => 'required_without:address_id|nullable|string|max:255',
            'city'          => 'required_without:address_id|nullable|string|max:100',
            'zip'           => 'nullable|string|max:20',
            'country_code'  => 'nullable|string|max:50',  // Accept full name or code; normalized in createPendingOrder
            'country_name'  => 'nullable|string|max:100',
            'notes'         => 'nullable|string|max:1000',
            'return_url'    => 'nullable|string',
            'cancel_url'    => 'nullable|string',
        ]);

        $user  = $request->user('global_users');
        $order = $this->createPendingOrder($user, $data, 'paypal');

        $appUrl    = GlobalSetting::getValue('global_app_url', 'https://esahlan.com');
        $returnUrl = $data['return_url'] ?? ($appUrl . '/global/orders/' . $order->id . '?success=1');
        $cancelUrl = $data['cancel_url'] ?? ($appUrl . '/global/checkout?cancelled=1');

        // Service returns ['paypal_order_id' => ..., 'approve_url' => ...]
        // and already persists paypal_order_id on the order internally
        $pp = $this->paypal->createOrder($order, $returnUrl, $cancelUrl);

        return response()->json([
            'order_id'     => $order->id,
            'order_number' => $order->order_number,
            'total'        => $order->total,
            'approval_url' => $pp['approve_url'] ?? null,
            'paypal_id'    => $pp['paypal_order_id'] ?? null,
        ]);
    }

    /** POST /checkout/stripe/confirm */
    public function stripeConfirm(Request $request)
    {
        $request->validate([
            'order_id'           => 'required|integer',
            'payment_intent_id'  => 'required|string',
        ]);

        $user  = $request->user('global_users');
        $order = GlobalOrder::where('id', $request->order_id)
            ->where('global_user_id', $user->id)
            ->firstOrFail();

        $this->stripe->confirmPayment($order, $request->payment_intent_id);

        // Clear cart now that payment is confirmed
        DB::table('global_cart_items')->where('global_user_id', $user->id)->delete();

        $fresh = $order->fresh();

        // Send order confirmation email
        $this->sendOrderConfirmationEmail($fresh, $user->email);

        // Send FCM push notification
        $this->sendOrderFcm($user, $fresh, 'confirmed');

        return response()->json([
            'success'  => true,
            'order_id' => $order->id,
            'status'   => $fresh->status,
        ]);
    }

    /** POST /checkout/paypal/capture */
    public function paypalCapture(Request $request)
    {
        $request->validate(['order_id' => 'required|integer']);

        $user  = $request->user('global_users');
        $order = GlobalOrder::where('id', $request->order_id)
            ->where('global_user_id', $user->id)
            ->firstOrFail();

        $this->paypal->captureOrder($order);

        // Clear cart now that payment is confirmed
        DB::table('global_cart_items')->where('global_user_id', $user->id)->delete();

        $fresh = $order->fresh();

        // Send order confirmation email
        $this->sendOrderConfirmationEmail($fresh, $user->email);

        // Send FCM push notification
        $this->sendOrderFcm($user, $fresh, 'confirmed');

        return response()->json([
            'success'  => true,
            'order_id' => $order->id,
            'status'   => $fresh->status,
        ]);
    }

    /** POST /checkout/stripe-web-session  (web: redirect to hosted Stripe checkout) */
    public function stripeWebSession(Request $request)
    {
        $data = $request->validate([
            'address_id'    => 'nullable|integer',
            'first_name'    => 'required_without:address_id|nullable|string|max:100',
            'last_name'     => 'nullable|string|max:100',
            'address_line1' => 'required_without:address_id|nullable|string|max:255',
            'city'          => 'required_without:address_id|nullable|string|max:100',
            'zip'           => 'nullable|string|max:20',
            'country_code'  => 'nullable|string|max:50',
            'country_name'  => 'nullable|string|max:100',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $user  = $request->user('global_users');

        GlobalOrder::where('global_user_id', $user->id)
            ->where('status', 'pending')
            ->where('payment_method', 'stripe')
            ->where('payment_status', 'pending')
            ->where('created_at', '<', now()->subMinutes(30))
            ->update(['status' => 'cancelled', 'payment_status' => 'failed']);

        $order = $this->createPendingOrder($user, $data, 'stripe');

        $sessionData = $this->stripe->createCheckoutSession($order);

        $order->update(['payment_intent_id' => $sessionData['session_id']]);

        // Send order confirmation FCM
        $this->sendOrderFcm($user, $order->fresh(), 'confirmed');

        return response()->json([
            'order_id'    => $order->id,
            'session_url' => $sessionData['session_url'],
        ]);
    }

    /** POST /checkout/stripe/webhook  (unauthenticated) */
    public function stripeWebhook(Request $request)
    {
        $sig    = $request->header('Stripe-Signature');
        $result = $this->stripe->handleWebhook($request->getContent(), $sig);
        return response()->json($result);
    }

    /** POST /checkout/paypal/webhook  (unauthenticated) */
    public function paypalWebhook(Request $request)
    {
        $eventType = $request->input('event_type');
        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $resource = $request->input('resource', []);
            $ppId     = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
            if ($ppId) {
                $order = GlobalOrder::where('paypal_order_id', $ppId)->first();
                if ($order && $order->payment_status !== 'paid') {
                    $order->update(['payment_status' => 'paid', 'status' => 'processing']);
                }
            }
        }
        return response()->json(['ok' => true]);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function createPendingOrder($user, array $data, string $paymentMethod): GlobalOrder
    {
        $userId = $user->id;

        $items = DB::table('global_cart_items as c')
            ->join('global_products as p', 'p.id', '=', 'c.global_product_id')
            ->where('c.global_user_id', $userId)
            ->select('c.*', 'p.name', 'p.thumbnail', 'p.track_stock', 'p.stock')
            ->get();

        if ($items->isEmpty()) {
            abort(422, 'Cart is empty.');
        }

        // Resolve address from saved address or inline fields
        if (!empty($data['address_id'])) {
            $addr = DB::table('global_addresses')
                ->where('id', $data['address_id'])
                ->where('global_user_id', $userId)
                ->first();
            if ($addr) {
                $nameParts             = explode(' ', $addr->name ?? $user->name ?? 'Guest', 2);
                $data['first_name']    = $addr->first_name  ?? $nameParts[0];
                $data['last_name']     = $addr->last_name   ?? ($nameParts[1] ?? '');
                $data['address_line1'] = $addr->address_line1;
                $data['city']          = $addr->city;
                $data['zip']           = $addr->zip ?? null;
                $rawCountry            = strtoupper(trim($addr->country ?? 'US'));
                $data['country_code']  = strlen($rawCountry) === 2 ? $rawCountry : substr($rawCountry, 0, 2);
                $data['country_name']  = $addr->country_name ?? $addr->country ?? 'United States';
            }
        }

        $subtotal    = $items->sum(fn($i) => $i->price_snapshot * $i->quantity);
        $rawCountry  = strtoupper(trim($data['country_code'] ?? $user->country ?? 'US'));
        $country     = strlen($rawCountry) === 2 ? $rawCountry : substr($rawCountry, 0, 2);
        $shipping    = $this->calcShipping($country, $subtotal);
        $total    = round($subtotal + $shipping['amount'], 2);

        $nameParts = explode(' ', $user->name ?? 'Guest', 2);
        $firstName = $data['first_name'] ?? $nameParts[0];
        $lastName  = $data['last_name']  ?? ($nameParts[1] ?? '');

        $order = GlobalOrder::create([
            'global_user_id'     => $userId,
            'order_number'       => 'GBL-' . strtoupper(Str::random(8)),
            'status'             => 'pending',
            'payment_status'     => 'pending',
            'payment_method'     => $paymentMethod,
            'subtotal'           => $subtotal,
            'shipping_cost'      => $shipping['amount'],
            'tax'                => 0,
            'discount'           => 0,
            'total'              => $total,
            'currency'           => 'USD',
            'ship_first_name'    => $firstName,
            'ship_last_name'     => $lastName,
            'ship_address_line1' => $data['address_line1'] ?? '',
            'ship_city'          => $data['city'] ?? '',
            'ship_zip'           => $data['zip'] ?? '',
            'ship_country_code'  => $country,
            'ship_country_name'  => $data['country_name'] ?? $country,
            'notes'              => $data['notes'] ?? null,
        ]);

        foreach ($items as $item) {
            GlobalOrderItem::create([
                'global_order_id'   => $order->id,
                'global_product_id' => $item->global_product_id,
                'product_name'      => $item->name,
                'product_image'     => $item->thumbnail ?? null,
                'variant_name'      => $item->variant ?? null,
                'quantity'          => $item->quantity,
                'unit_price'        => $item->price_snapshot,
                'total_price'       => round($item->price_snapshot * $item->quantity, 2),
            ]);

            if ($item->track_stock) {
                DB::table('global_products')
                    ->where('id', $item->global_product_id)
                    ->decrement('stock', $item->quantity);

                // Low stock alert if remaining stock <= 5
                $remaining = DB::table('global_products')->where('id', $item->global_product_id)->value('stock');
                if ($remaining !== null && $remaining <= 5) {
                    try {
                        AdminAlertService::send('low_stock',
                            "⚠️ Low Stock: {$item->name} ({$remaining} left)",
                            [
                                'Product'    => $item->name,
                                'Stock Left' => $remaining . ($remaining === 0 ? ' — OUT OF STOCK' : ''),
                                'Threshold'  => '5 units',
                                'Checked At' => now()->format('d M Y H:i') . ' UTC',
                            ],
                            'low_stock_' . $item->global_product_id, 3600
                        );
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('[AdminAlert][low_stock] ' . $e->getMessage());
                    }
                }
            }
        }

        // Cart is cleared only after payment is confirmed (stripeConfirm / paypalCapture)
        // This allows retry if payment sheet fails without losing cart items.

        return $order;
    }

    private function sendOrderConfirmationEmail(GlobalOrder $order, string $email): void
    {
        try {
            $items = DB::table('global_order_items')
                ->where('global_order_id', $order->id)
                ->get()
                ->map(fn($i) => [
                    'name'      => $i->product_name,
                    'thumbnail' => $i->product_image,
                    'variant'   => $i->variant_name,
                    'quantity'  => $i->quantity,
                    'total'     => $i->total_price,
                ])
                ->toArray();

            $addr = implode(', ', array_filter([
                $order->ship_address_line1,
                $order->ship_city,
                $order->ship_zip,
                $order->ship_country_name,
            ]));

            $orderData = [
                'id'               => $order->id,
                'order_number'     => $order->order_number,
                'customer_name'    => trim($order->ship_first_name . ' ' . $order->ship_last_name),
                'payment_method'   => $order->payment_method,
                'subtotal'         => $order->subtotal,
                'shipping'         => $order->shipping_cost,
                'total'            => $order->total,
                'shipping_address' => $addr,
            ];

            Mail::to($email)->queue(new GlobalOrderConfirmationMail($orderData, $items));
        } catch (\Throwable $e) {
            Log::error('GlobalOrderConfirmationMail failed', ['error' => $e->getMessage()]);
        }
    }

    private function sendOrderFcm($user, GlobalOrder $order, string $event): void
    {
        try {
            $fcmToken = $user->fcm_token ?? null;
            if (empty($fcmToken)) return;

            $messages = [
                'confirmed' => [
                    'title' => '✅ Order Confirmed!',
                    'body'  => 'Your order ' . $order->order_number . ' is confirmed. Total: $' . number_format($order->total, 2),
                ],
                'shipped' => [
                    'title' => '🚚 Order Shipped!',
                    'body'  => 'Your order ' . $order->order_number . ' is on its way!',
                ],
                'delivered' => [
                    'title' => '📦 Order Delivered!',
                    'body'  => 'Your order ' . $order->order_number . ' has been delivered.',
                ],
                'cancelled' => [
                    'title' => '❌ Order Cancelled',
                    'body'  => 'Your order ' . $order->order_number . ' has been cancelled.',
                ],
            ];

            $msg = $messages[$event] ?? [
                'title' => '🛍 Order Update',
                'body'  => 'Your order ' . $order->order_number . ' status: ' . $event,
            ];

            FcmService::sendToToken($fcmToken, $msg['title'], $msg['body'], [
                'type'         => 'global_order_update',
                'order_id'     => (string) $order->id,
                'order_number' => $order->order_number,
                'status'       => $event,
                'deep_link'    => '/global/orders',
            ]);
        } catch (\Throwable $e) {
            Log::error('GlobalOrder FCM failed', ['error' => $e->getMessage(), 'order' => $order->order_number]);
        }
    }

    private function calcShipping(string $country, float $subtotal): array
    {
        $zone = GlobalShippingZone::where('is_active', true)
            ->get()
            ->first(function ($z) use ($country) {
                $countries = is_array($z->countries)
                    ? $z->countries
                    : json_decode($z->countries, true);
                return in_array('*', $countries ?? [])
                    || in_array(strtoupper($country), $countries ?? []);
            });

        if (!$zone) {
            return ['amount' => 9.99, 'label' => 'Standard Shipping'];
        }

        if ($zone->free_shipping_over && $subtotal >= $zone->free_shipping_over) {
            return ['amount' => 0, 'label' => 'Free Shipping'];
        }

        return [
            'amount' => $zone->flat_rate,
            'label'  => $zone->name . ' ('
                . $zone->estimated_days_min . '-'
                . $zone->estimated_days_max . ' days)',
        ];
    }
}
