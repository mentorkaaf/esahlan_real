<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\Order;
use App\Models\Deliveryman;
use Illuminate\Http\Request;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AdminQrController extends Controller
{
    /**
     * Show QR code page (modal-friendly: returns SVG string for inline display)
     * GET /admin/qr/{type}/{id}
     */
    public function show(string $type, int $id)
    {
        [$label, $data, $meta] = match ($type) {
            'vendor'   => $this->vendorQr($id),
            'driver'   => $this->driverQr($id),
            'order'    => $this->orderQr($id),
            default    => abort(404),
        };

        $svg = QrCode::format('svg')
            ->size(300)
            ->margin(1)
            ->errorCorrection('H')
            ->generate($data);

        return view('admin.qr.show', compact('type', 'label', 'data', 'meta', 'svg'));
    }

    /**
     * Download QR as PNG
     * GET /admin/qr/{type}/{id}/download
     */
    public function download(string $type, int $id)
    {
        [$label, $data] = match ($type) {
            'vendor'   => $this->vendorQr($id),
            'driver'   => $this->driverQr($id),
            'order'    => $this->orderQr($id),
            default    => abort(404),
        };

        $png = QrCode::format('png')
            ->size(400)
            ->margin(2)
            ->errorCorrection('H')
            ->generate($data);

        $filename = $type . '-' . $id . '-qr.png';

        return response($png)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function vendorQr(int $id): array
    {
        $vendor = Vendor::with('module')->findOrFail($id);
        // Admin URL — when scanned opens vendor detail page
        $url    = url("/admin/vendors/{$id}");
        $label  = '🏪 ' . $vendor->name;
        $meta   = [
            'Module'  => $vendor->module?->name ?? '—',
            'Phone'   => $vendor->phone ?? '—',
            'Status'  => $vendor->is_active ? 'Active' : 'Inactive',
        ];
        return [$label, $url, $meta];
    }

    private function driverQr(int $id): array
    {
        $driver = Deliveryman::findOrFail($id);
        // Admin URL — when scanned opens driver detail page
        $url   = url("/admin/deliverymen/{$id}");
        $label = '🚗 ' . ($driver->name ?? 'Driver #' . $id);
        $meta  = [
            'Phone'  => $driver->phone ?? '—',
            'Status' => $driver->is_active ? 'Active' : 'Inactive',
        ];
        return [$label, $url, $meta];
    }

    private function orderQr(int $id): array
    {
        $order = Order::findOrFail($id);
        // Admin URL — driver scans to open order detail page
        $url   = url("/admin/orders/{$id}");
        $label = '📦 Order #' . $order->id;
        $meta  = [
            'Status'  => ucfirst($order->status ?? '—'),
            'Amount'  => '$' . number_format($order->total_price ?? 0, 2),
        ];
        return [$label, $url, $meta];
    }
}
