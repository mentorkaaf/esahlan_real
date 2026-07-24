<?php

namespace App\Http\Controllers\Api\Modules;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\DealOfDay;
use App\Models\EshopCampaign;
use App\Models\FlashDeal;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EShopController extends Controller
{
    // ── Pricing helper ────────────────────────────────────────────
    // Returns the discounted sale_price. Priority: override_price > deal discount > existing sale_price.
    private function calcEffectivePrice(float $originalPrice, string $discountType, float $discountValue, ?float $overridePrice = null): float
    {
        if ($overridePrice !== null && $overridePrice > 0) return round($overridePrice, 2);
        if ($discountValue <= 0) return round($originalPrice, 2);
        if ($discountType === 'percentage') return round($originalPrice * (1 - $discountValue / 100), 2);
        return round(max(0, $originalPrice - $discountValue), 2);
    }

    // Build maps of active deals for a set of product IDs
    private function activeDealMaps(array $productIds, $now): array
    {
        if (empty($productIds)) return [[], [], []];

        $flashMap = DB::table('flash_deal_products as fdp')
            ->join('flash_deals as fd', 'fdp.flash_deal_id', '=', 'fd.id')
            ->where('fd.is_active', true)
            
            ->where('fd.ends_at', '>', $now)
            ->whereIn('fdp.product_id', $productIds)
            ->select(['fdp.product_id', 'fdp.override_price', 'fd.discount_type', 'fd.discount_value'])
            ->get()->keyBy('product_id')->toArray();

        $dealMap = DB::table('deals_of_day')
            ->where('is_active', true)
            ->where('discount_value', '>', 0)
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->whereIn('product_id', $productIds)
            ->select(['product_id', 'discount_type', 'discount_value'])
            ->get()->keyBy('product_id')->toArray();

        $campMap = DB::table('eshop_campaign_products as ecp')
            ->join('eshop_campaigns as ec', 'ecp.campaign_id', '=', 'ec.id')
            ->where('ec.is_active', true)
            ->where('ec.discount_value', '>', 0)
            ->where(fn($q) => $q->whereNull('ec.starts_at')->orWhere('ec.starts_at', '<=', $now))
            ->where(fn($q) => $q->whereNull('ec.ends_at')->orWhere('ec.ends_at', '>', $now))
            ->whereIn('ecp.product_id', $productIds)
            ->select(['ecp.product_id', 'ec.discount_type', 'ec.discount_value'])
            ->get()->keyBy('product_id')->toArray();

        return [$flashMap, $dealMap, $campMap];
    }

    private function resolveImg(?string $path): ?string
    {
        return cdn_url($path);
    }

    // Apply the best active deal price to a product array/object
    private function applyDealPrice($product, array $flashMap, array $dealMap, array $campMap): array
    {
        $p = is_array($product) ? $product : (array)$product;
        $p['thumbnail'] = $this->resolveImg($p['thumbnail'] ?? null);
        $origPrice = (float)($p['price'] ?? 0);

        if (isset($flashMap[$p['id']])) {
            $d = (array)$flashMap[$p['id']];
            $p['sale_price'] = $this->calcEffectivePrice($origPrice, $d['discount_type'], (float)$d['discount_value'], isset($d['override_price']) ? (float)$d['override_price'] : null);
            $p['deal_badge'] = '⚡ Flash';
        } elseif (isset($dealMap[$p['id']])) {
            $d = (array)$dealMap[$p['id']];
            $p['sale_price'] = $this->calcEffectivePrice($origPrice, $d['discount_type'], (float)$d['discount_value']);
            $p['deal_badge'] = '🔥 Deal';
        } elseif (isset($campMap[$p['id']])) {
            $d = (array)$campMap[$p['id']];
            $p['sale_price'] = $this->calcEffectivePrice($origPrice, $d['discount_type'], (float)$d['discount_value']);
            $p['deal_badge'] = '🏷️ Offer';
        }

        return $p;
    }

    // GET /eshop/banners
    public function banners()
    {
        $banners = DB::table('banners')
            ->where('is_active', true)
            ->where(fn($q) => $q->where('module_slug', 'eshop')->orWhere('link_value', 'eshop'))
            ->orderBy('sort_order')
            ->get(['id', 'title', 'subtitle', 'image', 'action_url'])
            ->map(fn($b) => array_merge((array)$b, ['image' => $this->resolveImg($b->image)]));

        return response()->json(['success' => true, 'data' => $banners]);
    }

    // GET /eshop/categories
    public function categories()
    {
        $module = DB::table('modules')->where('slug', 'eshop')->first();
        $cats = DB::table('categories')
            ->where('module_id', $module?->id)
            ->whereNull('vendor_id')
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'image', 'slug'])
            ->map(fn($c) => array_merge((array)$c, ['image' => $this->resolveImg($c->image)]));

        return response()->json(['success' => true, 'data' => $cats]);
    }

    // GET /eshop/products?category_id=&search=&featured=&sort=newest|price_asc|price_desc|popular
    public function products(Request $request)
    {
        $module = DB::table('modules')->where('slug', 'eshop')->first();

        $query = DB::table('products')
            ->where('products.module_id', $module?->id)
            ->where('products.is_available', true)
            ->whereNull('products.deleted_at')
            ->select([
                'products.id', 'products.name', 'products.price', 'products.sale_price',
                'products.thumbnail', 'products.rating', 'products.total_reviews',
                'products.is_featured', 'products.stock_quantity',
                'categories.name as category_name',
                'vendors.name as shop_name',
            ])
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->join('vendors', 'products.vendor_id', '=', 'vendors.id')
            ->where('vendors.status', 'active');

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $q = '%' . $request->search . '%';
            $query->where('products.name', 'like', $q);
        }
        if ($request->boolean('featured')) {
            $query->where('products.is_featured', true);
        }

        match ($request->get('sort', 'newest')) {
            'price_asc'   => $query->orderBy('products.price'),
            'price_desc'  => $query->orderByDesc('products.price'),
            'popular'     => $query->orderByDesc('products.total_reviews'),
            default       => $query->orderByDesc('products.created_at'),
        };

        $products = $query->paginate(20);
        $now = now();

        // Apply active deal pricing to each product
        $productIds = array_column($products->items(), 'id');
        [$flashMap, $dealMap, $campMap] = $this->activeDealMaps($productIds, $now);

        $enriched = array_map(
            fn($p) => $this->applyDealPrice($p, $flashMap, $dealMap, $campMap),
            $products->items()
        );

        return response()->json([
            'success' => true,
            'data'    => $enriched,
            'meta'    => ['total' => $products->total(), 'last_page' => $products->lastPage()],
        ]);
    }

    // GET /eshop/products/{id}
    public function product($id)
    {
        $product = DB::table('products')
            ->where('products.id', $id)
            ->whereNull('products.deleted_at')
            ->join('vendors', 'products.vendor_id', '=', 'vendors.id')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->select(['products.*', 'vendors.name as shop_name', 'categories.name as category_name'])
            ->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        // Gallery: thumbnail first, then extra images
        $galleryRows = DB::table('product_images')->where('product_id', $id)->orderBy('sort_order')->get();
        $images = $galleryRows->map(fn($img) => [
            'id'    => $img->id,
            'image' => str_starts_with($img->image, 'http') ? $img->image : url('/api/v1/img/'.$img->image),
        ]);

        // Variants: cast is_active to bool, decode attributes JSON
        $variants = DB::table('product_variants')->where('product_id', $id)->where('is_active', 1)->get()
            ->map(fn($v) => array_merge((array)$v, [
                'is_active'  => true,
                'attributes' => $v->attributes ? json_decode($v->attributes, true) : null,
            ]));

        // Apply active deal pricing
        $now = now();
        [$flashMap, $dealMap, $campMap] = $this->activeDealMaps([$id], $now);
        $productArr = $this->applyDealPrice((array)$product, $flashMap, $dealMap, $campMap);

        return response()->json([
            'success' => true,
            'data'    => array_merge($productArr, ['images' => $images, 'variants' => $variants]),
        ]);
    }

    // POST /eshop/order (auth)
    public function createOrder(Request $request)
    {
        $v = Validator::make($request->all(), [
            'items'          => 'required|array|min:1',
            'items.*.product_id'  => 'required|exists:products,id',
            'items.*.quantity'    => 'required|integer|min:1',
            'items.*.variant_id'  => 'nullable|exists:product_variants,id',
            'delivery_address'    => 'required|array',
            'payment_method'      => 'required|in:wallet,cod,mobile_pay',
        ]);
        if ($v->fails()) return response()->json(['success' => false, 'errors' => $v->errors()], 422);

        $user   = $request->user();
        $total  = 0;
        $lines  = [];

        // Load all products at once, then apply active deal pricing
        $productIds   = array_column($request->items, 'product_id');
        $now          = now();
        [$flashMap, $dealMap, $campMap] = $this->activeDealMaps($productIds, $now);

        foreach ($request->items as $item) {
            $product = DB::table('products')->find($item['product_id']);

            // Get effective price considering active deals
            $productArr = $this->applyDealPrice((array)$product, $flashMap, $dealMap, $campMap);
            $price = (float)($productArr['sale_price'] ?? $productArr['price'] ?? $product->price);

            $variant = null;
            if (!empty($item['variant_id'])) {
                $variant = DB::table('product_variants')->find($item['variant_id']);
                $price   = (float)($variant?->price ?? $price);
            }
            $sub    = $price * $item['quantity'];
            $total += $sub;

            // Build meta with variant info for display in orders
            $meta = null;
            if ($variant) {
                $meta = json_encode([
                    'variant_id'    => $variant->id,
                    'variant_name'  => $variant->name,
                    'variant_attrs' => $variant->attributes ? json_decode($variant->attributes, true) : null,
                ]);
            }

            $lines[] = [
                'product_id' => $product->id,
                'name'       => $product->name,
                'price'      => $price,
                'quantity'   => $item['quantity'],
                'total'      => $sub,
                'variant_id' => $variant?->id ?? null,
                'meta'       => $meta,
            ];
        }

        $customerDistrictId = $request->input('district_id') ?? ($request->delivery_address['district_id'] ?? null);
        $deliveryFee = \App\Helpers\DeliveryPricing::forShopOrLaundry($customerDistrictId ? (int)$customerDistrictId : null, 2.00);
        $total += $deliveryFee;

        if ($request->payment_method === 'wallet') {
            if (!$user->wallet || $user->wallet->balance < $total) {
                return response()->json(['success' => false, 'message' => 'Insufficient wallet balance'], 422);
            }
        }

        $order = DB::transaction(function () use ($request, $user, $total, $deliveryFee, $lines) {
            $subtotal = $total - $deliveryFee;
            $order = Order::create([
                'order_number'    => 'SHOP-' . strtoupper(Str::random(8)),
                'user_id'         => $user->id,
                'module_slug'     => 'eshop',
                'status'          => 'pending',
                'payment_method'  => $request->payment_method,
                'payment_status'  => $request->payment_method === 'wallet' ? 'paid' : 'unpaid',
                'delivery_address'=> $request->delivery_address,
                'subtotal'        => $subtotal,
                'delivery_fee'    => $deliveryFee,
                'total_amount'    => $total,
                'placed_at'       => now(),
            ]);

            foreach ($lines as $line) {
                DB::table('order_items')->insert([
                    'order_id'   => $order->id,
                    'product_id' => $line['product_id'],
                    'name'       => $line['name'],
                    'price'      => $line['price'],
                    'quantity'   => $line['quantity'],
                    'total'      => $line['total'],
                ]);
            }

            OrderStatusHistory::create([
                'order_id'   => $order->id,
                'status'     => 'pending',
                'note'       => 'Shop order placed',
                'actor_id'   => $user->id,
                'actor_type' => 'App\\Models\\User',
                'changed_by' => $user->id,
            ]);

            if ($request->payment_method === 'wallet') {
                $user->wallet->decrement('balance', $total);
            }

            return $order;
        });

        // ── Push notification: order placed ──────────────────────────────
        try {
            if (!empty($user->fcm_token)) {
                \App\Services\FcmService::sendOrderUpdate(
                    $user->fcm_token,
                    $order->order_number,
                    'pending',
                    $order->id,
                    'eshop',
                );
            }
        } catch (\Throwable) {}
        return response()->json([
            'success' => true, 'message' => 'Order placed successfully!',
            'data'    => ['order_number' => $order->order_number, 'total' => $total],
        ], 201);
    }

    // GET /eshop/home — all home data in one request
    public function home()
    {
        $module = DB::table('modules')->where('slug', 'eshop')->first();
        $mid    = $module?->id;
        $now    = now();

        // Banners — admin stores link_value='eshop' (not module_slug)
        $banners = DB::table('banners')
            ->where('is_active', true)
            ->where(fn($q) => $q->where('module_slug', 'eshop')
                ->orWhere('link_value', 'eshop'))
            ->orderBy('sort_order')
            ->get(['id','title','subtitle','image','action_url'])
            ->map(fn($b) => (object)[
                'id'         => $b->id,
                'title'      => $b->title,
                'subtitle'   => $b->subtitle,
                'image'      => $b->image ? (str_starts_with($b->image,'http') ? $b->image : url('/api/v1/img/'.$b->image)) : null,
                'action_url' => $b->action_url,
            ]);

        // Categories
        $categories = DB::table('categories')
            ->where('module_id', $mid)->whereNull('vendor_id')
            ->whereNull('deleted_at')
            ->where('is_active', true)->orderBy('sort_order')
            ->get(['id','name','image','slug'])
            ->map(fn($c) => array_merge((array)$c, ['image' => $this->resolveImg($c->image)]));

        // Active flash deals with dynamic pricing applied per-product
        $flashDealsRaw = FlashDeal::where('is_active', true)
            ->where('ends_at', '>', $now)
            ->with(['products' => fn($q) => $q->whereNull('products.deleted_at')->where('products.is_available', true)->select(['products.id','products.name','products.price','products.sale_price','products.thumbnail'])->limit(10)])
            ->orderByDesc('created_at')->get();

        $flashDeals = $flashDealsRaw->map(function ($deal) {
            $deal->products = $deal->products->map(function ($p) use ($deal) {
                $effective = $this->calcEffectivePrice(
                    (float)$p->price,
                    $deal->discount_type ?? 'percentage',
                    (float)($deal->discount_value ?? 0),
                    isset($p->pivot->override_price) && $p->pivot->override_price > 0 ? (float)$p->pivot->override_price : null
                );
                $p->sale_price = $effective;
                $p->deal_badge = '⚡ Flash';
                $p->thumbnail  = $this->resolveImg($p->thumbnail);
                return $p;
            });
            return $deal;
        });

        // Deals of day — only active ones within ends_at, with discount applied, skip deleted products
        $dealsOfDayRaw = DealOfDay::where('is_active', true)
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->whereHas('product', fn($q) => $q->whereNull('deleted_at')->where('is_available', true))
            ->with(['product' => fn($q) => $q->select(['id','name','price','sale_price','thumbnail','rating','total_reviews'])])
            ->orderBy('sort_order')->get();

        $dealsOfDay = $dealsOfDayRaw->map(function ($deal) {
            if ($deal->product) {
                $p = $deal->product;
                $p->sale_price = $this->calcEffectivePrice(
                    (float)$p->price,
                    $deal->discount_type ?? 'percentage',
                    (float)($deal->discount_value ?? 0)
                );
                $p->deal_badge  = '🔥 Deal';
                $p->thumbnail   = $this->resolveImg($p->thumbnail);
            }
            return $deal;
        });

        // Campaigns with discount applied to products
        $campaignsRaw = EshopCampaign::where('is_active', true)
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->orderBy('sort_order')->get(['id','title','subtitle','banner','badge','discount_type','discount_value']);

        $campaigns = $campaignsRaw;

        // Featured products with deal pricing
        $featuredRaw = DB::table('products')
            ->where('products.module_id', $mid)->where('products.is_available', true)->where('products.is_featured', true)
            ->whereNull('products.deleted_at')
            ->join('vendors', 'products.vendor_id', '=', 'vendors.id')
            ->select(['products.id','products.name','products.price','products.sale_price','products.thumbnail','products.rating','products.total_reviews'])
            ->orderByDesc('products.created_at')->limit(10)->get();

        $featuredIds = $featuredRaw->pluck('id')->toArray();
        [$flashMap, $dealMap, $campMap] = $this->activeDealMaps($featuredIds, $now);
        $featured = $featuredRaw->map(fn($p) => $this->applyDealPrice($p, $flashMap, $dealMap, $campMap));

        return response()->json(['success' => true, 'data' => [
            'banners'          => $banners,
            'categories'       => $categories,
            'flash_deals'      => $flashDeals,
            'deals_of_day'     => $dealsOfDay,
            'campaigns'        => $campaigns,
            'featured_products'=> $featured,
        ]]);
    }

    // GET /eshop/flash-deals
    public function flashDeals()
    {
        $now   = now();
        $deals = FlashDeal::where('is_active', true)
            ->where('ends_at', '>', $now)
            ->with(['products' => fn($q) => $q->whereNull('products.deleted_at')->where('products.is_available', true)->select(['products.id','products.name','products.price','products.sale_price','products.thumbnail','products.rating'])])
            ->orderByDesc('created_at')->get();

        $deals = $deals->map(function ($deal) {
            $deal->products = $deal->products->map(function ($p) use ($deal) {
                $p->sale_price = $this->calcEffectivePrice(
                    (float)$p->price,
                    $deal->discount_type ?? 'percentage',
                    (float)($deal->discount_value ?? 0),
                    isset($p->pivot->override_price) && $p->pivot->override_price > 0 ? (float)$p->pivot->override_price : null
                );
                $p->deal_badge = '⚡ Flash';
                $p->thumbnail  = $this->resolveImg($p->thumbnail);
                return $p;
            });
            return $deal;
        });

        return response()->json(['success' => true, 'data' => $deals]);
    }

    // GET /eshop/deals-of-day
    public function dealsOfDay()
    {
        $now  = now();
        $deals = DealOfDay::where('is_active', true)
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->whereHas('product', fn($q) => $q->whereNull('deleted_at')->where('is_available', true))
            ->with(['product' => fn($q) => $q->select(['id','name','price','sale_price','thumbnail','rating','total_reviews','description'])])
            ->orderBy('sort_order')->get();

        $deals = $deals->map(function ($deal) {
            if ($deal->product) {
                $deal->product->sale_price = $this->calcEffectivePrice(
                    (float)$deal->product->price,
                    $deal->discount_type ?? 'percentage',
                    (float)($deal->discount_value ?? 0)
                );
                $deal->product->deal_badge = '🔥 Deal';
                $deal->product->thumbnail  = $this->resolveImg($deal->product->thumbnail);
            }
            return $deal;
        });

        return response()->json(['success' => true, 'data' => $deals]);
    }

    // GET /eshop/campaigns
    public function campaigns()
    {
        $now   = now();
        $camps = EshopCampaign::where('is_active', true)
            ->where(fn($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now))
            ->with(['products' => fn($q) => $q->whereNull('products.deleted_at')->where('products.is_available', true)->select(['products.id','products.name','products.price','products.sale_price','products.thumbnail','products.rating'])->limit(8)])
            ->orderBy('sort_order')->get();

        $camps = $camps->map(function ($camp) {
            $camp->products = $camp->products->map(function ($p) use ($camp) {
                $p->sale_price = $this->calcEffectivePrice(
                    (float)$p->price,
                    $camp->discount_type ?? 'percentage',
                    (float)($camp->discount_value ?? 0)
                );
                $p->deal_badge = '🏷️ Offer';
                $p->thumbnail  = $this->resolveImg($p->thumbnail);
                return $p;
            });
            return $camp;
        });

        return response()->json(['success' => true, 'data' => $camps]);
    }

    // POST /eshop/coupon/validate (auth)
    public function validateCoupon(Request $request)
    {
        $request->validate(['code' => 'required|string', 'order_amount' => 'required|numeric|min:0']);

        $coupon = Coupon::where('code', strtoupper($request->code))
            ->where('module_slug', 'eshop')
            ->where('is_active', true)
            ->first();

        if (!$coupon) {
            return response()->json(['valid' => false, 'message' => 'Coupon not found or inactive.']);
        }
        if ($coupon->starts_at && $coupon->starts_at > now()) {
            return response()->json(['valid' => false, 'message' => 'Coupon is not active yet.']);
        }
        if ($coupon->ends_at && $coupon->ends_at < now()) {
            return response()->json(['valid' => false, 'message' => 'Coupon has expired.']);
        }
        if ($coupon->usage_limit && $coupon->used_count >= $coupon->usage_limit) {
            return response()->json(['valid' => false, 'message' => 'Coupon usage limit reached.']);
        }
        if ($request->order_amount < ($coupon->min_order_amount ?? 0)) {
            return response()->json(['valid' => false, 'message' => 'Order amount too low for this coupon. Minimum: $'.number_format($coupon->min_order_amount,2)]);
        }

        // Calculate discount
        $discount = $coupon->type === 'percentage'
            ? ($request->order_amount * $coupon->value / 100)
            : $coupon->value;
        if ($coupon->max_discount) $discount = min($discount, $coupon->max_discount);

        return response()->json([
            'valid'          => true,
            'discount_type'  => $coupon->type,
            'discount_value' => $coupon->value,
            'discount_amount'=> round($discount, 2),
            'max_discount'   => $coupon->max_discount,
            'message'        => 'Coupon applied! You save $'.number_format($discount, 2),
        ]);
    }
}
