<?php

namespace App\Http\Controllers\Api\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalOrder;
use App\Models\Global\GlobalOrderItem;
use App\Models\Global\GlobalPayment;
use App\Models\Global\GlobalProduct;
use App\Models\Global\GlobalShippingZone;
use App\Models\Global\GlobalSetting;
use App\Services\StripeService;
use App\Services\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GlobalCheckoutController extends Controller
{
    public function __construct(
        private StripeService  $stripe,
        private PayPalService  $paypal,
    ) {}

    /** GET /checkout/summary */
    public function summary(Request $request)
    {
        $userId = $request->user('global_users')->id;
        $items  = DB::table('global_cart_items as c')
            ->join('global_products as p', 'p.id', '=', 'c.global_product_id')
            ->where('c.global_user_id', $userId)
            ->select('c.*', 'p.name', 'p.price', 'p.thumbnail', 'p.weight')
            ->get();

        if ($items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty.'], 422);
        }

        $subtotal = $items->sum(fn($i) => $i->price * $i->quantity);
        $country  = $request->query('country', $request->user('global_users')->country ?? 'US');
        $shipping = $this->calcShipping($country, $subtotal);

        return response()->json([
            'subtotal'   => round($subtotal, 2),
            'shipping'   => $shipping['amount'],
            'shipping_label' => $shipping['label'],
            'tax'        => 0,
            'total'      => round($subtotal + $shipping['amount'], 2),
            'items_count'=> $items->sum('quantity'),
        ]);
    }

    /** POST /checkout/stripe */
    public function stripe(Request $request)
    {
        $data = $request->validate([
            'address_id'        => 'nullable|integer',
            'shipping_name'     => 'required_without:address_id|string',
            'shipping_address1' => 'required_without:address_id|string',
            'shipping_city'     => 'required_without:address_id|string',
            'shipping_zip'      => 'required_without:address_id|string',
            'shipping_country'  => 'required_without:address_id|string|size:2',
            'notes'             => 'nullable|string',
        ]);

        $user   = $request->user('global_users');
        $userId = $user->id;

        [$order, $total] = $this->createPendingOrder($userId, $data, $user);

        $successUrl = GlobalSetting::getValue('global_app_url', 'https://esahlan.com')
            . '/global/orders/' . $order->id . '?success=1';
        $cancelUrl  = GlobalSetting::getValue('global_app_url', 'https://esahlan.com')
            . '/global/checkout?cancelled=1';

        $session = $this->stripe->createCheckoutSession([
            'amount'       => (int)round($total * 100),
            'currency'     => 'usd',
            'description'  => 'eSahlan Global Order #' . $order->order_number,
            'success_url'  => $successUrl,
            'cancel_url'   => $cancelUrl,
            'metadata'     => ['order_id' => $order->id],
        ]);

        GlobalPayment::create([
            'global_order_id'   => $order->id,
            'method'            => 'stripe',
            'amount'            => $total,
            'currency'          => 'usd',
            'status'            => 'pending',
            'transaction_id'    => $session['id'],
        ]);

        return response()->json(['checkout_url' => $session['url']]);
    }

    /** POST /checkout/paypal */
    public function paypal(Request $request)
    {
        $data = $request->validate([
            'address_id'        => 'nullable|integer',
            'shipping_name'     => 'required_without:address_id|string',
            'shipping_address1' => 'required_without:address_id|string',
            'shipping_city'     => 'required_without:address_id|string',
            'shipping_zip'      => 'required_without:address_id|string',
            'shipping_country'  => 'required_without:address_id|string|size:2',
            'notes'             => 'nullable|string',
        ]);

        $user   = $request->user('global_users');
        $userId = $user->id;

        [$order, $total] = $this->createPendingOrder($userId, $data, $user);

        $returnUrl = GlobalSetting::getValue('global_app_url', 'https://esahlan.com')
            . '/global/orders/' . $order->id . '?success=1';
        $cancelUrl = GlobalSetting::getValue('global_app_url', 'https://esahlan.com')
            . '/global/checkout?cancelled=1';

        $pp = $this->paypal->createOrder($total, 'USD', $returnUrl, $cancelUrl, [
            'order_id' => (string)$order->id,
        ]);

        GlobalPayment::create([
            'global_order_id'   => $order->id,
            'method'            => 'paypal',
            'amount'            => $total,
            'currency'          => 'usd',
            'status'            => 'pending',
            'transaction_id'    => $pp['id'],
        ]);

        $approveLink = collect($pp['links'])->firstWhere('rel', 'approve');

        return response()->json(['checkout_url' => $approveLink['href']]);
    }

    /** POST /checkout/stripe/webhook */
    public function stripeWebhook(Request $request)
    {
        $sig    = $request->header('Stripe-Signature');
        $secret = GlobalSetting::getValue('global_stripe_webhook_secret');
        $event  = $this->stripe->constructWebhookEvent($request->getContent(), $sig, $secret);

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $orderId = $session->metadata->order_id ?? null;
            if ($orderId) {
                $this->markOrderPaid((int)$orderId, $session->id, 'stripe');
            }
        }

        return response()->json(['ok' => true]);
    }

    /** POST /checkout/paypal/webhook */
    public function paypalWebhook(Request $request)
    {
        $eventType = $request->input('event_type');
        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $resource    = $request->input('resource', []);
            $customId    = $resource['custom_id'] ?? null;
            $captureId   = $resource['id'] ?? null;
            if ($customId) {
                $this->markOrderPaid((int)$customId, $captureId, 'paypal');
            }
        }
        return response()->json(['ok' => true]);
    }

    private function createPendingOrder(int $userId, array $data, $user): array
    {
        $items = DB::table('global_cart_items as c')
            ->join('global_products as p', 'p.id', '=', 'c.global_product_id')
            ->where('c.global_user_id', $userId)
            ->select('c.*', 'p.name', 'p.price', 'p.thumbnail', 'p.track_stock', 'p.stock')
            ->get();

        if ($items->isEmpty()) {
            abort(422, 'Cart is empty.');
        }

        $subtotal = $items->sum(fn($i) => $i->price * $i->quantity);
        $country  = $data['shipping_country'] ?? $user->country ?? 'US';
        $shipping = $this->calcShipping($country, $subtotal);
        $total    = round($subtotal + $shipping['amount'], 2);

        // Resolve address
        if (!empty($data['address_id'])) {
            $addr = DB::table('global_addresses')->where('id', $data['address_id'])->where('global_user_id', $userId)->first();
            if ($addr) {
                $data['shipping_name']    = $addr->name;
                $data['shipping_address1']= $addr->address_line1;
                $data['shipping_city']    = $addr->city;
                $data['shipping_zip']     = $addr->zip;
                $data['shipping_country'] = $addr->country;
            }
        }

        $order = GlobalOrder::create([
            'global_user_id'      => $userId,
            'order_number'        => 'GBL-' . strtoupper(Str::random(8)),
            'status'              => 'pending',
            'subtotal'            => $subtotal,
            'shipping_cost'       => $shipping['amount'],
            'tax'                 => 0,
            'total'               => $total,
            'currency'            => 'usd',
            'shipping_name'       => $data['shipping_name'] ?? $user->name,
            'shipping_address1'   => $data['shipping_address1'] ?? '',
            'shipping_city'       => $data['shipping_city'] ?? '',
            'shipping_zip'        => $data['shipping_zip'] ?? '',
            'shipping_country'    => $country,
            'notes'               => $data['notes'] ?? null,
        ]);

        foreach ($items as $item) {
            GlobalOrderItem::create([
                'global_order_id'   => $order->id,
                'global_product_id' => $item->global_product_id,
                'product_name'      => $item->name,
                'variant'           => $item->variant,
                'quantity'          => $item->quantity,
                'unit_price'        => $item->price,
                'total'             => round($item->price * $item->quantity, 2),
            ]);

            if ($item->track_stock) {
                DB::table('global_products')
                    ->where('id', $item->global_product_id)
                    ->decrement('stock', $item->quantity);
            }
        }

        // Clear cart
        DB::table('global_cart_items')->where('global_user_id', $userId)->delete();

        return [$order, $total];
    }

    private function markOrderPaid(int $orderId, ?string $txId, string $method): void
    {
        GlobalOrder::where('id', $orderId)->update([
            'status'     => 'paid',
            'paid_at'    => now(),
        ]);

        GlobalPayment::where('global_order_id', $orderId)
            ->where('method', $method)
            ->update([
                'status'         => 'paid',
                'transaction_id' => $txId,
                'paid_at'        => now(),
            ]);
    }

    private function calcShipping(string $country, float $subtotal): array
    {
        $zone = GlobalShippingZone::where('is_active', true)
            ->get()
            ->first(function ($z) use ($country) {
                $countries = is_array($z->countries) ? $z->countries : json_decode($z->countries, true);
                return in_array('*', $countries ?? []) || in_array(strtoupper($country), $countries ?? []);
            });

        if (!$zone) {
            return ['amount' => 9.99, 'label' => 'Standard Shipping'];
        }

        if ($zone->free_shipping_over && $subtotal >= $zone->free_shipping_over) {
            return ['amount' => 0, 'label' => 'Free Shipping'];
        }

        return [
            'amount' => $zone->flat_rate,
            'label'  => $zone->name . ' (' . $zone->estimated_days_min . '-' . $zone->estimated_days_max . ' days)',
        ];
    }
}
