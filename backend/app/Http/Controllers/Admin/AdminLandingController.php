<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class AdminLandingController extends Controller
{
    private array $keys = [
        'landing_hero_badge', 'landing_hero_title', 'landing_hero_subtitle',
        'landing_hero_btn1', 'landing_hero_btn2',
        'landing_stat1_num', 'landing_stat1_label',
        'landing_stat2_num', 'landing_stat2_label',
        'landing_stat3_num', 'landing_stat3_label',
        'landing_stat4_num', 'landing_stat4_label',
        'landing_cta_title', 'landing_cta_subtitle',
        'landing_vendor_label', 'landing_vendor_sub', 'landing_vendor_url',
        'landing_driver_label', 'landing_driver_sub', 'landing_driver_url',
        'landing_agent_label',  'landing_agent_sub',  'landing_agent_url',
        'landing_show_vendor',  'landing_show_driver', 'landing_show_agent',
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
        foreach ($this->keys as $key) {
            $value = $request->has($key) ? $request->input($key) : '0';
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'landing', 'type' => 'string']
            );
            \Cache::forget("setting_{$key}");
        }

        return back()->with('success', 'Landing page updated successfully.');
    }
}
