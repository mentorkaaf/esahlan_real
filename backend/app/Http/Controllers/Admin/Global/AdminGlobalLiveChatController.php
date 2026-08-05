<?php

namespace App\Http\Controllers\Admin\Global;

use App\Http\Controllers\Controller;
use App\Models\Global\GlobalSetting;
use Illuminate\Http\Request;

class AdminGlobalLiveChatController extends Controller
{
    public function index()
    {
        $liveKitUrl    = GlobalSetting::get('global_livekit_url', '');
        $liveKitApiKey = GlobalSetting::get('global_livekit_api_key', '');
        $isConfigured  = !empty($liveKitUrl) && !empty($liveKitApiKey);

        // Generate admin token if configured
        $adminToken = null;
        if ($isConfigured) {
            // Token generation needs livekit-server-sdk-php or a JWT
            // For now we show the config panel
        }

        return view('admin.global.live-chat.index', compact('liveKitUrl','isConfigured','adminToken'));
    }

    public function joinRoom(Request $request)
    {
        $request->validate([
            'room'       => 'required|string',
            'customer_id'=> 'nullable|integer',
        ]);

        $liveKitUrl    = GlobalSetting::get('global_livekit_url', '');
        $liveKitApiKey = GlobalSetting::get('global_livekit_api_key', '');
        $liveKitSecret = GlobalSetting::get('global_livekit_secret', '');

        if (!$liveKitUrl) {
            return back()->with('error', 'LiveKit is not configured.');
        }

        // Return room info for frontend to join
        return response()->json([
            'room'  => $request->room,
            'url'   => $liveKitUrl,
            'token' => '— configure livekit-server-sdk to generate tokens —',
        ]);
    }
}
