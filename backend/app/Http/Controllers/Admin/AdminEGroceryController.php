<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EGrocery\{
    EGroceryCategory, EGroceryBrand, EGroceryUnit, EGroceryProduct,
    EGroceryProductVariant, EGroceryStockMovement, EGroceryBanner,
    EGrocerySection, EGroceryFlashDeal, EGroceryOrder, EGroceryOrderItem,
    EGroceryDeliveryZone, EGroceryDeliverySlot, EGroceryReview
};
use App\Services\EGrocery\{CatalogService, StockService};
use App\Services\FcmService;
use App\Models\{District, User, Coupon, CouponUsage};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Cache};
use Illuminate\Support\Str;

class AdminEGroceryController extends Controller
{
    // ═══════════════════════════════════════════════════════════════
    // 1. DASHBOARD
    // ═══════════════════════════════════════════════════════════════

    public function index()
    {
        $today = today();

        $stats = [
            'today_orders'  => EGroceryOrder::whereDate('created_at', $today)->count(),
            'today_revenue' => (float) EGroceryOrder::whereDate('created_at', $today)->where('payment_status', 'paid')->sum('total'),
            'pending'       => EGroceryOrder::where('status', 'pending')->count(),
            'out_delivery'  => EGroceryOrder::where('status', 'out_for_delivery')->count(),
            'low_stock'     => EGroceryProductVariant::whereColumn('stock_qty', '<=', 'low_stock_threshold')->where('stock_qty', '>', 0)->where('is_active', true)->count(),
            'flash_deals'   => EGroceryFlashDeal::whereHas('section', fn($q) => $q->active())->where(fn($q) => $q->whereNull('qty_limit')->orWhereColumn('qty_sold', '<', 'qty_limit'))->count(),
        ];

        // 14-day chart
        $chart14 = DB::table('egrocery_orders')
            ->selectRaw('DATE(created_at) as day, COUNT(*) as cnt, SUM(CASE WHEN payment_status="paid" THEN total ELSE 0 END) as revenue')
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('day')->orderBy('day')->get()->keyBy('day');

        $chartDays = collect();
        for ($i = 13; $i >= 0; $i--) {
            $d = today()->subDays($i)->toDateString();
            $chartDays->push(['day' => $d, 'cnt' => $chart14->get($d)?->cnt ?? 0, 'revenue' => $chart14->get($d)?->revenue ?? 0]);
        }

        // Top 10 products 30d — items → variants → products
        $topProducts = DB::table('egrocery_order_items as oi')
            ->join('egrocery_orders as o', 'o.id', '=', 'oi.order_id')
            ->join('egrocery_product_variants as v', 'v.id', '=', 'oi.variant_id')
            ->join('egrocery_products as p', 'p.id', '=', 'v.product_id')
            ->where('o.created_at', '>=', now()->subDays(30))
            ->groupBy('p.id', 'p.name')
            ->orderByRaw('SUM(oi.line_total) DESC')
            ->limit(10)
            ->selectRaw('p.name, SUM(oi.qty) as total_qty, SUM(oi.line_total) as total_rev')
            ->get();

        // Category revenue 30d
        $catRevenue = DB::table('egrocery_order_items as oi')
            ->join('egrocery_orders as o', 'o.id', '=', 'oi.order_id')
            ->join('egrocery_product_variants as v', 'v.id', '=', 'oi.variant_id')
            ->join('egrocery_products as p', 'p.id', '=', 'v.product_id')
            ->join('egrocery_categories as c', 'c.id', '=', 'p.category_id')
            ->where('o.created_at', '>=', now()->subDays(30))
            ->where('o.payment_status', 'paid')
            ->groupBy('c.id', 'c.name', 'c.icon')
            ->selectRaw('c.name, c.icon, SUM(oi.line_total) as revenue')
            ->orderByRaw('SUM(oi.line_total) DESC')->limit(6)->get();

        // Needs attention
        $lowStockItems = EGroceryProductVariant::with('product:id,name,slug')
            ->whereColumn('stock_qty', '<=', 'low_stock_threshold')
            ->where('stock_qty', '>', 0)->where('is_active', true)
            ->orderBy('stock_qty')->limit(5)->get();

        $stalePending = EGroceryOrder::where('status', 'pending')
            ->where('created_at', '<=', now()->subMinutes(30))
            ->with('user:id,name,phone')->limit(5)->get();

        $flashEndingToday = EGrocerySection::whereDate('ends_at', today())->where('is_active', true)->get();

        return view('admin.egrocery.dashboard', compact('stats', 'chartDays', 'topProducts', 'catRevenue', 'lowStockItems', 'stalePending', 'flashEndingToday'));
    }

    // ═══════════════════════════════════════════════════════════════
    // 2. CATEGORIES
    // ═══════════════════════════════════════════════════════════════

    public function categories()
    {
        $parents = EGroceryCategory::with(['children' => fn($q) => $q->withCount('products')->orderBy('sort_order')])
            ->whereNull('parent_id')->withCount('products')->orderBy('sort_order')->get();
        return view('admin.egrocery.categories', compact('parents'));
    }

    public function categoryStore(Request $r)
    {
        $d = $r->validate([
            'parent_id'  => 'nullable|exists:egrocery_categories,id',
            'name'       => 'required|string|max:100',
            'name_so'    => 'nullable|string|max:100',
            'icon'       => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
            'image'      => 'nullable|image|max:2048',
        ]);
        EGroceryCategory::create([
            'parent_id'  => $d['parent_id'] ?? null,
            'name'       => $d['name'],
            'name_so'    => $d['name_so'] ?? null,
            'slug'       => Str::slug($d['name']) . '-' . Str::random(4),
            'icon'       => $d['icon'] ?? null,
            'sort_order' => $d['sort_order'] ?? 0,
            'is_active'  => $r->boolean('is_active', true),
            'image'      => $r->hasFile('image') ? $r->file('image')->store('egrocery/cats', 'public') : null,
        ]);
        (new CatalogService)->invalidateCategoryCache();
        return back()->with('success', 'Category created.');
    }

    public function categoryUpdate(Request $r, $id)
    {
        $cat = EGroceryCategory::findOrFail($id);
        $d = $r->validate([
            'name'       => 'required|string|max:100',
            'name_so'    => 'nullable|string|max:100',
            'icon'       => 'nullable|string|max:10',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
            'image'      => 'nullable|image|max:2048',
        ]);
        $cat->update([
            'name'       => $d['name'],
            'name_so'    => $d['name_so'] ?? $cat->name_so,
            'icon'       => $d['icon'] ?? $cat->icon,
            'sort_order' => $d['sort_order'] ?? $cat->sort_order,
            'is_active'  => $r->boolean('is_active'),
            'image'      => $r->hasFile('image') ? $r->file('image')->store('egrocery/cats', 'public') : $cat->image,
        ]);
        (new CatalogService)->invalidateCategoryCache();
        return back()->with('success', 'Category updated.');
    }

    public function categoryToggle($id)
    {
        $cat = EGroceryCategory::findOrFail($id);
        $cat->update(['is_active' => !$cat->is_active]);
        (new CatalogService)->invalidateCategoryCache();
        return response()->json(['active' => $cat->is_active]);
    }

    public function categorySortUpdate(Request $r)
    {
        foreach ($r->input('order', []) as $item) {
            EGroceryCategory::where('id', $item['id'])->update(['sort_order' => $item['order']]);
        }
        (new CatalogService)->invalidateCategoryCache();
        return response()->json(['ok' => true]);
    }

    public function categoryDestroy($id)
    {
        EGroceryCategory::findOrFail($id)->delete();
        (new CatalogService)->invalidateCategoryCache();
        return back()->with('success', 'Category deleted.');
    }

    // ═══════════════════════════════════════════════════════════════
    // 3. PRODUCTS
    // ═══════════════════════════════════════════════════════════════

    public function products(Request $r)
    {
        $q = EGroceryProduct::with(['category:id,name', 'brand:id,name', 'defaultVariant'])
            ->withCount('activeVariants as variant_count')
            ->withSum('activeVariants as total_stock', 'stock_qty');

        if ($r->filled('category')) $q->where('category_id', $r->category);
        if ($r->filled('brand'))    $q->where('brand_id', $r->brand);
        if ($r->status === 'active')    $q->where('is_active', true);
        if ($r->status === 'inactive')  $q->where('is_active', false);
        if ($r->status === 'featured')  $q->where('is_featured', true);
        if ($r->stock === 'out') $q->whereDoesntHave('activeVariants', fn($sq) => $sq->where('stock_qty', '>', 0));
        if ($r->stock === 'low') $q->whereHas('activeVariants', fn($sq) => $sq->whereColumn('stock_qty', '<=', 'low_stock_threshold')->where('stock_qty', '>', 0));
        if ($r->filled('search')) $q->where(fn($sq) => $sq->where('name', 'like', '%' . $r->search . '%')->orWhere('name_so', 'like', '%' . $r->search . '%'));

        $products   = $q->orderByDesc('created_at')->paginate(25)->withQueryString();
        $categories = EGroceryCategory::orderBy('name')->get(['id', 'name']);
        $brands     = EGroceryBrand::orderBy('name')->get(['id', 'name']);

        return view('admin.egrocery.products', compact('products', 'categories', 'brands'));
    }

    public function productCreate()
    {
        $categories = EGroceryCategory::orderBy('name')->get(['id', 'name', 'parent_id']);
        $brands     = EGroceryBrand::orderBy('name')->get(['id', 'name']);
        $units      = EGroceryUnit::orderBy('name')->get(['id', 'name', 'symbol']);
        return view('admin.egrocery.product_form', compact('categories', 'brands', 'units'));
    }

    public function productEdit($id)
    {
        $product    = EGroceryProduct::with('activeVariants.unit')->findOrFail($id);
        $categories = EGroceryCategory::orderBy('name')->get(['id', 'name', 'parent_id']);
        $brands     = EGroceryBrand::orderBy('name')->get(['id', 'name']);
        $units      = EGroceryUnit::orderBy('name')->get(['id', 'name', 'symbol']);
        return view('admin.egrocery.product_form', compact('product', 'categories', 'brands', 'units'));
    }

    public function productStore(Request $r)
    {
        $d = $r->validate([
            'category_id'     => 'required|exists:egrocery_categories,id',
            'brand_id'        => 'nullable|exists:egrocery_brands,id',
            'name'            => 'required|string|max:200',
            'name_so'         => 'nullable|string|max:200',
            'description'     => 'nullable|string',
            'base_unit_id'    => 'required|exists:egrocery_units,id',
            'is_weight_based' => 'nullable|boolean',
            'is_featured'     => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
            'images.*'        => 'nullable|image|max:3072',
            'variants'        => 'required|array|min:1',
            'variants.*.label'               => 'required|string|max:100',
            'variants.*.unit_id'             => 'required|exists:egrocery_units,id',
            'variants.*.unit_qty'            => 'required|numeric|min:0',
            'variants.*.price'               => 'required|numeric|min:0',
            'variants.*.compare_price'       => 'nullable|numeric|min:0',
            'variants.*.sku'                 => 'nullable|string|max:100',
            'variants.*.stock_qty'           => 'required|integer|min:0',
            'variants.*.low_stock_threshold' => 'nullable|integer|min:0',
        ]);

        $tags = array_values(array_filter(array_map('trim', explode(',', $r->input('tags', '')))));

        $product = EGroceryProduct::create([
            'category_id'    => $d['category_id'],
            'brand_id'       => $d['brand_id'] ?? null,
            'name'           => $d['name'],
            'name_so'        => $d['name_so'] ?? null,
            'slug'           => Str::slug($d['name']) . '-' . Str::random(5),
            'description'    => $d['description'] ?? null,
            'base_unit_id'   => $d['base_unit_id'],
            'is_weight_based'=> $r->boolean('is_weight_based'),
            'tags'           => $tags,
            'images'         => [],
            'is_featured'    => $r->boolean('is_featured'),
            'is_active'      => $r->boolean('is_active', true),
        ]);

        if ($r->hasFile('images')) {
            $imgs = [];
            foreach ($r->file('images') as $img) $imgs[] = $img->store('egrocery/products', 'public');
            $product->update(['images' => $imgs]);
        }

        $defaultSet = false;
        foreach ($d['variants'] as $i => $v) {
            $isDefault = !$defaultSet && ($r->input("variants.$i.is_default") == '1' || $i === 0);
            if ($isDefault) $defaultSet = true;
            EGroceryProductVariant::create([
                'product_id'          => $product->id,
                'label'               => $v['label'],
                'unit_id'             => $v['unit_id'],
                'unit_qty'            => $v['unit_qty'],
                'price'               => $v['price'],
                'compare_price'       => isset($v['compare_price']) && $v['compare_price'] !== '' ? $v['compare_price'] : null,
                'sku'                 => $v['sku'] ?? null,
                'stock_qty'           => $v['stock_qty'],
                'low_stock_threshold' => $v['low_stock_threshold'] ?? 10,
                'sort_order'          => $i,
                'is_default'          => $isDefault,
                'is_active'           => true,
            ]);
        }

        return redirect()->route('admin.module-data.egrocery.products')->with('success', 'Product created.');
    }

    public function productUpdate(Request $r, $id)
    {
        $product = EGroceryProduct::findOrFail($id);
        $d = $r->validate([
            'category_id'     => 'required|exists:egrocery_categories,id',
            'brand_id'        => 'nullable|exists:egrocery_brands,id',
            'name'            => 'required|string|max:200',
            'name_so'         => 'nullable|string|max:200',
            'description'     => 'nullable|string',
            'base_unit_id'    => 'required|exists:egrocery_units,id',
            'is_weight_based' => 'nullable|boolean',
            'is_featured'     => 'nullable|boolean',
            'is_active'       => 'nullable|boolean',
            'images.*'        => 'nullable|image|max:3072',
        ]);

        $tags = array_values(array_filter(array_map('trim', explode(',', $r->input('tags', '')))));
        $product->update([
            'category_id'    => $d['category_id'],
            'brand_id'       => $d['brand_id'] ?? null,
            'name'           => $d['name'],
            'name_so'        => $d['name_so'] ?? null,
            'description'    => $d['description'] ?? null,
            'base_unit_id'   => $d['base_unit_id'],
            'is_weight_based'=> $r->boolean('is_weight_based'),
            'tags'           => $tags,
            'is_featured'    => $r->boolean('is_featured'),
            'is_active'      => $r->boolean('is_active', true),
        ]);

        if ($r->hasFile('images')) {
            $imgs = $product->images ?? [];
            foreach ($r->file('images') as $img) $imgs[] = $img->store('egrocery/products', 'public');
            $product->update(['images' => $imgs]);
        }

        if ($r->has('variants')) {
            $existing = $product->activeVariants->keyBy('id');
            $defaultSet = false;
            foreach ($r->input('variants') as $i => $v) {
                $isDefault = !$defaultSet && ($v['is_default'] ?? '0') === '1';
                if ($isDefault) $defaultSet = true;
                $varData = [
                    'product_id'          => $product->id,
                    'label'               => $v['label'],
                    'unit_id'             => $v['unit_id'],
                    'unit_qty'            => $v['unit_qty'],
                    'price'               => $v['price'],
                    'compare_price'       => ($v['compare_price'] ?? '') !== '' ? $v['compare_price'] : null,
                    'sku'                 => $v['sku'] ?? null,
                    'stock_qty'           => $v['stock_qty'],
                    'low_stock_threshold' => $v['low_stock_threshold'] ?? 10,
                    'sort_order'          => $i,
                    'is_default'          => $isDefault,
                    'is_active'           => true,
                ];
                if (!empty($v['id']) && $existing->has($v['id'])) {
                    $existing[$v['id']]->update($varData);
                } else {
                    EGroceryProductVariant::create($varData);
                }
            }
            if (!$defaultSet) $product->activeVariants()->first()?->update(['is_default' => true]);
        }

        return redirect()->route('admin.module-data.egrocery.products')->with('success', 'Product updated.');
    }

    public function productToggle($id)
    {
        $p = EGroceryProduct::findOrFail($id);
        $p->update(['is_active' => !$p->is_active]);
        return back()->with('success', $p->is_active ? 'Product activated.' : 'Product deactivated.');
    }

    public function productBulkAction(Request $r)
    {
        $r->validate(['ids' => 'required|array', 'action' => 'required|in:activate,deactivate,delete']);
        $q = EGroceryProduct::whereIn('id', $r->ids);
        match ($r->action) {
            'activate'   => $q->update(['is_active' => true]),
            'deactivate' => $q->update(['is_active' => false]),
            'delete'     => $q->delete(),
        };
        return back()->with('success', count($r->ids) . ' products updated.');
    }

    public function productQuickEdit(Request $r, $id)
    {
        $r->validate([
            'variant_id' => 'required|exists:egrocery_product_variants,id',
            'price'      => 'required|numeric|min:0',
            'stock_qty'  => 'required|integer|min:0',
        ]);
        EGroceryProductVariant::where('id', $r->variant_id)->where('product_id', $id)
            ->update(['price' => $r->price, 'stock_qty' => $r->stock_qty]);
        return response()->json(['ok' => true]);
    }

    public function productDestroy($id)
    {
        EGroceryProduct::findOrFail($id)->delete();
        return back()->with('success', 'Product deleted.');
    }

    public function productCsvExport()
    {
        $rows = EGroceryProduct::with(['category', 'brand', 'activeVariants.unit'])->where('is_active', true)->get();
        $csv  = "name,name_so,category,brand,variant_label,price,compare_price,sku,stock_qty\n";
        foreach ($rows as $p) {
            foreach ($p->activeVariants as $v) {
                $csv .= implode(',', [
                    '"' . addslashes($p->name) . '"',
                    '"' . addslashes($p->name_so ?? '') . '"',
                    '"' . addslashes($p->category?->name ?? '') . '"',
                    '"' . addslashes($p->brand?->name ?? '') . '"',
                    '"' . addslashes($v->label) . '"',
                    $v->price, $v->compare_price ?? '', $v->sku ?? '', $v->stock_qty,
                ]) . "\n";
            }
        }
        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="egrocery_products_' . date('Y-m-d') . '.csv"',
        ]);
    }

    public function productCsvImport(Request $r)
    {
        $r->validate(['csv_file' => 'required|file|mimes:csv,txt|max:5120']);
        $handle = fopen($r->file('csv_file')->getRealPath(), 'r');
        fgetcsv($handle); // header
        $errors = [];
        $count  = 0;
        $row    = 2;
        while (($cols = fgetcsv($handle)) !== false) {
            if (count($cols) < 5) { $errors[] = "Row {$row}: not enough columns"; $row++; continue; }
            [$name, $nameSo, $catName, $brandName, $varLabel, $price, $compare, $sku, $stock] = array_pad($cols, 9, null);
            $cat  = EGroceryCategory::where('name', trim((string)$catName))->first();
            if (!$cat) { $errors[] = "Row {$row}: category '{$catName}' not found"; $row++; continue; }
            $unit = EGroceryUnit::where('name', 'kg')->first() ?? EGroceryUnit::first();
            if (!$unit) { $errors[] = "Row {$row}: no units defined"; $row++; continue; }
            $prod = EGroceryProduct::create([
                'category_id'  => $cat->id,
                'name'         => trim($name),
                'name_so'      => trim((string)$nameSo),
                'slug'         => Str::slug(trim($name)) . '-' . Str::random(4),
                'base_unit_id' => $unit->id,
                'images'       => [],
                'tags'         => [],
                'is_active'    => true,
            ]);
            EGroceryProductVariant::create([
                'product_id'          => $prod->id,
                'label'               => trim((string)$varLabel ?: '1 unit'),
                'unit_id'             => $unit->id,
                'unit_qty'            => 1,
                'price'               => (float)$price,
                'compare_price'       => $compare ? (float)$compare : null,
                'sku'                 => $sku ?: null,
                'stock_qty'           => (int)$stock,
                'low_stock_threshold' => 10,
                'is_default'          => true,
                'sort_order'          => 0,
                'is_active'           => true,
            ]);
            $count++;
            $row++;
        }
        fclose($handle);
        $msg = "Imported {$count} rows.";
        if ($errors) $msg .= ' Issues: ' . implode('; ', array_slice($errors, 0, 5));
        return back()->with('success', $msg);
    }

    public function productCsvTemplate()
    {
        $csv = "name,name_so,category,brand,variant_label,price,compare_price,sku,stock_qty\n";
        $csv .= "\"Basmati Rice\",\"Bariis Basmati\",\"Grains, Rice & Pasta\",\"Barwaaqo\",\"5 kg\",8.50,9.50,BSMTI-5KG,100\n";
        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="egrocery_import_template.csv"',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 4. INVENTORY
    // ═══════════════════════════════════════════════════════════════

    public function inventory(Request $r)
    {
        $q = DB::table('egrocery_stock_movements as sm')
            ->join('egrocery_product_variants as v', 'v.id', '=', 'sm.variant_id')
            ->join('egrocery_products as p', 'p.id', '=', 'v.product_id')
            ->leftJoin('users as u', 'u.id', '=', 'sm.actor_id')
            ->select('sm.*', 'p.name as product_name', 'v.label as variant_label', 'u.name as actor_name');

        if ($r->filled('type'))   $q->where('sm.type', $r->type);
        if ($r->filled('search')) $q->where('p.name', 'like', '%' . $r->search . '%');

        $movements  = $q->orderByDesc('sm.created_at')->paginate(30)->withQueryString();
        $lowStock   = EGroceryProductVariant::with('product:id,name')->whereColumn('stock_qty', '<=', 'low_stock_threshold')->where('stock_qty', '>', 0)->where('is_active', true)->orderBy('stock_qty')->get();
        $outOfStock = EGroceryProductVariant::with('product:id,name')->where('stock_qty', '<=', 0)->where('is_active', true)->limit(30)->get();

        return view('admin.egrocery.inventory', compact('movements', 'lowStock', 'outOfStock'));
    }

    public function inventoryAdjust(Request $r)
    {
        $r->validate([
            'variant_id' => 'required|exists:egrocery_product_variants,id',
            'qty'        => 'required|numeric|not_in:0',
            'type'       => 'required|in:purchase,adjustment,waste,return',
            'note'       => 'nullable|string|max:255',
        ]);
        $variant = EGroceryProductVariant::findOrFail($r->variant_id);
        (new StockService)->adjust($variant, (float)$r->qty, $r->type, null, $r->note, auth()->id(), strict: false);
        return back()->with('success', 'Stock adjusted for ' . $variant->label . '.');
    }

    public function inventoryVariantSearch(Request $r)
    {
        $variants = EGroceryProductVariant::with('product:id,name')
            ->join('egrocery_products as p', 'p.id', '=', 'egrocery_product_variants.product_id')
            ->where(fn($q) => $q->where('p.name', 'like', '%' . $r->q . '%')->orWhere('egrocery_product_variants.sku', 'like', '%' . $r->q . '%'))
            ->where('egrocery_product_variants.is_active', true)
            ->limit(10)->select('egrocery_product_variants.*')->get()
            ->map(fn($v) => ['id' => $v->id, 'label' => $v->product->name . ' — ' . $v->label, 'stock' => $v->stock_qty]);
        return response()->json($variants);
    }

    // ═══════════════════════════════════════════════════════════════
    // 5. MARKETING
    // ═══════════════════════════════════════════════════════════════

    public function marketing()
    {
        $banners  = EGroceryBanner::orderBy('placement')->orderBy('sort_order')->get();
        $sections = EGrocerySection::with(['products:id,name', 'flashDeals.variant.product:id,name'])->orderBy('sort_order')->get();
        return view('admin.egrocery.marketing', compact('banners', 'sections'));
    }

    public function bannerStore(Request $r)
    {
        $d = $r->validate([
            'title'      => 'nullable|string|max:100',
            'image'      => 'required|image|max:3072',
            'placement'  => 'required|in:home_top,home_mid,category',
            'link_type'  => 'nullable|in:product,category,section,url',
            'link_value' => 'nullable|string|max:200',
            'sort_order' => 'nullable|integer',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date',
            'is_active'  => 'nullable|boolean',
        ]);
        EGroceryBanner::create(array_merge($d, [
            'image'     => $r->file('image')->store('egrocery/banners', 'public'),
            'is_active' => $r->boolean('is_active', true),
        ]));
        return back()->with('success', 'Banner added.');
    }

    public function bannerUpdate(Request $r, $id)
    {
        $banner = EGroceryBanner::findOrFail($id);
        $d = $r->validate([
            'title'      => 'nullable|string|max:100',
            'image'      => 'nullable|image|max:3072',
            'placement'  => 'required|in:home_top,home_mid,category',
            'link_type'  => 'nullable|in:product,category,section,url',
            'link_value' => 'nullable|string|max:200',
            'sort_order' => 'nullable|integer',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date',
            'is_active'  => 'nullable|boolean',
        ]);
        $banner->update(array_merge($d, [
            'image'     => $r->hasFile('image') ? $r->file('image')->store('egrocery/banners', 'public') : $banner->image,
            'is_active' => $r->boolean('is_active'),
        ]));
        return back()->with('success', 'Banner updated.');
    }

    public function bannerDestroy($id)
    {
        EGroceryBanner::findOrFail($id)->delete();
        return back()->with('success', 'Banner deleted.');
    }

    public function bannerToggle($id)
    {
        $b = EGroceryBanner::findOrFail($id);
        $b->update(['is_active' => !$b->is_active]);
        return back()->with('success', 'Banner ' . ($b->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function sectionStore(Request $r)
    {
        $d = $r->validate([
            'title'      => 'required|string|max:100',
            'title_so'   => 'nullable|string|max:100',
            'type'       => 'required|in:featured,best_sellers,new_arrivals,flash_deal,category_promo,custom,buy_again',
            'layout'     => 'nullable|in:grid,horizontal_scroll,hero',
            'sort_order' => 'nullable|integer',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date',
            'is_active'  => 'nullable|boolean',
        ]);
        EGrocerySection::create(array_merge($d, ['is_active' => $r->boolean('is_active', true), 'layout' => $d['layout'] ?? 'grid']));
        return back()->with('success', 'Section created.');
    }

    public function sectionUpdate(Request $r, $id)
    {
        $section = EGrocerySection::findOrFail($id);
        $d = $r->validate([
            'title'      => 'required|string|max:100',
            'title_so'   => 'nullable|string|max:100',
            'layout'     => 'nullable|in:grid,horizontal_scroll,hero',
            'sort_order' => 'nullable|integer',
            'starts_at'  => 'nullable|date',
            'ends_at'    => 'nullable|date',
            'is_active'  => 'nullable|boolean',
        ]);
        $section->update(array_merge($d, ['is_active' => $r->boolean('is_active')]));
        return back()->with('success', 'Section updated.');
    }

    public function sectionProductsUpdate(Request $r, $id)
    {
        $r->validate(['product_ids' => 'nullable|array', 'product_ids.*' => 'exists:egrocery_products,id']);
        $section = EGrocerySection::findOrFail($id);
        $sync = [];
        foreach ($r->input('product_ids', []) as $i => $pid) $sync[$pid] = ['sort_order' => $i];
        $section->products()->sync($sync);
        return back()->with('success', 'Section products updated.');
    }

    public function sectionToggle($id)
    {
        $s = EGrocerySection::findOrFail($id);
        $s->update(['is_active' => !$s->is_active]);
        return back()->with('success', 'Section ' . ($s->is_active ? 'enabled' : 'disabled') . '.');
    }

    public function sectionDestroy($id)
    {
        EGrocerySection::findOrFail($id)->delete();
        return back()->with('success', 'Section deleted.');
    }

    public function flashDealStore(Request $r)
    {
        $r->validate([
            'section_id' => 'required|exists:egrocery_sections,id',
            'variant_id' => 'required|exists:egrocery_product_variants,id',
            'deal_price' => 'required|numeric|min:0',
            'qty_limit'  => 'nullable|integer|min:1',
        ]);
        EGroceryFlashDeal::updateOrCreate(
            ['section_id' => $r->section_id, 'variant_id' => $r->variant_id],
            ['deal_price' => $r->deal_price, 'qty_limit' => $r->qty_limit ?? null, 'qty_sold' => 0]
        );
        return back()->with('success', 'Flash deal saved.');
    }

    public function flashDealDestroy($id)
    {
        EGroceryFlashDeal::findOrFail($id)->delete();
        return back()->with('success', 'Flash deal removed.');
    }

    public function marketingProductSearch(Request $r)
    {
        $prods = EGroceryProduct::where('name', 'like', '%' . $r->q . '%')
            ->orWhere('name_so', 'like', '%' . $r->q . '%')
            ->limit(10)->get(['id', 'name', 'name_so']);
        return response()->json($prods);
    }

    public function marketingVariantSearch(Request $r)
    {
        $variants = EGroceryProductVariant::with('product:id,name')
            ->join('egrocery_products as p', 'p.id', '=', 'egrocery_product_variants.product_id')
            ->where('p.name', 'like', '%' . $r->q . '%')
            ->where('egrocery_product_variants.is_active', true)
            ->limit(10)->select('egrocery_product_variants.*')->get()
            ->map(fn($v) => ['id' => $v->id, 'text' => $v->product->name . ' — ' . $v->label, 'price' => $v->price]);
        return response()->json($variants);
    }

    // ═══════════════════════════════════════════════════════════════
    // 6. ORDERS
    // ═══════════════════════════════════════════════════════════════

    public function orders(Request $r)
    {
        $q = EGroceryOrder::with('user:id,name,phone')->orderByDesc('created_at');

        if ($r->filled('status')) $q->where('status', $r->status);
        if ($r->filled('date'))   $q->whereDate('created_at', $r->date);
        if ($r->filled('search')) {
            $q->where(fn($sq) => $sq->where('order_no', 'like', '%' . $r->search . '%')
                ->orWhereHas('user', fn($uq) => $uq->where('name', 'like', '%' . $r->search . '%')->orWhere('phone', 'like', '%' . $r->search . '%')));
        }

        $orders = $q->paginate(20)->withQueryString();
        $counts = [
            'all'              => EGroceryOrder::count(),
            'pending'          => EGroceryOrder::where('status', 'pending')->count(),
            'confirmed'        => EGroceryOrder::where('status', 'confirmed')->count(),
            'picking'          => EGroceryOrder::where('status', 'picking')->count(),
            'ready'            => EGroceryOrder::where('status', 'ready')->count(),
            'out_for_delivery' => EGroceryOrder::where('status', 'out_for_delivery')->count(),
            'delivered'        => EGroceryOrder::where('status', 'delivered')->count(),
            'cancelled'        => EGroceryOrder::where('status', 'cancelled')->count(),
        ];

        return view('admin.egrocery.orders', compact('orders', 'counts'));
    }

    public function orderShow($id)
    {
        $order = EGroceryOrder::with([
            'user:id,name,phone,email,fcm_token',
            'items.variant.product:id,name,slug',
            'items.variant:id,label,product_id',
            'items.substitutionVariant:id,label',
            'deliverySlot',
        ])->findOrFail($id);

        $driverRoleId = DB::table('roles')->where('slug', 'delivery_man')->value('id');
        $drivers      = $driverRoleId
            ? User::where('role_id', $driverRoleId)->get(['id', 'name', 'phone'])
            : collect();

        $transitions = $this->allowedTransitions($order->status);

        return view('admin.egrocery.order_detail', compact('order', 'drivers', 'transitions'));
    }

    private function allowedTransitions(string $status): array
    {
        return match ($status) {
            'pending'          => ['confirmed', 'cancelled'],
            'confirmed'        => ['picking', 'cancelled'],
            'picking'          => ['ready', 'cancelled'],
            'ready'            => ['out_for_delivery'],
            'out_for_delivery' => ['delivered'],
            default            => [],
        };
    }

    public function orderUpdateStatus(Request $r, $id)
    {
        $order   = EGroceryOrder::findOrFail($id);
        $allowed = $this->allowedTransitions($order->status);
        if (!in_array($r->status, $allowed)) {
            return back()->with('error', 'Status transition not allowed.');
        }
        $r->validate(['status' => 'required|string', 'cancelled_reason' => 'nullable|string|max:255']);

        $order->update([
            'status'           => $r->status,
            'cancelled_reason' => $r->status === 'cancelled' ? $r->cancelled_reason : $order->cancelled_reason,
            'confirmed_at'     => $r->status === 'confirmed' ? now() : $order->confirmed_at,
            'delivered_at'     => $r->status === 'delivered' ? now() : $order->delivered_at,
        ]);

        // Reverb broadcast
        try {
            $payload = ['order_id' => $order->id, 'status' => $r->status, 'order_no' => $order->order_no];
            \App\Services\RealtimeService::toUser($order->user_id, 'egrocery.order.status', $payload);
        } catch (\Throwable) {}

        // FCM to customer
        try {
            $order->load('user');
            if ($fcm = $order->user?->fcm_token) {
                $labels = [
                    'confirmed'        => ['✅ Order Confirmed', 'Your grocery order ' . $order->order_no . ' has been confirmed.'],
                    'picking'          => ['🛒 Picking Your Items', 'We are picking your items for order ' . $order->order_no . '.'],
                    'ready'            => ['📦 Order Ready', 'Your order ' . $order->order_no . ' is packed and ready!'],
                    'out_for_delivery' => ['🚚 On the Way!', 'Your order ' . $order->order_no . ' is out for delivery.'],
                    'delivered'        => ['🎉 Delivered!', 'Your order ' . $order->order_no . ' has been delivered. Enjoy!'],
                    'cancelled'        => ['❌ Order Cancelled', 'Your order ' . $order->order_no . ' was cancelled.'],
                ];
                [$title, $body] = $labels[$r->status] ?? ['Order Update', 'Status: ' . $r->status];
                FcmService::sendToToken($fcm, $title, $body,
                    ['type' => 'egrocery_order', 'order_id' => (string)$order->id, 'status' => $r->status],
                    null, 'esahlan_high_v3', null, $order->user_id,
                );
            }
        } catch (\Throwable) {}

        if ($r->wantsJson()) return response()->json(['ok' => true, 'status' => $r->status]);
        return back()->with('success', 'Order status updated to ' . $r->status . '.');
    }

    public function orderAssignDriver(Request $r, $id)
    {
        $r->validate(['driver_id' => 'required|exists:users,id']);
        EGroceryOrder::findOrFail($id)->update(['driver_id' => $r->driver_id]);
        return back()->with('success', 'Driver assigned.');
    }

    public function orderPickedQty(Request $r, $id)
    {
        $r->validate(['item_id' => 'required', 'picked_qty' => 'required|numeric|min:0']);
        EGroceryOrderItem::where('id', $r->item_id)->where('order_id', $id)->update(['picked_qty' => $r->picked_qty]);
        return back()->with('success', 'Picked qty updated.');
    }

    public function orderSubstitute(Request $r, $id)
    {
        $r->validate(['item_id' => 'required', 'variant_id' => 'required|exists:egrocery_product_variants,id']);
        $item = EGroceryOrderItem::where('id', $r->item_id)->where('order_id', $id)->firstOrFail();
        $item->update(['substitution_variant_id' => $r->variant_id, 'sub_status' => 'proposed']);

        try {
            $order = EGroceryOrder::with('user')->find($id);
            if ($fcm = $order->user?->fcm_token) {
                FcmService::sendToToken($fcm, '🔄 Item Substitution',
                    'An item in order ' . $order->order_no . ' may be substituted. Please review.',
                    ['type' => 'egrocery_sub', 'order_id' => (string)$id, 'item_id' => (string)$item->id],
                    null, 'esahlan_high_v3', null, $order->user_id,
                );
            }
        } catch (\Throwable) {}

        return back()->with('success', 'Substitution proposed and customer notified.');
    }

    public function orderPrint($id)
    {
        $order = EGroceryOrder::with(['items.variant.product', 'items.variant', 'user'])->findOrFail($id);
        return view('admin.egrocery.order_print', compact('order'));
    }

    // ═══════════════════════════════════════════════════════════════
    // 7. SETTINGS
    // ═══════════════════════════════════════════════════════════════

    public function settings()
    {
        $zones     = EGroceryDeliveryZone::orderBy('name')->get();
        $slots     = EGroceryDeliverySlot::orderBy('day_offset')->orderBy('start_time')->get();
        $districts = District::orderBy('name')->get(['id', 'name']);
        return view('admin.egrocery.settings', compact('zones', 'slots', 'districts'));
    }

    public function zoneStore(Request $r)
    {
        $d = $r->validate([
            'name'           => 'required|string|max:100',
            'district_ids'   => 'required|array',
            'district_ids.*' => 'exists:districts,id',
            'delivery_fee'   => 'required|numeric|min:0',
            'min_order'      => 'required|numeric|min:0',
            'free_over'      => 'nullable|numeric|min:0',
            'is_active'      => 'nullable|boolean',
        ]);
        EGroceryDeliveryZone::create(array_merge($d, ['is_active' => $r->boolean('is_active', true)]));
        return back()->with('success', 'Zone added.');
    }

    public function zoneUpdate(Request $r, $id)
    {
        $zone = EGroceryDeliveryZone::findOrFail($id);
        $d = $r->validate([
            'name'         => 'required|string|max:100',
            'district_ids' => 'required|array',
            'delivery_fee' => 'required|numeric|min:0',
            'min_order'    => 'required|numeric|min:0',
            'free_over'    => 'nullable|numeric|min:0',
            'is_active'    => 'nullable|boolean',
        ]);
        $zone->update(array_merge($d, ['is_active' => $r->boolean('is_active')]));
        return back()->with('success', 'Zone updated.');
    }

    public function zoneDestroy($id)
    {
        EGroceryDeliveryZone::findOrFail($id)->delete();
        return back()->with('success', 'Zone deleted.');
    }

    public function slotStore(Request $r)
    {
        $d = $r->validate([
            'label'      => 'required|string|max:100',
            'day_offset' => 'required|integer|min:0|max:6',
            'start_time' => 'required|date_format:H:i',
            'end_time'   => 'required|date_format:H:i|after:start_time',
            'capacity'   => 'required|integer|min:1',
            'is_active'  => 'nullable|boolean',
        ]);
        EGroceryDeliverySlot::create(array_merge($d, ['is_active' => $r->boolean('is_active', true)]));
        return back()->with('success', 'Slot added.');
    }

    public function slotUpdate(Request $r, $id)
    {
        $slot = EGroceryDeliverySlot::findOrFail($id);
        $d = $r->validate([
            'capacity'  => 'required|integer|min:1',
            'label'     => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ]);
        $slot->update(array_merge($d, ['is_active' => $r->boolean('is_active')]));
        return back()->with('success', 'Slot updated.');
    }

    public function slotDestroy($id)
    {
        EGroceryDeliverySlot::findOrFail($id)->delete();
        return back()->with('success', 'Slot deleted.');
    }

    // ═══════════════════════════════════════════════════════════════
    // 9. COUPONS
    // ═══════════════════════════════════════════════════════════════

    private function egroceryModuleId(): int
    {
        return (int) DB::table('modules')->where('slug', 'egrocery')->value('id') ?: 10;
    }

    public function coupons()
    {
        $moduleId   = $this->egroceryModuleId();
        $coupons    = Coupon::where('module_id', $moduleId)->latest()->paginate(20);
        $categories = EGroceryCategory::where('is_active', true)->orderBy('name')->get();
        return view('admin.egrocery.coupons', compact('coupons', 'categories'));
    }

    public function couponStore(Request $r)
    {
        $data = $r->validate([
            'code'             => 'required|string|max:50|unique:coupons,code',
            'title'            => 'required|string|max:200',
            'description'      => 'nullable|string',
            'type'             => 'required|in:percentage,fixed',
            'value'            => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount'     => 'nullable|numeric|min:0',
            'usage_limit'      => 'nullable|integer|min:1',
            'usage_per_user'   => 'nullable|integer|min:1',
            'category_ids'     => 'nullable|array',
            'category_ids.*'   => 'integer|exists:egrocery_categories,id',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date|after_or_equal:starts_at',
            'is_active'        => 'nullable|boolean',
        ]);

        Coupon::create(array_merge($data, [
            'module_id'   => $this->egroceryModuleId(),
            'module_slug' => 'egrocery',
            'code'        => strtoupper($data['code']),
            'category_ids'=> !empty($data['category_ids']) ? $data['category_ids'] : null,
            'is_active'   => $r->boolean('is_active', true),
        ]));

        return back()->with('success', 'Coupon created.');
    }

    public function couponUpdate(Request $r, $id)
    {
        $coupon = Coupon::findOrFail($id);
        $data = $r->validate([
            'title'            => 'required|string|max:200',
            'description'      => 'nullable|string',
            'type'             => 'required|in:percentage,fixed',
            'value'            => 'required|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_discount'     => 'nullable|numeric|min:0',
            'usage_limit'      => 'nullable|integer|min:1',
            'usage_per_user'   => 'nullable|integer|min:1',
            'category_ids'     => 'nullable|array',
            'category_ids.*'   => 'integer|exists:egrocery_categories,id',
            'starts_at'        => 'nullable|date',
            'ends_at'          => 'nullable|date',
            'is_active'        => 'nullable|boolean',
        ]);
        $coupon->update(array_merge($data, [
            'category_ids' => !empty($data['category_ids']) ? $data['category_ids'] : null,
            'is_active'    => $r->boolean('is_active'),
        ]));
        return back()->with('success', 'Coupon updated.');
    }

    public function couponDestroy($id)
    {
        Coupon::findOrFail($id)->delete();
        return back()->with('success', 'Coupon deleted.');
    }

    public function couponToggle($id)
    {
        $c = Coupon::findOrFail($id);
        $c->update(['is_active' => !$c->is_active]);
        return response()->json(['is_active' => $c->is_active]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 10. REPORTS
    // ═══════════════════════════════════════════════════════════════

    public function reports(Request $r)
    {
        $period = $r->input('period', '30d');
        $from = match($period) {
            '7d'  => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(30),
        };

        // Sales by day
        $salesByDay = EGroceryOrder::where('status', 'delivered')
            ->where('created_at', '>=', $from)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top products (variant → product join)
        $topProducts = DB::table('egrocery_order_items as oi')
            ->join('egrocery_orders as o', 'o.id', '=', 'oi.order_id')
            ->join('egrocery_product_variants as v', 'v.id', '=', 'oi.variant_id')
            ->join('egrocery_products as p', 'p.id', '=', 'v.product_id')
            ->where('o.status', 'delivered')
            ->where('o.created_at', '>=', $from)
            ->selectRaw('p.id, p.name, SUM(oi.qty) as units, SUM(oi.line_total) as revenue, COALESCE(AVG(v.cost), 0) as avg_cost')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('revenue')
            ->limit(20)
            ->get();

        // Category share
        $categoryShare = DB::table('egrocery_order_items as oi')
            ->join('egrocery_orders as o', 'o.id', '=', 'oi.order_id')
            ->join('egrocery_product_variants as v', 'v.id', '=', 'oi.variant_id')
            ->join('egrocery_products as p', 'p.id', '=', 'v.product_id')
            ->join('egrocery_categories as cat', 'cat.id', '=', 'p.category_id')
            ->where('o.status', 'delivered')
            ->where('o.created_at', '>=', $from)
            ->selectRaw('cat.name, SUM(oi.line_total) as revenue')
            ->groupBy('cat.id', 'cat.name')
            ->orderByDesc('revenue')
            ->get();

        // Slot utilisation
        $slotUtil = DB::table('egrocery_delivery_slots as s')
            ->leftJoin('egrocery_orders as o', function ($j) use ($from) {
                $j->on('o.delivery_slot_id', '=', 's.id')
                  ->where('o.created_at', '>=', $from)
                  ->whereNotIn('o.status', ['cancelled']);
            })
            ->selectRaw('s.id, CONCAT(s.label, " (", s.start_time, "-", s.end_time, ")") as label, s.capacity, COUNT(o.id) as booked')
            ->groupBy('s.id', 's.label', 's.start_time', 's.end_time', 's.capacity')
            ->get();

        // Cancellation reasons
        $cancelReasons = EGroceryOrder::where('status', 'cancelled')
            ->where('created_at', '>=', $from)
            ->selectRaw('COALESCE(cancelled_reason, "No reason given") as reason, COUNT(*) as count')
            ->groupBy('reason')
            ->orderByDesc('count')
            ->get();

        // Summary stats
        $stats = EGroceryOrder::where('created_at', '>=', $from)
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(CASE WHEN status="delivered" THEN 1 ELSE 0 END) as delivered,
                SUM(CASE WHEN status="cancelled" THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status="delivered" THEN total ELSE 0 END) as revenue
            ')
            ->first();

        if ($r->input('export') === 'csv') {
            return $this->exportReportsCsv($salesByDay, $topProducts);
        }

        return view('admin.egrocery.reports', compact(
            'period', 'salesByDay', 'topProducts', 'categoryShare',
            'slotUtil', 'cancelReasons', 'stats'
        ));
    }

    private function exportReportsCsv($salesByDay, $topProducts)
    {
        $csv = "Date,Orders,Revenue\n";
        foreach ($salesByDay as $row) {
            $csv .= "{$row->date},{$row->orders},{$row->revenue}\n";
        }
        $csv .= "\nProduct,Units,Revenue\n";
        foreach ($topProducts as $row) {
            $csv .= "\"{$row->name}\",{$row->units},{$row->revenue}\n";
        }
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="egrocery-report-' . date('Y-m-d') . '.csv"',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // 11. RATINGS MODERATION
    // ═══════════════════════════════════════════════════════════════

    public function reviews(Request $r)
    {
        $reviews = EGroceryReview::with('user:id,name', 'product:id,name')
            ->when($r->input('filter') === 'approved', fn ($q) => $q->where('is_approved', true))
            ->when($r->input('filter') === 'pending',  fn ($q) => $q->where('is_approved', false))
            ->latest()
            ->paginate(25);
        return view('admin.egrocery.reviews', compact('reviews'));
    }

    public function reviewToggle($id)
    {
        $review = EGroceryReview::findOrFail($id);
        $review->update(['is_approved' => !$review->is_approved]);
        return response()->json(['is_approved' => $review->is_approved]);
    }
}
