<?php
namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\VendorSchedule;
use Illuminate\Http\Request;

class VendorStoreWebController extends Controller
{
    public function index()
    {
        $vendor    = auth()->user()->vendor->load('schedules', 'documents');
        $schedules = $vendor->schedules()->orderBy('day')->get()->keyBy('day');
        return view('vendor.store.index', compact('vendor', 'schedules'));
    }

    public function update(Request $request)
    {
        $vendor = auth()->user()->vendor;

        $data = $request->validate([
            'name'          => 'required|string|max:200',
            'description'   => 'nullable|string',
            'phone'         => 'nullable|string|max:30',
            'email'         => 'nullable|email|max:100',
            'address'       => 'nullable|string|max:300',
            'minimum_order' => 'nullable|numeric|min:0',
            'delivery_fee'  => 'nullable|numeric|min:0',
            'delivery_time' => 'nullable|string|max:50',
            'logo'          => 'nullable|image|max:2048',
            'cover_image'   => 'nullable|image|max:4096',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('vendors', 'public');
        }
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('vendors', 'public');
        }

        $vendor->update($data);
        return back()->with('success', 'Store profile updated.');
    }

    public function updateSchedule(Request $request)
    {
        $vendor = auth()->user()->vendor;

        $request->validate([
            'schedule'              => 'required|array',
            'schedule.*.is_open'    => 'boolean',
            'schedule.*.open_time'  => 'nullable|date_format:H:i',
            'schedule.*.close_time' => 'nullable|date_format:H:i',
        ]);

        foreach ($request->schedule as $day => $hours) {
            VendorSchedule::updateOrCreate(
                ['vendor_id' => $vendor->id, 'day' => $day],
                [
                    'is_open'    => isset($hours['is_open']),
                    'open_time'  => $hours['open_time'] ?? '08:00',
                    'close_time' => $hours['close_time'] ?? '22:00',
                ]
            );
        }

        return back()->with('success', 'Schedule updated.');
    }

    public function toggleOpen()
    {
        $vendor = auth()->user()->vendor;
        $vendor->update(['temporarily_closed' => !$vendor->temporarily_closed]);
        $msg = $vendor->temporarily_closed ? 'Store temporarily closed.' : 'Store is now open.';
        return back()->with('success', $msg);
    }
}
