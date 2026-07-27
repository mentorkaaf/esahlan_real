<?php

namespace App\Http\Controllers\Admin;

use App\Services\FcmService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\DealOfDay;
use App\Models\EshopCampaign;
use App\Models\FlashDeal;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeValue;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminEShopController extends Controller
{
    // ── Helpers ──────────────────────────────────────────────────────────────

    private function eshopModuleId(): ?int
    {
        return DB::table('modules')->where('slug', 'eshop')->value('id');
    }

    private function storeUpload($file, string $folder = 'eshop'): string
    {
        $path = $file->store($folder, 'public');
        return url('/api/v1/img/' . $path);
    }

    private function syncVariants(int $productId, array $variants): void
    {
        $keepIds = [];
        foreach ($variants as $v) {
            if (empty($v['name']) || !isset($v['price'])) continue;
            // Parse "Color:Red, Size:XL" into {"Color":"Red","Size":"XL"}
            $attrs = [];
            if (!empty(trim($v['attributes_raw'] ?? ''))) {
                foreach (explode(',', $v['attributes_raw']) as $pair) {
                    $parts = explode(':', trim($pair), 2);
                    if (count($parts) === 2 && $parts[0] !== '') {
                        $attrs[trim($parts[0])] = trim($parts[1]);
                    }
                }
            }
            $payload = [
                'product_id'     => $productId,
                'name'           => $v['name'],
                'price'          => (float)$v['price'],
                'stock_quantity' => (int)($v['stock_quantity'] ?? 0),
                'sku'            => $v['sku'] ?? null,
                'attributes'     => !empty($attrs) ? json_encode($attrs) : null,
                'is_active'      => true,
                'updated_at'     => now(),
            ];
            if (!empty($v['id'])) {
                DB::table('product_variants')->where('id', $v['id'])->update($payload);
                $keepIds[] = (int)$v['id'];
            } else {
                $payload['created_at'] = now();
                $keepIds[] = DB::table('product_variants')->insertGetId($payload);
            }
        }
        // Deactivate removed variants (don't hard-delete in case orders reference them)
        DB::table('product_variants')
            ->where('product_id', $productId)
            ->when(!empty($keepIds), fn($q) => $q->whereNotIn('id', $keepIds))
            ->update(['is_active' => false]);
    }

    public function productImageDelete(int $imageId)
    {
        $img = DB::table('product_images')->find($imageId);
        if ($img) {
            // image stored as full URL — extract relative path for storage deletion
            $relative = str_replace(url('/api/v1/img/'), '', $img->image);
            \Illuminate\Support\Facades\Storage::disk('public')->delete($relative);
            DB::table('product_images')->delete($imageId);
        }
        return back()->with('success', 'Image deleted.');
    }

    // ── Index (dashboard) ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $moduleId = $this->eshopModuleId();

        // Stats
        $stats = [
            'total_products'   => Product::where('module_id', $moduleId)->count(),
            'active_products'  => Product::where('module_id', $moduleId)->where('is_available', true)->count(),
            'total_orders'     => DB::table('orders')->where('module_slug', 'eshop')->count(),
            'today_revenue'    => DB::table('orders')->where('module_slug', 'eshop')
                                     ->whereDate('created_at', today())->sum('total_amount'),
            'active_coupons'   => Coupon::where('module_slug', 'eshop')->where('is_active', true)->count(),
            'flash_deals'      => FlashDeal::where('is_active', true)->where('ends_at', '>', now())->count(),
        ];

        // Products
        $productQuery = Product::with(['category'])
            ->where('module_id', $moduleId)
            ->orderByDesc('created_at');
        if ($request->filled('search'))      $productQuery->where('name', 'like', '%'.$request->search.'%');
        if ($request->filled('category_id')) $productQuery->where('category_id', $request->category_id);
        if ($request->filled('status')) {
            $productQuery->where('is_available', $request->status === 'active');
        }
        $products = $productQuery->paginate(25)->withQueryString();

        // Categories
        $categories = Category::where('module_id', $moduleId)
            ->with('parent')
            ->orderBy('sort_order')
            ->get();

        // Attributes with values
        $attributes = ProductAttribute::with('values')->orderBy('sort_order')->get();

        // Units
        $units = ProductUnit::orderBy('name')->get();

        // Flash Deals with product count
        $flashDeals = FlashDeal::withCount('products')->orderByDesc('created_at')->get();

        // Deals of day with product
        $dealsOfDay = DealOfDay::with('product')->orderBy('sort_order')->get();

        // Coupons (eshop only)
        $coupons = Coupon::where('module_slug', 'eshop')->orderByDesc('created_at')->get();

        // Campaigns with product count
        $campaigns = EshopCampaign::withCount('products')->orderBy('sort_order')->get();

        // Orders
        $orders = DB::table('orders')
            ->where('module_slug', 'eshop')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->select('orders.*', 'users.name as customer_name', 'users.phone as customer_phone')
            ->orderByDesc('orders.created_at')
            ->paginate(20);

        // ── Multivendor: Vendors ──────────────────────────────────────────
        $vendors = DB::table('vendors')
            ->where('module_id', $moduleId)
            ->whereNull('deleted_at')
            ->select([
                'id', 'name', 'email', 'phone', 'logo', 'status',
                'is_approved', 'is_featured', 'is_open',
                'commission_type', 'commission_value',
                'rating', 'review_count', 'created_at',
            ])
            ->orderByDesc('created_at')
            ->paginate(25, ['*'], 'vendor_page');

        // Enrich vendors with product + order counts
        $vendorIds = $vendors->pluck('id')->toArray();
        $vendorProductCounts = DB::table('products')
            ->whereIn('vendor_id', $vendorIds)
            ->select('vendor_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('vendor_id')
            ->pluck('cnt', 'vendor_id');
        $vendorOrderCounts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->whereIn('products.vendor_id', $vendorIds)
            ->where('orders.module_slug', 'eshop')
            ->select('products.vendor_id', DB::raw('COUNT(DISTINCT orders.id) as cnt'))
            ->groupBy('products.vendor_id')
            ->pluck('cnt', 'vendor_id');
        $vendors->getCollection()->transform(fn($v) => (object)array_merge((array)$v, [
            'product_count' => $vendorProductCounts[$v->id] ?? 0,
            'order_count'   => $vendorOrderCounts[$v->id] ?? 0,
        ]));

        // Vendor stats for header
        $stats['total_vendors']   = DB::table('vendors')->where('module_id', $moduleId)->whereNull('deleted_at')->count();
        $stats['pending_vendors'] = DB::table('vendors')->where('module_id', $moduleId)->whereNull('deleted_at')->where('is_approved', false)->count();

        // ── Multivendor: Commissions ──────────────────────────────────────
        $commissions = DB::table('commissions')
            ->join('orders', 'commissions.order_id', '=', 'orders.id')
            ->join('vendors', 'commissions.vendor_id', '=', 'vendors.id')
            ->where('orders.module_slug', 'eshop')
            ->select([
                'commissions.id', 'commissions.order_id', 'commissions.vendor_id',
                'commissions.commission_rate', 'commissions.commission_amount',
                'commissions.vendor_earning', 'commissions.status',
                'commissions.paid_at', 'commissions.created_at',
                'orders.order_number', 'orders.total_amount',
                'vendors.name as vendor_name',
            ])
            ->orderByDesc('commissions.created_at')
            ->paginate(20, ['*'], 'comm_page');

        $stats['total_commissions']  = DB::table('commissions')
            ->join('orders', 'commissions.order_id', '=', 'orders.id')
            ->where('orders.module_slug', 'eshop')
            ->sum('commissions.commission_amount');
        $stats['pending_payouts'] = DB::table('withdrawal_requests')
            ->where('status', 'pending')
            ->where('owner_type', 'App\\Models\\Vendor')
            ->count();

        return view('admin.eshop.index', compact(
            'stats', 'products', 'categories', 'attributes', 'units',
            'flashDeals', 'dealsOfDay', 'coupons', 'campaigns', 'orders',
            'vendors', 'commissions', 'moduleId'
        ));
    }

    // ── Categories ────────────────────────────────────────────────────────

    public function categoryStore(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:150',
            'parent_id'   => 'nullable|exists:categories,id',
            'image'       => 'nullable|string|max:500',
            'image_file'  => 'nullable|image|max:5120',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'nullable|boolean',
        ]);
        if ($request->hasFile('image_file'))
            $data['image'] = $this->storeUpload($request->file('image_file'), 'eshop/categories');
        unset($data['image_file']);

        Category::create(array_merge($data, [
            'module_id'  => $this->eshopModuleId(),
            'slug'       => Str::slug($data['name']) . '-' . Str::random(5),
            'is_active'  => $request->boolean('is_active', true),
        ]));
        return back()->with('success', 'Category added successfully.');
    }

    public function categoryUpdate(Request $request, int $id)
    {
        $cat  = Category::findOrFail($id);
        $data = $request->validate([
            'name'        => 'required|string|max:150',
            'parent_id'   => 'nullable|exists:categories,id',
            'image'       => 'nullable|string|max:500',
            'image_file'  => 'nullable|image|max:5120',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'nullable|boolean',
        ]);
        if ($request->hasFile('image_file'))
            $data['image'] = $this->storeUpload($request->file('image_file'), 'eshop/categories');
        unset($data['image_file']);

        $cat->update(array_merge($data, ['is_active' => $request->boolean('is_active', true)]));
        return back()->with('success', 'Category updated.');
    }

    public function categoryDestroy(int $id)
    {
        Category::findOrFail($id)->delete();
        return back()->with('success', 'Category deleted.');
    }

    // ── Products ──────────────────────────────────────────────────────────

    public function productStore(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'description'    => 'nullable|string',
            'sku'            => 'nullable|string|max:100',
            'barcode'        => 'nullable|string|max:100',
            'brand'          => 'nullable|string|max:100',
            'unit'           => 'nullable|string|max:50',
            'unit_id'        => 'nullable|integer',
            'min_qty'        => 'nullable|integer|min:1',
            'max_qty'        => 'nullable|integer|min:1',
            'stock_quantity' => 'nullable|integer|min:0',
            'weight'         => 'nullable|numeric|min:0',
            'tags'           => 'nullable|string',
            'is_available'   => 'nullable|boolean',
            'is_featured'    => 'nullable|boolean',
            'image_file'     => 'nullable|image|max:10240',
            'thumbnail'      => 'nullable|string|max:500',
        ]);

        $thumbnail = $data['thumbnail'] ?? null;
        if ($request->hasFile('image_file'))
            $thumbnail = $this->storeUpload($request->file('image_file'), 'products');
        unset($data['image_file'], $data['thumbnail']);

        // Ensure NOT NULL integer fields have a default
        $data['stock_quantity'] = (int)($data['stock_quantity'] ?? 0);
        $data['min_qty']        = (int)($data['min_qty'] ?? 1);

        // Need a vendor_id — use a system/platform vendor or first vendor
        $vendorId = DB::table('vendors')->value('id') ?? 1;

        $product = Product::create(array_merge($data, [
            'module_id'      => $this->eshopModuleId(),
            'vendor_id'      => $vendorId,
            'slug'           => Str::slug($data['name']) . '-' . Str::random(6),
            'thumbnail'      => $thumbnail,
            'is_available'   => $request->boolean('is_available', true),
            'is_featured'    => $request->boolean('is_featured', false),
            'tags'           => $data['tags'] ?? null,
            'track_inventory'=> true,
        ]));

        // Gallery images
        if ($request->hasFile('gallery_files')) {
            foreach ($request->file('gallery_files') as $i => $file) {
                $path = $this->storeUpload($file, 'products/gallery');
                DB::table('product_images')->insert(['product_id'=>$product->id,'image'=>$path,'sort_order'=>$i,'is_primary'=>$i===0]);
            }
        }

        // Variants
        $this->syncVariants($product->id, $request->input('variants', []));

        return back()->with('success', 'Product added successfully.');
    }

    public function productUpdate(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        $data    = $request->validate([
            'name'           => 'required|string|max:200',
            'price'          => 'required|numeric|min:0',
            'sale_price'     => 'nullable|numeric|min:0',
            'category_id'    => 'nullable|exists:categories,id',
            'description'    => 'nullable|string',
            'sku'            => 'nullable|string|max:100',
            'barcode'        => 'nullable|string|max:100',
            'brand'          => 'nullable|string|max:100',
            'unit'           => 'nullable|string|max:50',
            'unit_id'        => 'nullable|integer',
            'min_qty'        => 'nullable|integer|min:1',
            'max_qty'        => 'nullable|integer|min:1',
            'stock_quantity' => 'nullable|integer|min:0',
            'weight'         => 'nullable|numeric|min:0',
            'tags'           => 'nullable|string',
            'is_available'   => 'nullable|boolean',
            'is_featured'    => 'nullable|boolean',
            'image_file'     => 'nullable|image|max:10240',
            'thumbnail'      => 'nullable|string|max:500',
        ]);

        if ($request->hasFile('image_file'))
            $data['thumbnail'] = $this->storeUpload($request->file('image_file'), 'products');
        unset($data['image_file']);

        // Ensure NOT NULL integer fields have a default
        $data['stock_quantity'] = (int)($data['stock_quantity'] ?? 0);
        $data['min_qty']        = (int)($data['min_qty'] ?? 1);

        $product->update(array_merge($data, [
            'is_available' => $request->boolean('is_available', true),
            'is_featured'  => $request->boolean('is_featured', false),
        ]));

        // Gallery images — append new ones
        if ($request->hasFile('gallery_files')) {
            $nextSort = DB::table('product_images')->where('product_id', $id)->max('sort_order') ?? -1;
            foreach ($request->file('gallery_files') as $i => $file) {
                $path = $this->storeUpload($file, 'products/gallery');
                DB::table('product_images')->insert(['product_id'=>$id,'image'=>$path,'sort_order'=>$nextSort+$i+1,'is_primary'=>0]);
            }
        }

        // Variants
        $this->syncVariants($id, $request->input('variants', []));

        return back()->with('success', 'Product updated.');
    }

    public function productDestroy(int $id)
    {
        Product::findOrFail($id)->delete();
        return back()->with('success', 'Product deleted.');
    }

    public function productDuplicate(int $id)
    {
        $original = Product::findOrFail($id);
        $copy = $original->replicate();
        $copy->name = $original->name . ' (Copy)';
        $copy->slug = Str::slug($original->name) . '-copy-' . Str::random(5);
        $copy->sku  = $original->sku ? $original->sku . '-COPY' : null;
        $copy->is_available = true;
        $copy->save();
        return back()->with('success', 'Product duplicated successfully. It is inactive by default.');
    }

    public function productToggle(int $id)
    {
        $p = Product::findOrFail($id);
        $p->update(['is_available' => !$p->is_available]);
        return back()->with('success', 'Product status toggled.');
    }

    public function productFeatureToggle(int $id)
    {
        $p = Product::findOrFail($id);
        $p->update(['is_featured' => !$p->is_featured]);
        return back()->with('success', 'Featured status toggled.');
    }

    // ── Units ─────────────────────────────────────────────────────────────

    public function unitStore(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'symbol' => 'required|string|max:20']);
        ProductUnit::create($data + ['is_active' => true]);
        return back()->with('success', 'Unit added.');
    }

    public function unitUpdate(Request $request, int $id)
    {
        $unit = ProductUnit::findOrFail($id);
        $data = $request->validate(['name' => 'required|string|max:100', 'symbol' => 'required|string|max:20', 'is_active' => 'nullable|boolean']);
        $unit->update($data + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Unit updated.');
    }

    public function unitDestroy(int $id)
    {
        ProductUnit::findOrFail($id)->delete();
        return back()->with('success', 'Unit deleted.');
    }

    // ── Attributes ────────────────────────────────────────────────────────

    public function attributeStore(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'type'       => 'required|in:select,color,text',
            'sort_order' => 'nullable|integer',
        ]);
        ProductAttribute::create($data + ['is_active' => true]);
        return back()->with('success', 'Attribute added.');
    }

    public function attributeUpdate(Request $request, int $id)
    {
        $attr = ProductAttribute::findOrFail($id);
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'type'       => 'required|in:select,color,text',
            'is_active'  => 'nullable|boolean',
        ]);
        $attr->update($data + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Attribute updated.');
    }

    public function attributeDestroy(int $id)
    {
        ProductAttribute::findOrFail($id)->delete();
        return back()->with('success', 'Attribute deleted.');
    }

    public function attributeValueStore(Request $request, int $id)
    {
        $attr = ProductAttribute::findOrFail($id);
        $data = $request->validate([
            'value'      => 'required|string|max:100',
            'color_code' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer',
        ]);
        ProductAttributeValue::create(array_merge($data, ['attribute_id' => $attr->id]));
        return back()->with('success', 'Value added.');
    }

    public function attributeValueDestroy(int $id)
    {
        ProductAttributeValue::findOrFail($id)->delete();
        return back()->with('success', 'Value deleted.');
    }

    // ── Flash Deals ───────────────────────────────────────────────────────

    public function flashDealStore(Request $request)
    {
        $data = $request->validate([
            'title'          => 'required|string|max:200',
            'subtitle'       => 'nullable|string|max:300',
            'banner'         => 'nullable|string|max:500',
            'banner_file'    => 'nullable|image|max:5120',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after:starts_at',
        ]);
        if ($request->hasFile('banner_file'))
            $data['banner'] = $this->storeUpload($request->file('banner_file'), 'eshop/flash-deals');
        unset($data['banner_file']);

        FlashDeal::create($data + ['is_active' => true]);
        return back()->with('success', 'Flash deal created.');
    }

    public function flashDealUpdate(Request $request, int $id)
    {
        $deal = FlashDeal::findOrFail($id);
        $data = $request->validate([
            'title'          => 'required|string|max:200',
            'subtitle'       => 'nullable|string|max:300',
            'banner'         => 'nullable|string|max:500',
            'banner_file'    => 'nullable|image|max:5120',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after:starts_at',
            'is_active'      => 'nullable|boolean',
        ]);
        if ($request->hasFile('banner_file'))
            $data['banner'] = $this->storeUpload($request->file('banner_file'), 'eshop/flash-deals');
        unset($data['banner_file']);

        $deal->update($data + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Flash deal updated.');
    }

    public function flashDealDestroy(int $id)
    {
        FlashDeal::findOrFail($id)->delete();
        return back()->with('success', 'Flash deal deleted.');
    }

    public function flashDealToggle(int $id)
    {
        $deal = FlashDeal::findOrFail($id);
        $deal->update(['is_active' => !$deal->is_active]);
        return back()->with('success', 'Flash deal status toggled.');
    }

    public function flashDealProductAdd(Request $request, int $id)
    {
        $deal = FlashDeal::findOrFail($id);
        $data = $request->validate([
            'product_id'     => 'required|exists:products,id',
            'override_price' => 'nullable|numeric|min:0',
        ]);
        DB::table('flash_deal_products')->updateOrInsert(
            ['flash_deal_id' => $deal->id, 'product_id' => $data['product_id']],
            ['override_price' => $data['override_price'] ?? null]
        );
        return back()->with('success', 'Product added to flash deal.');
    }

    public function flashDealProductRemove(int $dealId, int $productId)
    {
        DB::table('flash_deal_products')
            ->where('flash_deal_id', $dealId)
            ->where('product_id', $productId)
            ->delete();
        return back()->with('success', 'Product removed from flash deal.');
    }

    // ── Deals of Day ──────────────────────────────────────────────────────

    public function dealOfDayStore(Request $request)
    {
        $data = $request->validate([
            'product_id'     => 'required|exists:products,id',
            'badge'          => 'nullable|string|max:50',
            'discount_type'  => 'nullable|in:percentage,fixed',
            'discount_value' => 'nullable|numeric|min:0',
            'ends_at'        => 'nullable|date',
            'sort_order'     => 'nullable|integer',
        ]);
        DealOfDay::updateOrCreate(
            ['product_id' => $data['product_id']],
            [
                'badge'          => $data['badge'] ?? null,
                'discount_type'  => $data['discount_type'] ?? 'percentage',
                'discount_value' => $data['discount_value'] ?? 0,
                'ends_at'        => $data['ends_at'] ?? null,
                'sort_order'     => $data['sort_order'] ?? 0,
                'is_active'      => true,
            ]
        );
        return back()->with('success', 'Deal of the day added.');
    }

    public function dealOfDayDestroy(int $id)
    {
        DealOfDay::findOrFail($id)->delete();
        return back()->with('success', 'Deal removed.');
    }

    // ── Coupons ───────────────────────────────────────────────────────────

    public function couponStore(Request $request)
    {
        $data = $request->validate([
            'code'             => 'required|string|max:50|unique:coupons,code',
            'title'            => 'nullable|string|max:150',
            'description'      => 'nullable|string',
            'type'             => 'required|in:percentage,fixed',
            'value'            => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount'     => 'nullable|numeric|min:0',
            'usage_limit'      => 'nullable|integer|min:1',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date',
            'is_active'        => 'nullable|boolean',
        ]);
        Coupon::create(array_merge($data, [
            'module_slug' => 'eshop',
            'is_active'   => $request->boolean('is_active', true),
            'used_count'  => 0,
        ]));
        return back()->with('success', 'Coupon created.');
    }

    public function couponUpdate(Request $request, int $id)
    {
        $coupon = Coupon::findOrFail($id);
        $data   = $request->validate([
            'code'             => 'required|string|max:50|unique:coupons,code,'.$id,
            'title'            => 'nullable|string|max:150',
            'description'      => 'nullable|string',
            'type'             => 'required|in:percentage,fixed',
            'value'            => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount'     => 'nullable|numeric|min:0',
            'usage_limit'      => 'nullable|integer|min:1',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date',
            'is_active'        => 'nullable|boolean',
        ]);
        $coupon->update(array_merge($data, ['is_active' => $request->boolean('is_active', true)]));
        return back()->with('success', 'Coupon updated.');
    }

    public function couponDestroy(int $id)
    {
        Coupon::findOrFail($id)->delete();
        return back()->with('success', 'Coupon deleted.');
    }

    public function couponToggle(int $id)
    {
        $c = Coupon::findOrFail($id);
        $c->update(['is_active' => !$c->is_active]);
        return back()->with('success', 'Coupon toggled.');
    }

    // ── Campaigns ─────────────────────────────────────────────────────────

    public function campaignStore(Request $request)
    {
        $data = $request->validate([
            'title'          => 'required|string|max:200',
            'subtitle'       => 'nullable|string|max:300',
            'banner'         => 'nullable|string|max:500',
            'banner_file'    => 'nullable|image|max:5120',
            'badge'          => 'nullable|string|max:50',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'starts_at'      => 'nullable|date',
            'ends_at'        => 'nullable|date',
            'sort_order'     => 'nullable|integer',
        ]);
        if ($request->hasFile('banner_file'))
            $data['banner'] = $this->storeUpload($request->file('banner_file'), 'eshop/campaigns');
        unset($data['banner_file']);

        EshopCampaign::create($data + ['is_active' => true]);
        return back()->with('success', 'Campaign created.');
    }

    public function campaignUpdate(Request $request, int $id)
    {
        $camp = EshopCampaign::findOrFail($id);
        $data = $request->validate([
            'title'          => 'required|string|max:200',
            'subtitle'       => 'nullable|string|max:300',
            'banner'         => 'nullable|string|max:500',
            'banner_file'    => 'nullable|image|max:5120',
            'badge'          => 'nullable|string|max:50',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'starts_at'      => 'nullable|date',
            'ends_at'        => 'nullable|date',
            'sort_order'     => 'nullable|integer',
            'is_active'      => 'nullable|boolean',
        ]);
        if ($request->hasFile('banner_file'))
            $data['banner'] = $this->storeUpload($request->file('banner_file'), 'eshop/campaigns');
        unset($data['banner_file']);

        $camp->update($data + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Campaign updated.');
    }

    public function campaignDestroy(int $id)
    {
        EshopCampaign::findOrFail($id)->delete();
        return back()->with('success', 'Campaign deleted.');
    }

    public function campaignToggle(int $id)
    {
        $c = EshopCampaign::findOrFail($id);
        $c->update(['is_active' => !$c->is_active]);
        return back()->with('success', 'Campaign toggled.');
    }

    public function campaignProductAdd(Request $request, int $id)
    {
        $camp = EshopCampaign::findOrFail($id);
        $data = $request->validate(['product_id' => 'required|exists:products,id']);
        DB::table('eshop_campaign_products')->updateOrInsert(
            ['campaign_id' => $camp->id, 'product_id' => $data['product_id']]
        );
        return back()->with('success', 'Product added to campaign.');
    }

    public function campaignProductRemove(int $campaignId, int $productId)
    {
        DB::table('eshop_campaign_products')
            ->where('campaign_id', $campaignId)
            ->where('product_id', $productId)
            ->delete();
        return back()->with('success', 'Product removed from campaign.');
    }

    // ── Orders ────────────────────────────────────────────────────────────

    public function orderUpdateStatus(Request $request, int $id)
    {
        $data = $request->validate(['status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled,refunded']);
        DB::table('orders')->where('id',$id)->update(['status'=>$data['status'],'updated_at'=>now()]);
        try{$o=\App\Models\Order::with('user')->find($id);if($o?->user?->fcm_token)FcmService::sendOrderUpdate($o->user->fcm_token,$o->order_number??'#'.$id,$data['status'],$id,$o->module_slug);}catch(\Throwable $er){}
        return back()->with('success', 'Order status updated.');
    }

    // ── Image Upload ──────────────────────────────────────────────────────

    public function uploadImage(Request $request)
    {
        $request->validate(['image' => 'required|image|max:10240']);
        $url = $this->storeUpload($request->file('image'), 'eshop/uploads');
        return response()->json(['url' => $url]);
    }

    // ── Multivendor: Vendor Actions ───────────────────────────────────────

    public function vendorApprove(Request $request, int $id)
    {
        $approved = $request->boolean('is_approved');
        DB::table('vendors')->where('id', $id)->update([
            'is_approved' => $approved,
            'status'      => $approved ? 'active' : 'inactive',
            'updated_at'  => now(),
        ]);
        return back()->with('success', $approved ? 'Vendor approved.' : 'Vendor rejected.');
    }

    public function vendorToggleFeatured(int $id)
    {
        $vendor = DB::table('vendors')->find($id);
        if (!$vendor) return back()->with('error', 'Vendor not found.');
        DB::table('vendors')->where('id', $id)->update([
            'is_featured' => !$vendor->is_featured,
            'updated_at'  => now(),
        ]);
        return back()->with('success', 'Vendor featured status updated.');
    }

    public function vendorUpdateCommission(Request $request, int $id)
    {
        $data = $request->validate([
            'commission_type'  => 'required|in:percentage,fixed',
            'commission_value' => 'required|numeric|min:0|max:100',
        ]);
        DB::table('vendors')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
        return back()->with('success', 'Commission updated.');
    }

    // ── Multivendor: Commission Actions ──────────────────────────────────

    public function commissionMarkPaid(int $id)
    {
        DB::table('commissions')->where('id', $id)->update([
            'status'  => 'paid',
            'paid_at' => now(),
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Commission marked as paid.');
    }

    // ── Vendor Withdrawals ────────────────────────────────────────────────────

    public function withdrawals(\Illuminate\Http\Request $request)
    {
        $status = $request->input('status', 'pending');

        $withdrawals = DB::table('withdrawal_requests')
            ->join('vendors', function ($j) {
                $j->on('withdrawal_requests.owner_id', '=', 'vendors.id')
                  ->where('withdrawal_requests.owner_type', 'App\\Models\\Vendor');
            })
            ->where('vendors.module_slug', 'eshop')
            ->when($status !== 'all', fn($q) => $q->where('withdrawal_requests.status', $status))
            ->select('withdrawal_requests.*', 'vendors.name as vendor_name', 'vendors.logo as vendor_logo')
            ->orderByDesc('withdrawal_requests.created_at')
            ->paginate(20)->withQueryString();

        $counts = DB::table('withdrawal_requests')
            ->join('vendors', function ($j) {
                $j->on('withdrawal_requests.owner_id', '=', 'vendors.id')
                  ->where('withdrawal_requests.owner_type', 'App\\Models\\Vendor');
            })
            ->where('vendors.module_slug', 'eshop')
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return view('admin.eshop.withdrawals', compact('withdrawals', 'status', 'counts'));
    }

    public function withdrawalApprove(\Illuminate\Http\Request $request, int $id)
    {
        $data = $request->validate([
            'transaction_reference' => 'required|string|max:200',
            'admin_note'            => 'nullable|string|max:500',
        ]);

        DB::table('withdrawal_requests')->where('id', $id)->update([
            'status'                => 'processed',
            'transaction_reference' => $data['transaction_reference'],
            'admin_note'            => $data['admin_note'] ?? null,
            'processed_at'          => now(),
            'processed_by'          => auth()->id(),
            'updated_at'            => now(),
        ]);

        return back()->with('success', 'Withdrawal approved and marked as processed.');
    }

    public function withdrawalReject(\Illuminate\Http\Request $request, int $id)
    {
        $request->validate(['admin_note' => 'required|string|max:500']);

        DB::table('withdrawal_requests')->where('id', $id)->update([
            'status'       => 'rejected',
            'admin_note'   => $request->admin_note,
            'processed_at' => now(),
            'processed_by' => auth()->id(),
            'updated_at'   => now(),
        ]);

        return back()->with('success', 'Withdrawal request rejected.');
    }

    public function withdrawalProcess(\Illuminate\Http\Request $request, int $id)
    {
        $data = $request->validate([
            'status'     => 'required|in:approved,rejected',
            'admin_note' => 'nullable|string|max:500',
        ]);
        DB::table('withdrawal_requests')->where('id', $id)->update(array_merge($data, [
            'processed_at' => now(),
            'updated_at'   => now(),
        ]));
        return back()->with('success', 'Withdrawal request processed.');
    }
}
