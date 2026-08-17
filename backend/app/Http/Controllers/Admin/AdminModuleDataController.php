<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LaundryItem;
use App\Models\MovingPricing;
use App\Models\MovingExtraService;
use App\Models\ParcelType;
use App\Models\DeliveryZonePricing;
use App\Models\DataProvider;
use App\Models\DataPackage;
use App\Models\ExchangeRate;
use App\Models\District;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminModuleDataController extends Controller
{
    private function storeUpload($file, string $folder = 'uploads'): string
    {
        $path = $file->store($folder, 'public');
        return url('/api/v1/img/' . $path);
    }

    // ══════════════════════════════════════════════════════════════
    // eLAUNDRY — Items
    // ══════════════════════════════════════════════════════════════

    public function laundryIndex()
    {
        $items = LaundryItem::orderBy('sort_order')->get();
        return view('admin.module-data.laundry', compact('items'));
    }

    public function laundryStore(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'normal_price'  => 'required|numeric|min:0',
            'express_price' => 'required|numeric|min:0',
            'normal_days'   => 'required|integer|min:1',
            'express_hours' => 'required|integer|min:1',
            'sort_order'    => 'nullable|integer',
            'is_active'     => 'nullable|boolean',
            'image_file'    => 'nullable|image|max:5120',
        ]);
        if ($request->hasFile('image_file')) {
            $data['image'] = $this->storeUpload($request->file('image_file'), 'laundry');
        }
        unset($data['image_file']);
        LaundryItem::create($data + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Item added.');
    }

    public function laundryUpdate(Request $request, LaundryItem $item)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'normal_price'  => 'required|numeric|min:0',
            'express_price' => 'required|numeric|min:0',
            'normal_days'   => 'required|integer|min:1',
            'express_hours' => 'required|integer|min:1',
            'sort_order'    => 'nullable|integer',
            'is_active'     => 'nullable|boolean',
            'image_file'    => 'nullable|image|max:5120',
        ]);
        if ($request->hasFile('image_file')) {
            $data['image'] = $this->storeUpload($request->file('image_file'), 'laundry');
        }
        unset($data['image_file']);
        $item->update($data + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Item updated.');
    }

    public function laundryDestroy(LaundryItem $item)
    {
        $item->delete();
        return back()->with('success', 'Item deleted.');
    }

    // eMOVING methods are defined below (enhanced version)

    // ══════════════════════════════════════════════════════════════
    // ePARCEL — Parcel Types & Zone Pricing
    // ══════════════════════════════════════════════════════════════

    public function parcelIndex()
    {
        $types     = ParcelType::orderBy('sort_order')->orderBy('name')->get();
        $zones     = DeliveryZonePricing::with(['fromDistrict', 'toDistrict'])
                       ->where('module_id', 'eparcel')
                       ->where('is_active', true)
                       ->get();
        $districts = District::where('status', 'active')->orderBy('name')->get();
        return view('admin.module-data.parcel', compact('types', 'zones', 'districts'));
    }

    public function parcelTypeStore(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
        ]);
        ParcelType::create($data + ['is_active' => true]);
        return back()->with('success', 'Parcel type added.');
    }

    public function parcelTypeUpdate(Request $request, ParcelType $type)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'is_active'   => 'nullable|boolean',
        ]);
        $type->update($data + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Parcel type updated.');
    }

    public function parcelTypeDestroy(ParcelType $type)
    {
        $type->delete();
        return back()->with('success', 'Parcel type deleted.');
    }

    // Single zone add/update
    public function parcelZoneStore(Request $request)
    {
        $data = $request->validate([
            'from_district_id' => 'required|exists:districts,id',
            'to_district_id'   => 'required|exists:districts,id',
            'base_price'       => 'required|numeric|min:0',
        ]);
        DeliveryZonePricing::updateOrCreate(
            ['module_id' => 'eparcel', 'from_district_id' => $data['from_district_id'], 'to_district_id' => $data['to_district_id']],
            ['base_price' => $data['base_price'], 'price_per_kg' => 0, 'is_active' => true]
        );
        return back()->with('success', 'Zone pricing saved.');
    }

    // Bulk zone pricing save from matrix grid
    public function parcelZoneBulk(Request $request)
    {
        $request->validate(['prices' => 'required|array']);
        $saved = 0;
        foreach ($request->prices as $key => $price) {
            if ($price === null || $price === '') continue;
            [$fromId, $toId] = explode('_', $key);
            if (!is_numeric($fromId) || !is_numeric($toId) || !is_numeric($price)) continue;
            DeliveryZonePricing::updateOrCreate(
                ['module_id' => 'eparcel', 'from_district_id' => (int)$fromId, 'to_district_id' => (int)$toId],
                ['base_price' => (float)$price, 'price_per_kg' => 0, 'is_active' => true]
            );
            $saved++;
        }
        return back()->with('success', "$saved zone prices saved successfully.");
    }

    public function parcelZoneDestroy(DeliveryZonePricing $zone)
    {
        $zone->delete();
        return back()->with('success', 'Zone deleted.');
    }

    // eDATA methods moved below (enhanced version)

    // ══════════════════════════════════════════════════════════════
    // eEXCHANGE — Exchange Rates
    // ══════════════════════════════════════════════════════════════

    public function exchangeIndex(Request $request)
    {
        $rates    = ExchangeRate::orderBy('from_wallet')->orderBy('to_wallet')->get();
        $wallets  = ['evc', 'edahab', 'jeep', 'premier', 'ebesa'];
        $cryptoOn = \App\Models\Global\GlobalSetting::getBool('crypto_exchange_enabled', true);

        // ── Orders query (same logic as AdminExchangeController) ──────────────
        $query = \Illuminate\Support\Facades\DB::table('exchange_orders')
            ->join('users', 'users.id', '=', 'exchange_orders.user_id')
            ->select('exchange_orders.*', 'users.name as user_name', 'users.phone as user_phone')
            ->orderByDesc('exchange_orders.created_at');

        if ($request->filled('from_wallet')) $query->where('from_wallet', strtoupper($request->from_wallet));
        if ($request->filled('to_wallet'))   $query->where('to_wallet',   strtoupper($request->to_wallet));
        if ($request->filled('status'))      $query->where('exchange_orders.status', $request->status);
        if ($request->filled('date_from'))   $query->whereDate('exchange_orders.created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))     $query->whereDate('exchange_orders.created_at', '<=', $request->date_to);
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(fn($q) => $q->where('reference', 'like', $s)
                ->orWhere('recipient_phone', 'like', $s)
                ->orWhere('users.name', 'like', $s)
                ->orWhere('users.phone', 'like', $s));
        }
        if ($request->filled('fraud_only')) {
            $query->where(fn($q) => $q->where('sent_amount', '>=', 500)
                ->orWhereRaw('(SELECT COUNT(*) FROM exchange_orders e2 WHERE e2.user_id = exchange_orders.user_id AND e2.created_at >= NOW() - INTERVAL 1 HOUR) >= 5'));
        }

        $orders = $query->paginate(25)->withQueryString();

        // ── Stats ─────────────────────────────────────────────────────────────
        $db = \Illuminate\Support\Facades\DB::table('exchange_orders');
        $stats = [
            'total'        => (clone $db)->count(),
            'today'        => (clone $db)->whereDate('created_at', today())->count(),
            'pending'      => (clone $db)->where('status', 'pending')->count(),
            'completed'    => (clone $db)->where('status', 'completed')->count(),
            'volume'       => (clone $db)->where('status', 'completed')->sum('sent_amount'),
            'volume_today' => (clone $db)->where('status', 'completed')->whereDate('created_at', today())->sum('sent_amount'),
            'fees'         => (clone $db)->where('status', 'completed')->sum('fee_amount'),
            'fees_today'   => (clone $db)->where('status', 'completed')->whereDate('created_at', today())->sum('fee_amount'),
            'large_orders' => (clone $db)->where('sent_amount', '>=', 500)->whereDate('created_at', today())->count(),
        ];

        // ── Chart (7 days) ────────────────────────────────────────────────────
        $chart = collect(range(6, 0))->map(fn($i) => [
            'date'  => now()->subDays($i)->toDateString(),
            'count' => \Illuminate\Support\Facades\DB::table('exchange_orders')->whereDate('created_at', now()->subDays($i)->toDateString())->count(),
        ]);

        // ── Top wallet pairs ──────────────────────────────────────────────────
        $pairVolume = \Illuminate\Support\Facades\DB::table('exchange_orders')
            ->selectRaw('from_wallet, to_wallet, COUNT(*) as cnt, SUM(sent_amount) as vol')
            ->where('status', 'completed')
            ->groupBy('from_wallet', 'to_wallet')
            ->orderByDesc('cnt')->limit(6)->get();

        return view('admin.module-data.exchange',
            compact('rates', 'wallets', 'cryptoOn', 'stats', 'orders', 'chart', 'pairVolume'));
    }

    public function exchangeStore(Request $request)
    {
        $data = $request->validate([
            'from_wallet'     => 'required|in:evc,edahab,jeep,premier,ebesa',
            'to_wallet'       => 'required|in:evc,edahab,jeep,premier,ebesa|different:from_wallet',
            'rate'            => 'required|numeric|min:0',
            'fee_percentage'  => 'nullable|numeric|min:0|max:100',
        ]);
        ExchangeRate::updateOrCreate(
            ['from_wallet' => $data['from_wallet'], 'to_wallet' => $data['to_wallet']],
            [
                'rate'            => $data['rate'],
                'fee_type'        => 'percentage',
                'fee_value'       => $data['fee_percentage'] ?? 1.0,
                'fee_percentage'  => $data['fee_percentage'] ?? 1.0,
                'is_active'       => true,
            ]
        );
        return back()->with('success', 'Exchange rate saved.');
    }

    public function exchangeDestroy(ExchangeRate $rate)
    {
        $rate->delete();
        return back()->with('success', 'Rate deleted.');
    }

    public function exchangeUploadLogo(Request $request)
    {
        $request->validate([
            'wallet'  => 'required|in:evc,edahab,jeep,premier,ebesa',
            'logo'    => 'required|image|mimes:png,jpg,jpeg,webp,svg|max:2048',
        ]);

        $wallet = $request->input('wallet');
        $path   = $request->file('logo')->store("wallet-logos", 'public');
        $url    = asset('storage/' . $path);

        \App\Models\Global\GlobalSetting::set("wallet_logo_{$wallet}", $url);

        return back()->with('success', ucfirst($wallet) . ' logo updated successfully.');
    }

    // ══════════════════════════════════════════════════════════════
    // eHEALTH — Doctors
    // ══════════════════════════════════════════════════════════════

    public function healthIndex()
    {
        $doctors = DB::table('doctors')->orderBy('name')->get();
        return view('admin.module-data.health', compact('doctors'));
    }

    public function doctorStore(Request $request)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:150',
            'specialization'     => 'required|string|max:100',
            'experience_years'   => 'nullable|integer|min:0',
            'consultation_fee'   => 'nullable|numeric|min:0',
            'avatar'             => 'nullable|max:500',
            'avatar_file'        => 'nullable|image|max:5120',
            'bio'                => 'nullable|string',
            'is_available'       => 'nullable|boolean',
        ]);
        if ($request->hasFile('avatar_file')) $data['avatar'] = $this->storeUpload($request->file('avatar_file'), 'ehealth');
        unset($data['avatar_file']);
        DB::table('doctors')->insert($data + [
            'is_available' => $request->boolean('is_available', true),
            'created_at'   => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Doctor added.');
    }

    public function doctorUpdate(Request $request, int $id)
    {
        $data = $request->validate([
            'name'               => 'required|string|max:150',
            'specialization'     => 'required|string|max:100',
            'experience_years'   => 'nullable|integer|min:0',
            'consultation_fee'   => 'nullable|numeric|min:0',
            'avatar'             => 'nullable|max:500',
            'avatar_file'        => 'nullable|image|max:5120',
            'bio'                => 'nullable|string',
            'is_available'       => 'nullable|boolean',
        ]);
        if ($request->hasFile('avatar_file')) $data['avatar'] = $this->storeUpload($request->file('avatar_file'), 'ehealth');
        unset($data['avatar_file']);
        DB::table('doctors')->where('id', $id)->update($data + [
            'is_available' => $request->boolean('is_available', true),
            'updated_at'   => now(),
        ]);
        return back()->with('success', 'Doctor updated.');
    }

    public function doctorDestroy(int $id)
    {
        DB::table('doctors')->where('id', $id)->delete();
        return back()->with('success', 'Doctor deleted.');
    }

    // eRENT methods defined below (enhanced version)

    // ══════════════════════════════════════════════════════════════
    // eSHOP / eWHOLESALE / eGROCERY — Products (by module_id)
    // ══════════════════════════════════════════════════════════════

    public function foodIndex()
    {
        return $this->productIndex('efood', 'eFood — Menu Items');
    }
    public function shopIndex()
    {
        return $this->productIndex('eshop', 'eShop Products');
    }
    public function wholesaleIndex()
    {
        return $this->productIndex('ewholesale', 'Wholesale Products');
    }
    public function groceryIndex()
    {
        return $this->productIndex('egrocery', 'Grocery Products');
    }

    private function productIndex(string $moduleId, string $title)
    {
        $products   = Product::where('module_id', $moduleId)->with('category')->orderByDesc('created_at')->paginate(25);
        $categories = Category::where('module_id', $moduleId)->orderBy('name')->get();
        return view('admin.module-data.products', compact('products', 'categories', 'moduleId', 'title'));
    }

    public function productStore(Request $request)
    {
        $data = $request->validate([
            'module_id'      => 'required|in:efood,eshop,ewholesale,egrocery',
            'name'           => 'required|string|max:200',
            'price'          => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'min_qty'        => 'nullable|integer|min:1',
            'unit'           => 'nullable|string|max:30',
            'category_id'    => 'nullable|exists:categories,id',
            'description'    => 'nullable|string',
            'image'          => 'nullable|max:500',
            'image_file'     => 'nullable|image|max:5120',
            'stock'          => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
        ]);
        if ($request->hasFile('image_file')) $data['image'] = $this->storeUpload($request->file('image_file'), 'products');
        unset($data['image_file']);
        Product::create($data + ['is_active' => $request->boolean('is_active', true), 'slug' => Str::slug($data['name']) . '-' . Str::random(6)]);
        return back()->with('success', 'Product added.');
    }

    public function productUpdate(Request $request, Product $product)
    {
        $data = $request->validate([
            'name'           => 'required|string|max:200',
            'price'          => 'required|numeric|min:0',
            'original_price' => 'nullable|numeric|min:0',
            'min_qty'        => 'nullable|integer|min:1',
            'unit'           => 'nullable|string|max:30',
            'category_id'    => 'nullable|exists:categories,id',
            'description'    => 'nullable|string',
            'image'          => 'nullable|max:500',
            'image_file'     => 'nullable|image|max:5120',
            'stock'          => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
        ]);
        if ($request->hasFile('image_file')) $data['image'] = $this->storeUpload($request->file('image_file'), 'products');
        unset($data['image_file']);
        $product->update($data + ['is_active' => $request->boolean('is_active', true)]);
        return back()->with('success', 'Product updated.');
    }

    public function productDestroy(Product $product)
    {
        $product->delete();
        return back()->with('success', 'Product deleted.');
    }

    // ══════════════════════════════════════════════════════════════
    // eTICKET — Airlines, Routes, Flights
    // ══════════════════════════════════════════════════════════════

    public function ticketIndex()
    {
        $flights  = DB::table('flights')
            ->leftJoin('airlines', 'flights.airline_id', '=', 'airlines.id')
            ->select('flights.*', 'airlines.name as airline_name', 'airlines.logo as airline_logo_img', 'airlines.color as airline_color')
            ->orderByDesc('flights.departure_at')
            ->paginate(25);
        $airlines = DB::table('airlines')->where('is_active', true)->orderBy('name')->get();
        $routes   = DB::table('flight_routes')->orderBy('from_city')->get();
        $bookings = DB::table('flight_bookings')
            ->join('orders', 'flight_bookings.order_id', '=', 'orders.id')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->join('flights', 'flight_bookings.flight_id', '=', 'flights.id')
            ->select('flight_bookings.*', 'orders.order_number', 'orders.status as order_status',
                     'orders.payment_status', 'orders.total_amount',
                     'users.name as passenger_name', 'users.phone as passenger_phone',
                     'flights.flight_number', 'flights.from_city', 'flights.to_city', 'flights.departure_at')
            ->orderByDesc('flight_bookings.id')
            ->limit(50)->get();
        return view('admin.module-data.ticket', compact('flights', 'airlines', 'routes', 'bookings'));
    }

    // Airlines
    public function airlineStore(Request $request)
    {
        $data = $request->validate(['name'=>'required|string|max:100','code'=>'nullable|string|max:10',
            'color'=>'nullable|string|max:20','sort_order'=>'nullable|integer','logo_file'=>'nullable|image|max:5120']);
        $logo = null;
        if ($request->hasFile('logo_file')) $logo = $this->storeUpload($request->file('logo_file'), 'airlines');
        DB::table('airlines')->insert(['name'=>$data['name'],'code'=>$data['code']??null,
            'logo'=>$logo,'color'=>$data['color']??'#1a73e8','is_active'=>true,
            'sort_order'=>$data['sort_order']??0,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success', 'Airline added.');
    }

    public function airlineUpdate(Request $request, int $id)
    {
        $data = $request->validate(['name'=>'required|string|max:100','code'=>'nullable|string|max:10',
            'color'=>'nullable|string|max:20','sort_order'=>'nullable|integer',
            'is_active'=>'nullable|boolean','logo_file'=>'nullable|image|max:5120']);
        $update = ['name'=>$data['name'],'code'=>$data['code']??null,'color'=>$data['color']??'#1a73e8',
            'sort_order'=>$data['sort_order']??0,'is_active'=>$request->boolean('is_active',true),'updated_at'=>now()];
        if ($request->hasFile('logo_file')) $update['logo'] = $this->storeUpload($request->file('logo_file'), 'airlines');
        DB::table('airlines')->where('id', $id)->update($update);
        return back()->with('success', 'Airline updated.');
    }

    public function airlineDestroy(int $id)
    {
        DB::table('airlines')->where('id', $id)->delete();
        return back()->with('success', 'Airline deleted.');
    }

    // Routes
    public function routeStore(Request $request)
    {
        $data = $request->validate([
            'from_city'        => 'required|string|max:100',
            'from_code'        => 'nullable|string|max:10',
            'from_country'     => 'nullable|string|max:100',
            'to_city'          => 'required|string|max:100',
            'to_code'          => 'nullable|string|max:10',
            'to_country'       => 'nullable|string|max:100',
            'route_type'       => 'nullable|in:domestic,international',
            'distance_km'      => 'nullable|numeric|min:0',
            'default_duration' => 'nullable|integer|min:1',
        ]);
        DB::table('flight_routes')->insert($data + ['is_active'=>true,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success', 'Route added.');
    }

    public function routeUpdate(Request $request, int $id)
    {
        $data = $request->validate([
            'from_city'        => 'required|string|max:100',
            'from_code'        => 'nullable|string|max:10',
            'from_country'     => 'nullable|string|max:100',
            'to_city'          => 'required|string|max:100',
            'to_code'          => 'nullable|string|max:10',
            'to_country'       => 'nullable|string|max:100',
            'route_type'       => 'nullable|in:domestic,international',
            'distance_km'      => 'nullable|numeric|min:0',
            'default_duration' => 'nullable|integer|min:1',
            'is_active'        => 'nullable|boolean',
        ]);
        DB::table('flight_routes')->where('id', $id)->update($data + ['is_active'=>$request->boolean('is_active',true),'updated_at'=>now()]);
        return back()->with('success', 'Route updated.');
    }

    public function routeDestroy(int $id)
    {
        DB::table('flight_routes')->where('id', $id)->delete();
        return back()->with('success', 'Route deleted.');
    }

    // Flights
    public function flightStore(Request $request)
    {
        $data = $request->validate([
            'airline_id'      => 'required|exists:airlines,id',
            'flight_number'   => 'required|string|max:20',
            'from_city'       => 'required|string|max:100',
            'from_code'       => 'nullable|string|max:10',
            'to_city'         => 'required|string|max:100',
            'to_code'         => 'nullable|string|max:10',
            'departure_at'    => 'required|date',
            'arrival_at'      => 'required|date|after:departure_at',
            'total_seats'     => 'required|integer|min:1',
            'available_seats' => 'required|integer|min:0',
            'aircraft_type'   => 'nullable|string|max:100',
            'economy_price'   => 'required|numeric|min:0',
            'business_price'  => 'nullable|numeric|min:0',
            'child_price'     => 'nullable|numeric|min:0',
            'infant_price'    => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string',
        ]);
        $airline = DB::table('airlines')->find($data['airline_id']);
        $seat_classes = json_encode([
            'economy'  => (float)$data['economy_price'],
            'business' => (float)($data['business_price'] ?? 0),
            'child'    => (float)($data['child_price'] ?? 0),
            'infant'   => (float)($data['infant_price'] ?? 0),
        ]);
        $dep = \Carbon\Carbon::parse($data['departure_at']);
        $arr = \Carbon\Carbon::parse($data['arrival_at']);
        DB::table('flights')->insert([
            'airline_id'      => $data['airline_id'],
            'airline'         => $airline->name,
            'flight_number'   => $data['flight_number'],
            'from_city'       => $data['from_city'],
            'from_code'       => $data['from_code'] ?? null,
            'to_city'         => $data['to_city'],
            'to_code'         => $data['to_code'] ?? null,
            'departure_at'    => $data['departure_at'],
            'arrival_at'      => $data['arrival_at'],
            'duration_minutes'=> $dep->diffInMinutes($arr),
            'total_seats'     => $data['total_seats'],
            'available_seats' => $data['available_seats'],
            'aircraft_type'   => $data['aircraft_type'] ?? null,
            'seat_classes'    => $seat_classes,
            'notes'           => $data['notes'] ?? null,
            'status'          => 'scheduled',
            'created_at'      => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Flight added.');
    }

    public function flightUpdate(Request $request, int $id)
    {
        $data = $request->validate([
            'airline_id'      => 'required|exists:airlines,id',
            'flight_number'   => 'required|string|max:20',
            'from_city'       => 'required|string|max:100',
            'from_code'       => 'nullable|string|max:10',
            'to_city'         => 'required|string|max:100',
            'to_code'         => 'nullable|string|max:10',
            'departure_at'    => 'required|date',
            'arrival_at'      => 'required|date',
            'total_seats'     => 'required|integer|min:1',
            'available_seats' => 'required|integer|min:0',
            'aircraft_type'   => 'nullable|string|max:100',
            'economy_price'   => 'required|numeric|min:0',
            'business_price'  => 'nullable|numeric|min:0',
            'child_price'     => 'nullable|numeric|min:0',
            'infant_price'    => 'nullable|numeric|min:0',
            'status'          => 'nullable|in:scheduled,boarding,departed,landed,cancelled',
            'notes'           => 'nullable|string',
        ]);
        $airline = DB::table('airlines')->find($data['airline_id']);
        $seat_classes = json_encode([
            'economy'  => (float)$data['economy_price'],
            'business' => (float)($data['business_price'] ?? 0),
            'child'    => (float)($data['child_price'] ?? 0),
            'infant'   => (float)($data['infant_price'] ?? 0),
        ]);
        $dep = \Carbon\Carbon::parse($data['departure_at']);
        $arr = \Carbon\Carbon::parse($data['arrival_at']);
        DB::table('flights')->where('id', $id)->update([
            'airline_id'      => $data['airline_id'],
            'airline'         => $airline->name,
            'flight_number'   => $data['flight_number'],
            'from_city'       => $data['from_city'],
            'from_code'       => $data['from_code'] ?? null,
            'to_city'         => $data['to_city'],
            'to_code'         => $data['to_code'] ?? null,
            'departure_at'    => $data['departure_at'],
            'arrival_at'      => $data['arrival_at'],
            'duration_minutes'=> $dep->diffInMinutes($arr),
            'total_seats'     => $data['total_seats'],
            'available_seats' => $data['available_seats'],
            'aircraft_type'   => $data['aircraft_type'] ?? null,
            'seat_classes'    => $seat_classes,
            'status'          => $data['status'] ?? 'scheduled',
            'notes'           => $data['notes'] ?? null,
            'updated_at'      => now(),
        ]);
        return back()->with('success', 'Flight updated.');
    }

    public function flightDestroy(int $id)
    {
        DB::table('flights')->where('id', $id)->delete();
        return back()->with('success', 'Flight deleted.');
    }

    // ══════════════════════════════════════════════════════════════
    // eDATA — Providers, Packages, Bundles
    // ══════════════════════════════════════════════════════════════

    public function dataIndex()
    {
        $providers = DataProvider::orderBy('sort_order')->get();
        $packages  = DataPackage::with('provider')->orderBy('provider_id')->orderBy('sort_order')->get();
        $bundles   = DB::table('data_bundles')
            ->join('data_providers', 'data_bundles.provider_id', '=', 'data_providers.id')
            ->select('data_bundles.*', 'data_providers.name as provider_name')
            ->orderBy('data_bundles.provider_id')->orderBy('data_bundles.sort_order')
            ->get();
        $userPhones = DB::table('edata_user_phones as ep')
            ->join('users as u', 'u.id', '=', 'ep.user_id')
            ->join('data_providers as dp', 'dp.id', '=', 'ep.provider_id')
            ->select('ep.*', 'u.name as user_name', 'u.email as user_email', 'u.phone as user_phone', 'dp.name as provider_name', 'dp.color as provider_color')
            ->orderByDesc('ep.updated_at')
            ->get();
        return view('admin.module-data.data', compact('providers', 'packages', 'bundles', 'userPhones'));
    }

    public function dataProviderStore(Request $request)
    {
        $data = $request->validate(['name'=>'required|string|max:100',
            'color'=>'nullable|string|max:20','sort_order'=>'nullable|integer',
            'logo_file'=>'nullable|image|max:5120']);
        $logo = null;
        if ($request->hasFile('logo_file')) $logo = $this->storeUpload($request->file('logo_file'), 'providers');
        DataProvider::create(['name'=>$data['name'],'logo'=>$logo,'color'=>$data['color']??'#1a73e8',
            'sort_order'=>$data['sort_order']??0,'is_active'=>true]);
        return back()->with('success', 'Provider added.');
    }

    public function dataProviderUpdate(Request $request, DataProvider $provider)
    {
        $data = $request->validate(['name'=>'required|string|max:100',
            'color'=>'nullable|string|max:20','sort_order'=>'nullable|integer',
            'is_active'=>'nullable|boolean','logo_file'=>'nullable|image|max:5120']);
        $update = ['name'=>$data['name'],'color'=>$data['color']??'#1a73e8',
            'sort_order'=>$data['sort_order']??0,'is_active'=>$request->boolean('is_active',true)];
        if ($request->hasFile('logo_file')) $update['logo'] = $this->storeUpload($request->file('logo_file'), 'providers');
        $provider->update($update);
        return back()->with('success', 'Provider updated.');
    }

    public function dataProviderDestroy(DataProvider $provider)
    {
        $provider->delete();
        return back()->with('success', 'Provider deleted.');
    }

    public function dataPackageStore(Request $request)
    {
        $data = $request->validate([
            'provider_id' => 'required|exists:data_providers,id',
            'name'        => 'required|string|max:100',
        ]);
        $image = null;
        if ($request->hasFile('image')) $image = $this->storeUpload($request->file('image'), 'data-packages');
        DataPackage::create([
            'provider_id'   => $data['provider_id'],
            'name'          => $data['name'],
            'image'         => $image,
            'is_active'     => true,
            'sort_order'    => 0,
            'price'         => 0,
            'validity_days' => 30,
            'data_amount'   => '',
            'category'      => 'general',
        ]);
        return back()->with('success', 'Package added.');
    }

    public function dataPackageUpdate(Request $request, DataPackage $package)
    {
        $data = $request->validate([
            'provider_id' => 'required|exists:data_providers,id',
            'name'        => 'required|string|max:100',
            'is_active'   => 'nullable|boolean',
        ]);
        $update = [
            'provider_id' => $data['provider_id'],
            'name'        => $data['name'],
            'is_active'   => $request->boolean('is_active', true),
        ];
        if ($request->hasFile('image')) $update['image'] = $this->storeUpload($request->file('image'), 'data-packages');
        $package->update($update);
        return back()->with('success', 'Package updated.');
    }

    public function dataPackageDestroy(DataPackage $package)
    {
        $package->delete();
        return back()->with('success', 'Package deleted.');
    }

    public function dataBundleStore(Request $request)
    {
        $data = $request->validate([
            'provider_id'    => 'required|exists:data_providers,id',
            'package_id'     => 'nullable|exists:data_packages,id',
            'name'           => 'required|string|max:100',
            'description'    => 'nullable|string',
            'data_amount'    => 'nullable|string|max:50',
            'voice_minutes'  => 'nullable|string|max:50',
            'sms_count'      => 'nullable|string|max:50',
            'validity_days'  => 'required|integer|min:1',
            'price'          => 'required|numeric|min:0',
            'badge_label'    => 'nullable|string|max:50',
            'sort_order'     => 'nullable|integer',
        ]);
        $image = null;
        if ($request->hasFile('image')) $image = $this->storeUpload($request->file('image'), 'data-bundles');
        DB::table('data_bundles')->insert($data + ['image'=>$image,'is_active'=>true,
            'sort_order'=>$data['sort_order']??0,'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success', 'Bundle added.');
    }

    public function dataBundleUpdate(Request $request, int $id)
    {
        $data = $request->validate([
            'provider_id'    => 'required|exists:data_providers,id',
            'package_id'     => 'nullable|exists:data_packages,id',
            'name'           => 'required|string|max:100',
            'description'    => 'nullable|string',
            'data_amount'    => 'nullable|string|max:50',
            'voice_minutes'  => 'nullable|string|max:50',
            'sms_count'      => 'nullable|string|max:50',
            'validity_days'  => 'required|integer|min:1',
            'price'          => 'required|numeric|min:0',
            'badge_label'    => 'nullable|string|max:50',
            'is_active'      => 'nullable|boolean',
            'sort_order'     => 'nullable|integer',
        ]);
        $update = $data + ['is_active'=>$request->boolean('is_active',true),'updated_at'=>now()];
        if ($request->hasFile('image')) $update['image'] = $this->storeUpload($request->file('image'), 'data-bundles');
        DB::table('data_bundles')->where('id', $id)->update($update);
        return back()->with('success', 'Bundle updated.');
    }

    public function dataBundleDestroy(int $id)
    {
        DB::table('data_bundles')->where('id', $id)->delete();
        return back()->with('success', 'Bundle deleted.');
    }

    public function dataBundleStoreBulk(Request $request)
    {
        $request->validate([
            'provider_id' => 'required|exists:data_providers,id',
            'package_id'  => 'nullable|exists:data_packages,id',
            'bundles'     => 'required|array|min:1|max:50',
            'bundles.*.name'          => 'required|string|max:100',
            'bundles.*.price'         => 'required|numeric|min:0',
            'bundles.*.validity_days' => 'required|integer|min:1',
        ]);
        $providerId = (int)$request->provider_id;
        $packageId  = $request->package_id ? (int)$request->package_id : null;
        $inserted   = 0;
        $now        = now();
        foreach ($request->input('bundles', []) as $row) {
            $dataGb = trim(($row['data_amount'] ?? '0') . ' ' . ($row['data_unit'] ?? 'GB'));
            DB::table('data_bundles')->insert([
                'provider_id'    => $providerId,
                'package_id'     => $packageId,
                'name'           => $row['name'],
                'data_gb'        => $dataGb,
                'minutes'        => (int)($row['minutes'] ?? 0),
                'sms'            => (int)($row['sms'] ?? 0),
                'price'          => (float)$row['price'],
                'validity_days'  => (int)$row['validity_days'],
                'description'    => $row['description'] ?? null,
                'is_active'      => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
            $inserted++;
        }
        return back()->with('success', "{$inserted} bundle(s) added successfully.");
    }

    public function edataPhoneEmail(int $id)
    {
        $row = DB::table('edata_user_phones as ep')
            ->join('users as u', 'u.id', '=', 'ep.user_id')
            ->join('data_providers as dp', 'dp.id', '=', 'ep.provider_id')
            ->select('ep.*', 'u.name as user_name', 'u.email as user_email', 'dp.name as provider_name')
            ->where('ep.id', $id)
            ->first();
        if (!$row || !$row->user_email) {
            return back()->with('error', 'User email not found.');
        }
        \Illuminate\Support\Facades\Mail::raw(
            "Salaan " . ($row->user_name ?? 'Customer') . ",\n\n" .
            "Xogtaada eData ee aad u keydisay {$row->provider_name}:\n" .
            "💳 Telfoonka lacagta: {$row->payment_phone}\n" .
            "📶 Telfoonka internetka: {$row->data_phone}\n\n" .
            "Haddii xogtaan khalad tahay, fadlan app-ka oo eData checkout-ka galay oo edit samee.\n\n" .
            "eSahlan Team",
            function ($msg) use ($row) {
                $msg->to($row->user_email, $row->user_name ?? null)
                    ->subject('eData Phone Numbers — eSahlan');
            }
        );
        return back()->with('success', "Email u diray {$row->user_email}");
    }

    // ══════════════════════════════════════════════════════════════
    // eRENT — Properties & Bookings
    // ══════════════════════════════════════════════════════════════

    public function rentIndex()
    {
        $properties = DB::table('properties')
            ->join('districts', 'properties.district_id', '=', 'districts.id')
            ->leftJoin('users as agent_users', 'properties.agent_user_id', '=', 'agent_users.id')
            ->select('properties.*', 'districts.name as district_name',
                     'agent_users.name as agent_name', 'agent_users.phone as agent_phone')
            ->orderByDesc('properties.id')->paginate(25);
        $propCounts = DB::table('properties')->select('district_id', DB::raw('COUNT(*) as cnt'))->groupBy('district_id')->pluck('cnt', 'district_id');
        $districts  = DB::table('districts')->where('status', 'active')->orderBy('sort_order')->get()->each(function($d) use ($propCounts) {
            $d->property_count = $propCounts[$d->id] ?? 0;
        });
        $bookings   = DB::table('property_bookings')
            ->join('properties', 'property_bookings.property_id', '=', 'properties.id')
            ->join('users', 'property_bookings.user_id', '=', 'users.id')
            ->join('orders', 'property_bookings.order_id', '=', 'orders.id')
            ->select('property_bookings.*', 'properties.title as property_title',
                     'users.name as tenant_name', 'users.phone as tenant_phone',
                     'orders.order_number', 'orders.payment_status')
            ->orderByDesc('property_bookings.id')->limit(50)->get();

        $rentAgentRoleId = DB::table('roles')->where('slug', 'rent_agent')->value('id');
        $agents = DB::table('users')
            ->leftJoin('districts', 'users.district_id', '=', 'districts.id')
            ->where('users.role_id', $rentAgentRoleId)
            ->whereNull('users.deleted_at')
            ->select('users.*', 'districts.name as district_name')
            ->orderByDesc('users.id')->get();

        $commissionPct = \App\Models\Setting::get('erent_commission_pct', 10);

        $houseRequests = DB::table('house_requests')
            ->join('users as cust', 'house_requests.customer_user_id', '=', 'cust.id')
            ->leftJoin('districts', 'house_requests.district_id', '=', 'districts.id')
            ->leftJoin('users as agnt', 'house_requests.agent_user_id', '=', 'agnt.id')
            ->select('house_requests.*',
                     'cust.name as customer_name', 'cust.phone as customer_phone',
                     'districts.name as district_name',
                     'agnt.name as agent_name', 'agnt.phone as agent_phone')
            ->orderByDesc('house_requests.id')->limit(200)->get();

        return view('admin.module-data.rent', compact('properties', 'districts', 'bookings', 'agents', 'commissionPct', 'houseRequests'));
    }

    public function agentApprove(Request $request, $id)
    {
        DB::table('users')->where('id', $id)->update([
            'status'     => 'active',
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Agent approved successfully.');
    }

    public function agentReject(Request $request, $id)
    {
        DB::table('users')->where('id', $id)->delete();
        return back()->with('success', 'Agent rejected and removed.');
    }

    public function agentToggle(Request $request, $id)
    {
        $current = DB::table('users')->where('id', $id)->value('status');
        DB::table('users')->where('id', $id)->update([
            'status'     => $current === 'active' ? 'inactive' : 'active',
            'updated_at' => now(),
        ]);
        return back()->with('success', 'Agent status updated.');
    }

    public function saveRentCommission(Request $request)
    {
        $request->validate(['commission_pct' => 'required|numeric|min:0|max:100']);
        \App\Models\Setting::set('erent_commission_pct', $request->commission_pct);
        return back()->with('success', "Commission updated to {$request->commission_pct}%");
    }

    public function propertyStore(Request $request)
    {
        $data = $request->validate([
            'district_id'   => 'required|exists:districts,id',
            'title'         => 'required|string|max:200',
            'description'   => 'required|string',
            'address'       => 'nullable|string|max:300',
            'type'          => 'required|in:apartment,house,villa,room,office,shop',
            'bedrooms'      => 'required|integer|min:0',
            'bathrooms'     => 'required|integer|min:0',
            'kitchens'      => 'nullable|integer|min:0',
            'living_rooms'  => 'nullable|integer|min:0',
            'floor'         => 'nullable|integer',
            'year_built'    => 'nullable|integer|min:1900|max:2099',
            'furnishing'    => 'nullable|in:furnished,semi,unfurnished',
            'monthly_rent'  => 'required|numeric|min:0',
            'deposit'       => 'nullable|numeric|min:0',
            'brokerage_fee' => 'nullable|numeric|min:0',
            'amenities'     => 'nullable|string',
            'images.*'      => 'nullable|image|max:5120',
            'reels.*'       => 'nullable|mimetypes:video/mp4,video/quicktime,video/webm|max:51200',
        ]);
        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $images[] = $this->storeUpload($img, 'properties');
            }
        }
        $reels = [];
        if ($request->hasFile('reels')) {
            foreach ($request->file('reels') as $video) {
                $reels[] = $this->storeUpload($video, 'property-reels');
            }
        }
        // vendor_id: use first vendor or create without vendor
        $vendorId = DB::table('vendors')->value('id') ?? 1;
        $amenities = !empty($data['amenities']) ? array_map('trim', explode(',', $data['amenities'])) : [];
        DB::table('properties')->insert([
            'vendor_id'      => $vendorId,
            'district_id'    => $data['district_id'],
            'title'          => $data['title'],
            'description'    => $data['description'],
            'address'        => $data['address'] ?? null,
            'type'           => $data['type'],
            'bedrooms'       => $data['bedrooms'],
            'bathrooms'      => $data['bathrooms'],
            'kitchens'       => $data['kitchens'] ?? 0,
            'living_rooms'   => $data['living_rooms'] ?? 0,
            'floor'          => $data['floor'] ?? null,
            'year_built'     => $data['year_built'] ?? null,
            'furnishing'     => $data['furnishing'] ?? 'unfurnished',
            'monthly_rent'   => $data['monthly_rent'],
            'deposit'        => $data['deposit'] ?? 0,
            'brokerage_fee'  => $data['brokerage_fee'] ?? 0,
            'amenities'      => json_encode($amenities),
            'images'         => json_encode($images),
            'reels'          => json_encode($reels),
            'is_available'   => true,
            'is_booked'      => false,
            'created_at'     => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Property added.');
    }

    public function propertyUpdate(Request $request, int $id)
    {
        $data = $request->validate([
            'district_id'   => 'required|exists:districts,id',
            'title'         => 'required|string|max:200',
            'description'   => 'required|string',
            'address'       => 'nullable|string|max:300',
            'type'          => 'required|in:apartment,house,villa,room,office,shop',
            'bedrooms'      => 'required|integer|min:0',
            'bathrooms'     => 'required|integer|min:0',
            'kitchens'      => 'nullable|integer|min:0',
            'living_rooms'  => 'nullable|integer|min:0',
            'floor'         => 'nullable|integer',
            'year_built'    => 'nullable|integer|min:1900|max:2099',
            'furnishing'    => 'nullable|in:furnished,semi,unfurnished',
            'monthly_rent'  => 'required|numeric|min:0',
            'deposit'       => 'nullable|numeric|min:0',
            'brokerage_fee' => 'nullable|numeric|min:0',
            'amenities'     => 'nullable|string',
            'is_available'  => 'nullable|boolean',
            'images.*'      => 'nullable|image|max:5120',
            'reels.*'       => 'nullable|mimetypes:video/mp4,video/quicktime,video/webm|max:51200',
            'remove_reels'  => 'nullable|string',
        ]);
        $property = DB::table('properties')->find($id);
        $images = $property ? (json_decode($property->images, true) ?? []) : [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $images[] = $this->storeUpload($img, 'properties');
            }
        }
        // Handle reels — keep existing, remove flagged, add new
        $reels = $property ? (json_decode($property->reels ?? '[]', true) ?? []) : [];
        $removeReels = $request->input('remove_reels') ? json_decode($request->input('remove_reels'), true) : [];
        if (!empty($removeReels)) {
            $reels = array_values(array_filter($reels, fn($r) => !in_array($r, $removeReels)));
        }
        if ($request->hasFile('reels')) {
            foreach ($request->file('reels') as $video) {
                $reels[] = $this->storeUpload($video, 'property-reels');
            }
        }
        $amenities = !empty($data['amenities']) ? array_map('trim', explode(',', $data['amenities'])) : [];
        DB::table('properties')->where('id', $id)->update([
            'district_id'    => $data['district_id'],
            'title'          => $data['title'],
            'description'    => $data['description'],
            'address'        => $data['address'] ?? null,
            'type'           => $data['type'],
            'bedrooms'       => $data['bedrooms'],
            'bathrooms'      => $data['bathrooms'],
            'kitchens'       => $data['kitchens'] ?? 0,
            'living_rooms'   => $data['living_rooms'] ?? 0,
            'floor'          => $data['floor'] ?? null,
            'year_built'     => $data['year_built'] ?? null,
            'furnishing'     => $data['furnishing'] ?? 'unfurnished',
            'monthly_rent'   => $data['monthly_rent'],
            'deposit'        => $data['deposit'] ?? 0,
            'brokerage_fee'  => $data['brokerage_fee'] ?? 0,
            'amenities'      => json_encode($amenities),
            'images'         => json_encode($images),
            'reels'          => json_encode($reels),
            'is_available'   => $request->boolean('is_available', true),
            'updated_at'     => now(),
        ]);
        return back()->with('success', 'Property updated.');
    }

    public function propertyDestroy(int $id)
    {
        DB::table('properties')->where('id', $id)->delete();
        return back()->with('success', 'Property deleted.');
    }

    public function propertyMarkAvailable(int $id)
    {
        $property = DB::table('properties')->find($id);
        if (!$property) return back()->with('error', 'Property not found.');

        DB::table('properties')->where('id', $id)->update([
            'is_available' => true,
            'is_booked'    => false,
            'updated_at'   => now(),
        ]);

        // Mark any active bookings for this property as completed (tenant moved out)
        DB::table('property_bookings')
            ->where('property_id', $id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->update(['status' => 'completed', 'updated_at' => now()]);

        return back()->with('success', 'Property "' . $property->title . '" is now available for rent again.');
    }

    public function bookingUpdate(Request $request, int $id)
    {
        $status = $request->validate(['status'=>'required|in:pending,confirmed,active,completed,cancelled,refund_requested,refunded'])['status'];
        DB::table('property_bookings')->where('id', $id)->update(['status'=>$status,'updated_at'=>now()]);
        // if confirmed, mark property as booked
        $booking = DB::table('property_bookings')->find($id);
        if (in_array($status, ['confirmed','active'])) {
            DB::table('properties')->where('id', $booking->property_id)->update(['is_booked'=>true,'is_available'=>false,'updated_at'=>now()]);
        } elseif (in_array($status, ['completed','cancelled','refunded'])) {
            DB::table('properties')->where('id', $booking->property_id)->update(['is_booked'=>false,'is_available'=>true,'updated_at'=>now()]);
        }
        return back()->with('success', 'Booking status updated.');
    }

    public function bookingApproveRefund(int $id)
    {
        $booking = DB::table('property_bookings')->find($id);
        if (!$booking) return back()->with('error', 'Booking not found.');
        DB::transaction(function () use ($booking) {
            DB::table('property_bookings')->where('id', $booking->id)->update([
                'status' => 'refunded', 'updated_at' => now(),
            ]);
            // Free the property
            DB::table('properties')->where('id', $booking->property_id)->update([
                'is_available' => true, 'is_booked' => false, 'updated_at' => now(),
            ]);
            // Update order
            DB::table('orders')->where('id', $booking->order_id)->update([
                'status' => 'cancelled', 'updated_at' => now(),
            ]);
        });
        return back()->with('success', 'Refund approved. Property is now available again.');
    }

    public function bookingDenyRefund(int $id)
    {
        $booking = DB::table('property_bookings')->find($id);
        if (!$booking) return back()->with('error', 'Booking not found.');
        DB::table('property_bookings')->where('id', $id)->update([
            'status'        => 'pending',
            'refund_reason' => null,
            'updated_at'    => now(),
        ]);
        return back()->with('success', 'Refund request denied. Booking restored to pending.');
    }

    // ── eRent Districts ────────────────────────────────────────────

    public function districtStore(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'name_so'     => 'nullable|string|max:120',
            'description' => 'nullable|string|max:500',
        ]);
        DB::table('districts')->insert(array_merge($data, [
            'slug'       => \Illuminate\Support\Str::slug($data['name']),
            'status'     => 'active',
            'sort_order' => DB::table('districts')->max('sort_order') + 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        return back()->with('success', 'District added.');
    }

    public function districtUpdate(int $id, Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'name_so'     => 'nullable|string|max:120',
            'description' => 'nullable|string|max:500',
            'status'      => 'nullable|in:active,inactive',
        ]);
        DB::table('districts')->where('id', $id)->update(array_merge($data, ['updated_at' => now()]));
        return back()->with('success', 'District updated.');
    }

    public function districtDestroy(int $id)
    {
        $count = DB::table('properties')->where('district_id', $id)->count();
        if ($count > 0) return back()->with('error', "Cannot delete: {$count} propert" . ($count === 1 ? 'y' : 'ies') . ' still in this district.');
        DB::table('districts')->where('id', $id)->delete();
        return back()->with('success', 'District deleted.');
    }

    // ══════════════════════════════════════════════════════════════
    // eMOVING — Pricing, Packages, Extra Services
    // ══════════════════════════════════════════════════════════════

    public function movingIndex()
    {
        $pricings  = MovingPricing::with(['fromDistrict', 'toDistrict'])->orderBy('move_type')->get();
        $extras    = MovingExtraService::orderBy('sort_order')->get();
        $districts = District::where('status', 'active')->orderBy('sort_order')->get();
        $packages  = DB::table('moving_packages')->orderBy('move_type')->orderBy('sort_order')->get();
        return view('admin.module-data.moving', compact('pricings', 'extras', 'districts', 'packages'));
    }

    public function movingPricingStore(Request $request)
    {
        $data = $request->validate([
            'from_district_id' => 'required|exists:districts,id',
            'to_district_id'   => 'required|exists:districts,id',
            'move_type'        => 'required|in:house,office,commercial,single_item',
            'vehicle_type'     => 'required|string|max:100',
            'base_price'       => 'required|numeric|min:0',
            'price_per_room'   => 'nullable|numeric|min:0',
            'distance_price'   => 'nullable|numeric|min:0',
            'notes'            => 'nullable|string',
        ]);
        MovingPricing::create($data + ['is_active'=>true,'price_per_room'=>$data['price_per_room']??0,'distance_price'=>$data['distance_price']??20]);
        return back()->with('success', 'Pricing added.');
    }

    public function movingPricingDestroy(MovingPricing $pricing)
    {
        $pricing->delete();
        return back()->with('success', 'Pricing deleted.');
    }

    public function movingPricingUpdate(Request $request, int $id)
    {
        $data = $request->validate([
            'base_price'    => 'required|numeric|min:0',
            'price_per_room'=> 'nullable|numeric|min:0',
            'vehicle_type'  => 'required|string|max:100',
            'is_active'     => 'nullable|boolean',
            'notes'         => 'nullable|string',
        ]);
        DB::table('moving_pricing')->where('id', $id)->update([
            'base_price'     => $data['base_price'],
            'price_per_room' => $data['price_per_room'] ?? 0,
            'distance_price' => $request->input('distance_price', 20),
            'vehicle_type'   => $data['vehicle_type'],
            'is_active'      => $request->boolean('is_active', true),
            'notes'          => $data['notes'] ?? null,
            'updated_at'     => now(),
        ]);
        return back()->with('success', 'Pricing updated.');
    }

    public function movingPackageStore(Request $request)
    {
        $data = $request->validate([
            'move_type'   => 'required|in:commercial,office',
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'includes'    => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'sort_order'  => 'nullable|integer',
        ]);
        $includes = !empty($data['includes']) ? array_map('trim', explode("\n", $data['includes'])) : [];
        DB::table('moving_packages')->insert([
            'move_type'   => $data['move_type'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'includes'    => json_encode(array_filter($includes)),
            'price'       => $data['price'],
            'is_active'   => true,
            'sort_order'  => $data['sort_order'] ?? 0,
            'created_at'  => now(), 'updated_at' => now(),
        ]);
        return back()->with('success', 'Package added.');
    }

    public function movingPackageUpdate(Request $request, int $id)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string',
            'includes'    => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'is_active'   => 'nullable|boolean',
            'sort_order'  => 'nullable|integer',
        ]);
        $includes = !empty($data['includes']) ? array_map('trim', explode("\n", $data['includes'])) : [];
        DB::table('moving_packages')->where('id', $id)->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'includes'    => json_encode(array_filter($includes)),
            'price'       => $data['price'],
            'is_active'   => $request->boolean('is_active', true),
            'sort_order'  => $data['sort_order'] ?? 0,
            'updated_at'  => now(),
        ]);
        return back()->with('success', 'Package updated.');
    }

    public function movingPackageDestroy(int $id)
    {
        DB::table('moving_packages')->where('id', $id)->delete();
        return back()->with('success', 'Package deleted.');
    }

    public function movingExtraStore(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'name_so'    => 'nullable|string|max:100',
            'price'      => 'required|numeric|min:0',
            'unit'       => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer',
        ]);
        MovingExtraService::create($data + ['is_active'=>true,'sort_order'=>$data['sort_order']??0]);
        return back()->with('success', 'Extra service added.');
    }

    public function movingExtraUpdate(Request $request, MovingExtraService $extra)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'name_so'    => 'nullable|string|max:100',
            'price'      => 'required|numeric|min:0',
            'unit'       => 'nullable|string|max:30',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
        ]);
        $extra->update($data + ['is_active'=>$request->boolean('is_active',true)]);
        return back()->with('success', 'Extra service updated.');
    }

    public function movingExtraDestroy(MovingExtraService $extra)
    {
        $extra->delete();
        return back()->with('success', 'Extra service deleted.');
    }
}
