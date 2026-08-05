<?php

namespace App\Http\Controllers\Api\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GlobalCartController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->user('global')->id;
        $items  = $this->getCartItems($userId);
        return response()->json($this->cartSummary($items));
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:global_products,id',
            'quantity'   => 'integer|min:1|max:100',
            'variant'    => 'nullable|string',
        ]);

        $product = GlobalProduct::findOrFail($data['product_id']);
        if (!$product->is_active) {
            return response()->json(['message' => 'Product not available.'], 422);
        }

        $userId   = $request->user('global')->id;
        $qty      = $data['quantity'] ?? 1;
        $variant  = $data['variant'] ?? null;

        $existing = DB::table('global_cart_items')
            ->where('global_user_id', $userId)
            ->where('global_product_id', $data['product_id'])
            ->where('variant', $variant)
            ->first();

        if ($existing) {
            DB::table('global_cart_items')
                ->where('id', $existing->id)
                ->update(['quantity' => $existing->quantity + $qty, 'updated_at' => now()]);
        } else {
            DB::table('global_cart_items')->insert([
                'global_user_id'    => $userId,
                'global_product_id' => $data['product_id'],
                'quantity'          => $qty,
                'variant'           => $variant,
                'price_snapshot'    => $product->price,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);
        }

        $items = $this->getCartItems($userId);
        return response()->json($this->cartSummary($items), 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:100']);
        $userId = $request->user('global')->id;

        DB::table('global_cart_items')
            ->where('id', $id)
            ->where('global_user_id', $userId)
            ->update(['quantity' => $data['quantity'], 'updated_at' => now()]);

        $items = $this->getCartItems($userId);
        return response()->json($this->cartSummary($items));
    }

    public function remove(Request $request, $id)
    {
        $userId = $request->user('global')->id;
        DB::table('global_cart_items')
            ->where('id', $id)
            ->where('global_user_id', $userId)
            ->delete();

        $items = $this->getCartItems($userId);
        return response()->json($this->cartSummary($items));
    }

    public function clear(Request $request)
    {
        $userId = $request->user('global')->id;
        DB::table('global_cart_items')->where('global_user_id', $userId)->delete();
        return response()->json(['items' => [], 'subtotal' => 0, 'count' => 0]);
    }

    private function getCartItems(int $userId): \Illuminate\Support\Collection
    {
        return DB::table('global_cart_items as c')
            ->join('global_products as p', 'p.id', '=', 'c.global_product_id')
            ->where('c.global_user_id', $userId)
            ->select('c.id', 'c.quantity', 'c.variant', 'c.price_snapshot',
                     'p.id as product_id', 'p.name', 'p.price', 'p.thumbnail',
                     'p.track_stock', 'p.stock')
            ->get();
    }

    private function cartSummary(\Illuminate\Support\Collection $items): array
    {
        $mapped = $items->map(fn($i) => [
            'id'          => $i->id,
            'product_id'  => $i->product_id,
            'name'        => $i->name,
            'thumbnail'   => $i->thumbnail,
            'price'       => $i->price,
            'variant'     => $i->variant,
            'quantity'    => $i->quantity,
            'subtotal'    => round($i->price * $i->quantity, 2),
            'in_stock'    => !$i->track_stock || $i->stock >= $i->quantity,
        ]);

        $subtotal = round($mapped->sum('subtotal'), 2);

        return [
            'items'    => $mapped,
            'count'    => $items->sum('quantity'),
            'subtotal' => $subtotal,
        ];
    }
}
