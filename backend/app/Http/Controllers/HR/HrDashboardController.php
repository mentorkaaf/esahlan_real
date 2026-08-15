<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrEmployee;
use App\Models\HR\HrDepartment;
use App\Models\HR\HrContract;
use App\Models\HR\HrAuditLog;
use Illuminate\Support\Facades\Auth;

class HrDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_employees'  => HrEmployee::where('status', 'active')->count(),
            'on_probation'     => HrEmployee::where('status', 'probation')->count(),
            'departments'      => HrDepartment::where('is_active', true)->count(),
            'expiring_contracts' => HrContract::where('status', 'active')
                ->whereNotNull('end_date')
                ->where('end_date', '<=', now()->addDays(30))
                ->count(),
        ];

        $recentAudit = HrAuditLog::latest('created_at')->limit(10)->get();

        $newHires = HrEmployee::with(['department', 'position'])
            ->orderBy('hire_date', 'desc')
            ->limit(5)
            ->get();

        return view('hr.dashboard', compact('stats', 'recentAudit', 'newHires'));
    }
}
