<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminGlobalInventoryController extends Controller
{
    public function index(Request $request)
    {
        $query = GlobalProduct::where('track_stock', true);

        if ($request->filled('filter')) {
            match($request->filter) {
                'low'     => $query->where('stock', '>', 0)->where('stock', '<=', 10),
                'out'     => $query->where('stock', 0),
                'ok'      => $query->where('stock', '>', 10),
                default   => null,
            };
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name','like',"%{$request->search}%")->orWhere('sku','like',"%{$request->search}%");
            });
        }

        $products = $query->with('category')->orderBy('stock')->paginate(30)->withQueryString();

        $stats = [
            'total'     => GlobalProduct::where('track_stock', true)->count(),
            'low'       => GlobalProduct::where('track_stock', true)->where('stock', '>', 0)->where('stock', '<=', 10)->count(),
            'out'       => GlobalProduct::where('track_stock', true)->where('stock', 0)->count(),
            'ok'        => GlobalProduct::where('track_stock', true)->where('stock', '>', 10)->count(),
            'total_val' => GlobalProduct::where('track_stock', true)->selectRaw('SUM(stock * cost_price) as val')->value('val') ?? 0,
        ];

        return view('admin.global.inventory.index', compact('products', 'stats'));
    }

    public function adjust(Request $request, GlobalProduct $product)
    {
        $request->validate([
            'adjustment' => 'required|integer',
            'reason'     => 'nullable|string|max:255',
        ]);

        $newStock = max(0, $product->stock + $request->adjustment);
        $product->update(['stock' => $newStock]);

        return back()->with('success', "Stock adjusted to {$newStock} for \"{$product->name}\".");
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate(['updates' => 'required|array']);

        foreach ($request->updates as $id => $stock) {
            GlobalProduct::where('id', $id)->update(['stock' => max(0, (int)$stock)]);
        }

        return back()->with('success', count($request->updates) . ' products updated.');
    }
}
