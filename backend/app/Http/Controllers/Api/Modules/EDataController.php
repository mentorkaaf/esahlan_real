<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\DataPackage;
use App\Models\DataProvider;
use App\Models\Order;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Services\LoyaltyService;

class EDataController extends Controller
{
    private function imgUrl(?string $v): ?string {
        return cdn_url($v);
    }

    // GET /edata/providers
    public function providers()
    {
        $providers = DataProvider::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'logo', 'color', 'sort_order']);

        $result = $providers->map(function ($p) {
            return array_merge($p->toArray(), [
                'logo_url' => $this->imgUrl($p->logo),
            ]);
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    // GET /edata/providers/{id}/packages
    public function packages($id)
    {
        $packages = DataPackage::where('provider_id', $id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'provider_id', 'name', 'category', 'image', 'data_amount', 'speed', 'validity_days', 'price', 'description', 'is_featured']);

        $result = $packages->map(function ($p) {
            return array_merge($p->toArray(), [
                'image_url' => $this->imgUrl($p->image),
            ]);
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    // GET /edata/providers/{id}/bundles
    public function bundles($id)
    {
        $bundles = DB::table('data_bundles')
            ->where('provider_id', $id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'provider_id', 'package_id', 'name', 'description', 'image', 'data_amount', 'voice_minutes', 'sms_count', 'validity_days', 'price', 'badge_label']);

        $result = $bundles->map(function ($b) {
            $arr = (array)$b;
            $arr['image_url'] = $this->imgUrl($b->image);
            return $arr;
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    // GET /edata/packages/{id}/bundles
    public function packageBundles($id)
    {
        $bundles = DB::table('data_bundles')
            ->where('package_id', $id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'provider_id', 'package_id', 'name', 'description', 'image', 'data_amount', 'voice_minutes', 'sms_count', 'validity_days', 'price', 'badge_label']);

        $result = $bundles->map(function ($b) {
            $arr = (array)$b;
            $arr['image_url'] = $this->imgUrl($b->image);
            return $arr;
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    // GET /edata/all — all providers with their packages+bundles in one call
    public function all()
    {
        $providers = DataProvider::where('is_active', true)->orderBy('sort_order')->get();

        $result = $providers->map(function ($p) {
            $packages = DataPackage::where('provider_id', $p->id)->where('is_active', true)
                ->orderBy('category')->orderBy('sort_order')->get();
            $bundles = DB::table('data_bundles')->where('provider_id', $p->id)->where('is_active', true)
                ->orderBy('sort_order')->get();
            return [
                'id'       => $p->id,
                'name'     => $p->name,
                'logo'     => $this->imgUrl($p->logo),
                'color'    => $p->color ?? '#1a73e8',
                'packages' => $packages,
                'bundles'  => $bundles,
            ];
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    // POST /edata/purchase (auth)
    public function purchasePackage(Request $request)
    {
        $v = Validator::make($request->all(), [
            'package_id'     => 'nullable|exists:data_packages,id',
            'bundle_id'      => 'nullable|integer',
            'phone_number'   => 'required|string|min:7',
            'payment_method' => 'required|in:wallet,waafi_pay,mobile_pay',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);
        if (empty($request->package_id) && empty($request->bundle_id)) {
            return response()->json(['success' => false, 'message' => 'Select a package or bundle'], 422);
        }

        $user = $request->user();

        // Get item (package or bundle)
        if (!empty($request->package_id)) {
            $item = DataPackage::find($request->package_id);
            $itemName = $item->name ?? 'Package';
            $price = (float)($item->price ?? 0);
            $type = 'package';
        } else {
            $item = DB::table('data_bundles')->find($request->bundle_id);
            $itemName = $item->name ?? 'Bundle';
            $price = (float)($item->price ?? 0);
            $type = 'bundle';
        }

        // Points redeem
        $loyalty = LoyaltyService::processOrderRequest($request, $user->id, $price, 'edata');
        $price   = round(max(0, $price - $loyalty['points_discount']), 2);

        if ($request->payment_method === 'wallet') {
            $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
            if ($wallet->balance < $price) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $order = DB::transaction(function () use ($request, $user, $item, $price, $itemName, $type, $loyalty) {
            $ref = 'DATA-' . strtoupper(Str::random(8));

            $order = Order::create([
                'order_number'    => $ref,
                'user_id'         => $user->id,
                'module_slug'     => 'edata',
                'status'          => 'confirmed',
                'payment_method'  => $request->payment_method,
                'payment_status'  => $request->payment_method === 'wallet' ? 'paid' : 'unpaid',
                'delivery_address'=> ['phone' => $request->phone_number],
                'subtotal'        => $price,
                'delivery_fee'    => 0,
                'total_amount'    => $price,
                'points_used'     => $loyalty['points_used'],
                'points_discount' => $loyalty['points_discount'],
                'note'            => json_encode([
                    'type'         => $type,
                    'item_id'      => $item->id,
                    'item_name'    => $itemName,
                    'phone_number' => $request->phone_number,
                    'price'        => $price,
                    'data_amount'  => $item->data_amount ?? null,
                    'validity_days'=> $item->validity_days ?? null,
                ]),
                'placed_at' => now(),
            ]);

            if ($request->payment_method === 'wallet') {
                $wallet = Wallet::getOrCreateFor('App\\Models\\User', $user->id);
                $wallet->debit($price, "eData: {$itemName} → {$request->phone_number}", 'App\\Models\\Order', $order->id);
            }

            return $order;
        });

        // ── Push notification: order placed ──────────────────────────────
        try {
            if (!empty($user->fcm_token)) {
                \App\Services\FcmService::sendOrderUpdate(
                    $user->fcm_token,
                    $order->order_number,
                    'pending',
                    $order->id,
                    'edata',
                );
            }
        } catch (\Throwable) {}
        return response()->json([
            'success' => true,
            'message' => "Data {$type} sent to {$request->phone_number}! 📱",
            'data'    => [
                'order_number' => $order->order_number,
                'item_name'    => $itemName,
                'phone_number' => $request->phone_number,
                'total'        => $price,
            ],
        ], 201);
    }

    public function purchaseHistory()
    {
        $user = request()->user();
        if (!$user) return response()->json(['success' => true, 'data' => []]);
        $history = \DB::table('orders')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')->limit(50)->get();
        return response()->json(['success' => true, 'data' => $history]);
    }

    public function favorites()
    {
        $user = request()->user();
        if (!$user) return response()->json(['success' => true, 'data' => []]);
        $favs = \DB::table('edata_favorites')->where('user_id', $user->id)->get();
        return response()->json(['success' => true, 'data' => $favs]);
    }

    public function toggleFavorite()
    {
        $user = request()->user();
        if (!$user) return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        return response()->json(['success' => true, 'data' => []]);
    }

}
