<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\District;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminAdController extends Controller
{
    public function index()
    {
        $ads       = Ad::with('district')->orderBy('sort_order')->orderByDesc('created_at')->get();
        $modules   = Module::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'slug']);
        $districts = District::orderBy('name')->get(['id', 'name']);

        return view('admin.ads.index', compact('ads', 'modules', 'districts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'                  => 'required|string|max:255',
            'description'            => 'nullable|string|max:2000',
            'image'                  => 'nullable|image|max:4096',
            'image_url'              => 'nullable|string|max:500',
            'video_url'              => 'nullable|string|max:500',
            'ad_type'                => 'required|in:popup_fullscreen,popup_modal,banner_slider,banner_inline,card',
            'target_module'          => 'nullable|string|max:50',
            'target_district_id'     => 'nullable|exists:districts,id',
            'status'                 => 'required|in:active,inactive,scheduled',
            'start_date'             => 'nullable|date',
            'end_date'               => 'nullable|date|after_or_equal:start_date',
            'action_type'            => 'required|in:module,product,vendor,house,url,none',
            'action_value'           => 'nullable|string|max:500',
            'button_text'            => 'nullable|string|max:100',
            'button_color'           => 'nullable|string|max:20',
            'display_frequency'      => 'integer|min:1|max:100',
            'display_delay_seconds'  => 'integer|min:0|max:300',
            'show_on_app_open'       => 'boolean',
            'show_after_login'       => 'boolean',
            'allow_dont_show_today'  => 'boolean',
            'sort_order'             => 'integer|min:0',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('ads', 'public');
        }

        $data['button_text']  = $data['button_text']  ?: 'Learn More';
        $data['button_color'] = $data['button_color'] ?: '#FF8A00';
        $data['show_on_app_open']      = $request->boolean('show_on_app_open', true);
        $data['show_after_login']      = $request->boolean('show_after_login', false);
        $data['allow_dont_show_today'] = $request->boolean('allow_dont_show_today', true);

        Ad::create($data);
        $this->clearCaches();

        return back()->with('success', 'Ad created successfully.');
    }

    public function update(Request $request, Ad $ad)
    {
        $data = $request->validate([
            'title'                  => 'required|string|max:255',
            'description'            => 'nullable|string|max:2000',
            'image'                  => 'nullable|image|max:4096',
            'image_url'              => 'nullable|string|max:500',
            'video_url'              => 'nullable|string|max:500',
            'ad_type'                => 'required|in:popup_fullscreen,popup_modal,banner_slider,banner_inline,card',
            'target_module'          => 'nullable|string|max:50',
            'target_district_id'     => 'nullable|exists:districts,id',
            'status'                 => 'required|in:active,inactive,scheduled',
            'start_date'             => 'nullable|date',
            'end_date'               => 'nullable|date',
            'action_type'            => 'required|in:module,product,vendor,house,url,none',
            'action_value'           => 'nullable|string|max:500',
            'button_text'            => 'nullable|string|max:100',
            'button_color'           => 'nullable|string|max:20',
            'display_frequency'      => 'integer|min:1|max:100',
            'display_delay_seconds'  => 'integer|min:0|max:300',
            'show_on_app_open'       => 'boolean',
            'show_after_login'       => 'boolean',
            'allow_dont_show_today'  => 'boolean',
            'sort_order'             => 'integer|min:0',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('ads', 'public');
        }

        $data['show_on_app_open']      = $request->boolean('show_on_app_open', true);
        $data['show_after_login']      = $request->boolean('show_after_login', false);
        $data['allow_dont_show_today'] = $request->boolean('allow_dont_show_today', true);

        $ad->update($data);
        $this->clearCaches();

        return back()->with('success', 'Ad updated successfully.');
    }

    public function destroy(Ad $ad)
    {
        if ($ad->image) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($ad->image);
        }
        $ad->delete();
        $this->clearCaches();

        return back()->with('success', 'Ad deleted.');
    }

    public function toggleStatus(Ad $ad)
    {
        $newStatus = ($ad->status === 'active') ? 'inactive' : 'active';
        $ad->update(['status' => $newStatus]);
        $this->clearCaches();

        return back()->with('success', 'Ad status updated.');
    }

    private function clearCaches(): void
    {
        $modules = ['all', 'efood', 'eshop', 'eticket', 'ehealth', 'edata',
                    'eparcel', 'erent', 'emoving', 'ewholesale', 'egrocery',
                    'eexchange', 'elaundry', 'elearning'];
        $types   = ['popup', 'banner', 'card', ''];

        foreach ($types as $t) {
            foreach ($modules as $m) {
                Cache::forget("ads.{$t}.{$m}");
            }
            Cache::forget("ads.{$t}.");
        }
    }
}
