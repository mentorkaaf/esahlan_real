<?php

namespace App\Http\Controllers\Api\EWholesale;

use App\Http\Controllers\Controller;
use App\Models\EWholesale\{EWBuyer, EWCreditAccount, EWCreditLedger};
use App\Services\EWholesale\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EWBuyerController extends Controller
{
    public function __construct(private CreditService $credit) {}

    // ── POST /api/v1/ewholesale/buyer/register ────────────────────────────
    public function register(Request $r)
    {
        $r->validate([
            'business_name'  => 'required|string|max:200',
            'business_type'  => 'required|in:retailer,distributor,manufacturer,institution,other',
            'license_no'     => 'nullable|string|max:80',
            'license_doc'    => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'tax_id'         => 'nullable|string|max:80',
        ]);

        $user = $r->user();

        $existing = EWBuyer::where('user_id', $user->id)->first();
        if ($existing) {
            return response()->json(['message' => 'You already have a wholesale buyer profile.', 'data' => $this->buyerPayload($existing)], 409);
        }

        $docPath = null;
        if ($r->hasFile('license_doc')) {
            $docPath = $r->file('license_doc')->store('ewholesale/kyb', 'public');
        }

        $buyer = EWBuyer::create([
            'user_id'       => $user->id,
            'business_name' => $r->business_name,
            'business_type' => $r->business_type,
            'license_no'    => $r->license_no,
            'license_doc'   => $docPath,
            'tax_id'        => $r->tax_id,
            'kyb_status'    => 'pending',
        ]);

        return response()->json([
            'message' => 'Buyer profile submitted. KYB review in progress.',
            'data'    => $this->buyerPayload($buyer),
        ], 201);
    }

    // ── GET /api/v1/ewholesale/buyer/me ───────────────────────────────────
    public function me(Request $r)
    {
        $user  = $r->user();
        $buyer = EWBuyer::with(['creditAccount','priceListLink.priceList'])->where('user_id', $user->id)->firstOrFail();
        return response()->json(['data' => $this->buyerPayload($buyer)]);
    }

    // ─────────────────────────────────────────────────────────────────────

    private function buyerPayload(EWBuyer $buyer): array
    {
        $acct = $buyer->creditAccount;
        $available = $acct ? $this->credit->availableCredit($buyer) : null;

        // Next due date from open ledger entries
        $nextDue = null;
        if ($acct) {
            $nextDue = EWCreditLedger::where('credit_account_id', $acct->id)
                ->where('amount', '>', 0)
                ->whereNotNull('due_date')
                ->where('due_date', '>=', now())
                ->min('due_date');
        }

        return [
            'id'            => $buyer->id,
            'business_name' => $buyer->business_name,
            'business_type' => $buyer->business_type,
            'kyb_status'    => $buyer->kyb_status,
            'price_list'    => $buyer->priceListLink?->priceList?->name,
            'discount_percent' => $buyer->priceListLink?->priceList?->discount_percent ?? 0,
            'credit'        => $acct ? [
                'limit'     => $acct->credit_limit,
                'used'      => $acct->balance_used,
                'available' => $available,
                'term'      => $acct->term,
                'status'    => $acct->status,
                'next_due'  => $nextDue,
            ] : null,
        ];
    }
}
