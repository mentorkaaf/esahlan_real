<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SosController extends Controller
{
    /** Admin SOS dashboard */
    public function index()
    {
        $alerts = DB::table('driver_sos_alerts as s')
            ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
            ->leftJoin('users as ru', 'ru.id', '=', 's.resolved_by')
            ->select(
                's.*',
                'o.order_number',
                'ru.name as resolved_by_name'
            )
            ->orderByRaw("FIELD(s.status,'active','pending','resolved')")
            ->orderByDesc('s.created_at')
            ->limit(200)
            ->get();

        $activeCount = $alerts->whereIn('status', ['active', 'pending'])->count();

        return view('admin.sos.index', compact('alerts', 'activeCount'));
    }

    /** Single alert detail */
    public function show(int $id)
    {
        $alert = DB::table('driver_sos_alerts as s')
            ->leftJoin('orders as o', 'o.id', '=', 's.order_id')
            ->leftJoin('users as ru', 'ru.id', '=', 's.resolved_by')
            ->leftJoin('deliverymen as dm', 'dm.id', '=', 's.deliveryman_id')
            ->leftJoin('users as du', 'du.id', '=', 'dm.user_id')
            ->select('s.*', 'o.order_number', 'o.module_slug', 'ru.name as resolved_by_name')
            ->where('s.id', $id)
            ->first();

        abort_if(!$alert, 404);
        return view('admin.sos.show', compact('alert'));
    }

    /** Resolve / close an alert */
    public function resolve(Request $request, int $id)
    {
        $request->validate(['notes' => 'nullable|string|max:500']);

        DB::table('driver_sos_alerts')->where('id', $id)->update([
            'status'      => 'resolved',
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
            'admin_notes' => $request->notes,
            'updated_at'  => now(),
        ]);

        return response()->json(['success' => true]);
    }

    /** Live alerts JSON (polled every 10s by JS) */
    public function liveAlerts()
    {
        $alerts = DB::table('driver_sos_alerts')
            ->whereIn('status', ['active', 'pending'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $alerts, 'count' => $alerts->count()]);
    }
}
