<?php
namespace App\Http\Controllers\Admin\Global;
use App\Http\Controllers\Controller;
use App\Models\Global\GlobalSlider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminGlobalSlidersController extends Controller
{
    public function index() {
        $sliders = GlobalSlider::orderBy('sort_order')->get();
        return view('admin.global.sliders.index', compact('sliders'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'title'       => 'required|string|max:100',
            'subtitle'    => 'nullable|string|max:200',
            'image_url'   => 'nullable|url',
            'image_file'  => 'nullable|image|max:2048',
            'link_url'    => 'nullable|url',
            'bg_color'    => 'nullable|string|max:20',
            'button_text' => 'nullable|string|max:50',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'nullable|boolean',
        ]);
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('global/sliders', 'public');
            $data['image_url'] = Storage::url($path);
        }
        unset($data['image_file']);
        $data['is_active'] = $request->boolean('is_active', true);
        GlobalSlider::create($data);
        return back()->with('success', 'Slider created.');
    }

    public function update(Request $request, GlobalSlider $slider) {
        $data = $request->validate([
            'title'       => 'required|string|max:100',
            'subtitle'    => 'nullable|string|max:200',
            'image_url'   => 'nullable|url',
            'image_file'  => 'nullable|image|max:2048',
            'link_url'    => 'nullable|url',
            'bg_color'    => 'nullable|string|max:20',
            'button_text' => 'nullable|string|max:50',
            'sort_order'  => 'nullable|integer',
            'is_active'   => 'nullable|boolean',
        ]);
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('global/sliders', 'public');
            $data['image_url'] = Storage::url($path);
        }
        unset($data['image_file']);
        $data['is_active'] = $request->boolean('is_active');
        $slider->update($data);
        return back()->with('success', 'Slider updated.');
    }

    public function destroy(GlobalSlider $slider) {
        $slider->delete();
        return back()->with('success', 'Slider deleted.');
    }

    public function reorder(Request $request) {
        foreach ($request->order ?? [] as $i => $id) {
            GlobalSlider::where('id', $id)->update(['sort_order' => $i]);
        }
        return response()->json(['ok' => true]);
    }
}
