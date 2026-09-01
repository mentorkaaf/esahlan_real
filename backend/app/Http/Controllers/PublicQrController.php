<?php

namespace App\Http\Controllers;

use App\Models\QrCode;

class PublicQrController extends Controller
{
    public function show(string $token)
    {
        $qr = QrCode::where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        // Track scan count
        $qr->increment('scan_count');

        return view('public.qr', compact('qr'));
    }
}
