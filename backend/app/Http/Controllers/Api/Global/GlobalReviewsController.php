<?php

namespace App\Http\Controllers\Api\Global;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GlobalReviewsController extends Controller
{
    /** GET /products/{id}/reviews */
    public function index($productId)
    {
        $reviews = DB::table('global_reviews')
            ->join('global_users', 'global_users.id', '=', 'global_reviews.global_user_id')
            ->where('global_reviews.global_product_id', $productId)
            ->where('global_reviews.is_approved', true)
            ->orderBy('global_reviews.created_at', 'desc')
            ->limit(50)
            ->select(
                'global_reviews.id',
                'global_reviews.rating',
                'global_reviews.title',
                'global_reviews.body',
                'global_reviews.created_at',
                'global_users.name as user_name',
                'global_users.country as user_country',
            )
            ->get();

        $stats = DB::table('global_reviews')
            ->where('global_product_id', $productId)
            ->where('is_approved', true)
            ->selectRaw('
                COUNT(*) as total,
                AVG(rating) as avg,
                SUM(rating = 5) as r5,
                SUM(rating = 4) as r4,
                SUM(rating = 3) as r3,
                SUM(rating = 2) as r2,
                SUM(rating = 1) as r1
            ')
            ->first();

        return response()->json([
            'reviews' => $reviews,
            'stats'   => [
                'total'   => $stats->total ?? 0,
                'average' => round($stats->avg ?? 0, 1),
                'r5'      => $stats->r5 ?? 0,
                'r4'      => $stats->r4 ?? 0,
                'r3'      => $stats->r3 ?? 0,
                'r2'      => $stats->r2 ?? 0,
                'r1'      => $stats->r1 ?? 0,
            ],
        ]);
    }

    /** POST /products/{id}/reviews  (auth required) */
    public function store(Request $request, $productId)
    {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'title'  => 'nullable|string|max:100',
            'body'   => 'nullable|string|max:2000',
        ]);

        $userId = $request->user('global_users')->id;

        // One review per product per user
        $existing = DB::table('global_reviews')
            ->where('global_product_id', $productId)
            ->where('global_user_id', $userId)
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'You have already reviewed this product.'], 422);
        }

        // Must have a completed order containing this product
        $purchased = DB::table('global_orders')
            ->join('global_order_items', 'global_order_items.global_order_id', '=', 'global_orders.id')
            ->where('global_orders.global_user_id', $userId)
            ->where('global_orders.status', 'delivered')
            ->where('global_order_items.global_product_id', $productId)
            ->exists();

        $id = DB::table('global_reviews')->insertGetId([
            'global_product_id' => $productId,
            'global_user_id'    => $userId,
            'rating'            => $data['rating'],
            'title'             => $data['title'] ?? null,
            'body'              => $data['body'] ?? null,
            'is_approved'       => true,   // auto-approve; admin can moderate later
            'is_verified'       => $purchased,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Update product rating cache
        $avg = DB::table('global_reviews')
            ->where('global_product_id', $productId)
            ->where('is_approved', true)
            ->avg('rating');

        $count = DB::table('global_reviews')
            ->where('global_product_id', $productId)
            ->where('is_approved', true)
            ->count();

        DB::table('global_products')
            ->where('id', $productId)
            ->update([
                'rating_avg'    => round($avg, 2),
                'reviews_count' => $count,
            ]);

        return response()->json(['success' => true, 'review_id' => $id], 201);
    }
}
