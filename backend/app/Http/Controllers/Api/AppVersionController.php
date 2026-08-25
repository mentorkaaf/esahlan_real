<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    /**
     * GET /api/v1/app/version-check?app=customer&version=1.0.0
     *
     * Public endpoint — no auth required.
     * Returns whether the given app version needs a force update.
     */
    public function check(Request $request)
    {
        $appType = $request->query('app');
        $currentVersion = $request->query('version', '1.0.0');

        if (!in_array($appType, ['customer', 'driver', 'vendor'])) {
            return response()->json(['success' => false, 'message' => 'Invalid app type'], 400);
        }

        $row = AppVersion::where('app_type', $appType)->first();

        if (!$row) {
            return response()->json(['success' => true, 'data' => ['force_update' => false]]);
        }

        $needsUpdate = $row->force_update
            && AppVersion::needsUpdate($currentVersion, $row->min_version);

        return response()->json([
            'success' => true,
            'data' => [
                'force_update'    => $needsUpdate,
                'min_version'     => $row->min_version,
                'latest_version'  => $row->latest_version,
                'update_message'  => $row->update_message ?? 'A new version is available. Please update to continue.',
                'android_url'     => $row->android_url,
                'ios_url'         => $row->ios_url,
            ],
        ]);
    }
}
