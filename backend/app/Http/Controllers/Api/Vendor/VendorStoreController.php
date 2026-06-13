<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\VendorResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorStoreController extends Controller
{
    public function profile(): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        $vendor->load(['module', 'district', 'schedules', 'documents']);
        return $this->success(new VendorResource($vendor));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        $data = $request->validate([
            'name'                     => 'sometimes|string|max:200',
            'description'              => 'nullable|string',
            'phone'                    => 'sometimes|string|max:20',
            'email'                    => 'sometimes|email',
            'address'                  => 'nullable|string',
            'min_order_amount'         => 'nullable|numeric|min:0',
            'delivery_fee'             => 'nullable|numeric|min:0',
            'estimated_delivery_time'  => 'nullable|integer|min:0',
            'logo'                     => 'nullable|image|max:2048',
            'cover_image'              => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('vendors/logos', 'public');
        }
        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('vendors/covers', 'public');
        }

        $vendor->update($data);
        return $this->success(new VendorResource($vendor->fresh()), 'Profile updated.');
    }

    public function updateSchedule(Request $request): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        $request->validate([
            'schedules'             => 'required|array',
            'schedules.*.day'       => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'schedules.*.is_open'   => 'required|boolean',
            'schedules.*.open_time' => 'nullable|date_format:H:i',
            'schedules.*.close_time'=> 'nullable|date_format:H:i',
        ]);

        foreach ($request->schedules as $schedule) {
            $vendor->schedules()->updateOrCreate(
                ['day' => $schedule['day']],
                [
                    'is_open'    => $schedule['is_open'],
                    'open_time'  => $schedule['open_time'] ?? '00:00',
                    'close_time' => $schedule['close_time'] ?? '23:59',
                ]
            );
        }

        return $this->success($vendor->schedules, 'Schedule updated.');
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $vendor = auth()->user()->vendor;
        $request->validate([
            'type' => 'required|in:license,registration,id_front,id_back,other',
            'file' => 'required|file|max:5120|mimes:jpg,jpeg,png,pdf',
        ]);

        $path = $request->file('file')->store('vendors/documents', 'public');
        $doc = $vendor->documents()->create([
            'type'      => $request->type,
            'file_path' => $path,
            'status'    => 'pending',
        ]);

        return $this->success($doc, 'Document uploaded for review.', 201);
    }
}
