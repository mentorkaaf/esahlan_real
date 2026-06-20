<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\Category;
use App\Models\Product;
use App\Models\Addon;
use App\Models\Banner;
use App\Models\Coupon;
use App\Models\DiscountCampaign;
use App\Models\Order;
use App\Services\FcmService;
use App\Models\District;
use App\Models\Module;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminEFoodController extends Controller
{
    // ─── Helper: resolve efood module ────────────────────────────────
    private function efoodModule()
    {
        return Module::where('slug', 'efood')->first();
    }

    // ════════════════════════════════════════════════════════════════
    // INDEX — tabbed dashboard
    // ════════════════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $module    = $this->efoodModule();
        $moduleId  = $module?->id;

        $restaurants = Vendor::when($moduleId, fn($q) => $q->where('vendors.module_id', $moduleId))
            ->with(['district', 'user:id,phone,email'])
            ->orderByDesc('vendors.created_at')
            ->paginate(20);

        $categories = Category::where('module_id', $moduleId)
            ->with('vendor:id,name')
            ->orderByRaw('vendor_id IS NOT NULL, vendor_id')
            ->orderBy('sort_order')
            ->get();

        $allRestaurants = Vendor::when($moduleId, fn($q) => $q->where('module_id', $moduleId))
            ->orderBy('name')->get(['id', 'name']);

        $foodItems = Product::whereHas('vendor', fn($q) => $q->when($moduleId, fn($q2) => $q2->where('module_id', $moduleId)))
            ->with(['vendor', 'category'])
            ->orderByDesc('created_at')
            ->paginate(25);

        $addons = Addon::whereHas('vendor', fn($q) => $q->when($moduleId, fn($q2) => $q2->where('module_id', $moduleId)))
            ->with('vendor')
            ->orderBy('name')
            ->get();

        $banners = Banner::where(function ($q) use ($moduleId) {
                $q->where('module_id', $moduleId)
                  ->orWhere('module_slug', 'efood');
            })
            ->orWhere(function ($q) {
                $q->whereNull('module_id')->whereNull('module_slug');
            })
            ->orderBy('sort_order')
            ->get();

        $allBanners = Banner::whereNull('module_id')
            ->orWhere('module_id', $moduleId)
            ->orWhere('module_slug', 'efood')
            ->orderBy('sort_order')
            ->get();

        $coupons = Coupon::where(function ($q) use ($moduleId) {
                $q->where('module_id', $moduleId);
            })
            ->with('vendor')
            ->orderByDesc('created_at')
            ->get();

        $orders = Order::where('module_slug', 'efood')
            ->with(['user', 'vendor'])
            ->orderByDesc('created_at')
            ->paginate(20);

        $campaigns = DiscountCampaign::with('vendor')
            ->whereHas('vendor', fn($q) => $q->when($moduleId, fn($q2) => $q2->where('module_id', $moduleId)))
            ->orderByDesc('created_at')
            ->get();

        $districts = District::orderBy('name')->get();

        // Stats
        $stats = [
            'total_restaurants' => Vendor::when($moduleId, fn($q) => $q->where('vendors.module_id', $moduleId))->count(),
            'active_restaurants'=> Vendor::when($moduleId, fn($q) => $q->where('vendors.module_id', $moduleId))->where('vendors.status', 'active')->count(),
            'total_items'       => $foodItems->total(),
            'total_orders'      => Order::where('module_slug', 'efood')->count(),
            'pending_orders'    => Order::where('module_slug', 'efood')->where('status', 'pending')->count(),
            'today_revenue'     => Order::where('module_slug', 'efood')->whereDate('created_at', today())->sum('total_amount'),
        ];

        return view('admin.efood.index', compact(
            'restaurants', 'categories', 'allRestaurants',
            'foodItems', 'addons', 'allBanners', 'coupons', 'campaigns', 'orders',
            'districts', 'stats', 'module'
        ));
    }

    // ════════════════════════════════════════════════════════════════
    // RESTAURANTS
    // ════════════════════════════════════════════════════════════════

    /** Store uploaded image and return CORS-safe proxy URL */
    private function storeUpload($file, string $folder = 'efood'): string
    {
        $path = $file->store($folder, 'public');
        // Use /api/img/ proxy — goes through Laravel middleware (has CORS headers)
        // so Flutter Web (CanvasKit) can load images cross-origin.
        return url('/api/v1/img/' . $path);
    }

    public function restaurantStore(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:200',
            'vendor_type'   => 'nullable|string|max:100',
            'description'   => 'nullable|string',
            'district_id'   => 'nullable|exists:districts,id',
            'address'       => 'nullable|string|max:500',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:200',
            'logo'          => 'nullable|max:500',
            'logo_file'     => 'nullable|image|max:5120',
            'cover_image'   => 'nullable|max:500',
            'cover_file'    => 'nullable|image|max:5120',
            'delivery_time' => 'nullable|string|max:30',
            'minimum_order' => 'nullable|numeric|min:0',
            'delivery_fee'  => 'nullable|numeric|min:0',
            'is_featured'   => 'nullable|boolean',
            'status'        => 'nullable|in:active,inactive,pending',
        ]);
        if ($request->hasFile('logo_file'))  $data['logo']        = $this->storeUpload($request->file('logo_file'));
        if ($request->hasFile('cover_file')) $data['cover_image'] = $this->storeUpload($request->file('cover_file'));
        unset($data['logo_file'], $data['cover_file']);

        // Build working_hours JSON from day checkboxes + time inputs
        $workingHours = $this->buildWorkingHours($request);

        $module = $this->efoodModule();

        Vendor::create(array_merge($data, [
            'module_id'    => $module?->id,
            'module_slug'  => 'efood',
            'is_open'      => true,
            'is_active'    => true,
            'is_approved'  => true,
            'status'       => $data['status'] ?? 'active',
            'is_featured'  => $request->boolean('is_featured', false),
            'working_hours'=> $workingHours,
        ]));

        return back()->with('success', 'Restaurant added successfully!');
    }

    public function restaurantUpdate(Request $request, $id)
    {
        $vendor = Vendor::findOrFail($id);
        $data = $request->validate([
            'name'          => 'required|string|max:200',
            'vendor_type'   => 'nullable|string|max:100',
            'description'   => 'nullable|string',
            'district_id'   => 'nullable|exists:districts,id',
            'address'       => 'nullable|string|max:500',
            'phone'         => 'nullable|string|max:20',
            'email'         => 'nullable|email|max:200',
            'logo'          => 'nullable|max:500',
            'logo_file'     => 'nullable|image|max:5120',
            'cover_image'   => 'nullable|max:500',
            'cover_file'    => 'nullable|image|max:5120',
            'delivery_time' => 'nullable|string|max:30',
            'minimum_order' => 'nullable|numeric|min:0',
            'delivery_fee'  => 'nullable|numeric|min:0',
            'is_featured'   => 'nullable|boolean',
            'is_open'       => 'nullable|boolean',
            'status'        => 'nullable|in:active,inactive,pending',
            'owner_phone'   => 'nullable|string|max:20',
            'owner_email'   => 'nullable|email|max:200',
            'owner_password'=> 'nullable|string|min:4',
        ]);
        if ($request->hasFile('logo_file'))  $data['logo']        = $this->storeUpload($request->file('logo_file'));
        if ($request->hasFile('cover_file')) $data['cover_image'] = $this->storeUpload($request->file('cover_file'));
        unset($data['logo_file'], $data['cover_file'], $data['owner_phone'], $data['owner_email'], $data['owner_password']);

        $workingHours = $this->buildWorkingHours($request);

        $vendor->update(array_merge($data, [
            'is_featured'  => $request->boolean('is_featured'),
            'is_open'      => $request->boolean('is_open', true),
            'working_hours'=> $workingHours,
        ]));

        // Update vendor owner (user) credentials
        if ($vendor->user_id) {
            $ownerUpdate = [];
            if ($request->filled('owner_phone'))    $ownerUpdate['phone']    = $request->owner_phone;
            if ($request->filled('owner_email'))    $ownerUpdate['email']    = $request->owner_email;
            if ($request->filled('owner_password')) $ownerUpdate['password'] = Hash::make($request->owner_password);
            if (!empty($ownerUpdate)) {
                User::where('id', $vendor->user_id)->update($ownerUpdate);
            }
        }

        return back()->with('success', 'Restaurant updated!');
    }

    /**
     * Build working_hours JSON from request inputs.
     * Expects: wh_open[0..6], wh_open_time[0..6], wh_close_time[0..6]
     * Day 0=Sun, 1=Mon ... 6=Sat
     */
    private function buildWorkingHours(Request $request): ?string
    {
        $whOpen      = $request->input('wh_open', []);      // checked days
        $whOpenTime  = $request->input('wh_open_time', []);
        $whCloseTime = $request->input('wh_close_time', []);

        // If no working hours data submitted at all, return null (no schedule set)
        if (empty($whOpen) && empty(array_filter($whOpenTime)) && empty(array_filter($whCloseTime))) {
            return null;
        }

        $schedule = [];
        for ($day = 0; $day <= 6; $day++) {
            $isClosed  = !in_array((string)$day, array_map('strval', $whOpen));
            $openTime  = $whOpenTime[$day]  ?? '08:00';
            $closeTime = $whCloseTime[$day] ?? '22:00';

            $schedule[] = [
                'day'       => $day,
                'open'      => $openTime  ?: '08:00',
                'close'     => $closeTime ?: '22:00',
                'is_closed' => $isClosed,
            ];
        }

        return json_encode($schedule);
    }

    public function restaurantDestroy($id)
    {
        Vendor::findOrFail($id)->delete();
        return back()->with('success', 'Restaurant deleted.');
    }

    public function restaurantToggle($id)
    {
        $vendor = Vendor::findOrFail($id);
        $vendor->update(['is_open' => !$vendor->is_open]);
        return response()->json(['is_open' => $vendor->fresh()->is_open]);
    }

    // ════════════════════════════════════════════════════════════════
    // CATEGORIES
    // ════════════════════════════════════════════════════════════════

    public function categoryStore(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'image'      => 'nullable|max:500',
            'image_file' => 'nullable|image|max:5120',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);
        if ($request->hasFile('image_file')) $data['image'] = $this->storeUpload($request->file('image_file'));
        unset($data['image_file']);

        $module = $this->efoodModule();

        Category::create([
            'name'       => $data['name'],
            'slug'       => Str::slug($data['name']) . '-' . Str::random(4),
            'image'      => $data['image'] ?? null,
            'module_id'  => $module?->id,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Category added!');
    }

    public function categoryUpdate(Request $request, $id)
    {
        $cat = Category::findOrFail($id);
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'image'      => 'nullable|max:500',
            'image_file' => 'nullable|image|max:5120',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);
        if ($request->hasFile('image_file')) $data['image'] = $this->storeUpload($request->file('image_file'));
        unset($data['image_file']);
        $cat->update([
            'name'       => $data['name'],
            'image'      => $data['image'] ?? $cat->image,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_active'  => $request->boolean('is_active'),
        ]);
        return back()->with('success', 'Category updated!');
    }

    public function categoryDestroy($id)
    {
        Category::findOrFail($id)->delete();
        return back()->with('success', 'Category deleted.');
    }

    public function categoryAssign(Request $request, $id)
    {
        $request->validate(['vendor_ids' => 'required|array', 'vendor_ids.*' => 'integer|exists:vendors,id']);
        $category = Category::findOrFail($id);
        $module = $this->efoodModule();

        foreach ($request->vendor_ids as $vendorId) {
            Category::firstOrCreate(
                ['vendor_id' => $vendorId, 'name' => $category->name, 'module_id' => $module?->id],
                ['slug' => Str::slug($category->name) . '-' . Str::random(4), 'image' => $category->image, 'sort_order' => $category->sort_order, 'is_active' => true]
            );
        }

        return back()->with('success', 'Category assigned to ' . count($request->vendor_ids) . ' restaurant(s).');
    }

    // ════════════════════════════════════════════════════════════════
    // IMAGE UPLOAD  (AJAX — returns JSON)
    // ════════════════════════════════════════════════════════════════

    public function uploadImage(Request $request)
    {
        $request->validate(['image' => 'required|image|max:5120']); // 5 MB max

        $path = $request->file('image')->store('efood', 'public');
        $url  = url('/api/v1/img/' . $path);

        return response()->json(['success' => true, 'url' => $url]);
    }

    // ════════════════════════════════════════════════════════════════
    // FOOD ITEMS (Products)
    // ════════════════════════════════════════════════════════════════

    /** Ensure products table has all extra columns (SQLite ALTER TABLE ADD COLUMN is safe to re-run) */
    private function ensureProductColumns(): void
    {
        // Use MySQL INFORMATION_SCHEMA (PRAGMA is SQLite-only)
        $existing = DB::select(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'"
        );
        $has = array_column(array_map('get_object_vars', $existing), 'COLUMN_NAME');

        $toAdd = [
            'compare_price'  => "ALTER TABLE products ADD COLUMN compare_price  DECIMAL(10,2) NULL",
            'cost_price'     => "ALTER TABLE products ADD COLUMN cost_price     DECIMAL(10,2) NULL",
            'sale_price'     => "ALTER TABLE products ADD COLUMN sale_price     DECIMAL(10,2) NULL",
            'thumbnail'      => "ALTER TABLE products ADD COLUMN thumbnail      VARCHAR(500)  NULL",
            'image'          => "ALTER TABLE products ADD COLUMN image          VARCHAR(500)  NULL",
            'is_available'   => "ALTER TABLE products ADD COLUMN is_available   TINYINT(1) NOT NULL DEFAULT 1",
            'is_active'      => "ALTER TABLE products ADD COLUMN is_active      TINYINT(1) NOT NULL DEFAULT 1",
            'is_featured'    => "ALTER TABLE products ADD COLUMN is_featured    TINYINT(1) NOT NULL DEFAULT 0",
            'sort_order'     => "ALTER TABLE products ADD COLUMN sort_order     INT NOT NULL DEFAULT 0",
            'available_from' => "ALTER TABLE products ADD COLUMN available_from  TIME NULL",
            'available_until'=> "ALTER TABLE products ADD COLUMN available_until TIME NULL",
        ];
        foreach ($toAdd as $col => $sql) {
            if (!in_array($col, $has)) {
                DB::statement($sql);
            }
        }
    }

    public function itemStore(Request $request)
    {
        $this->ensureProductColumns();

        $data = $request->validate([
            'vendor_id'      => 'required|exists:vendors,id',
            'category_id'    => 'nullable|exists:categories,id',
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0',
            'compare_price'  => 'nullable|numeric|min:0',
            'image'          => 'nullable|max:500',
            'image_file'     => 'nullable|image|max:5120',
            'is_featured'    => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
            'sort_order'     => 'nullable|integer',
            'available_from' => 'nullable|string|max:8',
            'available_until'=> 'nullable|string|max:8',
        ]);

        // Handle file upload — takes priority over URL
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('efood', 'public');
            $data['image']     = asset('storage/' . $path);
            $data['thumbnail'] = $data['image'];
        }
        unset($data['image_file']);

        // Nullify empty time fields
        if (empty($data['available_from']))  $data['available_from']  = null;
        if (empty($data['available_until'])) $data['available_until'] = null;

        Product::create(array_merge($data, [
            'slug'       => Str::slug($data['name']) . '-' . Str::random(4),
            'is_active'  => $request->boolean('is_active', true),
            'is_featured'=> $request->boolean('is_featured', false),
        ]));

        return back()->with('success', 'Food item added!');
    }

    public function itemUpdate(Request $request, $id)
    {
        $this->ensureProductColumns();

        $product = Product::findOrFail($id);
        $data = $request->validate([
            'vendor_id'      => 'required|exists:vendors,id',
            'category_id'    => 'nullable|exists:categories,id',
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'price'          => 'required|numeric|min:0',
            'compare_price'  => 'nullable|numeric|min:0',
            'image'          => 'nullable|max:500',
            'image_file'     => 'nullable|image|max:5120',
            'is_featured'    => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
            'sort_order'     => 'nullable|integer',
            'available_from' => 'nullable|string|max:8',
            'available_until'=> 'nullable|string|max:8',
        ]);

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('efood', 'public');
            $data['image']     = asset('storage/' . $path);
            $data['thumbnail'] = $data['image'];
        }
        unset($data['image_file']);

        if (empty($data['available_from']))  $data['available_from']  = null;
        if (empty($data['available_until'])) $data['available_until'] = null;

        $product->update(array_merge($data, [
            'is_active'  => $request->boolean('is_active'),
            'is_featured'=> $request->boolean('is_featured'),
        ]));
        return back()->with('success', 'Food item updated!');
    }

    public function itemDestroy($id)
    {
        Product::findOrFail($id)->delete();
        return back()->with('success', 'Food item deleted.');
    }

    // ════════════════════════════════════════════════════════════════
    // ADDONS
    // ════════════════════════════════════════════════════════════════

    public function addonStore(Request $request)
    {
        $data = $request->validate([
            'vendor_id'  => 'required|exists:vendors,id',
            'name'       => 'required|string|max:100',
            'price'      => 'required|numeric|min:0',
            'is_active'  => 'nullable|boolean',
        ]);
        Addon::create(array_merge($data, [
            'is_active' => $request->boolean('is_active', true),
        ]));
        return back()->with('success', 'Addon added!');
    }

    public function addonUpdate(Request $request, $id)
    {
        $addon = Addon::findOrFail($id);
        $data = $request->validate([
            'vendor_id'  => 'required|exists:vendors,id',
            'name'       => 'required|string|max:100',
            'price'      => 'required|numeric|min:0',
            'is_active'  => 'nullable|boolean',
        ]);
        $addon->update(array_merge($data, [
            'is_active' => $request->boolean('is_active'),
        ]));
        return back()->with('success', 'Addon updated!');
    }

    public function addonDestroy($id)
    {
        Addon::findOrFail($id)->delete();
        return back()->with('success', 'Addon deleted.');
    }

    // ════════════════════════════════════════════════════════════════
    // BANNERS
    // ════════════════════════════════════════════════════════════════

    public function bannerStore(Request $request)
    {
        $data = $request->validate([
            'title'      => 'required|string|max:200',
            'subtitle'   => 'nullable|string|max:300',
            'image'      => 'nullable|max:500',
            'image_file' => 'nullable|image|max:5120',
            'action_url' => 'nullable|max:500',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date|after_or_equal:starts_at',
        ]);
        if ($request->hasFile('image_file')) $data['image'] = $this->storeUpload($request->file('image_file'));
        unset($data['image_file']);

        $module = $this->efoodModule();

        DB::table('banners')->insert(array_merge($data, [
            'module_id'   => $module?->id,
            'module_slug' => 'efood',
            'sort_order'  => $data['sort_order'] ?? 0,
            'is_active'   => $request->boolean('is_active', true),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]));

        return back()->with('success', 'Banner added!');
    }

    public function bannerUpdate(Request $request, $id)
    {
        $existing = DB::table('banners')->where('id', $id)->first();
        $data = $request->validate([
            'title'      => 'required|string|max:200',
            'subtitle'   => 'nullable|string|max:300',
            'image'      => 'nullable|max:500',
            'image_file' => 'nullable|image|max:5120',
            'action_url' => 'nullable|max:500',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);
        if ($request->hasFile('image_file')) $data['image'] = $this->storeUpload($request->file('image_file'));
        elseif (empty($data['image'])) $data['image'] = $existing->image ?? null;
        unset($data['image_file']);
        DB::table('banners')->where('id', $id)->update(array_merge($data, [
            'is_active'  => $request->boolean('is_active'),
            'updated_at' => now(),
        ]));
        return back()->with('success', 'Banner updated!');
    }

    public function bannerDestroy($id)
    {
        DB::table('banners')->where('id', $id)->delete();
        return back()->with('success', 'Banner deleted.');
    }

    // ════════════════════════════════════════════════════════════════
    // COUPONS / OFFERS
    // ════════════════════════════════════════════════════════════════

    public function couponStore(Request $request)
    {
        $data = $request->validate([
            'code'             => 'required|string|max:50|unique:coupons,code',
            'title'            => 'required|string|max:200',
            'description'      => 'nullable|string',
            'type'             => 'required|in:percentage,fixed',
            'value'            => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount'     => 'nullable|numeric|min:0',
            'usage_limit'      => 'nullable|integer|min:1',
            'vendor_id'        => 'nullable|exists:vendors,id',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date',
            'is_active'        => 'nullable|boolean',
        ]);

        $module = $this->efoodModule();

        Coupon::create(array_merge($data, [
            'module_id' => $module?->id,
            'is_active' => $request->boolean('is_active', true),
            'code'      => strtoupper($data['code']),
        ]));

        return back()->with('success', 'Coupon created!');
    }

    public function couponUpdate(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);
        $data = $request->validate([
            'title'            => 'required|string|max:200',
            'type'             => 'required|in:percentage,fixed',
            'value'            => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount'     => 'nullable|numeric|min:0',
            'usage_limit'      => 'nullable|integer|min:1',
            'vendor_id'        => 'nullable|exists:vendors,id',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date',
            'is_active'        => 'nullable|boolean',
        ]);
        $coupon->update(array_merge($data, [
            'is_active' => $request->boolean('is_active'),
        ]));
        return back()->with('success', 'Coupon updated!');
    }

    public function couponDestroy($id)
    {
        Coupon::findOrFail($id)->delete();
        return back()->with('success', 'Coupon deleted.');
    }

    // ════════════════════════════════════════════════════════════════
    // DISCOUNT CAMPAIGNS
    // ════════════════════════════════════════════════════════════════

    /** Merge date + time fields into a single datetime string */
    private function mergeDatetime(Request $request, string $prefix): string
    {
        $date = $request->input("{$prefix}_date", '');
        $time = $request->input("{$prefix}_time", '00:00');
        return trim($date . ' ' . $time . ':00');
    }

    public function campaignStore(Request $request)
    {
        // Merge date+time before validation
        $request->merge([
            'starts_at' => $this->mergeDatetime($request, 'starts_at'),
            'ends_at'   => $this->mergeDatetime($request, 'ends_at'),
        ]);

        $data = $request->validate([
            'vendor_id'      => 'required|exists:vendors,id',
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after:starts_at',
            'badge_text'     => 'nullable|string|max:100',
            'badge_color'    => 'nullable|string|max:30',
            'apply_to_all'   => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
            'internal_notes' => 'nullable|string',
        ]);

        DiscountCampaign::create(array_merge($data, [
            'apply_to_all'  => $request->boolean('apply_to_all', true),
            'is_active'     => $request->boolean('is_active', true),
            'badge_text'    => $data['badge_text'] ?? 'Special Offer',
            'badge_color'   => $data['badge_color'] ?? 'orange',
            'created_by'    => auth()->id(),
        ]));

        return back()->with('success', 'Campaign created!');
    }

    public function campaignUpdate(Request $request, $id)
    {
        $campaign = DiscountCampaign::findOrFail($id);

        $request->merge([
            'starts_at' => $this->mergeDatetime($request, 'starts_at'),
            'ends_at'   => $this->mergeDatetime($request, 'ends_at'),
        ]);

        $data = $request->validate([
            'vendor_id'      => 'required|exists:vendors,id',
            'name'           => 'required|string|max:200',
            'description'    => 'nullable|string',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date|after:starts_at',
            'badge_text'     => 'nullable|string|max:100',
            'badge_color'    => 'nullable|string|max:30',
            'apply_to_all'   => 'nullable|boolean',
            'is_active'      => 'nullable|boolean',
            'internal_notes' => 'nullable|string',
        ]);

        $campaign->update(array_merge($data, [
            'apply_to_all' => $request->boolean('apply_to_all'),
            'is_active'    => $request->boolean('is_active'),
        ]));

        return back()->with('success', 'Campaign updated!');
    }

    public function campaignDestroy($id)
    {
        DiscountCampaign::findOrFail($id)->delete();
        return back()->with('success', 'Campaign deleted.');
    }

    // ════════════════════════════════════════════════════════════════
    // ORDERS
    // ════════════════════════════════════════════════════════════════

    public function orderUpdateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $request->validate(['status' => 'required|in:pending,confirmed,preparing,on_the_way,delivered,cancelled']);
        $order->update(['status' => $request->status]);

        try {
            DB::table('order_status_history')->insert([
                'order_id'   => $order->id,
                'status'     => $request->status,
                'note'       => 'Status updated by admin',
                'changed_by' => auth()->id(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) { /* non-critical */ }

        try{$order->load('user');if($order->user?->fcm_token)FcmService::sendOrderUpdate($order->user->fcm_token,$order->order_number??'#'.$order->id,$request->status,$order->id,$order->module_slug);}catch(\Throwable $er){}
        return back()->with('success', 'Order status updated!');
    }
}
