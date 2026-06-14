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
        if (!$p) return null;
        return str_starts_with($p, 'http') ? $p : url('/api/v1/img/' . $p);
    }

    // GET /egrocery/categories
    public function categories()
    {
        $module = DB::table('modules')->where('slug', 'egrocery')->first();
        $cats = DB::table('categories')
            ->where('module_id', $module?->id)
            ->whereNull('vendor_id')
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
            ->join('vendors', 'products.vendor_id', '=', 'vendors.id')
            ->where('vendors.status', 'active');

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

    // POST /egrocery/order (auth)
    public function createOrder(Request $request)
    {
        $v = Validator::make($request->all(), [
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'delivery_address'   => 'required|array',
            'payment_method'     => 'required|in:wallet,cod',
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

        $deliveryFee = 1.50;
        $grandTotal  = $total + $deliveryFee;

        if ($request->payment_method === 'wallet') {
            if (!$user->wallet || $user->wallet->balance < $grandTotal) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $order = DB::transaction(function () use ($request, $user, $total, $deliveryFee, $grandTotal, $lines) {
            $order = Order::create([
                'order_number'    => 'GRC-' . strtoupper(Str::random(8)),
                'user_id'         => $user->id,
                'module_slug'     => 'egrocery',
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'payment_status'  => $request->payment_method === 'wallet' ? 'paid' : 'unpaid',
                'delivery_address'=> $request->delivery_address,
                'subtotal'        => $total,
                'delivery_fee'    => $deliveryFee,
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

        return response()->json([
            'success' => true, 'message' => 'Grocery order placed!',
            'data'    => ['order_number' => $order->order_number, 'total' => $grandTotal],
        ], 201);
    }
}
