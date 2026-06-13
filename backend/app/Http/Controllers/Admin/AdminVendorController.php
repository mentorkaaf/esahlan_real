<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminVendorController extends Controller
{
    public function index(Request $request)
    {
        $query = Vendor::with(['module', 'district', 'user'])
            ->when($request->module_id, fn($q) => $q->where('module_id', $request->module_id))
            ->when($request->approved, fn($q) => $q->where('is_approved', $request->approved === '1'))
            ->when($request->search, fn($q) => $q->where(function ($s) use ($request) {
                $s->where('name', 'like', "%{$request->search}%")
                  ->orWhere('phone', 'like', "%{$request->search}%");
            }))
            ->latest();

        $vendors = $query->paginate(20);
        $modules = Module::where('is_active', true)->get();

        return view('admin.vendors.index', compact('vendors', 'modules'));
    }

    public function show(Vendor $vendor)
    {
        $vendor->load(['module', 'district', 'user', 'schedules',
            'orders' => fn($q) => $q->latest()->limit(10)]);
        return view('admin.vendors.show', compact('vendor'));
    }

    public function approve(Vendor $vendor)
    {
        $vendor->update(['is_approved' => true, 'is_active' => true, 'status' => 'active']);
        return back()->with('success', 'Vendor approved successfully.');
    }

    public function reject(Request $request, Vendor $vendor)
    {
        $vendor->update(['is_approved' => false, 'is_active' => false, 'status' => 'suspended']);
        return back()->with('success', 'Vendor rejected.');
    }

    public function toggleFeatured(Vendor $vendor)
    {
        $vendor->update(['is_featured' => !$vendor->is_featured]);
        return back()->with('success', 'Featured status updated.');
    }

    public function destroy(Vendor $vendor)
    {
        $vendor->delete();
        return redirect()->route('admin.vendors.index')->with('success', 'Vendor deleted.');
    }
}
