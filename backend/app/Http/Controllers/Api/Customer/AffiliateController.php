<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Services\AffiliateService;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    // GET /affiliate — dashboard
    public function dashboard(Request $request)
    {
        $data = AffiliateService::dashboard($request->user()->id);
        return response()->json(['success' => true, 'data' => $data]);
    }

    // POST /affiliate/apply — become an affiliate
    public function apply(Request $request)
    {
        try {
            $affiliate = AffiliateService::apply($request->user()->id);
            return response()->json([
                'success' => true,
                'message' => $affiliate->status === 'active'
                    ? 'Welcome! Your affiliate account is now active.'
                    : 'Application submitted! We will review it shortly.',
                'data'    => $affiliate,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Failed to apply: ' . $e->getMessage()], 422);
        }
    }

    // POST /affiliate/payout — request payout
    public function requestPayout(Request $request)
    {
        $request->validate(['points' => 'required|integer|min:1']);

        $affiliate = \Illuminate\Support\Facades\DB::table('affiliates')
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$affiliate) {
            return response()->json(['success' => false, 'message' => 'No affiliate account found.'], 404);
        }

        $result = AffiliateService::requestPayout($affiliate->id, (int) $request->points);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data'    => ['dollar_value' => $result['dollar_value'] ?? null],
        ], $result['success'] ? 200 : 422);
    }
}
