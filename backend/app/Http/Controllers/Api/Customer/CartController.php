<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = Cart::with(['items.product.images', 'items.variant', 'vendor'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$cart) {
            return response()->json(['success' => true, 'data' => ['cart' => null, 'items' => [], 'total' => 0]]);
        }

        $subtotal    = $cart->items->sum(fn($i) => $i->price * $i->quantity);
        $itemCount   = $cart->items->sum('quantity');

        return response()->json([
            'success' => true,
            'data'    => [
                'id'         => $cart->id,
                'vendor'     => $cart->vendor ? [
                    'id'   => $cart->vendor->id,
                    'name' => $cart->vendor->name,
                ] : null,
                'items'      => $cart->items,
                'subtotal'   => $subtotal,
                'item_count' => $itemCount,
            ],
        ]);
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|exists:product_variants,id',
            'quantity'   => 'required|integer|min:1|max:50',
            'addons'     => 'nullable|array',
        ]);

        $product = Product::with('vendor')->findOrFail($request->product_id);

        if (!$product->is_available) {
            return response()->json(['success' => false, 'message' => 'This product is not available.'], 422);
        }

        // Check if cart has items from different vendor
        $existingCart = Cart::where('user_id', $request->user()->id)->first();
        if ($existingCart && $existingCart->vendor_id !== $product->vendor_id) {
            return response()->json([
                'success' => false,
                'message' => 'Your cart has items from a different store. Clear cart to continue.',
            ], 409);
        }

        $price = $product->price;
        if ($request->variant_id) {
            $variant = $product->variants()->find($request->variant_id);
            if ($variant) $price = $variant->price;
        }

        $cart = Cart::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['vendor_id' => $product->vendor_id]
        );

        $existingItem = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $request->product_id)
            ->where('variant_id', $request->variant_id)
            ->first();

        if ($existingItem) {
            $existingItem->increment('quantity', $request->quantity);
        } else {
            CartItem::create([
                'cart_id'    => $cart->id,
                'product_id' => $request->product_id,
                'variant_id' => $request->variant_id,
                'quantity'   => $request->quantity,
                'price'      => $price,
                'addons'     => $request->addons ? json_encode($request->addons) : null,
            ]);
        }

        $count = CartItem::where('cart_id', $cart->id)->sum('quantity');

        return response()->json(['success' => true, 'message' => 'Added to cart', 'data' => ['cart_item_count' => $count]]);
    }

    public function update(Request $request, CartItem $item)
    {
        $request->validate(['quantity' => 'required|integer|min:0|max:50']);

        $cart = Cart::where('user_id', $request->user()->id)->where('id', $item->cart_id)->firstOrFail();

        if ($request->quantity == 0) {
            $item->delete();
        } else {
            $item->update(['quantity' => $request->quantity]);
        }

        if ($cart->items()->count() === 0) {
            $cart->delete();
        }

        return response()->json(['success' => true, 'message' => 'Cart updated']);
    }

    public function clear(Request $request)
    {
        Cart::where('user_id', $request->user()->id)->delete();
        return response()->json(['success' => true, 'message' => 'Cart cleared']);
    }
}
