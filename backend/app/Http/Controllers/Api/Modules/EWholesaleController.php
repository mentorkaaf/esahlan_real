<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EWholesaleController extends Controller
{
    // GET /ewholesale/categories
    public function categories()
    {
        $module = DB::table('modules')->where('slug', 'ewholesale')->first();
        $cats = DB::table('categories')
            ->where('module_id', $module?->id)
            ->whereNull('vendor_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'image', 'slug']);

        return response()->json(['success' => true, 'data' => $cats]);
    }

    // GET /ewholesale/products?category_id=&search=
    public function products(Request $request)
    {
        $module = DB::table('modules')->where('slug', 'ewholesale')->first();

        $query = DB::table('products')
            ->where('products.module_id', $module?->id)
            ->where('products.is_available', true)
            ->select([
                'products.id', 'products.name', 'products.price', 'products.thumbnail',
                'products.min_qty', 'products.unit', 'products.stock_quantity',
                'products.description', 'products.is_featured',
                'categories.name as category_name',
                'vendors.name as supplier_name',
            ])
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->join('vendors', 'products.vendor_id', '=', 'vendors.id')
            ->where('vendors.status', 'active');

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $query->where('products.name', 'like', '%' . $request->search . '%');
        }

        $products = $query->orderByDesc('products.is_featured')->paginate(20);

        return response()->json([
            'success' => true,
            'data'    => $products->items(),
            'meta'    => ['total' => $products->total(), 'last_page' => $products->lastPage()],
        ]);
    }

    // POST /ewholesale/inquire (auth)
    public function inquire(Request $request)
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
            $sub    = $product->price * $item['quantity'];
            $total += $sub;
            $lines[] = ['product_id' => $product->id, 'name' => $product->name, 'price' => $product->price, 'qty' => $item['quantity'], 'sub' => $sub];
        }

        if ($request->payment_method === 'wallet') {
            if (!$user->wallet || $user->wallet->balance < $total) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $order = DB::transaction(function () use ($request, $user, $total, $lines) {
            $order = Order::create([
                'order_number'    => 'WHL-' . strtoupper(Str::random(8)),
                'user_id'         => $user->id,
                'module_slug'     => 'ewholesale',
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'payment_status'  => $request->payment_method === 'wallet' ? 'paid' : 'pending',
                'delivery_address'=> $request->delivery_address,
                'subtotal'        => $total,
                'delivery_fee'    => 0,
                'total_amount'    => $total,
                'note'            => json_encode(['items' => $lines, 'note' => $request->note]),
                'placed_at'       => now(),
            ]);

            foreach ($lines as $line) {
                DB::table('order_items')->insert([
                    'order_id'     => $order->id, 'product_id' => $line['product_id'],
                    'product_name' => $line['name'], 'price' => $line['price'],
                    'quantity'     => $line['qty'], 'subtotal' => $line['sub'],
                    'created_at'   => now(), 'updated_at' => now(),
                ]);
            }

            OrderStatusHistory::create([
                'order_id' => $order->id, 'status' => 'pending',
                'note' => 'Wholesale inquiry placed', 'actor_id' => $user->id,
                'actor_type' => 'App\\Models\\User',
            ]);

            if ($request->payment_method === 'wallet') {
                $user->wallet->decrement('balance', $total);
            }

            return $order;
        });

        return response()->json([
            'success' => true, 'message' => 'Wholesale order submitted!',
            'data'    => ['order_number' => $order->order_number, 'total' => $total],
        ], 201);
    }
}
