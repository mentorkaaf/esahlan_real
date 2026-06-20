<?php

namespace App\Http\Controllers\Vendor;

use App\Models\Vendor;

trait HasActiveVendor
{
    protected function activeVendor(): ?Vendor
    {
        $user = auth()->user();
        $activeId = session('active_vendor_id');
        if ($activeId) {
            $vendor = Vendor::where('id', $activeId)->where('user_id', $user->id)->first();
            if ($vendor) return $vendor;
        }
        return $user->vendor;
    }
}
