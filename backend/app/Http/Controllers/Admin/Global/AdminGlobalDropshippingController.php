<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AdminGlobalDropshippingController extends Controller
{
    public function index(Request $request)
    {
        $query = GlobalProduct::where('type', 'dropship')->with('category');

        if ($request->filled('supplier')) {
            $query->where('supplier_name', $request->supplier);
        }
        if ($request->filled('search')) {
            $query->where(fn($q) => $q->where('name','like',"%{$request->search}%")->orWhere('supplier_product_id','like',"%{$request->search}%"));
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $products  = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        $suppliers = GlobalProduct::where('type','dropship')->distinct()->pluck('supplier_name')->filter();

        $stats = [
            'total'    => GlobalProduct::where('type','dropship')->count(),
            'active'   => GlobalProduct::where('type','dropship')->where('is_active',true)->count(),
            'pending'  => GlobalProduct::where('type','dropship')->where('stock',0)->count(),
            'suppliers'=> GlobalProduct::where('type','dropship')->distinct('supplier_name')->count('supplier_name'),
        ];

        return view('admin.global.dropshipping.index', compact('products','suppliers','stats'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'supplier'    => 'required|in:cj,aliexpress,manual',
            'product_url' => 'nullable|url',
            'product_id'  => 'nullable|string',
            // Manual fields
            'name'        => 'required_if:supplier,manual|string',
            'price'       => 'required_if:supplier,manual|numeric',
            'stock'       => 'required_if:supplier,manual|integer',
        ]);

        if ($request->supplier === 'manual') {
            GlobalProduct::create([
                'name'               => $request->name,
                'slug'               => Str::slug($request->name) . '-' . Str::random(5),
                'price'              => $request->price,
                'stock'              => $request->stock ?? 999,
                'type'               => 'dropship',
                'supplier_name'      => 'Manual',
                'supplier_product_id'=> $request->product_id,
                'is_active'          => false, // review before publishing
                'track_stock'        => false,
            ]);
            return back()->with('success', 'Product imported manually. Review before activating.');
        }

        // CJDropshipping / AliExpress integration placeholder
        return back()->with('error', "API integration for {$request->supplier} coming soon. Use manual import.");
    }

    public function sync(Request $request, GlobalProduct $product)
    {
        // Sync stock/price from supplier API
        // Placeholder — real integration depends on supplier API keys
        return back()->with('info', "Sync for \"{$product->name}\" — supplier API integration pending. Configure API keys in Settings.");
    }
}
