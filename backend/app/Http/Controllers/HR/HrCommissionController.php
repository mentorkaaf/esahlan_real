<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\HrCommission;
use App\Models\HR\HrEmployee;
use App\Services\HR\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrCommissionController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->input('period', now()->format('Y-m'));

        $commissions = HrCommission::with(['employee.department', 'approver'])
            ->where('period', $period)
            ->orderByRaw("FIELD(status,'pending','approved','rejected','included')")
            ->get();

        $employees = HrEmployee::whereIn('status', ['active','probation'])->orderBy('first_name')->get();

        return view('hr.commissions.index', compact('commissions', 'period', 'employees'));
    }

    public function create()
    {
        $employees = HrEmployee::whereIn('status', ['active','probation'])->orderBy('first_name')->get();

        return view('hr.commissions.create', compact('employees'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hr_employees,id',
            'period'      => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'type'        => 'required|string|max:50',
            'description' => 'nullable|string|max:200',
            'target'      => 'required|integer|min:0',
            'achieved'    => 'required|integer|min:0',
            'rate'        => 'required|numeric|min:0',
            'note'        => 'nullable|string|max:500',
        ]);

        // Compute amount: achieved × rate (USD per unit)
        $data['amount'] = round($data['achieved'] * $data['rate'], 2);
        $data['status'] = 'pending';

        HrCommission::create($data);

        return redirect()->route('hr.commissions.index', ['period' => $data['period']])
            ->with('success', 'Commission recorded.');
    }

    public function approve(HrCommission $commission)
    {
        if ($commission->status !== 'pending') {
            return back()->with('error', 'Only pending commissions can be approved.');
        }

        $commission->update([
            'status'      => 'approved',
            'approved_by' => Auth::guard('hr')->id(),
            'approved_at' => now(),
        ]);

        AuditService::log('commission.approved', $commission);

        return back()->with('success', 'Commission approved — will be included in next payroll run.');
    }

    public function reject(Request $request, HrCommission $commission)
    {
        $request->validate(['note' => 'required|string|max:500']);

        if ($commission->status !== 'pending') {
            return back()->with('error', 'Only pending commissions can be rejected.');
        }

        $commission->update([
            'status' => 'rejected',
            'note'   => $request->note,
        ]);

        AuditService::log('commission.rejected', $commission);

        return back()->with('success', 'Commission rejected.');
    }

    public function destroy(HrCommission $commission)
    {
        if (in_array($commission->status, ['included'])) {
            return back()->with('error', 'Cannot delete a commission already included in a payroll run.');
        }

        $commission->delete();

        return back()->with('success', 'Commission deleted.');
    }
}
