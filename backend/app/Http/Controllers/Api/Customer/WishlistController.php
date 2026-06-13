<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $wishlist = Wishlist::where('user_id', $request->user()->id)
            ->with(['product.images', 'product.vendor'])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $wishlist->map(fn($w) => $w->product)->filter()->values(),
        ]);
    }

    public function toggle(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);

        $existing = Wishlist::where('user_id', $request->user()->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existing) {
            $existing->delete();
            return response()->json(['success' => true, 'data' => ['in_wishlist' => false], 'message' => 'Removed from wishlist']);
        }

        Wishlist::create(['user_id' => $request->user()->id, 'product_id' => $request->product_id]);
        return response()->json(['success' => true, 'data' => ['in_wishlist' => true], 'message' => 'Added to wishlist']);
    }
}
