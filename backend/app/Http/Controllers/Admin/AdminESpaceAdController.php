<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ESpaceAd;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AdminESpaceAdController extends Controller
{
    public function index()
    {
        $ads = ESpaceAd::orderByDesc('priority')->orderByDesc('created_at')->paginate(20);

        $stats = [
            'total'       => ESpaceAd::count(),
            'active'      => ESpaceAd::active()->count(),
            'impressions' => ESpaceAd::sum('impressions_count'),
            'clicks'      => ESpaceAd::sum('clicks_count'),
        ];
        $stats['ctr'] = $stats['impressions'] > 0
            ? round($stats['clicks'] / $stats['impressions'] * 100, 2)
            : 0;

        // Per-module stats
        $moduleStats = ESpaceAd::selectRaw('module, SUM(impressions_count) as imps, SUM(clicks_count) as clicks')
            ->groupBy('module')
            ->get()
            ->keyBy('module');

        return view('admin.espace-ads.index', compact('ads', 'stats', 'moduleStats'));
    }

    public function create()
    {
        $modules    = ESpaceAd::MODULES;
        $placements = ['feed', 'reels', 'comments', 'podcast'];
        return view('admin.espace-ads.form', compact('modules', 'placements'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'module'      => ['required', 'in:' . implode(',', array_keys(ESpaceAd::MODULES))],
            'title'       => ['required', 'max:100'],
            'subtitle'    => ['nullable', 'max:200'],
            'description' => ['nullable', 'max:1000'],
            'image'       => ['nullable', 'image', 'max:3072'],
            'cta_text'    => ['required', 'max:50'],
            'deep_link'   => ['required', 'max:100'],
            'placement'   => ['required'],
            'priority'    => ['required', 'integer', 'between:1,10'],
            'is_active'   => ['boolean'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date', 'after:starts_at'],
        ]);

        if ($request->hasFile('image')) {
            $data['image_url'] = $request->file('image')->store('espace-ads', 'public');
            $data['image_url'] = Storage::url($data['image_url']);
        }
        unset($data['image']);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['placement'] = is_array($request->placement) ? implode(',', $request->placement) : $request->placement;

        ESpaceAd::create($data);
        $this->flushCache();

        return redirect()->route('admin.espace-ads.index')
            ->with('success', 'eSpace Ad created successfully.');
    }

    public function edit(ESpaceAd $espaceAd)
    {
        $modules    = ESpaceAd::MODULES;
        $placements = ['feed', 'reels', 'comments', 'podcast'];
        $ad         = $espaceAd;
        return view('admin.espace-ads.form', compact('ad', 'modules', 'placements'));
    }

    public function update(Request $request, ESpaceAd $espaceAd)
    {
        $data = $request->validate([
            'module'      => ['required', 'in:' . implode(',', array_keys(ESpaceAd::MODULES))],
            'title'       => ['required', 'max:100'],
            'subtitle'    => ['nullable', 'max:200'],
            'description' => ['nullable', 'max:1000'],
            'image'       => ['nullable', 'image', 'max:3072'],
            'cta_text'    => ['required', 'max:50'],
            'deep_link'   => ['required', 'max:100'],
            'placement'   => ['required'],
            'priority'    => ['required', 'integer', 'between:1,10'],
            'is_active'   => ['boolean'],
            'starts_at'   => ['nullable', 'date'],
            'ends_at'     => ['nullable', 'date'],
        ]);

        if ($request->hasFile('image')) {
            $data['image_url'] = $request->file('image')->store('espace-ads', 'public');
            $data['image_url'] = Storage::url($data['image_url']);
        }
        unset($data['image']);

        $data['is_active'] = $request->boolean('is_active', true);
        $data['placement'] = is_array($request->placement) ? implode(',', $request->placement) : $request->placement;

        $espaceAd->update($data);
        $this->flushCache();

        return redirect()->route('admin.espace-ads.index')
            ->with('success', 'eSpace Ad updated successfully.');
    }

    public function destroy(ESpaceAd $espaceAd)
    {
        $espaceAd->delete();
        $this->flushCache();
        return back()->with('success', 'Ad deleted.');
    }

    public function toggle(ESpaceAd $espaceAd)
    {
        $espaceAd->update(['is_active' => !$espaceAd->is_active]);
        $this->flushCache();
        return back()->with('success', 'Status updated.');
    }

    private function flushCache(): void
    {
        foreach (['feed', 'reels', 'comments', 'podcast'] as $placement) {
            Cache::forget("espace_ads:{$placement}");
        }
    }
}
