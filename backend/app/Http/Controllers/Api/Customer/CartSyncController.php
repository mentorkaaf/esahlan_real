<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CartSyncController extends Controller
{
    /**
     * Flutter calls this whenever cart changes.
     * Replaces ALL cart items for this user+module.
     */
    public function sync(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['success' => false], 401);

        $request->validate([
            'module' => 'required|string',
            'items'  => 'present|array',
            'items.*.product_id'   => 'nullable|integer',
            'items.*.product_name' => 'required|string',
            'items.*.product_image'=> 'nullable|string',
            'items.*.price'        => 'required|numeric',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.meta'         => 'nullable|array',
        ]);

        $module = $request->module;
        $items  = $request->items ?? [];

        DB::transaction(function () use ($user, $module, $items) {
            // Delete existing items for this user+module
            DB::table('abandoned_cart_items')
                ->where('user_id', $user->id)
                ->where('module', $module)
                ->delete();

            if (empty($items)) return;

            $now = now();
            $rows = array_map(fn($item) => [
                'user_id'      => $user->id,
                'module'       => $module,
                'product_id'   => $item['product_id'] ?? null,
                'product_name' => $item['product_name'],
                'product_image'=> $item['product_image'] ?? null,
                'price'        => $item['price'],
                'quantity'     => $item['quantity'],
                'meta'         => isset($item['meta']) ? json_encode($item['meta']) : null,
                'added_at'     => $now,
                'created_at'   => $now,
                'updated_at'   => $now,
            ], $items);

            DB::table('abandoned_cart_items')->insert($rows);
        });

        return response()->json(['success' => true]);
    }

    /**
     * Clear cart after successful checkout.
     */
    public function clear(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['success' => false], 401);

        $module = $request->input('module');

        $query = DB::table('abandoned_cart_items')->where('user_id', $user->id);
        if ($module) $query->where('module', $module);
        $query->delete();

        return response()->json(['success' => true]);
    }
}
