<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingSection;
use Illuminate\Http\JsonResponse;

class LandingPageController extends Controller
{
    /**
     * GET /api/v1/landing/sections
     * Public — no auth required.
     * Returns the full section tree with enabled/disabled flags.
     */
    public function sections(): JsonResponse
    {
        return response()->json([
            'data' => LandingSection::tree(),
        ]);
    }
}
