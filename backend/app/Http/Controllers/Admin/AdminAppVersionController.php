<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use Illuminate\Http\Request;

class AdminAppVersionController extends Controller
{
    public function index()
    {
        $versions = AppVersion::orderByRaw("FIELD(app_type, 'customer', 'driver', 'vendor')")->get()->keyBy('app_type');

        // Ensure all 3 exist
        foreach (['customer', 'driver', 'vendor'] as $type) {
            if (!isset($versions[$type])) {
                $versions[$type] = AppVersion::create([
                    'app_type'       => $type,
                    'min_version'    => '1.0.0',
                    'latest_version' => '1.0.0',
                    'force_update'   => false,
                    'update_message' => 'A new version is available with improvements and bug fixes.',
                ]);
            }
        }

        return view('admin.app-versions.index', compact('versions'));
    }

    public function update(Request $request, string $appType)
    {
        if (!in_array($appType, ['customer', 'driver', 'vendor'])) {
            abort(404);
        }

        $data = $request->validate([
            'min_version'    => ['required', 'regex:/^\d+\.\d+\.\d+$/'],
            'latest_version' => ['required', 'regex:/^\d+\.\d+\.\d+$/'],
            'force_update'   => ['sometimes', 'boolean'],
            'update_message' => ['nullable', 'string', 'max:500'],
            'android_url'    => ['nullable', 'url', 'max:500'],
            'ios_url'        => ['nullable', 'url', 'max:500'],
        ]);

        $data['force_update'] = $request->boolean('force_update');

        AppVersion::updateOrCreate(
            ['app_type' => $appType],
            $data
        );

        return back()->with('success', ucfirst($appType) . ' app version settings saved.');
    }
}
