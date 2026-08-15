<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrAuditLog;
use Illuminate\Http\Request;

class HrAuditController extends Controller
{
    public function index(Request $request)
    {
        $query = HrAuditLog::query()
            ->when($request->action, fn($q, $a) => $q->where('action', 'like', "%$a%"))
            ->when($request->actor_name, fn($q, $n) => $q->where('actor_name', 'like', "%$n%"))
            ->latest('created_at');

        $logs = $query->paginate(50)->withQueryString();

        return view('hr.audit.index', compact('logs'));
    }
}
