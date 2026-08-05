<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalProduct;
use App\Models\Global\GlobalOrder;
use Illuminate\Http\Request;

class AdminGlobalPhysicalProductsController extends Controller
{
    public function index(Request $request)
    {
        $query = GlobalProduct::where('type', 'physical')->with('category');

        if ($request->filled('search')) {
            $query->where(fn($q) => $q->where('name','like',"%{$request->search}%")->orWhere('sku','like',"%{$request->search}%"));
        }
        if ($request->filled('stock_status')) {
            match($request->stock_status) {
                'out'  => $query->where('stock', 0),
                'low'  => $query->where('stock', '>', 0)->where('stock', '<=', 10),
                'ok'   => $query->where('stock', '>', 10),
                default => null,
            };
        }
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $products = $query->orderBy('stock')->paginate(25)->withQueryString();

        $stats = [
            'total'      => GlobalProduct::where('type','physical')->count(),
            'active'     => GlobalProduct::where('type','physical')->where('is_active',true)->count(),
            'out_stock'  => GlobalProduct::where('type','physical')->where('stock', 0)->count(),
            'low_stock'  => GlobalProduct::where('type','physical')->where('stock','>',0)->where('stock','<=',10)->count(),
            'total_units'=> GlobalProduct::where('type','physical')->sum('stock'),
            'inventory_value' => GlobalProduct::where('type','physical')->selectRaw('SUM(stock * cost_price) as v')->value('v') ?? 0,
        ];

        return view('admin.global.physical-products.index', compact('products', 'stats'));
    }

    public function restock(Request $request, GlobalProduct $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
            'note'     => 'nullable|string',
        ]);

        $product->increment('stock', $request->quantity);

        return back()->with('success', "Restocked {$request->quantity} units for \"{$product->name}\". New stock: {$product->fresh()->stock}");
    }
}
