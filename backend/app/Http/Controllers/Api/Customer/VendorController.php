<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    public function index(Request $request)
    {
        $query = Vendor::where('is_active', true)->where('is_approved', true);

        if ($request->module) $query->where('module_slug', $request->module);
        if ($request->district_id) $query->where('district_id', $request->district_id);
        if ($request->featured) $query->where('is_featured', true);
        if ($request->search) {
            $q = $request->search;
            $query->where(function ($q2) use ($q) {
                $q2->where('name', 'like', "%$q%")->orWhere('description', 'like', "%$q%");
            });
        }

        $vendors = $query->with('module')->paginate(20);

        return response()->json(['success' => true, 'data' => $vendors]);
    }

    public function show(Vendor $vendor)
    {
        if (!$vendor->is_approved || !$vendor->is_active) {
            return response()->json(['success' => false, 'message' => 'Vendor not found'], 404);
        }

        $vendor->load(['module', 'schedules', 'district']);

        return response()->json(['success' => true, 'data' => $vendor]);
    }

    public function products(Request $request, Vendor $vendor)
    {
        $products = $vendor->products()
            ->where('is_available', true)
            ->with(['images', 'variants', 'category'])
            ->when($request->category_id, fn($q) => $q->where('category_id', $request->category_id))
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function reviews(Request $request, Vendor $vendor)
    {
        $reviews = $vendor->reviews()
            ->with('user:id,name,avatar')
            ->latest()
            ->paginate(15);

        return response()->json(['success' => true, 'data' => $reviews]);
    }

    public function storeReview(Request $request, Vendor $vendor)
    {
        $v = Validator::make($request->all(), [
            'rating'  => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:500',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user = $request->user();

        // Check if user has ordered from this vendor
        $hasOrdered = \App\Models\Order::where('user_id', $user->id)
            ->where('vendor_id', $vendor->id)
            ->where('status', 'delivered')
            ->exists();

        if (!$hasOrdered) {
            return response()->json(['success' => false, 'message' => 'You can only review vendors after a completed order'], 403);
        }

        // Upsert review
        $review = $vendor->reviews()->updateOrCreate(
            ['user_id' => $user->id],
            ['rating' => $request->rating, 'comment' => $request->comment]
        );

        // Update average rating
        $avg = $vendor->reviews()->avg('rating');
        $vendor->update(['rating' => round($avg, 1)]);

        return response()->json(['success' => true, 'data' => $review]);
    }
}
