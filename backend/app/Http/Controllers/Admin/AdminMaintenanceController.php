<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\RealtimeService;
use Illuminate\Http\Request;

class AdminMaintenanceController extends Controller
{
    public function toggle(Request $request)
    {
        $request->validate([
            'enabled' => 'required|boolean',
            'message' => 'nullable|string|max:500',
        ]);

        $enabled = (bool) $request->enabled;
        $message = $request->message ?? 'The app is currently under maintenance. Please try again later.';

        Setting::set('maintenance_mode', $enabled ? '1' : '0');
        Setting::set('maintenance_message', $message);

        // Broadcast to all apps in real-time
        RealtimeService::toPublic('maintenance', 'maintenance.status', [
            'enabled' => $enabled,
            'message' => $message,
        ]);

        return response()->json([
            'success' => true,
            'data'    => ['enabled' => $enabled, 'message' => $message],
        ]);
    }

    public function status()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'enabled' => Setting::get('maintenance_mode', '0') === '1',
                'message' => Setting::get('maintenance_message', 'Under maintenance. Please try again later.'),
            ],
        ]);
    }
}
