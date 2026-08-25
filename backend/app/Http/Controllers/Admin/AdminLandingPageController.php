<?php

namespace App\Http\Controllers\Admin;

use App\Events\LandingSectionsUpdated;
use App\Http\Controllers\Controller;
use App\Models\LandingSection;
use Illuminate\Http\Request;

class AdminLandingPageController extends Controller
{
    public function index()
    {
        $all      = LandingSection::orderBy('sort_order')->get();
        $sections = $all->where('parent_slug', null)->values();

        // Attach children
        $sections = $sections->map(function ($s) use ($all) {
            $s->children_list = $all->where('parent_slug', $s->slug)->values();
            return $s;
        });

        return view('admin.landing.index', compact('sections'));
    }

    public function toggle(Request $request, string $slug)
    {
        $section = LandingSection::where('slug', $slug)->firstOrFail();
        $section->update(['is_enabled' => ! $section->is_enabled]);

        LandingSection::flushCache();

        // Broadcast realtime update to landing page
        broadcast(new LandingSectionsUpdated(LandingSection::tree()));

        if ($request->expectsJson()) {
            return response()->json(['is_enabled' => $section->is_enabled]);
        }

        return back()->with('success', "'{$section->label}' " . ($section->is_enabled ? 'enabled' : 'disabled'));
    }

    public function reorder(Request $request)
    {
        $request->validate(['order' => 'required|array', 'order.*' => 'string']);

        foreach ($request->order as $i => $slug) {
            LandingSection::where('slug', $slug)->update(['sort_order' => $i + 1]);
        }

        LandingSection::flushCache();
        broadcast(new LandingSectionsUpdated(LandingSection::tree()));

        return response()->json(['ok' => true]);
    }
}
