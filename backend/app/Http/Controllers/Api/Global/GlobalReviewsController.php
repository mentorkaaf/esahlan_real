<?php
namespace App\Http\Controllers\Api\Global;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
                'global_reviews.images',
                'global_reviews.is_verified_purchase',
                'global_reviews.created_at',
                'global_users.name as user_name',
                'global_users.country as user_country',
            )
            ->get()
            ->map(function ($r) {
                $r->images = $r->images ? json_decode($r->images, true) : [];
                return $r;
            });

        $stats = DB::table('global_reviews')
            ->where('global_product_id', $productId)
            ->where('is_approved', true)
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg,
                SUM(rating=5) as r5, SUM(rating=4) as r4, SUM(rating=3) as r3,
                SUM(rating=2) as r2, SUM(rating=1) as r1')
            ->first();

        return response()->json([
            'reviews' => $reviews,
            'stats'   => [
                'total'   => $stats->total ?? 0,
                'average' => round($stats->avg ?? 0, 1),
                'r5' => $stats->r5 ?? 0, 'r4' => $stats->r4 ?? 0,
                'r3' => $stats->r3 ?? 0, 'r2' => $stats->r2 ?? 0,
                'r1' => $stats->r1 ?? 0,
            ],
        ]);
    }

    /** GET /products/{id}/reviews/can-review  (auth required) */
    public function canReview(Request $request, $productId)
    {
        $userId = $request->user('global_users')->id;

        $alreadyReviewed = DB::table('global_reviews')
            ->where('global_product_id', $productId)
            ->where('global_user_id', $userId)
            ->exists();

        if ($alreadyReviewed) {
            return response()->json(['can_review' => false, 'reason' => 'already_reviewed']);
        }

        $order = DB::table('global_orders')
            ->join('global_order_items', 'global_order_items.global_order_id', '=', 'global_orders.id')
            ->where('global_orders.global_user_id', $userId)
            ->whereIn('global_orders.status', ['shipped', 'delivered'])
            ->where('global_order_items.global_product_id', $productId)
            ->select('global_orders.id', 'global_orders.status')
            ->first();

        if (!$order) {
            return response()->json(['can_review' => false, 'reason' => 'no_qualifying_order']);
        }

        return response()->json(['can_review' => true, 'order_id' => $order->id, 'order_status' => $order->status]);
    }

    /** POST /products/{id}/reviews  (auth required, multipart/form-data) */
    public function store(Request $request, $productId)
    {
        $data = $request->validate([
            'rating'    => 'required|integer|min:1|max:5',
            'title'     => 'nullable|string|max:100',
            'body'      => 'nullable|string|max:2000',
            'images.*'  => 'nullable|image|max:3072',
        ]);

        $userId = $request->user('global_users')->id;

        // Already reviewed?
        if (DB::table('global_reviews')->where('global_product_id', $productId)->where('global_user_id', $userId)->exists()) {
            return response()->json(['message' => 'You have already reviewed this product.'], 422);
        }

        // Must have shipped or delivered order containing this product
        $order = DB::table('global_orders')
            ->join('global_order_items', 'global_order_items.global_order_id', '=', 'global_orders.id')
            ->where('global_orders.global_user_id', $userId)
            ->whereIn('global_orders.status', ['shipped', 'delivered'])
            ->where('global_order_items.global_product_id', $productId)
            ->select('global_orders.id')
            ->first();

        if (!$order) {
            return response()->json(['message' => 'You can only review products from your delivered orders.'], 403);
        }

        // Upload images (up to 3)
        $imageUrls = [];
        if ($request->hasFile('images')) {
            foreach (array_slice($request->file('images'), 0, 3) as $file) {
                $path = $file->store('global/reviews', 'public');
                $imageUrls[] = Storage::url($path);
            }
        }

        $id = DB::table('global_reviews')->insertGetId([
            'global_product_id'    => $productId,
            'global_user_id'       => $userId,
            'global_order_id'      => $order->id,
            'rating'               => $data['rating'],
            'title'                => $data['title'] ?? null,
            'body'                 => $data['body'] ?? null,
            'images'               => !empty($imageUrls) ? json_encode($imageUrls) : null,
            'is_approved'          => true,
            'is_verified_purchase' => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        // Update product rating cache — correct column names: rating, review_count
        $avg   = DB::table('global_reviews')->where('global_product_id', $productId)->where('is_approved', true)->avg('rating');
        $count = DB::table('global_reviews')->where('global_product_id', $productId)->where('is_approved', true)->count();
        DB::table('global_products')->where('id', $productId)->update([
            'rating'       => round($avg, 2),
            'review_count' => $count,
        ]);

        return response()->json(['success' => true, 'review_id' => $id], 201);
    }
}
