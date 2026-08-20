<?php

namespace App\Http\Controllers\Api\EWholesale;

use App\Http\Controllers\Controller;
use App\Models\EWholesale\{EWBuyer, EWSavedList, EWSavedListItem};
use App\Services\EWholesale\{PricingService, CartValidator};
use Illuminate\Http\Request;

class EWSavedListController extends Controller
{
    public function __construct(
        private PricingService $pricing,
        private CartValidator $cartValidator,
    ) {}

    // ── GET /api/v1/ewholesale/lists ──────────────────────────────────────
    public function index(Request $r)
    {
        $buyer = $this->buyer($r);
        $lists = EWSavedList::withCount('items')->where('buyer_id', $buyer->id)->get();
        return response()->json([
            'data' => $lists->map(fn($l) => ['id'=>$l->id,'name'=>$l->name,'items_count'=>$l->items_count,'created_at'=>$l->created_at->toIso8601String()]),
        ]);
    }

    // ── POST /api/v1/ewholesale/lists ─────────────────────────────────────
    public function store(Request $r)
    {
        $r->validate(['name' => 'required|string|max:100']);
        $buyer = $this->buyer($r);
        $list  = EWSavedList::create(['buyer_id' => $buyer->id, 'name' => $r->name]);
        return response()->json(['message' => 'List created.', 'data' => ['id' => $list->id, 'name' => $list->name]], 201);
    }

    // ── GET /api/v1/ewholesale/lists/{id} ────────────────────────────────
    public function show(Request $r, int $id)
    {
        $buyer = $this->buyer($r);
        $list  = EWSavedList::with(['items.product.priceTiers','items.variant'])->findOrFail($id);
        if ($list->buyer_id !== $buyer->id) abort(403);

        return response()->json([
            'data' => [
                'id'    => $list->id,
                'name'  => $list->name,
                'items' => $list->items->map(fn($i) => [
                    'id'         => $i->id,
                    'product_id' => $i->product_id,
                    'product_name'=> $i->product?->name,
                    'variant_id' => $i->variant_id,
                    'qty'        => $i->qty,
                    'unit'       => $i->product?->unit,
                    'min_price'  => $i->product?->min_price,
                ]),
            ],
        ]);
    }

    // ── PUT /api/v1/ewholesale/lists/{id} ────────────────────────────────
    public function update(Request $r, int $id)
    {
        $r->validate(['name' => 'required|string|max:100']);
        $buyer = $this->buyer($r);
        $list  = EWSavedList::findOrFail($id);
        if ($list->buyer_id !== $buyer->id) abort(403);
        $list->update(['name' => $r->name]);
        return response()->json(['message' => 'List renamed.']);
    }

    // ── DELETE /api/v1/ewholesale/lists/{id} ─────────────────────────────
    public function destroy(Request $r, int $id)
    {
        $buyer = $this->buyer($r);
        $list  = EWSavedList::findOrFail($id);
        if ($list->buyer_id !== $buyer->id) abort(403);
        $list->delete();
        return response()->json(['message' => 'List deleted.']);
    }

    // ── POST /api/v1/ewholesale/lists/{id}/items ─────────────────────────
    public function addItem(Request $r, int $id)
    {
        $r->validate([
            'product_id' => 'required|exists:ewholesale_products,id',
            'variant_id' => 'nullable|exists:ewholesale_product_variants,id',
            'qty'        => 'required|numeric|min:1',
        ]);
        $buyer = $this->buyer($r);
        $list  = EWSavedList::findOrFail($id);
        if ($list->buyer_id !== $buyer->id) abort(403);

        EWSavedListItem::updateOrCreate(
            ['list_id'=>$list->id,'product_id'=>$r->product_id,'variant_id'=>$r->variant_id],
            ['qty' => $r->qty]
        );
        return response()->json(['message' => 'Item added to list.'], 201);
    }

    // ── DELETE /api/v1/ewholesale/lists/{listId}/items/{itemId} ──────────
    public function removeItem(Request $r, int $listId, int $itemId)
    {
        $buyer = $this->buyer($r);
        $list  = EWSavedList::findOrFail($listId);
        if ($list->buyer_id !== $buyer->id) abort(403);
        EWSavedListItem::where('id',$itemId)->where('list_id',$listId)->delete();
        return response()->json(['message' => 'Item removed.']);
    }

    // ── POST /api/v1/ewholesale/lists/{id}/to-cart ───────────────────────
    // Returns a cart validate payload (same shape as POST /cart/validate response)
    public function toCart(Request $r, int $id)
    {
        $buyer = $this->buyer($r);
        $list  = EWSavedList::with('items')->findOrFail($id);
        if ($list->buyer_id !== $buyer->id) abort(403);

        $lines = $list->items->map(fn($i) => [
            'product_id' => $i->product_id,
            'variant_id' => $i->variant_id,
            'qty'        => $i->qty,
        ])->toArray();

        $result = $this->cartValidator->validate($lines, $buyer);

        return response()->json([
            'data' => [
                'groups'   => $result['groups'],
                'errors'   => $result['errors'],
                'warnings' => $result['warnings'],
            ],
        ]);
    }

    private function buyer(Request $r): EWBuyer
    {
        $b = EWBuyer::where('user_id', $r->user()->id)->first();
        abort_unless($b, 404, 'Buyer profile not found.');
        return $b;
    }
}
