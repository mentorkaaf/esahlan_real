<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminSettingController extends Controller
{
    public function index()
    {
        $settings = Setting::all()->groupBy('group');

        // Check Firebase service account file status
        $firebaseFileExists = file_exists(storage_path('app/firebase-service-account.json'));

        return view('admin.settings.index', compact('settings', 'firebaseFileExists'));
    }

    public function update(Request $request)
    {
        $data = $request->except(['_token', '_method']);

        // ── Handle Firebase service account JSON specially ────────────────
        $firebaseJson = $request->input('firebase_service_account_json', '');
        if (!empty(trim($firebaseJson))) {
            // Validate it's valid JSON
            $decoded = json_decode($firebaseJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['firebase_service_account_json' => 'Invalid JSON format for Firebase service account.'])->withInput();
            }
            // Save to file for FcmService to use
            file_put_contents(
                storage_path('app/firebase-service-account.json'),
                json_encode($decoded, JSON_PRETTY_PRINT)
            );
        }

        // ── Update Google Maps API key in .env-like config cache ──────────
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::flush(); // Clear all settings cache

        return back()->with('success', 'Settings saved successfully.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate(['logo' => 'required|image|max:2048']);
        $path = $request->file('logo')->store('logos', 'public');
        Setting::updateOrCreate(['key' => 'app_logo'], ['value' => $path]);
        Cache::forget('setting_app_logo');
        return back()->with('success', 'Logo updated.');
    }

    // ── Test Firebase connection ──────────────────────────────────────────
    public function testFirebase(Request $request)
    {
        try {
            $token = $request->input('test_token');
            if (!$token) {
                return response()->json(['success' => false, 'message' => 'No FCM token provided.']);
            }

            $result = \App\Services\FcmService::sendToToken(
                $token,
                '🔥 Firebase Test',
                'eSahlan Firebase connection is working correctly!',
                ['type' => 'test'],
            );

            return response()->json([
                'success' => $result,
                'message' => $result ? 'Test notification sent successfully!' : 'Failed to send. Check service account JSON and Project ID.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
