<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminBannerController extends Controller
{
    public function index()
    {
        $banners = Banner::with('module')->orderBy('sort_order')->paginate(20);
        $modules = \App\Models\Module::where('is_active', true)->orderBy('sort_order')->get(['id','name','slug']);
        return view('admin.banners.index', compact('banners', 'modules'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'      => 'nullable|string|max:200',
            'module_id'  => 'nullable|exists:modules,id',
            'vendor_id'  => 'nullable|exists:vendors,id',
            'image'      => 'required|image|max:2048',
            'link_type'  => 'required|in:module,vendor,url,none',
            'link_value' => 'nullable|string',
            'position'   => 'required|in:home_top,home_middle,module_top,popup',
            'sort_order' => 'integer',
            'is_active'  => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        }

        // DB column is NOT NULL — use a default if admin left title blank
        if (empty($data['title'])) {
            $data['title'] = 'Banner';
        }

        Banner::create($data);
        $this->clearBannerCaches();
        return back()->with('success', 'Banner created.');
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();
        $this->clearBannerCaches();
        return back()->with('success', 'Banner deleted.');
    }

    public function toggleStatus(Banner $banner)
    {
        $banner->update(['is_active' => !$banner->is_active]);
        $this->clearBannerCaches();
        return back()->with('success', 'Banner status updated.');
    }

    private function clearBannerCaches(): void
    {
        // Legacy keys
        Cache::forget('banners.active');
        Cache::forget('banners.active.v2');
        Cache::forget('banners.home.v3');
        // Public endpoint cache (all known position keys)
        Cache::forget('banners.public.home');
        foreach (['home_top', 'home_middle', 'module_top', 'popup'] as $pos) {
            Cache::forget("banners.public.pos.{$pos}");
        }
    }

    public function reorder(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'exists:banners,id']);
        foreach ($request->order as $index => $id) {
            Banner::where('id', $id)->update(['sort_order' => $index + 1]);
        }
        return response()->json(['success' => true]);
    }
}
