<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\DataPackage;
use App\Models\DataProvider;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EDataController extends Controller
{
    // GET /edata/providers
    public function providers()
    {
        $providers = DataProvider::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'logo', 'color', 'sort_order']);

        $result = $providers->map(function ($p) {
            return array_merge($p->toArray(), [
                'logo_url' => $p->logo ? asset('storage/' . $p->logo) : null,
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
                'image_url' => $p->image ? asset('storage/' . $p->image) : null,
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
            $arr['image_url'] = $b->image ? asset('storage/' . $b->image) : null;
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
            $arr['image_url'] = $b->image ? asset('storage/' . $b->image) : null;
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
                'logo'     => $p->logo,
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
            'payment_method' => 'required|in:wallet,cod',
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

        if ($request->payment_method === 'wallet') {
            $wallet = $user->wallet;
            if (!$wallet || $wallet->balance < $price) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $order = DB::transaction(function () use ($request, $user, $item, $price, $itemName, $type) {
            $ref = 'DATA-' . strtoupper(Str::random(8));

            $order = Order::create([
                'order_number'    => $ref,
                'user_id'         => $user->id,
                'module_slug'     => 'edata',
                'status'          => 'confirmed',
                'payment_method'  => $request->payment_method,
                'payment_status'  => $request->payment_method === 'wallet' ? 'paid' : 'pending',
                'delivery_address'=> ['phone' => $request->phone_number],
                'subtotal'        => $price,
                'delivery_fee'    => 0,
                'total_amount'    => $price,
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
                $wallet = $user->wallet;
                $wallet->decrement('balance', $price);
                DB::table('wallet_transactions')->insert([
                    'wallet_id'   => $wallet->id,
                    'type'        => 'debit',
                    'amount'      => $price,
                    'description' => "eData: {$itemName} → {$request->phone_number}",
                    'reference_id'=> $order->id,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }

            return $order;
        });

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
}
