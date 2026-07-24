<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EGroceryController extends Controller
{
    private function resolveImg(?string $p): ?string
    {
        return cdn_url($p);
    }

    // GET /egrocery/categories
    public function categories()
    {
        $module = DB::table('modules')->where('slug', 'egrocery')->first();
        $cats = DB::table('categories')
            ->where('module_id', $module?->id)
            ->whereNull('vendor_id')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'name_so', 'image', 'slug'])
            ->map(fn($c) => array_merge((array)$c, ['image' => $this->resolveImg($c->image)]));

        return response()->json(['success' => true, 'data' => $cats]);
    }

    // GET /egrocery/products?category_id=&search=&featured=
    public function products(Request $request)
    {
        $module = DB::table('modules')->where('slug', 'egrocery')->first();

        $query = DB::table('products')
            ->where('products.module_id', $module?->id)
            ->where('products.is_available', true)
            ->select([
                'products.id', 'products.name', 'products.name_so',
                'products.price', 'products.sale_price', 'products.thumbnail',
                'products.unit', 'products.stock_quantity', 'products.is_featured',
                'categories.name as category_name',
            ])
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->leftJoin('vendors', 'products.vendor_id', '=', 'vendors.id')
            ->where(function ($q) {
                $q->whereNull('products.vendor_id')->orWhere('vendors.status', 'active');
            });

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            $query->where(function ($sq) use ($q) {
                $sq->where('products.name', 'like', $q)->orWhere('products.name_so', 'like', $q);
            });
        }
        if ($request->boolean('featured')) {
            $query->where('products.is_featured', true);
        }

        $products = $query->orderByDesc('products.is_featured')->orderBy('products.sort_order')->paginate(30);

        $items = array_map(fn($p) => array_merge((array)$p, ['thumbnail' => $this->resolveImg(((array)$p)['thumbnail'] ?? null)]), $products->items());

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => ['total' => $products->total(), 'last_page' => $products->lastPage()],
        ]);
    }

    // GET /egrocery/products/{id}
    public function productDetail($id)
    {
        $p = DB::table('products')->find($id);
        if (!$p) return response()->json(['success' => false, 'message' => 'Not found'], 404);

        $cat = $p->category_id ? DB::table('categories')->find($p->category_id) : null;

        $images = array_filter([
            $this->resolveImg($p->thumbnail),
            $this->resolveImg($p->image),
        ]);

        $reviews = collect();
        try {
            $reviews = DB::table('product_reviews')
                ->where('product_id', $id)
                ->join('users', 'product_reviews.user_id', '=', 'users.id')
                ->select('product_reviews.*', 'users.name as user_name')
                ->orderByDesc('product_reviews.created_at')
                ->limit(20)
                ->get()
                ->map(fn($r) => (array) $r);
        } catch (\Throwable) {}

        return response()->json(['success' => true, 'data' => [
            'id'             => $p->id,
            'name'           => $p->name,
            'name_so'        => $p->name_so,
            'description'    => $p->description,
            'price'          => $p->price,
            'sale_price'     => $p->sale_price,
            'images'         => array_values($images),
            'category_name'  => $cat?->name,
            'unit'           => $p->unit,
            'weight'         => $p->weight,
            'stock_quantity'  => $p->stock_quantity,
            'is_available'   => (bool) $p->is_available,
            'is_featured'    => (bool) $p->is_featured,
            'rating'         => (float) ($p->rating ?? 0),
            'total_reviews'  => (int) ($p->total_reviews ?? 0),
            'reviews'        => $reviews,
        ]]);
    }

    // POST /egrocery/order (auth)
    public function createOrder(Request $request)
    {
        $v = Validator::make($request->all(), [
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'delivery_address'   => 'required|array',
            'payment_method'     => 'required|in:wallet,cod,waafi_pay,mobile_pay',
            'waafi_reference'    => 'nullable|string',
            'note'               => 'nullable|string',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user  = $request->user();
        $total = 0;
        $lines = [];

        foreach ($request->items as $item) {
            $product = DB::table('products')->find($item['product_id']);
            if (!$product) continue;
            $price  = $product->sale_price ?? $product->price;
            $sub    = $price * $item['quantity'];
            $total += $sub;
            $lines[] = ['product_id' => $product->id, 'name' => $product->name, 'price' => $price, 'qty' => $item['quantity'], 'sub' => $sub];
        }

        // Delivery fee from zone pricing (Hamarweyne → customer district)
        $customerDistrictId = $request->input('district_id') ?? ($request->delivery_address['district_id'] ?? null);
        $deliveryFee = \App\Helpers\DeliveryPricing::forShopOrLaundry($customerDistrictId ? (int)$customerDistrictId : null, 1.50);

        // Commission
        $module = DB::table('modules')->where('slug', 'egrocery')->first();
        $commissionRate = ($module && $module->commission_value > 0) ? (float) $module->commission_value : 10;
        $commissionAmount = round($total * $commissionRate / 100, 2);

        $grandTotal = round($total + $deliveryFee, 2);

        if ($request->payment_method === 'wallet') {
            if (!$user->wallet || $user->wallet->balance < $grandTotal) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $order = DB::transaction(function () use ($request, $user, $total, $deliveryFee, $grandTotal, $commissionAmount, $lines) {
            $order = Order::create([
                'order_number'    => 'GRC-' . strtoupper(Str::random(8)),
                'user_id'         => $user->id,
                'module_slug'     => 'egrocery',
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'payment_status'  => in_array($request->payment_method, ['wallet', 'waafi_pay']) ? 'paid' : 'unpaid',
                'delivery_address'=> $request->delivery_address,
                'subtotal'        => $total,
                'delivery_fee'    => $deliveryFee,
                'commission'      => $commissionAmount,
                'total_amount'    => $grandTotal,
                'note'            => $request->note,
                'placed_at'       => now(),
            ]);

            foreach ($lines as $line) {
                DB::table('order_items')->insert([
                    'order_id' => $order->id, 'product_id' => $line['product_id'],
                    'product_name' => $line['name'], 'price' => $line['price'],
                    'quantity' => $line['qty'], 'subtotal' => $line['sub'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id, 'status' => 'pending',
                'note' => 'Grocery order placed', 'actor_id' => $user->id,
                'actor_type' => 'App\\Models\\User',
            ]);

            if ($request->payment_method === 'wallet') {
                $user->wallet->decrement('balance', $grandTotal);
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
                    'egrocery',
                );
            }
        } catch (\Throwable) {}
        return response()->json([
            'success' => true, 'message' => 'Grocery order placed!',
            'data'    => ['order_number' => $order->order_number, 'total' => $grandTotal],
        ], 201);
    }
}
