<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\Request;

class VendorAddonController extends Controller
{
    use HasActiveVendor;

    public function index()
    {
        $vendor = $this->activeVendor();
        $addons = Addon::where('vendor_id', $vendor->id)->orderBy('name')->get();
        return view('vendor.addons.index', compact('addons'));
    }

    public function store(Request $request)
    {
        $vendor = $this->activeVendor();
        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'price'      => 'required|numeric|min:0',
            'image_file' => 'nullable|image|max:2048',
            'is_active'  => 'nullable|boolean',
        ]);

        $addon = [
            'vendor_id' => $vendor->id,
            'name'      => $data['name'],
            'price'     => $data['price'],
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('addons', 'public');
            $addon['image'] = url('/api/v1/img/' . $path);
        }

        Addon::create($addon);
        return back()->with('success', 'Addon created.');
    }

    public function update(Request $request, Addon $addon)
    {
        $vendor = $this->activeVendor();
        abort_if($addon->vendor_id !== $vendor->id, 404);

        $data = $request->validate([
            'name'       => 'required|string|max:100',
            'price'      => 'required|numeric|min:0',
            'image_file' => 'nullable|image|max:2048',
            'is_active'  => 'nullable|boolean',
        ]);

        $update = [
            'name'      => $data['name'],
            'price'     => $data['price'],
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('addons', 'public');
            $update['image'] = url('/api/v1/img/' . $path);
        }

        $addon->update($update);
        return back()->with('success', 'Addon updated.');
    }

    public function destroy(Addon $addon)
    {
        $vendor = $this->activeVendor();
        abort_if($addon->vendor_id !== $vendor->id, 404);
        $addon->delete();
        return back()->with('success', 'Addon deleted.');
    }
}
