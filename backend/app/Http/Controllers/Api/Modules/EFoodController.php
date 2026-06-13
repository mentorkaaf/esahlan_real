<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Helpers\WorkingHours;
use App\Helpers\AppSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EFoodController extends Controller
{
    // GET /efood/banners
    // Returns ONLY banners with position = 'module_top' tagged for efood.
    // Home page banners (home_top / home_middle) are served via GET /api/banners.
    public function banners()
    {
        $banners = DB::table('banners')
            ->where('is_active', true)
            ->where('position', 'module_top')           // module-level banners only
            ->where(function ($q) {
                // Either not tagged to any specific module, or tagged to efood
                $q->whereNull('module_slug')->orWhere('module_slug', 'efood');
            })
            ->orderBy('sort_order')
            ->get()
            ->map(function ($b) {
                $imgUrl = null;
                if ($b->image) {
                    $imgUrl = str_starts_with($b->image, 'http')
                        ? $b->image
                        : url('/api/img/' . $b->image);
                }
                return [
                    'id'          => $b->id,
                    'title'       => $b->title ?? '',
                    'subtitle'    => $b->subtitle ?? null,
                    'image'       => $imgUrl,
                    'image_url'   => $imgUrl,
                    'action_type' => $b->link_type  ?? 'none',
                    'action_url'  => $b->link_value ?? null,
                ];
            });

        return response()->json(['success' => true, 'data' => $banners]);
    }

    // GET /efood/restaurants?district_id=&search=&page=&featured=&top_rated=
    public function restaurants(Request $request)
    {
        $module = DB::table('modules')->where('slug', 'efood')->first();
        if (!$module) return response()->json(['success' => false, 'message' => 'Module not found'], 404);

        $query = DB::table('vendors')
            ->where('vendors.module_id', $module->id)
            ->where('vendors.is_active', true)
            ->whereNull('vendors.deleted_at')
            ->select([
                'vendors.id', 'vendors.name', 'vendors.logo', 'vendors.cover_image',
                'vendors.vendor_type', 'vendors.rating', 'vendors.review_count',
                'vendors.is_open', 'vendors.working_hours', 'vendors.delivery_time',
                'vendors.delivery_fee', 'vendors.minimum_order',
                'vendors.temporarily_closed', 'vendors.is_featured',
                'vendors.address', 'vendors.district_id',
                'districts.name as district_name',
            ])
            ->leftJoin('districts', 'vendors.district_id', '=', 'districts.id');

        if ($request->filled('district_id')) {
            $query->where('vendors.district_id', $request->district_id);
        }

        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            $query->where(function ($sq) use ($q) {
                $sq->where('vendors.name', 'like', $q)
                   ->orWhere('vendors.vendor_type', 'like', $q);
            });
        }

        if ($request->filled('category')) {
            $query->where('vendors.vendor_type', $request->category);
        }

        if ($request->boolean('featured')) {
            $query->where('vendors.is_featured', true);
        }

        if ($request->boolean('top_rated')) {
            $query->where('vendors.rating', '>=', 4.0);
        }

        $query->orderByDesc('vendors.is_featured')
              ->orderByDesc('vendors.rating')
              ->orderByDesc('vendors.id');

        $restaurants = $query->paginate(20);

        // Attach best active campaign badge per restaurant
        $items = $restaurants->items();
        try {
            $vendorIds = array_column($items, 'id');
            if (!empty($vendorIds)) {
                $now = now();
                $campaigns = DB::table('discount_campaigns')
                    ->whereIn('vendor_id', $vendorIds)
                    ->where('is_active', true)
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at',   '>=', $now)
                    ->orderByDesc('discount_value')
                    ->get(['vendor_id', 'discount_type', 'discount_value', 'badge_text', 'badge_color']);

                // Index by vendor_id — keep best (already sorted by discount_value desc)
                $campaignMap = [];
                foreach ($campaigns as $c) {
                    if (!isset($campaignMap[$c->vendor_id])) {
                        $campaignMap[$c->vendor_id] = $c;
                    }
                }

                foreach ($items as &$r) {
                    $c = $campaignMap[$r->id] ?? null;
                    if ($c) {
                        $label = $c->discount_type === 'percentage'
                            ? '-' . (int)$c->discount_value . '%'
                            : '-$' . number_format($c->discount_value, 0);
                        $r->campaign_badge       = $label;
                        $r->campaign_badge_color = $c->badge_color ?? 'red';
                    } else {
                        $r->campaign_badge       = null;
                        $r->campaign_badge_color = null;
                    }

                    // Compute real-time is_open from working_hours schedule
                    if (!empty($r->working_hours)) {
                        $r->is_open = WorkingHours::isOpen($r->working_hours, (bool)$r->is_open);
                    }
                    // Temporarily closed overrides everything
                    if (!empty($r->temporarily_closed)) {
                        $r->is_open = false;
                    }
                    // Strip working_hours JSON from response (not needed by app)
                    unset($r->working_hours);
                }
                unset($r);
            }
        } catch (\Throwable) { /* table may not exist */ }

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'        => $restaurants->total(),
                'current_page' => $restaurants->currentPage(),
                'last_page'    => $restaurants->lastPage(),
            ],
        ]);
    }

    // GET /efood/restaurants/{id}
    public function restaurant($id)
    {
        $vendor = DB::table('vendors')
            ->where('vendors.id', $id)
            ->where('vendors.is_active', true)
            ->leftJoin('districts', 'vendors.district_id', '=', 'districts.id')
            ->select([
                'vendors.*',
                'districts.name as district_name',
            ])
            ->first();

        if (!$vendor) {
            return response()->json(['success' => false, 'message' => 'Restaurant not found'], 404);
        }

        // Compute real-time open status from working_hours
        if (!empty($vendor->working_hours)) {
            $vendor->is_open = WorkingHours::isOpen($vendor->working_hours, (bool)$vendor->is_open);
        }
        if (!empty($vendor->temporarily_closed)) {
            $vendor->is_open = false;
        }

        // Schedule
        $schedule = DB::table('vendor_schedules')
            ->where('vendor_id', $id)
            ->orderBy('day')
            ->get();

        // Categories for this vendor — derived from products that belong to this vendor
        $categories = DB::table('products')
            ->where('products.vendor_id', $id)
            ->whereNull('products.deleted_at')
            ->where('products.is_available', true)
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('categories.is_active', true)
            ->select('categories.id', 'categories.name', 'categories.image')
            ->distinct()
            ->orderBy('categories.sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => array_merge((array)$vendor, [
                'schedule'   => $schedule,
                'categories' => $categories,
            ]),
        ]);
    }

    // GET /efood/restaurants/{id}/products?category_id=&search=
    public function products(Request $request, $restaurantId)
    {
        // Auto-add missing columns (SQLite safe)
        try {
            $pdo = DB::connection()->getPdo();
            $existingCols = array_column(
                $pdo->query("PRAGMA table_info(products)")->fetchAll(\PDO::FETCH_ASSOC),
                'name'
            );
            $needed = [
                'compare_price' => "ALTER TABLE products ADD COLUMN compare_price DECIMAL(10,2) NULL",
                'sale_price'    => "ALTER TABLE products ADD COLUMN sale_price    DECIMAL(10,2) NULL",
                'thumbnail'     => "ALTER TABLE products ADD COLUMN thumbnail     VARCHAR(500)  NULL",
                'image'         => "ALTER TABLE products ADD COLUMN image         VARCHAR(500)  NULL",
                'is_available'    => "ALTER TABLE products ADD COLUMN is_available    INTEGER NOT NULL DEFAULT 1",
                'is_active'       => "ALTER TABLE products ADD COLUMN is_active       INTEGER NOT NULL DEFAULT 1",
                'is_featured'     => "ALTER TABLE products ADD COLUMN is_featured     INTEGER NOT NULL DEFAULT 0",
                'sort_order'      => "ALTER TABLE products ADD COLUMN sort_order      INTEGER NOT NULL DEFAULT 0",
                'available_from'  => "ALTER TABLE products ADD COLUMN available_from  TIME NULL",
                'available_until' => "ALTER TABLE products ADD COLUMN available_until TIME NULL",
            ];
            foreach ($needed as $col => $sql) {
                if (!in_array($col, $existingCols)) $pdo->exec($sql);
            }
        } catch (\Throwable $e) { /* ignore */ }

        $query = DB::table('products')
            ->where('products.vendor_id', $restaurantId)
            ->whereNull('products.deleted_at')      // exclude soft-deleted
            ->where('products.is_available', true)  // exclude unavailable
            ->select([
                'products.id', 'products.name', 'products.description',
                'products.price', 'products.compare_price', 'products.sale_price',
                'products.thumbnail', 'products.image', 'products.is_featured',
                'products.is_available', 'products.sort_order',
                'products.available_from', 'products.available_until',
                'categories.name as category_name', 'categories.id as category_id',
            ])
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->orderByDesc('products.is_featured')
            ->orderBy('products.sort_order')
            ->orderBy('products.id');

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            $query->where('products.name', 'like', $q);
        }

        $products = $query->get();

        // Fetch vendor-level addons (apply to all products)
        try {
            $vendorAddons = DB::table('addons')
                ->where('vendor_id', $restaurantId)
                ->where('is_active', true)
                ->get(['id', 'name', 'price', 'is_required', 'max_select']);
        } catch (\Throwable) {
            $vendorAddons = collect();
        }

        // Fetch product variants
        try {
            $variantRows = DB::table('product_variants')
                ->whereIn('product_id', $products->pluck('id'))
                ->get();
        } catch (\Throwable) {
            $variantRows = collect();
        }

        // Attach addons + variants per product
        $result = $products->map(function ($p) use ($vendorAddons, $variantRows) {
            // Product-specific addons via pivot, fall back to all vendor addons
            try {
                $productAddonIds = DB::table('product_addons')
                    ->where('product_id', $p->id)->pluck('addon_id');
                $addons = $productAddonIds->isNotEmpty()
                    ? $vendorAddons->whereIn('id', $productAddonIds)->values()
                    : $vendorAddons->values();
            } catch (\Throwable) {
                $addons = $vendorAddons->values();
            }

            $variants = $variantRows->where('product_id', $p->id)->values();

            // Time-based availability
            $isTimeAvailable = WorkingHours::isItemAvailable(
                $p->available_from ?? null,
                $p->available_until ?? null
            );

            return array_merge((array)$p, [
                'addons'           => $addons,
                'variants'         => $variants,
                'is_time_available'=> $isTimeAvailable,
            ]);
        });

        return response()->json([
            'success' => true,
            'data'    => $result,
            'addons'  => $vendorAddons, // vendor-level for backward compat
        ]);
    }

    // GET /efood/restaurants/{id}/addons/{productId}
    public function productAddons($restaurantId, $productId)
    {
        $addons = DB::table('addons')
            ->where('vendor_id', $restaurantId)
            ->where('is_active', true)
            ->get();

        return response()->json(['success' => true, 'data' => $addons]);
    }

    // POST /efood/order  (works with or without auth — guest checkout)
    public function createOrder(Request $request)
    {
        // ── Normalize items: accept both 'qty' and 'quantity' ──────────
        $rawItems = collect($request->input('items', []))->map(function ($item) {
            $item['quantity'] = (int)($item['quantity'] ?? $item['qty'] ?? 1);
            return $item;
        })->toArray();

        // ── Map payment method to schema enum values ───────────────────
        $pmRaw = $request->input('payment_method', 'wallet');
        $pmMap = ['waafi' => 'cod', 'waafi_pay' => 'cod', 'cash' => 'cod', 'wallet' => 'wallet', 'cod' => 'cod'];
        $pm    = $pmMap[$pmRaw] ?? 'cod';

        // Route is now auth:sanctum — user is always resolved correctly
        $userId = $request->user()->id;

        // ── Resolve efood module ───────────────────────────────────────
        $module = DB::table('modules')->where('slug', 'efood')->first();
        if (!$module) {
            // Auto-create module entry if missing
            $moduleId = DB::table('modules')->insertGetId([
                'name'       => 'eFood',
                'slug'       => 'efood',
                'is_active'  => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $moduleId = $module->id;
        }

        // ── Resolve vendor from request or first product ───────────────
        $vendorId = $request->input('vendor_id');
        if (!$vendorId && !empty($rawItems)) {
            $firstProd = DB::table('products')->find($rawItems[0]['product_id']);
            $vendorId  = $firstProd?->vendor_id;
        }
        // Fallback: use any existing vendor
        if (!$vendorId) {
            $vendorId = DB::table('vendors')->value('id');
        }

        // ── Find active discount campaign for this vendor ──────────────
        $activeCampaign = null;
        if ($vendorId) {
            try {
                $now = now();
                $activeCampaign = DB::table('discount_campaigns')
                    ->where('vendor_id', $vendorId)
                    ->where('is_active', true)
                    ->where('starts_at', '<=', $now)
                    ->where('ends_at',   '>=', $now)
                    ->orderByDesc('discount_value')
                    ->first();
            } catch (\Throwable) { /* table may not exist yet */ }
        }

        // ── Calculate totals (apply campaign discount server-side) ─────
        $subtotal      = 0.0;
        $discountTotal = 0.0;
        $orderItems    = [];
        foreach ($rawItems as $item) {
            $product = DB::table('products')->find($item['product_id']);
            if (!$product) continue;

            $origPrice = (float)($product->sale_price ?? $product->price ?? 0);

            // Apply campaign discount
            $price = $origPrice;
            if ($activeCampaign && ($activeCampaign->apply_to_all ?? true)) {
                if ($activeCampaign->discount_type === 'percentage') {
                    $price = $origPrice * (1 - (float)$activeCampaign->discount_value / 100);
                } else {
                    $price = max(0, $origPrice - (float)$activeCampaign->discount_value);
                }
                $price = round($price, 2);
            }

            $addonTotal = 0.0;
            if (!empty($item['addons']) && is_array($item['addons'])) {
                $addonIds    = array_filter(array_map(fn($a) => is_array($a) ? ($a['id'] ?? null) : $a, $item['addons']));
                $addonPrices = DB::table('addons')->whereIn('id', $addonIds)->pluck('price', 'id');
                foreach ($addonIds as $aid) { $addonTotal += (float)($addonPrices[$aid] ?? 0); }
            }

            $lineTotal       = ($price + $addonTotal) * $item['quantity'];
            $origLineTotal   = ($origPrice + $addonTotal) * $item['quantity'];
            $discountTotal  += ($origLineTotal - $lineTotal);
            $subtotal       += $lineTotal;

            $orderItems[] = [
                'product_id'   => $product->id,
                'name'         => $product->name,
                'price'        => $price,           // discounted price stored
                'quantity'     => $item['quantity'],
                'addons'       => json_encode($item['addons'] ?? []),
                'total'        => $lineTotal,
            ];
        }

        $deliveryFee   = 1.00;
        $discountTotal = round($discountTotal, 2);
        $total         = round($subtotal + $deliveryFee, 2);

        // ── Wallet balance check ───────────────────────────────────────
        $wallet = null;
        if ($pm === 'wallet' && $userId) {
            $wallet = DB::table('wallets')
                ->where('owner_type', 'App\\Models\\User')
                ->where('owner_id', $userId)
                ->first();
            $balance = $wallet ? (float)$wallet->balance : 0;
            if (!$wallet || $balance < $total) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient wallet balance. Available: $' . number_format($balance, 2) . ', Required: $' . number_format($total, 2),
                ], 422);
            }
        }

        // ── Delivery address: must be JSON ─────────────────────────────
        $deliveryAddr = $request->input('delivery_address');
        if (!is_array($deliveryAddr)) {
            $deliveryAddr = ['address' => $deliveryAddr ?? '', 'city' => 'Mogadishu', 'country' => 'Somalia'];
        }

        $order = DB::transaction(function () use (
            $userId, $vendorId, $moduleId, $pm, $total, $subtotal,
            $deliveryFee, $discountTotal, $activeCampaign,
            $deliveryAddr, $orderItems, $wallet, $request
        ) {
            $orderData = [
                'user_id'         => $userId,
                'vendor_id'       => $vendorId,
                'module_id'       => $moduleId,
                'module_slug'     => 'efood',
                'status'          => 'pending',
                'payment_method'  => $pm,
                'payment_status'  => $pm === 'wallet' ? 'paid' : 'unpaid',
                'delivery_address'=> $deliveryAddr,
                'subtotal'        => $subtotal,
                'delivery_fee'    => $deliveryFee,
                'total_amount'    => $total,
                'notes'           => $request->input('note'),
                'placed_at'       => now(),
            ];
            // Store discount info if columns exist
            try {
                if ($discountTotal > 0) {
                    $orderData['discount_amount'] = $discountTotal;
                }
                if ($activeCampaign) {
                    $orderData['campaign_id'] = $activeCampaign->id;
                }
            } catch (\Throwable) {}

            $order = Order::create($orderData);

            foreach ($orderItems as $item) {
                DB::table('order_items')->insert([
                    'order_id'   => $order->id,
                    'product_id' => $item['product_id'],
                    'name'       => $item['name'],
                    'price'      => $item['price'],
                    'quantity'   => $item['quantity'],
                    'addons'     => $item['addons'],
                    'total'      => $item['total'],
                ]);
            }

            // Status history (non-critical)
            try {
                DB::table('order_status_history')->insert([
                    'order_id'   => $order->id,
                    'status'     => 'pending',
                    'note'       => 'eFood order placed',
                    'changed_by' => $userId,
                    'created_at' => now(),
                ]);
            } catch (\Throwable) {}

            // Deduct wallet
            if ($pm === 'wallet' && $wallet) {
                DB::table('wallets')->where('id', $wallet->id)
                    ->decrement('balance', $total);
            }

            return $order;
        });

        // ── Push notification: order placed ───────────────────────────────
        try {
            $fcmToken = DB::table('users')->where('id', $userId)->value('fcm_token');
            if ($fcmToken) {
                \App\Services\FcmService::sendOrderUpdate(
                    $fcmToken,
                    $order->order_number,
                    'pending',
                    $order->id,
                );
            }
        } catch (\Throwable) {}

        return response()->json([
            'success' => true,
            'message' => 'Order placed successfully!',
            'data'    => [
                'order_id'        => $order->id,
                'order_number'    => $order->order_number,
                'total'           => $total,
                'discount_saved'  => $discountTotal,
                'campaign_applied'=> $activeCampaign?->name,
            ],
        ], 201);
    }

    // GET /efood/restaurants/{id}/campaigns — active discount campaigns for a restaurant
    public function restaurantCampaigns($id)
    {
        try {
            $now = now();
            $campaigns = DB::table('discount_campaigns')
                ->where('vendor_id', $id)
                ->where('is_active', true)
                ->where('starts_at', '<=', $now)
                ->where('ends_at',   '>=', $now)
                ->orderByDesc('discount_value')
                ->get([
                    'id', 'name', 'description',
                    'discount_type', 'discount_value',
                    'badge_text', 'badge_color',
                    'apply_to_all',
                    'starts_at', 'ends_at',
                ]);

            return response()->json(['success' => true, 'data' => $campaigns]);
        } catch (\Throwable $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    // GET /efood/restaurants/{id}/coupons — active coupons for a restaurant + global efood coupons
    public function restaurantCoupons($id)
    {
        try {
            $now = now();
            $coupons = DB::table('coupons')
                ->where('is_active', true)
                ->where(function ($q) use ($id) {
                    // Restaurant-specific OR global (no vendor)
                    $q->where('vendor_id', $id)->orWhereNull('vendor_id');
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) {
                    $q->whereNull('usage_limit')
                      ->orWhereRaw('used_count < usage_limit');
                })
                ->orderByRaw('CASE WHEN vendor_id IS NOT NULL THEN 0 ELSE 1 END')
                ->orderByDesc('value')
                ->get(['id', 'code', 'title', 'description', 'type', 'value',
                       'min_order_amount', 'max_discount', 'ends_at', 'vendor_id']);

            return response()->json(['success' => true, 'data' => $coupons]);
        } catch (\Throwable $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    // GET /efood/categories
    public function foodCategories()
    {
        $module = DB::table('modules')->where('slug', 'efood')->first();
        $categories = DB::table('categories')
            ->where('module_id', $module?->id)
            ->whereNull('vendor_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'image', 'slug']);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    // ── Resolve user ID from Bearer token (routes are public, no auth middleware) ─
    private function resolveUserId(Request $request): ?int
    {
        // First try Sanctum's resolved user (works if route has auth:sanctum)
        if ($uid = $request->user()?->id) return $uid;

        // Manual: parse Bearer token → personal_access_tokens → user
        // Sanctum token format: "{id}|{plaintext_token}"
        try {
            $bearer = $request->bearerToken();
            if ($bearer) {
                // Sanctum format: "1|abc123..."
                if (str_contains($bearer, '|')) {
                    [, $plainToken] = explode('|', $bearer, 2);
                } else {
                    $plainToken = $bearer;
                }
                $pat = DB::table('personal_access_tokens')
                    ->where('token', hash('sha256', $plainToken))
                    ->first();
                if ($pat) {
                    // Update last_used_at
                    DB::table('personal_access_tokens')
                        ->where('id', $pat->id)
                        ->update(['last_used_at' => now()]);
                    return (int)$pat->tokenable_id;
                }
            }
        } catch (\Throwable) {}
        return null;
    }

    // ─────────────────────────────────────────────────────────────────
    // ORDERS
    // ─────────────────────────────────────────────────────────────────

    // GET /efood/orders
    public function orders(Request $request)
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) return response()->json(['success' => false, 'message' => 'Login required to view orders'], 401);

        // Get efood module ID for fallback matching
        $efoodModuleId = DB::table('modules')->where('slug', 'efood')->value('id');

        $orders = DB::table('orders')
            ->where('orders.user_id', $userId)
            ->where(function ($q) use ($efoodModuleId) {
                $q->where('orders.module_slug', 'efood')
                  ->orWhere(function ($q2) use ($efoodModuleId) {
                      // Orders placed before module_slug was set
                      if ($efoodModuleId) {
                          $q2->where('orders.module_id', $efoodModuleId)
                             ->whereNull('orders.module_slug');
                      }
                  });
            })
            ->leftJoin('vendors', 'orders.vendor_id', '=', 'vendors.id')
            ->select([
                'orders.id', 'orders.order_number', 'orders.status',
                'orders.payment_method', 'orders.payment_status',
                'orders.subtotal', 'orders.delivery_fee', 'orders.total_amount',
                'orders.discount_amount', 'orders.placed_at', 'orders.created_at',
                'vendors.name as restaurant_name', 'vendors.logo as restaurant_logo',
            ])
            ->orderByDesc('orders.created_at')
            ->get();

        // Attach items to each order
        $result = $orders->map(function ($order) {
            $items = DB::table('order_items')
                ->where('order_id', $order->id)
                ->get(['name', 'price', 'quantity', 'total']);
            return array_merge((array)$order, [
                'restaurant' => [
                    'name' => $order->restaurant_name,
                    'logo' => $order->restaurant_logo,
                ],
                'items'       => $items,
                'items_count' => $items->count(),
                'total'       => $order->total_amount,
            ]);
        });

        return response()->json(['success' => true, 'data' => $result]);
    }

    // GET /efood/orders/{id}
    public function order(Request $request, $id)
    {
        $userId = $this->resolveUserId($request);
        $order  = DB::table('orders')->where('id', $id)
            ->where(function($q) use ($userId) {
                if ($userId) $q->where('user_id', $userId);
            })->first();

        if (!$order) return response()->json(['success' => false, 'message' => 'Order not found'], 404);

        $items    = DB::table('order_items')->where('order_id', $id)->get();
        $vendor   = DB::table('vendors')->find($order->vendor_id);
        $history  = DB::table('order_status_history')->where('order_id', $id)->orderBy('created_at')->get();

        return response()->json([
            'success' => true,
            'data'    => array_merge((array)$order, [
                'restaurant'  => $vendor,
                'items'       => $items,
                'items_count' => $items->count(),
                'total'       => $order->total_amount,
                'history'     => $history,
            ]),
        ]);
    }

    // GET /efood/orders/{id}/track
    public function trackOrder(Request $request, $id)
    {
        $order = DB::table('orders')->where('id', $id)->first();
        if (!$order) return response()->json(['success' => false, 'message' => 'Order not found'], 404);

        $history = DB::table('order_status_history')
            ->where('order_id', $id)
            ->orderBy('created_at')
            ->get();

        $steps = ['pending', 'confirmed', 'preparing', 'on_the_way', 'delivered'];
        $currentIdx = array_search($order->status, $steps);

        return response()->json([
            'success' => true,
            'data'    => [
                'order_id'     => $order->id,
                'order_number' => $order->order_number,
                'status'       => $order->status,
                'steps'        => $steps,
                'current_step' => $currentIdx !== false ? $currentIdx : 0,
                'history'      => $history,
                'placed_at'    => $order->placed_at ?? $order->created_at,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // FAVORITES
    // ─────────────────────────────────────────────────────────────────

    // GET /efood/favorites
    public function favorites(Request $request)
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) return response()->json(['success' => true, 'data' => []]);

        $favs = DB::table('restaurant_favorites')
            ->where('restaurant_favorites.user_id', $userId)
            ->join('vendors', 'restaurant_favorites.vendor_id', '=', 'vendors.id')
            ->where('vendors.is_active', true)
            ->select([
                'vendors.id', 'vendors.name', 'vendors.logo', 'vendors.cover_image',
                'vendors.vendor_type', 'vendors.rating', 'vendors.review_count',
                'vendors.is_open', 'vendors.delivery_time', 'vendors.delivery_fee',
                'vendors.minimum_order', 'vendors.is_featured',
                'restaurant_favorites.created_at as favorited_at',
            ])
            ->get();

        return response()->json(['success' => true, 'data' => $favs]);
    }

    // POST /efood/favorites/toggle  { restaurant_id: int }
    public function toggleFavorite(Request $request)
    {
        $userId   = $this->resolveUserId($request);
        $vendorId = $request->input('restaurant_id');

        if (!$userId || !$vendorId) {
            return response()->json(['success' => false, 'message' => 'Login required'], 401);
        }

        $exists = DB::table('restaurant_favorites')
            ->where('user_id', $userId)
            ->where('vendor_id', $vendorId)
            ->exists();

        if ($exists) {
            DB::table('restaurant_favorites')
                ->where('user_id', $userId)
                ->where('vendor_id', $vendorId)
                ->delete();
            $action = 'removed';
        } else {
            DB::table('restaurant_favorites')->insert([
                'user_id'    => $userId,
                'vendor_id'  => $vendorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $action = 'added';
        }

        // Return updated favorite IDs
        $favIds = DB::table('restaurant_favorites')
            ->where('user_id', $userId)
            ->pluck('vendor_id');

        return response()->json([
            'success' => true,
            'action'  => $action,
            'data'    => $favIds,
        ]);
    }

    // GET /efood/favorites/ids — just the IDs (for syncing local state)
    public function favoriteIds(Request $request)
    {
        $userId = $this->resolveUserId($request);
        if (!$userId) return response()->json(['success' => true, 'data' => []]);

        $ids = DB::table('restaurant_favorites')
            ->where('user_id', $userId)
            ->pluck('vendor_id');

        return response()->json(['success' => true, 'data' => $ids]);
    }

    public function getItem($id)
    {
        $item = \DB::table('products')->where('id', $id)->first();
        if (!$item) return response()->json(['success' => false, 'message' => 'Item not found'], 404);
        return response()->json(['success' => true, 'data' => $item]);
    }

}
