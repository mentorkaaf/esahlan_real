<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use SimpleSoftwareIO\QrCode\Facades\QrCode as QrGen;

class AdminQrManagerController extends Controller
{
    public function index(Request $request)
    {
        $qrCodes = QrCode::with('creator')
            ->when($request->search, fn($q) => $q->where('title', 'like', "%{$request->search}%"))
            ->when($request->type,   fn($q) => $q->where('type', $request->type))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.qr_manager.index', [
            'qrCodes' => $qrCodes,
            'types'   => QrCode::types(),
        ]);
    }

    public function create()
    {
        return view('admin.qr_manager.form', [
            'qr'    => null,
            'types' => QrCode::types(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'type'        => 'required|in:vendor,driver,order,event,promo,custom',
            'headline'    => 'nullable|string|max:200',
            'description' => 'nullable|string|max:2000',
            'logo_url'    => 'nullable|url|max:2000',
            'cta_label'   => 'nullable|string|max:80',
            'cta_url'     => 'nullable|url|max:2000',
            'color'       => 'nullable|string|max:20',
            'fields'      => 'nullable|array',
            'fields.*.label' => 'required|string|max:80',
            'fields.*.value' => 'required|string|max:200',
            'is_active'   => 'nullable|boolean',
        ]);

        // Filter out empty field rows
        if (!empty($data['fields'])) {
            $data['fields'] = collect($data['fields'])
                ->filter(fn($f) => !empty($f['label']) && !empty($f['value']))
                ->values()
                ->toArray();
        }

        $data['created_by'] = Auth::id();
        $data['is_active']  = $request->boolean('is_active', true);
        $data['color']      = $data['color'] ?? '#FF8A00';

        $qr = QrCode::create($data);

        return redirect()->route('admin.qr-manager.show', $qr)
            ->with('success', 'QR Code created successfully!');
    }

    public function show(QrCode $qrManager)
    {
        $svg = QrGen::format('svg')
            ->size(280)
            ->margin(1)
            ->errorCorrection('H')
            ->color(
                hexdec(substr($qrManager->color, 1, 2)),
                hexdec(substr($qrManager->color, 3, 2)),
                hexdec(substr($qrManager->color, 5, 2))
            )
            ->generate($qrManager->publicUrl());

        return view('admin.qr_manager.show', [
            'qr'    => $qrManager,
            'svg'   => $svg,
            'types' => QrCode::types(),
        ]);
    }

    public function edit(QrCode $qrManager)
    {
        return view('admin.qr_manager.form', [
            'qr'    => $qrManager,
            'types' => QrCode::types(),
        ]);
    }

    public function update(Request $request, QrCode $qrManager)
    {
        $data = $request->validate([
            'title'       => 'required|string|max:200',
            'type'        => 'required|in:vendor,driver,order,event,promo,custom',
            'headline'    => 'nullable|string|max:200',
            'description' => 'nullable|string|max:2000',
            'logo_url'    => 'nullable|url|max:2000',
            'cta_label'   => 'nullable|string|max:80',
            'cta_url'     => 'nullable|url|max:2000',
            'color'       => 'nullable|string|max:20',
            'fields'      => 'nullable|array',
            'fields.*.label' => 'required|string|max:80',
            'fields.*.value' => 'required|string|max:200',
            'is_active'   => 'nullable|boolean',
        ]);

        if (!empty($data['fields'])) {
            $data['fields'] = collect($data['fields'])
                ->filter(fn($f) => !empty($f['label']) && !empty($f['value']))
                ->values()
                ->toArray();
        }

        $data['is_active'] = $request->boolean('is_active', true);
        $data['color']     = $data['color'] ?? '#FF8A00';

        $qrManager->update($data);

        return redirect()->route('admin.qr-manager.show', $qrManager)
            ->with('success', 'QR Code updated!');
    }

    public function destroy(QrCode $qrManager)
    {
        $qrManager->delete();
        return redirect()->route('admin.qr-manager.index')
            ->with('success', 'QR Code deleted.');
    }

    public function download(QrCode $qrManager)
    {
        $png = QrGen::format('png')
            ->size(600)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($qrManager->publicUrl());

        $filename = 'qr-' . $qrManager->token . '.png';

        return response($png)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }
}
