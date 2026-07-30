<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class AdminLandingController extends Controller
{
    private array $keys = [
        'landing_hero_badge','landing_hero_title','landing_hero_subtitle',
        'landing_hero_btn1','landing_hero_btn2',
        'landing_stat1_num','landing_stat1_label','landing_stat2_num','landing_stat2_label',
        'landing_stat3_num','landing_stat3_label','landing_stat4_num','landing_stat4_label',
        'landing_how_title','landing_how_subtitle',
        'landing_why_title','landing_why_subtitle',
        'landing_why_feat1','landing_why_feat2','landing_why_feat3',
        'landing_espace_title','landing_espace_subtitle',
        'landing_join_title','landing_join_subtitle',
        'landing_cta_title','landing_cta_subtitle',
        'landing_vendor_label','landing_vendor_sub','landing_vendor_url',
        'landing_driver_label','landing_driver_sub','landing_driver_url',
        'landing_agent_label','landing_agent_sub','landing_agent_url',
        'landing_show_vendor','landing_show_driver','landing_show_agent',
        'landing_logo_nav','landing_logo_hero','landing_logo_footer',
        'landing_hero_image',
        'landing_dl_heading','landing_dl_subtitle',
        'landing_dl_image_left','landing_dl_image_right',
        'landing_gplay_url','landing_appstore_url',
        'landing_footer_tagline',
    ];

    public function index()
    {
        $settings = [];
        foreach ($this->keys as $key) {
            $settings[$key] = Setting::where('key', $key)->value('value') ?? '';
        }
        return view('admin.landing.index', compact('settings'));
    }

    public function update(Request $request)
    {
        // Handle image file uploads
        $uploadDir = public_path('uploads/landing');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }
        $imageUploadKeys = [
            'landing_dl_image_left', 'landing_dl_image_right',
            'landing_logo_nav', 'landing_logo_hero', 'landing_logo_footer',
            'landing_hero_image',
        ];
        foreach ($imageUploadKeys as $imageKey) {
            $fileKey = $imageKey . '_file';
            if ($request->hasFile($fileKey) && $request->file($fileKey)->isValid()) {
                $file = $request->file($fileKey);
                $filename = $imageKey . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file->move($uploadDir, $filename);
                $request->merge([$imageKey => '/uploads/landing/' . $filename]);
            }
        }

        foreach ($this->keys as $key) {
            // For image keys: if URL field is empty, keep existing value (don't overwrite with blank)
            if (in_array($key, ['landing_dl_image_left', 'landing_dl_image_right', 'landing_logo_nav', 'landing_logo_hero', 'landing_logo_footer', 'landing_hero_image'])) {
                $incoming = $request->input($key, '');
                if (empty($incoming)) {
                    // No URL and no new file upload — keep existing setting untouched
                    \Cache::forget("setting_{$key}");
                    continue;
                }
                $value = $incoming;
            } else {
                $value = $request->has($key) ? $request->input($key) : '0';
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'landing', 'type' => 'string']
            );
            \Cache::forget("setting_{$key}");
        }
        return back()->with('success', 'Landing page updated successfully.');
    }
}
